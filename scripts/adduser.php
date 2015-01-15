<?php 

require( '../includes/c_config.php' );
require_once( INCLUDES . 'leads.php' );

$leads = Leads::getInstance();
$leads->addUser( 'tallacmedia', 'rF;sp;X&', 158, 10 );
$leads->addUser( 'mediamotion', 'j!ix&H~', 154, 10 );
