<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks each import-API upsert request so the caller can poll its status by
 * request_id. The payload is stored here (not in the queue) so the processing
 * job stays light, and is cleared once the request reaches a terminal state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->unsignedBigInteger('store_id')->index();
            $table->string('type');                       // categories | products
            $table->string('status')->default('in-progress'); // in-progress | completed | error
            $table->longText('payload')->nullable();      // received rows; cleared when terminal
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('created_count')->nullable();
            $table->unsignedInteger('updated_count')->nullable();
            $table->unsignedInteger('failed_count')->nullable();
            $table->unsignedInteger('indexed_count')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_requests');
    }
};
