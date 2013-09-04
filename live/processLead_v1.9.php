<?php //LIVE_ROOT/processLead.php
//Version 1.6
//ES 20130820 1.6: Updated script to utilize c_config.php
include(SITE_ROOT."_connx.php");
include("processFunctions.php");

$c = true; 
$result = array(
	'success' => 'false'
	, 'reason' => 'None.'
);

if($c) { 
	$feedParams = loadParameters($feedLabel);
	if($feedParams === false){ 
		$c = false; $result['reason'] = 'Database failure, please try again later.';
		logError(
			'Feed '.$feedLabel
			, 'Database failure when attempting to load feed parameters. Check MySQL log file.'
			, true
		);
	} else { 
		$required = explode(';', $feedParams->required);
		$allowedFields = explode(';', $feedParams->allowedFields);
	}
}

lockTables();

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

if($c){ //Validation of incoming data.
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
if($feedParams && in_array('url', $allowedFields)){ //URL is expected so trim it and store in the database.
	if(isset($_REQUEST['url'])){ 
		$_REQUEST['urlTrim'] = url_reformat($_REQUEST['url']);
	} else { 
		$_REQUEST['url'] = 'No Url Given';
		$_REQUEST['urlTrim'] = url_reformat('No Url Given');
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
if($c){ //Inputted information is validated, go ahead and insert the record into the database.
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
} else { //There was a failure somewhere, so insert into the invalid database with the query string and the error.
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
			, 'Database failure when attempting to insert invalid record. Check MySQL log file.'
			, true
		);
	} else { //Successfully inserted into the data table, now insert into the count table.
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

//Population Portion of the script. 
if($c){ 
	$feedsOut = getPopulationSettings($feedParams->idFeedIn);
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
				if($p){ 
					$insertToFeedOut = 
						"INSERT INTO `".DATABASE_NAME."`.`feedout_".$feed->label."` ( `processed` ";
					foreach($allowedFields as $allowedField){ 
						$insertToFeedOut .= ", `".$allowedField."` ";
						if($allowedField == 'url'){ 
							$insertToFeedOut .= ", `urlTrim` ";
						}
					}
					$insertToFeedOut .= ") VALUES ( '0' ";
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
					}
				}
			}
		}
	}
}

unlockTables();
