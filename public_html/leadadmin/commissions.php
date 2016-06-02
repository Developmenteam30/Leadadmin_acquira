<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

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

$title = 'Commissions Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<?php
	$users = $leads->getStaffUsers( PDO::FETCH_OBJ );
	if( empty( $users ) ) {

?>
<p>No users exist in the database.</p>
<?php
	} else {

		foreach( $users as $user ) {

			printf( '<h3>%s</h3>' . PHP_EOL, htmlentities( $user->fullName ) );

			$entries = $leads->getPaidLedger( 1, $user->idUser );
			if( empty( $entries ) ) {
?>
<p>No commission entries exist in the database.</p>
<?php
			} else {

				$months = array();
				foreach( $entries as $entry ) {
					$month = substr( $entry->commissionDate, 0, 7 );
					$months[$month] = true;
				}
				ksort( $months );

				foreach( $months as $month => $val ) {

?>
<h4><?php echo date( 'F Y', strtotime( $month . '-01' ) ); ?></h4>
<table class="table table-bordered table-condensed table-striped ledger-sort" id="ledger_<?php echo $entry->idUser; ?>_<?php echo $month ?>">
	<thead>
		<tr class="bgGray header">
			<th>Division</th>
			<th>Company</th>
			<th>Invoice #</th>
			<th>Payment Amount</th>
			<th>Commission</th>
		</tr>
	</thead>
	<tbody>
<?php
					$paymentTotal = 0;
					$commissionTotal = 0;
					foreach( $entries as $entry ) {
						if( substr( $entry->commissionDate, 0, 7 ) == $month ) {
							$paymentTotal += $entry->paymentAmount;
							$commissionTotal += $entry->commissionAmount;
?>
		<tr>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td>$<?php echo number_format( $entry->commissionAmount, 2 ); ?></td>
		</tr>
<?php
						}
					}
?>
	</tbody>
	<tfoot>
		<tr class="bgGray header">
			<td colspan="3">Totals</td>
			<td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
			<td>$<?php echo number_format( $commissionTotal, 2 ); ?></td>
		</tr>
	</tfoot>
</table>
<?php
				}
			}
		}
	}
?>

</div>

<script type="text/javascript">
$('.ledger-sort').each(function( index ) {
	var tf = new TableFilter($(this).attr('id'), {
		base_path: '/leadadmin/libraries/tablefilter/',
		grid: false,
		filters_row_index: 1,
		extensions: [{
			name: 'sort',
			types: [
				'String', 'String', 'String', 'us', 'us'
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
