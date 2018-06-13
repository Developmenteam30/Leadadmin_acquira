<?php

include("../../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
require_once( INCLUDES . 'f_site.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch($_REQUEST['a']){
		case "addNewNote":
			$c = true;
			$result['error'] = 'Failed when trying to add a new opportunity note.';

			if( $c && empty( $_REQUEST['opportunityId'] ) ) {
				$result['error'] = 'Please supply an opportunity ID.';
				$c = false;
			}

			if( $c ) {
				$entry = $leads->getOpportunity( $_REQUEST['opportunityId'] );
				if( empty( $entry ) ) {
					$result['error'] = 'There is no opportunity that exists by that ID.';
					break;
				}
			}

			if( $c && empty( $_REQUEST['note'] ) ) {
				$result['error'] = 'Please type your note.';
				$c = false;
			}

			if($c){
				$noteId = $leads->addOpportunityNote( array(
					'opportunityId' => $_REQUEST['opportunityId'],
					'userId' => LeadsSession::getUserId(),
					'timestamp' => date( 'Y-m-d H:i:s' ),
					'note' => trim( $_REQUEST['note'] ),
				) );
				if( null === $noteId ) {
					$c = false;
					$result['error'] = 'Error adding this opportunity note to the database.';
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully added new opportunity note.';
			}
		break;

		case "addNewOpportunity":
			$c = true;
			$result['error'] = 'Failed when trying to add a new opportunity';

			if( $c && empty( $_REQUEST['status'] ) ) {
				$result['error'] = 'Please select a status from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['userId'] ) ) {
				$result['error'] = 'Please select a salesperson from the list.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['expense'] ) && is_numeric( $_REQUEST['expense'] ) === FALSE ) {
				$result['error'] = 'Expense amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['expense'] ) && floatval( $_REQUEST['expense'] ) < 0 ) {
				$result['error'] = 'Expense amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['revenue'] ) && is_numeric( $_REQUEST['revenue'] ) === FALSE ) {
				$result['error'] = 'Revenue amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['revenue'] ) && floatval( $_REQUEST['revenue'] ) < 0 ) {
				$result['error'] = 'Revenue amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['startQty'] ) && is_numeric( $_REQUEST['startQty'] ) === FALSE ) {
				$result['error'] = 'Starting qty amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['startQty'] ) && floatval( $_REQUEST['startQty'] ) < 0 ) {
				$result['error'] = 'Starting qty amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['goalQty'] ) && is_numeric( $_REQUEST['goalQty'] ) === FALSE ) {
				$result['error'] = 'Goal qty amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['goalQty'] ) && floatval( $_REQUEST['goalQty'] ) < 0 ) {
				$result['error'] = 'Goal qty amount cannot be less than zero.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['note'] ) ) {
				$result['error'] = 'Please type an intial note about this opportunity.';
				$c = false;
			}

			if($c){
				$opportunityId = $leads->addOpportunity( array(
					'companyId' => $_REQUEST['companyId'],
					'divisionId' => empty( $_REQUEST['divisionId'] ) ? null : $_REQUEST['divisionId'],
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
					'affiliate' => empty( $_REQUEST['affiliate'] ) ? null : trim( $_REQUEST['affiliate'] ),
					'products' => empty( $_REQUEST['products'] ) ? null : trim( $_REQUEST['products'] ),
					'status' => $_REQUEST['status'],
					'expense' => empty( $_REQUEST['expense'] ) ? 0.00 : $_REQUEST['expense'],
					'revenue' => empty( $_REQUEST['revenue'] ) ? 0.00 : $_REQUEST['revenue'],
					'startQty' => empty( $_REQUEST['startQty'] ) ? 0.00 : $_REQUEST['startQty'],
					'goalQty' => empty( $_REQUEST['goalQty'] ) ? 0.00 : $_REQUEST['goalQty'],
				) );
				if( null === $opportunityId ) {
					$c = false;
					$result['error'] = 'Error adding this opportunity to the database.';
				} else {
					$leads->auditLog( 'OPPORTUNITIES:ADD', $opportunityId );
					if( !empty( $_REQUEST['note'] ) ) {
						$leads->addOpportunityNote( array(
							'opportunityId' => $opportunityId,
							'userId' => LeadsSession::getUserId(),
							'timestamp' => date( 'Y-m-d H:i:s' ),
							'note' => trim( $_REQUEST['note'] ),
						) );
					}
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully added new opportunity.';
			}
		break;

		case "alterOpportunity":
			$c = true;
			$result['error'] = 'Failed when trying to edit an opportunity.';

			if( $c && empty( $_REQUEST['opportunityId'] ) ) {
				$result['error'] = 'Please supply an opportunity ID.';
				$c = false;
			}

			if( $c ) {
				$entry = $leads->getOpportunity( $_REQUEST['opportunityId'] );
				if( empty( $entry ) ) {
					$result['error'] = 'There is no opportunity that exists by that ID.';
					break;
				}
			}

			if( $c && empty( $_REQUEST['status'] ) ) {
				$result['error'] = 'Please select a status from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please select a company from the list.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['userId'] ) ) {
				$result['error'] = 'Please select a salesperson from the list.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['expense'] ) && is_numeric( $_REQUEST['expense'] ) === FALSE ) {
				$result['error'] = 'Expense amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['expense'] ) && floatval( $_REQUEST['expense'] ) < 0 ) {
				$result['error'] = 'Expense amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['revenue'] ) && is_numeric( $_REQUEST['revenue'] ) === FALSE ) {
				$result['error'] = 'Revenue amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['revenue'] ) && floatval( $_REQUEST['revenue'] ) < 0 ) {
				$result['error'] = 'Revenue amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['startQty'] ) && is_numeric( $_REQUEST['startQty'] ) === FALSE ) {
				$result['error'] = 'Starting qty amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['startQty'] ) && floatval( $_REQUEST['startQty'] ) < 0 ) {
				$result['error'] = 'Starting qty amount cannot be less than zero.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['goalQty'] ) && is_numeric( $_REQUEST['goalQty'] ) === FALSE ) {
				$result['error'] = 'Goal qty amount must be a numeric value.';
				$c = false;
			}

			if( $c && !empty( $_REQUEST['goalQty'] ) && floatval( $_REQUEST['goalQty'] ) < 0 ) {
				$result['error'] = 'Goal qty amount cannot be less than zero.';
				$c = false;
			}

			if($c){
				$alterOpportunityResult = $leads->updateOpportunity( $_REQUEST['opportunityId'], array(
					'companyId' => $_REQUEST['companyId'],
					'divisionId' => empty( $_REQUEST['divisionId'] ) ? null : $_REQUEST['divisionId'],
					'userId' => empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'],
					'affiliate' => empty( $_REQUEST['affiliate'] ) ? null : trim( $_REQUEST['affiliate'] ),
					'products' => empty( $_REQUEST['products'] ) ? null : trim( $_REQUEST['products'] ),
					'status' => $_REQUEST['status'],
					'expense' => empty( $_REQUEST['expense'] ) ? 0.00 : $_REQUEST['expense'],
					'revenue' => empty( $_REQUEST['revenue'] ) ? 0.00 : $_REQUEST['revenue'],
					'startQty' => empty( $_REQUEST['startQty'] ) ? 0.00 : $_REQUEST['startQty'],
					'goalQty' => empty( $_REQUEST['goalQty'] ) ? 0.00 : $_REQUEST['goalQty'],
				) );

				if($alterOpportunityResult === false){
					$c = false;
					$result['error'] = 'Database failure, could not alter opportunity.';
				} else {
					$leads->auditLog( 'OPPORTUNITIES:EDIT', $_REQUEST['opportunityId'] );
				}

			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully altered existing opportunity.';
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

		case "dialog_newopportunity":

			$divisions = $leads->getDivisions();
			$db_companies = $leads->getCompanies( 'active' );
			$companies = array();
			foreach( $db_companies as $db_company ) {
				$companies[$db_company->idCompany] = $db_company->name;
			}

			$fields = array(
				array(
					'id' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'placeholder' => 'Select a status',
					'choices' => $crmStatuses,
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'placeholder' => 'Select a company',
					'choices' => $companies,
				),
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => 'select',
					'placeholder' => 'Select a division',
					'choices' => $divisions,
				),
				array(
					'id' => 'affiliate',
					'label' => 'Affiliate',
					'type' => 'text',
					'required' => true,
				),
				array(
					'id' => 'products',
					'label' => 'Products',
					'type' => 'text',
				),
				array(
					'id' => 'userId',
					'label' => 'Salesperson',
					'type' => 'select',
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
				),
				array(
					'id' => 'expense',
					'label' => 'Expense',
					'type' => 'currency',
				),
				array(
					'id' => 'revenue',
					'label' => 'Revenue',
					'type' => 'currency',
				),
				array(
					'id' => 'startQty',
					'label' => 'Starting Qty',
					'type' => 'currency',
				),
				array(
					'id' => 'goalQty',
					'label' => 'Goal Qty',
					'type' => 'currency',
				),
				array(
					'id' => 'note',
					'label' => 'Initial Note',
					'type' => 'textarea',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewOpportunity',
				),
			);

			Display::displayForm( 'new_opportunity', $fields );

		break;

		case "dialog_editopportunity":
			$opportunityId = !empty( $_REQUEST['opportunityId'] ) ? $_REQUEST['opportunityId'] : '';
			$opportunity = $leads->getOpportunity( $opportunityId );

			if( empty( $opportunity ) ) {
?>
<p>There is no opportunity that exists by that ID.</p>
<?php

				break;

			}

			$divisions = $leads->getDivisions();
			$db_companies = $leads->getCompanies( 'active' );
			$companies = array();
			foreach( $db_companies as $db_company ) {
				$companies[$db_company->idCompany] = $db_company->name;
			}

			$fields = array(
				array(
					'id' => 'status',
					'label' => 'Status',
					'type' => 'select',
					'placeholder' => 'Select a status',
					'choices' => $crmStatuses,
					'value' => $opportunity->status,
				),
				array(
					'id' => 'companyId',
					'label' => 'Company',
					'type' => 'select',
					'placeholder' => 'Select a company',
					'choices' => $companies,
					'value' => $opportunity->companyId,
				),
				array(
					'id' => 'divisionId',
					'label' => 'Division',
					'type' => 'select',
					'placeholder' => 'Select a division',
					'choices' => $divisions,
					'value' => $opportunity->divisionId,
				),
				array(
					'id' => 'affiliate',
					'label' => 'Affiliate',
					'type' => 'text',
					'required' => true,
					'value' => $opportunity->affiliate,
				),
				array(
					'id' => 'products',
					'label' => 'Products',
					'type' => 'text',
					'value' => $opportunity->products,
				),
				array(
					'id' => 'userId',
					'label' => 'Salesperson',
					'type' => 'select',
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
					'value' => $opportunity->userId,
				),
				array(
					'id' => 'expense',
					'label' => 'Expense',
					'type' => 'currency',
					'value' => $opportunity->expense,
				),
				array(
					'id' => 'revenue',
					'label' => 'Revenue',
					'type' => 'currency',
					'value' => $opportunity->revenue,
				),
				array(
					'id' => 'startQty',
					'label' => 'Starting Qty',
					'type' => 'currency',
					'value' => $opportunity->startQty,
				),
				array(
					'id' => 'goalQty',
					'label' => 'Goal Qty',
					'type' => 'currency',
					'value' => $opportunity->goalQty,
				),
				array(
					'id' => 'opportunityId',
					'type' => 'hidden',
					'value' => $opportunity->opportunityId,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'alterOpportunity',
				),
			);

			Display::displayForm( 'edit_opportunity', $fields );

		break;


		case "dialog_opportunitynotes":
			$opportunityId = !empty( $_REQUEST['opportunityId'] ) ? $_REQUEST['opportunityId'] : '';
			$opportunity = $leads->getOpportunity( $opportunityId );

			if( empty( $opportunity ) ) {
?>
<p>There is no opportunity that exists by that ID.</p>
<?php

				break;

			}

			$fields = array(
				array(
					'id' => 'note',
					'label' => 'Add a Note',
					'type' => 'textarea',
				),
				array(
					'id' => 'opportunityId',
					'type' => 'hidden',
					'value' => $opportunity->opportunityId,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewNote',
				),
			);

			Display::displayForm( 'note_opportunity', $fields );

			$notes = $leads->getOpportunityNotes( $opportunity->opportunityId );
			if( empty( $notes ) || !is_array( $notes ) ) {
				print '<p>There are no notes on file for this opportunity.</p>' . PHP_EOL;
			} else {
				foreach( $notes as $note ) {
					printf( '<hr/><p>On <strong>%s</strong> at %s, <strong>%s</strong> wrote:<br/>%s</p>' . PHP_EOL,
						date( 'D, M jS, Y', strtotime( $note->timestamp ) ),
						date( 'g:ia', strtotime( $note->timestamp ) ),
						htmlentities( $note->fullName ),
						nl2br( htmlentities( $note->note ) )
					);
				}
			}

		break;
	}
	exit;
}

$title = 'CRM Opportunity Manager';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Opportunities</h2>

<form class="pull-right" id="status-select" method="get">
<select id="status" name="status">
	<option value=""<?php if( null === $status ) { print ' selected="selected"'; } ?>>Show all opportunities</option>
	<option value="active"<?php if( 'active' === $status ) { print ' selected="selected"'; } ?>>Show active opportunities</option>
<?php
	foreach( $crmStatuses as $key => $val ) {
		printf( '<option value="%s"%s>Show "%s" opportunities</option>' . PHP_EOL,
			$key,
			$status === $key ? ' selected="selected"' : "",
			$val
		);
	}
?>
</select>
</form>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#newopportunity">Add a new opportunity</button></p>

<?php
	$opportunities = $leads->getOpportunities( $status );
	if( empty( $opportunities ) ) {

		print '<p>No opportunities exist in the database.</p>';

	} else {
?>

<table class="table table-bordered table-condensed table-striped" id="crm-opportunities">
	<thead>
		<tr class="bgGray header">
			<th>Company</th>
			<th>Division</th>
			<th class="hidden-xs">Affiliate</th>
			<th class="hidden-xs">Salesperson</th>
			<th>Status</th>
			<th>Updated</th>
			<th class="hidden-xs">Products</th>
			<th>Rev</th>
			<th>Exp</th>
			<th>Start Qty</th>
			<th>Rev/Wk</th>
			<th>Exp/Wk</th>
			<th>GP/Wk</th>
			<th>Goal Qty</th>
			<th>Rev/Wk</th>
			<th>Exp/Wk</th>
			<th>GP/Wk</th>
			<th>Options</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach( $opportunities as $opportunity ) {
?>
		<tr>
			<td><?php echo htmlentities( $opportunity->companyName ); ?></td>
			<td><?php echo htmlentities( $opportunity->divisionName ); ?></td>
			<td class="hidden-xs"><?php echo htmlentities( $opportunity->affiliate ); ?></td>
			<td class="hidden-xs"><?php echo htmlentities( $opportunity->fullName ); ?></td>
			<td><?php echo htmlentities( $crmStatuses[$opportunity->status] ); ?></td>
			<td><?php echo !empty( $opportunity->lastDate ) ? htmlentities( date( 'Y-m-d', strtotime( $opportunity->lastDate ) ) ) : ''; ?></td>
			<td class="hidden-xs"><?php echo htmlentities( $opportunity->products ); ?></td>
			<td>$<?php echo number_format( $opportunity->revenue, 2 ); ?></td>
			<td>$<?php echo number_format( $opportunity->expense, 2 ); ?></td>
			<td class="crm-highlight" data-tf-sortKey="<?php echo number_format( $opportunity->startQty, 0, '', '' ); ?>"><?php echo number_format( $opportunity->startQty, 0 ); ?></td>
			<td class="crm-highlight">$<?php echo number_format( $opportunity->startQty * $opportunity->revenue, 2 ); ?></td>
			<td class="crm-highlight">$<?php echo number_format( $opportunity->startQty * $opportunity->expense, 2 ); ?></td>
			<td class="crm-highlight">$<?php echo number_format( ( $opportunity->startQty * $opportunity->revenue ) - ( $opportunity->startQty * $opportunity->expense ), 2 ); ?></td>
			<td data-tf-sortKey="<?php echo number_format( $opportunity->goalQty, 0, '', '' ); ?>"><?php echo number_format( $opportunity->goalQty, 0 ); ?></td>
			<td>$<?php echo number_format( $opportunity->goalQty * $opportunity->revenue, 2 ); ?></td>
			<td>$<?php echo number_format( $opportunity->goalQty * $opportunity->expense, 2 ); ?></td>
			<td>$<?php echo number_format( ( $opportunity->goalQty * $opportunity->revenue ) - ( $opportunity->goalQty * $opportunity->expense ), 2 ); ?></td>
			<td class="text-center">
<div class="btn-group">
	<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editopportunity" data-opportunity-id="<?php echo $opportunity->opportunityId; ?>">Edit</button>
	<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu">
		<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#opportunitynotes" data-opportunity-id="<?php echo $opportunity->opportunityId; ?>">Notes</a></li>
	</ul>
</div></td>
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

<div class="modal fade" id="newopportunity" tabindex="-1" role="dialog" aria-labelledby="newopportunity_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newopportunity_title">Add a new opportunity</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newopportunity" type="button" class="btn btn-primary">Add Opportunity</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="opportunitynotes" tabindex="-1" role="dialog" aria-labelledby="opportunitynotes_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="opportunitynotes_title">Opportunity Notes</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-opportunitynotes" type="button" class="btn btn-primary">Add A New Note</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editopportunity" tabindex="-1" role="dialog" aria-labelledby="editopportunity_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editopportunity_title">Edit an opportunity</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editopportunity" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newopportunity').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "opportunities.php",
		type: "POST",
		async: true,
		data: $("#new_opportunity").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newopportunity').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'opportunities.php',
		data: {
			'd': 'dialog_newopportunity'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editopportunity').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "opportunities.php",
		type: "POST",
		async: true,
		data: $("#edit_opportunity").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editopportunity').on('show.bs.modal', function(e) {
	var modal = $(this);
	var opportunityId = $(e.relatedTarget).data('opportunity-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'opportunities.php',
		data: {
			'd': 'dialog_editopportunity',
			'opportunityId': opportunityId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-opportunitynotes').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "opportunities.php",
		type: "POST",
		async: true,
		data: $("#note_opportunity").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#opportunitynotes').on('show.bs.modal', function(e) {
	var modal = $(this);
	var opportunityId = $(e.relatedTarget).data('opportunity-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'opportunities.php',
		data: {
			'd': 'dialog_opportunitynotes',
			'opportunityId': opportunityId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newopportunity, #editopportunity, #opportunitynotes').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});

$('#status-select select').change(function(e) {
	e.preventDefault();
	$('#status-select').submit();
});

$('table').each(function() {
	var tf = new TableFilter($(this).attr('id'), {
		base_path: '/leadadmin/libraries/tablefilter/',
		grid: false,
		filters_row_index: 1,
		extensions: [{
			name: 'sort',
			types: [
				'String', // Company
				'String', // Division
				'String', // Affiliate
				'String', // Salesperson
				'String', // Status
				'ymdddate', // Updated
				'String', // Products
				'us', // Rev
				'us', // Exp
				'Number', // Start Qty
				'us', // Rev/Wk
				'us', // Exp/Wk
				'us', // GP/Wk
				'Number', // Goal Qty
				'us', // Rev/Wk
				'us', // Exp/Wk
				'us' // GP/Wk
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
