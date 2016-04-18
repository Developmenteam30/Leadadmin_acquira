<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();

$details = $leads->getOutboundStatsDetail( '2015-02-09' );
foreach( $details as $detail ) {
	print "PROCESSING {$detail->idFeedOut},{$detail->url}...";
	$leads->resetOutboundStats( $detail->idFeedOut, $detail->url, '2015-02-09' );
	print "DONE\n";
	sleep(2);
}


die();


$feeds = $leads->getOutboundFeeds( 0 );
foreach( $feeds as $feed ) {
	print "PROCESSING {$feed->idFeedOut}\n";
	$leads->resetOutboundStats( $feed->idFeedOut, '2015-02-09' );
	sleep(2);
}


