<?php

namespace App\Services\Search\Concerns;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Query rules and campaigns reference products by SKU (the human-facing key).
 * This resolves those SKUs to product ids (= ES _id) so they can be fed to the
 * pin/exclude/boost ES clauses. Shared by SearchService and SuggestService.
 */
trait ResolvesRuleSkus
{
    /**
     * Map query-rule SKUs to product ids (= ES _id). Returns a [sku => id] map;
     * SKUs with no matching product are simply absent. Matching is case-
     * insensitive via the products table collation. Result is short-lived cached
     * and busted on reindex so a re-imported product's id is picked up.
     */
    protected function resolveSkusToIds(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('trim', $skus))));
        if (empty($skus)) {
            return [];
        }

        $version = Cache::get('search_index_version', 0);
        $cacheKey = 'rule_skus:v' . $version . ':' . md5(implode(',', $skus));

        return Cache::remember($cacheKey, 60, function () use ($skus) {
            return Product::whereIn('sku', $skus)
                ->pluck('id', 'sku')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }
}
