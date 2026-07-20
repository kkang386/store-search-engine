<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\SearchQueryDTO;
use App\Http\Controllers\Controller;
use App\Repositories\ElasticsearchRepository;
use App\Services\Search\QueryBuilder;
use App\Services\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchPreviewController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly ElasticsearchRepository $repository,
        private readonly QueryBuilder $queryBuilder,
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string'],
            'store_id' => ['nullable', 'integer'],
            'per_page' => ['integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'string'],
            'brand' => ['nullable', 'array'],
            'category' => ['nullable', 'array'],
            'price_min' => ['nullable', 'numeric'],
            'price_max' => ['nullable', 'numeric'],
        ]);

        $dto = SearchQueryDTO::fromRequest($data);
        $result = $this->searchService->search($dto);

        return response()->json([
            'results' => $result->toArray(),
            'query_dsl' => $this->queryBuilder->build($dto),
        ]);
    }

    public function explain(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string'],
            'product_id' => ['required', 'integer'],
            'store_id' => ['nullable', 'integer'],
        ]);

        $dto = SearchQueryDTO::fromRequest(array_merge($data, ['explain' => true]));
        $esBody = $this->queryBuilder->build($dto);

        $index = config('elasticsearch.indices.products');

        $explainResponse = $this->repository->rawSearch($index, array_merge($esBody, [
            'explain' => true,
            'query' => [
                'bool' => [
                    'must' => [$esBody['query']],
                    'filter' => [['ids' => ['values' => [(string) $data['product_id']]]]],
                ],
            ],
            'size' => 1,
        ]));

        $hit = $explainResponse['hits']['hits'][0] ?? null;

        if (!$hit) {
            return response()->json(['error' => 'Product not found in search results'], 404);
        }

        $rankingFactors = $this->parseRankingFactors($hit);

        return response()->json([
            'product_id' => $data['product_id'],
            'score' => $hit['_score'],
            'ranking_factors' => $rankingFactors,
            'raw_explanation' => $hit['_explanation'] ?? null,
        ]);
    }

    private function parseRankingFactors(array $hit): array
    {
        $explanation = $hit['_explanation'] ?? [];
        $source = $hit['_source'] ?? [];

        return [
            'total_score' => $hit['_score'],
            'text_relevance' => $this->extractTextScore($explanation),
            'inventory_score' => $source['inventory'] > 0 ? 1.5 : 0.3,
            'rating_score' => $source['rating'] >= 4.0 ? 1.2 : 1.0,
            'inventory' => $source['inventory'] ?? 0,
            'rating' => $source['rating'] ?? 0,
            'sales_rank' => $source['sales_rank'] ?? 0,
            'review_count' => $source['review_count'] ?? 0,
            'is_featured' => false,
        ];
    }

    private function extractTextScore(array $explanation): float
    {
        return $explanation['value'] ?? 0.0;
    }
}
