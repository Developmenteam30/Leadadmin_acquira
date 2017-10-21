<?php

chdir( __DIR__ );

require( '../includes/c_config.php' );

require_once( INCLUDES . 'leads.php' );

function sendNotification( $name, $email, $lastDate ) {
	$subject = "{$name} - NOT CONTACTED";
	$body = "Please note the client below has not been contacted by you in the last 30 days. It is imperative that clients are contacted at least once a month in order to maintain a relationship.\r\n\r\n";
	$body .= "Company: {$name}\r\n\r\n";
	$body .= "Last Contacted: " . ( !empty( $lastDate ) ? substr( $lastDate, 0, 10 ) : 'Never ' ) . "\r\n\r\n";
	$header = "From: <" . OWNER_EMAIL . ">\r\n";
	$header .= "BCC: " . OWNER_EMAIL . "\r\n";
	$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

	$sent = @mail( $email, $subject, wordwrap( $body, 70 ), $header, "-f" . OWNER_EMAIL );

	if( !$sent ) {
		$leads = Leads::getInstance();
		$leads->logError( 'Failed to send sales notification to ' . $email );
	}
}

$leads = Leads::getInstance();

$companies = $leads->getCompanySalesNotifications();
if( !empty( $companies ) && is_array( $companies ) ) {
	foreach( $companies as $company ) {
		sendNotification( $company['name'], $company['email'], $company['lastDate'] );
		$leads->updateCompanyNotificationDate( $company['idCompany'] );
	}
}