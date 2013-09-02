<?php //ADMIN_ROOT/f_site.php versioning file
//Version
if(isset($_REQUEST['f_siteVersionOverride'])){ 
	$version = $_REQUEST['f_siteVersionOverride'];
} else { 
	$version = "1.1";
}
include("f_site_v".$version.".php");
//Changelog
//ES 2013 08 27 : Created versioning file. 