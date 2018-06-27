<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_MANAGER );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'cryptor.php' );

$divisions = $leads->getDivisions();
$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
		case "addNewCredential":
			$c = true;
			$result['error'] = 'Failed when trying to add a new credential';

			if( empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'You must select a company from the list.';
				break;
			}

			$credentialId = $leads->addCredential( array(
				'companyId' => $_REQUEST['companyId'],
				'url' => empty( $_REQUEST['url'] ) ? null : Display::encryptValue( trim( $_REQUEST['url'] ) ),
				'username' => empty( $_REQUEST['username'] ) ? null : Display::encryptValue( trim( $_REQUEST['username'] ) ),
				'password' => empty( $_REQUEST['password'] ) ? null : Display::encryptValue( trim( $_REQUEST['password'] ) ),
				'notes' => empty( $_REQUEST['notes'] ) ? null : trim( $_REQUEST['notes'] ),
				'employeeName' => empty( $_REQUEST['employeeName'] ) ? null : trim( $_REQUEST['employeeName'] ),
			) );

			if( null === $credentialId ) {
				$c = false;
				$result['error'] = 'Error adding this credential to the database.';
			} else {
				$leads->auditLog( 'CREDENTIALS:ADD', $credentialId );
			}

			$result['status'] = 1;
			$result['error'] = 'Successfully added new credential.';
			break;

		case "alterCredential":
			$c = true;
			$result['error'] = 'Failed when trying to edit a credential';

			if( empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'You must select a company from the list.';
				break;
			}

			$alterCredentialResult = $leads->updateCredential( $_REQUEST['credentialId'], array(
				'companyId' => $_REQUEST['companyId'],
				'url' => empty( $_REQUEST['url'] ) ? null : Display::encryptValue( trim( $_REQUEST['url'] ) ),
				'username' => empty( $_REQUEST['username'] ) ? null : Display::encryptValue( trim( $_REQUEST['username'] ) ),
				'password' => empty( $_REQUEST['password'] ) ? null : Display::encryptValue( trim( $_REQUEST['password'] ) ),
				'notes' => empty( $_REQUEST['notes'] ) ? null : trim( $_REQUEST['notes'] ),
				'employeeName' => empty( $_REQUEST['employeeName'] ) ? null : trim( $_REQUEST['employeeName'] ),
				'status' => empty( $_REQUEST['status'] ) ? 'active' : trim( $_REQUEST['status'] ),
			) );

			if( $alterCredentialResult === false ) {
				$c = false;
				$result['error'] = 'Database failure, could not alter credential.';
			} else {
				$leads->auditLog( 'COMPANIES:EDIT', $_REQUEST['credentialId'] );
			}

			$result['status'] = 1;
			$result['error'] = 'Successfully altered existing credential.';
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

		case "dialog_newcredential":

			$db_companies = $leads->getCompanies( 'active' );
			$companies = array();
			foreach( $db_companies as $db_company ) {
				$companies[$db_company->idCompany] = $db_company->name;
			}

			$fields = array(
				array(
					'id' => 'companyId',
					'label' => 'Client',
					'type' => 'select',
					'required' => true,
					'placeholder' => 'Select a client',
					'choices' => $companies,
				),
				array(
					'id' => 'url',
					'label' => 'URL',
					'type' => 'url',
					'required' => true,
				),
				array(
					'id' => 'username',
					'label' => 'Username',
					'type' => 'text',
				),
				array(
					'id' => 'password',
					'label' => 'Password',
					'type' => 'text',
				),
				array(
					'id' => 'employeeName',
					'label' => 'Employee Name',
					'type' => 'text',
				),
				array(
					'id' => 'notes',
					'label' => 'Notes',
					'type' => 'textarea',
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewCredential',
				),
			);

			Display::displayForm( 'new_credential', $fields );

			break;

		case "dialog_editcredential":
			$credentialId = !empty( $_REQUEST['credentialId'] ) ? $_REQUEST['credentialId'] : '';
			$credential = $leads->getCredential( $credentialId );

			if( empty( $credential ) ) {
				?>
				<p>There is no credential that exists by that ID.</p>
				<?php
			} else {

				$db_companies = $leads->getCompanies( 'active' );
				$companies = array();
				foreach( $db_companies as $db_company ) {
					$companies[$db_company->idCompany] = $db_company->name;
				}

				$fields = array(
					array(
						'id' => 'companyId',
						'label' => 'Client',
						'type' => 'select',
						'required' => true,
						'placeholder' => 'Select a client',
						'choices' => $companies,
						'value' => $credential->companyId,
					),
					array(
						'id' => 'url',
						'label' => 'URL',
						'type' => 'url',
						'required' => true,
						'value' => Display::decryptValue( $credential->url ),
					),
					array(
						'id' => 'username',
						'label' => 'Username',
						'type' => 'text',
						'value' => Display::decryptValue( $credential->username ),
					),
					array(
						'id' => 'password',
						'label' => 'Password',
						'type' => 'text',
						'value' => Display::decryptValue( $credential->password ),
					),
					array(
						'id' => 'employeeName',
						'label' => 'Employee Name',
						'type' => 'text',
						'value' => $credential->employeeName,
					),
					array(
						'id' => 'notes',
						'label' => 'Notes',
						'type' => 'textarea',
						'value' => $credential->notes,
					),
					array(
						'id' => 'status',
						'label' => 'Status',
						'type' => 'select',
						'required' => true,
						'choices' => array(
								'active' => 'Active',
								'archived' => 'Archived',
						),
						'value' => $credential->status,
					),
					array(
						'id' => 'credentialId',
						'type' => 'hidden',
						'value' => $credential->credentialId,
					),
					array(
						'id' => 'a',
						'type' => 'hidden',
						'value' => 'alterCredential',
					),
				);

				Display::displayForm( 'edit_credential', $fields );

			}
			break;
	}
	exit;
}

