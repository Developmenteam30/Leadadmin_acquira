<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$mysqlErrorSource = 'Manager - Outgoing Feeds';
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

function checkExistsLabelFeedOut($label){ 
	//Returns quantity of matching records, or false if it fails.
	dbCon();
	$checkFeed = "SELECT * FROM `".DATABASE_NAME."`.`feedout` "
		."WHERE "
			."`label` = '".$GLOBALS['dbconnx']->escape_string($label)."' "
		.";";
	$docheckFeed = dbQry($checkFeed, 'Checking if label exists', true);
	dbDcon();
	if($docheckFeed === false){ return false; }
	return $docheckFeed->num_rows;
}

function addFeedOut(
	$label
	, $description
	, $idCompany
	, $feedType
	, $postUrl
	, $staticFields
	, $varFields
	, $fieldMap
	, $successString
	, $dailyLimit
	, $delay
	, $urlassignments
){ 
	$result = array(
		'success' => false
		, 'reason' => 'None.'
	);
	$c = true;
	dbCon("insertUpdate");
	if($c){ //Add feed.
		$addFeed = "INSERT INTO `".DATABASE_NAME."`.`feedout` "
			."(`label`,`description`,`idCompany`,`feedType`,`postUrl`,`staticFields`,`varFields`,`fieldMap` "
				.",`status`,`cron`,`cronTiming`,`successString`,`dailyLimit`,`delay`,`throttle`, `urlassignments`) VALUES ( "
			."  '".$GLOBALS['dbconnx']->escape_string($label)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($description)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($idCompany)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($feedType)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($postUrl)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($staticFields)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($varFields)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($fieldMap)."' "
			.", 'active', '0', '1' "
			.", '".$GLOBALS['dbconnx']->escape_string($successString)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($dailyLimit)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($delay)."' "
			.", '100' "
			.", '".$GLOBALS['dbconnx']->escape_string($urlassignments)."' "
			.");";
		$doaddFeed = dbQry($addFeed, 'Adding new feed.', true);
		if($doaddFeed === false){ 
			$c = false; $result['reason'] = 'Database failure - could not add feed.';
		} 
	}
	if($c && LEGACY_DB ){ //Create feedout table..
		$createTable = "CREATE TABLE `".DATABASE_NAME."`.`feedout_".$label."` ( "
			."`idRecord` bigint(20) NOT NULL auto_increment, "
			."`processed` enum('-1','0','1') default '0', "
			."`poststamp` datetime default NULL, "
			."`postrequest` varchar(1000) default NULL, "
			."`postresponse` varchar(1000) default NULL, "
			."`listcode` varchar(20) default NULL, "
			."`urlTrim` varchar(100) default NULL, "
			."`url` varchar(500) default NULL, "
			."`ip` varchar(16) default NULL, "
			."`stamp` datetime default NULL, "
			."`email` varchar(150) default NULL, "
			."`fname` varchar(50) default NULL, "
			."`lname` varchar(50) default NULL, "
			."`addr` varchar(150) default NULL, "
			."`addr2` varchar(150) default NULL, "
			."`city` varchar(75) default NULL, "
			."`state` varchar(25) default NULL, "
			."`zip` varchar(20) default NULL, "
			."`dob` date default NULL, "
			."`gender` varchar(10) default NULL, "
			."`landline` varchar(20) default NULL, "
			."`cellphone` varchar(20) default NULL, "
			."`country` varchar(75) default NULL, "
			."PRIMARY KEY  (`idRecord`), "
			."KEY `email` (`email`), "
			."KEY `postStamp` (`postStamp`), "
			."KEY `processed` (`processed`), "
			."KEY `urlTrimDate` (`urlTrim`,`postStamp`) "
			.") AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		$docreateTable = dbQry($createTable, 'Creating table for feed out.', true);
		if($docreateTable === false){ 
			$c = false; $result['reason'] = 'Database failure - could not create feed out table.';
		}
	}
	dbDcon();
	if($c){ 
		$result['success'] = true;
		$result['reason'] = 'Successfully added feed and created feed table.';
	}
	return $result;
}

function alterFeedOut($idFeedOut, $property, $newVal){ 
	$result = array(
		'success' => false
		, 'reason' => 'None.'
	);
	$c = true;
	switch($property){
		case 'label':
			//Change label in database.
			if($c){ 
				$leads = Leads::getInstance();
				$feed = $leads->getOutboundFeed( $idFeedOut );
				if($feed === false){ 
					$c = false; $result['reason'] = 'Database failure - could not fetch feed to alter.';
				}
			}
			dbCon("insertUpdate");
			if($c){ //Updated feedout entry.
				$updateLabel = "UPDATE `".DATABASE_NAME."`.`feedout` "
					."SET `label` = '".$newVal."' "
					."WHERE `idFeedOut` = '".$idFeedOut."'; ";
				$doupdateLabel = dbQry($updateLabel, 'Updating feed label', true);
				if($doupdateLabel === false){ $c = false; $result['reason'] = 'Database failure - could not update '
					.'label name.';
				}
			}
			if($c && LEGACY_DB ){ //Updating table names.
				$updateTableNames = 
					"RENAME TABLE "
						."`".DATABASE_NAME."`.`feedout_".$feed->label."` "
							."TO `".DATABASE_NAME."`.`feedout_".$newVal."`; ";
				$doupdateTableNames = dbQry($updateTableNames, 'Updating table names', true);
				if($doupdateTableNames === false){ 
					$c = false; $result['reason'] = 'Database failure - could not update table names.';
				}
			}
			dbDcon();
			if($c){ $result['success'] = true; $result['reason'] = 'Successfully updated label for outgoing feed.'; }
		break;
		default: 
			dbCon("insertUpdate");
			if($c){ //Updated feedinc entry.
				$updateProperty = "UPDATE `".DATABASE_NAME."`.`feedout` "
					."SET `".$property."` = ". valueEmpty( $newVal ) ." "
					."WHERE `idFeedOut` = '".$idFeedOut."'; ";
				$doupdateProperty = dbQry($updateProperty, 'Updating feed property '.$property, true);
				if($doupdateProperty === false){ $c = false; $result['reason'] = 'Database failure - could not update '
					.$property.'.';
				}
			}
			if($c){ $result['success'] = true; $result['reason'] = 'Successfully updated '.$property.' for outgoing '
				.'feed.';
			}
		break;
	}
	if( $c ) {
		$leads = Leads::getInstance();
		$leads->auditLog( 'FEEDOUT:EDIT', $idFeedOut );
	}
	return $result;
}

function addPopulationParameter(
	$idFeedOut
	, $idFeedIn
	, $filterTypeUrl
	, $filterUrl
	, $filterTypeEmail
	, $filterEmail
	, $filterTypeListcode
	, $filterListcode
	, $livedata
	, $forceUrl
	, $forceUrlList
){ 
	dbCon("insertUpdate");
	$addParameter = "INSERT INTO `".DATABASE_NAME."`.`feedPopulation` "
		."( "
			."`enabled`,`idFeedIn`,`idFeedOut`,`filterTypeUrl`,`filterUrl`,`filterTypeEmail`,`filterEmail` "
			.", `filterTypeListcode`,`filterListcode`, `livedata`, `forceUrl`, `forceUrlList` "
		.") VALUES ('1', "
		."  ".$idFeedIn." "
		.", ".$idFeedOut." "
		.", ".$filterTypeUrl." "
		.", '".$GLOBALS['dbconnx']->escape_string($filterUrl)."' "
		.", ".$filterTypeEmail." "
		.", '".$GLOBALS['dbconnx']->escape_string($filterEmail)."' "
		.", ".$filterTypeListcode." "
		.", '".$GLOBALS['dbconnx']->escape_string($filterListcode)."' "
		.", ".$livedata." "
		.", ".$forceUrl." "
		.", '".$GLOBALS['dbconnx']->escape_string($forceUrlList)."' "
		.");";
	$doaddParameter = dbQry($addParameter, 'Adding new feed.', true);
	if($doaddParameter === false){ return false; }
	$idAssoc = $GLOBALS['dbconnx']->insert_id;
	$leads = Leads::getInstance();
	$leads->auditLog( 'FEEDOUT:POP:ADD', $idAssoc );
	return true;
}

function alterPopulationParameter($idAssoc, $property, $newVal){ 
	dbCon("insertUpdate");
	$escapers = array("filterUrl", "filterEmail", "filterListcode", "forceUrlList");
	if(in_array($property, $escapers)){ 
		$newVal = $GLOBALS['dbconnx']->escape_string($newVal);
	}
	$quoters = array("filterUrl", "filterEmail", "filterListcode", "forceUrlList");
	if(in_array($property, $quoters)){ 
		$newVal = "'".$newVal."'";
	}
	if( empty( $newVal ) ) {
		$newVal = "NULL";
	}
	$updateProperty = "UPDATE `".DATABASE_NAME."`.`feedPopulation` "
		."SET `".$property."` = ".$newVal." "
		."WHERE `idAssoc` = '".$idAssoc."'; ";
	$doupdateProperty = dbQry($updateProperty, 'Updating feed property '.$property, true);
	if($doupdateProperty === false){ return false; }
	$leads = Leads::getInstance();
	$leads->auditLog( 'FEEDOUT:POP:EDIT', $idAssoc );
	return true;
}

