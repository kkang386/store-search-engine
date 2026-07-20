<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            ['name' => 'US Store', 'code' => 'us', 'region' => 'NA', 'locale' => 'en_US', 'currency' => 'USD'],
            ['name' => 'CA Store', 'code' => 'ca', 'region' => 'NA', 'locale' => 'en_CA', 'currency' => 'CAD'],
            ['name' => 'UK Store', 'code' => 'uk', 'region' => 'EU', 'locale' => 'en_GB', 'currency' => 'GBP'],
        ];

        foreach ($stores as $store) {
            Store::firstOrCreate(['code' => $store['code']], $store);
        }
    }
}
