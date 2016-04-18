<?php

//pcntl_fork();
set_time_limit( 0 );

if( empty( $argv[1] ) ) {
	print "No feed id specified\n";
	die();
}

chdir(dirname(__FILE__));

function signalHandler($signal) {
	global $running;
	// Tell the main loop to stop running so we can exit gracefully
	$running = false;
}

function assignValue( $key, $value, &$requestdata ) {

	if( strpos( $key, '|' ) !== FALSE ) {
		$vars = explode('|', $key );
		$requestdata[$vars[0]][$vars[1]] = $value;
	} else {
		$requestdata[$key] = $value;
	}
}

$running = true;
$debug = true;
$idFeedOut = $argv[1];

require_once("_f_curl.php"); //Easy to use curl function
require_once("../includes/c_config.php");
require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$pidFile = sprintf( '/tmp/pushlead-%d-%d', intval( $idFeedOut ), getmypid() );
$fh = fopen( $pidFile, 'w' );
if( !$fh ) {
	print "Unable to write PID file\n";
	die();
}
fwrite( $fh, $idFeedOut );
fclose( $fh );

declare(ticks = 1);
 
pcntl_signal( SIGTERM, 'signalHandler' );// Termination ('kill' was called)
pcntl_signal( SIGHUP, 'signalHandler' ); // Terminal log-out
pcntl_signal( SIGINT, 'signalHandler' ); // Interrupted (Ctrl-C is pressed)

$feedOut = $leads->getOutboundFeed( $idFeedOut );

if( empty( $feedOut ) ) {
	print "Feed {$idFeedOut} does not exist!\n";
	@unlink( $pidFile );
	die();
}

if( empty( $feedOut->cron ) ) {
	print "Outbound processing is paused for feed {$feedOut->idFeedOut}\n";
	@unlink( $pidFile );
	die();
}

$empties = 0;

