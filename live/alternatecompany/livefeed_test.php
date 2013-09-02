<?php //livefeed.php
$feedLabel = basename(__DIR__);
echo $feedLabel; exit;

$mysqlErrorSource = 'Feed '.$feedLabel;
if($_SERVER['SERVER_ADDR'] == '127.0.0.1'){ 
	$siteRoot = "M:\\WAMP\\wamp\\www\\dnrdirectmarketing.com\\";
	$liveRoot = "M:\\WAMP\\wamp\\www\\dnrdirectmarketing.com\\live\\";
} else { 
	$siteRoot = "/home/content/22/10755022/html/";
	$liveRoot = "/home/content/22/10755022/html/live/";
}
include($liveRoot."processLead.php");
?>