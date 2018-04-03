<?php
//ADMIN_ROOT/f_site.php
//Version 1.1
//ES 2013 08 27 v1.1 : Added hex2rgb function
//Core Values//
$recordFields = array(
	'listcode',
	'listId',
	'url',
	'ip',
	'stamp',
	'email',
	'fname',
	'lname',
	'addr',
	'addr2',
	'city',
	'state',
	'zip',
	'dob',
	'gender',
	'landline',
	'cellphone',
	'country',
	'custom1',
	'custom2',
	'custom3',
	'custom4',
	'custom5',
	'custom6',
);
$additionalMapFields = array(
	'urlAssign', 'dobUS', 'stampUS', 'stampUS_dateOnly', 'stampUSAMPM', 'stampUS+AMPM', 'stamp_YYYYmmdd', 'stamp_YYYY-mm-dd', 'stampUS_slashes',
);
$incomingAdditionalRequirementSettings = array(
	'phone',
);

$crmStatuses = array(
	'cold' => 'Cold',
	'prospecting' => 'Prospecting',
	'negotiating' => 'Negotiating',
	'intheworks' => 'In The Works',
	'live' => 'Live',
	'paused' => 'Paused',
	'retired' => 'Retired',
);

//Utility Functions//
function hex2rgb( $hex ) { //Converts a hex color code to rgb color code for css.
	$hex = str_replace( "#", "", $hex );

	if( strlen( $hex ) == 3 ) {
		$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
		$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
		$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
	} else {
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
	}
	$rgb = array( $r, $g, $b );
	return implode( ",", $rgb ); // returns the rgb values separated by commas
	//return $rgb; // returns an array with the rgb values
}

function genFeedPass( $length = 16 ) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';
	for( $i = 0; $i < $length; $i++ ) {
		$randomString .= $characters[rand( 0, strlen( $characters ) - 1 )];
	}
	return $randomString;
}
