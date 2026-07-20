<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QueryRule;
use App\Services\Admin\QueryRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QueryRuleController extends Controller
{
    public function __construct(private readonly QueryRuleService $service) {}

    public function index(Request $request): JsonResponse
    {
        $rules = QueryRule::forStore($request->integer('store_id') ?: null)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($rules);
    }

    public function show(QueryRule $queryRule): JsonResponse
    {
        return response()->json($queryRule);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'query_pattern' => ['required', 'string', 'max:500'],
            'match_type' => ['required', 'in:exact,contains,starts_with,regex'],
            'action' => ['required', 'in:pin,exclude,boost,bury,redirect,banner'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'boost_factor' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'pin_position' => ['nullable', 'integer', 'min:0'],
            'redirect_url' => ['nullable', 'url'],
            'banner_html' => ['nullable', 'string'],
            'include_category_ids' => ['nullable', 'array'],
            'include_category_ids.*' => ['integer'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_category_ids.*' => ['integer'],
            'include_brands' => ['nullable', 'array'],
            'include_brands.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'priority' => ['integer', 'min:0', 'max:1000'],
        ]);

        return response()->json($this->service->create($data), Response::HTTP_CREATED);
    }

    public function update(Request $request, QueryRule $queryRule): JsonResponse
    {
        $data = $request->validate([
            'query_pattern' => ['string', 'max:500'],
            'match_type' => ['in:exact,contains,starts_with,regex'],
            'action' => ['in:pin,exclude,boost,bury,redirect,banner'],
            'product_ids' => ['nullable', 'array'],
            'boost_factor' => ['nullable', 'numeric'],
            'pin_position' => ['nullable', 'integer'],
            'redirect_url' => ['nullable', 'url'],
            'banner_html' => ['nullable', 'string'],
            'include_category_ids' => ['nullable', 'array'],
            'include_category_ids.*' => ['integer'],
            'exclude_category_ids' => ['nullable', 'array'],
            'exclude_category_ids.*' => ['integer'],
            'include_brands' => ['nullable', 'array'],
            'include_brands.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'priority' => ['integer'],
        ]);

        return response()->json($this->service->update($queryRule, $data));
    }

    public function destroy(QueryRule $queryRule): Response
    {
        $this->service->delete($queryRule);
        return response()->noContent();
    }
}
