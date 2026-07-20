<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\StoreImportJob;
use App\Models\ImportLog;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function index(): JsonResponse
    {
        $disk   = Storage::disk('imports');
        $stores = Store::withTrashed()->orderBy('id')->get();

        $lastLogs = ImportLog::whereIn('store_id', $stores->pluck('id'))
            ->latest('started_at')
            ->get()
            ->groupBy('store_id')
            ->map(fn ($group) => $group->first());

        $data = $stores->map(function (Store $store) use ($disk, $lastLogs) {
            $base       = "imports/{$store->code}";
            $hasPending = $disk->exists("{$base}/products.csv")
                       || $disk->exists("{$base}/categories.csv")
                       || $disk->exists("{$base}/product_categories.csv");

            $displayPath = $base;

            $lastLog = $lastLogs->get($store->id);

            return [
                'id'               => $store->id,
                'name'             => $store->name,
                'code'             => $store->code,
                'deleted_at'       => $store->deleted_at,
                'folder'           => $displayPath,
                'has_pending_files'=> $hasPending,
                'last_import'      => $lastLog ? $this->formatLog($lastLog) : null,
            ];
        });

        return response()->json($data);
    }

    public function history(Store $store): JsonResponse
    {
        $logs = ImportLog::where('store_id', $store->id)
            ->latest('started_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => $this->formatLog($log));

        return response()->json($logs);
    }

    public function run(Store $store): JsonResponse
    {
        $alreadyRunning = ImportLog::where('store_id', $store->id)
            ->where('status', 'running')
            ->exists();

        if ($alreadyRunning) {
            return response()->json(['message' => 'An import is already running for this store.'], 409);
        }

        StoreImportJob::dispatch($store->id);

        return response()->json(['message' => "Import queued for {$store->name}."]);
    }

    public function cancel(Store $store): JsonResponse
    {
        $log = ImportLog::where('store_id', $store->id)
            ->where('status', 'running')
            ->latest('started_at')
            ->first();

        if (!$log) {
            return response()->json(['message' => 'No running import found for this store.'], 404);
        }

        // Mark cancelled first — ImportService checks this before writing 'completed'
        $log->update([
            'status'       => 'cancelled',
            'completed_at' => now(),
            'errors'       => ['Import cancelled by user.'],
        ]);

        // Remove pending (not yet started) jobs from both queues.
        // Do NOT call queue:restart — it causes the in-flight job's attempt count
        // to be replayed on restart, which hits $tries=1 and triggers MaxAttemptsExceededException.
        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'imports']);
        Artisan::call('queue:clear', ['connection' => 'redis', '--queue' => 'indexing']);

        // Rename CSV files so they cannot be re-imported accidentally
        $disk      = Storage::disk('imports');
        $base      = "imports/{$store->code}";
        $timestamp = now()->format('Y-m-d_H-i-s');

        foreach (['products', 'categories', 'product_categories'] as $name) {
            $src = "{$base}/{$name}.csv";
            $dst = "{$base}/{$name}_cancelled_{$timestamp}.csv";
            if (!$disk->exists($src)) {
                continue;
            }
            try {
                $disk->copy($src, $dst);
            } catch (\League\Flysystem\UnableToSetVisibility $e) {
                // mountpoint-s3 does not support chmod; copy itself succeeded
            }
            if ($disk->exists($dst)) {
                $disk->delete($src);
            }
        }

        return response()->json(['message' => "Import cancelled for {$store->name}."]);
    }

    private function formatLog(ImportLog $log): array
    {
        return [
            'id'                 => $log->id,
            'status'             => $log->status,
            'phase'              => $log->phase,
            'products_created'   => $log->products_created,
            'products_updated'   => $log->products_updated,
            'products_failed'    => $log->products_failed,
            'products_to_index'  => (int) $log->products_to_index,
            'products_indexed'   => (int) $log->products_indexed,
            'categories_created' => $log->categories_created,
            'categories_updated' => $log->categories_updated,
            'categories_failed'  => $log->categories_failed,
            'duplicate_skus'     => $log->duplicate_skus ?? [],
            'errors'             => $log->errors ?? [],
            'started_at'         => $log->started_at?->toISOString(),
            'completed_at'       => $log->completed_at?->toISOString(),
            'duration_seconds'   => ($log->started_at && $log->completed_at)
                ? $log->started_at->diffInSeconds($log->completed_at)
                : null,
        ];
    }
}
