<?php	
//ONLINE LIST MANAGEMENT - DELAY BOARD PROCESSING SCRIPT
include("_f_parseArgs.php");
//Accepting argv into $_GET when running from PHP Bin.
if(isset($argv) && is_array($argv)) { $_GET = array_merge($_GET, parseArgs($argv)); }

include("_f_onlms.php");//all the functions necessary to run the script are included first.
//Versioning will allow for testing of new scripts parallel to actual operation

main_pre();//all the functions taht occur before the settings variable is set.

$settings = array(
	"idFeedOut" => ( isset($_GET['idFeedOut']) ) ? $_GET['idFeedOut'] : NULL,
	"cron" => ( isset($_GET['cron']) ) ? $_GET['cron'] : 0,
	"testing" => ( isset($_GET['testing']) ) ? $_GET['testing'] : 0,
	"samplerun" => ( isset($_GET['samplerun']) ) ? $_GET['samplerun'] : 0,
	"forcethrottle" => ( isset($_GET['forcethrottle']) ) ? $_GET['forcethrottle']: 0,
	"onlyurl" => ( isset($_GET['onlyurl']) ) ? $_GET['onlyurl'] : NULL,
	"onlyemail" => ( isset($_GET['onlyemail']) ) ? $_GET['onlyemail'] : NULL,
	"onlytag" => ( isset($_GET['onlytag']) ) ? $_GET['onlytag'] : NULL,
);
/*
The settings array: 
All of these variables are set during the execution call of the script.

list: the list code of the list manager the script is assigned to run for.

mailcode: the mail code of the mailer the leads will be posted to

cron: If this is set to 1, we're running the script on the cron schedule. Subject to effects
of 'pause cron' options in the GUI. 

testing: Setting this to 1 in the url var string will cause the script to run in testing mode, 
With almost anything worth noting being echoed out. Will only one read one lead at a time
In this mode, unless forcethrottle is also set.

samplerun: Setting this to 1 in the url var string will cause the script to run in sample run
mode. In this mode, the request and response, update queries will all be echoed, but various
other information is excluded. Use testing mode for more details.

forcethrottle: Setting this to anything above zero in the url var string will case the script to 
attempt to post leads up to the specified number used. THe script will still try to run under
the MAX_RUNTIME_PROCESS constant - so setting it to a huge number will still have the script
run under about one minute - setting it too high though will cause a large select query which 
will decrease the amount of time avaiable for processing. 

onlyurl: Setting this to any text value will cause the script to attempt to limit the 
urls which it processes. Works well for getting specific results when testing.

onlyemail: Setting this to any text value will cause the script to attempt to limit the 
emails which it processes. Works well for getting specific results when testing.

onlytag: Setting this to any text value will cause the script to attempt to limit the 
tags which it processes. Works well for getting specific results when testing.
*/

main_post(); //all the functions that occur after the settings variable is set.
