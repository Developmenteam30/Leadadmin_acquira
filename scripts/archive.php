<?php

require( '../includes/c_config.php' );
require_once( INCLUDES . "leads.php" );

$leads = Leads::getInstance();

// We started keeping outbound rejected on 9/1/2018 and inbound rejected on 5/1/2018.

$leads->archiveErrors();
//$leads->purgeOutboundRejections();
//$leads->purgeInboundRejections();
$leads->archiveInboundRecords();