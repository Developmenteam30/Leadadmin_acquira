<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$divisionId = !empty( $_REQUEST['divisionId'] ) ? $_REQUEST['divisionId'] : '';
$type = !empty( $_REQUEST['type'] ) ? 1 : 0;

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addLedger":
			$c = true;
			$result['error'] = 'Failed when trying to add a new ledger entry.';

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

			if( $c && empty( trim( $_REQUEST['invoiceNum'] ) ) ) {
				$result['error'] = 'Invoice number cannot be blank.';
				$c = false;
			}

			if( $c && ( empty( $_REQUEST['invoiceAmount'] ) || $_REQUEST['invoiceAmount'] <= 0.00 ) ) {
				$result['error'] = 'Invoice amount cannot be blank.';
				$c = false;
			}

			if( $c && empty( trim( $_REQUEST['ledgerMonth'] ) ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				$c = false;
			}

			if( $c ) {

				$ledgerId = $leads->addLedger( array(
					'companyId' => empty( $_REQUEST['companyId'] ) ? null : $_REQUEST['companyId'],
					'divisionId' => $divisionId,
					'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
					'paymentDate' => empty( $_REQUEST['paymentDate'] ) ? null : $_REQUEST['paymentDate'],
					'ledgerMonth' => empty( $_REQUEST['ledgerMonth'] ) ? null : $_REQUEST['ledgerMonth'],
					'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? 0.00 : $_REQUEST['invoiceAmount'],
					'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
					'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
					'checkNum' => empty( $_REQUEST['checkNum'] ) ? null : $_REQUEST['checkNum'],
					'type' => $type,
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to add entry to the database';
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully added a new ledger entry.';
			}
		break;

		case "editLedger":
			$c = true;
			$result['error'] = 'Failed when trying to edit a ledger entry.';

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['verticalId'] ) ) {
				$result['error'] = 'Please select a vertical from the list.';
				$c = false;
			}

			if( $c && empty( trim( $_REQUEST['invoiceNum'] ) ) ) {
				$result['error'] = 'Invoice number cannot be blank.';
				$c = false;
			}

			if( $c && ( empty( $_REQUEST['invoiceAmount'] ) || $_REQUEST['invoiceAmount'] <= 0.00 ) ) {
				$result['error'] = 'Invoice amount cannot be blank.';
				$c = false;
			}

			if( $c && empty( trim( $_REQUEST['ledgerMonth'] ) ) ) {
				$result['error'] = 'Ledger month cannot be blank.';
				$c = false;
			}

			if( $c ) {

				$ledgerId = $leads->updateLedger( $_REQUEST['ledgerId'], array(
					'companyId' => empty( $_REQUEST['companyId'] ) ? null : $_REQUEST['companyId'],
					'verticalId' => empty( $_REQUEST['verticalId'] ) ? null : $_REQUEST['verticalId'],
					'paymentDate' => empty( $_REQUEST['paymentDate'] ) ? null : $_REQUEST['paymentDate'],
					'ledgerMonth' => empty( $_REQUEST['ledgerMonth'] ) ? null : $_REQUEST['ledgerMonth'],
					'invoiceAmount' => empty( $_REQUEST['invoiceAmount'] ) ? 0.00 : $_REQUEST['invoiceAmount'],
					'invoiceNum' => empty( $_REQUEST['invoiceNum'] ) ? null : $_REQUEST['invoiceNum'],
					'paymentAmount' => empty( $_REQUEST['paymentAmount'] ) ? null : $_REQUEST['paymentAmount'],
					'checkNum' => empty( $_REQUEST['checkNum'] ) ? null : $_REQUEST['checkNum'],
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
				) );

				if( null === $ledgerId ) {
					$c = false;
					$result['error'] = 'Unable to updated ledger entry.';
				}

			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully edited ledger entry.';
			}
		break;

		case "getDivisionCompanies":
			if( !empty( $_REQUEST['divisionId'] ) ) {
				echo json_encode( $leads->getDivisionCompanies( $_REQUEST['divisionId'], PDO::FETCH_ASSOC ) );
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
					'value' => $divisionId,
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a company',
					'choices' =>  $leads->getDivisionCompanies( $divisionId ),
				),
				array(
					'id' => 'verticalId',
					'label' => 'Vertical',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a vertical',
					'choices' =>  $leads->getDivisionVerticals( $divisionId ),
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
					'type' => 'date',
				),
				array(
					'id' => 'paymentDate',
					'label' => 'Date Paid',
					'type' => 'date',
				),
				array(
					'id' => 'checkNum',
					'label' => ( 0 == $type ) ? 'Client Check #' : 'Payment Method',
					'type' => ( 0 == $type ) ? 'number' : 'text',
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
					'choices' =>  $leads->getStaffUsers(),
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
$('#newledger input[name=paymentDate]').datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});

