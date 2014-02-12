<?php
//ADMIN_ROOT/c_loginRequired.php
//Version 1.0
//ES 2013 08 20 v1.0 : Created this file to have one place to change all login required checks for all pages.
//Core Values//
if(!$adminLoggedIn){ 
	if(isset($_REQUEST['a'])){
		$result = array('status' => 0, 'error'=> 'You are no longer logged in. Log back in and try again.');
		echo json_encode($result); exit;
	}
	elseif(isset($_REQUEST['d'])){
		echo "You are no longer logged in. Log back in and try again.";	exit;
	} else { 
		header("Location: index.php"); exit;
	}
}

?>