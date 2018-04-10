<?php

include( "../../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
require_once( INCLUDES . 'f_site.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$filterStatus = !empty( $_REQUEST['filterStatus'] ) ? $_REQUEST['filterStatus'] : null;
$filterUserId = !empty( $_REQUEST['filterUserId'] ) ? $_REQUEST['filterUserId'] : null;

$staffUsers = array(
	'47' => 'Bobby Lindsey',
	'3' => 'Chris Meehan',
	'63' => 'Naomi Barbeau',
);

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
		case "addNewNote":
			$c = true;
			$result['error'] = 'Failed when trying to add a new prospect note.';

			if( $c && empty( $_REQUEST['prospectId'] ) ) {
				$result['error'] = 'Please supply an prospect ID.';
				$c = false;
			}

			if( $c ) {
				$entry = $leads->getProspect( $_REQUEST['prospectId'] );
				if( empty( $entry ) ) {
					$result['error'] = 'There is no prospect that exists by that ID.';
					break;
				}
			}

			if( $c && empty( $_REQUEST['note'] ) ) {
				$result['error'] = 'Please type your note.';
				$c = false;
			}

			if( $c ) {
				$noteId = $leads->addProspectNote( array(
					'prospectId' => $_REQUEST['prospectId'],
					'userId' => LeadsSession::getUserId(),
					'timestamp' => date( 'Y-m-d H:i:s' ),
					'note' => trim( $_REQUEST['note'] ),
				) );
				if( null === $noteId ) {
					$c = false;
					$result['error'] = 'Error adding this prospect note to the database.';
				}
			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully added new prospect note.';
			}
			break;

		case "addNewProspect":
			$c = true;
			$result['error'] = 'Failed when trying to add a new prospect';

			if( $c && empty( $_REQUEST['company'] ) ) {
				$result['error'] = 'Please type in a company name.';
				$c = false;
			}

			if( $c && empty( $_REQUEST['note'] ) ) {
				$result['error'] = 'Please type an initial note about this prospect.';
				$c = false;
			}

			if( !empty( $_REQUEST['expectedClose'] ) ) {
				try {
					$expectedClose = new DateTime( $_REQUEST['expectedClose'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid expected close date.';
					break;
				}
			}

			$userId = LeadsSession::getUserId();
			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				if( $c && empty( $_REQUEST['userId'] ) ) {
					$result['error'] = 'Please select a salesperson from the list.';
					$c = false;
				}
				$userId = empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'];
			}

			if( $c ) {
				$prospectId = $leads->addProspect( array(
					'company' => $_REQUEST['company'],
					'name' => empty( $_REQUEST['name'] ) ? null : $_REQUEST['name'],
					'opportunity' => empty( $_REQUEST['opportunity'] ) ? null : $_REQUEST['opportunity'],
					'phone' => empty( $_REQUEST['phone'] ) ? null : $_REQUEST['phone'],
					'email' => empty( $_REQUEST['email'] ) ? null : $_REQUEST['email'],
					'userId' => $userId,
					'divisions' => empty( $_REQUEST['divisions'] ) ? null : implode( ',', $_REQUEST['divisions'] ),
					'percentage' => intval( $_REQUEST['percentage'] ),
					'expectedClose' => !isset( $expectedClose ) ? null : $expectedClose->format( 'Y-m-d' ),
				) );
				if( null === $prospectId ) {
					$c = false;
					$result['error'] = 'Error adding this prospect to the database.';
				} else {
					$leads->auditLog( 'PROSPECT:ADD', $prospectId );
					if( !empty( $_REQUEST['note'] ) ) {
						$leads->addProspectNote( array(
							'prospectId' => $prospectId,
							'userId' => LeadsSession::getUserId(),
							'timestamp' => date( 'Y-m-d H:i:s' ),
							'note' => trim( $_REQUEST['note'] ),
						) );
					}
				}
			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully added new prospect.';
			}
			break;

		case "alterProspect":
			$c = true;
			$result['error'] = 'Failed when trying to edit an prospect.';

			if( $c && empty( $_REQUEST['prospectId'] ) ) {
				$result['error'] = 'Please supply an prospect ID.';
				$c = false;
			}

			if( $c ) {
				$entry = $leads->getProspect( $_REQUEST['prospectId'] );
				if( empty( $entry ) ) {
					$result['error'] = 'There is no prospect that exists by that ID.';
					break;
				}
			}

			if( $c && empty( $_REQUEST['company'] ) ) {
				$result['error'] = 'Please type in a company name.';
				$c = false;
			}

			if( !empty( $_REQUEST['expectedClose'] ) ) {
				try {
					$expectedClose = new DateTime( $_REQUEST['expectedClose'] );
				} catch( Exception $e ) {
					$result['error'] = 'Please enter a valid expected close date.';
					break;
				}
			}

			$userId = LeadsSession::getUserId();
			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				if( $c && empty( $_REQUEST['userId'] ) ) {
					$result['error'] = 'Please select a salesperson from the list.';
					$c = false;
				}
				$userId = empty( $_REQUEST['userId'] ) ? null : $_REQUEST['userId'];
			}

			if( $c ) {
				$alterProspectResult = $leads->updateProspect( $_REQUEST['prospectId'], array(
					'company' => $_REQUEST['company'],
					'name' => empty( $_REQUEST['name'] ) ? null : $_REQUEST['name'],
					'opportunity' => empty( $_REQUEST['opportunity'] ) ? null : $_REQUEST['opportunity'],
					'phone' => empty( $_REQUEST['phone'] ) ? null : $_REQUEST['phone'],
					'email' => empty( $_REQUEST['email'] ) ? null : $_REQUEST['email'],
					'userId' => $userId,
					'divisions' => empty( $_REQUEST['divisions'] ) ? null : implode( ',', $_REQUEST['divisions'] ),
					'percentage' => intval( $_REQUEST['percentage'] ),
					'isArchived' => !empty( $_REQUEST['isArchived'] ) ? 1 : 0,
					'expectedClose' => !isset( $expectedClose ) ? null : $expectedClose->format( 'Y-m-d' ),
				) );

				if( $alterProspectResult === false ) {
					$c = false;
					$result['error'] = 'Database failure, could not alter prospect.';
				} else {
					$leads->auditLog( 'PROSPECT:EDIT', $_REQUEST['prospectId'] );
				}

			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully altered existing prospect.';
			}
			break;
	}
	echo json_encode( $result );
	exit;
}

