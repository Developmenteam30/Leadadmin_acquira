<?php //c_nav.php versioning file
//Version
if(isset($_REQUEST['indexVersionOverride'])){ 
	$version = $_REQUEST['indexVersionOverride'];
} else { 
	$version = "1.1";
}
include("index_v".$version.".php");
//Changelog
//ES 2013 07 06 v1.0: Created script.
//ES 2013 08 27 v1.1: Created versioning file. Updated index to utilize header include.