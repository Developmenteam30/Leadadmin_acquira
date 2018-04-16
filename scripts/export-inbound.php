<?php

include( __DIR__ . "/../includes/c_config.php");

require_once( INCLUDES . 'leads.php' );

set_time_limit(0);

$leads = Leads::getInstance();

$fields = unserialize( 'a:7:{s:8:"idFeedIn";s:2:"84";s:1:"a";s:10:"exportData";s:5:"label";s:22:"permissiondirectphones";s:7:"columns";a:8:{i:0;s:5:"email";i:1;s:5:"fname";i:2;s:5:"lname";i:3;s:4:"addr";i:4;s:4:"city";i:5;s:5:"state";i:6;s:3:"zip";i:7;s:9:"cellphone";}s:9:"dateStart";s:10:"2016-08-09";s:7:"dateEnd";s:10:"2016-08-09";s:5:"limit";s:0:"";}' );

var_dump( $leads->exportInboundRecords( 84, $fields ) );
