<?php 
//c_config.php
//Verserion 1.1
//ES20130827 v1.1: Added two new constants for folder names for use in urls and such.

define("CONFIG_COMPANY_NAME", "Qatalyst Media");
define("SITE_URL", "qmleads.com");

$folder_delimiter = "/";
if(isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] == '127.0.0.1'){ //If you are deploying this to test in a local dev environment, 
	//This will be the site root.
	define("SITE_ROOT", "M:\\WAMP\\wamp\\www\\qmleads.com\\");
	//Uncomment this line if you are running development in a windows environment.
	$folder_delimiter = "\\";
	//Database Connection Configuration, Dev Environment
	define("DATABASE_NAME", 'dnrdmktg');
	define("DATABASE_HOST", 'localhost');
	define("ENVIRONMENT", 'dev');
	define("MIGRATING", false);
	define("DATABASE_HOST_MIGRATION", '');
	//Applicable Usernames
	$username 	= 'root';
	$password 	= 'hammertime10';
	$GLOBALS['databaseName'] = 'dnrdmktg';
	$GLOBALS['connxSettings'] = array( //Don't really care for development
		'selectOnly' => array(
			  'u' => 'root'
			, 'p' => 'hammertime10'
		)
		, 'insertUpdate' => array(
			  'u' => 'root'
			, 'p' => 'hammertime10'
		)
	);
} else { //Otherwise we're in the production environment, and this is the site root.
	define("SITE_ROOT", "/var/www/html/qmleads.com/");
	//Uncomment this line if you are running production in a windows environment.
	//$folder_delimiter = "\\";
	//Database Connection Configuration, Production Environment
	define("DATABASE_NAME", 'dnrdmktg');
	define("DATABASE_HOST", 'localhost');
	define("ENVIRONMENT", 'prod');
	define("MIGRATING", false);
	define("DATABASE_HOST_MIGRATION", '');
	//Applicable Usernames
	$GLOBALS['connxSettings'] = array(
		'selectOnly' => array( 
			  'u' => 'dnrdmktg'
			, 'p' => 'Pumping#10Lead'
		)
		, 'insertUpdate' => array( 
			  'u' => 'dnrdmktg'
			, 'p' => 'Pumping#10Lead'
		)
	);
}
define("FD", $folder_delimiter); 
define("ADMIN_FOLDER", "leadadmin");
define("ADMIN_ROOT", SITE_ROOT.ADMIN_FOLDER.FD);
define("LIVE_FOLDER", "live");
define("LIVE_ROOT", SITE_ROOT.LIVE_FOLDER.FD);
define("MYSQL_TIMEOUT", 10);
$GLOBALS['dbconnx'] = '';
$GLOBALS['dbmigration'] = '';
define("ADMINISTRATOR_EMAIL", 'rsmith@thebrandingbuzz.com');
?>
