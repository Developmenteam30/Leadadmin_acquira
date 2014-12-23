<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );

if( empty( $_REQUEST['idFeedIn'] ) ) {
	die('ERROR: Please specify a feed id.');
}

$leads = Leads::getInstance();
$feed = $leads->getInboundFeed( $_REQUEST['idFeedIn'] );
if( empty( $feed ) ) {
	die('ERROR: Feed not found.');
}

$company = $leads->getCompany( $feed->idCompany );

$fields = array(
	'pswd' => array( 'type' => 'varchar(16)', 'format' => '', 'notes' => $feed->password ),
	'listcode' => array( 'type' => 'varchar(20)', 'format' => '', 'notes' => 'Campaign ID or List Descriptor' ),
	'url' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Source of the lead' ),
	'ip' => array( 'type' => 'varchar(16)', 'format' => '', 'notes' => 'IP Address' ),
	'stamp' => array( 'type' => 'datetime', 'format' => 'YYYY-MM-DD hh:mm:ss', 'notes' => 'Lead action date' ),
	'email' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Email address' ),
	'fname' => array( 'type' => 'varchar(50)', 'format' => '', 'notes' => 'First name' ),
	'lname' => array( 'type' => 'varchar(50)', 'format' => '', 'notes' => 'Last name' ),
	'addr' => array( 'type' => 'varchar(150)', 'format' => '', 'notes' => 'Address line 1' ),
	'addr2' => array( 'type' => 'varchar(150)', 'format' => '', 'notes' => 'Address line 2' ),
	'city' => array( 'type' => 'varchar(75)', 'format' => '', 'notes' => 'City' ),
	'state' => array( 'type' => 'varchar(25)', 'format' => 'XX', 'notes' => 'State/Province' ),
	'zip' => array( 'type' => 'varchar(120)', 'format' => '#####', 'notes' => 'Zip or Postal code' ),
	'country' => array( 'type' => 'char(2)', 'format' => 'XX', 'notes' => '2-letter ISO-3166 country code' ),
	'dob' => array( 'type' => 'date', 'format' => 'YYYY-MM-DD', 'notes' => 'Date of birth' ),
	'gender' => array( 'type' => 'char(1)', 'format' => 'M, F', 'notes' => 'Gender' ),
	'landline' => array( 'type' => 'varchar(20)', 'format' => '##########', 'notes' => 'Default phone' ),
	'cellphone' => array( 'type' => 'varchar(20)', 'format' => '##########', 'notes' => 'Alternate phone' ),
);

$requiredArray = explode( ';', 'pswd;' . $feed->required );
$allowedArray = explode( ';', 'pswd;' . $feed->allowedFields );

?>
<!DOCTYPE html>
<html lang="en-US" prefix="og: http://ogp.me/ns#">

<head>
<meta charset="UTF-8" />
<title>API Specifications - <?php echo $company->name; ?></title>
<style type="text/css">
body {
	font-family: Verdana, sans-serif;
}

table {
	border-collapse:collapse;
	page-break-after: always;
}

table td {
	border: 1px solid #000;
	padding: 5px;
}

thead td {
	font-weight: bold;
	text-align: center;
}
</style>
</head>

<body>

<h1><?php echo CONFIG_COMPANY_NAME; ?></h1>

<h2>Lead Submission API Specifications</h2>

<h3>Company: <?php echo $company->name; ?> (Feed: <?php echo $feed->idFeedIn ?>)</h3>

<p>The lead submission system works on a key-value pair submission via HTTP POST or HTTP GET. An XML response is produced after an attempt to post a lead to the system.  All submissions must use SSL over HTTPS.</p>

<h4>API Field Definitions</h4>

<p><strong>API URL:</strong> https://www.<?php echo SITE_URL; ?>/<?php echo LIVE_FOLDER; ?>/<?php echo $feed->label; ?>/livefeed.php</p>

<table>
	<thead>
	<tr>
		<td>Field</td>
		<td>Type</td>
		<td>Required</td>
		<td>Format</td>
		<td>Notes</td>
	</tr>
	</thead>
	<tbody>
<?php
	foreach( $allowedArray as $allowed ) {
?>
	<tr>
		<td><?php echo $allowed; ?></td>
		<td><?php echo $fields[$allowed]['type']; ?></td>
		<td><?php echo in_array( $allowed, $requiredArray ) ? 'Yes' : 'No'; ?></td>
		<td><?php echo $fields[$allowed]['format']; ?></td>
		<td><?php echo $fields[$allowed]['notes']; ?></td>
	</tr>
<?php
	}
?>
	</tbody>
</table>

<h4>API Responses</h4>

<h5>Valid Response Examples</h5>

<p>&lt;?xml version="1.0" encoding="UTF-8"?&gt;<br/>
&lt;response&gt;<br/>
&lt;success&gt;true&lt;/success&gt;<br/>
&lt;reason&gt;Successfully inserted new record.&lt;/reason&gt;<br/>
&lt;/response&gt;</p>

<h5>Invalid Response Examples</h5>

<p>&lt;?xml version="1.0" encoding="UTF-8"?&gt;<br/>
&lt;response&gt;<br/>
&lt;success&gt;false&lt;/success&gt;<br/>
&lt;reason&gt;Unauthorized access.&lt;/reason&gt;<br/>
&lt;/response&gt;</p>

<p>&lt;?xml version="1.0" encoding="UTF-8"?&gt;<br/>
&lt;response&gt;<br/>
&lt;success&gt;false&lt;/success&gt;<br/>
&lt;reason&gt;Email is a required field, and may not be empty.&lt;/reason&gt;<br/>
&lt;/response&gt;</p>

</body>
</html>
