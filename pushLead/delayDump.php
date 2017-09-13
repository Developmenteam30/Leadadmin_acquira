<?php

include( "../includes/c_config.php" );

require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'processLeads.php' );

$leads = Leads::getInstance();
$leads_export = new Leads( false );

$feeds = $leads->getOutboundFeedsDelayDump();
if( empty( $feeds ) || !is_array( $feeds ) ) {
	die( 'No delay dump feeds to process' );
}

foreach( $feeds as $feed ) {

	print "Processing outbound feed: " . $feed->idFeedOut . PHP_EOL;

	$populations = $leads->getPopulations( $feed->idFeedOut );
	if( empty( $populations ) || !is_array( $populations ) ) {
		echo "\tNo populations setup for feed" . PHP_EOL;
		continue;
	}

	foreach( $populations as $population ) {

		print "\tProcessing population: " . $population->idAssoc . PHP_EOL;

		if( empty( $population->enabled ) ) {
			echo "\t\tSkipping disabled population" . PHP_EOL;
			continue;
		}

		$feedParams = $leads->getInboundFeed( $population->idFeedIn );
		if( empty( $feedParams ) ) {
			echo "\t\tCannot find incoming feed: " . $population->idFeedIn . PHP_EOL;
			continue;
		}

		$sql = "SELECT * FROM data_inbound ";
		$sql .= "WHERE 1=1 ";
		$sql .= "AND idFeedIn = ? ";
		$sql .= "AND timestamp >= DATE_FORMAT(DATE_SUB(CONVERT_TZ(NOW(),?,?), INTERVAL ? MINUTE ),'%Y-%m-%d 00:00:00') ";
		$sql .= "AND timestamp <= DATE_FORMAT(DATE_SUB(CONVERT_TZ(NOW(),?,?), INTERVAL ? MINUTE ),'%Y-%m-%d 23:59:59') ";
		$sql .= "AND ( result IS NULL OR result != 'Email exists in our global suppression file.' )";
		$params = array();
		$params[] = $population->idFeedIn;
		$params[] = DB_TIMEZONE;
		$params[] = LOCAL_TIMEZONE;
		$params[] = $feed->delay;
		$params[] = DB_TIMEZONE;
		$params[] = LOCAL_TIMEZONE;
		$params[] = $feed->delay;

		$query = $leads_export->exportRecords( $sql, $params );

		if( empty( $query ) ) {
			echo "\t\tNo records found" . PHP_EOL;
			continue;
		}

		$cnt = 0;
		while( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {

			print "\t\tRecord: {$row['idRecord']} {$row['idFeedIn']}\n";

			if( ( $pushError = ProcessLeads::pushIncomingData( $feedParams, $row, $row['idRecord'], $feed->idFeedOut ) ) === null ) {
				echo "\t\t\tSUCCESS\n";
			} else {
				echo "\t{$pushError}\n";
			}
		}
	}
}


