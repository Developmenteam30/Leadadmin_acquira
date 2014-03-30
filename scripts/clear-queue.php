<?php

require( '../includes/c_config.php' );

$mysqlErrorSource = 'Archive script';
require( INCLUDES."_connx.php" );

dbCon();

print "Cleaning up tables ...\n";
$tables = dbQry( "SELECT label,successString FROM feedout", 'Getting list of outgoing tables', true );
if ( $tables ) {
    while($row = $tables->fetch_array(MYSQLI_NUM)){

		print "\t{$row[0]}\n";
		dbQry( "DELETE FROM `feedout_" . $row[0] . "` WHERE poststamp <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND processed = '1' AND postresponse NOT LIKE '%" . $row[1] . "%'", 'Deleting old outgoing invalid entries', true );
		dbQry( "DELETE FROM `feedout_" . $row[0] . "` WHERE processed = '0'", 'Deleting queued records', true );
		dbQry( "OPTIMIZE TABLE `feedout_" . $row[0] . "`", 'Rebuilding table', true );

    }
}


dbDCon();
