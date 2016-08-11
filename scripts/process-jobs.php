<?php

if( extension_loaded( 'newrelic' ) ) {
	newrelic_set_appname( 'Qatalyst Scripts' );
}

include( __DIR__ . "/../includes/c_config.php");

require_once( INCLUDES . 'leads.php' );

$mysqlErrorSource = 'Process Jobs';
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

if( 'clear-outbound-queue' === $job->type ) {

	$fields = unserialize( $job->fields );
	$status = 'Unknown error.';

	if( empty( $job->destination ) || empty( $fields['label'] ) ) {

		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Missing required fields',
		) );
		$status = 'ERROR: Missing required fields.';

	} else {

		print "Clearing outbound queue for: {$job->destination} {$fields['label']}\n";

		$cnt = $leads->clearOutboundQueue( $job->destination, $fields['label'] );
		if( $cnt === null ) {
			$leads->updateJob( $job->jobId, array(
				'status' => 'error',
				'message' => 'Database error while clearing the outbound queue',
			) );
			$status = 'Database error clearing the outbound queue';
		} else {
			$leads->updateJob( $job->jobId, array(
				'status' => 'finished',
			) );
			$status = "Successful";
		}

	}

	$body  = "Job Results\r\n";
	$body .= "\r\n";
	$body .= "Job ID: {$job->jobId}\r\n";
	$body .= "Job Type: clear-outbound-queue\r\n";
	$body .= "\r\n";
	$body .= "Feed ID: {$job->destination}\r\n";
	$body .= "Feed Label: {$fields['label']}\r\n";
	$body .= "\r\n";
	$body .= "Job Status: {$status}\r\n";
	if( $cnt !== null ) {
		$body .= "Total Records: {$cnt}\r\n";
	}
	$body .= "\r\n";

	$from = 'lmsalerts@'.SITE_URL;
	$fromName = CONFIG_COMPANY_NAME;
	$to = MANAGER_EMAIL;
	$subject = 'Job Results - Clear Outbound Queue';
	$header = "From:" . $fromName . " <" . $from . ">\n";
	$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );

} else if( 'export-incoming' === $job->type ) {

	$fields = unserialize( $job->fields );
	$status = 'Unknown error.';

	if( empty( $job->destination ) || empty( $fields['columns'] ) ) {

		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Missing required fields',
		) );
		$status = 'ERROR: Missing required fields.';

	} else {

		print "Exporting incoming records for: {$job->destination}\n";

		$result = $leads->exportInboundRecords( $job->destination, $fields );

		if( $result['success'] !== true ) {

			$leads->updateJob( $job->jobId, array(
				'status' => 'error',
				'message' => 'Database error while exporting records',
			) );
			$status = 'Database error while exporting records';

		} else {

			$leads->updateJob( $job->jobId, array(
				'status' => 'finished',
				'records' => $result['cnt'],
				'filename' => $result['fileLink'],
				'message' => null,
			) );
			$status = "Successful";
		}

	}

	$body  = "Job Results\r\n";
	$body .= "\r\n";
	$body .= "Job ID: {$job->jobId}\r\n";
	$body .= "Job Type: export-incoming\r\n";
	$body .= "\r\n";
	$body .= "Feed ID: {$job->destination}\r\n";
	$body .= "Feed Label: {$fields['label']}\r\n";
	$body .= "\r\n";
	$body .= "Job Status: {$status}\r\n";
	if( isset( $result['cnt'] ) ) {
		$body .= "Total Records: {$result['cnt']}\r\n";
	}
	if( !empty( $result['cnt'] ) && !empty( $result['fileLink'] ) ) {
		$body .= sprintf( "\r\nDownload Link: https://www.%s/leadadmin/%s\r\n",
			SITE_URL,
			$result['fileLink']
		);
	}
	$body .= "\r\n";

	$from = 'lmsalerts@'.SITE_URL;
	$fromName = CONFIG_COMPANY_NAME;
	$to = MANAGER_EMAIL;
	$subject = 'Job Results - Export Incoming Data';
	$header = "From:" . $fromName . " <" . $from . ">\n";
	$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );

} else if( 'feedinc' === $job->type ) {

	$handle = @fopen( $job->filename, "r" );
	if( !$handle ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Cannot open uploaded file for reading',
		) );
		print 'ERROR: Cannot open uploaded file for reading';
		exit;
	}

	print "Importing legacy records from: {$job->filename}\n";

	$feedParams = $leads->getInboundFeed( $job->destination );
	if( empty( $feedParams ) ) {
		print 'ERROR: Invalid incoming feed ID supplied';
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Invalid incoming feed ID supplied',
		) );
		exit;
	}

	$allowedFields = explode(";", $feedParams->allowedFields);
	$fields = unserialize( $job->fields );

	$counts = array(
		'success' => 0,
		'invalid' => 0,
		'failures' => 0,
		'dupe' => 0,
	);

	$cnt = 0;
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

		if( isset( $data['email'] ) ) {
			print "{$data['email']}";
		} else {
			print " ";
		}

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
				pushIncomingData( $feedParams->idFeedIn, $data, $inboundId );
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

		$cnt++;
		unset( $data );

	}
	fclose($handle);

	if( $cnt == intval( $job->records ) ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'finished',
		) );
	} else {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Record count does not match',
		) );
	}

	print "FILE IMPORT COMPLETE!\n";

	print "Successful: {$counts['success']}\n";
	print "Duplicates: {$counts['dupe']}\n";
	print "Invalid: {$counts['invalid']}\n";
	print "Failures: {$counts['failures']}\n";

} else if( 'suppression' === $job->type ) {

	$handle = @fopen( $job->filename, "r" );
	if( !$handle ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Cannot open uploaded file for reading',
		) );
		print 'ERROR: Cannot open uploaded file for reading';
		exit;
	}

	print "Importing suppression records from: {$job->filename}\n";

	$fields = unserialize( $job->fields );

	if( empty( $fields['list'] ) ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'No list specified',
		) );
		exit;
	}

	$lists = array();
	if( 'multiple' == $fields['list'] ) {
		foreach( $fields as $key => $val ) {
			if( strpos( $key,'suppress_multiselect_' ) !== FALSE && isset( $val ) ) {
				$lists[] = intval( $val );
			}
		}
	} else if( 'global' == $fields['list'] ) {
		$lists[] = 0;
	} else {
		$lists[] = intval( $fields['list'] );
	}

	if( sizeOf( $lists ) == 0 ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'No list specified',
		) );
		exit;
	}

	$counts = array(
		'success' => 0,
		'invalid' => 0,
		'failures' => 0,
		'dupe' => 0,
	);

	$cnt = 0;
	while( ( $raw_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {

		$raw_data = trim ( $raw_data[0] );

		if( strpos( $raw_data, '@' ) !== FALSE && !filter_var( $raw_data, FILTER_VALIDATE_EMAIL ) ) {
			$counts['invalid']++;
		} else {
			foreach( $lists as $list ) {
				$result = $leads->addSuppression( $list, $raw_data );
				if( null === $result ) {
					$counts['dupe']++;
				} else if( false === $result ) {
					$counts['failures']++;
				} else {
					$counts['success']++;
				}
			}
		}

		$cnt++;
	}
	fclose($handle);

	if( $cnt == intval( $job->records ) ) {
		$leads->updateJob( $job->jobId, array(
			'status' => 'finished',
		) );
	} else {
		$leads->updateJob( $job->jobId, array(
			'status' => 'error',
			'message' => 'Record count does not match',
		) );
	}

	print "FILE IMPORT COMPLETE!\n";

	print "Successful: {$counts['success']}\n";
	print "Duplicates: {$counts['dupe']}\n";
	print "Invalid: {$counts['invalid']}\n";
	print "Failures: {$counts['failures']}\n";

	$body  = "Job Results\r\n";
	$body .= "\r\n";
	$body .= "Job ID: {$job->jobId}\r\n";
	$body .= "Job Type: suppression\r\n";
	$body .= "\r\n";
	$body .= "Total Records: {$cnt}\r\n";
	$body .= "\r\n";
	$body .= "Successful: {$counts['success']}\r\n";
	$body .= "Duplicates: {$counts['dupe']}\r\n";
	$body .= "Invalid: {$counts['invalid']}\r\n";
	$body .= "Failures: {$counts['failures']}\r\n";
	$body .= "\r\n";

	$from = 'lmsalerts@'.SITE_URL;
	$fromName = CONFIG_COMPANY_NAME;
	$to = MANAGER_EMAIL;
	$subject = 'Job Results - Suppression Import';
	$header = "From:" . $fromName . " <" . $from . ">\n";
    $header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );

} else {

	$leads->updateJob( $job->jobId, array(
		'status' => 'error',
		'message' => 'Unknown job type',
	) );

}
