<?php

require_once('c_config.php');
require_once(INCLUDES . '_f_validation.php');
require_once(INCLUDES . 'f_site.php');

class ProcessLeads
{
    public static function assignValue($key, $value, &$requestdata, &$xmldata, &$headerdata)
    {

        if (strpos($key, '|') !== false) {
            // Send a subarray with the data
            $vars = explode('|', $key);
            if (sizeOf($vars) == 3) {
                $requestdata[$vars[0]][$vars[1]][] = $value;
            } else {
                $requestdata[$vars[0]][$vars[1]] = $value;
            }
        } elseif (strpos($key, '#') !== false) {
            // Send a JSON object with the data
            $vars = explode('#', $key);

            // If there's already JSON data present, append to it
            if (!empty($requestdata[$vars[0]])) {
                $requestdata[$vars[0]] = json_encode((object)array_merge(array($vars[1] => $value), (array)json_decode($requestdata[$vars[0]])));
            } else {
                $requestdata[$vars[0]] = json_encode((object)array($vars[1] => $value));
            }
        } elseif (strpos($key, '~') === 0) {
            // Assign this as XML data
            $key = str_replace('~', '', $key);
            $xmldata[$key] = $value;
        } elseif (strpos($key, '@') === 0) {
            // Assign this as header data
            $key = str_replace('@', '', $key);
            $headerdata[] = $key . ': ' . $value;
        } else {
            $requestdata[$key] = $value;
        }
    }

    public static function filterValue($filterType, $value, $filters)
    {
        switch ($filterType) {
            case 'accept':
                $valueAcceptable = false;
                $acceptableFilters = explode(";", $filters);
                foreach ($acceptableFilters as $acceptableFilter) {
                    if (stripos($value, $acceptableFilter) !== false) {
                        $valueAcceptable = true;
                        break;
                    }
                }
                break;

            case 'reject':
                $valueAcceptable = true;
                $rejectableFilters = explode(";", $filters);
                foreach ($rejectableFilters as $rejectableFilter) {
                    if (stripos($value, $rejectableFilter) !== false) {
                        $valueAcceptable = false;
                        break;
                    }
                }
                break;

            default:
                $valueAcceptable = true;
                break;

        }

        return $valueAcceptable;
    }

    public static function getXml813($requestdata)
    {

        return '<?xml version="1.0" encoding="utf-8"?>
<AgentCubedAPI xmlns="http://dataexchange.agentcubed.com">
    <LoginCredentials>
        <ErrorNotificationEmail>david@qatalystinc.com</ErrorNotificationEmail>
        <Username>' . htmlspecialchars($requestdata['Username'] ?? '', ENT_XML1, 'UTF-8') . '</Username>
        <Password>' . htmlspecialchars('healthinsuranceinnovations=#1!', ENT_XML1, 'UTF-8') . '</Password>
        <Group_WebLead_ID>' . htmlspecialchars($requestdata['Group_WebLead_ID'] ?? '', ENT_XML1, 'UTF-8') . '</Group_WebLead_ID>
        <LeadSourceKey>' . htmlspecialchars($requestdata['LeadSourceKey'] ?? '', ENT_XML1, 'UTF-8') . '</LeadSourceKey>
    </LoginCredentials>
    <Leads>
        <Lead>
            <LeadInformation>
               <LeadGeneratedDateTime>' . htmlspecialchars($requestdata['LeadGeneratedDateTime'] ?? '', ENT_XML1, 'UTF-8') . '</LeadGeneratedDateTime>
            </LeadInformation>
            <LeadIndividuals>
                <Individual IndividualID="0">
                    <DOB>' . htmlspecialchars($requestdata['DOB'] ?? '', ENT_XML1, 'UTF-8') . '</DOB>
                    <LastName>' . htmlspecialchars($requestdata['LastName'] ?? '', ENT_XML1, 'UTF-8') . '</LastName>
                    <FirstName>' . htmlspecialchars($requestdata['FirstName'] ?? '', ENT_XML1, 'UTF-8') . '</FirstName>
                    <Email>' . htmlspecialchars($requestdata['Email'] ?? '', ENT_XML1, 'UTF-8') . '</Email>
                    <RelationType>Applicant</RelationType>
                </Individual>
            </LeadIndividuals>
            <LeadOpportunities>
                <Opportunity>
                    <InsuranceType>' . htmlspecialchars($requestdata['InsuranceType'] ?? '', ENT_XML1, 'UTF-8') . '</InsuranceType>
				</Opportunity>
			</LeadOpportunities>
            <LeadContactDetails>
                <PrimaryPhone>' . htmlspecialchars($requestdata['PrimaryPhone'] ?? '', ENT_XML1, 'UTF-8') . '</PrimaryPhone>
                <Address>
                    <ZipCode>' . htmlspecialchars($requestdata['ZipCode'] ?? '', ENT_XML1, 'UTF-8') . '</ZipCode>
                    <Address1>' . htmlspecialchars($requestdata['Address'] ?? '', ENT_XML1, 'UTF-8') . '</Address1>
                </Address>
            </LeadContactDetails>
        </Lead>
    </Leads>
</AgentCubedAPI>';
    }

