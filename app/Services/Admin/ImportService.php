<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\ImportLog;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Observers\ProductObserver;
use App\Services\Search\IndexingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportService
{
    private const FILES  = ['categories', 'products', 'product_categories'];
    private const BULK_SIZE = 500;

    public function __construct(private readonly IndexingService $indexingService) {}

    public function importStore(Store $store): ImportLog
    {
        $log = ImportLog::create([
            'store_id'   => $store->id,
            'status'     => 'running',
            'started_at' => now(),
        ]);

        try {
            $disk     = Storage::disk('imports');
            $base     = "imports/{$store->code}";

            $disk->makeDirectory($base);

            $categoryMap = [];

            if ($disk->exists("{$base}/categories.csv")) {
                $this->setPhase($log, 'categories');
                [$catCreated, $catUpdated, $catFailed, $categoryMap] =
                    $this->importCategories($disk->readStream("{$base}/categories.csv"), $store);
                $log->update([
                    'categories_created' => $catCreated,
                    'categories_updated' => $catUpdated,
                    'categories_failed'  => $catFailed,
                ]);
            }

            $productIds = [];
            $dupSkus    = [];
            if ($disk->exists("{$base}/products.csv")) {
                $this->setPhase($log, 'products');
                // Suppress per-row IndexProductJob dispatches — every affected product
                // is reindexed in one bulk pass below (bulkIndexProducts), which avoids
                // flooding the 'indexing' queue with one job per row.
                [$prodCreated, $prodUpdated, $prodFailed, $dupSkus, $productIds] =
                    ProductObserver::withoutIndexing(
                        fn () => $this->importProducts($disk->readStream("{$base}/products.csv"), $store)
                    );
                $log->update([
                    'products_created' => $prodCreated,
                    'products_updated' => $prodUpdated,
                    'products_failed'  => $prodFailed,
                    'duplicate_skus'   => $dupSkus ?: null,
                ]);
            }

            if ($disk->exists("{$base}/product_categories.csv") && !empty($categoryMap)) {
                $this->setPhase($log, 'linking');
                $this->importProductCategories($disk->readStream("{$base}/product_categories.csv"), $categoryMap);
            }

            // Bulk-index all imported products synchronously.
            // This is intentionally done after product_categories are linked so
            // the category_ids field is populated correctly in each document.
            if (!empty($productIds)) {
                $this->setPhase($log, 'indexing');
                $this->bulkIndexProducts($productIds, $log);
            }

            $this->archiveFiles($disk, $base);

            // A cancel request may have arrived while the job was running.
            // Re-read the row so we don't overwrite a 'cancelled' status.
            $log->refresh();
            if ($log->status !== 'cancelled') {
                $log->update([
                    'status'       => 'completed',
                    'phase'        => 'done',
                    'completed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("CSV import failed for store {$store->code}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $log->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'errors'       => [$e->getMessage()],
            ]);
        }

        return $log->fresh();
    }

    // Record the current step on the log (visible in DB + admin UI) and in the log file,
    // so a long-running import isn't a black box. On failure the last phase stays set,
    // showing which step it died in.
    private function setPhase(ImportLog $log, string $phase): void
    {
        $log->update(['phase' => $phase]);
        Log::info("Import #{$log->id} (store {$log->store_id}): phase → {$phase}");
    }

    private function bulkIndexProducts(array $productIds, ImportLog $log): void
    {
        // Seed progress so the admin UI can show an indexing percentage while this runs.
        $log->update([
            'products_to_index' => count($productIds),
            'products_indexed'  => 0,
        ]);

        $chunks = array_chunk($productIds, self::BULK_SIZE);
        foreach ($chunks as $chunk) {
            $products = Product::withTrashed()
                ->with(['categories', 'storeProducts.store'])
                ->whereIn('id', $chunk)
                ->get()
                ->keyBy('id');

            $toIndex  = new Collection();
            $toDelete = [];

            // Mirror IndexingService::updateProductInIndex (the per-row path this
            // replaces): index active products, and remove inactive/deleted/missing
            // ones from the index so an import that deactivates a product also drops
            // it from search. Both are batched — no per-doc refresh round-trips.
            foreach ($chunk as $id) {
                $product = $products->get($id);

                if ($product && $product->is_active && !$product->trashed()) {
                    $toIndex->push($product);
                } else {
                    $toDelete[] = $id;
                }
            }

            if ($toIndex->isNotEmpty()) {
                $this->indexingService->bulkIndex($toIndex);
            }

            if (!empty($toDelete)) {
                $this->indexingService->bulkDelete($toDelete);
            }

            // Advance the progress counter by the whole chunk (indexed + removed) so
            // it reaches products_to_index at 100%.
            $log->increment('products_indexed', count($chunk));
        }
    }

    // -------------------------------------------------------------------------
    // Categories
    // -------------------------------------------------------------------------

    private function importCategories($stream, Store $store): array
    {
        $handle    = $stream;
        $header    = array_map('trim', fgetcsv($handle));
        $created   = $updated = $failed = 0;
        $csvIdMap  = []; // csv category_id → internal id
        $parentMap = []; // csv category_id → csv parent_category_id (tiny: just ID pairs)
        $usedSlugs = [];

        // Single pass: upsert each row immediately, collect parent relationships as ID pairs only
        while (($raw = fgetcsv($handle)) !== false) {
            if (count($raw) < count($header)) {
                continue;
            }
            $row = array_combine($header, array_map('trim', $raw));

            try {
                $slug = $this->resolveUniqueSlug($row['slug'], $row['name'], $usedSlugs);
                $usedSlugs[] = $slug;

                $cat = Category::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name'       => $row['name'],
                        'slug'       => $slug,
                        'depth'      => (int) $row['depth'],
                        'sort_order' => (int) $row['sort_order'],
                        'is_active'  => filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN),
                    ]
                );

                $csvIdMap[$row['category_id']] = $cat->id;

                $parentCsvId = $row['parent_category_id'] ?? '';
                if ($parentCsvId !== '' && $parentCsvId !== '0') {
                    $parentMap[$row['category_id']] = $parentCsvId;
                }

                $cat->wasRecentlyCreated ? $created++ : $updated++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Category import row failed", ['row' => $row, 'error' => $e->getMessage()]);
            }
        }
        fclose($handle);

        // Resolve parent relationships using the in-memory ID maps (no re-reading the file)
        foreach ($parentMap as $csvId => $csvParentId) {
            $internalId       = $csvIdMap[$csvId] ?? null;
            $internalParentId = $csvIdMap[$csvParentId] ?? null;
            if ($internalId && $internalParentId) {
                Category::where('id', $internalId)->update(['parent_id' => $internalParentId]);
            }
        }

        // Link all imported categories to this store
        $i = 0;
        $pivotData = [];
        foreach ($csvIdMap as $internalId) {
            $pivotData[$internalId] = ['is_active' => true, 'sort_order' => $i++];
        }
        if (!empty($pivotData)) {
            $store->categories()->syncWithoutDetaching($pivotData);
        }

        return [$created, $updated, $failed, $csvIdMap];
    }

    // -------------------------------------------------------------------------
    // Products
    // -------------------------------------------------------------------------

    private function importProducts($stream, Store $store): array
    {
        $handle    = $stream;
        $header    = array_map('trim', fgetcsv($handle));
        $created   = $updated = $failed = 0;
        $skuCounts = []; // sku → count for duplicate detection (strings only, no row data)
        $productIds = [];
        $seenIds   = []; // deduplicate product IDs when the same SKU appears multiple times
        $rowNum    = 0;  // for periodic progress logging on large files

        // Single pass: upsert each row immediately.
        // Last occurrence of a duplicate SKU wins naturally because each row overwrites the previous.
        while (($raw = fgetcsv($handle)) !== false) {
            if (count($raw) < count($header)) {
                continue;
            }
            $row = array_combine($header, array_map('trim', $raw));
            $sku = $row['sku'];
            if ($sku === '') {
                continue;
            }

            // Heartbeat so the (slow) parse phase isn't a black box on large files.
            if (++$rowNum % 5000 === 0) {
                Log::info("Import products: {$rowNum} rows parsed ({$created} created, {$updated} updated)");
            }

            $skuCounts[$sku] = ($skuCounts[$sku] ?? 0) + 1;

            try {
                $data    = $this->parseProductRow($row);
                $product = Product::withTrashed()->where('sku', $sku)->first();

                if ($product) {
                    $product->update($data);
                    if ($product->trashed()) {
                        $product->restore();
                    }
                    $updated++;
                } else {
                    $data['slug'] = $this->uniqueSlug($data['slug'], $sku);
                    $product      = Product::create($data);
                    $created++;
                }

                StoreProduct::updateOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    [
                        'price'     => $data['price'],
                        'inventory' => $data['inventory'],
                        'is_active' => $data['is_active'],
                    ]
                );

                if (!isset($seenIds[$product->id])) {
                    $productIds[]           = $product->id;
                    $seenIds[$product->id]  = true;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning("Product import failed for SKU {$sku}", ['error' => $e->getMessage()]);
            }
        }
        fclose($handle);

        $duplicateSkus = array_values(array_keys(array_filter($skuCounts, fn ($c) => $c > 1)));
        sort($duplicateSkus);

        return [$created, $updated, $failed, $duplicateSkus, $productIds];
    }

    private function parseProductRow(array $row): array
    {
        return [
            'sku'         => $row['sku'],
            'name'        => $row['name'],
            'slug'        => $row['slug'] !== '' ? $row['slug'] : Str::slug($row['name']),
            'brand'       => $row['brand'] !== '' ? $row['brand'] : null,
            'description' => $row['description'] !== '' ? $row['description'] : null,
            'price'       => (float) ($row['price'] ?? 0),
            'inventory'   => (int) ($row['inventory'] ?? 0),
            'is_active'   => filter_var($row['is_active'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'attributes'  => $this->decodeJson($row['attributes'] ?? ''),
            'images'      => $this->decodeJson($row['images'] ?? ''),
            'meta'        => $this->decodeJson($row['meta'] ?? ''),
            'sales_rank'  => (int) ($row['sales_rank'] ?? 0),
        ];
    }

    private function decodeJson(string $value): ?array
    {
        if ($value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function uniqueSlug(string $candidate, string $sku): string
    {
        if ($candidate === '') {
            $candidate = Str::slug($sku);
        }

        $slug  = $candidate;
        $count = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $candidate . '-' . (++$count);
        }

        return $slug;
    }

    /**
     * Resolve a slug for a category row.
     * If the CSV slug is blank, derive it from the name.
     * Appends -2/-3/… only to avoid collisions with other rows in the same
     * CSV batch — NOT against the DB. Re-importing the same CSV regenerates
     * the same slug from the same name, so updateOrCreate naturally finds and
     * updates the existing record without creating duplicates.
     */
    private function resolveUniqueSlug(string $csvSlug, string $name, array $usedInBatch): string
    {
        $base = $csvSlug !== '' ? $csvSlug : Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }

        $slug  = $base;
        $count = 1;

        while (in_array($slug, $usedInBatch, true)) {
            $slug = $base . '-' . (++$count);
        }

        return $slug;
    }

    // -------------------------------------------------------------------------
    // Product categories
    // -------------------------------------------------------------------------

    private function importProductCategories($stream, array $csvIdMap): void
    {
        $handle = $stream;
        $header = array_map('trim', fgetcsv($handle));

        // Group pivot rows by sku
        $byProduct = [];

        while (($raw = fgetcsv($handle)) !== false) {
            if (count($raw) < count($header)) {
                continue;
            }
            $row        = array_combine($header, array_map('trim', $raw));
            $sku        = $row['sku'];
            $csvCatId   = $row['category_id'];
            $internalId = $csvIdMap[$csvCatId] ?? null;

            if ($sku === '' || $internalId === null) {
                continue;
            }

            $byProduct[$sku][$internalId] = [
                'is_primary' => filter_var($row['is_primary'] ?? '0', FILTER_VALIDATE_BOOLEAN),
            ];
        }
        fclose($handle);

        foreach ($byProduct as $sku => $pivotData) {
            $product = Product::withTrashed()->where('sku', $sku)->first();
            if (!$product) {
                continue;
            }
            $product->categories()->sync($pivotData);
        }
    }

    // -------------------------------------------------------------------------
    // Archive files after a successful import
    // -------------------------------------------------------------------------

    private function archiveFiles(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $base): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        foreach (self::FILES as $name) {
            $src = "{$base}/{$name}.csv";
            $dst = "{$base}/{$name}_done_{$timestamp}.csv";
            if (!$disk->exists($src)) {
                continue;
            }
            try {
                $disk->copy($src, $dst);
            } catch (\League\Flysystem\UnableToSetVisibility $e) {
                // mountpoint-s3 does not support chmod; copy itself succeeded —
                // verify the destination file exists before proceeding
            }
            if ($disk->exists($dst)) {
                $disk->delete($src);
            } else {
                Log::warning("Import archive copy failed, keeping original: {$src}");
            }
        }
    }
}
