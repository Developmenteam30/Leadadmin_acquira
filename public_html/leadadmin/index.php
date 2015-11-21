<?php 

require_once( '../../includes/c_config.php' );
require_once( INCLUDES . 'session.php' );

if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ) ) {
	header("Location: dashboard.php");
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

$leads->logError( 'SUCCESS ' . print_r($user,true) );

					$result['status'] = 1;
					$result['error'] = 'Successfully logged in.';
					LeadsSession::login( $user['idUser'], $user['level'], $user['idCompany'] );

				} else {

$leads->logError( 'FAIL ');

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

<div class="mainContainer">
	<div class="centered loginLogo"><img src="images/logo.png"/></div>
	<div class="loginContainer">
		<h1 class="boxTitle siteHeader">Log In</h1>
		<div class="loginBox siteBorder">
			<form action="index.php" method="post" onsubmit="logIn(); return false;">
			<p>User:</p>
			<p class="aCenter"><input id="username" type="text" name="username" class="loginBox" /></p>
			<p>Password:</p>
			<p class="aCenter"><input id="password" type="password" name="password" class="loginBox" /></p>
			<p class="aRight"><input class="siteButton" type="submit" value="Log In" /></p>
			</form>
		</div>
	</div>
</div>

</body>
</html>
