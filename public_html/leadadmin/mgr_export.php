<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

$mysqlErrorSource = 'Manager - Suppression';
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");
include(INCLUDES."processFunctions.php");

require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->exportOutboundQueue( 127 );
