<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    private array $categories = [
        'Electronics' => ['Cameras', 'Televisions', 'Audio', 'Computers', 'Smartphones', 'Tablets'],
        'Cameras' => ['Mirrorless Cameras', 'DSLR Cameras', 'Point & Shoot', 'Camera Lenses', 'Camera Accessories'],
        'Audio' => ['Headphones', 'Speakers', 'Soundbars', 'Earbuds'],
        'Computers' => ['Laptops', 'Desktops', 'Monitors', 'Keyboards', 'Mice', 'Storage'],
    ];

    // Categories excluded per store (by slug). Omitting a store = gets all categories.
    private array $storeExclusions = [
        // CA: same full range as US — no exclusions
        'ca' => [],
        // UK: narrower assortment — no desktop-focused items or tablets
        'uk' => ['desktops', 'storage', 'tablets', 'point-shoot'],
    ];

    public function run(): void
    {
        foreach ($this->categories as $parent => $children) {
            $parentCat = Category::firstOrCreate(
                ['slug' => Str::slug($parent)],
                ['name' => $parent, 'depth' => 0, 'is_active' => true]
            );

            foreach ($children as $child) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($child)],
                    ['name' => $child, 'parent_id' => $parentCat->id, 'depth' => 1, 'is_active' => true]
                );
            }
        }

        $this->seedStoreCategories();
    }

    private function seedStoreCategories(): void
    {
        $stores = Store::withTrashed()->get()->keyBy('code');
        $allCategories = Category::all();

        if ($stores->isEmpty()) {
            return;
        }

        foreach ($stores as $code => $store) {
            $exclusions = $this->storeExclusions[$code] ?? [];

            $pivot = $allCategories
                ->filter(fn (Category $cat) => !in_array($cat->slug, $exclusions, true))
                ->mapWithKeys(fn (Category $cat, $i) => [
                    $cat->id => ['is_active' => true, 'sort_order' => $i],
                ])
                ->toArray();

            // sync without detaching so re-running the seeder is idempotent
            $store->categories()->syncWithoutDetaching($pivot);
        }
    }
}
