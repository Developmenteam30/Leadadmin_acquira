<?php //live/processFunctions.php
//Version 1.6
//ES 20130820 1.6: Updated this script to utilize c_config constants.
include("Array2XML.php");
include("_f_validEmail.php");
include("_f_validation.php");

function formaturl($url)
{	
	//VER 1.1
	//1.1 added url format for .info, if none of the extensions are found, don't truncate
	//find FIRST extension
	$extensions = array(
		".com",
		".net",
		".org",
		".uk",
		".info",
		".us",
		".me"
		, ".mobi"
	);
	
	$positions = array();
	foreach($extensions as $ext)
	{
		if(strpos($url, $ext) !== false) $positions[$ext] = strpos($url, $ext);
	}
	
	asort($positions);
	$extensions = array_keys($positions);
	$position = 0;
	if(!isset($extensions[0]))
	{
		return false;
	}
	$extension = $extensions[0];

	$posplus = strlen($extension);
	$position = strpos($url, $extension);
	
	$trimlen = $position+$posplus;
	$url = substr($url, 0, $trimlen); 
	return $url;
}

function url_reformat($urlstring)
{
	$secure = false;
	$new = strtolower($urlstring);
	$new = str_replace("http://", "", $new);
	$new = str_replace("www.", "", $new);
	$new = str_replace("www/", "", $new);
	if(strpos($new, "?") !== false)
	{
		$new = substr($new, 0, strpos($new, "?"));
	}
	
	if(!formaturl($new)) { return "INVALID:".$urlstring; }
	
	$new = formaturl($new);
	
	$new = str_replace("'", "", $new);
	
	$removechars = '/{}|\^~[]$+!*(),:"';
	for($count = 0; $count < strlen($removechars); $count++)
	{
		$new = str_replace($removechars[$count], "", $new);
	}
	
	if(strpos($new, "https") !== false)
	{
		$secure = true;
		$new = str_replace("https", "", $new);
	}
	
	$parts = explode(".", $new);
	$ext = array ("com", "net", "org", "uk", "info", "us", "me", "mobi");
	if((isset($parts[1]) && in_array($parts[1], $ext)))
	{
			$final = "http";
			if($secure) $final .= "s";
			$final .= "://www.$new";
	}
	else 
	{
		if(isset($parts[2]) && $parts[2] == "uk" && $parts[1] == "co")
		{
			$final = "http";
			if($secure) $final .= "s";
			$final .= "://www.$new";
		}
		else
		{
			$final = "http";
			if($secure) $final .= "s";
			$final .= "://$new";
		}
	}
		
	return $final;
	
}

function loadParameters($feedLabel){ 
	dbCon();
	$getParameters = "SELECT * FROM `".DATABASE_NAME."`.`feedinc` WHERE `label` = '".$feedLabel."';";
	$dogetParameters = dbQry($getParameters, 'Fetching parameters for '.$feedLabel, true);
	if($dogetParameters === false){ return false; }
	if($dogetParameters->num_rows == 0){ return 0; }
	$parameters = $dogetParameters->fetch_object();
	return $parameters;
}

function logError($origination, $description, $notify = false){ 
	//Store the error in the database.
	dbCon("insertUpdate");
	$stamp = date("Y-m-d H:i:s");
	$insertError = "INSERT INTO `".DATABASE_NAME."`.`errorlog` (`origination`,`description`,`stamp`) "
		."VALUES ('".$origination."', '".$description."', '".$stamp."'); ";
	$doinsertError = dbQry($insertError, 'Inserting log of script error.', true);
	if($doinsertError === false){ 
		//Store the error in a log file if the database fails.
		$logFile = fopen(SITE_ROOT.".errorLog", "a");
		fwrite($logFile, $stamp.":(".$origination.") ".$description);
		fclose($logFile);
	}	
	if($notify){ 
		$from = 'lmsalerts@'.SITE_URL;
		$body = 
			"Error Report<br />"
			."Error Stamp: ".$stamp."<br />"
			."Origin: ".$origination."<br />"
			."Description: ".$description."<br />"
		;
		$fromName 	= CONFIG_COMPANY_NAME.' List Management System';
		$to			= ADMINISTRATOR_EMAIL;
		$subject	= 'List Management Incoming Feed Alert';
		$messages 	= $body;
		$header = "From:" . $fromName . " <" . $from . ">\n";
		$header .= "Content-type: text/html; charset=iso-8859-1\n";
		$header .= "Reply-To: <" . $from . ">\n";
		$header .= "X-Sender: <" . $from . ">\n";
		$header .= "X-Mailer: PHP5\n";
		$header .= "X-Priority: 3\n";
		$header .= "Return-Path: <" . $from . ">\n";
		$sent = @mail($to, $subject, $messages ,$header);
		if(!$sent){ 
			logError(
				'Error Logging'
				, 'Failed to send error report notification to administrator'
			);
		}
	}
}