function deletePopulationParam($idAssoc){ 
	dbCon("insertUpdate");
	$deleteProperty = "DELETE FROM `".DATABASE_NAME."`.`feedPopulation` "
		."WHERE `idAssoc` = '".$idAssoc."'; ";
	$dodeleteProperty = dbQry($deleteProperty, 'Deleting feed population paramter.', true);
	if($dodeleteProperty === false){ return false; }
	$leads = Leads::getInstance();
	$leads->auditLog( 'FEEDOUT:POP:DEL', $idAssoc );
	return true;
}

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "manageFeedOut":
			$c = true; $result['error'] = 'Failed when attempting to manage feeds.';
			$action = $_REQUEST['action'];
			if($action == 'new'){
				$result['error'] = 'Failed when adding a new feed.';
				//Validate Input
				if($c && ( //Label Cannot be empty.
					$_REQUEST['label'] == ''
				)){ $c = false; $result['error'] = 'Label cannot be empty.'; }
				if($c //Label cannot have invalid characters
				){
					$pattern = '/^[a-z][a-z0-9_]*$/';
					if(!preg_match($pattern, $_REQUEST['label'])){ 
						$c = false; $result['error'] = 'Label must start with a letter, can contain letters, '
							.'numbers, and underscore only.';
					}
				}
				//Special Validation of Inputs
				if($c){  //Label can not be already used
					$checkResult = checkExistsLabelFeedOut($_REQUEST['label']);
					if($checkResult === false){ 
						$c = false; $result['error'] = 'Database failure - could not '
							.'check if label is already in use.'; 
					}
					if($c && $checkResult > 0){ 
						$c = false; $result['error'] = 'Label is already in use.'; 
					}					
				}
				if($c){ //Add entry to the database.
					$addResult = addFeedOut(
						$_REQUEST['label']
						, $_REQUEST['description']
						, $_REQUEST['idCompany']
						, $_REQUEST['feedType']
						, trim( $_REQUEST['postUrl'] )
						, $_REQUEST['staticFields']
						, $_REQUEST['varFields']
						, $_REQUEST['fieldMap']
						, $_REQUEST['successString']
						, $_REQUEST['dailyLimit']
						, $_REQUEST['delay']
						, $_REQUEST['urlassignments']
					);
					if(!$addResult['success']){ 
						$c = false; $result['error'] = $addResult['reason'];
					}
				}
				if($c){ 
					$result['status'] = 1;
					$result['error'] = 'Successfully created new feed.';
				}
			} else {			
				$result['error'] = 'Failed when editing feed.';

				$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;
				if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
					$idCompany = LeadsSession::getCompanyId();
					if( empty( $idCompany ) ) {
						$idCompany = -9999;
					}
					if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
						$c = false;
						$result['error'] = 'Sorry, you do not have access to this feed.';
						break;
					}
				}

				if($c){ 
					$feed = $leads->getOutboundFeed( $idFeedOut );
					if($feed === false){ 
						$c = false; $result['error'] = 'Database failure - could not fetch feed information for '
							.'editing.';
					}				
					if($c && !is_object($feed) && $feed == 0){ 
						$c = false; $result['error'] = 'Could not alter feed - feed does not exist.';
					}
				}
				if($c){ 
					if($_REQUEST['label'] != $feed->label){ //Label is being altered. 
						if($c && ( //Label Cannot be empty.
							$_REQUEST['label'] == ''
						)){ $c = false; $result['error'] = 'Label cannot be empty.'; }
						if($c //Label cannot have invalid characters
						){
							$pattern = '/^[a-z][a-z0-9_]*$/';
							if(!preg_match($pattern, $_REQUEST['label'])){ 
								$c = false; $result['error'] = 'Label must start with a letter, can can contain '
								.'letters, numbers, and underscore only.';
							}
						}
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut($_REQUEST['idFeedOut'], 'label', $_REQUEST['label']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['description'] != $feed->description){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut($_REQUEST['idFeedOut'], 'description', $_REQUEST['description']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['idCompany'] != $feed->idCompany){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut($_REQUEST['idFeedOut'], 'idCompany', $_REQUEST['idCompany']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['feedType'] != $feed->feedType){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut($_REQUEST['idFeedOut'], 'feedType', $_REQUEST['feedType']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['postUrl'] != $feed->postUrl){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut($_REQUEST['idFeedOut'], 'postUrl', trim( $_REQUEST['postUrl'] ));
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['staticFields'] != $feed->staticFields){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut(
								$_REQUEST['idFeedOut'], 'staticFields', $_REQUEST['staticFields']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['urlassignments'] != $feed->urlassignments){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut(
								$_REQUEST['idFeedOut'], 'urlassignments', $_REQUEST['urlassignments']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['varFields'] != $feed->varFields){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut(
								$_REQUEST['idFeedOut'], 'varFields', $_REQUEST['varFields']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['fieldMap'] != $feed->fieldMap){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut(
								$_REQUEST['idFeedOut'], 'fieldMap', $_REQUEST['fieldMap']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['successString'] != $feed->successString){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedOut(
								$_REQUEST['idFeedOut'], 'successString', $_REQUEST['successString']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['status'] != $feed->status){ 
						if($c){
							$alterResult = alterFeedOut( $_REQUEST['idFeedOut'], 'status', $_REQUEST['status'] );
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
						if( 'retired' === $_REQUEST['status'] ) {
							$leads->retireOutboundFeed( $_REQUEST['idFeedOut'] );
						}
					}
					$dailyLimit = empty( $_REQUEST['dailyLimit'] ) ? null : abs( intval( $_REQUEST['dailyLimit'] ) );
					if( $dailyLimit != $feed->dailyLimit ){ 
						if($c){
							$alterResult = alterFeedOut( $_REQUEST['idFeedOut'], 'dailyLimit', $dailyLimit );
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					$delay = empty( $_REQUEST['delay'] ) ? null : abs( intval( $_REQUEST['delay'] ) );
					if( $delay != $feed->delay ){ 
						if($c){
							$alterResult = alterFeedOut( $_REQUEST['idFeedOut'], 'delay', $delay );
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
				}		
				if($c){ 
					$result['status'] = 1;
					$result['error'] = 'Successfully updated feed.';
				}		
			}
		break;
		case "managePopulation":
			$c = true; $result['error'] = 'Failed when attempting to manage population.';
			$action = $_REQUEST['action'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $_REQUEST['idFeedOut'] ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
					break;
				}
			}

			if($action == 'new'){
				$result['error'] = 'Failed when adding a new population parameter.';
				//Validate Input
				$filters = array("Url", "Email", "Listcode");
				foreach($filters as $filterType){ 
					if($c && ( //If filterType is accept or reject, filter{Type} cannot be empty.
						$_REQUEST['filterType'.$filterType] == 'accept'
						&& $_REQUEST['filter'.$filterType] == ''
					)){ 
						$c = false; $result['error'] = 'If using an accept filter, the filters cannot '
						.'be empty.'; 
						break;
					}
				}
				if($c){ //Add entry to the database.
					if($_REQUEST['filterTypeUrl'] == 'null'){ $filterTypeUrl = "NULL"; } 
					else { $filterTypeUrl = "'".$_REQUEST['filterTypeUrl']."'"; }
					if($_REQUEST['filterTypeEmail'] == 'null'){ $filterTypeEmail = "NULL"; } 
					else { $filterTypeEmail = "'".$_REQUEST['filterTypeEmail']."'"; }
					if($_REQUEST['filterTypeListcode'] == 'null'){ $filterTypeListcode = "NULL"; } 
					else { $filterTypeListcode = "'".$_REQUEST['filterTypeListcode']."'"; }
					$addResult = addPopulationParameter(
						$_REQUEST['idFeedOut']
						, $_REQUEST['idFeedIn']
						, $filterTypeUrl
						, $_REQUEST['filterUrl']
						, $filterTypeEmail
						, $_REQUEST['filterEmail']
						, $filterTypeListcode
						, $_REQUEST['filterListcode']
						, $_REQUEST['livedata']
						, $_REQUEST['forceUrl']
						, $_REQUEST['forceUrlList']
					);
					if(!$addResult){ 
						$c = false; $result['error'] = 'Database failure, could not create parameter.';
					}
				}
				if($c){ 
					$result['status'] = 1;
					$result['error'] = 'Successfully created new population parameter.';
				}
			} else {			
				$result['error'] = 'Failed when editing population parameter.';
				//Validate Input
				$filters = array("Url", "Email", "Listcode");
				foreach($filters as $filterType){ 
					if($c && ( //If filterType is accept or reject, filter{Type} cannot be empty.
						$_REQUEST['filterType'.$filterType] == 'accept'
						&& $_REQUEST['filter'.$filterType] == ''
					)){ 
						$c = false; $result['error'] = 'If using an accept filter, the filters cannot '
						.'be empty.'; 
						break;
					}
				}
				if($c){ 
					$feed = getPopulationSetting($_REQUEST['idAssoc']);
					if($feed === false){ 
						$c = false; $result['error'] = 'Database failure - could not fetch population '
							.'information for editing.';
					}				
					if($c && !is_object($feed) && $feed == 0){ 
						$c = false; $result['error'] = 'Could not alter parameter - paramter does not exist.';
					}
				}
				if($c){ 
					if($_REQUEST['idFeedIn'] != $feed->idFeedIn){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'idFeedIn', $_REQUEST['idFeedIn']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter. (idFeedIn)';
							}
						}
					}
					if($_REQUEST['filterTypeUrl'] != $feed->filterTypeUrl){ 
						if($c){ 
							if($_REQUEST['filterTypeUrl'] == 'null'){ $filterTypeUrl = NULL; } 
							else { $filterTypeUrl = "'".$_REQUEST['filterTypeUrl']."'"; }
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterTypeUrl', $filterTypeUrl
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterTypeUrl)';
							}
						}
					}
					if($_REQUEST['filterUrl'] != $feed->filterUrl){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterUrl', $_REQUEST['filterUrl']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterUrl)';
							}
						}
					}
					if($_REQUEST['filterTypeEmail'] != $feed->filterTypeEmail){ 
						if($c){ 
							if($_REQUEST['filterTypeEmail'] == 'null'){ $filterTypeEmail = NULL; } 
							else { $filterTypeEmail = "'".$_REQUEST['filterTypeEmail']."'"; }
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterTypeEmail', $filterTypeEmail
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterTypeEmail)';
							}
						}
					}
					if($_REQUEST['filterEmail'] != $feed->filterEmail){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterEmail', $_REQUEST['filterEmail']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterEmail)';
							}
						}
					}
					if($_REQUEST['filterTypeListcode'] != $feed->filterTypeListcode){ 
						if($c){ 
							if($_REQUEST['filterTypeListcode'] == 'null'){ $filterTypeListcode = NULL; } 
							else { $filterTypeListcode = "'".$_REQUEST['filterTypeListcode']."'"; }
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterTypeListcode', $filterTypeListcode
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterTypeListcode)';
							}
						}
					}
					if($_REQUEST['filterListcode'] != $feed->filterListcode){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'filterListcode', $_REQUEST['filterListcode']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (filterListcode)';
							}
						}
					}
					if($_REQUEST['forceUrlList'] != $feed->forceUrlList){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'forceUrlList', $_REQUEST['forceUrlList']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (forceUrlList)';
							}
						}
					}
					if($_REQUEST['forceUrl'] != $feed->forceUrl){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'forceUrl', $_REQUEST['forceUrl']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (forceUrl)';
							}
						}
					}
					if($_REQUEST['livedata'] != $feed->livedata){ 
						if($c){ 
							$alterResult = alterPopulationParameter(
								$_REQUEST['idAssoc'], 'livedata', $_REQUEST['livedata']
							);
							if(!$alterResult){ 
								$c = false; $result['error'] = 'Database failure, could not update population '
									.'parameter (livedata)';
							}
						}
					}
				}		
				if($c){ 
					$result['status'] = 1;
					$result['error'] = 'Successfully updated parameter.';
				}		
			}
		break;
		case "managePopulationParam":
			$c = true; $result['error'] = 'Failed when attempting to manage population params.';
			switch($_REQUEST['action']){ 
				case "toggle":
					if($c){ 
						$popSet = getPopulationSetting($_REQUEST['idAssoc']);
						if($popSet === false){ 
							$c = false; $result['error'] = 'Database failure - could not fetch population '
								.'information for editing.';
						}				
						if($c && !is_object($popSet) && $popSet == 0){ 
							$c = false; $result['error'] = 'Could not alter parameter - parameter does not exist.';
						}
					}
					if($c){ 
						if($popSet->enabled){ 
							$enabled = 0; 
							$result['enabledText'] = 'Disabled';
						} else { 
							$enabled = 1; 
							$result['enabledText'] = 'Populating';
						}
						$alterResult = alterPopulationParameter(
							$_REQUEST['idAssoc'], 'enabled', "'".$enabled."'"
						);
						if(!$alterResult){ 
							$c = false; $result['error'] = $alterResult['reason'];
						}
					}
					if($c){ 
						$result['error'] = 'Successfully toggled population.';
					}
				break;
				case "delete":
					if($c){ 
						$popSet = getPopulationSetting($_REQUEST['idAssoc']);
						if($popSet === false){ 
							$c = false; $result['error'] = 'Database failure - could not fetch population '
								.'information for editing.';
						}				
						if($c && !is_object($popSet) && $popSet == 0){ 
							$c = false; $result['error'] = 'Could not alter parameter - parameter does not exist.';
						}
					}
					if($c){ 
						$actionResult = deletePopulationParam($_REQUEST['idAssoc']);
						if(!$actionResult){ 
							$c = false; $result['error'] = 'Database failure - failed to delete parameter.';
						}
					}
					if($c){ 
						$result['error'] = 'Successfully deleted population parameter.';
						$result['idFeedOut'] = $popSet->idFeedOut;
					}
				break;
			}
			if($c){ 
				$result['status'] = 1;
			}
		break;
		case 'manageFeedParam':
			$c = true; $result['error'] = 'Failed when attempting to manage feed params.';

			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
					break;
				}
			}


			switch($_REQUEST['action']){ 
				case "toggle":
					if($c){ 
						$feed = $leads->getOutboundFeed( $_REQUEST['idFeedOut'] );
						if($feed === false){ 
							$c = false; $result['error'] = 'Database failure - could not fetch feed for editing.';
						}				
						if($c && !is_object($feed) && $feed == 0){ 
							$c = false; $result['error'] = 'Could not alter feed - feed does not exist.';
						}
					}
					if($c){
						switch($_REQUEST['param']){
							case 'cron':
								if($feed->cron){ 
									$cron = 0; 
									$result['enabledText'] = 'Disabled';
								} else { 
									$cron = 1; 
									$result['enabledText'] = 'Populating';
								}
								$alterResult = alterFeedOut(
									$_REQUEST['idFeedOut'], 'cron', $cron
								);
								if(!$alterResult){ 
									$c = false; $result['error'] = $alterResult['reason'];
								}
							break;
							default: 
								$c = false; $result['error'] = 'Could not alter feed, invalid parameter';
							break;
						}
					}
					if($c){ 
						$result['error'] = 'Successfully toggled feed.';
					}
				break;
			}
			if($c){ 
				$result['status'] = 1;
			}
		break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){

		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

		case 'outgoingFeeds':
			if( !empty( $_REQUEST['options']['status'] ) ) {
				$status = $_REQUEST['options']['status'];
			} else {
				$status = null;
			}
			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$outgoingFeeds = $leads->getOutboundFeeds( null, $status );
			} else {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				$outgoingFeeds = $leads->getOutboundFeeds( $idCompany, $status );
			}
