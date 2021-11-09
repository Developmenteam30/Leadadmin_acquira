<?php

ini_set('memory_limit', '3G');

include(__DIR__ . "/../includes/c_config.php");

require_once(INCLUDES . 'leads.php');
require_once(INCLUDES . 'processLeads.php');

$mysqlErrorSource = 'Process Jobs';
require_once(INCLUDES . "f_site.php");

// PHPSPREADSHEET
include(INCLUDES . "vendor/autoload.php");

ini_set("auto_detect_line_endings", true);
set_time_limit(0);

$leads = Leads::getInstance();
$leads->setNetTimeouts(3600);

$job = $leads->getPendingJob();
if ($job === null) {
    print "No pending jobs";
    exit;
}

if ('clear-outbound-queue' === $job->type) {

    $fields = unserialize($job->fields);
    $status = 'Unknown error.';

    if (empty($job->destination) || empty($fields['label'])) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Missing required fields',
        ));
        $status = 'ERROR: Missing required fields.';

    } else {

        print "Clearing outbound queue for: {$job->destination} {$fields['label']}\n";

        $cnt = $leads->clearOutboundQueueNibble($job->destination);
        if ($cnt === null) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Database error while clearing the outbound queue',
            ));
            $status = 'Database error clearing the outbound queue';
        } else {
            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
            ));
            $status = "Successful";
        }

    }

    $feedOut = $leads->getOutboundFeed($job->destination);
    $feedCompany = $leads->getCompany($feedOut->idCompany);

    $body = "Job Results\r\n";
    $body .= "\r\n";
    $body .= "Job ID: {$job->jobId}\r\n";
    $body .= "Job Type: clear-outbound-queue\r\n";
    $body .= "\r\n";
    $body .= "Company: {$feedCompany->name}\r\n";
    $body .= "Feed ID: {$job->destination}\r\n";
    $body .= "Feed Label: {$feedOut->label}\r\n";
    $body .= "Feed Description: {$feedOut->description}\r\n";
    $body .= "\r\n";
    $body .= "Job Status: {$status}\r\n";
    if ($cnt !== null) {
        $body .= "Total Records: {$cnt}\r\n";
    }
    $body .= "\r\n";

    $from = SYSTEM_FROM_EMAIL;
    $fromName = CONFIG_COMPANY_NAME;
    $to = MANAGER_EMAIL;
    $subject = 'Job Results - Clear Outbound Queue';
    $header = "From:" . $fromName . " <" . $from . ">\n";
    $sent = @mail($to, $subject, $body, $header, "-f {$from}");

} elseif ('retry-outbound-rejections' === $job->type) {

    $fields = unserialize($job->fields);
    $status = 'Unknown error.';

    if (empty($job->destination) || empty($fields['label']) || empty($fields['dateStart']) || empty($fields['dateEnd'])) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Missing required fields',
        ));
        $status = 'ERROR: Missing required fields.';

    } else {

        print "Retrying outbound rejections for: {$job->destination} {$fields['label']}\n";

        $cnt = 0;
        try {
            $date = new \DateTime($fields['dateStart']);
            $dateEnd = new \DateTime($fields['dateEnd']);

            while ($date <= $dateEnd) {
                print "Trying date: " . $date->format(' Y-m-d') . PHP_EOL;
                $statusCnt = $leads->retryOutboundRejections($job->destination, $date->format('Y-m-d'));
                if ($statusCnt === null) {
                    $leads->updateJob($job->jobId, array(
                        'status' => 'error',
                        'message' => 'Database error while retrying outbound rejections',
                    ));
                    $status = 'Database error retrying outbound rejections';
                    break;
                }
                $cnt += $statusCnt;
                $date->add(new \DateInterval('P1D'));
            }
            if ('Unknown error.' == $status) {
                $leads->updateJob($job->jobId, array(
                    'status' => 'finished',
                    'records' => $cnt,
                ));
                $status = "Successful";
            }

        } catch (Exception $e) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage(),
            ));
            $status = 'ERROR: Exception: ' . $e->getMessage();
        }
    }

    $user = $leads->getUser($job->idUser);
    if (empty($user) || empty($user->email)) {
        return;
    }

    $feedOut = $leads->getOutboundFeed($job->destination);
    $feedCompany = $leads->getCompany($feedOut->idCompany);

    $body = "Job Results\r\n";
    $body .= "\r\n";
    $body .= "Job ID: {$job->jobId}\r\n";
    $body .= "Job Type: retry-outbound-rejections\r\n";
    $body .= "\r\n";
    $body .= "Company: {$feedCompany->name}\r\n";
    $body .= "Feed ID: {$job->destination}\r\n";
    $body .= "Feed Label: {$feedOut->label}\r\n";
    $body .= "Feed Description: {$feedOut->description}\r\n";
    $body .= "\r\n";
    $body .= "Job Status: {$status}\r\n";
    if ($cnt !== null) {
        $body .= "Total Records: {$cnt}\r\n";
    }
    $body .= "\r\n";

    $from = SYSTEM_FROM_EMAIL;
    $fromName = CONFIG_COMPANY_NAME;
    $to = filter_var($user->email, FILTER_SANITIZE_EMAIL);
    $subject = 'Job Results - Retry Outbound Rejections';
    $header = "From:" . $fromName . " <" . $from . ">\n";
    $header .= "CC: " . OWNER_EMAIL . "\r\n";
    $sent = @mail($to, $subject, $body, $header, "-f {$from}");

} elseif ('export-incoming' === $job->type) {

    $fields = unserialize($job->fields);
    $status = 'Unknown error.';

    if (empty($fields['columns'])) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Missing required fields',
        ));
        $status = 'ERROR: Missing required fields.';

    } else {

        print "Exporting incoming records for: {$job->destination}\n";

        $result = $leads->exportInboundRecords($fields);

        if ($result['success'] !== true) {

            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => $result['reason'],
            ));
            $status = $result['reason'];

        } else {

            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
                'records' => $result['cnt'],
                'filename' => $result['fileLink'],
                'message' => null,
            ));
            $status = "Successful";
        }

    }

    $user = $leads->getUser($job->idUser);
    if (empty($user) || empty($user->email)) {
        return;
    }

    $body = "Job Results\r\n";
    $body .= "\r\n";
    $body .= "Job ID: {$job->jobId}\r\n";
    $body .= "Job Type: export-incoming\r\n";
    $body .= "\r\n";
    if (empty($fields['feedIds'])) {
        $body .= "Company: ALL\r\n";
        $body .= "Feed ID: ALL\r\n";
        $body .= "Feed Label: ALL\r\n";
        $body .= "Feed Description: ALL\r\n";
    } else {
        $feedIn = $leads->getInboundFeed($job->destination);
        $feedCompany = $leads->getCompany($feedIn->idCompany);
        $body .= "Company: {$feedCompany->name}\r\n";
        $body .= "Feed ID: {$job->destination}\r\n";
        $body .= "Feed Label: {$feedIn->label}\r\n";
        $body .= "Feed Description: {$feedIn->description}\r\n";
    }
    $body .= "\r\n";
    $body .= "Job Status: {$status}\r\n";
    if (isset($result['cnt'])) {
        $body .= "Total Records: {$result['cnt']}\r\n";
    }
    if (!empty($result['cnt']) && !empty($result['fileLink'])) {
        $body .= sprintf("\r\nDownload Link: %s\r\n",
            $result['fileLink']
        );
    }
    $body .= "\r\n";

    $from = SYSTEM_FROM_EMAIL;
    $fromName = CONFIG_COMPANY_NAME;
    $to = filter_var($user->email, FILTER_SANITIZE_EMAIL);
    $subject = 'Job Results - Export Incoming Data';
    $header = "From:" . $fromName . " <" . $from . ">\n";
    $header .= "CC: " . OWNER_EMAIL . "\r\n";
    $sent = @mail($to, $subject, $body, $header, "-f {$from}");

} elseif ('export-outgoing' === $job->type) {

    $fields = unserialize($job->fields);
    $status = 'Unknown error.';

    print "Exporting outgoing records for: {$job->destination}\n";

    $result = $leads->exportOutboundRecords($fields);

    if ($result['success'] !== true) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => $result['reason'],
        ));
        $status = $result['reason'];

    } else {

        $leads->updateJob($job->jobId, array(
            'status' => 'finished',
            'records' => $result['cnt'],
            'filename' => $result['fileLink'],
            'message' => null,
        ));
        $status = "Successful";
    }

    $user = $leads->getUser($job->idUser);
    if (empty($user) || empty($user->email)) {
        return;
    }

    $body = "Job Results\r\n";
    $body .= "\r\n";
    $body .= "Job ID: {$job->jobId}\r\n";
    $body .= "Job Type: export-outgoing\r\n";
    $body .= "\r\n";
    if (empty($fields['feedIds'])) {
        $body .= "Company: ALL\r\n";
        $body .= "Feed ID: ALL\r\n";
        $body .= "Feed Label: ALL\r\n";
        $body .= "Feed Description: ALL\r\n";
    } else {
        $feedOut = $leads->getOutboundFeed($job->destination);
        $feedCompany = $leads->getCompany($feedOut->idCompany);
        $body .= "Company: {$feedCompany->name}\r\n";
        $body .= "Feed ID: {$job->destination}\r\n";
        $body .= "Feed Label: {$feedOut->label}\r\n";
        $body .= "Feed Description: {$feedOut->description}\r\n";
    }
    $body .= "\r\n";
    $body .= "Job Status: {$status}\r\n";
    if (isset($result['cnt'])) {
        $body .= "Total Records: {$result['cnt']}\r\n";
    }
    if (!empty($result['cnt']) && !empty($result['fileLink'])) {
        $body .= sprintf("\r\nDownload Link: %s\r\n",
            $result['fileLink']
        );
    }
    $body .= "\r\n";

    $from = SYSTEM_FROM_EMAIL;
    $fromName = CONFIG_COMPANY_NAME;
    $to = filter_var($user->email, FILTER_SANITIZE_EMAIL);
    $subject = 'Job Results - Export Outgoing Data';
    $header = "From:" . $fromName . " <" . $from . ">\n";
    $header .= "CC: " . OWNER_EMAIL . "\r\n";
    $sent = @mail($to, $subject, $body, $header, "-f {$from}");

} elseif ('feedinc' === $job->type) {

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($job->filename);
        $spreadsheet = $reader->load($job->filename);

        $worksheet = $spreadsheet->getActiveSheet();

        print "Importing legacy records from: {$job->filename}\n";

        $feedParams = $leads->getInboundFeed($job->destination);
        if (empty($feedParams)) {
            print 'ERROR: Invalid incoming feed ID supplied';
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Invalid incoming feed ID supplied',
            ));
            exit;
        }

        $allowedFields = explode(";", $feedParams->allowedFields);
        $fields = unserialize($job->fields);

        $counts = array(
            'success' => 0,
            'invalid' => 0,
            'failures' => 0,
            'dupe' => 0,
        );

        $recordsPerDay = null;
        if (!empty($job->records) && !empty($fields['splitDelay'])) {
            $recordsPerDay = round($job->records / $fields['splitDelay']);
            $splitTimestamp = new \DateTime();
        }

        $cnt = 0;
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $raw_data = [];
            foreach ($cellIterator as $cell) {
                $raw_data[] = trim($cell->getFormattedValue());
            }

            $data = array();

            foreach ($allowedFields as $field) {
                if (isset($fields['field_' . $field]) && is_numeric($fields['field_' . $field])) {
                    $col = $fields['field_' . $field];
                    if (!empty($raw_data[$col])) {
                        if ('stamp' == $field) {
                            // Check to see if we're using two separate timestamp columns
                            if (!empty($fields['field_time']) && is_numeric($fields['field_time'])) {
                                $time_col = $fields['field_time'];
                                // Remove extraneous data from the date field
                                if (strpos($raw_data[$col], ' ') !== false) {
                                    list($date, $garbage) = explode(' ', $raw_data[$col], 2);
                                } else {
                                    $date = $raw_data[$col];
                                }
                                $data['stamp'] = date("Y-m-d H:i:s", strtotime($date . (!empty($raw_data[$time_col]) ? ' ' . $raw_data[$time_col] : '')));
                            } else {
                                $data['stamp'] = date("Y-m-d H:i:s", strtotime($raw_data[$col]));
                            }
                        } else {
                            $data[$field] = $raw_data[$col];
                        }
                    }
                }
            }

            // Fix zip codes with a missing leading zero
            if (!empty($data['zip'])) {
                $data['zip'] = str_pad($data['zip'], 5, '0', STR_PAD_LEFT);
            }

            if (isset($data['email'])) {
                print "{$data['email']}";
            } else {
                print " ";
            }

            // Change the timestamp for split delay uploads
            if (!empty($recordsPerDay)) {
                // Increment the day once we've hit the recordsPerDay count
                if (!empty($cnt) && ($cnt % $recordsPerDay) === 0) {
                    try {
                        $splitTimestamp->add(new \DateInterval('P1D'));
                    } catch (\Exception $e) {
                        // Do nothing
                    }
                }

                // Combine our fake date with a random hour plus the original minutes and seconds
                $timestampOverride = $splitTimestamp->format('Y-m-d') . ' ' . rand(6, 23) . date(':i:s', strtotime($data['stamp']));
                $data['stamp'] = $timestampOverride;
                $data['timestampOverride'] = $timestampOverride;
            }

            $result = ProcessLeads::validateIncomingData($feedParams, $data);

            if ($result['valid']) {

                $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $data, date('Y-m-d'), null, $job->jobId);
                if (null === $inboundId) {
                    print " - DBFAIL\n";
                    $counts['failures']++;
                } else {

                    $pushResponse = ProcessLeads::pushIncomingData($feedParams, $data, $inboundId);
                    if (isset($pushResponse['reason']) && $pushResponse['reason'] !== null) {
                        print " - ERROR\n";
                        $counts['invalid']++;
                    } else {
                        print " - VALID\n";
                        $counts['success']++;
                    }
                }

            } else {

                $foundDupe = false;

                print " - ERROR\n";
                foreach ($result['errors'] as $error) {
                    if (strpos($error, 'Duplicate') === 0) {
                        $foundDupe = true;
                    }
                    print "\t{$error}\n";
                }

                if ($foundDupe) {
                    $counts['dupe']++;
                } else {
                    $counts['invalid']++;
                }

                $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $data, date('Y-m-d'), $result['errors'][0],
                    $job->jobId);

            }

            print "\n";

            $cnt++;
            unset($data);

        }

        if ($cnt == intval($job->records)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
            ));
        } else {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Record count does not match',
            ));
        }

        print "FILE IMPORT COMPLETE!\n";

        print "Successful: {$counts['success']}\n";
        print "Duplicates: {$counts['dupe']}\n";
        print "Invalid: {$counts['invalid']}\n";
        print "Failures: {$counts['failures']}\n";

        $feedIn = $leads->getInboundFeed($job->destination);
        $feedCompany = $leads->getCompany($feedIn->idCompany);

        $body = "Job Results\r\n";
        $body .= "\r\n";
        $body .= "Job ID: {$job->jobId}\r\n";
        $body .= "Job Type: export-incoming\r\n";
        $body .= "\r\n";
        $body .= "Company: {$feedCompany->name}\r\n";
        $body .= "Feed ID: {$job->destination}\r\n";
        $body .= "Feed Label: {$feedIn->label}\r\n";
        $body .= "Feed Description: {$feedIn->description}\r\n";
        $body .= "\r\n";
        $body .= "Total Records: {$cnt}\r\n";
        $body .= "\r\n";
        $body .= "Successful: {$counts['success']}\r\n";
        $body .= "Duplicates: {$counts['dupe']}\r\n";
        $body .= "Invalid: {$counts['invalid']}\r\n";
        $body .= "Failures: {$counts['failures']}\r\n";
        $body .= "\r\n";

        $from = SYSTEM_FROM_EMAIL;
        $fromName = CONFIG_COMPANY_NAME;
        $to = MANAGER_EMAIL;
        $subject = 'Job Results - Inbound Record Import';
        $header = "From:" . $fromName . " <" . $from . ">\n";
        $sent = @mail($to, $subject, $body, $header, "-f {$from}");

    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open uploaded file for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open default worksheet for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    }

} elseif ('upload-outbound' === $job->type) {

    if (empty($job->destination)) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Missing required fields',
        ));
        $status = 'ERROR: Missing required fields.';

        return;

    }

    $feedOut = $leads->getOutboundFeed($job->destination);
    if (empty($feedOut)) {
        print 'ERROR: Invalid outgoing feed ID supplied';
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Invalid outgoing feed ID supplied',
        ));
        exit;
    }

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($job->filename);
        $spreadsheet = $reader->load($job->filename);

        $worksheet = $spreadsheet->getActiveSheet();

        print "Importing legacy records from: {$job->filename}\n";

        // Override inbound feed settings.
        $feedParams = new stdClass();
        $feedParams->idFeedIn = INBOUND_FEEDID_MANUAL_UPLOAD;
        $feedParams->notifications = false;
        $feedParams->required = null;
        $feedParams->allowedFields = implode(";", $recordFields);
        $feedParams->dailyLimit = null;
        $feedParams->filterTypeUrl = null;
        $feedParams->dedupeEmail = null;
        $feedParams->dedupeLandline = null;
        $feedParams->dedupeCellphone = null;
        $feedParams->rejectOldLeads = false;
        $feedParams->feedCategory = $feedOut->feedCategory;
        $feedParams->timezone = 'UTC';

        $allowedFields = explode(";", $feedParams->allowedFields);
        $fields = unserialize($job->fields);

        $counts = array(
            'success' => 0,
            'invalid' => 0,
            'failures' => 0,
            'dupe' => 0,
        );

        $cnt = 0;
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $raw_data = [];
            foreach ($cellIterator as $cell) {
                $raw_data[] = $cell->getFormattedValue();
            }

            $data = array();

            foreach ($allowedFields as $field) {
                if (isset($fields['field_' . $field]) && is_numeric($fields['field_' . $field])) {
                    $col = $fields['field_' . $field];
                    if (!empty($raw_data[$col])) {
                        if ('stamp' == $field) {
                            // Check to see if we're using two separate timestamp columns
                            if (!empty($fields['field_time']) && is_numeric($fields['field_time'])) {
                                $time_col = $fields['field_time'];
                                // Remove extraneous data from the date field
                                if (strpos($raw_data[$col], ' ') !== false) {
                                    list($date, $garbage) = explode(' ', $raw_data[$col], 2);
                                } else {
                                    $date = $raw_data[$col];
                                }
                                $data['stamp'] = date("Y-m-d H:i:s",
                                    strtotime($date . (!empty($raw_data[$time_col]) ? ' ' . $raw_data[$time_col] : '')));
                            } else {
                                $data['stamp'] = date("Y-m-d H:i:s", strtotime($raw_data[$col]));
                            }
                        } elseif ('dob' == $field) {
                            $data['dob'] = date("Y-m-d", strtotime($raw_data[$col]));
                        } else {
                            $data[$field] = $raw_data[$col];
                        }
                    }
                }
            }

            // Fix zip codes with a missing leading zero
            if (!empty($data['zip'])) {
                $data['zip'] = str_pad($data['zip'], 5, '0', STR_PAD_LEFT);
            }

            if (isset($data['email'])) {
                print "{$data['email']}";
            } else {
                print " ";
            }

            $result = ProcessLeads::validateIncomingData($feedParams, $data);

            if ($result['valid']) {

                $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $data, date('Y-m-d'), null, $job->jobId);
                if (null === $inboundId) {
                    print " - DBFAIL\n";
                    $counts['failures']++;
                } else {

                    $pushResponse = ProcessLeads::pushIncomingData($feedParams, $data, $inboundId, $job->destination);
                    if (isset($pushResponse['reason']) && $pushResponse['reason'] !== null) {
                        print " - ERROR\n";
                        $counts['invalid']++;
                    } else {
                        print " - VALID\n";
                        $counts['success']++;
                    }
                }

            } else {

                $counts['invalid']++;

                print " - ERROR\n";
                foreach ($result['errors'] as $error) {
                    print "\t{$error}\n";
                }

                $inboundId = $leads->inboundAdd($feedParams->idFeedIn, $data, date('Y-m-d'), $result['errors'][0],
                    $job->jobId);

            }

            print "\n";

            $cnt++;
            unset($data);

        }

        if ($cnt == intval($job->records)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
            ));
        } else {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Record count does not match',
            ));
        }

        print "FILE IMPORT COMPLETE!\n";

        print "Successful: {$counts['success']}\n";
        print "Duplicates: {$counts['dupe']}\n";
        print "Invalid: {$counts['invalid']}\n";
        print "Failures: {$counts['failures']}\n";

        $feedOut = $leads->getOutboundFeed($job->destination);
        $feedCompany = $leads->getCompany($feedOut->idCompany);

        $body = "Job Results\r\n";
        $body .= "\r\n";
        $body .= "Job ID: {$job->jobId}\r\n";
        $body .= "Job Type: upload-outbound\r\n";
        $body .= "\r\n";
        $body .= "Company: {$feedCompany->name}\r\n";
        $body .= "Feed ID: {$job->destination}\r\n";
        $body .= "Feed Label: {$feedOut->label}\r\n";
        $body .= "Feed Description: {$feedOut->description}\r\n";
        $body .= "\r\n";
        $body .= "Total Records: {$cnt}\r\n";
        $body .= "\r\n";
        $body .= "Successful: {$counts['success']}\r\n";
        $body .= "Duplicates: {$counts['dupe']}\r\n";
        $body .= "Invalid: {$counts['invalid']}\r\n";
        $body .= "Failures: {$counts['failures']}\r\n";
        $body .= "\r\n";

        $from = SYSTEM_FROM_EMAIL;
        $fromName = CONFIG_COMPANY_NAME;
        $to = MANAGER_EMAIL;
        $subject = 'Job Results - Outbound Record Upload';
        $header = "From:" . $fromName . " <" . $from . ">\n";
        $sent = @mail($to, $subject, $body, $header, "-f {$from}");

    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open uploaded file for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open default worksheet for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    }

} elseif ('import-legacy-outbound' === $job->type) {

    $fields = unserialize($job->fields);
    $status = 'Unknown error.';

    if (empty($job->destination) || empty($fields['idAssoc']) || empty($fields['dateStart']) || empty($fields['dateEnd'])) {

        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Missing required fields',
        ));
        $status = 'ERROR: Missing required fields.';

    } else {

        print "Importing legacy records for: {$job->destination}\n";

        $population = $leads->getPopulationSetting($fields['idAssoc']);
        if (empty($population) || empty($population->idFeedIn)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Cannot find population parameter: ' . $fields['idAssoc'],
            ));

            return;
        }

        $leads_export = new Leads(false);
        $leads_export->setNetTimeouts(3600);

        $feedParams = $leads->getInboundFeed($population->idFeedIn);
        if (empty($feedParams)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Cannot find inbound feed: ' . $population->idFeedIn,
            ));

            return;
        }

        $params = array();
        $sql = "SELECT * FROM data_inbound ";
        $sql .= "WHERE 1=1 ";
        $sql .= "AND idFeedIn = ? ";
        $params[] = $population->idFeedIn;
        $sql .= "AND timestamp >= CONVERT_TZ(?,?,?) ";
        $params[] = $fields['dateStart'] . ' 00:00:00';
        $params[] = LOCAL_TIMEZONE;
        $params[] = DB_TIMEZONE;
        $sql .= "AND timestamp <= CONVERT_TZ(?,?,?) ";
        $params[] = $fields['dateEnd'] . ' 23:59:59';
        $params[] = LOCAL_TIMEZONE;
        $params[] = DB_TIMEZONE;
        $sql .= "AND ( result IS NULL ";
        if (!empty($fields['includeLiveRejects'])) {
            $sql .= "OR result LIKE 'Third-party rejection [%0]' ";
        }
        if (!empty($fields['includeChokeRejects'])) {
            $sql .= "OR result LIKE 'Third-party rejection [%1]' ";
        }
        if (!empty($fields['includeStandardRejects'])) {
            $sql .= "OR result NOT LIKE 'Third-party rejection [%' ";
        }
        $sql .= ") ";
        if (!empty(intval($fields['limit']))) {
            $sql .= "LIMIT " . intval($fields['limit']);
        }

        $query = $leads_export->exportRecords($sql, $params);

        $cnt = 0;
        if (!empty($query)) {
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {

                print "Record: {$row['idRecord']} {$row['idFeedIn']}\n";
                $pushResponse = ProcessLeads::pushIncomingData($feedParams, $row, $row['idRecord'], $job->destination);
                if (isset($pushResponse['reason']) && $pushResponse['reason'] !== null) {
                    echo "\t{$pushResponse['reason']}\n";
                } else {
                    echo "\tSUCCESS\n";
                    $cnt++;

                    $leads->updateJob($job->jobId, array(
                        'records' => $cnt,
                    ));
                }
            }
        }

        $leads->updateJob($job->jobId, array(
            'status' => 'finished',
            'records' => $cnt,
            'message' => null,
        ));
        $status = "Successful";

    }

} elseif ('email-suppression' === $job->type) {

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($job->filename);
        $spreadsheet = $reader->load($job->filename);

        $worksheet = $spreadsheet->getActiveSheet();

        print "Importing phone suppression records from: {$job->filename}\n";

        $fields = unserialize($job->fields);

        if (empty($fields['list'])) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'No list specified',
            ));
            exit;
        }

        $lists = array();
        if ('multiple' == $fields['list']) {
            foreach ($fields as $key => $val) {
                if (strpos($key, 'suppress_multiselect_') !== false && isset($val)) {
                    $lists[] = intval($val);
                }
            }
        } elseif ('global' == $fields['list']) {
            $lists[] = 0;
        } else {
            $lists[] = intval($fields['list']);
        }

        if (sizeOf($lists) == 0) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'No list specified',
            ));
            exit;
        }

        $counts = array(
            'success' => 0,
            'invalid' => 0,
            'failures' => 0,
            'dupe' => 0,
        );

        $cnt = 0;
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $raw_data = [];
            foreach ($cellIterator as $cell) {
                $raw_data = trim($cell->getValue());

                if (strpos($raw_data, '@') !== false && !filter_var($raw_data, FILTER_VALIDATE_EMAIL)) {
                    $counts['invalid']++;
                } else {
                    foreach ($lists as $list) {
                        $result = $leads->addSuppression('email', $list, $raw_data);
                    }
                    if (null === $result) {
                        $counts['dupe']++;
                    } elseif (false === $result) {
                        $counts['failures']++;
                    } else {
                        $counts['success']++;
                    }
                }
            }

            $cnt++;
        }

        if ($cnt == intval($job->records)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
            ));
        } else {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Record count does not match',
            ));
        }

        print "FILE IMPORT COMPLETE!\n";

        print "Successful: {$counts['success']}\n";
        print "Duplicates: {$counts['dupe']}\n";
        print "Invalid: {$counts['invalid']}\n";
        print "Failures: {$counts['failures']}\n";

        $body = "Job Results\r\n";
        $body .= "\r\n";
        $body .= "Job ID: {$job->jobId}\r\n";
        $body .= "Job Type: email-suppression\r\n";
        $body .= "\r\n";
        $body .= "Total Records: {$cnt}\r\n";
        $body .= "\r\n";
        $body .= "Successful: {$counts['success']}\r\n";
        $body .= "Duplicates: {$counts['dupe']}\r\n";
        $body .= "Invalid: {$counts['invalid']}\r\n";
        $body .= "Failures: {$counts['failures']}\r\n";
        $body .= "\r\n";

        $from = SYSTEM_FROM_EMAIL;
        $fromName = CONFIG_COMPANY_NAME;
        $to = MANAGER_EMAIL;
        $subject = 'Job Results - Email Suppression Import';
        $header = "From:" . $fromName . " <" . $from . ">\n";
        $sent = @mail($to, $subject, $body, $header, "-f {$from}");

    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open uploaded file for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open default worksheet for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    }

} elseif ('phone-suppression' === $job->type) {

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($job->filename);
        $spreadsheet = $reader->load($job->filename);

        $worksheet = $spreadsheet->getActiveSheet();

        print "Importing phone suppression records from: {$job->filename}\n";

        $fields = unserialize($job->fields);

        if (empty($fields['list'])) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'No list specified',
            ));
            exit;
        }

        $lists = array();
        if ('multiple' == $fields['list']) {
            foreach ($fields as $key => $val) {
                if (strpos($key, 'suppress_multiselect_') !== false && isset($val)) {
                    $lists[] = intval($val);
                }
            }
        } elseif ('global' == $fields['list']) {
            $lists[] = 0;
        } else {
            $lists[] = intval($fields['list']);
        }

        if (sizeOf($lists) == 0) {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'No list specified',
            ));
            exit;
        }

        $counts = array(
            'success' => 0,
            'invalid' => 0,
            'failures' => 0,
            'dupe' => 0,
        );

        $cnt = 0;
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $raw_data = [];
            foreach ($cellIterator as $cell) {
                $raw_data = preg_replace('/[^0-9]/', '', trim($cell->getValue()));

                if (empty($raw_data)) {
                    $counts['invalid']++;
                } else {
                    foreach ($lists as $list) {
                        $result = $leads->addSuppression('phone', $list, $raw_data);
                    }
                    if (null === $result) {
                        $counts['dupe']++;
                    } elseif (false === $result) {
                        $counts['failures']++;
                    } else {
                        $counts['success']++;
                    }
                }
            }

            $cnt++;
        }

        if ($cnt == intval($job->records)) {
            $leads->updateJob($job->jobId, array(
                'status' => 'finished',
            ));
        } else {
            $leads->updateJob($job->jobId, array(
                'status' => 'error',
                'message' => 'Record count does not match',
            ));
        }

        print "FILE IMPORT COMPLETE!\n";

        print "Successful: {$counts['success']}\n";
        print "Duplicates: {$counts['dupe']}\n";
        print "Invalid: {$counts['invalid']}\n";
        print "Failures: {$counts['failures']}\n";

        $body = "Job Results\r\n";
        $body .= "\r\n";
        $body .= "Job ID: {$job->jobId}\r\n";
        $body .= "Job Type: phone-suppression\r\n";
        $body .= "\r\n";
        $body .= "Total Records: {$cnt}\r\n";
        $body .= "\r\n";
        $body .= "Successful: {$counts['success']}\r\n";
        $body .= "Duplicates: {$counts['dupe']}\r\n";
        $body .= "Invalid: {$counts['invalid']}\r\n";
        $body .= "Failures: {$counts['failures']}\r\n";
        $body .= "\r\n";

        $from = SYSTEM_FROM_EMAIL;
        $fromName = CONFIG_COMPANY_NAME;
        $to = MANAGER_EMAIL;
        $subject = 'Job Results - Phone Suppression Import';
        $header = "From:" . $fromName . " <" . $from . ">\n";
        $sent = @mail($to, $subject, $body, $header, "-f {$from}");

    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open uploaded file for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
        $leads->updateJob($job->jobId, array(
            'status' => 'error',
            'message' => 'Cannot open default worksheet for reading',
        ));
        print 'ERROR: Cannot open uploaded file for reading';
        exit;
    }

} else {

    $leads->updateJob($job->jobId, array(
        'status' => 'error',
        'message' => 'Unknown job type',
    ));

}
