<?php
require_once( "../includes/c_config.php" );
require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$recordsPerRun = 1000;
$maxThreads = 20;

function countProcesses( $idFeedOut ) {

	exec( sprintf( "ps auxwww|grep '[p]ushLeads.php %d$'", intval( $idFeedOut ) ), $output );
	return !empty( $output ) && is_array( $output ) ? sizeOf( $output ) : 0;

}

while( true ) {

	$feeds = $leads->getOutboundFeedsCron( null );

	if( !$feeds || !is_array( $feeds ) ) {
		print "Unable to get feed list\n";
		die();
	}

	print "\n====================================\n";

	foreach( $feeds as $feed ) {

		$cnt = countProcesses( $feed->idFeedOut );
		print "Feed: {$feed->idFeedOut}, Processes: {$cnt}\n";

		$threads = round( $feed->queued / $recordsPerRun );
		if( $threads < 1 ) {
			$threads = 1;
		} else if( $threads > $maxThreads ) {
			$threads = $maxThreads;
		}

		print "\tThreads: {$threads}\n";

		while( $cnt < $threads ) {
			print "\tSpawning new\n";

			exec( sprintf( 'php -f pushLeads.php %s>/dev/null 2>&1 &',
					escapeshellarg( $feed->idFeedOut )
				)
			);
			usleep( 500000 );

			$cnt++;
		}
	}

	sleep( 30 );
}
