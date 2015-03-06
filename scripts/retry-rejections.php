<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();
var_dump( $leads->retryOutboundRejections( 231, '2015-03-05' ) );
