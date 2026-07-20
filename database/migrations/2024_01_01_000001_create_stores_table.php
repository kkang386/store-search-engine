<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('region', 32)->nullable();
            $table->string('locale', 10)->default('en_US');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
