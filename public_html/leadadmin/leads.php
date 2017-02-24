<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

// Remove leading and trailing spaces from all values
$_REQUEST = array_map( 'trim', $_REQUEST );

require_once( INCLUDES . 'display.php' );

$ledgerMonths = array();
$date = new DateTime();
while( $date->format( 'Ym' ) >= '201701' ) {
	$small = $date->format( 'Ym' );
	$ledgerMonths[$small] = $date->format( 'M Y' );
	$date->sub( new DateInterval( 'P1M' ) );
}

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addPhoneLedger":
			$result['error'] = 'Failed when trying to add a new ledger entry.';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['clientCompanyId'] ) ) {
				$result['error'] = 'Please select a client from the list.';
				break;
			}

            if( $c && empty( $_REQUEST['verticalId'] ) ) {
                $result['error'] = 'Please select a vertical from the list.';
                $c = false;
            }

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && is_numeric( $_REQUEST['invoiceAmount'] ) === FALSE ) {
				$result['error'] = 'Invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && floatval( $_REQUEST['invoiceAmount'] ) < 0 ) {
				$result['error'] = 'Invoice amount cannot be less than zero.';
				break;
			}

			if( empty( $_REQUEST['ledgerMonth'] ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				break;
			}

			if( !empty( $_REQUEST['paymentDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['paymentDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === FALSE ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount1'] ) && is_numeric( $_REQUEST['loInvoiceAmount1'] ) === FALSE ) {
				$result['error'] = 'LO #1 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount1'] ) && floatval( $_REQUEST['loInvoiceAmount1'] ) < 0 ) {
				$result['error'] = 'LO #1 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount1'] ) && is_numeric( $_REQUEST['loPaymentAmount1'] ) === FALSE ) {
				$result['error'] = 'LO #1 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount1'] ) && floatval( $_REQUEST['loPaymentAmount1'] ) < 0 ) {
				$result['error'] = 'LO #1 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate1'] ) ) {
				try {
					$loPaymentDate1 = new DateTime( $_REQUEST['loPaymentDate1'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #1 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount2'] ) && is_numeric( $_REQUEST['loInvoiceAmount2'] ) === FALSE ) {
				$result['error'] = 'LO #2 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount2'] ) && floatval( $_REQUEST['loInvoiceAmount2'] ) < 0 ) {
				$result['error'] = 'LO #2 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount2'] ) && is_numeric( $_REQUEST['loPaymentAmount2'] ) === FALSE ) {
				$result['error'] = 'LO #2 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount2'] ) && floatval( $_REQUEST['loPaymentAmount2'] ) < 0 ) {
				$result['error'] = 'LO #2 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate2'] ) ) {
				try {
					$loPaymentDate2 = new DateTime( $_REQUEST['loPaymentDate2'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #2 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount3'] ) && is_numeric( $_REQUEST['loInvoiceAmount3'] ) === FALSE ) {
				$result['error'] = 'LO #3 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount3'] ) && floatval( $_REQUEST['loInvoiceAmount3'] ) < 0 ) {
				$result['error'] = 'LO #3 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount3'] ) && is_numeric( $_REQUEST['loPaymentAmount3'] ) === FALSE ) {
				$result['error'] = 'LO #3 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount3'] ) && floatval( $_REQUEST['loPaymentAmount3'] ) < 0 ) {
				$result['error'] = 'LO #3 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate3'] ) ) {
				try {
					$loPaymentDate3 = new DateTime( $_REQUEST['loPaymentDate3'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #3 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount4'] ) && is_numeric( $_REQUEST['loInvoiceAmount4'] ) === FALSE ) {
				$result['error'] = 'LO #4 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount4'] ) && floatval( $_REQUEST['loInvoiceAmount4'] ) < 0 ) {
				$result['error'] = 'LO #4 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount4'] ) && is_numeric( $_REQUEST['loPaymentAmount4'] ) === FALSE ) {
				$result['error'] = 'LO #4 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount4'] ) && floatval( $_REQUEST['loPaymentAmount4'] ) < 0 ) {
				$result['error'] = 'LO #4 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate4'] ) ) {
				try {
					$loPaymentDate4 = new DateTime( $_REQUEST['loPaymentDate4'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #4 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount5'] ) && is_numeric( $_REQUEST['loInvoiceAmount5'] ) === FALSE ) {
				$result['error'] = 'LO #5 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount5'] ) && floatval( $_REQUEST['loInvoiceAmount5'] ) < 0 ) {
				$result['error'] = 'LO #5 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount5'] ) && is_numeric( $_REQUEST['loPaymentAmount5'] ) === FALSE ) {
				$result['error'] = 'LO #5 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount5'] ) && floatval( $_REQUEST['loPaymentAmount5'] ) < 0 ) {
				$result['error'] = 'LO #5 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate5'] ) ) {
				try {
					$loPaymentDate5 = new DateTime( $_REQUEST['loPaymentDate5'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #5 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionDate'] ) ) {
				try {
					$commissionDate = new DateTime( $_REQUEST['commissionDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount'] ) && is_numeric( $_REQUEST['commissionAmount'] ) === FALSE ) {
				$result['error'] = 'Commission amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount'] ) && floatval( $_REQUEST['commissionAmount'] ) < 0 ) {
				$result['error'] = 'Commission amount cannot be less than zero.';
				break;
			}

			$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

			$ledgerId = $leads->addPhoneLedger( array(
				'clientCompanyId' => empty( $_REQUEST['clientCompanyId'] ) ? null : $_REQUEST['clientCompanyId'],
				'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
				'listName' => empty( $_REQUEST['listName'] ) ? null : $_REQUEST['listName'],
				'orderDate' => !isset( $orderDate ) ? null : $orderDate->format( 'Y-m-d' ),
				'qty' => empty( $_REQUEST['qty'] ) ? null : $_REQUEST['qty'],
				'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
				'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? null : $_REQUEST['invoiceAmount'],
				'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
				'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
				'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
				'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
				'commissionDate' => !isset( $commissionDate ) ? null : $commissionDate->format( 'Y-m-d' ),
				'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
				'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to add entry to the database';
				break;
			}

			$leads->addPhoneLedgerVendor( array(
				'ledgerId' => $ledgerId,
				'indexId' => 1,
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId1'] ) ? null : $_REQUEST['vendorCompanyId1'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum1'] ) ? null : $_REQUEST['loInvoiceNum1'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount1'] ) ? null : $_REQUEST['loInvoiceAmount1'],
				'loPaymentDate' => !isset( $loPaymentDate1 ) ? null : $loPaymentDate1->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod1'] ) ? null : $_REQUEST['loPaymentMethod1'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount1'] ) ? null : $_REQUEST['loPaymentAmount1'],
			) );

			$leads->addPhoneLedgerVendor( array(
				'ledgerId' => $ledgerId,
				'indexId' => 2,
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId2'] ) ? null : $_REQUEST['vendorCompanyId2'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum2'] ) ? null : $_REQUEST['loInvoiceNum2'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount2'] ) ? null : $_REQUEST['loInvoiceAmount2'],
				'loPaymentDate' => !isset( $loPaymentDate2 ) ? null : $loPaymentDate2->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod2'] ) ? null : $_REQUEST['loPaymentMethod2'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount2'] ) ? null : $_REQUEST['loPaymentAmount2'],
			) );

			$leads->addPhoneLedgerVendor( array(
				'ledgerId' => $ledgerId,
				'indexId' => 3,
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId3'] ) ? null : $_REQUEST['vendorCompanyId3'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum3'] ) ? null : $_REQUEST['loInvoiceNum3'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount3'] ) ? null : $_REQUEST['loInvoiceAmount3'],
				'loPaymentDate' => !isset( $loPaymentDate3 ) ? null : $loPaymentDate3->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod3'] ) ? null : $_REQUEST['loPaymentMethod3'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount3'] ) ? null : $_REQUEST['loPaymentAmount3'],
			) );

			$leads->addPhoneLedgerVendor( array(
				'ledgerId' => $ledgerId,
				'indexId' => 4,
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId4'] ) ? null : $_REQUEST['vendorCompanyId4'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum4'] ) ? null : $_REQUEST['loInvoiceNum4'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount4'] ) ? null : $_REQUEST['loInvoiceAmount4'],
				'loPaymentDate' => !isset( $loPaymentDate4 ) ? null : $loPaymentDate4->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod4'] ) ? null : $_REQUEST['loPaymentMethod4'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount4'] ) ? null : $_REQUEST['loPaymentAmount4'],
			) );

			$leads->addPhoneLedgerVendor( array(
				'ledgerId' => $ledgerId,
				'indexId' => 5,
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId5'] ) ? null : $_REQUEST['vendorCompanyId5'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum5'] ) ? null : $_REQUEST['loInvoiceNum5'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount5'] ) ? null : $_REQUEST['loInvoiceAmount5'],
				'loPaymentDate' => !isset( $loPaymentDate5 ) ? null : $loPaymentDate5->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod5'] ) ? null : $_REQUEST['loPaymentMethod5'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount5'] ) ? null : $_REQUEST['loPaymentAmount5'],
			) );

			$leads->auditLog( 'LEDGER-PHONE:ADD', $ledgerId );
			$result['status'] = 1;
			$result['error'] = 'Successfully added a new ledger entry.';
		break;

		case "deletePhoneLedger":
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['ledgerId'] ) ) {
				$result['error'] = 'Ledger ID is empty. Cannot delete!';
				break;
			}

			$entry = $leads->getPhoneLedgerById( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There is no ledger entry that exists by that ID.';
				break;
			}

			$status = $leads->deletePhoneLedger( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There was an error deleting this ledger entry.';
				break;
			}

			$leads->auditLog( 'LEDGER-PHONE:DELETE', $_REQUEST['ledgerId'] );
			$result['status'] = 1;
			$result['error'] = 'Leads ledger deleted successfully.';

		break;

		case "editPhoneLedger":
			$result['error'] = 'Failed when trying to edit a ledger entry.';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['ledgerId'] ) ) {
				$result['error'] = 'Ledger ID is empty. Cannot edit!';
				break;
			}

			if( empty( $_REQUEST['clientCompanyId'] ) ) {
				$result['error'] = 'Please select a client from the list.';
				break;
			}

            if( $c && empty( $_REQUEST['verticalId'] ) ) {
                $result['error'] = 'Please select a vertical from the list.';
                $c = false;
            }

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && is_numeric( $_REQUEST['invoiceAmount'] ) === FALSE ) {
				$result['error'] = 'Invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && floatval( $_REQUEST['invoiceAmount'] ) < 0 ) {
				$result['error'] = 'Invoice amount cannot be less than zero.';
				break;
			}

			if( empty( $_REQUEST['ledgerMonth'] ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				break;
			}

			if( !empty( $_REQUEST['paymentDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['paymentDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === FALSE ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount1'] ) && is_numeric( $_REQUEST['loInvoiceAmount1'] ) === FALSE ) {
				$result['error'] = 'LO #1 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount1'] ) && floatval( $_REQUEST['loInvoiceAmount1'] ) < 0 ) {
				$result['error'] = 'LO #1 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount1'] ) && is_numeric( $_REQUEST['loPaymentAmount1'] ) === FALSE ) {
				$result['error'] = 'LO #1 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount1'] ) && floatval( $_REQUEST['loPaymentAmount1'] ) < 0 ) {
				$result['error'] = 'LO #1 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate1'] ) ) {
				try {
					$loPaymentDate1 = new DateTime( $_REQUEST['loPaymentDate1'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #1 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount2'] ) && is_numeric( $_REQUEST['loInvoiceAmount2'] ) === FALSE ) {
				$result['error'] = 'LO #2 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount2'] ) && floatval( $_REQUEST['loInvoiceAmount2'] ) < 0 ) {
				$result['error'] = 'LO #2 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount2'] ) && is_numeric( $_REQUEST['loPaymentAmount2'] ) === FALSE ) {
				$result['error'] = 'LO #2 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount2'] ) && floatval( $_REQUEST['loPaymentAmount2'] ) < 0 ) {
				$result['error'] = 'LO #2 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate2'] ) ) {
				try {
					$loPaymentDate2 = new DateTime( $_REQUEST['loPaymentDate2'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #2 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount3'] ) && is_numeric( $_REQUEST['loInvoiceAmount3'] ) === FALSE ) {
				$result['error'] = 'LO #3 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount3'] ) && floatval( $_REQUEST['loInvoiceAmount3'] ) < 0 ) {
				$result['error'] = 'LO #3 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount3'] ) && is_numeric( $_REQUEST['loPaymentAmount3'] ) === FALSE ) {
				$result['error'] = 'LO #3 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount3'] ) && floatval( $_REQUEST['loPaymentAmount3'] ) < 0 ) {
				$result['error'] = 'LO #3 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate3'] ) ) {
				try {
					$loPaymentDate3 = new DateTime( $_REQUEST['loPaymentDate3'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #3 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount4'] ) && is_numeric( $_REQUEST['loInvoiceAmount4'] ) === FALSE ) {
				$result['error'] = 'LO #4 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount4'] ) && floatval( $_REQUEST['loInvoiceAmount4'] ) < 0 ) {
				$result['error'] = 'LO #4 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount4'] ) && is_numeric( $_REQUEST['loPaymentAmount4'] ) === FALSE ) {
				$result['error'] = 'LO #4 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount4'] ) && floatval( $_REQUEST['loPaymentAmount4'] ) < 0 ) {
				$result['error'] = 'LO #4 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate4'] ) ) {
				try {
					$loPaymentDate4 = new DateTime( $_REQUEST['loPaymentDate4'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #4 payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['loInvoiceAmount5'] ) && is_numeric( $_REQUEST['loInvoiceAmount5'] ) === FALSE ) {
				$result['error'] = 'LO #5 invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount5'] ) && floatval( $_REQUEST['loInvoiceAmount5'] ) < 0 ) {
				$result['error'] = 'LO #5 invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount5'] ) && is_numeric( $_REQUEST['loPaymentAmount5'] ) === FALSE ) {
				$result['error'] = 'LO #5 payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount5'] ) && floatval( $_REQUEST['loPaymentAmount5'] ) < 0 ) {
				$result['error'] = 'LO #5 payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate5'] ) ) {
				try {
					$loPaymentDate5 = new DateTime( $_REQUEST['loPaymentDate5'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid LO #5 payment date.';
					break;
				}
			}


			if( !empty( $_REQUEST['commissionDate'] ) ) {
				try {
					$commissionDate = new DateTime( $_REQUEST['commissionDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount'] ) && is_numeric( $_REQUEST['commissionAmount'] ) === FALSE ) {
				$result['error'] = 'Commission amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount'] ) && floatval( $_REQUEST['commissionAmount'] ) < 0 ) {
				$result['error'] = 'Commission amount cannot be less than zero.';
				break;
			}

			$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

			$ledgerId = $leads->updatePhoneLedger( $_REQUEST['ledgerId'], array(
				'clientCompanyId' => empty( $_REQUEST['clientCompanyId'] ) ? null : $_REQUEST['clientCompanyId'],
				'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
				'listName' => empty( $_REQUEST['listName'] ) ? null : $_REQUEST['listName'],
				'orderDate' => !isset( $orderDate ) ? null : $orderDate->format( 'Y-m-d' ),
				'qty' => empty( $_REQUEST['qty'] ) ? null : $_REQUEST['qty'],
				'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
				'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? null : $_REQUEST['invoiceAmount'],
				'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
				'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
				'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
				'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
				'commissionDate' => !isset( $commissionDate ) ? null : $commissionDate->format( 'Y-m-d' ),
				'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
				'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to update ledger entry.';
				break;
			}

			$leads->updatePhoneLedgerVendor( $_REQUEST['ledgerId'], 1, array(
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId1'] ) ? null : $_REQUEST['vendorCompanyId1'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum1'] ) ? null : $_REQUEST['loInvoiceNum1'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount1'] ) ? null : $_REQUEST['loInvoiceAmount1'],
				'loPaymentDate' => !isset( $loPaymentDate1 ) ? null : $loPaymentDate1->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod1'] ) ? null : $_REQUEST['loPaymentMethod1'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount1'] ) ? null : $_REQUEST['loPaymentAmount1'],
			) );

			$leads->updatePhoneLedgerVendor( $_REQUEST['ledgerId'], 2, array(
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId2'] ) ? null : $_REQUEST['vendorCompanyId2'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum2'] ) ? null : $_REQUEST['loInvoiceNum2'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount2'] ) ? null : $_REQUEST['loInvoiceAmount2'],
				'loPaymentDate' => !isset( $loPaymentDate2 ) ? null : $loPaymentDate2->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod2'] ) ? null : $_REQUEST['loPaymentMethod2'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount2'] ) ? null : $_REQUEST['loPaymentAmount2'],
			) );

			$leads->updatePhoneLedgerVendor( $_REQUEST['ledgerId'], 3, array(
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId3'] ) ? null : $_REQUEST['vendorCompanyId3'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum3'] ) ? null : $_REQUEST['loInvoiceNum3'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount3'] ) ? null : $_REQUEST['loInvoiceAmount3'],
				'loPaymentDate' => !isset( $loPaymentDate3 ) ? null : $loPaymentDate3->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod3'] ) ? null : $_REQUEST['loPaymentMethod3'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount3'] ) ? null : $_REQUEST['loPaymentAmount3'],
			) );

			$leads->updatePhoneLedgerVendor( $_REQUEST['ledgerId'], 4, array(
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId4'] ) ? null : $_REQUEST['vendorCompanyId4'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum4'] ) ? null : $_REQUEST['loInvoiceNum4'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount4'] ) ? null : $_REQUEST['loInvoiceAmount4'],
				'loPaymentDate' => !isset( $loPaymentDate4 ) ? null : $loPaymentDate4->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod4'] ) ? null : $_REQUEST['loPaymentMethod4'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount4'] ) ? null : $_REQUEST['loPaymentAmount4'],
			) );

			$leads->updatePhoneLedgerVendor( $_REQUEST['ledgerId'], 5, array(
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId5'] ) ? null : $_REQUEST['vendorCompanyId5'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum5'] ) ? null : $_REQUEST['loInvoiceNum5'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount5'] ) ? null : $_REQUEST['loInvoiceAmount5'],
				'loPaymentDate' => !isset( $loPaymentDate5 ) ? null : $loPaymentDate5->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod5'] ) ? null : $_REQUEST['loPaymentMethod5'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount5'] ) ? null : $_REQUEST['loPaymentAmount5'],
			) );

			$leads->auditLog( 'LEDGER-PHONE:EDIT', $_REQUEST['ledgerId'] );
			$result['status'] = 1;
			$result['error'] = 'Successfully edited ledger entry.';
		break;

		case "getDivisionCompanies":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionCompanies( $_REQUEST['divisionId'], null, PDO::FETCH_ASSOC ) );
			} else {
				echo json_encode( array( ) );
			}
			exit;
		break;

	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){
	switch($_REQUEST['d']){
		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

		case "newPhoneLedger":

			$fields = array(
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => '_text',
					'value' => 'Leads',
				),
				array(
					'id' => 'clientCompanyId',
					'label' => 'Client',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a client',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'verticalId',
					'label' => 'Vertical',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vertical',
					'choices' => $leads->getDivisionVerticals( 5 ),
				),
				array(
					'id' => 'listName',
					'label' => 'List Name',
					'type' => 'text',
				),
				array(
					'id' => 'orderDate',
					'label' => 'Order Date',
					'type' => 'text',
				),
				array(
					'id' => 'qty',
					'label' => 'Quantity',
					'type' => 'number',
				),
				array(
					'id' => 'invoiceNum',
					'label' => 'Invoice Number',
					'type' => 'text',
				),
				array(
					'id' => 'invoiceAmount',
					'label' => 'Invoice Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'ledgerMonth',
					'label' => 'Ledger Month',
					'type' => 'select',
					'choices' => $ledgerMonths,
				),
				array(
					'id' => 'paymentDate',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'paymentMethod',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'paymentAmount',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'vendorCompanyId1',
					'label' => 'Vendor #1',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'loInvoiceNum1',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount1',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate1',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentMethod1',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount1',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'vendorCompanyId2',
					'label' => 'Vendor #2',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'loInvoiceNum2',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount2',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate2',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentMethod2',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount2',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'vendorCompanyId3',
					'label' => 'Vendor #3',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'loInvoiceNum3',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount3',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate3',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentMethod3',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount3',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'vendorCompanyId4',
					'label' => 'Vendor #4',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'loInvoiceNum4',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount4',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate4',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentMethod4',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount4',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'vendorCompanyId5',
					'label' => 'Vendor #5',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				),
				array(
					'id' => 'loInvoiceNum5',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount5',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate5',
					'label' => 'Date Paid',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentMethod5',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount5',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'userId',
					'label' => 'Salesperson',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'commissionDate',
					'label' => 'Commission Date',
					'type' => 'text',
				),
				array(
					'id' => 'commissionAmount',
					'label' => 'Commission Amt',
					'type' => 'currency',
				),

				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addPhoneLedger',
				),
			);

			Display::displayForm( 'new_phoneledger', $fields );

