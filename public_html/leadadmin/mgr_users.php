<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addNewUser":
			$result['error'] = 'Failed when trying to add a new user';

			if( empty( $_REQUEST['username'] ) ) {
				$result['error'] = 'The username field cannot be blank.';
				break;
			}

			if( !ctype_alnum( $_REQUEST['username'] ) ) {
				$result['error'] = 'The username may only contain alphanumeric characters.';
				break;
			}

			if( $leads->getUsername( $_REQUEST['username'] ) ) {
				$result['error'] = 'Username already exists in the database.';
				break;
			}

			if( empty( $_REQUEST['password'] ) || strlen( $_REQUEST['password'] ) < 8 ) {
				$result['error'] = 'Password must be at least 8 characters.';
				break;
			}

			if( ( LEADS_SESSION_LEVEL_CLIENT_REPORTS == $_REQUEST['level'] || LEADS_SESSION_LEVEL_CLIENT_DASHBOARD == $_REQUEST['level'] ) && empty( $_REQUEST['idCompany'] ) ) {
				$result['error'] = 'Please associate this user with a company.';
				break;
			}

			// Do not set a company for staff members and higher
			if( $_REQUEST['level'] >= LEADS_SESSION_LEVEL_STAFF ) {
				$_REQUEST['idCompany'] = null;
			}

			$idUser = $leads->addUser( strtolower( trim( $_REQUEST['username'] ) ), $_REQUEST['password'], empty( trim( $_REQUEST['fullName'] ) ) ? null : trim( $_REQUEST['fullName'] ), empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'], empty( $_REQUEST['level'] ) ? 0 : $_REQUEST['level'], empty( trim( $_REQUEST['email'] ) ) ? null : trim( $_REQUEST['email'] ) );
			if( null === $idUser ) {
				$result['error'] = 'Unable to add new user';
				break;
			}

			$message  = "\r\n";
			$message .= "A new user was created in the " . CONFIG_COMPANY_NAME . " System.\r\n";
			$message .= "\r\n";
			$message .= "Username: " . $_REQUEST['username'] . "\r\n";
			$message .= "Password: " . $_REQUEST['password'] . "\r\n";
			$message .= "\r\n";

			mail( OWNER_EMAIL, CONFIG_COMPANY_NAME . ' User Added', $message, 'From: lmsalerts@'.SITE_URL . "\r\nBCC: " . ADMINISTRATOR_EMAIL, '-f' . 'lmsalerts@'.SITE_URL );

			$leads->auditLog( 'USERS:ADD', $idUser );

			$result['status'] = 1;
			$result['error'] = 'Successfully added new user.';

		break;

		case "editUser":
			$result['error'] = 'Failed when trying to edit user';

			if( ( $user = $leads->getUser( $_REQUEST['idUser'] ) ) === null ) {
				$result['error'] = 'Cannot find that userId in the database.';
				break;
			}

			if( !empty( $_REQUEST['password'] ) && strlen( $_REQUEST['password'] ) < 8 ) {
				$result['error'] = 'Password must be at least 8 characters.';
				break;
			}

			if( ( LEADS_SESSION_LEVEL_CLIENT_REPORTS == $_REQUEST['level'] || LEADS_SESSION_LEVEL_CLIENT_DASHBOARD == $_REQUEST['level'] ) && empty( $_REQUEST['idCompany'] ) ) {
				$result['error'] = 'Please associate this user with a company.';
				break;
			}

			// Do not set a company for staff members and higher
			if( $_REQUEST['level'] >= LEADS_SESSION_LEVEL_STAFF ) {
				$_REQUEST['idCompany'] = null;
			}

			if( !empty( $_REQUEST['password'] ) ) {
				$leads->setPasswordHash( $user->username, $_REQUEST['password'] );
			}

			$status = $leads->updateUser( $_REQUEST['idUser'], array(
				'fullName' => empty( $_REQUEST['fullName'] ) ? null : $_REQUEST['fullName'],
				'idCompany' => empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'],
				'level' => empty( $_REQUEST['level'] ) ? 0 : $_REQUEST['level'],
				'email' => empty( $_REQUEST['email'] ) ? null : $_REQUEST['email'],
			) );
			if( null === $status ) {
				$result['error'] = 'Unable to edit user';
				break;
			}

/*
			$message  = "\r\n";
			$message .= "A new user was created in the " . CONFIG_COMPANY_NAME . " System.\r\n";
			$message .= "\r\n";
			$message .= "Username: " . $_REQUEST['username'] . "\r\n";
			$message .= "Password: " . $_REQUEST['password'] . "\r\n";
			$message .= "\r\n";

			mail( OWNER_EMAIL, CONFIG_COMPANY_NAME . ' User Added', $message, 'From: lmsalerts@'.SITE_URL . "\r\nBCC: " . ADMINISTRATOR_EMAIL, '-f' . 'lmsalerts@'.SITE_URL );
*/

			$leads->auditLog( 'USERS:EDIT', $_REQUEST['idUser'] );

			$result['status'] = 1;
			$result['error'] = 'Successfully edit user account.';

		break;

	}
	echo json_encode($result);
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

		case "dialog_newuser":

			$companyChoices = array();
			$companies = $leads->getCompanies();
			foreach( $companies as $company ) {
				$companyChoices[$company->idCompany] = $company->name;
			}

			$fields = array(
				array(
					'id' => 'username',
					'label' => 'Username',
					'type' => 'text',
					'required' => true,
				),
				array(
					'id' => 'password',
					'label' => 'Password (8 chars)',
					'type' => 'text',
					'value' => substr( base64_encode(mcrypt_create_iv(64, MCRYPT_DEV_URANDOM ) ), 0, 12 ),
					'required' => true,
				),
				array(
					'id' => 'fullName',
					'label' => 'Full Name',
					'type' => 'text',
				),
				array(
					'id' => 'email',
					'label' => 'Email Address',
					'type' => 'email',
				),
				array(
					'id' => 'level',
					'label' => 'Access Level',
					'type' => 'select',
					'choices' => array(
						0 => 'No Access',
						LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Client Reporting Access',
						LEADS_SESSION_LEVEL_CLIENT_IMPORT => 'Client Import Access',
						LEADS_SESSION_LEVEL_CLIENT_DASHBOARD => 'Client Dashboard Access',
						LEADS_SESSION_LEVEL_STAFF => 'Staff Member',
						LEADS_SESSION_LEVEL_ADMIN => 'Administrator',
					),
					'required' => true,
				),
				array(
					'id' => 'idCompany',
					'label' => 'Company Access',
					'type' => 'select',
					'choices' => $companyChoices,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewUser',
				),
			);

			Display::displayForm( 'new_user', $fields );

		break;

		case "dialog_edituser":
			$idUser = $_REQUEST['userId'];
			$user = $leads->getUser( $idUser );
			if( empty( $user ) ) {
?>
<p>There is no user that exists by that ID.</p>
<?php
			} else {

			$companyChoices = array();
			$companies = $leads->getCompanies();
			foreach( $companies as $company ) {
				$companyChoices[$company->idCompany] = $company->name;
			}

			$fields = array(
				array(
					'id' => 'username',
					'type' => '_text',
					'label' => 'Username',
					'value' => $user->username,
				),
				array(
					'id' => 'idUser',
					'type' => 'hidden',
					'value' => $idUser,
				),
				array(
					'id' => 'password',
					'label' => 'Password (8 chars)',
					'type' => 'text',
				),
				array(
					'id' => 'fullName',
					'label' => 'Full Name',
					'type' => 'text',
					'value' => $user->fullName,
				),
				array(
					'id' => 'email',
					'label' => 'Email Address',
					'type' => 'email',
					'value' => $user->email,
				),
				array(
					'id' => 'level',
					'label' => 'Access Level',
					'type' => 'select',
					'choices' => array(
						0 => 'No Access',
						LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Client Reporting Access',
						LEADS_SESSION_LEVEL_CLIENT_IMPORT => 'Client Import Access',
						LEADS_SESSION_LEVEL_CLIENT_DASHBOARD => 'Client Dashboard Access',
						LEADS_SESSION_LEVEL_STAFF => 'Staff Member',
						LEADS_SESSION_LEVEL_ADMIN => 'Administrator',
					),
					'required' => true,
					'value' => $user->level,
				),
				array(
					'id' => 'idCompany',
					'label' => 'Company Access',
					'type' => 'select',
					'choices' => $companyChoices,
					'value' => $user->idCompany,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'editUser',
				),
			);

			Display::displayForm( 'edit_user', $fields );

		}
		break;

	}
	exit;
}

