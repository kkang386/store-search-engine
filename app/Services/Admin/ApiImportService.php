<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * JSON upsert path for the import API. Mirrors the CSV ImportService semantics
 * (upsert products by SKU, categories by slug) but reads pre-parsed JSON and
 * bridges the two independent endpoints through the store-scoped external
 * category id stored on store_categories.
 */
class ApiImportService
{
    /**
     * Upsert a batch of categories for a store. Each row carries the client's
     * external category_id / parent_category_id (same ids the product endpoint
     * references). Returns ['created','updated','failed'].
     */
    public function upsertCategories(Store $store, array $rows): array
    {
        $created = $updated = $failed = 0;
        $extToInternal = [];   // external category_id => internal id (this batch)
        $parentMap     = [];   // external category_id => external parent_category_id
        $links         = [];   // internal id => ['is_active','sort_order','external_id']
        $usedSlugs     = [];

        DB::transaction(function () use (
            $store, $rows, &$created, &$updated, &$failed,
            &$extToInternal, &$parentMap, &$links, &$usedSlugs
        ) {
            foreach ($rows as $i => $row) {
                try {
                    $ext  = (string) ($row['category_id'] ?? '');
                    if ($ext === '') {
                        $failed++;
                        continue;
                    }
                    $slug = $this->resolveUniqueSlug((string) ($row['slug'] ?? ''), (string) ($row['name'] ?? ''), $usedSlugs);
                    $usedSlugs[] = $slug;

                    $cat = Category::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name'       => $row['name'] ?? '',
                            'slug'       => $slug,
                            'depth'      => (int) ($row['depth'] ?? 0),
                            'sort_order' => (int) ($row['sort_order'] ?? 0),
                            'is_active'  => $this->toBool($row['is_active'] ?? true),
                        ]
                    );

                    $extToInternal[$ext] = $cat->id;

                    $parent = $row['parent_category_id'] ?? null;
                    if ($parent !== null && (string) $parent !== '' && (string) $parent !== '0') {
                        $parentMap[$ext] = (string) $parent;
                    }

                    $links[$cat->id] = ['is_active' => true, 'sort_order' => $i, 'external_id' => $ext];

                    $cat->wasRecentlyCreated ? $created++ : $updated++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('API category upsert row failed', ['row' => $row, 'error' => $e->getMessage()]);
                }
            }

            // Resolve parent relationships. A parent may be in this batch or a
            // previously imported category (looked up via its stored external id).
            foreach ($parentMap as $ext => $parentExt) {
                $internalId = $extToInternal[$ext] ?? null;
                $parentId   = $extToInternal[$parentExt]
                    ?? $this->existingCategoryId($store, $parentExt);
                if ($internalId && $parentId && $internalId !== $parentId) {
                    Category::where('id', $internalId)->update(['parent_id' => $parentId]);
                }
            }

            // Keep external_id unique per store: if any of these external ids were
            // previously mapped to a different category, clear the stale mapping.
            $externalIds = array_column(array_values($links), 'external_id');
            if (!empty($externalIds)) {
                DB::table('store_categories')
                    ->where('store_id', $store->id)
                    ->whereIn('external_id', $externalIds)
                    ->whereNotIn('category_id', array_keys($links))
                    ->update(['external_id' => null]);
            }

            if (!empty($links)) {
                $store->categories()->syncWithoutDetaching($links);
            }
        });

        return compact('created', 'updated', 'failed');
    }

    /**
     * Upsert a batch of products (and their category links) for a store. Each
     * row's product_categories is an array of external category ids, the first
     * being primary. Per-product observer indexing is suppressed during the
     * batch; the caller is responsible for reindexing the returned affected ids.
     * Returns ['created','updated','failed','affected_ids'].
     */
    public function upsertProducts(Store $store, array $rows): array
    {
        $created = $updated = $failed = 0;
        $affectedIds = [];

        // external category id => internal id, for this store (built once).
        $catMap = DB::table('store_categories')
            ->where('store_id', $store->id)
            ->whereNotNull('external_id')
            ->pluck('category_id', 'external_id')
            ->all();

        ProductObserver::withoutIndexing(function () use (
            $store, $rows, $catMap, &$created, &$updated, &$failed, &$affectedIds
        ) {
            DB::transaction(function () use (
                $store, $rows, $catMap, &$created, &$updated, &$failed, &$affectedIds
            ) {
                foreach ($rows as $row) {
                    $sku = trim((string) ($row['sku'] ?? ''));
                    if ($sku === '') {
                        $failed++;
                        continue;
                    }

                    try {
                        $data    = $this->parseProductData($row);
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

                        $this->syncProductCategories($product, $row['product_categories'] ?? [], $catMap);

                        $affectedIds[$product->id] = true;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning("API product upsert failed for SKU {$sku}", ['error' => $e->getMessage()]);
                    }
                }
            });
        });

        return compact('created', 'updated', 'failed') + ['affected_ids' => array_keys($affectedIds)];
    }

    /**
     * Resolve external category ids to internal ids and sync the pivot. The
     * first resolvable category in the list is marked primary. Unresolvable
     * ids (unknown to this store) are skipped.
     */
    private function syncProductCategories(Product $product, mixed $externalIds, array $catMap): void
    {
        if (!is_array($externalIds)) {
            return;
        }

        $pivot   = [];
        $primary = true;
        foreach ($externalIds as $ext) {
            $internalId = $catMap[(string) $ext] ?? null;
            if ($internalId === null || isset($pivot[$internalId])) {
                continue;
            }
            $pivot[$internalId] = ['is_primary' => $primary];
            $primary = false;
        }

        $product->categories()->sync($pivot);
    }

    private function parseProductData(array $row): array
    {
        $name = (string) ($row['name'] ?? '');

        return [
            'sku'         => (string) $row['sku'],
            'name'        => $name,
            'slug'        => !empty($row['slug']) ? (string) $row['slug'] : Str::slug($name),
            'brand'       => !empty($row['brand']) ? (string) $row['brand'] : null,
            'description' => !empty($row['description']) ? (string) $row['description'] : null,
            'price'       => (float) ($row['price'] ?? 0),
            'inventory'   => (int) ($row['inventory'] ?? 0),
            'is_active'   => $this->toBool($row['is_active'] ?? true),
            'attributes'  => $this->arrayOrNull($row['attributes'] ?? null),
            'images'      => $this->arrayOrNull($row['images'] ?? null),
            'meta'        => $this->arrayOrNull($row['meta'] ?? null),
            'sales_rank'  => (int) ($row['sales_rank'] ?? 0),
        ];
    }

    private function arrayOrNull(mixed $value): ?array
    {
        return is_array($value) && $value !== [] ? $value : null;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    private function existingCategoryId(Store $store, string $externalId): ?int
    {
        $id = DB::table('store_categories')
            ->where('store_id', $store->id)
            ->where('external_id', $externalId)
            ->value('category_id');

        return $id ? (int) $id : null;
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

    private function resolveUniqueSlug(string $slug, string $name, array $usedInBatch): string
    {
        $base = $slug !== '' ? $slug : Str::slug($name);
        if ($base === '') {
            $base = 'category';
        }

        $resolved = $base;
        $count    = 1;
        while (in_array($resolved, $usedInBatch, true)) {
            $resolved = $base . '-' . (++$count);
        }

        return $resolved;
    }
}
