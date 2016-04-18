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
<h4>Incoming Feeds (Last Updated: <?php echo date("m-d g:i:s a"); ?>) <a href="#" class="btn btn-primary btn-xs nonLink" onclick="automaticRefresh = true; autoRefresh();">Refresh</a></h4>
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
	$grandTotalAccepted = 0;
	$grandTotalRejected = 0;
	$grandTotalFeeds = 0;
?>
<table class="table table-bordered table-condensed table-striped-custom">
	<thead>
	<tr>
		<th>Company</th>
		<th>Accepted</th>
		<th>Rejected</th>
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
			$grandTotalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];
			$grandTotalRejected += $stats['rejected'];

		}
?>
	<tr class="clickable striped-master" onclick="toggleHidden('incoming_companyFeedList', {'sub':<?php echo $idCompany; ?>});">
		<td><?php echo $feed->name; ?> (<?php echo count( $companyFeedList ); ?>)</td>
		<td class="text-right"><?php echo number_format( $totalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $totalRejected, 0 ); ?></td>
	</tr>
	<tr id="incoming_companyFeedList_<?php echo $idCompany; ?>" class="hidden-custom">
		<td colspan="3">
			<table class="table table-bordered table-condensed table-striped">
<?php
		foreach($companyFeedList as $feed){
?>
	<tr>
		<td><?php echo $feed->idFeedIn; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</td>
		<td class="text-right"><?php echo number_format( $feed->dailyCount, 0 ); ?></td>
		<td class="text-right"><a href="mgr_rejections.php?type=inbound&amp;id=<?php echo urlencode($feed->idFeedIn);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo number_format( $feed->dailyCountInvalid, 0 ); ?></a></td>
		<td class="text-center"><a href="#" id="link_feedinc_<?php echo $feed->idFeedIn; ?>" onclick="display('feedinc', { 'sub':'<?php echo $feed->idFeedIn; ?>', 'idFeedIn':'<?php echo $feed->idFeedIn; ?>', 'hiddenText': 'Show URLs', 'shownText': 'Close' } );">Show URLs</a></td>
	</tr>
	<tr><td class="hidden-custom" id="feedinc_<?php echo $feed->idFeedIn; ?>" colspan="4"></td></tr>
<?php
		}
		$grandTotalFeeds += count($companyFeedList);
?>
			</table>
		</td>
	</tr>
<?php
	}
?>
	<tfoot>
	<tr>
		<td>GRAND TOTAL</td>
		<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
	</tr>
	</tfoot>
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
<?php
if( empty( $urls ) ) {
?>
<p>No URLs received today.</p>
<?php
} else {
?>
<table class="table table-bordered table-condensed">
	<tbody>
<?php
	foreach( $urls as $url ){
?>
		<tr>
			<td><?php echo $url['url']; ?></td>
			<td class="aRight"><?php echo number_format( $url['accepted'], 0 ); ?></td>
			<td class="aRight"><?php echo number_format( $url['rejected'], 0 ); ?></td>
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
<h4>Outgoing Feeds (Last Updated: <?php echo date("m-d g:i:s a"); ?>) <a href="#" class="btn btn-primary btn-xs nonLink" onclick="automaticRefresh = true; autoRefresh();">Refresh</a></h4>
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

	$grandTotalAccepted = 0;
	$grandTotalRejected = 0;
	$grandTotalQueued = 0;
	$grandTotalFeeds = 0;
?>
<table class="table table-bordered table-condensed table-striped-custom">
	<thead>
	<tr>
		<th>Company</th>
		<th>Accepted</th>
		<th>Rejected</th>
		<th>Queued</th>
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
			$grandTotalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];
			$grandTotalRejected += $stats['rejected'];

			$companyFeedList[$keyFeed]->dailyCountQueued = $feed->queued;
			$totalQueued += $feed->queued;
			$grandTotalQueued += $feed->queued;
		}
		$grandTotalFeeds += count($companyFeedList);
?>
	<tr class="clickable striped-master" onclick="toggleHidden('outgoing_companyFeedList', {'sub':<?php echo $idCompany; ?>});">
		<td><?php echo $feed->name; ?> (<?php echo count( $companyFeedList ); ?>)</td>
		<td class="text-right"><?php echo number_format( $totalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $totalRejected, 0 ); ?></td>
<?php
	if( $totalQueued > 5000 ) {
		$bg = 'bg-danger';
	} else if( $totalQueued > 1000 ) {
		$bg = 'bg-warning';
	} else {
		$bg = 'bg-success';
	}
?>
		<td class="text-right <?php print $bg; ?>"><?php echo number_format( $totalQueued, 0 ); ?></td>
	</tr>
	<tr id="outgoing_companyFeedList_<?php echo $idCompany; ?>" class="hidden-custom">
		<td colspan="4">
			<table class="table table-bordered table-condensed table-striped">
<?php
		foreach($companyFeedList as $feed){
?>
	<tr>
		<td><?php echo $feed->idFeedOut; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</td>
		<td><?php echo number_format( $feed->dailyCount, 0 ); ?></td>
		<td><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode($feed->idFeedOut);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo number_format( $feed->dailyCountInvalid, 0 ); ?></a></td>
		<td><?php echo number_format( $feed->dailyCountQueued, 0 ); ?></td>
	</tr>
<?php
		}
?>
			</table>
		</td>
	</tr>
<?php
	}
?>
	<tfoot>
	<tr>
		<td>GRAND TOTAL</td>
		<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalQueued, 0 ); ?></td>
	</tr>
	</tfoot>
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
<body>
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

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">
	<div class="row">
		<div class="col-md-6">
			<div id="incomingFeeds"></div>
		</div>
		<div class="col-md-6">
			<div id="outgoingFeeds"></div>
		</div>
	</div>
</div>

</body>
</html>
