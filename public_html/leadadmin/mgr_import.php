<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );

function dieError( $error ) {
	dieError( '' . $error . '' );
	print "\t</div>\n";
	print "</div>\n";
	print "</body>\n";
	print "</html>\n";
	@ob_flush();
	@flush();
	exit;
}

$mysqlErrorSource = 'Manager - File Import';
require_once(INCLUDES."_connx.php");
require_once(INCLUDES."f_site.php");
require_once(INCLUDES."_f_validEmail.php");
require_once(INCLUDES."processFunctions.php");

ini_set("auto_detect_line_endings", true);
set_time_limit(0);

$title = 'Upload File';
include(INCLUDES."c_header.php");

?>

<body>

<div class='mainContainer'>
    <?php include(INCLUDES.'c_nav.php'); ?>
    <div style='margin: auto;'>

<?php

if( empty( $_REQUEST['idFeedIn'] )) {
	dieError( 'No incoming feed ID supplied' );
}

$idFeedIn = !empty( $_REQUEST['idFeedIn'] ) ? $_REQUEST['idFeedIn'] : 0;
$leads = Leads::getInstance();

if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
		dieError( 'Sorry, you do not have access to this feed.' );
	}
}

$feedParams = $leads->getInboundFeed( $idFeedIn );
if($feedParams === false){
	dieError( 'Database failure.  Cannot load feed information.' );
} else if( 0 === $feedParams ) {
	dieError( 'Invalid incoming feed ID supplied' );
}

if( empty( $_FILES['import_file']['tmp_name'] ) ) {
	dieError( 'You did not select a file to upload' );
}

if( $_FILES['import_file']['size'] > MAX_UPLOAD_SIZE ) {
	dieError( 'File size cannot exceed ' . (MAX_UPLOAD_SIZE / 1024000) . 'MB' );
}

if( !empty( $_FILES['import_file']['error'] ) ) {
	dieError( 'Upload error (' .  $_FILES['import_file']['error'] . ')' );
}

if( !is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) {
	dieError( 'Possible file upload attack!' );
}

$handle = @fopen( $_FILES['import_file']['tmp_name'], "r" );
if( !$handle ) {
	dieError( 'Cannot open uploaded file for reading' );
}

// Turn off output buffering
ini_set('output_buffering', 'off');

// Turn off PHP output compression
ini_set('zlib.output_compression', false);

// Flush (send) the output buffer and turn off output buffering
// ob_end_flush();
while (@ob_end_flush());

// Implicitly flush the buffer(s)
ini_set('implicit_flush', true);
ob_implicit_flush(true);

print '<p>Uploading: ';

@ob_flush();
@flush();

$cnt = 0;
while( ( $raw_data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {
	$cnt++;
	print ". \n";
	@ob_flush();
	@flush();
}
fclose($handle);

print 'Done!</p>';

@ob_flush();
@flush();

if( 0 === $cnt ) {
	dieError( 'File contains no records' );
}

$newFile = SITE_ROOT . 'uploads/' . hash( 'sha256', $_FILES['import_file']['tmp_name'] );
if( move_uploaded_file( $_FILES['import_file']['tmp_name'], $newFile  ) !== true ) {
	dieError( 'Cannot move uploaded file for processing' );
}

$jobId = $leads->addJob( $_REQUEST['idFeedIn'], serialize( $_REQUEST ), $newFile, $cnt );
if( null === $jobId ) {
	dieError( 'Cannot add job to database' );
}

$leads->auditLog( 'FEEDINC:IMPORT', $jobId );

$link = sprintf( '/leadadmin/mgr_job.php?jobId=%d&count=%d',
		$jobId,
		$cnt );

?>
<p><a href="<?php echo $link; ?>">View results</a></p>
<script>window.location = '<?php echo $link; ?>';</script>

    </div>
</div>

</body>
</html>

<?php
@ob_flush();
@flush();

