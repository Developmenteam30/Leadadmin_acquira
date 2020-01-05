<?php

require( '../includes/c_config.php' );
require_once( INCLUDES . "leads.php" );

$leads = Leads::getInstance();

$recs = $leads->archiveOutboundPhone('4047544843', '2014-06-01 00:00:00', '2018-08-30 23:59:59');

var_dump($recs);