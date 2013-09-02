<?php
//dnrdmadmin/loginCheck.php
//Version 1.0
//ES20130706 Version 1.0: Script to determine if the user is logged in.
if(session_id() == '') {
    session_start();
}
//Login Authetication
//Perform session check.
//Check if we even have the login session vars set
$adminLoggedIn = false;
$userLoggedIn = false;
if(
	isset($_SESSION['idAdmin'])
	&& isset($_SESSION['token_admin'])
	&& isset($_SESSION['tokenTime_admin'])
){ 
	//Session vars set. 
	//Check that the token is correct.
	$token = md5(session_id().$_SERVER['REMOTE_ADDR']);
	if($_SESSION['token_admin'] == $token){ 
		//Token is correct.
		//Check that the last time a page change occurred was within 10 minutes.
		$currentTime = microtime(true);
		$allowedTime = 60*15; //(60 secs * 15 mins);
		$usedTime = $currentTime - $_SESSION['tokenTime_admin'];
		if($usedTime < $allowedTime){ 
			//Token time is fine. 
			//Change session id and reset the token and token time.
			session_regenerate_id();
			$newToken = md5(session_id().$_SERVER['REMOTE_ADDR']);
			$_SESSION['token_admin'] = $newToken;
			$_SESSION['tokenTime_admin'] = microtime(true);
			
			$adminLoggedIn = true;
		}
	}
}

if(
	isset($_SESSION['idUser'])
	&& isset($_SESSION['token_user'])
	&& isset($_SESSION['tokenTime_user'])
){ 
	//Session vars set. 
	//Check that the token is correct.
	$token = md5(session_id().$_SERVER['REMOTE_ADDR']);
	if($_SESSION['token_user'] == $token){ 
		//Token is correct.
		//Check that the last time a page change occurred was within 24 Hours
		$currentTime = microtime(true);
		$allowedTime = 60*60*24; //(60 secs * 60 mins * 24 Hours);
		$usedTime = $currentTime - $_SESSION['tokenTime_user'];
		if($usedTime < $allowedTime){ 
			//Token time is fine. 
			//Change session id and reset the token and token time.
			session_regenerate_id();
			$newToken = md5(session_id().$_SERVER['REMOTE_ADDR']);
			$_SESSION['token_user'] = $newToken;
			$_SESSION['tokenTime_user'] = microtime(true);
			
			$memberLoggedIn = true;
		}
	}
}
?>