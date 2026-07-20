<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private array $sampleProducts = [
        ['name' => 'Canon EOS R6 Mark II', 'brand' => 'Canon', 'price' => 1999.99, 'category' => 'Mirrorless Cameras', 'attrs' => ['color' => 'Black', 'sensor_size' => 'Full Frame', 'mount' => 'RF']],
        ['name' => 'Sony Alpha A7 IV', 'brand' => 'Sony', 'price' => 2399.99, 'category' => 'Mirrorless Cameras', 'attrs' => ['color' => 'Black', 'sensor_size' => 'Full Frame', 'mount' => 'E']],
        ['name' => 'Nikon Z6 III', 'brand' => 'Nikon', 'price' => 1799.99, 'category' => 'Mirrorless Cameras', 'attrs' => ['color' => 'Black', 'sensor_size' => 'Full Frame', 'mount' => 'Z']],
        ['name' => 'Canon RF 24-70mm f/2.8L', 'brand' => 'Canon', 'price' => 2299.99, 'category' => 'Camera Lenses', 'attrs' => ['mount' => 'RF', 'aperture' => 'f/2.8']],
        ['name' => 'Samsung 55" 4K QLED TV', 'brand' => 'Samsung', 'price' => 899.99, 'category' => 'Televisions', 'attrs' => ['color' => 'Black', 'resolution' => '4K', 'panel_type' => 'QLED']],
        ['name' => 'LG 27" 4K Monitor', 'brand' => 'LG', 'price' => 399.99, 'category' => 'Monitors', 'attrs' => ['resolution' => '4K', 'refresh_rate' => '60Hz', 'panel_type' => 'IPS']],
        ['name' => 'Samsung 980 Pro 1TB SSD', 'brand' => 'Samsung', 'price' => 89.99, 'category' => 'Storage', 'attrs' => ['storage' => '1TB', 'interface' => 'NVMe']],
        ['name' => 'Apple AirPods Pro 2', 'brand' => 'Apple', 'price' => 249.99, 'category' => 'Headphones', 'attrs' => ['color' => 'White', 'connectivity' => 'Bluetooth']],
        ['name' => 'Sony WH-1000XM5', 'brand' => 'Sony', 'price' => 349.99, 'category' => 'Headphones', 'attrs' => ['color' => 'Black', 'connectivity' => 'Bluetooth']],
        ['name' => 'Logitech MX Keys Keyboard', 'brand' => 'Logitech', 'price' => 109.99, 'category' => 'Keyboards', 'attrs' => ['connectivity' => 'Wireless', 'color' => 'Space Gray']],
    ];

    public function run(): void
    {
        $stores = Store::all();
        $defaultStore = $stores->first();

        foreach ($this->sampleProducts as $i => $data) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($data['category'])],
                ['name' => $data['category'], 'depth' => 1, 'is_active' => true]
            );

            $product = Product::firstOrCreate(
                ['sku' => 'SEED-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $data['name'],
                    'slug' => Str::slug($data['name']),
                    'brand' => $data['brand'],
                    'price' => $data['price'],
                    'inventory' => rand(0, 100),
                    'rating' => round(rand(30, 50) / 10, 1),
                    'review_count' => rand(10, 500),
                    'sales_rank' => rand(100, 10000),
                    'is_active' => true,
                    'attributes' => $data['attrs'],
                ]
            );

            $product->categories()->syncWithoutDetaching([$category->id => ['is_primary' => true]]);

            foreach ($stores as $store) {
                StoreProduct::firstOrCreate(
                    ['store_id' => $store->id, 'product_id' => $product->id],
                    [
                        'price' => $data['price'],
                        'inventory' => rand(0, 50),
                        'is_active' => true,
                        'boost' => 1.0,
                    ]
                );
            }
        }
    }
}
