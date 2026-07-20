<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Search\IndexingService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BulkIndexProductsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly array $productIds) {}

    public function handle(IndexingService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $products = Product::with(['categories', 'storeProducts.store'])
            ->whereIn('id', $this->productIds)
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $result = $service->bulkIndex($products);

        Cache::put('search_index_version', microtime(true));

        Log::info('BulkIndexProductsJob completed', [
            'success' => count($result['successIds']),
            'failed' => count($result['failedIds']),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('BulkIndexProductsJob failed', [
            'product_ids' => $this->productIds,
            'error' => $e->getMessage(),
        ]);
    }
}
