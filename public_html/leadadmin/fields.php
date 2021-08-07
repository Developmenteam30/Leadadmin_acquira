<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0,
        'error' => 'Action does not exist.',
    );
    switch ($_REQUEST['a']) {
        case "addnewfield":
            $c = true;
            $result['error'] = 'Failed when trying to add a new field';

            array_walk($_REQUEST, 'trim');

            if (empty($_REQUEST['fieldName']) || 'c_' === $_REQUEST['fieldName']) {
                $result['error'] = 'Field name cannot be blank.';
                break;
            }

            if (strpos($_REQUEST['fieldName'], 'c_') !== 0) {
                $_REQUEST['fieldName'] = 'c_' . $_REQUEST['fieldName'];
            }

            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $_REQUEST['fieldName'])) {
                $result['error'] = 'Field name may only contain letters, numbers, underscores, and/or dashes.';
                break;
            }

            if ($leads->checkFieldName($_REQUEST['fieldName'], 'custom')) {
                $result['error'] = 'That field name already exists in the database.';
                break;
            }

            if (empty($_REQUEST['fieldDescription'])) {
                $result['error'] = 'Field description cannot be blank.';
                break;
            }

            $fieldId = $leads->addField(array(
                'fieldName' => $_REQUEST['fieldName'],
                'fieldType' => 'custom',
                'fieldDescription' => $_REQUEST['fieldDescription'],
                'fieldFormat' => !empty($_REQUEST['fieldFormat']) ? $_REQUEST['fieldFormat'] : null,
            ));

            if (null === $fieldId) {
                $result['error'] = 'DB Error: Unable to add new field.';
                break;
            }

            $leads->auditLog('FIELDS:ADD', $fieldId);
            $result['status'] = 1;
            $result['error'] = 'Successfully added new field.';
            break;

        case "alterField":
            $c = true;
            $result['error'] = 'Failed when trying to edit a field';

            if (empty($_REQUEST['fieldId'])) {
                $result['error'] = 'Field ID cannot be blank.';
                break;
            }

            $alterFieldResult = $leads->updateField($_REQUEST['fieldId'], array(
                'fieldDescription' => $_REQUEST['fieldDescription'],
                'fieldFormat' => !empty($_REQUEST['fieldFormat']) ? $_REQUEST['fieldFormat'] : null,
            ));

            if ($alterFieldResult === false) {
                $result['error'] = 'Database failure, could not alter field.';
                break;
            }

            $leads->auditLog('FIELDS:EDIT', $_REQUEST['fieldId']);

            $result['status'] = 1;
            $result['error'] = 'Successfully altered existing field.';
            break;
    }
    echo json_encode($result);
    exit;
}

