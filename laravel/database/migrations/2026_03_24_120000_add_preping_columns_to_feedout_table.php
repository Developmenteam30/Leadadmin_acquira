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
        DB::statement("ALTER TABLE feedout ADD COLUMN prepingEnabled TINYINT(1) NOT NULL DEFAULT 0");
        DB::statement('ALTER TABLE feedout ADD COLUMN prepingUrl VARCHAR(1000) NULL');
        DB::statement("ALTER TABLE feedout ADD COLUMN prepingHttpMethod VARCHAR(4) NOT NULL DEFAULT 'POST'");
        DB::statement("ALTER TABLE feedout ADD COLUMN prepingAuthType VARCHAR(20) NOT NULL DEFAULT 'none'");
        DB::statement('ALTER TABLE feedout ADD COLUMN prepingAuthValue TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE feedout DROP COLUMN prepingAuthValue');
        DB::statement('ALTER TABLE feedout DROP COLUMN prepingAuthType');
        DB::statement('ALTER TABLE feedout DROP COLUMN prepingHttpMethod');
        DB::statement('ALTER TABLE feedout DROP COLUMN prepingUrl');
        DB::statement('ALTER TABLE feedout DROP COLUMN prepingEnabled');
    }
};
