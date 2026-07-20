<?php

namespace App\Jobs;

use App\Services\Search\IndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IndexProductJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 60;

    public function __construct(public readonly int $productId) {}

    public function uniqueId(): string
    {
        return "index-product-{$this->productId}";
    }

    public function handle(IndexingService $service): void
    {
        $service->updateProductInIndex($this->productId);
        Cache::put('search_index_version', microtime(true));
    }

    public function failed(\Throwable $e): void
    {
        Log::error("IndexProductJob failed for product {$this->productId}", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