$('#newledger input[name=ledgerMonth]').datepicker({
	dateFormat: 'yy-mm-dd',
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
					companyId.append('<option value="' + obj.companyId + '">' + obj.name + '</option>');
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

		case "editLedger":
			$ledgerId = !empty( $_REQUEST['ledgerId'] ) ? $_REQUEST['ledgerId'] : '';
			$entry = $leads->getLedgerById( $ledgerId );

			if( empty( $entry ) ) {

				print '<p>There is no ledger that exists by that ID.</p>';

			} else {

				$fields = array(
					array(
						'id' => 'companyId',
						'label' => 'Company',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a company',
						'choices' =>  $leads->getDivisionCompanies( $entry->divisionId ),
						'value' => $entry->companyId,
					),
					array(
						'id' => 'verticalId',
						'label' => 'Vertical',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a vertical',
						'choices' =>  $leads->getDivisionVerticals( $entry->divisionId ),
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
						'type' => 'date',
						'required' => true,
						'value' => $entry->ledgerMonth,
					),
					array(
						'id' => 'paymentDate',
						'label' => 'Date Paid',
						'type' => 'date',
						'value' => $entry->paymentDate,
					),
					array(
						'id' => 'checkNum',
						'label' => ( 0 == $entry->type ) ? 'Client Check #' : 'Payment Method',
						'type' => ( 0 == $type ) ? 'number' : 'text',
						'value' => $entry->checkNum,
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
						'choices' =>  $leads->getStaffUsers(),
						'value' => $entry->userId,
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
$('#editledger input[name=paymentDate]').datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
});
$('#editledger input[name=ledgerMonth]').datepicker({
	// Consistent format with the HTML5 picker
	dateFormat: 'yy-mm-dd'
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

<h2><?php echo ( $type == 0 ? 'Publisher' : 'Advertiser' ) . ' - ' . $leads->getDivisionName( $divisionId ); ?></h2>

<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newledger">Add a new entry</button>

<?php
	$entries = $leads->getLedger( $divisionId, $type );
	if( empty( $entries ) ) {

		print '<p>No ledger entries exist in the database.</p>';

	} else {
?>
<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr class="header">
			<th>Company Name</th>
			<th>Vertical</th>
			<th>Month</th>
			<th>Invoice Amount</th>
			<th>Invoice #</th>
			<th>Date Paid</th>
			<th>Payment Amount</th>
			<th>Method</th>
			<th>Salesperson</th>
			<th>Options</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach( $entries as $entry ) {
?>
		<tr>
			<td><?php echo htmlentities( $entry->companyName ); ?></td>
			<td><?php echo htmlentities( $entry->verticalName ); ?></td>
			<td><?php echo $entry->ledgerMonth; ?></td>
			<td class="text-right">$<?php echo number_format( $entry->invoiceAmount, 2 ); ?></td>
			<td class="text-right"><?php echo htmlentities( $entry->invoiceNum ); ?></td>
			<td><?php echo $entry->paymentDate; ?></td>
			<td class="text-right">$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
			<td><?php echo htmlentities( $entry->checkNum ); ?></td>
			<td><?php echo $entry->username; ?></td>
			<td class="text-center"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editledger" data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit</button></td>
		</tr>
<?php
		}
?>
	</tbody>
</table>
<?php
	}
?>

<div class="modal fade" id="newledger" tabindex="-1" role="dialog" aria-labelledby="newledger_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="editcompany_title"><?php echo ( 1 == $type ) ? 'Add a new client invoice' : 'Add a new payment'; ?></h4>
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

</div>

<script type="text/javascript">
$('#modal-save-newledger').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#new_ledger").serialize()
	}).done(function(responseText){
		var result = $.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			//$('#newledger').modal('toggle');
			window.location.reload(true);
		} else {
			alert(result.error);
			display('dialog_newledger', { 'name': name, 'note' : note } );
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
			'divisionId': '<?php echo intval( $divisionId ); ?>',
			'type': '<?php echo $type; ?>'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
})

$('#modal-save-editledger').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "ledger.php",
		type: "POST",
		async: true,
		data: $("#edit_ledger").serialize()
	}).done(function(responseText){
		var result = $.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			//$('#editcompany').modal('toggle');
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
})

$('#newledger, #editledger').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});
</script>

</body>
</html>