?>
<p>
	Outgoing Feeds
</p>
<?php		
if($outgoingFeeds === false){ 
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
} else if($outgoingFeeds == 0){ 
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
} else { 
	//Go through each and compile the company list.
	$companyFeedLists = array();
	foreach($outgoingFeeds as $feed){ 
		//Add company to the cache list of companies.
		if(!isset($companyCache[$feed->idCompany])){
			$company = getCompany($feed->idCompany);
			if(	is_object($company) ){
				$companyCache[$feed->idCompany] = $company;
				$companyFeedLists[$feed->idCompany] = array();
			}
		}
		//Add feed to list of feeds for the specified company.
		$companyFeedLists[$feed->idCompany][] = $feed;
	}	
	//print_r($companyFeedLists);
	function companyListSort($item1,$item2)
	{
		global $companyCache;
		//print_r($companyCache[$item1]->name);
		//print_r($companyCache[$item2]->name);
		return strcasecmp ( $companyCache[$item1]->name , $companyCache[$item2]->name );
	}
	uksort($companyFeedLists,'companyListSort');
?>
<table class='standard'>
	<thead>
	<tr class='fTORow fTO_Row bgGray' style='width: 100%;'>
		<td class='fTO_companyName' colspan='2'><p>Company</p></td>
		<td class='fTO_feedOverview' colspan='4'><p>Total Feeds</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Total Queued</p></td>
		<td class='fTO_options'><p>Options</p></td>
	</tr>
	</thead>
<?php
	$grandTotalFeeds = 0;
	$grandTotalAccepted = 0;
	$grandTotalRejected = 0;
	$grandTotalQueued = 0;
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		$totalActive = 0;
		$totalQueued = 0;

		foreach($companyFeedList as $keyFeed => $feed){

			$stats = $leads->getOutboundStats( $feed->idFeedOut );

			$companyFeedList[$keyFeed]->accepted = $stats['accepted'];
			$totalAccepted += $stats['accepted'];
			$grandTotalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->rejected = $stats['rejected'];
			$totalRejected += $stats['rejected'];
			$grandTotalRejected += $stats['rejected'];

			$companyFeedList[$keyFeed]->queued = $feed->queued;
			$totalQueued += $feed->queued;
			$grandTotalQueued += $feed->queued;

			if($feed->enabled) { $totalActive++; }
			$companyFeedList[$keyFeed]->statusFeed = $feed->status;
			$companyFeedList[$keyFeed]->statusCron = ($feed->cron)?'Running':'Paused';
			$companyFeedList[$keyFeed]->statusPop = getPopulationStatus($feed->idFeedOut);
		}
		$grandTotalFeeds += count($companyFeedList);
?>
	<tr class='fTORow fTO_Row bgGray'>
		<td class='fTO_companyName' colspan='2'><p><?php echo $companyCache[$idCompany]->name; ?></p></td>
		<td class='fTO_feedOverview' colspan='4'>
			<p>
				<?php echo count($companyFeedList); ?> (<?php echo $totalActive; ?> Active)
			</p>
		</td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $totalAccepted; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><?php echo $totalRejected; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><?php echo $totalQueued; ?></p></td>
		<td class='fTO_options'>
			<p>
				<a href='#' class='nonLink'
					id='link_companyFeedList_<?php echo $idCompany; ?>'
					onclick="toggleHidden('companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='companyFeedList_<?php echo $idCompany; ?>' class='fTORow fTO_Row hidden'>
	<tr>
		<td class='fTO_idFeedOut'><p>ID</p></td>
		<td class='fTO_label'><p>Feed Label</p></td>
		<td class='fTO_description'><p>Description</p></td>
		<td class='fTO_statusPop'><p>Population</p></td>
		<td class='fTO_statusFeed'><p>Feed Status</p></td>
		<td class='fTO_statusCron'><p>Processing</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Queued</p></td>
		<td class='fTO_options'><p>Options</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr class='fTORow fTO_Row'>
		<td class='fTO_idFeedOut'><p><?php echo $feed->idFeedOut; ?></p></td>
		<td class='fTO_label status-<?php echo $feed->status; ?>'><p><?php echo $feed->label; ?></p></td>
		<td class='fTO_description'><p><?php echo $feed->description; ?></p></td>
		<td class='fTO_statusPop'>
			<p>
				<a href='#' class='nonLink' 
				onclick="display('dialog_editpopulation', { 'sub': <?php echo $feed->idFeedOut; ?>, 'idFeedOut': <?php echo $feed->idFeedOut; ?> });" 
				><?php echo $feed->statusPop; ?></a>					
			</p>
		</td>
		<td class='fTO_statusFeed'>
			<p>
				<?php echo ucfirst( $feed->status ); ?>
			</p>
		</td>
		<td class='fTO_statusCron'>
			<p>
				<a href='#' class='nonLink'
					id='feedset_<?php echo $feed->idFeedOut; ?>_statusFeed'
					onclick="manageFeedParam('cron', <?php echo $feed->idFeedOut; ?>, 'toggle', {'sub':<?php echo $feed->idCompany; ?>, 'idFeedOut':<?php echo $feed->idFeedOut; ?>});"
				><?php echo $feed->statusCron; ?></a>				
			</p>
		</td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $feed->accepted; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode($feed->idFeedOut);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->rejected; ?></a></p></td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $feed->queued; ?></p></td>
		<td class='fTO_options'>
			<p>
				<a href='#' class='nonLink'
					onclick="$('#cM_<?php echo $feed->idFeedOut; ?>').toggle();"
				>Options</a>
			</p>
			<div class='absContainer'>
				<div class='contextMenu' id='cM_<?php echo $feed->idFeedOut; ?>'>
					<p>
						<a href='#' class='nonLink' 
							onclick="display(<?php
								?>'feedout'<?php
								?>, { <?php
								?>'sub': '<?php echo $feed->idFeedOut; ?>' <?php
								?>, 'idFeedOut':'<?php echo $feed->idFeedOut; ?>'<?php
								?>}<?php
								?>); $('#cM_<?php echo $feed->idFeedOut; ?>').toggle();"
						>Show Details</a><br />
						<a href='#' class='nonLink' 
					onclick="display('dialog_newfeedout', { 'idFeedOut':'<?php echo $feed->idFeedOut; ?>'}, true);  $('#cM_<?php echo $feed->idFeedOut; ?>').toggle();" 
						>Create New Feed From This Feed</a><br />
						<a href='#' class='nonLink'
					onclick='display("dialog_testrecord", { "sub":"<?php echo $feed->idFeedOut; ?>", "idFeedOut": <?php echo $feed->idFeedOut; ?> });'
						>Send one test record</a><br />
						<a href='#' class='nonLink'	onclick='display("dialog_clearqueue", { "sub":"<?php echo $feed->idFeedOut; ?>", "idFeedOut": <?php echo $feed->idFeedOut; ?> });'>Clear Queue</a><br />
						<a href='#' class='nonLink'
					onclick='display("dialog_urlreport", { "sub":"<?php echo $feed->idFeedOut; ?>", "idFeedOut": <?php echo $feed->idFeedOut; ?> });'
						>URL Report</a>
					</p>
				</div>
			</div>
		</td>
	</tr>
	<tr><td class='hidden' id='feedout_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_editpopulation_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_editfeedout_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_urlreport_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_urlreportdetails_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_testrecord_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_clearqueue_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
<?php
		}
?>
	</tbody>
<?php
	}
?>
	<tr class='fTORow fTO_Row bgGray subtotal'>
		<td class='fTO_companyName' colspan='2'><p>GRAND TOTAL</p></td>
		<td class='fTO_feedOverview' colspan='4'><?php echo number_format( $grandTotalFeeds, 0 ); ?></td>
		<td class='fTO_accepted'><p class='aRight'><?php echo number_format( $grandTotalAccepted, 0 ); ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><?php echo number_format( $grandTotalRejected, 0 ); ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><?php echo number_format( $grandTotalQueued, 0 ); ?></p></td>
		<td class='fTO_options'></td>
	</tr>
</table>
<?php
}
	
		break;
		case 'feedout':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			$populationSettings = getPopulationSettings($idFeedOut);	
			$cacheFeedIn = array();
?>
<div class='w100'>
<hr />
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("feedout", { "sub": <?php echo $feed->idFeedOut; ?> });' 
	>Close [X]</a>
</div>
<p>Feed Details for <?php echo $feed->label; ?> (ID: <?php echo $feed->idFeedOut; ?>)</p>
<p>Description - <?php echo $feed->description; ?></p>
<p>
	Population
	<a href='#' class='nonLink' 
		onclick="display('dialog_editpopulation', { 'sub':  <?php echo $feed->idFeedOut; ?>, 'idFeedOut': <?php echo $feed->idFeedOut; ?> });" 
	>Edit</a>
