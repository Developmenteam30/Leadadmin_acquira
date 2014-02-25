<?php

require( '../includes/c_config.php' );

$mysqlErrorSource = 'Archive script';
require( INCLUDES."_connx.php" );

dbCon();

print "Deleting old errorLog entries ...\n";
dbQry( "DELETE FROM errorlog WHERE stamp <= DATE_SUB(NOW(), INTERVAL 30 DAY)", 'Deleting old errorlog entries', true );

print "Deleting old incoming invalid entries ...\n";
$tables = dbQry( "SHOW TABLES LIKE 'feedinc_%_invalid'", 'Getting list of inbound invalid tables', true );
if ( $tables ) {
    while($row = $tables->fetch_array(MYSQLI_NUM)){
		print "\t{$row[0]}\n";
		dbQry( "DELETE FROM `" . $row[0] . "` WHERE received <= DATE_SUB(NOW(), INTERVAL 15 DAY)", 'Deleting old incoming invalid entries', true );
    }
}

print "Deleting old outgoing invalid entries ...\n";
$tables = dbQry( "SELECT label,successString FROM feedout", 'Getting list of outgoing tables', true );
if ( $tables ) {
    while($row = $tables->fetch_array(MYSQLI_NUM)){

		print "\t{$row[0]}\n";
		dbQry( "DELETE FROM `feedout_" . $row[0] . "` WHERE poststamp <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND processed = '1' AND postresponse NOT LIKE '%" . $row[1] . "%'", 'Deleting old outgoing invalid entries', true );

    }
}


dbDCon();
