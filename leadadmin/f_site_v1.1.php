<?php
//ADMIN_ROOT/f_site.php
//Version 1.1
//ES 2013 08 27 v1.1 : Added hex2rgb function
//Core Values//
$recordFields = array(
	'listcode', 'url', 'ip', 'stamp', 'email', 'fname', 'lname', 'addr', 'addr2',
	'city', 'state', 'zip', 'dob', 'gender', 'landline', 'cellphone', 'country'
);
$additionalMapFields = array(
	'urlAssign', 'dobUS', 'stampUS', 'stampUS_dateOnly', 'stampUSAMPM', 'stampUS+AMPM'
);
$incomingAdditionalRequirementSettings = array(
	'phone'
);

//Utility Functions//
function hex2rgb($hex) { //Converts a hex color code to rgb color code for css.
   $hex = str_replace("#", "", $hex);

   if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
   } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
   }
   $rgb = array($r, $g, $b);
   return implode(",", $rgb); // returns the rgb values separated by commas
   //return $rgb; // returns an array with the rgb values
}

function add_quotes( $str ) {
	return sprintf( "'%s'", $GLOBALS['dbconnx']->escape_string( $str ) );
}

function genFeedPass($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

//Logging Functions//
function userLog($idUser, $actionType, $actionSubType, $description){ 
	$idUser = $_SESSION['idUser'];
	$stamp = time();
	dbCon("insertUpdate");
	$insertLog = "INSERT INTO `".DATABASE_NAME."`.`userLog` "
		."( "
			."  `idUser` "
			.", `stamp` "
			.", `actiontype` "
			.", `actionsubtype` "
			.", `description` "
		 .") VALUES ( "
			."  '".$idUser."' "
			.", '".$stamp."' "
			.", '".$actionType."' "
			.", '".$actionSubType."' "
			.", '".$GLOBALS['dbconnx']->escape_string($description)."' "
		.");";
	$doinsertLog = dbQry($insertLog, 'Inserting log of user action', true);
	dbDcon();
}

//Error Functions//

function getErrorCount(){ 
	$today = date("Y-m-d");
	$getErrorCount = "SELECT count(*) FROM `".DATABASE_NAME."`.`errorlog` "
		."WHERE `stamp` LIKE '".$today."%';";
	dbCon();
	$dogetErrorCount = dbQry($getErrorCount, 'Fetching count of errors for today', true);
	if($dogetErrorCount === false){ return false; }
	$errorCount = $dogetErrorCount->fetch_assoc();
	$errorCount = $errorCount['count(*)'];
	return $errorCount;
}

function getErrors(){
	$today = date("Y-m-d");
	//Fetches all incoming feeds into an array of objects.
	$getErrors = "SELECT * FROM `".DATABASE_NAME."`.`errorlog` "
		."WHERE `stamp` > '".$today."' "
		."ORDER BY `stamp` DESC;";
	dbCon();
	$dogetErrors = dbQry($getErrors, 'Fetching feeds.', true);
	dbDcon();
	if($dogetErrors === false){ 
		return false;
	}
	if($dogetErrors->num_rows == 0){ 
		return 0;
	}
	$errors = array();
	while($row = $dogetErrors->fetch_object()){ 
		$errors[] = $row;
	}
	return $errors;
}

//Company Functions//

function getCompanies(){
	$getCompanies = "SELECT * FROM `".DATABASE_NAME."`.`companies` "
		."ORDER BY `name` ASC;";
	dbCon();
	$dogetCompanies = dbQry($getCompanies, 'Fetching all companies', true);
	dbDcon();
	if($dogetCompanies === false){ return false; }
	if($dogetCompanies->num_rows == 0){ return 0; }
	$companies = array();
	while($row = $dogetCompanies->fetch_object()){ 
		$companies[] = $row;
	}
	return $companies;
}

function getCompany($idCompany){ 
	$getCompany = "SELECT * FROM `".DATABASE_NAME."`.`companies` "
		."WHERE `idCompany` = '".$idCompany."';";
	dbCon();
	$dogetCompany = dbQry($getCompany, 'Fetching company by ID', true);
	dbDcon();
	if($dogetCompany === false){ return false; }
	if($dogetCompany->num_rows == 0){ return 0; }
	return $dogetCompany->fetch_object();
}

//Incoming Feed Functions//

function getIncomingFeeds(){
	//Fetches all incoming feeds into an array of objects.
	$getFeeds = "SELECT * FROM `".DATABASE_NAME."`.`feedinc`;";
	dbCon();
	$dogetFeeds = dbQry($getFeeds, 'Fetching feeds.', true);
	dbDcon();
	if($dogetFeeds === false){ 
		return false;
	}
	if($dogetFeeds->num_rows == 0){ 
		return 0;
	}
	$feeds = array();
	while($row = $dogetFeeds->fetch_object()){ 
		$feeds[] = $row;
	}
	return $feeds;
}

function getIncomingFeed($idFeedIn){ 
	dbCon();
	$getFeed = "SELECT * FROM `".DATABASE_NAME."`.`feedinc` "
		."WHERE `idFeedIn` = '".$idFeedIn."';";
	$dogetFeed = dbQry($getFeed, 'Fetching feed information', true);
	dbDcon();
	if($dogetFeed === false){ return false; }
	if($dogetFeed->num_rows == 0){ return 0; }
	return $dogetFeed->fetch_object();
}

function getIncomingUrls( $label ){
	$query  = "SELECT DISTINCT(urlTrim),LEFT(MIN(received),10) start ";
	$query .= "FROM `".DATABASE_NAME."`.`feedinc_" . $label . "` ";
	$query .= "WHERE urlTrim != '' AND urlTrim IS NOT NULL AND urlTrim NOT LIKE 'INVALID:%' ";
	$query .= "GROUP BY 1";

	dbCon();
	$result = dbQry( $query, 'Getting incoming feed URLs', true );

	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
	$values = array();
	while( $row = $result->fetch_object() ){
		$values[] = $row;
	}
	return $values;
}


//Outgoing Feed Functions//

function getOutgoingFeeds($subset = 'all'){ 	
	//Fetches all outgoing feeds into an array of objects.
	$getFeeds = "SELECT * FROM `".DATABASE_NAME."`.`feedout` ";
	switch($subset){ 
		case 'active':
			$getFeeds .= "WHERE `retired` = 0 ";
		break;
		case 'retired':
			$getFeeds .= "WHERE `retired` = 1 ";
		break;
	}
	$getFeeds .= ";";
	dbCon();
	$dogetFeeds = dbQry($getFeeds, 'Fetching feeds.', true);
	dbDcon();
	if($dogetFeeds === false){ 
		return false;
	}
	if($dogetFeeds->num_rows == 0){ 
		return 0;
	}
	$feeds = array();
	while($row = $dogetFeeds->fetch_object()){ 
		$feeds[] = $row;
	}
	return $feeds;
}

function getOutgoingFeed($idFeedOut){ 
	dbCon();
	$getFeed = "SELECT * FROM `".DATABASE_NAME."`.`feedout` "
		."WHERE `idFeedOut` = '".$idFeedOut."';";
	$dogetFeed = dbQry($getFeed, 'Fetching feed information', true);
	dbDcon();
	if($dogetFeed === false){ return false; }
	if($dogetFeed->num_rows == 0){ return 0; }
	return $dogetFeed->fetch_object();
}

//Population Functions//

function getPopulationStatus($idFeedOut){ 
	dbCon();
	$getPopulation = "SELECT * FROM `".DATABASE_NAME."`.`feedPopulation` "
		."WHERE `idFeedOut` = '".$idFeedOut."';";
	$dogetPopulation = dbQry($getPopulation, 'Fetching population status for feed', true);
	if($dogetPopulation === false){ 
		return 'Error';
	}
	if($dogetPopulation->num_rows == 0){ 
		return 'None Setup';
	}
	$enabled = 0;
	while($row = $dogetPopulation->fetch_object()){ 
		if($row->enabled){ $enabled++; }
	}
	if($enabled == 0){ 
		return 'Disabled';
	} elseif($enabled < $dogetPopulation->num_rows){ 
		return 'Partially Enabled';
	} else { 
		return 'Enabled';
	}
}

function getPopulationSettings($idFeedOut){ 
	dbCon();
	$getSettings = "SELECT * FROM `".DATABASE_NAME."`.`feedPopulation` "
		."WHERE `idFeedOut` = '".$idFeedOut."';";
	$dogetSettings = dbQry($getSettings, 'Fetching population settings', true);
	if($dogetSettings === false){ return false; }
	if($dogetSettings->num_rows == 0){ return 0; }
	$settings = array();
	while($row = $dogetSettings->fetch_object()){ 
		$settings[] = $row;
	}
	return $settings;
}

function getPopulationSetting($idAssoc){ 
	dbCon();
	$getSettings = "SELECT * FROM `".DATABASE_NAME."`.`feedPopulation` "
		."WHERE `idAssoc` = '".$idAssoc."';";
	$dogetSettings = dbQry($getSettings, 'Fetching population setting', true);
	if($dogetSettings === false){ return false; }
	if($dogetSettings->num_rows == 0){ return 0; }
	return $dogetSettings->fetch_object();
}

//Statistics//

function getDailyCount($idFeedIn, $date){
	dbCon();
	$getCount = "SELECT SUM(quantity) AS dailyCount "
		."FROM `".DATABASE_NAME."`.`urlcount` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` = '".$date."' "
		.";";
	$dogetCount = dbQry($getCount, 'Fetching incoming daily count', true);
	dbDcon();
	if($dogetCount === false){ return false; }
	$dailyCount = $dogetCount->fetch_assoc();
	$dailyCount = $dailyCount['dailyCount'];
	return $dailyCount;
}

function getDailyCountInvalid($idFeedIn, $date){
	dbCon();
	$getCount = "SELECT SUM(quantity) AS dailyCount "
		."FROM `".DATABASE_NAME."`.`urlcount_invalid` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` = '".$date."' "
		.";";
	$dogetCount = dbQry($getCount, 'Fetching incoming daily count of invalids', true);
	dbDcon();
	if($dogetCount === false){ return false; }
	$dailyCount = $dogetCount->fetch_assoc();
	$dailyCount = $dailyCount['dailyCount'];
	return $dailyCount;
}

function getUrlsForPeriod($idFeedIn, $dateStart, $dateEnd){ 
	dbCon();
	$getUrls = "SELECT `urlFull`, `urlTrim`, SUM(`quantity`) as totalQty "
		."FROM `".DATABASE_NAME."`.`urlcount` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` >= '".$dateStart."' "
			."AND `stamp` <= '".$dateEnd."' "
		."GROUP BY `urlFull` "
		.";";
	$dogetUrls = dbQry($getUrls, 'Fetching url list for feed', true);
	dbDcon();
	if($dogetUrls === false){ return false; }
	if($dogetUrls->num_rows == 0){ return 0; }
	$urlList = array();
	while($row = $dogetUrls->fetch_object()){ 
		$urlList[] = $row;
	}
	return $urlList;
}

function getUrlsForPeriodInvalid($idFeedIn, $dateStart, $dateEnd){ 
	dbCon();
	$getUrls = "SELECT `urlFull`, `urlTrim`, SUM(`quantity`) as totalQty "
		."FROM `".DATABASE_NAME."`.`urlcount_invalid` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` >= '".$dateStart."' "
			."AND `stamp` <= '".$dateEnd."' "
		."GROUP BY `urlFull` "
		.";";
	$dogetUrls = dbQry($getUrls, 'Fetching url list for feed', true);
	dbDcon();
	if($dogetUrls === false){ return false; }
	if($dogetUrls->num_rows == 0){ return 0; }
	$urlList = array();
	while($row = $dogetUrls->fetch_object()){ 
		$urlList[] = $row;
	}
	return $urlList;
}

function getCount($idFeedOut, $type){ 
	$table = date("Ym");
	$today = date("Y-m-d");
	dbCon();
	$getTotalPosted = "SELECT SUM(`".$type."`) as qty "
		."FROM `".DATABASE_NAME."`.`statistics_".$table."` "
		."WHERE `idFeedOut` = '".$idFeedOut."' "
		."AND `stamp` = '".$today."' "
		.";";
	$dogetTotalPosted = dbQry($getTotalPosted, 'Fetching count of posted', true);
	if($dogetTotalPosted === false){ return false; }
	if($dogetTotalPosted->num_rows == 0){ return 0; }
	$totalPosted = $dogetTotalPosted->fetch_assoc();
	$totalPosted = $totalPosted['qty'];
	return $totalPosted;
}
?>
