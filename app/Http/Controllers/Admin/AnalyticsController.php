<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id', 1);
        $days = $request->integer('days', 7);

        return response()->json($this->service->getDashboardMetrics($storeId, $days));
    }

    public function topQueries(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id', 1);
        $limit = $request->integer('limit', 20);

        return response()->json(
            $this->service->getTopQueries($storeId, now()->subDays(7), $limit)
        );
    }

    public function zeroResults(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id', 1);

        return response()->json(
            $this->service->getZeroResultQueries($storeId, now()->subDays(7))
        );
    }

    public function grafana(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id', 1);
        $days = $request->integer('days', 7);

        return response()->json(
            $this->service->getGrafanaMetrics($storeId, now()->subDays($days))
        );
    }
}
