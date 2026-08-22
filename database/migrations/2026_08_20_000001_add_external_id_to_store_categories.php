<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The import API upserts categories and products in two independent calls, so
 * the client's external category_id must be persisted to bridge them: the
 * category endpoint records it here (per store), and the product endpoint
 * resolves each product_categories entry to an internal category id through it.
 * Nullable — categories imported via CSV simply have no external id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('category_id');
            $table->index(['store_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'external_id']);
            $table->dropColumn('external_id');
        });
    }
};
