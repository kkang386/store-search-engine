<?php

namespace App\Observers;

use App\Jobs\IndexProductJob;
use App\Models\Product;
use Closure;

class ProductObserver
{
    // When true, model events do not enqueue per-product IndexProductJobs.
    // CSV imports enable this (see ImportService) and reindex every affected
    // product in one bulk pass at the end, instead of one job per row.
    private static bool $indexingSuppressed = false;

    /**
     * Run $callback with per-product index dispatching disabled.
     */
    public static function withoutIndexing(Closure $callback): mixed
    {
        $previous = self::$indexingSuppressed;
        self::$indexingSuppressed = true;

        try {
            return $callback();
        } finally {
            self::$indexingSuppressed = $previous;
        }
    }

    public function saved(Product $product): void
    {
        if (self::$indexingSuppressed) {
            return;
        }

        IndexProductJob::dispatch($product->id)
            ->onQueue('indexing')
            ->delay(now()->addSeconds(2));
    }

    public function deleted(Product $product): void
    {
        if (self::$indexingSuppressed) {
            return;
        }

        IndexProductJob::dispatch($product->id)->onQueue('indexing');
    }

    public function restored(Product $product): void
    {
        if (self::$indexingSuppressed) {
            return;
        }

        IndexProductJob::dispatch($product->id)->onQueue('indexing');
    }
}