</p>
<?php
if($populationSettings === false){ 
?>
<p>Error getting population settings.</p>
<?php
} elseif($populationSettings == 0){ 
?>
<p>No settings found.</p>
<?php
} else { 
?>
<table class='populationTable' border='1' cellpadding='0' cellspacing='0'>
	<thead>
		<tr>
			<th>Populating Feed</th>
			<th>Population Status</th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach($populationSettings as $popSet){ 
		if(!isset($cacheFeedIn[$popSet->idFeedIn])){ 
			$cacheFeedIn[$popSet->idFeedIn] = getIncomingFeed($popSet->idFeedIn);
			if(!is_object($cacheFeedIn[$popSet->idFeedIn])){ 
				$cacheFeedIn[$popSet->idFeedIn] = new stdClass;
				$cacheFeedIn[$popSet->idFeedIn]->label = 'Error';
			}
		}
		$statusPopulation = ($popSet->enabled)?'Populating':'Disabled';
?>
		<tr>
			<td>
				(<?php echo $popSet->idFeedIn; ?>) 
				<?php echo $cacheFeedIn[$popSet->idFeedIn]->label; ?>
			</td>
			<td>
				<?php echo $statusPopulation; ?>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
<p>
	Posting Instructions 
	<a href='#' class='nonLink' 
		onclick="display('dialog_editfeedout', { 'sub': <?php echo $feed->idFeedOut; ?>, 'idFeedOut': <?php echo $feed->idFeedOut; ?> });" 
	>Edit</a>
</p>
<p>
	Posting Type: <?php echo $feed->feedType; ?><br />
	Posting URL: <?php echo $feed->postUrl; ?><br />
	Static Fields: <?php echo $feed->staticFields; ?><br />
	Mapped Fields: <?php echo $feed->varFields; ?><br />
	Mapping: <?php echo $feed->fieldMap; ?><br />
	URL Assignments: <?php echo $feed->urlassignments; ?><br />
</p>
<hr />
</div>
<?php
		break;
		default:
?>
<p>Requested information doesn't exist.</p>
<?php
		break;
		case 'dialog_testrecord':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );

			require_once( SITE_ROOT . '/pushLead/_f_onlms.php' );
			$settings['testing'] = 0;
			$settings['testrecord'] = 1;
			$leaddata = array(
				'stamp' => date( 'Y-m-d H:i:s' ),
				'url' => 'http://www.' . SITE_URL,
				'ip' => '1.2.3.4',
				'email' => 'johndoe@somewhere.com',
				'fname' => 'John',
				'lname' => 'Doe',
				'addr' => '123 Main St',
				'addr2' => '',
				'city' => 'New York',
				'state' => 'NY',
				'zip' => '10003',
				'dob' => '1970-01-01',
				'gender' => 'M',
				'landline' => '2125551212',
				'cellphone' => '2125559999',
				'country' => 'US',
				'listcode' => '',
			);

			print "<p><strong>HTTP Method:</strong> " . $feed->feedType . "</p>";

			$response = runlead( $leaddata, $feed );

			print "<strong>Query String:</strong> " . htmlspecialchars( $response['querystring'] ) . "</p>";

			print "<strong>Response:</strong> " . htmlspecialchars( stripslashes( $response['text'] ) ) . "</p>";

			break;

		case 'dialog_clearqueue':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );

			$jobId = $leads->addJob( 'clear-outbound-queue', $idFeedOut, serialize( array( 'label' => $feed->label ) ), '', 0 );
			if( null === $jobId ) {
				print '<p>ERROR: Cannot add job to database.</p>';
			} else {
				$leads->auditLog( 'FEEDOUT:CLEAR-QUEUE', $jobId );
				printf( '<p>Clear outbound queue job submitted. <a href="/leadadmin/mgr_job.php?jobId=%d">View results</a></p>', $jobId );
			}

			break;

		case 'dialog_editfeedout':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$e = 'edit_'.$idFeedOut.'_';
			$feed = $leads->getOutboundFeed( $idFeedOut );
			if($feed === false){ 
?>
<p>Database failure - could not fetch requested feed information.</p>
<?php
				exit;
			} elseif(!is_object($feed) && $feed == 0){ 
?>
<p>Could not fetch requested feed information - feed does not exist.</p>
<?php
				exit;
			}
			if(!is_null($feed->staticFields) && $feed->staticFields != ''){ 
				$staticFields = explode(";", $feed->staticFields);
			}
			$varFields = explode(";", $feed->varFields);
			$fieldMap = explode(";", $feed->fieldMap);
		case 'dialog_newfeedout':
			if(!isset($e)){ 
				$e = 'new_'; 
				if(isset($_REQUEST['options']['idFeedOut'])){
					if($_REQUEST['options']['idFeedOut'] != ''){
						$idFeedOut = $_REQUEST['options']['idFeedOut'];
						$feed = $leads->getOutboundFeed( $idFeedOut );
						if($feed === false){ 
							$feed->label = 'Error! Could not copy.';
						} elseif(!is_object($feed) && $feed == 0){ 
							$feed->label = 'Error! Could not copy.';
						} else {
							$feed->label = (isset($_REQUEST['options']['label']))?$_REQUEST['options']['label']:'';
							$feed->description = (isset($_REQUEST['options']['description']))?$_REQUEST['options']['description']:'';
							if(!is_null($feed->staticFields) && $feed->staticFields != ''){ 
								$staticFields = explode(";", $feed->staticFields);
							}
							$varFields = explode(";", $feed->varFields);
							$fieldMap = explode(";", $feed->fieldMap);
						}
					}
				}
			}
			$feedProps = array('idFeedOut', 'label', 'description', 'idCompany', 'feedType', 'postUrl', 'successString', 'status', 'dailyLimit', 'delay' );
			foreach($feedProps as $feedProp){ 
				if(isset($feed)){ 
					${"feed_".$feedProp} = $feed->$feedProp;
				}elseif(isset($_REQUEST['options'][$feedProp])){ 
					${"feed_".$feedProp} = $_REQUEST['options'][$feedProp];
				}else { 
					${"feed_".$feedProp} = '';
				}
			}
			
			$explodableProperties = array(
				'staticFields', 'varFields', 'fieldMap', 'urlassignments'
			);
			foreach($explodableProperties as $eP){
				if( !isset($_REQUEST['options'][$eP]) ){ 
					if(!isset($feed->$eP) || $feed->$eP == ''){ 
						${"feed_".$eP} = array(); 
					} else {
						${"feed_".$eP} = explode(";", $feed->$eP);
					}
				} else {
					if($_REQUEST['options'][$eP] == ''){ 
						${"feed_".$eP} = array(); 
					} else { 
						${"feed_".$eP} = explode(";", $_REQUEST['options'][$eP]); 
					}
				}
			}

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$companies = array( $leads->getCompany( $feed_idCompany ) );
			} else {
				$companies = $leads->getCompanies();
			}
