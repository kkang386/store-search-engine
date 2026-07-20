<?php

namespace App\Services\Benchmark;

use App\DTOs\SearchQueryDTO;
use App\DTOs\SuggestQueryDTO;
use App\Models\BenchmarkRun;
use App\Services\Search\SearchService;
use App\Services\Search\SuggestService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class BenchmarkService
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly SuggestService $suggestService,
        private readonly MetricsCalculator $calculator,
        private readonly BenchmarkReportGenerator $reportGenerator,
    ) {}

    public function run(?string $datasetPath = null): BenchmarkRun
    {
        $datasetPath ??= config('search.benchmark.dataset_path');
        $dataset = json_decode(file_get_contents($datasetPath), true);

        $runId = Str::uuid()->toString();
        $run = BenchmarkRun::create([
            'run_id' => $runId,
            'version' => config('app.version', '1.0'),
            'git_commit' => $this->getGitCommit(),
            'metrics' => [],
            'latency' => [],
            'config' => config('search.benchmark'),
            'status' => 'running',
            'total_queries' => count($dataset['queries'] ?? []),
            'started_at' => now(),
        ]);

        try {
            $results = $this->executeAllBenchmarks($dataset);
            $metrics = $this->calculateOverallMetrics($results);
            $latency = $this->calculateLatencyMetrics($results);

            $run->update([
                'metrics' => $metrics,
                'latency' => $latency,
                'status' => 'completed',
                'failed_queries' => $results['failed_count'],
                'completed_at' => now(),
            ]);

            $this->reportGenerator->generate($run, $results);

        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }

        return $run;
    }

    private function executeAllBenchmarks(array $dataset): array
    {
        $searchResults = $this->benchmarkSearch($dataset['queries'] ?? []);
        $suggestResults = $this->benchmarkSuggest($dataset['suggest_queries'] ?? []);
        $facetedResults = $this->benchmarkFaceted($dataset['faceted_queries'] ?? []);

        $failedCount = count(array_filter($searchResults, fn ($r) => $r['failed'] ?? false))
            + count(array_filter($suggestResults, fn ($r) => $r['failed'] ?? false))
            + count(array_filter($facetedResults, fn ($r) => $r['failed'] ?? false));

        return [
            'search' => $searchResults,
            'suggest' => $suggestResults,
            'faceted' => $facetedResults,
            'failed_count' => $failedCount,
        ];
    }

    private function benchmarkSearch(array $queries): array
    {
        $results = [];

        foreach ($queries as $queryDef) {
            $dto = SearchQueryDTO::fromRequest(['q' => $queryDef['query']]);

            $startTime = microtime(true);
            try {
                $result = $this->searchService->search($dto);
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $retrievedIds = array_map(
                    fn ($p) => $p->id,
                    $result->products
                );

                $relevantIds = $queryDef['expected_product_ids'] ?? [];

                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'latency_ms' => $latencyMs,
                    'total_hits' => $result->pagination->total,
                    'retrieved_ids' => $retrievedIds,
                    'relevant_ids' => $relevantIds,
                    'precision_at_5' => $this->calculator->precisionAtK($retrievedIds, $relevantIds, 5),
                    'precision_at_10' => $this->calculator->precisionAtK($retrievedIds, $relevantIds, 10),
                    'recall_at_10' => $this->calculator->recallAtK($retrievedIds, $relevantIds, 10),
                    'ndcg_at_10' => $this->calculator->ndcgAtK($retrievedIds, $relevantIds, 10),
                    'mrr' => $this->calculator->meanReciprocalRank($retrievedIds, $relevantIds),
                    'has_results' => $result->pagination->total > 0,
                    'meets_min_results' => $result->pagination->total >= ($queryDef['min_results'] ?? 1),
                    'failed' => false,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'failed' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function benchmarkSuggest(array $queries): array
    {
        $results = [];

        foreach ($queries as $queryDef) {
            $dto = SuggestQueryDTO::fromRequest(['q' => $queryDef['query']]);

            $startTime = microtime(true);
            try {
                $result = $this->suggestService->suggest($dto);
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $brandNames = array_column($result->brands, 'value');
                $categoryNames = array_column($result->categories, 'value');

                $expectedBrands = $queryDef['expected_brands'] ?? [];
                $expectedCategories = $queryDef['expected_categories'] ?? [];

                $brandRecall = empty($expectedBrands)
                    ? 1.0
                    : count(array_intersect($brandNames, $expectedBrands)) / count($expectedBrands);

                $categoryRecall = empty($expectedCategories)
                    ? 1.0
                    : count(array_intersect($categoryNames, $expectedCategories)) / count($expectedCategories);

                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'latency_ms' => $latencyMs,
                    'brand_count' => count($result->brands),
                    'category_count' => count($result->categories),
                    'product_count' => count($result->products),
                    'brand_recall' => $brandRecall,
                    'category_recall' => $categoryRecall,
                    'meets_min_brand_results' => count($result->brands) >= ($queryDef['min_brand_results'] ?? 0),
                    'failed' => false,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'failed' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function benchmarkFaceted(array $queries): array
    {
        $results = [];

        foreach ($queries as $queryDef) {
            $requestData = ['q' => $queryDef['query']];
            if (!empty($queryDef['filters']['brand'])) {
                $requestData['brand'] = $queryDef['filters']['brand'];
            }

            $dto = SearchQueryDTO::fromRequest($requestData);

            $startTime = microtime(true);
            try {
                $result = $this->searchService->search($dto);
                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $returnedFacetKeys = array_map(fn ($f) => $f->key, $result->facets);
                $expectedFacets = $queryDef['expected_facets'] ?? [];
                $facetPrecision = empty($expectedFacets)
                    ? 1.0
                    : count(array_intersect($returnedFacetKeys, $expectedFacets)) / count($expectedFacets);

                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'latency_ms' => $latencyMs,
                    'total_hits' => $result->pagination->total,
                    'facet_count' => count($result->facets),
                    'returned_facets' => $returnedFacetKeys,
                    'facet_precision' => $facetPrecision,
                    'meets_min_results' => $result->pagination->total >= ($queryDef['min_results'] ?? 1),
                    'failed' => false,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'query_id' => $queryDef['id'],
                    'query' => $queryDef['query'],
                    'failed' => true,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function calculateOverallMetrics(array $results): array
    {
        $searchResults = array_filter($results['search'], fn ($r) => !($r['failed'] ?? false));

        if (empty($searchResults)) {
            return [];
        }

        $searchValues = array_values($searchResults);

        return [
            'precision_at_5' => $this->avg(array_column($searchValues, 'precision_at_5')),
            'precision_at_10' => $this->avg(array_column($searchValues, 'precision_at_10')),
            'recall_at_10' => $this->avg(array_column($searchValues, 'recall_at_10')),
            'ndcg_at_10' => $this->avg(array_column($searchValues, 'ndcg_at_10')),
            'mrr' => $this->avg(array_column($searchValues, 'mrr')),
            'zero_result_rate' => count(array_filter($searchValues, fn ($r) => !$r['has_results'])) / count($searchValues),
            'total_queries' => count($searchValues),
        ];
    }

    private function calculateLatencyMetrics(array $results): array
    {
        $allLatencies = [];
        $suggestLatencies = [];
        $facetedLatencies = [];

        foreach ($results['search'] as $r) {
            if (!($r['failed'] ?? false)) {
                $allLatencies[] = $r['latency_ms'];
            }
        }

        foreach ($results['suggest'] as $r) {
            if (!($r['failed'] ?? false)) {
                $suggestLatencies[] = $r['latency_ms'];
            }
        }

        foreach ($results['faceted'] as $r) {
            if (!($r['failed'] ?? false)) {
                $facetedLatencies[] = $r['latency_ms'];
            }
        }

        return [
            'search' => $this->computeLatencyStats($allLatencies),
            'suggest' => $this->computeLatencyStats($suggestLatencies),
            'faceted' => $this->computeLatencyStats($facetedLatencies),
            'p50_ms' => $this->calculator->percentile($allLatencies, 50),
            'p95_ms' => $this->calculator->percentile($allLatencies, 95),
            'p99_ms' => $this->calculator->percentile($allLatencies, 99),
            'avg_ms' => !empty($allLatencies) ? $this->avg($allLatencies) : 0,
        ];
    }

    private function computeLatencyStats(array $latencies): array
    {
        if (empty($latencies)) {
            return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'avg' => 0, 'min' => 0, 'max' => 0];
        }

        return [
            'p50' => $this->calculator->percentile($latencies, 50),
            'p95' => $this->calculator->percentile($latencies, 95),
            'p99' => $this->calculator->percentile($latencies, 99),
            'avg' => $this->avg($latencies),
            'min' => min($latencies),
            'max' => max($latencies),
        ];
    }

    public function checkRegressions(BenchmarkRun $currentRun): array
    {
        $previousRun = BenchmarkRun::where('status', 'completed')
            ->where('id', '<', $currentRun->id)
            ->orderByDesc('id')
            ->first();

        if (!$previousRun) {
            return [];
        }

        $config = config('search.benchmark');
        $threshold = $config['precision10_drop_threshold'] ?? 5.0;

        $regressions = $this->calculator->compareMetrics(
            $currentRun->metrics,
            $previousRun->metrics,
            $threshold
        );

        $p95Config = $config['p95_search_threshold_ms'] ?? 300;
        if ($currentRun->getP95LatencyMs() > $p95Config) {
            $regressions[] = [
                'metric' => 'P95 Search Latency',
                'current' => $currentRun->getP95LatencyMs(),
                'threshold' => $p95Config,
                'drop_percent' => null,
            ];
        }

        return $regressions;
    }

    private function avg(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        return array_sum($values) / count($values);
    }

    private function getGitCommit(): ?string
    {
        try {
            return trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?? '');
        } catch (\Throwable) {
            return null;
        }
    }
}
