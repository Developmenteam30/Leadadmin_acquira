<?php
require_once("../../includes/c_config.php");
require_once( INCLUDES . 'leads.php' );

Header('Content-Type: text/xml');

function updateStats( $idFeedOut, $url, $win, $fail, $skip ) {

	$statsDate = date( 'Ym' );
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
                    ."'".$idFeedOut."' "
                    .", '".$url."' "
                    .", '".url_reformat($url)."' "
                    .", '".date('Y-m-d')."' "
                    .", '".$win."' "
                    .", '".$fail."' "
                    .", '".$skip."' "
                .") "
                ."ON DUPLICATE KEY UPDATE "
                ."`win` = `win`+".$win." "
                .", `fail` = `fail`+".$fail." "
                .", `skip` = `skip`+".$skip." "
                .";";
            $doupdateStats = dbQry($updateStats, 'Updating statistics.', true);
}

$c = true; 
$result = array(
	'success' => 'false'
	, 'reason' => 'None.'
);

$feedLabel = getenv('FEED_LABEL');
if( empty( $feedLabel ) ) {
	$c = false;
	$result['reason'] = "Feed label is not set.";
}

$pattern = '/^[a-z][a-z0-9_]*$/';
if( $c && !preg_match( $pattern, $feedLabel ) ) {
	$c = false;
	$result['reason'] = "Feed label contains invalid characters";
	unset( $feedLabel );
}

if( $c ) {
	$mysqlErrorSource = 'Incoming Feed '.$feedLabel;
} else {
	$mysqlErrorSource = 'Incoming Feed';
}
include(INCLUDES."_connx.php");

if($c) { 
	$feedParams = loadParameters($feedLabel);
	if($feedParams === false){
		$c = false; 
		$result['reason'] = 'Database failure, please try again later.';
		logError(
			'Feed '.$feedLabel
			, 'Database failure when attempting to load feed parameters. Check MySQL log file.'
			, true
		);
	} else if( 0 === $feedParams ) {
		$c = false;
		$result['reason'] = 'Invalid feed label';
		unset( $feedLabel );
	} else { 
		$required = explode(';', $feedParams->required);
		$allowedFields = explode(';', $feedParams->allowedFields);
	}
}

if(0 && $c) {
	lockTables($feedLabel);
}

if($c &&( 
	!isset($_REQUEST['pswd'])
	|| empty($_REQUEST['pswd'])
	|| $_REQUEST['pswd'] != $feedParams->password
)){
	$c = false; $result['reason'] = 'Unauthorized access.';
	logError(
		'Feed '.$feedLabel
		, 'Unauthorized user at '.$_SERVER["REMOTE_ADDR"]
	);
}

if( $c && '1' == $feedParams->retired ) {
	$c = false; 
	$result['reason'] = 'This feed has been disabled.';
}

if($c){ //Validation of incoming data.

	// Special handling for TurnTwo feed that cannot change what URL value is being sent to us
	if( !empty( $_REQUEST['url'] ) && 'www.5minutemoney.co.uk,www.5minutemoney.co.uk' == $_REQUEST['url'] ) {
		$_REQUEST['url'] = 'www.5minutemoney.co.uk';
	}

	// Special handling for Digital Bulldogs feed that contains an invalid URL
	if( !empty( $_REQUEST['url'] ) && 'https//www.instantcheckmate.com/register' == $_REQUEST['url'] ) {
		$_REQUEST['url'] = 'https://www.instantcheckmate.com/register';
	}

	// Fix incoming URLs missing a protocol so they validate properly
	if( !empty( $_REQUEST['url'] ) && strpos( $_REQUEST['url'], 'http' ) !== 0 ) {
		$_REQUEST['url'] = 'http://' . $_REQUEST['url'];
	}

	// Fix cases where gender is set to a blank value
	if( !empty( $_REQUEST['gender'] ) && ' ' == $_REQUEST['gender'] ) {
		unset( $_REQUEST['gender'] );
	}

	if($c){ 
		foreach($required as $requiredKey){ 
			switch($requiredKey){
				case "phone":
					if(
						(
							!isset($_REQUEST['landline'])
							|| trim($_REQUEST['landline']) == ''
						) && (
							!isset($_REQUEST['cellphone'])
							|| trim($_REQUEST['cellphone']) == ''
						)
					){
						$c = false; $result['reason'] = "A phone number is required, either landline or cellphone. "
							."They can not both be empty.";
					}
				break; 
				default:
					if(
						!isset($_REQUEST[$requiredKey]) 
						|| trim($_REQUEST[$requiredKey]) == ''
					){ 
						$c = false; $result['reason'] = ucfirst($requiredKey).' is a required field, and may not be empty.';
					}
			}
			if(!$c){ break; }
		}
	}	
	if($c){ //All required fields were sent. Check that the sent information is valid.
		foreach($allowedFields as $allowedField){ 
			if(	!empty($_REQUEST[$allowedField]) ){ 
				$validateResult = validate($allowedField, $_REQUEST[$allowedField], $feedParams);
				if(!$validateResult['valid']){ 
					$c = false; $result['reason'] = $validateResult['reason'];
				}
			}
			if(!$c){ break; }
		}
	}
}
if( in_array('url', $allowedFields ) ) { //URL is expected so trim it and store in the database.
	if( !empty( $_REQUEST['url'] ) ){ 
		$_REQUEST['urlTrim'] = url_reformat($_REQUEST['url']);
	} else { 
		$_REQUEST['url'] = 'No Url Given';
		$_REQUEST['urlTrim'] = url_reformat('No Url Given');
	}
}
if( $c && !empty( $_REQUEST['email'] ) ) {
	$exists = checkSuppression( $_REQUEST['email'], 'global' );
	if( $exists ) {
		$c = false;
		$result['reason'] = 'Email exists in our global suppression file.';
	}
}

