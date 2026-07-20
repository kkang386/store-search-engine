<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->default(1);
            $table->string('query');
            $table->string('session_id', 128)->nullable();
            $table->string('user_id', 128)->nullable();
            $table->integer('result_count')->default(0);
            $table->unsignedBigInteger('clicked_product_id')->nullable();
            $table->integer('click_position')->nullable();
            $table->boolean('converted')->default(false);
            $table->decimal('revenue', 12, 2)->nullable();
            $table->integer('latency_ms')->nullable();
            $table->json('filters_applied')->nullable();
            $table->json('facets_used')->nullable();
            $table->string('sort_order')->nullable();
            $table->string('endpoint', 32)->default('search');
            $table->timestamp('created_at');

            $table->index(['store_id', 'created_at']);
            $table->index('query');
            $table->index('session_id');
            $table->index('clicked_product_id');
            $table->index(['store_id', 'query', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};
