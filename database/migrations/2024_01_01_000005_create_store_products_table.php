<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->integer('inventory')->default(0);
            $table->boolean('is_active')->default(true);
            $table->decimal('boost', 5, 2)->default(1.0);
            $table->boolean('featured')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'product_id']);
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index(['store_id', 'is_active']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_products');
    }
};
