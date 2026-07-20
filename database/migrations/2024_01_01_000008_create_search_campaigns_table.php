<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['boost', 'banner', 'exclusion', 'synonym'])->default('boost');
            $table->json('query_patterns')->nullable();
            $table->json('product_ids')->nullable();
            $table->decimal('boost_factor', 5, 2)->nullable();
            $table->json('banner_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index(['store_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_campaigns');
    }
};
