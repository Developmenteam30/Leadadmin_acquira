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
            CREATE TABLE IF NOT EXISTS `feedout` (
                `idFeedOut` int NOT NULL AUTO_INCREMENT,
                `label` varchar(30) DEFAULT NULL,
                `description` varchar(100) DEFAULT NULL,
                `idCompany` int unsigned DEFAULT NULL,
                `feedType` enum('curlPOST','curlGET','JSON','csvString','soapPOST','curlPOST-urlencoded','xmlPOST') DEFAULT NULL,
                `postUrl` varchar(1000) DEFAULT NULL,
                `staticFields` varchar(1000) DEFAULT NULL,
                `varFields` varchar(1000) DEFAULT NULL,
                `fieldMap` text,
                `cron` enum('0','1') DEFAULT '0',
                `cronTiming` int DEFAULT '1',
                `successString` varchar(50) DEFAULT NULL,
                `throttle` int DEFAULT '100',
                `urlassignments` varchar(1000) DEFAULT NULL,
                `dailyLimit` mediumint unsigned DEFAULT NULL,
                `delay` int unsigned DEFAULT NULL,
                `queued` int DEFAULT '0',
                `status` enum('active','hidden','retired') NOT NULL DEFAULT 'active',
                `feedCategory` varchar(255) NOT NULL DEFAULT 'email',
                `delayDump` tinyint unsigned NOT NULL DEFAULT '0',
                `notifyThresholdCount` int unsigned NOT NULL DEFAULT '0',
                `notifyThresholdTime` time DEFAULT NULL,
                `notifyThresholdLastSent` datetime DEFAULT NULL,
                `notifyThresholdDays` varchar(100) DEFAULT NULL,
                `revenuePerLead` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000',
                `launchDate` date DEFAULT NULL,
                `costPerLeadOverride` decimal(10,4) unsigned DEFAULT NULL,
                `valueMap` text,
                `salesperson` smallint unsigned DEFAULT NULL,
                `xmlDTD` mediumtext,
                `processingSchedule` mediumtext,
                `staticFieldsJSON` json DEFAULT NULL,
                `varFieldsJSON` json DEFAULT NULL,
                `timezone` varchar(255) NOT NULL DEFAULT 'UTC',
                `leadStatus` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`idFeedOut`),
                KEY `feedout_idCompany_IDX` (`idCompany`),
                CONSTRAINT `feedout_companies_FK` FOREIGN KEY (`idCompany`) REFERENCES `companies` (`idCompany`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedout');
    }
};
