<?php

namespace App\Console\Commands;

use App\Jobs\BulkIndexProductsJob;
use App\Models\Product;
use App\Services\Search\IndexingService;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SearchReindex extends Command
{
    protected $signature = 'search:reindex
                            {--fresh : Drop and recreate the index before reindexing}
                            {--batch-size=500 : Number of products per batch}
                            {--queue : Use queue jobs instead of synchronous indexing}
                            {--filter=active : Filter: all, active, or needs-reindex}';

    protected $description = 'Reindex all products into Elasticsearch';

    public function handle(IndexingService $indexingService): int
    {
        $this->info('Starting product reindex...');
        $startTime = microtime(true);

        if ($this->option('fresh') || !$this->option('queue')) {
            $this->info('Recreating index...');
            $indexingService->recreateIndex();
            $this->info('Index recreated.');
        }

        $batchSize = (int) $this->option('batch-size');
        $filter = $this->option('filter');

        if ($this->option('queue')) {
            return $this->reindexViaQueue($batchSize, $filter);
        }

        return $this->reindexSynchronously($indexingService, $batchSize, $filter);
    }

    private function reindexSynchronously(IndexingService $service, int $batchSize, string $filter): int
    {
        $query = $this->buildQuery($filter);
        $total = $query->count();

        if ($total === 0) {
            $this->warn('No products to index.');
            return self::SUCCESS;
        }

        $this->info("Indexing {$total} products in batches of {$batchSize}...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $indexed = 0;
        $failed = 0;

        $query->with(['categories', 'storeProducts.store'])
            ->orderBy('id')
            ->chunk($batchSize, function ($products) use ($service, $bar, &$indexed, &$failed) {
                $result = $service->bulkIndex($products);
                $indexed += count($result['successIds']);
                $failed += count($result['failedIds']);
                $bar->advance($products->count());
            });

        $bar->finish();
        $this->newLine();

        $elapsed = round(microtime(true) - microtime(true), 2);
        $this->info("Reindex complete: {$indexed} indexed, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reindexViaQueue(int $batchSize, string $filter): int
    {
        $query = $this->buildQuery($filter);
        $total = $query->count();

        if ($total === 0) {
            $this->warn('No products to index.');
            return self::SUCCESS;
        }

        $this->info("Dispatching {$total} products for queue indexing in batches of {$batchSize}...");

        $jobs = [];
        $query->orderBy('id')
            ->chunk($batchSize, function ($products) use (&$jobs) {
                $jobs[] = new BulkIndexProductsJob($products->pluck('id')->toArray());
            });

        $batch = Bus::batch($jobs)
            ->name('search-reindex')
            ->onQueue('indexing')
            ->allowFailures()
            ->finally(function (Batch $batch) {
                $this->info("Batch {$batch->id} completed. Failed: {$batch->failedJobs}");
            })
            ->dispatch();

        $this->info("Dispatched batch {$batch->id} with " . count($jobs) . " jobs.");
        $this->info('Monitor progress: php artisan queue:batches-table');

        return self::SUCCESS;
    }

    private function buildQuery(string $filter)
    {
        $query = Product::query();

        return match ($filter) {
            'active' => $query->where('is_active', true),
            'needs-reindex' => $query->needsReindex(),
            default => $query,
        };
    }
}
