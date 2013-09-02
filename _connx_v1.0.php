<?php //_connx.php
//Version 1.0
//ES20130706 Version 1.0: Created this script for DNR Direct Marketing
//MYSQL connection script utilizing mysqli.

//Are we working on production or local?
if(isset($dbConfigLocation)){ $GLOBALS['dbconfig'] = $dbConfigLocation;
} else { $GLOBALS['dbconfig'] = "_db_config.php"; }

include($GLOBALS['dbconfig']);

if(isset($forceMysqlLogFile)){ 
	$GLOBALS['mysqlLog'] = $forceMysqlLogFile;
} else { 
	if($GLOBALS['server'] == 'prod'){ 
		$GLOBALS['mysqlLog'] = 
			"/home/content/22/10755022/html/error/log"; 
	} else { 
		$GLOBALS['mysqlLog'] = 
			"M:\\WAMP\\wamp\\www\\dnrdirectmarketing.com\\error\\log"; 
	}
}

if(isset($mysqlErrorSource)){ $GLOBALS['mysqlErrSrc'] = $mysqlErrorSource;
} else { $GLOBALS['mysqlErrSrc'] = "MYSQL Error (Source Not Listed)";	}

$GLOBALS['dbconnx'] = '';

function dbCon($level = 'selectOnly', $keepAlive = false)
{
	ini_set("mysql.connect_timeout", 10);
	$GLOBALS['dbconnx'] = 
		new mysqli(
			$GLOBALS['host']
			, $GLOBALS['connxSettings'][$level]['u']
			, $GLOBALS['connxSettings'][$level]['p']
		);
	if($GLOBALS['dbconnx']->connect_error) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		$written = fwrite(
			$errfile
			, "MYSQL Failed at "
			.date("Y-m-d H:i:s")
			.": (".$GLOBALS['mysqlErrSrc'].") "
			.$GLOBALS['dbconnx']->connect_error
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
}

function dbQry(
	$query
	, $queryDescription = ''
	, $keepAliveAfterError = false
	, $logDupeNotices = false 
) { 
	$mysqliObject = $GLOBALS['dbconnx'];
	$result = $mysqliObject->query($query, MYSQLI_STORE_RESULT);
	if($result === false) { 
		$errfile = fopen($GLOBALS['mysqlLog'], "a");
		$error = $mysqliObject->error;
		if(
			strpos($error, "Duplicate") === false //It is NOT a duplicate notice
			|| $logDupeNotices //Dupe notices are turned on
		){
			$errorMsg = "MYSQL Failed at ".date("Y-m-d H:i:s").": "
			."(".$GLOBALS['mysqlErrSrc'].") "
			.$error." (Desc: ".$queryDescription." (Q:".$query.") \r\n";
			fwrite($errfile, $errorMsg);
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
			return false;
		}
	} else { //Query was successful, return results.
		if($mysqliObject->more_results()){ 
			$mysqliObject->next_result();
		}
		return $result;
	}
}
?>