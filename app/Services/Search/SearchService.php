<?php

namespace App\Services\Search;

use App\DTOs\FacetDTO;
use App\DTOs\PaginationDTO;
use App\DTOs\ProductDTO;
use App\DTOs\SearchMetaDTO;
use App\DTOs\SearchQueryDTO;
use App\DTOs\SearchResultDTO;
use App\Models\QueryRule;
use App\Models\SearchAnalytic;
use App\Models\Store;
use App\Repositories\ElasticsearchRepository;
use App\Services\Admin\QueryRuleService;
use App\Services\Admin\CampaignService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SearchService
{
    public function __construct(
        private readonly ElasticsearchRepository $repository,
        private readonly QueryBuilder $queryBuilder,
        private readonly FacetBuilder $facetBuilder,
        private readonly QueryRuleService $queryRuleService,
        private readonly CampaignService $campaignService,
        private readonly CategoryService $categoryService,
    ) {}

    public function search(SearchQueryDTO $dto): SearchResultDTO
    {
        $startTime = microtime(true);

        $redirect = $this->queryRuleService->getRedirect($dto->query, $dto->storeId);
        if ($redirect) {
            return $this->buildRedirectResult($dto, $redirect);
        }

        $cacheKey = $this->buildCacheKey($dto);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $esBody = $this->queryBuilder->build($dto);
        $esBody = $this->applyStoreCategoryFilter($esBody, $dto->storeId);
        $esBody = $this->applyQueryRules($esBody, $dto);
        $esBody = $this->applyCampaignBoosts($esBody, $dto);

        try {
            $response = $this->repository->rawSearch(
                config('elasticsearch.indices.products'),
                $esBody
            );
        } catch (\Throwable $e) {
            Log::error('Search failed', ['error' => $e->getMessage(), 'dto' => $dto]);
            throw $e;
        }

        $result = $this->buildResult($response, $dto);

        Cache::put($cacheKey, $result, 60);

        $this->trackAnalytics($dto, $result, (int) ((microtime(true) - $startTime) * 1000));

        return $result;
    }

    private function buildResult(array $response, SearchQueryDTO $dto): SearchResultDTO
    {
        $hits = $response['hits'];
        $total = $hits['total']['value'] ?? 0;

        $products = array_map(
            fn (array $hit) => ProductDTO::fromElasticsearch($hit, $dto->storeId),
            $hits['hits'] ?? []
        );

        $facets = [];
        if ($dto->withFacets && isset($response['aggregations'])) {
            $facets = $this->facetBuilder->parse($response['aggregations']);
        }

        $banner = $this->queryRuleService->getBanner($dto->query, $dto->storeId);

        return new SearchResultDTO(
            products: $products,
            facets: $facets,
            pagination: PaginationDTO::fromTotal($total, $dto->page, $dto->perPage),
            meta: new SearchMetaDTO(
                query: $dto->query,
                totalHits: $total,
                maxScore: $hits['hits'][0]['_score'] ?? 0.0,
                tookMs: $response['took'] ?? 0,
                timedOut: $response['timed_out'] ?? false,
                storeId: $dto->storeId,
            ),
            banner: $banner,
        );
    }

    // Restrict results to the store's linked active categories.
    // Only applied when the store has at least one linked category.
    private function applyStoreCategoryFilter(array $esBody, ?int $storeId): array
    {
        if (!$storeId) {
            return $esBody;
        }

        $linkedIds = Cache::remember("store_cat_ids:{$storeId}", 300, function () use ($storeId) {
            return Store::find($storeId)?->categories()
                ->wherePivot('is_active', true)
                ->pluck('categories.id')
                ->toArray() ?? [];
        });

        if (empty($linkedIds)) {
            return $esBody;
        }

        $esBody['query']['function_score']['query']['bool']['filter'][] = [
            'terms' => ['category_ids' => $linkedIds],
        ];

        return $esBody;
    }

    private function applyQueryRules(array $esBody, SearchQueryDTO $dto): array
    {
        if (empty($dto->query)) {
            return $esBody;
        }

        $rules = $this->queryRuleService->getActiveRules($dto->query, $dto->storeId);

        $pinnedIds     = [];
        $excludedIds   = [];
        $boostRules    = [];
        $includeCatIds = [];
        $excludeCatIds = [];
        $includeBrands = [];

        foreach ($rules as $rule) {
            switch ($rule->action) {
                case 'pin':
                    $pinnedIds = array_merge($pinnedIds, $rule->product_ids ?? []);
                    break;
                case 'exclude':
                    $excludedIds = array_merge($excludedIds, $rule->product_ids ?? []);
                    break;
                case 'boost':
                    $boostRules[] = $rule;
                    break;
            }

            $includeCatIds = array_merge($includeCatIds, $rule->include_category_ids ?? []);
            $excludeCatIds = array_merge($excludeCatIds, $rule->exclude_category_ids ?? []);
            $includeBrands = array_merge($includeBrands, $rule->include_brands ?? []);
        }

        // Scoping a rule to a category also covers its subcategories.
        $includeCatIds = $this->categoryService->withDescendants($includeCatIds);
        $excludeCatIds = $this->categoryService->withDescendants($excludeCatIds);

        $esBody = $this->applyScopeFilters($esBody, $includeCatIds, $excludeCatIds, $includeBrands);

        if (!empty($excludedIds)) {
            $esBody['query']['function_score']['query']['bool']['must_not'][] = [
                'ids' => ['values' => array_map('strval', $excludedIds)],
            ];
        }

        if (!empty($boostRules)) {
            foreach ($boostRules as $rule) {
                $esBody['query']['function_score']['functions'][] = [
                    'filter' => ['ids' => ['values' => array_map('strval', $rule->product_ids ?? [])]],
                    'weight' => $rule->boost_factor ?? 2.0,
                ];
            }
        }

        if (!empty($pinnedIds)) {
            $organicQuery = $esBody['query'];
            $esBody['query'] = $this->queryBuilder->buildPinnedQuery($pinnedIds, $organicQuery);
        }

        return $esBody;
    }

    // Shared helper: add include/exclude category and brand filters to the ES bool query.
    private function applyScopeFilters(
        array $esBody,
        array $includeCatIds,
        array $excludeCatIds,
        array $includeBrands,
    ): array {
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

        return $esBody;
    }

    private function applyCampaignBoosts(array $esBody, SearchQueryDTO $dto): array
    {
        $campaigns = $this->campaignService->getActiveCampaigns($dto->query, $dto->storeId);

        foreach ($campaigns as $campaign) {
            if ($campaign->type === 'boost' && !empty($campaign->product_ids)) {
                $functions = $esBody['query']['function_score']['functions'] ?? [];
                $functions[] = [
                    'filter' => ['ids' => ['values' => array_map('strval', $campaign->product_ids)]],
                    'weight' => $campaign->boost_factor ?? 1.5,
                ];
                $esBody['query']['function_score']['functions'] = $functions;
            }
        }

        return $esBody;
    }

    private function buildCacheKey(SearchQueryDTO $dto): string
    {
        $version = Cache::get('search_index_version', 0);
        return 'search:' . md5(serialize([
            $dto->query,
            $dto->page,
            $dto->perPage,
            $dto->sort,
            $dto->brands,
            $dto->categoryIds,
            $dto->priceMin,
            $dto->priceMax,
            $dto->attributes,
            $dto->storeId,
            $version,
        ]));
    }

    private function buildRedirectResult(SearchQueryDTO $dto, string $redirectUrl): SearchResultDTO
    {
        return new SearchResultDTO(
            products: [],
            facets: [],
            pagination: PaginationDTO::fromTotal(0, 1, $dto->perPage),
            meta: new SearchMetaDTO(
                query: $dto->query,
                totalHits: 0,
                maxScore: 0.0,
                tookMs: 0,
                timedOut: false,
            ),
            redirect: $redirectUrl,
        );
    }

    private function trackAnalytics(SearchQueryDTO $dto, SearchResultDTO $result, int $latencyMs): void
    {
        try {
            SearchAnalytic::create([
                'store_id' => $dto->storeId ?? 1,
                'query' => $dto->query,
                'result_count' => $result->pagination->total,
                'latency_ms' => $latencyMs,
                'filters_applied' => $dto->hasFilters() ? array_filter([
                    'brands' => $dto->brands,
                    'categories' => $dto->categoryIds,
                    'price_min' => $dto->priceMin,
                    'price_max' => $dto->priceMax,
                    'attributes' => $dto->attributes,
                ]) : null,
                'sort_order' => $dto->sort,
                'endpoint' => 'search',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to track search analytics', ['error' => $e->getMessage()]);
        }
    }
}
