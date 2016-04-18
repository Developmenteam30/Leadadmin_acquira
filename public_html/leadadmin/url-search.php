<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

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

		case 'dialog_search_url_results':
			$url = $_REQUEST['options']['url'];
?>
<p>Searching incoming feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th>Incoming feed</th>
			<th>Total records</th>
			<th>Last record received on</th>
		</tr>
	</thead>
	<tbody>
<?php
			$records = $leads->inboundURLSearch( $url );
			if( is_array( $records ) ) {
				foreach( $records as $record ) {
					if( $record['cnt'] > 0 ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedIn'] ); ?>)</td>
		<td><?php echo number_format( htmlspecialchars( $record['cnt'] ) ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
	</tr>
<?php
					}
				}

			}
?>
	</tbody>
</table>

<p>Searching outgoing feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th>Outgoing feed</th>
			<th>Total records</th>
			<th>Last record sent on</th>
		</tr>
	</thead>
	<tbody>
<?php
			$records = $leads->outboundURLSearch( $url );
			if( is_array( $records ) ) {
				foreach( $records as $record ) {
					if( $record['cnt'] > 0 ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedOut'] ); ?>)</td>
		<td><?php echo number_format( htmlspecialchars( $record['cnt'] ) ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
	</tr>
<?php
					}
				}
			}
?>
	</tbody>
</table>
<?php
		break;

	}
	exit;
}

$title = 'URL Search';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<p>URL: <input type="text" name="search_email" id="search_url" value="" /> <input class="btn btn-primary" type="button" value="Search" onclick="display( 'dialog_search_url_results', { 'url': $('#search_url').val() });" /></p>

<div class="hidden-custom" id="dialog_search_url_results"></div>

</div>
</body>
</html>
