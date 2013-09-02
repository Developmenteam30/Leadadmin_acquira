<?php //c_nav.php versioning file
//Version
if(isset($_REQUEST['c_navVersionOverride'])){ 
	$version = $_REQUEST['c_navVersionOverride'];
} else { 
	$version = "1.2";
}
include("c_nav_v".$version.".php");
//Changelog
//ES 2013 07 22 : Created to provide one script for all navigation controls for the system.
//ES 2013 08 22 : Created versioning file. 