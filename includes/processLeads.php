<?php

require_once( 'c_config.php' );

class ProcessLeads
{
	public static function assignValue( $key, $value, &$requestdata ) {

		if( strpos( $key, '|' ) !== FALSE ) {
			$vars = explode('|', $key );
			$requestdata[$vars[0]][$vars[1]] = $value;
		} else {
			$requestdata[$key] = $value;
		}
	}

	public static function filterValue( $filterType, $value, $filters ) {
		switch( $filterType ) {
			case 'accept':
				$valueAcceptable = false;
				$acceptableFilters = explode( ";", $filters );
				foreach( $acceptableFilters as $acceptableFilter ) {
					if( stripos( $value, $acceptableFilter ) !== false ) {
						$valueAcceptable = true;
						break;
					}
				}
			break;

			case 'reject':
				$valueAcceptable = true;
				$rejectableFilters = explode( ";", $filters );
				foreach( $rejectableFilters as $rejectableFilter ) {
					if( stripos( $value, $rejectableFilter ) !== false ) {
						$valueAcceptable = false;
						break;
					}
				}
			break;

			default:
				$valueAcceptable = true;
			break;

		}

		return $valueAcceptable;
	}

	function curlLead( $requestdata, $url, $post, $verifypeer = false, $returntransfer = true, $header = false, $httpheader = NULL, $followlocation = false ) {

		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_URL, $url );
		if( $post ) {
			curl_setopt( $ch, CURLOPT_POST, true );
		}
		if( !empty( $requestdata ) ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $requestdata );
		}
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, $verifypeer );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, $returntransfer );
		curl_setopt( $ch, CURLOPT_HEADER, $header );
		if( !empty( $httpheader ) ) {
			curl_setopt( $ch, CURLOPT_HTTPHEADER, $httpheader );
		}
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, $followlocation );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 65 );

		$response = curl_exec ( $ch );
		if( curl_errno( $ch ) != 0 ) {
			$response = "CURL Error: " . curl_error( $ch );
		}
		curl_close( $ch );

		return $response;
	}

	public static function pushIncomingData( $feedParams, $data, $inboundId ) {

		$leads = Leads::getInstance();

		if( !empty( $data['url'] ) && !empty( $feedParams->notifications ) ) {

			// Notify if this is the first time we've seen this URL on this feed
			$urlExists = $leads->checkInboundURLExists( $feedParams->idFeedIn, $data['url'] );
			if( false === $urlExists ) {

				$body = sprintf( "\r\nWe received a new URL on this feed.\r\n\r\nFeed: {$feedParams->label}\r\n\r\nURL: %s\r\n\r\n",
							str_replace( '.', '*', $data['url'] )
				);

				$from = 'lmsalerts@' . SITE_URL;
				$fromName = CONFIG_COMPANY_NAME . ' List Management System';
				$to = MANAGER_EMAIL;
				$subject = 'List Management - New URL Alert';
				$header	 = "From:" . $fromName . " <" . $from . ">\r\n";
				$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";
				$header .= "Reply-To: <" . $from . ">\r\n";
				$header .= "X-Sender: <" . $from . ">\r\n";
				$header .= "Return-Path: <" . $from . ">\r\n";
				$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );
				if( !$sent ) {
					$leads->logError( 'Failed to send error report notification to administrator' );
				}

			}

			// Add an entry to the notification table to see if this feed goes dormant
			$leads->addNotification( $feedParams->idFeedIn, $data['url'] );

		}

		$feedsOut = $leads->getInboundPopulationSettings( $feedParams->idFeedIn );
		if( !empty( $feedsOut ) && is_array( $feedsOut ) ) {
			foreach( $feedsOut as $feed ) {

				// Is this population parameter enabled?
				if( empty( $feed->enabled ) ) {
					break;
				}

				// Ensure the record passes the population parameter filters for this feed
				if( !is_null( $feed->filterTypeUrl ) && !ProcessLeads::filterValue( $feed->filterTypeUrl, $data['url'], $feed->filterUrl ) ) {
					break;
				}

				if( !is_null( $feed->filterTypeEmail ) && !ProcessLeads::filterValue( $feed->filterTypeEmail, $data['email'], $feed->filterEmail ) ) {
					break;
				}

				if( !is_null( $feed->filterTypeListcode ) && !ProcessLeads::filterValue( $feed->filterTypeListcode, $data['listcode'], $feed->filterListcode ) ) {
					break;
				}

				// Ensure we haven't reached our daily limit of records
				if( !is_null( $feed->dailyLimit ) && intval( $feed->dailyLimit ) > 0 ) {
					$cnt = $leads->getOutboundDailyCount( $feed->idFeedOut );
					if( $cnt && $cnt > $feed->dailyLimit ) {
						$leads->logError( 'Feed '.$feed->label . ' Daily feed limit of ' . $feed->dailyLimit . ' reached', true, false );
						break;
					}
				}

				// Handle URL rewriting
				$urlRewritten = false;
				if( !empty( $feed->forceUrl ) && !empty( $feed->forceUrlList ) ) {

					$forceUrls = explode( ';', $feed->forceUrlList );
					if( !empty( $forceUrls ) && is_array( $forceUrls ) && sizeOf( $forceUrls ) > 0 ) {
						shuffle( $forceUrls ); // Randomize the order of the array incase we are re-writing to multiple URLs

						foreach( $forceUrls as $forceUrl ) {
							$mapping = explode( '=', $forceUrl, 2 );
							if( !empty( $mapping[0] ) && !empty( $mapping[1] ) ) {
								if( parse_url( $data['url'], PHP_URL_HOST ) === $mapping[0] ) {
									$data['url'] = 'http://' . $mapping[1];
									$urlRewritten = true;
									break;
								}
							}
						}
					}
				}

				$leads->outboundAdd( $inboundId, null, $feedParams->idFeedIn, $feed->idFeedOut, $data['url'], ( !empty( $feed->livedata ) ? -1 : 0 ), $urlRewritten );

				// If this is a "livedata" population, immediately try to send the record through to the receiving feed
				if( !empty( $feed->livedata ) ) {

					$record = $leads->getOutboundRecord( $inboundId, $feed->idFeedOut, -1 );
					if( !empty( $record ) ) {
						$feedOut = $leads->getOutboundFeed( $feed->idFeedOut );
						$status = ProcessLeads::pushOutboundData( $feedOut, $record );
						if( isset( $status['status'] ) && $status['status'] != true ) {

							$reason = 'This record was rejected by the receiving party [Feed ID: ' . $feed->idFeedOut . ']';
							$leads->inboundProcess( $inboundId, $feedParams->idFeedIn, $data['url'], date( 'Y-m-d' ), $reason );
							return $reason;
						}
					}
				}
			} // foreach $feedsOut
		}

		return null;
	}

	public static function pushOutboundData( $feedOut, $row ) {

		$leads = Leads::getInstance();

		$debug = false;
		$result = array(
			'status' => false,
			'text' => 'Unknown error.',
		);

		$staticFields = explode( ";", $feedOut->staticFields );
		$varFields = explode( ";", $feedOut->varFields );
		$fieldMap = explode( ";", $feedOut->fieldMap );

		// Override the outbound URL
		if( !empty( $row->urlRewrite ) ) {
			$row->url = $row->urlRewrite;
		}

		// Check for legacy stamp field
		if( empty( $row->stamp ) ) {
			$row->stamp = $row->leadstamp;
		}

		// Check global and local suppression lists
		if( !empty( $row->email ) && $leads->checkSuppression( $row->email, null ) ) {

			$result['text'] = 'LOCAL REJECTION: Email is suppressed (global)';
			$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url, $result['text'] );

			if( $debug ) {
				print "\t" . $result['text'] . PHP_EOL;
			}
			return $result;

		} else if( !empty( $row->email ) && $leads->checkSuppression( $row->email, $feedOut->idCompany ) ) {

			$result['text'] = 'LOCAL REJECTION: Email is suppressed (company)';
			$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url,$result['text'] );

			if( $debug ) {
				print "\t" . $result['text'] . PHP_EOL;
			}
			return $result;

		}

		$requestdata = array();
		foreach( $staticFields as $sF ) { //Compile Static Fields into the post array.
			if( !empty( $sF ) ) {
				$fieldValuePair = explode( "=", $sF );
				ProcessLeads::assignValue( $fieldValuePair[0], $fieldValuePair[1], $requestdata );
			}
		}

		for( $count = 0; $count < count( $varFields); $count++ ) { //Compile mapped fields into the post array.
			if( !empty( $varFields[$count] ) ) {
				switch( $fieldMap[$count] ){
					case 'urlAssign':
						$urlassignments = explode( ";", $feedOut->urlassignments );
						$urlassignment = '';
						foreach( $urlassignments as $instructions ) {
							if( !empty( $instructions ) ) {
								$fieldValuePair = explode( "=", $instructions );
								if( stripos( $row->url, $fieldValuePair[0]) !== false ) {
									if( $debug ) {
										echo "\tMatched assignment: " . $fieldValuePair[0] . "\n";
									}
									$urlassignment = $fieldValuePair[1];
									break;
								}
							}
						}
						ProcessLeads::assignValue( $varFields[$count], $urlassignment, $requestdata );
						break;

					case 'dobUS':
						ProcessLeads::assignValue( $varFields[$count], date("m-d-Y", strtotime( $row->dob ) ), $requestdata );
						break;

					case 'stampUS':
						ProcessLeads::assignValue( $varFields[$count], date("m-d-Y H:i:s", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stampUS_dateOnly':
						ProcessLeads::assignValue( $varFields[$count], date("m-d-Y", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stamp_YYYYmmdd':
						ProcessLeads::assignValue( $varFields[$count], date("Ymd", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stamp_YYYY-mm-dd':
						ProcessLeads::assignValue( $varFields[$count], date("Y-m-d", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stampUSAMPM':
						ProcessLeads::assignValue( $varFields[$count], date("m-d-Y h:i:sA", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stampUS+AMPM':
						ProcessLeads::assignValue( $varFields[$count], date("m-d-Y h:i:s A", strtotime( $row->stamp ) ), $requestdata );
						break;

					case 'stampUS_slashes':
						ProcessLeads::assignValue( $varFields[$count], date("m/d/Y H:i:s", strtotime( $row->stamp ) ), $requestdata );
						break;

					default:
						ProcessLeads::assignValue( $varFields[$count], $row->{$fieldMap[$count]}, $requestdata );
						break;

				}
			}
		}

		if( $debug ) {
			echo "\tPosting Array: \n";
			print_r($requestdata);
		}

		if( $feedOut->feedType == 'curlGET' ) { // Method is GET

			$geturl = $feedOut->postUrl . "?";
			$flag = false;
			foreach( $requestdata as $field => $value ) {
				if( $flag ) {
					$geturl .= "&";
				}
				$geturl .= $field . "=" . urlencode( $value );
				$flag = true;
			}
			if( $debug ) {
				echo "\tGet URL: \n";
				echo "\t" . $geturl."\n";
				echo "\tPosting data.\n";
			}
			$result['text'] = ProcessLeads::curlLead(
				"",
				$geturl,
				false
			);

		} else if( 'csvString' == $feedOut->feedType ) { // Method is CVS

			$geturl = $feedOut->postUrl . "?data=";
			$flag = false;
			foreach( $requestdata as $field => $value ) {
				if( $flag ) {
					$geturl .= ",";
				}
				$geturl .= urlencode( str_replace( ',', '', $value ) );
				$flag = true;
			}
			if( $debug ) {
				echo "\tGet URL (CSV): \n";
				echo "\t" . $geturl."\n";
				echo "\tPosting data.\n";
			}
			$result['text'] = ProcessLeads::curlLead( "", $geturl, false );

		} else if( 'JSON' == $feedOut->feedType ) { // Method is JSON

			if( $debug ) {
				echo "\tPosting JSON data.\n";
			}
			$result['text'] = ProcessLeads::curlLead(
				json_encode( $requestdata ),
				$feedOut->postUrl,
				true,
				false,
				true,
				false,
				array( 'Content-Type: application/json' )
			);

		} else { // Method is post

			if( $debug ) {
				echo "\tPosting data.\n";
			}
			$result['text'] = ProcessLeads::curlLead(
				$requestdata,
				$feedOut->postUrl,
				true
			);
		}

		// Check if the response we got is a success for this feed.
		if( strpos( $feedOut->successString, 'REGEX:' ) === 0 ) {

			// Check for a regular expression match
			if( preg_match( substr( $feedOut->successString, 6 ), $result['text'] ) === 1 ) {
				$result['status'] = true;
			} else {
				$result['status'] = false;
			}

		} else {

			// Check for a direct substring comparison match
			$sucstr = str_replace( '%', '', $feedOut->successString ); // Remove mysql wildcards
			if( stripos( $result['text'], $sucstr ) !== false ) {
				$result['status'] = true;
			} else {
				$result['status'] = false;
			}
		}

		if( $debug ) {
			echo "\tResponse: {$result['text']}\n";
		}

		$leads->outboundProcess( $row->idRecord, $feedOut->idFeedOut, $row->url, ( $result['status'] ? null : trim( $result['text'] ) ) );

		$result['querystring'] = $geturl;

		return $result;
	}

	public static function validateField( $fieldType, $value, $feedParams ) {
		$result = array(
			'valid' => false,
			'reason' => 'No reason given.',
		);

		$c = true;

		switch($fieldType){
			case 'listcode':
				if($c && strlen($value) > 20){
					$c = false; $result['reason'] = 'List code (listcode) exceeds maximum allowed length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'List code (listcode) contains invalid characters.';
				}
			break;

			case 'url':
				if($c && strlen($value) > 500){
					$c = false; $result['reason'] = 'URL (url) exceeds maximum allowed length.';
				}
				if($c && !filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$c = false; $result['reason'] = 'URL (url) is invalid.';
				}
			break;

			case 'ip':
				if($c && strlen($value) > 45){
					$c = false; $result['reason'] = 'IP (ip) exceeds maximum allowed length.';
				}
				if($c && !filter_var( $value, FILTER_VALIDATE_IP ) ) {
					$c = false; $result['reason'] = 'IP (ip) is invalid.';
				}
			break;

			case 'stamp':
				if( $c && strtotime($value) === false ) {
					$c = false; $result['reason'] = 'Action Date (stamp) is invalid.';
				}
				if($c
					&& $feedParams->rejectOldLeads
					&& strtotime($value) < strtotime($feedParams->rejectOldLeadsMaxAge)
				){
					$c = false; $result['reason'] = 'Action Date (stamp) is too old, lead rejected.';
				}
			break;

			case 'email':
				if($c && strlen($value) > 150){
					$c = false; $result['reason'] = 'Email (email) exceeds maximum allowed length.';
				}
				if($c && !filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
					$c = false; $result['reason'] = 'Email (email) is invalid.';
				}
			break;

			case 'fname':
				if($c && strlen($value) > 50){
					$c = false; $result['reason'] = 'First Name (fname) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 1){
					$c = false; $result['reason'] = 'First Name (fname) does not meet required length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'First Name (fname) contains invalid characters.';
				}
				break;

			case 'lname':
				if($c && strlen($value) > 50){
					$c = false; $result['reason'] = 'Last Name (lname) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 1){
					$c = false; $result['reason'] = 'Last Name (lname) does not meet required length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'Last Name (lname) contains invalid characters.';
				}
				break;

			case 'addr':
				if($c && strlen($value) > 150){
					$c = false; $result['reason'] = 'Address Line 1 (addr) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 3){
					$c = false; $result['reason'] = 'Address Line 1 (addr) does not meet required length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'Address Line 1 (addr) contains invalid characters.';
				}
			break;

			case 'addr2':
				if($c && strlen($value) > 150){
					$c = false; $result['reason'] = 'Address Line 2 (addr2) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 3){
					$c = false; $result['reason'] = 'Address Line 2 (addr2) does not meet required length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'Address Line 2 (addr2) contains invalid characters.';
				}
				break;

			case 'city':
				if($c && strlen($value) > 75){
					$c = false; $result['reason'] = 'City (city) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 3){
					$c = false; $result['reason'] = 'City (city) does not meet required length.';
				}
				if($c && hasinvalidchars($value)){
					$c = false; $result['reason'] = 'City (city) contains invalid characters.';
				}
			break;

			case 'state':
				if($c && strlen($value) > 25){
					$c = false; $result['reason'] = 'State (state) exceeds maximum allowed length.';
				}
				if($c && strlen($value) < 2){
					$c = false; $result['reason'] = 'State (state) does not meet required length.';
				}
				if($c && !onlyalphas($value)){
					$c = false; $result['reason'] = 'State (state) contains invalid characters.';
				}
				break;

			case 'zip':
				if( strlen( $value ) < 5 || strlen( $value ) > 10 ) {
					$c = false; $result['reason'] = 'Zip (zip) is an invalid length.';
				}
				if( $c && hasinvalidzipchars( $value ) ){
					$c = false; $result['reason'] = 'Zip (zip) contains invalid characters.';
				}
			break;

			case 'dob':
				if(	 $c
					&& (
						strtotime($value) == -1
						|| strtotime($value) == false
					)
				){
					$c = false; $result['reason'] = 'Date of Birth (dob) is invalid.';
				}
			break;

			case 'gender':
				$allowedGenders = array('m','f','male','female','na','not applicable');
				if( $c && (
						!in_array(strtolower($value), $allowedGenders)
				)){
					$c = false; $result['reason'] = 'Gender is an invalid value.';
				}
			break;

			case 'landline':
				if($c && strlen($value) != 10){
					$c = false; $result['reason'] = 'Default Phone (landline) is an invalid length.';
				}
				if($c && !onlynos($value)){
					$c = false; $result['reason'] = 'Default Phone (landline) contains invalid characters.';
				}
			break;

			case 'cellphone':
				if($c && strlen($value) != 10){
					$c = false; $result['reason'] = 'Alternate Phone (cellphone) is an invalid length.';
				}
				if($c && !onlynos($value)){
					$c = false; $result['reason'] = 'Alternate Phone (cellphone) contains invalid characters.';
				}
			break;

		}

		if( $c ) {
			$result['valid'] = true;
			$result['reason'] = ucfirst( $fieldType ) .' passed validation.';
		}

		return $result;
	}

	public static function validateIncomingData( $feedParams, &$data ) {

		$leads = Leads::getInstance();

		$result = array();
		$result['valid'] = true;
		$result['errors'] = array();

		$requiredFields = explode( ';', $feedParams->required );
		$allowedFields = explode( ';', $feedParams->allowedFields );

		// Special handling for TurnTwo feed that cannot change what URL value is being sent to us
		if( !empty( $data['url'] ) && 'www.5minutemoney.co.uk,www.5minutemoney.co.uk' == $data['url'] ) {
			$data['url'] = 'http://www.5minutemoney.co.uk';
		}

		// Special handling for Digital Bulldogs feed that contains an invalid URL
		if( !empty( $data['url'] ) && 'https//www.instantcheckmate.com/register' == $data['url'] ) {
			$data['url'] = 'https://www.instantcheckmate.com/register';
		}

		// Fix cases where gender is set to a blank value
		if( !empty( $data['gender'] ) && ' ' == $data['gender'] ) {
			unset( $data['gender'] );
		}

		// Fix legacy lead timestamp field
		if( !empty( $data['leadstamp'] ) ) {
			$data['stamp'] = $data['leadstamp'];
		}

		// Remove non-numeric characters from phone numbers
		if( !empty( $data['landline'] ) ) {
			$data['landline'] = preg_replace( '/[^0-9]/', '', $data['landline'] );
		}
		if( !empty( $data['cellphone'] ) ) {
			$data['cellphone'] = preg_replace( '/[^0-9]/', '', $data['cellphone'] );
		}

		foreach( $requiredFields as $requiredKey ) {
			switch( $requiredKey ) {
				case 'phone':
					if( empty( $data['landline'] ) && empty( $data['cellphone'] ) ) {
						$result['valid'] = false;
						$result['errors'][] = 'A phone number is required, either landline or cellphone. They cannot both be empty.';
					}
				break;

				default:
					if( empty( $data[$requiredKey] ) ) {
						$result['valid'] = false;
						$result['errors'][] = ucfirst( $requiredKey ) . ' is a required field, and may not be empty.';
					}
				break;
			}
		}

		foreach( $allowedFields as $allowedField ) {
			if(	!empty( $data[$allowedField] ) ) {
				$validateResult = ProcessLeads::validateField( $allowedField, $data[$allowedField], $feedParams );
				if( !$validateResult['valid'] ) {
					$result['valid'] = false;
					$result['errors'][] = $validateResult['reason'];
				}
			}
		}

		if( in_array('url', $allowedFields ) ) { //URL is expected so trim it and store in the database.
			if( !empty( $data['url'] ) ) {
				$data['url'] = $leads->parseUrl( $data['url'] );
			} else {
				$data['url'] = '';
			}
		}

		// Required fields are missing or invalid, so don't bother with further checks
		if( !$result['valid'] ) {
			return $result;
		}

		if( !empty( $data['email'] ) ) {
			$exists = $leads->checkSuppression( $data['email'], null );
			if( $exists === true ) {
				$result['valid'] = false;
				$result['errors'][] = 'Email exists in our global suppression file.';
			}
		}

		if( !is_null( $feedParams->filterTypeUrl ) ) {
			$urlAcceptable = ProcessLeads::filterValue( $feedParams->filterTypeUrl, $data['url'], $feedParams->filterUrl );
			if( !$urlAcceptable ) {
				$result['valid'] = false;
				$result['errors'][] = 'URL is not allowed on this feed.';
			}
		}

		if( $feedParams->dedupeEmail && !empty( $data['email'] ) ) {
			$dupeCount = $leads->inboundCheckDuplicates( $feedParams->idFeedIn, 'email', $data, $feedParams->dedupeAcross );
			if( $dupeCount === null ) {
				$result['valid'] = false;
				$result['errors'][] = 'Database failure - could not check duplicate email.';
			} elseif( $dupeCount === true ) {
				$result['valid'] = false;
				$result['errors'][] = 'Duplicate email.';
			}
		}

		if( $feedParams->dedupeLandline && !empty( $data['landline'] ) ) {
			$dupeCount = $leads->inboundCheckDuplicates( $feedParams->idFeedIn, 'landline', $data, $feedParams->dedupeAcross );
			if( $dupeCount === null ) {
				$result['valid'] = false;
				$result['errors'][] = 'Database failure - could not check duplicate landline.';
			} elseif( $dupeCount === true ) {
				$result['valid'] = false;
				$result['errors'][] = 'Duplicate landline phone.';
			}
		}

		if( $feedParams->dedupeCellphone && !empty( $data['cellphone'] ) ) {
			$dupeCount = $leads->inboundCheckDuplicates( $feedParams->idFeedIn, 'cellphone', $data, $feedParams->dedupeAcross );
			if( $dupeCount === null ) {
				$result['valid'] = false;
				$result['errors'][] = 'Database failure - could not check duplicate cellphone.';
			} elseif( $dupeCount === true ) {
				$result['valid'] = false;
				$result['errors'][] = 'Duplicate cellphone.';
			}
		}

		if( !empty( $data['email'] ) && defined( 'SIFTLOGIC_APIKEY' ) && !is_null( $feedParams->filterTypeSiftLogic ) ) {
			if( ProcessLeads::filterValue( $feedParams->filterTypeSiftLogic, $data['url'], $feedParams->filterSiftLogic ) ) {
				require_once( INCLUDES . 'siftLogic.php' );
				$sl = new SiftLogic;
				if( $sl->check(
					$data['email'],
					!empty( $data['ip'] ) ? $data['ip'] : null,
					!empty( $data['fname'] ) ? $data['fname'] : null,
					!empty( $data['lname'] ) ? $data['lname'] : null,
					!empty( $data['addr'] ) ? $data['addr'] : null,
					!empty( $data['addr2'] ) ? $data['addr2'] : null,
					!empty( $data['city'] ) ? $data['city'] : null,
					!empty( $data['state'] ) ? $data['state'] : null,
					!empty( $data['zip'] ) ? $data['zip'] : null,
					!empty( $data['country'] ) ? $data['country'] : null,
					false
				) === false ) {
					$result['valid'] = false;
					$result['errors'][] = 'Email address was rejected by our third-party filters [SL]';
				}
			}
		}

		return $result;
	}

}
