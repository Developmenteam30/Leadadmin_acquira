<?php

include("../../../includes/c_config.php");

require_once(INCLUDES . 'display.php');

set_time_limit(0);

include(INCLUDES . "f_site.php");
include(INCLUDES . "vendor/autoload.php");

$token = $_REQUEST['token'] ?? '';

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;

Display::sendNoCacheHeaders();

if (empty($token)) {
    showError("The file download link you were provided is invalid.");
    exit;
}

function showError($error)
{
    ?>

    <div class="container mt-2 mb-2">

        <h1>File Download Error</h1>

        <p><?php echo $error; ?></p>

    </div>

    <?php
}

try {
    $decoded = JWT::decode($token, HASH_SALT, array('HS256'));

    if (empty($decoded->file)) {
        showError("The file download link you were provided does not contain a file.");
        exit;
    }

    $baseDir = realpath(FILES_DIR);
    $filePath = realpath(FILES_DIR . $decoded->file);

    // realpath() verifies that the file actually exists
    if (empty($filePath)) {
        showError("The file download link you were provided has been deleted.");
        exit;
    }

    // Ensure the final path is within our base "files" directory to prevent reading sensitive files.
    if (!preg_match('~^' . $baseDir . '~', $filePath)) {
        showError("The file download link you were provided contains an invalid file.");
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));

    $file = @fopen($filePath, "rb");
    while (!feof($file)) {
        print(@fread($file, 1024 * 8));
        ob_flush();
        flush();
    }

} catch (SignatureInvalidException $e) {
    showError("We could not verify the file download link you provided. Please try copy and pasting the original link in your browser.");
} catch (ExpiredException $e) {
    showError("The file download link you were provided has expired. Download links are only valid for 30 days. Please contact the original sender for a new link.");
} catch (UnexpectedValueException $e) {
    showError("There was an error validating your file download link.");
} catch (Exception $e) {
    showError("There was an error validating your file download link.");
}