?>
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td><p>Feed Label</p></td>
		<td>
			<p>	
				<input type='hidden' name='<?php echo $e; ?>feed_idFeedOut'
					id='<?php echo $e; ?>feed_idFeedOut'
					value='<?php echo $feed_idFeedOut; ?>' 
				/>
				<input type='text' name='<?php echo $e; ?>feed_label'
					id='<?php echo $e; ?>feed_label'
					value='<?php echo $feed_label; ?>' 
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Description</p></td>
		<td>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_description'
					id='<?php echo $e; ?>feed_description'
					value='<?php echo $feed_description; ?>' 
				class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Company</p></td>
		<td>
			<p>
				<?php if($companies === false){ ?>
				Database failure - could not fetch company list
				<?php } elseif(!is_object($companies) && $companies == 0){ ?>
				There are no companies in the database. Please create a company before
				creating a feed.
				<?php } else { ?>
				<select name='<?php echo $e; ?>feed_idCompany' 
					id='<?php echo $e; ?>feed_idCompany'
				>
				<?php foreach($companies as $company){ ?>
					<option value='<?php echo $company->idCompany; ?>'
					<?php if($company->idCompany == $feed_idCompany){ 
					?>selected='selected'<?php } ?>
					><?php echo $company->name; ?></option>
				<?php } ?>
				</select>
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Feed Type</p></td>
		<td>
			<p>
				<select name='<?php echo $e; ?>feed_feedType' 
					id='<?php echo $e; ?>feed_feedType'
				>
					<option value='curlGET'<?php if($feed_feedType == 'curlGET'){ ?>selected='selected'<?php } ?>>HTTP GET</option>
					<option value='curlPOST'<?php if($feed_feedType == 'curlPOST'){ ?>selected='selected'<?php } ?>>HTTP POST</option>
					<option value='JSON'<?php if($feed_feedType == 'JSON'){ ?>selected='selected'<?php } ?>>JSON</option>
					<option value='csvString'<?php if($feed_feedType == 'csvString'){ ?>selected='selected'<?php } ?>>CSV string</option>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Post URL</p></td>
		<td>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_postUrl'
					id='<?php echo $e; ?>feed_postUrl'
					value='<?php echo $feed_postUrl; ?>' 
				class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Static Fields</p></td>
		<td>
			<p>
				These are fields that are assigned values specific to this feed, usually provided by the receiving
				client.
			</p>
			<p>
				<a href='#' class='nonLink' 
	onclick='element("<?php echo $e; ?>feed_staticFields_container", "staticField", { "e": "<?php echo $e; ?>" });'
				>Add New Static Field</a>
			</p>
			<div>
				<div id='<?php echo $e; ?>feed_staticFields_container' >
				<?php foreach($feed_staticFields as $sF){ 
					$valuePair = explode("=", $sF);
				?>
					<div>
						<input type='text' 
							name='<?php echo $e; ?>feed_staticFields_field[]'
							value='<?php echo $valuePair[0]; ?>'
						/> = <input type='text'
							name='<?php echo $e; ?>feed_staticFields_value[]'
							value='<?php echo $valuePair[1]; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Mapped Fields</p></td>
		<td>
			<p>These are fields that are assigned values for each lead. Enter the field name from the receiving 
				client's API spec, and select the lead value to be mapped from the drop-down.
			</p>
			<p>
				<a href='#' class='nonLink' 
	onclick='element("<?php echo $e; ?>feed_varFields_container", "varField", { "e": "<?php echo $e; ?>" });'
				>Add New Mapped Field</a>
			</p>
			<div>
				<div id='<?php echo $e; ?>feed_varFields_container' >
				<?php $sFCount = 0; foreach($feed_varFields as $vF){ ?>
					<div>
						API Field: <input type='text' 
							name='<?php echo $e; ?>feed_varFields[]'
							value='<?php echo $vF; ?>'
						/> Mapped To: <select
							name='<?php echo $e; ?>feed_fieldMap[]'
						>
							<?php foreach($recordFields as $rF){ ?>
							<option value='<?php echo $rF; ?>'
								<?php if($feed_fieldMap[$sFCount] == $rF){ echo "selected='selected'"; } ?>
							><?php echo $rF; ?></option>
							<?php } ?>
							<?php foreach($additionalMapFields as $aF){ ?>
							<option value='<?php echo $aF; ?>'
								<?php if($feed_fieldMap[$sFCount] == $aF){ echo "selected='selected'"; } ?>
							><?php echo $aF; ?></option>
							<?php } ?>
						</select>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php $sFCount++; } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>URL Assignments</p></td>
		<td>
			<p>
				If you utilize the urlAssign mapped field, when the feed is processing it will populate the mapped 
				field with values according to what you set here, that way you can have multiple different
				unique id's per url within the same feed.
			</p>
			<p>
				<a href='#' class='nonLink' 
	onclick='element("<?php echo $e; ?>feed_urlassignments_container", "urlassignment", { "e": "<?php echo $e; ?>" });'
				>Add New URL Assignment</a>
			</p>
			<div>
				<div id='<?php echo $e; ?>feed_urlassignments_container' >
				<?php foreach($feed_urlassignments as $uA){ 
					$valuePair = explode("=", $uA);
				?>
					<div>
						<input type='text' 
							name='<?php echo $e; ?>feed_urlassignments_url[]'
							value='<?php echo $valuePair[0]; ?>'
						/> = <input type='text'
							name='<?php echo $e; ?>feed_urlassignments_id[]'
							value='<?php echo $valuePair[1]; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Success String</p></td>
		<td>
			<p>This is the smallest form of the success response from the receiving client's API spec.</p>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_successString'
					id='<?php echo $e; ?>feed_successString'
					value='<?php echo $feed_successString; ?>' 
				class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Daily Feed Limit</p></td>
		<td>
			<p>Leave blank for no limit (default). If a value is supplied here, the feed will stop sending records after the daily limit is reached.</p>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_dailyLimit'
					id='<?php echo $e; ?>feed_dailyLimit'
					value='<?php echo $feed_dailyLimit; ?>' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Feed Delay</p></td>
		<td>
			<p>Leave blank for no delay (default). If a value is supplied here, records will sit in the queue for this number of minutes before being processed.</p>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_delay' id='<?php echo $e; ?>feed_delay' value='<?php echo $feed_delay; ?>' /> Minutes
			</p>
		</td>
	</tr>
	<tr>
	   <td><p>Feed Status</p></td>
	   <td>
		   <p>
			   <input type='radio' name='<?php echo $e; ?>feed_status' id='<?php echo $e; ?>feed_status' value='active' <?php if( empty( $feed_status ) || 'active' == $feed_status ) {?>checked='checked'<?php } ?>/> Active (Visible)<br/>
			   <input type='radio' name='<?php echo $e; ?>feed_status' id='<?php echo $e; ?>feed_status' value='hidden' <?php if( 'hidden' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Hidden)<br/>
			   <input type='radio' name='<?php echo $e; ?>feed_status' id='<?php echo $e; ?>feed_status' value='retired' <?php if( 'retired' == $feed_status ) { ?>checked='checked'<?php } ?>/> Retired
		   </p>
	   </td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
		<?php if(isset($idFeedOut) && $e == 'edit_'.$idFeedOut.'_'){ ?>
				<input type='button' value='Cancel Changes'  
					onclick='closeContent("dialog_editfeedout", { "sub": <?php echo $feed_idFeedOut; ?>});'
				/> 
				<input type='button' value='Save Changes' 
					onclick='manageFeed("update", <?php echo $feed_idFeedOut; ?>);'
				/>
		<?php } else { ?>
				<input type='button' value='Cancel'  
					onclick='closeContent("dialog_newfeedout");'
				/> 
				<input type='button' value='Add New Feed' 
					onclick='manageFeed("new");'
				/>
		<?php } ?>
			</p>
		</td>
	</tr>
</table>
<?php
		break;

		case 'dialog_urlreport':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_urlreport", {"sub": <?php echo $idFeedOut; ?>}); closeContent("dialog_urlreportdetails", {"sub": <?php echo $idFeedOut; ?>});' 
	>Close [X]</a>
</div>
<?php
			if($feed === false){ 
?>
<p>Database failure - could not fetch feed information.</p>
<?php 
			} elseif(!is_object($feed) && $feed == 0){ 
?>
<p>Error fetching feed information - feed does not exist.</p>
<?php
			} else { 
?>
<p>URL Report from Feed (ID:<?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?></p>
<input type='hidden' id='urlreport_idFeedOut' value='<?php echo $feed->idFeedOut; ?>' />
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td colspan='2'><p class='aCenter'>Report Settings</p></td>
	</tr>
	<tr>
		<td>
			<p>Period</p>
		</td>
		<td>
			<p>
				Period goes from midnight of the first date to midnight of the second date. Leave blank to select
				from all time records. (This could take a long time.)
			</p>
			<p>
				<input type='text' 
					name='urlreport_<?php echo $idFeedOut; ?>_dateStart' 
					id='urlreport_<?php echo $idFeedOut; ?>_dateStart' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d"); ?>'
				/>
				to <input type='text' 
					name='urlreport_<?php echo $idFeedOut; ?>_dateEnd' 
					id='urlreport_<?php echo $idFeedOut; ?>_dateEnd' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				URLs
			</p>
		</td>
		<td>
			<p>
				URLs to limit the selection by. Leave blank to select all records regardless of URL.
			</p>
			<p>
<?php
				$urls = $leads->getOutboundURLDates( $idFeedOut );
				if( $urls && is_array( $urls ) ) {
					printf( "<select multiple=\"multiple\" id=\"urlreport_%s_urls\" size=\"%d\">\n", $idFeedOut, sizeOf( $urls ) );
					foreach( $urls as $url ) {
						printf( "<option value=\"%s\">%s (%s)</option>\n", htmlspecialchars( $url['url'] ), htmlspecialchars( $url['url'] ), $url['date'] );
					}
					print "</select>\n";
				}
?>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Count By
			</p>
		</td>
		<td>
			<p><select id="urlreport_<?php echo $idFeedOut; ?>_breakdown"><option value="day" selected="selected">Day</option><option value="month">Month</option><option value="year">Year</option><option value="total">Total</option</select></p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Sort By
			</p>
		</td>
		<td>
			<p><select id="urlreport_<?php echo $idFeedOut; ?>_sort"><option value="date" selected="selected">Date</option><option value="url">URL</option><option value="count">Count</option></select></p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
				<input type="button" value="Run Report" onclick="display( 'dialog_urlreportdetails', { 'sub': <?php echo $idFeedOut; ?>, 
					'idFeedOut': <?php echo $idFeedOut; ?>, 
					'dateStart': $('#urlreport_<?php echo $idFeedOut; ?>_dateStart').val(),
					'dateEnd': $('#urlreport_<?php echo $idFeedOut; ?>_dateEnd').val(),
					'urlList': $('#urlreport_<?php echo $idFeedOut; ?>_urls').val(),
					'sort': $('#urlreport_<?php echo $idFeedOut; ?>_sort').val(),
					'breakdown': $('#urlreport_<?php echo $idFeedOut; ?>_breakdown').val() });" />
			</p>
		</td>
	</tr>
</table>
<?php
			}
		break;
		case 'dialog_urlreportdetails':

			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			if($feed === false){ 
?>
<p>Database failure - could not fetch feed information.</p>
<?php 

			} else if( !is_object($feed) && $feed == 0 ) { 
?>
<p>Error - could not fetch feed. Feed does not exist.</p>
<?php 
			} else {

				$stats = $leads->getOutboundURLStatsReport( $_REQUEST['options']['idFeedOut'], $_REQUEST['options']['urlList'], $_REQUEST['options']['breakdown'], $_REQUEST['options']['dateStart'], $_REQUEST['options']['dateEnd'], $_REQUEST['options']['sort'] );

				if( empty( $stats ) ) {
?>
<p>No records found.</p>
<?php
				} else {

					$fileLink = 'exports/' . $feed->label."_".time().".csv";
					$filePath = ADMIN_ROOT.$fileLink;
					$file = fopen($filePath, "w");
					if(!file_exists($filePath)){
?>
<p>Failed to create CSV report file.</p>
<?php
					} else {
						fputcsv( $file, array( 'URL', 'Date', 'Accepted', 'Rejected' ) );
						print "<table class='urlTable'>\n";
						print "<thead>\n";
						print "\t<tr>\n";
						print "\t<td>URL</td>\n";
						print "\t<td>Date</td>\n";
						print "\t<td>Accepted</td>\n";
						print "\t<td>Rejected</td>\n";
						print "\t</tr>\n";
						print "</thead>\n";
						print "<tbody>\n";
						print "\t<tr>\n";
						foreach( $stats as $stat ) {
							print "\t<tr>\n";
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['url'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['date'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['accepted'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['rejected'] ) );
							print "\t</tr>\n";
							fputcsv( $file, array( $stat['url'], $stat['date'], $stat['accepted'], $stat['rejected'] ) );
						}
						fclose($file);
						print "</tbody>\n";
						print "</table>\n";
						printf( '<p><a href="%s">Download this report</a></p>', $fileLink );
					}
				}
			}
		break;

		case 'staticField':
			$e = $_REQUEST['options']['e'];
?>
<div>
	<input type='text' 
		name='<?php echo $e; ?>feed_staticFields_field[]'
		value=''
	/> = <input type='text'
		name='<?php echo $e; ?>feed_staticFields_value[]'
		value=''
	/>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'urlassignment':
			$e = $_REQUEST['options']['e'];
?>
<div>
	<input type='text' 
		name='<?php echo $e; ?>feed_urlassignments_url[]'
		value=''
		placeholder='URL'
	/> = <input type='text'
		name='<?php echo $e; ?>feed_urlassignments_id[]'
		value=''
		placeholder='Unique ID'
	/>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'varField':
			$e = $_REQUEST['options']['e'];
?>
<div>
	API Field: <input type='text' 
		name='<?php echo $e; ?>feed_varFields[]'
		value=''
	/> Mapped To: <select
		name='<?php echo $e; ?>feed_fieldMap[]'
	>
		<?php foreach($recordFields as $rF){ ?>
		<option value='<?php echo $rF; ?>'
		><?php echo $rF; ?></option>
		<?php } ?>
		<?php foreach($additionalMapFields as $aF){ ?>
		<option value='<?php echo $aF; ?>'
		><?php echo $aF; ?></option>
		<?php } ?>
	</select>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'dialog_editpopsetting':
			$e = 'edit_';
			$idAssoc = $_REQUEST['options']['idAssoc'];
			$popset = getPopulationSetting($idAssoc);
			if($popset === false){ 
?>
<p>Database failure - could not fetch population setting.</p>
<?php
				exit;
			} elseif(!is_object($popset) && $popset == 0){ 
?>
<p>Could not fetch requested population setting - setting does not exist.</p>
<?php
				exit;
			}
		case 'dialog_newpopsetting':
			if(!isset($e)){ $e = 'new_'; }
			$populationProperties = array(
				'idAssoc', 'idFeedOut', 'idFeedIn', 'filterTypeUrl', 'filterTypeEmail'
				, 'filterTypeListcode', 'forceUrl', 'livedata'
			);
			foreach($populationProperties as $pP){ 
				if(isset($popset)){ 
					${"popset_".$pP} = $popset->$pP;
				}elseif(isset($_REQUEST['options'][$pP])){ 
					${"popset_".$pP} = $_REQUEST['options'][$pP];
				}else { 
					${"popset_".$pP} = '';
				}
			}		
			$explodableProperties = array(
				'filterUrl', 'filterEmail', 'filterListcode', 'forceUrlList'
			);
			foreach($explodableProperties as $eP){
				if( !isset($_REQUEST['options'][$eP]) ){ 
					if(!isset($popset->$eP)){ 
						${"popset_".$eP} = array(); 
					} else {
						${"popset_".$eP} = explode(";", $popset->$eP);
					}
				} else {
					if($_REQUEST['options'][$eP] == ''){ 
						${"popset_".$eP} = array(); 
					} else { 
						${"popset_".$eP} = explode(";", $_REQUEST['options'][$eP]); 
					}
				}
			}
			$feed = $leads->getOutboundFeed( $popset_idFeedOut );

			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$incomingFeeds = $leads->getInboundFeeds( null, 'active' );
			} else {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				$incomingFeeds = $leads->getInboundFeeds( $idCompany, 'active' );
			}
?>
<input type='hidden' name='<?php echo $e; ?>popset_idAssoc'
	id='<?php echo $e; ?>popset_idAssoc'
	value='<?php echo $popset_idAssoc; ?>'
/>
<input type='hidden' name='<?php echo $e; ?>popset_idFeedOut'
	id='<?php echo $e; ?>popset_idFeedOut'
	value='<?php echo $popset_idFeedOut; ?>'
/>
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td><p>Incoming Feed (To Populate From)</p></td>
		<td>
			<p>	
				<select id='<?php echo $e; ?>popset_idFeedIn'>
				<?php 
				foreach($incomingFeeds as $fI){ 
				?>
					<option value='<?php echo $fI->idFeedIn; ?>'
						<?php if($fI->idFeedIn == $popset_idFeedIn){ echo "selected='selected'"; } ?>
					>(<?php echo $fI->idFeedIn; ?>) <?php echo $fI->label; ?></option>
				<?php
				}
				?>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>URL Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into
				the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeUrl'
					id='<?php echo $e; ?>popset_filterTypeUrl_disabled'
					value='true'
					<?php if(
						empty($popset_filterTypeUrl)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeUrl').hide(); <?php 
					?>$('#<?php echo $e; ?>popset_filterUrl_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeUrl'
					id='<?php echo $e; ?>popset_filterTypeUrl_accept'
					value='true'
					<?php if($popset_filterTypeUrl == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeUrl').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterUrl_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeUrl'
					id='<?php echo $e; ?>popset_filterTypeUrl_reject'
					value='true'
					<?php if($popset_filterTypeUrl == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeUrl').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterUrl_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='<?php echo $e; ?>popset_toggler_filterTypeUrl' 
				style='display:<?php 
					if(empty($popset_filterTypeUrl)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following urls:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("<?php echo $e; ?>popset_filterUrl_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Url" });'
					>Add New URL to <span id='<?php echo $e; ?>popset_filterUrl_descriptor'></span></a>
					| <a href='#' class='nonLink'
						onclick='element("<?php echo $e; ?>popset_filterUrl_multipleInsert"<?php
						?>, "element_multifilter"<?php
						?>, { "e": "<?php echo $e; ?>"<?php
						?>, "type": "Url" });'
					>Add Multiple</a>
				</p>
				<div id='<?php echo $e; ?>popset_filterUrl_multipleInsert'></div>
				<div id='<?php echo $e; ?>popset_filterUrl_container'>
				<?php foreach($popset_filterUrl as $filterUrl){ ?>
					<div>
						<input type='text' 
							name='<?php echo $e; ?>popset_filterUrl[]'
							value='<?php echo $filterUrl; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Email Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, email domains that are listed here are the only ones that will be 
				accepted into the feed. Using the 'Reject' option, all email domains will be accepted, except 
				the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeEmail'
					id='<?php echo $e; ?>popset_filterTypeEmail_disabled'
					value='true'
					<?php if(
						empty($popset_filterTypeEmail)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeEmail').hide(); <?php 
					?>$('#<?php echo $e; ?>popset_filterEmail_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeEmail'
					id='<?php echo $e; ?>popset_filterTypeEmail_accept'
					value='true'
					<?php if($popset_filterTypeEmail == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeEmail').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterEmail_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeEmail'
					id='<?php echo $e; ?>popset_filterTypeEmail_reject'
					value='true'
					<?php if($popset_filterTypeEmail == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeEmail').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterEmail_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='<?php echo $e; ?>popset_toggler_filterTypeEmail' 
				style='display:<?php 
					if(empty($popset_filterTypeEmail)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following email domains:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("<?php echo $e; ?>popset_filterEmail_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Email"});'
					>Add New Email Domain to <span id='<?php echo $e; ?>popset_filterEmail_descriptor'></span></a>
				</p>
				<div id='<?php echo $e; ?>popset_filterEmail_container'>
				<?php foreach($popset_filterEmail as $filterEmail){ ?>
					<div>
						<input type='text' 
							name='<?php echo $e; ?>popset_filterEmail[]'
							value='<?php echo $filterEmail; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Listcode Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, listcodes that are listed here are the only ones that will be 
				accepted into the feed. Using the 'Reject' option, all listcodes will be accepted, except 
				the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeListcode'
					id='<?php echo $e; ?>popset_filterTypeListcode_disabled'
					value='true'
					<?php if(
						empty($popset_filterTypeListcode)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeListcode').hide(); <?php 
					?>$('#<?php echo $e; ?>popset_filterListcode_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeListcode'
					id='<?php echo $e; ?>popset_filterTypeListcode_accept'
					value='true'
					<?php if($popset_filterTypeListcode == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeListcode').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterListcode_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_filterTypeListcode'
					id='<?php echo $e; ?>popset_filterTypeListcode_reject'
					value='true'
					<?php if($popset_filterTypeListcode == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_filterTypeListcode').show(); <?php 
					?>$('#<?php echo $e; ?>popset_filterListcode_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='<?php echo $e; ?>popset_toggler_filterTypeListcode' 
				style='display:<?php 
					if(empty($popset_filterTypeListcode)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following email domains:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("<?php echo $e; ?>popset_filterListcode_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Listcode"});'
					>Add New Listcode to <span id='<?php echo $e; ?>popset_filterListcode_descriptor'></span></a>
				</p>
				<div id='<?php echo $e; ?>popset_filterListcode_container'>
				<?php foreach($popset_filterListcode as $filterListcode){ ?>
					<div>
						<input type='text' 
							name='<?php echo $e; ?>popset_filterListcode[]'
							value='<?php echo $filterListcode; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Force URL Options</p></td>
		<td>
			<p>
				Utilizing 'URL Forcing' changes the url listed in the incoming feed to a completely different URL
				for use in the outgoing feed. 
			</p>
			<p>
				<input type='radio' 
					name='<?php echo $e; ?>popset_forceUrl'
					id='<?php echo $e; ?>popset_forceUrl_disabled'
					value='true'
					<?php if($popset_forceUrl != '1'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_forceUrlList').hide();"
				/> Disabled<br />
				<input type='radio' 
					name='<?php echo $e; ?>popset_forceUrl'
					id='<?php echo $e; ?>popset_forceUrl_enabled'
					value='true'
					<?php if($popset_forceUrl == '1'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#<?php echo $e; ?>popset_toggler_forceUrlList').show();"
				/> Enabled
			</p>
			<div id='<?php echo $e; ?>popset_toggler_forceUrlList' 
				style='display:<?php 
					if($popset_forceUrl){ echo "block"; }
					else { echo "none"; } 
				?>;'
			>
				<p>
					Enter 'all' in the url field to force all non-specified urls to be changed to the new
					url. Other specified urls will be changed to the listed forced url.
				</p>
				<div>
					<p>Force Populating URLs to: </p>
					<p>
						<a href='#' class='nonLink' 
onclick='element("<?php echo $e; ?>popset_filterUrlList_container", "element_forceUrl", { "e": "<?php echo $e; ?>"});'
						>Add URL To Force</a>
					</p>
					<div id='<?php echo $e; ?>popset_filterUrlList_container'>
						<?php foreach($popset_forceUrlList as $fU){ 
							$valuePair = explode("=", $fU);
						?>
						<div>
							URL: <input type='text' 
								name='<?php echo $e; ?>popset_forceUrlList_original[]'
								value='<?php echo $valuePair[0]; ?>'
							/> Will be populated as: <input type='text'
								name='<?php echo $e; ?>popset_forceUrlList_altered[]'
								value='<?php echo $valuePair[1]; ?>'
							>
							<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
						</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Live data feed</p></td>
		<td>
			<p>
				Incoming records will be sent to this provider in REAL TIME as they come in.  Do not use this option unless authorized.  Most feeds have this option disabled.
			</p>
			<p>
				<input type='radio' name='<?php echo $e; ?>popset_livedata' id='<?php echo $e; ?>popset_livedata_disabled' value='true'
					<?php if($popset_livedata != '1'){ ?> checked='checked' <?php } ?>/> Disabled (DEFAULT)<br />
				<input type='radio' name='<?php echo $e; ?>popset_livedata' id='<?php echo $e; ?>popset_livedata_enabled' value='true'
					<?php if($popset_livedata == '1'){ ?> checked='checked' <?php } ?>/> Enabled
			</p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
		<?php if($e == 'edit_'){ ?>
				<input type='button' value='Cancel Changes'  
					onclick='closeContent("dialog_editpopsetting");'
				/> 
				<input type='button' value='Save Changes' 
					onclick='managePopulation("update", {"sub": <?php echo $feed->idCompany; ?>});'
				/>
		<?php } else { ?>
				<input type='button' value='Cancel'  
					onclick='closeContent("dialog_newpopsetting");'
				/> 
				<input type='button' value='Add New Population Parameter' 
					onclick='managePopulation("new", {"sub": <?php echo $feed->idCompany; ?>});'
				/>
		<?php } ?>
			</p>
		</td>
	</tr>
</table>
<?php
		break;
		case 'element_filter':
			$e = $_REQUEST['options']['e'];
			$t = $_REQUEST['options']['type'];
?>
<div>
	<input type='text' 
		name='<?php echo $e; ?>popset_filter<?php echo $t; ?>[]'
		value='<?php if(isset($_REQUEST['options']['value'])){ 
			echo $_REQUEST['options']['value']; 
		} ?>'
	/>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'element_forceUrl':
			$e = $_REQUEST['options']['e'];
?>
<div>
	URL: <input type='text' 
		name='<?php echo $e; ?>popset_forceUrlList_original[]'
		value=''
	/> Will be populated as: <input type='text'
		name='<?php echo $e; ?>popset_forceUrlList_altered[]'
		value=''
	>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'element_multifilter':
			$e = $_REQUEST['options']['e'];
			$t = $_REQUEST['options']['type'];
?>
<textarea name='<?php echo $e; ?>popset_filter<?php echo $t; ?>Multi'
id='<?php echo $e; ?>popset_filter<?php echo $t; ?>Multi' ></textarea>
<input type='button' value='Add Multiple Urls' 
	onclick="splitMultiFilter('<?php echo $e; ?>', '<?php echo $t; ?>');"
/>
<?php
		break;
		case 'dialog_editpopulation':
			$idFeedOut = !empty( $_REQUEST['options']['idFeedOut'] ) ? $_REQUEST['options']['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_editpopulation", {"sub":  <?php echo $idFeedOut; ?>} );' >Close [X]</a>
</div>
<?php
			$feed = $leads->getOutboundFeed( $idFeedOut );
			$populationSettings = getPopulationSettings($idFeedOut);	
			$cacheFeedIn = array();
?>
<p>
	Population Settings for (ID: <?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?><br />
	Description: <?php echo $feed->description; ?>
</p>
<p><a href='#' class='nonLink' onclick='display("dialog_newpopsetting", {"idFeedOut": <?php echo $feed->idFeedOut; ?>});' >Add New Population Parameter</a></p>
<div id='dialog_newpopsetting'></div>
<div id='dialog_editpopsetting'></div>
<?php
			if($populationSettings === false){ 
?>
<p>Error getting population settings.</p>
<?php
			} elseif($populationSettings == 0){ 
?>
<p>No settings found.</p>
<?php
			} else { 
?>
<table class='populationTable' border='1' cellpadding='0' cellspacing='0'>
	<thead>
		<tr>
			<th><p>Populating Feed</p></th>
			<th><p>Population Status</p></th>
			<th><p>Filtering By URL</p></th>
			<th><p>URL Filter Settings</p></th>
			<th><p>Filtering By Email</p></th>
			<th><p>Email Filter Settings</p></th>
			<th><p>Filtering By Listcode</p></th>
			<th><p>Listcode Filter Settings</p></th>
			<th><p>Force URLs</p></th>
			<th><p>Force URL Settings</p></th>
			<th><p>Actions</p></th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach($populationSettings as $popSet){ 
		if(!isset($cacheFeedIn[$popSet->idFeedIn])){ 
			$cacheFeedIn[$popSet->idFeedIn] = getIncomingFeed($popSet->idFeedIn);
			if(!is_object($cacheFeedIn[$popSet->idFeedIn])){ 
				$cacheFeedIn[$popSet->idFeedIn] = new stdClass;
				$cacheFeedIn[$popSet->idFeedIn]->label = 'Error';
			}
		}
		$statusPopulation = ($popSet->enabled)?'Populating':'Disabled';
		if(is_null($popSet->filterTypeUrl)){ 
			$filterTypeUrl = 'Off';
			$filterUrl = 'Disabled';
		} else { 
			$filterTypeUrl = 'On';
			if($popSet->filterTypeUrl == 'accept'){ 
				$filterUrl = 'Accepting: ';
			} else {
				$filterUrl = 'Rejecting: ';
			}
			$filterUrls = explode(';', $popSet->filterUrl);
			$comma = false;
			foreach($filterUrls as $url){ 
				if($comma){ $filterUrl .= ', '; }
				$filterUrl .= $url;
				$comma = true;
			}
		}
		if(is_null($popSet->filterTypeEmail)){ 
			$filterTypeEmail = 'Off';
			$filterEmail = 'Disabled';
		} else { 
			$filterTypeEmail = 'On';
			if($popSet->filterTypeEmail == 'accept'){ 
				$filterEmail = 'Accepting: ';
			} else {
				$filterEmail = 'Rejecting: ';
			}
			$filterEmails = explode(';', $popSet->filterEmail);
			$comma = false;
			foreach($filterEmails as $email){ 
				if($comma){ $filterEmail .= ', '; }
				$filterEmail .= $email;
				$comma = true;
			}
		}
		if(is_null($popSet->filterTypeListcode)){ 
			$filterTypeListcode = 'Off';
			$filterListcode = 'Disabled';
		} else { 
			$filterTypeListcode = 'On';
			if($popSet->filterTypeListcode == 'accept'){ 
				$filterListcode = 'Accepting: ';
			} else {
				$filterListcode = 'Rejecting: ';
			}
			$filterListcodes = explode(';', $popSet->filterListcode);
			$comma = false;
			foreach($filterListcodes as $listcode){ 
				if($comma){ $filterListcode .= ', '; }
				$filterListcode .= $listcode;
				$comma = true;
			}
		}
		if($popSet->forceUrl){ $forceUrl = 'On'; } else { $forceUrl = 'Off'; }
		$forceUrlListArray = explode(";", $popSet->forceUrlList);
		$forceUrlList = 'No urls assigned for force urls.';
		if($popSet->forceUrlList != '' && is_arraY( $forceUrlListArray )){ 
			$forceUrlList = '';
			foreach($forceUrlListArray as $valuePair){ 
				list($original, $altered) = explode("=", $valuePair);
				if($original == 'all'){ $original = 'All Urls'; }
				$forceUrlList .= "$original -> $altered<br />";
			}
		}
?>
		<tr>
			<td valign='top'>
				<p>
					(<?php echo $popSet->idFeedIn; ?>) 
					<?php echo $cacheFeedIn[$popSet->idFeedIn]->label; ?>
				</p>
			</td>
			<td valign='top'>
				<p><a href='#' class='nonLink'
					id='popset_<?php echo $popSet->idAssoc; ?>_enabled'
					onclick="managePopulationParam(<?php echo $popSet->idAssoc; ?>, 'toggle', {'sub':<?php echo $feed->idCompany; ?>, 'idFeedOut':<?php echo $feed->idFeedOut; ?>});"
				><?php echo $statusPopulation; ?></a></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeEmail; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterEmail; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeListcode; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterListcode; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $forceUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $forceUrlList; ?></p>
			</td>
			<td valign='top'>
				<p>
					<a href='#' class='nonLink' 
						onclick='display("dialog_editpopsetting", <?php
						?>{ <?php
							?>"idFeedOut": <?php echo $feed->idFeedOut; ?> <?php
							?>, "idAssoc": <?php echo $popSet->idAssoc; ?> <?php
						?> });' 
					>Edit</a>
				</p>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
<?php
		break;
	}
	exit;
}

$title = 'Outgoing Feed Manager';
include(INCLUDES."c_header.php");
?>
<script>
function manageFeed(action, idFeedOut){ 
	if(action == "new"){ e = "#new_feed_"; c = 'new'; } else { e = "#edit_"+idFeedOut+"_feed_"; c = 'edit'; }
	idFeedOut = $(e+'idFeedOut').val();
	label = $(e+'label').val();
	description = $(e+'description').val();
	idCompany = $(e+'idCompany').val();
	feedType = $(e+'feedType').val();
	postUrl = $(e+'postUrl').val();
	if(c == 'new'){
		staticFields_labels = $("input[name='"+c+"_feed_staticFields_field\\[\\]']")
			.map(function(){return $(this).val().trim();});
		staticFields_values = $("input[name='"+c+"_feed_staticFields_value\\[\\]']")
			.map(function(){return $(this).val().trim();});
		staticFields = ''; colonFlag = false;
		for(count = 0; count < staticFields_labels.length; count++){ 
			if(colonFlag) staticFields += ';';
			staticFields += staticFields_labels[count]+"="+staticFields_values[count];
			colonFlag = true;
		}
		
		urlassignments_urls = $("input[name='"+c+"_feed_urlassignments_url\\[\\]']")
			.map(function(){return $(this).val().trim();});
		urlassignments_ids = $("input[name='"+c+"_feed_urlassignments_id\\[\\]']")
			.map(function(){return $(this).val().trim();});
		urlassignments = ''; colonFlag = false;
		for(count = 0; count < urlassignments_urls.length; count++){ 
			if(colonFlag) urlassignments += ';';
			urlassignments += urlassignments_urls[count]+"="+urlassignments_ids[count];
			colonFlag = true;
		}
		
		varFields = $("input[name='"+c+"_feed_varFields\\[\\]']")
			.map(function(){return $(this).val().trim();}).get().join(";");
		fieldMap = $("select[name='"+c+"_feed_fieldMap\\[\\]']")
			.map(function(){return $(this).val().trim();}).get().join(";");
	} else {
		staticFields_labels = $("input[name='edit_"+idFeedOut+"_feed_staticFields_field\\[\\]']")
			.map(function(){return $(this).val().trim();});
		staticFields_values = $("input[name='edit_"+idFeedOut+"_feed_staticFields_value\\[\\]']")
			.map(function(){return $(this).val().trim();});
		staticFields = ''; colonFlag = false;
		for(count = 0; count < staticFields_labels.length; count++){ 
			if(colonFlag) staticFields += ';';
			staticFields += staticFields_labels[count]+"="+staticFields_values[count];
			colonFlag = true;
		}
		urlassignments_urls = $("input[name='edit_"+idFeedOut+"_feed_urlassignments_url\\[\\]']")
			.map(function(){return $(this).val().trim();});
		urlassignments_ids = $("input[name='edit_"+idFeedOut+"_feed_urlassignments_id\\[\\]']")
			.map(function(){return $(this).val().trim();});
		urlassignments = ''; colonFlag = false;
		for(count = 0; count < urlassignments_urls.length; count++){ 
			if(colonFlag) urlassignments += ';';
			urlassignments += urlassignments_urls[count]+"="+urlassignments_ids[count];
			colonFlag = true;
		}
		varFields = $("input[name='edit_"+idFeedOut+"_feed_varFields\\[\\]']")
			.map(function(){return $(this).val().trim();}).get().join(";");
		fieldMap = $("select[name='edit_"+idFeedOut+"_feed_fieldMap\\[\\]']")
			.map(function(){return $(this).val().trim();}).get().join(";");
	}
	successString = $(e+'successString').val();
	dailyLimit = $(e+'dailyLimit').val();
	if( dailyLimit == '' ) { dailyLimit = 0; }
	delay = $(e+'delay').val();
	if( delay == '' ) { delay = 0; }
	status = $(e+'status:checked').val();
/* 	alert(
		"idFeedOut: "+idFeedOut
		+"\n"+"label: "+label
		+"\n"+"description: "+description
		+"\n"+"idCompany: "+idCompany
		+"\n"+"feedType: "+feedType
		+"\n"+"postUrl: "+postUrl
		+"\n"+"staticFields: "+staticFields
		+"\n"+"urlassignments: "+urlassignments
		+"\n"+"varFields: "+varFields
		+"\n"+"fieldMap: "+fieldMap
		+"\n"+"successString: "+successString
	);	  */
	v = true;
	if(v && staticFields.length > 1000){ 
		v = false; alert("Maximum length reached for staticFields.");	
	}
	if(v && urlassignments.length > 1000){ 
		v = false;
		alert(
			"Maximum length reached for url assignments, remove "
			+"some url assignments and consider creating a new feed."
		);
	}
	if(v && varFields.length > 1000){ 
		v = false; 
		alert("Maximum length reached for field mapping (field names).");
	}
	if(v && fieldMap.length > 1000){ 
		v = false; 
		alert("Maximum length reached for field mapping (field values).");
	}
	if(v){ 
		var response = $.ajax({
			url: "mgr_feedout.php",
			type: "POST",
			async: true,
			data: ({
				"a" : "manageFeedOut"
				, "action" : action
				, "idFeedOut": idFeedOut
				, "label":label
				, "description":description
				, "idCompany":idCompany
				, "feedType":feedType
				, "postUrl": postUrl
				, "staticFields":staticFields
				, "varFields":varFields
				, "fieldMap":fieldMap
				, "successString":successString
				, "dailyLimit":dailyLimit
				, "delay":delay
				, "status":status
				, "urlassignments":urlassignments
			})
		}).done(function(responseText){ 
			var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
			if(result===null) { 
				alert("JSON Failed: "+responseText); 
				if(c == 'new'){
					display('dialog_'+c+'feedout'
						, { 
							  "idFeedOut": idFeedOut
							, "label":label
							, "description":description
							, "idCompany":idCompany
							, "feedType":feedType
							, "postUrl": postUrl
							, "staticFields":staticFields
							, "varFields":varFields
							, "fieldMap":fieldMap
							, "successString":successString
							, "dailyLimit":dailyLimit
							, "delay":delay
							, "urlassignments":urlassignments
						} 
					);
				} else { 
					display('dialog_'+c+'feedout'
						, { 
							"sub": idFeedOut
							, "idFeedOut": idFeedOut
							, "label":label
							, "description":description
							, "idCompany":idCompany
							, "feedType":feedType
							, "postUrl": postUrl
							, "staticFields":staticFields
							, "varFields":varFields
							, "fieldMap":fieldMap
							, "successString":successString
							, "dailyLimit":dailyLimit
							, "delay":delay
							, "urlassignments":urlassignments
						} 
					);
				}
			}
			if(result.status == 1){ 
				if(c == 'new'){
					alert("Successfully created new feed.");
					closeContent('dialog_'+c+'feedout');
					display(
						'outgoingFeeds'
						, {
							'callbackParams': {
								'idCompany': idCompany
							}
						}
						, true
						, function(o){ 
							toggleHidden(
								'companyFeedList'
								, {'sub':o.idCompany, 'hiddenText':'Show Feeds', 'shownText':'Close' }
							);
						}
					);
				} else { 
					alert("Successfully saved updated settings.");
					//alert("Editing feed");
					closeContent('dialog_'+c+'feedout', { "sub": idFeedOut });
					display(
						'outgoingFeeds'
						, {
							'callbackParams': {
								'idCompany': idCompany
								, 'idFeedOut': idFeedOut
							}
						}
						, true
						, function(o){ 
							toggleHidden(
								'companyFeedList'
								, {'sub':o.idCompany, 'hiddenText':'Show Feeds', 'shownText':'Close' }
							);
							display(
								'feedout'
								, { 
									'sub': o.idFeedOut
									, 'idFeedOut': o.idFeedOut
								}
							); 
						}
					);
				}
			} else { 
				alert(result.error);
				if(c == 'new'){
					display('dialog_'+c+'feedout'
						, { 
							  "idFeedOut": idFeedOut
							, "label":label
							, "description":description
							, "idCompany":idCompany
							, "feedType":feedType
							, "postUrl": postUrl
							, "staticFields":staticFields
							, "varFields":varFields
							, "fieldMap":fieldMap
							, "successString":successString
							, "dailyLimit":dailyLimit
							, "delay":delay
							, "urlassignments":urlassignments
						} 
					);
				} else { 
					display('dialog_'+c+'feedout'
						, { 
							"sub": idFeedOut
							, "idFeedOut": idFeedOut
							, "label":label
							, "description":description
							, "idCompany":idCompany
							, "feedType":feedType
							, "postUrl": postUrl
							, "staticFields":staticFields
							, "varFields":varFields
							, "fieldMap":fieldMap
							, "successString":successString
							, "dailyLimit":dailyLimit
							, "delay":delay
							, "urlassignments":urlassignments
						} 
					);
				}
			}
		});
		$('#dialog_'+c+'feedout').html("Processing...");
	}
}
function managePopulation(action, options){ 
	if(action == "new"){ e = "#new_popset_"; c = 'new'; } else { e = "#edit_popset_"; c = 'edit'; }
	idAssoc = $(e+'idAssoc').val();
	idFeedOut = $(e+'idFeedOut').val();
	idFeedIn = $(e+'idFeedIn').val();
	if($(e+'filterTypeUrl_disabled').is(":checked")){
		filterTypeUrl = 'null';
	}else if($(e+'filterTypeUrl_accept').is(":checked")){ 
		filterTypeUrl = 'accept';
	}else if($(e+'filterTypeUrl_reject').is(":checked")){ 
		filterTypeUrl = 'reject';
	}
	if($(e+'filterTypeEmail_disabled').is(":checked")){
		filterTypeEmail = 'null';
	}else if($(e+'filterTypeEmail_accept').is(":checked")){ 
		filterTypeEmail = 'accept';
	}else if($(e+'filterTypeEmail_reject').is(":checked")){ 
		filterTypeEmail = 'reject';
	}
	if($(e+'filterTypeListcode_disabled').is(":checked")){
		filterTypeListcode = 'null';
	}else if($(e+'filterTypeListcode_accept').is(":checked")){ 
		filterTypeListcode = 'accept';
	}else if($(e+'filterTypeListcode_reject').is(":checked")){ 
		filterTypeListcode = 'reject';
	}
	filterUrl = $("input[name='"+c+"_popset_filterUrl\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");
	filterEmail = $("input[name='"+c+"_popset_filterEmail\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");
	filterListcode = $("input[name='"+c+"_popset_filterListcode\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");
	
	if($(e+'forceUrl_disabled').is(":checked")){ forceUrl = '0'; }
	else { forceUrl = '1'; }	
	forceUrlList_originals = $("input[name='"+c+"_popset_forceUrlList_original\\[\\]']")
		.map(function(){return $(this).val().trim();});
	forceUrlList_altereds = $("input[name='"+c+"_popset_forceUrlList_altered\\[\\]']")
		.map(function(){return $(this).val().trim();});
	forceUrlList = ''; colonFlag = false;
	for(count = 0; count < forceUrlList_originals.length; count++){ 
		if(colonFlag) forceUrlList += ';';
		forceUrlList += forceUrlList_originals[count]+"="+forceUrlList_altereds[count];
		colonFlag = true;
	}
	if($(e+'livedata_disabled').is(":checked")){ livedata = '0'; }
	else { livedata = '1'; }	

	/* alert(
		"idAssoc:"+idAssoc
		+"\n"+"idFeedOut: "+idFeedOut
		+"\n"+"idFeedIn: "+idFeedIn
		+"\n"+"filterTypeUrl: "+filterTypeUrl
		+"\n"+"filterUrl: "+filterUrl
		+"\n"+"filterTypeEmail: "+filterTypeEmail
		+"\n"+"filterEmail: "+filterEmail
		+"\n"+"filterTypeListcode: "+filterTypeListcode
		+"\n"+"filterListcode: "+filterListcode
		+"\n"+"forceUrl: "+forceUrl
		+"\n"+"forceUrlList: "+forceUrlList
	);	 */
	c = true;
	if(c && filterUrl.length > 5000){ alert("Maximum length reached for url filters, remove some <?php
		?>filters and consider creating a new feed, or consider changing the filtering type.");
		c = false;
	}
	if(c && filterEmail.length > 5000){ alert("Maximum length reached for email filters, remove <?php
	?>some filters and consider creating a new feed, or consider changing the filtering type.");
		c = false;
	}
	if(c && filterListcode.length > 1000){ alert("Maximum length reached for listcode filters, <?php
	?>remove some filters and consider creating a new feed, or consider changing the filtering type.");
		c = false;
	}
	if(c && forceUrlList.length > 5000){ alert("Maximum length reached for url forcing, remove <?php
	?>some urls to force, and consider creating a new feed.");
		c = false;
	}
	if(c){ 
		var response = $.ajax({
			url: "mgr_feedout.php",
			type: "POST",
			async: true,
			data: ({
				"a" : "managePopulation"
				, "action" : action
				, "idAssoc": idAssoc
				, "idFeedOut":idFeedOut
				, "idFeedIn":idFeedIn
				, "filterTypeUrl":filterTypeUrl
				, "filterUrl":filterUrl
				, "filterTypeEmail": filterTypeEmail
				, "filterEmail":filterEmail
				, "filterTypeListcode":filterTypeListcode
				, "filterListcode":filterListcode
				, "livedata":livedata
				, "forceUrl":forceUrl
				, "forceUrlList":forceUrlList
			})
		}).done(function(responseText){ 
			var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
			if(result===null) { 
				alert("JSON Failed: "+responseText); 
				display('dialog_'+c+'popsetting'
					, { 
						"sub": idFeedOut
						, "idAssoc": idAssoc
						, "idFeedOut":idFeedOut
						, "idFeedIn":idFeedIn
						, "filterTypeUrl":filterTypeUrl
						, "filterUrl":filterUrl
						, "filterTypeEmail": filterTypeEmail
						, "filterEmail":filterEmail
						, "filterTypeListcode":filterTypeListcode
						, "filterListcode":filterListcode
						, "livedata":livedata
						, "forceUrl":forceUrl
						, "forceUrlList":forceUrlList
					} 
				);
			}
			if(result.status == 1){ 
				closeContent('dialog_'+c+'popsetting');
				display(
					'outgoingFeeds'
					, { 
						'callbackParams': {
							'sub':options.sub
							, 'idFeedOut':idFeedOut
						}
					}
					, true
					, function(o){ 
						toggleHidden(
							'companyFeedList'
							, {'sub':o.sub, 'hiddenText':'Show Feeds', 'shownText':'Close' }
						);
						display(
							'dialog_editpopulation'
							, { 'sub': o.idFeedOut, 'idFeedOut': o.idFeedOut }
						);
					}
				);	
			} else { 
				alert(result.error);
				display('dialog_'+c+'popsetting'
					, { 
						"sub": idFeedOut
						, "idAssoc": idAssoc
						, "idFeedOut":idFeedOut
						, "idFeedIn":idFeedIn
						, "filterTypeUrl":filterTypeUrl
						, "filterUrl":filterUrl
						, "filterTypeEmail": filterTypeEmail
						, "filterEmail":filterEmail
						, "filterTypeListcode":filterTypeListcode
						, "filterListcode":filterListcode
						, "livedata":livedata
						, "forceUrl":forceUrl
						, "forceUrlList":forceUrlList
					} 
				);
			}
		});
		$('#dialog_'+c+'feedout').html("Processing..."); 
	}
}

function splitMultiFilter(e, t){ 
	values = $('#'+e+'popset_filter'+t+'Multi').val();
	//alert(values);
	valueArray = values.match(/[^\r\n]+/g);
	for(count = 0; count < valueArray.length; count++){ 
		element(
			e+"popset_filter"+t+"_container"
			, "element_filter"
			, { 
				"e": e
				, "type": t 
				, "value": valueArray[count]
			}
		);
	}
	//alert('#'+e+'popset_filter'+t+'_multipleInsert');
	$('#'+e+'popset_filter'+t+'_multipleInsert').html("");	
}

function managePopulationParam(idAssoc, action, options){ 
	c = true;
	if(action == 'delete'){ c = confirm("Are you sure you want to delete this population parameter?"); }
	if(c){ 
		var response = $.ajax({
			url: "mgr_feedout.php",
			type: "POST",
			async: true,
			data: ({
				"a" : "managePopulationParam"
				, "action" : action
				, "idAssoc": idAssoc
			})
		}).done(function(responseText){ 
			var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
			if(result===null) { 
				alert("JSON Failed: "+responseText); 
			} else { 
				if(result.status == 1){ 
					switch(action){ 
						case 'toggle':
							display(
								'outgoingFeeds'
								, { 
									'callbackParams': {
										'sub':options.sub
										, 'idFeedOut':options.idFeedOut
									}
								}
								, true
								, function(o){ 
									toggleHidden(
										'companyFeedList'
										, {'sub':o.sub, 'hiddenText':'Show Feeds', 'shownText':'Close' }
									);
									display(
										'dialog_editpopulation'
										, { 'sub': o.idFeedOut, 'idFeedOut': o.idFeedOut }
									);
								}
							);												
						break;
						case 'delete':
							display(
								'outgoingFeeds'
								, { 
									'callbackParams': {
										'sub':options.sub
										, 'idFeedOut':options.idFeedOut
									}
								}
								, true
								, function(o){ 
									toggleHidden(
										'companyFeedList'
										, {'sub':o.sub, 'hiddenText':'Show Feeds', 'shownText':'Close' }
									);
									display(
										'dialog_editpopulation'
										, { 'sub': o.idFeedOut, 'idFeedOut': o.idFeedOut }
									);
								}
							);	
						break;
					}
				} else { 
					alert(result.error);
				}
			}			
		});
	}
}

function manageFeedParam(param, idFeedOut, action, options){ 
	c = true;
	//if(action == 'delete'){ c = confirm("Are you sure you want to delete this population parameter?"); }
	if(c){ 
		var response = $.ajax({
			url: "mgr_feedout.php",
			type: "POST",
			async: true,
			data: ({
				"a" : "manageFeedParam"
				, "param": param
				, "action" : action
				, "idFeedOut": idFeedOut
			})
		}).done(function(responseText){ 
			var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
			if(result===null) { 
				alert("JSON Failed: "+responseText); 
			} else { 
				if(result.status == 1){ 
					switch(action){ 
						case 'toggle':
							display(
								'outgoingFeeds'
								, { 
									'callbackParams': {
										'sub':options.sub
										, 'idFeedOut':options.idFeedOut
									}
								}
								, true
								, function(o){ 
									toggleHidden(
										'companyFeedList'
										, {'sub':o.sub, 'hiddenText':'Show Feeds', 'shownText':'Close' }
									);
								}
							);												
						break;
					}
				} else { 
					alert(result.error);
				}
			}			
		});
	}
}

$(document).ready(function(){ 
	display('outgoingFeeds', { 'status': 'active' } );

	$('#status').change(function() {
		display('outgoingFeeds', { 'status': $(this).val() } );
	});
});
</script>
<body>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls'>
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>
			<a href='#' class='nonLink' onclick="display('dialog_newfeedout');">Add New Feed</a>
			<select class="fr" id="status" name="status">
				<option value="active">Show active feeds</option>
				<option value="hidden">Show hidden feeds</option>
				<option value="retired">Show retired feeds</option>
				<option value="">Show all feeds</option>
			</select>
<?php } ?>
		</div>
		<div id='dialogs'>
			<div id='dialog_newfeedout' style='display:none;'></div>
			<div id='dialog_editfeedout' style='display:none;'></div>
			<div id='dialog_editpopulation' style='display:none;'></div>
		</div>
		<div>
			<div id='feedSettings' style='display:none;'></div>
		</div>
		<div>
			<div id='outgoingFeeds'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>

</body>
</html>
