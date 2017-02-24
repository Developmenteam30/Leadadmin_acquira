<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$type = 0;

if(isset($_REQUEST['d'])){
	switch($_REQUEST['d']){
		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;
	}
	exit;
}

$title = 'Income Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Income Ledger</h2>

<?php
	$monthIn = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : null;
	$monthSelected = null;
	$months = $leads->getPaidLedger( 1, null, 'LEFT(paymentDate,7)' );

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

		if( strlen( $monthSelected ) == 4 ) {
			$entries = $leads->getPaidLedger( 1, null, 'LEFT(paymentDate,4)', $monthSelected );
		} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $monthSelected, $matches ) ) {
			$entries = $leads->getPaidLedger( 1, null, 'CONCAT(LEFT(paymentDate,4),QUARTER(paymentDate))', $matches[1] . $matches[2] );
		} else {
			$entries = $leads->getPaidLedger( 1, null, 'LEFT(paymentDate,7)', $monthSelected );
		}

		if( empty( $entries ) ) {
?>
<p>No ledger entries exist in the database.</p>
<?php
		} else {
			$months = array();
			foreach( $entries as $entry ) {
				$month = substr( $entry->paymentDate, 0, 7 );
				$months[$month] = true;
			}
			ksort( $months );

			foreach( $months as $month => $val ) {
?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped" id="income_ledger_<?php echo $date; ?>">
	<thead>
		<tr class="bgGray header">
			<th>Entry #</th>
			<th>Payment Date</th>
			<th>Division</th>
			<th>Company</th>
			<th>Invoice #</th>
			<th>Salesperson</th>
			<th>Commissions</th>
			<th>Commission Date</th>
			<th>Payment Method</th>
			<th>Payment Amount</th>
			<th>Options</th>
		</tr>
	</thead>
	<tbody>
<?php
				$paymentTotal = 0;
				foreach( $entries as $entry ) {
					if( substr( $entry->paymentDate, 0, 7 ) == $month ) {
						$paymentTotal += $entry->paymentAmount;
?>
		<tr>
			<td><?php echo htmlentities( $entry->entryId ); ?></td>
			<td><?php echo htmlentities( $entry->paymentDate ); ?></td>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td><?php echo !empty( $entry->commissionAmount ) ? '$' . number_format( $entry->commissionAmount, 2 ) : ''; ?><?php if( !empty( $entry->commissionDate ) && !empty( $entry->commissionAmount ) ) { echo ' <img alt="Green checkmark" height="13" src="images/green_check.png" width="12" />'; } ?></td>
			<td><?php echo htmlentities( $entry->commissionDate ); ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td class="text-center"><?php if( '4' === $entry->divisionId ) { ?>
<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editofflineledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button>
<?php } else if( '5' === $entry->divisionId ) { ?>
<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editphoneledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button> 
<?php } else if( 'email' === $entry->source ) { print "&nbsp;"; ?>
<?php } else { ?>
<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button></td>
<?php } ?>
		</tr>
<?php
					}
				}
?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="9">Monthly Total</td>
			<td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
			<td>&nbsp;</td>
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
				'String',
				'date',
				'String',
				'String',
				'String',
				'String',
				'formatted-number',
				'date',
				'String',
				'formatted-number'
			],
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
