<?php

include("_f_parseArgs.php");
$myArgs = parseArgs($argv);
if( !empty( $myArgs['even'] ) ) {
	$mod = 'even';
} else if( !empty( $myArgs['odd'] ) ) {
	$mod= 'odd';
} else {
	$mod = null;
}

chdir(__DIR__);

include("_f_onlms.php");//all the functions necessary to run the script are included first.

// Loop through the list of active feeds
if ( ( $result = getActiveFeeds( $mod ) ) ) {
	while ($obj = $result->fetch_object()) {

		print "* Processing feed: {$obj->idFeedOut}\n";

		system( sprintf( 'php -f onlms_process.php -- --idFeedOut=%s --cron=1 >/dev/null 2>&1 &',
							escapeshellarg( $obj->idFeedOut ) 
						)
				);
	}
}
