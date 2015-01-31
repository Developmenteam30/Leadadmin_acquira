<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );

$mysqlErrorSource = 'Manager - File Import';
require_once(INCLUDES."_connx.php");
require_once(INCLUDES."f_site.php");
require_once(INCLUDES."_f_validEmail.php");
require_once(INCLUDES."processFunctions.php");

ini_set("auto_detect_line_endings", true);
set_time_limit(0);

if( empty( $_REQUEST['idFeedIn'] )) {
	print '<p class="error">ERROR: No incoming feed ID supplied</p>';
	exit;
}

$idFeedIn = !empty( $_REQUEST['idFeedIn'] ) ? $_REQUEST['idFeedIn'] : 0;
$jobId = time();
$leads = Leads::getInstance();

if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
		die( 'Sorry, you do not have access to this feed.' );
	}
}

$feedParams = getFeedIn ( $idFeedIn );
if($feedParams === false){
	print '<p class="error">Database failure.  Cannot load feed information.</p>';
	logError(
		'Feed '.$idFeedIn
, 'Database failure when attempting to load feed parameters. Check MySQL log file.'
, true
		);

	exit;
} else if( 0 === $feedParams ) {
	print '<p class="error">ERROR: Invalid incoming feed ID supplied</p>';
	exit;
}

if( empty( $_FILES['import_file']['tmp_name'] ) ) {
	print '<p class="error">ERROR: You did not select a file to upload</p>';
	exit;
}

if( $_FILES['import_file']['size'] > MAX_UPLOAD_SIZE ) {
	print '<p class="error">ERROR: File size cannot exceed ' . (MAX_UPLOAD_SIZE / 1024000) . 'MB</p>';
	exit;
}

if( !empty( $_FILES['import_file']['error'] ) ) {
	print '<p class="error">ERROR: Upload error (' .  $_FILES['import_file']['error'] . ')</p>';
	exit;
}

if( !is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
	print '<p class="error">ERROR: Possible file upload attack!</p>';
	exit;
}

$counts = array(
	'success' => 0,
	'invalid' => 0,
	'failures' => 0,
	'dupe' => 0,
);

$handle = @fopen( $_FILES['import_file']['tmp_name'], "r" );
if( !$handle ) {
	print '<p class="error">ERROR: Cannot open uploaded file for reading</p>';
	exit;
}

print "<p>Importing records from: <strong>{$_FILES['import_file']['name']}</strong></p>\n";

$allowedFields = explode(";", $feedParams->allowedFields);

$cnt = 1;
while( ( $raw_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {

	$data = array();

	foreach( $allowedFields as $field ) {
		if( isset( $_REQUEST['field_' . $field] ) && is_numeric( $_REQUEST['field_' . $field] ) ) {
			$col = $_REQUEST['field_' . $field];
			if( !empty( $raw_data[$col] ) ) {
				if( 'stamp' == $field ) {
					// Check to see if we're using two separate timestamp columns
					if( !empty( $_REQUEST['field_time'] ) && is_numeric( $_REQUEST['field_time'] ) ) {
						$time_col = $_REQUEST['field_time'];
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

		$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $data, date('Y-m-d'), null, $jobId );
		if( null === $inboundId ) {
			$counts['failures']++;
		} else {
			if( LEGACY_DB ) {
				insertIncomingData( $feedParams, $data, $jobId );
			}
			pushIncomingData( $idFeedIn, $data, $inboundId );
			$counts['success']++;
		}

	} else {

		$counts['invalid']++;

		print " - ERROR\n";
		print "<ul>\n";
		foreach($result['errors'] as $error) {
			print "<li class=\"error\">{$error}</li>\n";
		}
		print "</ul>\n";

		$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $data, date('Y-m-d'), $result['errors'][0], $jobId );

		if( LEGACY_DB ) {
			insertIncomingData( $feedParams, $data, $jobId, $result['errors'][0] );
		}

	}

	print "<br/>\n";

	@ob_flush; flush();

	unset( $data );

}
fclose($handle);

print "<strong>FILE UPLOAD COMPLETE!</strong>\n";

print "<p><strong>Successful: {$counts['success']}</strong></p>\n";
print "<p><strong>Duplicates: {$counts['dupe']}</strong></p>\n";
print "<p><strong>Invalid: {$counts['invalid']}</strong></p>\n";
print "<p><strong>Failures: {$counts['failures']}</strong></p>\n";

$leads->auditLog( 'FEEDINC:IMPORT', $_REQUEST['idFeedIn'] );
