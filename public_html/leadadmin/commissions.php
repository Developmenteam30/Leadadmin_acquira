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

			$entries = $leads->getIncomeLedger( $user->idUser );
			if( empty( $entries ) ) {
?>
<p>No ledger entries exist in the database.</p>
<?php
			} else {
?>
<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr class="bgGray header">
			<th>Date</th>
			<th>Division</th>
			<th>Company</th>
			<th>Invoice #</th>
			<th>Payment Amount</th>
			<th>Commission</th>
		</tr>
	</thead>
	<tbody>
<?php
				foreach( $entries as $entry ) {
?>
		<tr>
			<td><?php echo $entry->paymentDate; ?></td>
			<td><?php echo htmlentities( $entry->divisionName ); ?></td>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td class="text-right"><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td class="text-right">$<?php echo number_format( $entry->commissionAmount, 2 ); ?></td>
		</tr>
<?php
				}
?>
	</tbody>
</table>
<?php
			}
		}
	}
?>

</div>

</body>
</html>
