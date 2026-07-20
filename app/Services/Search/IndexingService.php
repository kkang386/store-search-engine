<?php

namespace App\Services\Search;

use App\Models\Product;
use App\Repositories\ElasticsearchRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class IndexingService
{
    private string $index;

    public function __construct(private readonly ElasticsearchRepository $repository)
    {
        $this->index = config('elasticsearch.indices.products');
    }

    public function indexProduct(Product $product): void
    {
        $product->loadMissing(['categories', 'storeProducts.store']);

        $document = $product->toSearchDocument();

        $this->repository->indexDocument(
            $this->index,
            (string) $product->id,
            $document
        );

        // updateQuietly: writing indexed_at must NOT fire the saved observer, or it
        // would dispatch another IndexProductJob for this product → indexing loop.
        $product->updateQuietly(['indexed_at' => now()]);

        Log::debug("Indexed product {$product->id}: {$product->name}");
    }

    public function deleteProduct(int $productId): void
    {
        $this->repository->deleteDocument($this->index, (string) $productId);
        Log::debug("Deleted product {$productId} from index");
    }

    /**
     * Remove many products from the index in one _bulk request — far faster than
     * per-product deleteProduct() (which waits on a refresh each call).
     */
    public function bulkDelete(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        $this->repository->bulkDelete($this->index, $productIds);
    }

    public function bulkIndex(Collection $products): array
    {
        $products->loadMissing(['categories', 'storeProducts.store']);

        $documents = $products->map(fn (Product $p) => $p->toSearchDocument())->toArray();

        $result = $this->repository->bulkIndex($this->index, $documents);

        $successIds = [];
        $failedIds = [];

        foreach ($result['items'] ?? [] as $item) {
            $indexItem = $item['index'] ?? [];
            $id = (int) ($indexItem['_id'] ?? 0);

            if (isset($indexItem['error'])) {
                $failedIds[] = $id;
                Log::warning("Failed to index product {$id}", ['error' => $indexItem['error']]);
            } else {
                $successIds[] = $id;
            }
        }

        if (!empty($successIds)) {
            Product::whereIn('id', $successIds)->update(['indexed_at' => now()]);
        }

        return compact('successIds', 'failedIds');
    }

    public function reindexAll(int $batchSize = 500, ?callable $progressCallback = null): array
    {
        $this->recreateIndex();

        $total = 0;
        $failed = 0;

        Product::with(['categories', 'storeProducts.store'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunk($batchSize, function (Collection $products) use (&$total, &$failed, $progressCallback) {
                $result = $this->bulkIndex($products);
                $total += count($result['successIds']);
                $failed += count($result['failedIds']);

                if ($progressCallback) {
                    $progressCallback($total, $failed);
                }
            });

        return compact('total', 'failed');
    }

    public function recreateIndex(): void
    {
        $mappingPath = config('elasticsearch.mappings_path') . '/products.json';
        // products.json is a template: substitute the synonym-set name before decoding.
        $rawMapping = str_replace(
            '__SYNONYM_SET__',
            config('elasticsearch.synonym_set'),
            file_get_contents($mappingPath)
        );
        $mapping = json_decode($rawMapping, true);

        // The synonym set must exist before an index whose analyzers reference it is created.
        $this->putSynonymSet();

        // Delete unconditionally; deleteIndex() already swallows 404.
        $this->repository->deleteIndex($this->index);
        Log::info("Deleted existing index: {$this->index}");

        // Retry up to 3 times with a short back-off to handle the rare race where a
        // background worker dynamically recreates the index in the deletion window.
        $attempts = 0;
        while (true) {
            try {
                $this->repository->createIndex($this->index, $mapping);
                Log::info("Created index: {$this->index}");
                return;
            } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
                if ($attempts < 3 && str_contains($e->getMessage(), 'resource_already_exists_exception')) {
                    $attempts++;
                    usleep(300_000 * $attempts); // 300 ms, 600 ms, 900 ms
                    $this->repository->deleteIndex($this->index);
                    continue;
                }
                throw $e;
            }
        }
    }

    public function updateProductInIndex(int $productId): void
    {
        $product = Product::with(['categories', 'storeProducts.store'])->find($productId);

        if (!$product) {
            $this->deleteProduct($productId);
            return;
        }

        if (!$product->is_active || $product->deleted_at) {
            $this->deleteProduct($productId);
            return;
        }

        $this->indexProduct($product);
    }

    /**
     * Push all active synonyms to the ES synonym set. Does NOT reload analyzers —
     * used before index creation (when the index may not exist yet).
     */
    public function putSynonymSet(): void
    {
        $this->repository->putSynonymSet(
            config('elasticsearch.synonym_set'),
            $this->buildSynonymRules()
        );
    }

    /**
     * Push synonyms to the set AND reload the index's search analyzers so the
     * change takes effect immediately (used after admin edits).
     */
    public function syncSynonymSet(): void
    {
        $this->putSynonymSet();
        $this->repository->reloadSearchAnalyzers($this->index);
        Log::info('Synonym set updated and search analyzers reloaded');
    }

    /**
     * Build Synonyms-API rules from the active synonyms. Seeds a harmless
     * placeholder when there are none, because ES rejects an empty synonym set.
     */
    private function buildSynonymRules(): array
    {
        $rules = \App\Models\Synonym::active()
            ->get()
            ->map(fn ($s) => ['synonyms' => $s->toSynonymLine()])
            ->filter(fn ($r) => trim($r['synonyms']) !== '')
            ->values()
            ->all();

        if (empty($rules)) {
            $rules = [['synonyms' => '__nosynonym_a__, __nosynonym_b__']];
        }

        return $rules;
    }
}
