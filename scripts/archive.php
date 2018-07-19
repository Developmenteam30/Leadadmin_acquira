<?php

require( '../includes/c_config.php' );
require_once( INCLUDES . "leads.php" );

$leads = Leads::getInstance();

$leads->archiveErrors();
$leads->purgeInboundRejections();
$leads->purgeOutboundRejections();
$leads->archiveInboundAccepted();