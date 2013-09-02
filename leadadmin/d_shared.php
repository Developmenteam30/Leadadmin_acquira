<?php //ADMIN_ROOT/d_shared.php versioning file
//Version
if(isset($_REQUEST['d_sharedVersionOverride'])){ 
	$version = $_REQUEST['d_sharedVersionOverride'];
} else { 
	$version = "1.0";
}
include("d_shared_v".$version.".php");
//Changelog
//ES 2013 08 22 : Created versioning file. 