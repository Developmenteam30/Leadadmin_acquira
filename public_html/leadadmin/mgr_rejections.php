<?php 

include("../../includes/c_config.php");
$title = 'Rejection Log';
include(INCLUDES."c_header.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

?>

<body>
<div class="mainContainer">

<?php

$label = isset($_REQUEST['label']) ? $_REQUEST['label'] : '';
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$offset = isset($_REQUEST['offset']) ? $_REQUEST['offset'] : 0;
$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

if(empty($id) || empty($type)) {
	print "<p>ERROR: Invalid parameters specified.</p>\n";
} else {

	if( $type == 'inbound' ) {
		// If this a client, ensure they have access for this feed
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
		    $idCompany = LeadsSession::getCompanyId();
		    if( empty( $idCompany ) ) {
        		$idCompany = -9999;
	    	}
		    if( !$leads->checkInboundFeedAccess( $idCompany, $id ) ) {
        		die( 'Sorry, you do not have access to view this feed' );
		    }
		}

		$records = $leads->getInboundRejections( $id, $offset );
	} else if( $type == 'outbound' ) {
		// If this a client, ensure they have access for this feed
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
		    $idCompany = LeadsSession::getCompanyId();
		    if( empty( $idCompany ) ) {
        		$idCompany = -9999;
	    	}
		    if( !$leads->checkOutboundFeedAccess( $idCompany, $id ) ) {
        		die( 'Sorry, you do not have access to view this feed' );
		    }
		}

		$records = $leads->getOutboundRejections( $id, $offset );
	} else {
		print "<p>ERROR: Invalid type specified</p>\n";
	}

	if( $records === false ) {
		print "<p>ERROR: Cannot get records from database</p>\n";
	} else if ( sizeOf( $records ) == 0 ) {
		print "<p>ERROR: No rejections exist for this feed</p>\n";
	} else {
?>

<h3>Last 100 rejections for <?php echo htmlspecialchars($type); ?> feed: <?php echo htmlspecialchars($label);?></h3>

<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Error Timestamp</th>
			<th colspan="9">Error Message</th>
			<th>URL</th>
			<th>Email</th>
			<th>Lead Timestamp</th>
		</tr>
		<tr>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Address 1</th>
			<th>Address 2</th>
			<th>City</th>
			<th>State</th>
			<th>Zipcode</th>
			<th>Country</th>
			<th>DOB</th>
			<th>Gender</th>
			<th>Landline</th>
			<th>Cellphone</th>
			<th>IP Address</th>
		</tr>
	</thead>
	<tbody>
<?php		foreach($records as $record) {  ?>
	<tr>
		<td><?php echo htmlspecialchars($record['timestamp']); ?></td>
		<td class="error" colspan="9"><?php echo htmlspecialchars($record['result']); ?></td>
		<td><?php echo htmlspecialchars($record['url']); ?></td>
		<td><?php echo htmlspecialchars($record['email']); ?></td>
		<td><?php echo htmlspecialchars($record['leadstamp']); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars($record['fname']); ?></td>
		<td><?php echo htmlspecialchars($record['lname']); ?></td>
		<td><?php echo htmlspecialchars($record['addr']); ?></td>
		<td><?php echo htmlspecialchars($record['addr2']); ?></td>
		<td><?php echo htmlspecialchars($record['city']); ?></td>
		<td><?php echo htmlspecialchars($record['state']); ?></td>
		<td><?php echo htmlspecialchars($record['zip']); ?></td>
		<td><?php echo htmlspecialchars($record['country']); ?></td>
		<td><?php echo htmlspecialchars($record['dob']); ?></td>
		<td><?php echo htmlspecialchars($record['gender']); ?></td>
		<td><?php echo htmlspecialchars($record['landline']); ?></td>
		<td><?php echo htmlspecialchars($record['cellphone']); ?></td>
		<td><?php echo htmlspecialchars($record['ip']); ?></td>
	</tr>
<?php		} //foreach ?>
	</tbody>
</table>

<p><?php   printf('<a href="mgr_rejections.php?type=%s&amp;id=%s&amp;label=%s&amp;offset=%d">Next page</a>', urlencode( $type ), urlencode( $id ), urlencode( $label ), intval( $offset + 100 ) ); ?></p>
<br/>

<?php }  ?>
<?php }  ?>

</div><!-- #.mainContainer-->
</body>
</html>
