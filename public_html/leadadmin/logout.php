<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::logout();

header('Location: index.php');
