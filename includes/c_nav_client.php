<?php
require_once( INCLUDES . 'session.php' );

$name = 'Qatalyst Client';
$user = $leads->getUser( LeadsSession::getUserId() );
if( !empty( $user->username ) ) {
	$name = $user->username;
}

?>
<div class="headerRow client">
	<div class="logoContainer navLogo fl"><img alt="logo" id="logo-reports" src="images/logo.png" /></div>
	<div class="logoutContainer fr">Welcome, <strong><?php echo htmlspecialchars( $name ); ?></strong>! [ <a href="logout.php">Logout</a> ]</div>
</div>
