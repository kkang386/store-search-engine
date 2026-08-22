<?php

namespace App\Jobs;

use App\Models\ImportRequest;
use App\Models\Product;
use App\Models\Store;
use App\Services\Admin\ApiImportService;
use App\Services\Search\IndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs an import-API upsert off the request thread and records its outcome on
 * the ImportRequest so callers can poll by request_id. For product batches it
 * also reindexes the affected products inline, so a 'completed' status means
 * both the DB upsert and Elasticsearch indexing are done.
 */
class ProcessApiImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;

    public function __construct(public readonly string $requestId)
    {
        $this->onQueue('imports');
    }

    public function handle(ApiImportService $service, IndexingService $indexer): void
    {
        $request = ImportRequest::where('request_id', $this->requestId)->first();
        if (!$request || $request->status !== ImportRequest::STATUS_IN_PROGRESS) {
            return;
        }

        $store = Store::find($request->store_id);
        if (!$store) {
            $request->update([
                'status'  => ImportRequest::STATUS_ERROR,
                'error'   => 'Store no longer exists',
                'payload' => null,
            ]);
            return;
        }

        $rows = json_decode($request->payload ?? '[]', true) ?: [];

        if ($request->type === 'categories') {
            $result = $service->upsertCategories($store, $rows);
            $indexed = null;
        } else {
            $result  = $service->upsertProducts($store, $rows);
            $indexed = $this->indexProducts($indexer, $result['affected_ids'] ?? []);
        }

        $request->update([
            'status'        => ($result['failed'] ?? 0) > 0
                ? ImportRequest::STATUS_ERROR
                : ImportRequest::STATUS_COMPLETED,
            'created_count' => $result['created'] ?? 0,
            'updated_count' => $result['updated'] ?? 0,
            'failed_count'  => $result['failed'] ?? 0,
            'indexed_count' => $indexed,
            'error'         => ($result['failed'] ?? 0) > 0
                ? "{$result['failed']} row(s) failed to import; see logs for details"
                : null,
            'payload'       => null,
        ]);
    }

    /**
     * Index the affected products in one pass and bust the search cache, mirroring
     * BulkIndexProductsJob. Returns the number of documents successfully indexed.
     */
    private function indexProducts(IndexingService $indexer, array $productIds): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $products = Product::with(['categories', 'storeProducts.store'])
            ->whereIn('id', $productIds)
            ->get();

        if ($products->isEmpty()) {
            return 0;
        }

        $result = $indexer->bulkIndex($products);
        Cache::put('search_index_version', microtime(true));

        return count($result['successIds'] ?? []);
    }

    public function failed(\Throwable $e): void
    {
        ImportRequest::where('request_id', $this->requestId)->update([
            'status'  => ImportRequest::STATUS_ERROR,
            'error'   => $e->getMessage(),
            'payload' => null,
        ]);
        Log::error('ProcessApiImportJob failed', ['request_id' => $this->requestId, 'error' => $e->getMessage()]);
    }
}
