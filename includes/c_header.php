<?php 
//ADMIN_ROOT/c_header.php
if(!isset($title)){ 
	$title = CONFIG_COMPANY_NAME.' Admin';
} else { 
	$title = $title .' | '.CONFIG_COMPANY_NAME.' Admin';
}
?><!DOCTYPE html>
<html>
<head>	

<title><?php echo $title; ?></title>
<link href="/v4/leadadmin/style.php" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="//code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />
<meta name="viewport" content="initial-scale=1, maximum-scale=1" />
<script src="//code.jquery.com/jquery-2.0.3.js"></script>
<script src="//code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
<script src="./js/default.js"></script>


</head>
