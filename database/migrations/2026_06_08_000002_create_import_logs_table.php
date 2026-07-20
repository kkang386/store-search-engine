<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->unsignedInteger('products_created')->default(0);
            $table->unsignedInteger('products_updated')->default(0);
            $table->unsignedInteger('products_failed')->default(0);
            $table->unsignedInteger('categories_created')->default(0);
            $table->unsignedInteger('categories_updated')->default(0);
            $table->unsignedInteger('categories_failed')->default(0);
            $table->json('duplicate_skus')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