while($running) {

	$rows = $leads->getOutboundQueueRecord( $feedOut->idFeedOut );
	
	if( empty( $rows ) || !is_array( $rows ) ) {
		print "Empty response returned\n";
		$empties++;
		if( $empties > 10 ) {
			print "Too many empty responses ... dying off now\n";
			$running = false;
		}
		sleep(5);
		continue;
	}
	
	$empties = 0;
	
	print "Found " . sizeOf( $rows ) . " pending records in the queue\n\n";
	
	$staticFields = explode( ";", $feedOut->staticFields );
	$varFields = explode( ";", $feedOut->varFields ); 
	$fieldMap = explode( ";", $feedOut->fieldMap );
	
	foreach( $rows as $row ) {
	
		print "\n\n\nRecord: {$row->idRecord} (" . getmypid() . ")\n";
	
		// Override the outbound URL
		if( !empty( $row->urlRewrite ) ) {
			$row->url = $row->urlRewrite;
		}
	
		// Check for legacy stamp field
		if( empty( $row->stamp ) ) {
			$row->stamp = $row->leadstamp;
		}
	
		// Check global and local suppression lists
		if( !empty( $row->email ) && $leads->checkSuppression( $row->email, null ) ) {
			
			$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url, 'LOCAL REJECTION: Email is suppressed (global)' );
	
			print "\tLOCAL REJECTION: Email is suppressed (global)\n";
			continue;
	
		} else if( !empty( $row->email ) && $leads->checkSuppression( $row->email, $feedOut->idCompany ) ) {
	
			$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url, 'LOCAL REJECTION: Email is suppressed (company)' );
	
			print "\tLOCAL REJECTION: Email is suppressed (company)\n";
			continue;
	
		} 
	
		$requestdata = array();
		foreach( $staticFields as $sF ) { //Compile Static Fields into the post array.
			if( !empty( $sF ) ) {
				$fieldValuePair = explode( "=", $sF );
				assignValue( $fieldValuePair[0], $fieldValuePair[1], $requestdata );
	        }
		}
	
		for( $count = 0; $count < count( $varFields); $count++ ) { //Compile mapped fields into the post array.
			if( !empty( $varFields[$count] ) ) { 
				switch( $fieldMap[$count] ){
					case 'urlAssign':
						$urlassignments = explode( ";", $feedOut->urlassignments );
						$urlassignment = '';
						foreach( $urlassignments as $instructions ) {
							if( !empty( $instructions ) ) {
								$fieldValuePair = explode( "=", $instructions );
								if( stripos( $row->url, $fieldValuePair[0]) !== false ) {
									if( $debug ) {
										echo "\tMatched assignment: " . $fieldValuePair[0] . "\n";
									}
									$urlassignment = $fieldValuePair[1];
									break;
								}
							}
						}
						assignValue( $varFields[$count], $urlassignment, $requestdata );
						break;
	
					case 'dobUS':
						assignValue( $varFields[$count], date("m-d-Y", strtotime( $row->dob ) ), $requestdata );
						break;
	
					case 'stampUS':
						assignValue( $varFields[$count], date("m-d-Y H:i:s", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stampUS_dateOnly':
						assignValue( $varFields[$count], date("m-d-Y", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stamp_YYYYmmdd':
						assignValue( $varFields[$count], date("Ymd", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stamp_YYYY-mm-dd':
						assignValue( $varFields[$count], date("Y-m-d", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stampUSAMPM':
						assignValue( $varFields[$count], date("m-d-Y h:i:sA", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stampUS+AMPM':
						assignValue( $varFields[$count], date("m-d-Y h:i:s A", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					case 'stampUS_slashes':
						assignValue( $varFields[$count], date("m/d/Y H:i:s", strtotime( $row->stamp ) ), $requestdata );
						break;
	
					default:
						assignValue( $varFields[$count], $row->{$fieldMap[$count]}, $requestdata );
						break;
	
				}
			}
		}
	
		if( $debug ) { 
			echo "\tPosting Array: \n";
			print_r($requestdata);
		}
	
		if( $feedOut->feedType == 'curlGET' ) { 
	
			#GET method to be used, so compile data onto the url string.
			$geturl = $feedOut->postUrl . "?";
			$flag = false;
			foreach( $requestdata as $field => $value ) { 
				if( $flag ) {
					$geturl .= "&";
				}
				$geturl .= $field . "=" . urlencode( $value );
				$flag = true;
			}
			if( $debug ) { 
				echo "\tGet URL: \n";
				echo "\t" . $geturl."\n";
				echo "\tPosting data.\n";
			}
			$response = PushLead(
				"", 
				$geturl, 
				false
			);
	
		} else if( 'csvString' == $feedOut->feedType ) {
	
			#GET method to be used, so compile data onto the url string.
			$geturl = $feedOut->postUrl . "?data=";
			$flag = false;
			foreach( $requestdata as $field => $value ) { 
				if( $flag ) {
					$geturl .= ",";
				}
				$geturl .= urlencode( str_replace( ',', '', $value ) );
				$flag = true;
			}
			if( $debug ) { 
				echo "\tGet URL (CSV): \n";
				echo "\t" . $geturl."\n";
				echo "\tPosting data.\n";
			}
			$response = PushLead( "", $geturl, false );
	
		} else if( 'JSON' == $feedOut->feedType ) { //Method is JSON
	
			if( $debug ) { 
				echo "\tPosting JSON data.\n";
			}
			$response = PushLead(
				json_encode( $requestdata ),
				$feedOut->postUrl, 
				true,
				false,
				true,
				false,
				array( 'Content-Type: application/json' )
			);
	
		} else { //Method is post
	
			if( $debug ) { 
				echo "\tPosting data.\n";
			}
			$response = PushLead(
				$requestdata, 
				$feedOut->postUrl, 
				true
			);
		}
	
		//Check if the response we got is a success for this feed.
		if( strpos( $feedOut->successString, 'REGEX:' ) === 0 ) {
	
			// Check for a regular expression match
			if( preg_match( substr( $feedOut->successString, 6 ), $response ) === 1 ) {
				$status = 1;
			} else {
				$status = 0;
			}
	
		} else {
	
			// Check for a direct substring comparison match
			$sucstr = str_replace( '%', '', $feedOut->successString ); //Remove mysql wildcards
			if( stripos( $response, $sucstr ) !== false ) { 
				$status = 1;
			} else { 
				$status = 0;
			}
		}
	
		if( $debug ) { 
			echo "\tResponse: {$response}\n";
		}
	
		$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url, ( $status ? null : trim( $response ) ) );
	
		unset($requestdata);
	
	}

}

print "Finished!\n";

@unlink( $pidFile );
