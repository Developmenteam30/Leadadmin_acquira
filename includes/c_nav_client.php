<?php
require_once( INCLUDES . 'session.php' );

$name = CONFIG_COMPANY_NAME . ' Client';
$user = $leads->getUser( LeadsSession::getUserId() );
if( !empty( $user->username ) ) {
	$name = $user->username;
}

?>
<div class="headerRow client">
	<div class="logoContainer navLogo pull-left"><img alt="logo" id="logo-reports" src="images/logo.png" /></div>
	<div class="logoutContainer pull-right">Welcome, <strong><?php echo htmlspecialchars( $name ); ?></strong>! [ <a href="logout.php">Logout</a> ]</div>
</div>
