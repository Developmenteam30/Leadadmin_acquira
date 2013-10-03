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

$jobId = '1';
$idFeedIn = 8;

    
$feedParams = getFeedIn ( $idFeedIn );
if($feedParams === false){
	print "Database failure, please try again later.";
        
	logError(
		'Feed '.$idFeedIn
            , 'Database failure when attempting to load feed parameters. Check MySQL log file.'
            , true
        );

	exit;
} else if( 0 === $feedParams ) {
	print 'Invalid feed ID';
	exit;
}

$file = './exports/payday.csv';

$handle = @fopen( $file, 'r' );
if( !$handle ) {
	print 'Cannot open file for reading';
	exit;
}

print "<pre>\n";

$cnt = 0;
while( ( $raw_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {

	$data = array();

	if( !empty($raw_data[0]) && !empty( $raw_data[1] ) )
		$data['stamp'] = date( "Y-m-d H:i:s", strtotime( $raw_data[0] . ' ' . $raw_data[1] ) );

	if( !empty($raw_data[2]) )
		$data['fname'] = $raw_data[2];

	if( !empty($raw_data[3]) )
		$data['lname'] = $raw_data[3];

	if( !empty($raw_data[4]) )
		$data['addr'] = $raw_data[4];

	if( !empty($raw_data[5]) )
		$data['city'] = $raw_data[5];

	if( !empty($raw_data[6]) )
		$data['state'] = $raw_data[6];

	if( !empty($raw_data[7]) )
		$data['zip'] = $raw_data[7];

	if( !empty($raw_data[8]) )
		$data['email'] = $raw_data[8];

	if( !empty($raw_data[9]) )
		$data['dob'] = date( "Y-m-d", strtotime( $raw_data[9] ) );

	if( !empty($raw_data[15]) )
		$data['ip'] = $raw_data[15];

	$data['url'] = 'worldwidecashadvance.com';

	// Fix zip codes with a missing leading zero
	if( !empty( $data['zip']) && 4 == strlen( $data['zip'] ) ) {
		$data['zip'] = '0' . $data['zip'];
	}

	$result = validateIncomingData( $feedParams, &$data );

	if( $result['valid'] ) {

		print "{$data['email']} - VALID\n";

		if( insertIncomingData( $feedParams, $data, $jobId ) === true ) {
			pushIncomingData( $idFeedIn, $data );
		}

	} else {

		print "{$data['email']} - ERROR\n";
		print_r($result['errors']);

		insertIncomingData( $feedParams, $data, $jobId, $result['errors'][0] );

	}

	@ob_flush; flush();

	unset( $data );

}
fclose($handle);
