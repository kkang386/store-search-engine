<?php

namespace App\Http\Controllers\Api;

use App\DTOs\SearchQueryDTO;
use App\DTOs\SuggestQueryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchRequest;
use App\Http\Requests\SuggestRequest;
use App\Services\Admin\AnalyticsService;
use App\Services\Search\SearchService;
use App\Services\Search\SuggestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SuggestService $suggestService,
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function search(SearchRequest $request): JsonResponse
    {
        $dto = SearchQueryDTO::fromRequest($request->validated());
        $result = $this->searchService->search($dto);

        return response()->json($result->toArray());
    }

    public function suggest(SuggestRequest $request): JsonResponse
    {
        $dto = SuggestQueryDTO::fromRequest($request->validated());
        $result = $this->suggestService->suggest($dto);

        return response()->json($result->toArray());
    }

    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string'],
            'product_id' => ['required', 'integer'],
            'position' => ['required', 'integer', 'min:0'],
            'store_id' => ['nullable', 'integer'],
        ]);

        $this->analyticsService->trackClick(
            storeId: $request->input('store_id', 1),
            query: $request->input('query'),
            productId: $request->input('product_id'),
            position: $request->input('position'),
        );

        return response()->json(['status' => 'ok']);
    }
}
