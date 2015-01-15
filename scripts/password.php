<?php 

require( '../includes/c_config.php' );
require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->setPasswordHash( 'DreamDirect', 'cOA~?Yc&\'NFh' );
$leads->setPasswordHash( 'Red3i', 'g~ft,mXmXi8s' );

