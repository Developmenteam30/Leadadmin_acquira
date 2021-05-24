<?php

chdir(__DIR__);

require( '../includes/c_config.php' );

$mysqlErrorSource = 'Notification script';
require( INCLUDES."_connx.php" );
require_once( INCLUDES . 'leads.php' );

function sendNotification( $label, $feedId, $url, $time, $hours ) {
	$to         = MANAGER_EMAIL;
	$subject    = 'Dormant URL notification';
	$body  = "\nThe following URL has gone dormant for more than {$hours} hours:\n\n";
	$body .= "URL: " . str_replace( '.', '*', $url ) . "\n\n";
	$body .= "Feed: {$label} ({$feedId})\n\n";
	$body .= "Last seen time: {$time}\n\n";

	$from       = SYSTEM_FROM_EMAIL;
	$fromName   = CONFIG_COMPANY_NAME.' List Management System';
	$header     = "From: " . $fromName . " <" . $from . ">\r\n";
	//$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

	$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );
  
	if(!$sent){
		$leads = Leads::getInstance();
		$leads->logError( 'Failed to send error report notification to administrator' );
	}
}

dbCon();

$leads = Leads::getInstance();
$notifyInterval1 = $leads->getConfiguration( 'notify_interval_1' );
$notifyInterval2 = $leads->getConfiguration( 'notify_interval_2' );

if( !empty( $notifyInterval1 ) ) {

	$query = "SELECT f.label,f.idFeedIn,n.url,lastTime FROM notifications n LEFT JOIN feedinc f ON f.idFeedIn = n.idFeedIn WHERE lastTime < DATE_SUB(NOW(), INTERVAL " . intval( $notifyInterval1 ) . " HOUR) AND notifyTime = 0";

	if( $result = dbQry( $query, 'Getting notification URLs', true ) ) {
		while ($obj = $result->fetch_object()) {
			sendNotification( $obj->label, $obj->idFeedIn, $obj->url, $obj->lastTime, $notifyInterval1 );
			$obj->url = $GLOBALS['dbconnx']->escape_string( $obj->url );
			$obj->idFeedIn = $GLOBALS['dbconnx']->escape_string( $obj->idFeedIn );
			dbQry( "UPDATE notifications SET notifyTime = NOW() WHERE url = '{$obj->url}' AND idFeedIn = '{$obj->idFeedIn}'", 'Update URL notification time', true );
		}
		$result->close();
	}
}

if( !empty( $notifyInterval2 ) ) {

	$query = "SELECT f.label,f.idFeedIn,n.url,lastTime FROM notifications n LEFT JOIN feedinc f ON f.idFeedIn = n.idFeedIn WHERE lastTime < DATE_SUB(NOW(), INTERVAL " . intval( $notifyInterval2 ) . " HOUR) AND notifyTime < DATE_ADD(lastTime, INTERVAL " . intval( $notifyInterval2 ) . " HOUR)";

	if( $result = dbQry( $query, 'Getting notification URLs', true ) ) {
		while ($obj = $result->fetch_object()) {
			sendNotification( $obj->label, $obj->idFeedIn, $obj->url, $obj->lastTime, $notifyInterval2 );
			$obj->url = $GLOBALS['dbconnx']->escape_string( $obj->url );
			$obj->idFeedIn = $GLOBALS['dbconnx']->escape_string( $obj->idFeedIn );
			dbQry( "UPDATE notifications SET notifyTime = NOW() WHERE url = '{$obj->url}' AND idFeedIn = '{$obj->idFeedIn}'", 'Update URL notification time', true );
		}
		$result->close();
	}
}

dbDCon();