if( isset( $_REQUEST['d'] ) ) {
	switch( $_REQUEST['d'] ) {
		case 'errorCount':
			Display::errorCount();
			break;

		case 'errorList':
			Display::errorList();
			break;

		case "dialog_newprospect":

			$divisions = $leads->getDivisions();

			$fields = array(
				array(
					'id' => 'company',
					'label' => 'Company',
					'type' => 'text',
					'required' => true,
				),
				array(
					'id' => 'name',
					'label' => 'Contact Name',
					'type' => 'text',
				),
				array(
					'id' => 'opportunity',
					'label' => 'Opportunity Description',
					'type' => 'text',
				),
				array(
					'id' => 'phone',
					'label' => 'Contact Phone',
					'type' => 'text',
				),
				array(
					'id' => 'email',
					'label' => 'Contact Email',
					'type' => 'text',
				),
				array(
					'id' => 'divisions',
					'label' => 'Division(s)',
					'type' => 'checkbox',
					'choices' => $divisions,
					'choice_append' => '<br/>',
				),
				array(
					'id' => 'percentage',
					'label' => 'Pct Complete',
					'type' => 'select',
					'placeholder' => 'Select a progress',
					'choices' => array(
						'0' => 'New Lead (0%)',
						'25' => 'Initial Contact Made (25%)',
						'50' => 'Opportunity Defined (50%)',
						'75' => 'Agreement/IO Pending (75%)',
						'100' => 'Closed (100%)',
					),
					'active' => LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ? true : false,
				),
				array(
					'id' => 'expectedClose',
					'label' => 'Expected Close Date',
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
					'id' => 'note',
					'label' => 'Initial Note',
					'type' => 'textarea',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewProspect',
				),
			);

			Display::displayForm( 'new_prospect', $fields );

			break;

		case "dialog_editprospect":
			$prospectId = !empty( $_REQUEST['prospectId'] ) ? $_REQUEST['prospectId'] : '';
			$prospect = $leads->getProspect( $prospectId );

			if( empty( $prospect ) ) {
				?>
				<p>There is no prospect that exists by that ID.</p>
				<?php

				break;

			}

			$divisions = $leads->getDivisions();
			$divisions_selected = array();
			if( !empty( $prospect->divisions ) ) {
				$tmp = explode( ',', $prospect->divisions );
				foreach( $tmp as $key => $val ) {
					$divisions_selected[$val] = 1;
				}
			}

			$fields = array(
				array(
					'id' => 'company',
					'label' => 'Company',
					'type' => 'text',
					'required' => true,
					'value' => $prospect->company,
				),
				array(
					'id' => 'name',
					'label' => 'Contact Name',
					'type' => 'text',
					'value' => $prospect->name,
				),
				array(
					'id' => 'opportunity',
					'label' => 'Opportunity Description',
					'type' => 'text',
					'value' => $prospect->opportunity,
				),
				array(
					'id' => 'phone',
					'label' => 'Contact Phone',
					'type' => 'text',
					'value' => $prospect->phone,
				),
				array(
					'id' => 'email',
					'label' => 'Contact Email',
					'type' => 'text',
					'value' => $prospect->email,
				),
				array(
					'id' => 'divisions',
					'label' => 'Division(s)',
					'type' => 'checkbox',
					'choices' => $divisions,
					'choice_append' => '<br/>',
					'value' => $divisions_selected,
				),
				array(
					'id' => 'percentage',
					'label' => 'Pct Complete',
					'type' => 'select',
					'placeholder' => 'Select a progress',
					'choices' => array(
						'0' => 'New Lead (0%)',
						'25' => 'Initial Contact Made (25%)',
						'50' => 'Opportunity Defined (50%)',
						'75' => 'Agreement/IO Pending (75%)',
						'100' => 'Closed (100%)',
					),
					'value' => $prospect->percentage,
				),
				array(
					'id' => 'expectedClose',
					'label' => 'Expected Close Date',
					'type' => 'text',
					'value' => $prospect->expectedClose,
				),
				array(
					'id' => 'userId',
					'label' => 'Salesperson',
					'type' => 'select',
					'placeholder' => 'Select a salesperson',
					'choices' => $leads->getStaffUsers(),
					'value' => $prospect->userId,
					'active' => LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ? true : false,
				),
				array(
					'id' => 'isArchived',
					'label' => 'Archived',
					'type' => 'checkbox',
					'choices' => array(
						'1' => 'Archive/Hide this record',
					),
					'value' => array(
						'1' => !empty( $prospect->isArchived ) ? 1 : 0,
					),
				),
				array(
					'id' => 'prospectId',
					'type' => 'hidden',
					'value' => $prospect->prospectId,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'alterProspect',
				),
			);

			Display::displayForm( 'edit_prospect', $fields );

			break;


		case "dialog_prospectnotes":
			$prospectId = !empty( $_REQUEST['prospectId'] ) ? $_REQUEST['prospectId'] : '';
			$prospect = $leads->getProspect( $prospectId );

			if( empty( $prospect ) ) {
				?>
				<p>There is no prospect that exists by that ID.</p>
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
					'id' => 'prospectId',
					'type' => 'hidden',
					'value' => $prospect->prospectId,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewNote',
				),
			);

			Display::displayForm( 'note_prospect', $fields );

			$notes = $leads->getProspectNotes( $prospect->prospectId );
			if( empty( $notes ) || !is_array( $notes ) ) {
				print '<p>There are no notes on file for this prospect.</p>' . PHP_EOL;
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

$title = 'CRM Prospect Manager';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Prospects</h2>

	<form class="pull-right" id="filter-select" method="get">
		<select id="filterStatus" name="filterStatus">
			<option value=""<?php if( null === $filterStatus ) {
				print ' selected="selected"';
			} ?>>Show all prospects
			</option>
			<option value="active"<?php if( 'active' === $filterStatus ) {
				print ' selected="selected"';
			} ?>>Show active prospects
			</option>
			<option value="archived"<?php if( 'archived' === $filterStatus ) {
				print ' selected="selected"';
			} ?>>Show archived prospects
			</option>
		</select>
		<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$users = $leads->getStaffUsers();
		} else {
			$users = $staffUsers;
		} ?>
		<select id="filterUserId" name="filterUserId">
			<option value=""<?php if( empty( $filterUserId ) ) {
				print ' selected="selected"';
			} ?>>Show all users
			</option>
			<?php
			foreach( $users as $key => $val ) {
				printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
					htmlentities( $key ),
					$filterUserId == $key ? ' selected="selected"' : '',
					htmlentities( $val )
				);
			}
			?>
		</select>
	</form>

	<p>
		<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newprospect">Add a new prospect</button>
	</p>

	<?php

	if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) && !array_key_exists( $filterUserId, $staffUsers ) ) {
		$filterUserId = LeadsSession::getUserId();
	}
	$prospects = $leads->getProspects( $filterStatus, $filterUserId );
	if( empty( $prospects ) ) {

		print '<p>No prospects exist in the database.</p>';

	} else {
		?>

		<table class="table table-bordered table-condensed table-striped" id="crm-prospects">
			<thead>
			<tr class="bgGray header">
				<th>Company</th>
				<th>Name</th>
				<th class="hidden-xs">Opportunity</th>
				<th class="hidden-xs">Phone</th>
				<th class="hidden-xs">Email</th>
				<th>Divisions</th>
				<th>Percentage</th>
				<th>Updated</th>
				<th>Options</th>
			</tr>
			</thead>
			<tbody>
			<?php
			$divisions = $leads->getDivisions();
			foreach( $prospects as $prospect ) {
				$divisions_selected = '';
				$tmp = explode( ',', $prospect->divisions );
				foreach( $tmp as $key => $val ) {
					if( isset( $divisions[$val] ) ) {
						if( !empty( $divisions_selected ) ) {
							$divisions_selected .= '<br/>';
						}
						$divisions_selected .= $divisions[$val];
					}
				}

				$progressClass = 'progress-bar-success';
				if( $prospect->percentage < 75 ) {
					$progressClass = 'progress-bar-danger';
				} else if( $prospect->percentage < 100 ) {
					$progressClass = 'progress-bar-warning';
				}
				?>
				<tr>
					<td><?php echo htmlentities( $prospect->company ); ?></td>
					<td><?php echo htmlentities( $prospect->name ); ?></td>
					<td class="hidden-xs"><?php echo htmlentities( $prospect->opportunity ); ?></td>
					<td class="hidden-xs"><?php echo htmlentities( $prospect->phone ); ?></td>
					<td class="hidden-xs"><?php echo htmlentities( $prospect->email ); ?></td>
					<td><?php echo $divisions_selected; ?></td>
					<td data-tf-sortKey="<?php echo intval( $prospect->percentage ); ?>">
						<div class="progress">
							<div class="progress-bar <?php echo $progressClass; ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="min-width: 2.5em; width: <?php echo intval( $prospect->percentage ); ?>%">
								<?php echo intval( $prospect->percentage ); ?>%
							</div>
						</div>
						<?php echo htmlentities( $prospect->expectedClose ); ?>
					</td>
					<td><?php echo !empty( $prospect->lastDate ) ? htmlentities( date( 'Y-m-d', strtotime( $prospect->lastDate ) ) ) : ''; ?></td>
					<td class="text-center">
						<div class="btn-group">
							<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editprospect" data-prospect-id="<?php echo $prospect->prospectId; ?>">Edit</button>
							<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<span class="caret"></span>
								<span class="sr-only">Toggle Dropdown</span>
							</button>
							<ul class="dropdown-menu">
								<li><a href="#" data-toggle="modal" data-target="#prospectnotes" data-prospect-id="<?php echo $prospect->prospectId; ?>">Notes</a></li>
							</ul>
						</div>
					</td>
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

<div class="modal fade" id="newprospect" tabindex="-1" role="dialog" aria-labelledby="newprospect_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newprospect_title">Add a new prospect</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newprospect" type="button" class="btn btn-primary">Add Prospect</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="prospectnotes" tabindex="-1" role="dialog" aria-labelledby="prospectnotes_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="prospectnotes_title">Prospect Notes</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-prospectnotes" type="button" class="btn btn-primary">Add A New Note</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editprospect" tabindex="-1" role="dialog" aria-labelledby="editprospect_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editprospect_title">Edit an prospect</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editprospect" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$('#modal-save-newprospect').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "prospects.php",
			type: "POST",
			async: true,
			data: $("#new_prospect").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#newprospect').on('show.bs.modal', function (e) {
		var modal = $(this);

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'prospects.php',
			data: {
				'd': 'dialog_newprospect'
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-editprospect').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "prospects.php",
			type: "POST",
			async: true,
			data: $("#edit_prospect").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#editprospect').on('show.bs.modal', function (e) {
		var modal = $(this);
		var prospectId = $(e.relatedTarget).data('prospect-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'prospects.php',
			data: {
				'd': 'dialog_editprospect',
				'prospectId': prospectId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-prospectnotes').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "prospects.php",
			type: "POST",
			async: true,
			data: $("#note_prospect").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#prospectnotes').on('show.bs.modal', function (e) {
		var modal = $(this);
		var prospectId = $(e.relatedTarget).data('prospect-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'prospects.php',
			data: {
				'd': 'dialog_prospectnotes',
				'prospectId': prospectId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#newprospect, #editprospect, #prospectnotes').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});

	$('#filter-select select').change(function (e) {
		e.preventDefault();
		$('#filter-select').submit();
	});

	$('#newprospect, #editprospect').on('shown.bs.modal', function (e) {
		$(".modal-body input[name=expectedClose]").datepicker({
			// Consistent format with the HTML5 picker
			dateFormat: 'yy-mm-dd'
		});
	});

	$('table').each(function () {
		var tf = new TableFilter($(this).attr('id'), {
			base_path: '/leadadmin/libraries/tablefilter/',
			grid: false,
			filters_row_index: 1,
			extensions: [{
				name: 'sort',
				types: [
					'caseinsensitivestring', // Company
					'caseinsensitivestring', // Name
					'caseinsensitivestring', // Opportunity
					'caseinsensitivestring', // Phone
					'caseinsensitivestring', // Email
					'caseinsensitivestring', // Divisions
					'Number', // Percentage
					{type: 'date', locale: 'en-US'} // Updated
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
