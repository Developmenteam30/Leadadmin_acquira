<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

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

$title = 'Income Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Income Ledger</h2>

<?php
	$entries = $leads->getPaidLedger( 1 );
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

		foreach( $months as $month => $val ) {
?>
<table class="table table-bordered table-condensed table-striped" id="<?php echo $entry->idUser; ?>_<?php echo $month ?>">
	<thead>
		<tr class="bgGray header">
			<th>Date</th>
			<th>Division</th>
			<th>Company</th>
			<th>Invoice #</th>
			<th>Salesperson</th>
			<th>Payment Method</th>
			<th>Payment Amount</th>
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
			<td><?php echo $entry->paymentDate; ?></td>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td class="text-right"><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->fullName; ?></td>
			<td><?php echo htmlentities( $entry->paymentMethod ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
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
		</tr>
	</tfoot>
</table>
<?php
		}
	}
?>

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
</script>

</body>
</html>
