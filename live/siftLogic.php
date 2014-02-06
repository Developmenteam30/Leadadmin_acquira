<?php

class SiftLogic {

	function check( $email )
	{
		if( !defined( 'SIFTLOGIC_APIKEY' ) ) {
			return null;
		}

		$ch = curl_init();

		$postData = array(
			'auth' => '14c38003-cbb6-4709-acb4-f7ef1ff8e83a',
			'format' => 'json',
			'subscriber_email' => $email,
		);

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

		if( !isset( $values->mailable ) ) {
			return null;    
		}

		if( true == $values->mailable ) {
			return true;
		}

		return false;
	}
}
