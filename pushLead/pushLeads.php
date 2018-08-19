<?php

if( extension_loaded( 'newrelic' ) ) {
	newrelic_set_appname( 'Qatalyst Scripts' );
}

if( extension_loaded( 'newrelic' ) ) {
	newrelic_ignore_transaction();
}

//pcntl_fork();
set_time_limit( 0 );

if( empty( $argv[1] ) ) {
	print "No feed id specified\n";
	die();
}

chdir( dirname( __FILE__ ) );

function signalHandler( $signal ) {
	global $running;
	// Tell the main loop to stop running so we can exit gracefully
	$running = false;
}

$running = true;
$debug = true;
$idFeedOut = $argv[1];

require_once( "_f_curl.php" ); //Easy to use curl function
require_once( "../includes/c_config.php" );
require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'processLeads.php' );
$leads = Leads::getInstance();

$pidFile = sprintf( '/tmp/pushlead-%d-%d', intval( $idFeedOut ), getmypid() );
$fh = fopen( $pidFile, 'w' );
if( !$fh ) {
	print "Unable to write PID file\n";
	die();
}
fwrite( $fh, $idFeedOut );
fclose( $fh );

declare( ticks = 1 );

pcntl_signal( SIGTERM, 'signalHandler' );// Termination ('kill' was called)
pcntl_signal( SIGHUP, 'signalHandler' ); // Terminal log-out
pcntl_signal( SIGINT, 'signalHandler' ); // Interrupted (Ctrl-C is pressed)

$feedOut = $leads->getOutboundFeed( $idFeedOut );

if( empty( $feedOut ) ) {
	print "Feed {$idFeedOut} does not exist!\n";
	@unlink( $pidFile );
	die();
}

if( empty( $feedOut->cron ) ) {
	print "Outbound processing is paused for feed {$feedOut->idFeedOut}\n";
	@unlink( $pidFile );
	die();
}

// Ensure we are within scheduled time frame
if( !ProcessLeads::isWithinProcessingSchedule( $feedOut ) ) {
	print "Skipping because not within processing schedule";
	@unlink( $pidFile );
	die();
}

$empties = 0;

while( $running ) {

	$rows = $leads->getOutboundQueueRecord( $feedOut->idFeedOut );

	if( empty( $rows ) || !is_array( $rows ) ) {
		print "Empty response returned\n";
		$empties++;
		if( $empties > 10 ) {
			print "Too many empty responses ... dying off now\n";
			$running = false;
		}
		sleep( 5 );
		continue;
	}

	$empties = 0;

	print "Found " . sizeOf( $rows ) . " pending records in the queue\n\n";

	foreach( $rows as $row ) {

		if( extension_loaded( 'newrelic' ) ) {
			newrelic_start_transaction( 'Qatalyst Scripts' );
		}

		print "\n\n\nRecord: {$row->idRecord} (" . getmypid() . ")\n";

		ProcessLeads::pushOutboundData( $feedOut, $row );

		if( extension_loaded( 'newrelic' ) ) {
			newrelic_end_transaction();
		}

	}
}

print "Finished!\n";

@unlink( $pidFile );
