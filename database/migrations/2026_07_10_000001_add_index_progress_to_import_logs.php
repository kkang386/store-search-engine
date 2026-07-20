<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            // Progress of the bulk-index phase (runs after parsing). products_to_index
            // is the total to (re)index; products_indexed climbs per 500-product batch.
            $table->unsignedInteger('products_to_index')->default(0)->after('products_failed');
            $table->unsignedInteger('products_indexed')->default(0)->after('products_to_index');
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropColumn(['products_to_index', 'products_indexed']);
        });
    }
};
