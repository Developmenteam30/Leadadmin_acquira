<?php //mgr_feedinc.php versioning file
//Version
if(isset($_REQUEST['mgr_feedincVersionOverride'])){ 
	$version = $_REQUEST['mgr_feedincVersionOverride'];
} else { 
	$version = "1.1";
}
include("mgr_feedinc_v".$version.".php");
//Changelog
//ES 2013 07 06 v1.0: Created script.
//ES 2013 08 27 v1.1: Created versioning file. Updated index to utilize header include.