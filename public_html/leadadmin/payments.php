<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

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
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch( $_REQUEST['a'] ) {

		case "sendEmail":
			$c = true;
			$result['error'] = 'Failed when trying to send a payment email.';

			if( $c && empty( $_REQUEST['divisionId'] ) ) {
				$result['error'] = 'Please select a division from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
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

			if( $c && empty( $_REQUEST['paymentDate'] ) ) {
				$result['error'] = 'Payment date cannot be blank.';
				$c = false;
			} else if( $c && !empty( $_REQUEST['paymentDate'] ) ) {
				try {
					$paymentDate = new DateTime( $_REQUEST['paymentDate'] );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid payment date.';
					$c = false;
				}
			}

			if( $c && empty( $_REQUEST['paymentMethod'] ) ) {
				$result['error'] = 'Payment method cannot be blank.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && is_numeric( $_REQUEST['paymentAmount'] ) === FALSE ) {
				$result['error'] = 'Payment amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['paymentAmount'] ) && floatval( $_REQUEST['paymentAmount'] ) < 0 ) {
				$result['error'] = 'Payment amount cannot be less than zero.';
				$c = false;
			}

			if( $c ) {

				try {
					$ledgerMonth = new DateTime( $_REQUEST['ledgerMonth'] . '01' );
				} catch ( Exception $e ) {
					$result['error'] = 'Please enter a valid ledger month.';
					$c = false;
				}

				$company = $leads->getCompany( $_REQUEST['companyId'] );
				if( $c && empty( $company ) ) {
					$result['error'] = 'That company was not found in the database.';
					$c = false;
				}

				if( $c && empty( $company->acct_email ) ) {
					$result['error'] = 'Accounting contact email is not setup. No notification sent.';
					$c = false;
				}

				if( $c && empty( $company->acct_name ) ) {
					$result['error'] = 'Accounting contact name is not setup. No notification sent.';
					$c = false;
				}

				if( $c ) {

					$date = date( 'F Y', strtotime( $_REQUEST['ledgerMonth'] . '01' ) );
					list( $first, $garbage ) = explode( ' ', $company->acct_name, 2 );

					$message  = "Hi {$first},\r\n";
					$message .= "\r\n";
					$message .= "The invoice below has been paid.\r\n";
					$message .= "\r\n";
					$message .= "Month: {$date}\r\n";
					$message .= "Invoice #: " . $_REQUEST['invoiceNum'] . "\r\n";
					$message .= "Amount: \$" . number_format( $_REQUEST['paymentAmount'], 2 ) . "\r\n";
					$message .= "Payment Method: " . $_REQUEST['paymentMethod'] . "\r\n";
					$message .= "\r\n";
					$message .= "\r\n";
					$message .= "Thank you and we appreciate your business.\r\n";
					$message .= "\r\n";
					$message .= "Warmly,\r\n";
					$message .= "\r\n";
					$message .= "Accounting\r\n";
					$message .= COMPANY_LEGAL_NAME . "\r\n";
					$message .= COMPANY_ADDRESS_1 . "\r\n";
					$message .= COMPANY_ADDRESS_2 . "\r\n";

					if( !mail( $company->acct_email, "Invoice #" . $_REQUEST['invoiceNumber'] . " PAID | " . CONFIG_COMPANY_NAME, $message, "From: \"" . CONFIG_COMPANY_NAME . "\" <" . PAYMENT_EMAIL . ">\r\nBCC: " . PAYMENT_EMAIL, '-f' . PAYMENT_EMAIL ) ) {
						$result['error'] = 'Unable to send message.';
						$c = false;
					}
				}

			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully sent payment email.';
			}
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

		case 'dialog_email':

			$divisionId = '';
			$companyId = '';
			$invoiceNum = '';
			$invoiceAmount = 0.00;
			$ledgerMonth = '';
			$paymentDate = '';
			$paymentMethod = '';
			$paymentAmount = 0.00;

			if( !empty( $_REQUEST['emailLedgerId'] ) ) {
				foreach( $_REQUEST['emailLedgerId'] as $ledgerId ) {
					$entry = $leads->getLedgerById( $ledgerId );
					if( !empty( $entry ) ) {
						$divisionId = $entry->divisionId;
						$companyId = $entry->companyId;
						$invoiceNum = $entry->invoiceNum;
						$invoiceAmount += $entry->invoiceAmount;
						$ledgerMonth = new DateTime( $entry->ledgerMonth );
						$ledgerMonth = $ledgerMonth->format( 'Ym' );
						$paymentDate = $entry->paymentDate;
						$paymentMethod = $entry->paymentMethod;
						$paymentAmount += $entry->paymentAmount;
					}
				}
			}

			$fields = array(
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a division',
					'choices' => $leads->getDivisions(),
					'value' => $divisionId,
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a company',
					'choices' => !empty( $divisionId ) ? $leads->getDivisionCompanies( $divisionId ) : array(),
					'value' => $companyId,
				),
				array(
					'id' => 'invoiceNum',
					'label' => 'Client Invoice #',
					'type' => 'text',
					'required' => true,
					'value' => $invoiceNum,
				),
				array(
					'id' => 'invoiceAmount',
					'label' => 'Invoice Amount',
					'type' => 'currency',
					'required' => true,
					'value' => !empty( $invoiceAmount ) ? $invoiceAmount : '',
				),
				array(
					'id' => 'ledgerMonth',
					'label' => 'Ledger Month',
					'type' => 'select',
					'choices' => $ledgerMonths,
					'value' => $ledgerMonth,
				),
				array(
					'id' => 'paymentDate',
					'label' => 'Date Paid',
					'type' => 'text',
					'value' => $paymentDate,
				),
				array(
					'id' => 'paymentMethod',
					'label' => 'Payment Method',
					'type' => 'text',
					'value' => $paymentMethod,
				),
				array(
					'id' => 'paymentAmount',
					'label' => 'Payment Amount',
					'type' => 'currency',
					'required' => true,
					'value' => !empty( $paymentAmount ) ? $paymentAmount : '',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'sendEmail',
				),
			);

			Display::displayForm( 'email_form', $fields );

?>

<script type="text/javascript">
$('#email_form input[name=paymentDate]').datepicker({
		// Consistent format with the HTML5 picker
		dateFormat: 'yy-mm-dd'
});

$("#email_form select[name='divisionId']").select2({
		placeholder: "Select a division",
		allowClear: true
});

$("#email_form select[name='companyId']").select2({
		placeholder: "Select a company",
		allowClear: true
});

$("#email_form select[name='divisionId']").change( function() {
	$.ajax({
		type: "post",
		url: "ledger.php",
		data: {
			a: 'getDivisionCompanies',
			divisionId: $("#email_form select[name='divisionId']").val()
		},
		dataType: "json",
		success: function(data) {
			var companyId = $("#email_form select[name='companyId']")
			if( companyId ) {
				companyId.empty();
				companyId.append('<option></option>');
				$.each( data, function(i, obj) {
					companyId.append('<option value="' + obj.companyId + '">' + obj.name + '</option>');
				});
				companyId.select2({
					placeholder: "Select a company",
					allowClear: true
				});
			}
		}
	}); //close $.ajax()
});

</script>

<?php
		break;
	}
	exit;
}