    public static function curlLead($requestdata, $url, $post, $verifypeer = false, $returntransfer = true, $header = false, $httpheader = null, $followlocation = false)
    {

        $ch = curl_init();
        $verbose = false;

        if ($verbose) {
            ob_start();
            $out = fopen('php://output', 'w');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
        }
        if (!empty($requestdata)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestdata);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifypeer);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returntransfer);
        curl_setopt($ch, CURLOPT_HEADER, $header);
        if (!empty($httpheader)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followlocation);
        curl_setopt($ch, CURLOPT_TIMEOUT, 65);
        if ($verbose) {
            curl_setopt($ch, CURLOPT_VERBOSE, 1);
            curl_setopt($ch, CURLOPT_STDERR, $out);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch) != 0) {
            $response = "CURL Error: " . curl_error($ch);
        }
        if ($verbose) {
            if ($out) {
                fclose($out);
            }
            echo nl2br(ob_get_clean());
        }
        curl_close($ch);

        return $response;
    }

    public static function pushIncomingData($feedParams, $data, $idRecord, $idFeedOut = null)
    {
        $leads = Leads::getInstance();
        $result = [
            'reason' => null,
            'fields' => [],
        ];
        $debug = false;

        if (!empty($data['url']) && !empty($feedParams->notifications)) {

            // Notify if this is the first time we've seen this URL on this feed
            $urlExists = $leads->checkInboundURLExists($feedParams->idFeedIn, $data['url']);
            if (false === $urlExists) {

                $body = sprintf("\r\nWe received a new URL on this feed.\r\n\r\nCompany: {$feedParams->companyName}\r\n\r\nFeed: {$feedParams->label}\r\n\r\nURL: %s\r\n\r\n",
                    str_replace('.', '*', $data['url'])
                );

                $from = 'lmsalerts@' . SITE_URL;
                $fromName = CONFIG_COMPANY_NAME . ' List Management System';
                $to = MANAGER_EMAIL;
                $subject = 'List Management - New URL Alert';
                $header = "From:" . $fromName . " <" . $from . ">\r\n";
                $header .= "Content-type: text/plain; charset=iso-8859-1\r\n";
                $header .= "Reply-To: <" . $from . ">\r\n";
                $header .= "X-Sender: <" . $from . ">\r\n";
                $header .= "Return-Path: <" . $from . ">\r\n";
                $sent = @mail($to, $subject, $body, $header, "-f {$from}");
                if (!$sent) {
                    $leads->logError('Failed to send error report notification to administrator');
                }

            }

            // Add an entry to the notification table to see if this feed goes dormant
            if (empty($idFeedOut)) {
                $leads->addNotification($feedParams->idFeedIn, $data['url']);
            }

        }

        $data['originalUrl'] = $data['url'];

        $liveData = array(
            'enabled' => false,
            'accepted' => false,
            'anyProcessed' => false,
            'reason' => null,
        );

        if (!empty($idFeedOut)) {
            $feedOut = $leads->getOutboundFeed($idFeedOut);
            if (empty($feedOut)) {
                return 'Invalid outbound feed.';
            }

            // If forcing this to an outbound feed, force all feed parameters.
            $feed = new stdClass();
            $feed->enabled = true;
            $feed->delayDump = false;
            $feed->startDate = null;
            $feed->idFeedOut = $idFeedOut;
            $feed->queueType = 'queue';
            $feed->filterTypeUrl = null;
            $feed->filterTypeEmail = null;
            $feed->filterTypeListcode = null;
            $feed->dailyLimit = $feedOut->dailyLimit;
            $feed->forceUrl = null;
            $feed->forceUrlList = null;
            $feedsOut = array($feed);
        } else {
            $feedsOut = $leads->getInboundPopulationSettings($feedParams->idFeedIn, false);
        }

        if (!empty($feedsOut) && is_array($feedsOut)) {
            foreach ($feedsOut as $feed) {

                // Is this population parameter enabled? Allow if we are importing.
                if (empty($idFeedOut) && (empty($feed->enabled) || !empty($feed->delayDump))) {
                    continue;
                }

                if (empty($idFeedOut) && !empty($feed->startDate) && $feed->startDate > date('Y-m-d')) {
                    if ($debug) {
                        print "Skipping because feed start date has not passed yet: {$feed->startDate}";
                    }
                    continue;
                }

                // Are we limiting records to a specific outbound feed?
                if (!empty($idFeedOut) && $idFeedOut != $feed->idFeedOut) {
                    continue;
                }

                if ($debug) {
                    print "<p>{$idRecord} {$feedParams->idFeedIn} => {$feed->idFeedOut} {$feed->queueType}: ";
                }

                // Ensure we don't re-import records sent within the last 6 months
                if (!empty($idFeedOut) && $leads->checkOutboundRecordExists($idRecord, $feedParams->idFeedIn, $feed->idFeedOut)) {
                    if ($debug) {
                        print "Skipping because already sent within the last 6 months</p>";
                    }
                    continue;
                }

                // Ensure the record passes the population parameter filters for this feed
                if (!is_null($feed->filterTypeUrl) && !ProcessLeads::filterValue($feed->filterTypeUrl, $data['url'], $feed->filterUrl)) {
                    if ($debug) {
                        print "Skipping because does not pass population parameter URL filter</p>";
                    }
                    continue;
                }

                if (!is_null($feed->filterTypeEmail) && !ProcessLeads::filterValue($feed->filterTypeEmail, $data['email'], $feed->filterEmail)) {
                    if ($debug) {
                        print "Skipping because does not pass population parameter email filter</p>";
                    }
                    continue;
                }

                if (!is_null($feed->filterTypeListcode) && !ProcessLeads::filterValue($feed->filterTypeListcode, $data['listcode'], $feed->filterListcode)) {
                    if ($debug) {
                        print "Skipping because does not pass population parameter listcode filter</p>";
                    }
                    continue;
                }

                // Ensure we haven't reached our daily limit of records
                if (!is_null($feed->dailyLimit) && intval($feed->dailyLimit) > 0) {
                    $cnt = $leads->getOutboundDailyCount($feed->idFeedOut);
                    if ($cnt && $cnt >= $feed->dailyLimit) {
                        //$leads->logError( 'Feed ' . $feed->label . ' Daily feed limit of ' . $feed->dailyLimit . ' reached', true, false );
                        if ($debug) {
                            print "Skipping because we've hit our feed limit of {$feed->dailyLimit}</p>";
                        }
                        continue;
                    }
                }

                // Handle URL rewriting
                $urlRewritten = false;
                if (!empty($feed->forceUrl) && !empty($feed->forceUrlList)) {

                    $forceUrls = explode(';', $feed->forceUrlList);
                    if (!empty($forceUrls) && is_array($forceUrls) && sizeOf($forceUrls) > 0) {
                        shuffle($forceUrls); // Randomize the order of the array incase we are re-writing to multiple URLs

                        foreach ($forceUrls as $forceUrl) {
                            $mapping = explode('=', $forceUrl, 2);
                            if (!empty($mapping[0]) && !empty($mapping[1])) {
                                if (parse_url($data['url'], PHP_URL_HOST) === $mapping[0]) {
                                    $data['url'] = 'http://' . $mapping[1];
                                    $urlRewritten = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (empty($idFeedOut) && ('livedata' == $feed->queueType || 'waterfall' == $feed->queueType || 'waterfallLimitLive' == $feed->queueType)) {

                    $liveData['enabled'] = true;

                    // Ensure we are within scheduled time frame
                    if (!ProcessLeads::isWithinProcessingSchedule($feed)) {
                        if ($debug) {
                            print "Skipping because not within processing schedule</p>";
                        }
                        continue;
                    }

                    // If we already had an accepted waterfall submission, skip the rest of the waterfall candidates.
                    if ('waterfall' == $feed->queueType && $liveData['accepted']) {
                        if ($debug) {
                            print "Skipping because of previously accepted waterfall</p>";
                        }
                        continue;
                    }

                    // If we already processed any "waterfallLimitLive" submission, skip the rest of the waterfallLimitLive candidates.
                    if ('waterfallLimitLive' == $feed->queueType && $liveData['anyProcessed']) {
                        if ($debug) {
                            print "Skipping because of previously anyProcessed waterfall</p>";
                        }
                        continue;
                    }
                }

                $leads->outboundAdd($idRecord, null, $feedParams->idFeedIn, $feed->idFeedOut, $data['url'], ((empty($idFeedOut) && ('livedata' == $feed->queueType || 'waterfall' == $feed->queueType || 'waterfallLimitLive' == $feed->queueType)) ? -1 : 0), $urlRewritten);

                // If this is one of the "livedata" populations, immediately try to send the record through to the receiving feed.
                if (empty($idFeedOut) && ('livedata' == $feed->queueType || 'waterfall' == $feed->queueType || 'waterfallLimitLive' == $feed->queueType)) {
                    $record = $leads->getOutboundRecord($idRecord, $feed->idFeedOut, -1);
                    if (!empty($record)) {
                        $feedOut = $leads->getOutboundFeed($feed->idFeedOut);
                        $status = ProcessLeads::pushOutboundData($feedOut, $record);
                        if ((isset($status['status']) && $status['status'] != true)) {

                            $liveData['reason'] = sprintf('Third-party rejection [Reason: %s] [Code: O%s0]',
                                $status['text'],
                                $feed->idFeedOut
                            );
                            $liveData['anyProcessed'] = true;

                            if ($debug) {
                                print "Live record failed with: {$liveData['reason']}</p>";
                            }

                        } else {
                            if ($debug) {
                                print "Live record succeeded</p>";
                            }

                            $liveData['accepted'] = true;
                            $liveData['anyProcessed'] = true;
                            $liveData['fields'] = $status['fields'] ?? [];
                        }
                    }
                } elseif ($debug) {
                    print "</p>";
                }
            } // foreach $feedsOut
        }

        // If there was at least one live feed enabled.
        if ($liveData['enabled']) {

            try {
                $randomChoke = random_int(1, 100);
            } catch (\Exception $e) {
                // Failure, so set to some unattainable value
                $randomChoke = 1000;
            }

            if (!$liveData['anyProcessed']) {
                // We did not attempt to send any records, probably because we were outside of feed processing hours.
                $result['reason'] = 'No suitable buyers found.';
                $leads->inboundProcess($idRecord, $feedParams->idFeedIn, $data['originalUrl'], date('Y-m-d'), $result['reason']);
            } elseif (!$liveData['accepted'] || (!empty($feedParams->chokePercent) && $randomChoke <= $feedParams->chokePercent)) {
                // All attempts failed, so send the last failure message or randomly choke the record.
                if (!$liveData['accepted']) {
                    $result['reason'] = $liveData['reason'];
                    $leads->inboundProcess($idRecord, $feedParams->idFeedIn, $data['originalUrl'], date('Y-m-d'), $result['reason']);
                } else {
                    $result['reason'] = sprintf('Third-party rejection [Reason: Duplicate record] [Code: I%s1]',
                        $feedParams->idFeedIn
                    );
                    $leads->inboundProcess($idRecord, $feedParams->idFeedIn, $data['originalUrl'], date('Y-m-d'), $result['reason']);
                }
            } else {
                $result['fields'] = $liveData['fields'] ?? [];
            }
        }

        return $result;
    }

    public static function pushOutboundData($feedOut, $row)
    {

        $leads = Leads::getInstance();

        $debug = false;
        $result = array(
            'status' => false,
            'text' => 'Unknown error.',
        );

        $staticFields = !empty($feedOut->staticFieldsJSON) ? json_decode($feedOut->staticFieldsJSON, true) : array();
        $varFields = !empty($feedOut->varFieldsJSON) ? json_decode($feedOut->varFieldsJSON, true) : array();
        $valueMap = !empty($feedOut->valueMap) ? json_decode($feedOut->valueMap, true) : array();

        // Override the outbound URL
        if (!empty($row->urlRewrite)) {
            $row->url = $row->urlRewrite;
        }

        // Check for legacy stamp field
        if (empty($row->stamp)) {
            $row->stamp = $row->leadstamp;
        }

        // Explode custom fields from the database
        if (!empty($row->customFields) && ($customFields = json_decode($row->customFields)) !== false && !empty($customFields)) {
            foreach ($customFields as $key => $val) {
                $row->{$key} = $val;
            }
        }

        // Perform any value translations on the data
        if (!empty($valueMap) && is_array($valueMap)) {
            foreach ($valueMap as $vm) {
                if (isset($vm['field'], $vm['oldValue'], $vm['newValue'], $row->{$vm['field']}) && $row->{$vm['field']} == $vm['oldValue']) {
                    $row->{$vm['field']} = $vm['newValue'];
                }
            }
        }

        // Check global and local suppression lists for email feeds
        if ('email' == $feedOut->feedCategory) {

            if (!empty($row->email) && $leads->checkSuppression($row->email, null)) {

                $result['text'] = 'LOCAL REJECTION: Email is suppressed (global)';
                $leads->outboundProcess($row, $feedOut, $result['text'], 0);

                if ($debug) {
                    print "\t" . $result['text'] . PHP_EOL;
                }
                return $result;

            } elseif (!empty($row->email) && $leads->checkSuppression($row->email, $feedOut->idCompany)) {

                $result['text'] = 'LOCAL REJECTION: Email is suppressed (company)';
                $leads->outboundProcess($row, $feedOut, $result['text'], 0);

                if ($debug) {
                    print "\t" . $result['text'] . PHP_EOL;
                }
                return $result;

            }
        }

        $requestdata = array();
        $xmldata = array();
        $headerdata = array();
        foreach ($staticFields as $key => $val) { //Compile Static Fields into the post array.
            ProcessLeads::assignValue($key, $val, $requestdata, $xmldata, $headerdata);
        }

        $genderMap = array(
            'M' => 'Male',
            'F' => 'Female',
        );

        if (!empty($row->stamp)) {
            try {
                // Convert from the DB timezone of UTC to whatever this feed is expecting us to send.
                $date = new DateTime($row->stamp, new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone($feedOut->timezone));
                $row->stamp = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // This should never happen since it's coming from the DB in a standard format.
            }
        } else {
            try {
                // Convert from the DB timezone of UTC to whatever this feed is expecting us to send.
                $date = new DateTime("now", new DateTimeZone('UTC'));
                $date->setTimezone(new DateTimeZone($feedOut->timezone));
                $row->stamp = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // This should never happen since it's coming from the DB in a standard format.
            }
        }

        foreach ($varFields as $key => $val) {
            switch ($val) {
                case 'urlAssign':
                    $urlassignments = explode(";", $feedOut->urlassignments);
                    $urlassignment = '';
                    foreach ($urlassignments as $instructions) {
                        if (!empty($instructions)) {
                            $fieldValuePair = explode("=", $instructions);
                            if (stripos($row->url, $fieldValuePair[0]) !== false) {
                                if ($debug) {
                                    echo "\tMatched assignment: " . $fieldValuePair[0] . "\n";
                                }
                                $urlassignment = $fieldValuePair[1];
                                break;
                            }
                        }
                    }
                    ProcessLeads::assignValue($key, $urlassignment, $requestdata, $xmldata, $headerdata);
                    break;

                case 'recordId':
                    ProcessLeads::assignValue($key, $row->idRecord ?? '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'dobUS':
                    ProcessLeads::assignValue($key, date("m-d-Y", strtotime($row->dob)), $requestdata, $xmldata, $headerdata);
                    break;

                case 'dob_slashes':
                    ProcessLeads::assignValue($key, date("m/d/Y", strtotime($row->dob)), $requestdata, $xmldata, $headerdata);
                    break;

                case 'dob_YYYY':
                    ProcessLeads::assignValue($key, date("Y", strtotime($row->dob)), $requestdata, $xmldata, $headerdata);
                    break;

                case 'dob_MM':
                    ProcessLeads::assignValue($key, date("m", strtotime($row->dob)), $requestdata, $xmldata, $headerdata);
                    break;

                case 'dob_DD':
                    ProcessLeads::assignValue($key, date("d", strtotime($row->dob)), $requestdata, $xmldata, $headerdata);
                    break;

                case 'gender_full':
                    ProcessLeads::assignValue($key, $genderMap[$row->gender] ?? $row->gender, $requestdata, $xmldata, $headerdata);
                    break;

                case 'stampUS':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("m-d-Y H:i:s") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stampUS_dateOnly':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("m-d-Y") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stamp_YYYYmmdd':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("Ymd") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stamp_YYYY-mm-dd':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("Y-m-d") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stampUSAMPM':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("m-d-Y h:i:sA") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stampUS+AMPM':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("m-d-Y h:i:s A") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stampUS_slashes':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format("m/d/Y H:i:s") : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'stamp_ISO8601':
                    ProcessLeads::assignValue($key, !empty($date) ? $date->format('c') : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'landline_areacode':
                    ProcessLeads::assignValue($key, 10 == strlen($row->landline) ? substr($row->landline, 0, 3) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'landline_NXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->landline) ? substr($row->landline, 3, 3) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'landline_XXXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->landline) ? substr($row->landline, 6, 4) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'landline_NXX+XXXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->landline) ? substr($row->landline, 3, 7) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'cellphone_areacode':
                    ProcessLeads::assignValue($key, 10 == strlen($row->cellphone) ? substr($row->cellphone, 0, 3) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'cellphone_NXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->cellphone) ? substr($row->cellphone, 3, 3) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'cellphone_XXXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->cellphone) ? substr($row->cellphone, 6, 4) : '', $requestdata, $xmldata, $headerdata);
                    break;

                case 'cellphone_NXX+XXXX':
                    ProcessLeads::assignValue($key, 10 == strlen($row->cellphone) ? substr($row->cellphone, 3, 7) : '', $requestdata, $xmldata, $headerdata);
                    break;

                default:
                    ProcessLeads::assignValue($key, $row->{$val} ?? '', $requestdata, $xmldata, $headerdata);
                    break;

            }
        }

        if ($debug) {
            echo "\tPosting Array: \n";
            print_r($requestdata);
        }

        $geturl = '';
        if ($feedOut->feedType == 'curlGET') { // Method is GET

            $geturl = $feedOut->postUrl . "?" . http_build_query($requestdata);
            if ($debug) {
                echo "\tGet URL: \n";
                echo "\t" . $geturl . "\n";
                echo "\tPosting data.\n";
            }
            $result['text'] = ProcessLeads::curlLead(
                "",
                $geturl,
                false,
                false,
                true,
                false,
                $headerdata
            );

        } elseif ($feedOut->feedType == 'curlPOST-urlencoded') { // Method is POST

            if ($debug) {
                echo "\tPosting data.\n";
            }
            $result['text'] = ProcessLeads::curlLead(
                http_build_query($requestdata),
                $feedOut->postUrl,
                true,
                false,
                true,
                false,
                $headerdata
            );

            $geturl = $feedOut->postUrl . "\n\nPOST BODY (application/x-www-form-urlencoded): " . http_build_query($requestdata);

        } elseif ('csvString' == $feedOut->feedType) { // Method is CVS

            $geturl = $feedOut->postUrl . "?data=";
            $flag = false;
            foreach ($requestdata as $field => $value) {
                if ($flag) {
                    $geturl .= ",";
                }
                $geturl .= urlencode(str_replace(',', '', $value));
                $flag = true;
            }
            if ($debug) {
                echo "\tGet URL (CSV): \n";
                echo "\t" . $geturl . "\n";
                echo "\tPosting data.\n";
            }
            $result['text'] = ProcessLeads::curlLead("", $geturl, false, false, true, false, $headerdata);

        } elseif ('JSON' == $feedOut->feedType) { // Method is JSON

            if ($debug) {
                echo "\tPosting JSON data.\n";
            }

            $geturl = $feedOut->postUrl . ' JSON BODY: ' . json_encode($requestdata);
            $headerdata[] = 'Content-Type: application/json';

            $result['text'] = ProcessLeads::curlLead(
                json_encode($requestdata),
                $feedOut->postUrl,
                true,
                false,
                true,
                false,
                $headerdata
            );

        } elseif ('soapPOST' == $feedOut->feedType) { // Method is JSON

            if ($debug) {
                echo "\tPosting SOAP data.\n";
            }

//			$xml = ProcessLeads::getXml813( $requestdata );
            $xml = $feedOut->xmlDTD;
            foreach ($xmldata as $key => $val) {
                $xml = str_replace('##' . $key . '##', htmlspecialchars($val, ENT_COMPAT | ENT_XML1), $xml);
            }

            $geturl = $feedOut->postUrl . ' SOAP BODY: ' . $xml;

            $client = new SoapClient($feedOut->postUrl, array('trace' => true));
            $response = $client->AddLeadsUsingXMLString(array('xmlstring' => $xml));
            $result['text'] = $client->__getLastResponse();

        } elseif ('xmlPOST' == $feedOut->feedType) { // Method is XML

            $geturl = $feedOut->postUrl . "?" . http_build_query($requestdata);
            if ($debug) {
                echo "\tPosting XML data.\n";
            }

            $xml = $feedOut->xmlDTD;
            foreach ($xmldata as $key => $val) {
                $xml = str_replace('##' . $key . '##', htmlspecialchars($val, ENT_COMPAT | ENT_XML1), $xml);
            }

            $result['text'] = ProcessLeads::curlLead(
                $xml,
                $geturl,
                true,
                false,
                true,
                false,
                $headerdata
            );

            $geturl = $geturl . ' XML BODY: ' . $xml;

        } else { // Method is post

            if ($debug) {
                echo "\tPosting data.\n";
            }
            $result['text'] = ProcessLeads::curlLead(
                $requestdata,
                $feedOut->postUrl,
                true,
                false,
                true,
                false,
                $headerdata
            );

            $geturl = $feedOut->postUrl . "\n\nPOST BODY (multipart/form-data): " . http_build_query($requestdata);
        }

        // Check if the response we got is a success for this feed.
        if (strpos($feedOut->successString, 'REGEX:') === 0) {

            // Check for a regular expression match
            if (preg_match(substr($feedOut->successString, 6), $result['text']) === 1) {
                $result['status'] = true;
            } else {
                $result['status'] = false;
            }

        } else {

            // Check for a direct substring comparison match
            $sucstr = str_replace('%', '', $feedOut->successString); // Remove mysql wildcards
            if (stripos($result['text'], $sucstr) !== false) {
                $result['status'] = true;
                if (921 == $feedOut->idFeedOut && ($json = json_decode($result['text'])) !== null && isset($json->{'patient_id'})) {
                    $result['fields']['patientId'] = $json->patient_id;
                }

            } else {
                $result['status'] = false;
            }
        }

        if ($debug) {
            echo "\tResponse: {$result['text']}\n";
        }

        if (empty($row->testRecord)) { // We don't need to record test records
            $leads->outboundProcess($row, $feedOut, substr(trim($result['text']), 0, 65535), $result['status']);
        }

        $result['querystring'] = $geturl;
        $result['headers'] = $headerdata;

        return $result;
    }

    public static function validateField($fieldType, &$value, $feedParams)
    {
        $result = array(
            'valid' => false,
            'reason' => 'No reason given.',
        );

        $c = true;

        switch ($fieldType) {
            case 'listcode':
                if ($c && strlen($value) > 20) {
                    $c = false;
                    $result['reason'] = 'List code (listcode) exceeds maximum allowed length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'List code (listcode) contains invalid characters.';
                }
                break;

            case 'leadId':
                if ($c && strlen($value) > 255) {
                    $c = false;
                    $result['reason'] = 'leadId exceeds maximum allowed length of 255 characters.';
                }
                break;

            case 'url':
                if ($c && strlen($value) > 500) {
                    $c = false;
                    $result['reason'] = 'URL (url) exceeds maximum allowed length.';
                }
                if ($c && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $c = false;
                    $result['reason'] = 'URL (url) is invalid.';
                }
                break;

            case 'ip':
                if ($c && strlen($value) > 45) {
                    $c = false;
                    $result['reason'] = 'IP (ip) exceeds maximum allowed length.';
                }
                if ($c && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $c = false;
                    $result['reason'] = 'IP (ip) is invalid.';
                }
                break;

            case 'stamp':
                if ($c) {
                    try {
                        // Convert from whatever timezone the feed is sending over to UTC before saving in the DB.
                        $date = new DateTime($value, new DateTimeZone($feedParams->timezone));
                        $date->setTimezone(new DateTimeZone('UTC'));
                        $value = $date->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $c = false;
                        $result['reason'] = 'Action Date (stamp) is invalid.';
                    }
                }
                if ($c
                    && $feedParams->rejectOldLeads
                    && strtotime($value) < strtotime($feedParams->rejectOldLeadsMaxAge)
                ) {
                    $c = false;
                    $result['reason'] = 'Action Date (stamp) is too old, lead rejected.';
                }
                break;

            case 'email':
                if ($c && strlen($value) > 150) {
                    $c = false;
                    $result['reason'] = 'Email (email) exceeds maximum allowed length.';
                }
                if ($c && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $c = false;
                    $result['reason'] = 'Email (email) is invalid.';
                }
                break;

            case 'fname':
                if ($c && strlen($value) > 50) {
                    $c = false;
                    $result['reason'] = 'First Name (fname) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 1) {
                    $c = false;
                    $result['reason'] = 'First Name (fname) does not meet required length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'First Name (fname) contains invalid characters.';
                }
                break;

            case 'lname':
                if ($c && strlen($value) > 50) {
                    $c = false;
                    $result['reason'] = 'Last Name (lname) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 1) {
                    $c = false;
                    $result['reason'] = 'Last Name (lname) does not meet required length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'Last Name (lname) contains invalid characters.';
                }
                break;

            case 'addr':
                if ($c && strlen($value) > 150) {
                    $c = false;
                    $result['reason'] = 'Address Line 1 (addr) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 3) {
                    $c = false;
                    $result['reason'] = 'Address Line 1 (addr) does not meet required length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'Address Line 1 (addr) contains invalid characters.';
                }
                break;

            case 'addr2':
                if ($c && strlen($value) > 150) {
                    $c = false;
                    $result['reason'] = 'Address Line 2 (addr2) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 3) {
                    $c = false;
                    $result['reason'] = 'Address Line 2 (addr2) does not meet required length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'Address Line 2 (addr2) contains invalid characters.';
                }
                break;

            case 'city':
                if ($c && strlen($value) > 75) {
                    $c = false;
                    $result['reason'] = 'City (city) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 3) {
                    $c = false;
                    $result['reason'] = 'City (city) does not meet required length.';
                }
                if ($c && hasinvalidchars($value)) {
                    $c = false;
                    $result['reason'] = 'City (city) contains invalid characters.';
                }
                break;

            case 'state':
                if ($c && strlen($value) > 25) {
                    $c = false;
                    $result['reason'] = 'State (state) exceeds maximum allowed length.';
                }
                if ($c && strlen($value) < 2) {
                    $c = false;
                    $result['reason'] = 'State (state) does not meet required length.';
                }
                if ($c && !onlyalphas($value)) {
                    $c = false;
                    $result['reason'] = 'State (state) contains invalid characters.';
                }
                break;

            case 'zip':
                if (strlen($value) < 5 || strlen($value) > 10) {
                    $c = false;
                    $result['reason'] = 'Zip (zip) is an invalid length.';
                }
                if ($c && hasinvalidzipchars($value)) {
                    $c = false;
                    $result['reason'] = 'Zip (zip) contains invalid characters.';
                }
                break;

            case 'dob':
                if ($c
                    && (
                        strtotime($value) == -1
                        || strtotime($value) == false
                    )
                ) {
                    $c = false;
                    $result['reason'] = 'Date of Birth (dob) is invalid.';
                }
                break;

            case 'gender':
                $allowedGenders = array('m', 'f', 'male', 'female', 'na', 'not applicable');
                if ($c && (
                    !in_array(strtolower($value), $allowedGenders)
                    )) {
                    $c = false;
                    $result['reason'] = 'Gender is an invalid value.';
                }
                break;

            case 'landline':
                if ($c && strlen($value) != 10) {
                    $c = false;
                    $result['reason'] = 'Default Phone (landline) is an invalid length.';
                }
                if ($c && !onlynos($value)) {
                    $c = false;
                    $result['reason'] = 'Default Phone (landline) contains invalid characters.';
                }
                break;

            case 'cellphone':
                if ($c && strlen($value) != 10) {
                    $c = false;
                    $result['reason'] = 'Alternate Phone (cellphone) is an invalid length.';
                }
                if ($c && !onlynos($value)) {
                    $c = false;
                    $result['reason'] = 'Alternate Phone (cellphone) contains invalid characters.';
                }
                break;

            case 'custom1':
            case 'custom2':
            case 'custom3':
            case 'custom4':
            case 'custom5':
            case 'custom6':
                if ($c && strlen($value) > 255) {
                    $c = false;
                    $result['reason'] = $fieldType . ' exceeds maximum allowed length of 255 characters.';
                }
                break;

            default:
                if ($c && strpos($fieldType, 'c_') === 0 && strlen($value) > 255) {
                    $c = false;
                    $result['reason'] = $fieldType . ' exceeds maximum allowed length of 255 characters.';
                }
                break;

        }

        if ($c) {
            $result['valid'] = true;
            $result['reason'] = ucfirst($fieldType) . ' passed validation.';
        }

        return $result;
    }

    public static function validateIncomingData($feedParams, &$data)
    {

        $leads = Leads::getInstance();

        $result = array();
        $result['valid'] = true;
        $result['errors'] = array();

        $requiredFields = explode(';', $feedParams->required);
        $allowedFields = explode(';', $feedParams->allowedFields);

        // Special handling for TurnTwo feed that cannot change what URL value is being sent to us
        if (!empty($data['url']) && 'www.5minutemoney.co.uk,www.5minutemoney.co.uk' == $data['url']) {
            $data['url'] = 'http://www.5minutemoney.co.uk';
        }

        // Special handling for Digital Bulldogs feed that contains an invalid URL
        if (!empty($data['url']) && 'https//www.instantcheckmate.com/register' == $data['url']) {
            $data['url'] = 'https://www.instantcheckmate.com/register';
        }

        // Fix cases where gender is set to a blank value
        if (!empty($data['gender']) && ' ' == $data['gender']) {
            unset($data['gender']);
        }

        // Fix legacy lead timestamp field
        if (!empty($data['leadstamp'])) {
            $data['stamp'] = $data['leadstamp'];
        }

        // Remove non-numeric characters from phone numbers
        if (!empty($data['landline'])) {
            $data['landline'] = preg_replace('/[^0-9]/', '', $data['landline']);
        }
        if (!empty($data['cellphone'])) {
            $data['cellphone'] = preg_replace('/[^0-9]/', '', $data['cellphone']);
        }

        // Fix incoming URLs missing a protocol so they validate properly
        if (!empty($data['url']) && strpos($data['url'], 'http') !== 0) {
            $data['url'] = 'http://' . $data['url'];
        }

        // Fix zip codes with a missing leading zero
        if (!empty($data['zip'])) {
            $data['zip'] = str_pad($data['zip'], 5, '0', STR_PAD_LEFT);
        }

        foreach ($requiredFields as $requiredKey) {
            switch ($requiredKey) {
                // Skip empty field definition
                case '':
                    break;

                case 'phone':
                    if (empty($data['landline']) && empty($data['cellphone'])) {
                        $result['valid'] = false;
                        $result['errors'][] = 'A phone number is required, either landline or cellphone. They cannot both be empty.';
                    }
                    break;

                default:
                    if (empty($data[$requiredKey])) {
                        $result['valid'] = false;
                        $result['errors'][] = $requiredKey . ' is a required field, and may not be empty.';
                    }
                    break;
            }
        }

        foreach ($allowedFields as $allowedField) {
            if (!empty($data[$allowedField])) {
                $validateResult = ProcessLeads::validateField($allowedField, $data[$allowedField], $feedParams);
                if (!$validateResult['valid']) {
                    $result['valid'] = false;
                    $result['errors'][] = $validateResult['reason'];
                }
            }
        }

        if (in_array('url', $allowedFields)) { //URL is expected so trim it and store in the database.
            if (empty($data['url'])) {
                $data['url'] = '';
            }
        }

        // Required fields are missing or invalid, so don't bother with further checks
        if (!$result['valid']) {
            return $result;
        }

        // Ensure we haven't reached our daily limit of records
        if (!is_null($feedParams->dailyLimit) && intval($feedParams->dailyLimit) > 0) {
            $cnt = $leads->getInboundDailyCount($feedParams->idFeedIn);
            if ($cnt && $cnt > $feedParams->dailyLimit) {
                $result['valid'] = false;
                $result['errors'][] = 'Daily lead limit has been reached.';
            }
        }

        // Check global suppression list for email feeds
        if (!empty($data['email']) && 'email' == $feedParams->feedCategory) {
            $exists = $leads->checkSuppression($data['email'], null);
            if ($exists === true) {
                $result['valid'] = false;
                $result['errors'][] = 'Email exists in our global suppression file.';
            }
        }

        if (!is_null($feedParams->filterTypeUrl)) {
            $urlAcceptable = ProcessLeads::filterValue($feedParams->filterTypeUrl, $data['url'], $feedParams->filterUrl);
            if (!$urlAcceptable) {
                $result['valid'] = false;
                $result['errors'][] = 'URL is not allowed on this feed.';
            }
        }

        if ($feedParams->dedupeEmail && !empty($data['email'])) {
            $dupeCount = $leads->inboundCheckDuplicates($feedParams->idFeedIn, 'email', $data, $feedParams->dedupeAcross);
            if ($dupeCount === null) {
                $result['valid'] = false;
                $result['errors'][] = 'Database failure - could not check duplicate email.';
            } elseif ($dupeCount === true) {
                $result['valid'] = false;
                $result['errors'][] = 'Duplicate email.';
            }
        }

        if ($feedParams->dedupeLandline && !empty($data['landline'])) {
            $dupeCount = $leads->inboundCheckDuplicates($feedParams->idFeedIn, 'landline', $data, $feedParams->dedupeAcross);
            if ($dupeCount === null) {
                $result['valid'] = false;
                $result['errors'][] = 'Database failure - could not check duplicate landline.';
            } elseif ($dupeCount === true) {
                $result['valid'] = false;
                $result['errors'][] = 'Duplicate landline phone.';
            }
        }

        if ($feedParams->dedupeCellphone && !empty($data['cellphone'])) {
            $dupeCount = $leads->inboundCheckDuplicates($feedParams->idFeedIn, 'cellphone', $data, $feedParams->dedupeAcross);
            if ($dupeCount === null) {
                $result['valid'] = false;
                $result['errors'][] = 'Database failure - could not check duplicate cellphone.';
            } elseif ($dupeCount === true) {
                $result['valid'] = false;
                $result['errors'][] = 'Duplicate cellphone.';
            }
        }

        if (!empty($data['cellphone']) && !empty($feedParams->filterTypeDNCScrub)) {
            $dncScrub = json_decode($feedParams->filterTypeDNCScrub);
            if (null !== $dncScrub && !empty($dncScrub->enabled) && !empty($dncScrub->rejectStatuses) && is_array($dncScrub->rejectStatuses)) {
                require_once(INCLUDES . 'dncScrub.php');
                $dnc = new DNCScrub();
                if (($dncResult = $dnc->scrub($data['cellphone'], $dncScrub->rejectStatuses)) !== true) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Cellphone was rejected by our third-party filters [DNC:' . $dncResult . ']';
                }
            }
        }

        if (!empty($data['landline']) && !empty($feedParams->filterTypeDNCScrub)) {
            $dncScrub = json_decode($feedParams->filterTypeDNCScrub);
            if (null !== $dncScrub && !empty($dncScrub->enabled) && !empty($dncScrub->rejectStatuses) && is_array($dncScrub->rejectStatuses)) {
                require_once(INCLUDES . 'dncScrub.php');
                $dnc = new DNCScrub();
                if (($dncResult = $dnc->scrub($data['landline'], $dncScrub->rejectStatuses)) !== true) {
                    $result['valid'] = false;
                    $result['errors'][] = 'Landline was rejected by our third-party filters [DNC:' . $dncResult . ']';
                }
            }
        }

        return $result;
    }

    public static function isWithinProcessingSchedule($feed)
    {
        if (!empty($feed->processingSchedule)) {
            $current_day = strtolower(date('D'));
            $current_time = date('H:i');
            $schedule_array = json_decode($feed->processingSchedule);
            if ($schedule_array !== false) {
                if (empty($schedule_array->$current_day->enabled) ||
                    (!empty($schedule_array->$current_day->startTime) && strtotime($current_time) < strtotime($schedule_array->$current_day->startTime)) ||
                    (!empty($schedule_array->$current_day->endTime) && strtotime($current_time) > strtotime($schedule_array->$current_day->endTime))
                ) {
                    return false;
                }
            }
        }

        return true;
    }

}
