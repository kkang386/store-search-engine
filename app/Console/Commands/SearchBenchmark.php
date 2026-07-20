<?php

namespace App\Console\Commands;

use App\Services\Benchmark\BenchmarkService;
use Illuminate\Console\Command;

class SearchBenchmark extends Command
{
    protected $signature = 'search:benchmark
                            {--dataset= : Path to the benchmark dataset JSON file}
                            {--ci : Exit with failure code if regressions detected}
                            {--output= : Output directory for reports}';

    protected $description = 'Run search quality and performance benchmarks';

    public function handle(BenchmarkService $benchmarkService): int
    {
        $this->info('Starting search benchmark...');

        $datasetPath = $this->option('dataset') ?: config('search.benchmark.dataset_path');

        if (!file_exists($datasetPath)) {
            $this->error("Dataset not found: {$datasetPath}");
            return self::FAILURE;
        }

        $startTime = microtime(true);

        try {
            $run = $benchmarkService->run($datasetPath);
        } catch (\Throwable $e) {
            $this->error('Benchmark failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("\n=== Benchmark Results ===");
        $this->info("Run ID: {$run->run_id}");
        $this->info("Duration: {$elapsed}s");

        $metrics = $run->metrics;
        $this->newLine();
        $this->info('Quality Metrics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Precision@5', number_format($metrics['precision_at_5'] ?? 0, 3)],
                ['Precision@10', number_format($metrics['precision_at_10'] ?? 0, 3)],
                ['Recall@10', number_format($metrics['recall_at_10'] ?? 0, 3)],
                ['NDCG@10', number_format($metrics['ndcg_at_10'] ?? 0, 3)],
                ['MRR', number_format($metrics['mrr'] ?? 0, 3)],
                ['Zero Result Rate', number_format(($metrics['zero_result_rate'] ?? 0) * 100, 1) . '%'],
            ]
        );

        $latency = $run->latency;
        $this->newLine();
        $this->info('Latency Metrics:');
        $this->table(
            ['Endpoint', 'P50', 'P95', 'P99', 'Avg'],
            [
                [
                    'Search',
                    ($latency['search']['p50'] ?? 0) . 'ms',
                    ($latency['search']['p95'] ?? 0) . 'ms',
                    ($latency['search']['p99'] ?? 0) . 'ms',
                    ($latency['search']['avg'] ?? 0) . 'ms',
                ],
                [
                    'Suggest',
                    ($latency['suggest']['p50'] ?? 0) . 'ms',
                    ($latency['suggest']['p95'] ?? 0) . 'ms',
                    ($latency['suggest']['p99'] ?? 0) . 'ms',
                    ($latency['suggest']['avg'] ?? 0) . 'ms',
                ],
                [
                    'Faceted',
                    ($latency['faceted']['p50'] ?? 0) . 'ms',
                    ($latency['faceted']['p95'] ?? 0) . 'ms',
                    ($latency['faceted']['p99'] ?? 0) . 'ms',
                    ($latency['faceted']['avg'] ?? 0) . 'ms',
                ],
            ]
        );

        if ($this->option('ci')) {
            $regressions = $benchmarkService->checkRegressions($run);

            if (!empty($regressions)) {
                $this->newLine();
                $this->error('REGRESSIONS DETECTED:');
                foreach ($regressions as $regression) {
                    $this->error("  {$regression['metric']}: {$regression['current']} (was {$regression['previous']}, -{$regression['drop_percent']}%)");
                }
                return self::FAILURE;
            }

            $this->info('No regressions detected.');
        }

        $this->newLine();
        $this->info("Reports saved to: " . storage_path('app/benchmarks/'));

        return self::SUCCESS;
    }
}
