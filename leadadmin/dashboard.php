<?php 
//ADMIN_ROOT/loginCheck.php
//Version 1.0
//ES20130706 Version 1.0: Admin login script.
session_start();
$mysqlErrorSource = 'Dashboard';
include("../c_config.php");
$forceMysqlLogFile = SITE_ROOT."error".FD."log_dashboard"; 
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.

function storeFeedOutgoingAttribute($idFeedOut, $attribute, $value){ 
	dbCon("insertUpdate");
	$updateFeed = "UPDATE `".DATABASE_NAME."`.`feedout` "
		."SET `".$attribute."` = '".$value."' "
		."WHERE `idFeedOut` = '".$idFeedOut."';";
	$doupdateFeed = dbQry($updateFeed, 'Updating feed attribute', true);
	if($doupdateFeed === false){ return false; }
	else { return true; }
}

function getUrlsForDay($idFeedIn, $date){ 
	dbCon();
	$getUrls = "SELECT * FROM `".DATABASE_NAME."`.`urlcount` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` = '".$date."' "
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

function getUrlsForDayInvalid($idFeedIn, $date){ 
	dbCon();
	$getUrls = "SELECT * FROM `".DATABASE_NAME."`.`urlcount_invalid` "
		."WHERE "
			."`idFeedIn` = '".$idFeedIn."' "
			."AND `stamp` = '".$date."' "
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

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
		case 'incomingFeeds':
$incomingFeeds = getIncomingFeeds();
?>
<p>
	Incoming Feeds (Last Updated: <?php echo date("m-d g:i:s a"); ?>)
	<a href='#' class='nonLink' onclick='automaticRefresh = true; autoRefresh();' >Refresh</a>
</p>
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
					onclick="toggleHidden('incoming_companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='incoming_companyFeedList_<?php echo $idCompany; ?>' class='hidden'>
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
	onclick='display("feedinc", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn":"<?php echo $feed->idFeedIn; ?>"} );'
				>Show URLs</a>
			</p>
		</td>
	</tr>
	<tr><td class='hidden' id='feedinc_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
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
		case 'feedinc':
		
		
$idFeedIn = $_REQUEST['options']['sub'];		
$feed = getIncomingFeed($idFeedIn);
$date = date("Y-m-d");
$urlBreakdown = getUrlsForDay($idFeedIn, $date);
$urlBreakdown_invalid = getUrlsForDayInvalid($idFeedIn, $date);
?>
<hr />
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("feedinc", { "sub":"<?php echo $idFeedIn; ?>"});' 
	>Close [X]</a>
</div>
<p>URL Breakdown for <?php echo $feed->label; ?> (ID: <?php echo $feed->idFeedIn; ?>) : </p>
<?php
if($urlBreakdown === false){ 
?>
<p>Database error when fetching URL list.</p>
<?php
} elseif($urlBreakdown == 0){ 
?>
<p>No URLs received for <?php echo $date; ?>.</p>
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
?>
		<tr>
			<td><?php echo $urlEntry->urlTrim; ?></td>
			<td><?php echo $urlEntry->urlFull; ?></td>
			<td class='aRight'><?php echo $urlEntry->quantity; ?></td>
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
<p>No URLs received for <?php echo $date; ?>.</p>
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
?>
		<tr>
			<td><?php echo $urlEntry->urlTrim; ?></td>
			<td><?php echo $urlEntry->urlFull; ?></td>
			<td class='aRight'><?php echo $urlEntry->quantity; ?></td>
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
		case 'outgoingFeeds':
		
$outgoingFeeds = getOutgoingFeeds('active');
?>
<p>
	Outgoing Feeds (Last Updated: <?php echo date("m-d g:i:s a"); ?>)
	<a href='#' class='nonLink' onclick='automaticRefresh = true; autoRefresh();' >Refresh</a>
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
<table class='feedTableOutgoing bgWhite'>
	<tr class='fTORow fTO_Row bgGray' style='width: 100%;'>
		<td class='fTO_companyName' colspan='2'><p>Company</p></td>
		<td class='fTO_feedOverview'><p>Total Feeds</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTO_options'><p>Options</p></td>
	</tr>
<?php
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		$totalActive = 0;
		foreach($companyFeedList as $keyFeed => $feed){ 
			$companyFeedList[$keyFeed]->statusFeed = ($feed->enabled)?'Processable':'Deactivated';
			if($feed->enabled) { $totalActive++; }
			$companyFeedList[$keyFeed]->statusCron = ($feed->cron)?'Running':'Paused';
			$companyFeedList[$keyFeed]->statusPop = getPopulationStatus($feed->idFeedOut);
			
			$companyFeedList[$keyFeed]->accepted = getCount($feed->idFeedOut, 'win');
			if($companyFeedList[$keyFeed]->accepted === false){ $companyFeedList[$keyFeed]->accepted = 'Error'; }
			elseif(is_null($companyFeedList[$keyFeed]->accepted)){ $companyFeedList[$keyFeed]->accepted = 0; }
			else { $totalAccepted += $companyFeedList[$keyFeed]->accepted; }
			
			$companyFeedList[$keyFeed]->rejected = getCount($feed->idFeedOut, 'fail');
			if($companyFeedList[$keyFeed]->rejected === false){ $companyFeedList[$keyFeed]->rejected = 'Error'; }
			elseif(is_null($companyFeedList[$keyFeed]->rejected)){ $companyFeedList[$keyFeed]->rejected = 0; }
			else { $totalRejected += $companyFeedList[$keyFeed]->rejected; }
		}
?>
	<tr class='fTORow fTO_Row bgGray'>
		<td class='fTO_companyName' colspan='2'><p><?php echo $companyCache[$idCompany]->name; ?></p></td>
		<td class='fTO_feedOverview'>
			<p>
				<?php echo count($companyFeedList); ?> (<?php echo $totalActive; ?> Active)
			</p>
		</td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $totalAccepted; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><?php echo $totalRejected; ?></p></td>
		<td class='fTO_options'>
			<p>
				<a href='#' class='nonLink'
					id='link_companyFeedList_<?php echo $idCompany; ?>'
					onclick="toggleHidden('outgoing_companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='outgoing_companyFeedList_<?php echo $idCompany; ?>' class='fTORow fTO_Row hidden'>
	<tr>
		<td class='fTO_idFeedOut'><p>ID</p></td>
		<td class='fTO_label'><p>Feed Label</p></td>
		<td class='fTO_description'><p>Description</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTO_options'><p>Notes</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr class='fTORow fTO_Row'>
		<td class='fTO_idFeedOut'><p><?php echo $feed->idFeedOut; ?></p></td>
		<td class='fTO_label'><p><?php echo $feed->label; ?></p></td>
		<td class='fTO_description'><p><?php echo $feed->description; ?></p></td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $feed->accepted; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><a href="mgr_rejections.php?type=outbound&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->rejected; ?></a></p></td>
		<td class='fTO_options'>
			<p>&nbsp;</p>
		</td>
	</tr>
	<tr><td class='hidden' id='feedout_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_editpopulation_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
	<tr><td class='hidden' id='dialog_editfeedout_<?php echo $feed->idFeedOut; ?>' colspan='9'></td></tr>
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
		case 'feedout':
		
		
$idFeedOut = $_REQUEST['options']['sub'];	
$feed = getOutgoingFeed($idFeedOut);
$populationSettings = getPopulationSettings($idFeedOut);	
$cacheFeedIn = array();
?>
<hr />
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("feedout", { "sub":"<?php echo $idFeedOut; ?>"});' 
	>Close [X]</a>
</div>
<p>Feed Details for <?php echo $feed->label; ?> (ID: <?php echo $feed->idFeedOut; ?>)</p>
<p>Description - <?php echo $feed->description; ?></p>
<p>Population</p>
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
<p>Posting Instructions</p>
<p>
	Posting Type: <?php echo $feed->feedType; ?><br />
	Posting URL: <?php echo $feed->postUrl; ?><br />
	Static Fields: <?php echo $feed->staticFields; ?><br />
	Mapped Fields: <?php echo $feed->varFields; ?><br />
	Mapping: <?php echo $feed->fieldMap; ?><br />
</p>
<hr />
<?php
		
		
		break;
		case 'errorCount':
		
		
$errorCount = getErrorCount();
if($errorCount === false){ 
?>
X
<?php
} else { 
?>
<?php echo $errorCount; ?>
<?php
}


		break;
		case 'errorList':
		
		
$errorList = getErrors();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("errorList");' >Close [X]</a>
</div>
<?php
if($errorList === false){ 
?>
Error fetching errors list.
<?php
} elseif($errorList == 0){
?>
No errors listed for today.
<?php 
} else { 
	foreach($errorList as $error){ 
?>
<p>(<?php echo $error->stamp; ?>) [<?php echo $error->origination; ?>] : <?php echo $error->description; ?></p>
<?php
	}
}
		
		
		break;
	}
	exit;
}
$title = 'Dashboard';
include("c_header.php");
?>
<script>
var automaticRefresh = true;
var refreshTimeout;

$(document).ready(function(){ 
	display('incomingFeeds');
	display('outgoingFeeds');
	autoRefresh(automaticRefresh);
});

function autoRefresh(){ 
	clearTimeout(refreshTimeout);
	if(automaticRefresh){ 
		console.log('Refreshing Information.');
		display('incomingFeeds');
		display('outgoingFeeds');
		display('errorCount');
	} 
	refreshTimeout = setTimeout(function(){ autoRefresh(); }, 120000);
}
</script>
<style>
table.urlTable th, table.urlTable td { padding: 3px; }
table.feedTable th, table.feedTable td { padding: 3px; }
table.populationTable { margin-bottom: 20px; }
table.populationTable th, table.populationTable td { padding: 3px; }
div.chartLabel { float: left; width: 100px; }
div.chartValue { float: left; width: 75px; }
</style>

<body>

<div class='mainContainer'>
	<?php include('c_nav.php'); ?>
	<div class='fl dashboardIncoming'>
		<div id='incomingFeeds'></div>
	</div>
	<div class='fl dashboardOutgoing'>
		<div id='outgoingFeeds'></div>
	</div>
	<div class='clr'></div>
</div>

</body>
</html>
