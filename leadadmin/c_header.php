<?php //c_nav.php versioning file
//Version
if(isset($_REQUEST['c_headerVersionOverride'])){ 
	$version = $_REQUEST['c_headerVersionOverride'];
} else { 
	$version = "1.1";
}
include("c_header_v".$version.".php");
//Changelog
//ES 2013 07 22 v1.0: Moved header information into a separate script.
//ES 2013 08 27 v1.1: Created versioning file. Changed css file from .css to .php