$title = 'Payments Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<button type="button" class="btn btn-primary pull-right" data-toggle="modal" data-target="#sendEmail">Send Email</button>
<h2>Payment Ledger</h2>
<input class="email-payment" type="hidden" name="d" value="dialog_email" />

<?php
	$entries = $leads->getPaidLedger( 0 );
	if( empty( $entries ) ) {
?>
<p>No ledger entries exist in the database.</p>
<?php
	} else {
		$months = array();
		foreach( $entries as $entry ) {
			$month = substr( $entry->ledgerMonth, 0, 7 );
			$months[$month] = true;
		}

		foreach( $months as $month => $val ) {
?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped" id="<?php echo $entry->idUser; ?>_<?php echo $month ?>">
	<thead>
		<tr class="bgGray header">
			<th>Division</th>
			<th>Company</th>
			<th>Invoice #</th>
			<th>Salesperson</th>
			<th>Payment Date</th>
			<th>Payment Method</th>
			<th>Payment Amount</th>
			<th>Email</th>
		</tr>
	</thead>
	<tbody>
<?php
			$paymentTotal = 0;
			foreach( $entries as $entry ) {
				if( substr( $entry->ledgerMonth, 0, 7 ) == $month ) {
					$paymentTotal += $entry->paymentAmount;
?>
		<tr>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td class="text-right"><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td class="text-center"><input class="email-payment" type="checkbox" name="emailLedgerId[]" value="<?php echo $entry->ledgerId; ?>" /></td>
		</tr>
<?php
				}
			}
?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="6">Monthly Total</td>
			<td class="text-right">$<?php echo number_format( $paymentTotal, 2 ); ?></td>
			<td>&nbsp;</td>
		</tr>
	</tfoot>
</table>
<?php
		}
	}
?>

</div>

<div class="modal fade" id="sendEmail" tabindex="-1" role="dialog" aria-labelledby="sendEmail_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="sendEmail_title">Send a payment email</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-send-email" type="button" class="btn btn-primary">Send Email</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$( "table" ).each(function( index ) {
	var tf = new TableFilter($(this).attr('id'), {
		base_path: '/leadadmin/libraries/tablefilter/',
		grid: false,
		filters_row_index: 1,
		extensions: [{
			name: 'sort',
			types: [
				'ymddate', 'String', 'String', 'String', 'String', 'String', 'us'
			],
			image_asc_class_name: 'custom-ascending',
			image_desc_class_name: 'custom-descending'
		}],
		sort: true
	});
	tf.init();
});

$('#modal-send-email').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "payments.php",
		type: "POST",
		async: true,
		data: $("#email_form").serialize()
	}).done(function(result){
		alert(result.error);
		if(result.status == 1){
			window.location.reload(true);
		}
	});
});

$('#sendEmail').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'payments.php',
		data: $(".email-payment").serialize(),
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});
</script>

</body>
</html>