?>

<script type="text/javascript">
$("#new_phoneledger input[name=orderDate], #new_phoneledger input[name=paymentDate], #new_phoneledger input[name=loPaymentDate1], #new_phoneledger input[name=loPaymentDate2], #new_phoneledger input[name=loPaymentDate3], #new_phoneledger input[name=loPaymentDate4], #new_phoneledger input[name=loPaymentDate5], #new_phoneledger input[name=commissionDate]").datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$("#new_phoneledger select[name='clientCompanyId']").select2({
	placeholder: "Select a client",
	allowClear: true
});

$("#new_phoneledger select[name='verticalId']").select2({
	placeholder: "Select a vertical",
	allowClear: true
});

$("#new_phoneledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#new_phoneledger select[name='vendorCompanyId1'], #new_phoneledger select[name='vendorCompanyId2'], #new_phoneledger select[name='vendorCompanyId3'], #new_phoneledger select[name='vendorCompanyId4'], #new_phoneledger select[name='vendorCompanyId5']").select2({
	placeholder: "Select a vendor",
	allowClear: true
});

$("#new_phoneledger select[name='userId']").select2({
	placeholder: "Select a salesperson",
	allowClear: true
});
</script>

<?php

		break;

		case "deletePhoneLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getPhoneLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				print '<p>Are you sure you wish to <strong>delete</strong> this entry?</p>';

				$ledgerMonth = new DateTime( $entry->ledgerMonth );

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => '_text',
						'value' => 'Leads',
					),
					array(
						'id' => 'clientCompanyId',
						'label' => 'Client',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a client',
						'choices' => $leads->getDivisionCompanies( 5, $entry->clientCompanyId ),
						'value' => $entry->clientCompanyId,
						'readonly' => true,
					),
					array(
						'id' => 'verticalId',
						'label' => 'Vertical',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vertical',
						'choices' => $leads->getDivisionVerticals( 5 ),
						'value' => $entry->verticalId,
					),
					array(
						'id' => 'listName',
						'label' => 'List Name',
						'type' => 'text',
						'value' => $entry->listName,
						'readonly' => true,
					),
					array(
						'id' => 'orderDate',
						'label' => 'Order Date',
						'type' => 'text',
						'value' => $entry->orderDate,
						'readonly' => true,
					),
					array(
						'id' => 'qty',
						'label' => 'Quantity',
						'type' => 'number',
						'value' => $entry->qty,
						'readonly' => true,
					),
					array(
						'id' => 'invoiceNum',
						'label' => 'Invoice Number',
						'type' => 'text',
						'value' => $entry->invoiceNum,
						'readonly' => true,
					),
					array(
						'id' => 'invoiceAmount',
						'label' => 'Invoice Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->invoiceAmount,
						'readonly' => true,
					),
					array(
						'id' => 'ledgerMonth',
						'label' => 'Ledger Month',
						'type' => 'select',
						'choices' => $ledgerMonths,
						'value' => $ledgerMonth->format( 'Ym' ),
						'readonly' => true,
					),
					array(
						'id' => 'paymentDate',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->paymentDate,
						'readonly' => true,
					),
					array(
						'id' => 'paymentMethod',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->paymentMethod,
						'readonly' => true,
					),
					array(
						'id' => 'paymentAmount',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->paymentAmount,
						'readonly' => true,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId',
						'label' => 'Salesperson',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId,
						'readonly' => true,
					),
					array(
						'id' => 'commissionDate',
						'label' => 'Commission Date',
						'type' => 'text',
						'value' => $entry->commissionDate,
						'readonly' => true,
					),
					array(
						'id' => 'commissionAmount',
						'label' => 'Commission Amt',
						'type' => 'currency',
						'value' => $entry->commissionAmount,
						'readonly' => true,
					),

					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'deletePhoneLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);

				Display::displayForm( 'delete_phoneledger', $fields );

			}

		break;

		case "editPhoneLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getPhoneLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				$ledgerMonth = new DateTime( $entry->ledgerMonth );

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => '_text',
						'value' => 'Leads',
					),
					array(
						'id' => 'clientCompanyId',
						'label' => 'Client',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a client',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->clientCompanyId,
					),
					array(
						'id' => 'listName',
						'label' => 'List Name',
						'type' => 'text',
						'value' => $entry->listName,
					),
					array(
						'id' => 'orderDate',
						'label' => 'Order Date',
						'type' => 'text',
						'value' => $entry->orderDate,
					),
					array(
						'id' => 'qty',
						'label' => 'Quantity',
						'type' => 'number',
						'value' => $entry->qty,
					),
					array(
						'id' => 'invoiceNum',
						'label' => 'Invoice Number',
						'type' => 'text',
						'value' => $entry->invoiceNum,
					),
					array(
						'id' => 'invoiceAmount',
						'label' => 'Invoice Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->invoiceAmount,
					),
					array(
						'id' => 'ledgerMonth',
						'label' => 'Ledger Month',
						'type' => 'select',
						'choices' => $ledgerMonths,
						'value' => $ledgerMonth->format( 'Ym' ),
					),
					array(
						'id' => 'paymentDate',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->paymentDate,
					),
					array(
						'id' => 'paymentMethod',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->paymentMethod,
					),
					array(
						'id' => 'paymentAmount',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->paymentAmount,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'vendorCompanyId1',
						'label' => 'Vendor #1',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->vendorCompanyId1,
					),
					array(
						'id' => 'loInvoiceNum1',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum1,
					),
					array(
						'id' => 'loInvoiceAmount1',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount1,
					),
					array(
						'id' => 'loPaymentDate1',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate1,
					),
					array(
						'id' => 'loPaymentMethod1',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod1,
					),
					array(
						'id' => 'loPaymentAmount1',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount1,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'vendorCompanyId2',
						'label' => 'Vendor #2',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->vendorCompanyId2,
					),
					array(
						'id' => 'loInvoiceNum2',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum2,
					),
					array(
						'id' => 'loInvoiceAmount2',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount2,
					),
					array(
						'id' => 'loPaymentDate2',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate2,
					),
					array(
						'id' => 'loPaymentMethod2',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod2,
					),
					array(
						'id' => 'loPaymentAmount2',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount2,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'vendorCompanyId3',
						'label' => 'Vendor #3',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->vendorCompanyId3,
					),
					array(
						'id' => 'loInvoiceNum3',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum3,
					),
					array(
						'id' => 'loInvoiceAmount3',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount3,
					),
					array(
						'id' => 'loPaymentDate3',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate3,
					),
					array(
						'id' => 'loPaymentMethod3',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod3,
					),
					array(
						'id' => 'loPaymentAmount3',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount3,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'vendorCompanyId4',
						'label' => 'Vendor #4',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->vendorCompanyId4,
					),
					array(
						'id' => 'loInvoiceNum4',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum4,
					),
					array(
						'id' => 'loInvoiceAmount4',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount4,
					),
					array(
						'id' => 'loPaymentDate4',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate4,
					),
					array(
						'id' => 'loPaymentMethod4',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod4,
					),
					array(
						'id' => 'loPaymentAmount4',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount4,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'vendorCompanyId5',
						'label' => 'Vendor #5',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->vendorCompanyId5,
					),
					array(
						'id' => 'loInvoiceNum5',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum5,
					),
					array(
						'id' => 'loInvoiceAmount5',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount5,
					),
					array(
						'id' => 'loPaymentDate5',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate5,
					),
					array(
						'id' => 'loPaymentMethod5',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod5,
					),
					array(
						'id' => 'loPaymentAmount5',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount5,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId',
						'label' => 'Salesperson',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId,
					),
					array(
						'id' => 'commissionDate',
						'label' => 'Commission Date',
						'type' => 'text',
						'value' => $entry->commissionDate,
					),
					array(
						'id' => 'commissionAmount',
						'label' => 'Commission Amt',
						'type' => 'currency',
						'value' => $entry->commissionAmount,
					),

					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'editPhoneLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);


				Display::displayForm( 'edit_phoneledger', $fields );
