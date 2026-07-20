<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Admin\ImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StoreImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries          = 2;
    public int $timeout        = 10800; // hard ceiling: kill the import after 3h. Must stay
                                        // below the connection's retry_after (REDIS_QUEUE_RETRY_AFTER=11000).
    public bool $failOnTimeout = true;  // a timed-out import fails immediately — no 3h retry.
    public int $backoff        = 60;    // retry wait before re-attempting a *failed* (non-timeout)
                                        // run — independent of $timeout.

    public function __construct(public readonly int $storeId)
    {
        // Use a dedicated high-priority queue so import jobs are never blocked
        // behind a backlog of IndexProductJob entries on the indexing queue.
        $this->onQueue('imports');
    }

    public function handle(ImportService $service): void
    {
        $store = Store::findOrFail($this->storeId);
        $log   = $service->importStore($store);

        Cache::put('search_index_version', microtime(true));

        Log::info("StoreImportJob completed for store {$store->code}", [
            'status'            => $log->status,
            'products_created'  => $log->products_created,
            'products_updated'  => $log->products_updated,
            'products_failed'   => $log->products_failed,
            'categories_created'=> $log->categories_created,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("StoreImportJob failed for store {$this->storeId}", [
            'error' => $e->getMessage(),
        ]);
    }
}
