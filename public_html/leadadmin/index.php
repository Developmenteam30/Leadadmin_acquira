<?php 

require_once( '../../includes/c_config.php' );
require_once( INCLUDES . 'session.php' );

if( LeadsSession::isValid( [LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF] ) ) {
	header("Location: dashboard.php");
	exit;
} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CRM ) ) {
    header("Location: crm/prospects.php?searchIsArchived=0");
    exit;
} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_IMPORT ) ) {
	header("Location: mgr_feedinc.php");
	exit;
} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS ) ) {
	header("Location: phone-leads-report.php");
	exit;
} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_REPORTS ) ) {
	header("Location: client_reports.php");
	exit;
}

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

if( isset( $_REQUEST['a'] ) ) {
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch( $_REQUEST['a'] ) {

		case 'logIn':
			$c = true;
			$result['error'] = 'Failed to log in user.';
			$expectedVars = array( 'username', 'password' );

			foreach( $expectedVars as $expectedVar ) {
				if( empty( $_REQUEST[$expectedVar] ) ) {
					$c = false;
					$result['error'] = 'Missing expected value: '.$expectedVar;
				}
				if(!$c){
					break;
				}
			}
			if($c){

				if( ( $user = $leads->verifyUser( $_REQUEST['username'], $_REQUEST['password'] ) ) !== null ) {

					$result['status'] = 1;
					$result['error'] = 'Successfully logged in.';
					LeadsSession::login( $user['idUser'], $user['accessBits'], $user['idCompany'] );

				} else {

					$c = false; $result['error'] = 'Invalid username / password.';
				}


			}

		break;
	}

	echo json_encode( $result );
	exit;
}

$title = CONFIG_COMPANY_NAME." Admin Panel";
include( INCLUDES . 'c_header.php' );
?>
<script>
function logIn(){
	var response = $.ajax({
		url: "index.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "logIn"
			, "username": $('#username').val()
			, "password": $('#password').val()
		})
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		else { //Processing was a success. Check the result.
			if(result.status == 1){
				//alert(result.error);
				location.reload();
			} else {
				alert(result.error);
			}
		}
	});
	return false;
}
</script>

<body>

<div class="container">

<form class="form-signin" onsubmit="logIn(); return false;">
	<p class="text-center"><img src="images/logo.png"/></p>
	<label for="inputEmail" class="sr-only">Email address</label>
	<input type="text" id="username" class="form-control" placeholder="Username" required autofocus>
	<label for="inputPassword" class="sr-only">Password</label>
	<input type="password" id="password" class="form-control" placeholder="Password" required>
	<button class="btn btn-lg btn-primary btn-block" type="submit">Log in</button>
</form>

</div>

</body>
</html>
