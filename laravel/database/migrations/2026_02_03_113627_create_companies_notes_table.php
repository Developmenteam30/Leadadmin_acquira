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
            CREATE TABLE IF NOT EXISTS `companies_notes` (
                `noteId` int unsigned NOT NULL AUTO_INCREMENT,
                `companyId` int unsigned NOT NULL,
                `userId` smallint unsigned NOT NULL,
                `timestamp` datetime NOT NULL,
                `note` text,
                PRIMARY KEY (`noteId`),
                KEY `fk_compnotes_userId_idx` (`userId`),
                KEY `idx_compnotes_compId` (`companyId`),
                CONSTRAINT `fk_compnotes_compId` FOREIGN KEY (`companyId`) REFERENCES `companies` (`idCompany`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_compnotes_userId` FOREIGN KEY (`userId`) REFERENCES `users` (`idUser`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies_notes');
    }
};
