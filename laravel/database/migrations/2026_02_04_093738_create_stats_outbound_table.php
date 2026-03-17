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
            CREATE TABLE IF NOT EXISTS `stats_outbound` (
                `idFeedOut` int NOT NULL,
                `url` varchar(255) NOT NULL,
                `stamp` date NOT NULL,
                `accepted` int unsigned NOT NULL DEFAULT '0',
                `rejected` int unsigned NOT NULL DEFAULT '0',
                `queued` int unsigned NOT NULL DEFAULT '0',
                `billable` int unsigned NOT NULL DEFAULT '0',
                UNIQUE KEY `feedUrl` (`idFeedOut`,`url`,`stamp`),
                KEY `idx_feedUrl` (`idFeedOut`,`url`),
                KEY `url` (`url`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_outbound');
    }
};
