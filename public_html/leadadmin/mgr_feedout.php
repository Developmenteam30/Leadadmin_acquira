<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );
require_once( INCLUDES . 'f_site.php' );

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "manageFeed":
			$c = true;
			$result['error'] = 'Failed when attempting to manage feeds.';
			$action = !empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

			//Validate Input
			if( $c && empty( $_REQUEST['label'] ) ) {
				$c = false;
				$result['error'] = 'Label cannot be empty.';
			}

			if( $c && empty( $_REQUEST['idCompany'] ) ) {
				$c = false;
				$result['error'] = 'Please select a company from the list.';
			}

			if( $c && empty( $_REQUEST['feedType'] ) ) {
				$c = false;
				$result['error'] = 'Please select a feed type from the list.';
			}

			if( $c && empty( $_REQUEST['postUrl'] ) ) {
				$c = false;
				$result['error'] = 'Please provide a post URL.';
			}

			$staticFields = '';
			if( !empty( $_REQUEST['staticFields_field'] ) && is_array( $_REQUEST['staticFields_field'] ) ) {
				$_REQUEST['staticFields_field'] = array_map( 'trim', $_REQUEST['staticFields_field'] );
				$_REQUEST['staticFields_value'] = array_map( 'trim', $_REQUEST['staticFields_value'] );
				$temp = array();
				for( $i = 0; $i < sizeOf( $_REQUEST['staticFields_field'] ); $i++ ) {
					// Strip our field delimiters of = and ; out of the input
					$temp[] = sprintf( '%s=%s',
						str_replace( array( '=', ';' ), '', $_REQUEST['staticFields_field'][$i] ),
						!empty( $_REQUEST['staticFields_value'][$i] ) ? str_replace( array( '=', ';' ), '', $_REQUEST['staticFields_value'][$i] ) : ''
					);
				}
				$staticFields = implode( ';', $temp );
			}

			$varFields = '';
			if( !empty( $_REQUEST['varFields'] ) && is_array( $_REQUEST['varFields'] ) ) {
				$_REQUEST['varFields'] = array_map( 'trim', $_REQUEST['varFields'] );
				$varFields = implode( ';', $_REQUEST['varFields'] );
			}

			$fieldMap = '';
			if( !empty( $_REQUEST['fieldMap'] ) && is_array( $_REQUEST['fieldMap'] ) ) {
				$_REQUEST['fieldMap'] = array_map( 'trim', $_REQUEST['fieldMap'] );
				$fieldMap = implode( ';', $_REQUEST['fieldMap'] );
			}

			$urlAssign = '';
			if( !empty( $_REQUEST['urlassignments_url'] ) && is_array( $_REQUEST['urlassignments_url'] ) ) {
				$_REQUEST['urlassignments_url'] = array_map( 'trim', $_REQUEST['urlassignments_url'] );
				$_REQUEST['urlassignments_id'] = array_map( 'trim', $_REQUEST['urlassignments_id'] );
				$temp = array();
				for( $i = 0; $i < sizeOf( $_REQUEST['urlassignments_url'] ); $i++ ) {
					// Strip our field delimiters of = and ; out of the input
					$temp[] = sprintf( '%s=%s',
						str_replace( array( '=', ';' ), '', $_REQUEST['urlassignments_url'][$i] ),
						!empty( $_REQUEST['urlassignments_id'][$i] ) ? str_replace( array( '=', ';' ), '', $_REQUEST['urlassignments_id'][$i] ) : ''
					);
				}
				$urlAssign = implode( ';', $temp );
			}

			if( $action == 'new' ) {

				if( $c ) {
					$pattern = '/^[a-z][a-z0-9_]*$/';
					if( !preg_match( $pattern, $_REQUEST['label'] ) ) {
						$c = false;
						$result['error'] = 'Label must start with a letter, can contain letters, numbers, and underscore only.';
					}
				}

				if( $c ) {
					//Label can not be already used
					$checkResult = $leads->checkOutboundFeedLabelExists( $_REQUEST['label'] );
					if( true === $checkResult ) {
						$c = false;
						$result['error'] = 'That feed label is already being used.';
					}
				}

				if( $c ) { //Add entry to the database.

					$idFeedOut = $leads->addOutboundFeed( array(
						'label' => $_REQUEST['label'],
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => $_REQUEST['idCompany'],
						'feedType' => empty( $_REQUEST['feedType'] ) ? 'curlPOST' : $_REQUEST['feedType'],
						'postUrl' => empty( $_REQUEST['postUrl'] ) ? null : $_REQUEST['postUrl'],
						'staticFields' => empty( $staticFields ) ? null : $staticFields,
						'varFields' => empty( $varFields ) ? null : $varFields,
						'fieldMap' => empty( $fieldMap ) ? null : $fieldMap,
						'cron' => 1,
						'cronTiming' => 1,
						'successString' => empty( $_REQUEST['successString'] ) ? null : $_REQUEST['successString'],
						'throttle' => 0,
						'urlassignments' => empty( $urlAssign ) ? null : $urlAssign,
						'dailyLimit' => empty( $_REQUEST['dailyLimit'] ) ? null : $_REQUEST['dailyLimit'],
						'delay' => empty( $_REQUEST['delay'] ) ? null : $_REQUEST['delay'],
						'queued' => 0,
						'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
					) );

					if( null === $idFeedOut ) {
						$c = false;
						$result['status'] = 0;
						$result['error'] = 'Failed to create new feed.';
					} else {
						$result['status'] = 1;
						$result['error'] = "Successfully created new feed #{$idFeedOut}.";
						$leads->auditLog( 'FEEDOUT:ADD', $idFeedOut );
					}
				}

			} else {

				$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

				if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
					$idCompany = LeadsSession::getCompanyId();
					if( empty( $idCompany ) ) {
						$idCompany = -9999;
					}
					if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
						$c = false;
						$result['error'] = 'Sorry, you do not have access to this feed.';
						break;
					}
				}

				if( $c ) {
					$feed = $leads->getOutboundFeed( $idFeedOut );
					if( empty( $feed ) ) {
						$c = false;
						$result['error'] = 'Sorry, this feed does not exist.';
					}
				}

				if( $c ){
					if( $_REQUEST['label'] != $feed->label ) { //Label is being altered.

						$pattern = '/^[a-z][a-z0-9_]*$/';
						if( !preg_match($pattern, $_REQUEST['label'] ) ) {
							$c = false; $result['error'] = 'Label must start with a letter, can can contain '
							.'letters, numbers, and underscore only.';
						}

						if( $c ) {
							//Label can not be already used
							$checkResult = $leads->checkOutboundFeedLabelExists( $_REQUEST['label'] );
							if( true === $checkResult ) {
								$c = false;
								$result['error'] = 'That feed label is already being used.';
							}
						}
					}

					$dbResult = $leads->updateOutboundFeed( $idFeedOut, array(
						'label' => $_REQUEST['label'],
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => $_REQUEST['idCompany'],
						'feedType' => empty( $_REQUEST['feedType'] ) ? 'curlPOST' : $_REQUEST['feedType'],
						'postUrl' => empty( $_REQUEST['postUrl'] ) ? null : $_REQUEST['postUrl'],
						'staticFields' => empty( $staticFields ) ? null : $staticFields,
						'varFields' => empty( $varFields ) ? null : $varFields,
						'fieldMap' => empty( $fieldMap ) ? null : $fieldMap,
						'successString' => empty( $_REQUEST['successString'] ) ? null : $_REQUEST['successString'],
						'urlassignments' => empty( $urlAssign ) ? null : $urlAssign,
						'dailyLimit' => empty( $_REQUEST['dailyLimit'] ) ? null : $_REQUEST['dailyLimit'],
						'delay' => empty( $_REQUEST['delay'] ) ? null : $_REQUEST['delay'],
						'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
					) );

					if( null === $dbResult ) {
						$c = false;
						$result['status'] = 0;
						$result['error'] = 'Error updating feed settings.';
					} else {
						$result['status'] = 1;
						$result['error'] = "Successfully updated feed #{$idFeedOut}.";
						$leads->auditLog( 'FEEDOUT:EDIT', $idFeedOut );
					}

				}

				if( $c ) {
					$result['status'] = 1;
					$result['error'] = 'Successfully updated feed.';
				}
			}
		break;

		case "managePopulation":
			$result['error'] = 'Failed when attempting to manage population.';

			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : '';
			$idAssoc = !empty( $_REQUEST['idAssoc'] ) ? $_REQUEST['idAssoc'] : '';
			$action = !empty( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					$result['error'] = 'Sorry, you do not have access to this feed.';
					break;
				}
			}

			// Validate inputs
			if( empty( $_REQUEST['idFeedIn'] ) ) {
				$result['error'] = 'Please select an incoming feed from the list.';
				break;
			}

			if( !isset( $_REQUEST['filterTypeUrl'] ) ) {
				$result['error'] = 'Please select a URL filter type.';
				break;
			}

			$filterUrl = '';
			if( !empty( $_REQUEST['filterUrl'] ) && is_array( $_REQUEST['filterUrl'] ) ) {
				$_REQUEST['filterUrl'] = array_map( 'trim', $_REQUEST['filterUrl'] );
				$filterUrl = implode( ';', $_REQUEST['filterUrl'] );
			}

			if( !empty( $_REQUEST['filterTypeUrl'] ) && empty( $filterUrl ) ) {
				$result['error'] = 'URL filtering is set to accept/reject, but no URLs were provided.';
				break;
			}

			if( !isset( $_REQUEST['filterTypeEmail'] ) ) {
				$result['error'] = 'Please select an email filter type.';
				break;
			}

			$filterEmail = '';
			if( !empty( $_REQUEST['filterEmail'] ) && is_array( $_REQUEST['filterEmail'] ) ) {
				$_REQUEST['filterEmail'] = array_map( 'trim', $_REQUEST['filterEmail'] );
				$filterEmail = implode( ';', $_REQUEST['filterEmail'] );
			}

			if( !empty( $_REQUEST['filterTypeEmail'] ) && empty( $filterEmail ) ) {
				$result['error'] = 'Email filtering is set to accept/reject, but no emails were provided.';
				break;
			}

			if( !isset( $_REQUEST['filterTypeListcode'] ) ) {
				$result['error'] = 'Please select a listcode filter type.';
				break;
			}

			$filterListcode = '';
			if( !empty( $_REQUEST['filterListcode'] ) && is_array( $_REQUEST['filterListcode'] ) ) {
				$_REQUEST['filterListcode'] = array_map( 'trim', $_REQUEST['filterListcode'] );
				$filterListcode = implode( ';', $_REQUEST['filterListcode'] );
			}

			if( !empty( $_REQUEST['filterTypeListcode'] ) && empty( $filterListcode ) ) {
				$result['error'] = 'Listcode filtering is set to accept/reject, but no listcodes were provided.';
				break;
			}

			$forceUrlList = '';
			if( !empty( $_REQUEST['forceUrlList_original'] ) && is_array( $_REQUEST['forceUrlList_original'] ) ) {
				$_REQUEST['forceUrlList_original'] = array_map( 'trim', $_REQUEST['forceUrlList_original'] );
				$_REQUEST['forceUrlList_altered'] = array_map( 'trim', $_REQUEST['forceUrlList_altered'] );
				$temp = array();
				for( $i = 0; $i < sizeOf( $_REQUEST['forceUrlList_original'] ); $i++ ) {
					if( !empty( $_REQUEST['forceUrlList_original'][$i] ) && !empty( $_REQUEST['forceUrlList_altered'][$i] ) ) {
						// Strip our field delimiters of = and ; out of the input
						$temp[] = sprintf( '%s=%s',
							str_replace( array( '=', ';' ), '', $_REQUEST['forceUrlList_original'][$i] ),
							!empty( $_REQUEST['forceUrlList_altered'][$i] ) ? str_replace( array( '=', ';' ), '', $_REQUEST['forceUrlList_altered'][$i] ) : ''
						);
					}
				}
				$forceUrlList = implode( ';', $temp );
			}

			if( !empty( $_REQUEST['forceUrl'] ) && empty( $forceUrlList ) ) {
				$result['error'] = 'URL forcing is set to enabled, but no URLs were provided.';
				break;
			}

			if($action == 'new'){

				$idAssoc = $leads->addPopulation( array(
					'idFeedIn' => $_REQUEST['idFeedIn'],
					'idFeedOut' => $_REQUEST['idFeedOut'],
					'enabled' => 1,
					'filterTypeUrl' => !empty( $_REQUEST['filterTypeUrl'] ) ? $_REQUEST['filterTypeUrl'] : null,
					'filterUrl' => !empty( $filterUrl ) ? $filterUrl : null,
					'filterTypeEmail' => !empty( $_REQUEST['filterTypeEmail'] ) ? $_REQUEST['filterTypeEmail'] : null,
					'filterEmail' => !empty( $filterEmail ) ? $filterEmail : null,
					'filterTypeListcode' => !empty( $_REQUEST['filterTypeListcode'] ) ? $_REQUEST['filterTypeListcode'] : null,
					'filterListcode' => !empty( $filterListcode ) ? $filterListcode : null,
					'forceUrlList' => !empty( $forceUrlList ) ? $forceUrlList : null,
					'forceUrl' => !empty( $_REQUEST['forceUrl'] ) ? 1 : 0,
					'livedata' => !empty( $_REQUEST['livedata'] ) ? 1 : 0,
				) );

				if( empty( $idAssoc ) ) {
					$result['error'] = 'Database failure, could not create population.';
					break;
				}

				$leads->auditLog( 'FEEDOUT:POP:ADD', $idAssoc );

				$result['status'] = 1;
				$result['error'] = 'Successfully created new population parameter.';
				break;

			} else {

				$dbResult = $leads->updatePopulation( $_REQUEST['idAssoc'], array(
					'idFeedIn' => $_REQUEST['idFeedIn'],
					'idFeedOut' => $_REQUEST['idFeedOut'],
					'filterTypeUrl' => !empty( $_REQUEST['filterTypeUrl'] ) ? $_REQUEST['filterTypeUrl'] : null,
					'filterUrl' => !empty( $filterUrl ) ? $filterUrl : null,
					'filterTypeEmail' => !empty( $_REQUEST['filterTypeEmail'] ) ? $_REQUEST['filterTypeEmail'] : null,
					'filterEmail' => !empty( $filterEmail ) ? $filterEmail : null,
					'filterTypeListcode' => !empty( $_REQUEST['filterTypeListcode'] ) ? $_REQUEST['filterTypeListcode'] : null,
					'filterListcode' => !empty( $filterListcode ) ? $filterListcode : null,
					'forceUrlList' => !empty( $forceUrlList ) ? $forceUrlList : null,
					'forceUrl' => !empty( $_REQUEST['forceUrl'] ) ? 1 : 0,
					'livedata' => !empty( $_REQUEST['livedata'] ) ? 1 : 0,
				) );

				if( empty( $dbResult ) ) {
					$result['error'] = 'Database failure, could not create population.';
					break;
				}

				$leads->auditLog( 'FEEDOUT:POP:EDIT', $_REQUEST['idAssoc'] );

				$result['status'] = 1;
				$result['error'] = 'Successfully edited this population parameter.';
				break;

			}
		break;
		case "managePopulationParam":
			$c = true; $result['error'] = 'Failed when attempting to manage population params.';
			switch( $_REQUEST['action'] ) {
				case "toggle":
					if( $c ) {
						$popSet = $leads->getPopulationSetting( $_REQUEST['idAssoc'] );
						if( empty( $popSet ) ) {
							$c = false;
							$result['error'] = 'Database failure - could not fetch population information for editing.';
						}
					}
					if( $c ) {
						if( $popSet->enabled ) {
							$enabled = 0;
						} else {
							$enabled = 1;
						}

						$alterResult = $leads->updatePopulation( $_REQUEST['idAssoc'], array( 'enabled' => $enabled ) );

						if( empty( $alterResult ) ) {
							$c = false;
							$result['error'] = 'Unable to update population.';
						} else {
							$leads->auditLog( 'FEEDOUT:POP:STATE', $popSet->idAssoc . ':' . ( $enabled ? 'ENABLED' : 'DISABLED' ) );
						}
					}
					if( $c ) {
						$result['error'] = 'Successfully toggled population.';
					}
				break;
			}
			if( $c ) {
				$result['status'] = 1;
			}
		break;

		case 'manageFeedParam':
			$c = true;
			$result['error'] = 'Failed when attempting to manage feed params.';

			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
					break;
				}
			}

			switch( $_REQUEST['action'] ) {
				case "toggle":
					if( $c ) {
						$feed = $leads->getOutboundFeed( $_REQUEST['idFeedOut'] );
						if( empty( $feed ) ) {
							$c = false;
							$result['error'] = 'Database failure - could not fetch feed for editing.';
						}
					}
					if( $c ) {
						switch( $_REQUEST['param'] ) {
							case 'cron':
								if( !empty( $feed->cron ) ) {
									$cron = 0;
								} else {
									$cron = 1;
								}

								$alterResult = $leads->updateOutboundFeed( $feed->idFeedOut, array( 'cron' => $cron ) );
								if( empty( $alterResult ) ) {
									$c = false;
									$result['error'] = 'Unable to update the database.';
								} else {
									$leads->auditLog( 'FEEDOUT:CRON', $feed->idFeedOut . ':' . ( $cron ? 'RUNNING' : 'PAUSED' ) );
								}
							break;
							default:
								$c = false;
								$result['error'] = 'Could not alter feed, invalid parameter';
							break;
						}
					}
					if( $c ) {
						$result['error'] = 'Successfully toggled feed.';
					}
				break;
			}
			if( $c ) {
				$result['status'] = 1;
			}
		break;
	}
	echo json_encode($result);
	exit;
}