if( $c && !is_null( $feedParams->filterTypeUrl ) ) {
                    
	$urlAcceptable = filterValue($feedParams->filterTypeUrl, $_REQUEST['url'], $feedParams->filterUrl);
	if( !$urlAcceptable ) {
		$c = false;
		$result['reason'] = 'URL is not allowed on this feed.';
	}
}

if($c && $feedParams->dedupeEmail && isset($_REQUEST['email']) && $_REQUEST['email'] != ''){ 
	$dupeCount = checkDuplicate('email', $_REQUEST, $feedParams->label, $feedParams->dedupeAcross);
	if($dupeCount === false){ 
		$c = false; $result['reason'] = 'Database failure - could not check duplicates.';
	} elseif($dupeCount > 0){ 
		$c = false; $result['reason'] = 'Duplicate Email';
	}
}
if($c && $feedParams->dedupeLandline && isset($_REQUEST['landline']) && $_REQUEST['landline'] != ''){ 
	$dupeCount = checkDuplicate('landline', $_REQUEST, $feedParams->label, $feedParams->dedupeAcross);
	if($dupeCount === false){ 
		$c = false; $result['reason'] = 'Database failure - could not check duplicates.';
	} elseif($dupeCount > 0){ 
		$c = false; $result['reason'] = 'Duplicate Phone (landline)';
	}
}
if($c && $feedParams->dedupeCellphone && isset($_REQUEST['cellphone']) && $_REQUEST['cellphone'] != ''){ 
	$dupeCount = checkDuplicate('cellphone', $_REQUEST, $feedParams->label, $feedParams->dedupeAcross);
	if($dupeCount === false){ 
		$c = false; $result['reason'] = 'Database failure - could not check duplicates.';
	} elseif($dupeCount > 0){ 
		$c = false; $result['reason'] = 'Duplicate Phone (cellphone)';
	}
}
if( $c && !empty( $_REQUEST['email'] ) && defined( 'SIFTLOGIC_APIKEY' ) && !is_null( $feedParams->filterTypeSiftLogic ) ) {
	$filter = filterValue($feedParams->filterTypeSiftLogic, $_REQUEST['url'], $feedParams->filterSiftLogic);
	if( $filter ) {
		require_once( INCLUDES . 'siftLogic.php' );
		$sl = new SiftLogic;
		if( $sl->check( $_REQUEST['email'], 
						!empty( $_REQUEST['ip'] ) ? $_REQUEST['ip'] : null, 
						!empty( $_REQUEST['fname'] ) ? $_REQUEST['fname'] : null, 
						!empty( $_REQUEST['lname'] ) ? $_REQUEST['lname'] : null, 
						!empty( $_REQUEST['addr'] ) ? $_REQUEST['addr'] : null, 
						!empty( $_REQUEST['addr2'] ) ? $_REQUEST['addr2'] : null, 
						!empty( $_REQUEST['city'] ) ? $_REQUEST['city'] : null, 
						!empty( $_REQUEST['state'] ) ? $_REQUEST['state'] : null, 
						!empty( $_REQUEST['zip'] ) ? $_REQUEST['zip'] : null, 
						!empty( $_REQUEST['country'] ) ? $_REQUEST['country'] : null, 
					false ) === false ) {
			$c = false;
			$result['reason'] = 'Email address was rejected by our third-party filters [SL]';
		}
	}
}