if (isset($_REQUEST['d'])) {
    switch ($_REQUEST['d']) {
        case 'errorCount':
            Display::errorCount();
            break;

        case 'errorList':
            Display::errorList();
            break;

        case "dialog_newfield":
            $fields = array(
                array(
                    'id' => 'instructions',
                    'type' => '_html',
                    'value' => '<p>Custom field names are <strong>case sensitive</strong>. Your vendor will need to send the field with the exact capitalization you specify here. We recommend using all lowercase letters, but this is not required. Valid characters are lowercase and uppercase letters, numbers, underscores, and dashses.</p><p>Custom field names are automatically prepended with a "c_".  So if you name the field "income_level", the API specs will expect the field to come over as "c_income_level".</p><p>Once a custom field is added, the field cannot be removed or renamed since this may affect existing feeds already using that field.</p><p>Custom fields cannot be searched by in the "Record Search" feature. If you think you will need a field to be searchable in the future, please ask for it to be added as a "System" field instead.</p><hr/>',
                ),
                array(
                    'id' => 'fieldType',
                    'label' => 'Type',
                    'type' => '_text',
                    'value' => 'Custom',
                ),
                array(
                    'id' => 'fieldName',
                    'label' => 'Field Name',
                    'type' => 'text',
                    'required' => true,
                ),
                array(
                    'id' => 'fieldDescription',
                    'label' => 'Description',
                    'type' => 'text',
                    'required' => true,
                ),
                array(
                    'id' => 'fieldFormat',
                    'label' => 'Format',
                    'type' => 'text',
                ),
                array(
                    'id' => 'a',
                    'type' => 'hidden',
                    'value' => 'addnewfield',
                ),
            );

            Display::displayForm('new_field', $fields);

            break;

        case "dialog_editfield":
            $fieldId = !empty($_REQUEST['fieldId']) ? $_REQUEST['fieldId'] : '';
            $field = $leads->getField($fieldId);

            if (empty($field)) {
                ?>
				<p>There is no field that exists by that ID.</p>
                <?php
            } else {

                $fields = array(
                    array(
                        'id' => 'instructions',
                        'type' => '_html',
                        'value' => '<p>Once a custom field is added, the field cannot be removed or renamed since this may affect existing feeds already using that field.</p><hr/>',
                    ),
                    array(
                        'id' => 'fieldType',
                        'label' => 'Type',
                        'type' => '_text',
                        'value' => ucfirst($field->fieldType),
                    ),
                    array(
                        'id' => 'fieldName',
                        'label' => 'Field Name',
                        'type' => '_text',
                        'value' => $field->fieldName,
                    ),
                    array(
                        'id' => 'fieldDescription',
                        'label' => 'Description',
                        'type' => 'text',
                        'value' => $field->fieldDescription,
                        'required' => true,
                    ),
                    array(
                        'id' => 'fieldFormat',
                        'label' => 'Format',
                        'type' => 'text',
                        'value' => $field->fieldFormat,
                    ),
                    array(
                        'id' => 'fieldId',
                        'type' => 'hidden',
                        'value' => $field->fieldId,
                    ),
                    array(
                        'id' => 'a',
                        'type' => 'hidden',
                        'value' => 'alterField',
                    ),
                );

                Display::displayForm('edit_field', $fields);

            }
            break;
    }
    exit;
}

$title = 'Field Management';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

	<h2>Field Management</h2>

	<p>
		<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#newfield">Add a new custom field</button>
	</p>

    <?php
    $fields = $leads->getAllFields();

    ?>
	<table class="table table-bordered table-condensed table-striped">
		<thead>
		<tr class="bgGray header">
			<th>Name</th>
			<th>Type</th>
			<th>Description</th>
			<th>Definition</th>
			<th>Format</th>
			<th>Options</th>
		</tr>
		</thead>
		<tbody>
        <?php
        foreach ($fields as $field) {
            ?>
			<tr>
				<td><?php echo Display::escHtml($field->fieldName); ?></td>
				<td><?php echo Display::escHtml(ucfirst($field->fieldType)); ?></td>
				<td><?php echo Display::escHtml($field->fieldDescription); ?></td>
				<td><?php echo Display::escHtml($field->fieldDefinition); ?></td>
				<td><?php echo Display::escHtml($field->fieldFormat); ?></td>
				<td class="text-center">
					<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editfield" data-field-id="<?php echo Display::escHtml($field->fieldId); ?>">Edit</button>
				</td>
			</tr>
            <?php
        }
        ?>
		</tbody>
	</table>

</div>

<div class="modal fade" id="newfield" tabindex="-1" role="dialog" aria-labelledby="newfield_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newfield_title">Add a new custom field</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newfield" type="button" class="btn btn-primary">Add Field</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editfield" tabindex="-1" role="dialog" aria-labelledby="editfield_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editfield_title">Edit a field</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editfield" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$('#modal-save-newfield').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "fields.php",
			type: "POST",
			async: true,
			data: $("#new_field").serialize()
		}).done(function (result) {
			if (result.status === 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#newfield').on('show.bs.modal', function (e) {
		var modal = $(this);

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'fields.php',
			data: {
				'd': 'dialog_newfield'
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-editfield').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "fields.php",
			type: "POST",
			async: true,
			data: $("#edit_field").serialize()
		}).done(function (result) {
			if (result.status === 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#editfield').on('show.bs.modal', function (e) {
		var modal = $(this);
		var fieldId = $(e.relatedTarget).data('field-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'fields.php',
			data: {
				'd': 'dialog_editfield',
				'fieldId': fieldId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#newfield, #editfield').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});
</script>

</body>
</html>
