<?php 
//ADMIN_ROOT/mgr_companies.php
//Version 1.0
//ES20130722 Version 1.0: Company manager created.
session_start();
$mysqlErrorSource = 'Manager - Incoming Feeds';
include("../c_config.php");
$forceMysqlLogFile = SITE_ROOT."error".FD."log_feedinc"; 
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.

function checkExistsLabelFeedIn($label){ 
	//Returns quantity of matching records, or false if it fails.
	dbCon();
	$checkFeed = "SELECT * FROM `".DATABASE_NAME."`.`feedinc` "
		."WHERE "
			."`label` = '".$GLOBALS['dbconnx']->escape_string($label)."' "
		.";";
	$docheckFeed = dbQry($checkFeed, 'Checking if label exists', true);
	dbDcon();
	if($docheckFeed === false){ return false; }
	return $docheckFeed->num_rows;
}

function addFeedIn(
	$label
	, $description
	, $idCompany
	, $required
	, $allowedFields
	, $password
	, $dedupeEmail
	, $dedupeLandline
	, $dedupeCellphone
	, $dedupeAcross
	, $filterTypeUrl
	, $filterUrl
){ 
	$result = array(
		'success' => false
		, 'reason' => 'None.'
	);
	$c = true;
	dbCon("insertUpdate");
	if($c){ //Add feed.
		$addFeed = "INSERT INTO `".DATABASE_NAME."`.`feedinc` "
			."(`label`,`description`,`idCompany`,`required`,`allowedFields`,`password`, "
			."`dedupeEmail`,`dedupeLandline`, `dedupeCellphone`, `dedupeAcross`) VALUES ( "
			."  '".$GLOBALS['dbconnx']->escape_string($label)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($description)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($idCompany)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($required)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($allowedFields)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($password)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($dedupeEmail)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($dedupeLandline)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($dedupeCellphone)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($dedupeAcross)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($filterTypeUrl)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($filterUrl)."' "
			.");";
		$doaddFeed = dbQry($addFeed, 'Adding new feed.', true);
		if($doaddFeed === false){ 
			$c = false; $result['reason'] = 'Database failure - could not add feed.';
		} 
	}
	if($c){ //Create valids table.
		$createValidsTable = "CREATE TABLE `".DATABASE_NAME."`.`feedinc_".$label."` ( "
			."`idRecord` int(11) NOT NULL auto_increment, "
			."`queryString` varchar(1000) default NULL, "
			."`listcode` varchar(20) default NULL, "
			."`urlTrim` varchar(100) default NULL, "
			."`url` varchar(500) default NULL, "
			."`ip` varchar(16) default NULL, "
			."`received` datetime default NULL, "
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
			."KEY `urlTrim` (`urlTrim`) "
			.") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		$docreateValidsTable = dbQry($createValidsTable, 'Creating table for valid records.', true);
		if($docreateValidsTable === false){ 
			$c = false; $result['reason'] = 'Database failure - could not create valids table.';
		}
	}
	if($c){ //Create invalids table.
		$createValidsTable = "CREATE TABLE `".DATABASE_NAME."`.`feedinc_".$label."_invalid` ( "
			."`idRecord` int(11) NOT NULL auto_increment, "
			."`queryString` varchar(2500) default NULL, "
			."`error` varchar(500) default NULL, "
			."`listcode` varchar(20) default NULL, "
			."`urlTrim` varchar(100) default NULL, "
			."`url` varchar(500) default NULL, "
			."`ip` varchar(16) default NULL, "
			."`received` datetime default NULL, "
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
			."PRIMARY KEY  (`idRecord`) "
			.") ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;";
		$docreateValidsTable = dbQry($createValidsTable, 'Creating table for valid records.', true);
		if($docreateValidsTable === false){ 
			$c = false; $result['reason'] = 'Database failure - could not create valids table.';
		}
	}
	dbDcon();
	if($c){ 
		$result['success'] = true;
		$result['reason'] = 'Successfully added feed and created feed tables.';
	}
	return $result;
}

function alterFeedIn($idFeedIn, $property, $newVal){ 
	$result = array(
		'success' => false
		, 'reason' => 'None.'
	);
	$c = true;
	switch($property){
		case 'label':
			//Change label in database.
			if($c){ 
				$feed = getIncomingFeed($idFeedIn);
				if($feed === false){ 
					$c = false; $result['reason'] = 'Database failure - could not fetch feed to alter.';
				}
			}
			dbCon("insertUpdate");
			if($c){ //Updated feedinc entry.
				$updateLabel = "UPDATE `".DATABASE_NAME."`.`feedinc` "
					."SET `label` = '".$newVal."' "
					."WHERE `idFeedIn` = '".$idFeedIn."'; ";
				$doupdateLabel = dbQry($updateLabel, 'Updating feed label', true);
				if($doupdateLabel === false){ $c = false; $result['reason'] = 'Database failure - could not update '
					.'label name.';
				}
			}
			if($c){ //Updating table names.
				$updateTableNames = 
					"RENAME TABLE "
						."`".DATABASE_NAME."`.`feedinc_".$feed->label."` "
							."TO `".DATABASE_NAME."`.`feedinc_".$newVal."`, "
						."`".DATABASE_NAME."`.`feedinc_".$feed->label."_invalid` "
							."TO `".DATABASE_NAME."`.`feedinc_".$newVal."_invalid`; ";
				$doupdateTableNames = dbQry($updateTableNames, 'Updating table names', true);
				if($doupdateTableNames === false){ 
					$c = false; $result['reason'] = 'Database failure - could not update table names.';
				}
			}
			dbDcon();
			if($c){ //Update folder name.
				$path = LIVE_ROOT.$feed->label;
				$renameResult = rename($path, LIVE_ROOT.$newVal);
				if(!$renameResult){ 
					$c = false; $result['reason'] = 'Could not rename folder for incoming feed.';
				}
			}
			if($c){ $result['success'] = true; $result['reason'] = 'Successfully updated label for incoming feed.'; }
		break;
		default: 
			dbCon("insertUpdate");
			if($c){ //Updated feedinc entry.
				$updateProperty = "UPDATE `".DATABASE_NAME."`.`feedinc` "
					."SET `".$property."` = '".$newVal."' "
					."WHERE `idFeedIn` = '".$idFeedIn."'; ";
				$doupdateProperty = dbQry($updateProperty, 'Updating incoming feed properties', true);
				if($doupdateProperty === false){ $c = false; $result['reason'] = 'Database failure - could not update '
					.$property.'.';
				}
			}
			if($c){ $result['success'] = true; $result['reason'] = 'Successfully updated '.$property.' for incoming '
				.'feed.';
			}
		break;
	}
	return $result;
}

