<?php

include("../../../includes/c_config.php");

require_once(INCLUDES . 'session.php');

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_CLIENT_IMPORT, LEADS_SESSION_LEVEL_STAFF, LEADS_SESSION_LEVEL_PPC])) {
    Header('Content-Type: application/json');
    http_response_code(403);

    $result = new stdClass();
    $result->success = false;
    $result->error = 'You do not have access to this page or your session has timed out. Log back in and try again.';
    $result->preventRetry = true;
    echo json_encode($result);

    die();
}

if (empty($_REQUEST['type'])) {
    Header('Content-Type: application/json');
    http_response_code(400);

    $result = new stdClass();
    $result->success = false;
    $result->error = 'Missing "type" parameter.';
    $result->preventRetry = true;
    echo json_encode($result);

    die();
}

if (empty($_REQUEST['orderId'])) {
    Header('Content-Type: application/json');
    http_response_code(400);

    $result = new stdClass();
    $result->success = false;
    $result->error = 'Missing "orderId" parameter.';
    $result->preventRetry = true;
    echo json_encode($result);

    die();
}

if (empty($order = $leads->getInsertionOrder($_REQUEST['orderId']))) {
    Header('Content-Type: application/json');
    http_response_code(404);

    $result = new stdClass();
    $result->success = false;
    $result->error = 'Cannot find this order.';
    $result->preventRetry = true;
    echo json_encode($result);

    die();
}

$output = [];
$files = Display::findFilesRecurse(FILES_DIR . 'insertion-orders' . DIRECTORY_SEPARATOR . $order->orderId);
if (!empty($files)) {
    foreach ($files as $file) {
        $output[] = (object)[
            'name' => basename($file),
            'uuid' => basename(dirname($file)),
        ];
    }
}

echo json_encode($output);