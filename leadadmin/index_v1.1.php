<?php 
//ADMIN_ROOT/index.php
session_start();
$mysqlErrorSource = 'Index/Login';
include("../c_config.php");
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){ 		
		case 'logIn':
			$c = true; $result['error'] = 'Failed to log in user.';
			$expectedVars = array('username', 'password');
			foreach($expectedVars as $expectedVar){ 
				if(!isset($_REQUEST[$expectedVar]) || empty($_REQUEST[$expectedVar])){ 
					$c = false; $result['error'] = 'Missing expected value: '.$expectedVar;
				}
				if(!$c){ break; }
			}
			if($c){
				$username 		= $_REQUEST['username'];
				$password 		= $_REQUEST['password'];
				
				$username = stripslashes($username);
				$password = stripslashes($password);
				dbCon();
				$username = $GLOBALS['dbconnx']->escape_string($username);
				$password = $GLOBALS['dbconnx']->escape_string($password);
				
				include("PasswordHash.php");
				$pHasher = new PasswordHash(12, false);
				$getUser = "SELECT * FROM `".DATABASE_NAME."`.`users` "
					."WHERE `username` = '". $username ."';";
				$dogetUser = dbQry($getUser, 'Fetching user for login', true);
				if($dogetUser === false) { 
					$c = false; $result['error'] = 'Could not connect to database.';
				}
			}
			if($c){ 
				if($dogetUser->num_rows == 0) { //Didn't find a matching username, check the password against the 
					//fake hash so that we match password hashing time for real requests.
					$fakeHash = "abcdefghijklmnopqrst";
					$fakeCheck = $pHasher->CheckPassword($password, $fakeHash);
					$c = false; $result['error'] = 'Invalid username / password.';
				}
			}
			if($c) { 
				//Found matching username, check the password against the stored hash
				$user = $dogetUser->fetch_object();
				$passwordHash = $user->password;
				$passwordCheckSuccess = $pHasher->CheckPassword($password, $passwordHash);
				if(!$passwordCheckSuccess){ 
					$c = false; $result['error'] = 'Invalid username / password.';
				}
			}
			if($c){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully logged in.';
				$_SESSION['idAdmin'] = $user->idUser;
				$_SESSION['token_admin'] = md5(session_id().$_SERVER['REMOTE_ADDR']);
				$_SESSION['tokenTime_admin'] = microtime(true); //Start token time in seconds.					
			}
		break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
	}
	exit;
}

if($adminLoggedIn){ 
	header("location: dashboard.php"); exit;
}

$title = CONFIG_COMPANY_NAME." Admin Panel";
include("c_header.php");
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

<div class='mainContainer'>
	<div class='logoContainer centered loginLogo'><img 
		src='images/logo.jpg'
	/></div>
	<div class='loginContainer'>
		<h1 class='boxTitle siteHeader'>Log In</h1>
		<div class='loginBox siteBorder'>
			<form action='index.php' method='post' onsubmit='logIn(); return false;'>
			<p>User:</p>
			<p class='aCenter'><input id='username' type='text' name='username' class='loginBox' /></p>
			<p>Password:</p>
			<p class='aCenter'><input id='password' type='password' name='password' class='loginBox' /></p>
			<p class='aRight'><input class='siteButton' type='submit' value='Log In' /></p>
			</form>
		</div>
	</div>
</div>

</body>
</html>