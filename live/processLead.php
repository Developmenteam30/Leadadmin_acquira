<?php //live/processLead.php versioning file.
//Version
if(isset($_REQUEST['processLeadVersionOverride'])){ 
	$version = $_REQUEST['processLeadVersionOverride'];
	error_reporting(-1);
} else { 
	$version = "1.9";
	error_reporting(0);
}
include("processLead_v".$version.".php");
//Changelog
//ES20130707 Version 1.0: Created this script to access the first version of the processLead.php script, to process 
//leads from incoming feeds into the database.
?>