<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess([LEADS_SESSION_LEVEL_CLIENT_IMPORT, LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF]);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

// PHPSPREADSHEET
include("../../includes/vendor/autoload.php");

function dieError($error)
{
    print "<p>{$error}</p>";
    print "\t</div>\n";
    print "</div>\n";
    print "</body>\n";
    print "</html>\n";
    @ob_flush();
    @flush();
    exit;
}

ini_set("auto_detect_line_endings", true);

// Turn off output buffering
ini_set('output_buffering', 'off');

// Turn off PHP output compression
ini_set('zlib.output_compression', false);

set_time_limit(0);

if (isset($_REQUEST['d'])) {
    switch ($_REQUEST['d']) {
        case 'errorCount':
            Display::errorCount();
            break;

        case 'errorList':
            Display::errorList();
            break;
    }
    exit;
}

$title = 'Upload File';
include(INCLUDES . "c_header.php");
?>

    <body>

    <div class='mainContainer'>
        <?php include(INCLUDES . 'c_nav.php'); ?>
        <div style='margin: auto;'>

            <?php

            if (!isset($_REQUEST['destination'])) {
                dieError('No destination supplied');
            }

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_STAFF,LEADS_SESSION_LEVEL_PPC])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $_REQUEST['destination'])) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            if (!isset($_FILES['import_file']['error'])) {
                dieError('Cannot determine file error code.');
            }

            if (UPLOAD_ERR_INI_SIZE == $_FILES['import_file']['error'] || UPLOAD_ERR_FORM_SIZE == $_FILES['import_file']['error']) {
                dieError('File size cannot exceed ' . (MAX_UPLOAD_SIZE / 1024000) . 'MB');
            } else {
                if (UPLOAD_ERR_PARTIAL == $_FILES['import_file']['error']) {
                    dieError('The file upload was interrupted.');
                } else {
                    if (UPLOAD_ERR_NO_TMP_DIR == $_FILES['import_file']['error']) {
                        dieError('The temporary folder is missing. Contact Ryan.');
                    } else {
                        if (UPLOAD_ERR_NO_TMP_DIR == $_FILES['import_file']['error']) {
                            dieError('The temporary folder is missing. Contact Ryan.');
                        } else {
                            if (UPLOAD_ERR_CANT_WRITE == $_FILES['import_file']['error']) {
                                dieError('Cannot write to the temporary folder. Contact Ryan.');
                            } else {
                                if (UPLOAD_ERR_EXTENSION == $_FILES['import_file']['error']) {
                                    dieError('A PHP extension stopped the file upload. Contact Ryan.');
                                } else {
                                    if (UPLOAD_ERR_OK != $_FILES['import_file']['error']) {
                                        dieError('An unknown upload error was encountered.');
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (empty($_REQUEST['type'])) {
                dieError('No upload type supplied');
            }

            $validTypes = array(
                'feedinc',
                'email-suppression',
                'phone-suppression',
                'upload-outbound',
            );

            if (!in_array($_REQUEST['type'], $validTypes)) {
                dieError('Invalid upload type supplied');
            }

            if (empty($_FILES['import_file']['tmp_name'])) {
                dieError('You did not select a file to upload');
            }

            if ($_FILES['import_file']['size'] > MAX_UPLOAD_SIZE) {
                dieError('File size cannot exceed ' . (MAX_UPLOAD_SIZE / 1024000) . 'MB');
            }

            if (!empty($_FILES['import_file']['error'])) {
                dieError('Upload error (' . $_FILES['import_file']['error'] . ')');
            }

            if (!is_uploaded_file($_FILES['import_file']['tmp_name'])) {
                dieError('Possible file upload attack!');
            }

            $handle = @fopen($_FILES['import_file']['tmp_name'], "r");
            if (!$handle) {
                dieError('Cannot open uploaded file for reading');
            }

            // Flush (send) the output buffer and turn off output buffering
            // ob_end_flush();
            while (@ob_end_flush()) {
                ;
            }

            // Implicitly flush the buffer(s)
            ini_set('implicit_flush', true);
            ob_implicit_flush(true);

            print '<p>Uploading: ';

            @ob_flush();
            @flush();

            $cnt = 0;
            while (($raw_data = fgetcsv($handle, 1000, ',')) !== false) {
                $cnt++;
                print ". \n";
                @ob_flush();
                @flush();
            }
            fclose($handle);

            print 'Done!</p>';

            @ob_flush();
            @flush();

            if (0 === $cnt) {
                dieError('File contains no records');
            }

            $newFile = UPLOADS_DIR . hash('sha256', $_FILES['import_file']['tmp_name']);
            if (move_uploaded_file($_FILES['import_file']['tmp_name'], $newFile) !== true) {
                dieError('Cannot move uploaded file for processing');
            }

            $jobId = $leads->addJob($_REQUEST['type'], $_REQUEST['destination'], serialize($_REQUEST), $newFile, $cnt);
            if (null === $jobId) {
                dieError('Cannot add job to database');
            }

            switch ($_REQUEST['type']) {
                case 'feedinc':
                    $leads->auditLog('FEEDINC:IMPORT', $jobId);
                    break;

                case 'email-suppression':
                    $leads->auditLog('SUPPRESSION-EMAIL:IMPORT', $jobId);
                    break;

                case 'phone-suppression':
                    $leads->auditLog('SUPPRESSION-PHONE:IMPORT', $jobId);
                    break;

                case 'upload-outbound':
                    $leads->auditLog('FEEDOUT:IMPORT', $jobId);
                    break;
            }

            $link = sprintf('/leadadmin/mgr_job.php?jobId=%d&count=%d',
                $jobId,
                $cnt);

            ?>
            <p><a href="<?php echo $link; ?>">View results</a></p>
            <script>window.location = '<?php echo $link; ?>';</script>

        </div>
    </div>

    </body>
    </html>

<?php
@ob_flush();
@flush();
