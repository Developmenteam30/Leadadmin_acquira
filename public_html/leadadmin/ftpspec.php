<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );

if( empty( $_REQUEST['idFeedIn'] ) ) {
	die( 'ERROR: Please specify a feed id.' );
}

$leads = Leads::getInstance();
$feed = $leads->getInboundFeed( $_REQUEST['idFeedIn'] );
if( empty( $feed ) ) {
	die( 'ERROR: Feed not found.' );
}

$company = $leads->getCompany( $feed->idCompany );

$fields = array(
	'listcode' => array( 'type' => 'varchar(20)', 'format' => '', 'notes' => 'Campaign ID or List Descriptor' ),
	'leadId' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Lead ID or List Descriptor' ),
	'url' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Source of the lead' ),
	'ip' => array( 'type' => 'varchar(16)', 'format' => '', 'notes' => 'IP Address' ),
	'stamp' => array( 'type' => 'datetime', 'format' => '', 'notes' => 'Lead action date' ),
	'email' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Email address' ),
	'fname' => array( 'type' => 'varchar(50)', 'format' => '', 'notes' => 'First name' ),
	'lname' => array( 'type' => 'varchar(50)', 'format' => '', 'notes' => 'Last name' ),
	'addr' => array( 'type' => 'varchar(150)', 'format' => '', 'notes' => 'Address line 1' ),
	'addr2' => array( 'type' => 'varchar(150)', 'format' => '', 'notes' => 'Address line 2' ),
	'city' => array( 'type' => 'varchar(75)', 'format' => '', 'notes' => 'City' ),
	'state' => array( 'type' => 'varchar(25)', 'format' => 'XX', 'notes' => 'State/Province' ),
	'zip' => array( 'type' => 'varchar(120)', 'format' => '#####', 'notes' => 'Zip or Postal code' ),
	'country' => array( 'type' => 'char(2)', 'format' => 'XX', 'notes' => '2-letter ISO-3166 country code' ),
	'dob' => array( 'type' => 'date', 'format' => 'YYYY-mm-dd', 'notes' => 'Date of birth' ),
	'gender' => array( 'type' => 'char(1)', 'format' => 'M, F', 'notes' => 'Gender' ),
	'landline' => array( 'type' => 'varchar(20)', 'format' => '##########', 'notes' => 'Default phone' ),
	'cellphone' => array( 'type' => 'varchar(20)', 'format' => '##########', 'notes' => 'Alternate phone' ),
	'custom1' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 1' ),
	'custom2' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 2' ),
	'custom3' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 3' ),
	'custom4' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 4' ),
	'custom5' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 5' ),
	'custom6' => array( 'type' => 'varchar(255)', 'format' => '', 'notes' => 'Custom Field 6' ),
);

$requiredArray = explode( ';', $feed->required );
$allowedArray = explode( ';', $feed->allowedFields );

?>
<!DOCTYPE html>
<html>
<head>
	<title>FTP Specifications - <?php echo $company->name; ?></title>
	<style type="text/css">
		<!--
		body {
			font-family: Verdana, sans-serif;
			padding-bottom: 50px;
		}

		@page {
			margin: 0.79in
		}

		td p {
			margin-bottom: 0in
		}

		p {
			margin-bottom: 0.08in
		}

		table, td {
			border: 1px solid;
			border-collapse: collapse;
			padding: 5px 10px 5px 10px;
		}

		table thead {
			font-weight: bold;
			text-align: center;
		}

		-->
	</style>
</head>
<body>

<h1><?php echo CONFIG_COMPANY_NAME; ?></h1>

<h2>Lead Submission FTP Specifications</h2>

<h3>Company: <?php echo $company->name; ?> (Feed: <?php echo $feed->idFeedIn ?>)</h3>

<hr/>

<h3>Server Information</h3>
<p>FTP Hostname: <strong>ftp.<?php echo SITE_URL; ?></strong></p>
<p>FTP Username: <strong><?php echo $feed->label; ?></strong></p>
<p>FTP Password: <strong><?php echo $feed->password; ?></strong></p>

<hr/>

<h3>File Format</h3>
<p>Files must be submitted in a tab-delimited format (.txt, .tsv, or .csv). All columns must be included in the file. If you do not have data for a particular column, please include it with an empty value.</p>

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

</body>
</html>
