<?php

namespace App\Services\Search;

use App\DTOs\SuggestQueryDTO;
use App\DTOs\SuggestResultDTO;
use App\Models\SearchAnalytic;
use App\Repositories\ElasticsearchRepository;
use App\Services\Admin\QueryRuleService;
use App\Services\Search\Concerns\ResolvesRuleSkus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SuggestService
{
    use ResolvesRuleSkus;

    public function __construct(
        private readonly ElasticsearchRepository $repository,
        private readonly SuggestQueryBuilder $queryBuilder,
        private readonly QueryRuleService $queryRuleService,
        private readonly CategoryService $categoryService,
    ) {}

    public function suggest(SuggestQueryDTO $dto): SuggestResultDTO
    {
        if (strlen($dto->query) < 1) {
            return new SuggestResultDTO();
        }

        $version = Cache::get('search_index_version', 0);
        $cacheKey = "suggest:{$dto->storeId}:" . md5($dto->query . $dto->limit . $version);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $startTime = microtime(true);

        try {
            $result = $this->executeSuggest($dto);
        } catch (\Throwable $e) {
            Log::error('Suggest failed', ['error' => $e->getMessage(), 'query' => $dto->query]);
            return new SuggestResultDTO();
        }

        $tookMs = (int) ((microtime(true) - $startTime) * 1000);

        $result = new SuggestResultDTO(
            queries: $result['queries'],
            brands: $result['brands'],
            categories: $result['categories'],
            products: $result['products'],
            tookMs: $tookMs,
        );

        Cache::put($cacheKey, $result, 30);

        $this->trackAnalytics($dto);

        return $result;
    }

    private function executeSuggest(SuggestQueryDTO $dto): array
    {
        $index = config('elasticsearch.indices.products');

        $productBody = $this->queryBuilder->buildProductSearch($dto);
        $productBody = $this->applyQueryRules($productBody, $dto);
        $productResponse = $this->repository->rawSearch($index, $productBody);

        $aggBody = $this->queryBuilder->build($dto);
        $aggResponse = $this->repository->rawSearch($index, $aggBody);

        $products = $this->parseProductHits($productResponse['hits']['hits'] ?? [], $dto->limit);
        $brands = $this->parseBrandAggregation($aggResponse['aggregations']['brands']['top_brands']['buckets'] ?? []);
        $categories = $this->parseCategoryAggregation($aggResponse['aggregations']['categories']['top_categories']['buckets'] ?? []);
        $queries = $this->buildQuerySuggestions($dto->query, $brands, $categories, $products);

        return compact('queries', 'brands', 'categories', 'products');
    }

    private function parseProductHits(array $hits, int $limit): array
    {
        return array_slice(
            array_map(fn (array $hit) => [
                'id'          => $hit['_source']['id'],
                'sku'         => $hit['_source']['sku'] ?? '',
                'name'        => $hit['_source']['name'],
                'slug'        => $hit['_source']['slug'],
                'brand'       => $hit['_source']['brand'] ?? null,
                'price'       => $hit['_source']['price'],
                'rating'      => $hit['_source']['rating'] ?? null,
                'category'    => $hit['_source']['category_names'][0] ?? null,
                'description' => $hit['_source']['description'] ?? null,
                'meta'        => $hit['_source']['meta'] ?? null,
                'image'       => $hit['_source']['images'][0] ?? null,
                'inventory'   => $hit['_source']['inventory'] ?? 0,
            ], $hits),
            0,
            $limit
        );
    }

    private function parseBrandAggregation(array $buckets): array
    {
        return array_slice(
            array_map(fn (array $bucket) => [
                'value' => $bucket['key'],
                'label' => $bucket['key'],
                'count' => $bucket['doc_count'],
            ], $buckets),
            0,
            10
        );
    }

    private function parseCategoryAggregation(array $buckets): array
    {
        return array_slice(
            array_map(fn (array $bucket) => [
                'value' => $bucket['key'],
                'label' => $bucket['key'],
                'count' => $bucket['doc_count'],
            ], $buckets),
            0,
            10
        );
    }

    private function buildQuerySuggestions(
        string $query,
        array $brands,
        array $categories,
        array $products
    ): array {
        $suggestions = [$query];

        foreach ($brands as $brand) {
            $suggestions[] = strtolower($brand['value']);
        }

        foreach ($categories as $category) {
            $suggestions[] = strtolower($category['value']);
        }

        if (!empty($brands) && !empty($categories)) {
            $brandName = strtolower($brands[0]['value']);
            $categoryName = strtolower($categories[0]['value']);
            $suggestions[] = "{$brandName} {$categoryName}";
        }

        $unique = array_unique($suggestions);
        $filtered = array_filter($unique, fn ($s) => strlen($s) >= 2 && $s !== $query);
        array_unshift($filtered, $query);

        return array_values(array_slice($filtered, 0, 10));
    }

    private function applyQueryRules(array $esBody, SuggestQueryDTO $dto): array
    {
        if (empty($dto->query)) {
            return $esBody;
        }

        $rules = $this->queryRuleService->getActiveRules($dto->query, $dto->storeId);

        $pinnedSkus    = [];
        $includeCatIds = [];
        $excludeCatIds = [];
        $includeBrands = [];

        foreach ($rules as $rule) {
            if ($rule->action === 'pin') {
                $pinnedSkus = array_merge($pinnedSkus, $rule->skus ?? []);
            }
            $includeCatIds = array_merge($includeCatIds, $rule->include_category_ids ?? []);
            $excludeCatIds = array_merge($excludeCatIds, $rule->exclude_category_ids ?? []);
            $includeBrands = array_merge($includeBrands, $rule->include_brands ?? []);
        }

        // Scoping a rule to a category also covers its subcategories.
        $includeCatIds = $this->categoryService->withDescendants($includeCatIds);
        $excludeCatIds = $this->categoryService->withDescendants($excludeCatIds);

        if (!empty($includeCatIds)) {
            $esBody['query']['function_score']['query']['bool']['filter'][] = [
                'terms' => ['category_ids' => array_values(array_unique($includeCatIds))],
            ];
        }

        if (!empty($excludeCatIds)) {
            $esBody['query']['function_score']['query']['bool']['must_not'][] = [
                'terms' => ['category_ids' => array_values(array_unique($excludeCatIds))],
            ];
        }

        if (!empty($includeBrands)) {
            $esBody['query']['function_score']['query']['bool']['filter'][] = [
                'terms' => ['brand' => array_values(array_unique($includeBrands))],
            ];
        }

        // Pin rules force their products to the top of the suggestions. Wrap the
        // scored query in a `pinned` query (same mechanism as full search): the
        // pinned docs are returned first even if they wouldn't match the typed
        // prefix. Resolved after scope so pinned ids survive the wrap.
        $pinnedIds = array_values($this->resolveSkusToIds($pinnedSkus));
        if (!empty($pinnedIds)) {
            $esBody['query'] = [
                'pinned' => [
                    'ids' => array_map('strval', $pinnedIds),
                    'organic' => $esBody['query'],
                ],
            ];
        }

        return $esBody;
    }

    private function trackAnalytics(SuggestQueryDTO $dto): void
    {
        try {
            SearchAnalytic::create([
                'store_id' => $dto->storeId ?? 1,
                'query' => $dto->query,
                'endpoint' => 'suggest',
                'result_count' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to track suggest analytics', ['error' => $e->getMessage()]);
        }
    }
}