unlockTables();

if( !empty( $feedParams ) ) {
	$leads = Leads::getInstance();
	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $c ? null : $result['reason'], null );
}

//Population Portion of the script. 
if($c){ 
	$feedsOut = getIncomingPopulationSettings($feedParams->idFeedIn);
	if($feedsOut === false){ 
		logError(
			'Feed '.$feedLabel
			, 'Database failure when attempting to load outgoing feed population parameters. Check MySQL log file.'
			, true
		);
	} elseif(count($feedsOut) != 0 && $feedsOut != 0) { 
		/*
		logError(
			'Feed '.$feedLabel
			, 'Capturing information from feeds.'.print_r($feedsOut, true)
			, false
		);
		*/
		foreach($feedsOut as $feed){ 
			if($feed->enabled){ 
				$p = true;
				if($p && !is_null($feed->filterTypeUrl)){
					$urlAcceptable = filterValue($feed->filterTypeUrl, $_REQUEST['url'], $feed->filterUrl);
					if(!$urlAcceptable){ 
						$p = false;
					}
				}
				if($p && !is_null($feed->filterTypeEmail)){
					$domainAcceptable = filterValue($feed->filterTypeEmail, $_REQUEST['email'], $feed->filterEmail);
					if(!$domainAcceptable){ 
						$p = false;
					}
				}
				if($p && !is_null($feed->filterTypeListcode)){
					$listcodeAcceptable = filterValue($feed->filterTypeListcode, $_REQUEST['listcode'], $feed->filterListcode);
					if(!$listcodeAcceptable){ 
						$p = false;
					}
				}
				// Ensure we haven't reached our daily limit of records
				if($p && !is_null($feed->dailyLimit) && intval($feed->dailyLimit) > 0) {
					$cnt = getOutboundDailyCount( $feed->label );
					if( $cnt && $cnt > $feed->dailyLimit ) {
						logError( 'Feed '.$feed->label, 'Daily feed limit of ' . $feed->dailyLimit . ' reached', false );
						$p = false;
					}
				}
				if($p){ 
					$insertToFeedOut = 
						"INSERT INTO `".DATABASE_NAME."`.`feedout_".$feed->label."` ( `processed` ";
					foreach($allowedFields as $allowedField){ 
						$insertToFeedOut .= ", `".$allowedField."` ";
						if($allowedField == 'url'){ 
							$insertToFeedOut .= ", `urlTrim` ";
						}
					}
					if( !empty( $feed->livedata ) ) {
						$insertToFeedOut .= ") VALUES ( '-1' ";
					} else {
						$insertToFeedOut .= ") VALUES ( '0' ";
					}
					foreach($allowedFields as $allowedField){ 
						if(isset($_REQUEST[$allowedField])){ 
							if($allowedField == 'listcode' && empty($_REQUEST[$allowedField])){ 
								$insertToFeedOut .= ", 'No listcode'";
							} elseif($allowedField == 'stamp'){ 
								$insertToFeedOut .= ", '".date("Y-m-d H:i:s", strtotime($_REQUEST[$allowedField]))."' ";
							} else { 
								$insertToFeedOut .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST[$allowedField])."' ";
							}
						} else { 
							if($allowedField == 'listcode'){ 
								$insertToFeedOut .= ", 'No listcode'";
							} else { 
								$insertToFeedOut .= ", ''";
							}
						}
						if($allowedField == 'url'){ 
							$insertToFeedOut .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST['urlTrim'])."' ";
						}
					}
					$insertToFeedOut .= ");";
					$doinsertRecord = dbQry($insertToFeedOut, 'Populating '.$feed->label, true);
					if($doinsertRecord === false){ 
						logError(
							'Feed '.$feedLabel
							, 'Database failure when populate outgoing feed '.$feed->label.'. Check MySQL log file.'
							, true
						);
					} else {
						$lastRecord = $GLOBALS['dbconnx']->insert_id;
					}

					$leads = Leads::getInstance();
					$leads->outboundAdd( $inboundId, $lastRecord, $feedParams->idFeedIn, $feed->idFeedOut, $_REQUEST['urlTrim'] );
				}

				// If this is a "livedata" population, immediately try to send the record through to the receiving feed
				if( $p && !empty( $lastRecord ) && !empty( $feed->livedata ) ) {
					require_once( SITE_ROOT . FD . 'pushLead/_f_onlms.php' );
    
					$getLead = "SELECT * FROM `".DATABASE_NAME."`.`feedout_".$feed->label. "` WHERE `processed` = '-1' AND idRecord = " . $lastRecord;
					$dogetLead = dbQry($getLead, 'Fetching live lead to process', true);
					if($dogetLead === false){
						logError(
							'Outgoing Feed '.$settings['feedParams']->label
							, 'Database failure when trying to select leads for processing. View MySQL log.'
							, true
						);
					} else if( $dogetLead->num_rows > 0 ) {

						$feedOut = getOutgoingFeed( $feed->idFeedOut );
						while( $c && ( $row = $dogetLead->fetch_array( MYSQLI_ASSOC ) ) ) {

							$status = runlead( $row, $feedOut );
							if( isset( $status ) ) {

								$update  = "UPDATE `".DATABASE_NAME."`.`feedout_".$feed->label."` ";
								$update .= "SET processed = '1', poststamp = NOW(), postresponse = " . valueSet( $status['text'] ) . " ";
								$update .= "WHERE idRecord = " . $lastRecord;
								dbQry( $update, 'Update processed status of live record' );

								$leads = Leads::getInstance();

								if( isset( $status['status'] ) && $status['status'] != true ) {

									$c = false;
									$result['reason'] = 'This record was rejected by the receiving party [Feed ID: ' . $feed->idFeedOut . ']';

									if( !empty( $_REQUEST['url'] ) ) {
										updateStats( $feed->idFeedOut, $_REQUEST['url'], 0, 1, 0 );
										$leads->inboundProcess( $inboundId, $feedParams->idFeedIn, $_REQUEST['url'], $result['reason'] );
									}

								} else {

									if( !empty( $_REQUEST['url'] ) ) {
										updateStats( $feed->idFeedOut, $_REQUEST['url'], 1, 0, 0 );
									}

								}

							}
						}

					}
				}
			}
		}
	}
}