function notifyManagers($body)
{
		$from 		= 'lmsalerts@'.SITE_URL;
		$fromName 	= CONFIG_COMPANY_NAME.' List Management System';
		$to			= MANAGER_EMAIL;
		$subject	= 'List Management - New URL Alert';
		$messages 	= $body;
		$header 	= "From:" . $fromName . " <" . $from . ">\r\n";
		$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
		$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";
		$header .= "Reply-To: <" . $from . ">\r\n";
		$header .= "X-Sender: <" . $from . ">\r\n";
		$header .= "Return-Path: <" . $from . ">\r\n";
		$sent = @mail($to, $subject, $messages ,$header);
		if(!$sent){ 
			logError(
				'Error Logging'
				, 'Failed to send error report notification to administrator'
			);
		}
}

function validate($fieldType, $value, $feedParams){ 
	$result = array(
		'valid' => false
		, 'reason' => 'No reason given.'
	);
	$c = true;
	switch($fieldType){ 
		case 'listcode':
			if($c && strlen($value) > 20){ 
				$c = false; $result['reason'] = 'List code (listcode) exceeds maximum allowed length.';
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'List code (listcode) contains invalid characters.';
			}
		break;
		case 'url':
			if($c && strlen($value) > 500){ 
				$c = false; $result['reason'] = 'URL (url) exceeds maximum allowed length.'; 			
			}
			if($c && hasinvalidurlchars($value)){ 
				$c = false; $result['reason'] = 'URL (url) contains invalid characters.'; 			
			}
		break;
		case 'ip':
			if($c && strlen($value) > 16){ 
				$c = false; $result['reason'] = 'IP (ip) exceeds maximum allowed length.';
			}
			if($c && !valid_ip($value)){ 
				$c = false; $result['reason'] = 'IP (ip) is invalid.';
			}
		break;
		case 'stamp':
			if( $c &&( 
				preg_match('/^[0-3][0-9]-[0-3][0-9]-[0-9]{4}/', $value)
				|| preg_match('/^[0-3][0-9]\/[0-3][0-9]\/[0-9]{4}/', $value)
			)){ 
				$c = false; $result['reason'] = 'Action Date (stamp) is invalid format. Please submit stamp as YYYY-mm-dd HH:ii:ss';
			}
			if(	$c && (
				strtotime($value) == -1
				|| strtotime($value) == false
			)){ 
				$c = false; $result['reason'] = 'Action Date (stamp) is invalid.';
			}
			if($c
				&& $feedParams->rejectOldLeads
				&& strtotime($value) < strtotime($feedParams->rejectOldLeadsMaxAge)
			){ 
				$c = false; $result['reason'] = 'Action Date (stamp) is too old, lead rejected.';
			}
		break;
		case 'email':
			if($c && strlen($value) > 150){ 
				$c = false; $result['reason'] = 'Email (email) exceeds maximum allowed length.'; 
			}
			if($c && !valid_email($value)){ 
				$c = false; $result['reason'] = 'Email (email) is invalid.';
			}
		break;
		case 'fname': 
			if($c && strlen($value) > 50){ 
				$c = false; $result['reason'] = 'First Name (fname) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 1){ 
				$c = false; $result['reason'] = 'First Name (fname) does not meet required length.'; 
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'First Name (fname) contains invalid characters.'; 
			}
		break;
		case 'lname': 
			if($c && strlen($value) > 50){ 
				$c = false; $result['reason'] = 'Last Name (lname) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 2){ 
				$c = false; $result['reason'] = 'Last Name (lname) does not meet required length.'; 
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'Last Name (lname) contains invalid characters.'; 
			}
		break;
		case 'addr': 
			if($c && strlen($value) > 150){ 
				$c = false; $result['reason'] = 'Address Line 1 (addr) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 3){ 
				$c = false; $result['reason'] = 'Address Line 1 (addr) does not meet required length.'; 
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'Address Line 1 (addr) contains invalid characters.'; 
			}
		break;
		case 'addr2': 
			if($c && strlen($value) > 150){ 
				$c = false; $result['reason'] = 'Address Line 2 (addr2) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 3){ 
				$c = false; $result['reason'] = 'Address Line 2 (addr2) does not meet required length.'; 
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'Address Line 2 (addr2) contains invalid characters.'; 
			}
		break;
		case 'city': 
			if($c && strlen($value) > 75){ 
				$c = false; $result['reason'] = 'City (city) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 3){ 
				$c = false; $result['reason'] = 'City (city) does not meet required length.'; 
			}
			if($c && hasinvalidchars($value)){ 
				$c = false; $result['reason'] = 'City (city) contains invalid characters.'; 
			}
		break;
		case 'state': 
			if($c && strlen($value) > 25){ 
				$c = false; $result['reason'] = 'State (state) exceeds maximum allowed length.'; 
			}
			if($c && strlen($value) < 2){ 
				$c = false; $result['reason'] = 'State (state) does not meet required length.'; 
			}
			if($c && !onlyalphas($value)){ 
				$c = false; $result['reason'] = 'State (state) contains invalid characters.'; 
			}
		break;
		case 'zip': 
			if( strlen( $value ) < 5 || strlen( $value ) > 10 ) {
				$c = false; $result['reason'] = 'Zip (zip) is an invalid length.'; 
			}
			if( $c && hasinvalidzipchars( $value ) ){ 
				$c = false; $result['reason'] = 'Zip (zip) contains invalid characters.'; 
			}
		break;
		case 'dob':
			if(	$c 
				&& (
					strtotime($value) == -1
					|| strtotime($value) == false
				)
			){ 
				$c = false; $result['reason'] = 'Date of Birth (dob) is invalid.';
			}
		break;
		case 'gender':
			$allowedGenders = array('m','f','male','female','na','not applicable');
			if( $c && (
				!in_array(strtolower($value), $allowedGenders)
			)){
				$c = false; $result['reason'] = 'Gender is an invalid value.';
			}
		break;
		case 'landline':
			if($c && strlen($value) != 10){ 
				$c = false; $result['reason'] = 'Default Phone (landline) is an invalid length.'; 
			}
			if($c && !onlynos($value)){ 
				$c = false; $result['reason'] = 'Default Phone (landline) contains invalid characters.'; 
			}
		break;
		case 'cellphone':
			if($c && strlen($value) != 10){ 
				$c = false; $result['reason'] = 'Alternate Phone (cellphone) is an invalid length.'; 
			}
			if($c && !onlynos($value)){ 
				$c = false; $result['reason'] = 'Alternate Phone (cellphone) contains invalid characters.'; 
			}
		break;
	}
	if($c){ 
		$result['valid'] = true;
		$result['reason'] = ucfirst($fieldType).' passed validation.';
	}
	return $result;
}

