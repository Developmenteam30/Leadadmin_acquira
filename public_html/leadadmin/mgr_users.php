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

			$idUser = $leads->addUser( strtolower( $_REQUEST['username'] ), $_REQUEST['password'], empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'], empty( $_REQUEST['level'] ) ? 0 : $_REQUEST['level'] );
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
				'idCompany' => empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'],
				'level' => empty( $_REQUEST['level'] ) ? 0 : $_REQUEST['level'],
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
				),
				array(
					'id' => 'level',
					'label' => 'Access Level',
					'type' => 'select',
					'choices' => array(
						0 => 'No Access',
						LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Client Reporting Access',
						LEADS_SESSION_LEVEL_CLIENT_DASHBOARD => 'Client Dashboard Access',
						LEADS_SESSION_LEVEL_STAFF => 'Staff Member',
						LEADS_SESSION_LEVEL_ADMIN => 'Administrator',
					),
					required => true,
				),
				array(
					'id' => 'idCompany',
					'label' => 'Company Access',
					'type' => 'select',
					'choices' => $companyChoices,
				),
				array(
					'id' => 'submit',
					'type' => 'submit',
					'label' => 'Add new user',
				),
			);

			Display::displayForm( 'new_user', $fields, 'Add a New User' );
?>

<script type="text/javascript">
$('#new_user').submit( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_admin.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "addNewUser",
			"username": $("#new_user #username").val(),
			"password": $("#new_user #password").val(),
			"level": $("#new_user #level").val(),
			"idCompany": $("#new_user #idCompany").val(),
		}),
		success: function(data) {
			if (data.status == "1") {
				closeContent('dialog_newuser');
				display('dialog_users');
			} else {
				alert(data.error);
			}
		}
	});
});
</script>

<?php
		break;

		case "dialog_edituser":
			$idUser = $_REQUEST['options']['idUser'];
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
					'id' => 'level',
					'label' => 'Access Level',
					'type' => 'select',
					'choices' => array(
						0 => 'No Access',
						LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Client Reporting Access',
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
					'id' => 'submit',
					'type' => 'submit',
					'label' => 'Edit user',
				),
			);

			Display::displayForm( 'edit_user', $fields, 'Edit User: ' . $user->username );
?>

<script type="text/javascript">
$('#edit_user').submit( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_admin.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "editUser",
			"idUser": $("#edit_user #idUser").val(),
			"username": $("#edit_user #username").val(),
			"password": $("#edit_user #password").val(),
			"level": $("#edit_user #level").val(),
			"idCompany": $("#edit_user #idCompany").val(),
		}),
		success: function(data) {
			if (data.status == "1") {
				closeContent('dialog_edituser');
				display('dialog_users');
			} else {
				alert(data.error);
			}
		}
	});
});
</script>

<?php
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

<p><a href="#" class="btn btn-primary" onclick="display('dialog_newuser');">Add a New User</a></p>
<div class="hidden-custom" id="dialog_newuser"></div>
<div class="hidden-custom" id="dialog_edituser"></div>
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
					<th>Access Level</th>
					<th>Company Id</th>
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
					<td><a href="#" class="nonLink" onclick="display('dialog_edituser', { 'idUser': <?php echo $user->idUser; ?> } );"><?php echo $user->username; ?></a></td>
					<td><?php echo $level; ?></td>
					<td><?php echo $user->idCompany; ?></td>
				</tr>
<?php
		}
	}
?>

</div>

</body>
</html>
