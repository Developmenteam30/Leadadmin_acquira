<?php

include("../../includes/c_config.php");

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

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
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

			if( $c && is_numeric( $_REQUEST['invoiceAmount'] ) === FALSE ) {
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
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === FALSE ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate'] ) ) {
				try {
					$commissionDate = new DateTime( $_REQUEST['commissionDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount'] ) && is_numeric( $_REQUEST['commissionAmount'] ) === FALSE ) {
				$result['error'] = 'Commission amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount'] ) && floatval( $_REQUEST['commissionAmount'] ) < 0 ) {
				$result['error'] = 'Commission amount cannot be less than zero.';
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
					'commissionDate' => !isset( $commissionDate ) ? null : $commissionDate->format( 'Y-m-d' ),
					'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
					'type' => $type,
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to add entry to the database';
				} else {
					$leads->auditLog( 'LEDGER:ADD', $ledgerId );
				}
			}

			if($c){
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

			if( $c && is_numeric( $_REQUEST['invoiceAmount'] ) === FALSE ) {
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
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === FALSE ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionDate'] ) ) {
				try {
					$commissionDate = new DateTime( $_REQUEST['commissionDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid commission date.';
					$c = false;
				}
			}

			if( $c && !empty( $_REQUEST['commissionAmount'] ) && is_numeric( $_REQUEST['commissionAmount'] ) === FALSE ) {
				$result['error'] = 'Commission amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['commissionAmount'] ) && floatval( $_REQUEST['commissionAmount'] ) < 0 ) {
				$result['error'] = 'Commission amount cannot be less than zero.';
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
					'commissionDate' => !isset( $commissionDate ) ? null : $commissionDate->format( 'Y-m-d' ),
					'commissionAmount' => empty( $_REQUEST['commissionAmount'] ) ? null : $_REQUEST['commissionAmount'],
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to updated ledger entry.';
				} else {
					$leads->auditLog( 'LEDGER:EDIT', $_REQUEST['ledgerId'] );
				}

			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully edited ledger entry.';
			}
		break;

		case "getDivisionCompanies":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionCompanies( $_REQUEST['divisionId'], null, PDO::FETCH_ASSOC ) );
			} else {
				echo json_encode( array( ) );
			}
			exit;
		break;

		case "getDivisionVerticals":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionVerticals( $_REQUEST['divisionId'], PDO::FETCH_ASSOC ) );
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
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a division',
					'choices' => $leads->getDivisions(),
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a company',
				),
				array(
					'id' => 'verticalId',
					'label' => 'Vertical',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vertical',
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
$('#newledger input[name=paymentDate], #newledger input[name=comissionDate]').datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$("#newledger select[name='divisionId']").select2({
	placeholder: "Select a division",
	allowClear: true
});

$("#newledger select[name='companyId']").select2({
	placeholder: "Select a company",
	allowClear: true
});

$("#newledger select[name='verticalId']").select2({
	placeholder: "Select a vertical",
	allowClear: true
});

$("#newledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#newledger select[name='userId']").select2({
	placeholder: "Select a salesperson",
	allowClear: true
});

$("#newledger select[name='divisionId']").change( function() {
	$.ajax({
		type: "post",
		url: "ledger.php",
		data: {
			a: 'getDivisionCompanies',
			divisionId: $("#newledger select[name='divisionId']").val()
		},
		dataType: "json",
		success: function(data) {
			var companyId = $("#newledger select[name='companyId']")
			if( companyId ) {
				companyId.empty();
				companyId.append('<option></option>');
				$.each( data, function(i, obj) {
					companyId.append('<option value="' + obj.idCompany + '">' + obj.name + '</option>');
				});
				companyId.select2({
					placeholder: "Select a company",
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
			divisionId: $("#newledger select[name='divisionId']").val()
		},
		dataType: "json",
		success: function(data) {
			var verticalId = $("#newledger select[name='verticalId']")
			if( verticalId ) {
				verticalId.empty();
				verticalId.append('<option></option>');
				$.each( data, function(i, obj) {
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

				$fields = array(
					array(
						'id' => 'divisionId',
						'label' => 'Division',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a division',
						'choices' => $leads->getDivisions(),
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
$('#editledger input[name=paymentDate], #editledger input[name=commissionDate]').datepicker({
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

$("#editledger select[name='ledgerMonth']").select2({
	placeholder: "Select the ledger month",
	allowClear: true
});

$("#editledger select[name='userId']").select2({
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

$title = 'Ledger Entry';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2><?php echo ( $type == 0 ? 'Publisher' : 'Advertiser' ); ?></h2>

<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newledger">Add a new entry</button></p>
<?php } ?>

<?php
	$entries = $leads->getLedger( $type );
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
			<th>Company Name</th>
			<th>Vertical</th>
			<th>Invoice Amount</th>
			<th>Invoice #</th>
			<th>Date Paid</th>
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
					$commissionTotal += $entry->commissionAmount;

					$ledger = new DateTime( $entry->ledgerMonth );
?>
		<tr>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td><?php echo htmlentities( $entry->verticalName ); ?></td>
			<td>$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->paymentDate; ?></td>
			<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td>$<?php echo number_format( $entry->commissionAmount, 2 ); ?></td>
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
</div></td>
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
				<button id="modal-save-newledger" type="button" class="btn btn-primary">Add Ledger Entry</button>
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

<div class="modal fade" id="deleteledger" tabindex="-1" role="dialog" aria-labelledby="deleteledger_title">
  <div class="modal-dialog" role="document">
	<div class="modal-content">
	  <div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<h4 class="modal-title" id="deleteledger_title">Delete a ledger entry</h4>
	  </div>
	  <div class="modal-body">
	  </div>
	  <div class="modal-footer">
		<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button id="modal-deleteledger" type="button" class="btn btn-primary">Delete</button>
	  </div>
	</div>
  </div>
</div>

</div>

<script type="text/javascript">
$('#modal-save-newledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
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
		url: 'ledger.php',
		data: {
			'd': 'newLedger',
			'type': '<?php echo $type; ?>'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-deleteledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#delete_ledger").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#deleteledger').on('show.bs.modal', function(e) {
	var modal = $(this);
	var ledgerId = $(e.relatedTarget).data('ledger-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'ledger.php',
		data: {
			'd': 'deleteLedger',
			'ledgerId': ledgerId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
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
		url: 'ledger.php',
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

$('.ledger-sort').each(function() {
	var tf = new TableFilter($(this).attr('id'), {
		base_path: '/leadadmin/libraries/tablefilter/',
		grid: false,
		filters_row_index: 1,
		extensions: [{
			name: 'sort',
			types: [
				'String','String','us','String','ymddate','us','String','String','us'
			],
			image_asc_class_name: 'custom-ascending',
			image_desc_class_name: 'custom-descending'
		}],
		sort: true
	});
	tf.init();
});
</script>

</body>
</html>
