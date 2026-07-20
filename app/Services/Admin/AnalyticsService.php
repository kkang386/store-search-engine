<?php

namespace App\Services\Admin;

use App\Models\SearchAnalytic;
use App\Repositories\ElasticsearchRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(private readonly ElasticsearchRepository $repository) {}

    public function getDashboardMetrics(int $storeId, int $days = 7): array
    {
        $since = now()->subDays($days);

        return [
            'top_queries' => $this->getTopQueries($storeId, $since),
            'zero_result_queries' => $this->getZeroResultQueries($storeId, $since),
            'ctr' => $this->getClickThroughRate($storeId, $since),
            'avg_latency_ms' => $this->getAvgLatency($storeId, $since),
            'search_volume' => $this->getSearchVolume($storeId, $since),
            'facet_usage' => $this->getFacetUsage($storeId, $since),
        ];
    }

    public function getTopQueries(int $storeId, Carbon $since, int $limit = 20): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->where('endpoint', 'search')
            ->groupBy('query')
            ->select('query', DB::raw('COUNT(*) as searches'), DB::raw('AVG(result_count) as avg_results'))
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getZeroResultQueries(int $storeId, Carbon $since, int $limit = 20): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->where('result_count', 0)
            ->groupBy('query')
            ->select('query', DB::raw('COUNT(*) as occurrences'))
            ->orderByDesc('occurrences')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getClickThroughRate(int $storeId, Carbon $since): float
    {
        $total = SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->where('endpoint', 'search')
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $clicks = SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('clicked_product_id')
            ->count();

        return round(($clicks / $total) * 100, 2);
    }

    public function getAvgLatency(int $storeId, Carbon $since): array
    {
        $data = SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->pluck('latency_ms')
            ->sort()
            ->values();

        if ($data->isEmpty()) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'avg' => 0];
        }

        return [
            'p50' => (int) $data->get((int) ($data->count() * 0.5)),
            'p95' => (int) $data->get((int) ($data->count() * 0.95)),
            'p99' => (int) $data->get((int) ($data->count() * 0.99)),
            'avg' => (int) $data->avg(),
        ];
    }

    public function getSearchVolume(int $storeId, Carbon $since): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->where('endpoint', 'search')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function getFacetUsage(int $storeId, Carbon $since): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('facets_used')
            ->get()
            ->flatMap(fn ($a) => $a->facets_used ?? [])
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->toArray();
    }

    public function trackClick(int $storeId, string $query, int $productId, int $position): void
    {
        SearchAnalytic::where('store_id', $storeId)
            ->where('query', $query)
            ->whereNull('clicked_product_id')
            ->latest()
            ->first()
            ?->update([
                'clicked_product_id' => $productId,
                'click_position' => $position,
            ]);
    }

    public function getGrafanaMetrics(int $storeId, Carbon $since): array
    {
        return [
            'search_latency_histogram' => $this->getLatencyHistogram($storeId, $since),
            'top_queries_timeseries' => $this->getTopQueriesTimeseries($storeId, $since),
            'zero_results_timeseries' => $this->getZeroResultsTimeseries($storeId, $since),
        ];
    }

    private function getLatencyHistogram(int $storeId, Carbon $since): array
    {
        $buckets = [0, 50, 100, 150, 200, 300, 500, 1000, PHP_INT_MAX];
        $result = [];

        $latencies = SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('latency_ms')
            ->pluck('latency_ms');

        for ($i = 0; $i < count($buckets) - 1; $i++) {
            $min = $buckets[$i];
            $max = $buckets[$i + 1];
            $label = $max === PHP_INT_MAX ? "{$min}+" : "{$min}-{$max}ms";
            $result[$label] = $latencies->filter(fn ($l) => $l >= $min && $l < $max)->count();
        }

        return $result;
    }

    private function getTopQueriesTimeseries(int $storeId, Carbon $since): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    private function getZeroResultsTimeseries(int $storeId, Carbon $since): array
    {
        return SearchAnalytic::where('store_id', $storeId)
            ->where('created_at', '>=', $since)
            ->where('result_count', 0)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }
}
