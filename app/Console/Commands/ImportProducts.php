<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\Admin\ImportService;
use Illuminate\Console\Command;

class ImportProducts extends Command
{
    protected $signature = 'import:products
                            {store? : Store code to import (omit to run all active stores)}
                            {--queue : Dispatch as a background job instead of running synchronously}';

    protected $description = 'Import products, categories, and product_categories from per-store CSV folders';

    public function handle(ImportService $service): int
    {
        $storeCode = $this->argument('store');

        if ($storeCode) {
            $store = Store::where('code', $storeCode)->first();
            if (!$store) {
                $this->error("Store '{$storeCode}' not found.");
                return self::FAILURE;
            }
            $stores = collect([$store]);
        } else {
            $stores = Store::where('is_active', true)->get();
        }

        if ($stores->isEmpty()) {
            $this->warn('No active stores found.');
            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            foreach ($stores as $store) {
                \App\Jobs\StoreImportJob::dispatch($store->id);
                $this->info("Queued import for store: {$store->code}");
            }
            return self::SUCCESS;
        }

        $hasFailures = false;

        foreach ($stores as $store) {
            $this->info("Importing store: {$store->code} ({$store->name})");
            $log = $service->importStore($store);

            if ($log->status === 'completed') {
                $this->line("  Products  : {$log->products_created} created, {$log->products_updated} updated, {$log->products_failed} failed");
                $this->line("  Categories: {$log->categories_created} created, {$log->categories_updated} updated, {$log->categories_failed} failed");

                if (!empty($log->duplicate_skus)) {
                    $this->warn('  Duplicate SKUs (last value kept): ' . implode(', ', $log->duplicate_skus));
                }
            } else {
                $this->error("  Import failed: " . implode('; ', $log->errors ?? ['unknown error']));
                $hasFailures = true;
            }
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
