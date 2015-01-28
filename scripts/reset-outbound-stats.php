<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();
$feeds = $leads->getOutboundFeeds( 0 );
foreach( $feeds as $feed ) {
	print "PROCESSING {$feed->idFeedOut}\n";
	$leads->resetOutboundStats( $feed->idFeedOut, '2015-01-26' );
	sleep(5);
}


