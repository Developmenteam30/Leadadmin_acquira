<?php 

include("../includes/c_config.php");

require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
//$leads->getOutboundQueue( 217 );
//$leads->getRejected( 217 );
$leads->exportOutboundQueue( 262 );
$leads->exportOutboundQueue( 263 );
$leads->exportOutboundQueue( 264 );
$leads->exportOutboundQueue( 265 );
$leads->exportOutboundQueue( 266 );
$leads->exportOutboundQueue( 267 );
$leads->exportOutboundQueue( 268 );
$leads->exportOutboundQueue( 269 );
$leads->exportOutboundQueue( 270 );