function getPopulationSettings($idFeedIn){ 
	dbCon();
	$getSettings = 
		"SELECT fp.*, fo.label "
		."FROM "
			."`".DATABASE_NAME."`.`feedPopulation` fp "
			.", `".DATABASE_NAME."`.`feedout` fo "
		."WHERE "
			."fp.`idFeedIn` = '".$idFeedIn."' "
			."AND fp.`idFeedOut` = fo.`idFeedOut` "
		.";";
	$dogetSettings = dbQry($getSettings, 'Fetching population settings', true);
	if($dogetSettings === false){ return false; }
	if($dogetSettings->num_rows == 0){ return 0; }
	$settings = array();
	while($row = $dogetSettings->fetch_object()){ 
		$settings[] = $row;
	}
	return $settings;
}

function filterValue($filterType, $value, $filters){
	switch($filterType){ 
		case 'accept':
			$valueAcceptable = false;
			$acceptableFilters = explode(";", $filters);
			foreach($acceptableFilters as $acceptableFilter){ 
				if(stripos($value, $acceptableFilter) !== false){ 
					$valueAcceptable = true; break;
				}
			}
		break;
		case 'reject':
			$valueAcceptable = true;
			$rejectableFilters = explode(";", $filters);
			foreach($rejectableFilters as $rejectableFilter){ 
				if(stripos($value, $rejectableFilter) !== false){ 
					$valueAcceptable = false; break;
				}
			}
		break;
		default: 
			$valueAcceptable = true;
		break;
	}
	return $valueAcceptable;
}

