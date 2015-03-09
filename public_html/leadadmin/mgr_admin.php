<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['d'] ) ) {
    switch( $_REQUEST['d'] ) {
        case 'errorCount':
            Display::errorCount();
        break;

        case 'errorList':
            Display::errorList();
        break;

		case 'displayLogs':
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
					$timestamp = new DateTime( $log->timestamp, new DateTimeZone( 'UTC' ) );
					$timestamp->setTimezone( new DateTimeZone( 'America/New_York' ) );
?>
				<tr>
					<td><?php echo $timestamp->format( 'Y-m-d H:i:s' ); ?></td>
					<td><?php echo $log->username; ?></td>
					<td><?php echo $log->ipaddress; ?></td>
					<td><?php echo $log->action; ?></td>
					<td><?php echo $log->notes; ?></td>
				</tr>
<?php
				}
			}
		break;
	}
	exit;
}

$title = 'Audit Log';
include(INCLUDES."c_header.php");

?>

<body>

<script>
$(document).ready(function(){
    display( 'displayLogs' );
});
</script>

<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div class='fl' style='width: 100%;'>
		<div id='displayLogs'></div>
	</div>
	<div class='clr'></div>
</div>

</body>
</html>