?>

<script type="text/javascript">
$("#edit_phoneledger input[name=orderDate], #edit_phoneledger input[name=paymentDate], #edit_phoneledger input[name=loPaymentDate1], #edit_phoneledger input[name=loPaymentDate2], #edit_phoneledger input[name=loPaymentDate3], #edit_phoneledger input[name=loPaymentDate4], #edit_phoneledger input[name=loPaymentDate5], #edit_phoneledger input[name=commissionDate]").datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$("#edit_phoneledger select[name='clientCompanyId']").select2({
	placeholder: "Select a client",
	allowClear: true
});

$("#edit_phoneledger select[name='verticalId']").select2({
	placeholder: "Select a vertical",
	allowClear: true
});

$("#edit_phoneledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#edit_phoneledger select[name='vendorCompanyId1'], #edit_phoneledger select[name='vendorCompanyId2'], #edit_phoneledger select[name='vendorCompanyId3'], #edit_phoneledger select[name='vendorCompanyId4'], #edit_phoneledger select[name='vendorCompanyId5']").select2({
	placeholder: "Select a vendor",
	allowClear: true
});

$("#edit_phoneledger select[name='userId']").select2({
	placeholder: "Select a salesperson",
	allowClear: true
});
</script>

<?php
			}
		break;
	}
	exit;
}

