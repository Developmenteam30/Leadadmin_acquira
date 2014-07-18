<?php //live/processFunctions.php
//Version 1.6
//ES 20130820 1.6: Updated this script to utilize c_config constants.
include_once(INCLUDES."Array2XML.php");
include_once(INCLUDES."_f_validEmail.php");
include_once(INCLUDES."_f_validation.php");

require_once( INCLUDES . 'leads.php' );

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

function getFeedIn($idFeedIn){ 
	dbCon();
	$getParameters = "SELECT * FROM `".DATABASE_NAME."`.`feedinc` WHERE `idFeedIn` = " . intval( $idFeedIn );
	$dogetParameters = dbQry($getParameters, 'Fetching parameters for '.$idFeedIn, true);
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
		$logFile = fopen(SITE_ROOT."error".FD."mysql", "a");
		fwrite($logFile, $stamp.":(".$origination.") ".$description);
		fclose($logFile);
	}	
	if($notify){ 

		// Limit notification emails to one per minute to prevent flooding
		$time = @file_get_contents( SITE_ROOT."error".FD."email-stamp" );
		if( $time === FALSE || ( $time < ( time() - 60 ) ) ) {
			file_put_contents( SITE_ROOT."error".FD."email-stamp", time() );
		} else {
			return;			
		}

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

function getIncomingPopulationSettings($idFeedIn){ 
	dbCon();
	$getSettings = 
		"SELECT fp.*, fo.label, fo.dailyLimit "
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
	list( $local, $domain ) = explode( '@', $email, 2 );
	$query = "SELECT 1 AS cnt FROM `".DATABASE_NAME."`.`suppression_".$idCompany."` "
			."WHERE `email` = '".$GLOBALS['dbconnx']->escape_string( $email )."' "
			."OR `email` = '".$GLOBALS['dbconnx']->escape_string( $domain )."' "
			."LIMIT 1";
	$result = dbQry($query, 'Checking if email is suppressed.', true);
	dbDcon();
	if($result === false){ return false; }
	$count = $result->fetch_assoc();
	return $count['cnt'];
}

function lockTables($feedLabel){
	dbCon();
	$lockQuery = "LOCK TABLE `".DATABASE_NAME."`.`feedinc_".$feedLabel."` WRITE, `".DATABASE_NAME."`.`feedinc_".$feedLabel."_invalid` WRITE, `".DATABASE_NAME."`.urlcount WRITE, `".DATABASE_NAME."`.urlcount_invalid WRITE, `".DATABASE_NAME."`.suppression_global READ, `".DATABASE_NAME."`.errorlog WRITE, `".DATABASE_NAME."`.notifications WRITE ";
	dbQry($lockQuery, 'Locking tables.', true);
	dbDcon();
}

function unlockTables(){
	dbCon();
	dbQry('UNLOCK TABLES', 'Unlocking tables.', true);
	dbDcon();
}

function getPopulation( $idFeedOut ){
	$query  = "SELECT p.*,i.label inLabel, o.label outLabel FROM `".DATABASE_NAME."`.`feedPopulation` p ";
	$query .= "LEFT JOIN `".DATABASE_NAME."`.`feedinc` i ON p.idFeedIn = i.idFeedIn ";
	$query .= "LEFT JOIN `".DATABASE_NAME."`.`feedout` o ON p.idFeedOut = o.idFeedOut ";
    $query .= "WHERE i.label IS NOT NULL ";
    $query .= "AND o.label IS NOT NULL ";
    $query .= "AND p.idFeedOut = '" . intval( $idFeedOut ) . "'";
    
	dbCon();
    $result = dbQry( $query, 'Getting population parameters', true );

    if( $result === false ) { return false; }
    if( $result->num_rows == 0 ) { return 0; }
    $values = array();
    while( $row = $result->fetch_object() ){
        $values[] = $row;
    }
    return $values;
}

function getOutboundStamp( $label ) {
	$query  = "SELECT MIN(stamp) stamp ";
	$query .= "FROM `".DATABASE_NAME."`.`feedout_".$label."` ";
    
	dbCon();
    $result = dbQry($query, 'Getting earlist outbound record stamp.', true);
    if( $result === false ) { return null; }
    if( $result->num_rows == 0 ) { return null; }
    $stamp = $result->fetch_assoc();
    return $stamp['stamp'];
}

function getOutboundDailyCount( $label ) {
	$query  = "SELECT COUNT(*) cnt ";
	$query .= "FROM `".DATABASE_NAME."`.`feedout_".$label."` ";
	$query .= "WHERE poststamp >= DATE_FORMAT(NOW(), '%Y-%m-%d 00:00:00')";
    
	dbCon();
    $result = dbQry($query, 'Getting outbound record count.', true);
    if( $result === false ) { return null; }
    if( $result->num_rows == 0 ) { return null; }
    $cnt = $result->fetch_assoc();
    return $cnt['cnt'];
}

function addOutboundRecord( $label, $listcode, $urlTrim, $url, $ip, $stamp, $email, $fname, $lname, $addr, $addr2, $city, $state, $zip, $country, $dob, $gender, $landline, $cellphone, $processed = '0', $poststamp = null, $postrequest = null, $postresponse = null ) {
	$query  = "INSERT INTO `".DATABASE_NAME."`.`feedout_".$label."` (processed,poststamp,postrequest,postresponse,listcode,urlTrim,url,ip,stamp,email,fname,lname,addr,addr2,city,state,zip,country,dob,gender,landline,cellphone) ";
	$query .= "VALUES( ";
    $query .= "'" . $GLOBALS['dbconnx']->escape_string( $processed ) . "', ";
    $query .= valueSet( $poststamp ) . ", ";
    $query .= valueSet( $postrequest ) . ", ";
    $query .= valueSet( $postresponse ) . ", ";
    $query .= valueEmpty( $listcode ) . ", ";
    $query .= valueEmpty( $urlTrim ) . ", ";
    $query .= valueEmpty( $url ) . ", ";
    $query .= valueEmpty( $ip ) . ", ";
    $query .= valueEmpty( $stamp ) . ", ";
    $query .= valueEmpty( $email ) . ", ";
    $query .= valueEmpty( $fname ) . ", ";
    $query .= valueEmpty( $lname ) . ", ";
    $query .= valueEmpty( $addr ) . ", ";
    $query .= valueEmpty( $addr2 ) . ", ";
    $query .= valueEmpty( $city ) . ", ";
    $query .= valueEmpty( $state ) . ", ";
    $query .= valueEmpty( $zip ) . ", ";
    $query .= valueEmpty( $country ) . ", ";
    $query .= valueEmpty( $dob ) . ", ";
    $query .= valueEmpty( $gender ) . ", ";
    $query .= valueEmpty( $landline ) . ", ";
    $query .= valueEmpty( $cellphone ) . " ";
	$query .= " )";

	$result = dbQry( $query, 'Populating '.$label, true);
	if( $result === false ) {
		logError(
			'Feed '.$label
			, 'Database failure when populate outgoing feed '.$label.'. Check MySQL log file.'
			, true
		);
	}
}

function checkPopulationFilters( $feed, $url, $email, $listcode ) {

	if( !is_null( $feed->filterTypeUrl ) ) {
		$valid = filterValue( $feed->filterTypeUrl, $url, $feed->filterUrl );
		if( !$valid ) {
			return false;
		}
	}

	if( !is_null( $feed->filterTypeEmail ) ) {
		$valid = filterValue( $feed->filterTypeEmail, $email, $feed->filterEmail );
		if( !$valid ) {
			return false;
		}
	}

	if( !is_null( $feed->filterTypeListcode ) ) {
		$valid = filterValue( $feed->filterTypeListcode, $listcode, $feed->filterListcode );
		if( !$valid ) {
			return false;
		}
	}

	return true;

}

function validateIncomingData( $feedParams, &$data ) {

	$result = array();
	$result['valid'] = true;

	$requiredFields = explode(';', $feedParams->required);
	$allowedFields = explode(';', $feedParams->allowedFields);

	// Special handling for TurnTwo feed that cannot change what URL value is being sent to us
	if( !empty( $data['url'] ) && 'www.5minutemoney.co.uk,www.5minutemoney.co.uk' == $data['url'] ) {
		$data['url'] = 'www.5minutemoney.co.uk';
	}

	// Remove non-numeric characters from phone numbers
	if( !empty( $data['landline'] ) ) {
		$data['landline'] = preg_replace( '/[^0-9]/', '', $data['landline'] );
	}
	if( !empty( $data['cellphone'] ) ) {
		$data['cellphone'] = preg_replace( '/[^0-9]/', '', $data['cellphone'] );
	}

	foreach( $requiredFields as $requiredKey ) {
		switch( $requiredKey ) {
			case "phone":
				if(
					(
						!isset( $data['landline'] )
						|| trim( $data['landline'] ) == ''
					) && (
						!isset( $data['cellphone'] )
						|| trim( $data['cellphone'] ) == ''
					)
				){
					$result['valid'] = false; 
					$result['errors'][] = "A phone number is required, either landline or cellphone. They cannot both be empty.";
				}
			break; 
			default:
				if(
					!isset( $data[$requiredKey] ) 
					|| trim( $data[$requiredKey] ) == ''
				){ 
					$result['valid'] = false;
					$result['errors'][] = ucfirst( $requiredKey ).' is a required field, and may not be empty.';
				}
		}
	}

	foreach( $allowedFields as $allowedField ) { 
		if(	!empty( $data[$allowedField]) ) { 
			$validateResult = validate( $allowedField, $data[$allowedField], $feedParams );
			if( !$validateResult['valid'] ) { 
				$result['valid'] = false;
				$result['errors'][] = $validateResult['reason'];
			}
		}
	}

	if( in_array('url', $allowedFields) ) { //URL is expected so trim it and store in the database.
		if( !empty( $data['url'] ) ){ 
			$data['urlTrim'] = url_reformat( $data['url'] );
		} else { 
			$data['url'] = 'No Url Given';
			$data['urlTrim'] = url_reformat( 'No Url Given' );
		}
	}

	if( !$result['valid'] ) {
		return $result;
	}

	if( !empty( $data['email'] ) ) {
		$exists = checkSuppression( $data['email'], 'global' );
		if( $exists ) {
			$result['valid'] = false;
			$result['errors'][] = 'Email exists in our global suppression file.';
		}
	}

	if( !is_null( $feedParams->filterTypeUrl ) ) {
		$urlAcceptable = filterValue( $feedParams->filterTypeUrl, $data['url'], $feedParams->filterUrl );
		if( !$urlAcceptable ) {
			$result['valid'] = false;
			$result['errors'][] = 'URL is not allowed on this feed.';
		}
	}

	if( $feedParams->dedupeEmail && !empty( $data['email'] ) ) {
		$dupeCount = checkDuplicate( 'email', $data, $feedParams->label, $feedParams->dedupeAcross );
		if( $dupeCount === false ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Database failure - could not check duplicate email.';
		} elseif( $dupeCount > 0 ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Duplicate email.';
		}
	}

	if( $feedParams->dedupeLandline && !empty( $data['landline'] ) ) {
		$dupeCount = checkDuplicate( 'landline', $data, $feedParams->label, $feedParams->dedupeAcross );
		if( $dupeCount === false ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Database failure - could not check duplicate landline.';
		} elseif( $dupeCount > 0 ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Duplicate landline phone.';
		}
	}

	if( $feedParams->dedupeCellphone && !empty( $data['cellphone'] ) ) {
		$dupeCount = checkDuplicate('cellphone', $data, $feedParams->label, $feedParams->dedupeAcross);
		if( $dupeCount === false ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Database failure - could not check duplicate cellphone.';
		} elseif( $dupeCount > 0 ) { 
			$result['valid'] = false;
			$result['errors'][] = 'Duplicate cellphone.';
		}
	}

	return $result;
}

function insertIncomingData( $feedParams, $data, $jobId, $error = null ) {

	$lastRecord = null;
	$requiredFields = explode(';', $feedParams->required);
	$allowedFields = explode(';', $feedParams->allowedFields);

	// Establish our table names
	if( !empty( $error ) ) {
		$insertTable = "`".DATABASE_NAME."`.`feedinc_".$feedParams->label."_invalid`";
		$countTable = "`".DATABASE_NAME."`.`urlcount_invalid`";
		$insertRecord = "INSERT INTO " . $insertTable . " ( `jobId`,`queryString`,`received`,`error` ";
	} else {
		$insertTable = "`".DATABASE_NAME."`.`feedinc_".$feedParams->label."`";
		$countTable = "`".DATABASE_NAME."`.`urlcount`";
		$insertRecord = "INSERT INTO " . $insertTable . " ( `jobId`,`queryString`,`received` ";
	}

	dbCon("insertUpdate");

	foreach( $allowedFields as $allowedField ) { 
		$insertRecord .= ", `".$allowedField."` ";
		if( 'url' == $allowedField ) { 
			$insertRecord .= ", `urlTrim` ";
		}
	}
	$insertRecord .= ") VALUES ( "
		. valueEmpty( $jobId ) . ","
		."'".$GLOBALS['dbconnx']->escape_string(serialize($data))."', "
		."'".date("Y-m-d H:i:s")."' ";

	if( !empty( $error ) ) {
		$insertRecord .= "," . valueEmpty( $error );
	}

	foreach( $allowedFields as $allowedField ) { 
		if( isset( $data[$allowedField] ) ) { 
			if( $allowedField == 'listcode' && empty($data[$allowedField] ) ) { 
				$insertRecord .= ", 'No listcode'";
			} elseif( $allowedField == 'stamp' ) { 
				$insertRecord .= ", '".date("Y-m-d H:i:s", strtotime($data[$allowedField]))."' ";
			} else { 
				$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($data[$allowedField])."' ";
			}
		} else { 
			if($allowedField == 'listcode'){ 
				$insertRecord .= ", 'No listcode'";
			} else { 
				$insertRecord .= ", ''";
			}
		}
		if($allowedField == 'url'){ 
			$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($data['urlTrim'])."' ";
		}
	}
	$insertRecord .= ");";

	$doinsertRecord = dbQry($insertRecord, 'Inserting new record for '.$feedParams->label, true);
	if($doinsertRecord === false){
		logError(
			'Feed '.$feedParams->label
			, 'Database failure when attempting to insert record. Check MySQL log file.'
			, true
		);
		return null;

	} else { //Successfully inserted into the data table, now insert into the count table.

		$lastRecord = $GLOBALS['dbconnx']->insert_id;

	    // Notify if this is the first time we've seen this URL on this feed
		if( !empty( $data['urlTrim'] ) && empty( $error ) ) {
			$urlCount = checkExists( 'urlTrim', $data, $feedParams->label );
			if( $urlCount == 0 ) {
				notifyManagers( sprintf( "\r\nWe received a new URL on this feed.\r\n\r\nFeed: {$feedParams->label}\r\n\r\nURL: %s\r\n\r\n",
										str_replace( '.', '*', $data['urlTrim'] ) )
							);
			}
		}

		$date = date("Y-m-d");
		$insertCountChange = "INSERT INTO " . $countTable . "(`idFeedIn`,`urlTrim`,`urlFull`,`quantity`,`stamp`) VALUES ("
				." '".$feedParams->idFeedIn."' "
				.",'".$GLOBALS['dbconnx']->escape_string($data['urlTrim'])."' "
				.",'".$GLOBALS['dbconnx']->escape_string($data['url'])."' "
				.",'1' "
				.",'".$date."' "
			.") "
			."ON DUPLICATE KEY UPDATE `quantity`=`quantity`+1; ";
		$doinsertCountChange = dbQry($insertCountChange, 'Inserting quantity change', true);
		if($doinsertCountChange === false){ 
			logError(
				'Feed '.$feedParams->label
				, 'Database failure when attempting to add quantity change for record. Check MySQL log file.'
				, true
			);
		}
	}

	return $lastRecord;
}

function pushIncomingData( $idFeedIn, $data, $inboundId, $legacyId ) {

	$populations = getIncomingPopulationSettings( $idFeedIn );
    if( $populations === false ) {
        print "Database error";
    } else if( $populations == 0 ) {
        print "No populations for this feed";
    } else {
        foreach( $populations as $population ) {

			// Ensure the record passes the population parameter filters for this feed
			if( checkPopulationFilters( $population,
						isset( $data['url'] ) ? $data['url'] : null,
						isset( $data['email'] ) ? $data['email'] : null,
						isset( $data['listcode'] ) ? $data['listcode'] : null ) ) {

				addOutboundRecord( $population->label, 
					isset($data['listcode']) ? $data['listcode'] : null,
					isset($data['urlTrim']) ? $data['urlTrim'] : null,
					isset($data['url']) ? $data['url'] : null,
					isset($data['ip']) ? $data['ip'] : null,
					isset($data['stamp']) ? $data['stamp'] : null,
					isset($data['email']) ? $data['email'] : null,
					isset($data['fname']) ? $data['fname'] : null,
					isset($data['lname']) ? $data['lname'] : null,
					isset($data['addr']) ? $data['addr'] : null,
					isset($data['addr2']) ? $data['addr2'] : null,
					isset($data['city']) ? $data['city'] : null,
					isset($data['state']) ? $data['state'] : null,
					isset($data['zip']) ? $data['zip'] : null,
					isset($data['country']) ? $data['country'] : null,
					isset($data['dob']) ? $data['dob'] : null,
					isset($data['gender']) ? $data['gender'] : null,
					isset($data['landline']) ? $data['landline'] : null,
					isset($data['cellphone']) ? $data['cellphone'] : null, 
					'0', null, null, null );

				$leads = Leads::getInstance();
				$leads->outboundAdd( $inboundId, $legacyId, $idFeedIn, $population->idFeedOut, $data['url'] );

			}
		}
	}
}
