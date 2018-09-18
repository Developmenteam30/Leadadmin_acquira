<?php

include( "../../../includes/c_config.php" );

Header( 'Content-Type: application/json' );

require_once( INCLUDES . 'session.php' );

function dieError( $error ) {
	$result = new stdClass();
	$result->success = false;
	$result->error = $error;
	echo json_encode( $result );

	die();
}

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

if( !isset( $_REQUEST['destination'] ) ) {
	dieError( 'No destination supplied' );
}

if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	if( !$leads->checkInboundFeedAccess( $idCompany, $_REQUEST['destination'] ) ) {
		die( 'Sorry, you do not have access to this feed.' );
	}
}

if( empty( $_REQUEST['filename'] ) ) {
	dieError( 'No filename was set (was a file uploaded?).' );
}

if( empty( $_REQUEST['uuid'] ) ) {
	dieError( 'No file uuid was set (was a file uploaded?).' );
}

if( empty( $_REQUEST['type'] ) ) {
	dieError( 'No upload type supplied' );
}

$validTypes = array(
	'feedinc',
	'suppression',
	'upload-outbound',
);

if( !in_array( $_REQUEST['type'], $validTypes ) ) {
	dieError( 'Invalid upload type supplied' );
}

$filename = UPLOADS_DIR . basename( $_REQUEST['uuid'] ) . DIRECTORY_SEPARATOR . basename( $_REQUEST['filename'] );

if( ( $fh = fopen( $filename, 'rb' ) ) === false ) {
	dieError( 'Cannot open uploaded file for reading' );
}
$lines = 0;

while( !feof( $fh ) ) {
	$lines += substr_count( fread( $fh, 8192 ), "\n" );
}

fclose( $fh );

$jobId = $leads->addJob( $_REQUEST['type'], $_REQUEST['destination'], serialize( $_REQUEST ), $filename, $lines );
if( null === $jobId ) {
	dieError( 'Cannot add job to database' );
}

switch( $_REQUEST['type'] ) {
	case 'feedinc':
		$leads->auditLog( 'FEEDINC:IMPORT', $jobId );
		break;

	case 'suppression':
		$leads->auditLog( 'SUPPRESSION:IMPORT', $jobId );
		break;

	case 'upload-outbound':
		$leads->auditLog( 'FEEDOUT:IMPORT', $jobId );
		break;
}

$result = new stdClass();
$result->success = true;
$result->link = sprintf( '/leadadmin/mgr_job.php?jobId=%d&count=%d', $jobId, $lines );
echo json_encode( $result );