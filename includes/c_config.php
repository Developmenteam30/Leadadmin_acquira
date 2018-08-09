<?php
//c_config.php
//Verserion 1.1
//ES20130827 v1.1: Added two new constants for folder names for use in urls and such.

define( "CONFIG_COMPANY_NAME", "Qatalyst" );
define( "SITE_URL", "qmleads.com" );

$folder_delimiter = "/";
if( isset( $_SERVER['SERVER_ADDR'] ) && $_SERVER['SERVER_ADDR'] == '127.0.0.1' ) { //If you are deploying this to test in a local dev environment,
	//This will be the site root.
	define( "SITE_ROOT", "M:\\WAMP\\wamp\\www\\qmleads.com\\" );
	//Uncomment this line if you are running development in a windows environment.
	$folder_delimiter = "\\";
	//Database Connection Configuration, Dev Environment
	define( "DATABASE_NAME", 'dnrdmktg' );
	define( "DATABASE_HOST", 'localhost' );
	define( "ENVIRONMENT", 'dev' );
	define( "MIGRATING", false );
	define( "DATABASE_HOST_MIGRATION", '' );
	//Applicable Usernames
	$username = 'root';
	$password = 'hammertime10';
	$GLOBALS['databaseName'] = 'dnrdmktg';
	$GLOBALS['connxSettings'] = array( //Don't really care for development
	                                   'selectOnly' => array(
		                                   'u' => 'root'
		                                   , 'p' => 'hammertime10',
	                                   )
	                                   , 'insertUpdate' => array(
			'u' => 'root'
			, 'p' => 'hammertime10',
		),
	);
} else { //Otherwise we're in the production environment, and this is the site root.
	define( "SITE_ROOT", "/var/www/html/production/qmleads.com/" );
	//Uncomment this line if you are running production in a windows environment.
	//$folder_delimiter = "\\";
	//Database Connection Configuration, Production Environment
	define( "DATABASE_NAME", 'dnrdmktg' );
	//define("DATABASE_HOST", 'qmleads.ck44eyk7mgen.us-east-1.rds.amazonaws.com'); // PNT
	define( "DATABASE_HOST", 'qmleads.cxkrvmxyvmna.us-east-1.rds.amazonaws.com' ); // QM
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
}
define( "FD", $folder_delimiter );
define( "ADMIN_FOLDER", "leadadmin" );
define( "ADMIN_ROOT", SITE_ROOT . FD . "public_html" . FD . ADMIN_FOLDER . FD );
define( "LIVE_FOLDER", "live" );
define( "LIVE_ROOT", SITE_ROOT . FD . "public_html" . FD . LIVE_FOLDER . FD );
define( "INCLUDES", SITE_ROOT . FD . "includes" . FD );
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