function exportData($feedObject, $settings){
	$result = array(
		'success' => false
		, 'reason' => 'None.'
		, 'fileLink' => null
	);
	$c = true;
	dbCon();
	if($c){ 
		$fetchData = "SELECT "; $csvTopLine = ""; $comma = false;
		foreach($settings['columns'] as $column){ 
			if($comma){ $fetchData .= ", "; $csvTopLine .= ","; }
			$fetchData .= "`".$column."` "; $csvTopLine .= $column;
			$comma = true;
		}
	}
	if($c){ 
		$fetchData .= "FROM `".DATABASE_NAME."`.`feedinc_".$feedObject->label."` ";
	}
	$whereFlag = false;
	if($c && $settings['dateStart'] != '' && $settings['dateEnd'] != ''){ 
		if(
			strtotime($settings['dateStart']) 
			> strtotime($settings['dateEnd'])
		){ 
			$dateStart = date("Y-m-d H:i:s", strtotime($settings['dateEnd']));
			$dateEnd = date("Y-m-d H:i:s", strtotime($settings['dateStart']));
		}else { 
			$dateStart = date("Y-m-d H:i:s", strtotime($settings['dateStart']));
			$dateEnd = date("Y-m-d H:i:s", strtotime($settings['dateEnd']));
		}
		if(!$whereFlag) { $fetchData .= "WHERE "; $whereFlag = true; }
		$fetchData .= "`received` >= '".$dateStart."' AND `received` < '".$dateEnd."' ";
	}
	if($c && count($settings['urlList']) != 0){ 
		if(!$whereFlag) { $fetchData .= "WHERE "; $whereFlag = true; } else {
			$fetchData .= "AND ";
		}
		$fetchData .= "( "; $orFlag = false;
		foreach($settings['urlList'] as $url){ 
			if($orFlag){ $fetchData .= "OR "; }
			$fetchData .= "`url` LIKE '%".$url."%' ";
			$orFlag = true; 
		}
		$fetchData .= ") ";
	}
	if($c){ 
		$dofetchData = dbQry($fetchData, 'Fetching specified data set.', true);
		if($dofetchData === false){ 
			$c = false; $result['reason'] = 'Database failure - failed to fetch data set.';
		}
	}
	dbDcon();
	if($c){ 
		$fileLink = 'exports/' . $feedObject->label."_".time().".csv";
		$filePath = ADMIN_ROOT.$fileLink;
		$file = fopen($filePath, "w");
		if(!file_exists($filePath)){ 
			$c = false; $result['reason'] = 'Failed to create CSV file.';
		}
	}
	if($c){ 
		fwrite($file, $csvTopLine."\r\n");
		while($row = $dofetchData->fetch_object()){ 
			$line = ''; $comma = false;
			foreach($settings['columns'] as $column){ 
				if($comma){ $line .= ','; }
				$line .= $row->$column;
				$comma = true;
			}
			fwrite($file, $line."\r\n");
		}
		fclose($file);
	}
	if($c){ 
		$result['success'] = true;
		$result['reason'] = 'Successfully exported data to file.';
		$result['query'] = $fetchData;
		$result['fileLink'] = $fileLink;
	}
	return $result;
}

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "manageFeed":
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
					$pattern = '/^[a-z][a-z1-9_]*$/';
					if(!preg_match($pattern, $_REQUEST['label'])){ 
						$c = false; $result['error'] = 'Label must start with a letter, can contain letters, '
							.'numbers, and underscore only.';
					}
				}
				if($c && ( //Must allow some fields, or the feed is worthless isn't it
					$_REQUEST['allowedFields'] == ''
				)){ 
					$c = false; $result['error'] = 'You must allow fields to be processed.'; 
				}
				//Special Validation of Inputs
				if($c){  //Label can not be already used
					$checkResult = checkExistsLabelFeedIn($_REQUEST['label']);
					if($checkResult === false){ 
						$c = false; $result['error'] = 'Database failure - could not '
							.'check if label is already in use.'; 
					}
					if($c && $checkResult > 0){ 
						$c = false; $result['error'] = 'Label is already in use.'; 
					}					
				}
				if($c){ //Make sure that any required fields are also allowed
					$selectedRequired = explode(";", $_REQUEST['required']);
					$selectedAllowedFields = explode(";", $_REQUEST['allowedFields']);
					foreach($selectedRequired as $f){ 
						switch($f){ 
							case "phone":
								if(
									!in_array('landline', $selectedAllowedFields) 
									|| !in_array('cellphone', $selectedAllowedFields)
								){
									$c = false; $result['error'] = 'If phone is selected, both landline and cellphone '
										.'must be allowed fields.';
								}
							break;
							default: 
								if(!in_array($f, $selectedAllowedFields)){ 
									$c = false; $result['error'] = $f.' is a required field, '
										.'and must be allowed as well.';
								}
						}
						if(!$c){ break; }
					}
				}
				if($c){ //Completed Validation, go ahead and create the feed.
					$password = genFeedPass();
				}
				if($c){ //Add entry to the database.
					$addResult = addFeedIn(
						$_REQUEST['label']
						, $_REQUEST['description']
						, $_REQUEST['idCompany']
						, $_REQUEST['required']
						, $_REQUEST['allowedFields']
						, $password
						, $_REQUEST['dedupeEmail']
						, $_REQUEST['dedupeLandline']
						, $_REQUEST['dedupeCellphone']
						, $_REQUEST['dedupeAcross']
						, $_REQUEST['filterTypeUrl']
						, $_REQUEST['filterUrl']
					);
					if(!$addResult['success']){ 
						$c = false; $result['error'] = $addResult['reason'];
					}
				}
				if($c){ //Create directory for the new live feed.	
					$path = LIVE_ROOT.$_REQUEST['label'];
					$directoryMade = mkdir($path, 0775);
					if(!$directoryMade){ 
						$c = false; $result['error'] = 'Could not create directory for new live feed. Please check '
							.'permissions and try again.';
					}
				}
				if($c){ //Create live feed processing file.
					$copyResult = copy(ADMIN_ROOT."livefeed.php", $path.FD."livefeed.php");
					if(!file_exists($path.FD."livefeed.php")){ 
						$c = false; $result['error'] = 'Could not create live feed script. Please check permissions
						and try again.';
					}
				}
				if($c){ 
					$result['status'] = 1;
					$result['error'] = 'Successfully created new feed.';
				}
			} else {			
				$result['error'] = 'Failed when editing feed.';
				if($c){ 
					$feed = getIncomingFeed($_REQUEST['idFeedIn']);
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
							$pattern = '/^[a-z][a-z1-9_]*$/';
							if(!preg_match($pattern, $_REQUEST['label'])){ 
								$c = false; $result['error'] = 'Label must start with a letter, can can contain '
								.'letters, numbers, and underscore only.';
							}
						}
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn($_REQUEST['idFeedIn'], 'label', $_REQUEST['label']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['description'] != $feed->description){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn($_REQUEST['idFeedIn'], 'description', $_REQUEST['description']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['idCompany'] != $feed->idCompany){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn($_REQUEST['idFeedIn'], 'idCompany', $_REQUEST['idCompany']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['required'] != $feed->required){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn($_REQUEST['idFeedIn'], 'required', $_REQUEST['required']);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['allowedFields'] != $feed->allowedFields){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn(
								$_REQUEST['idFeedIn'], 'allowedFields', $_REQUEST['allowedFields']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['dedupeEmail'] != $feed->dedupeEmail){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn(
								$_REQUEST['idFeedIn'], 'dedupeEmail', $_REQUEST['dedupeEmail']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['dedupeLandline'] != $feed->dedupeLandline){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn(
								$_REQUEST['idFeedIn'], 'dedupeLandline', $_REQUEST['dedupeLandline']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['dedupeCellphone'] != $feed->dedupeCellphone){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn(
								$_REQUEST['idFeedIn'], 'dedupeCellphone', $_REQUEST['dedupeCellphone']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
					if($_REQUEST['dedupeAcross'] != $feed->dedupeAcross){ 
						if($c){ //Validated, change label, change table names.
							$alterResult = alterFeedIn(
								$_REQUEST['idFeedIn'], 'dedupeAcross', $_REQUEST['dedupeAcross']
							);
							if(!$alterResult['success']){ 
								$c = false; $result['error'] = $alterResult['reason'];
							}
						}
					}
                    if($_REQUEST['filterTypeUrl'] != $feed->filterTypeUrl){
                        if($c){
                            if($_REQUEST['filterTypeUrl'] == 'null'){ $filterTypeUrl = "NULL"; }
                            else { $filterTypeUrl = $_REQUEST['filterTypeUrl']; }
                            $alterResult = alterFeedIn(
                                $_REQUEST['idFeedIn'], 'filterTypeUrl', $filterTypeUrl
                            );
                            if(!$alterResult){
                                $c = false; $result['error'] = 'Database failure, could not update incoming feed '
                                    .'parameter (filterTypeUrl)';
                            }
                        }
                    }
                    if($_REQUEST['filterUrl'] != $feed->filterUrl){
                        if($c){
                            $alterResult = alterFeedIn(
                                $_REQUEST['idFeedIn'], 'filterUrl', $_REQUEST['filterUrl']
                            );
                            if(!$alterResult){
                                $c = false; $result['error'] = 'Database failure, could not update incoming feed '
                                    .'parameter (filterUrl)';
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
		case 'exportData':
			$c = true; $result['error'] = 'Failed when trying to export data.';
			if($c){ 
				$feed = getIncomingFeed($_REQUEST['idFeedIn']);
				if($feed === false){ 
					$c = false; $result['error'] = 'Database failure - could not fetch feed information.';
				}
				if($c && !is_object($feed) && $feed == 0){ 
					$c = false; $result['error'] = 'Error - could not fetch feed. Feed does not exist.';
				}
			}
			if($c){ 
				if($_REQUEST['exportColumns'] == ''){ 
					$c = false; $result['error'] = 'Error - you need to select data columns to export.';
				}
				$exportColumns = explode(";", $_REQUEST['exportColumns']);
			}
			if($c){ 
				$exportUrlList = explode(";", $_REQUEST['exportUrlList']);
			}
			if($c){
				$settings = array(
					'columns' => $exportColumns
					, 'dateStart' => $_REQUEST['exportDateStart']
					, 'dateEnd' => $_REQUEST['exportDateEnd']
					, 'urlList' => $exportUrlList
				);
				$exportResult = exportData($feed, $settings);
				if(!$exportResult['success']){ 
					$c = false; $result['error'] = $exportResult['reason'];
				}
			}
			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully exported file.';
				$result['query'] = $exportResult['query'];
				$result['link'] = $exportResult['fileLink'];
			}
		break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
		case 'errorCount':		
			$errorCount = getErrorCount();
			if($errorCount === false){ echo "X"; } else { echo $errorCount; }
		break;
		case 'errorList':
			$errorList = getErrors();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("errorList");' >Close [X]</a>
</div>
<?php
if($errorList === false){ echo "Error fetching errors list."; } 
elseif($errorList == 0){ echo "No errors listed for today."; } 
else { 
	foreach($errorList as $error){ 
?>
<p>(<?php echo $error->stamp; ?>) [<?php echo $error->origination; ?>] : <?php echo $error->description; ?></p>
<?php
	}
}
		break;
		case 'incomingFeeds':
$incomingFeeds = getIncomingFeeds();
?>
<?php		
if($incomingFeeds === false){ 
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
} else if($incomingFeeds == 0){ 
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
} else { 
	//Go through each and compile the company list.
	$companyFeedLists = array();
	foreach($incomingFeeds as $feed){ 
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
<p>Incoming Feeds</p>
<table class='feedTableIncoming bgWhite'>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p>Company</p></td>
		<td class='fTI_feedOverview'><p>Total Feeds</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTI_options'><p>Actions</p></td>
	</tr>
<?php
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		foreach($companyFeedList as $keyFeed => $feed){ 
			$dailyCount = getDailyCount($feed->idFeedIn, date("Y-m-d"));
			if($dailyCount === false){ 
				$dailyCount = 'E';
			} elseif(empty($dailyCount)){ 
				$dailyCount = 0; 
			} 			
			$companyFeedList[$keyFeed]->dailyCount = $dailyCount;
			if($dailyCount != 'E'){
				$totalAccepted += $dailyCount;
			}
			
			$dailyCountInvalid = getDailyCountInvalid($feed->idFeedIn, date("Y-m-d"));
			if($dailyCountInvalid === false){ 
				$dailyCountInvalid = 'E';
			} elseif(empty($dailyCountInvalid)){ $dailyCountInvalid = 0; }
			$companyFeedList[$keyFeed]->dailyCountInvalid = $dailyCountInvalid;
			if($dailyCountInvalid != 'E'){
				$totalRejected += $dailyCountInvalid;
			}
		}
?>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p><?php echo $companyCache[$idCompany]->name; ?></p></td>
		<td class='fTI_feedOverview'>
			<p>
				<?php echo count($companyFeedList); ?>
			</p>
		</td>
		<td class='fTI_accepted'><p class='aRight'><?php echo $totalAccepted; ?></p></td>
		<td class='fTI_rejected'><p class='aRight'><?php echo $totalRejected; ?></p></td>
		<td class='fTI_options'>
			<p>
				<a href='#' class='nonLink'
					id='link_companyFeedList_<?php echo $idCompany; ?>'
					onclick="toggleHidden('companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='companyFeedList_<?php echo $idCompany; ?>' class='hidden'>
	<tr>
		<td class='fTI_idFeedOut'><p>ID</p></td>
		<td class='fTI_label'><p>Feed Label</p></td>
		<td class='fTI_description'><p>Description</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTI_options'><p>Options</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr>
		<td class='fTI_idFeedOut'><p><?php echo $feed->idFeedIn; ?></p></td>
		<td class='fTI_label'><p><?php echo $feed->label; ?></p></td>
		<td class='fTI_description'><p><?php echo $feed->description; ?></p></td>
		<td class='fTI_accepted'><p class='aRight'><?php echo $feed->dailyCount; ?></p></td>
		<td class='fTI_rejected'><p class='aRight'><a href="mgr_rejections.php?type=inbound&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></p></td>
		<td class='fTI_options'>
			<p>
				<a href='#' class='nonLink' 
	onclick='display("urlList", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn":"<?php echo $feed->idFeedIn; ?>"} );'
				>Show URLs</a> |
				<a href='apispec.php?idFeedIn=<?php echo $feed->idFeedIn; ?>' 
					target='_blank'
				>API Spec</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_editfeed", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>Edit Feed</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_export", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>Export Data to File</a>
			</p>
		</td>
	</tr>
	<tr><td class='hidden' id='urlList_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_listcodes_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_editfeed_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_export_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
<?php
		}
?>
	</tbody>
<?php
	}
?>
</table>
<?php
}

			
			
		break;
		case 'urlList':
$idFeedIn = $_REQUEST['options']['idFeedIn'];		
$feed = getIncomingFeed($idFeedIn);
if(isset($_REQUEST['options']['dateStart'])){ 
	if(strtotime($_REQUEST['options']['dateStart']) 
		> strtotime($_REQUEST['options']['dateEnd'])
	){ 
		$dateStart = $_REQUEST['options']['dateEnd'];
	}else { 
		$dateStart = $_REQUEST['options']['dateStart'];
	}
} else { 
	$dateStart = date("Y-m-d");
}
if(isset($_REQUEST['options']['dateEnd'])){ 
	if(strtotime($_REQUEST['options']['dateStart']) 
		> strtotime($_REQUEST['options']['dateEnd'])
	){ 
		$dateStart = $_REQUEST['options']['dateEnd'];
	}else { 
		$dateStart = $_REQUEST['options']['dateStart'];
	}
	$dateEnd = $_REQUEST['options']['dateEnd'];
} else { 
	$dateEnd = date("Y-m-d");
}
if($dateStart == $dateEnd){ $period = 'Day of '.$dateStart; } else { 
	$period = $dateStart.' to '.$dateEnd.' (inclusive).';
}
$urlBreakdown = getUrlsForPeriod($idFeedIn, $dateStart, $dateEnd);
$urlBreakdown_invalid = getUrlsForPeriodInvalid($idFeedIn, $dateStart, $dateEnd);
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("urlList", {"sub" : <?php echo $idFeedIn; ?>});' >Close [X]</a>
</div>
<hr />
<p>URL Breakdown for <?php echo $feed->label; ?> (ID: <?php echo $feed->idFeedIn; ?>) : </p>
<p>Period: <?php echo $period; ?></p>
<p>Select New Period: 
	<input type='text' name='dateStart' id='dateStart_<?php echo $idFeedIn; ?>' class='dateSelector' 
		value='<?php echo $dateStart; ?>'
	/>
	to <input type='text' name='dateEnd' id='dateEnd_<?php echo $idFeedIn; ?>' class='dateSelector' 
		value='<?php echo $dateEnd; ?>'
	/>
	<input type='button' value='Show Selected Period' 
		onclick="<?php
		?>display( <?php
			?>'urlList'<?php
			?>, { <?php
				?>'sub': <?php echo $idFeedIn; ?> <?php
				?>, 'idFeedIn': <?php echo $idFeedIn; ?> <?php
				?>, 'dateStart': $('#dateStart_<?php echo $idFeedIn; ?>').val() <?php
				?>, 'dateEnd': $('#dateEnd_<?php echo $idFeedIn; ?>').val() <?php
			?>}<?php
		?>);"
	/>
</p>
<p>URL Breakdown for accepted leads: </p>
<?php
if($urlBreakdown === false){ 
?>
<p>Database error when fetching URL list.</p>
<?php
} elseif($urlBreakdown == 0){ 
?>
<p>No URLs received for <?php echo $period; ?></p>
<?php
} else { 
?>
<table class='urlTable' cellpadding='0' cellspacing='0' border='1' style='width: 100%;'>
	<thead>
		<tr>
			<th>Base URL</th>
			<th>Full URL</th>
			<th>Quantity</th>
		</tr>
	</thead>
	<tbody>
<?php 
	foreach($urlBreakdown as $urlEntry){ 
		if(is_null($urlEntry->urlTrim)){ 
			$urlEntry->urlTrim = 'No Valid URLs';
			$urlEntry->urlFull = 'No Valid URLs';
			$urlEntry->totalQty = 0;
		}
?>
		<tr>
			<td><?php echo $urlEntry->urlTrim; ?></td>
			<td><?php echo $urlEntry->urlFull; ?></td>
			<td class='aRight'><?php echo $urlEntry->totalQty; ?></td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
<p>URL Breakdown for rejected leads: </p>
<?php
if($urlBreakdown_invalid === false){ 
?>
<p>Database error when fetching URL list.</p>
<?php
} elseif($urlBreakdown_invalid == 0){ 
?>
<p>No URLs received for <?php echo $period; ?></p>
<?php
} else { 
?>
<table class='urlTable' cellpadding='0' cellspacing='0' border='1' style='width: 100%;'>
	<thead>
		<tr>
			<th>Base URL</th>
			<th>Full URL</th>
			<th>Quantity</th>
		</tr>
	</thead>
	<tbody>
<?php 
	foreach($urlBreakdown_invalid as $urlEntry){ 
		if(is_null($urlEntry->urlTrim)){ 
			$urlEntry->urlTrim = 'No Invalid URLs';
			$urlEntry->urlFull = 'No Invalid URLs';
			$urlEntry->totalQty = 0;
		}
?>
		<tr>
			<td><?php echo $urlEntry->urlTrim; ?></td>
			<td><?php echo $urlEntry->urlFull; ?></td>
			<td class='aRight'><?php echo $urlEntry->totalQty; ?></td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<?php
}
?>
<hr />
<?php
		
		break;
		
		case 'dialog_editfeed':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$e = 'edit_'.$idFeedIn.'_'; $d = 'edit';
			$feed = getIncomingFeed($idFeedIn);
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
			$selectedRequired = explode(";", $feed->required);
			$selectedAllowedFields = explode(";", $feed->allowedFields);
		case 'dialog_newfeed':
			if(!isset($e)){ $e = 'new_'; $d = 'new'; }
			$feedProps = array('idFeedIn', 'label', 'description', 'idCompany'
				, 'dedupeEmail', 'dedupeLandline', 'dedupeCellphone', 'dedupeAcross', 'filterTypeUrl'
			);
			foreach($feedProps as $feedProp){ 
				if(isset($feed)){ 
					${"feed_".$feedProp} = $feed->$feedProp;
				}elseif(isset($_REQUEST['options'][$feedProp])){ 
					${"feed_".$feedProp} = $_REQUEST['options'][$feedProp];
				}else { 
					if(in_array($feedProp, array('dedupeEmail', 'dedupeLandline', 'dedupeCellphone'))){ 
						${"feed_".$feedProp} = '0';
					} else { 
						${"feed_".$feedProp} = '';
					}
				}
			}
            $explodableProperties = array(
                'filterUrl',
            );
            foreach($explodableProperties as $eP){
                if( !isset($_REQUEST['options'][$eP]) ){
                    if(!isset($feed->$eP)){
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

			if(!isset($selectedRequired)){ 
				$selectedRequired = array('email', 'ip', 'url', 'stamp');
			}
			if(!isset($selectedAllowedFields)){ 
				$selectedAllowedFields = $recordFields;
			}
			$companies = getCompanies();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='<?php
		?>closeContent(<?php
			?>"dialog_<?php echo $d; ?>feed<?php 
				if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
		?>);' >Close [X]</a>
</div>
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td><p>Feed Label</p></td>
		<td>
			<p>	
				<input type='hidden' name='<?php echo $e; ?>feed_idFeedIn'
					id='<?php echo $e; ?>feed_idFeedIn'
					value='<?php echo $feed_idFeedIn; ?>' 
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
		<td><p>Required Fields</p></td>
		<td>
			<p>
				<?php foreach($recordFields as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedRequired)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>			
				<?php } ?>
				<?php foreach($incomingAdditionalRequirementSettings as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedRequired)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>				
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Allowed Fields</p></td>
		<td>
			<p>
				<?php foreach($recordFields as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_allowedFields_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_allowedFields_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedAllowedFields)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Duplicate Filters</p></td>
		<td>
			<p>
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeEmail'
					id='<?php echo $e; ?>feed_dedupeEmail'
					value='1'
					<?php if($feed_dedupeEmail){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Emails
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeLandline'
					id='<?php echo $e; ?>feed_dedupeLandline'
					value='1'
					<?php if($feed_dedupeLandline){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Landline Numbers
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeCellphone'
					id='<?php echo $e; ?>feed_dedupeCellphone'
					value='1'
					<?php if($feed_dedupeCellphone){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Cellphone Numbers
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Duplicate Options</p></td>
		<td>
			<p>
				<input type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_global'
					value='global'
					<?php if($feed_dedupeAcross == 'global'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe Across entire feed
				<input type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_url'
					value='url'
					<?php if($feed_dedupeAcross == 'url'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe across same URL
				<input  type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_listcode'
					value='listcode'
					<?php if($feed_dedupeAcross == 'listcode'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe across same Listcode
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
                                        name='<?php echo $e; ?>feed_filterTypeUrl'
                                        id='<?php echo $e; ?>feed_filterTypeUrl_disabled'
                                        value='true'
                                        <?php if(
                                                empty($feed_filterTypeUrl)
                                        ){ ?>
                                        checked='checked'
                                        <?php } ?>
                                        onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').hide(); <?php
                                        ?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Do nothing with');"
                                /> Disabled<br />
                                <input type='radio'
                                        name='<?php echo $e; ?>feed_filterTypeUrl'
                                        id='<?php echo $e; ?>feed_filterTypeUrl_accept'
                                        value='true'
                                        <?php if($feed_filterTypeUrl == 'accept'){ ?>
                                        checked='checked'
                                        <?php } ?>
                                        onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').show(); <?php
                                        ?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Accept');"
                                /> Accept<br />
                                <input type='radio'
                                        name='<?php echo $e; ?>feed_filterTypeUrl'
                                        id='<?php echo $e; ?>feed_filterTypeUrl_reject'
                                        value='true'
                                        <?php if($feed_filterTypeUrl == 'reject'){ ?>
                                        checked='checked'
                                        <?php } ?>
                                        onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').show(); <?php
                                        ?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Reject');"
                                /> Reject<br />
                        </p>
                        <div id='<?php echo $e; ?>feed_toggler_filterTypeUrl'
                                style='display:<?php
                                        if(empty($feed_filterTypeUrl)){ echo "none"; }
                                        else { echo "block"; }
                                ?>;'
                        >
                                <p>The following urls:</p>
                                <p>
                                        <a href='#' class='nonLink'
                onclick='element("<?php echo $e; ?>feed_filterUrl_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Url" });'
                                        >Add New URL to <span id='<?php echo $e; ?>feed_filterUrl_descriptor'></span></a>
                                        | <a href='#' class='nonLink'
                                                onclick='element("<?php echo $e; ?>feed_filterUrl_multipleInsert"<?php
                                                ?>, "element_multifilter"<?php
                                                ?>, { "e": "<?php echo $e; ?>"<?php
                                                ?>, "type": "Url" });'
                                        >Add Multiple</a>
                                </p>
                                <div id='<?php echo $e; ?>feed_filterUrl_multipleInsert'></div>
                                <div id='<?php echo $e; ?>feed_filterUrl_container'>
                                <?php foreach($feed_filterUrl as $filterUrl){ ?>
                                        <div>
                                                <input type='text'
                                                        name='<?php echo $e; ?>feed_filterUrl[]'
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
		<td colspan='2'>
			<p class='aRight'>
		<?php if(isset($idFeedIn) && $e == 'edit_'.$idFeedIn.'_'){ ?>
				<input type='button' value='Cancel Changes'  
					onclick='<?php
						?>closeContent(<?php
							?>"dialog_<?php echo $d; ?>feed<?php 
								if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
						?>);'
				/> 
				<input type='button' value='Save Changes' 
					onclick='manageFeed("update", <?php echo $idFeedIn; ?>);'
				/>
		<?php } else { ?>
				<input type='button' value='Cancel'  
					onclick='<?php
						?>closeContent(<?php
							?>"dialog_<?php echo $d; ?>feed<?php 
								if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
						?>);'
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
		case 'dialog_export':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$feed = getIncomingFeed($idFeedIn);
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_export", {"sub": <?php echo $idFeedIn; ?>});' 
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
<p>Exporting Data from Feed (ID:<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
<input type='hidden' id='export_idFeedIn' value='<?php echo $feed->idFeedIn; ?>' />
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td colspan='2'><p class='aCenter'>Export Settings</p></td>
	</tr>
	<tr>
		<td>
			<p>Columns</p>
		</td>
		<td>
			<?php foreach($recordFields as $f){ ?>
			<input type='checkbox' 
				name='export_<?php echo $idFeedIn; ?>_column_<?php echo $f; ?>'
				id='export_<?php echo $idFeedIn; ?>_column_<?php echo $f; ?>'
				value='<?php echo $f; ?>'
			/> <?php echo $f; ?>
			<?php } ?>
		</td>
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
					name='export_<?php echo $idFeedIn; ?>_dateStart' 
					id='export_<?php echo $idFeedIn; ?>_dateStart' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d"); ?>'
				/>
				to <input type='text' 
					name='export_<?php echo $idFeedIn; ?>_dateEnd' 
					id='export_<?php echo $idFeedIn; ?>_dateEnd' 
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
				<a href='#' class='nonLink' 
	onclick='element("export_<?php echo $idFeedIn; ?>_urls", "urlField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'
				>Add URL</a>
			</p>
			<div>
				<div id='export_<?php echo $idFeedIn; ?>_urls' >
				</div>
			</div>
			</p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
				<a href='#' id='resultExport_<?php echo $idFeedIn; ?>'></a>
				<input type='button' value='Export Data' onclick='exportFile(<?php echo $idFeedIn; ?>);'/>
				<br />
				<span id='resultQuery_<?php echo $idFeedIn; ?>'></span>
			</p>
		</td>
	</tr>
</table>
<?php
			}
		break;
		case 'urlField':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
?>
<div>
	URL: <input type='text' 
		name='export_<?php echo $idFeedIn; ?>_urlList[]'
		value=''
	/> 
	<a href='#' class='nonLink' onclick='$(this).parent().remove();' >[X]</a>
</div>
<?php
		break;
		case 'dialog_listcodes':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$feed = getIncomingFeed($idFeedIn);
?>
<p>Generate New Listcode for (<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
<p>
	Select an Option:
	<select id='' name='' 
		onchange="display('dialog_listcodeManager', {'sub': <?php echo $feed->idFeedIn; ?>, 'type': $(this).val() });"
	>
		<option value='0' >Choose: </option>
		<option value='1' >Generate Listcode for Single Url</option>
		<option value='2' >Generate Individual Listcodes for Multiple Urls</option>
		<option value='3' >Generate Listcode for URL Group</option>
		<option value='4' >Browse existing listcodes</option>
	</select>
</p>
<div id='dialog_listcodeManager_<?php echo $feed->idFeedIn; ?>'>
</div>
<?php
		break;
		case 'dialog_listcodeManager':
			switch($_REQUEST['options']['type']){
				case 0:
?>
<p>Please choose an option.</p>
<?php
				break;
				case 1:
?>
<p>Individual URL Listcode</p>
<div>
	<input type='text' 
		name='<?php echo $e; ?>popset_filterUrl[]'
		value='<?php echo $filterUrl; ?>'
	/>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
				break;
				case 2:
?>
<p>Multiple Individual URL Listcodes</p>
<?php
				break;
				case 3:
?>
<p>URL Group Listcode</p>
<?php
				break;
				case 4:
?>
<p>Browse Listcodes</p>
<?php
				break;
			}			
		break;
                case 'element_filter':
                        $e = $_REQUEST['options']['e'];
                        $t = $_REQUEST['options']['type'];
?>
<div>
        <input type='text'
                name='<?php echo $e; ?>feed_filter<?php echo $t; ?>[]'
                value='<?php if(isset($_REQUEST['options']['value'])){
                        echo $_REQUEST['options']['value'];
                } ?>'
        />
        <a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
                break;
                case 'element_multifilter':
                        $e = $_REQUEST['options']['e'];
                        $t = $_REQUEST['options']['type'];
?>
<textarea name='<?php echo $e; ?>feed_filter<?php echo $t; ?>Multi'
id='<?php echo $e; ?>feed_filter<?php echo $t; ?>Multi' ></textarea>
<input type='button' value='Add Multiple Urls'
        onclick="splitMultiFilter('<?php echo $e; ?>', '<?php echo $t; ?>');"
/>
<?php
                break;

		default:
?>
<p>Requested information doesn't exist.</p>
<?php
		break;
	}
	exit;
}

$title = 'Incoming Feed Manager';
include("c_header.php");
?>
<script>
function splitMultiFilter(e, t){
    values = $('#'+e+'feed_filter'+t+'Multi').val();
    //alert(values);
    valueArray = values.match(/[^\r\n]+/g);
    for(count = 0; count < valueArray.length; count++){
        element(
            e+"feed_filter"+t+"_container"
            , "element_filter"
            , {
                "e": e
                , "type": t
                , "value": valueArray[count]
            }
        );
    }
    //alert('#'+e+'feed_filter'+t+'_multipleInsert');
    $('#'+e+'feed_filter'+t+'_multipleInsert').html("");
}

function manageFeed(action, idFeedIn){ 
	if(action == "new"){ e = "#new_feed_"; c = 'new'; } else { e = "#edit_"+idFeedIn+"_feed_"; c = 'edit'; }
	idFeedIn = $(e+'idFeedIn').val();
	label = $(e+'label').val();
	description = $(e+'description').val();
	idCompany = $(e+'idCompany').val();
	required = '';
	allowedFields = '';
	recordFields = new Array( <?php 
$comma = false; 
foreach($recordFields as $f){ 
	if($comma) echo ','; 
	echo "'".$f."'";
	$comma = true; 
} 
foreach($incomingAdditionalRequirementSettings as $f){ 
	if($comma) echo ','; 
	echo "'".$f."'";
	$comma = true; 
}
	?> );
	requiredFlag = false; allowedFieldsFlag = false;
	for(count = 0; count < recordFields.length; count++){ 
		if($(e+'required_'+recordFields[count]).is(":checked")){
			if(requiredFlag) { required += ';'; }
			required += recordFields[count];
			requiredFlag = true;
		}
		if($(e+'allowedFields_'+recordFields[count]).is(":checked")){
			if(allowedFieldsFlag) { allowedFields += ';'; }
			allowedFields += recordFields[count];
			allowedFieldsFlag = true;
		}
	}
    if($(e+'filterTypeUrl_disabled').is(":checked")){
        filterTypeUrl = 'null';
    }else if($(e+'filterTypeUrl_accept').is(":checked")){
        filterTypeUrl = 'accept';
    }else if($(e+'filterTypeUrl_reject').is(":checked")){
        filterTypeUrl = 'reject';
    }
    filterUrl = $("input[name='"+c+"_"+idFeedIn+"_feed_filterUrl\\[\\]']")
        .map(function(){return $(this).val();}).get().join(";");

	if($(e+'dedupeEmail').is(":checked")){ dedupeEmail = 1;	} else { dedupeEmail = 0; }
	if($(e+'dedupeLandline').is(":checked")){ dedupeLandline = 1;	} else { dedupeLandline = 0; }
	if($(e+'dedupeCellphone').is(":checked")){ dedupeCellphone = 1;	} else { dedupeCellphone = 0; }
	if(c == 'new'){ 
		dedupeAcross = $('input[name="'+c+'_feed_dedupeAcross"]:checked').val();
	} else { 
		dedupeAcross = $('input[name="'+c+'_'+idFeedIn+'_feed_dedupeAcross"]:checked').val();
	}
	/* alert(
		"label: "+label
		+"\n"+"description: "+description
		+"\n"+"idCompany: "+idCompany
		+"\n"+"required: "+required
		+"\n"+"allowedFields: "+allowedFields
		+"\n"+"dedupeAcross: "+dedupeAcross
	);  
	return false; */
	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "manageFeed"
			, "action" : action
			, "idFeedIn": idFeedIn
			, "label": label
			, "description": description
			, "idCompany": idCompany
			, "required": required
			, "allowedFields": allowedFields
			, "dedupeEmail": dedupeEmail
			, "dedupeLandline": dedupeLandline
			, "dedupeCellphone": dedupeCellphone
			, "dedupeAcross": dedupeAcross
			, "filterTypeUrl": filterTypeUrl
			, "filterUrl": filterUrl
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { 
			alert("JSON Failed: "+responseText); 
			if(c == 'new'){ 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);				
			} else { 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'sub' : idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);
			}
			return false; 
		}
		if(result.status == 1){ 
			if(c == 'new'){ 
				alert("Successfully added new feed.");
				closeContent('dialog_'+c+'feed');
			} else { 
				alert("Feed settings updated.");
				closeContent('dialog_'+c+'feed', {'sub' : idFeedIn} );
			}
			display('incomingFeeds', {'callbackParams': {'sub': idCompany} }, true, function(callbackParams){ 
				toggleHidden(
					'companyFeedList'
					, {
						'sub': callbackParams.sub
						, 'hiddenText':'Show Feeds'
						, 'shownText':'Close' 
					}
				);
			});
		} else { 
			alert(result.error);
			if(c == 'new'){ 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);				
			} else { 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'sub' : idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);
			}
		}
	});
}
function exportFile(idFeedIn){ 
	recordFields = new Array( <?php $comma = false; 
	foreach($recordFields as $f){ if($comma) echo ','; 
	?>'<?php echo $f; ?>'<?php $comma = true; } ?> );
	comma = false; 
	exportColumns = '';
	for(count = 0; count < recordFields.length; count++){ 
		if($('#export_'+idFeedIn+'_column_'+recordFields[count]).is(":checked")){
			if(comma) { exportColumns += ';'; }
			exportColumns += recordFields[count];
			comma = true;
		}
	}
	exportDateStart = $('#export_'+idFeedIn+'_dateStart').val();
	exportDateEnd = $('#export_'+idFeedIn+'_dateEnd').val();
	exportUrlList = $("input[name='export_"+idFeedIn+"_urlList\\[\\]']")
        .map(function(){return $(this).val();}).get().join(";");
	alert(
		"idFeedIn: "+idFeedIn
		+"\n"+"exportColumns: "+exportColumns
		+"\n"+"exportDateStart: "+exportDateStart
		+"\n"+"exportDateEnd: "+exportDateEnd
		+"\n"+"exportUrlList: "+exportUrlList
	);  
	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "exportData"
			, "idFeedIn": idFeedIn
			, "exportColumns": exportColumns
			, "exportDateStart": exportDateStart
			, "exportDateEnd": exportDateEnd
			, "exportUrlList": exportUrlList
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { 
			alert("JSON Failed: "+responseText); 
			return false; 
		}
		if(result.status == 1){ 
			$('#resultExport_'+idFeedIn).html('Download File');
			$('#resultQuery_'+idFeedIn).html(result.query);
			$('#resultExport_'+idFeedIn).attr('href', result.link);
		} else { 
			alert(result.error);
		}
	});
	$('#resultExport_'+idFeedIn).html("Processing...");
}
$(document).ready(function(){ 
	display('incomingFeeds');
});
</script>
<style>
table.urlTable th, table.urlTable td { padding: 3px; }
table.feedTable { font-size: .8em; margin-bottom: 20px; }
table.feedTable th, table.feedTable td { padding: 3px; }
</style>
<body>
<div class='mainContainer'>
	<?php include('c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls'>
			<a href='#' class='nonLink' onclick="display('dialog_newfeed');" 
			>Add New Feed</a>
		</div>
		<div id='dialogs'>
			<div id='dialog_newfeed' style='display:none;'></div>
			<div id='dialog_editfeed' style='display:none;'></div>
		</div>
		<div class='fl' style='width: 100%;'>
			<div id='incomingFeeds'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>
</body>
</html>