function checkDuplicate($column, $requestValues, $feedLabel, $dedupeAcross){
	dbCon();
	switch($dedupeAcross){
		case 'global':
			$checkDupe = "SELECT count(*) FROM `".DATABASE_NAME."`.`feedinc_".$feedLabel."` "
			."WHERE `".$column."` = '".$GLOBALS['dbconnx']->escape_string($requestValues[$column])."' "
			.";";
		break;
		case 'url':
			$checkDupe = "SELECT count(*) FROM `".DATABASE_NAME."`.`feedinc_".$feedLabel."` "
			."WHERE `".$column."` = '".$GLOBALS['dbconnx']->escape_string($requestValues[$column])."' "
			."AND `urlTrim` = '".$GLOBALS['dbconnx']->escape_string($requestValues['urlTrim'])."' "
			.";";
		break;
		case 'listcode':
			$checkDupe = "SELECT count(*) FROM `".DATABASE_NAME."`.`feedinc_".$feedLabel."` "
			."WHERE `".$column."` = '".$GLOBALS['dbconnx']->escape_string($value)."' "
			."AND `listcode` = '".$GLOBALS['dbconnx']->escape_string($requestValues['listcode'])."' "
			.";";
		break;
	}	
	$docheckDupe = dbQry($checkDupe, 'Checking if value is duplicate.', true);
	dbDcon();
	if($docheckDupe === false){ return false; }
	$dupeCount = $docheckDupe->fetch_assoc();
	$dupeCount = $dupeCount['count(*)'];
	return $dupeCount;
	
}

function checkExists( $column, $requestValues, $feedLabel ){
	dbCon();
	$query = "SELECT 1 AS cnt FROM `".DATABASE_NAME."`.`feedinc_".$feedLabel."` "
			."WHERE `".$column."` = '".$GLOBALS['dbconnx']->escape_string($requestValues[$column])."' "
			."LIMIT 1";
	$docheckDupe = dbQry($query, 'Checking if value exists.', true);
	dbDcon();
	if($docheckDupe === false){ return false; }
	$dupeCount = $docheckDupe->fetch_assoc();
	return $dupeCount['cnt'];
}

function checkSuppression( $email, $idCompany ) {
	dbCon();
	$query = "SELECT 1 AS cnt FROM `".DATABASE_NAME."`.`suppression_".$idCompany."` "
			."WHERE `email` = '".$GLOBALS['dbconnx']->escape_string( $email )."' "
			."LIMIT 1";
	$result = dbQry($query, 'Checking if email is suppressed.', true);
	dbDcon();
	if($result === false){ return false; }
	$count = $result->fetch_assoc();
	return $count['cnt'];
}

function lockTables($feedLabel){
	dbCon();
	$lockQuery = "LOCK TABLE `".DATABASE_NAME."`.`feedinc_".$feedLabel."` WRITE";
	dbQry($lockQuery, 'Locking tables.', true);
	dbDcon();
}

function unlockTables(){
	dbCon();
	dbQry('UNLOCK TABLES', 'Unlocking tables.', true);
	dbDcon();
}

