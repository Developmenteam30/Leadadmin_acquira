<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$divisions = $leads->getDivisions();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch($_REQUEST['a']){
		case "addnewvertical":
			$c = true;
			$result['error'] = 'Failed when trying to add a new vertical';

			if( empty( $_REQUEST['divisionId'] ) ) {
				$result['error'] = 'Please select a division.';
				$c = false;
			}

			if( $c && empty( trim( $_REQUEST['name'] ) ) ) {
				$result['error'] = 'Vertical name cannot be blank.';
				$c = false;
			}

			if($c){
				if( $leads->checkVerticalName( trim( $_REQUEST['name'], $_REQUEST['divisionId'] ) ) ) {
					$c = false;
					$result['error'] = 'That vertical name already exists in the database.';
				}
			}

			if($c){
				$verticalId = $leads->addVertical( array(
					'name' => trim( $_REQUEST['name'] ),
					'divisionId' => $_REQUEST['divisionId'],
				) );
				if( null === $verticalId ) {
					$c = false;
					$result['error'] = $newverticalResult['error'];
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully added new vertical.';
			}
		break;

		case "alterVertical":
			$c = true;
			$result['error'] = 'Failed when trying to edit a vertical';

			if( empty( trim( $_REQUEST['name'] ) ) ) {
				$result['error'] = 'Vertical name cannot be blank.';
				$c = false;
			}

			if($c){
				if( $leads->checkVerticalName( trim( $_REQUEST['name'] ), $_REQUEST['divisionId'], $_REQUEST['verticalId'] ) ) {
					$c = false;
					$result['error'] = 'That vertical name already exists in the database.';
				}
			}

			if($c){
				$alterVerticalResult = $leads->updateVertical( $_REQUEST['verticalId'], array(
					'name' => trim( $_REQUEST['name'] ),
				) );

				if($alterVerticalResult === false){
					$c = false;
					$result['error'] = 'Database failure, could not alter vertical.';
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully altered existing vertical.';
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

		case "dialog_newvertical":
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
					'id' => 'name',
					'label' => 'Vertical Name',
					'type' => 'text',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addnewvertical',
				),
			);

			Display::displayForm( 'new_vertical', $fields );

		break;

		case "dialog_editvertical":
			$verticalId = !empty( $_REQUEST['verticalId'] ) ? $_REQUEST['verticalId'] : '';
			$vertical = $leads->getVertical( $verticalId );

			if( empty( $vertical ) ) {
?>
<p>There is no vertical that exists by that ID.</p>
<?php
			} else {

				$fields = array(
					array(
						'id' => 'name',
						'label' => 'Vertical Name',
						'type' => 'text',
						'required' => true,
						'value' => $vertical->name,
					),
					array(
						'id' => 'verticalId',
						'type' => 'hidden',
						'value' => $vertical->verticalId,
					),
					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'alterVertical',
					),
				);

				Display::displayForm( 'edit_vertical', $fields );

			}
		break;
	}
	exit;
}

$title = 'Vertical Manager';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newvertical">Add a new vertical</button>

<?php
	if( empty( $divisions ) ) {

		print '<p>No divisions exist in the database.</p>';

	} else {

		foreach($divisions as $key => $val){
			printf( '<h3>%s</h3>' . PHP_EOL,
				htmlentities( $val )
			);


			$verticals = $leads->getDivisionVerticals( $key );

?>
<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr class="bgGray header">
			<th>Vertical Name</th>
			<th>Options</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach($verticals as $key => $val ){
?>
		<tr>
			<td><?php echo htmlentities( $val ); ?></td>
			<td class="text-center"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editvertical" data-vertical-id="<?php echo intval( $key ); ?>">Edit</button></td>
		</tr>
<?php
		}
?>
	</tbody>
</table>

<?php
		}
	}

?>
</div>

<div class="modal fade" id="newvertical" tabindex="-1" role="dialog" aria-labelledby="newvertical_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newvertical_title">Add a new vertical</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newvertical" type="button" class="btn btn-primary">Add Vertical</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editvertical" tabindex="-1" role="dialog" aria-labelledby="editvertical_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editvertical_title">Edit a vertical</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editvertical" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newvertical').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "verticals.php",
		type: "POST",
		async: true,
		data: $("#new_vertical").serialize()
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			$('#newvertical').modal('toggle');
			$('#new_vertical').trigger('reset');
			window.location.reload(true);
		} else {
			alert(result.error);
			display('dialog_newvertical', { 'name': name, 'note' : note } );
		}
	});
});

$('#newvertical').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'verticals.php',
		data: {
			'd': 'dialog_newvertical'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editvertical').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "verticals.php",
		type: "POST",
		async: true,
		data: $("#edit_vertical").serialize()
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			$('#editvertical_<?php echo $vertical->verticalId; ?>').modal('toggle');
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editvertical').on('show.bs.modal', function(e) {
	var modal = $(this);
	var verticalId = $(e.relatedTarget).data('vertical-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'verticals.php',
		data: {
			'd': 'dialog_editvertical',
			'verticalId': verticalId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newvertical, #editvertical').on('hide.bs.modal', function(e) {
    $(this).find('.modal-body').html('');
});
</script>

</body>
</html>
