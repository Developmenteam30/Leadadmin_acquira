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
		case "addLedger":
			$c = true;
			$result['error'] = 'Failed when trying to add a new ledger entry.';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['divisionId'] ) ) {
				$result['error'] = 'Please select a division from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['verticalId'] ) ) {
				$result['error'] = 'Please select a vertical from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['invoiceNum'] ) ) {
				$result['error'] = 'Invoice number cannot be blank.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['invoiceAmount'] ) ) {
				$result['error'] = 'Invoice amount cannot be blank.';
				$c = false;
			}

			if( $c && is_numeric( $_REQUEST['invoiceAmount'] ) === false ) {
				$result['error'] = 'Invoice amount must be a numeric value.';
				$c = false;
			}

			if( $c && floatval( $_REQUEST['invoiceAmount'] ) < 0 ) {
				$result['error'] = 'Invoice amount cannot be less than zero.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['ledgerMonth'] ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['paymentDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === false ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate1'] ) ) {
				try {
					$commissionDate1 = new DateTime( $_REQUEST['commissionDate1'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 1.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount1'] ) && is_numeric( $_REQUEST['commissionAmount1'] ) === false ) {
				$result['error'] = 'Commission amount 1 must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount1'] ) && floatval( $_REQUEST['commissionAmount1'] ) < 0 ) {
				$result['error'] = 'Commission amount 1 cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate2'] ) ) {
				try {
					$commissionDate2 = new DateTime( $_REQUEST['commissionDate2'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 2.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount2'] ) && is_numeric( $_REQUEST['commissionAmount2'] ) === false ) {
				$result['error'] = 'Commission amount 2 must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount2'] ) && floatval( $_REQUEST['commissionAmount2'] ) < 0 ) {
				$result['error'] = 'Commission amount 2 cannot be less than zero.';
				$c = false;
			}

			if( $c ) {

				$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

				$ledgerId = $leads->addLedger( array(
					'divisionId' => empty( $_REQUEST['divisionId'] ) ? null : $_REQUEST['divisionId'],
					'companyId' => empty( $_REQUEST['companyId'] ) ? null : $_REQUEST['companyId'],
					'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
					'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
					'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
					'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
					'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? 0.00 : $_REQUEST['invoiceAmount'],
					'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
					'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
					'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
					'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
					'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
					'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
					'type' => $type,
					'userId1' => empty( $_REQUEST['userId1'] ) ? null : $_REQUEST['userId1'],
					'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
					'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId'] ) ? null : $_REQUEST['vendorCompanyId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to add entry to the database';
				} else {
					$leads->auditLog( 'LEDGER:ADD', $ledgerId );
				}
			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully added a new ledger entry.';
			}
			break;

		case "deleteLedger":
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				break;
			}

			if( empty( $_REQUEST['ledgerId'] ) ) {
				$result['error'] = 'Ledger ID is empty. Cannot delete!';
				break;
			}

			$entry = $leads->getLedgerById( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There is no ledger entry that exists by that ID.';
				break;
			}

			$status = $leads->deleteLedger( $_REQUEST['ledgerId'] );
			if( empty( $entry ) ) {
				$result['error'] = 'There was an error deleting this ledger entry.';
				break;
			}

			$leads->auditLog( 'LEDGER:DELETE', $_REQUEST['ledgerId'] );
			$result['status'] = 1;
			$result['error'] = 'Ledger deleted successfully.';

			break;

		case "editLedger":
			$c = true;
			$result['error'] = 'Failed when trying to edit a ledger entry.';

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['error'] = 'You do not have access to add/edit entries.';
				$c = false;
			}

			if( empty( $_REQUEST['ledgerId'] ) ) {
				$result['error'] = 'Ledger ID is empty. Cannot edit!';
				$c = false;
			}

			if( $c && empty( $_REQUEST['divisionId'] ) ) {
				$result['error'] = 'Please select a division from the list.';
				$c = false;
			}
			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['verticalId'] ) ) {
				$result['error'] = 'Please select a vertical from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['invoiceNum'] ) ) {
				$result['error'] = 'Invoice number cannot be blank.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['invoiceAmount'] ) ) {
				$result['error'] = 'Invoice amount cannot be blank.';
				$c = false;
			}

			if( $c && is_numeric( $_REQUEST['invoiceAmount'] ) === false ) {
				$result['error'] = 'Invoice amount must be a numeric value.';
				$c = false;
			}

			if( $c && floatval( $_REQUEST['invoiceAmount'] ) < 0 ) {
				$result['error'] = 'Invoice amount cannot be less than zero.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['ledgerMonth'] ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['paymentDate'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === false ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate1'] ) ) {
				try {
					$commissionDate1 = new DateTime( $_REQUEST['commissionDate1'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 1.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount1'] ) && is_numeric( $_REQUEST['commissionAmount1'] ) === false ) {
				$result['error'] = 'Commission amount 1 must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount1'] ) && floatval( $_REQUEST['commissionAmount1'] ) < 0 ) {
				$result['error'] = 'Commission amount 1 cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate2'] ) ) {
				try {
					$commissionDate2 = new DateTime( $_REQUEST['commissionDate2'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date 2.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount2'] ) && is_numeric( $_REQUEST['commissionAmount2'] ) === false ) {
				$result['error'] = 'Commission amount 2 must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount2'] ) && floatval( $_REQUEST['commissionAmount2'] ) < 0 ) {
				$result['error'] = 'Commission amount 2 cannot be less than zero.';
				$c = false;
			}

			if( $c ) {

				$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );

				$ledgerId = $leads->updateLedger( $_REQUEST['ledgerId'], array(
					'divisionId' => empty( $_REQUEST['divisionId'] ) ? null : $_REQUEST['divisionId'],
					'companyId' => empty( $_REQUEST['companyId'] ) ? null : $_REQUEST['companyId'],
					'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
					'paymentDate' => !isset( $paymentDate ) ? null : $paymentDate->format( 'Y-m-d' ),
					'paymentMethod' => empty( $_REQUEST['paymentMethod'] ) ? null : $_REQUEST['paymentMethod'],
					'ledgerMonth' => $ledgerMonth->format( 'Y-m-d' ),
					'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? 0.00 : $_REQUEST['invoiceAmount'],
					'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
					'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
					'commissionDate1' => !isset( $commissionDate1 ) ? null : $commissionDate1->format( 'Y-m-d' ),
					'commissionAmount1' => empty( $_REQUEST['commissionAmount1'] ) ? null : $_REQUEST['commissionAmount1'],
					'userId1' => empty( $_REQUEST['userId1'] ) ? null : $_REQUEST['userId1'],
					'commissionDate2' => !isset( $commissionDate2 ) ? null : $commissionDate2->format( 'Y-m-d' ),
					'commissionAmount2' => empty( $_REQUEST['commissionAmount2'] ) ? null : $_REQUEST['commissionAmount2'],
					'userId2' => empty( $_REQUEST['userId2'] ) ? null : $_REQUEST['userId2'],
					'vendorCompanyId' => empty( $_REQUEST['vendorCompanyId'] ) ? null : $_REQUEST['vendorCompanyId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to updated ledger entry.';
				} else {
					$leads->auditLog( 'LEDGER:EDIT', $_REQUEST['ledgerId'] );
				}

			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully edited ledger entry.';
			}
			break;

		case "getDivisionCompanies":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionCompanies( $_REQUEST['divisionId'], null, PDO::FETCH_ASSOC ) );
			} else {
				echo json_encode( array() );
			}
			exit;
			break;

		case "getDivisionVerticals":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionVerticals( $_REQUEST['divisionId'], PDO::FETCH_ASSOC ) );
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

		case "newLedger":

			$divisions = $leads->getDivisions();
			unset( $divisions[4] ); // Remove the offline division

			$fields = array(
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a division',
					'choices' => $divisions,
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a company',
					'choices' => array(),
				),
				array(
					'id' => 'verticalId',
					'label' => 'Vertical',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vertical',
					'choices' => array(),
				),
				array(
					'id' => 'invoiceNum',
					'label' => ( 0 == $type ) ? 'Client Invoice #' : 'QM Invoice #',
					'type' => 'text',
					'required' => true,
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
					'id' => 'vendorCompanyId',
					'label' => 'Vendor',
					'type' => 'select',
					'placeholder' => 'Select a vendor',
					'choices' => array(),
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
				),
				array(
					'id' => 'commissionAmount1',
					'label' => 'Commission Amt 1',
					'type' => 'currency',
				),
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
				),
				array(
					'id' => 'commissionAmount2',
					'label' => 'Commission Amt 2',
					'type' => 'currency',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addLedger',
				),
				array(
					'id' => 'type',
					'type' => 'hidden',
					'value' => $type,
				),
			);

			Display::displayForm( 'new_ledger', $fields );

			?>

			<script type="text/javascript">
				$('#new_ledger input[name=paymentDate], #new_ledger input[name=commissionDate1], #new_ledger input[name=commissionDate2]').datepicker({
					// Consistent format with the HTML5 picker
					dateFormat: 'yy-mm-dd'
				});

				//$('#new_ledger').on('shown.bs.modal', function (e) {
				$("#new_ledger select[name='divisionId']").select2({
					placeholder: "Select a division",
					allowClear: true
				});

				$("#new_ledger select[name='companyId']").select2({
					placeholder: "Select a company",
					allowClear: true
				});

				$("#new_ledger select[name='verticalId']").select2({
					placeholder: "Select a vertical",
					allowClear: true
				});

				$("#new_ledger select[name='vendorCompanyId']").select2({
					placeholder: "Select a vendor",
					allowClear: true
				});

				$("#new_ledger select[name='ledgerMonth']").select2({
					placeholder: "Select the ledger month",
					allowClear: true
				});

				$("#new_ledger select[name='userId1'], #new_ledger select[name='userId2']").select2({
					placeholder: "Select a salesperson",
					allowClear: true
				});

				$("#new_ledger select[name='divisionId']").change(function () {
					$.ajax({
						type: "post",
						url: "ledger.php",
						data: {
							a: 'getDivisionCompanies',
							divisionId: $("#new_ledger select[name='divisionId']").val()
						},
						dataType: "json",
						success: function (data) {
							var companyId = $("#new_ledger select[name='companyId']")
							if (companyId) {
								companyId.empty();
								companyId.append('<option></option>');
								$.each(data, function (i, obj) {
									companyId.append('<option value="' + obj.idCompany + '">' + obj.name + '</option>');
								});
								companyId.select2({
									placeholder: "Select a company",
									allowClear: true
								});
							}

							var vendorCompanyId = $("#new_ledger select[name='vendorCompanyId']")
							if (vendorCompanyId) {
								vendorCompanyId.empty();
								vendorCompanyId.append('<option></option>');
								$.each(data, function (i, obj) {
									vendorCompanyId.append('<option value="' + obj.idCompany + '">' + obj.name + '</option>');
								});
								vendorCompanyId.select2({
									placeholder: "Select a vendor",
									allowClear: true
								});
							}
						}
					}); //close $.ajax()

					$.ajax({
						type: "post",
						url: "ledger.php",
						data: {
							a: 'getDivisionVerticals',
							divisionId: $("#new_ledger select[name='divisionId']").val()
						},
						dataType: "json",
						success: function (data) {
							var verticalId = $("#new_ledger select[name='verticalId']")
							if (verticalId) {
								verticalId.empty();
								verticalId.append('<option></option>');
								$.each(data, function (i, obj) {
									verticalId.append('<option value="' + obj.verticalId + '">' + obj.name + '</option>');
								});
								verticalId.select2({
									placeholder: "Select a vertical",
									allowClear: true
								});
							}
						}
					}); //close $.ajax()

				});
				//});
			</script>

			<?php

			break;

		case "deleteLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				print '<p>Are you sure you wish to <strong>delete</strong> this entry?</p>';

				$ledgerMonth = new DateTime( $entry->ledgerMonth );

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a division',
						'choices' => $leads->getDivisions(),
						'value' => $entry->divisionId,
						'readonly' => true,
					),
					array(
						'id' => 'companyId',
						'label' => 'Company',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a company',
						'choices' => $leads->getDivisionCompanies( $entry->divisionId, $entry->companyId ),
						'value' => $entry->companyId,
						'readonly' => true,
					),
					array(
						'id' => 'verticalId',
						'label' => 'Vertical',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vertical',
						'choices' => $leads->getDivisionVerticals( $entry->divisionId ),
						'value' => $entry->verticalId,
						'readonly' => true,
					),
					array(
						'id' => 'invoiceNum',
						'label' => ( 0 == $entry->type ) ? 'Client Invoice #' : 'QM Invoice #',
						'type' => 'text',
						'required' => true,
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
						'required' => true,
						'choices' => $ledgerMonths,
						'value' => $ledgerMonth->format( 'Ym' ),
						'readonly' => true,
					),
					array(
						'id' => 'vendorCompanyId',
						'label' => 'Vendor',
						'type' => 'select',
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( $entry->divisionId, $entry->vendorCompanyId ),
						'value' => $entry->vendorCompanyId,
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
						'value' => $entry->paymentAmount,
						'readonly' => true,
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
					), array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'deleteLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);

				Display::displayForm( 'delete_ledger', $fields );

			}
			break;

		case "editLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				$ledgerMonth = new DateTime( $entry->ledgerMonth );
				$divisions = $leads->getDivisions();
				unset( $divisions[4] ); // Remove the offline division

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a division',
						'choices' => $divisions,
						'value' => $entry->divisionId,
					),
					array(
						'id' => 'companyId',
						'label' => 'Company',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a company',
						'choices' => $leads->getDivisionCompanies( $entry->divisionId, $entry->companyId ),
						'value' => $entry->companyId,
					),
					array(
						'id' => 'verticalId',
						'label' => 'Vertical',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vertical',
						'choices' => $leads->getDivisionVerticals( $entry->divisionId ),
						'value' => $entry->verticalId,
					),
					array(
						'id' => 'invoiceNum',
						'label' => ( 0 == $entry->type ) ? 'Client Invoice #' : 'QM Invoice #',
						'type' => 'text',
						'required' => true,
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
						'required' => true,
						'choices' => $ledgerMonths,
						'value' => $ledgerMonth->format( 'Ym' ),
					),
					array(
						'id' => 'vendorCompanyId',
						'label' => 'Vendor',
						'type' => 'select',
						'placeholder' => 'Select a vendor',
						'choices' => $leads->getDivisionCompanies( $entry->divisionId, $entry->vendorCompanyId ),
						'value' => $entry->vendorCompanyId,
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
						'value' => $entry->paymentAmount,
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
						'value' => $entry->commissionDate1,
					),
					array(
						'id' => 'commissionAmount1',
						'label' => 'Commission Amt 1',
						'type' => 'currency',
						'value' => $entry->commissionAmount1,
					),
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
						'value' => $entry->commissionDate2,
					),
					array(
						'id' => 'commissionAmount2',
						'label' => 'Commission Amt 2',
						'type' => 'currency',
						'value' => $entry->commissionAmount2,
					),
					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'editLedger',
					),
					array(
						'id' => 'ledgerId',
						'type' => 'hidden',
						'value' => $ledgerId,
					),
				);

				Display::displayForm( 'edit_ledger', $fields );
				?>

				<script type="text/javascript">
					$('#editledger').on('shown.bs.modal', function (e) {
						console.log('test');
						$('#editledger input[name=paymentDate], #editledger input[name=commissionDate1], #editledger input[name=commissionDate2]').datepicker({
							// Consistent format with the HTML5 picker
							dateFormat: 'yy-mm-dd'
						});

						$("#editledger select[name='divisionId']").select2({
							placeholder: "Select a division",
							allowClear: true
						});

						$("#editledger select[name='companyId']").select2({
							placeholder: "Select a company",
							allowClear: true
						});

						$("#editledger select[name='verticalId']").select2({
							placeholder: "Select a vertical",
							allowClear: true
						});

						$("#editledger select[name='vendorCompanyId']").select2({
							placeholder: "Select a vendor",
							allowClear: true
						});

						$("#editledger select[name='ledgerMonth']").select2({
							placeholder: "Select the ledger month",
							allowClear: true
						});

						$("#editledger select[name='userId1'], #editledger select[name='userId2']").select2({
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

$title = 'Ledger Entry';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2><?php echo( $type == 0 ? 'Publisher' : 'Advertiser' ); ?></h2>

	<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
		<p>
			<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#genericledger">Add a new entry</button>
		</p>
	<?php } ?>

	<?php
	$monthIn = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : null;
	$monthSelected = null;
	$months = $leads->getLedger( $type, true );

	if( empty( $months ) ) {

		print '<p>No ledger entries exist in the database.</p>' . PHP_EOL;

	} else {
		print '<form class="form-inline" method="get">' . PHP_EOL;
		printf( '<input type="hidden" name="type" value="%s" />' . PHP_EOL,
			$type
		);
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

		$entries = $leads->getLedger( $type, false, $monthSelected );

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
				<table class="table table-bordered table-condensed table-striped ledger-sort" id="ledger_<?php echo $month; ?>">
					<thead>
					<tr class="header">
						<th>Entry #</th>
						<th>Company Name</th>
						<th>Vertical</th>
						<th>Invoice Amount</th>
						<th>Invoice #</th>
						<th>Date Paid</th>
						<th>Vendor</th>
						<th>Payment Amount</th>
						<th>Method</th>
						<th>Salesperson</th>
						<th>Commissions</th>
						<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
							<th>Options</th>
						<?php } ?>
					</tr>
					</thead>
					<tbody>
					<?php
					$invoiceTotal = $paymentTotal = $commissionTotal = 0;
					foreach( $entries as $entry ) {
						if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
							$invoiceTotal += $entry->invoiceAmount;
							$paymentTotal += $entry->paymentAmount;
							if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId1 ) {
								$commissionTotal += $entry->commissionAmount1;
							}
							if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId2 ) {
								$commissionTotal += $entry->commissionAmount2;
							}

							$ledger = new DateTime( $entry->ledgerMonth );
							?>
							<tr>
								<td><?php echo htmlentities( $entry->entryId ); ?></td>
								<td><?php echo htmlentities( $entry->companyName ); ?></td>
								<td><?php echo htmlentities( $entry->verticalName ); ?></td>
								<td data-tf-sortKey="<?php echo number_format( $entry->invoiceAmount, 2 ); ?>">$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
								<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
								<td><?php echo $entry->paymentDate; ?></td>
								<td><?php echo htmlentities( $entry->vendorCompanyName ); ?></td>
								<td data-tf-sortKey="<?php echo number_format( $entry->paymentAmount, 2 ); ?>">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
								<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
								<td><?php echo htmlentities( $entry->fullName1 ); ?><br/><?php echo htmlentities( $entry->fullName2 ); ?></td>
								<td data-tf-sortKey="<?php echo number_format( $entry->commissionAmount1, 2 ); ?>"><?php echo ( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId1 ) ? '$' . number_format( $entry->commissionAmount1, 2 ) : '&nbsp;'; ?><?php if( ( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId1 ) && !empty( $entry->commissionDate1 ) && !empty( $entry->commissionAmount1 ) ) {
										echo ' <img alt="Green checkmark" height="13" src="images/green_check.png" width="12" />';
									} ?><br/><?php echo ( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId2 ) ? '$' . number_format( $entry->commissionAmount2, 2 ) : '&nbsp;'; ?><?php if( ( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) || LeadsSession::getUserId() == $entry->userId2 ) && !empty( $entry->commissionDate2 ) && !empty( $entry->commissionAmount2 ) ) {
										echo ' <img alt="Green checkmark" height="13" src="images/green_check.png" width="12" />';
									} ?></td>
								<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
									<td class="text-center">
										<div class="btn-group">
											<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button>
											<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
												<span class="caret"></span>
												<span class="sr-only">Toggle Dropdown</span>
											</button>
											<ul class="dropdown-menu">
												<li><a href="#" data-toggle="modal" data-target="#deleteledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Delete</a></li>
											</ul>
										</div>
									</td>
								<?php } ?>
							</tr>
							<?php
						}
					}
					?>
					</tbody>
					<tfoot>
					<tr>
						<td>Monthly Totals</td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $invoiceTotal, 2 ); ?></td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
						<td>&nbsp;</td>
						<td>&nbsp;</td>
						<td>$<?php echo number_format( $commissionTotal, 2 ); ?></td>
						<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
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
	$('.ledger-sort').each(function () {
		var tf = new TableFilter($(this).attr('id'), {
			base_path: '/leadadmin/libraries/tablefilter/',
			state: {
				types: ['local_storage'],
				sort: true,
				filters: false,
				page_number: false,
				page_length: false,
				columns_visibility: false,
				filters_visibility: false
			},
			grid: false,
			filters_row_index: 1,
			col_types: [
				'number',
				'string',
				'string',
				{type: 'formatted-number', decimal: '.', thousands: ','},
				'string',
				{type: 'date', locale: 'en-US'},
				{type: 'formatted-number', decimal: '.', thousands: ','},
				'string',
				'string',
				{type: 'formatted-number', decimal: '.', thousands: ','}
			],
			extensions: [{
				name: 'sort',
				image_asc_class_name: 'custom-ascending',
				image_desc_class_name: 'custom-descending'
			}],
			sort: true
		});
		tf.init();
	});
</script>

<?php require_once( INCLUDES . 'modals.php' ); ?>

</body>
</html>