$title = 'Leads Ledger Entries';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Leads Ledger</h2>

<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#genericledger">Add a new entry</button></p>
<?php } ?>

<?php
	$monthIn = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : null;
	$monthSelected = null;
	$months = $leads->getPhoneLedger( true );

	if( empty( $months ) ) {

		print '<p>No ledger entries exist in the database.</p>' . PHP_EOL;

	} else {

		print '<form class="form-inline" method="get">' . PHP_EOL;
		print '<div class="form-group">' . PHP_EOL;
		print '<label for="month">Month:</label>' . PHP_EOL;
		print '<select class="form-control" id="month" name="month">' . PHP_EOL;
		$years = array();
		$quarters = array();
		foreach( $months as $month ) {
			$year = substr( $month->month, 0, 4 );
			$quarter = $year . '-Q' . ceil( substr( $month->month, 5, 2 ) / 3 );
			if( empty( $monthIn ) ) {
				$monthIn = $month->month;
			}
			if( $monthIn == $month->month ) {
				$monthSelected = $month->month;
			}
			if( $monthIn == $year ) {
				$monthSelected = $year;
			}
			if( $monthIn == $quarter ) {
				$monthSelected = $quarter;
			}
			if( empty( $years[$year] ) ) {
				printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
					$year,
					$monthIn == $year ? ' selected="selected"' : '',
					htmlentities( $year . ' Year' )
				);
				$years[$year] = true;
			}
			if( empty( $quarters[$quarter] ) ) {
				printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
					$quarter,
					$monthIn == $quarter ? ' selected="selected"' : '',
					htmlentities( str_replace( '-Q', ' Qtr ', $quarter ) )
				);
				$quarters[$quarter] = true;
			}

			printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
				$month->month,
				$monthIn == $month->month ? ' selected="selected"' : '',
				htmlentities( $month->month )
			);
		}
		print '</select>' . PHP_EOL;
		print '</div>' . PHP_EOL;
		print '</form>' . PHP_EOL;

	}

	if( empty( $monthSelected ) ) {

		print '<p>Please select a valid report period above.</p>';

	} else {

		$entries = $leads->getPhoneLedger( false, $monthSelected );

		if( empty( $entries ) ) {

			print '<p>No ledger entries exist in the database.</p>';

		} else {

			$months = array();
			foreach( $entries as $entry ) {
				$month = substr( $entry->ledgerMonth, 0, 7 );
				$months[$month] = true;
			}

			foreach( $months as $month => $val ) {

?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped-double ledger-sort" id="phoneledger_<?php echo $month; ?>">
	<thead>
		<tr class="header">
			<th rowspan="6" style="vertical-align: middle;">Entry #</th>
			<th style="width:250px;">Client Name</th>
			<th style="width:300px;">List Name</th>
			<th>Order Date</th>
			<th>Qty</th>
			<th>QM Inv #</th>
			<th>Inv Amt</th>
			<th>Pmt Date</th>
			<th>Pmt Mthd</th>
			<th>Pmt Amt</th>
			<th>Salesperson</th>
			<th>Commissions</th>
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
			<th rowspan="6" style="vertical-align: middle;">Options</th>
<?php } ?>
		</tr>
		<tr class="header">
			<th colspan="2">Vendor Name</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		</tr>
		<tr class="header">
			<th colspan="2">Vendor Name</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		</tr>
		<tr class="header">
			<th colspan="2">Vendor Name</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		</tr>
		<tr class="header">
			<th colspan="2">Vendor Name</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		</tr>
		<tr class="header">
			<th colspan="2">Vendor Name</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
			<th>&nbsp;</th>
			<th>&nbsp;</th>
		</tr>
	</thead>
<?php
				$paymentTotal = 0;
				foreach( $entries as $entry ) {
					if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
						$paymentTotal += $entry->paymentAmount;

						$ledger = new DateTime( $entry->ledgerMonth );
?>
	<tbody>
		<tr>
			<td rowspan="6" class="text-center" style="vertical-align:middle;"><?php echo htmlentities( $entry->entryId ); ?></td>
			<td><?php echo htmlentities( $entry->clientCompanyName ); ?></td>
			<td><?php echo htmlentities( $entry->listName ); ?></td>
			<td><?php echo htmlentities( $entry->orderDate ); ?></td>
			<td><?php echo number_format( $entry->qty, 0 ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td>$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td>$<?php echo number_format( $entry->commissionAmount, 2 ); ?></td>
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
			<td class="text-center" rowspan="6" style="vertical-align: middle;">
<div class="btn-group">
	<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editphoneledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button>
	<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu">
		<li><a href="#" data-toggle="modal" data-target="#deletephoneledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Delete</a></li>
	</ul>
</div></td>
<?php } ?>
		</tr>
		<tr>
			<td colspan="2"><?php echo htmlentities( $entry->vendorCompanyName1 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><?php echo htmlentities( $entry->loInvoiceNum1 ); ?></td>
			<td>$<?php echo number_format( $entry->loInvoiceAmount1, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate1 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod1 ); ?></td>
			<td>$<?php echo number_format( $entry->loPaymentAmount1, 2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"><?php echo htmlentities( $entry->vendorCompanyName2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><?php echo htmlentities( $entry->loInvoiceNum2 ); ?></td>
			<td>$<?php echo number_format( $entry->loInvoiceAmount2, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod2 ); ?></td>
			<td>$<?php echo number_format( $entry->loPaymentAmount2, 2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"><?php echo htmlentities( $entry->vendorCompanyName3 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><?php echo htmlentities( $entry->loInvoiceNum3 ); ?></td>
			<td>$<?php echo number_format( $entry->loInvoiceAmount3, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate3 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod3 ); ?></td>
			<td>$<?php echo number_format( $entry->loPaymentAmount3, 2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"><?php echo htmlentities( $entry->vendorCompanyName4 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><?php echo htmlentities( $entry->loInvoiceNum4 ); ?></td>
			<td>$<?php echo number_format( $entry->loInvoiceAmount4, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate4 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod4 ); ?></td>
			<td>$<?php echo number_format( $entry->loPaymentAmount4, 2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
		<tr>
			<td colspan="2"><?php echo htmlentities( $entry->vendorCompanyName5 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><?php echo htmlentities( $entry->loInvoiceNum5 ); ?></td>
			<td>$<?php echo number_format( $entry->loInvoiceAmount5, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate5 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod5 ); ?></td>
			<td>$<?php echo number_format( $entry->loPaymentAmount5, 2 ); ?></td>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
		</tr>
	</tbody>
<?php
					}
				}
?>
</table>
<?php
			}
		}
	}
?>

</div>

<script type="text/javascript">
$('.form-inline select').change(function() {
	$('.form-inline').submit();
});
/*
$('.ledger-sort').each(function() { console.log($(this).attr('id'));
	var tf = new TableFilter($(this).attr('id'), {
		base_path: '/leadadmin/libraries/tablefilter/',
		grid: false,
		filters_row_index: 1,
		extensions: [{
			name: 'sort',
			types: [
				'String','String','String','String','String','ymddate','String','us','String','String','us',
			],
			image_asc_class_name: 'custom-ascending',
			image_desc_class_name: 'custom-descending'
		}],
		sort: true
	});
	tf.init();
});
*/
</script>

<?php require_once( INCLUDES . 'modals.php' ); ?>

</body>
</html>
