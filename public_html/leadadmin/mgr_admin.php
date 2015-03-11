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
					$timestamp = new DateTime( $log->timestamp, new DateTimeZone( DB_TIMEZONE ) );
					$timestamp->setTimezone( new DateTimeZone( LOCAL_TIMEZONE ) );

					$notes = $log->notes;
					if( !empty( $log->notes ) && 'FEEDINC:IMPORT' == $log->action ) {
						$notes = '<a href="/leadadmin/mgr_job.php?jobId=21&count=10">Job ' . $log->notes . '</a>';
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
