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
            CREATE TABLE IF NOT EXISTS `stats_inbound` (
                `idFeedIn` int NOT NULL,
                `url` varchar(255) NOT NULL,
                `stamp` date NOT NULL,
                `accepted` int DEFAULT '0',
                `rejected` int DEFAULT '0',
                UNIQUE KEY `feedUrl` (`idFeedIn`,`url`,`stamp`),
                KEY `idFeedIn` (`idFeedIn`),
                KEY `url` (`url`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_inbound');
    }
};
