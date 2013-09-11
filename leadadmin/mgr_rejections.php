<?php 
//ADMIN_ROOT/mgr_companies.php
//Version 1.0
//ES20130722 Version 1.0: Company manager created.
session_start();
$mysqlErrorSource = 'Manager - Rejections';
include("../c_config.php");
$forceMysqlLogFile = SITE_ROOT."error".FD."log_companies"; 
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.
$title = 'Dashboard';
include("c_header.php");

function getInboundRejections( $label ) {
	dbCon();
	$query = "SELECT received as timestamp,error,stamp,listcode,url,fname,lname,addr,addr2,city,state,zip,country,dob,gender,landline,cellphone,email,ip ";
	$query.= "FROM `" . DATABASE_NAME . "`.`feedinc_" . $GLOBALS['dbconnx']->escape_string( $label ) . "_invalid` ";
	$query.= "ORDER BY received DESC ";
	$query.= "LIMIT 100";

	$result = dbQry( $query, 'Getting inbound rejections', true );
	dbDcon();
	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
	$values = array();
	while( $row = $result->fetch_object() ){
		$values[] = $row;
	}
	return $values;
}

function getOutboundRejections( $label ) {
	dbCon();
	$query = "SELECT o.poststamp as timestamp,o.postresponse as error,o.stamp,o.listcode,o.url,o.fname,o.lname,o.addr,o.addr2,o.city,o.state,o.zip,o.country,o.dob,o.gender,o.landline,o.cellphone,o.email,o.ip ";
	$query.= "FROM `" . DATABASE_NAME . "`.`feedout_" . $GLOBALS['dbconnx']->escape_string( $label ) . "` o, `" . DATABASE_NAME . "`.feedout f ";
	$query.= "WHERE f.label = '" . $GLOBALS['dbconnx']->escape_string( $label ) . "' ";
	$query.= "AND o.processed = '1' ";
	$query.= "AND o.postresponse NOT LIKE CONCAT('%',f.successString,'%') ";
	$query.= "ORDER BY o.poststamp DESC ";
	$query.= "LIMIT 100";

	$result = dbQry($query, 'Getting outbound rejections', true);
	dbDcon();
	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
	$values = array();
	while( $row = $result->fetch_object() ){
		$values[] = $row;
	}
	return $values;
}

?>

<body>
<div class="mainContainer">

<?php

$label = isset($_REQUEST['label']) ? $_REQUEST['label'] : '';
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';

if(empty($label) || empty($type)) {
	print "<p>ERROR: Invalid parameters specified.</p>\n";
} else {

	if( $type == 'inbound' ) {
		$records = getInboundRejections( $label );
	} else if( $type == 'outbound' ) {
		$records = getOutboundRejections( $label );
	} else {
		print "<p>ERROR: Invalid type specified</p>\n";
	}

	if( $records === false ) {
		print "<p>ERROR: Cannot get records from database</p>\n";
	} else if ( $records == 0 ) {
		print "<p>ERROR: No rejections exist for this feed</p>\n";
	} else {
?>

<h3>Last 100 rejections for <?php echo htmlspecialchars($type); ?> feed: <?php echo htmlspecialchars($label);?></h3>

<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Timestamp</th>
			<th colspan="10">Error Message</th>
			<th>URL</th>
			<th>Email</th>
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
		<td><?php echo htmlspecialchars($record->timestamp); ?></td>
		<td class="error" colspan="10"><?php echo htmlspecialchars($record->error); ?></td>
		<td><?php echo htmlspecialchars($record->url); ?></td>
		<td><?php echo htmlspecialchars($record->email); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars($record->fname); ?></td>
		<td><?php echo htmlspecialchars($record->lname); ?></td>
		<td><?php echo htmlspecialchars($record->addr); ?></td>
		<td><?php echo htmlspecialchars($record->addr2); ?></td>
		<td><?php echo htmlspecialchars($record->city); ?></td>
		<td><?php echo htmlspecialchars($record->state); ?></td>
		<td><?php echo htmlspecialchars($record->zip); ?></td>
		<td><?php echo htmlspecialchars($record->country); ?></td>
		<td><?php echo htmlspecialchars($record->dob); ?></td>
		<td><?php echo htmlspecialchars($record->gender); ?></td>
		<td><?php echo htmlspecialchars($record->landline); ?></td>
		<td><?php echo htmlspecialchars($record->cellphone); ?></td>
		<td><?php echo htmlspecialchars($record->ip); ?></td>
	</tr>
<?php		} //foreach ?>
	</tbody>
</table>

<?php }  ?>
<?php }  ?>

</div><!-- #.mainContainer-->
</body>
</html>
