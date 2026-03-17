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
            CREATE TABLE IF NOT EXISTS `companies_divisions` (
                `companyId` int unsigned NOT NULL,
                `divisionId` int unsigned NOT NULL,
                PRIMARY KEY (`companyId`,`divisionId`),
                KEY `fk_compdiv_divisionId_idx` (`divisionId`),
                CONSTRAINT `fk_compdiv_companyId` FOREIGN KEY (`companyId`) REFERENCES `companies` (`idCompany`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_compdiv_divisionId` FOREIGN KEY (`divisionId`) REFERENCES `divisions` (`divisionId`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies_divisions');
    }
};
