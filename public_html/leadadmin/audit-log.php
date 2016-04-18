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
	}
	exit;
}

$title = 'Audit Logs';
include(INCLUDES."c_header.php");

?>

<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Audit Log</h2>

<?php
	$logs = $leads->getAuditLog();
	if( empty( $logs ) || !is_array( $logs ) ) {
		print "No logs found.";
	} else {
?>

<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr>
			<th>Timestamp</th>
			<th>Username</th>
			<th>IP Address</th>
			<th>Action</th>
			<th>Notes</th>
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
			} else if( !empty( $log->notes ) && 'FEEDOUT:CLEAR-QUEUE' == $log->action ) {
				$info = $leads->getJob( $log->notes );
				$notes = '<a href="/leadadmin/mgr_job.php?jobId=' . intval( $log->notes ) . '">Job ' . $log->notes . '</a>';
			} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDINC:' ) === 0 ) {
				$info = $leads->getInboundFeed( $log->notes );
				$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
			} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDOUT:POP:' ) === 0 ) {
				$info = $leads->getOutboundFeedPopulation( $log->notes );
				$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
			} else if( !empty( $log->notes ) && strpos( $log->action, 'FEEDOUT:' ) === 0 ) {
				$info = $leads->getOutboundFeed( $log->notes );
				$notes = $log->notes . ': ' . $info->label . ' (' . htmlentities( $info->description ) . ')';
			} else if( !empty( $log->notes ) && strpos( $log->action, 'USERS:' ) === 0 ) {
				$info = $leads->getUser( $log->notes );
				if( !empty( $info ) && !empty( $info->username ) ) {
					$notes = $log->notes . ': ' . $info->username;
				}
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
?>
</div>

</body>
</html>
