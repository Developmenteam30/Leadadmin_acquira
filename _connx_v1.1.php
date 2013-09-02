<?php //_connx.php
//Version 1.1
//ES20130722 Version 1.1: Script updated so we can start migrating things to the new database. Every NON-SELECT query 
//should update both MYSQL servers.

//Are we working on production or local?
if(isset($dbConfigLocation)){ $GLOBALS['dbconfig'] = $dbConfigLocation;
} else { $GLOBALS['dbconfig'] = "_db_config.php"; }

include($GLOBALS['dbconfig']);

if(isset($forceMysqlLogFile)){ 
	$GLOBALS['mysqlLog'] = $forceMysqlLogFile;
} else { 
	if($GLOBALS['server'] == 'prod'){ 
		$GLOBALS['mysqlLog'] = 
			"/var/www/html/qmleads.com/error/log"; 
	} else { 
		$GLOBALS['mysqlLog'] = 
			"M:\\WAMP\\wamp\\www\\qmleads.com\\error\\log"; 
	}
}

if(isset($mysqlErrorSource)){ $GLOBALS['mysqlErrSrc'] = $mysqlErrorSource;
} else { $GLOBALS['mysqlErrSrc'] = "MYSQL Error (Source Not Listed)";	}

$GLOBALS['dbconnx'] = '';
$GLOBALS['dbmigration'] = '';

function dbCon($level = 'selectOnly', $keepAlive = false)
{
	ini_set("mysql.connect_timeout", 10);
	$GLOBALS['dbconnx'] = 
		new mysqli(
			$GLOBALS['host']
			, $GLOBALS['connxSettings'][$level]['u']
			, $GLOBALS['connxSettings'][$level]['p']
		);
	if($GLOBALS['migrating']){ 
	$GLOBALS['dbmigration'] = 
		new mysqli(
			$GLOBALS['migrationHost']
			, $GLOBALS['connxSettings'][$level]['u']
			, $GLOBALS['connxSettings'][$level]['p']
		);
	}
	if(
		$GLOBALS['dbconnx']->connect_error 
		|| (
			$GLOBALS['migrating'] 
			&& $GLOBALS['dbmigration']->connect_error
		)
	) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		if($GLOBALS['migrating']){ 
			$errors = 'dbconnx: '.$GLOBALS['dbconnx']->connect_error
				   .'|dbmigration: '.$GLOBALS['dbmigration']->connect_error;
		} else { 
			$errors = $GLOBALS['dbconnx']->connect_error;
		}
		$written = fwrite(
			$errfile
			, "MYSQL Failed at "
			.date("Y-m-d H:i:s")
			.": (".$GLOBALS['mysqlErrSrc'].") "
			.$errors
			."\r\n"
		);
		fclose($errfile);	
		if(!$keepAlive){ 
			echo "Database connection failure.<br />\r\n";
			exit;
		} else { 
			return false;
		}
	} else { 
		return true;
	}
}

function dbDcon()
{
	$GLOBALS['dbconnx']->close();
	if($GLOBALS['migrating']){ 
		$GLOBALS['dbmigration']->close();
	}
}

function dbQry(
	$query
	, $queryDescription = ''
	, $keepAliveAfterError = false
	, $logDupeNotices = false 
) { 
	$nonSelectQueries = array('INSERT', 'UPDATE', 'DELETE', 'CREATE', 'RENAME');
	$nonSelect = false;
	foreach($nonSelectQueries as $queryCommand){ 
		if(preg_match('/^'.$queryCommand.'/', $query)){ 
			$nonSelect = true;
		}
	}
	$mysqliObject = $GLOBALS['dbconnx']; 
	if($nonSelect && $GLOBALS['migrating']){ $mysqliObject_migration = $GLOBALS['dbmigration']; }
	$result = $mysqliObject->query($query, MYSQLI_STORE_RESULT);
	if($nonSelect && $GLOBALS['migrating']){ 
		$result_migration = $mysqliObject_migration->query($query, MYSQLI_STORE_RESULT);
	}
	if(
		$result === false
		|| (
			$nonSelect
			&& $GLOBALS['migrating']
			&& $result_migration === false
		)
	) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		if($nonSelect && $GLOBALS['migrating']){ 
			$error = 'dbconnx: '.$mysqliObject->error . '|dbmigration: '.$mysqliObject_migration->error;
		} else { 
			$error = $mysqliObject->error;
		}
		if(
			strpos($error, "Duplicate") === false //It is NOT a duplicate notice
			|| $logDupeNotices //Dupe notices are turned on
		){
			$errorMsg = "MYSQL Failed at ".date("Y-m-d H:i:s").": "
			."(".$GLOBALS['mysqlErrSrc'].") "
			.$error." (Desc: ".$queryDescription." (Q:".$query.") \r\n";
			fwrite($errfile, $errorMsg);
		} else { 
			$errorMsg = "MYSQL Failed at ".date("Y-m-d H:i:s").": "
			."(".$GLOBALS['mysqlErrSrc'].") "
			.$error." (Desc: ".$queryDescription." (Q:".$query.") \r\n";
		}
		fclose($errfile);
		if(!$keepAliveAfterError){ //Script should end.
			echo $errorMsg;
			exit; 
		}
		else { //Let the script continue, just return false.
			if($mysqliObject->more_results()){ 
				$mysqliObject->next_result();
			}
			if($nonSelect && $GLOBALS['migrating'] && $mysqliObject_migration->more_results()){ 
				$mysqliObject_migration->next_result();
			}
			return false;
		}
	} else { //Query was successful, return results.
		if($mysqliObject->more_results()){ 
			$mysqliObject->next_result();
		}
		if($nonSelect && $GLOBALS['migrating'] && $mysqliObject_migration->more_results()){ 
			$mysqliObject_migration->next_result();
		}
		return $result;
	}
}
?>