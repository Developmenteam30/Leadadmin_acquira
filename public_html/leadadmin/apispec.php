<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_CLIENT_DASHBOARD);

require_once(INCLUDES . 'leads.php');
require_once(INCLUDES . 'display.php');

if (empty($_REQUEST['idFeedIn'])) {
    die('ERROR: Please specify a feed id.');
}

$leads = Leads::getInstance();
// If this a client, ensure they have access for this feed
if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_STAFF)) {
    $idCompany = LeadsSession::getCompanyId();
    if (empty($idCompany)) {
        $idCompany = -9999;
    }
    if (!$leads->checkInboundFeedAccess($idCompany, $_REQUEST['idFeedIn'])) {
        die('Sorry, you do not have access to view this feed');
    }
}

$feed = $leads->getInboundFeed($_REQUEST['idFeedIn']);
if (empty($feed)) {
    die('ERROR: Feed not found.');
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
            } else {
                return $val->$param;
            }
        }
    }

    return null;
}

$requiredArray = explode(';', 'pswd;' . $feed->required);
$allowedArray = explode(';', 'pswd;' . $feed->allowedFields);

?>
<!DOCTYPE html>
<html lang="en-US" prefix="og: http://ogp.me/ns#">

<head>
	<meta charset="UTF-8"/>
	<title>API Specifications - <?php echo $company->name; ?></title>
	<style type="text/css">
		body {
			font-family: Verdana, sans-serif;
		}

		table {
			border-collapse: collapse;
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

<p>The lead submission system works on a key-value pair submission via HTTP POST or HTTP GET. An XML response is produced after an attempt to post a lead to the system. All submissions must use SSL over HTTPS.</p>

<h4>API Field Definitions</h4>

<p><strong>API URL:</strong> https://www.<?php echo SITE_URL; ?>/<?php echo LIVE_FOLDER; ?>/<?php echo $feed->idFeedIn; ?>/livefeed.php</p>

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
