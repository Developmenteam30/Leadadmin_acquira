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
            CREATE TABLE IF NOT EXISTS `companies` (
                `idCompany` int unsigned NOT NULL AUTO_INCREMENT,
                `name` text,
                `url` varchar(255) DEFAULT NULL,
                `note` varchar(1000) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `city` varchar(255) DEFAULT NULL,
                `state` varchar(255) DEFAULT NULL,
                `zipcode` varchar(10) DEFAULT NULL,
                `country` int unsigned NOT NULL DEFAULT '236',
                `main_name` varchar(255) DEFAULT NULL,
                `main_phone` varchar(30) DEFAULT NULL,
                `main_email` varchar(255) DEFAULT NULL,
                `acct_name` varchar(255) DEFAULT NULL,
                `acct_phone` varchar(30) DEFAULT NULL,
                `acct_email` varchar(255) DEFAULT NULL,
                `tech_name` varchar(255) DEFAULT NULL,
                `tech_phone` varchar(30) DEFAULT NULL,
                `tech_email` varchar(255) DEFAULT NULL,
                `returns_name` varchar(255) DEFAULT NULL,
                `returns_phone` varchar(255) DEFAULT NULL,
                `returns_email` varchar(255) DEFAULT NULL,
                `accountManager` smallint unsigned DEFAULT NULL,
                `status` enum('active','hidden','retired') NOT NULL DEFAULT 'active',
                `isPublisher` tinyint unsigned NOT NULL DEFAULT '0',
                `isAdvertiser` tinyint unsigned NOT NULL DEFAULT '0',
                `isCallCenter` tinyint unsigned DEFAULT '0',
                `accountOpener` smallint unsigned DEFAULT NULL,
                `salesperson` smallint unsigned DEFAULT NULL,
                `paymentTerms` varchar(255) DEFAULT NULL,
                `costPerLead` decimal(10,4) unsigned NOT NULL DEFAULT '0.0000',
                `dialer_report_type` varchar(255) DEFAULT NULL,
                `dialer_product_id` bigint unsigned DEFAULT NULL,
                `dialer_payment_type_id` bigint unsigned DEFAULT NULL,
                `dialer_billable_rate` decimal(6,2) DEFAULT NULL,
                `dialer_payable_rate` decimal(6,2) DEFAULT NULL,
                `dialer_bonus_rate` decimal(6,2) DEFAULT NULL,
                `dialer_integer` smallint unsigned DEFAULT NULL,
                PRIMARY KEY (`idCompany`),
                KEY `idx_companies_id` (`idCompany`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
