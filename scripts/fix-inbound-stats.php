<?php

include( __DIR__ . "/../includes/c_config.php" );

require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->fixInboundStats('2022-09-02');
