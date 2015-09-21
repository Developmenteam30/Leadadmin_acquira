<?php //ADMIN_ROOT/pushLead/_f_onlms.php
//Version 9.5
//ES 2013 08 23 v9.5: Updated to add urlAssign field as a mappable, and compiled from the urlassignments parameter.
chdir(dirname(__FILE__));
require_once("../includes/c_config.php");
$mysqlErrorSource = 'Outgoing Feed Process Script';
require_once(INCLUDES."_connx.php");
require_once(INCLUDES."processFunctions.php"); //Validation Functions
require_once("_f_curl.php"); //Easy to use curl function
//Constants for Script running
define("MAX_RUNTIME_POST_QUERY", 15);
define("MAX_RUNTIME_PROCESS", 200);
define("MAX_SLAVE_LAG_ALLOWED", 1);

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

function assignValue( $key, $value, &$requestdata ) {

    if( strpos( $key, '|' ) !== FALSE ) {
        $vars = explode('|', $key );
        $requestdata[$vars[0]][$vars[1]] = $value;
    } else {
        $requestdata[$key] = $value;
    }
}

function main_pre()
{ //main_pre VER 1.1	
	global $runtime;
	ob_implicit_flush(); 
	//This is turned on so that I can echo out portions of the script AS the script is running.
	//Otherwise, the script wouldn't show anything meaningful until after it's run, 
	//Also prevents browser timeouts from occurring because of nothing being output to the browser.
	$runtime = microtime(true); //Recording the scripts total runtime, starting point here.
}

function main_post()
{ //main_post VER 1.4
	global $settings;
	global $runtime;
	global $results;
	
	$settings['end'] = false;
	$settings['statsDate'] = date("Y-m-d");//For reporting purposes, need to know what table we're going to post statistics to, and what stamp to use.
	if(is_null($settings['idFeedOut'])){ 
		$settings['end'] = true;
		echo "No feed id set. Ending.\n"; @ob_flush(); flush();
	}
	if(!$settings['end']){
		$settings['feedParams'] = getOutgoingFeed($settings['idFeedOut']);
		if($settings['feedParams'] === false){ 
			$settings['end'] = true; 
			logError(
				'Outgoing Feed'.$settings['idFeedOut']
				, 'Failed to fetch feed parameters.'
				, true
			);
		}
	}
	if(!$settings['end']) { 
		echo "Feed Label: ".$settings['feedParams']->label."\n"; @ob_flush(); flush();
		if($settings['testing'] == 1) { 
			echo "[][][][]TEST MODE[][][][]\n"; 
		}elseif($settings['samplerun'] == 1) { 
			echo "[][][][]SAMPLE RUN MODE[][][][]\n"; 
		}else { 
			echo "[][][][]NORMAL MODE[][][][]\n"; 
		}
	}
	if(!$settings['end']) { 
		zeroout($runtime); //Creates all the results variables I need.
	}
	if(!$settings['end']) { 
		lockfile(); //checks to see if the file is locked, if it is, this script will end here. //Otherwise it will lock the file so that it can't run over itself.
	}
	if(!$settings['end']) { 
		dbasesettings(); //Retrieves the database based settings.
	}
	if(!$settings['end']) { 
		$leadset = queryset(); //Queries the database for the leads based on the delay settings, 
		//Stores those into the variable for use in the process function.
	}
	if(!$settings['end']) { 
		$updatequery = process($leadset); //Processes the queried leads through curl to the list manager.
		//Stores the responses with email associations in the array for 
		//use in the update_records function.
	}
	if(!$settings['end'] && LEGACY_DB ) { 
		update_records($updatequery); //Updates the database with the appropriate responses per email.
	}
	$results['runtime'] = microtime(true) - $results['runtime']; 
	//Finalizes the important runtime of the script.
	if(!$settings['end']) { 
		finish(); //This is the reporting function. Statistics compiling will occur at this step. 
	}	
	if(!$settings['end']) { 
		throttle_reset(); //Resets the amount of leads the current feed will post for the next run.
	}
	unlockfile(); //Unlocks the file. Runover can occur at this point. Which is ok, since everything is done.
}