if( isset( $_REQUEST['d'] ) ) {
	switch( $_REQUEST['d'] ) {

		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

		case 'dialog_testrecord':
			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			if( empty( $feed ) ) {
				print '<p>Sorry, the feed you specified does not exist.</p>';
				break;
			}

			require_once( SITE_ROOT . '/pushLead/_f_onlms.php' );
			$settings['testing'] = 0;
			$settings['testrecord'] = 1;
			$leaddata = array(
				'stamp' => date( 'Y-m-d H:i:s' ),
				'url' => 'http://www.' . SITE_URL,
				'ip' => '1.2.3.4',
				'email' => 'johndoe@somewhere.com',
				'fname' => 'John',
				'lname' => 'Doe',
				'addr' => '123 Main St',
				'addr2' => '',
				'city' => 'New York',
				'state' => 'NY',
				'zip' => '10003',
				'dob' => '1970-01-01',
				'gender' => 'M',
				'landline' => '2125551212',
				'cellphone' => '2125559999',
				'country' => 'US',
				'listcode' => '',
			);

			print "<p><strong>HTTP Method:</strong> " . $feed->feedType . "</p>";

			$response = runlead( $leaddata, $feed );

			print "<strong>Query String:</strong> " . htmlspecialchars( $response['querystring'] ) . "</p>";

			print "<strong>Response:</strong> " . htmlspecialchars( stripslashes( $response['text'] ) ) . "</p>";

			$leads->auditLog( 'FEEDOUT:TEST-RECORD', $idFeedOut );

			break;

		case 'dialog_clearqueue':
			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			if( empty( $feed ) ) {
				print '<p>Sorry, the feed you specified does not exist.</p>';
				break;
			}

			$jobId = $leads->addJob( 'clear-outbound-queue', $idFeedOut, serialize( array( 'label' => $feed->label ) ), '', 0 );
			if( null === $jobId ) {
				print '<p>ERROR: Cannot add job to database.</p>';
			} else {
				$leads->auditLog( 'FEEDOUT:CLEAR-QUEUE', $jobId );
				printf( '<p>Clear outbound queue job submitted.</p><p><a class="btn btn-primary" href="/leadadmin/mgr_job.php?jobId=%d&amp;count=0">View results</a></p>', $jobId );
			}

			break;

		case 'dialog_editfeed':
			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$id = 'edit_feed';
			$mode = 'edit';
			$feed = $leads->getOutboundFeed( $idFeedOut );
			if( empty( $feed ) ){
?>
<p>Database failure - could not fetch requested feed information.</p>
<?php
				exit;
			}

			if(!is_null($feed->staticFields) && $feed->staticFields != ''){
				$staticFields = explode( ";", $feed->staticFields );
			}
			$varFields = explode( ";", $feed->varFields );
			$fieldMap = explode( ";", $feed->fieldMap );
		case 'dialog_newfeed':
			if( empty( $idFeedOut ) ) {
				$idFeedOut = '';
			}
			if( empty( $id ) ) {
				$id = 'new_feed';
			}
			if( empty( $mode ) ) {
				$mode = 'new';
			}
			if( 'new' === $mode && !empty( $_REQUEST['idFeedOut'] ) ) {
				$idFeedOut = $_REQUEST['idFeedOut'];
				$feed = $leads->getOutboundFeed( $idFeedOut );
				if( !empty( $feed ) ) {
					$feed->label = '';
					$feed->description = '';
					if( !empty( $feed->staticFields ) ) {
						$staticFields = explode( ";", $feed->staticFields );
					}
					$varFields = explode( ";", $feed->varFields );
					$fieldMap = explode( ";", $feed->fieldMap );
				}
			}
			$feedProps = array('idFeedOut', 'label', 'description', 'idCompany', 'feedType', 'postUrl', 'successString', 'status', 'dailyLimit', 'delay' );
			foreach($feedProps as $feedProp){
				if(isset($feed)){
					${"feed_".$feedProp} = $feed->$feedProp;
				}elseif(isset($_REQUEST[$feedProp])){
					${"feed_".$feedProp} = $_REQUEST[$feedProp];
				}else {
					${"feed_".$feedProp} = '';
				}
			}

			$explodableProperties = array(
				'staticFields', 'varFields', 'fieldMap', 'urlassignments'
			);
			foreach($explodableProperties as $eP){
				if( !isset($_REQUEST[$eP]) ){
					if(!isset($feed->$eP) || $feed->$eP == ''){
						${"feed_".$eP} = array();
					} else {
						${"feed_".$eP} = explode(";", $feed->$eP);
					}
				} else {
					if( $_REQUEST[$eP] == '' ) {
						${"feed_".$eP} = array();
					} else {
						${"feed_".$eP} = explode(";", $_REQUEST[$eP]);
					}
				}
			}

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$companies = array( $leads->getCompany( $feed_idCompany ) );
			} else {
				$companies = $leads->getCompanies();
			}
?>
<form id="<?php echo $id; ?>">
<input type='hidden' name='idFeedOut' value='<?php echo $feed_idFeedOut; ?>' />
<input type="hidden" name="a" value="manageFeed" />
<input type="hidden" name="action" value="<?php echo $mode; ?>" />
<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td><p>Feed Label</p></td>
		<td>
			<p>
				<input type='text' name='label' value='<?php echo $feed_label; ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Description</p></td>
		<td>
			<p>
				<input type='text' name='description' value='<?php echo htmlentities( $feed_description ); ?>' class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Company</p></td>
		<td>
			<p>
				<?php if($companies === false){ ?>
				Database failure - could not fetch company list
				<?php } elseif(!is_object($companies) && $companies == 0){ ?>
				There are no companies in the database. Please create a company before
				creating a feed.
				<?php } else { ?>
				<select name='idCompany'>
				<option></option>
				<?php foreach($companies as $company){ ?>
					<option value='<?php echo $company->idCompany; ?>'
					<?php if($company->idCompany == $feed_idCompany){ 
					?>selected='selected'<?php } ?>
					><?php echo htmlentities( $company->name ); ?></option>
				<?php } ?>
				</select>
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Feed Type</p></td>
		<td>
			<p>
				<select name='feedType'>
					<option value='curlGET'<?php if($feed_feedType == 'curlGET'){ ?>selected='selected'<?php } ?>>HTTP GET</option>
					<option value='curlPOST'<?php if($feed_feedType == 'curlPOST'){ ?>selected='selected'<?php } ?>>HTTP POST</option>
					<option value='JSON'<?php if($feed_feedType == 'JSON'){ ?>selected='selected'<?php } ?>>JSON</option>
					<option value='csvString'<?php if($feed_feedType == 'csvString'){ ?>selected='selected'<?php } ?>>CSV string</option>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Post URL</p></td>
		<td>
			<p>
				<input type='text' name='postUrl' value='<?php echo $feed_postUrl; ?>' class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Static Fields</p></td>
		<td>
			<p>
				These are fields that are assigned values specific to this feed, usually provided by the receiving
				client.
			</p>
			<p>
				<a href='#' class='nonLink' onclick='element("staticFields_container", "staticField", {});'
				>Add New Static Field</a>
			</p>
			<div>
				<div id='staticFields_container' >
				<?php foreach($feed_staticFields as $sF){ 
					$valuePair = explode("=", $sF);
				?>
					<div>
						<input type='text' 
							name='staticFields_field[]'
							value='<?php echo $valuePair[0]; ?>'
						/> = <input type='text'
							name='staticFields_value[]'
							value='<?php echo $valuePair[1]; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Mapped Fields</p></td>
		<td>
			<p>These are fields that are assigned values for each lead. Enter the field name from the receiving 
				client's API spec, and select the lead value to be mapped from the drop-down.
			</p>
			<p>
				<a href='#' class='nonLink' onclick='element("varFields_container", "varField", {});'
				>Add New Mapped Field</a>
			</p>
			<div>
				<div id='varFields_container' >
				<?php $sFCount = 0; foreach($feed_varFields as $vF){ ?>
					<div>
						API Field: <input type='text' 
							name='varFields[]'
							value='<?php echo $vF; ?>'
						/> Mapped To: <select
							name='fieldMap[]'
						>
							<?php foreach($recordFields as $rF){ ?>
							<option value='<?php echo $rF; ?>'
								<?php if($feed_fieldMap[$sFCount] == $rF){ echo "selected='selected'"; } ?>
							><?php echo $rF; ?></option>
							<?php } ?>
							<?php foreach($additionalMapFields as $aF){ ?>
							<option value='<?php echo $aF; ?>'
								<?php if($feed_fieldMap[$sFCount] == $aF){ echo "selected='selected'"; } ?>
							><?php echo $aF; ?></option>
							<?php } ?>
						</select>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php $sFCount++; } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>URL Assignments</p></td>
		<td>
			<p>
				If you utilize the urlAssign mapped field, when the feed is processing it will populate the mapped 
				field with values according to what you set here, that way you can have multiple different
				unique id's per url within the same feed.
			</p>
			<p>
				<a href='#' class='nonLink' onclick='element("urlassignments_container", "urlassignment", {});'
				>Add New URL Assignment</a>
			</p>
			<div>
				<div id='urlassignments_container'>
				<?php foreach($feed_urlassignments as $uA){ 
					$valuePair = explode("=", $uA);
				?>
					<div>
						<input type='text' 
							name='urlassignments_url[]'
							value='<?php echo $valuePair[0]; ?>'
						/> = <input type='text'
							name='urlassignments_id[]'
							value='<?php echo $valuePair[1]; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Success String</p></td>
		<td>
			<p>This is the smallest form of the success response from the receiving client's API spec.</p>
			<p>
				<input type='text' name='successString' value='<?php echo htmlentities( $feed_successString ); ?>' class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Daily Feed Limit</p></td>
		<td>
			<p>Leave blank for no limit (default). If a value is supplied here, the feed will stop sending records after the daily limit is reached.</p>
			<p>
				<input type='text' name='dailyLimit' value='<?php echo $feed_dailyLimit; ?>' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Feed Delay</p></td>
		<td>
			<p>Leave blank for no delay (default). If a value is supplied here, records will sit in the queue for this number of minutes before being processed.</p>
			<p>
				<input type='text' name='delay' value='<?php echo $feed_delay; ?>' /> Minutes
			</p>
		</td>
	</tr>
	<tr>
	   <td><p>Feed Status</p></td>
	   <td>
		   <p>
			   <input type='radio' name='status' value='active' <?php if( empty( $feed_status ) || 'active' == $feed_status ) {?>checked='checked'<?php } ?>/> Active (Visible)<br/>
			   <input type='radio' name='status' value='hidden' <?php if( 'hidden' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Hidden)<br/>
			   <input type='radio' name='status' value='retired' <?php if( 'retired' == $feed_status ) { ?>checked='checked'<?php } ?>/> Retired
		   </p>
	   </td>
	</tr>
</table>
</form>
<?php
		break;

		case 'dialog_urlreport':
			$idFeedOut = !empty( $_REQUEST['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			if( empty( $_REQUEST['submit'] ) ) {
				$_REQUEST['dateStart'] = date("Y-m-d");
				$_REQUEST['dateEnd'] = date("Y-m-d", strtotime('Tomorrow'));
				$_REQUEST['urlList'] = array();
				$_REQUEST['breakdown'] = 'day';
				$_REQUEST['sort'] = 'date';
			}
			$_REQUEST['dateStart'] = !empty( $_REQUEST['dateStart'] ) ? $_REQUEST['dateStart'] : '';
			$_REQUEST['dateEnd'] = !empty( $_REQUEST['dateEnd'] ) ? $_REQUEST['dateEnd'] : '';
			$_REQUEST['urlList'] = !empty( $_REQUEST['urlList'] ) && is_array( $_REQUEST['urlList'] ) ? $_REQUEST['urlList'] : array();
			$_REQUEST['breakdown'] = !empty( $_REQUEST['breakdown'] ) ? $_REQUEST['breakdown'] : 'day';
			$_REQUEST['sort'] = !empty( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : 'date';

			$feed = $leads->getOutboundFeed( $idFeedOut );

			if($feed === false){
?>
<p>Database failure - could not fetch feed information.</p>
<?php 
			} elseif(!is_object($feed) && $feed == 0){
?>
<p>Error fetching feed information - feed does not exist.</p>
<?php
			} else {
?>
<p>URL Report from Feed (ID:<?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?></p>
<form id="form-urlreport">
<input type="hidden" name="idFeedOut" value="<?php echo $feed->idFeedOut; ?>" />
<input type="hidden" name="d" value="dialog_urlreport" />
<input type="hidden" name="submit" value="submit" />
<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td colspan='2'><p class='aCenter'>Report Settings</p></td>
	</tr>
	<tr>
		<td>
			<p>Period</p>
		</td>
		<td>
			<p>
				Period goes from midnight of the first date to midnight of the second date. Leave blank to select
				from all time records. (This could take a long time.)
			</p>
			<p>
				<input type='text' name='dateStart' class='dateSelector' value='<?php echo date("Y-m-d"); ?>' />
				to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>' />
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				URLs
			</p>
		</td>
		<td>
			<p>
				URLs to limit the selection by. Leave blank to select all records regardless of URL.
			</p>
			<p>
<?php
				$urls = $leads->getOutboundURLDates( $idFeedOut );
				if( $urls && is_array( $urls ) ) {
					printf( "<select multiple=\"multiple\" name=\"urlList[]\" size=\"%d\">\n", sizeOf( $urls ) );
					foreach( $urls as $url ) {
						printf( "<option value=\"%s\">%s (%s)</option>\n", htmlspecialchars( $url['url'] ), htmlspecialchars( $url['url'] ), $url['date'] );
					}
					print "</select>\n";
				}
?>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Count By
			</p>
		</td>
		<td>
			<p><select name="breakdown"><option value="day" selected="selected">Day</option><option value="month">Month</option><option value="year">Year</option><option value="total">Total</option</select></p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Sort By
			</p>
		</td>
		<td>
			<p><select name="sort"><option value="date" selected="selected">Date</option><option value="url">URL</option><option value="count">Count</option></select></p>
		</td>
	</tr>
</table>
<?php

			if( !empty( $_REQUEST['submit'] ) ) {

				$stats = $leads->getOutboundURLStatsReport( $_REQUEST['idFeedOut'], $_REQUEST['urlList'], $_REQUEST['breakdown'], $_REQUEST['dateStart'], $_REQUEST['dateEnd'], $_REQUEST['sort'] );

				if( empty( $stats ) ) {
?>
<p>No records found.</p>
<?php
				} else {

					$fileLink = 'exports/' . $feed->label."_".time().".csv";
					$filePath = ADMIN_ROOT.$fileLink;
					$file = fopen($filePath, "w");
					if(!file_exists($filePath)){
?>
<p>Failed to create CSV report file.</p>
<?php
					} else {
			 			fputcsv( $file, array( 'URL', 'Date', 'Accepted', 'Rejected' ) );
						print "<table class=\"table table-bordered table-condensed table-striped\">\n";
						print "<thead>\n";
						print "\t<tr>\n";
						print "\t<th>URL</th>\n";
						print "\t<th>Date</th>\n";
						print "\t<th>Accepted</th>\n";
						print "\t<th>Rejected</th>\n";
						print "\t</tr>\n";
						print "</thead>\n";
						print "<tbody>\n";
						print "\t<tr>\n";
						foreach( $stats as $stat ) {
							print "\t<tr>\n";
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['url'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['date'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['accepted'] ) );
							printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['rejected'] ) );
							print "\t</tr>\n";
							fputcsv( $file, array( $stat['url'], $stat['date'], $stat['accepted'], $stat['rejected'] ) );
						}
						fclose($file);
						print "</tbody>\n";
						print "</table>\n";
						printf( '<p><a <a class="btn btn-primary" href="%s">Export this report</a></p>', $fileLink );
					}
				}
			}
		}
		break;

		case 'staticField':
			$e = $_REQUEST['e'];
?>
<div>
	<input type='text' name='staticFields_field[]' value='' /> = <input type='text' name='staticFields_value[]' value='' />
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'urlassignment':
			$e = $_REQUEST['e'];
?>
<div>
	<input type='text' name='urlassignments_url[]' value='' placeholder='URL'/> = <input type='text' name='urlassignments_id[]' value='' placeholder='Unique ID' />
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'varField':
			$e = $_REQUEST['e'];
?>
<div>
	API Field: <input type='text' name='varFields[]' value='' /> Mapped To: <select	name='fieldMap[]'>
		<?php foreach($recordFields as $rF){ ?>
		<option value='<?php echo $rF; ?>'><?php echo $rF; ?></option>
		<?php } ?>
		<?php foreach($additionalMapFields as $aF){ ?>
		<option value='<?php echo $aF; ?>'><?php echo $aF; ?></option>
		<?php } ?>
	</select>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;
		case 'dialog_editpopsetting':
			$mode = 'edit';
			$idAssoc = !empty( $_REQUEST['idAssoc'] ) ? $_REQUEST['idAssoc'] : 0;
			$popset = $leads->getPopulationSetting( $idAssoc );
			if( empty( $popset ) ) {
?>
<p>Database failure - could not fetch population setting.</p>
<?php
				exit;
			}
		case 'dialog_newpopsetting':
			if( empty( $mode ) ) {
				$mode = 'new';
			}
			$populationProperties = array(
				'idAssoc',
				'idFeedOut',
				'idFeedIn',
				'filterTypeUrl',
				'filterTypeEmail',
				'filterTypeListcode',
				'forceUrl',
				'livedata',
			);
			foreach( $populationProperties as $pP ) {
				if( isset( $popset ) ) {
					${"popset_".$pP} = $popset->$pP;
				} elseif( isset( $_REQUEST[$pP] ) ) {
					${"popset_".$pP} = $_REQUEST[$pP];
				} else {
					${"popset_".$pP} = '';
				}
			}
			$explodableProperties = array(
				'filterUrl',
				'filterEmail',
				'filterListcode',
				'forceUrlList',
			);
			foreach( $explodableProperties as $eP ) {
				if( !isset( $_REQUEST[$eP] ) ){
					if( !isset( $popset->$eP ) ) {
						${"popset_".$eP} = array();
					} else {
						${"popset_".$eP} = explode( ";", $popset->$eP );
					}
				} else {
					if( $_REQUEST[$eP] == '' ) {
						${"popset_".$eP} = array();
					} else {
						${"popset_".$eP} = explode( ";", $_REQUEST[$eP] );
					}
				}
			}
			$feed = $leads->getOutboundFeed( $popset_idFeedOut );

			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$incomingFeeds = $leads->getInboundFeeds( null, 'active' );
			} else {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				$incomingFeeds = $leads->getInboundFeeds( $idCompany, 'active' );
			}
?>
<form id="<?php echo $mode; ?>_pop">
<input type="hidden" name="idAssoc"	value="<?php echo $popset_idAssoc; ?>" />
<input type="hidden" name="idFeedOut" value="<?php echo $popset_idFeedOut; ?>" />
<input type="hidden" name="a" value="managePopulation" />
<input type="hidden" name="action" value="<?php echo $mode; ?>" />
<table class="table table-bordered table-condensed table-striped">
<?php if( 'edit' === $mode ) { ?>
	<tr>
		<td>Population ID</td>
		<td><?php echo $popset_idAssoc; ?></td>
	</tr>
<?php } ?>
	<tr>
		<td><p>Incoming Feed (To Populate From)</p></td>
		<td>
			<p>
				<select name="idFeedIn">
				<option></option>
				<?php
				$lastCompany = '';
				foreach( $incomingFeeds as $fI ) {
					if( $lastCompany !== $fI->name ) {
						$lastCompany = $fI->name;
						printf( '<optgroup label="%s">' . PHP_EOL,
							htmlentities( $fI->name, ENT_QUOTEs | ENT_HTML5 )
						);
					}
				?>
					<option value='<?php echo $fI->idFeedIn; ?>'
						<?php if($fI->idFeedIn == $popset_idFeedIn){ echo "selected='selected'"; } ?>
					>(<?php echo $fI->idFeedIn; ?>) <?php echo htmlentities( $fI->label ); ?></option>
				<?php
					if( $lastCompany !== $fI->name ) {
						$lastCompany = $fI->name;
						print '</optgroup>' . PHP_EOL;
					}
				}
				?>
				</select>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>URL Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into
				the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='filterTypeUrl'
					id='filterTypeUrl_disabled'
					value=''
					<?php if(
						empty($popset_filterTypeUrl)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeUrl').hide(); <?php 
					?>$('#filterUrl_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='filterTypeUrl'
					id='filterTypeUrl_accept'
					value='accept'
					<?php if($popset_filterTypeUrl == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeUrl').show(); <?php 
					?>$('#filterUrl_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='filterTypeUrl'
					id='filterTypeUrl_reject'
					value='reject'
					<?php if($popset_filterTypeUrl == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeUrl').show(); <?php 
					?>$('#filterUrl_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='toggler_filterTypeUrl' 
				style='display:<?php 
					if(empty($popset_filterTypeUrl)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following urls:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("filterUrl_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Url" });'
					>Add New URL to <span id='filterUrl_descriptor'></span></a>
					| <a href='#' class='nonLink'
						onclick='element("filterUrl_multipleInsert"<?php
						?>, "element_multifilter"<?php
						?>, { "type": "Url" });'
					>Add Multiple</a>
				</p>
				<div id='filterUrl_multipleInsert'></div>
				<div id='filterUrl_container'>
				<?php foreach($popset_filterUrl as $filterUrl){ ?>
					<div>
						<input type='text' 
							name='filterUrl[]'
							value='<?php echo $filterUrl; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Email Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, email domains that are listed here are the only ones that will be 
				accepted into the feed. Using the 'Reject' option, all email domains will be accepted, except 
				the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='filterTypeEmail'
					id='filterTypeEmail_disabled'
					value=''
					<?php if(
						empty($popset_filterTypeEmail)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeEmail').hide(); <?php 
					?>$('#filterEmail_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='filterTypeEmail'
					id='filterTypeEmail_accept'
					value='accept'
					<?php if($popset_filterTypeEmail == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeEmail').show(); <?php 
					?>$('#filterEmail_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='filterTypeEmail'
					id='filterTypeEmail_reject'
					value='reject'
					<?php if($popset_filterTypeEmail == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeEmail').show(); <?php 
					?>$('#filterEmail_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='toggler_filterTypeEmail' 
				style='display:<?php 
					if(empty($popset_filterTypeEmail)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following email domains:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("filterEmail_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Email"});'
					>Add New Email Domain to <span id='filterEmail_descriptor'></span></a>
				</p>
				<div id='filterEmail_container'>
				<?php foreach($popset_filterEmail as $filterEmail){ ?>
					<div>
						<input type='text' 
							name='filterEmail[]'
							value='<?php echo $filterEmail; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Listcode Filter Options</p></td>
		<td>
			<p>
				Using the 'Accept' option, listcodes that are listed here are the only ones that will be 
				accepted into the feed. Using the 'Reject' option, all listcodes will be accepted, except 
				the ones listed here.
			</p>
			<p>
				<input type='radio' 
					name='filterTypeListcode'
					id='filterTypeListcode_disabled'
					value=''
					<?php if(
						empty($popset_filterTypeListcode)
					){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeListcode').hide(); <?php 
					?>$('#filterListcode_descriptor').html('Do nothing with');"
				/> Disabled<br />
				<input type='radio' 
					name='filterTypeListcode'
					id='filterTypeListcode_accept'
					value='accept'
					<?php if($popset_filterTypeListcode == 'accept'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeListcode').show(); <?php 
					?>$('#filterListcode_descriptor').html('Accept');"
				/> Accept<br />
				<input type='radio' 
					name='filterTypeListcode'
					id='filterTypeListcode_reject'
					value='reject'
					<?php if($popset_filterTypeListcode == 'reject'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_filterTypeListcode').show(); <?php 
					?>$('#filterListcode_descriptor').html('Reject');"
				/> Reject<br />
			</p>
			<div id='toggler_filterTypeListcode' 
				style='display:<?php 
					if(empty($popset_filterTypeListcode)){ echo "none"; }
					else { echo "block"; } 
				?>;'
			>
				<p>The following email domains:</p>
				<p>
					<a href='#' class='nonLink' 
		onclick='element("filterListcode_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Listcode"});'
					>Add New Listcode to <span id='filterListcode_descriptor'></span></a>
				</p>
				<div id='filterListcode_container'>
				<?php foreach($popset_filterListcode as $filterListcode){ ?>
					<div>
						<input type='text' 
							name='filterListcode[]'
							value='<?php echo $filterListcode; ?>'
						/>
						<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
					</div>
				<?php } ?>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Force URL Options</p></td>
		<td>
			<p>
				Utilizing 'URL Forcing' changes the url listed in the incoming feed to a completely different URL
				for use in the outgoing feed. 
			</p>
			<p>
				<input type='radio' 
					name='forceUrl'
					id='forceUrl_disabled'
					value='0'
					<?php if($popset_forceUrl != '1'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_forceUrlList').hide();"
				/> Disabled<br />
				<input type='radio' 
					name='forceUrl'
					id='forceUrl_enabled'
					value='1'
					<?php if($popset_forceUrl == '1'){ ?>
					checked='checked'
					<?php } ?>
					onclick="$('#toggler_forceUrlList').show();"
				/> Enabled
			</p>
			<div id='toggler_forceUrlList' 
				style='display:<?php 
					if($popset_forceUrl){ echo "block"; }
					else { echo "none"; } 
				?>;'
			>
				<p>
					Enter 'all' in the url field to force all non-specified urls to be changed to the new
					url. Other specified urls will be changed to the listed forced url.
				</p>
				<div>
					<p>Force Populating URLs to: </p>
					<p>
						<a href='#' class='nonLink' onclick='element("filterUrlList_container", "element_forceUrl", { "e": "<?php echo $e; ?>"});'
						>Add URL To Force</a>
					</p>
					<div id='filterUrlList_container'>
						<?php foreach($popset_forceUrlList as $fU){ 
							$valuePair = explode("=", $fU);
						?>
						<div>
							URL: <input type='text' 
								name='forceUrlList_original[]'
								value='<?php echo $valuePair[0]; ?>'
							/> Will be populated as: <input type='text'
								name='forceUrlList_altered[]'
								value='<?php echo $valuePair[1]; ?>'
							>
							<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
						</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</td>
	</tr>
	<tr>
		<td><p>Live data feed</p></td>
		<td>
			<p>
				Incoming records will be sent to this provider in REAL TIME as they come in.  Do not use this option unless authorized.  Most feeds have this option disabled.
			</p>
			<p>
				<input type='radio' name='livedata' id='livedata_disabled' value='0'
					<?php if($popset_livedata != '1'){ ?> checked='checked' <?php } ?>/> Disabled (DEFAULT)<br />
				<input type='radio' name='livedata' id='livedata_enabled' value='1'
					<?php if($popset_livedata == '1'){ ?> checked='checked' <?php } ?>/> Enabled
			</p>
		</td>
	</tr>
</table>
</form>
<script type="text/javascript">
$("#new_pop select[name='idFeedIn'], #edit_pop select[name='idFeedIn']").select2({
    placeholder: "Select an incoming feed",
    allowClear: true
});
</script>
<?php
		break;

		case 'element_filter':
			$t = $_REQUEST['options']['type'];
?>
<div>
	<input type='text' name='filter<?php echo $t; ?>[]' value='<?php if(isset($_REQUEST['value'])){ echo $_REQUEST['value']; } ?>' />
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;

		case 'element_forceUrl':
?>
<div>
	URL: <input type='text' name='forceUrlList_original[]' value='' /> Will be populated as: <input type='text' name='forceUrlList_altered[]' value='' />
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
		break;

		case 'element_multifilter':
			$t = $_REQUEST['options']['type'];
?>
<textarea name='filter<?php echo $t; ?>Multi' id='filter<?php echo $t; ?>Multi' ></textarea>
<input type='button' value='Add Multiple Urls' onclick="splitMultiFilter('<?php echo $e; ?>', '<?php echo $t; ?>');" />
<?php
		break;

		case 'dialog_editpopulation':
			$idFeedOut = !empty( ['idFeedOut'] ) ? $_REQUEST['idFeedOut'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			$populationSettings = $leads->getPopulations( $idFeedOut );
			$cacheFeedIn = array();
?>
<p>
	Feed ID: <?php echo $feed->idFeedOut; ?><br/>
	Label: <?php echo htmlentities( $feed->label ); ?><br/>
	Description: <?php echo htmlentities( $feed->description ); ?>
</p>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-newpop" data-dismiss="modal" data-feed-id="<?php echo $feed->idFeedOut; ?>">Add a new population parameter</button></p>
<?php
			if($populationSettings === false){ 
?>
<p>Error getting population settings.</p>
<?php
			} elseif($populationSettings == 0){ 
?>
<p>No settings found.</p>
<?php
			} else { 
?>
<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr>
			<th><p>Populating Feed</p></th>
			<th><p>Population Status</p></th>
			<th><p>Filtering By URL</p></th>
			<th><p>URL Filter Settings</p></th>
			<th><p>Filtering By Email</p></th>
			<th><p>Email Filter Settings</p></th>
			<th><p>Filtering By Listcode</p></th>
			<th><p>Listcode Filter Settings</p></th>
			<th><p>Force URLs</p></th>
			<th><p>Force URL Settings</p></th>
			<th><p>Actions</p></th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach($populationSettings as $popSet){ 
		if(!isset($cacheFeedIn[$popSet->idFeedIn])){ 
			$cacheFeedIn[$popSet->idFeedIn] = $leads->getInboundFeed($popSet->idFeedIn);
			if(!is_object($cacheFeedIn[$popSet->idFeedIn])){ 
				$cacheFeedIn[$popSet->idFeedIn] = new stdClass;
				$cacheFeedIn[$popSet->idFeedIn]->label = 'Error';
			}
		}
		$statusPopulation = ($popSet->enabled)?'Populating':'Disabled';
		if(is_null($popSet->filterTypeUrl)){ 
			$filterTypeUrl = 'Off';
			$filterUrl = 'Disabled';
		} else { 
			$filterTypeUrl = 'On';
			if($popSet->filterTypeUrl == 'accept'){ 
				$filterUrl = 'Accepting: ';
			} else {
				$filterUrl = 'Rejecting: ';
			}
			$filterUrls = explode(';', $popSet->filterUrl);
			$comma = false;
			foreach($filterUrls as $url){ 
				if($comma){ $filterUrl .= ', '; }
				$filterUrl .= $url;
				$comma = true;
			}
		}
		if(is_null($popSet->filterTypeEmail)){ 
			$filterTypeEmail = 'Off';
			$filterEmail = 'Disabled';
		} else { 
			$filterTypeEmail = 'On';
			if($popSet->filterTypeEmail == 'accept'){ 
				$filterEmail = 'Accepting: ';
			} else {
				$filterEmail = 'Rejecting: ';
			}
			$filterEmails = explode(';', $popSet->filterEmail);
			$comma = false;
			foreach($filterEmails as $email){ 
				if($comma){ $filterEmail .= ', '; }
				$filterEmail .= $email;
				$comma = true;
			}
		}
		if(is_null($popSet->filterTypeListcode)){ 
			$filterTypeListcode = 'Off';
			$filterListcode = 'Disabled';
		} else { 
			$filterTypeListcode = 'On';
			if($popSet->filterTypeListcode == 'accept'){ 
				$filterListcode = 'Accepting: ';
			} else {
				$filterListcode = 'Rejecting: ';
			}
			$filterListcodes = explode(';', $popSet->filterListcode);
			$comma = false;
			foreach($filterListcodes as $listcode){ 
				if($comma){ $filterListcode .= ', '; }
				$filterListcode .= $listcode;
				$comma = true;
			}
		}
		if($popSet->forceUrl){ $forceUrl = 'On'; } else { $forceUrl = 'Off'; }
		$forceUrlListArray = explode(";", $popSet->forceUrlList);
		$forceUrlList = 'No urls assigned for force urls.';
		if($popSet->forceUrlList != '' && is_arraY( $forceUrlListArray )){ 
			$forceUrlList = '';
			foreach($forceUrlListArray as $valuePair){ 
				list($original, $altered) = explode("=", $valuePair);
				if($original == 'all'){ $original = 'All Urls'; }
				$forceUrlList .= "$original -> $altered<br />";
			}
		}
?>
		<tr>
			<td valign='top'>
				<p>
					(<?php echo $popSet->idFeedIn; ?>) 
					<?php echo $cacheFeedIn[$popSet->idFeedIn]->label; ?>
				</p>
			</td>
			<td valign='top' class="text-center">
				<input class="population-toggle" <?php if( !empty( $popSet->enabled ) ) { print 'checked="checked" '; } ?>data-toggle="toggle" data-size="mini" data-width="80" data-on="Enabled" data-onstyle="success" data-off="Disabled" data-offstyle="danger" data-assoc-id="<?php echo $popSet->idAssoc; ?>" type="checkbox" /></td>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeEmail; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterEmail; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterTypeListcode; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $filterListcode; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $forceUrl; ?></p>
			</td>
			<td valign='top'>
				<p><?php echo $forceUrlList; ?></p>
			</td>
			<td valign='top' class="text-center">
				<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#modal-editpop" data-feed-id="<?php echo $feed->idFeedOut; ?>" data-assoc-id="<?php echo $popSet->idAssoc; ?>" data-dismiss="modal">Edit</button>
			</td>
		</tr>
<?php
	}
?>
	</tbody>
</table>
<script type="text/javascript">
$('.population-toggle').bootstrapToggle();

$('.population-toggle').change( function() {
	var idAssoc = $(this).data('assoc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'a': 'managePopulationParam',
			'idAssoc': idAssoc,
			'action': 'toggle',
			'param': 'enabled'
		}
	});
});
</script>
<?php
}
?>
<?php
		break;

		default:
?>
<p>Requested information doesn't exist.</p>
<?php
		break;
	}
	exit;
}

$title = 'Outgoing Feed Manager';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Outgoing Feeds</h2>

<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>

<form class="pull-right" id="status-select" method="get">
<select id="status" name="status">
	<option value="active"<?php if( 'active' === $status ) { print ' selected="selected"'; } ?>>Show active feeds</option>
	<option value="hidden"<?php if( 'hidden' === $status ) { print ' selected="selected"'; } ?>>Show hidden feeds</option>
	<option value="retired"<?php if( 'retired' === $status ) { print ' selected="selected"'; } ?>>Show retired feeds</option>
	<option value=""<?php if( null === $status ) { print ' selected="selected"'; } ?>>Show all feeds</option>
</select>
</form>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newfeed" data-feed-id="">Add a new feed</button></p>

<?php } ?>

<?php

if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$outgoingFeeds = $leads->getOutboundFeeds( null, $status );
} else {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	$outgoingFeeds = $leads->getOutboundFeeds( $idCompany, $status );
}
?>
<?php
if( $outgoingFeeds === false ) {
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
} else if( $outgoingFeeds == 0 ){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
} else {
	//Go through each and compile the company list.
	$companyFeedLists = array();
	foreach($outgoingFeeds as $feed){
		//Add company to the cache list of companies.
		if(!isset($companyCache[$feed->idCompany])){
			$company = $leads->getCompany($feed->idCompany);
			if(	is_object($company) ){
				$companyCache[$feed->idCompany] = $company;
				$companyFeedLists[$feed->idCompany] = array();
			}
		}
		//Add feed to list of feeds for the specified company.
		$companyFeedLists[$feed->idCompany][] = $feed;
	}
	//print_r($companyFeedLists);
	function companyListSort($item1,$item2)
	{
		global $companyCache;
		//print_r($companyCache[$item1]->name);
		//print_r($companyCache[$item2]->name);
		return strcasecmp ( $companyCache[$item1]->name , $companyCache[$item2]->name );
	}
	uksort($companyFeedLists,'companyListSort');
?>
<table class="table table-bordered table-condensed table-striped-custom">
	<thead>
	<tr>
		<th class='fTO_companyName' colspan='2'>Company</th>
		<th class='fTO_feedOverview' colspan='4'>Total Feeds</th>
		<th class='fTO_accepted'>Total Accepted</th>
		<th class='fTO_rejected'>Total Rejected</th>
		<th class='fTO_rejected'>Total Queued</th>
		<th class='fTO_options'>Options</th>
	</tr>
	</thead>
<?php
	$grandTotalFeeds = 0;
	$grandTotalAccepted = 0;
	$grandTotalRejected = 0;
	$grandTotalQueued = 0;
	foreach($companyFeedLists as $idCompany => $companyFeedList){
		$totalAccepted = 0;
		$totalRejected = 0;
		$totalActive = 0;
		$totalQueued = 0;

		foreach($companyFeedList as $keyFeed => $feed){

			$stats = $leads->getOutboundStats( $feed->idFeedOut );

			$companyFeedList[$keyFeed]->accepted = $stats['accepted'];
			$totalAccepted += $stats['accepted'];
			$grandTotalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->rejected = $stats['rejected'];
			$totalRejected += $stats['rejected'];
			$grandTotalRejected += $stats['rejected'];

			$companyFeedList[$keyFeed]->queued = $feed->queued;
			$totalQueued += $feed->queued;
			$grandTotalQueued += $feed->queued;

			if('active' === $feed->status) { $totalActive++; }
			$companyFeedList[$keyFeed]->statusFeed = $feed->status;
			$companyFeedList[$keyFeed]->statusPop = $leads->getPopulationStatus( $feed->idFeedOut );
		}
		$grandTotalFeeds += count($companyFeedList);
?>
	<tr class='fTORow fTO_Row bgGray'>
		<td colspan='2'><?php echo $companyCache[$idCompany]->name; ?></td>
		<td colspan='4'><?php echo count($companyFeedList); ?> (<?php echo $totalActive; ?> Active)</td>
		<td class="text-right"><?php echo number_format( $totalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $totalRejected, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $totalQueued, 0 ); ?></td>
		<td class="text-center"><button class="btn btn-primary btn-xs" type="button" data-toggle="collapse" data-target=".feed-toggle-<?php echo $idCompany; ?>" aria-expanded="false" aria-controls="collapseExample">Show Feeds</button></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){
?>
	<tr class="collapse bg-gray feed-toggle feed-toggle-<?php echo $idCompany; ?>">
		<td><?php echo $feed->idFeedOut; ?></td>
		<td class='fTO_label status-<?php echo $feed->status; ?>'><?php echo htmlentities( $feed->label ); ?></td>
		<td><?php echo htmlentities( $feed->description ); ?></td>
		<td><?php echo htmlentities( $feed->statusPop ); ?></td>
		<td><?php echo ucfirst( $feed->status ); ?></td>
		<td><input class="cron-toggle" <?php if( !empty( $feed->cron ) ) { print 'checked="checked" '; } ?>data-toggle="toggle" data-size="mini" data-width="80" data-on="Running" data-onstyle="success" data-off="Paused" data-offstyle="danger" data-feed-id="<?php echo $feed->idFeedOut; ?>" type="checkbox" /></td>
		<td class="text-right"><?php echo $feed->accepted; ?></td>
		<td class="text-right"><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode($feed->idFeedOut);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->rejected; ?></a></td>
		<td class="text-right"><?php echo $feed->queued; ?></td>
		<td class="text-center">
<div class="btn-group">
  <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editfeed" data-mode="edit" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Edit Feed</button>
  <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	<span class="caret"></span>
	<span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
	<li><a href="#" data-toggle="modal" data-target="#modal-showpop" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Show populations</a></li>
	<li><a href="#" data-toggle="modal" data-target="#newfeed" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Duplicate feed</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-testrecord" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Send test record</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-clearqueue" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Clear queue</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-urlreport" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">URL report</a></li>
  </ul>
</div>
</td>
	</tr>
<?php
		}
	}
?>
	<tfoot>
	<tr>
		<td colspan='2'>GRAND TOTAL</td>
		<td colspan='4'><?php echo number_format( $grandTotalFeeds, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalQueued, 0 ); ?></td>
		<td>&nbsp;</td>
	</tr>
	</tfoot>
</table>
<?php
}
?>

<div class="modal fade" id="newfeed" tabindex="-1" role="dialog" aria-labelledby="newfeed_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newfeed_title">Add a new outgoing feed</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newfeed" type="button" class="btn btn-primary">Add feed</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editfeed" tabindex="-1" role="dialog" aria-labelledby="editfeed_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editfeed_title">Edit an outgoing feed</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editfeed" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-showpop" tabindex="-1" role="dialog" aria-labelledby="showpop_title">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="showpop_title">Population Settings</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-clearqueue" tabindex="-1" role="dialog" aria-labelledby="clearqueue_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="clearqueue_title">Clear queued records</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-testrecord" tabindex="-1" role="dialog" aria-labelledby="testrecord_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="testrecord_title">Send a test record</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-urlreport" tabindex="-1" role="dialog" aria-labelledby="urlreport_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="urlreport_title">URL Report</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-urlreport" type="button" class="btn btn-primary">Run Report</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-newpop" tabindex="-1" role="dialog" aria-labelledby="newpop_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newpop_title">Add a new population parameter</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newpop" type="button" class="btn btn-primary">Add population</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-editpop" tabindex="-1" role="dialog" aria-labelledby="editpop_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editpop_title">Edit a population parameter</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editpop" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newfeed').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedout.php",
		type: "POST",
		async: true,
		data: $("#new_feed").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newfeed').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_newfeed',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editfeed').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedout.php",
		type: "POST",
		async: true,
		data: $("#edit_feed").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editfeed').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_editfeed',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-showpop').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_editpopulation',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-clearqueue').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_clearqueue',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-testrecord').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_testrecord',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-urlreport').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_urlreport',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-urlreport').click(function(event) {
	event.preventDefault();

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: $("#form-urlreport").serialize(),
		success: function(data) {
			$('#modal-urlreport').find('.modal-body').html(data);
		}
	});
});

$('#modal-save-newpop').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedout.php",
		type: "POST",
		async: true,
		data: $("#new_pop").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#modal-newpop').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_newpopsetting',
			'idFeedOut': idFeedOut
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editpop').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedout.php",
		type: "POST",
		async: true,
		data: $("#edit_pop").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#modal-editpop').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedOut = $(e.relatedTarget).data('feed-id');
	var idAssoc = $(e.relatedTarget).data('assoc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'd': 'dialog_editpopsetting',
			'idFeedOut': idFeedOut,
			'idAssoc': idAssoc
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#status-select select').change(function(e) {
	e.preventDefault();
	$('#status-select').submit();
});

$('.feed-toggle').on('show.bs.collapse', function() {
	$(this).prev().find('button[data-toggle="collapse"]').html('Hide Feeds');
});
$('.feed-toggle').on('hide.bs.collapse', function() {
	$(this).prev().find('button[data-toggle="collapse"]').html('Show Feeds');
});

$('.cron-toggle').change( function() {
	var idFeedOut = $(this).data('feed-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedout.php',
		data: {
			'a': 'manageFeedParam',
			'idFeedOut': idFeedOut,
			'action': 'toggle',
			'param': 'cron'
		}
	});
});

$(document).on('hidden.bs.modal', '.modal', function () {
	$('.modal:visible').length && $(document.body).addClass('modal-open');
});

function splitMultiFilter(e, t){
	values = $('#'+e+'popset_filter'+t+'Multi').val();
	//alert(values);
	valueArray = values.match(/[^\r\n]+/g);
	for(count = 0; count < valueArray.length; count++){
		element(
			e+"popset_filter"+t+"_container"
			, "element_filter"
			, {
				"e": e
				, "type": t
				, "value": valueArray[count]
			}
		);
	}
	//alert('#'+e+'popset_filter'+t+'_multipleInsert');
	$('#'+e+'popset_filter'+t+'_multipleInsert').html("");
}
</script>

</body>
</html>
