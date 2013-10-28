<?php
session_start();
$mysqlErrorSource = 'Manager - File Import';
include("../c_config.php");
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.
include(LIVE_ROOT."_f_validEmail.php");
include(LIVE_ROOT."processFunctions.php");

ini_set("auto_detect_line_endings", true);

if( empty( $_REQUEST['idFeedIn'] )) {
	print '<p class="error">ERROR: No incoming feed ID supplied</p>';
	exit;
}

if( empty( $_REQUEST['url'] )) {
	print '<p class="error">ERROR: No url supplied</p>';
	exit;
}

$idFeedIn = $_REQUEST['idFeedIn'];
$jobId = time();
    
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

if( !empty( $_FILES['import_file']['error'] ) ) {
	print '<p class="error">ERROR: Upload error (' .  $_FILES['import_file']['error'] . ')</p>';
	exit;
}

if( empty( $_FILES['import_file']['tmp_name'] ) ) {
	print '<p class="error">ERROR: No file uploaded</p>';
	exit;
}

if( $_FILES['import_file']['size'] > MAX_UPLOAD_SIZE ) {
	print '<p class="error">ERROR: File size cannot exceed 10MB</p>';
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
				if( 'stamp' == $field )
					$data['stamp'] = date( "Y-m-d H:i:s", strtotime( $raw_data[$col] ) );
				elseif( 'dob' == $field )
					$data['dob'] = date( "Y-m-d", strtotime( $raw_data[$col] ) );
				else
					$data[$field] = $raw_data[$col];
			}
		}
	}

	if( !empty( $_REQUEST['url'] ) )
		$data['url'] = $_REQUEST['url'];
	if( !empty( $_REQUEST['listcode'] ) )
		$data['listcode'] = $_REQUEST['listcode'];

	// Fix zip codes with a missing leading zeros
	if( !empty( $data['zip'] ) ) {
		$data['zip'] = str_pad( $data['zip'], 5, '0', STR_PAD_LEFT);
	}

	print "{$data['email']}";

	$result = validateIncomingData( $feedParams, &$data );

	if( $result['valid'] ) {

		print " - VALID\n";

		if( insertIncomingData( $feedParams, $data, $jobId ) === true ) {
			pushIncomingData( $idFeedIn, $data );
			$counts['success']++;
		} else {
			$counts['failures']++;
		}

	} else {

		$counts['invalid']++;

		print " - ERROR\n";
		print "<ul>\n";
		foreach($result['errors'] as $error) {
			print "<li class=\"error\">{$error}</li>\n";
		}
		print "</ul>\n";

		insertIncomingData( $feedParams, $data, $jobId, $result['errors'][0] );

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
