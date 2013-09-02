<?php //_connx.php versioning file
//Version
if(isset($_REQUEST['connxVersionOverride'])){ 
	$version = $_REQUEST['connxVersionOverride'];
} else { 
	$version = "1.3";
}
include("_connx_v".$version.".php");
//Changelog
//ES20130707 Version 1.0: Created this script for DNR Direct Marketing
//ES20130722 Version 1.1: Script updated so we can start migrating things to the new database. Every NON-SELECT query 
//should update both MYSQL servers.
//ES20130822 Version 1.3: Updated so that we had a function that could give verbose error messages.