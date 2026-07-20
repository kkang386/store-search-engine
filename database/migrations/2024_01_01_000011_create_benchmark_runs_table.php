<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 64)->unique();
            $table->string('version')->nullable();
            $table->string('git_commit', 40)->nullable();
            $table->json('metrics');
            $table->json('latency');
            $table->json('config');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->text('error')->nullable();
            $table->integer('total_queries')->default(0);
            $table->integer('failed_queries')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at');

            $table->index('run_id');
            $table->index('created_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_runs');
    }
};
