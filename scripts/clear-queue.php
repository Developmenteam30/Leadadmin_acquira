<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();
$leads->clearOutboundQueue( 331, 'qam_insurance_1682' );
$leads->clearOutboundQueue( 333, 'dms_2009' );
$leads->clearOutboundQueue( 332, 'easyquotefinder' );
