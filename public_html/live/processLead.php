<?php
require_once("../../includes/c_config.php");
require_once(INCLUDES . 'leads.php');
require_once(INCLUDES . 'Array2XML.php');
require_once(INCLUDES . 'processLeads.php');
include(INCLUDES . "vendor/autoload.php");

use Firebase\JWT\JWT;

function showResultAndDie($result)
{
    if (!empty($_REQUEST['outFormat']) && 'json' === strtolower($_REQUEST['outFormat'])) {
        Header('Content-Type: text/json');
        echo json_encode($result);
    } else {
        Header('Content-Type: text/xml');
        $xml = Array2XML::createXML('response', $result);
        echo $xml->saveXML();
    }
    die();
}

$leads = Leads::getInstance();

$statsDay = date('Y-m-d');

$result = array(
    'success' => false,
    'reason' => 'Unknown error.',
);

$idFeedIn = getenv('FEED_LABEL');
if (empty($idFeedIn)) {
    http_response_code(400);
    $result['reason'] = "Feed id is not set.";
    showResultAndDie($result);
}

if (preg_match('/^[0-9]+$/', $idFeedIn)) { // New style uses immutable idFeedIn
    $feedParams = $leads->getInboundFeed($idFeedIn);
} elseif (preg_match('/^[a-z][a-z0-9_]*$/', $idFeedIn)) { // Old style uses feedLabel
    $feedParams = $leads->getInboundFeedLabel($idFeedIn);
} else {
    http_response_code(400);
    $result['reason'] = "Feed id contains invalid characters";
    showResultAndDie($result);
}

if ($feedParams === null) {
    http_response_code(500);
    $result['reason'] = 'Database failure, please try again later.';
    showResultAndDie($result);
} elseif (false === $feedParams) {
    http_response_code(403);
    $result['reason'] = 'Invalid feed id';
    showResultAndDie($result);
}

if (empty($_REQUEST['pswd']) || $_REQUEST['pswd'] != $feedParams->password) {
    http_response_code(403);
    $result['reason'] = 'Unauthorized access.';

    // Verbose logging for troubleshooting. Turn off once testing is complete.
    if (false) {
        $from = SYSTEM_FROM_EMAIL;
        $body = print_r($_REQUEST, true) . PHP_EOL;
        $body .= print_r($_SERVER, true) . PHP_EOL;
        $fromName = CONFIG_COMPANY_NAME . ' List Management System';
        $to = ADMINISTRATOR_EMAIL;
        $subject = 'Incoming Feed Password Mismatch';
        $header = "From:" . $fromName . " <" . $from . ">\n";
        $header .= "Content-type: text/html; charset=iso-8859-1\n";
        $header .= "Reply-To: <" . $from . ">\n";
        $header .= "Return-Path: <" . $from . ">\n";
        if (defined('GLOBAL_BCC')) {
            $header .= "BCC: " . GLOBAL_BCC . "\r\n";
        }
        $sent = @mail($to, $subject, $body, $header, "-f {$from}");
    }

    // Only log if a password was actually sent
    if (!empty($_REQUEST['pswd'])) {
        $leads->logError('Feed ' . $feedParams->label . ' Unauthorized user at ' . $_SERVER["REMOTE_ADDR"], true,
            false);
        $_REQUEST['url'] = $_REQUEST['url'] ?? ''; // Ensure a value for the URL is set
        $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null);
    }
    showResultAndDie($result);
}

if ('retired' == $feedParams->status) {
    $result['reason'] = 'This feed has been disabled.';
    $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null);
    showResultAndDie($result);
}

if (!empty($feedParams->paused)) {
    $result['reason'] = (!empty($feedParams->pauseMessage) ? $feedParams->pauseMessage : 'Lead rejected.') . ' [Status: PIF]';
    $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null);
    showResultAndDie($result);
}

if (!empty($_REQUEST['ping']) && 'phone-preping' !== $feedParams->feedCategory) {
    $result['reason'] = 'This feed is not authorized for PING access.';
    $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $result['reason'], null);
    showResultAndDie($result);
}

$validateResult = ProcessLeads::validateIncomingData($feedParams, $_REQUEST);

if (!empty($_REQUEST['ping'])) {

    if ($validateResult['valid']) {

        $payload = array(
            'iss' => 'https://' . POSTING_URL,
            'aud' => 'https://' . POSTING_URL,
            'iat' => time(),
            'nbf' => time(),
            'idFeedIn' => $feedParams->idFeedIn,
        );

        if (!empty($feedParams->pingTimeout)) {
            $payload['exp'] = time() + $feedParams->pingTimeout;
        }

        foreach ($_REQUEST as $field => $val) {
            if (in_array($field, ['pswd', 'ping'])) {
                continue;
            }
            $payload['ping_' . $field] = $val;
        }

        try {
            $result['authorization'] = JWT::encode($payload, HASH_SALT);
            $result['success'] = true;
            $result['reason'] = 'Successful ping.';
        } catch (Exception $e) {
            $result['reason'] = 'Error creating JWT';
        }

    } else {

        // Only need to log the record (and the stats record) for rejections.
        $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $validateResult['errors'][0], null);
        $result['reason'] = $validateResult['errors'][0];

    }

} else {

    if ($validateResult['valid']) {

        $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, null);
        if (null === $inboundId) {
            $result['reason'] = 'Database error while trying to add your record.';
        } else {
            $pushResponse = ProcessLeads::pushIncomingData($feedParams, $_REQUEST, $inboundId);
            if (isset($pushResponse['reason']) && $pushResponse['reason'] !== null) {
                $result['reason'] = $pushResponse['reason'];
            } else {
                $result['success'] = true;
                $result['reason'] = 'Successfully inserted new record.';
                if (!empty($pushResponse['fields']) && is_array($pushResponse['fields'])) {
                    foreach ($pushResponse['fields'] as $key => $val) {
                        $result[$key] = $val;
                    }
                }
            }
        }

    } else {

        $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $_REQUEST, $statsDay, $validateResult['errors'][0], null);
        $result['reason'] = $validateResult['errors'][0];

    }

}

showResultAndDie($result);
