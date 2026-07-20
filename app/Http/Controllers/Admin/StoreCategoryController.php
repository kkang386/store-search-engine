<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StoreCategoryController extends Controller
{
    /**
     * All categories with a flag indicating whether each is linked to the store.
     */
    public function index(Store $store): JsonResponse
    {
        $linked = $store->categories()->pluck('store_categories.is_active', 'categories.id');

        $categories = Category::orderBy('depth')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Category $cat) => [
                'id'         => $cat->id,
                'name'       => $cat->name,
                'slug'       => $cat->slug,
                'parent_id'  => $cat->parent_id,
                'depth'      => $cat->depth,
                'sort_order' => $cat->sort_order,
                'is_active'  => $cat->is_active,
                'linked'     => $linked->has($cat->id),
                'pivot_active' => $linked->get($cat->id, true),
            ]);

        return response()->json([
            'store'      => $store->only('id', 'name', 'code'),
            'categories' => $categories,
        ]);
    }

    /**
     * Sync the store's categories. Accepts an array of objects:
     * [{ category_id, is_active, sort_order }]
     */
    public function sync(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'categories'              => ['required', 'array'],
            'categories.*.category_id'=> ['required', 'integer', 'exists:categories,id'],
            'categories.*.is_active'  => ['boolean'],
            'categories.*.sort_order' => ['integer'],
        ]);

        $sync = collect($request->input('categories'))
            ->keyBy('category_id')
            ->map(fn ($item) => [
                'is_active'  => $item['is_active'] ?? true,
                'sort_order' => $item['sort_order'] ?? 0,
            ])
            ->toArray();

        $store->categories()->sync($sync);

        Cache::forget("store_cat_ids:{$store->id}");

        return response()->json([
            'linked' => count($sync),
            'message' => 'Categories synced.',
        ]);
    }
}
