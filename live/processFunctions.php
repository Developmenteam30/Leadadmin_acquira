<?php //live/processFunctions.php versioning file.
//Version
if(isset($_REQUEST['processFunctionsVersionOverride'])){ 
	$version = $_REQUEST['processFunctionsVersionOverride'];
} else { 
	$version = "1.8";
}
include("processFunctions_v".$version.".php");
//Changelog
//ES20130829 Version 1.8: Dates improperly formatted with / will also be rejected.
//ES20130707 Version 1.0: Created this script to act as the functions file for processLead.php
?>