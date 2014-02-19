<?php

class SiftLogic {

	function check( $email, $ip, $first, $last, $addr1, $addr2, $city, $state, $zip, $country, $verbose = false )
	{
		if( !defined( 'SIFTLOGIC_APIKEY' ) ) {
			return null;
		}

		$ch = curl_init();

		$postData = array(
			'auth' => SIFTLOGIC_APIKEY,
			'format' => 'json',
			'subscriber_email' => $email,
		);

		if( !empty( $ip ) ) {
			$postData['subscriber_signup_ip'] = $ip;
		}

		if( !empty( $first ) ) {
			$postData['subscriber_fname'] = $first;
		}

		if( !empty( $last ) ) {
			$postData['subscriber_lname'] = $last;
		}

		if( !empty( $addr1 ) ) {
			$postData['subscriber_addr1'] = $addr1;
		}

		if( !empty( $addr2 ) ) {
			$postData['subscriber_addr2'] = $addr2;
		}

		if( !empty( $city ) ) {
			$postData['subscriber_city'] = $city;
		}

		if( !empty( $state ) ) {
			$postData['subscriber_state'] = $state;
		}

		if( !empty( $zip ) ) {
			$postData['subscriber_zip'] = $zip;
		}

		if( !empty( $country ) ) {
			$postData['subscriber_country'] = $country;
		}

		curl_setopt( $ch, CURLOPT_URL, 'http://api.tempaccess.pw:8080/api/live/verify' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postData );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$result = curl_exec( $ch );
		curl_close( $ch );

		if( ( $values = json_decode( $result ) ) == null ) {
			return null;    
		}

		if( $verbose ) { 
			print_r( $values );
		}

		if( !isset( $values->status ) ) {
			return null;
		}

		if( ( isset( $values->results->subscriber_email_is_blacklisted ) && '1' == $values->results->subscriber_email_is_blacklisted ) || 'trap' == $values->status ) {

			require_once( INCLUDES.'connx.php' );
			dbCon();
			$insert = "INSERT INTO `".DATABASE_NAME."`.`suppression_global` (`email`) VALUES ('".$GLOBALS['dbconnx']->escape_string($email)."');";
			$doinsert = dbQry($insert, 'Inserting email into suppression', true, true, true); //Verbose result turned on.
			dbDcon();
			return false;

		}

		if( isset( $values->results->subscriber_score_bucket ) && !in_array( $values->results->subscriber_score_bucket, array( 'high', 'medium' ) ) ) {
			return false;
		}

		if( isset( $values->mailable ) && false == $values->mailable ) {
			return false;
		}

		return true;
	}
}
