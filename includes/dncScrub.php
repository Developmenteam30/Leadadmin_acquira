<?php

class DNCScrub
{

	function scrub( $phone, $rejectStatuses ) {
		$leads = Leads::getInstance();
		$loginId = $leads->getConfiguration( 'DNCScrub_loginId' );
		$campaignId = $leads->getConfiguration( 'DNCScrub_campaignId' );
		$projectId = $leads->getConfiguration( 'DNCScrub_projectId' );

		if( empty( $loginId ) || empty( $campaignId ) || empty( $projectId ) ) {
			return null;
		}

		$ch = curl_init();

		$postData = array(
			'loginId' => $loginId,
			'campaignId' => $campaignId,
			'projectId' => $projectId,
			'version' => 3,
			'phoneList' => $phone,
		);

		curl_setopt( $ch, CURLOPT_URL, 'http://www.dncscrub.com/app/main/rpc/scrub' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $postData );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$result = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		// Empty result. Probably an API error, but fail safe.
		if( empty( $result ) ) {
			return true;
		}

		// Malformed data. Probably a bad phone number, which we should catch before this anyway.
		if( 400 == $http_code ) {
			return "Malformed";
		}

		// No comma found. Probably an API error, but fail safe.
		if( strpos( $result, ',' ) === false ) {
			return true;
		}

		$data = str_getcsv( $result );
		if( !empty( $data[1] ) && in_array( $data[1], $rejectStatuses ) ) {
			return $data[1];
		}

		return true;
	}
}