function getOutgoingFeed($idFeedOut){ 
	dbCon();
	$getFeed = "SELECT * FROM `".DATABASE_NAME."`.`feedout` WHERE `idFeedOut` = '".$idFeedOut."';";
	$dogetFeed = dbQry($getFeed, 'Fetching feed information', true);
	dbDcon();
	if($dogetFeed === false){ return false; }
	if($dogetFeed->num_rows == 0){ return 0; }
	return $dogetFeed->fetch_object();
}

function getActiveFeeds( $mod = null ){ 
	dbCon();
	if( !empty( $mod ) ) {
		$getFeed = "SELECT idFeedOut FROM `".DATABASE_NAME."`.`feedout` WHERE cron = '1' AND status IN( 'active', 'hidden' ) AND MOD(idFeedOut,2) = " . ( 'even' === $mod ? 0 : 1 );
	} else {
		$getFeed = "SELECT idFeedOut FROM `".DATABASE_NAME."`.`feedout` WHERE cron = '1' AND status IN( 'active', 'hidden' )";
	}
	$dogetFeed = dbQry($getFeed, 'Fetching active feeds', true);
	dbDcon();
	if($dogetFeed === false){ return false; }
	if($dogetFeed->num_rows == 0){ return 0; }
	return $dogetFeed;
}

function storeFeedOutgoingAttribute($idFeedOut, $attribute, $value){ 
	dbCon("insertUpdate");
	$updateFeed = "UPDATE `".DATABASE_NAME."`.`feedout` "
		."SET `".$attribute."` = '".$value."' "
		."WHERE `idFeedOut` = '".$idFeedOut."';";
	$doupdateFeed = dbQry($updateFeed, 'Updating feed attribute', true);
	if($doupdateFeed === false){ return false; }
	else { return true; }
}

function zeroout($runtime)
{	//VER 1.1
	global $results;
	$results = array(
		"runtime" => $runtime,
		"updatetime" => 0,
		'count' => 0,
		'timeouts' => 0,
		'to_standard' => 0,
		'to_namelookup' => 0,
		'to_hostconn' => 0,
		'skips' => 0,
		'skip_edf' => 0,
		'skip_sdf' => 0,
		'skip_tdf' => 0,
		'skip_ie' => 0,
		'skip_bd' => 0,
		'skip_ms' => 0,
		'skip_bs' => 0, 
		'skip_fr' => 0,
		'finish' => "",
		'locktime' => 0
	);
}

function lockfile()
{ //VER 2.5
	global $settings;
	global $results;
	$locktime = microtime(true); //Record how long it takes to run this function
	
	$filename = $settings['feedParams']->label;	
	$results['lockfile'] = SITE_ROOT."pushLead".FD."lockfiles".FD.$filename;
	if (!file_exists($results['lockfile']))
	{
		echo "Creating lockfile ".$results['lockfile']."\n"; @ob_flush(); flush();
		touch($results['lockfile']);
		chmod($results['lockfile'], 0777);
	}
	
	$results['lock'] = fopen($results['lockfile'], "r+");
	if($results['lock'] === false)
	{	
		$settings['end'] = true;
		logError(
			'Outgoing Feed '.$settings['feedParams']->label
			, 'Failure to create/access lock file.'
			, true
		);
		echo "Could not access lock file. Permission denied. Ending script.\n";
	}
	
	if(!flock($results['lock'], LOCK_EX | LOCK_NB))
	{
		$settings['end'] = true;
		echo "Script already running. Ending script.\n";
	}
	
	$results['locktime'] = microtime(true) - $locktime;
}

function unlockfile()
{	//VER 2.2
	global $results;
	if(!is_null($results['lock'])){ 
		flock($results['lock'], LOCK_UN);
		fclose($results['lock']);
	}
}

