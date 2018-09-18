<?php
//c_config.php
//Verserion 1.1
//ES20130827 v1.1: Added two new constants for folder names for use in urls and such.

defined( 'APPLICATION_ENV' ) || define( 'APPLICATION_ENV', ( getenv( 'APPLICATION_ENV' ) ? getenv( 'APPLICATION_ENV' ) : 'production' ) );

define( "CONFIG_COMPANY_NAME", "Qatalyst" );
define( "SITE_URL", "qmleads.com" );

if( 'development' == APPLICATION_ENV ) {
	define( "SITE_ROOT", "/var/www/html/development/qmleads.com/" );
} else {
	define( "SITE_ROOT", "/var/www/html/production/qmleads.com/" );
}
//Database Connection Configuration, Production Environment
define( "DATABASE_NAME", 'dnrdmktg' );
define( "DATABASE_HOST", 'qmleads.cxkrvmxyvmna.us-east-1.rds.amazonaws.com' );
define( "ENVIRONMENT", 'prod' );
define( "MIGRATING", false );
define( "DATABASE_HOST_MIGRATION", '' );

//Applicable Usernames
$GLOBALS['connxSettings'] = array(
	'selectOnly' => array(
		'u' => 'dnrdmktg'
		, 'p' => '6MOuPjjT(zL}7hLnSSMkjUieWmSw]}_x',
	)
	, 'insertUpdate' => array(
		'u' => 'dnrdmktg'
		, 'p' => '6MOuPjjT(zL}7hLnSSMkjUieWmSw]}_x',
	),
);

$GLOBALS['database'] = array(
	'username' => 'dnrdmktg',
	'password' => 'Pumping#10Lead',
	'hostname' => 'localhost',
	'database' => 'dnrdmktg',
);

define( "ADMIN_FOLDER", "leadadmin" );
define( "ADMIN_ROOT", SITE_ROOT . DIRECTORY_SEPARATOR . "public_html" . DIRECTORY_SEPARATOR . ADMIN_FOLDER . DIRECTORY_SEPARATOR );
define( "LIVE_FOLDER", "live" );
define( "LIVE_ROOT", SITE_ROOT . DIRECTORY_SEPARATOR . "public_html" . DIRECTORY_SEPARATOR . LIVE_FOLDER . DIRECTORY_SEPARATOR );
define( "INCLUDES", SITE_ROOT . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR );
define( "UPLOADS_DIR", SITE_ROOT . 'uploads' . DIRECTORY_SEPARATOR );
define( "MYSQL_TIMEOUT", 10 );
define( "MAX_UPLOAD_SIZE", 10240000 );
$GLOBALS['dbconnx'] = '';
$GLOBALS['dbmigration'] = '';
define( "ADMINISTRATOR_EMAIL", 'ryan@playnicetogether.com' );
define( "MANAGER_EMAIL", 'david@qatalystinc.com,naomi@qatalystinc.com' );
define( "OWNER_EMAIL_FROM", 'david@qatalystinc.com' );
define( "OWNER_EMAIL", 'david@qatalystinc.com,naomi@qatalystinc.com' );
define( "PAYMENT_EMAIL", 'accounting@qatalystinc.com' );
define( "LEGACY_DB", false );
define( 'DB_TIMEZONE', 'UTC' );
define( 'LOCAL_TIMEZONE', 'America/New_York' );

define( 'COMPANY_LEGAL_NAME', 'Qatalyst, Inc.' );
define( 'COMPANY_ADDRESS_1', '100 4th Avenue South #138' );
define( 'COMPANY_ADDRESS_2', 'St Petersburg, FL 33701' );
define( 'MAX_PHONE_LEADS_VENDORS', 10 );
define( 'ENCRYPTION_KEY', 'TJ>VJ2!S%0?l`3s<}r5v7hL(Q(tm%nXo' );
define( 'IP_WHITELIST', array(
	'108.191.119.96', // Qatalyst Office
	'100.37.120.82', // Ryan home
) );