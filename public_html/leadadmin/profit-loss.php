<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$v = !empty( $_REQUEST['v'] ) ? $_REQUEST['v'] : 'p';

require_once( INCLUDES . 'display.php' );

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

$title = 'Profit & Loss Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Profit &amp; Loss Report</h2>
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
				$monthIn = $year;
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

		print '<h3>Income</h3>' . PHP_EOL;

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
			$divisions = array();
			foreach( $entries as $entry ) {
				$month = substr( $entry->paymentDate, 0, 7 );
				if( isset( $months[$month][$entry->divisionId] ) ) {
					$months[$month][$entry->divisionId] += $entry->paymentAmount;
				} else {
					$months[$month][$entry->divisionId] = $entry->paymentAmount;
				}
				$divisions[$entry->divisionId] = $entry->divisionName;
			}
			asort( $divisions );
?>
<table class="table table-bordered table-condensed table-striped" id="<?php echo $entry->idUser; ?>_<?php echo $month ?>">
	<thead>
		<tr class="bgGray header">
			<th>Month</th>
<?php
			foreach( $divisions as $d_key => $d_val ) {
				printf( "\t\t\t<th>%s</th>\n",
					htmlentities( $d_val )
				);
			}
?>
			<th>TOTAL</th>
		</tr>
	</thead>
	<tbody>

<?php
			$grandTotal = 0.00;
			$divisionTotal = array();
			foreach( $divisions as $d_key => $d_val ) {
				$divisionTotal[$d_key] = 0.00;
			}
			foreach( $months as $key => $val ) {
				print "\t\t<tr>\n";
				printf( "\t\t\t<td>%s</td>\n",
					date( 'F Y', strtotime( $key . '-01' ) )
				);
				$total = 0.00;
				foreach( $divisions as $d_key => $d_val ) {
					$thisAmount = isset( $val[$d_key] ) ? $val[$d_key] : 0.00;
					printf( "<td class=\"text-right\">$%s</td>\n",
						number_format( $thisAmount, 2 )
					);
					$total += $thisAmount;
					$grandTotal += $thisAmount;
					$divisionTotal[$d_key] += $thisAmount;
				}
				printf( "\t\t\t<td class=\"text-right\"><strong>$%s</strong></td>\n",
					number_format( $total, 2 )
				);
				print "\t\t</tr>\n";
			}
?>
	<tfoot>
		<td>GRAND TOTALS</td>
<?php
			foreach( $divisions as $d_key => $d_val ) {
				printf( "\t\t\t<td class=\"text-right\">$%s</td>\n",
					number_format( $divisionTotal[$d_key], 2 )
				);
			}
?>
		<td class="text-right">$<?php echo number_format( $grandTotal, 2 ); ?></td>
	</tfoot>
	</tbody>
</table>
<?php
		}
?>

<h3>Expenses</h3>

<?php
		if( strlen( $monthSelected ) == 4 ) {
			$entries = $leads->getPaidLedger( 0, null, 'LEFT(paymentDate,4)', $monthSelected );
		} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $monthSelected, $matches ) ) {
			$entries = $leads->getPaidLedger( 0, null, 'CONCAT(LEFT(paymentDate,4),QUARTER(paymentDate))', $matches[1] . $matches[2] );
		} else {
			$entries = $leads->getPaidLedger( 0, null, 'LEFT(paymentDate,7)', $monthSelected );
		}

		if( empty( $entries ) ) {
?>
<p>No ledger entries exist in the database.</p>
<?php
		} else {

			$months = array();
			$divisions = array();
			foreach( $entries as $entry ) {
				$month = substr( $entry->paymentDate, 0, 7 );
				if( isset( $months[$month][$entry->divisionId] ) ) {
					$months[$month][$entry->divisionId] += $entry->paymentAmount;
				} else {
					$months[$month][$entry->divisionId] = $entry->paymentAmount;
				}
				$divisions[$entry->divisionId] = $entry->divisionName;
			}
			asort( $divisions );
?>
<table class="table table-bordered table-condensed table-striped" id="<?php echo $entry->idUser; ?>_<?php echo $month ?>">
	<thead>
		<tr class="bgGray header">
			<th>Month</th>
<?php
			foreach( $divisions as $d_key => $d_val ) {
				printf( "\t\t\t<th>%s</th>\n",
					htmlentities( $d_val )
				);
			}
?>
			<th>TOTAL</th>
		</tr>
	</thead>
	<tbody>

<?php
			$grandTotal = 0.00;
			$divisionTotal = array();
			foreach( $divisions as $d_key => $d_val ) {
				$divisionTotal[$d_key] = 0.00;
			}
			foreach( $months as $key => $val ) {
				print "\t\t<tr>\n";
				printf( "\t\t\t<td>%s</td>\n",
					date( 'F Y', strtotime( $key . '-01' ) )
				);
				$total = 0.00;
				foreach( $divisions as $d_key => $d_val ) {
					$thisAmount = isset( $val[$d_key] ) ? $val[$d_key] : 0.00;
					printf( "<td class=\"text-right\">$%s</td>\n",
						number_format( $thisAmount, 2 )
					);
					$total += $thisAmount;
					$grandTotal += $thisAmount;
					$divisionTotal[$d_key] += $thisAmount;
				}
				printf( "\t\t\t<td class=\"text-right\"><strong>$%s</strong></td>\n",
					number_format( $total, 2 )
				);
				print "\t\t</tr>\n";
			}
?>
	<tfoot>
		<td>GRAND TOTALS</td>
<?php
			foreach( $divisions as $d_key => $d_val ) {
				printf( "\t\t\t<td class=\"text-right\">$%s</td>\n",
					number_format( $divisionTotal[$d_key], 2 )
				);
			}
?>
		<td class="text-right">$<?php echo number_format( $grandTotal, 2 ); ?></td>
	</tfoot>
	</tbody>
</table>
<?php
		}
	}
?>

</div>

<script type="text/javascript">
$('.form-inline select').change(function() {
	$('.form-inline').submit();
});
</script>
</body>
</html>
