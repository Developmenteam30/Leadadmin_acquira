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

		case "alterCompany":
			$c = true;
			$result['error'] = 'Failed when trying to edit a company';

			if($c){
				if( $leads->checkCompanyName( $_REQUEST['name'], $_REQUEST['idCompany'] ) ) {
					$c = false;
					$result['error'] = 'Company already exists in the database.';
				}
			}

			if($c){
				$alterCompanyResult = $leads->updateCompany( $_REQUEST['idCompany'], array(
					'name' => $_REQUEST['name'],
					'note' => empty( $_REQUEST['note'] ) ? null : $_REQUEST['note'],
					'url' => empty( $_REQUEST['url'] ) ? null : $_REQUEST['url'],
					'address' => empty( $_REQUEST['address'] ) ? null : $_REQUEST['address'],
					'city' => empty( $_REQUEST['city'] ) ? null : $_REQUEST['city'],
					'state' => empty( $_REQUEST['state'] ) ? null : $_REQUEST['state'],
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : $_REQUEST['zipcode'],
					'main_name' => empty( $_REQUEST['main_name'] ) ? null : $_REQUEST['main_name'],
					'main_phone' => empty( $_REQUEST['main_phone'] ) ? null : $_REQUEST['main_phone'],
					'main_email' => empty( $_REQUEST['main_email'] ) ? null : $_REQUEST['main_email'],
					'acct_name' => empty( $_REQUEST['acct_name'] ) ? null : $_REQUEST['acct_name'],
					'acct_phone' => empty( $_REQUEST['acct_phone'] ) ? null : $_REQUEST['acct_phone'],
					'acct_email' => empty( $_REQUEST['acct_email'] ) ? null : $_REQUEST['acct_email'],
					'tech_name' => empty( $_REQUEST['tech_name'] ) ? null : $_REQUEST['tech_name'],
					'tech_phone' => empty( $_REQUEST['tech_phone'] ) ? null : $_REQUEST['tech_phone'],
					'tech_email' => empty( $_REQUEST['tech_email'] ) ? null : $_REQUEST['tech_email'],
				) );

				if($alterCompanyResult === false){
					$c = false;
					$result['error'] = 'Database failure, could not alter company.';
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully altered new company.';
			}
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

		case 'menu':
?>

<p><a href="#" class="nonLink" onclick="display('dialog_auditlog'); closeContent('dialog_users');">Audit Log</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_users'); closeContent('dialog_auditlog');">User Management</a></p>
<div class="hidden" id="dialog_auditlog"></div>
<div class="hidden" id="dialog_users"></div>

<?php
		break;

		case 'dialog_auditlog':
			$logs = $leads->getAuditLog();
			if( empty( $logs ) || !is_array( $logs ) ) {
				print "No logs found.";
			} else {
?>
		<table class="standard" id="jobs">
			<thead>
				<tr>
					<td>Timestamp</td>
					<td>Username</td>
					<td>IP Address</td>
					<td>Action</td>
					<td>Notes</td>
				</tr>
			</thead>
			<tbody>
<?php
				foreach( $logs as $log ) {
					$timestamp = new DateTime( $log->timestamp, new DateTimeZone( DB_TIMEZONE ) );
					$timestamp->setTimezone( new DateTimeZone( LOCAL_TIMEZONE ) );

					$notes = $log->notes;
					if( !empty( $log->notes ) && 'FEEDINC:IMPORT' == $log->action ) {
						$info = $leads->getJob( $log->notes );
						$notes = '<a href="/leadadmin/mgr_job.php?jobId=' . intval( $log->notes ) . '&amp;count=' . intval( $info->records ) . '">Job ' . $log->notes . '</a>';
					} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDINC:' ) === 0 ) {
						$info = $leads->getInboundFeed( $log->notes );
						$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
					} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDOUT:POP:' ) === 0 ) {
						$info = $leads->getOutboundFeedPopulation( $log->notes );
						$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
					} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDOUT:' ) === 0 ) {
						$info = $leads->getOutboundFeed( $log->notes );
						$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
					}

?>
				<tr>
					<td><?php echo $timestamp->format( 'Y-m-d H:i:s' ); ?></td>
					<td><?php echo $log->username; ?></td>
					<td><?php echo $log->ipaddress; ?></td>
					<td><?php echo $log->action; ?></td>
					<td><?php echo $notes; ?></td>
				</tr>
<?php
				}
			}
		break;

		case 'dialog_users':
?>
<p><a href="#" class="nonLink" onclick="display('dialog_newuser');">Add a New User</a></p>
<div class="hidden" id="dialog_newuser"></div>
<?php
			$users = $leads->getUsers();
			if( empty( $users ) || !is_array( $users ) ) {
				print "No users found.";
			} else {
?>
		<table class="standard" id="jobs">
			<thead>
				<tr>
					<td>Username</td>
					<td>Access Level</td>
					<td>Company Id</td>
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
					<td><?php echo $user->username; ?></td>
					<td><?php echo $level; ?></td>
					<td><?php echo $user->idCompany; ?></td>
				</tr>
<?php
				}
			}
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

	}
	exit;
}

$title = 'Administration Menu';
include(INCLUDES."c_header.php");

?>

<body>

<script>
$(document).ready(function(){
	display( 'menu' );
});
</script>

<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div class='fl' style='width: 100%;'>
		<div id='menu'></div>
	</div>
	<div class='clr'></div>
</div>

</body>
</html>
