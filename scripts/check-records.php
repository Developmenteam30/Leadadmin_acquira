<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();


$fi = fopen( '../input/simint_1407969673.csv', 'r' );
$fo = fopen( '../input/simint_1407969673-DATES-NEW.csv', 'w' );
while (($data = fgetcsv($fi, 1000, ",")) !== FALSE) {


	$data[4] = $leads->inboundEmailSearchByLabel( $data[3], 'simint' );
print_r($data);

	fputcsv( $fo, $data );
}
fclose( $fi );
fclose( $fo );


$fi = fopen( '../input/digitalbulldogs_1407969596.csv', 'r' );
$fo = fopen( '../input/digitalbulldogs_1407969596-DATES-NEW.csv', 'w' );
while (($data = fgetcsv($fi, 1000, ",")) !== FALSE) {


	$data[4] = $leads->inboundEmailSearchByLabel( $data[3], 'digitalbulldogs' );
print_r($data);

	fputcsv( $fo, $data );
}
fclose( $fi );
fclose( $fo );


