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

		case 'dialog_search_email_results':
			$email = $_REQUEST['options']['email'];
?>
<p>Searching incoming feeds for <strong><?php echo htmlspecialchars( $email ); ?></strong> ...</p>
<table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th>Incoming Feed</th>
			<th>Listcode</th>
			<th>Timestamp</th>
			<th>URL</th>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Lead Timestamp</th>
			<th>IP Address</th>
			<th>DOB</th>
		</tr>
		<tr>
			<th>Address 1</th>
			<th>Address 2</th>
			<th>City</th>
			<th>State</th>
			<th>Zipcode</th>
			<th>Country</th>
			<th>Landline</th>
			<th>Cellphone</th>
			<th>Gender</th>
		</tr>
	</thead>
	<tbody>
<?php
		$records = $leads->inboundEmailSearch( $email );
		if( is_array( $records ) ) {
			foreach( $records as $record ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedIn'] ); ?>)</td>
		<td><?php echo htmlspecialchars( $record['listcode'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['url'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['fname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['lname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['leadstamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['ip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['dob'] ); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars( $record['addr'] ); ?>&nbsp;</td>
		<td><?php echo htmlspecialchars( $record['addr2'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['city'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['state'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['zip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['country'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['landline'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['cellphone'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['gender'] ); ?></td>
	</tr>
<?php
			}

		}
?>
	</tbody>
</table>

<p>Searching outgoing feeds for <strong><?php echo htmlspecialchars( $email ); ?></strong> ...</p>
<table class="table table-striped table-bordered table-condensed">
	<thead>
		<tr>
			<th>Outgoing Feed</th>
			<th>Listcode</th>
			<th>Timestamp</th>
			<th>URL</th>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Lead Timestamp</th>
			<th>IP Address</th>
			<th>DOB</th>
		</tr>
		<tr>
			<th>Address 1</th>
			<th>Address 2</th>
			<th>City</th>
			<th>State</th>
			<th>Zipcode</th>
			<th>Country</th>
			<th>Landline</th>
			<th>Cellphone</th>
			<th>Gender</th>
		</tr>
	</thead>
	<tbody>
<?php
		$records = $leads->outboundEmailSearch( $email );
		if( is_array( $records ) ) {
			foreach( $records as $record ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedOut'] ); ?>)</td>
		<td><?php echo htmlspecialchars( $record['listcode'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['url'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['fname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['lname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['leadstamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['ip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['dob'] ); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars( $record['addr'] ); ?>&nbsp;</td>
		<td><?php echo htmlspecialchars( $record['addr2'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['city'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['state'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['zip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['country'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['landline'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['cellphone'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['gender'] ); ?></td>
	</tr>
<?php
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

$title = 'Email Search';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<p>Email Address: <input type="text" name="search_email" id="search_email" value="" /> <input type="button" class="btn btn-primary" value="Search" onclick="display( 'dialog_search_email_results', { 'email': $('#search_email').val() });" /></p>

<div class="hidden-custom" id="dialog_search_email_results"></div>

</div>
</body>
</html>
