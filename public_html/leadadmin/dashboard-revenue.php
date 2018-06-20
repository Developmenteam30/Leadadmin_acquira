<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'session.php' );
if( session_status() !== PHP_SESSION_ACTIVE ) {
//	session_start();
}
//$_SESSION['level'] = LEADS_SESSION_LEVEL_MANAGER;

require_once( INCLUDES . 'display.php' );

if( !isset( $_SERVER['REMOTE_ADDR'] ) || !in_array( $_SERVER['REMOTE_ADDR'], IP_WHITELIST ) ) {
	http_response_code( 403 );
	die( sprintf( '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h3>You are not allowed to access this page. Please ask your IP (%s) to be whitelisted.</h3></body></html>',
		Display::escHtml( $_SERVER['REMOTE_ADDR'] ?? 'Unknown' )
	) );
}

$statsStart = date( 'Y-m-d' );
$statsEnd = date( 'Y-m-d' );

$title = 'Dashboard Revenue';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav_logo.php' ); ?>

<div class="container-fluid">

	<h2 class="text-center">Revenue Projections</h2>
	<h4 class="text-center">Last Updated: <?php echo date( "m/d/Y g:i:s a" ); ?></h4>

	<?php
	$users = $leads->getDashboardRevenueUsers();
	Display::displayDashboardRevenueTable( $leads, $users, $statsStart, $statsEnd, true );
	?>
</div>

<script>
	$(document).ready(function () {
		setTimeout(function () {
			location.reload();
		}, 120000);
	});
</script>

</body>
</html>
