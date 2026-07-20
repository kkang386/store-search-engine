<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Synonyms are now global and backed by the Elasticsearch synonym set.
        // Start fresh: wipe existing rows (per-store/scoped data is obsolete)...
        DB::table('synonyms')->delete();

        // ...and drop the store scoping + per-synonym category/brand scope columns.
        Schema::table('synonyms', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id']);
            $table->dropColumn([
                'store_id',
                'include_category_ids',
                'exclude_category_ids',
                'include_brands',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('synonyms', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('id');
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index('store_id');
            $table->json('include_category_ids')->nullable();
            $table->json('exclude_category_ids')->nullable();
            $table->json('include_brands')->nullable();
        });
    }
};
