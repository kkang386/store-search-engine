<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('query_pattern');
            $table->enum('match_type', ['exact', 'contains', 'starts_with', 'regex'])->default('exact');
            $table->enum('action', ['pin', 'exclude', 'boost', 'bury', 'redirect', 'banner'])->default('pin');
            $table->json('product_ids')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('boost_factor', 5, 2)->nullable();
            $table->integer('pin_position')->nullable();
            $table->string('redirect_url')->nullable();
            $table->string('banner_html')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index(['store_id', 'is_active']);
            $table->index('query_pattern');
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_rules');
    }
};