function dbasesettings()
{	//VER 1.9
	global $settings;
	global $results;
	
	$results['dbasetime'] = microtime(true); //Record how long it takes to run this function.
	if($settings['testing'] == 1)
	{ //Testing: Will echo this out as it happens.
		echo "||||DATABASE RETRIEVED SETTINGS||||\n"; @ob_flush(); flush();
	}
	
	if(!$settings['end']) { 
		//Should this list to mailer association be processed?
		if( 'retired' == $settings['feedParams']->status) { 
			echo "This feed is deactivated. Script exiting.\n";
			$settings['end'] = true; //List to mailer assoc is inactive, end the script.
		}
	}
	if(!$settings['end']) {
		//Should this list to mailer association be running on cron?
		if($settings['cron'] == 1 && !$settings['feedParams']->cron) { 
			echo "Running this List->Mailer Association script on CRON, but it is disabled for this association. Ending script.\n";
			$settings['end'] = true; //List to mailer assoc is inactive, end the script.
		}
		else { 	//This association is ok to run on CRON, but should we run it right this minute?
			$timing = $settings['feedParams']->cronTiming;
			$curMin = date("i"); //get current minute
			if($curMin%$timing != 0) { 
				//We're running this script every X minute (timing) if the current minute (curMin) is divisible by X, 
				//with no remainder than this minute is acceptable, but since in this case it's off, end the script.
				$settings['end'] = true;
			}
		}
	}
	
	if(!$settings['end']) { 
		$throttle = $settings['feedParams']->throttle;
		if($settings['testing'] == 1) { //Testing mode - only process one lead.
			$throttle = 1;
		}
		if($settings['forcethrottle'] > 0) { //Forcing throttle in given parameters. 
			$throttle = $settings['forcethrottle'];
		}
		if($settings['testing'] == 1) { 
			echo "Throttling: ".$throttle."\n";
		}
		$settings['feedParams']->throttle = $throttle;
	}
	
	$results['dbasetime'] = microtime(true) - $results['dbasetime'];
}

function queryset()
{
	global $settings;
	global $leads;

	$query = $leads->getOutboundQueue( $settings['feedParams']->idFeedOut );

	if( empty( $query ) ) {
		$settings['end'] = true; 
		echo "No updates.\n"; @ob_flush(); flush();
		return false;
	}

	return $query;
}

function process($leadset)
{	//VER 1.3
	global $settings;
	global $results;
	global $leads;
	
	$updatequery = array(); 
	$results['statistics'] = array();

	$posttime = microtime(true); //Record how long it takes to process all leads.
	if($settings['testing'] == 1)
	{ // Testing mode will echo out everything as it runs.
		echo "PROCESSING\n\n"; @ob_flush(); flush();
	}
	while ( $leaddata = $leadset->fetch( PDO::FETCH_ASSOC ) ) {

		//Check to make sure we haven't run over the allotted time for processing.
		$current_posttime = microtime(true) - $posttime;
		if($settings['testing'] == 1 || $settings['samplerun'] == 1)
		{ // Testing mode will echo out everything as it runs, as will sample run.
			echo "RunTime: ".number_format($current_posttime, 2, ".", "")."\n"; @ob_flush(); flush();
		}
		
		if($current_posttime > MAX_RUNTIME_PROCESS) {
			if($settings['testing'] == 1)
			{ // Testing mode will echo out everything as it runs.
				echo "Ending posting due to time constraints.\n"; @ob_flush(); flush();
			}
			//$settings['end'] = true;
			$results['finish'] .= "Posting cancelled due to time.\n";
			break; //Break out of the while loop going through the queried leads.
		}
		$response = array(	//Structure of the response. 
			'text' => '',
			'status' => '', 
			'url' => '',
			'idFeedOut' => $settings['feedParams']->idFeedOut
		);
		if($leaddata['email'] != '' && $leads->checkSuppression($leaddata['email'], null )){
			$response = array(
				'text' => 'Email is suppressed (global).'
				, 'status' => 2
				, 'url' => $leaddata['url']
				, 'idFeedOut' => $settings['feedParams']->idFeedOut
			);
			$leads->outboundProcess( $leaddata['idRecord'], $settings['feedParams']->idFeedOut, $leaddata['url'], 'LOCAL REJECTION: Email is suppressed (global)' );
		} elseif($leaddata['email'] != '' && $leads->checkSuppression($leaddata['email'], $settings['feedParams']->idCompany)) {
			$response = array(
				'text' => 'Email is suppressed (company).'
				, 'status' => 2
				, 'url' => $leaddata['url']
				, 'idFeedOut' => $settings['feedParams']->idFeedOut
			);
			$leads->outboundProcess( $leaddata['idRecord'], $settings['feedParams']->idFeedOut, $leaddata['url'], 'LOCAL REJECTION: Email is suppressed (company)' );
		} else {
			$response = runlead($leaddata, $settings['feedParams']);
		}
		
		if($settings['samplerun'] == 1) { 
			print_r($leaddata);
			print_r($response);
		}
		//Statistics compiling
		if(!isset($results['statistics'][$response['url']][$response['idFeedOut']])) { 
			switch($response['status']) { 
				case '0': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['win'] = 0;
					$results['statistics'][$response['url']][$response['idFeedOut']]['fail'] = 1;
					$results['statistics'][$response['url']][$response['idFeedOut']]['skip'] = 0;
				break;
				case '1': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['win'] = 1;
					$results['statistics'][$response['url']][$response['idFeedOut']]['fail'] = 0;
					$results['statistics'][$response['url']][$response['idFeedOut']]['skip'] = 0;
				break;
				case '2': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['win'] = 0;
					$results['statistics'][$response['url']][$response['idFeedOut']]['fail'] = 0;
					$results['statistics'][$response['url']][$response['idFeedOut']]['skip'] = 1;
				break; 
			}
		}
		else { 
			switch($response['status']) { 
				case '0': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['fail'] += 1;
				break;
				case '1': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['win'] += 1;
				break;
				case '2': 
					$results['statistics'][$response['url']][$response['idFeedOut']]['skip'] += 1;
				break; 
			}
		}
		//Each seperate response is stored in an array with the emails that received that response in a sub array.
		//This line will create a new array cell if the response doesn't already exist.
		if(!isset($updatequery[addslashes($response['text'])])) { $updatequery[addslashes($response['text'])] = array(); }
		$updatequery[addslashes($response['text'])][] = $leaddata['idRecord']; //Adds the idRecord to the sub array for the specific response.
		$results['count']++; //Number of processed leads tallied.
		unset($response);
	}
	
	//Store how long it takes to process all leads into results array.
	$results['posttime'] = microtime(true) - $posttime; 
	
	return $updatequery;
}

