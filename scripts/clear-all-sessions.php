<?php

chdir( __DIR__ );

require( '../includes/c_config.php' );
require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->clearAllSessions();