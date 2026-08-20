<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Query rules pin/exclude/boost products by SKU instead of internal product id.
 * SKUs are the human-facing natural key; they are resolved to product ids
 * (= ES _id) at query time. Backfills existing rules by mapping their stored
 * product_ids back to SKUs via the products table (best-effort — rows whose
 * products no longer exist keep an empty set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('query_rules', function (Blueprint $table) {
            $table->json('skus')->nullable()->after('product_ids');
        });

        foreach (DB::table('query_rules')->whereNotNull('product_ids')->get() as $rule) {
            $ids = json_decode($rule->product_ids, true) ?: [];
            if (empty($ids)) {
                continue;
            }
            $skus = DB::table('products')->whereIn('id', $ids)->pluck('sku')->all();
            DB::table('query_rules')->where('id', $rule->id)
                ->update(['skus' => json_encode(array_values($skus))]);
        }

        Schema::table('query_rules', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }

    public function down(): void
    {
        Schema::table('query_rules', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('skus');
        });

        foreach (DB::table('query_rules')->whereNotNull('skus')->get() as $rule) {
            $skus = json_decode($rule->skus, true) ?: [];
            if (empty($skus)) {
                continue;
            }
            $ids = DB::table('products')->whereIn('sku', $skus)->pluck('id')->all();
            DB::table('query_rules')->where('id', $rule->id)
                ->update(['product_ids' => json_encode(array_values($ids))]);
        }

        Schema::table('query_rules', function (Blueprint $table) {
            $table->dropColumn('skus');
        });
    }
};
