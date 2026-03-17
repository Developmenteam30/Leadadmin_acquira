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
        DB::statement("
            CREATE TABLE IF NOT EXISTS `companies_verticals` (
                `companyId` int unsigned NOT NULL,
                `verticalId` int unsigned NOT NULL,
                PRIMARY KEY (`companyId`,`verticalId`),
                KEY `fk_compvert_verticalId_idx` (`verticalId`),
                CONSTRAINT `fk_compvert_companyId` FOREIGN KEY (`companyId`) REFERENCES `companies` (`idCompany`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_compvert_verticalId` FOREIGN KEY (`verticalId`) REFERENCES `verticals` (`verticalId`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies_verticals');
    }
};
