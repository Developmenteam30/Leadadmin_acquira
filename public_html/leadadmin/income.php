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

<?php
	$entries = $leads->getIncomeLedger();
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
			<th>Salesperson</th>
			<th>Payment Method</th>
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
			<td><?php echo $entry->username; ?></td>
			<td><?php echo htmlentities( $entry->checkNum ); ?></td>
		</tr>
<?php
		}
?>
	</tbody>
</table>
<?php
	}
?>

</div>

</body>
</html>
