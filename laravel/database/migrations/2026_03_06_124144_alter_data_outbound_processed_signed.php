<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. Allow processed=-1 for live records (legacy convention).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE data_outbound MODIFY processed TINYINT NOT NULL DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE data_outbound MODIFY processed TINYINT UNSIGNED NOT NULL DEFAULT 0');
    }
};
