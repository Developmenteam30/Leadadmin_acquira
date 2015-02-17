<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch($_REQUEST['a']){
	}

	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
		case 'incomingFeeds':
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
	$companyFeedLists = array();
	foreach($incomingFeeds as $feed){
		//Add company to the cache list of companies.
		if(!isset($companyCache[$feed->idCompany])){
			$companyCache[$feed->idCompany] = $feed->name;
			$companyFeedLists[$feed->idCompany] = array();
		}

		//Add feed to list of feeds for the specified company.
		$companyFeedLists[$feed->idCompany][] = $feed;
	}
?>
<table class='standard'>
	<thead>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p>Company</p></td>
		<td class='fTI_feedOverview'><p>Total Feeds</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTI_options'><p>Actions</p></td>
	</tr>
	</thead>
<?php
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		
		foreach($companyFeedList as $keyFeed => $feed){

			$stats = $leads->getInboundStats( $feed->idFeedIn );

			$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
			$totalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];

		}
?>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p><?php echo $feed->name; ?></p></td>
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
		<td colspan="3" class='fTI_description'><p>ID / Label</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTI_options'><p>Options</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr>
		<td colspan="3" class='fTI_description'><p><?php echo $feed->idFeedIn; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</p></td>
		<td class='fTI_accepted'><p class='aRight'><?php echo $feed->dailyCount; ?></p></td>
		<td class='fTI_rejected'><p class='aRight'><a href="mgr_rejections.php?type=inbound&amp;id=<?php echo urlencode($feed->idFeedIn);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></p></td>
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
		
$idFeedIn = intval( $_REQUEST['options']['sub'] );

if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
		die( 'Sorry, you do not have access to this feed' );
	}
}

$urls = $leads->getInboundURLStats( $idFeedIn );
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("feedinc", { "sub":"<?php echo $idFeedIn; ?>"});' >Close [X]</a>
</div>
<p>URL Breakdown</p>
<?php
if( empty( $urls ) ) {
?>
<p>No URLs received today.</p>
<?php
} else { 
?>
<table class='urlTable' cellpadding='0' cellspacing='0' border='1' style='width: 100%;'>
	<thead>
		<tr>
			<th>URL</th>
			<th>Accepted</th>
			<th>Rejected</th>
		</tr>
	</thead>
	<tbody>
<?php 
	foreach( $urls as $url ){ 
?>
		<tr>
			<td><?php echo $url['url']; ?></td>
			<td class="aRight"><?php echo $url['accepted']; ?></td>
			<td class="aRight"><?php echo $url['rejected']; ?></td>
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
		case 'outgoingFeeds':
			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$outgoingFeeds = $leads->getOutboundFeeds( null, 'active' );
			} else {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				$outgoingFeeds = $leads->getOutboundFeeds( $idCompany, 'active' );
			}
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
	$companyFeedLists = array();
	foreach($outgoingFeeds as $feed){
		//Add company to the cache list of companies.
		if(!isset($companyCache[$feed->idCompany])){
			$companyCache[$feed->idCompany] = $feed->name;
			$companyFeedLists[$feed->idCompany] = array();
		}

		//Add feed to list of feeds for the specified company.
		$companyFeedLists[$feed->idCompany][] = $feed;
	}
?>
<table class='standard'>
	<thead>
	<tr class='fTORow fTO_Row bgGray' style='width: 100%;'>
		<td class='fTO_companyName' colspan='2'><p>Company</p></td>
		<td class='fTO_feedOverview'><p>Total Feeds</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Total Queued</p></td>
		<td class='fTO_options'><p>Options</p></td>
	</tr>
	</thead>
<?php
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		$totalActive = 0;
		$totalQueued = 0;

		foreach($companyFeedList as $keyFeed => $feed){ 

			$stats = $leads->getOutboundStats( $feed->idFeedOut );

			$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
			$totalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];

			$companyFeedList[$keyFeed]->dailyCountQueued = $feed->queued;
			$totalQueued += $feed->queued;

			if($feed->enabled) { $totalActive++; }
		}
?>
	<tr class='fTORow fTO_Row bgGray'>
		<td class='fTO_companyName' colspan='2'><p><?php echo $feed->name; ?></p></td>
		<td class='fTO_feedOverview'>
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
					onclick="toggleHidden('outgoing_companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='outgoing_companyFeedList_<?php echo $idCompany; ?>' class='fTORow fTO_Row hidden'>
	<tr>
		<td colspan="3" class='fTO_description'><p>ID / Label</p></td>
		<td class='fTO_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTO_rejected'><p class='aCenter'>Queued</p></td>
		<td class='fTO_options'><p>Notes</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr class='fTORow fTO_Row'>
		<td colspan="3" class='fTI_description'><p><?php echo $feed->idFeedOut; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</p></td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $feed->dailyCount; ?></p></td>
		<td class='fTO_rejected'><p class='aRight'><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode($feed->idFeedOut);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></p></td>
		<td class='fTO_accepted'><p class='aRight'><?php echo $feed->dailyCountQueued; ?></p></td>
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

		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

	}
	exit;
}
$title = 'Dashboard';
include(INCLUDES."c_header.php");
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
	<?php include(INCLUDES.'c_nav.php'); ?>
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
