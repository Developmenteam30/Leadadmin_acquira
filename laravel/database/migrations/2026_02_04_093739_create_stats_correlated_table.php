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
            CREATE TABLE IF NOT EXISTS `stats_correlated` (
                `idFeedIn` int NOT NULL,
                `idFeedOut` int NOT NULL,
                `url` varchar(255) NOT NULL,
                `stamp` date NOT NULL,
                `costPerLead` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000',
                `revenuePerLead` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000',
                `accepted` int unsigned NOT NULL DEFAULT '0',
                `billable` int unsigned NOT NULL DEFAULT '0',
                `rejected` int unsigned NOT NULL DEFAULT '0',
                UNIQUE KEY `feedUrl` (`idFeedIn`,`idFeedOut`,`url`,`stamp`),
                KEY `idFeedIn` (`idFeedIn`),
                KEY `idFeedOut` (`idFeedOut`),
                KEY `stamp` (`stamp`),
                KEY `url` (`url`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_correlated');
    }
};
