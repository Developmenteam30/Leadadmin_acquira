<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$type = !empty( $_REQUEST['type'] ) ? 1 : 0;

// Remove leading and trailing spaces from all values
$_REQUEST = array_map( 'trim', $_REQUEST );

require_once( INCLUDES . 'display.php' );

$ledgerMonths = array();
$date = new DateTime();
while( $date->format( 'Ym' ) >= '201601' ) {
	$small = $date->format( 'Ym' );
	$ledgerMonths[$small] = $date->format( 'M Y' );
	$date->sub( new DateInterval( 'P1M' ) );
}

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
		case "addOfflineLedger":
			$result['error'] = 'Failed when trying to add a new ledger entry.';

			if( !LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['clientCompanyId'] ) ) {
				$result['error'] = 'Please select a client from the list.';
				break;
			}

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['mailDate'] ) ) {
				try {
					$mailDate = new DateTime( $_REQUEST['mailDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
					break;
				}
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && is_numeric( $_REQUEST['invoiceAmount'] ) === false ) {
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
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === false ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && is_numeric( $_REQUEST['loInvoiceAmount'] ) === false ) {
				$result['error'] = 'LO invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && floatval( $_REQUEST['loInvoiceAmount'] ) < 0 ) {
				$result['error'] = 'LO invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && is_numeric( $_REQUEST['loPaymentAmount'] ) === false ) {
				$result['error'] = 'LO payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && floatval( $_REQUEST['loPaymentAmount'] ) < 0 ) {
				$result['error'] = 'LO payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate'] ) ) {
				try {
					$loPaymentDate = new DateTime( $_REQUEST['loPaymentDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid LO payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionDate1'] ) ) {
				try {
					$commissionDate1 = new DateTime( $_REQUEST['commissionDate1'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 1.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount1'] ) && is_numeric( $_REQUEST['commissionAmount1'] ) === false ) {
				$result['error'] = 'Commission amount 1 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount1'] ) && floatval( $_REQUEST['commissionAmount1'] ) < 0 ) {
				$result['error'] = 'Commission amount 1 cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['commissionDate2'] ) ) {
				try {
					$commissionDate2 = new DateTime( $_REQUEST['commissionDate2'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 2.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount2'] ) && is_numeric( $_REQUEST['commissionAmount2'] ) === false ) {
				$result['error'] = 'Commission amount 2 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount2'] ) && floatval( $_REQUEST['commissionAmount2'] ) < 0 ) {
				$result['error'] = 'Commission amount 2 cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['commissionDate3'] ) ) {
				try {
					$commissionDate3 = new DateTime( $_REQUEST['commissionDate3'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 3.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount3'] ) && is_numeric( $_REQUEST['commissionAmount3'] ) === false ) {
				$result['error'] = 'Commission amount 3 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount3'] ) && floatval( $_REQUEST['commissionAmount3'] ) < 0 ) {
				$result['error'] = 'Commission amount 3 cannot be less than zero.';
				break;
			}
			
			$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

			$ledgerId = $leads->addOfflineLedger( array(
				'clientCompanyId' => empty( $_REQUEST['clientCompanyId'] ) ? null : $_REQUEST['clientCompanyId'],
				'mailerName' => empty( $_REQUEST['mailerName'] ) ? null : $_REQUEST['mailerName'],
				'listName' => empty( $_REQUEST['listName'] ) ? null : $_REQUEST['listName'],
				'clientPoNum' => empty( $_REQUEST['clientPoNum'] ) ? null : $_REQUEST['clientPoNum'],
				'orderType' => empty( $_REQUEST['orderType'] ) ? null : $_REQUEST['orderType'],
				'orderDate' => !isset( $orderDate ) ? null : $orderDate->format( 'Y-m-d' ),
				'mailDate' => !isset( $mailDate ) ? null : $mailDate->format( 'Y-m-d' ),
				'qty' => empty( $_REQUEST['qty'] ) ? null : $_REQUEST['qty'],
				'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
				'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? null : $_REQUEST['invoiceAmount'],
				'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
				'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
				'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
				'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId'] ) ? null : $_REQUEST['vendorCompanyId'],
				'ourPoNum' => empty( $_REQUEST['ourPoNum'] ) ? null : $_REQUEST['ourPoNum'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum'] ) ? null : $_REQUEST['loInvoiceNum'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount'] ) ? null : $_REQUEST['loInvoiceAmount'],
				'loPaymentDate' => !isset( $loPaymentDate ) ? null : $loPaymentDate->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod'] ) ? null : $_REQUEST['loPaymentMethod'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount'] ) ? null : $_REQUEST['loPaymentAmount'],
				'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
				'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
				'commissionRevenue1' => empty( $_REQUEST['commissionRevenue1'] ) ? null : $_REQUEST['commissionRevenue1'],
				'userId1' => empty( $_REQUEST['userId1'] ) ? null : $_REQUEST['userId1'],
				/* Disabled per #3309.
				'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
				'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
				'commissionRevenue2' => empty( $_REQUEST['commissionRevenue2'] ) ? null : $_REQUEST['commissionRevenue2'],
				'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
				'commissionDate3' => !isset( $commissionDate3 ) ? null : $commissionDate3->format( 'Y-m-d' ),
				'commissionAmount3' => empty( $_REQUEST['commissionAmount3'] ) ? null : $_REQUEST['commissionAmount3'],
				'commissionRevenue3' => empty( $_REQUEST['commissionRevenue3'] ) ? null : $_REQUEST['commissionRevenue3'],
				*/
				'userId3' => empty( $_REQUEST['userId3'] ) ? null : $_REQUEST['userId3'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to add entry to the database';
				break;
			}

			$leads->auditLog( 'LEDGER-OFFLINE:ADD', $ledgerId );
			$result['status'] = 1;
			$result['error'] = 'Successfully added a new ledger entry.';
			break;

		case "deleteOfflineLedger":
			if( !LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['ledgerId'] ) ) {
				$result['error'] = 'Ledger ID is empty. Cannot delete!';
				break;
			}

			$entry = $leads->getOfflineLedgerById( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There is no ledger entry that exists by that ID.';
				break;
			}

			$status = $leads->deleteOfflineLedger( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There was an error deleting this ledger entry.';
				break;
			}

			$leads->auditLog( 'LEDGER-OFFLINE:DELETE', $_REQUEST['ledgerId'] );
			$result['status'] = 1;
			$result['error'] = 'Offline ledger deleted successfully.';

			break;

		case "editOfflineLedger":
			$result['error'] = 'Failed when trying to edit a ledger entry.';

			if( !LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) {
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

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['mailDate'] ) ) {
				try {
					$mailDate = new DateTime( $_REQUEST['mailDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
					break;
				}
			}

			if( !empty( $_REQUEST['invoiceAmount'] ) && is_numeric( $_REQUEST['invoiceAmount'] ) === false ) {
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
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === false ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && is_numeric( $_REQUEST['loInvoiceAmount'] ) === false ) {
				$result['error'] = 'LO invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && floatval( $_REQUEST['loInvoiceAmount'] ) < 0 ) {
				$result['error'] = 'LO invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && is_numeric( $_REQUEST['loPaymentAmount'] ) === false ) {
				$result['error'] = 'LO payment amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && floatval( $_REQUEST['loPaymentAmount'] ) < 0 ) {
				$result['error'] = 'LO payment amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentDate'] ) ) {
				try {
					$loPaymentDate = new DateTime( $_REQUEST['loPaymentDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid LO payment date.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionDate1'] ) ) {
				try {
					$commissionDate1 = new DateTime( $_REQUEST['commissionDate1'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 1.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount1'] ) && is_numeric( $_REQUEST['commissionAmount1'] ) === false ) {
				$result['error'] = 'Commission amount 1 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount1'] ) && floatval( $_REQUEST['commissionAmount1'] ) < 0 ) {
				$result['error'] = 'Commission amount 1 cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['commissionDate2'] ) ) {
				try {
					$commissionDate2 = new DateTime( $_REQUEST['commissionDate2'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 2.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount2'] ) && is_numeric( $_REQUEST['commissionAmount2'] ) === false ) {
				$result['error'] = 'Commission amount 2 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount2'] ) && floatval( $_REQUEST['commissionAmount2'] ) < 0 ) {
				$result['error'] = 'Commission amount 2 cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['commissionDate3'] ) ) {
				try {
					$commissionDate3 = new DateTime( $_REQUEST['commissionDate3'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 3.';
					break;
				}
			}

			if( !empty( $_REQUEST['commissionAmount3'] ) && is_numeric( $_REQUEST['commissionAmount3'] ) === false ) {
				$result['error'] = 'Commission amount 3 must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['commissionAmount3'] ) && floatval( $_REQUEST['commissionAmount3'] ) < 0 ) {
				$result['error'] = 'Commission amount 3 cannot be less than zero.';
				break;
			}

			$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

			$ledgerId = $leads->updateOfflineLedger( $_REQUEST['ledgerId'], array(
				'clientCompanyId' => empty( $_REQUEST['clientCompanyId'] ) ? null : $_REQUEST['clientCompanyId'],
				'mailerName' => empty( $_REQUEST['mailerName'] ) ? null : $_REQUEST['mailerName'],
				'listName' => empty( $_REQUEST['listName'] ) ? null : $_REQUEST['listName'],
				'clientPoNum' => empty( $_REQUEST['clientPoNum'] ) ? null : $_REQUEST['clientPoNum'],
				'orderType' => empty( $_REQUEST['orderType'] ) ? null : $_REQUEST['orderType'],
				'orderDate' => !isset( $orderDate ) ? null : $orderDate->format( 'Y-m-d' ),
				'mailDate' => !isset( $mailDate ) ? null : $mailDate->format( 'Y-m-d' ),
				'qty' => empty( $_REQUEST['qty'] ) ? null : $_REQUEST['qty'],
				'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
				'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? null : $_REQUEST['invoiceAmount'],
				'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
				'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
				'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
				'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
				'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId'] ) ? null : $_REQUEST['vendorCompanyId'],
				'ourPoNum' => empty( $_REQUEST['ourPoNum'] ) ? null : $_REQUEST['ourPoNum'],
				'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum'] ) ? null : $_REQUEST['loInvoiceNum'],
				'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount'] ) ? null : $_REQUEST['loInvoiceAmount'],
				'loPaymentDate' => !isset( $loPaymentDate ) ? null : $loPaymentDate->format( 'Y-m-d' ),
				'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod'] ) ? null : $_REQUEST['loPaymentMethod'],
				'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount'] ) ? null : $_REQUEST['loPaymentAmount'],
				'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
				'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
				'commissionRevenue1' => empty( $_REQUEST['commissionRevenue1'] ) ? null : $_REQUEST['commissionRevenue1'],
				'userId1' => empty( $_REQUEST['userId1'] ) ? null : $_REQUEST['userId1'],
				/* Disabled per #3309.
				'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
				'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
				'commissionRevenue2' => empty( $_REQUEST['commissionRevenue2'] ) ? null : $_REQUEST['commissionRevenue2'],
				'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
				'commissionDate3' => !isset( $commissionDate3 ) ? null : $commissionDate3->format( 'Y-m-d' ),
				'commissionAmount3' => empty( $_REQUEST['commissionAmount3'] ) ? null : $_REQUEST['commissionAmount3'],
				'commissionRevenue3' => empty( $_REQUEST['commissionRevenue3'] ) ? null : $_REQUEST['commissionRevenue3'],
				'userId3' => empty( $_REQUEST['userId3'] ) ? null : $_REQUEST['userId3'],
				*/
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to updated ledger entry.';
				break;
			}

			$leads->auditLog( 'LEDGER-OFFLINE:EDIT', $_REQUEST['ledgerId'] );
			$result['status'] = 1;
			$result['error'] = 'Successfully edited ledger entry.';
			break;

		case "getDivisionCompanies":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionCompanies( $_REQUEST['divisionId'], null, PDO::FETCH_ASSOC ) );
			} else {
				echo json_encode( array() );
			}
			exit;
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

		case "newOfflineLedger":

			$fields = array(
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => '_text',
					'value' => 'Offline',
				),
				array(
					'id' => 'clientCompanyId',
					'label' => 'Client',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a client',
					'choices' => $leads->getDivisionCompanies( 4, null ),
				),
				array(
					'id' => 'mailerName',
					'label' => 'Mailer',
					'type' => 'text',
				),
				array(
					'id' => 'listName',
					'label' => 'List Name',
					'type' => 'text',
				),
				array(
					'id' => 'clientPoNum',
					'label' => 'Client PO #',
					'type' => 'text',
				),
				array(
					'id' => 'orderType',
					'label' => 'Order Type',
					'type' => 'radio',
					'choices' => array(
						't' => 'Test',
						'c' => 'Continuation',
					),
				),
				array(
					'id' => 'orderDate',
					'label' => 'Order Date',
					'type' => 'text',
                    'autocomplete' => 'off',
				),
				array(
					'id' => 'mailDate',
					'label' => 'Mail Date',
					'type' => 'text',
                    'autocomplete' => 'off',
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
                    'autocomplete' => 'off',
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
					'id' => 'vendorCompanyId',
					'label' => 'Vendor',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 4, null ),
				),
				array(
					'id' => 'ourPoNum',
					'label' => 'QM PO #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceNum',
					'label' => 'LO Invoice #',
					'type' => 'text',
				),
				array(
					'id' => 'loInvoiceAmount',
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				),
				array(
					'id' => 'loPaymentDate',
					'label' => 'Date Paid',
					'type' => 'text',
                    'autocomplete' => 'off',
				),
				array(
					'id' => 'loPaymentMethod',
					'label' => 'Payment Method',
					'type' => 'text',
				),
				array(
					'id' => 'loPaymentAmount',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'userId1',
					'label' => 'Salesperson 1',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'commissionDate1',
					'label' => 'Commission Date 1',
					'type' => 'text',
                    'autocomplete' => 'off',
				),
				array(
					'id' => 'commissionAmount1',
					'label' => 'Commission Amt 1',
					'type' => 'currency',
				),
				array(
					'id' => 'commissionRevenue1',
					'label' => 'Revenue/Profit 1',
					'type' => 'select',
					'choices' => array(
						'existing' => 'Existing revenue/profit',
						'new' => 'New revenue/profit',
					),
				),

				array(
					'type' => '_divider',
				),
                /* Disabled per #3309.
				array(
					'id' => 'userId2',
					'label' => 'Salesperson 2',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'commissionDate2',
					'label' => 'Commission Date 2',
					'type' => 'text',
                    'autocomplete' => 'off',
				),
				array(
					'id' => 'commissionAmount2',
					'label' => 'Commission Amt 2',
					'type' => 'currency',
				),
				array(
					'id' => 'commissionRevenue2',
					'label' => 'Revenue/Profit 2',
					'type' => 'select',
					'choices' => array(
						'existing' => 'Existing revenue/profit',
						'new' => 'New revenue/profit',
					),
				),

				array(
					'type' => '_divider',
				),

				array(
					'id' => 'userId3',
					'label' => 'Salesperson 3',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'commissionDate3',
					'label' => 'Commission Date 3',
					'type' => 'text',
                    'autocomplete' => 'off',
				),
				array(
					'id' => 'commissionAmount3',
					'label' => 'Commission Amt 3',
					'type' => 'currency',
				),
				array(
					'id' => 'commissionRevenue3',
					'label' => 'Revenue/Profit 3',
					'type' => 'select',
					'choices' => array(
						'existing' => 'Existing revenue/profit',
						'new' => 'New revenue/profit',
					),
				),
                */
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addOfflineLedger',
				),
				array(
					'id' => 'type',
					'type' => 'hidden',
					'value' => $type,
				),
			);

			Display::displayForm( 'new_offlineledger', $fields );

			?>

			<script type="text/javascript">
				$("#new_offlineledger input[name=orderDate], #new_offlineledger input[name=mailDate], #new_offlineledger input[name=paymentDate], #new_offlineledger input[name=loPaymentDate], #new_offlineledger input[name=commissionDate1], #new_offlineledger input[name=commissionDate2], #new_offlineledger input[name=commissionDate3]").datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});

				$("#new_offlineledger select[name='clientCompanyId']").select2({
					placeholder: "Select a client",
					allowClear: true
				});

				$("#new_offlineledger select[name='ledgerMonth']").select2({
					placeholder: "Select the ledger month",
					allowClear: true
				});

				$("#new_offlineledger select[name='vendorCompanyId']").select2({
					placeholder: "Select a vendor",
					allowClear: true
				});

				$("#new_offlineledger select[name='userId1'], #new_offlineledger select[name='userId2'], #new_offlineledger select[name='userId3']").select2({
					placeholder: "Select a salesperson",
					allowClear: true
				});
			</script>

			<?php

			break;

		case "deleteOfflineLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getOfflineLedgerById( $ledgerId );

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
						'value' => 'Offline',
					),
					array(
						'id' => 'clientCompanyId',
						'label' => 'Client',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a client',
						'choices' => $leads->getDivisionCompanies( 4, $entry->clientCompanyId ),
						'value' => $entry->clientCompanyId,
						'readonly' => true,
					),
					array(
						'id' => 'mailerName',
						'label' => 'Mailer',
						'type' => 'text',
						'value' => $entry->mailerName,
						'readonly' => true,
					),
					array(
						'id' => 'listName',
						'label' => 'List Name',
						'type' => 'text',
						'value' => $entry->listName,
						'readonly' => true,
					),
					array(
						'id' => 'clientPoNum',
						'label' => 'Client PO #',
						'type' => 'text',
						'value' => $entry->clientPoNum,
						'readonly' => true,
					),
					array(
						'id' => 'orderType',
						'label' => 'Order Type',
						'type' => 'radio',
						'choices' => array(
							't' => 'Test',
							'c' => 'Continuation',
						),
						'value' => $entry->orderType,
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
						'id' => 'mailDate',
						'label' => 'Mail Date',
						'type' => 'text',
						'value' => $entry->mailDate,
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
						'id' => 'vendorCompanyId',
						'label' => 'Vendor',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 4, $entry->vendorCompanyId ),
						'value' => $entry->vendorCompanyId,
						'readonly' => true,
					),
					array(
						'id' => 'ourPoNum',
						'label' => 'QM PO #',
						'type' => 'text',
						'value' => $entry->ourPoNum,
						'readonly' => true,
					),
					array(
						'id' => 'loInvoiceNum',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum,
						'readonly' => true,
					),
					array(
						'id' => 'loInvoiceAmount',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount,
						'readonly' => true,
					),
					array(
						'id' => 'loPaymentDate',
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->loPaymentDate,
						'readonly' => true,
					),
					array(
						'id' => 'loPaymentMethod',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod,
						'readonly' => true,
					),
					array(
						'id' => 'loPaymentAmount',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount,
						'readonly' => true,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId1',
						'label' => 'Salesperson 1',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId1,
						'readonly' => true,
					),
					array(
						'id' => 'commissionDate1',
						'label' => 'Commission Date 1',
						'type' => 'text',
						'value' => $entry->commissionDate1,
						'readonly' => true,
					),
					array(
						'id' => 'commissionAmount1',
						'label' => 'Commission Amt 1',
						'type' => 'currency',
						'value' => $entry->commissionAmount1,
						'readonly' => true,
					),
					array(
						'id' => 'commissionRevenue1',
						'label' => 'Revenue/Profit 1',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue1,
						'readonly' => true,
					),

					array(
						'type' => '_divider',
					),
                    /* Disabled per #3309.
					array(
						'id' => 'userId2',
						'label' => 'Salesperson 2',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId2,
						'readonly' => true,
					),
					array(
						'id' => 'commissionDate2',
						'label' => 'Commission Date 2',
						'type' => 'text',
						'value' => $entry->commissionDate2,
						'readonly' => true,
					),
					array(
						'id' => 'commissionAmount2',
						'label' => 'Commission Amt 2',
						'type' => 'currency',
						'value' => $entry->commissionAmount2,
						'readonly' => true,
					),
					array(
						'id' => 'commissionRevenue2',
						'label' => 'Revenue/Profit 2',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue2,
						'readonly' => true,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId3',
						'label' => 'Salesperson 3',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId3,
						'readonly' => true,
					),
					array(
						'id' => 'commissionDate3',
						'label' => 'Commission Date 3',
						'type' => 'text',
						'value' => $entry->commissionDate3,
						'readonly' => true,
					),
					array(
						'id' => 'commissionAmount3',
						'label' => 'Commission Amt 3',
						'type' => 'currency',
						'value' => $entry->commissionAmount3,
						'readonly' => true,
					),
					array(
						'id' => 'commissionRevenue3',
						'label' => 'Revenue/Profit 3',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue3,
						'readonly' => true,
					),
                    */
					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'deleteOfflineLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);

				Display::displayForm( 'delete_offlineledger', $fields );

			}

			break;

		case "editOfflineLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getOfflineLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				$ledgerMonth = new DateTime( $entry->ledgerMonth );

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => '_text',
						'value' => 'Offline',
					),
					array(
						'id' => 'clientCompanyId',
						'label' => 'Client',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a client',
						'choices' => $leads->getDivisionCompanies( 4, $entry->clientCompanyId ),
						'value' => $entry->clientCompanyId,
					),
					array(
						'id' => 'mailerName',
						'label' => 'Mailer',
						'type' => 'text',
						'value' => $entry->mailerName,
					),
					array(
						'id' => 'listName',
						'label' => 'List Name',
						'type' => 'text',
						'value' => $entry->listName,
					),
					array(
						'id' => 'clientPoNum',
						'label' => 'Client PO #',
						'type' => 'text',
						'value' => $entry->clientPoNum,
					),
					array(
						'id' => 'orderType',
						'label' => 'Order Type',
						'type' => 'radio',
						'choices' => array(
							't' => 'Test',
							'c' => 'Continuation',
						),
						'value' => $entry->orderType,
					),
					array(
						'id' => 'orderDate',
						'label' => 'Order Date',
						'type' => 'text',
						'value' => $entry->orderDate,
					),
					array(
						'id' => 'mailDate',
						'label' => 'Mail Date',
						'type' => 'text',
                        'autocomplete' => 'off',
						'value' => $entry->mailDate,
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
                        'autocomplete' => 'off',
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
						'id' => 'vendorCompanyId',
						'label' => 'Vendor',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 4, $entry->vendorCompanyId ),
						'value' => $entry->vendorCompanyId,
					),
					array(
						'id' => 'ourPoNum',
						'label' => 'QM PO #',
						'type' => 'text',
						'value' => $entry->ourPoNum,
					),
					array(
						'id' => 'loInvoiceNum',
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->loInvoiceNum,
					),
					array(
						'id' => 'loInvoiceAmount',
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loInvoiceAmount,
					),
					array(
						'id' => 'loPaymentDate',
						'label' => 'Date Paid',
						'type' => 'text',
                        'autocomplete' => 'off',
						'value' => $entry->loPaymentDate,
					),
					array(
						'id' => 'loPaymentMethod',
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->loPaymentMethod,
					),
					array(
						'id' => 'loPaymentAmount',
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->loPaymentAmount,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId1',
						'label' => 'Salesperson 1',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId1,
					),
					array(
						'id' => 'commissionDate1',
						'label' => 'Commission Date 1',
						'type' => 'text',
                        'autocomplete' => 'off',
						'value' => $entry->commissionDate1,
					),
					array(
						'id' => 'commissionAmount1',
						'label' => 'Commission Amt 1',
						'type' => 'currency',
						'value' => $entry->commissionAmount1,
					),
					array(
						'id' => 'commissionRevenue1',
						'label' => 'Revenue/Profit 1',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue1,
					),

					array(
						'type' => '_divider',
					),
                    /* Disabled per #3309.
					array(
						'id' => 'userId2',
						'label' => 'Salesperson 2',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId2,
					),
					array(
						'id' => 'commissionDate2',
						'label' => 'Commission Date 2',
						'type' => 'text',
                        'autocomplete' => 'off',
						'value' => $entry->commissionDate2,
					),
					array(
						'id' => 'commissionAmount2',
						'label' => 'Commission Amt 2',
						'type' => 'currency',
						'value' => $entry->commissionAmount2,
					),
					array(
						'id' => 'commissionRevenue2',
						'label' => 'Revenue/Profit 2',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue2,
					),

					array(
						'type' => '_divider',
					),

					array(
						'id' => 'userId3',
						'label' => 'Salesperson 3',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId3,
					),
					array(
						'id' => 'commissionDate3',
						'label' => 'Commission Date 3',
						'type' => 'text',
                        'autocomplete' => 'off',
						'value' => $entry->commissionDate3,
					),
					array(
						'id' => 'commissionAmount3',
						'label' => 'Commission Amt 3',
						'type' => 'currency',
						'value' => $entry->commissionAmount3,
					),
					array(
						'id' => 'commissionRevenue3',
						'label' => 'Revenue/Profit 3',
						'type' => 'select',
						'choices' => array(
							'existing' => 'Existing revenue/profit',
							'new' => 'New revenue/profit',
						),
						'value' => $entry->commissionRevenue3,
					),
                    */
					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'editOfflineLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);

				Display::displayForm( 'edit_offlineledger', $fields );
				?>

				<script type="text/javascript">
					$('#editofflineledger').on('shown.bs.modal', function (e) {
						$("#edit_offlineledger input[name=orderDate], #edit_offlineledger input[name=mailDate], #edit_offlineledger input[name=paymentDate], #edit_offlineledger input[name=loPaymentDate], #edit_offlineledger input[name=commissionDate1], #edit_offlineledger input[name=commissionDate2], #edit_offlineledger input[name=commissionDate3]").datepicker({
							// Consistent format with the HTML5 picker
							dateFormat: 'yy-mm-dd'
						});

						$("#edit_offlineledger select[name='clientCompanyId']").select2({
							placeholder: "Select a client",
							allowClear: true
						});

						$("#edit_offlineledger select[name='ledgerMonth']").select2({
							placeholder: "Select the ledger month",
							allowClear: true
						});

						$("#edit_offlineledger select[name='vendorCompanyId']").select2({
							placeholder: "Select a vendor",
							allowClear: true
						});

						$("#edit_offlineledger select[name='userId1'], #edit_offlineledger select[name='userId2'], #edit_offlineledger select[name='userId3']").select2({
							placeholder: "Select a salesperson",
							allowClear: true
						});
					});
				</script>

				<?php
			}
			break;
	}
	exit;
}

