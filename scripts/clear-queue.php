<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();
$leads->clearOutboundQueue( '211', 'qam_health_1346' );
