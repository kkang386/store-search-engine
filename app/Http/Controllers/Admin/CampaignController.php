<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchCampaign;
use App\Services\Admin\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $service) {}

    public function index(Request $request): JsonResponse
    {
        $campaigns = SearchCampaign::where(function ($q) use ($request) {
            $storeId = $request->integer('store_id') ?: null;
            if ($storeId) {
                $q->whereNull('store_id')->orWhere('store_id', $storeId);
            }
        })
            ->orderByDesc('starts_at')
            ->paginate(20);

        return response()->json($campaigns);
    }

    public function show(SearchCampaign $campaign): JsonResponse
    {
        return response()->json($campaign);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:boost,banner,exclusion,synonym'],
            'query_patterns' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'boost_factor' => ['nullable', 'numeric'],
            'banner_config' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        return response()->json($this->service->create($data), Response::HTTP_CREATED);
    }

    public function update(Request $request, SearchCampaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'name' => ['string'],
            'description' => ['nullable', 'string'],
            'query_patterns' => ['nullable', 'array'],
            'product_ids' => ['nullable', 'array'],
            'boost_factor' => ['nullable', 'numeric'],
            'banner_config' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'starts_at' => ['date'],
            'ends_at' => ['date'],
        ]);

        return response()->json($this->service->update($campaign, $data));
    }

    public function destroy(SearchCampaign $campaign): Response
    {
        $this->service->delete($campaign);
        return response()->noContent();
    }
}
