<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\Store;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CheckPendingImportsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    // Only one instance may sit on the queue at a time. Guards against a scheduled
    // run overlapping a manual `import:check-pending`, or a slow run being queued twice.
    public int $uniqueFor = 900;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        if (ImportLog::where('status', 'running')->exists()) {
            Log::info('CheckPendingImportsJob: import already running, skipping this check.');
            return;
        }

        $disk = Storage::disk('imports');
        $dispatched = 0;

        foreach (Store::where('is_active', true)->get() as $store) {
            $base = "imports/{$store->code}";
            $hasPending = $disk->exists("{$base}/products.csv")
                       || $disk->exists("{$base}/categories.csv")
                       || $disk->exists("{$base}/product_categories.csv");

            if ($hasPending) {
                StoreImportJob::dispatch($store->id);
                $dispatched++;
                Log::info("CheckPendingImportsJob: queued import for store {$store->code}.");
            }
        }

        if ($dispatched === 0) {
            Log::debug('CheckPendingImportsJob: no pending import files found.');
        }
    }
}
