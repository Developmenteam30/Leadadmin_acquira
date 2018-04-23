<?php

include( "../../includes/c_config.php" );

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

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
		case "addPhoneLedger":
			$result['error'] = 'Failed when trying to add a new ledger entry.';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['clientCompanyId'] ) ) {
				$result['error'] = 'Please select a client from the list.';
				break;
			}

			if( empty( $_REQUEST['verticalId'] ) ) {
				$result['error'] = 'Please select a vertical from the list.';
				$c = false;
			}

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
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

			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
				if( !empty( $_REQUEST['loInvoiceAmount' . $i] ) && is_numeric( $_REQUEST['loInvoiceAmount' . $i] ) === false ) {
					$result['error'] = 'LO #' . $i . ' invoice amount must be a numeric value.';
					break 2;
				}

				if( !empty( $_REQUEST['loInvoiceAmount' . $i] ) && floatval( $_REQUEST['loInvoiceAmount' . $i] ) < 0 ) {
					$result['error'] = 'LO #' . $i . ' invoice amount cannot be less than zero.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentAmount' . $i] ) && is_numeric( $_REQUEST['loPaymentAmount' . $i] ) === false ) {
					$result['error'] = 'LO #' . $i . ' payment amount must be a numeric value.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentAmount' . $i] ) && floatval( $_REQUEST['loPaymentAmount' . $i] ) < 0 ) {
					$result['error'] = 'LO #' . $i . ' payment amount cannot be less than zero.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentDate' . $i] ) ) {
					try {
						${'loPaymentDate' . $i} = new DateTime( $_REQUEST['loPaymentDate' . $i] );
					} catch( Exception $e ) {
						$result['error'] = 'Please enter a valid LO #' . $i . ' payment date.';
						break 2;
					}
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
				'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
				'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
				'userId1' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
				'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
				'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
				'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to add entry to the database';
				break;
			}

			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
				$leads->replacePhoneLedgerVendor( array(
					'ledgerId' => $ledgerId,
					'indexId' => $i,
					'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId' . $i] ) ? null : $_REQUEST['vendorCompanyId' . $i],
					'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum' . $i] ) ? null : $_REQUEST['loInvoiceNum' . $i],
					'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount' . $i] ) ? null : $_REQUEST['loInvoiceAmount' . $i],
					'loPaymentDate' => !isset( ${'loPaymentDate' . $i} ) ? null : ${'loPaymentDate' . $i}->format( 'Y-m-d' ),
					'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod' . $i] ) ? null : $_REQUEST['loPaymentMethod' . $i],
					'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount' . $i] ) ? null : $_REQUEST['loPaymentAmount' . $i],
				) );
			}

			$leads->auditLog( 'LEDGER-PHONE:ADD', $ledgerId );
			$result['status'] = 1;
			$result['error'] = 'Successfully added a new ledger entry.';
			break;

		case "deletePhoneLedger":
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) {
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

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) {
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

			if( empty( $_REQUEST['verticalId'] ) ) {
				$result['error'] = 'Please select a vertical from the list.';
				$c = false;
			}

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
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

			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
				if( !empty( $_REQUEST['loInvoiceAmount' . $i] ) && is_numeric( $_REQUEST['loInvoiceAmount' . $i] ) === false ) {
					$result['error'] = 'LO #' . $i . ' invoice amount must be a numeric value.';
					break 2;
				}

				if( !empty( $_REQUEST['loInvoiceAmount' . $i] ) && floatval( $_REQUEST['loInvoiceAmount' . $i] ) < 0 ) {
					$result['error'] = 'LO #' . $i . ' invoice amount cannot be less than zero.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentAmount' . $i] ) && is_numeric( $_REQUEST['loPaymentAmount' . $i] ) === false ) {
					$result['error'] = 'LO #' . $i . ' payment amount must be a numeric value.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentAmount' . $i] ) && floatval( $_REQUEST['loPaymentAmount' . $i] ) < 0 ) {
					$result['error'] = 'LO #' . $i . ' payment amount cannot be less than zero.';
					break 2;
				}

				if( !empty( $_REQUEST['loPaymentDate' . $i] ) ) {
					try {
						${'loPaymentDate' . $i} = new DateTime( $_REQUEST['loPaymentDate' . $i] );
					} catch( Exception $e ) {
						$result['error'] = 'Please enter a valid LO #' . $i . ' payment date.';
						break 2;
					}
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
				'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
				'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
				'userId1' => empty( $_REQUEST['userId1'] ) ? null : $_REQUEST['userId1'],
				'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
				'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
				'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to update ledger entry.';
				break;
			}

			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
				$leads->replacePhoneLedgerVendor( array(
					'ledgerId' => $_REQUEST['ledgerId'],
					'indexId' => $i,
					'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId' . $i] ) ? null : $_REQUEST['vendorCompanyId' . $i],
					'loInvoiceNum' => empty( $_REQUEST['loInvoiceNum' . $i] ) ? null : $_REQUEST['loInvoiceNum' . $i],
					'loInvoiceAmount' => empty( $_REQUEST['loInvoiceAmount' . $i] ) ? null : $_REQUEST['loInvoiceAmount' . $i],
					'loPaymentDate' => !isset( ${'loPaymentDate' . $i} ) ? null : ${'loPaymentDate' . $i}->format( 'Y-m-d' ),
					'loPaymentMethod' => empty( $_REQUEST['loPaymentMethod' . $i] ) ? null : $_REQUEST['loPaymentMethod' . $i],
					'loPaymentAmount' => empty( $_REQUEST['loPaymentAmount' . $i] ) ? null : $_REQUEST['loPaymentAmount' . $i],
				) );
			}

			$leads->auditLog( 'LEDGER-PHONE:EDIT', $_REQUEST['ledgerId'] );
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
			);

			$fields[] = array(
				'type' => '_divider',
			);

			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {

				$fields[] = array(
					'type' => '_toggle_start',
					'value' => 'Vendor #' . $i,
					'id' => 'vendor_collapse_' . $i,
					'collapsed' => $i > 1 ? true : false,
				);

				$fields[] = array(
					'id' => 'vendorCompanyId' . $i,
					'label' => 'Vendor #' . $i,
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vendor',
					'choices' => $leads->getDivisionCompanies( 5, null ),
				);
				$fields[] = array(
					'id' => 'loInvoiceNum' . $i,
					'label' => 'LO Invoice #',
					'type' => 'text',
				);
				$fields[] = array(
					'id' => 'loInvoiceAmount' . $i,
					'label' => 'LO Amount',
					'type' => 'currency',
					'required' => true,
				);
				$fields[] = array(
					'id' => 'loPaymentDate' . $i,
					'label' => 'Date Paid',
					'type' => 'text',
				);
				$fields[] = array(
					'id' => 'loPaymentMethod' . $i,
					'label' => 'Payment Method',
					'type' => 'text',
				);
				$fields[] = array(
					'id' => 'loPaymentAmount' . $i,
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
				);

				$fields[] = array(
					'type' => '_divider',
				);

				$fields[] = array(
					'type' => '_toggle_end',
				);
			}

			$fields[] = array(
				'type' => '_divider',
			);
			$fields[] = array(
				'id' => 'userId1',
				'label' => 'Salesperson 1',
				'type' => 'select',
				'required' => true,
				'placeholder' => 'Select a salesperson',
				'choices' => $leads->getStaffUsers(),
			);
			$fields[] = array(
				'id' => 'commissionDate1',
				'label' => 'Commission Date 1',
				'type' => 'text',
			);
			$fields[] = array(
				'id' => 'commissionAmount 1',
				'label' => 'Commission Amt 1',
				'type' => 'currency',
			);

			$fields[] = array(
				'type' => '_divider',
			);
			$fields[] = array(
				'id' => 'userId2',
				'label' => 'Salesperson 2',
				'type' => 'select',
				'required' => true,
				'placeholder' => 'Select a salesperson',
				'choices' => $leads->getStaffUsers(),
			);
			$fields[] = array(
				'id' => 'commissionDate2',
				'label' => 'Commission Date 2',
				'type' => 'text',
			);
			$fields[] = array(
				'id' => 'commissionAmount 2',
				'label' => 'Commission Amt 2',
				'type' => 'currency',
			);

			$fields[] = array(
				'id' => 'a',
				'type' => 'hidden',
				'value' => 'addPhoneLedger',
			);

			Display::displayForm( 'new_phoneledger', $fields );

			?>

            <script type="text/javascript">
				$("#new_phoneledger input[name=orderDate], #new_phoneledger input[name=paymentDate], #new_phoneledger input[name=commissionDate1], #new_phoneledger input[name=commissionDate2]").datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});

				<?php for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) { ?>
				$("#new_phoneledger input[name=loPaymentDate<?php echo $i; ?>]").datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});
				$("#new_phoneledger select[name='vendorCompanyId<?php echo $i;?>']").select2({
					placeholder: "Select a vendor",
					allowClear: true
				});
				<?php } ?>

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

				$("#new_phoneledger select[name='userId1'], #new_phoneledger select[name='userId2']").select2({
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
						'type' => '_divider',
					),

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
				);

				for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
					$fields[] = array(
						'type' => '_toggle_start',
						'value' => 'Vendor #' . $i,
						'id' => 'vendor_collapse_' . $i,
						'collapsed' => empty( $entry->{'vendorCompanyId' . $i} ) ? true : false,
					);

					$fields[] = array(
						'id' => 'vendorCompanyId' . $i,
						'label' => 'Vendor #' . $i,
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( 5, null ),
						'value' => $entry->{'vendorCompanyId' . $i},
					);
					$fields[] = array(
						'id' => 'loInvoiceNum' . $i,
						'label' => 'LO Invoice #',
						'type' => 'text',
						'value' => $entry->{'loInvoiceNum' . $i},
					);
					$fields[] = array(
						'id' => 'loInvoiceAmount' . $i,
						'label' => 'LO Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->{'loInvoiceAmount' . $i},
					);
					$fields[] = array(
						'id' => 'loPaymentDate' . $i,
						'label' => 'Date Paid',
						'type' => 'text',
						'value' => $entry->{'loPaymentDate' . $i},
					);
					$fields[] = array(
						'id' => 'loPaymentMethod' . $i,
						'label' => 'Payment Method',
						'type' => 'text',
						'value' => $entry->{'loPaymentMethod' . $i},
					);
					$fields[] = array(
						'id' => 'loPaymentAmount' . $i,
						'label' => 'Payment Amount',
						'type' => 'currency',
						'required' => true,
						'value' => $entry->{'loPaymentAmount' . $i},
					);

					$fields[] = array(
						'type' => '_divider',
					);

					$fields[] = array(
						'type' => '_toggle_end',
					);
				}

				$fields[] = array(
					'type' => '_divider',
				);

				$fields[] = array(
					'id' => 'userId1',
					'label' => 'Salesperson 1',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
					'value' => $entry->userId1,
				);
				$fields[] = array(
					'id' => 'commissionDate1',
					'label' => 'Commission Date 1',
					'type' => 'text',
					'value' => $entry->commissionDate1,
				);
				$fields[] = array(
					'id' => 'commissionAmount1',
					'label' => 'Commission Amt 1',
					'type' => 'currency',
					'value' => $entry->commissionAmount1,
				);

				$fields[] = array(
					'type' => '_divider',
				);

				$fields[] = array(
					'id' => 'userId2',
					'label' => 'Salesperson 2',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
					'value' => $entry->userId2,
				);
				$fields[] = array(
					'id' => 'commissionDate2',
					'label' => 'Commission Date 2',
					'type' => 'text',
					'value' => $entry->commissionDate2,
				);
				$fields[] = array(
					'id' => 'commissionAmount2',
					'label' => 'Commission Amt 2',
					'type' => 'currency',
					'value' => $entry->commissionAmount2,
				);

				$fields[] = array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'editPhoneLedger',
				);
				$fields[] = array(
					'id' => 'ledgerId',
					'type' => 'hidden',
					'value' => $ledgerId,
				);


				Display::displayForm( 'edit_phoneledger', $fields );
				?>

                <script type="text/javascript">
					$('#editphoneledger').on('shown.bs.collapse', function (e) {
						<?php for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) { ?>
						$("#edit_phoneledger select[name='vendorCompanyId<?php echo $i;?>']").select2({
							placeholder: "Select a vendor",
							allowClear: true
						});
						<?php } ?>
                        console.log('firing');
					});
					$('#editphoneledger').on('shown.bs.modal', function (e) {
						$("#edit_phoneledger input[name=orderDate], #edit_phoneledger input[name=paymentDate], #edit_phoneledger input[name=commissionDate1], #edit_phoneledger input[name=commissionDate2]").datepicker({
							// Consistent format with the HTML5 picker
							dateFormat: 'yy-mm-dd'
						});

						<?php for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) { ?>
						$("#edit_phoneledger input[name=loPaymentDate<?php echo $i; ?>]").datepicker({
							// Consistent format with the HTML5 picker
							dateFormat: 'yy-mm-dd'
						});
						$("#edit_phoneledger select[name='vendorCompanyId<?php echo $i;?>']").select2({
							placeholder: "Select a vendor",
							allowClear: true
						});
						<?php } ?>

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

						$("#edit_phoneledger select[name='userId1'], #edit_phoneledger select[name='userId2']").select2({
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

$title = 'Leads Ledger Entries';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

    <h2>Leads Ledger</h2>

	<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
        <p>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#genericledger">Add a new entry</button>
        </p>
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
                        <th rowspan="2" style="vertical-align: middle;">Entry #</th>
                        <th style="width:250px;">Client Name</th>
                        <th style="width:300px;">List Name</th>
                        <th>Order Date</th>
                        <th>Qty</th>
                        <th>QM Inv #</th>
                        <th>Inv Amt</th>
                        <th>Pmt Date</th>
                        <th>Pmt Mthd</th>
                        <th>Pmt Amt</th>
                        <th>Salesperson 1</th>
                        <th>Commissions 1</th>
						<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
                            <th rowspan="2" style="vertical-align: middle;">Options</th>
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
                        <th>Salesperson 2</th>
                        <th>Commissions 2</th>
                    </tr>
                    </thead>
					<?php
					$invoiceAmount = $loInvoiceAmount = $commissionTotal = $paymentTotal = $loPaymentTotal = 0;
					foreach( $entries as $entry ) {
						if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
							$invoiceAmount += $entry->invoiceAmount;
							$loInvoiceAmount += $entry->loInvoiceAmount1;
							if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) || LeadsSession::getUserId() == $entry->userId1 ) {
								$commissionTotal += $entry->commissionAmount1;
							}
							if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) || LeadsSession::getUserId() == $entry->userId2 ) {
								$commissionTotal += $entry->commissionAmount2;
							}
							$paymentTotal += $entry->paymentAmount;
							$loPaymentTotal += $entry->loPaymentAmount1;

							$ledger = new DateTime( $entry->ledgerMonth );
							?>
                            <tbody>
                            <tr>
                                <td rowspan="2" class="text-center" style="vertical-align:middle;"><?php echo htmlentities( $entry->entryId ); ?></td>
                                <td><?php echo htmlentities( $entry->clientCompanyName ); ?></td>
                                <td><?php echo htmlentities( $entry->listName ); ?></td>
                                <td><?php echo htmlentities( $entry->orderDate ); ?></td>
                                <td><?php echo number_format( $entry->qty, 0 ); ?></td>
                                <td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
                                <td>$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
                                <td><?php echo htmlentities( $entry->paymentDate ); ?></td>
                                <td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
                                <td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
                                <td><?php echo htmlentities( $entry->fullName1 ); ?></td>
                                <td><?php echo ( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) || LeadsSession::getUserId() == $entry->userId1 ) ? '$' . number_format( $entry->commissionAmount1, 2 ) : '&nbsp;'; ?></td>
								<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
                                    <td class="text-center" rowspan="2" style="vertical-align: middle;">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editphoneledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button>
                                            <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <span class="caret"></span>
                                                <span class="sr-only">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a href="#" data-toggle="modal" data-target="#deletephoneledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
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
                                <td><?php htmlentities( $entry->fullName2 ); ?></td>
                                <td><?php echo ( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) || LeadsSession::getUserId() == $entry->userId2 ) ? '$' . number_format( $entry->commissionAmount2, 2 ) : '&nbsp;'; ?></td>
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
		                <td>$<?php echo number_format( $invoiceAmount, 2 ); ?></td>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
		                <td>&nbsp;</td>
		                <td rowspan="2">$<?php echo number_format( $commissionTotal, 2 ); ?></td>
		                <?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
			                <td>&nbsp;</td>
		                <?php } ?>
	                </tr>
	                <tr>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>$<?php echo number_format( $loInvoiceAmount, 2 ); ?></td>
		                <td>&nbsp;</td>
		                <td>&nbsp;</td>
		                <td>$<?php echo number_format( $loPaymentTotal, 2 ); ?></td>
		                <td>&nbsp;</td>
		                <?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
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
