<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();

$month = new DateTime( '2014-06-01' );

print "Deleting old errorLog entries: ";
print $leads->archiveErrors() . PHP_EOL;

print "Archiving old inbound entries ...\n";

if( ( $feeds = $leads->getInboundFeeds() ) !== null ) {
	foreach( $feeds as $feed ) {
		print "\tdata_inbound: " . $feed->idFeedIn . " - ";
		print $leads->archiveInbound( $feed->idFeedIn, $month ) . PHP_EOL;
	}
}

/*
if( ( $tables = $leads->getLegacyInboundTables() ) !== null ) {
	foreach( $tables as $table ) {
		print "\t" . $table[0] . ": ";
		print $leads->archiveLegacyInbound( $table[0] ) . PHP_EOL;
	}
}
*/

print "Deleting old outgoing invalid entries ...\n";

if( ( $tables = $leads->getOutboundTables() ) !== null ) {
	foreach( $tables as $table ) {
/*
		print "\tidFeedOut " . $table[2] . ": ";
		print $leads->archiveOutbound( $table[2], $table[1] ) . PHP_EOL;
*/
		print "\t" . $table[0] . ": ";
		print $leads->archiveLegacyOutbound( $table[0], $table[1] ) . PHP_EOL;
	}
}