$title = 'Credentials Vault';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Credentials Vault</h2>

	<form class="pull-right" id="status-select" method="get">
		<select id="status" name="status">
			<option value="active"<?php if( 'active' === $status ) {
				print ' selected="selected"';
			} ?>>Show active credentials
			</option>
			<option value="archived"<?php if( 'archived' === $status ) {
				print ' selected="selected"';
			} ?>>Show archived credentials
			</option>
			<option value=""<?php if( null === $status ) {
				print ' selected="selected"';
			} ?>>Show all credentials
			</option>
		</select>
	</form>

	<p>
		<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#newcredential">Add a new credential</button>
	</p>

	<?php
	$credentials = $leads->getCredentials( $status );
	if( empty( $credentials ) ) {

		print '<p>No credentials exist in the database.</p>';

	} else {
		?>

		<table class="table table-bordered table-condensed table-striped">
			<thead>
			<tr class="bgGray header">
				<th>ID</th>
				<th>Company Name</th>
				<th>URL</th>
				<th class="hidden-xs">Username</th>
				<th class="hidden-xs">Employee</th>
				<th>Options</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach( $credentials as $credential ) {
				?>
				<tr>
					<td><?php echo $credential->credentialId; ?></td>
					<td><?php echo Display::escHtml( $credential->name ); ?></td>
					<td class="text-center"><?php if( !empty( $credential->url ) ) { ?><a class="btn btn-primary btn-xs" href="<?php echo Display::escHtml( Display::decryptValue( $credential->url ) ); ?>" target="_blank">Open URL</a><?php } else { ?>&nbsp;<?php } ?></td>
					<td class="hidden-xs"><?php echo Display::escHtml( Display::decryptValue( $credential->username ) ); ?></td>
					<td class="hidden-xs"><?php echo Display::escHtml( $credential->employeeName ); ?></td>
					<td class="text-center">
						<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editcredential" data-credential-id="<?php echo $credential->credentialId; ?>">Edit</button>
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

<div class="modal fade" id="newcredential" tabindex="-1" role="dialog" aria-labelledby="newcredential_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newcredential_title">Add a new credential</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newcredential" type="button" class="btn btn-primary">Add Credential</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editcredential" tabindex="-1" role="dialog" aria-labelledby="editcredential_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editcredential_title">Edit a credential</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editcredential" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$('#modal-save-newcredential').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "credentials-vault.php",
			type: "POST",
			async: true,
			data: $("#new_credential").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#newcredential').on('show.bs.modal', function (e) {
		var modal = $(this);

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'credentials-vault.php',
			data: {
				'd': 'dialog_newcredential'
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-editcredential').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "credentials-vault.php",
			type: "POST",
			async: true,
			data: $("#edit_credential").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#editcredential').on('show.bs.modal', function (e) {
		var modal = $(this);
		var credentialId = $(e.relatedTarget).data('credential-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'credentials-vault.php',
			data: {
				'd': 'dialog_editcredential',
				'credentialId': credentialId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#newcredential, #editcredential').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});

	$('#status-select select').change(function (e) {
		e.preventDefault();
		$('#status-select').submit();
	});
</script>

</body>
</html>
