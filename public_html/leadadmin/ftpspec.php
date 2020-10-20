<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

require_once(INCLUDES . 'leads.php');
require_once(INCLUDES . 'display.php');

if (empty($_REQUEST['idFeedIn'])) {
    die('ERROR: Please specify a feed id.');
}

if (empty($_REQUEST['h'])) {
    die('ERROR: Please specify the security code.');
}

$leads = Leads::getInstance();
$feed = $leads->getInboundFeed($_REQUEST['idFeedIn']);
if (empty($feed)) {
    die('ERROR: Feed not found.');
}

if ($_REQUEST['h'] !== hash('sha256', $feed->idFeedIn . HASH_SALT . $feed->password)) {
    die('ERROR: Security code is invalid.');
}

$company = $leads->getCompany($feed->idCompany);

$fields = $leads->getInboundFields();

function findField($feed, $fields, $field, $param)
{
    foreach ($fields as $key => $val) {
        if (isset($val->fieldName) && $val->fieldName == $field && isset($val->$param)) {
            if (preg_match('/^custom[1-6]$/', $field)) {
                $label = $field . 'Label';
                return $val->$param . (!empty($feed->$label) ? ': ' . $feed->$label : '');
            } elseif ('pswd' === $field && 'fieldDescription' === $param) {
                return $feed->password;
            } else {
                return $val->$param;
            }
        }
    }

    return null;
}

$requiredArray = explode(';', $feed->required);
$allowedArray = explode(';', $feed->allowedFields);

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
    foreach ($allowedArray as $allowed) {
        ?>
		<tr>
			<td><?php echo Display::escHtml($allowed); ?></td>
			<td><?php echo Display::escHtml(findField($feed, $fields, $allowed, 'fieldDefinition')); ?></td>
			<td><?php echo in_array($allowed, $requiredArray) ? 'Yes' : 'No'; ?></td>
			<td><?php echo Display::escHtml(findField($feed, $fields, $allowed, 'fieldFormat')); ?></td>
			<td><?php echo Display::escHtml(findField($feed, $fields, $allowed, 'fieldDescription')); ?></td>
		</tr>
        <?php
    }
    ?>
	</tbody>
</table>

</body>
</html>
