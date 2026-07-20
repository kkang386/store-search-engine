<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synonyms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->enum('type', ['equivalent', 'one_way'])->default('equivalent');
            $table->json('terms');
            $table->string('from_term')->nullable();
            $table->string('to_term')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();
            $table->index('store_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synonyms');
    }
};
