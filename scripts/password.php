<?php 

require( '../includes/c_config.php' );
require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->setPasswordHash( 'digitalbulldogs', 'DMn~y/AO/Ze3' );

