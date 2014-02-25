<?php

chdir(__DIR__);

require( '../includes/c_config.php' );

$mysqlErrorSource = 'Notification script';
require( INCLUDES."_connx.php" );
require( INCLUDES."processFunctions.php" );

function sendNotification( $label, $feedId, $url, $time ) {
	$to         = MANAGER_EMAIL;
	$subject    = 'Dormant URL notification';
	$body  = "\nThe following URL has gone dormant for more than 90 minutes:\n\n";
	$body .= "URL: {$url}\n\n";
	$body .= "Feed: {$label} ({$feedId})\n\n";
	$body .= "Last seen time: {$time}\n\n";

	$from       = 'lmsalerts@'.SITE_URL;
	$fromName   = CONFIG_COMPANY_NAME.' List Management System';
	$header     = "From: " . $fromName . " <" . $from . ">\r\n";
	$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

	$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );
  
	if(!$sent){
		logError( 'Error Logging', 'Failed to send error report notification to administrator' );
	}
}

dbCon();

$query = "SELECT f.label,f.idFeedIn,n.url,lastTime FROM notifications n LEFT JOIN feedinc f ON f.idFeedIn = n.idFeedIn WHERE lastTime < DATE_SUB(NOW(), INTERVAL 90 MINUTE) AND notifyTime = 0";

if( $result = dbQry( $query, 'Getting notification URLs', true ) ) {
	while ($obj = $result->fetch_object()) {
		sendNotification( $obj->label, $obj->idFeedIn, $obj->url, $obj->lastTime );
		$obj->url = $GLOBALS['dbconnx']->escape_string( $obj->url );
		$obj->idFeedIn = $GLOBALS['dbconnx']->escape_string( $obj->idFeedIn );
		dbQry( "UPDATE notifications SET notifyTime = NOW() WHERE url = '{$obj->url}' AND idFeedIn = '{$obj->idFeedIn}'", 'Update URL notification time', true );
	}
	$result->close();
}

dbDCon();
