<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE feedout ADD COLUMN responseType ENUM('realtime', 'marketplace') NOT NULL DEFAULT 'realtime'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE feedout DROP COLUMN responseType');
    }
};