function runlead($leaddata, $fP)
{
	global $settings;
	global $leads;
	
	$response = "";
	
	$posturl = $fP->postUrl;
	$staticFields = explode(";", $fP->staticFields);
	$varFields = explode(";", $fP->varFields); 
	$fieldMap = explode(";", $fP->fieldMap);
	$requestdata = array();
	foreach($staticFields as $sF) { //Compile Static Fields into the post array.
		if($sF != '') { 
			$fieldValuePair = explode("=", $sF);
			assignValue( $fieldValuePair[0], $fieldValuePair[1], $requestdata );
		}
	}
	if( empty( $leaddata['stamp'] ) ) {
		$leaddata['stamp'] = $leaddata['leadstamp'];
	}
	for($count = 0; $count < count($varFields); $count++) { //Compile mapped fields into the post array.
		if($varFields[$count] != '') { 
			switch($fieldMap[$count]){
				case 'urlAssign':
					$urlassignments = explode(";", $fP->urlassignments);
					$urlassignment = '';
					foreach($urlassignments as $instructions){
						if($instructions != ''){
							$fieldValuePair = explode("=", $instructions);
							if(stripos($leaddata['url'], $fieldValuePair[0]) !== false){
								if($settings['testing']){
									echo "Matched assignment: ".$fieldValuePair[0]."\r\n";
								}
								$urlassignment = $fieldValuePair[1]; break;
							}
						}
					}
					assignValue( $varFields[$count], $urlassignment, $requestdata );
					break;
				case 'dobUS':
					assignValue( $varFields[$count], date("m-d-Y", strtotime($leaddata['dob'])), $requestdata );
					break;
				case 'stampUS':
					assignValue( $varFields[$count], date("m-d-Y H:i:s", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stampUS_dateOnly':
					assignValue( $varFields[$count], date("m-d-Y", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stamp_YYYYmmdd':
					assignValue( $varFields[$count], date("Ymd", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stamp_YYYY-mm-dd':
					assignValue( $varFields[$count], date("Y-m-d", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stampUSAMPM':
					assignValue( $varFields[$count], date("m-d-Y h:i:sA", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stampUS+AMPM':
					assignValue( $varFields[$count], date("m-d-Y h:i:s A", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'stampUS_slashes':
					assignValue( $varFields[$count], date("m/d/Y H:i:s", strtotime($leaddata['stamp'])), $requestdata );
					break;
				case 'url':
					if( !empty( $leaddata['urlRewrite'] ) ) {
						assignValue( $varFields[$count], $leaddata['urlRewrite'], $requestdata );
					} else {
						assignValue( $varFields[$count], $leaddata[$fieldMap[$count]], $requestdata );
					}
					break;
				default:
					assignValue( $varFields[$count], $leaddata[$fieldMap[$count]], $requestdata );
					break;
			}
		}
	}
	
	if($settings['testing'] == 1) { 
		echo "Posting Array: \n"; @ob_flush(); flush();
		print_r($requestdata);
	}

	if($fP->feedType == 'curlGET') { 
		#GET method to be used, so compile data onto the url string.
		$geturl = $posturl."?";
		$flag = false;
		foreach($requestdata as $field => $value) { 
			if($flag) $geturl .= "&";
			$geturl .= $field."=".urlencode($value);
			$flag = true;
		}
		if($settings['testing'] == 1) { 
			echo "Get URL: \n"; @ob_flush(); flush();
			echo $geturl."\n"; @ob_flush(); flush();
			echo "Posting data.\n"; @ob_flush(); flush();
		}
		$response['text'] = addslashes(
			PushLead(
				"", 
				$geturl, 
				false
			)
		);
	} else if( 'csvString' == $fP->feedType ) {
		#GET method to be used, so compile data onto the url string.
		$geturl = $posturl."?data=";
		$flag = false;
		foreach($requestdata as $field => $value) { 
			if($flag) $geturl .= ",";
			$geturl .= urlencode( str_replace( ',', '', $value ) );
			$flag = true;
		}
		if($settings['testing'] == 1) { 
			echo "Get URL: \n"; @ob_flush(); flush();
			echo $geturl."\n"; @ob_flush(); flush();
			echo "Posting data.\n"; @ob_flush(); flush();
		}
		$response['text'] = addslashes(
			PushLead(
				"", 
				$geturl, 
				false
			)
		);
		
	} else if( 'JSON' == $fP->feedType ) { //Method is JSON
		if($settings['testing'] == 1) { 
			echo "Posting data.\n"; @ob_flush(); flush();
		}
		$response['text'] = addslashes(
			PushLead(
				json_encode( $requestdata ),
				$posturl, 
				true,
				false,
				true,
				false,
				array( 'Content-Type: application/json' )
			)
		);
	} else { //Method is post
		if($settings['testing'] == 1) { 
			echo "Posting data.\n"; @ob_flush(); flush();
		}
		$response['text'] = addslashes(
			PushLead(
				$requestdata, 
				$posturl, 
				true
			)
		);
	}
	$response['url'] = $leaddata['url']; 
	$response['idFeedOut'] = $fP->idFeedOut;
	//Check if the response we got is a success for this feed.

	if( strpos( $fP->successString, 'REGEX:' ) === 0 ) {
		// Check for a regular expression match
		if( preg_match( substr( $fP->successString, 6 ), $response['text'] ) === 1 ) {
			$response['status'] = 1;
		} else {
			$response['status'] = 0;
		}

	} else {
		// Check for a direct substring comparison match
		$sucstr = str_replace('%', '', $fP->successString); //Remove mysql wildcards
		if(stripos($response['text'], $sucstr) !== false) { 
			$response['status'] = 1;
		} else { 
			$response['status'] = 0;
		}
	}
	
	if($settings['testing'] == 1) { 
		echo "Response information: \n";
		print_r($response);
	}

	if( !empty( $settings['testrecord'] ) ) {
		if( 'curlPOST' == $fP->feedType ) {
			$geturl = $posturl."?";
			$flag = false;
			foreach($requestdata as $field => $value) {
				if($flag) $geturl .= "&";
				$geturl .= $field."=".urlencode($value);
				$flag = true;
			}
		} else if( 'JSON' == $fP->feedType ) {
			$geturl = $posturl . "?" . json_encode( $requestdata );
		}
		$response['querystring'] = $geturl;
	} else {
		$leads->outboundProcess( $leaddata['idRecord'], $fP->idFeedOut, $leaddata['url'], ( $response['status'] ? null : trim( $response['text'] ) ) );
	}

	unset($requestdata);
	return $response;
}

function update_records($updatequery)
{	//VER 1.3
	
	global $settings;
	global $results; 
	
	$updatetime = microtime(true);	
	if($settings['testing'] == 1)
	{
		echo "Updating processed leads.\n"; @ob_flush(); flush();
	}

	if($updatequery)
	{
		dbCon();
		foreach($updatequery as $delayresponse => $emails)
		{
			if($settings['testing'] == 1) { echo "Delayresponse: ".$delayresponse."\n"; @ob_flush(); flush(); }
			foreach($emails as $email)
			{
				$rightnow = date("Y-m-d H:i:s");
				$query2 =
					"UPDATE "
						."`".DATABASE_NAME."`. "
						."`feedout_".$settings['feedParams']->label."` "
					."SET "
						."poststamp = '".$rightnow."' "
						.", postresponse = '".$GLOBALS['dbconnx']->escape_string($delayresponse)."' "
						.", processed = '1' "
					."WHERE "
						."processed = '0' "
						."AND idRecord = '".$email."' "
					.";";			
				if($settings['testing'] == 1)
				{
					echo "UPDATE QUERY: ".$query2."\n"; @ob_flush(); flush();
				}				
				$doquery2 = dbQry($query2, 'Updating entries with post reponses.', true);
			}
		}
		dbDcon();
		$results['updatetime'] = microtime(true) - $updatetime;

		if($settings['samplerun'] == 1)
		{
			echo "Updates ran in ".number_format($results['updatetime'], 2, ".", "")." seconds.\n"; @ob_flush(); flush();
		}
	}
}

function finish()
{	//VER 1.6
	global $settings;
	global $results;
	
	$statsTime = microtime(true);
	if($settings['testing'] == 1)
	{
		echo "STATISTICS FILING\n";	@ob_flush(); flush();
	}
	//Confirm the existence of a table for this month's statistics. 
	$statsDate = date("Ym", strtotime($settings['statsDate']));
	$checkTable = "SHOW TABLES FROM `".DATABASE_NAME."` "
		."WHERE `Tables_in_".DATABASE_NAME."` = 'statistics_".$statsDate."' "
		.";";
	dbCon();
	$docheckTable = dbQry($checkTable, 'Checking existence of stats table.', true);
	dbDcon();
	if($docheckTable->num_rows == 0 ) { //Stats table for this month doesn't exist, create it real quick.
		if($settings['testing'] == 1)
		{
			echo "Stats table for this month does not exist. Creating.\n";	@ob_flush(); flush();
		}
		$createStatsTable = 
			"CREATE  TABLE `".DATABASE_NAME."`.`statistics_".$statsDate."` "
			."( "
				."`idFeedOut` INT, "
				."`url` VARCHAR(250) NOT NULL , "
				."`urlRef` VARCHAR(100) NOT NULL , "
				."`stamp` DATE NOT NULL , "
				."`win` INT NULL DEFAULT 0 , "
				."`fail` INT NULL DEFAULT 0 , "
				."`skip` INT NULL DEFAULT 0 , "
				."UNIQUE INDEX `uniqueness` (`idFeedOut` ASC, `url` ASC, `stamp` ASC) "
				.") "
			.";";
		dbCon("insertUpdate");
		$docreateStatsTable = dbQry($createStatsTable, 'Creating stats table', true); 
		dbDcon();
		if($settings['testing'] == 1)
		{
			if($docreateStatsTable == false) { 
				logError(
					'Outgoing Feed '.$settings['feedParams']->label
					, 'Attempted to create statistics table, but failed. Check MySQL Logs.'
					, true
				);
				echo "Failed to create stats table, please check mysql error logs.\n";	@ob_flush(); flush();
			} else { 
				echo "Stats table created successfully.\n";	@ob_flush(); flush();
			}
		}
	}
	if($settings['testing'] == 1)
	{
		echo "Stats table for this month exists. Uploading stats.\n";	@ob_flush(); flush();
	}
	//Update statistics.
	dbCon("insertUpdate");
	$updateCount = 0;
	$mysqlFailures = 0;	
	if($settings['testing'] == 1 || $settings['samplerun'] == 1)
	{
		echo "Updating statistics.\n";	@ob_flush(); flush();
	}
	foreach($results['statistics'] as $url => $feed) { 
		foreach($feed as $idFeed => $stats) { 
			if($settings['samplerun'] == 1) { 
				echo "URL : ".$url." / idFeed : ".$idFeed."\n";
				print_r($stats); @ob_flush(); @flush();
			}
			$updateStats = 
				"INSERT INTO `".DATABASE_NAME."`.`statistics_".$statsDate."` "
				."( "
					." `idFeedOut` "
					.", `url` "
					.", `urlRef` "
					.", `stamp` "
					.", `win` "
					.", `fail` "
					.", `skip` "
				.") "
				."VALUES "
				."( "
					."'".$settings['feedParams']->idFeedOut."' "
					.", '".$url."' "
					.", '".url_reformat($url)."' "
					.", '".$settings['statsDate']."' "
					.", '".$stats['win']."' "
					.", '".$stats['fail']."' "
					.", '".$stats['skip']."' "
				.") "
				."ON DUPLICATE KEY UPDATE "
				."`win` = `win`+".$stats['win']." "
				.", `fail` = `fail`+".$stats['fail']." "
				.", `skip` = `skip`+".$stats['skip']." "
				.";";
			$doupdateStats = dbQry($updateStats, 'Updating statistics.', true);			
			if($doupdateStats == false) { $mysqlFailures++; } else { $updateCount++; }
		}
	}
	if($settings['testing'] == 1)
	{
		echo "Updated ".$updateCount." URL entries.\n";	@ob_flush(); flush();
		if($mysqlFailures > 0) { 
			logError(
				'Outgoing Feed '.$settings['feedParams']->label
				, 'Failed to update on '.$mysqlFailures.' URL entries. Check MySQL Logs.'
				, true
			);
			echo "Failed to update on ".$mysqlFailures." URL entries, check the mysql error logs.\n";	@ob_flush(); flush();
		}
	}
	dbDcon();
	$results['statstime'] = microtime(true) - $statsTime;
	if($settings['samplerun'] == 1) { 
		echo "Statistics ran in ".number_format($results['statstime'], 2, ".", "")." seconds.\n";
	}
}

function throttle_reset()
{	//VER 1.4
	global $settings;
	global $results;
	
	if($settings['testing'] == 0 && $settings['forcethrottle'] == 0) { 
		$totalTime = 40 - ($results['updatetime'] + $results['statstime']); //Total of fourty seconds to process leads, update leads, and update statistics.
		$processTime = $results['posttime'];
		$deltaTime = $totalTime - $processTime; //Difference between the preferred total process time and the actual processed time. 
		$leads = $results['count'];
		$speed = $leads / $processTime; //Speed the feed was processing leads at.
		$throttleAdjust = $deltaTime * $speed; //If delta is negative, it will subtract from throttle instead of add. 
		$throttleAdjust = number_format($throttleAdjust, 0); //Remove trailing decimal, we can only use integers.
		$newThrottle = $settings['feedParams']->throttle + $throttleAdjust; //Adjust the current throttle to the new one.
		$updateThrottle = storeFeedOutgoingAttribute($settings['feedParams']->idFeedOut, 'throttle', $newThrottle);
	}else { 
		if($settings['forcethrottle'] > 0) { 
			echo "Throttle was forced - throttle update disabled.\n"; @ob_flush(); @flush();
		}else { 
			echo "Test mode - throttle update disabled.\n"; @ob_flush(); @flush();
		}
	}
	
	if($settings['samplerun'] == 1 && $settings['forcethrottle'] == 0) { 
		echo "Throttle updating to ".$newThrottle.".\n"; @ob_flush(); @flush();
		if($updateThrottle === false) { 		
			echo "Throttle failed to update, check mysql error logs.\n"; @ob_flush(); @flush();
		}else { 
			echo "Throttle updated.\n"; @ob_flush(); @flush();
		}		
	}
}
?>
