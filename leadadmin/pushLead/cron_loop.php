<?php
$cron_version = '9.6';
include("_f_onlms_v".$cron_version.".php");//all the functions necessary to run the script are included first.

// Loop through the list of active feeds
if ( ( $result = getActiveFeeds() ) ) {
	while ($obj = $result->fetch_object()) {

		print "* Processing feed: {$obj->idFeedOut}\n";

		system( sprintf( 'php -f onlms_process.php -- --v=%s --idFeedOut=%s --cron=1 >/dev/null 2>&1 &',
							escapeshellarg( $cron_version ),
							escapeshellarg( $obj->idFeedOut ) 
						)
				);
	}
}
