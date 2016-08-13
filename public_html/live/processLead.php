<?php
require_once("../../includes/c_config.php");
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

Header('Content-Type: text/xml');

$result = array(
	'success' => 'false',
	'reason' => 'Unknown error.'
);

$feedLabel = getenv('FEED_LABEL');
if( empty( $feedLabel ) ) {
	$result['reason'] = "Feed label is not set.";
	showResultAndDie( $result );
}

$pattern = '/^[a-z][a-z0-9_]*$/';
if( !preg_match( $pattern, $feedLabel ) ) {
	$result['reason'] = "Feed label contains invalid characters";
	showResultAndDie( $result );
}

$feedParams = $leads->getInboundFeedLabel( $feedLabel );
if( $feedParams === null ) {
	$result['reason'] = 'Database failure, please try again later.';
	showResultAndDie( $result );
} else if( false === $feedParams ) {
	$result['reason'] = 'Invalid feed label';
	showResultAndDie( $result );
}

if( empty( $_REQUEST['pswd'] ) || $_REQUEST['pswd'] != $feedParams->password ) {
	$result['reason'] = 'Unauthorized access.';
	$leads->logError( 'Feed '. $feedLabel . ' Unauthorized user at '.$_SERVER["REMOTE_ADDR"], true, false );
	$inboundId = $leads->inboundAdd( $feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null );
	showResultAndDie( $result );
}

if( 'retired' == $feedParams->status ) {
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
