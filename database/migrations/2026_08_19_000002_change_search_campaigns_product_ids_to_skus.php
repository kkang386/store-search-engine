<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search campaigns target products by SKU instead of internal product id, to
 * match query rules. SKUs are resolved to product ids (= ES _id) at query time.
 * Backfills existing campaigns by mapping their stored product_ids back to SKUs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_campaigns', function (Blueprint $table) {
            $table->json('skus')->nullable()->after('product_ids');
        });

        foreach (DB::table('search_campaigns')->whereNotNull('product_ids')->get() as $campaign) {
            $ids = json_decode($campaign->product_ids, true) ?: [];
            if (empty($ids)) {
                continue;
            }
            $skus = DB::table('products')->whereIn('id', $ids)->pluck('sku')->all();
            DB::table('search_campaigns')->where('id', $campaign->id)
                ->update(['skus' => json_encode(array_values($skus))]);
        }

        Schema::table('search_campaigns', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }

    public function down(): void
    {
        Schema::table('search_campaigns', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('skus');
        });

        foreach (DB::table('search_campaigns')->whereNotNull('skus')->get() as $campaign) {
            $skus = json_decode($campaign->skus, true) ?: [];
            if (empty($skus)) {
                continue;
            }
            $ids = DB::table('products')->whereIn('sku', $skus)->pluck('id')->all();
            DB::table('search_campaigns')->where('id', $campaign->id)
                ->update(['product_ids' => json_encode(array_values($ids))]);
        }

        Schema::table('search_campaigns', function (Blueprint $table) {
            $table->dropColumn('skus');
        });
    }
};
