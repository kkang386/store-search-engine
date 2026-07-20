<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE import_logs MODIFY COLUMN status ENUM('running','completed','failed','cancelled') DEFAULT 'running'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE import_logs MODIFY COLUMN status ENUM('running','completed','failed') DEFAULT 'running'");
    }
};
