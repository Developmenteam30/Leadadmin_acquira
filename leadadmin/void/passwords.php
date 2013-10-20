<?php 
die();
include("../../c_config.php");
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."PasswordHash.php");
$users = array(
	array(
		'username' => 'QatalystMedia'
		, 'password' => '$$Leads##'
	)
);

$pHasher = new PasswordHash(12, false);
foreach($users as $user){ 
	$hash = $pHasher->HashPassword($user['password']);
	dbCon("insertUpdate");
	//$insertPassword = "INSERT INTO `dnrdmktg`.`users` (`username`,`password`) VALUES ('".$user['username']."', '".$hash."');";
	$insertPassword = "UPDATE `dnrdmktg`.`users` SET `password` = '".$hash."' WHERE `username` = '".$user['username']."';";
	$doinsertPassword = dbQry($insertPassword, 'Inserting new user into users');
}
