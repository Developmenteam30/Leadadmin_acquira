<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to match the exact structure from the legacy database
        DB::statement('
            CREATE TABLE IF NOT EXISTS `verticals` (
                `verticalId` int unsigned NOT NULL AUTO_INCREMENT,
                `divisionId` int NOT NULL,
                `name` varchar(255) NOT NULL,
                PRIMARY KEY (`verticalId`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verticals');
    }
};