$title = 'User Management';
include(INCLUDES."c_header.php");

?>

<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>User Management</h2>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newuser">Add a new user</button></p>

<?php
	$users = $leads->getUsers();
	if( empty( $users ) || !is_array( $users ) ) {
		print "No users found.";
	} else {
?>
		<table class="table table-bordered table-condensed table-striped">
			<thead>
				<tr>
					<th>Username</th>
					<th>Full Name</th>
					<th>Access Level</th>
					<th>Company Id</th>
					<th>Options</th>
				</tr>
			</thead>
			<tbody>
<?php
				foreach( $users as $user ) {

					$level = $user->level;
					if( LEADS_SESSION_LEVEL_ADMIN == $level ) {
						$level = 'Administrator';
					} else if( LEADS_SESSION_LEVEL_STAFF == $level ) {
						$level = 'Staff Member';
					} else if( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD == $level ) {
						$level = 'Client Dashboard';
					} else if( LEADS_SESSION_LEVEL_CLIENT_IMPORT == $level ) {
						$level = 'Client Import';
					} else if( LEADS_SESSION_LEVEL_CLIENT == $level ) {
						$level = 'Client Reporting';
					} else if( 0 == $level ) {
						$level = 'No Access';
					}

					if( !empty( $user->idCompany ) ) {
						$company = $leads->getCompany( $user->idCompany );
						$user->idCompany = $company->name . ' (' . $company->idCompany . ')';
					}

?>
				<tr>
					<td><?php echo htmlentities( $user->username ); ?></td>
					<td><?php echo htmlentities( $user->fullName ); ?></td>
					<td><?php echo $level; ?></td>
					<td><?php echo $user->idCompany; ?></td>
					<td class="text-center"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#edituser" data-user-id="<?php echo $user->idUser; ?>">Edit</button></td>
				</tr>
<?php
		}
	}
?>

</div>

<div class="modal fade" id="newuser" tabindex="-1" role="dialog" aria-labelledby="newuser_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newuser_title">Add a new user</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newuser" type="button" class="btn btn-primary">Add User</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="edituser" tabindex="-1" role="dialog" aria-labelledby="edituser_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="edituser_title">Edit a user</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-edituser" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newuser').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_users.php",
		type: "POST",
		async: true,
		data: $("#new_user").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newuser').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_users.php',
		data: {
			'd': 'dialog_newuser'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-edituser').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_users.php",
		type: "POST",
		async: true,
		data: $("#edit_user").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#edituser').on('show.bs.modal', function(e) {
	var modal = $(this);
	var userId = $(e.relatedTarget).data('user-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_users.php',
		data: {
			'd': 'dialog_edituser',
			'userId': userId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newuser, #edituser').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});
</script>

</body>
</html>
