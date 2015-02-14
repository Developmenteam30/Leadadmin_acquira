<?php

include( __DIR__ . "/../includes/c_config.php");

require_once( INCLUDES . 'leads.php' );

$mysqlErrorSource = 'Manager - File Import';
require_once(INCLUDES."_connx.php");
require_once(INCLUDES."f_site.php");
require_once(INCLUDES."_f_validEmail.php");
require_once(INCLUDES."processFunctions.php");

ini_set("auto_detect_line_endings", true);
set_time_limit(0);

$leads = Leads::getInstance();
$job = $leads->getPendingJob();
if( $job === null ) {
	print "No pending jobs";
	exit;
}

$feedParams = $leads->getInboundFeed( $job->idFeedIn );
if($feedParams === false){
	print 'Database failure.  Cannot load feed information.';
	exit;
} else if( 0 === $feedParams ) {
	print 'ERROR: Invalid incoming feed ID supplied';
	exit;
}

$handle = @fopen( $job->filename, "r" );
if( !$handle ) {
	$leads->updateJob( $job->jobId, array(
		'status' => 'error',
	) );
	print 'ERROR: Cannot open uploaded file for reading';
	exit;
}

print "Importing records from: {$job->filename}\n";

$allowedFields = explode(";", $feedParams->allowedFields);
$fields = unserialize( $job->fields );

$counts = array(
	'success' => 0,
	'invalid' => 0,
	'failures' => 0,
	'dupe' => 0,
);

$cnt = 1;
while( ( $raw_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {

	$data = array();

	foreach( $allowedFields as $field ) {
		if( isset( $fields['field_' . $field] ) && is_numeric( $fields['field_' . $field] ) ) {
			$col = $fields['field_' . $field];
			if( !empty( $raw_data[$col] ) ) {
				if( 'stamp' == $field ) {
					// Check to see if we're using two separate timestamp columns
					if( !empty( $fields['field_time'] ) && is_numeric( $fields['field_time'] ) ) {
						$time_col = $fields['field_time'];
						// Remove extraneous data from the date field
						if( strpos( $raw_data[$col], ' ' ) !== FALSE ) {
							list( $date, $garbage ) = explode( ' ', $raw_data[$col], 2 );
						} else {
							$date = $raw_data[$col];
						}
						$data['stamp'] = date( "Y-m-d H:i:s", strtotime( $date . ( !empty($raw_data[$time_col]) ? ' ' . $raw_data[$time_col] : '' ) ) );
					} else {
						$data['stamp'] = date( "Y-m-d H:i:s", strtotime( $raw_data[$col] ) );
					}
				} elseif( 'dob' == $field ) {
					$data['dob'] = date( "Y-m-d", strtotime( $raw_data[$col] ) );
				} else {
					$data[$field] = $raw_data[$col];
				}
			}
		}
	}

	// Fix zip codes with a missing leading zeros
	if( !empty( $data['zip'] ) ) {
		$data['zip'] = str_pad( $data['zip'], 5, '0', STR_PAD_LEFT);
	}

	if( isset( $data['email'] ) ) 
		print "{$data['email']}";
	else
		print " ";

	$result = validateIncomingData( $feedParams, $data );

	if( $result['valid'] ) {

		print " - VALID\n";

		$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $data, date('Y-m-d'), null, $job->jobId );
		if( null === $inboundId ) {
			$counts['failures']++;
		} else {
			if( LEGACY_DB ) {
				insertIncomingData( $feedParams, $data, $job->jobId );
			}
			pushIncomingData( $job->idFeedIn, $data, $inboundId );
			$counts['success']++;
		}

	} else {

		$counts['invalid']++;

		print " - ERROR\n";
		foreach($result['errors'] as $error) {
			print "\t{$error}\n";
		}

		$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $data, date('Y-m-d'), $result['errors'][0], $job->jobId );

		if( LEGACY_DB ) {
			insertIncomingData( $feedParams, $data, $job->jobId, $result['errors'][0] );
		}

	}

	print "\n";

	unset( $data );

}
fclose($handle);

if( $cnt === $job->records ) {
	$leads->updateJob( $job->jobId, array(
		'status' => 'finished',
	) );
} else {
	$leads->updateJob( $job->jobId, array(
		'status' => 'error',
	) );
}

print "FILE IMPORT COMPLETE!\n";

print "Successful: {$counts['success']}\n";
print "Duplicates: {$counts['dupe']}\n";
print "Invalid: {$counts['invalid']}\n";
print "Failures: {$counts['failures']}\n";
