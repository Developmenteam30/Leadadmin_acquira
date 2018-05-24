<?php
require_once( "../../includes/c_config.php" );
require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'Array2XML.php' );
require_once( INCLUDES . 'processLeads.php' );

function showResultAndDie( $result ) {
	$xml = Array2XML::createXML( 'response', $result );
	echo $xml->saveXML();
	die();
}

$leads = Leads::getInstance();

$statsDay = date( 'Y-m-d' );

Header( 'Content-Type: text/xml' );

$result = array(
	'success' => 'false',
	'reason' => 'Unknown error.',
);

$idFeedIn = getenv( 'FEED_LABEL' );
if( empty( $idFeedIn ) ) {
	http_response_code( 400 );
	$result['reason'] = "Feed id is not set.";
	showResultAndDie( $result );
}

if( preg_match( '/^[0-9]+$/', $idFeedIn ) ) { // New style uses immutable idFeedIn
	$feedParams = $leads->getInboundFeed( $idFeedIn );
} else if( preg_match( '/^[a-z][a-z0-9_]*$/', $idFeedIn ) ) { // Old style uses feedLabel
	$feedParams = $leads->getInboundFeedLabel( $idFeedIn );
} else {
	http_response_code( 400 );
	$result['reason'] = "Feed id contains invalid characters";
	showResultAndDie( $result );
}

if( $feedParams === null ) {
	http_response_code( 500 );
	$result['reason'] = 'Database failure, please try again later.';
	showResultAndDie( $result );
} else if( false === $feedParams ) {
	http_response_code( 403 );
	$result['reason'] = 'Invalid feed id';
	showResultAndDie( $result );
}

if( empty( $_REQUEST['pswd'] ) || $_REQUEST['pswd'] != $feedParams->password ) {
	http_response_code( 403 );
	$result['reason'] = 'Unauthorized access.';
	$leads->logError( 'Feed ' . $feedParams->label . ' Unauthorized user at ' . $_SERVER["REMOTE_ADDR"], true, false );
	$_REQUEST['url'] = $_REQUEST['url'] ?? ''; // Ensure a value for the URL is set
	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null );
	showResultAndDie( $result );
}

if( 'retired' == $feedParams->status ) {
	http_response_code( 403 );
	$result['reason'] = 'This feed has been disabled.';
	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null );
	showResultAndDie( $result );
}

$validateResult = ProcessLeads::validateIncomingData( $feedParams, $_REQUEST );

if( $validateResult['valid'] ) {

	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $statsDay, null );
	if( null === $inboundId ) {
		$result['reason'] = 'Database error while trying to add your record.';
	} else {
		if( ( $pushError = ProcessLeads::pushIncomingData( $feedParams, $_REQUEST, $inboundId ) ) === null ) {
			$result['success'] = 'true';
			$result['reason'] = 'Successfully inserted new record.';
		} else {
			$result['reason'] = $pushError;
		}
	}

} else {

	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $statsDay, $validateResult['errors'][0], null );
	$result['reason'] = $validateResult['errors'][0];

}

showResultAndDie( $result );
