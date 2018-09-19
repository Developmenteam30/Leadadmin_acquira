<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'processLeads.php' );
$leads = Leads::getInstance();

$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );
require_once( INCLUDES . 'f_site.php' );

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
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

			$valueMap = array();
			if( !empty( $_REQUEST['valueMap']['field'] ) && is_array( $_REQUEST['valueMap']['field'] ) ) {
				foreach( $_REQUEST['valueMap']['field'] as $key => $val ) {
					$valueMap[] = array(
						'field' => trim( $val ),
						'oldValue' => trim( $_REQUEST['valueMap']['oldValue'][$key] ?? '' ),
						'newValue' => trim( $_REQUEST['valueMap']['newValue'][$key] ?? '' ),
					);
				}
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

			if( $c && !empty( $_REQUEST['notifyThresholdCount'] ) && is_numeric( $_REQUEST['notifyThresholdCount'] ) === false ) {
				$c = false;
				$result['error'] = 'Please enter a numeric value for the notification threshold amount.';
			}

			if( $c && !empty( $_REQUEST['notifyThresholdTime'] ) ) {
				$notifyThresholdTime = DateTime::createFromFormat( 'Y-m-d g:iA', ( date( 'Y-m-d' ) . ' ' . $_REQUEST['notifyThresholdTime'] ) );
				if( empty( $notifyThresholdTime ) ) {
					$result['error'] = 'Please enter a valid notification threshold time in the format hh:mmAM or hh:mmPM.';
					$c = false;
				}
			}

			if( !empty( $_REQUEST['revenuePerLead'] ) && is_numeric( $_REQUEST['revenuePerLead'] ) === false ) {
				$result['error'] = 'Revenue per lead must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['costPerLeadOverride'] ) && is_numeric( $_REQUEST['costPerLeadOverride'] ) === false ) {
				$result['error'] = 'Cost per lead override must be blank or a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['xmlDTD'] ) && @simplexml_load_string( $_REQUEST['xmlDTD'] ) === false ) {
				$result['error'] = 'XML DTD/Schema is not valid.';
				break;
			}

			if( $c ) {
				//Set up processingSchedule array
				$processingSchedule = array();
				$schedule_array = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
				foreach( $schedule_array as $id => $day ) {
					if( !empty( $_REQUEST[$day . '_schedule'] ) ) {
						if( ( empty( $_REQUEST[$day . '_start'] ) && !empty( $_REQUEST[$day . '_end'] ) || ( !empty( $_REQUEST[$day . '_start'] ) && empty( $_REQUEST[$day . '_end'] ) ) ) ) {
							$c = false;
							$result['error'] = 'Please enter both a start time and an end time for all days where times are used.';
							break;
						}


						if( ( !empty( $_REQUEST[$day . '_start'] ) && !preg_match( '/([01]?[0-9]|2[0-3]):[0-5][0-9]/', $_REQUEST[$day . '_start'] ) ) || ( !empty( $_REQUEST[$day . '_end'] ) && !preg_match( '/([01]?[0-9]|2[0-3]):[0-5][0-9]/', $_REQUEST[$day . '_end'] ) ) ) {
							$c = false;
							$result['error'] = 'Please enter valid start and end times in the 24-hour format of HH:MM.';
							break;
						}

						if( strtotime( $_REQUEST[$day . '_start'] ) > strtotime( $_REQUEST[$day . '_end'] ) ) {
							$c = false;
							$result['error'] = 'Please enter an end time that is greater than the start time.';
							break;
						}

						$processingSchedule[$day]['enabled'] = true;
						$processingSchedule[$day]['startTime'] = $_REQUEST[$day . '_start'] ?? '';
						$processingSchedule[$day]['endTime'] = $_REQUEST[$day . '_end'] ?? '';
					} else {
						$processingSchedule[$day]['enabled'] = false;
					}
				}
				$processingSchedule = json_encode( $processingSchedule );
			}

			if( $action == 'new' ) {

				if( $c ) {
					//Label can not be already used
					$checkResult = $leads->checkOutboundFeedLabelExists( $_REQUEST['label'] );
					if( true === $checkResult ) {
						$c = false;
						$result['error'] = 'That feed label is already being used.';
					}
				}

				if( $c ) { //Add entry to the database.

					$fields = array(
						'label' => trim( $_REQUEST['label'] ),
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => $_REQUEST['idCompany'],
						'feedType' => empty( $_REQUEST['feedType'] ) ? 'curlPOST' : $_REQUEST['feedType'],
						'postUrl' => empty( $_REQUEST['postUrl'] ) ? null : $_REQUEST['postUrl'],
						'staticFields' => empty( $staticFields ) ? null : $staticFields,
						'varFields' => empty( $varFields ) ? null : $varFields,
						'fieldMap' => empty( $fieldMap ) ? null : $fieldMap,
						'valueMap' => empty( $valueMap ) ? null : json_encode( $valueMap ),
						'cron' => 1,
						'cronTiming' => 1,
						'successString' => empty( $_REQUEST['successString'] ) ? null : $_REQUEST['successString'],
						'throttle' => 0,
						'urlassignments' => empty( $urlAssign ) ? null : $urlAssign,
						'dailyLimit' => empty( $_REQUEST['dailyLimit'] ) ? null : $_REQUEST['dailyLimit'],
						'delay' => empty( $_REQUEST['delay'] ) ? null : $_REQUEST['delay'],
						'delayDump' => !empty( $_REQUEST['delayDump'] ) ? 1 : 0,
						'queued' => 0,
						'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
						'feedCategory' => empty( $_REQUEST['feedCategory'] ) ? 'email' : $_REQUEST['feedCategory'],
						'notifyThresholdCount' => empty( $_REQUEST['notifyThresholdCount'] ) ? 0 : $_REQUEST['notifyThresholdCount'],
						'notifyThresholdTime' => !empty( $notifyThresholdTime ) ? $notifyThresholdTime->format( 'H:i:s' ) : null,
						'notifyThresholdDays' => empty( $_REQUEST['notifyThresholdDays'] ) ? null : implode( ',', $_REQUEST['notifyThresholdDays'] ),
						'revenuePerLead' => !empty( $_REQUEST['revenuePerLead'] ) ? $_REQUEST['revenuePerLead'] : 0.00,
						'costPerLeadOverride' => '' === trim( $_REQUEST['costPerLeadOverride'] ) ? null : $_REQUEST['costPerLeadOverride'],
						'salesperson' => empty( $_REQUEST['salesperson'] ) ? null : $_REQUEST['salesperson'],
						'xmlDTD' => empty( $_REQUEST['xmlDTD'] ) ? null : $_REQUEST['xmlDTD'],
						'processingSchedule' => $processingSchedule,
					);

					if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) {
						$fields['launchDate'] = !empty( $_REQUEST['launchDate'] ) ? $_REQUEST['launchDate'] : null;
					}

					$idFeedOut = $leads->addOutboundFeed( $fields );

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

				if( $c ) {
					if( $_REQUEST['label'] != $feed->label ) { //Label is being altered.

						$pattern = '/^[a-z][a-z0-9_]*$/';
						if( !preg_match( $pattern, $_REQUEST['label'] ) ) {
							$c = false;
							$result['error'] = 'Label must start with a letter, can can contain '
								. 'letters, numbers, and underscore only.';
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

					$fields = array(
						'label' => trim( $_REQUEST['label'] ),
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => $_REQUEST['idCompany'],
						'feedType' => empty( $_REQUEST['feedType'] ) ? 'curlPOST' : $_REQUEST['feedType'],
						'postUrl' => empty( $_REQUEST['postUrl'] ) ? null : $_REQUEST['postUrl'],
						'staticFields' => empty( $staticFields ) ? null : $staticFields,
						'varFields' => empty( $varFields ) ? null : $varFields,
						'fieldMap' => empty( $fieldMap ) ? null : $fieldMap,
						'valueMap' => empty( $valueMap ) ? null : json_encode( $valueMap ),
						'successString' => empty( $_REQUEST['successString'] ) ? null : $_REQUEST['successString'],
						'urlassignments' => empty( $urlAssign ) ? null : $urlAssign,
						'dailyLimit' => empty( $_REQUEST['dailyLimit'] ) ? null : $_REQUEST['dailyLimit'],
						'delay' => empty( $_REQUEST['delay'] ) ? null : $_REQUEST['delay'],
						'delayDump' => !empty( $_REQUEST['delayDump'] ) ? 1 : 0,
						'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
						'feedCategory' => empty( $_REQUEST['feedCategory'] ) ? 'email' : $_REQUEST['feedCategory'],
						'notifyThresholdCount' => empty( $_REQUEST['notifyThresholdCount'] ) ? 0 : $_REQUEST['notifyThresholdCount'],
						'notifyThresholdTime' => !empty( $notifyThresholdTime ) ? $notifyThresholdTime->format( 'H:i:s' ) : null,
						'notifyThresholdDays' => empty( $_REQUEST['notifyThresholdDays'] ) ? null : implode( ',', $_REQUEST['notifyThresholdDays'] ),
						'revenuePerLead' => !empty( $_REQUEST['revenuePerLead'] ) ? $_REQUEST['revenuePerLead'] : 0.00,
						'costPerLeadOverride' => '' === trim( $_REQUEST['costPerLeadOverride'] ) ? null : $_REQUEST['costPerLeadOverride'],
						'salesperson' => empty( $_REQUEST['salesperson'] ) ? null : $_REQUEST['salesperson'],
						'xmlDTD' => empty( $_REQUEST['xmlDTD'] ) ? null : $_REQUEST['xmlDTD'],
						'processingSchedule' => $processingSchedule,
					);

					if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) {
						$fields['launchDate'] = !empty( $_REQUEST['launchDate'] ) ? $_REQUEST['launchDate'] : null;
					}

					// For retired feeds, automatically turn off cron processing and set all populations as disabled
					if( 'retired' == $_REQUEST['status'] ) {
						$fields['cron'] = 0;

						$populations = $leads->getPopulations( $idFeedOut );
						if( !empty( $populations ) && is_array( $populations ) ) {
							foreach( $populations as $population ) {
								if( $population->enabled ) {
									$leads->updatePopulation( $population->idAssoc, array( 'enabled' => 0 ) );
								}
							}
						}
					}

					$dbResult = $leads->updateOutboundFeed( $idFeedOut, $fields );

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

			if( !empty( $_REQUEST['waterfallPriority'] ) && is_numeric( $_REQUEST['waterfallPriority'] ) === false ) {
				$result['error'] = 'Please enter a numeric value for the waterfall priority.';
				break;
			}

			if( !empty( $_REQUEST['waterfallPriority'] ) && intval( $_REQUEST['waterfallPriority'] ) > 65535 ) {
				$result['error'] = 'Please enter a value of 65,535 or less for the waterfall priority.';
				break;
			}

			if( !empty( $_REQUEST['startDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['startDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid start date.';
					break;
				}
			}

			if( $action == 'new' ) {

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
					'queueType' => !empty( $_REQUEST['queueType'] ) ? $_REQUEST['queueType'] : 'livedata',
					'startDate' => !empty( $_REQUEST['startDate'] ) ? $_REQUEST['startDate'] : null,
					'waterfallPriority' => !empty( $_REQUEST['waterfallPriority'] ) ? $_REQUEST['waterfallPriority'] : 0,
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
					'queueType' => !empty( $_REQUEST['queueType'] ) ? $_REQUEST['queueType'] : 'livedata',
					'startDate' => !empty( $_REQUEST['startDate'] ) ? $_REQUEST['startDate'] : null,
					'waterfallPriority' => !empty( $_REQUEST['waterfallPriority'] ) ? $_REQUEST['waterfallPriority'] : 0,
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
			$c = true;
			$result['error'] = 'Failed when attempting to manage population params.';
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

		case 'import-legacy-data':
			$c = true;
			$result['error'] = 'Failed when trying to import data.';

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$c = false;
				$result['error'] = 'Sorry, you do not have permission to import legacy data.';
			}

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $_REQUEST['idFeedOut'] ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
				}
			}

			if( $c ) {
				$feed = $leads->getOutboundFeed( $_REQUEST['idFeedOut'] );
				if( $feed === false ) {
					$c = false;
					$result['error'] = 'Database failure - could not fetch feed information.';
				}
				if( $c && !is_object( $feed ) && $feed == 0 ) {
					$c = false;
					$result['error'] = 'Error - could not fetch feed. Feed does not exist.';
				}
			}

			if( $c ) {
				try {
					$dateStart = new \DateTime( $_REQUEST['dateStart'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid start date.';
					$c = false;
				}
			}

			if( $c ) {
				try {
					$dateEnd = new \DateTime( $_REQUEST['dateEnd'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid end date.';
					$c = false;
				}
			}

			if( $c ) {
				if( $dateEnd < $dateStart ) {
					$result['error'] = 'The end date must come after the start date.';
					$c = false;
				}
			}

			if( $c ) {
				$date = new \DateTime();
				$date->sub( new \DateInterval( 'P6M' ) );
				if( $dateStart < $date || $dateEnd < $date ) {
					$result['error'] = 'Please select start and end dates that fall within the last 6 months.';
					$c = false;
				}
			}

			if( $c ) {
				$jobId = $leads->addJob( 'import-legacy-outbound', $feed->idFeedOut, serialize( $_REQUEST ), '', 0 );
				if( null === $jobId ) {
					$c = false;
					$result['error'] = 'Error adding this job to the database.';
				} else {
					$leads->auditLog( 'FEEDOUT:IMPORT', $jobId );
					$result['status'] = 1;
					$result['error'] = 'Import job #' . $jobId . ' submitted successfully. The selected records will be added to the outbound queue.';
				}
			}

			break;

		case 'exportData':
			$c = true;
			$result['error'] = 'Failed when trying to export data.';

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ) ) {
				$c = false;
				$result['error'] = 'Sorry, you do not have permission to export data.';
			}

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $_REQUEST['idFeedOut'] ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
				}
			}

			if( $c ) {
				$feed = $leads->getOutboundFeed( $_REQUEST['idFeedOut'] );
				if( $feed === false ) {
					$c = false;
					$result['error'] = 'Database failure - could not fetch feed information.';
				}
				if( $c && !is_object( $feed ) && $feed == 0 ) {
					$c = false;
					$result['error'] = 'Error - could not fetch feed. Feed does not exist.';
				}
			}

			if( $c ) {
				$jobId = $leads->addJob( 'export-outgoing', $feed->idFeedOut, serialize( $_REQUEST ), '', 0 );
				if( null === $jobId ) {
					$c = false;
					$result['error'] = 'Error adding this job to the database.';
				} else {
					$leads->auditLog( 'FEEDOUT:EXPORT', $jobId );
					$result['status'] = 1;
					$result['error'] = 'Export job #' . $jobId . ' submitted successfully. You will be notified by email when your download is ready.';
				}
			}

			break;

		case 'retry-outbound-rejections':
			$c = true;
			$result['error'] = 'Failed when trying to retry outbound rejections.';

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$c = false;
				$result['error'] = 'Sorry, you do not have permission to retry outbound rejections.';
			}

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkOutboundFeedAccess( $idCompany, $_REQUEST['idFeedOut'] ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
				}
			}

			if( $c ) {
				$feed = $leads->getOutboundFeed( $_REQUEST['idFeedOut'] );
				if( $feed === false ) {
					$c = false;
					$result['error'] = 'Database failure - could not fetch feed information.';
				}
				if( $c && !is_object( $feed ) && $feed == 0 ) {
					$c = false;
					$result['error'] = 'Error - could not fetch feed. Feed does not exist.';
				}
			}

			if( $c ) {
				try {
					$dateStart = new \DateTime( $_REQUEST['dateStart'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid start date.';
					$c = false;
				}
			}

			if( $c ) {
				try {
					$dateEnd = new \DateTime( $_REQUEST['dateEnd'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid end date.';
					$c = false;
				}
			}

			if( $c ) {
				if( $dateEnd < $dateStart ) {
					$result['error'] = 'The end date must come after the start date.';
					$c = false;
				}
			}

			if( $c ) {
				$date = new \DateTime();
				$date->sub( new \DateInterval( 'P6M' ) );
				if( $dateStart < $date || $dateEnd < $date ) {
					$result['error'] = 'Please select start and end dates that fall within the last 6 months.';
					$c = false;
				}
			}

			if( $c ) {
				$jobId = $leads->addJob( 'retry-outbound-rejections', $feed->idFeedOut, serialize( $_REQUEST ), '', 0 );
				if( null === $jobId ) {
					$c = false;
					$result['error'] = 'Error adding this job to the database.';
				} else {
					$leads->auditLog( 'FEEDOUT:IMPORT', $jobId );
					$result['status'] = 1;
					$result['error'] = 'Retry rejections job #' . $jobId . ' submitted successfully. The selected records will be readded to the outbound queue.';
				}
			}

			break;
	}
	echo json_encode( $result );
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

			$feedOut = $leads->getOutboundFeed( $idFeedOut );
			if( empty( $feedOut ) ) {
				print '<p>Sorry, the feed you specified does not exist.</p>';
				break;
			}

			$_REQUEST['gender'] = strtoupper( $_REQUEST['gender'] ?? 'M' );
			$_REQUEST['country'] = strtoupper( $_REQUEST['country'] ?? 'US' );
			$_REQUEST['cellphone'] = preg_replace( '/[^0-9]/', '', ( $_REQUEST['cellphone'] ?? '2125551818' ) );
			$_REQUEST['landline'] = preg_replace( '/[^0-9]/', '', ( $_REQUEST['landline'] ?? '2125552020' ) );

			print '<h2>Test submission parameters</h2>' . PHP_EOL;
			print '<p>You may use the default values below, or change them appropriately if you are receiving duplicate errors due to multiple test submissions.</p>' . PHP_EOL;

			$fields = array(
				array(
					'id' => 'idFeedOut',
					'type' => 'hidden',
					'value' => $_REQUEST['idFeedOut'],
				),
				array(
					'id' => 'd',
					'type' => 'hidden',
					'value' => 'dialog_testrecord',
				),
				array(
					'id' => 'submit',
					'type' => 'hidden',
					'value' => 'true',
				),
				array(
					'id' => 'email',
					'label' => 'Email',
					'type' => 'email',
					'value' => $_REQUEST['email'] ?? 'johndoe@somewhere.com',
				),
				array(
					'id' => 'fname',
					'label' => 'First Name',
					'type' => 'text',
					'value' => $_REQUEST['fname'] ?? 'John',
				),
				array(
					'id' => 'lname',
					'label' => 'Last Name',
					'type' => 'text',
					'value' => $_REQUEST['lname'] ?? 'Adams',
				),
				array(
					'id' => 'addr',
					'label' => 'Adddress 1',
					'type' => 'text',
					'value' => $_REQUEST['addr'] ?? '123 Main St',
				),
				array(
					'id' => 'addr2',
					'label' => 'Adddress 2',
					'type' => 'text',
					'value' => $_REQUEST['addr2'] ?? '',
				),
				array(
					'id' => 'city',
					'label' => 'City',
					'type' => 'text',
					'value' => $_REQUEST['city'] ?? 'New York',
				),
				array(
					'id' => 'state',
					'label' => 'State',
					'type' => 'text',
					'value' => $_REQUEST['state'] ?? 'NY',
				),
				array(
					'id' => 'zip',
					'label' => 'Zip Code',
					'type' => 'text',
					'value' => $_REQUEST['zip'] ?? '10013',
				),
				array(
					'id' => 'country',
					'label' => 'Country (2-letters)',
					'type' => 'text',
					'value' => $_REQUEST['country'],
				),
				array(
					'id' => 'cellphone',
					'label' => 'Cellphone',
					'type' => 'text',
					'value' => $_REQUEST['cellphone'],
				),
				array(
					'id' => 'landline',
					'label' => 'Landline',
					'type' => 'text',
					'value' => $_REQUEST['landline'],
				),
				array(
					'id' => 'gender',
					'label' => 'Gender (M or F only)',
					'type' => 'text',
					'value' => $_REQUEST['gender'],
				),
				array(
					'id' => 'dob',
					'label' => 'DOB (YYYY-MM-DD)',
					'type' => 'text',
					'value' => $_REQUEST['dob'] ?? '1980-02-03',
				),
				array(
					'id' => 'ip',
					'label' => 'IP Address',
					'type' => 'text',
					'value' => $_REQUEST['ip'] ?? '10.1.2.3',
				),
				array(
					'id' => 'url',
					'label' => 'URL',
					'type' => 'text',
					'value' => $_REQUEST['url'] ?? ( 'http://www.' . SITE_URL ),
				),
				array(
					'id' => 'stamp',
					'label' => 'Lead Timestamp',
					'type' => 'text',
					'value' => $_REQUEST['stamp'] ?? date( 'Y-m-d H:i:s' ),
				),
				array(
					'id' => 'listcode',
					'label' => 'List Code',
					'type' => 'text',
					'value' => $_REQUEST['listcode'] ?? '',
				),
				array(
					'id' => 'leadId',
					'label' => 'Lead ID',
					'type' => 'text',
					'value' => $_REQUEST['leadId'] ?? '12345',
				),
				array(
					'id' => 'custom1',
					'label' => 'Custom Field 1',
					'type' => 'text',
					'value' => $_REQUEST['custom1'] ?? '',
				),
				array(
					'id' => 'custom2',
					'label' => 'Custom Field 2',
					'type' => 'text',
					'value' => $_REQUEST['custom2'] ?? '',
				),
				array(
					'id' => 'custom3',
					'label' => 'Custom Field 3',
					'type' => 'text',
					'value' => $_REQUEST['custom3'] ?? '',
				),
				array(
					'id' => 'custom4',
					'label' => 'Custom Field 4',
					'type' => 'text',
					'value' => $_REQUEST['custom4'] ?? '',
				),
				array(
					'id' => 'custom5',
					'label' => 'Custom Field 5',
					'type' => 'text',
					'value' => $_REQUEST['custom5'] ?? '',
				),
				array(
					'id' => 'custom6',
					'label' => 'Custom Field 6',
					'type' => 'text',
					'value' => $_REQUEST['custom6'] ?? '',
				),
				array(
					'id' => 'idRecord',
					'label' => 'Record Id',
					'type' => 'text',
					'value' => $_REQUEST['idRecord'] ?? rand( 10000, getrandmax() ),
				),
			);

			Display::displayForm( 'testrecord', $fields );

			if( empty( $_REQUEST['submit'] ) ) {
				break;
			}

			print '<hr id="scroll-to"/>';

			$leaddata = (object) array(
				'stamp' => trim( $_REQUEST['stamp'] ?? '' ),
				'url' => trim( $_REQUEST['url'] ?? '' ),
				'ip' => trim( $_REQUEST['ip'] ?? '' ),
				'email' => trim( $_REQUEST['email'] ?? '' ),
				'fname' => trim( $_REQUEST['fname'] ?? '' ),
				'lname' => trim( $_REQUEST['lname'] ?? '' ),
				'addr' => trim( $_REQUEST['addr'] ?? '' ),
				'addr2' => trim( $_REQUEST['addr2'] ?? '' ),
				'city' => trim( $_REQUEST['city'] ?? '' ),
				'state' => trim( $_REQUEST['state'] ?? '' ),
				'zip' => trim( $_REQUEST['zip'] ?? '' ),
				'dob' => trim( $_REQUEST['dob'] ?? '' ),
				'gender' => trim( $_REQUEST['gender'] ?? '' ),
				'landline' => trim( $_REQUEST['landline'] ?? '' ),
				'cellphone' => trim( $_REQUEST['cellphone'] ?? '' ),
				'country' => trim( $_REQUEST['country'] ?? '' ),
				'listcode' => trim( $_REQUEST['listcode'] ?? '' ),
				'leadId' => trim( $_REQUEST['leadId'] ?? '' ),
				'custom1' => trim( $_REQUEST['custom1'] ?? '' ),
				'custom2' => trim( $_REQUEST['custom2'] ?? '' ),
				'custom3' => trim( $_REQUEST['custom3'] ?? '' ),
				'custom4' => trim( $_REQUEST['custom4'] ?? '' ),
				'custom5' => trim( $_REQUEST['custom5'] ?? '' ),
				'custom6' => trim( $_REQUEST['custom6'] ?? '' ),
				'idRecord' => trim( $_REQUEST['idRecord'] ?? '' ),
				'testRecord' => 1,
			);
			$errors = array();

			if( !empty( $leaddata->stamp ) && !preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $leaddata->stamp ) ) {
				$errors[] = 'Lead Timestamp must be in the format: YYYY-MM-DD HH:mm:ss';
			}

			if( !empty( $leaddata->url ) && filter_var( $leaddata->url, FILTER_VALIDATE_URL ) === false ) {
				$errors[] = 'URL is invalid.';
			}

			if( !empty( $leaddata->ip ) && filter_var( $leaddata->ip, FILTER_VALIDATE_IP ) === false ) {
				$errors[] = 'IP address is invalid.';
			}

			if( !empty( $leaddata->email ) && filter_var( $leaddata->email, FILTER_VALIDATE_EMAIL ) === false ) {
				$errors[] = 'Email address is invalid.';
			}

			if( !empty( $leaddata->country ) && !preg_match( '/^[A-Z]{2}$/', $leaddata->country ) ) {
				$errors[] = 'Country must be a 2-letter country code.';
			}

			if( !empty( $leaddata->gender ) && !preg_match( '/^[MF]{1}$/', $leaddata->gender ) ) {
				$errors[] = 'Gender must be either M or F.';
			}
			if( !empty( $leaddata->dob ) && !preg_match( '/^\d{4}-\d{2}-\d{2}$/', $leaddata->dob ) ) {
				$errors[] = 'Date of Birth must be in the format: YYYY-MM-DD';
			}

			if( !empty( $leaddata->url ) ) {
				$leaddata->url = $leads->parseUrl( $leaddata->url );
			}

			if( !empty( $errors ) ) {

				print '<ul class="errors">' . PHP_EOL;
				foreach( $errors as $error ) {
					printf( '<li>%s</li>' . PHP_EOL,
						Display::escHtml( $error )
					);
				}
				print '</ul>' . PHP_EOL;

			} else {
				print '<h2>Test submission results</h2>';

				$settings['testing'] = 0;
				$settings['testrecord'] = 1;

				print "<p><strong>HTTP Method:</strong> " . $feedOut->feedType . "</p>";

				$response = ProcessLeads::pushOutboundData( $feedOut, $leaddata );

				// Manually increment the queue counter because the pushOutboundData function will decrement the queue, resulting in a mismatch
				//$leads->incrementOutboundQueue( $idFeedOut );

				print "<p><strong>Query String:</strong> " . nl2br( htmlspecialchars( $response['querystring'], ENT_QUOTES | ENT_HTML5 ) ) . "</p>";

				print "<p><strong>Status:</strong> " . ( true === $response['status'] ? '<span class="success">ACCEPTED</span>' : '<span class="errors">REJECTED</span>' ) . "</p>";

				print "<p><strong>Response:</strong> " . htmlspecialchars( $response['text'], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE ) . "</p>";

				$leads->auditLog( 'FEEDOUT:TEST-RECORD', $idFeedOut );

				print '<hr/><p class="text-right">Click "Submit" again to send another test record, or click "Close" when finished.</p>';
			}

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
			if( empty( $feed ) ) {
				?>
				<p>Database failure - could not fetch requested feed information.</p>
				<?php
				exit;
			}

			if( !is_null( $feed->staticFields ) && $feed->staticFields != '' ) {
				$staticFields = explode( ";", $feed->staticFields );
			}
			$varFields = explode( ";", $feed->varFields );
			$fieldMap = explode( ";", $feed->fieldMap );
			$valueMap = !empty( $feed->valueMap ) ? json_decode( $feed->valueMap, true ) : array();
			$selectedNotifyThresholdDays = !empty( $feed->notifyThresholdDays ) ? explode( ",", $feed->notifyThresholdDays ) : array();

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
					$valueMap = !empty( $feed->valueMap ) ? json_decode( $feed->valueMap, true ) : array();
				}
			}

			if( !isset( $valueMap ) ) {
				$valueMap = array();
			}

			$feedProps = array(
				'idFeedOut',
				'label',
				'description',
				'idCompany',
				'feedType',
				'postUrl',
				'successString',
				'status',
				'dailyLimit',
				'delay',
				'delayDump',
				'feedCategory',
				'notifyThresholdCount',
				'notifyThresholdTimeFormatted',
				'revenuePerLead',
				'costPerLeadOverride',
				'launchDate',
				'salesperson',
				'xmlDTD',
				'processingSchedule',
			);
			foreach( $feedProps as $feedProp ) {
				if( isset( $feed ) ) {
					${"feed_" . $feedProp} = $feed->$feedProp;
				} else if( isset( $_REQUEST[$feedProp] ) ) {
					${"feed_" . $feedProp} = $_REQUEST[$feedProp];
				} else {
					${"feed_" . $feedProp} = '';
				}
			}

			$explodableProperties = array(
				'staticFields',
				'varFields',
				'fieldMap',
				'urlassignments',
			);
			foreach( $explodableProperties as $eP ) {
				if( !isset( $_REQUEST[$eP] ) ) {
					if( !isset( $feed->$eP ) || $feed->$eP == '' ) {
						${"feed_" . $eP} = array();
					} else {
						${"feed_" . $eP} = explode( ";", $feed->$eP );
					}
				} else {
					if( $_REQUEST[$eP] == '' ) {
						${"feed_" . $eP} = array();
					} else {
						${"feed_" . $eP} = explode( ";", $_REQUEST[$eP] );
					}
				}
			}

			if( !isset( $selectedNotifyThresholdDays ) ) {
				$selectedNotifyThresholdDays = array();
			}

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$companies = array( $leads->getCompany( $feed_idCompany ) );
			} else {
				$companies = $leads->getCompanies();
			}
			?>
			<form id="<?php echo $id; ?>">
				<input type='hidden' name='idFeedOut' value='<?php echo $feed_idFeedOut; ?>'/>
				<input type="hidden" name="a" value="manageFeed"/>
				<input type="hidden" name="action" value="<?php echo $mode; ?>"/>
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
								<input type='text' name='description' value='<?php echo htmlentities( $feed_description ); ?>' class='long'/>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Company</p></td>
						<td>
							<p>
								<?php if( $companies === false ) { ?>
									Database failure - could not fetch company list
								<?php } else if( !is_object( $companies ) && $companies == 0 ) { ?>
									There are no companies in the database. Please create a company before
									creating a feed.
								<?php } else { ?>
									<select name='idCompany'>
										<option></option>
										<?php foreach( $companies as $company ) { ?>
											<option value='<?php echo $company->idCompany; ?>'
											        <?php if( $company->idCompany == $feed_idCompany ){
											        ?>selected='selected'<?php } ?>
											><?php echo htmlentities( $company->name ); ?></option>
										<?php } ?>
									</select>
								<?php } ?>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Feed Category</p></td>
						<td>
							<p>Determines which section this feed shows up under on the dashboard.</p>
							<p>
								<input type="radio" name="feedCategory" value="email"<?php if( empty( $feed_feedCategory ) || 'email' == $feed_feedCategory ) {
									print ' checked="checked"';
								} ?> /> Email<br/>
								<input type="radio" name="feedCategory" value="phone"<?php if( 'phone' == $feed_feedCategory ) {
									print ' checked="checked"';
								} ?> /> Phone<br/>
								<input type="radio" name="feedCategory" value="ppc"<?php if( 'ppc' == $feed_feedCategory ) {
									print ' checked="checked"';
								} ?> /> PPC<br/>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Feed Type</p></td>
						<td>
							<p>
								<select name='feedType'>
									<option value='curlGET' <?php if( $feed_feedType == 'curlGET' ){ ?>selected='selected'<?php } ?>>HTTP GET</option>
									<option value='curlPOST' <?php if( $feed_feedType == 'curlPOST' ){ ?>selected='selected'<?php } ?>>HTTP POST (form-data)</option>
									<option value='curlPOST-urlencoded' <?php if( $feed_feedType == 'curlPOST-urlencoded' ){ ?>selected='selected'<?php } ?>>HTTP POST (urlencoded)</option>
									<option value='JSON' <?php if( $feed_feedType == 'JSON' ){ ?>selected='selected'<?php } ?>>JSON</option>
									<option value='csvString' <?php if( $feed_feedType == 'csvString' ){ ?>selected='selected'<?php } ?>>CSV string</option>
									<option value='xmlPOST' <?php if( $feed_feedType == 'xmlPOST' ){ ?>selected='selected'<?php } ?>>XML POST</option>
									<option value='soapPOST' <?php if( $feed_feedType == 'soapPOST' ){ ?>selected='selected'<?php } ?>>SOAP POST</option>
								</select>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Post URL</p></td>
						<td>
							<p>
								<input type='text' name='postUrl' value='<?php echo $feed_postUrl; ?>' style="width:100%"/>
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
								<div id='staticFields_container'>
									<?php foreach( $feed_staticFields as $sF ) {
										$valuePair = explode( "=", $sF );
										?>
										<div>
											<input type='text'
											       name='staticFields_field[]'
											       value='<?php echo $valuePair[0]; ?>'
											/> = <input type='text'
											            name='staticFields_value[]'
											            value='<?php echo $valuePair[1]; ?>'
											/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
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
								<div id='varFields_container'>
									<?php
									$sFCount = 0;
									foreach( $feed_varFields as $vF ) {
										?>
										<div>
											API Field: <input type='text' name='varFields[]' value='<?php echo $vF; ?>'/>
											Mapped To: <select name='fieldMap[]'>
												<?php
												$sortedFields = array_merge( $recordFields, $additionalMapFields );
												asort( $sortedFields );
												foreach( $sortedFields as $rF ) { ?>
													<option value='<?php echo htmlentities( $rF, ENT_QUOTES ); ?>' <?php if( $feed_fieldMap[$sFCount] == $rF ) {
														echo "selected='selected'";
													} ?>><?php echo htmlentities( $rF ); ?></option>
												<?php } ?>
											</select>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
										</div>
										<?php
										$sFCount++;
									}
									?>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<td><p>Field Value Translation</p></td>
						<td>
							<p>Send a different field value to the outgoing feed than was received on the inbound feed.
							</p>
							<p>
								<a href='#' class='nonLink' onclick='element("varValues_container", "varValue", {});'>Add New Value Translation</a>
							</p>
							<div>
								<div id='varValues_container'>
									<?php
									foreach( $valueMap as $vF ) {
										?>
										<div>
											Field: <select name='valueMap[field][]'>
												<?php foreach( $recordFields as $rF ) { ?>
													<option value='<?php echo htmlentities( $rF, ENT_QUOTES ); ?>' <?php if( isset( $vF['field'] ) && $vF['field'] == $rF ) {
														echo "selected='selected'";
													} ?>><?php echo htmlentities( $rF ); ?></option>
												<?php } ?>
											</select>
											Incoming Value: <input type='text' name='valueMap[oldValue][]' value='<?php echo htmlentities( $vF['oldValue'] ?? '' ); ?>'/>
											Outgoing Value: <input type='text' name='valueMap[newValue][]' value='<?php echo htmlentities( $vF['newValue'] ?? '' ); ?>'/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
										</div>
										<?php
									}
									?>
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
									<?php foreach( $feed_urlassignments as $uA ) {
										$valuePair = explode( "=", $uA );
										?>
										<div>
											<input type='text'
											       name='urlassignments_url[]'
											       value='<?php echo $valuePair[0]; ?>'
											/> = <input type='text'
											            name='urlassignments_id[]'
											            value='<?php echo $valuePair[1]; ?>'
											/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
										</div>
									<?php } ?>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<td><p>XML DTD/Schema</p></td>
						<td>
							<p>This is only required for SOAP and XML feeds. Define the XML schema to be sent.</p>
							<p>
								<textarea name="xmlDTD" style="width:100%; height:300px;"><?php echo htmlentities( $feed_xmlDTD ); ?></textarea>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Success String</p></td>
						<td>
							<p>This is the smallest form of the success response from the receiving client's API spec.</p>
							<p>
								<input type='text' name='successString' value='<?php echo htmlentities( $feed_successString ); ?>' class='long'/>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Daily Feed Limit</p></td>
						<td>
							<p>Leave blank for no limit (default). If a value is supplied here, the feed will stop sending records after the daily limit is reached.</p>
							<p>
								<input type='text' name='dailyLimit' value='<?php echo $feed_dailyLimit; ?>'/>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Feed Delay</p></td>
						<td>
							<p>Leave blank for no delay (default). If a value is supplied here, records will sit in the queue for this number of minutes before being processed.</p>
							<p>
								<input type='text' name='delay' value='<?php echo $feed_delay; ?>'/> Minutes
							</p>
							<p>
								<input type='radio' name='delayDump' value='0' <?php if( empty( $feed_delayDump ) ) { ?>checked='checked'<?php } ?>/> Trickle dump delayed records based on actual timestamps (default)<br/>
								<input type='radio' name='delayDump' value='1' <?php if( !empty( $feed_delayDump ) ) { ?>checked='checked'<?php } ?>/> Mass dump all delayed records for the entire day
							</p>

						</td>
					</tr>
					<tr>
						<td>Threshold Notifications</p></td>
						<td>
							<p>Send an email notification if we have not sent <input type="text" name="notifyThresholdCount" value="<?php echo htmlentities( $feed_notifyThresholdCount ); ?>"/> leads by <input type="text" name="notifyThresholdTime" placeholder="Example: 10:00AM" value="<?php echo htmlentities( $feed_notifyThresholdTimeFormatted ); ?>"/> on<br/>
								<?php for( $i = 0; $i <= 6; $i++ ) { ?>
									<label style="margin-right:1.5em; font-weight: normal;"><input type="checkbox" name="notifyThresholdDays[]" value="<?php echo $i; ?>" <?php if( in_array( $i, $selectedNotifyThresholdDays ) ){ ?>checked="checked"<?php } ?> />&nbsp;<?php echo $dowMap[$i]; ?></label>
								<?php } ?>
							</p>
							<p><strong>To disable notifications from being sent, set the lead count to zero or uncheck all day boxes.</strong></p>
						</td>
					</tr>
					<tr>
						<td><p>Revenue and Cost Per Lead</p></td>
						<td>
							<p>
								RPL: <input type="text" name="revenuePerLead" value="<?php echo htmlentities( $feed_revenuePerLead ); ?>"/> CPL Override: <input type="text" name="costPerLeadOverride" value="<?php echo htmlentities( $feed_costPerLeadOverride ); ?>"/><br/>
								If a value is set for CPL Override (including a 0.00 amount), this will override the CPL set on the incoming feed. To use the default CPL from the incoming feed, leave this field completely blank.
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Salesperson Override</p></td>
						<td>
							<p>By default, salesperson revenues are assigned at a company level. Only set this value if you are overriding the company-level salesperson with a feed-level salesperson.</p>
							<p>
								<select name="salesperson">
									<option></option>
									<?php
									$users = $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true );
									foreach( $users as $idUser => $fullName ) {
										printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
											Display::escHtml( $idUser ),
											$feed_salesperson == $idUser ? ' selected="selected"' : '',
											Display::escHtml( $fullName )
										);
									}
									?>
								</select>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Launch Date</p></td>
						<td>
							<p>
								<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
									<input type='text' name='launchDate' value='<?php echo htmlentities( $feed_launchDate ); ?>'/>
								<?php } else { ?>
									<?php echo htmlentities( $feed_launchDate ); ?>
								<?php } ?>
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Feed Status</p></td>
						<td>
							<p>
								<input type='radio' name='status' value='active' <?php if( empty( $feed_status ) || 'active' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Visible)<br/>
								<input type='radio' name='status' value='hidden' <?php if( 'hidden' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Hidden)<br/>
								<input type='radio' name='status' value='retired' <?php if( 'retired' == $feed_status ) { ?>checked='checked'<?php } ?>/> Retired
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Processing Schedule</p></td>
						<td>
							<p>By default, leads are passed to clients 24 hours a day, 7 days a week. To limit which days and/or times leads are passed, check off the days and input the times you would like leads to be passed. If you would like to pass leads for the entire day, you may leave both the start and end times blank, otherwise both must be filled in if restricted to certain times.</p>
							<table>
								<tr>
									<?php
									$processing_schedule = json_decode( !empty( $feed_processingSchedule ) ? $feed_processingSchedule : '{"sun":{"enabled":true,"startTime":"","endTime":""},"mon":{"enabled":true,"startTime":"","endTime":""},"tue":{"enabled":true,"startTime":"","endTime":""},"wed":{"enabled":true,"startTime":"","endTime":""},"thu":{"enabled":true,"startTime":"","endTime":""},"fri":{"enabled":true,"startTime":"","endTime":""},"sat":{"enabled":true,"startTime":"","endTime":""}}' );
									$schedule_array = array( 'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat' );
									foreach( $schedule_array as $id => $day ) { ?>
										<td>
											<p style="text-transform:capitalize;">
												<input type="checkbox"
												       id="<?php echo $day; ?>-times"
												       name="<?php echo $day; ?>_schedule"
												       value="enabled"
												       onclick="enableTextBox('<?php echo $day; ?>-times')"
													<?php echo !empty( $processing_schedule->$day->enabled ) ? ' checked="checked"' : ''; ?>> <?php echo $day; ?>
											</p>
											<p>
												<input type="text"
												       id="<?php echo $day; ?>_start"
												       class="<?php echo $day; ?>-times"
												       name="<?php echo $day; ?>_start"
												       placeholder="Start Time"
												       style="width: 100%"
												       pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]"
												       value="<?php echo !empty( $processing_schedule->$day->startTime ) ? Display::escHtml( $processing_schedule->$day->startTime ) : ''; ?>"
													<?php echo empty( $processing_schedule->$day->enabled ) ? ' disabled' : ''; ?>><br/>
												<input type="text"
												       id="<?php echo $day; ?>_end"
												       class="<?php echo $day; ?>-times"
												       name="<?php echo $day; ?>_end"
												       placeholder="End Time"
												       style="width: 100%"
												       pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]"
												       value="<?php echo !empty( $processing_schedule->$day->endTime ) ? Display::escHtml( $processing_schedule->$day->endTime ) : ''; ?>"
													<?php echo empty( $processing_schedule->$day->enabled ) ? ' disabled' : ''; ?>>
											</p>
										</td>
									<?php } ?>
								</tr>
							</table>
							<p>Enter start/end time in 24 hour format (HH:MM).</p>
						</td>
					</tr>
				</table>
			</form>
			<script type="text/javascript" language="javascript">

				function enableTextBox(classname) {
					if (document.getElementById(classname).checked == true) {
						var status = false;
					} else {
						var status = true;
					}
					var allItems = document.getElementsByClassName(classname);
					for (var i = 0; i < allItems.length; i++) {
						allItems[i].disabled = status;
					}
				}

				$('input[name=launchDate]').datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});
			</script>
			<?php
			break;

		case 'dialog_export':
			$idFeedOut = $_REQUEST['idFeedOut'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ) ) {
				die( 'Sorry, you do not have permission to export data.' );
			}

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedOut ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getOutboundFeed( $idFeedOut );
			?>
			<?php
			if( $feed === false ) {
				?>
				<p>Database failure - could not fetch feed information.</p>
				<?php
			} else if( !is_object( $feed ) && $feed == 0 ) {
				?>
				<p>Error fetching feed information - feed does not exist.</p>
				<?php
			} else {
				?>
				<p>Exporting Data from Feed (ID:<?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?></p>
				<form id="form-export">
					<input type="hidden" name="idFeedOut" value="<?php echo $feed->idFeedOut; ?>"/>
					<input type="hidden" name="a" value="exportData"/>
					<input type="hidden" name="label" value="<?php echo htmlspecialchars( $feed->label, ENT_QUOTES ); ?>"/>
					<table class="table table-bordered table-condensed table-striped">
						<tr>
							<td colspan='2'><p class='aCenter'>Export Settings</p></td>
						</tr>
						<tr>
							<td>
								Period
							</td>
							<td>
								<p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
								<p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date( "Y-m-d" ); ?>'/>
									to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date( "Y-m-d", strtotime( 'Tomorrow' ) ); ?>'/></p>
							</td>
						</tr>
						<tr>
							<td>
								URLs
							</td>
							<td>
								<p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
								<p><a href='#' class='nonLink' onclick='element("export_<?php echo $idFeedOut; ?>_urls", "urlField", {"idFeedOut": <?php echo $idFeedOut; ?>} );'>Add URL</a></p>
								<div>
									<div id='export_<?php echo $idFeedOut; ?>_urls'>
									</div>
								</div>
								</p>
							</td>
						</tr>
						<tr>
							<td>
								Email domains
							</td>
							<td>
								<p>Email domains to limit the selection by. Leave blank to select all records regardless of email address. Do not include the @ symbol.</p>
								<p><a href='#' class='nonLink' onclick='element("export_<?php echo $idFeedOut; ?>_emails", "emailField", {"idFeedOut": <?php echo $idFeedOut; ?>} );'>Add email domain</a></p>
								<div>
									<div id='export_<?php echo $idFeedOut; ?>_emails'>
									</div>
								</div>
								</p>
							</td>
						</tr>
						<tr>
							<td>
								Limit</p>
							</td>
							<td>
								<p>Set a limit on the number of records that are returned. Leave blank to return ALL records.</p>
								<p><input type="text" name="limit" value=""/></p>
								</p>
							</td>
						</tr>
						<tr>
							<td>
								Rejects</p>
							</td>
							<td>
								<p><input type="checkbox" name="includeRejects" value="1"/> Include rejected records in the export.</p>
							</td>
						</tr>
					</table>
				</form>
				<?php
			}
			break;

		case 'dialog_import':
			$idFeedOut = $_REQUEST['idFeedOut'] ?? '';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				die( 'Sorry, you do not have permission to import data.' );
			}

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
			?>
			<?php
			if( $feed === false ) {
				?>
				<p>Database failure - could not fetch feed information.</p>
				<?php
			} else if( !is_object( $feed ) && $feed == 0 ) {
				?>
				<p>Error fetching feed information - feed does not exist.</p>
				<?php
			} else {

				$populations = $leads->getPopulations( $feed->idFeedOut );
				if( empty( $populations ) || !is_array( $populations ) ) {

					print '<p>Error: No popluations are setup for this feed.</p>';

				} else {

					?>
					<p>Importing Data into Feed (ID:<?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?></p>
					<form id="form-import">
						<input type="hidden" name="idFeedOut" value="<?php echo $feed->idFeedOut; ?>"/>
						<input type="hidden" name="a" value="import-legacy-data"/>
						<input type="hidden" name="label" value="<?php echo htmlspecialchars( $feed->label, ENT_QUOTES ); ?>"/>
						<table class="table table-bordered table-condensed table-striped">
							<tr>
								<td colspan='2'><p class='aCenter'>Import Settings</p></td>
							</tr>
							<tr>
								<td>
									Inbound population
								</td>
								<td>
									<select name="idAssoc">
										<?php foreach( $populations as $population ) {
											$feedIn = $leads->getInboundFeed( $population->idFeedIn );
											printf( '<option value="%s">Pop #%s - Feed In #%s (%s)</option>' . PHP_EOL,
												$population->idAssoc,
												$population->idAssoc,
												$population->idFeedIn,
												htmlspecialchars( $feedIn->label, ENT_QUOTES )
											);
										} ?>
									</select>
								</td>
							</tr>
							<tr>
								<td>
									Period
								</td>
								<td>
									<p>Period goes from midnight of the first date to 11:59p of the second date. Maximum of 6 months in the past.</p>
									<p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date( "Y-m-d" ); ?>'/>
										to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date( "Y-m-d", strtotime( 'Tomorrow' ) ); ?>'/></p>
								</td>
							</tr>
							<tr>
								<td>
									Limit</p>
								</td>
								<td>
									<p>Set a limit on the number of records that are returned. Leave blank to return ALL records.</p>
									<p><input type="text" name="limit" value=""/></p>
									</p>
								</td>
							</tr>
							<tr>
								<td>
									Rejects</p>
								</td>
								<td>
									<p><input type="checkbox" name="includeRejects" value="1" checked="checked"/> Include live feed rejections and choked records in the import.</p>
								</td>
							</tr>
						</table>
					</form>
					<?php
				}
			}
			break;

		case 'dialog_upload':
			$idFeedOut = $_REQUEST['idFeedOut'];

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

			if( $feed === false ) {
				?>
				<p>Database failure - could not fetch feed information.</p>
				<?php
			} else if( !is_object( $feed ) && $feed == 0 ) {
				?>
				<p>Error fetching feed information - feed does not exist.</p>
				<?php
			} else {

				$company = $leads->getCompany( $feed->idCompany );

				?>
				<p><strong>Company:</strong> <?php echo htmlentities( $company->name ); ?></p>
				<p><strong>Feed:</strong> <?php echo htmlentities( $feed->label ); ?> (#<?php echo $feed->idFeedOut; ?>)</p>

				<form enctype="multipart/form-data" id="form-upload" action="mgr_import.php" method="post" target="_blank">
					<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE; ?>"/>
					<input type="hidden" name="destination" value="<?php echo intval( $idFeedOut ); ?>"/>
					<input type="hidden" name="type" value="upload-outbound"/>
					<input type="hidden" name="a" value="import"/>

					<table class="table table-bordered table-condensed table-striped">
						<tr>
							<td>File</p></td>
							<td>Please select the file to upload from your computer. File must be in CSV format. Limit <?php echo( MAX_UPLOAD_SIZE / 1024000 ); ?>MB.</p><input type="file" name="import_file" multiple="false" accept="text/csv"/></p></td>
						</tr>
						<tr>
							<td>Field mapping</p></td>
							<td>
								<?php
								$allowedFields = $recordFields;
								$requiredFields = array();

								// Add a separate time field in case the file uses separate columns
								if( ( $key = array_search( 'stamp', $allowedFields ) ) !== false ) {
									array_splice( $allowedFields, $key + 1, 0, 'time' );
								}

								foreach( $allowedFields as $field ) {
									printf( "<p>%s%s <select name=\"field_%s\">",
										$field, in_array( $field, $requiredFields ) ? '*' : '', $field );
									print "<option>--</option>\n";
									for( $i = 0; $i < 26; $i++ ) {
										print "<option value=\"{$i}\">" . chr( 65 + $i ) . "</option>\n";
									}
									print "</select>";
									if( 'stamp' == $field ) {
										print " (Use for either a full date+time stamp or just a date stamp field)";
									} else if( 'time' == $field ) {
										print " (Use for just a time stamp field)";
									}
									print "</p>\n";
								}

								?>
							</td>
						</tr>
					</table>
				</form>
				<?php
			}
			break;

		case 'dialog_retry_rejections':
			$idFeedOut = $_REQUEST['idFeedOut'] ?? '';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				die( 'Sorry, you do not have permission to retry outbound rejections.' );
			}

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
			?>
			<?php
			if( $feed === false ) {
				?>
				<p>Database failure - could not fetch feed information.</p>
				<?php
			} else if( !is_object( $feed ) && $feed == 0 ) {
				?>
				<p>Error fetching feed information - feed does not exist.</p>
				<?php
			} else {

				$populations = $leads->getPopulations( $feed->idFeedOut );
				if( empty( $populations ) || !is_array( $populations ) ) {

					print '<p>Error: No popluations are setup for this feed.</p>';

				} else {

					?>
					<p>Retry rejections for Feed (ID:<?php echo $feed->idFeedOut; ?>) <?php echo $feed->label; ?></p>
					<form id="form-import">
						<input type="hidden" name="idFeedOut" value="<?php echo $feed->idFeedOut; ?>"/>
						<input type="hidden" name="a" value="retry-outbound-rejections"/>
						<input type="hidden" name="label" value="<?php echo htmlspecialchars( $feed->label, ENT_QUOTES ); ?>"/>
						<table class="table table-bordered table-condensed table-striped">
							<tr>
								<td colspan='2'><p class='aCenter'>Retry Rejections Settings</p></td>
							</tr>
							<tr>
								<td>
									Period
								</td>
								<td>
									<p>Period goes from midnight of the first date to 11:59p of the second date. Maximum of 6 months in the past.</p>
									<p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date( "Y-m-d" ); ?>'/>
										to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date( "Y-m-d", strtotime( 'Tomorrow' ) ); ?>'/></p>
								</td>
							</tr>
						</table>
					</form>
					<?php
				}
			}
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

			$_REQUEST['dateStart'] = !empty( $_REQUEST['dateStart'] ) ? $_REQUEST['dateStart'] : date( "Y-m-d" );
			$_REQUEST['dateEnd'] = !empty( $_REQUEST['dateEnd'] ) ? $_REQUEST['dateEnd'] : date( "Y-m-d", strtotime( 'Tomorrow' ) );
			$_REQUEST['urlList'] = !empty( $_REQUEST['urlList'] ) && is_array( $_REQUEST['urlList'] ) ? $_REQUEST['urlList'] : array();
			$_REQUEST['breakdown'] = !empty( $_REQUEST['breakdown'] ) ? $_REQUEST['breakdown'] : 'day';
			$_REQUEST['sort'] = !empty( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : 'date';
			$_REQUEST['group'] = !empty( $_REQUEST['group'] ) ? $_REQUEST['group'] : 'date';

			$feed = $leads->getOutboundFeed( $idFeedOut );

			if( $feed === false ) {
				?>
				<p>Database failure - could not fetch feed information.</p>
				<?php
			} else if( !is_object( $feed ) && $feed == 0 ) {
				?>
				<p>Error fetching feed information - feed does not exist.</p>
				<?php
			} else {
				?>
				<p>Feed ID: <strong><?php echo $feed->idFeedOut; ?></strong><br/>Feed Label: <strong><?php echo htmlspecialchars( $feed->label, ENT_QUOTES ); ?></strong></p>

				<form id="form-urlreport" class="form-inlin1e">
					<input type="hidden" name="idFeedOut" value="<?php echo $feed->idFeedOut; ?>"/>
					<input type="hidden" name="d" value="dialog_urlreport"/>
					<input type="hidden" name="submit" value="submit"/>

					<p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
					<div class="form-group">
						<label for="dateStart">Start Date:</label>
						<input type="text" id="dateStart" name="dateStart" class="form-control dateSelector" value="<?php echo htmlspecialchars( $_REQUEST['dateStart'], ENT_QUOTES ); ?>"/>
					</div>

					<div class="form-group">
						<label for="dateEnd">End Date:</label>
						<input type="text" id="dateEnd" name="dateEnd" class="form-control dateSelector" value="<?php echo htmlspecialchars( $_REQUEST['dateEnd'], ENT_QUOTES ); ?>"/>
					</div>

					<p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
					<div class="form-group">
						<label for="urls">URLs:</label>
						<?php
						$urls = $leads->getOutboundURLDates( $idFeedOut );
						if( $urls && is_array( $urls ) ) {
							printf( "<select class=\"form-control\" id=\"urls\" multiple=\"multiple\" name=\"urlList[]\" size=\"%d\">\n", sizeOf( $urls ) );
							foreach( $urls as $url ) {
								printf( "<option value=\"%s\"%s>%s (%s)</option>\n", htmlspecialchars( $url['url'], ENT_QUOTES ), in_array( $url['url'], $_REQUEST['urlList'] ) ? ' selected="selected"' : '', htmlspecialchars( $url['url'] ), $url['date'] );
							}
							print "</select>\n";
						}
						?>
					</div>

					<div class="form-group">
						<label for="breakdown">Count By:</label>
						<select class="form-control" id="breakdown" name="breakdown">
							<?php
							$choices = array(
								'day' => 'Day',
								'month' => 'Month',
								'year' => 'Year',
								'total' => 'Total',
							);
							foreach( $choices as $key => $val ) {
								printf( "<option value=\"%s\"%s>%s</option>\n",
									htmlspecialchars( $key, ENT_QUOTES ),
									$_REQUEST['breakdown'] === $key ? ' selected="selected"' : '',
									htmlspecialchars( $val )
								);
							}
							?>
						</select>
					</div>

					<div class="form-group">
						<label for="id">Sort By:</label>
						<select class="form-control" id="sort" name="sort">
							<?php
							$choices = array(
								'date' => 'Date',
								'url' => 'URL',
								'count' => 'Count',
							);
							foreach( $choices as $key => $val ) {
								printf( "<option value=\"%s\"%s>%s</option>\n",
									htmlspecialchars( $key, ENT_QUOTES ),
									$_REQUEST['sort'] === $key ? ' selected="selected"' : '',
									htmlspecialchars( $val )
								);
							}
							?>
						</select>
					</div>

					<div class="form-group">
						<label for="id">Group By:</label>
						<select class="form-control" id="group" name="group">
							<?php
							$choices = array(
								'date' => 'Date',
								'url' => 'URL',
							);
							foreach( $choices as $key => $val ) {
								printf( "<option value=\"%s\"%s>%s</option>\n",
									htmlspecialchars( $key, ENT_QUOTES ),
									$_REQUEST['group'] === $key ? ' selected="selected"' : '',
									htmlspecialchars( $val )
								);
							}
							?>
						</select>
					</div>

				</form>
				<?php

				if( !empty( $_REQUEST['submit'] ) ) {

					$stats = $leads->getOutboundURLStatsReport( $_REQUEST['idFeedOut'], $_REQUEST['urlList'], $_REQUEST['breakdown'], $_REQUEST['dateStart'], $_REQUEST['dateEnd'], $_REQUEST['sort'], $_REQUEST['group'] );

					if( empty( $stats ) ) {
						?>
						<p>No records found.</p>
						<?php
					} else {

						$fileLink = 'exports/' . $feed->idFeedOut . "_" . time() . ".csv";
						$filePath = ADMIN_ROOT . $fileLink;
						$file = fopen( $filePath, "w" );
						if( !file_exists( $filePath ) ) {
							?>
							<p>Failed to create CSV report file.</p>
							<?php
						} else {
							$accepted = 0;
							$rejected = 0;
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
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( 'date' == $_REQUEST['group'] ? 'N/A' : $stat['url'] ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $stat['date'] ) );
								printf( "\t\t<td>%s</td>\n", number_format( $stat['accepted'], 0 ) );
								printf( "\t\t<td>%s</td>\n", number_format( $stat['rejected'], 0 ) );
								print "\t</tr>\n";
								$accepted += $stat['accepted'];
								$rejected += $stat['rejected'];
								fputcsv( $file, array( 'date' == $_REQUEST['group'] ? 'N/A' : $stat['url'], $stat['date'], $stat['accepted'], $stat['rejected'] ) );
							}
							fclose( $file );
							print "\t<tr>\n";
							print "\t\t<td colspan=\"2\"><strong>GRAND TOTAL</strong></td>\n";
							printf( "\t\t<td>%s</td>\n", number_format( $accepted, 0 ) );
							printf( "\t\t<td>%s</td>\n", number_format( $rejected, 0 ) );
							print "\t</tr>\n";
							print "</tbody>\n";
							print "</table>\n";
							printf( '<p><a <a class="btn btn-primary" href="%s">Export this report</a></p>', $fileLink );
						}
					}

				}

			}
			break;

		case 'staticField':
			$e = $_REQUEST['e'] ?? '';
			?>
			<div>
				<input type='text' name='staticFields_field[]' value=''/> = <input type='text' name='staticFields_value[]' value=''/>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
			</div>
			<?php
			break;
		case 'urlassignment':
			$e = $_REQUEST['e'] ?? '';
			?>
			<div>
				<input type='text' name='urlassignments_url[]' value='' placeholder='URL'/> = <input type='text' name='urlassignments_id[]' value='' placeholder='Unique ID'/>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
			</div>
			<?php
			break;
		case 'varField':
			$e = $_REQUEST['e'] ?? '';
			?>
			<div>
				API Field: <input type='text' name='varFields[]' value=''/> Mapped To: <select name='fieldMap[]'>
					<?php foreach( $recordFields as $rF ) { ?>
						<option value='<?php echo htmlentities( $rF, ENT_QUOTES ); ?>'><?php echo htmlentities( $rF ); ?></option>
					<?php } ?>
					<?php foreach( $additionalMapFields as $aF ) { ?>
						<option value='<?php echo htmlentities( $aF, ENT_QUOTES ); ?>'><?php echo htmlentities( $aF ); ?></option>
					<?php } ?>
				</select>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
			</div>
			<?php
			break;
		case 'varValue':
			$e = $_REQUEST['e'] ?? '';
			?>
			<div>
				Field: <select name='valueMap[field][]'>
					<?php foreach( $recordFields as $rF ) { ?>
						<option value='<?php echo htmlentities( $rF, ENT_QUOTES ); ?>'><?php echo htmlentities( $rF ); ?></option>
					<?php } ?>
				</select>
				Incoming Value: <input type='text' name='valueMap[oldValue][]' value=''/>
				Outgoing Value: <input type='text' name='valueMap[newValue][]' value=''/>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
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
				'queueType',
				'startDate',
				'waterfallPriority',
			);
			foreach( $populationProperties as $pP ) {
				if( isset( $popset ) ) {
					${"popset_" . $pP} = $popset->$pP;
				} else if( isset( $_REQUEST[$pP] ) ) {
					${"popset_" . $pP} = $_REQUEST[$pP];
				} else {
					${"popset_" . $pP} = '';
				}
			}
			$explodableProperties = array(
				'filterUrl',
				'filterEmail',
				'filterListcode',
				'forceUrlList',
			);
			foreach( $explodableProperties as $eP ) {
				if( !isset( $_REQUEST[$eP] ) ) {
					if( !isset( $popset->$eP ) ) {
						${"popset_" . $eP} = array();
					} else {
						${"popset_" . $eP} = explode( ";", $popset->$eP );
					}
				} else {
					if( $_REQUEST[$eP] == '' ) {
						${"popset_" . $eP} = array();
					} else {
						${"popset_" . $eP} = explode( ";", $_REQUEST[$eP] );
					}
				}
			}
			$feed = $leads->getOutboundFeed( $popset_idFeedOut );

			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$incomingFeeds = $leads->getInboundFeeds( null, 'active', null, $popset_idFeedIn );
			} else {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				$incomingFeeds = $leads->getInboundFeeds( $idCompany, 'active' );
			}
			?>
			<form id="<?php echo $mode; ?>_pop">
				<input type="hidden" name="idAssoc" value="<?php echo $popset_idAssoc; ?>"/>
				<input type="hidden" name="idFeedOut" value="<?php echo $popset_idFeedOut; ?>"/>
				<input type="hidden" name="a" value="managePopulation"/>
				<input type="hidden" name="action" value="<?php echo $mode; ?>"/>
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
												htmlentities( $fI->name, ENT_QUOTES | ENT_HTML5 )
											);
										}
										?>
										<option value='<?php echo $fI->idFeedIn; ?>'
											<?php if( $fI->idFeedIn == $popset_idFeedIn ) {
												echo "selected='selected'";
											} ?>
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
									empty( $popset_filterTypeUrl )
									) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeUrl').hide(); <?php
									   ?>$('#filterUrl_descriptor').html('Do nothing with');"
								/> Disabled<br/>
								<input type='radio'
								       name='filterTypeUrl'
								       id='filterTypeUrl_accept'
								       value='accept'
									<?php if( $popset_filterTypeUrl == 'accept' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeUrl').show(); <?php
									   ?>$('#filterUrl_descriptor').html('Accept');"
								/> Accept<br/>
								<input type='radio'
								       name='filterTypeUrl'
								       id='filterTypeUrl_reject'
								       value='reject'
									<?php if( $popset_filterTypeUrl == 'reject' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeUrl').show(); <?php
									   ?>$('#filterUrl_descriptor').html('Reject');"
								/> Reject<br/>
							</p>
							<div id='toggler_filterTypeUrl'
							     style='display:<?php
							     if( empty( $popset_filterTypeUrl ) ) {
								     echo "none";
							     } else {
								     echo "block";
							     }
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
									<?php foreach( $popset_filterUrl as $filterUrl ) { ?>
										<div>
											<input type='text'
											       name='filterUrl[]'
											       value='<?php echo $filterUrl; ?>'
											/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
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
									empty( $popset_filterTypeEmail )
									) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeEmail').hide(); <?php
									   ?>$('#filterEmail_descriptor').html('Do nothing with');"
								/> Disabled<br/>
								<input type='radio'
								       name='filterTypeEmail'
								       id='filterTypeEmail_accept'
								       value='accept'
									<?php if( $popset_filterTypeEmail == 'accept' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeEmail').show(); <?php
									   ?>$('#filterEmail_descriptor').html('Accept');"
								/> Accept<br/>
								<input type='radio'
								       name='filterTypeEmail'
								       id='filterTypeEmail_reject'
								       value='reject'
									<?php if( $popset_filterTypeEmail == 'reject' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeEmail').show(); <?php
									   ?>$('#filterEmail_descriptor').html('Reject');"
								/> Reject<br/>
							</p>
							<div id='toggler_filterTypeEmail'
							     style='display:<?php
							     if( empty( $popset_filterTypeEmail ) ) {
								     echo "none";
							     } else {
								     echo "block";
							     }
							     ?>;'
							>
								<p>The following email domains:</p>
								<p>
									<a href='#' class='nonLink'
									   onclick='element("filterEmail_container", "element_filter", { "e": "<?php echo $e ?? ''; ?>", "type": "Email"});'
									>Add New Email Domain to <span id='filterEmail_descriptor'></span></a>
								</p>
								<div id='filterEmail_container'>
									<?php foreach( $popset_filterEmail as $filterEmail ) { ?>
										<div>
											<input type='text'
											       name='filterEmail[]'
											       value='<?php echo $filterEmail; ?>'
											/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
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
									empty( $popset_filterTypeListcode )
									) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeListcode').hide(); <?php
									   ?>$('#filterListcode_descriptor').html('Do nothing with');"
								/> Disabled<br/>
								<input type='radio'
								       name='filterTypeListcode'
								       id='filterTypeListcode_accept'
								       value='accept'
									<?php if( $popset_filterTypeListcode == 'accept' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeListcode').show(); <?php
									   ?>$('#filterListcode_descriptor').html('Accept');"
								/> Accept<br/>
								<input type='radio'
								       name='filterTypeListcode'
								       id='filterTypeListcode_reject'
								       value='reject'
									<?php if( $popset_filterTypeListcode == 'reject' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_filterTypeListcode').show(); <?php
									   ?>$('#filterListcode_descriptor').html('Reject');"
								/> Reject<br/>
							</p>
							<div id='toggler_filterTypeListcode'
							     style='display:<?php
							     if( empty( $popset_filterTypeListcode ) ) {
								     echo "none";
							     } else {
								     echo "block";
							     }
							     ?>;'
							>
								<p>The following email domains:</p>
								<p>
									<a href='#' class='nonLink'
									   onclick='element("filterListcode_container", "element_filter", { "e": "<?php echo $e ?? ''; ?>", "type": "Listcode"});'
									>Add New Listcode to <span id='filterListcode_descriptor'></span></a>
								</p>
								<div id='filterListcode_container'>
									<?php foreach( $popset_filterListcode as $filterListcode ) { ?>
										<div>
											<input type='text'
											       name='filterListcode[]'
											       value='<?php echo $filterListcode; ?>'
											/>
											<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
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
									<?php if( $popset_forceUrl != '1' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_forceUrlList').hide();"
								/> Disabled<br/>
								<input type='radio'
								       name='forceUrl'
								       id='forceUrl_enabled'
								       value='1'
									<?php if( $popset_forceUrl == '1' ) { ?>
										checked='checked'
									<?php } ?>
									   onclick="$('#toggler_forceUrlList').show();"
								/> Enabled
							</p>
							<div id='toggler_forceUrlList'
							     style='display:<?php
							     if( $popset_forceUrl ) {
								     echo "block";
							     } else {
								     echo "none";
							     }
							     ?>;'
							>
								<p>
									Enter 'all' in the url field to force all non-specified urls to be changed to the new
									url. Other specified urls will be changed to the listed forced url.
								</p>
								<div>
									<p>Force Populating URLs to: </p>
									<p>
										<a href='#' class='nonLink' onclick='element("filterUrlList_container", "element_forceUrl", { "e": "<?php echo $e ?? ''; ?>"});'
										>Add URL To Force</a>
									</p>
									<div id='filterUrlList_container'>
										<?php foreach( $popset_forceUrlList as $fU ) {
											$valuePair = explode( "=", $fU );
											?>
											<div>
												URL: <input type='text'
												            name='forceUrlList_original[]'
												            value='<?php echo $valuePair[0]; ?>'
												/> Will be populated as: <input type='text'
												                                name='forceUrlList_altered[]'
												                                value='<?php echo $valuePair[1]; ?>'
												>
												<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
											</div>
										<?php } ?>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<td><p>Queue Type</p></td>
						<td>
							<p>
								Incoming records will be sent to this provider in REAL TIME as they come in. Do not use this option unless authorized. Most feeds have this option disabled.
							</p>
							<p>
								<input type="radio" name="queueType" id="queueType_livedata" value="livedata" <?php if( empty( $popset_queueType ) || $popset_queueType == 'livedata' ) { ?> checked="checked" <?php } ?>/> Live Data (leads sent in real-time) [DEFAULT]<br/>
								<input type="radio" name="queueType" id="queueType_queue" value="queue" <?php if( $popset_queueType == 'queue' ) { ?> checked="checked" <?php } ?>/> Standard Queue<br/>
								<input type="radio" name="queueType" id="queueType_waterfall" value="waterfall" <?php if( $popset_queueType == 'waterfall' ) { ?> checked="checked" <?php } ?>/> Waterfall Live Standard (attempt each vendor in descending priority order; stop after the first accepted response)<br/>
								<input type="radio" name="queueType" id="queueType_waterfallLimit" value="waterfallLimit" <?php if( $popset_queueType == 'waterfallLimit' ) { ?> checked="checked" <?php } ?>/> Waterfall Limit &amp; Queue (attempt vendors in priority order and queue; only skip to the next after the feed limits are hit)<br/>
								<input type="radio" name="queueType" id="queueType_waterfallLimitLive" value="waterfallLimitLive" <?php if( $popset_queueType == 'waterfallLimitLive' ) { ?> checked="checked" <?php } ?>/> Waterfall Limit Live (attempt vendors in real-time in priority order; only skip to the next after the feed limits are hit)
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Waterfall Priority</p></td>
						<td>
							<p>
								<input type="text" name="waterfallPriority" value="<?php echo htmlentities( $popset_waterfallPriority ); ?>"/><br/>
								Only applies if the Queue Type setting above is set to "Waterfall" or "Waterfall Limit". Use any number from 0 to 65,535. A higher number means higher priority in the waterfall.
							</p>
						</td>
					</tr>
					<tr>
						<td><p>Population Start Date</p></td>
						<td>
							<p>
								If a value is filled in here, then records will not start populating this queue until midnight of the date provided. When using this feature, it is recommended to turn the "Queueing" option ON, because if the "Queueing" option is set to off, then no records will be queued at all, even if the start date below passes.
							</p>
							<p>
								<input type="text" name="startDate" id="startDate" value="<?php echo Display::escHtml( $popset_startDate ); ?>"/>
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

				$('#new_pop input[name="startDate"], #edit_pop input[name="startDate"]').datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});

			</script>
			<?php
			break;

		case 'element_filter':
			$t = $_REQUEST['options']['type'];
			?>
			<div>
				<input type='text' name='filter<?php echo $t; ?>[]' value='<?php if( isset( $_REQUEST['value'] ) ) {
					echo $_REQUEST['value'];
				} ?>'/>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
			</div>
			<?php
			break;

		case 'element_forceUrl':
			?>
			<div>
				URL: <input type='text' name='forceUrlList_original[]' value=''/> Will be populated as: <input type='text' name='forceUrlList_altered[]' value=''/>
				<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
			</div>
			<?php
			break;

		case 'element_multifilter':
			$t = $_REQUEST['options']['type'];
			?>
			<textarea name='filter<?php echo $t; ?>Multi' id='filter<?php echo $t; ?>Multi'></textarea>
			<input type='button' value='Add Multiple Urls' onclick="splitMultiFilter('<?php echo $e ?? ''; ?>', '<?php echo $t; ?>');"/>
			<?php
			break;

		case 'dialog_editpopulation':
			$idFeedOut = !empty( [ 'idFeedOut' ] ) ? $_REQUEST['idFeedOut'] : 0;

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

			<p>
				<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#modal-newpop" data-dismiss="modal" data-feed-id="<?php echo $feed->idFeedOut; ?>">Add a new population parameter</button>
			</p>
			<?php
			if( $populationSettings === false ) {
				?>
				<p>Error getting population settings.</p>
				<?php
			} else if( $populationSettings == 0 ) {
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
					foreach( $populationSettings as $popSet ) {
						if( !isset( $cacheFeedIn[$popSet->idFeedIn] ) ) {
							$cacheFeedIn[$popSet->idFeedIn] = $leads->getInboundFeed( $popSet->idFeedIn );
							if( !is_object( $cacheFeedIn[$popSet->idFeedIn] ) ) {
								$cacheFeedIn[$popSet->idFeedIn] = new stdClass;
								$cacheFeedIn[$popSet->idFeedIn]->label = 'Error';
							}
						}
						$statusPopulation = ( $popSet->enabled ) ? 'Populating' : 'Disabled';
						if( is_null( $popSet->filterTypeUrl ) ) {
							$filterTypeUrl = 'Off';
							$filterUrl = 'Disabled';
						} else {
							$filterTypeUrl = 'On';
							if( $popSet->filterTypeUrl == 'accept' ) {
								$filterUrl = 'Accepting: ';
							} else {
								$filterUrl = 'Rejecting: ';
							}
							$filterUrls = explode( ';', $popSet->filterUrl );
							$comma = false;
							foreach( $filterUrls as $url ) {
								if( $comma ) {
									$filterUrl .= ', ';
								}
								$filterUrl .= $url;
								$comma = true;
							}
						}
						if( is_null( $popSet->filterTypeEmail ) ) {
							$filterTypeEmail = 'Off';
							$filterEmail = 'Disabled';
						} else {
							$filterTypeEmail = 'On';
							if( $popSet->filterTypeEmail == 'accept' ) {
								$filterEmail = 'Accepting: ';
							} else {
								$filterEmail = 'Rejecting: ';
							}
							$filterEmails = explode( ';', $popSet->filterEmail );
							$comma = false;
							foreach( $filterEmails as $email ) {
								if( $comma ) {
									$filterEmail .= ', ';
								}
								$filterEmail .= $email;
								$comma = true;
							}
						}
						if( is_null( $popSet->filterTypeListcode ) ) {
							$filterTypeListcode = 'Off';
							$filterListcode = 'Disabled';
						} else {
							$filterTypeListcode = 'On';
							if( $popSet->filterTypeListcode == 'accept' ) {
								$filterListcode = 'Accepting: ';
							} else {
								$filterListcode = 'Rejecting: ';
							}
							$filterListcodes = explode( ';', $popSet->filterListcode );
							$comma = false;
							foreach( $filterListcodes as $listcode ) {
								if( $comma ) {
									$filterListcode .= ', ';
								}
								$filterListcode .= $listcode;
								$comma = true;
							}
						}
						if( $popSet->forceUrl ) {
							$forceUrl = 'On';
						} else {
							$forceUrl = 'Off';
						}
						$forceUrlListArray = explode( ";", $popSet->forceUrlList );
						$forceUrlList = 'No urls assigned for force urls.';
						if( $popSet->forceUrlList != '' && is_arraY( $forceUrlListArray ) ) {
							$forceUrlList = '';
							foreach( $forceUrlListArray as $valuePair ) {
								list( $original, $altered ) = explode( "=", $valuePair );
								if( $original == 'all' ) {
									$original = 'All Urls';
								}
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
								<input class="population-toggle" <?php if( !empty( $popSet->enabled ) ) {
									print 'checked="checked" ';
								} ?>data-toggle="toggle" data-size="mini" data-width="80" data-on="Queueing" data-onstyle="success" data-off="Queueing" data-offstyle="danger" data-assoc-id="<?php echo $popSet->idAssoc; ?>" type="checkbox"/></td>
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
								<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#modal-editpop" data-feed-id="<?php echo $feed->idFeedOut; ?>" data-assoc-id="<?php echo $popSet->idAssoc; ?>" data-dismiss="modal">Edit</button>
							</td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				<script type="text/javascript">
					$('.population-toggle').bootstrapToggle();

					$('.population-toggle').change(function () {
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
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Outgoing Feeds</h2>

	<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>

		<form class="pull-right" id="status-select" method="get">
			<select id="status" name="status">
				<option value="active"<?php if( 'active' === $status ) {
					print ' selected="selected"';
				} ?>>Show active feeds
				</option>
				<option value="hidden"<?php if( 'hidden' === $status ) {
					print ' selected="selected"';
				} ?>>Show hidden feeds
				</option>
				<option value="retired"<?php if( 'retired' === $status ) {
					print ' selected="selected"';
				} ?>>Show retired feeds
				</option>
				<option value=""<?php if( null === $status ) {
					print ' selected="selected"';
				} ?>>Show all feeds
				</option>
			</select>
		</form>

		<p>
			<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#newfeed" data-feed-id="">Add a new feed</button>
		</p>

	<?php } ?>

	<?php

	foreach( $feedCategories

	         as $categoryKey => $categoryVal ) {

		print "<h4>Outgoing $categoryVal Feeds</h4>" . PHP_EOL;

		if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
			$outgoingFeeds = $leads->getOutboundFeeds( null, $status, $categoryKey );
		} else {
			$idCompany = LeadsSession::getCompanyId();
			if( empty( $idCompany ) ) {
				$idCompany = -9999;
			}
			$outgoingFeeds = $leads->getOutboundFeeds( $idCompany, $status, $categoryKey );
		}
		?>
		<?php
		if( $outgoingFeeds === false ) {
			?>
			<p>Error when trying to fetch feeds: database error.</p>
			<?php
		} else if( $outgoingFeeds == 0 ) {
			?>
			<p>Error when trying to fetch feeds: there are no feeds.</p>
			<?php
		} else {
			//Go through each and compile the company list.
			$companyFeedLists = array();
			foreach( $outgoingFeeds as $feed ) {
				//Add company to the cache list of companies.
				if( !isset( $companyCache[$feed->idCompany] ) ) {
					$company = $leads->getCompany( $feed->idCompany );
					if( is_object( $company ) ) {
						$companyCache[$feed->idCompany] = $company;
						$companyFeedLists[$feed->idCompany] = array();
					}
				}
				//Add feed to list of feeds for the specified company.
				$companyFeedLists[$feed->idCompany][] = $feed;
			}

			uksort( $companyFeedLists, 'companyListSort' );
			?>
			<table class="table table-bordered table-condensed table-striped-custom">
				<thead>
				<tr>
					<th class="fTO_companyName outgoing-col-large" colspan="4">Company</th>
					<th class="fTO_feedOverview outgoing-col-small" colspan="2">Total Feeds</th>
					<th class="fTO_accepted outgoing-col-small">Total Accepted</th>
					<th class="fTO_rejected outgoing-col-small">Total Rejected</th>
					<th class="fTO_rejected outgoing-col-small">Total Queued</th>
					<th class="fTO_options outgoing-col-small">Options</th>
				</tr>
				</thead>
				<?php
				$grandTotalFeeds = 0;
				$grandTotalAccepted = 0;
				$grandTotalRejected = 0;
				$grandTotalQueued = 0;
				foreach( $companyFeedLists as $idCompany => $companyFeedList ) {
					$totalAccepted = 0;
					$totalRejected = 0;
					$totalActive = 0;
					$totalQueued = 0;

					foreach( $companyFeedList as $keyFeed => $feed ) {

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

						if( 'active' === $feed->status ) {
							$totalActive++;
						}
						$companyFeedList[$keyFeed]->statusFeed = $feed->status;
						$companyFeedList[$keyFeed]->statusPop = $leads->getPopulationStatus( $feed->idFeedOut );
					}
					$grandTotalFeeds += count( $companyFeedList );
					?>
					<tr class='fTORow fTO_Row bgGray'>
						<td colspan='4'><?php echo $companyCache[$idCompany]->name; ?></td>
						<td colspan='2'><?php echo count( $companyFeedList ); ?> (<?php echo $totalActive; ?> Active)</td>
						<td class="text-right"><?php echo number_format( $totalAccepted, 0 ); ?></td>
						<td class="text-right"><?php echo number_format( $totalRejected, 0 ); ?></td>
						<td class="text-right"><?php echo number_format( $totalQueued, 0 ); ?></td>
						<td class="text-center">
							<button class="btn btn-primary btn-xs" type="button" data-toggle="collapse" data-target=".feed-toggle-<?php echo $idCompany; ?>" aria-expanded="false" aria-controls="collapseExample">Show Feeds</button>
						</td>
					</tr>
					<?php
					foreach( $companyFeedList as $feed ) {
						?>
						<tr class="collapse bg-gray feed-toggle feed-toggle-<?php echo $idCompany; ?>">
							<td><?php echo $feed->idFeedOut; ?></td>
							<td class='fTO_label status-<?php echo $feed->status; ?>'><?php echo htmlentities( $feed->label ); ?></td>
							<td><?php echo htmlentities( $feed->description ); ?></td>
							<td><?php echo htmlentities( $feed->statusPop ); ?></td>
							<td><?php echo ucfirst( $feed->status ); ?></td>
							<td><input class="cron-toggle" <?php if( !empty( $feed->cron ) ) {
									print 'checked="checked" ';
								} ?>data-toggle="toggle" data-size="mini" data-width="80" data-on="Pushing" data-onstyle="success" data-off="Pushing" data-offstyle="danger" data-feed-id="<?php echo $feed->idFeedOut; ?>" type="checkbox"/></td>
							<td class="text-right"><?php echo $feed->accepted; ?></td>
							<td class="text-right"><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode( $feed->idFeedOut ); ?>&amp;label=<?php echo urlencode( $feed->label ); ?>" target="_blank"><?php echo $feed->rejected; ?></a></td>
							<td class="text-right"><?php echo $feed->queued; ?></td>
							<td class="text-center">
								<div class="btn-group">
									<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editfeed" data-mode="edit" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Edit Feed</button>
									<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
										<span class="caret"></span>
										<span class="sr-only">Toggle Dropdown</span>
									</button>
									<ul class="dropdown-menu">
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-showpop" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Show populations</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#newfeed" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Duplicate feed</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-testrecord" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Send test record</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-clearqueue" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Clear queue</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-urlreport" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">URL report</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-import" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Import data</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-upload" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Upload data</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-export" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Export data</a></li>
										<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-retry-rejections" data-feed-id="<?php echo intval( $feed->idFeedOut ); ?>">Retry rejections</a></li>
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
					<button id="modal-save-testrecord" type="button" class="btn btn-primary">Submit</button>
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

	<div class="modal fade" id="modal-import" tabindex="-1" role="dialog" aria-labelledby="import_title">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="import_title">Import Legacy Data</h4>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button id="modal-save-import" type="button" class="btn btn-primary">Import</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modal-upload" tabindex="-1" role="dialog" aria-labelledby="upload_title">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="upload_title">Upload Legacy Data</h4>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button id="modal-save-upload" type="button" class="btn btn-primary">Upload</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modal-retry-rejections" tabindex="-1" role="dialog" aria-labelledby="retry_rejections_title">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="retry_rejections_title">Retry Rejections</h4>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button id="modal-save-retry-rejections" type="button" class="btn btn-primary">Submit</button>
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

	<div class="modal fade" id="modal-export" tabindex="-1" role="dialog" aria-labelledby="modal-export_title">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="modal-export_title">Export legacy data</h4>
				</div>
				<div class="modal-body"></div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					<button id="modal-save-export" type="button" class="btn btn-primary">Export Data</button>
				</div>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		$('#modal-save-newfeed').click(function (event) {
			event.preventDefault();

			var response = $.ajax({
				url: "mgr_feedout.php",
				type: "POST",
				async: true,
				data: $("#new_feed").serialize()
			}).done(function (result) {
				if (result.status == 1) {
					window.location.reload(true);
				} else {
					alert(result.error);
				}
			});
		});

		$('#newfeed').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-editfeed').click(function (event) {
			event.preventDefault();

			var response = $.ajax({
				url: "mgr_feedout.php",
				type: "POST",
				async: true,
				data: $("#edit_feed").serialize()
			}).done(function (result) {
				if (result.status == 1) {
					window.location.reload(true);
				} else {
					alert(result.error);
				}
			});
		});

		$('#editfeed').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-showpop').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-clearqueue').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-testrecord').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-testrecord').click(function (event) {
			event.preventDefault();

			var data = $("#testrecord").serialize();
			$('#modal-testrecord').find('.modal-body').html('Processing ...');

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: data,
				success: function (result) {
					$('#modal-testrecord').find('.modal-body').html(result);
					$('#modal-testrecord').animate({scrollTop: $('#scroll-to').offset().top}, 'fast');
				}
			});
		});


		$('#modal-urlreport').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-urlreport').click(function (event) {
			event.preventDefault();

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: $("#form-urlreport").serialize(),
				success: function (data) {
					$('#modal-urlreport').find('.modal-body').html(data);
				}
			});
		});

		$('#modal-import').on('show.bs.modal', function (e) {
			var modal = $(this);
			var idFeedOut = $(e.relatedTarget).data('feed-id');

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: {
					'd': 'dialog_import',
					'idFeedOut': idFeedOut
				},
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-import').click(function (event) {
			event.preventDefault();

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: $("#form-import").serialize(),
				success: function (result) {
					if (result.status == 1) {
						window.location.reload(true);
					} else {
						alert(result.error);
					}
				}
			});
		});

		$('#modal-upload').on('show.bs.modal', function (e) {
			var modal = $(this);
			var idFeedOut = $(e.relatedTarget).data('feed-id');

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: {
					'd': 'dialog_upload',
					'idFeedOut': idFeedOut
				},
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-upload').click(function (event) {
			event.preventDefault();
			$('#form-upload').submit();
		});

		$('#modal-export').on('show.bs.modal', function (e) {
			var modal = $(this);
			var idFeedOut = $(e.relatedTarget).data('feed-id');

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: {
					'd': 'dialog_export',
					'idFeedOut': idFeedOut
				},
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-export').click(function (event) {
			event.preventDefault();

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: $("#form-export").serialize(),
				success: function (result) {
					if (result.status == 1) {
						window.location.reload(true);
					} else {
						alert(result.error);
					}
				}
			});
		});

		$('#modal-retry-rejections').on('show.bs.modal', function (e) {
			var modal = $(this);
			var idFeedOut = $(e.relatedTarget).data('feed-id');

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: {
					'd': 'dialog_retry_rejections',
					'idFeedOut': idFeedOut
				},
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-retry-rejections').click(function (event) {
			event.preventDefault();

			$.ajax({
				cache: false,
				type: 'POST',
				url: 'mgr_feedout.php',
				data: $("#form-import").serialize(),
				success: function (result) {
					if (result.status == 1) {
						window.location.reload(true);
					} else {
						alert(result.error);
					}
				}
			});
		});

		$('#modal-save-newpop').click(function (event) {
			event.preventDefault();

			var response = $.ajax({
				url: "mgr_feedout.php",
				type: "POST",
				async: true,
				data: $("#new_pop").serialize()
			}).done(function (result) {
				if (result.status == 1) {
					window.location.reload(true);
				} else {
					alert(result.error);
				}
			});
		});

		$('#modal-newpop').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#modal-save-editpop').click(function (event) {
			event.preventDefault();

			var response = $.ajax({
				url: "mgr_feedout.php",
				type: "POST",
				async: true,
				data: $("#edit_pop").serialize()
			}).done(function (result) {
				if (result.status == 1) {
					window.location.reload(true);
				} else {
					alert(result.error);
				}
			});
		});

		$('#modal-editpop').on('show.bs.modal', function (e) {
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
				success: function (data) {
					modal.find('.modal-body').html(data);
				}
			});
		});

		$('#status-select select').change(function (e) {
			e.preventDefault();
			$('#status-select').submit();
		});

		$('.feed-toggle').on('show.bs.collapse', function () {
			$(this).prev().find('button[data-toggle="collapse"]').html('Hide Feeds');
		});
		$('.feed-toggle').on('hide.bs.collapse', function () {
			$(this).prev().find('button[data-toggle="collapse"]').html('Show Feeds');
		});

		$('.cron-toggle').change(function () {
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

		function splitMultiFilter(e, t) {
			values = $('#' + e + 'popset_filter' + t + 'Multi').val();
			//alert(values);
			valueArray = values.match(/[^\r\n]+/g);
			for (count = 0; count < valueArray.length; count++) {
				element(
					e + "popset_filter" + t + "_container"
					, "element_filter"
					, {
						"e": e
						, "type": t
						, "value": valueArray[count]
					}
				);
			}
			//alert('#'+e+'popset_filter'+t+'_multipleInsert');
			$('#' + e + 'popset_filter' + t + '_multipleInsert').html("");
		}
	</script>

</body>
</html>