if($c){ //Inputted information is validated, go ahead and insert the record into the database.

	if( !empty( $_REQUEST['urlTrim'] ) ) {

		// Notify if this is the first time we've seen this URL on this feed
		$urlCount = checkExists( 'urlTrim', $_REQUEST, $feedParams->label );
		if( $urlCount == 0 ) {
			notifyManagers( sprintf( "\r\nWe received a new URL on this feed.\r\n\r\nFeed: {$feedParams->label}\r\n\r\nURL: %s\r\n\r\n",
                                        str_replace( '.', '*', $_REQUEST['urlTrim'] ) )
							);
		}

		// Add an entry to the notification table to see if this feed goes dormant
		if( !empty( $feedParams->notifications ) ) {
			$leads = Leads::getInstance();
			$leads->addNotification( $feedParams->idFeedIn, $_REQUEST['url'] );
		}
	}


	$insertRecord = "INSERT INTO `".DATABASE_NAME."`.`feedinc_".$feedLabel."` ( `queryString`, `received` ";
	dbCon("insertUpdate");
	foreach($allowedFields as $allowedField){ 
		$insertRecord .= ", `".$allowedField."` ";
		if($allowedField == 'url'){ 
			$insertRecord .= ", `urlTrim` ";
		}
	}
	$insertRecord .= ") VALUES ( "
		."'".$GLOBALS['dbconnx']->escape_string(serialize($_REQUEST))."', "
		."'".date("Y-m-d H:i:s")."' ";
	foreach($allowedFields as $allowedField){ 
		if(isset($_REQUEST[$allowedField])){ 
			if($allowedField == 'listcode' && empty($_REQUEST[$allowedField])){ 
				$insertRecord .= ", 'No listcode'";
			} elseif($allowedField == 'stamp'){ 
				$insertRecord .= ", '".date("Y-m-d H:i:s", strtotime($_REQUEST[$allowedField]))."' ";
			} else { 
				$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST[$allowedField])."' ";
			}
		} else { 
			if($allowedField == 'listcode'){ 
				$insertRecord .= ", 'No listcode'";
			} else { 
				$insertRecord .= ", ''";
			}
		}
		if($allowedField == 'url'){ 
			$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST['urlTrim'])."' ";
		}
	}
	$insertRecord .= ");";
	$doinsertRecord = dbQry($insertRecord, 'Inserting new record for '.$feedLabel, true);
	if($doinsertRecord === false){
		$c = false; $result['reason'] = 'Database failure, please try again later.';
		logError(
			'Feed '.$feedLabel
			, 'Database failure when attempting to insert valid record. Check MySQL log file.'
			, true
		);
	} else { //Successfully inserted into the data table, now insert into the count table.
		$date = date("Y-m-d");
		$insertCountChange = "INSERT INTO "
			."`".DATABASE_NAME."`.`urlcount` (`idFeedIn`,`urlTrim`,`urlFull`,`quantity`,`stamp`) "
			."VALUES ( "
				." '".$feedParams->idFeedIn."' "
				.",'".$GLOBALS['dbconnx']->escape_string($_REQUEST['urlTrim'])."' "
				.",'".$GLOBALS['dbconnx']->escape_string($_REQUEST['url'])."' "
				.",'1' "
				.",'".$date."' "
			.") "
			."ON DUPLICATE KEY UPDATE `quantity`=`quantity`+1; ";
		$doinsertCountChange = dbQry($insertCountChange, 'Inserting quantity change', true);
		if($doinsertCountChange === false){ 
			logError(
				'Feed '.$feedLabel
				, 'Database failure when attempting to add quantity change. Check MySQL log file.'
				, true
			);
		}
	}
	dbDcon();
} else if( !empty( $feedLabel ) ) { //There was a failure somewhere, so insert into the invalid database with the query string and the error.
	$insertRecord = "INSERT INTO `".DATABASE_NAME."`.`feedinc_".$feedLabel."_invalid` ( `queryString`,`error`,`received` ";
	dbCon("insertUpdate");
	foreach($allowedFields as $allowedField){ 
		$insertRecord .= ", `".$allowedField."` ";
		if($allowedField == 'url'){ 
			$insertRecord .= ", `urlTrim` ";
		}
	}
	$insertRecord .= ") VALUES ( "
		."'".$GLOBALS['dbconnx']->escape_string(serialize($_REQUEST))."', "
		."'".$result['reason']."', "
		."'".date("Y-m-d H:i:s")."' ";
	foreach($allowedFields as $allowedField){ 
		if(isset($_REQUEST[$allowedField])){ 
			if($allowedField == 'listcode' && empty($_REQUEST[$allowedField])){ 
				$insertRecord .= ", 'No listcode'";
			} elseif($allowedField == 'stamp'){ 
				$insertRecord .= ", '".date("Y-m-d H:i:s", strtotime($_REQUEST[$allowedField]))."' ";
			} else { 
				$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST[$allowedField])."' ";
			}
		} else { 
			if($allowedField == 'listcode'){ 
				$insertRecord .= ", 'No listcode'";
			} else { 
				$insertRecord .= ", ''";
			}
		}
		if($allowedField == 'url') {
 			if ( !empty( $_REQUEST['urlTrim'] ) ){ 
				$insertRecord .= ", '".$GLOBALS['dbconnx']->escape_string($_REQUEST['urlTrim'])."' ";
			} else { 
				$insertRecord .= ", ''";
			}
		}
	}
	$insertRecord .= ");";
	$doinsertRecord = dbQry($insertRecord, 'Inserting new record for '.$feedLabel, true);
	if($doinsertRecord === false){
		$c = false; $result['reason'] = 'Database failure, please try again later.';
		logError(
			'Feed '.$feedLabel
			, 'Database failure when attempting to insert invalid record. Check MySQL log file.'
			, true
		);
	} else if( !empty( $_REQUEST['url'] ) && !empty( $_REQUEST['urlTrim'] ) ) { //Successfully inserted into the data table, now insert into the count table.
		$date = date("Y-m-d");
		$insertCountChange = "INSERT INTO "
			."`".DATABASE_NAME."`.`urlcount_invalid` (`idFeedIn`,`urlTrim`,`urlFull`,`quantity`,`stamp`) "
			."VALUES ( "
				." '".$feedParams->idFeedIn."' "
				.",'".$GLOBALS['dbconnx']->escape_string($_REQUEST['urlTrim'])."' "
				.",'".$GLOBALS['dbconnx']->escape_string($_REQUEST['url'])."' "
				.",'1' "
				.",'".$date."' "
			.") "
			."ON DUPLICATE KEY UPDATE `quantity`=`quantity`+1; ";
			//echo $insertCountChange;
		$doinsertCountChange = dbQry($insertCountChange, 'Inserting quantity change', true);
		if($doinsertCountChange === false){ 
			logError(
				'Feed '.$feedLabel
				, 'Database failure when attempting to add quantity change for invalid record. Check MySQL log file.'
				, true
			);
		}
	}
	dbDcon();
}

if($c){ 
	$result['success'] = 'true';
	$result['reason'] = 'Successfully inserted new record.';
}

$xml = Array2XML::createXML('response', $result);
echo $xml->saveXML();
