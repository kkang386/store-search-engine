<?php

namespace App\Services\Search;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    /**
     * Expand a set of category IDs to include all of their descendants, so a
     * query rule scoped to a category also covers its subcategories.
     */
    public function withDescendants(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return [];
        }

        $childrenMap = $this->childrenMap();

        $seen  = [];
        $stack = $categoryIds;
        while ($stack) {
            $id = array_pop($stack);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach ($childrenMap[$id] ?? [] as $childId) {
                if (!isset($seen[$childId])) {
                    $stack[] = $childId;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * parent_id => [child ids]. Cached and keyed on search_index_version so an
     * import (which bumps that version) refreshes the tree; 600s TTL backstop.
     */
    private function childrenMap(): array
    {
        $version = Cache::get('search_index_version', 0);

        return Cache::remember("category_children_map:{$version}", 600, function () {
            $map = [];
            foreach (Category::query()->get(['id', 'parent_id']) as $cat) {
                if ($cat->parent_id !== null) {
                    $map[(int) $cat->parent_id][] = (int) $cat->id;
                }
            }
            return $map;
        });
    }
}
