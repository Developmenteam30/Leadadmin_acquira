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
            CREATE TABLE IF NOT EXISTS `feedinc` (
                `idFeedIn` int NOT NULL AUTO_INCREMENT,
                `label` varchar(255) DEFAULT NULL,
                `description` varchar(100) DEFAULT NULL,
                `idCompany` int DEFAULT NULL,
                `required` varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'email;ip;url;stamp',
                `allowedFields` varchar(1000) DEFAULT 'listcode;url;ip;stamp;email;fname;lname;addr;addr2;city;state;zip;dob;gender;landline;cellphone',
                `password` varchar(16) DEFAULT NULL,
                `dedupeEmail` enum('0','1') DEFAULT '0',
                `dedupeLandline` enum('0','1') DEFAULT '0',
                `dedupeCellphone` enum('0','1') DEFAULT '0',
                `rejectOldLeads` tinyint DEFAULT '1',
                `rejectOldLeadsMaxAge` varchar(50) DEFAULT '7 Days Ago',
                `dedupeAcross` varchar(32) DEFAULT NULL,
                `filterTypeUrl` enum('reject','accept') DEFAULT NULL,
                `filterUrl` varchar(5000) DEFAULT NULL,
                `filterTypeSiftLogic` enum('reject','accept') DEFAULT NULL,
                `filterSiftLogic` varchar(5000) DEFAULT NULL,
                `notifications` enum('0','1') DEFAULT '1',
                `status` enum('active','hidden','retired') NOT NULL DEFAULT 'active',
                `chokePercent` tinyint unsigned NOT NULL DEFAULT '0',
                `feedCategory` varchar(255) NOT NULL DEFAULT 'email',
                `dailyLimit` mediumint unsigned DEFAULT NULL,
                `custom1Label` varchar(255) DEFAULT NULL,
                `custom2Label` varchar(255) DEFAULT NULL,
                `custom3Label` varchar(255) DEFAULT NULL,
                `custom4Label` varchar(255) DEFAULT NULL,
                `custom5Label` varchar(255) DEFAULT NULL,
                `custom6Label` varchar(255) DEFAULT NULL,
                `costPerLead` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000',
                `notifyThresholdCount` int unsigned NOT NULL DEFAULT '0',
                `notifyThresholdDays` varchar(100) DEFAULT NULL,
                `notifyThresholdLastSent` datetime DEFAULT NULL,
                `notifyThresholdTime` time DEFAULT NULL,
                `salesperson` smallint unsigned DEFAULT NULL,
                `paused` tinyint unsigned NOT NULL DEFAULT '0',
                `pauseMessage` varchar(255) DEFAULT NULL,
                `filterTypeDNCScrub` varchar(5000) DEFAULT NULL,
                `timezone` varchar(255) NOT NULL DEFAULT 'America/New_York',
                `timeskew` varchar(255) DEFAULT NULL,
                `filterState` json DEFAULT NULL,
                `lookbackPeriod` tinyint unsigned NOT NULL DEFAULT '90',
                `pingTimeout` int unsigned NOT NULL DEFAULT '300',
                `requiredPingFields` varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
                `allowedPingFields` varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
                `minimumBirthAge` tinyint unsigned DEFAULT NULL,
                `maximumBirthAge` tinyint unsigned DEFAULT NULL,
                `filterZip` json DEFAULT NULL,
                `leadStatus` varchar(10) DEFAULT NULL,
                PRIMARY KEY (`idFeedIn`),
                KEY `idx_idCompany` (`idCompany`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedinc');
    }
};
