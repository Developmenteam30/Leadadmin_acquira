<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

$mysqlErrorSource = 'API Spec Page';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_apispec"; 
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

if(isset($_REQUEST['idFeedIn'])){ 
	$feed = getIncomingFeed($_REQUEST['idFeedIn']);
	$company = getCompany($feed->idCompany);
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
	<title><?php echo $company->name; ?> - FTP Spec</title>
	<style type="text/css">
	<!--
		body { font-family: Verdana, sans-serif; padding-bottom: 50px; }
		@page { margin: 0.79in }
		td p { margin-bottom: 0in }
		p { margin-bottom: 0.08in }
		table,td { border: 1px solid; border-collapse: collapse; padding: 5px 10px 5px 10px; }
		table thead { font-weight: bold; text-align: center; }
	-->
	</style>
</head>
<body>

<h2><?php echo CONFIG_COMPANY_NAME; ?></h2>

<h2>Lead Submission FTP Specification</h2>

<p>For: <strong><?php echo $company->name; ?></strong></p>

<hr/>

<h3>Server Information</h3>
<p>FTP Hostname: <strong><?php echo SITE_URL; ?></strong></p>
<p>FTP Username: <strong><?php echo $feed->label; ?></strong></p>
<p>FTP Password: <strong><?php echo $feed->password; ?></strong></p>

<hr/>

<h3>File Format</h3>
<p>Files must be submitted in a tab-delimited format (.txt, .tsv, or .csv).  All columns must be included in the file.  If you do not have data for a particular column, please include it with an empty value.</p>


<table>
	<thead>
	<tr>
		<td>
			<p>Field</p>
		</td>
		<td>
			<p>Type</p>
		</td>
		<td>
			<p>Required</p>
		</td>
		<td>
			<p>Format
			Requirements</p>
		</td>
		<td>
			<p>Description</p>
		</td>
	</tr>
	</thead>
	<tr>
		<td>
			<p>listcode</p>
		</td>
		<td>
			<p>varchar(20)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Campaign ID or
			List Descriptor</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>url</p>
		</td>
		<td>
			<p>varchar(500)</p>
		</td>
		<td>
			<p>Yes</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Source of the
			Lead</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>ip</p>
		</td>
		<td>
			<p>varchar(16)</p>
		</td>
		<td>
			<p>Yes</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>IP Address</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>stamp</p>
		</td>
		<td>
			<p>datetime</p>
		</td>
		<td>
			<p>Yes</p>
		</td>
		<td>
			<p>YYYY-mm-dd
			HH:ii:ss</p>
		</td>
		<td>
			<p>Lead Action Date</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>email</p>
		</td>
		<td>
			<p>varchar(150)</p>
		</td>
		<td>
			<p>Yes</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Email Address</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>fname</p>
		</td>
		<td>
			<p>varchar(50)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>First Name</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>lname</p>
		</td>
		<td>
			<p>varchar(50)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Last Name</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>addr</p>
		</td>
		<td>
			<p>varchar(150)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Address Line 1</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>addr2</p>
		</td>
		<td>
			<p>varchar(150)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>Address Line 2</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>city</p>
		</td>
		<td>
			<p>varchar(75)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p><br />
			</p>
		</td>
		<td>
			<p>City</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>state</p>
		</td>
		<td>
			<p>varchar(25)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>XX</p>
		</td>
		<td>
			<p>State</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>zip</p>
		</td>
		<td>
			<p>varchar(20)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>#####</p>
		</td>
		<td>
			<p>Zip</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>country</p>
		</td>
		<td>
			<p>char(2)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>XX</p>
		</td>
		<td>
			<p>2-letter ISO-3166 country code</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>dob</p>
		</td>
		<td>
			<p>date</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>YYYY-mm-dd</p>
		</td>
		<td>
			<p>Date of Birth</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>gender</p>
		</td>
		<td>
			<p>varchar(10)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>M, F</p>
		</td>
		<td>
			<p>Gender</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>landline</p>
		</td>
		<td>
			<p>varchar(20)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>##########</p>
		</td>
		<td>
			<p>Default Phone</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>cellphone</p>
		</td>
		<td>
			<p>varchar(20)</p>
		</td>
		<td>
			<p>No</p>
		</td>
		<td>
			<p>##########</p>
		</td>
		<td>
			<p>Alternate Phone</p>
		</td>
	</tr>
</table>

</body>
</html>

<?php } ?>
