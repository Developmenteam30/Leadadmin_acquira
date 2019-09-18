<?php

/**
 * PHP Server-Side Example for Fine Uploader (traditional endpoint handler).
 * Maintained by Widen Enterprises.
 *
 * This example:
 *  - handles chunked and non-chunked requests
 *  - supports the concurrent chunking feature
 *  - assumes all upload requests are multipart encoded
 *  - supports the delete file feature
 *
 * Follow these steps to get up and running with Fine Uploader in a PHP environment:
 *
 * 1. Setup your client-side code, as documented on http://docs.fineuploader.com.
 *
 * 2. Copy this file and handler.php to your server.
 *
 * 3. Ensure your php.ini file contains appropriate values for
 *    max_input_time, upload_max_filesize and post_max_size.
 *
 * 4. Ensure your "chunks" and "files" folders exist and are writable.
 *    "chunks" is only needed if you have enabled the chunking feature client-side.
 *
 * 5. If you have chunking enabled in Fine Uploader, you MUST set a value for the `chunking.success.endpoint` option.
 *    This will be called by Fine Uploader when all chunks for a file have been successfully uploaded, triggering the
 *    PHP server to combine all parts into one file. This is particularly useful for the concurrent chunking feature,
 *    but is now required in all cases if you are making use of this PHP example.
 */

include("../../../includes/c_config.php");

require_once(INCLUDES . 'session.php');

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_CLIENT_IMPORT)) {
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

if ('insertion-order-update' === $_REQUEST['type']) {
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
}

// Include the upload handler class
require_once(INCLUDES . "UploadHandler.php");

$uploader = new UploadHandler();

switch ($_REQUEST['type']) {
	case 'feedinc':
	case 'upload-outbound':
        $uploader->allowedExtensions = array('csv', 'txt');
        break;

    case 'insertion-order-add':
    case 'insertion-order-update':
        $uploader->allowedExtensions = array('gif', 'jpg', 'jpeg', 'png');
        break;

    default:
        $uploader->allowedExtensions = array('xxxxxxx');
        break;
}

$uploadsDir = UPLOADS_DIR;
if ('insertion-order-update' === $_REQUEST['type']) {
    $uploadsDir = FILES_DIR . 'insertion-orders' . DIRECTORY_SEPARATOR . $order->orderId;
    if (!file_exists($uploadsDir)) {
        mkdir($uploadsDir);
    }
}

// Specify max file size in bytes.
$uploader->sizeLimit = 500000000;

// Specify the input name set in the javascript.
$uploader->inputName = "qqfile"; // matches Fine Uploader's default inputName value by default

// If you want to use the chunking/resume feature, specify the folder to temporarily save parts.
$uploader->chunksFolder = UPLOADS_DIR . 'chunks';

$method = get_request_method();

// This will retrieve the "intended" request method.  Normally, this is the
// actual method of the request.  Sometimes, though, the intended request method
// must be hidden in the parameters of the request.  For example, when attempting to
// delete a file using a POST request. In that case, "DELETE" will be sent along with
// the request in a "_method" parameter.
function get_request_method()
{
    global $HTTP_RAW_POST_DATA;

    if (isset($HTTP_RAW_POST_DATA)) {
        parse_str($HTTP_RAW_POST_DATA, $_POST);
    }

    if (isset($_POST["_method"]) && $_POST["_method"] != null) {
        return $_POST["_method"];
    }

    return $_SERVER["REQUEST_METHOD"];
}

if ($method == "POST") {
    header("Content-Type: text/plain");

    // Assumes you have a chunking.success.endpoint set to point here with a query parameter of "done".
    // For example: /myserver/handlers/endpoint.php?done
    if (!empty($_REQUEST["done"])) {
        $result = $uploader->combineChunks($uploadsDir);

        if ('insertion-order-add' === $_REQUEST['type'] && !empty($_REQUEST['uid']) && $result['success']) {
            $_SESSION['insertionOrderFiles'][$_REQUEST['uid']][$result['uuid']] = true;
        }
    } // Handles upload requests
    else {
        // Call handleUpload() with the name of the folder, relative to PHP's getcwd()
        $result = $uploader->handleUpload($uploadsDir);

        // To return a name used for uploaded file you can use the following line.
        $result["uploadName"] = $uploader->getUploadName();

        if ('insertion-order-add' === $_REQUEST['type'] && !empty($_REQUEST['uid']) && $result['success'] && !empty($result['uploadName'])) {
            $_SESSION['insertionOrderFiles'][$_REQUEST['uid']][$result['uuid']] = true;
        }
    }

    echo json_encode($result);
} elseif ($method == "DELETE") {
    $result = $uploader->handleDelete($uploadsDir);
    if ('insertion-order-add' === $_REQUEST['type'] && !empty($_REQUEST['uid']) && $result['success'] && isset($_SESSION['insertionOrderFiles'][$_REQUEST['uid']][$result['uuid']])) {
        unset($_SESSION['insertionOrderFiles'][$_REQUEST['uid']][$result['uuid']]);
    }
    echo json_encode($result);
} else {
    header("HTTP/1.0 405 Method Not Allowed");
}