<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

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

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addLedger":
			$result['error'] = 'Failed when trying to add a new ledger entry.';

			if( empty( $_REQUEST['clientCompanyId'] ) ) {
				$result['error'] = 'Please select a client from the list.';
				break;
			}

			if( !empty( $_REQUEST['orderDate'] ) ) {
				try {
					$orderDate = new DateTime( $_REQUEST['orderDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['mailDate'] ) ) {
				try {
					$mailDate = new DateTime( $_REQUEST['mailDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
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
					$result['error'] = 'Please enter a valid mail date.';
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

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && is_numeric( $_REQUEST['loInvoiceAmount'] ) === FALSE ) {
				$result['error'] = 'LO invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && floatval( $_REQUEST['loInvoiceAmount'] ) < 0 ) {
				$result['error'] = 'LO invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && is_numeric( $_REQUEST['loPaymentAmount'] ) === FALSE ) {
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
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
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
				'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
				'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
			) );

			if( null === $ledgerId ) {
				$result['error'] = 'Unable to add entry to the database';
				break;
			}

			$leads->auditLog( 'LEDGER-OFFLINE:ADD', $ledgerId );
			$result['status'] = 1;
			$result['error'] = 'Successfully added a new ledger entry.';
		break;

		case "editLedger":
			$result['error'] = 'Failed when trying to edit a ledger entry.';

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
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid order date.';
					break;
				}
			}

			if( !empty( $_REQUEST['mailDate'] ) ) {
				try {
					$mailDate = new DateTime( $_REQUEST['mailDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
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
					$result['error'] = 'Please enter a valid mail date.';
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

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && is_numeric( $_REQUEST['loInvoiceAmount'] ) === FALSE ) {
				$result['error'] = 'LO invoice amount must be a numeric value.';
				break;
			}

			if( !empty( $_REQUEST['loInvoiceAmount'] ) && floatval( $_REQUEST['loInvoiceAmount'] ) < 0 ) {
				$result['error'] = 'LO invoice amount cannot be less than zero.';
				break;
			}

			if( !empty( $_REQUEST['loPaymentAmount'] ) && is_numeric( $_REQUEST['loPaymentAmount'] ) === FALSE ) {
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
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid mail date.';
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
				'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
				'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
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

		case "newLedger":

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
				),
				array(
					'id' => 'mailDate',
					'label' => 'Mail Date',
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
					'id' => 'userId',
					'label' => 'Salesperson',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'commissionAmount',
					'label' => 'Commission Amt',
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
$("#new_ledger input[name=orderDate], #new_ledger input[name=mailDate], #new_ledger input[name=paymentDate], #new_ledger input[name=loPaymentDate]").datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$("#new_ledger select[name='clientCompanyId']").select2({
	placeholder: "Select a client",
	allowClear: true
});

$("#new_ledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#new_ledger select[name='vendorCompanyId']").select2({
	placeholder: "Select a vendor",
	allowClear: true
});

$("#new_ledger select[name='userId']").select2({
	placeholder: "Select a salesperson",
	allowClear: true
});
</script>

<?php

		break;

		case "editLedger":
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
						'id' => 'userId',
						'label' => 'Salesperson',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a salesperson',
						'choices' => $leads->getStaffUsers(),
						'value' => $entry->userId,
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
$("#edit_ledger input[name=orderDate], #edit_ledger input[name=mailDate], #edit_ledger input[name=paymentDate], #edit_ledger input[name=loPaymentDate]").datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$("#edit_ledger select[name='clientCompanyId']").select2({
	placeholder: "Select a client",
	allowClear: true
});

$("#edit_ledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#edit_ledger select[name='vendorCompanyId']").select2({
	placeholder: "Select a vendor",
	allowClear: true
});

$("#edit_ledger select[name='userId']").select2({
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

$title = 'Offline Ledger Entries';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Offline Ledger</h2>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newledger">Add a new entry</button></p>

<?php
	$entries = $leads->getOfflineLedger();
	if( empty( $entries ) ) {

		print '<p>No ledger entries exist in the database.</p>';

	} else {
		$months = array();
		foreach( $entries as $entry ) {
			$month = substr( $entry->ledgerMonth, 0, 7 );
			$months[$month] = true;
		}

		$itemTypes = array(
			'' => '',
			't' => 'Test',
			'c' => 'Continuation',
		);

		foreach( $months as $month => $val ) {
?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped-double ledger-sort" id="ledger_<?php echo $month; ?>">
	<thead>
		<tr class="header">
			<th>Client Name</th>
			<th>Mailer Name</th>
			<th>Client PO</th>
			<th>Type</th>
			<th>Salesperson</th>
			<th>Order Date</th>
			<th>QM Inv #</th>
			<th>Amount</th>
			<th>Pmt Date</th>
			<th>Pmt Mthd</th>
			<th>Pmt Amt</th>
			<th rowspan="2" style="vertical-align: middle;">Options</th>
		</tr>
		<tr class="header">
			<th>Vendor Name</th>
			<th>List Name</th>
			<th>QM PO #</th>
			<th>Qty</th>
			<th>Commissions</th>
			<th>Mail Date</th>
			<th>LO Inv #</th>
			<th>LO Inv Amt</th>
			<th>LO Pmt Date</th>
			<th>LO Pmt Mthd</th>
			<th>LO Pmt Amt</th>
		</tr>
	</thead>
	<tbody>
<?php
			$paymentTotal = 0;
			foreach( $entries as $entry ) {

				if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
					$paymentTotal += $entry->paymentAmount;

					$ledger = new DateTime( $entry->ledgerMonth );
?>
		<tr>
			<td><?php echo htmlentities( $entry->clientCompanyName ); ?></td>
			<td><?php echo htmlentities( $entry->mailerName ); ?></td>
			<td><?php echo htmlentities( $entry->clientPoNum ); ?></td>
			<td><?php echo isset( $itemTypes[$entry->orderType] ) ? $itemTypes[$entry->orderType] : ''; ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td><?php echo htmlentities( $entry->orderDate ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td class="text-center" rowspan="2" style="vertical-align: middle;"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button></td>
		</tr>
		<tr>
			<td><?php echo htmlentities( $entry->vendorCompanyName ); ?></td>
			<td><?php echo htmlentities( $entry->listName ); ?></td>
			<td><?php echo htmlentities( $entry->ourPoNum ); ?></td>
			<td class="text-right"><?php echo number_format( $entry->qty, 0 ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->commissionAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->mailDate ); ?></td>
			<td><?php echo htmlentities( $entry->loInvoiceNum ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->loInvoiceAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->loPaymentMethod ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->loPaymentAmount, 2 ); ?></td>
		</tr>
<?php
				}
			}
?>
	</tbody>
</table>
<?php
		}
	}
?>

<div class="modal fade" id="newledger" tabindex="-1" role="dialog" aria-labelledby="newledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="newcompany_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newledger" type="button" class="btn btn-primary">Add entry</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editledger" tabindex="-1" role="dialog" aria-labelledby="editledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="editledger_title">Edit a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
		<button id="modal-save-editledger" type="button" class="btn btn-primary">Save changes</button>
	  </div>
	</div>
  </div>
</div>

</div>

<script type="text/javascript">
$('#modal-save-newledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "offline.php",
		type: "POST",
		async: true,
		data: $("#new_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newledger').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'offline.php',
		data: {
			'd': 'newLedger',
			'type': '<?php echo $type; ?>'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "offline.php",
		type: "POST",
		async: true,
		data: $("#edit_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'offline.php',
		data: {
			'd': 'editLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newledger, #editledger').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
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

</body>
</html>
