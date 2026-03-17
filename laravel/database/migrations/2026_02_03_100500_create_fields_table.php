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
        DB::statement("
            CREATE TABLE IF NOT EXISTS `fields` (
                `fieldId` mediumint unsigned NOT NULL AUTO_INCREMENT,
                `fieldName` varchar(255) NOT NULL,
                `fieldType` enum('system','custom','derived','outbound','outbound-export','inbound-export') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'custom',
                `fieldDescription` varchar(255) DEFAULT NULL,
                `fieldFormat` varchar(255) DEFAULT NULL,
                `fieldDefinition` varchar(255) DEFAULT 'varchar(255)',
                PRIMARY KEY (`fieldId`),
                UNIQUE KEY `fields_UN` (`fieldName`,`fieldType`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
