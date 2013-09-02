<?php //_connx.php
//Version 1.2
//ES20130820 Version 1.2: Updated this so it works off the configuration file for the site.
//REQUIRES THAT THE CONFIGURATION FILE IS INCLUDED BEFORE THIS IS INCLUDED.

if(isset($forceMysqlLogFile)){ 
	$GLOBALS['mysqlLog'] = $forceMysqlLogFile;
} else { 
	$GLOBALS['mysqlLog'] = 
		SITE_ROOT."error".FD."log"; 
}

if(isset($mysqlErrorSource)){ 
	$GLOBALS['mysqlErrSrc'] = $mysqlErrorSource;
} else { 
	$GLOBALS['mysqlErrSrc'] = "MYSQL Error (Source Not Listed)";
}

function dbCon($level = 'selectOnly', $keepAlive = false)
{
	ini_set("mysql.connect_timeout", MYSQL_TIMEOUT);
	$GLOBALS['dbconnx'] = 
		new mysqli(
			DATABASE_HOST
			, $GLOBALS['connxSettings'][$level]['u']
			, $GLOBALS['connxSettings'][$level]['p']
		);
	if(MIGRATING){ 
		$GLOBALS['dbmigration'] = 
			new mysqli(
				DATABASE_HOST_MIGRATION
				, $GLOBALS['connxSettings'][$level]['u']
				, $GLOBALS['connxSettings'][$level]['p']
			);
	}
	if(
		$GLOBALS['dbconnx']->connect_error 
		|| (
			MIGRATING
			&& $GLOBALS['dbmigration']->connect_error
		)
	) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		if(MIGRATING){ 
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
	if(MIGRATING){ 
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
	if($nonSelect && MIGRATING){ $mysqliObject_migration = $GLOBALS['dbmigration']; }
	$result = $mysqliObject->query($query, MYSQLI_STORE_RESULT);
	if($nonSelect && MIGRATING){ 
		$result_migration = $mysqliObject_migration->query($query, MYSQLI_STORE_RESULT);
	}
	if(
		$result === false
		|| (
			$nonSelect
			&& MIGRATING
			&& $result_migration === false
		)
	) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		if($nonSelect && MIGRATING){ 
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
			if($nonSelect && MIGRATING && $mysqliObject_migration->more_results()){ 
				$mysqliObject_migration->next_result();
			}
			return false;
		}
	} else { //Query was successful, return results.
		if($mysqliObject->more_results()){ 
			$mysqliObject->next_result();
		}
		if($nonSelect && MIGRATING && $mysqliObject_migration->more_results()){ 
			$mysqliObject_migration->next_result();
		}
		return $result;
	}
}
?>