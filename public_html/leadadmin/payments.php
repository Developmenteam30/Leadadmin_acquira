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

			if( $c && empty( $_REQUEST['invoiceNum'] ) ) {
				$result['error'] = 'Invoice number cannot be blank.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['ledgerMonth'] ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				$c = false;
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

					$BCCText = '';
					$BCCText = "BCC: " . PAYMENT_EMAIL . "\r\n";
					if( !empty( $_REQUEST['commissionBCC'] ) ) {
						$BCCs = explode( ',', $_REQUEST['commissionBCC'] );
						foreach( $BCCs as $BCC ) {
							$user = $leads->getUser( $BCC );
							if( !empty( $user->email ) ) {
								$BCCText .= "BCC: {$user->email}\r\n";
							}
						}
					}

					$date = date( 'F Y', strtotime( $_REQUEST['ledgerMonth'] . '01' ) );
					list( $first, $garbage ) = explode( ' ', $company->acct_name, 2 );

					$message  = "Hi, {$first}.\r\n";
					$message .= "\r\n";
					if( strpos( $_REQUEST['invoiceNum'], ',' ) ) {
						$message .= "The invoices below have been paid.\r\n";
					} else {
						$message .= "The invoice below has been paid.\r\n";
					}
					$message .= "\r\n";
					$message .= "Month: {$date}\r\n";
					if( strpos( $_REQUEST['invoiceNum'], ',' ) ) {
						$message .= "Invoice Numbers: " . $_REQUEST['invoiceNum'] . "\r\n";
					} else {
						$message .= "Invoice Number: " . $_REQUEST['invoiceNum'] . "\r\n";
					}
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

					if( !mail( $company->acct_email, "Invoice #" . $_REQUEST['invoiceNum'] . " PAID | " . CONFIG_COMPANY_NAME, $message, "From: \"" . CONFIG_COMPANY_NAME . "\" <" . PAYMENT_EMAIL . ">\r\n" . $BCCText, '-f' . PAYMENT_EMAIL ) ) {
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

			$entries = array();

			if( !empty( $_REQUEST['emailLedgerId'] ) ) {
				foreach( $_REQUEST['emailLedgerId'] as $divisionLedgerId ) {
					list( $divisionId, $ledgerId ) = explode( '|', $divisionLedgerId, 2 );
					if( 'E' === $divisionId ) {
						list( $reportDate, $companyId ) = explode( '|', $ledgerId );
						$entry = $leads->getInvoiceDetails( $reportDate, $companyId );
						if( !empty( $entry ) ) {
							$ledgerMonth = new DateTime( $entry->paymentDate );
							$revenue = $leads->getRevenueInboundClientMonthTotal( $entry->idCompany, $entry->date );
							$entries[] = array(
								'invoiceNum' => $entry->invoiceNumber,
								'paymentAmount' => isset( $revenue[0]['partner'] ) ? $revenue[0]['partner'] : 0.00,
								'paymentMethod' => 'ACH',
								'ledgerMonth' => $ledgerMonth->format( 'Ym' ),
								'companyId' => $entry->idCompany,
								'userId' => $entry->userId,
							);
						}
					} else if( '4' === $divisionId ) {
						$entry = $leads->getOfflineLedgerById( $ledgerId );
						if( !empty( $entry ) ) {
							$ledgerMonth = new DateTime( $entry->ledgerMonth );
							$entries[] = array(
								'invoiceNum' => $entry->loInvoiceNum,
								'paymentAmount' => $entry->loPaymentAmount,
								'paymentMethod' => $entry->loPaymentMethod,
								'ledgerMonth' => $ledgerMonth->format( 'Ym' ),
								'companyId' => $entry->vendorCompanyId,
								'userId' => $entry->userId,
							);
						}
					} else {
						$entry = $leads->getLedgerById( $ledgerId );
						if( !empty( $entry ) ) {
							$ledgerMonth = new DateTime( $entry->ledgerMonth );
							$entries[] = array(
								'invoiceNum' => $entry->invoiceNum,
								'paymentAmount' => $entry->paymentAmount,
								'paymentMethod' => $entry->paymentMethod,
								'ledgerMonth' => $ledgerMonth->format( 'Ym' ),
								'companyId' => $entry->companyId,
								'userId' => $entry->userId,
							);
						}
					}
				}
			}

			if( empty( $entries ) ) {
				print '<div class="alert alert-danger" role="alert">No entries were selected.</div>' . PHP_EOL;
				break;
			}

			// Ensure there is no mixing of companies
			$companyId = null;
			$mixedCompanies = false;
			$invoiceNumbers = '';
			$paymentMethod = '';
			$ledgerMonth = '';
			$paymentAmount = 0.00;
			$commissionBCCs = array();
			foreach( $entries as $entry ) {
				if( empty( $companyId ) ) {
					$companyId = $entry['companyId'];
				}
				if( $entry['companyId'] != $companyId ) {
					$mixedCompanies = true;
				}

				if( !empty( $invoiceNumbers ) ) {
					$invoiceNumbers .= ', ';
				}
				$invoiceNumbers .= $entry['invoiceNum'];

				if( empty( $ledgerMonth ) ) {
					$ledgerMonth = $entry['ledgerMonth'];
				}

				if( empty( $paymentMethod ) ) {
					$paymentMethod = $entry['paymentMethod'];
				}

				$paymentAmount += floatval( $entry['paymentAmount'] );

				$commissionBCCs[$entry['userId']] = true;
			}

			if( $mixedCompanies ) {
				print '<div class="alert alert-danger" role="alert">You cannot send an email to different companies at the same time. Please only select payments that all belong to the same company.</div>' . PHP_EOL;
				break;
			}

			$company = $leads->getCompany( $companyId );

			$commissionBCCList = '';
			foreach( $commissionBCCs as $commissionBCC => $val ) {
				if( !empty( $commissionBCCList ) ) {
					$commissionBCCList .= ',';
				}
				$commissionBCCList .= $commissionBCC;
			}

			$fields = array(
				array(
					'id' => 'companyId_text',
					'label' => 'Company',
					'type' => '_text',
					'value' => $company->name,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'sendEmail',
				),
				array(
					'id' => 'commissionBCC',
					'type' => 'hidden',
					'value' => $commissionBCCList,
				),
				array(
					'id' => 'companyId',
					'type' => 'hidden',
					'value' => $companyId,
				),
				array(
					'id' => 'invoiceNum',
					'label' => 'Invoice Number(s)',
					'type' => 'text',
					'required' => true,
					'value' => $invoiceNumbers,
				),
				array(
					'id' => 'ledgerMonth',
					'label' => 'Ledger Month',
					'type' => 'select',
					'choices' => $ledgerMonths,
					'value' => $ledgerMonth,
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
					'value' => !empty( $paymentAmount ) ? number_format( $paymentAmount, 2, '.', '' ) : '',
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
	$monthIn = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : null;
	$monthSelected = null;
	$months = $leads->getPaidLedger( 0, null, 'LEFT(ledgerMonth,7)' );

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

		print '<p>Please select a valid report period above.</p>' . PHP_EOL;

	} else {

		if( strlen( $monthSelected ) == 4 ) {
			$entries = $leads->getPaidLedger( 0, null, 'LEFT(ledgerMonth,4)', $monthSelected );
		} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $monthSelected, $matches ) ) {
			$entries = $leads->getPaidLedger( 0, null, 'CONCAT(LEFT(ledgerMonth,4),QUARTER(ledgerMonth))', $matches[1] . $matches[2] );
		} else {
			$entries = $leads->getPaidLedger( 0, null, 'LEFT(ledgerMonth,7)', $monthSelected );
		}

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
			ksort( $months );

			foreach( $months as $month => $val ) {
?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped" id="payment_ledger_<?php echo $month; ?>">
	<thead>
		<tr class="bgGray header">
			<th>Entry #</th>
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
			<td><?php echo htmlentities( $entry->entryId ); ?></td>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td class="text-center"><?php if( 'email' === $entry->source ) { ?><input class="email-payment" type="checkbox" name="emailLedgerId[]" value="<?php echo 'E|' . $entry->ledgerId . '|' . $entry->companyId; ?>" /><?php } else { ?><input class="email-payment" type="checkbox" name="emailLedgerId[]" value="<?php echo $entry->divisionId . '|' . $entry->ledgerId; ?>" /><?php } ?></td>
		</tr>
<?php
					}
				}
?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="7">Monthly Total</td>
			<td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
			<td class="text-center"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#sendEmail">Send Email</button></td>
		</tr>
	</tfoot>
</table>
<?php
			}
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
$('.form-inline select').change(function() {
	$('.form-inline').submit();
});
$( "table" ).each(function( index ) {
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
		extensions: [{
			name: 'sort',
			types: [
				'string',
				'string',
				'string',
				'string',
				'string',
				'date',
				'string',
				'formatted-number',
				'none'
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
