<?php

chdir( dirname( __FILE__ ) );

include( "../includes/c_config.php" );

require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'processLeads.php' );

$leads = Leads::getInstance();
$leads->setNetTimeouts( 3600 );
$leads_export = new Leads( false );
$leads_export->setNetTimeouts( 3600 );

$feeds = $leads->getOutboundFeedsDelayDump();
if( empty( $feeds ) || !is_array( $feeds ) ) {
	die( 'No delay dump feeds to process' );
}

foreach( $feeds as $feed ) {

	print date( 'c' ) .  " - Processing outbound feed: " . $feed->idFeedOut . PHP_EOL;

	$populations = $leads->getPopulations( $feed->idFeedOut );
	if( empty( $populations ) || !is_array( $populations ) ) {
		echo date( 'c' ) . " - \tNo populations setup for feed" . PHP_EOL;
		continue;
	}

	foreach( $populations as $population ) {

		print date( 'c' ) . " - \tProcessing population: " . $population->idAssoc . PHP_EOL;

		if( empty( $population->enabled ) ) {
			echo date( 'c' ) . " - \t\tSkipping disabled population" . PHP_EOL;
			continue;
		}

		$feedParams = $leads->getInboundFeed( $population->idFeedIn );
		if( empty( $feedParams ) ) {
			echo date( 'c' ) . " - \t\tCannot find incoming feed: " . $population->idFeedIn . PHP_EOL;
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
			echo date( 'c' ) . " - \t\tNo records found" . PHP_EOL;
			continue;
		}

		$cnt = 0;
		while( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {

			print date( 'c' ) . " - \t\tRecord: {$row['idRecord']} {$row['idFeedIn']}\n";

			if( ( $pushError = ProcessLeads::pushIncomingData( $feedParams, $row, $row['idRecord'], $feed->idFeedOut ) ) === null ) {
				echo date( 'c' ) . " - \t\t\tSUCCESS\n";
			} else {
				echo date( 'c' ) . " - \t{$pushError}\n";
			}
		}
	}
}


