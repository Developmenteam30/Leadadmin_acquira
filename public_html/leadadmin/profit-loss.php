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

<h3>Income</h3>

<?php
	$entries = $leads->getPaidLedger( 1 );

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
	$entries = $leads->getPaidLedger( 0 );

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

</div>

</body>
</html>
