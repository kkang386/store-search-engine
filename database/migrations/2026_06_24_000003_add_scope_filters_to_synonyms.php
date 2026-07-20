<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('synonyms', function (Blueprint $table) {
            $table->json('include_category_ids')->nullable()->after('is_active');
            $table->json('exclude_category_ids')->nullable()->after('include_category_ids');
            $table->json('include_brands')->nullable()->after('exclude_category_ids');
        });
    }

    public function down(): void
    {
        Schema::table('synonyms', function (Blueprint $table) {
            $table->dropColumn(['include_category_ids', 'exclude_category_ids', 'include_brands']);
        });
    }
};
