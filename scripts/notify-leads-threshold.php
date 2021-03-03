<?php

chdir( __DIR__ );

require( '../includes/c_config.php' );

require_once( INCLUDES . 'leads.php' );

function sendInboundNotification( $notification ) {
	print "Sending notification for #{$notification->idFeedIn}" . PHP_EOL;
	$fromName = CONFIG_COMPANY_NAME . ' List Management System';
	$subject = "{$notification->name} - {$notification->label} - Below Daily Threshold";
	$body = "Company: {$notification->name}\r\n";
	$body .= "Feed Label: {$notification->label} (#{$notification->idFeedIn})\r\n";
	$body .= "Feed Description: {$notification->description}\r\n";
	$body .= "We have received {$notification->leadsPassed} leads on this feed today and we are expecting to have received at least {$notification->notifyThresholdCount} leads by {$notification->notifyThresholdTimeFormatted}.\r\n\r\n";
	$header = "From: \"{$fromName}\" <" . OWNER_EMAIL_FROM . ">\r\n";
	//$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

	$sent = @mail( MANAGER_EMAIL, $subject, wordwrap( $body, 70 ), $header, "-f" . OWNER_EMAIL_FROM );

	if( !$sent ) {
		$leads = Leads::getInstance();
		$leads->logError( 'Failed to send threshold notification to ' . MANAGER_EMAIL );
	}
}

function sendOutboundNotification( $notification ) {
	print "Sending notification for #{$notification->idFeedOut}" . PHP_EOL;
	$fromName = CONFIG_COMPANY_NAME . ' List Management System';
	$subject = "{$notification->name} - {$notification->label} - Below Daily Threshold";
	$body = "Company: {$notification->name}\r\n\r\n";
	$body .= "Feed Label: {$notification->label} (#{$notification->idFeedOut})\r\n\r\n";
	$body .= "Feed Description: {$notification->description}\r\n\r\n";
	$body .= "We have sent {$notification->leadsPassed} leads on this feed today and we are expecting to have sent at least {$notification->notifyThresholdCount} leads by {$notification->notifyThresholdTimeFormatted}.\r\n\r\n";
	$header = "From: \"{$fromName}\" <" . OWNER_EMAIL_FROM . ">\r\n";
	//$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
	$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

	$sent = @mail( MANAGER_EMAIL, $subject, wordwrap( $body, 70 ), $header, "-f" . OWNER_EMAIL_FROM );

	if( !$sent ) {
		$leads = Leads::getInstance();
		$leads->logError( 'Failed to send threshold notification to ' . MANAGER_EMAIL );
	}
}

$leads = Leads::getInstance();

try {
	$notifications = $leads->checkInboundFeedThresholds();
	if( !empty( $notifications ) && is_array( $notifications ) ) {
		foreach( $notifications as $notification ) {
			sendInboundNotification( $notification );
			$leads->updateInboundFeed( $notification->idFeedIn, array( 'notifyThresholdLastSent' => date( 'Y-m-d H:i:s' ) ) );
		}
	}

	$notifications = $leads->checkOutboundFeedThresholds();
	if( !empty( $notifications ) && is_array( $notifications ) ) {
		foreach( $notifications as $notification ) {
			sendOutboundNotification( $notification );
			$leads->updateOutboundFeed( $notification->idFeedOut, array( 'notifyThresholdLastSent' => date( 'Y-m-d H:i:s' ) ) );
		}
	}
}catch( \Leads_PDOException $e ) {
	print "PDO Error: " . $e->getMessage() . PHP_EOL;
}