$title = 'Offline Ledger Entries';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Offline Ledger</h2>

	<?php if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) { ?>
		<p>
			<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#genericledger">Add a new
				entry
			</button>
		</p>
	<?php } ?>

	<?php
	$monthIn = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : null;
	$monthSelected = null;
	$months = $leads->getOfflineLedger( true );

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

		$entries = $leads->getOfflineLedger( false, $monthSelected );

		if( empty( $entries ) ) {

			print '<p>No ledger entries exist in the database.</p>';

		} else {

			$itemTypes = array(
				'' => '',
				't' => 'Test',
				'c' => 'Continuation',
			);

			$months = array();
			foreach( $entries as $entry ) {
				$month = substr( $entry->ledgerMonth, 0, 7 );
				$months[$month] = true;
			}

			foreach( $months as $month => $val ) {

				?>
				<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
				<table class="table table-bordered table-condensed table-striped-double ledger-sort"
				       id="offlineledger_<?php echo $month; ?>">
					<thead>
					<tr class="header">
						<th rowspan="2" style="vertical-align: middle;">Entry #</th>
						<th style="width:250px;">Client Name</th>
						<th style="width:300px;">Mailer Name</th>
						<th>Client PO</th>
						<th>Type</th>
						<th>Salesperson 1</th>
						<th>Salesperson 2</th>
						<th>Order Date</th>
						<th>QM Inv #</th>
						<th>Amount</th>
						<th>Pmt Date</th>
						<th>Pmt Mthd</th>
						<th>Pmt Amt</th>
						<?php if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) { ?>
							<th rowspan="2" style="vertical-align: middle;">Options</th>
						<?php } ?>
					</tr>
					<tr class="header">
						<th>Vendor Name</th>
						<th>List Name</th>
						<th>QM PO #</th>
						<th>Qty</th>
						<th>Commissions 1</th>
						<th>Commissions 2</th>
						<th>Mail Date</th>
						<th>LO Inv #</th>
						<th>LO Inv Amt</th>
						<th>LO Pmt Date</th>
						<th>LO Pmt Mthd</th>
						<th>LO Pmt Amt</th>
					</tr>
					</thead>
					<?php
					$invoiceAmount = $loInvoiceAmount = $commissionTotal = $paymentTotal = $loPaymentTotal = 0;
					foreach( $entries as $entry ) {
						if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
							$invoiceAmount += $entry->invoiceAmount;
							$loInvoiceAmount += $entry->loInvoiceAmount;
							if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) || LeadsSession::getUserId() == $entry->userId1 ) {
								$commissionTotal += $entry->commissionAmount1;
							}
							if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) || LeadsSession::getUserId() == $entry->userId2 ) {
								$commissionTotal += $entry->commissionAmount2;
							}
							if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) || LeadsSession::getUserId() == $entry->userId3 ) {
								$commissionTotal += $entry->commissionAmount3;
							}
							$paymentTotal += $entry->paymentAmount;
							$loPaymentTotal += $entry->loPaymentAmount;

							$ledger = new DateTime( $entry->ledgerMonth );
							?>
							<tbody>
							<tr>
								<td rowspan="2" class="text-center"
								    style="vertical-align:middle;"><?php echo htmlentities( $entry->entryId ); ?></td>
								<td><?php echo htmlentities( $entry->clientCompanyName ); ?></td>
								<td><?php echo htmlentities( $entry->mailerName ); ?></td>
								<td><?php echo htmlentities( $entry->clientPoNum ); ?></td>
								<td><?php echo isset( $itemTypes[$entry->orderType] ) ? $itemTypes[$entry->orderType] : ''; ?></td>
								<td><?php echo htmlentities( $entry->fullName1 ); ?></td>
								<td><?php echo htmlentities( $entry->fullName2 ); ?></td>
								<td><?php echo htmlentities( $entry->orderDate ); ?></td>
								<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
								<td>$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
								<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
								<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
								<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
								<?php if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) { ?>
									<td class="text-center" rowspan="2" style="vertical-align: middle;">
										<div class="btn-group">
											<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static"
											        data-target="#editofflineledger"
											        data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit
											</button>
											<button type="button" class="btn btn-primary btn-xs dropdown-toggle"
											        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
												<span class="caret"></span>
												<span class="sr-only">Toggle Dropdown</span>
											</button>
											<ul class="dropdown-menu">
												<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#deleteofflineledger"
												       data-ledger-id="<?php echo $entry->ledgerId; ?>">Delete</a></li>
											</ul>
										</div>
									</td>
								<?php } ?>
							</tr>
							<tr>
								<td><?php echo htmlentities( $entry->vendorCompanyName ); ?></td>
								<td><?php echo htmlentities( $entry->listName ); ?></td>
								<td><?php echo htmlentities( $entry->ourPoNum ); ?></td>
								<td><?php echo number_format( $entry->qty, 0 ); ?></td>
								<td><?php echo ( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) || LeadsSession::getUserId() == $entry->userId1 ) ? '$' . number_format( $entry->commissionAmount1, 2 ) : '&nbsp;'; ?></td>
								<td><?php echo ( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) || LeadsSession::getUserId() == $entry->userId2 ) ? '$' . number_format( $entry->commissionAmount2, 2 ) : '&nbsp;'; ?></td>
								<td><?php echo htmlentities( $entry->mailDate ); ?></td>
								<td><?php echo htmlentities( $entry->loInvoiceNum ); ?></td>
								<td>$<?php echo number_format( $entry->loInvoiceAmount, 2 ); ?></td>
								<td><?php echo htmlentities( $entry->loPaymentDate ); ?></td>
								<td><?php echo htmlentities( $entry->loPaymentMethod ); ?></td>
								<td>$<?php echo number_format( $entry->loPaymentAmount, 2 ); ?></td>
							</tr>
							</tbody>
							<?php
						}
					}
					?>
					<tfoot>
					<tr>
						<td class="text-center" rowspan="2">Totals</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $invoiceAmount, 2 ); ?></td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
						<?php if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) { ?>
							<td>&nbsp;</td>
						<?php } ?>
					</tr>
					<tr>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td colspan="2">$<?php echo number_format( $commissionTotal, 2 ); ?></td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $loInvoiceAmount, 2 ); ?></td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $loPaymentTotal, 2 ); ?></td>
						<?php if( LeadsSession::isValid([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]) ) { ?>
							<td>&nbsp;</td>
						<?php } ?>
					</tr>
					</tfoot>
				</table>
				<?php
			}
		}
	}
	?>

</div>

<script type="text/javascript">
	$('.form-inline select').change(function () {
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
