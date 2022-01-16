<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_IMPORT, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF]);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

$status = !empty($_REQUEST['status']) ? $_REQUEST['status'] : null;

require_once(INCLUDES . 'display.php');

include(INCLUDES . "f_site.php");

$all_states = array(
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'DC' => 'District of Columbia',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming',
);

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0
        ,
        'error' => 'Action does not exist.',
    );
    switch ($_REQUEST['a']) {
        case "manageFeed":
            $c = true;
            $result['error'] = 'Failed when attempting to manage feeds.';
            $action = $_REQUEST['action'];

            if ($c && !LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                $c = false;
                $result['error'] = 'Sorry, you do not have permission to edit feeds.';
            }

            //Validate Input
            if ($c && empty($_REQUEST['label'])) {
                $c = false;
                $result['error'] = 'Feed label cannot be empty.';
            }

            if ($c && empty($_REQUEST['idCompany'])) {
                $c = false;
                $result['error'] = 'Company cannot be empty.';
            }

            $filterStateField = !empty($_REQUEST['filterState']) ? $_REQUEST['filterState'] : '';
            $filterStateChoice = empty($filterStateField) || empty($_REQUEST['filterStateChoice']) ? '' : $_REQUEST['filterStateChoice'];

            if ($c && !empty($filterStateField) && empty($filterStateChoice)) {
                $c = false;
                $result['error'] = 'If using the state filter feature, at least one state must be selected.';
            }

            $filterZipField = !empty($_REQUEST['filterZip']) ? $_REQUEST['filterZip'] : '';
            $filterZipCodes = empty($filterZipField) || empty($_REQUEST['filterZipCodes']) ? '' : $_REQUEST['filterZipCodes'];

            if ($c && !empty($filterZipField) && empty($filterZipCodes)) {
                $c = false;
                $result['error'] = 'If using the zip filter feature, at least one zip must be added.';
            }

            if ($c && !empty($filterZipCodes)) {
                foreach ($filterZipCodes as $filterZipCode) {
                    if (strlen($filterZipCode) === 0) {
                        $c = false;
                        $result['error'] = "Please remove any empty zip code filter entries.";
                        break;
                    } elseif (strlen($filterZipCode) !== 5) {
                        $c = false;
                        $result['error'] = "Zip Code {$filterZipCode} does not have exactly 5 characters.";
                        break;
                    }
                }
            }

            if ($c && (empty($_REQUEST['allowedFields']) || !is_array($_REQUEST['allowedFields']))) {
                // Must allow some fields, or the feed is worthless isn't it
                $c = false;
                $result['error'] = 'You must allow at least one field to be processed.';
            }

            if ($c) {
                //Make sure that any required fields are also allowed
                if (!empty($_REQUEST['required'])) {
                    foreach ($_REQUEST['required'] as $f) {
                        switch ($f) {
                            case "phone":
                                if (!in_array('landline', $_REQUEST['allowedFields']) || !in_array('cellphone', $_REQUEST['allowedFields'])) {
                                    $c = false;
                                    $result['error'] = 'If phone is selected, both landline and cellphone must be allowed fields.';
                                }
                                break;

                            default:
                                if (!in_array($f, $_REQUEST['allowedFields'])) {
                                    $c = false;
                                    $result['error'] = "If {$f} is a required field, then that field must be allowed as well.";
                                }
                        }
                        if (!$c) {
                            break;
                        }
                    }
                }
            }

            if ('phone-preping' === $_REQUEST['feedCategory']) {
                if ($c && (empty($_REQUEST['allowedPingFields']) || !is_array($_REQUEST['allowedPingFields']))) {
                    // Must allow some fields, or the feed is worthless isn't it
                    $c = false;
                    $result['error'] = 'You must allow at least one PING field to be processed.';
                }

                if ($c) {
                    //Make sure that any required fields are also allowed
                    if (!empty($_REQUEST['requiredPingFields'])) {
                        foreach ($_REQUEST['requiredPingFields'] as $f) {
                            switch ($f) {
                                case "phone":
                                    if (!in_array('landline', $_REQUEST['allowedPingFields']) || !in_array('cellphone', $_REQUEST['allowedFields'])) {
                                        $c = false;
                                        $result['error'] = 'If phone is selected, both landline and cellphone must be allowed PING fields.';
                                    }
                                    break;

                                default:
                                    if (!in_array($f, $_REQUEST['allowedFields'])) {
                                        $c = false;
                                        $result['error'] = "If {$f} is a required PING field, then that field must be an allowed PING field as well.";
                                    }
                            }
                            if (!$c) {
                                break;
                            }
                        }
                    }
                }
            }

            // Force authorization field for ping/post
            if ('phone-preping' === $_REQUEST['feedCategory']) {
                if (!in_array('authorization', $_REQUEST['allowedFields'])) {
                    $_REQUEST['allowedFields'][] = 'authorization';
                }
                if (!in_array('authorization', $_REQUEST['required'])) {
                    $_REQUEST['required'][] = 'authorization';
                }
            } else {
                if (in_array('authorization', $_REQUEST['allowedFields'])) {
                    $_REQUEST['allowedFields'] = array_diff($_REQUEST['allowedFields'], ['authorization']);
                }
                if (in_array('authorization', $_REQUEST['required'])) {
                    $_REQUEST['required'] = array_diff($_REQUEST['required'], ['authorization']);
                }
            }

            if ($c && !empty($_REQUEST['custom1Label']) && strlen($_REQUEST['custom1Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom1 label to 255 characters or less.';
            }

            if ($c && !empty($_REQUEST['custom2Label']) && strlen($_REQUEST['custom2Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom2 label to 255 characters or less.';
            }

            if ($c && !empty($_REQUEST['custom3Label']) && strlen($_REQUEST['custom3Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom3 label to 255 characters or less.';
            }

            if ($c && !empty($_REQUEST['custom4Label']) && strlen($_REQUEST['custom4Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom4 label to 255 characters or less.';
            }

            if ($c && !empty($_REQUEST['custom5Label']) && strlen($_REQUEST['custom5Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom5 label to 255 characters or less.';
            }

            if ($c && !empty($_REQUEST['custom6Label']) && strlen($_REQUEST['custom6Label']) > 255) {
                $c = false;
                $result['error'] = 'Please limit custom6 label to 255 characters or less.';
            }

            if ($c && empty($_REQUEST['timezone'])) {
                $c = false;
                $result['error'] = 'Please select the feed timezone from the list.';
            }

            if ($c && !empty($_REQUEST['dailyLimit']) && is_numeric($_REQUEST['dailyLimit']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the daily limit.';
            }

            if ($c && !empty($_REQUEST['dailyLimit']) && intval($_REQUEST['dailyLimit']) < 0) {
                $c = false;
                $result['error'] = 'Please enter a positive number for the daily limit.';
            }

            if ($c && is_numeric($_REQUEST['lookbackPeriod']) === false) {
                $c = false;
                $result['error'] = 'Please select a valid value for the lookback period.';
            }

            if ($c && (intval($_REQUEST['lookbackPeriod']) < 0 || intval($_REQUEST['lookbackPeriod']) > 120)) {
                $c = false;
                $result['error'] = 'Please select a valid value for the lookback period.';
            }

            if ('phone-preping' === $_REQUEST['feedCategory']) {
                if ($c && !empty($_REQUEST['pingTimeout']) && is_numeric($_REQUEST['pingTimeout']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the ping timeout.';
                }

                if ($c && !empty($_REQUEST['pingTimeout']) && intval($_REQUEST['pingTimeout']) < 0) {
                    $c = false;
                    $result['error'] = 'Please enter a positive value the ping timeout.';
                }
            }

            if ($c && !empty($_REQUEST['chokePercent']) && is_numeric($_REQUEST['chokePercent']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the choke percent.';
            }

            if ($c && !empty($_REQUEST['chokePercent']) && (intval($_REQUEST['chokePercent']) < 0 || intval($_REQUEST['chokePercent']) > 100)) {
                $c = false;
                $result['error'] = 'Please enter a value between 0 and 100 for the choke percent.';
            }

            if ($c && !empty($_REQUEST['costPerLead']) && is_numeric($_REQUEST['costPerLead']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the cost per lead.';
            }

            if ($c && !empty($_REQUEST['minimumBirthAge']) && is_numeric($_REQUEST['minimumBirthAge']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the minimum age.';
            }

            if ($c && !empty($_REQUEST['maximumBirthAge']) && is_numeric($_REQUEST['maximumBirthAge']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the maximum age.';
            }

            if ($c && !empty($_REQUEST['minimumBirthAge']) && !empty($_REQUEST['maximumBirthAge']) && $_REQUEST['maximumBirthAge'] <= $_REQUEST['minimumBirthAge']) {
                $c = false;
                $result['error'] = 'The maximum age must be larger than the minimum age.';
            }

            if ($c && !empty($_REQUEST['notifyThresholdCount']) && is_numeric($_REQUEST['notifyThresholdCount']) === false) {
                $c = false;
                $result['error'] = 'Please enter a numeric value for the notification threshold amount.';
            }

            if ($c && !empty($_REQUEST['notifyThresholdTime'])) {
                $notifyThresholdTime = DateTime::createFromFormat('Y-m-d g:iA',
                    (date('Y-m-d') . ' ' . $_REQUEST['notifyThresholdTime']));
                if (empty($notifyThresholdTime)) {
                    $result['error'] = 'Please enter a valid notification threshold time in the format hh:mmAM or hh:mmPM.';
                    $c = false;
                }
            }

            $filterUrl = '';
            $filterUrlMulti = array();
            if (!empty($_REQUEST['filterUrlMulti'])) {
                $filterUrlMulti = explode("\n", $_REQUEST['filterUrlMulti']);
            }
            $_REQUEST['filterUrl'] = !empty($_REQUEST['filterUrl']) ? array_merge($_REQUEST['filterUrl'],
                $filterUrlMulti) : $filterUrlMulti;
            if (!empty($_REQUEST['filterUrl']) && is_array($_REQUEST['filterUrl'])) {
                $_REQUEST['filterUrl'] = array_map('trim', $_REQUEST['filterUrl']);
                $filterUrl = implode(';', $_REQUEST['filterUrl']);
            }

            if ('new' == $action) {

                if ($c && !LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                    $c = false;
                    $result['error'] = 'Sorry, you do not have permission to add new feeds.';
                }

                if ($c && 'Q' === COMPANY_INITIALS) {
                    //Label can not be already used
                    $checkResult = $leads->checkInboundFeedLabelExists($_REQUEST['label']);
                    if (true === $checkResult) {
                        $c = false;
                        $result['error'] = 'That feed label is already being used.';
                    }
                }

                if ($c) { //Add entry to the database.

                    $filterStateArray = array(
                        'mode' => $filterStateField,
                        'states' => $filterStateChoice,
                    );
                    $filterState = json_encode($filterStateArray);

                    sort($filterZipCodes);
                    $filterZipArray = array(
                        'mode' => $filterZipField,
                        'zipCodes' => $filterZipCodes,
                    );
                    $filterZip = json_encode($filterZipArray);

                    $idFeedIn = $leads->addInboundFeed(array(
                        'label' => empty($_REQUEST['label']) ? null : $_REQUEST['label'],
                        'description' => empty($_REQUEST['description']) ? null : $_REQUEST['description'],
                        'idCompany' => empty($_REQUEST['idCompany']) ? null : $_REQUEST['idCompany'],
                        'required' => empty($_REQUEST['required']) ? null : implode(';', $_REQUEST['required']),
                        'allowedFields' => empty($_REQUEST['allowedFields']) ? null : implode(';', $_REQUEST['allowedFields']),
                        'requiredPingFields' => empty($_REQUEST['requiredPingFields']) ? null : implode(';', $_REQUEST['requiredPingFields']),
                        'allowedPingFields' => empty($_REQUEST['allowedPingFields']) ? null : implode(';', $_REQUEST['allowedPingFields']),
                        'password' => genFeedPass(),
                        'dedupeEmail' => empty($_REQUEST['dedupeEmail']) ? 0 : 1,
                        'dedupeLandline' => empty($_REQUEST['dedupeLandline']) ? 0 : 1,
                        'dedupeCellphone' => empty($_REQUEST['dedupeCellphone']) ? 0 : 1,
                        'dedupeAcross' => empty($_REQUEST['dedupeAcross']) ? null : $_REQUEST['dedupeAcross'],
                        'filterTypeUrl' => empty($_REQUEST['filterTypeUrl']) ? null : $_REQUEST['filterTypeUrl'],
                        'filterUrl' => empty($filterUrl) ? null : $filterUrl,
                        'notifications' => empty($_REQUEST['notifications']) ? 0 : 1,
                        'rejectOldLeads' => empty($_REQUEST['rejectOldLeadsMaxAge']) ? 0 : 1,
                        'rejectOldLeadsMaxAge' => empty($_REQUEST['rejectOldLeadsMaxAge']) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
                        'status' => empty($_REQUEST['status']) ? 'active' : $_REQUEST['status'],
                        'chokePercent' => empty($_REQUEST['chokePercent']) ? 0 : intval($_REQUEST['chokePercent']),
                        'filterState' => $filterState,
                        'filterZip' => $filterZip,
                        'feedCategory' => empty($_REQUEST['feedCategory']) ? 'email' : $_REQUEST['feedCategory'],
                        'dailyLimit' => empty($_REQUEST['dailyLimit']) ? null : intval($_REQUEST['dailyLimit']),
                        'custom1Label' => empty($_REQUEST['custom1Label']) ? null : $_REQUEST['custom1Label'],
                        'custom2Label' => empty($_REQUEST['custom2Label']) ? null : $_REQUEST['custom2Label'],
                        'custom3Label' => empty($_REQUEST['custom3Label']) ? null : $_REQUEST['custom3Label'],
                        'custom4Label' => empty($_REQUEST['custom4Label']) ? null : $_REQUEST['custom4Label'],
                        'custom5Label' => empty($_REQUEST['custom5Label']) ? null : $_REQUEST['custom5Label'],
                        'custom6Label' => empty($_REQUEST['custom6Label']) ? null : $_REQUEST['custom6Label'],
                        'costPerLead' => empty($_REQUEST['costPerLead']) ? 0.00 : floatval($_REQUEST['costPerLead']),
                        'notifyThresholdCount' => empty($_REQUEST['notifyThresholdCount']) ? 0 : $_REQUEST['notifyThresholdCount'],
                        'notifyThresholdTime' => !empty($notifyThresholdTime) ? $notifyThresholdTime->format('H:i:s') : null,
                        'notifyThresholdDays' => empty($_REQUEST['notifyThresholdDays']) ? null : implode(',', $_REQUEST['notifyThresholdDays']),
                        'salesperson' => empty($_REQUEST['salesperson']) ? null : $_REQUEST['salesperson'],
                        'pauseMessage' => empty($_REQUEST['pauseMessage']) ? null : trim($_REQUEST['pauseMessage']),
                        'timezone' => $_REQUEST['timezone'],
                        'timeskew' => empty($_REQUEST['timeskew']) ? null : $_REQUEST['timeskew'],
                        'lookbackPeriod' => empty($_REQUEST['lookbackPeriod']) ? 120 : $_REQUEST['lookbackPeriod'],
                        'pingTimeout' => empty($_REQUEST['pingTimeout']) ? 0 : $_REQUEST['pingTimeout'],
                        'minimumBirthAge' => empty($_REQUEST['minimumBirthAge']) ? null : $_REQUEST['minimumBirthAge'],
                        'maximumBirthAge' => empty($_REQUEST['maximumBirthAge']) ? null : $_REQUEST['maximumBirthAge'],
                    ));

                    if (null === $idFeedIn) {
                        $c = false;
                        $result['status'] = 0;
                        $result['error'] = 'Failed to create new feed.';
                    } else {
                        $result['status'] = 1;
                        $result['error'] = "Successfully created new feed #{$idFeedIn}.";
                        $leads->auditLog('FEEDINC:ADD', $idFeedIn);
                    }

                }
            } else {
                if ($c) {
                    if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                        $idCompany = LeadsSession::getCompanyId();
                        if (empty($idCompany)) {
                            $idCompany = -9999;
                        }
                        if (!$leads->checkInboundFeedAccess($idCompany, $_REQUEST['idFeedIn'])) {
                            $c = false;
                            $result['error'] = 'Sorry, you do not have access to this feed.';
                        }
                    } else {
                        $idCompany = empty($_REQUEST['idCompany']) ? null : $_REQUEST['idCompany'];
                    }
                }

                if ($c) {
                    $feed = $leads->getInboundFeed($_REQUEST['idFeedIn']);

                    if ($feed === false) {
                        $c = false;
                        $result['error'] = 'Database failure - could not fetch feed information for editing.';
                    }

                    if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_STAFF) && 'ppc' !== $feed->feedCategory) {
                        $c = false;
                        $result['error'] = 'Sorry, you do not have access to this feed.';
                    }
                }
                if ($c && $_REQUEST['label'] != $feed->label && 'Q' === COMPANY_INITIALS) { //Label is being altered.

                    if ($c) {
                        //Label can not be already used
                        $checkResult = $leads->checkInboundFeedLabelExists($_REQUEST['label']);
                        if (true === $checkResult) {
                            $c = false;
                            $result['error'] = 'Label is already in use.';
                        }
                    }
                }

                $filterStateField = !empty($_REQUEST['filterState']) ? $_REQUEST['filterState'] : '';
                $filterStateChoice = empty($filterStateField) || empty($_REQUEST['filterStateChoice']) ? '' : $_REQUEST['filterStateChoice'];

                if ($c && !empty($filterStateField) && empty($filterStateChoice)) {
                    $c = false;
                    $result['error'] = 'If using the state filter feature, at least one state must be selected.';
                }

                $filterZipField = !empty($_REQUEST['filterZip']) ? $_REQUEST['filterZip'] : '';
                $filterZipCodes = empty($filterZipField) || empty($_REQUEST['filterZipCodes']) ? '' : $_REQUEST['filterZipCodes'];

                if ($c && !empty($filterZipField) && empty($filterZipCodes)) {
                    $c = false;
                    $result['error'] = 'If using the zip filter feature, at least one zip must be added.';
                }

                if ($c && !empty($filterZipCodes)) {
                    foreach ($filterZipCodes as $filterZipCode) {
                        if (strlen($filterZipCode) === 0) {
                            $c = false;
                            $result['error'] = "Please remove any empty zip code filter entries.";
                            break;
                        } elseif (strlen($filterZipCode) !== 5) {
                            $c = false;
                            $result['error'] = "Zip Code {$filterZipCode} does not have exactly 5 characters.";
                            break;
                        }
                    }
                }

                if ($c && empty($_REQUEST['timezone'])) {
                    $c = false;
                    $result['error'] = 'Please select the feed timezone from the list.';
                }

                if ($c && (empty($_REQUEST['allowedFields']) || !is_array($_REQUEST['allowedFields']))) {
                    // Must allow some fields, or the feed is worthless isn't it
                    $c = false;
                    $result['error'] = 'You must allow at least one field to be processed.';
                }

                if ($c) {
                    //Make sure that any required fields are also allowed
                    if (!empty($_REQUEST['required'])) {
                        foreach ($_REQUEST['required'] as $f) {
                            switch ($f) {
                                case "phone":
                                    if (!in_array('landline', $_REQUEST['allowedFields']) || !in_array('cellphone', $_REQUEST['allowedFields'])) {
                                        $c = false;
                                        $result['error'] = 'If phone is selected, both landline and cellphone must be allowed fields.';
                                    }
                                    break;

                                default:
                                    if (!in_array($f, $_REQUEST['allowedFields'])) {
                                        $c = false;
                                        $result['error'] = "If {$f} is a required field, then that field must be allowed as well.";
                                    }
                            }
                            if (!$c) {
                                break;
                            }
                        }
                    }
                }

                if ('phone-preping' === $_REQUEST['feedCategory']) {
                    if ($c && (empty($_REQUEST['allowedPingFields']) || !is_array($_REQUEST['allowedPingFields']))) {
                        // Must allow some fields, or the feed is worthless isn't it
                        $c = false;
                        $result['error'] = 'You must allow at least one PING field to be processed.';
                    }

                    if ($c) {
                        //Make sure that any required fields are also allowed
                        if (!empty($_REQUEST['requiredPingFields'])) {
                            foreach ($_REQUEST['requiredPingFields'] as $f) {
                                switch ($f) {
                                    case "phone":
                                        if (!in_array('landline', $_REQUEST['allowedPingFields']) || !in_array('cellphone', $_REQUEST['allowedFields'])) {
                                            $c = false;
                                            $result['error'] = 'If phone is selected, both landline and cellphone must be allowed PING fields.';
                                        }
                                        break;

                                    default:
                                        if (!in_array($f, $_REQUEST['allowedFields'])) {
                                            $c = false;
                                            $result['error'] = "If {$f} is a required PING field, then that field must be an allowed PING field as well.";
                                        }
                                }
                                if (!$c) {
                                    break;
                                }
                            }
                        }
                    }

                }

                // Force authorization field for ping/post
                if ('phone-preping' === $_REQUEST['feedCategory']) {
                    if (!in_array('authorization', $_REQUEST['allowedFields'])) {
                        $_REQUEST['allowedFields'][] = 'authorization';
                    }
                    if (!in_array('authorization', $_REQUEST['required'])) {
                        $_REQUEST['required'][] = 'authorization';
                    }
                } else {
                    if (in_array('authorization', $_REQUEST['allowedFields'])) {
                        $_REQUEST['allowedFields'] = array_diff($_REQUEST['allowedFields'], ['authorization']);
                    }
                    if (in_array('authorization', $_REQUEST['required'])) {
                        $_REQUEST['required'] = array_diff($_REQUEST['required'], ['authorization']);
                    }
                }

                if ($c && !empty($_REQUEST['dailyLimit']) && is_numeric($_REQUEST['dailyLimit']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the daily limit.';
                }

                if ($c && !empty($_REQUEST['dailyLimit']) && intval($_REQUEST['dailyLimit']) < 0) {
                    $c = false;
                    $result['error'] = 'Please enter a positive number for the daily limit.';
                }

                if ($c && is_numeric($_REQUEST['lookbackPeriod']) === false) {
                    $c = false;
                    $result['error'] = 'Please select a valid value for the lookback period.';
                }

                if ($c && (intval($_REQUEST['lookbackPeriod']) < 0 || intval($_REQUEST['lookbackPeriod']) > 120)) {
                    $c = false;
                    $result['error'] = 'Please select a valid value for the lookback period.';
                }

                if ('phone-preping' === $_REQUEST['feedCategory']) {
                    if ($c && !empty($_REQUEST['pingTimeout']) && is_numeric($_REQUEST['pingTimeout']) === false) {
                        $c = false;
                        $result['error'] = 'Please enter a numeric value for the ping timeout.';
                    }

                    if ($c && !empty($_REQUEST['pingTimeout']) && intval($_REQUEST['pingTimeout']) < 0) {
                        $c = false;
                        $result['error'] = 'Please enter a positive value the ping timeout.';
                    }
                }

                if ($c && !empty($_REQUEST['chokePercent']) && is_numeric($_REQUEST['chokePercent']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the choke percent.';
                }

                if ($c && !empty($_REQUEST['chokePercent']) && (intval($_REQUEST['chokePercent']) < 0 || intval($_REQUEST['chokePercent']) > 100)) {
                    $c = false;
                    $result['error'] = 'Please enter a value between 0 and 100 for the choke percent.';
                }

                if ($c && !empty($_REQUEST['costPerLead']) && is_numeric($_REQUEST['costPerLead']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the cost per lead.';
                }

                if ($c && !empty($_REQUEST['minimumBirthAge']) && is_numeric($_REQUEST['minimumBirthAge']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the minimum age.';
                }

                if ($c && !empty($_REQUEST['maximumBirthAge']) && is_numeric($_REQUEST['maximumBirthAge']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the maximum age.';
                }

                if ($c && !empty($_REQUEST['minimumBirthAge']) && !empty($_REQUEST['maximumBirthAge']) && $_REQUEST['maximumBirthAge'] <= $_REQUEST['minimumBirthAge']) {
                    $c = false;
                    $result['error'] = 'The maximum age must be larger than the minimum age.';
                }

                if ($c && !empty($_REQUEST['notifyThresholdCount']) && is_numeric($_REQUEST['notifyThresholdCount']) === false) {
                    $c = false;
                    $result['error'] = 'Please enter a numeric value for the notification threshold amount.';
                }

                if ($c && !empty($_REQUEST['notifyThresholdTime'])) {
                    $notifyThresholdTime = DateTime::createFromFormat('Y-m-d g:iA',
                        (date('Y-m-d') . ' ' . $_REQUEST['notifyThresholdTime']));
                    if (empty($notifyThresholdTime)) {
                        $result['error'] = 'Please enter a valid notification threshold time in the format hh:mmAM or hh:mmPM.';
                        $c = false;
                    }
                }

                if ($c) {
                    // Remove old notifications from the database if we've now disabled them
                    if (empty($_REQUEST['notifications'])) {
                        $leads->deleteNotifications($_REQUEST['idFeedIn']);
                    }
                }

                if ($c) {

                    $filterStateArray = array(
                        'mode' => $filterStateField,
                        'states' => $filterStateChoice,
                    );
                    $filterState = json_encode($filterStateArray);

                    sort($filterZipCodes);
                    $filterZipArray = array(
                        'mode' => $filterZipField,
                        'zipCodes' => $filterZipCodes,
                    );
                    $filterZip = json_encode($filterZipArray);

                    $status = $leads->updateInboundFeed($_REQUEST['idFeedIn'], array(
                        'label' => trim($_REQUEST['label']),
                        'description' => empty($_REQUEST['description']) ? null : $_REQUEST['description'],
                        'idCompany' => empty($_REQUEST['idCompany']) ? null : $_REQUEST['idCompany'],
                        'required' => empty($_REQUEST['required']) ? null : implode(';', $_REQUEST['required']),
                        'allowedFields' => empty($_REQUEST['allowedFields']) ? null : implode(';', $_REQUEST['allowedFields']),
                        'requiredPingFields' => empty($_REQUEST['requiredPingFields']) ? null : implode(';', $_REQUEST['requiredPingFields']),
                        'allowedPingFields' => empty($_REQUEST['allowedPingFields']) ? null : implode(';', $_REQUEST['allowedPingFields']),
                        'dedupeEmail' => empty($_REQUEST['dedupeEmail']) ? 0 : 1,
                        'dedupeLandline' => empty($_REQUEST['dedupeLandline']) ? 0 : 1,
                        'dedupeCellphone' => empty($_REQUEST['dedupeCellphone']) ? 0 : 1,
                        'dedupeAcross' => empty($_REQUEST['dedupeAcross']) ? null : $_REQUEST['dedupeAcross'],
                        'filterTypeUrl' => empty($_REQUEST['filterTypeUrl']) ? null : $_REQUEST['filterTypeUrl'],
                        'filterUrl' => empty($filterUrl) ? null : $filterUrl,
                        'notifications' => empty($_REQUEST['notifications']) ? 0 : 1,
                        'rejectOldLeads' => empty($_REQUEST['rejectOldLeadsMaxAge']) ? 0 : 1,
                        'rejectOldLeadsMaxAge' => empty($_REQUEST['rejectOldLeadsMaxAge']) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
                        'status' => empty($_REQUEST['status']) ? 'active' : $_REQUEST['status'],
                        'chokePercent' => empty($_REQUEST['chokePercent']) ? 0 : intval($_REQUEST['chokePercent']),
                        'filterState' => $filterState,
                        'filterZip' => $filterZip,
                        'feedCategory' => empty($_REQUEST['feedCategory']) ? 'email' : $_REQUEST['feedCategory'],
                        'dailyLimit' => empty($_REQUEST['dailyLimit']) ? null : intval($_REQUEST['dailyLimit']),
                        'custom1Label' => empty($_REQUEST['custom1Label']) ? null : $_REQUEST['custom1Label'],
                        'custom2Label' => empty($_REQUEST['custom2Label']) ? null : $_REQUEST['custom2Label'],
                        'custom3Label' => empty($_REQUEST['custom3Label']) ? null : $_REQUEST['custom3Label'],
                        'custom4Label' => empty($_REQUEST['custom4Label']) ? null : $_REQUEST['custom4Label'],
                        'custom5Label' => empty($_REQUEST['custom5Label']) ? null : $_REQUEST['custom5Label'],
                        'custom6Label' => empty($_REQUEST['custom6Label']) ? null : $_REQUEST['custom6Label'],
                        'costPerLead' => empty($_REQUEST['costPerLead']) ? 0.00 : floatval($_REQUEST['costPerLead']),
                        'notifyThresholdCount' => empty($_REQUEST['notifyThresholdCount']) ? 0 : $_REQUEST['notifyThresholdCount'],
                        'notifyThresholdTime' => !empty($notifyThresholdTime) ? $notifyThresholdTime->format('H:i:s') : null,
                        'notifyThresholdDays' => empty($_REQUEST['notifyThresholdDays']) ? null : implode(',', $_REQUEST['notifyThresholdDays']),
                        'salesperson' => empty($_REQUEST['salesperson']) ? null : $_REQUEST['salesperson'],
                        'pauseMessage' => empty($_REQUEST['pauseMessage']) ? null : trim($_REQUEST['pauseMessage']),
                        'timezone' => $_REQUEST['timezone'],
                        'timeskew' => empty($_REQUEST['timeskew']) ? null : $_REQUEST['timeskew'],
                        'lookbackPeriod' => empty($_REQUEST['lookbackPeriod']) ? 120 : $_REQUEST['lookbackPeriod'],
                        'pingTimeout' => empty($_REQUEST['pingTimeout']) ? 0 : $_REQUEST['pingTimeout'],
                        'minimumBirthAge' => empty($_REQUEST['minimumBirthAge']) ? null : $_REQUEST['minimumBirthAge'],
                        'maximumBirthAge' => empty($_REQUEST['maximumBirthAge']) ? null : $_REQUEST['maximumBirthAge'],
                    ));

                    if (null === $status) {
                        $c = false;
                        $result['error'] = 'Error updating feed settings.';
                    } else {
                        $leads->auditLog('FEEDINC:EDIT', $_REQUEST['idFeedIn']);
                    }

                }

                if ($c) {
                    $result['status'] = 1;
                    $result['error'] = 'Successfully updated feed.';
                }
            }
            break;

        case 'exportData':
            $c = true;
            $result['error'] = 'Failed when trying to export data.';

            if ($c && !LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                $c = false;
                $result['error'] = 'Sorry, you do not have permission to export data.';
            }

            if ($c && !LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])
            ) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $_REQUEST['feedIds'][0] ?? -1)) {
                    $c = false;
                    $result['error'] = 'Sorry, you do not have access to this feed.';
                }
            }

            if ($c) {
                $feed = $leads->getInboundFeed($_REQUEST['feedIds'][0] ?? -1);
                if ($feed === false) {
                    $c = false;
                    $result['error'] = 'Database failure - could not fetch feed information.';
                }
                if ($c && !is_object($feed) && $feed == 0) {
                    $c = false;
                    $result['error'] = 'Error - could not fetch feed. Feed does not exist.';
                }
            }
            if ($c) {
                if (empty($_REQUEST['columns'])) {
                    $c = false;
                    $result['error'] = 'Error - you need to select data columns to export.';
                }
            }

            if ($c) {
                $jobId = $leads->addJob('export-incoming', $feed->idFeedIn, serialize($_REQUEST), '', 0);
                if (null === $jobId) {
                    $c = false;
                    $result['error'] = 'Error adding this job to the database.';
                } else {
                    $leads->auditLog('FEEDINC:EXPORT', $jobId);
                    $result['status'] = 1;
                    $result['error'] = 'Export job #' . $jobId . ' submitted successfully. You will be notified by email when your download is ready.';
                }
            }

            break;

        case "managePaused":
            $c = true;
            $result['error'] = 'Failed when attempting to manage incoming feed.';
            switch ($_REQUEST['action']) {
                case "toggle":
                    if ($c) {
                        $feed = $leads->getInboundFeed($_REQUEST['idFeedIn'] ?? '');
                        if (empty($feed)) {
                            $c = false;
                            $result['error'] = 'Database failure - could not fetch feed for editing.';
                        }
                    }
                    if ($c) {
                        if ($feed->paused) {
                            $paused = 0;
                        } else {
                            $paused = 1;
                        }

                        $alterResult = $leads->updateInboundFeed($_REQUEST['idFeedIn'], array('paused' => $paused));

                        if (empty($alterResult)) {
                            $c = false;
                            $result['error'] = 'Unable to update feed.';
                        } else {
                            $leads->auditLog('FEEDINC:PAUSED',
                                $feed->idFeedIn . ':' . ($paused ? 'PAUSED' : 'ENABLED'));
                        }
                    }
                    if ($c) {
                        $result['error'] = 'Successfully toggled paused status.';
                    }
                    break;
            }
            if ($c) {
                $result['status'] = 1;
            }
            break;
    }
    echo json_encode($result);
    exit;
}

if (isset($_REQUEST['d'])) {
    switch ($_REQUEST['d']) {
        case 'errorCount':
            Display::errorCount();
            break;

        case 'errorList':
            Display::errorList();
            break;

        case 'filterZipCode':
            $e = $_REQUEST['e'] ?? '';
            ?>
            <div>
                <input type='text' name='filterZipCodes[]' value=''/> <a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
            </div>
            <?php
            break;

        case 'dialog_editfeed':
            $id = 'edit_feedinc';
            $mode = 'edit';
            $idFeedIn = $_REQUEST['idFeedIn'];

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                die('Sorry, you do not have permission to edit feeds.');
            }

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $feed = $leads->getInboundFeed($idFeedIn);
            if ($feed === false) {
                ?>
                <p>Database failure - could not fetch requested feed information.</p>
                <?php
                exit;
            } elseif (!is_object($feed) && $feed == 0) {
                ?>
                <p>Could not fetch requested feed information - feed does not exist.</p>
                <?php
                exit;
            }
            $selectedRequired = explode(";", $feed->required);
            $selectedAllowedFields = explode(";", $feed->allowedFields);
            $selectedRequiredPingFields = explode(";", $feed->requiredPingFields);
            $selectedAllowedPingFields = explode(";", $feed->allowedPingFields);
            $selectedNotifyThresholdDays = !empty($feed->notifyThresholdDays) ? explode(",", $feed->notifyThresholdDays) : array();

        case 'dialog_newfeed':
            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                die('Sorry, you do not have permission to add new feeds.');
            }

            if (empty($id)) {
                $id = 'new_feedinc';
            }
            if (empty($mode)) {
                $mode = 'new';
            }
            $feedProps = array(
                'idFeedIn',
                'label',
                'description',
                'idCompany',
                'dedupeEmail',
                'dedupeLandline',
                'dedupeCellphone',
                'dedupeAcross',
                'filterTypeUrl',
                'notifications',
                'status',
                'rejectOldLeadsMaxAge',
                'chokePercent',
                'dailyLimit',
                'filterState',
                'filterZip',
                'feedCategory',
                'custom1Label',
                'custom2Label',
                'custom3Label',
                'custom4Label',
                'custom5Label',
                'custom6Label',
                'costPerLead',
                'notifyThresholdCount',
                'notifyThresholdTimeFormatted',
                'salesperson',
                'pauseMessage',
                'timezone',
                'timeskew',
                'lookbackPeriod',
                'pingTimeout',
                'minimumBirthAge',
                'maximumBirthAge',
            );
            foreach ($feedProps as $feedProp) {
                if (isset($feed)) {
                    ${"feed_" . $feedProp} = $feed->$feedProp;
                } elseif (isset($_REQUEST['options'][$feedProp])) {
                    ${"feed_" . $feedProp} = $_REQUEST['options'][$feedProp];
                } else {
                    if (in_array($feedProp, array('dedupeEmail', 'dedupeLandline', 'dedupeCellphone'))) {
                        ${"feed_" . $feedProp} = '0';
                    } elseif (in_array($feedProp, array('notifications'))) {
                        ${"feed_" . $feedProp} = '1';
                    } elseif (in_array($feedProp, array('rejectOldLeadsMaxAge'))) {
                        ${"feed_" . $feedProp} = '7 Days Ago';
                    } elseif ('timezone' == $feedProp) {
                        ${"feed_" . $feedProp} = 'America/New_York';
                    } else {
                        ${"feed_" . $feedProp} = '';
                    }
                }
            }
            $explodableProperties = array(
                'filterUrl',
            );
            foreach ($explodableProperties as $eP) {
                if (!isset($_REQUEST['options'][$eP])) {
                    if (!isset($feed->$eP)) {
                        ${"feed_" . $eP} = array();
                    } else {
                        ${"feed_" . $eP} = explode(";", $feed->$eP);
                    }
                } else {
                    if ($_REQUEST['options'][$eP] == '') {
                        ${"feed_" . $eP} = array();
                    } else {
                        ${"feed_" . $eP} = explode(";", $_REQUEST['options'][$eP]);
                    }
                }
            }

            if (!isset($selectedRequired)) {
                $selectedRequired = array('email', 'ip', 'url', 'stamp');
            }
            if (!isset($selectedAllowedFields)) {
                $selectedAllowedFields = $recordFields;
            }
            if (!isset($selectedRequiredPingFields)) {
                $selectedRequiredPingFields = [];
            }
            if (!isset($selectedAllowedPingFields)) {
                $selectedAllowedPingFields = [];
            }
            if (!isset($selectedNotifyThresholdDays)) {
                $selectedNotifyThresholdDays = array();
            }

            $companies = $leads->getCompanies('active');
            ?>

            <form id="<?php echo $id; ?>">
                <table class="table table-bordered table-condensed table-striped">
                    <tr>
                        <td>Feed Label</p></td>
                        <td>
                            <input type="hidden" name="idFeedIn" id="idFeedIn" value="<?php echo $feed_idFeedIn; ?>"/>
                            <?php if ('Q' === COMPANY_INITIALS && !empty($idFeedIn) && $idFeedIn < 123) { ?>
                                <input type="hidden" name="label" id="label"
                                       value="<?php echo Display::escHtml($feed_label); ?>"/><?php echo Display::escHtml($feed_label); ?>
                                <br/>(Cannot modify incoming feed labels created before 5/24/18)
                            <?php } else { ?>
                                <input class="input-long" type="text" name="label" id="label" value="<?php echo Display::escHtml($feed_label); ?>"/>
                            <?php } ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>Description</p></td>
                        <td>
                            <input class="input-long" type='text' name='description' id='description'
                                   value='<?php echo htmlentities($feed_description); ?>'/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>Company</p></td>
                        <td>

                            <?php if (LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) { ?>
                                <?php if ($companies === false) { ?>
                                    Database failure - could not fetch company list
                                <?php } elseif (!is_object($companies) && $companies == 0) { ?>
                                    There are no companies in the database. Please create a company before
                                    creating a feed.
                                <?php } else { ?>
                                    <select name='idCompany'
                                            id='idCompany'
                                    >
                                        <?php foreach ($companies as $company) { ?>
                                            <option value='<?php echo $company->idCompany; ?>'
                                                    <?php if ($company->idCompany == $feed_idCompany){
                                                    ?>selected='selected'<?php } ?>
                                            ><?php echo $company->name; ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                            <?php } else { ?>
                                <?php echo $idCompany; ?>
                                <input type="hidden" name="idCompany" id="idCompany" value="<?php echo $idCompany; ?>"/>
                            <?php } ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <script>
                            $('input[name="filterState"]:radio').change(function () {
                                if ($(this).val() == 'includeOnly' || $(this).val() == 'excludeOnly') {
                                    $('#filterStateChoice').show();
                                } else {
                                    $('#filterStateChoice').hide();
                                }
                            });
                        </script>
                        <td><p>Filter State</p></td>
                        <td>
                            <?php $filterState_value = json_decode($feed_filterState); ?>
                            <p>Use this feature to limit which state(s) leads are allowed to come from.</p>
                            <p>
                                <input type="radio" name="filterState"
                                       value=""<?php if (empty($filterState_value->mode)) {
                                    print ' checked="checked"';
                                } ?> /> Off<br/>
                                <input type="radio" name="filterState"
                                       value="includeOnly"<?php if (!empty($filterState_value->mode) && 'includeOnly' == $filterState_value->mode) {
                                    print ' checked="checked"';
                                } ?> /> Include Only<br/>
                                <input type="radio" name="filterState"
                                       value="excludeOnly"<?php if (!empty($filterState_value->mode) && 'excludeOnly' == $filterState_value->mode) {
                                    print ' checked="checked"';
                                } ?> /> Exclude Only<br/>
                            </p>
                            <div id="filterStateChoice"<?php if (empty($filterState_value->mode) || ($filterState_value->mode != 'includeOnly' && $filterState_value->mode != 'excludeOnly')) {
                                echo ' style="display: none;"';
                            } ?>>
                                <p>Choose which states to include/exclude.</p>
                                <p><?php foreach ($all_states as $abbr => $st) { ?>
                                        <label class="checkbox-label"><input type='checkbox'
                                                                             name='filterStateChoice[]'
                                                                             value='<?php echo $abbr; ?>'<?php if (!empty($filterState_value->states) && in_array($abbr,
                                                    $filterState_value->states)) { ?> checked='checked'<?php } ?> />&nbsp;<?php echo $st; ?>
                                        </label>
                                    <?php } ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <script>
                            $('input[name="filterZip"]:radio').change(function () {
                                if ($(this).val() === 'includeOnly' || $(this).val() === 'excludeOnly') {
                                    $('#filterZipChoice').show();
                                } else {
                                    $('#filterZipChoice').hide();
                                }
                            });
                        </script>
                        <td><p>Filter Zip Code</p></td>
                        <td>
                            <?php $filterZip_value = json_decode($feed_filterZip); ?>
                            <p>Use this feature to limit which zip code(s) leads are allowed to come from.</p>
                            <p>
                                <input type="radio" name="filterZip"
                                       value=""<?php if (empty($filterZip_value->mode)) {
                                    print ' checked="checked"';
                                } ?> /> Off<br/>
                                <input type="radio" name="filterZip"
                                       value="includeOnly"<?php if (!empty($filterZip_value->mode) && 'includeOnly' == $filterZip_value->mode) {
                                    print ' checked="checked"';
                                } ?> /> Include Only<br/>
                                <input type="radio" name="filterZip"
                                       value="excludeOnly"<?php if (!empty($filterZip_value->mode) && 'excludeOnly' == $filterZip_value->mode) {
                                    print ' checked="checked"';
                                } ?> /> Exclude Only<br/>
                            </p>
                            <div id="filterZipChoice"<?php if (empty($filterZip_value->mode) || ($filterZip_value->mode != 'includeOnly' && $filterZip_value->mode != 'excludeOnly')) {
                                echo ' style="display: none;"';
                            } ?>>
                                <p>Choose which zip codes to include/exclude.</p>
                                <p>
                                    <a href='#' class='nonLink' onclick='element("filterZipCodes_container", "filterZipCode", {});'>Add New Zip Code</a>
                                </p>
                                <div>
                                    <div id='filterZipCodes_container'>
                                        <?php
                                        if (!empty($filterZip_value->zipCodes)) {
                                            foreach ($filterZip_value->zipCodes as $zipCode) {
                                                ?>
                                                <div>
                                                    <input type='text' name='filterZipCodes[]' value='<?php echo Display::escHtml($zipCode); ?>'/>
                                                    <a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
                                                </div>
                                            <?php }
                                        } ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Feed Category</p></td>
                        <td>
                            <p>Determines which section this feed shows up under on the dashboard.</p>
                            <p>
                                <?php
                                reset($feedCategories);
                                if ('EQ' == COMPANY_INITIALS) {
                                    $firstKey = 'phone'; // EQ defaults to phone
                                } else {
                                    $firstKey = key($feedCategories);
                                }
                                foreach ($feedCategories as $categoryKey => $categoryVal) {
                                    printf('<input type="radio" name="feedCategory" value="%s"%s/> %s<br/>',
                                        Display::escHtml($categoryKey),
                                        (empty($feed_feedCategory) && $categoryKey === $firstKey) || $feed_feedCategory == $categoryKey ? ' checked="checked"' : '',
                                        Display::escHtml($categoryVal)
                                    );
                                } ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Timezone</p></td>
                        <td>
                            <p>Specify what timezone the incoming leads are being sent as. Please confirm with the
                                vendor, as this may throw off the timestamps that are being sent to the client if
                                incorrect.</p>
                            <p>
                                <select name="timezone">
                                    <?php
                                    $timezones = DateTimeZone::listIdentifiers();
                                    foreach ($timezones as $timezone) {
                                        printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                                            Display::escHtml($timezone),
                                            $feed_timezone == $timezone ? ' selected="selected"' : '',
                                            Display::escHtml($timezone)
                                        );
                                    }
                                    ?>
                                </select>
                            </p>
                        </td>
                    </tr>
                    <tr class="preping-row" <?php if ('phone-preping' !== $feed_feedCategory) {
                        print ' style="display:none;"';
                    } ?>>
                        <td>Required PING Fields</p></td>
                        <td>
                            <?php foreach ($leads->getInboundFields() as $f) { ?>
                                <label class="checkbox-label"><input type='checkbox'
                                                                     name='requiredPingFields[]'
                                                                     value='<?php echo Display::escHtml($f->fieldName); ?>'
                                                                     <?php if (in_array($f->fieldName,
                                                                         $selectedRequiredPingFields)){ ?>checked='checked'<?php } ?> />&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr class="preping-row" <?php if ('phone-preping' !== $feed_feedCategory) {
                        print ' style="display:none;"';
                    } ?>>
                        <td>Allowed PING Fields</p></td>
                        <td>
                            <?php foreach ($leads->getInboundFields() as $f) { ?>
                                <label class="checkbox-label"><input type='checkbox'
                                                                     name='allowedPingFields[]'
                                                                     value='<?php echo Display::escHtml($f->fieldName); ?>'
                                                                     <?php if (in_array($f->fieldName,
                                                                         $selectedAllowedPingFields)){ ?>checked='checked'<?php } ?> />&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Required POST Fields</p></td>
                        <td>
                            <?php foreach ($leads->getInboundFields() as $f) { ?>
                                <label class="checkbox-label"><input type='checkbox'
                                                                     name='required[]'
                                                                     value='<?php echo Display::escHtml($f->fieldName); ?>'
                                                                     <?php if (in_array($f->fieldName,
                                                                         $selectedRequired)){ ?>checked='checked'<?php } ?> />&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Allowed POST Fields</p></td>
                        <td>
                            <?php foreach ($leads->getInboundFields() as $f) { ?>
                                <label class="checkbox-label"><input type='checkbox'
                                                                     name='allowedFields[]'
                                                                     value='<?php echo Display::escHtml($f->fieldName); ?>'
                                                                     <?php if (in_array($f->fieldName,
                                                                         $selectedAllowedFields)){ ?>checked='checked'<?php } ?> />&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Legacy Custom Fields</td>
                        <td>
                            <p>Use this section to store notes about what each custom field is being used for. <strong>These
                                    notes will be shown to the vendor in the API posting specs, so don't include
                                    anything proprietary.</strong></p>
                            custom1 = <input class="input-long" type="text" name="custom1Label"
                                             value="<?php echo Display::escHtml($feed_custom1Label); ?>"/><br/>
                            custom2 = <input class="input-long" type="text" name="custom2Label"
                                             value="<?php echo Display::escHtml($feed_custom2Label); ?>"/><br/>
                            custom3 = <input class="input-long" type="text" name="custom3Label"
                                             value="<?php echo Display::escHtml($feed_custom3Label); ?>"/><br/>
                            custom4 = <input class="input-long" type="text" name="custom4Label"
                                             value="<?php echo Display::escHtml($feed_custom4Label); ?>"/><br/>
                            custom5 = <input class="input-long" type="text" name="custom5Label"
                                             value="<?php echo Display::escHtml($feed_custom5Label); ?>"/><br/>
                            custom6 = <input class="input-long" type="text" name="custom6Label"
                                             value="<?php echo Display::escHtml($feed_custom6Label); ?>"/>
                        </td>
                    </tr>
                    <tr>
                        <td>Duplicate Filters</p></td>
                        <td>
                            <label class="checkbox-label"><input type='checkbox'
                                                                 name='dedupeEmail' value='1'
                                                                 <?php if ($feed_dedupeEmail){ ?>checked='checked'<?php } ?> />&nbsp;Reject
                                Duplicate Emails</label>
                            <label class="checkbox-label"><input type='checkbox'
                                                                 name='dedupeLandline'
                                                                 value='1'
                                                                 <?php if ($feed_dedupeLandline){ ?>checked='checked'<?php } ?> />&nbsp;Reject
                                Duplicate Landline Numbers</label>
                            <label class="checkbox-label"><input type='checkbox'
                                                                 name='dedupeCellphone'
                                                                 value='1'
                                                                 <?php if ($feed_dedupeCellphone){ ?>checked='checked'<?php } ?> />&nbsp;Reject
                                Duplicate Cellphone Numbers</label>
                        </td>
                    </tr>
                    <tr>
                        <td>Duplicate Options</p></td>
                        <td>
                            DISABLED: <input type='radio' name='dedupeAcross' id='dedupeAcross_none' value='none'
                                             <?php if ($feed_dedupeAcross == 'none'){ ?>checked='checked'<?php } ?> />
                            Allow duplicate records<br/>
                            THIS FEED: <input type='radio' name='dedupeAcross' id='dedupeAcross_all' value='all'
                                              <?php if ($feed_dedupeAcross == 'all'){ ?>checked='checked'<?php } ?> />
                            Dedupe across all records of this feed
                            <input type='radio' name='dedupeAcross' id='dedupeAcross_url' value='url'
                                   <?php if ($feed_dedupeAcross == 'url'){ ?>checked='checked'<?php } ?> /> Dedupe
                            across same URL of this feed
                            <input type='radio' name='dedupeAcross' id='dedupeAcross_listcode' value='listcode'
                                   <?php if ($feed_dedupeAcross == 'listcode'){ ?>checked='checked'<?php } ?> /> Dedupe
                            across same listcode of this feed<br/>
                            ALL FEEDS: <input type='radio' name='dedupeAcross' id='dedupeAcross_global'
                                              value='allGlobal'
                                              <?php if ($feed_dedupeAcross == 'allGlobal'){ ?>checked='checked'<?php } ?> />
                            Dedupe across all records of all feeds
                            <input type='radio' name='dedupeAcross' id='dedupeAcross_url' value='urlGlobal'
                                   <?php if (empty($feed_dedupeAcross) || $feed_dedupeAcross == 'urlGlobal'){ ?>checked='checked'<?php } ?> />
                            Dedupe across same URL of all feeds
                            <input type='radio' name='dedupeAcross' id='dedupeAcross_listcode' value='listcodeGlobal'
                                   <?php if ($feed_dedupeAcross == 'listcodeGlobal'){ ?>checked='checked'<?php } ?> />
                            Dedupe across same listcode of all feeds

                            <p style="margin-top: 1em;">Lookback period</p>
                            <input type='radio' name='lookbackPeriod' id='lookbackPeriod_30' value='30'<?php if ('30' === $feed_lookbackPeriod) { ?> checked='checked'<?php } ?>/> 30 days<br/>
                            <input type='radio' name='lookbackPeriod' id='lookbackPeriod_60' value='60'<?php if ('60' === $feed_lookbackPeriod) { ?> checked='checked'<?php } ?>/> 60 days<br/>
                            <input type='radio' name='lookbackPeriod' id='lookbackPeriod_90' value='90'<?php if ('90' === $feed_lookbackPeriod) { ?> checked='checked'<?php } ?>/> 90 days<br/>
                            <input type='radio' name='lookbackPeriod' id='lookbackPeriod_120' value='120'<?php if (empty($feed_lookbackPeriod) || '120' === $feed_lookbackPeriod) { ?> checked='checked'<?php } ?>/> 120
                            days (default)<br/>

                        </td>
                    </tr>
                    <tr>
                        <td>URL Filter Options</p></td>
                        <td>

                            <p>
                                Using the 'Accept' option, urls that are listed here are the only ones that will be
                                accepted into
                                the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed
                                here.
                            </p>

                            <input type='radio'
                                   name='filterTypeUrl'
                                   id='filterTypeUrl_disabled'
                                   value=''
                                <?php if (
                                    empty($feed_filterTypeUrl)
                                ) { ?>
                                    checked='checked'
                                <?php } ?>
                                   onclick="$('#toggler_filterTypeUrl').hide(); <?php
                                   ?>$('#filterUrl_descriptor').html('Do nothing with');"
                            /> Disabled<br/>
                            <input type='radio'
                                   name='filterTypeUrl'
                                   id='filterTypeUrl_accept'
                                   value='accept'
                                <?php if ($feed_filterTypeUrl == 'accept') { ?>
                                    checked='checked'
                                <?php } ?>
                                   onclick="$('#toggler_filterTypeUrl').show(); <?php
                                   ?>$('#filterUrl_descriptor').html('Accept');"
                            /> Accept<br/>
                            <input type='radio'
                                   name='filterTypeUrl'
                                   id='filterTypeUrl_reject'
                                   value='reject'
                                <?php if ($feed_filterTypeUrl == 'reject') { ?>
                                    checked='checked'
                                <?php } ?>
                                   onclick="$('#toggler_filterTypeUrl').show(); <?php
                                   ?>$('#filterUrl_descriptor').html('Reject');"
                            /> Reject<br/>
                            </p>
                            <div id='toggler_filterTypeUrl'
                                 style='display:<?php
                                 if (empty($feed_filterTypeUrl)) {
                                     echo "none";
                                 } else {
                                     echo "block";
                                 }
                                 ?>;'
                            >
                                <p>The following urls:</p>
                                <p>
                                    <a href='#' class='nonLink'
                                       onclick='element("filterUrl_container", "element_filter", { "e": "<?php echo $e ?? ''; ?>", "type": "Url" });'
                                    >Add New URL to <span id='filterUrl_descriptor'></span></a>
                                    | <a href='#' class='nonLink'
                                         onclick='element("filterUrl_multipleInsert"<?php
                                         ?>, "element_multifilter"<?php
                                         ?>, { "e": "<?php echo $e ?? ''; ?>"<?php
                                         ?>, "type": "Url" });'
                                    >Add Multiple</a>
                                </p>
                                <div id='filterUrl_multipleInsert'></div>
                                <div id='filterUrl_container'>
                                    <?php foreach ($feed_filterUrl as $filterUrl) { ?>
                                        <div>
                                            <input type='text'
                                                   name='filterUrl[]'
                                                   value='<?php echo $filterUrl; ?>'
                                            />
                                            <a href='#' class='nonLink'
                                               onclick='$(this).parent().remove(); return false;'>[X]</a>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Lead Rejections</p></td>
                        <td>
                            <p>How old are leads allowed to be before we reject them? This should be a text string like
                                "7 Days Ago" or "30 Days Ago". Do not enter just a number. A blank value disables this
                                feature.</p>
                            <p>
                                <input type='text' name='rejectOldLeadsMaxAge' id='rejectOldLeadsMaxAge'
                                       value='<?php echo Display::escHtml($feed_rejectOldLeadsMaxAge); ?>'
                                       class='input-long'/>
                            </p>
                        </td>
                    </tr>
                    <tr class="preping-row" <?php if ('phone-preping' !== $feed_feedCategory) {
                        print ' style="display:none;"';
                    } ?>>
                        <td>Ping/Post Timeout</p></td>
                        <td>
                            <p>Enter the maximum number of <strong>seconds</strong> allowed between a PING and a POST. If a PING record is not POSTed in this time period, the POST will be rejected. To disable the timeout
                                feature, set to
                                0.</p>
                            <p>300 seconds = 5 minutes<br/>
                                3600 seconds = 1 hour<br/>
                                86400 seconds = 1 day
                            </p>
                            <p>
                                <input type='text' name='pingTimeout' id='pingTimeout' value='<?php echo Display::escHtml($feed_pingTimeout); ?>' class='input-long'/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Daily Feed Limit</p></td>
                        <td>
                            <p>Leave blank for no limit (default). If a value is supplied here, the feed will stop
                                accepting records after the daily limit is reached.</p>
                            <p>Note: If a choke percentage is defined below, then we will silently accept that
                                percentage of leads over and above this daily limit. So if the choke is set at 25% and
                                the feed limit is set at 750, we will accept approximately 1,000 leads before hitting
                                the daily limit (but still only show 750 were accepted to the vendor).</p>
                            <p>
                                <input type="text" name="dailyLimit"
                                       value="<?php echo Display::escHtml($feed_dailyLimit); ?>"/>
                            </p>
                            <?php if (!empty($feed_chokePercent)) { ?>
                                <p>Effective daily limit with
                                    choke: <?php echo round($feed_dailyLimit / ((100 - $feed_chokePercent) * .01)); ?></p>
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Choke Percent</p></td>
                        <td>
                            <p>The percentage of leads that will randomly be rejected. For example, entering a value of
                                "20" means that approximately 20% of all leads coming in will be rejected. This feature
                                ONLY applies to feeds that are setup as "live" or "waterfall" on the outgoing population
                                side. Normally this value is zero.</p>
                            <p>
                                <input type="text" name="chokePercent" id="chokePercent"
                                       value="<?php echo Display::escHtml($feed_chokePercent); ?>"/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Cost Per Lead</p></td>
                        <td>
                            <p>
                                <input type="text" name="costPerLead"
                                       value="<?php echo Display::escHtml($feed_costPerLead); ?>"/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Salesperson</p></td>
                        <td>
                            <p>By default, salesperson revenues are assigned at a company level. Only set this value if
                                you are overriding the company-level salesperson with a feed-level salesperson.</p>
                            <p>
                                <select name="salesperson">
                                    <option></option>
                                    <?php
                                    $users = $leads->getStaffUsers(\PDO::FETCH_KEY_PAIR, true, $feed_salesperson);
                                    foreach ($users as $idUser => $fullName) {
                                        printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                                            Display::escHtml($idUser),
                                            $feed_salesperson == $idUser ? ' selected="selected"' : '',
                                            Display::escHtml($fullName)
                                        );
                                    }
                                    ?>
                                </select>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>Dormant Notifications</p></td>
                        <td>
                            <p>Should we send dormant URL notifications for URLs in this feed?</p>
                            <p>
                                <input type='radio' name='notifications' id='notifications_yes' value='1'
                                       <?php if ('1' == $feed_notifications) { ?>checked='checked'<?php } ?>/> Enabled
                                <input type='radio' name='notifications' id='notifications_no' value='0'
                                       <?php if ($feed_notifications != '1') { ?>checked='checked'<?php } ?>/> Disabled
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td>Threshold Notifications</p></td>
                        <td>
                            <p>Send an email notification if we have not received <input type="text"
                                                                                         name="notifyThresholdCount"
                                                                                         value="<?php echo htmlentities($feed_notifyThresholdCount); ?>"/>
                                leads by <input type="text" name="notifyThresholdTime" placeholder="Example: 10:00AM"
                                                value="<?php echo htmlentities($feed_notifyThresholdTimeFormatted); ?>"/>
                                on<br/>
                                <?php for ($i = 0; $i <= 6; $i++) { ?>
                                    <label class="checkbox-label"><input type="checkbox"
                                                                         name="notifyThresholdDays[]"
                                                                         value="<?php echo $i; ?>"
                                                                         <?php if (in_array($i,
                                                                             $selectedNotifyThresholdDays)){ ?>checked="checked"<?php } ?> />&nbsp;<?php echo $dowMap[$i]; ?>
                                    </label>
                                <?php } ?>
                            </p>
                            <p><strong>To disable notifications from being sent, set the lead count to zero or uncheck
                                    all day boxes.</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Pause Message</p></td>
                        <td>
                            <p>If the feed is paused, send this rejection message to the vendor. If nothing is set here,
                                the default message is "Lead rejected".</p>
                            <p>
                                <input type="text" name="pauseMessage"
                                       value="<?php echo Display::escHtml($feed_pauseMessage); ?>" class="input-long"/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Time Skew</p></td>
                        <td>
                            <p>If inbound timestamps on the feed should be manipulated before being saved to the DB,
                                enter the amount of the skew below. This feature is not normally used. Examples: "-14
                                days", "+5 hours", etc. Use the timezone feature to adjust for timezones.</p>
                            <p>
                                <input type="text" name="timeskew"
                                       value="<?php echo Display::escHtml($feed_timeskew); ?>" class="input-long"/>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Feed Status</p></td>
                        <td>
                            <p>
                                <input type='radio' name='status' id='status_active' value='active'
                                       <?php if (empty($feed_status) || 'active' == $feed_status) { ?>checked='checked'<?php } ?>/>
                                Active (Visible)<br/>
                                <input type='radio' name='status' id='status_hidden' value='hidden'
                                       <?php if ('hidden' == $feed_status) { ?>checked='checked'<?php } ?>/> Active
                                (Hidden)<br/>
                                <input type='radio' name='status' id='status_retired' value='retired'
                                       <?php if ('retired' == $feed_status) { ?>checked='checked'<?php } ?>/> Retired
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td><p>Date of Birth Restrictions</p></td>
                        <td>
                            <p>If either or both of these values are set, the system will calculate the age of the person based on the DOB passed and reject if the age falls outside these values.</p>
                            <p><label for="minimumBirthAge">Minimum Birth Age:</label>
                                <input type="text" name="minimumBirthAge" id="minimumBirthAge" value="<?php echo Display::escHtml($feed_minimumBirthAge); ?>"/>
                            </p>
                            <p><label for="maximumBirthAge">Maximum Birth Age:</label>
                                <input type="text" name="maximumBirthAge" id="maximumBirthAge" value="<?php echo Display::escHtml($feed_maximumBirthAge); ?>"/>
                            </p>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="a" value="manageFeed"/>
                <input type="hidden" name="action" value="<?php echo $mode; ?>"/>
            </form>
            <?php
            break;

        case 'dialog_import':
            $idFeedIn = $_REQUEST['idFeedIn'];

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $feed = $leads->getInboundFeed($idFeedIn);

            if ($feed === false) {
                ?>
                <p>Database failure - could not fetch feed information.</p>
                <?php
            } elseif (!is_object($feed) && $feed == 0) {
                ?>
                <p>Error fetching feed information - feed does not exist.</p>
                <?php
            } else {

                $company = $leads->getCompany($feed->idCompany);

                ?>

                <script type="text/template" id="qq-template">
                    <div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop file here">
                        <div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
                            <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                 class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
                        </div>
                        <div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
                            <span class="qq-upload-drop-area-text-selector"></span>
                        </div>
                        <div class="qq-upload-button-selector qq-upload-button">
                            <div>Upload a file</div>
                        </div>
                        <span class="qq-drop-processing-selector qq-drop-processing">
                    <span>Processing dropped file...</span>
                    <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
                </span>
                        <ul class="qq-upload-list-selector qq-upload-list" aria-live="polite"
                            aria-relevant="additions removals">
                            <li>
                                <div class="qq-progress-bar-container-selector">
                                    <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                         class="qq-progress-bar-selector qq-progress-bar"></div>
                                </div>
                                <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
                                <img class="qq-thumbnail-selector" qq-max-size="100" qq-server-scale>
                                <span class="qq-upload-file-selector qq-upload-file"></span>
                                <span class="qq-upload-size-selector qq-upload-size"></span>
                                <button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel
                                </button>
                                <button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry
                                </button>
                                <button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">Delete
                                </button>
                                <span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
                            </li>
                        </ul>

                        <dialog class="qq-alert-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">Close</button>
                            </div>
                        </dialog>

                        <dialog class="qq-confirm-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">No</button>
                                <button type="button" class="qq-ok-button-selector">Yes</button>
                            </div>
                        </dialog>

                        <dialog class="qq-prompt-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <input type="text">
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">Cancel</button>
                                <button type="button" class="qq-ok-button-selector">Ok</button>
                            </div>
                        </dialog>
                    </div>
                </script>
                <p><strong>Company:</strong> <?php echo htmlentities($company->name); ?></p>
                <p><strong>Feed:</strong> <?php echo htmlentities($feed->label); ?> (#<?php echo $feed->idFeedIn; ?>)
                </p>

                <form enctype="multipart/form-data" id="form-import" action="mgr_import.php" method="post"
                      target="_blank">
                    <input type="hidden" name="destination" value="<?php echo intval($idFeedIn); ?>"/>
                    <input type="hidden" name="type" value="feedinc"/>
                    <input type="hidden" name="a" value="Upload"/>
                    <input type="hidden" name="filename" value=""/>
                    <input type="hidden" name="uuid" value=""/>

                    <table class="table table-bordered table-condensed table-striped">
                        <tr>
                            <td>File</p></td>
                            <td>
                                <p>Please select the file to upload from your computer. File may be in CSV or Excel
                                    format.</p>
                                <div id="import-uploader"></div>
                            </td>
                        </tr>
                        <tr>
                            <td>Field mapping</p></td>
                            <td>
                                <?php
                                $allowedFields = explode(";", $feed->allowedFields);
                                $requiredFields = explode(";", $feed->required);

                                // Add a separate time field in case the file uses separate columns
                                if (($key = array_search('stamp', $allowedFields)) !== false) {
                                    array_splice($allowedFields, $key + 1, 0, 'time');
                                }

                                foreach ($allowedFields as $field) {
                                    printf("<p>%s%s <select name=\"field_%s\">",
                                        $field, in_array($field, $requiredFields) ? '*' : '', $field);
                                    print "<option>--</option>\n";
                                    for ($i = 0; $i < 26; $i++) {
                                        print "<option value=\"{$i}\">" . chr(65 + $i) . "</option>\n";
                                    }
                                    print "</select>";
                                    if ('stamp' == $field) {
                                        print " (Use for either a full date+time stamp or just a date stamp field)";
                                    } elseif ('time' == $field) {
                                        print " (Use for just a time stamp field)";
                                    }
                                    print "</p>\n";
                                }

                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td>Queue Split</p></td>
                            <td>
                                <p>If you want this upload to be split and siphoned out over the course of X days, enter
                                    the number of days below. For normal uploads, you'll leave this blank. <strong>NOTE:
                                        The original lead timestamps will be altered when using this option.</strong>
                                </p>
                                <p><input type="number" name="splitDelay" min="0" value=""/></p>
                                <p>In order for this feature to work properly, the outbound feeds accepting this data
                                    must be setup with a feed delay of 1 minute or greater AND the population must be setup as a "Standard Queue".
                                    <?php
                                    $feeds = $leads->getInboundPopulationSettings($idFeedIn, true);
                                    if (empty($feeds)) {
                                        print 'There are currently <strong>NO</strong> feeds setup to receive this data. If you setup a population for this data in the future, please add a feed delay of 1 minute or greater on the outbound side.</p>';
                                    } else {
                                        print 'The following feeds are setup to receive this data:</p><ul>';
                                        foreach ($feeds as $feed) {
                                            printf('<li>%s: %s (%s) - Feed Delay: %s - Queue Type: %s</li>' . PHP_EOL,
                                                Display::escHtml($feed->idFeedOut),
                                                Display::escHtml($feed->label),
                                                Display::escHtml($feed->description),
                                                empty($feed->delay) ? '<span style="color:red; font-weight:bold">ERROR: NO DELAY SET</span>' : ('<span style="color:green; font-weight:bold"> ' . Display::escHtml(($feed->delay % (60 * 24)) == 0 ? ($feed->delay / (60 * 24) . ' Days') : ($feed->delay . ' Minutes')) . '</span>'),
                                                ('queue' !== $feed->queueType ? '<span style="color:red; font-weight:bold">ERROR: WRONG QUEUE TYPE' : '<span style="color:green; font-weight:bold">QUEUE TYPE ') . ' (' . Display::escHtml(strtoupper($feed->queueType)) . ')</span>'
                                            );
                                        }
                                        print '</ul>';
                                    }
                                    ?>
                            </td>
                        </tr>
                    </table>
                </form>
                <script>
                    var importUploader = new qq.FineUploader({
                        callbacks: {
                            onComplete: function (id, name, responseJSON) {
                                if (responseJSON.success) {
                                    $("#form-import input[name='filename']").val(importUploader.getName(id));
                                    $("#form-import input[name='uuid']").val(importUploader.getUuid(id));
                                }
                            },
                        },
                        chunking: {
                            concurrent: {
                                enabled: true
                            },
                            enabled: true,
                            success: {
                                endpoint: '/leadadmin/ajax/fileUpload.php?done=1'
                            }
                        },
                        debug: <?php print ('development' === APPLICATION_ENV ? "true" : "false"); ?>,
                        element: document.getElementById("import-uploader"),
                        failedUploadTextDisplay: {
                            mode: 'custom'
                        },
                        multiple: false,
                        request: {
                            endpoint: '/leadadmin/ajax/fileUpload.php',
                            params: {
                                'type': 'feedinc',
                            },
                        },
                        retry: {
                            enableAuto: true
                        },
                        template: 'qq-template',
                        thumbnails: {
                            placeholders: {
                                waitingPath: '/leadadmin/libraries/fine-uploader/placeholders/waiting-generic.png',
                                notAvailablePath: '/leadadmin/libraries/fine-uploader/placeholders/not_available-generic.png'
                            }
                        },
                        validation: {
                            allowedExtensions: ['csv', 'txt', 'xlsx', 'xls'],
                            itemLimit: 1
                        }
                    });
                </script>
                <?php
            }
            break;

        case 'dialog_filter_zip_import':
            $idFeedIn = $_REQUEST['idFeedIn'];

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $feed = $leads->getInboundFeed($idFeedIn);

            if ($feed === false) {
                ?>
                <p>Database failure - could not fetch feed information.</p>
                <?php
            } elseif (!is_object($feed) && $feed == 0) {
                ?>
                <p>Error fetching feed information - feed does not exist.</p>
                <?php
            } else {

                $company = $leads->getCompany($feed->idCompany);

                ?>

                <script type="text/template" id="qq-template">
                    <div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop file here">
                        <div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
                            <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                 class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
                        </div>
                        <div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
                            <span class="qq-upload-drop-area-text-selector"></span>
                        </div>
                        <div class="qq-upload-button-selector qq-upload-button">
                            <div>Upload a file</div>
                        </div>
                        <span class="qq-drop-processing-selector qq-drop-processing">
                    <span>Processing dropped file...</span>
                    <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
                </span>
                        <ul class="qq-upload-list-selector qq-upload-list" aria-live="polite"
                            aria-relevant="additions removals">
                            <li>
                                <div class="qq-progress-bar-container-selector">
                                    <div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                                         class="qq-progress-bar-selector qq-progress-bar"></div>
                                </div>
                                <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
                                <img class="qq-thumbnail-selector" qq-max-size="100" qq-server-scale>
                                <span class="qq-upload-file-selector qq-upload-file"></span>
                                <span class="qq-upload-size-selector qq-upload-size"></span>
                                <button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel
                                </button>
                                <button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry
                                </button>
                                <button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">Delete
                                </button>
                                <span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
                            </li>
                        </ul>

                        <dialog class="qq-alert-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">Close</button>
                            </div>
                        </dialog>

                        <dialog class="qq-confirm-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">No</button>
                                <button type="button" class="qq-ok-button-selector">Yes</button>
                            </div>
                        </dialog>

                        <dialog class="qq-prompt-dialog-selector">
                            <div class="qq-dialog-message-selector"></div>
                            <input type="text">
                            <div class="qq-dialog-buttons">
                                <button type="button" class="qq-cancel-button-selector">Cancel</button>
                                <button type="button" class="qq-ok-button-selector">Ok</button>
                            </div>
                        </dialog>
                    </div>
                </script>
                <p><strong>Company:</strong> <?php echo htmlentities($company->name); ?></p>
                <p><strong>Feed:</strong> <?php echo htmlentities($feed->label); ?> (#<?php echo $feed->idFeedIn; ?>)
                </p>

                <form enctype="multipart/form-data" id="form-filter-zip-import" action="mgr_import.php" method="post" target="_blank">
                    <input type="hidden" name="destination" value="<?php echo intval($idFeedIn); ?>"/>
                    <input type="hidden" name="type" value="filter-zip-import"/>
                    <input type="hidden" name="a" value="Upload"/>
                    <input type="hidden" name="filename" value=""/>
                    <input type="hidden" name="uuid" value=""/>

                    <table class="table table-bordered table-condensed table-striped">
                        <tr>
                            <td>File</p></td>
                            <td>
                                <p>Please select the file to upload from your computer. File may be in CSV or Excel format.</p>
                                <p>The first column in the spreadsheet must contain the Zip Codes.</p>
                                <p>Zip Codes must be exactly 5 characters long.</p>
                                <p>The upload feature will APPEND this file to any existing zip codes filters already in the database for this feed.</p>
                                <div id="filter-zip-import-uploader"></div>
                            </td>
                        </tr>
                    </table>
                </form>
                <script>
                    var importUploader = new qq.FineUploader({
                        callbacks: {
                            onComplete: function (id, name, responseJSON) {
                                if (responseJSON.success) {
                                    $("#form-filter-zip-import input[name='filename']").val(importUploader.getName(id));
                                    $("#form-filter-zip-import input[name='uuid']").val(importUploader.getUuid(id));
                                }
                            },
                        },
                        chunking: {
                            concurrent: {
                                enabled: true
                            },
                            enabled: true,
                            success: {
                                endpoint: '/leadadmin/ajax/fileUpload.php?done=1'
                            }
                        },
                        debug: <?php print ('development' === APPLICATION_ENV ? "true" : "false"); ?>,
                        element: document.getElementById("filter-zip-import-uploader"),
                        failedUploadTextDisplay: {
                            mode: 'custom'
                        },
                        multiple: false,
                        request: {
                            endpoint: '/leadadmin/ajax/fileUpload.php',
                            params: {
                                'type': 'filter-zip-import',
                            },
                        },
                        retry: {
                            enableAuto: true
                        },
                        template: 'qq-template',
                        thumbnails: {
                            placeholders: {
                                waitingPath: '/leadadmin/libraries/fine-uploader/placeholders/waiting-generic.png',
                                notAvailablePath: '/leadadmin/libraries/fine-uploader/placeholders/not_available-generic.png'
                            }
                        },
                        validation: {
                            allowedExtensions: ['csv', 'txt', 'xlsx', 'xls'],
                            itemLimit: 1
                        }
                    });
                </script>
                <?php
            }
            break;

        case 'dialog_export':
            $idFeedIn = $_REQUEST['idFeedIn'];

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                die('Sorry, you do not have permission to export data.');
            }

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $feed = $leads->getInboundFeed($idFeedIn);
            ?>
            <?php
            if ($feed === false) {
                ?>
                <p>Database failure - could not fetch feed information.</p>
                <?php
            } elseif (!is_object($feed) && $feed == 0) {
                ?>
                <p>Error fetching feed information - feed does not exist.</p>
                <?php
            } else {
                ?>
                <p>Exporting Data from Feed (ID:<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
                <form id="form-export">
                    <input type="hidden" name="feedIds[]" value="<?php echo $feed->idFeedIn; ?>"/>
                    <input type="hidden" name="a" value="exportData"/>
                    <input type="hidden" name="label"
                           value="<?php echo htmlspecialchars($feed->label, ENT_QUOTES); ?>"/>
                    <table class="table table-bordered table-condensed table-striped">
                        <tr>
                            <td colspan='2'><p class='aCenter'>Export Settings</p></td>
                        </tr>
                        <tr>
                            <td>
                                Columns
                            </td>
                            <td>
                                <button type="button" class="btn btn-primary" id="exp-check-all"
                                        style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Check All
                                </button>
                                <button type="button" class="btn btn-primary" id="exp-uncheck-all"
                                        style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Uncheck All
                                </button>
                                <br>
                                <?php $fields = $leads->getInboundFields(); ?>
                                <?php foreach ($fields as $f) { ?>
                                    <label class="checkbox-label">
                                        <input class="export-check" type='checkbox' name='columns[]' value='<?php echo Display::escHtml($f->fieldName); ?>'/>&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                    </label>
                                <?php } ?>
                            </td>
                        </tr>
                        <script>
                            $('#exp-check-all').click(function () {
                                $('.export-check').prop('checked', true);
                            });
                            $('#exp-uncheck-all').click(function () {
                                $('.export-check').prop('checked', false);
                            });
                        </script>
                        <tr>
                            <td>
                                Period
                            </td>
                            <td>
                                <p>Period goes from midnight of the first date to midnight of the second date. Leave
                                    blank to select from all time records. (This could take a long time.)</p>
                                <p><input type='text' name='dateStart' class='dateSelector'
                                          value='<?php echo date("Y-m-d"); ?>'/>
                                    to <input type='text' name='dateEnd' class='dateSelector'
                                              value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'/></p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                URLs
                            </td>
                            <td>
                                <p>URLs to limit the selection by. Leave blank to select all records regardless of
                                    URL.</p>
                                <p><a href='#' class='nonLink'
                                      onclick='element("export_<?php echo $idFeedIn; ?>_urls", "urlField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'>Add
                                        URL</a></p>
                                <div>
                                    <div id='export_<?php echo $idFeedIn; ?>_urls'>
                                    </div>
                                </div>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Email domains
                            </td>
                            <td>
                                <p>Email domains to limit the selection by. Leave blank to select all records regardless
                                    of email address. Do not include the @ symbol.</p>
                                <p><a href='#' class='nonLink'
                                      onclick='element("export_<?php echo $idFeedIn; ?>_emails", "emailField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'>Add
                                        email domain</a></p>
                                <div>
                                    <div id='export_<?php echo $idFeedIn; ?>_emails'>
                                    </div>
                                </div>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Limit</p>
                            </td>
                            <td>
                                <p>Set a limit on the number of records that are returned. Leave blank to return ALL
                                    records.</p>
                                <p><input type="text" name="limit" value=""/></p>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                Rejects</p>
                            </td>
                            <td>
                                <p><input type="checkbox" name="includeRejects" value="1"/> Include rejected records in
                                    the export.</p>
                            </td>
                        </tr>
                    </table>
                </form>
                <?php
            }
            break;

        case 'dialog_urlreport':
            $idFeedIn = !empty($_REQUEST['idFeedIn']) ? $_REQUEST['idFeedIn'] : 0;

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                die('Sorry, you do not have permission to run URL reports.');
            }

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $_REQUEST['dateStart'] = !empty($_REQUEST['dateStart']) ? $_REQUEST['dateStart'] : date("Y-m-d");
            $_REQUEST['dateEnd'] = !empty($_REQUEST['dateEnd']) ? $_REQUEST['dateEnd'] : date("Y-m-d",
                strtotime('Tomorrow'));
            $_REQUEST['urlList'] = !empty($_REQUEST['urlList']) && is_array($_REQUEST['urlList']) ? $_REQUEST['urlList'] : array();
            $_REQUEST['breakdown'] = !empty($_REQUEST['breakdown']) ? $_REQUEST['breakdown'] : 'day';
            $_REQUEST['sort'] = !empty($_REQUEST['sort']) ? $_REQUEST['sort'] : 'date';
            $_REQUEST['group'] = !empty($_REQUEST['group']) ? $_REQUEST['group'] : 'date';

            $feed = $leads->getInboundFeed($idFeedIn);
            ?>
            <?php
            if ($feed === false) {
                ?>
                <p>Database failure - could not fetch feed information.</p>
                <?php
            } elseif (!is_object($feed) && $feed == 0) {
                ?>
                <p>Error fetching feed information - feed does not exist.</p>
                <?php
            } else {
                ?>
                <p>Feed ID: <strong><?php echo $feed->idFeedIn; ?></strong><br/>Feed Label:
                    <strong><?php echo htmlspecialchars($feed->label, ENT_QUOTES); ?></strong></p>

                <form id="form-urlreport" class="form-inlin1e">
                    <input type="hidden" name="idFeedIn" value="<?php echo $feed->idFeedIn; ?>"/>
                    <input type="hidden" name="d" value="dialog_urlreport"/>
                    <input type="hidden" name="submit" value="submit"/>

                    <p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select
                        from all time records. (This could take a long time.)</p>
                    <div class="form-group">
                        <label for="dateStart">Start Date:</label>
                        <input type="text" id="dateStart" name="dateStart" class="form-control dateSelector"
                               value="<?php echo htmlspecialchars($_REQUEST['dateStart'], ENT_QUOTES); ?>"/>
                    </div>

                    <div class="form-group">
                        <label for="dateEnd">End Date:</label>
                        <input type="text" id="dateEnd" name="dateEnd" class="form-control dateSelector"
                               value="<?php echo htmlspecialchars($_REQUEST['dateEnd'], ENT_QUOTES); ?>"/>
                    </div>

                    <p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
                    <div class="form-group">
                        <label for="urls">URLs:</label>
                        <?php
                        $urls = $leads->getInboundURLDates($idFeedIn);
                        if ($urls && is_array($urls)) {
                            printf("<select class=\"form-control\" id=\"urls\" multiple=\"multiple\" name=\"urlList[]\" size=\"%d\">\n",
                                sizeOf($urls));
                            foreach ($urls as $url) {
                                printf("<option value=\"%s\"%s>%s (%s)</option>\n",
                                    htmlspecialchars($url['url'], ENT_QUOTES),
                                    in_array($url['url'], $_REQUEST['urlList']) ? ' selected="selected"' : '',
                                    htmlspecialchars($url['url']), $url['date']);
                            }
                            print "</select>\n";
                        }
                        ?>
                    </div>

                    <div class="form-group">
                        <label for="breakdown">Count By:</label>
                        <select class="form-control" id="breakdown" name="breakdown">
                            <?php
                            $choices = array(
                                'day' => 'Day',
                                'month' => 'Month',
                                'year' => 'Year',
                                'total' => 'Total',
                            );
                            foreach ($choices as $key => $val) {
                                printf("<option value=\"%s\"%s>%s</option>\n",
                                    htmlspecialchars($key, ENT_QUOTES),
                                    $_REQUEST['breakdown'] === $key ? ' selected="selected"' : '',
                                    htmlspecialchars($val)
                                );
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id">Sort By:</label>
                        <select class="form-control" id="sort" name="sort">
                            <?php
                            $choices = array(
                                'date' => 'Date',
                                'url' => 'URL',
                                'count' => 'Count',
                            );
                            foreach ($choices as $key => $val) {
                                printf("<option value=\"%s\"%s>%s</option>\n",
                                    htmlspecialchars($key, ENT_QUOTES),
                                    $_REQUEST['sort'] === $key ? ' selected="selected"' : '',
                                    htmlspecialchars($val)
                                );
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="id">Group By:</label>
                        <select class="form-control" id="group" name="group">
                            <?php
                            $choices = array(
                                'date' => 'Date',
                                'url' => 'URL',
                            );
                            foreach ($choices as $key => $val) {
                                printf("<option value=\"%s\"%s>%s</option>\n",
                                    htmlspecialchars($key, ENT_QUOTES),
                                    $_REQUEST['group'] === $key ? ' selected="selected"' : '',
                                    htmlspecialchars($val)
                                );
                            }
                            ?>
                        </select>
                    </div>

                </form>
                <?php

                if (!empty($_REQUEST['submit'])) {

                    $stats = $leads->getInboundURLStatsReport($_REQUEST['idFeedIn'], $_REQUEST['urlList'],
                        $_REQUEST['breakdown'], $_REQUEST['dateStart'], $_REQUEST['dateEnd'], $_REQUEST['sort'],
                        $_REQUEST['group']);

                    if (empty($stats)) {
                        ?>
                        <p>No records found.</p>
                        <?php
                    } else {

                        $fileLink = 'exports/' . $feed->idFeedIn . "_" . time() . ".csv";
                        $filePath = ADMIN_ROOT . $fileLink;
                        $file = fopen($filePath, "w");
                        if (!file_exists($filePath)) {
                            ?>
                            <p>Failed to create CSV report file.</p>
                            <?php
                        } else {
                            $accepted = 0;
                            $rejected = 0;
                            fputcsv($file, array('URL', 'Date', 'Accepted', 'Rejected'));
                            print "<table class=\"table table-bordered table-condensed table-striped\">\n";
                            print "<thead>\n";
                            print "\t<tr>\n";
                            print "\t<th>URL</th>\n";
                            print "\t<th>Date</th>\n";
                            print "\t<th>Accepted</th>\n";
                            print "\t<th>Rejected</th>\n";
                            print "\t</tr>\n";
                            print "</thead>\n";
                            print "<tbody>\n";
                            print "\t<tr>\n";
                            foreach ($stats as $stat) {
                                print "\t<tr>\n";
                                printf("\t\t<td>%s</td>\n",
                                    htmlspecialchars('date' == $_REQUEST['group'] ? 'N/A' : $stat['url']));
                                printf("\t\t<td>%s</td>\n", htmlspecialchars($stat['date']));
                                printf("\t\t<td>%s</td>\n", number_format($stat['accepted'], 0));
                                printf("\t\t<td>%s</td>\n", number_format($stat['rejected'], 0));
                                print "\t</tr>\n";
                                $accepted += $stat['accepted'];
                                $rejected += $stat['rejected'];
                                fputcsv($file,
                                    array($stat['url'], $stat['date'], $stat['accepted'], $stat['rejected']));
                            }
                            fclose($file);
                            print "\t<tr>\n";
                            print "\t\t<td colspan=\"2\"><strong>GRAND TOTAL</strong></td>\n";
                            printf("\t\t<td>%s</td>\n", number_format($accepted, 0));
                            printf("\t\t<td>%s</td>\n", number_format($rejected, 0));
                            print "\t</tr>\n";
                            print "</tbody>\n";
                            print "</table>\n";
                            printf('<p><a <a class="btn btn-primary" href="%s">Export this report</a></p>', $fileLink);
                        }
                    }

                }

            }
            break;

        case 'urlField':
            $idFeedIn = $_REQUEST['options']['idFeedIn'];
            ?>
            <div>
                URL: <input type='text' name='urlList[]' value=''/>
                <a href='#' class='nonLink' onclick='$(this).parent().remove();'>[X]</a>
            </div>
            <?php
            break;
        case 'emailField':
            $idFeedIn = $_REQUEST['options']['idFeedIn'];
            ?>
            <div>
                Email domain: <input type='text' name='emailList[]' value=''/> (do not include @ symbol)
                <a href='#' class='nonLink' onclick='$(this).parent().remove();'>[X]</a>
            </div>
            <?php
            break;
        case 'dialog_listcodes':
            $idFeedIn = $_REQUEST['options']['idFeedIn'];

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF])) {
                die('Sorry, you do not have permission to generate listcodes.');
            }

            if (!LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {
                    die('Sorry, you do not have access to this feed.');
                }
            }

            $feed = $leads->getInboundFeed($idFeedIn);
            ?>
            <p>Generate New Listcode for (<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
            <p>
                Select an Option:
                <select id='' name=''
                        onchange="display('dialog_listcodeManager', {'sub': <?php echo $feed->idFeedIn; ?>, 'type': $(this).val() });"
                >
                    <option value='0'>Choose:</option>
                    <option value='1'>Generate Listcode for Single Url</option>
                    <option value='2'>Generate Individual Listcodes for Multiple Urls</option>
                    <option value='3'>Generate Listcode for URL Group</option>
                    <option value='4'>Browse existing listcodes</option>
                </select>
            </p>
            <div id='dialog_listcodeManager_<?php echo $feed->idFeedIn; ?>'>
            </div>
            <?php
            break;
        case 'dialog_listcodeManager':
            switch ($_REQUEST['options']['type']) {
                case 0:
                    ?>
                    <p>Please choose an option.</p>
                    <?php
                    break;
                case 1:
                    ?>
                    <p>Individual URL Listcode</p>
                    <div>
                        <input type='text'
                               name='<?php echo $e ?? ''; ?>popset_filterUrl[]'
                               value='<?php echo $filterUrl; ?>'
                        />
                        <a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
                    </div>
                    <?php
                    break;
                case 2:
                    ?>
                    <p>Multiple Individual URL Listcodes</p>
                    <?php
                    break;
                case 3:
                    ?>
                    <p>URL Group Listcode</p>
                    <?php
                    break;
                case 4:
                    ?>
                    <p>Browse Listcodes</p>
                    <?php
                    break;
            }
            break;
        case 'element_filter':
            $e = $_REQUEST['options']['e'] ?? '';
            $t = $_REQUEST['options']['type'] ?? '';
            ?>
            <div>
                <input type='text'
                       name='filter<?php echo $t; ?>[]'
                       value='<?php if (isset($_REQUEST['options']['value'])) {
                           echo $_REQUEST['options']['value'];
                       } ?>'
                />
                <a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;'>[X]</a>
            </div>
            <?php
            break;
        case 'element_multifilter':
            $e = $_REQUEST['options']['e'] ?? '';
            $t = $_REQUEST['options']['type'] ?? '';
            ?>
            <textarea name='filter<?php echo $t; ?>Multi' id='filter<?php echo $t; ?>Multi'></textarea>
            <?php
            break;

        default:
            ?>
            <p>Requested information doesn't exist.</p>
            <?php
            break;
    }
    exit;
}

$title = 'Incoming Feed Manager';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

    <h2>Incoming Feeds</h2>

    <?php require_once(INCLUDES . 'quick-jump.php'); ?>

    <?php if (LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) { ?>

        <form class="pull-right" id="status-select" method="get">
            <select id="status" name="status">
                <option value="active"<?php if ('active' === $status) {
                    print ' selected="selected"';
                } ?>>Show active feeds
                </option>
                <option value="hidden"<?php if ('hidden' === $status) {
                    print ' selected="selected"';
                } ?>>Show hidden feeds
                </option>
                <option value="retired"<?php if ('retired' === $status) {
                    print ' selected="selected"';
                } ?>>Show retired feeds
                </option>
                <option value=""<?php if (null === $status) {
                    print ' selected="selected"';
                } ?>>Show all feeds
                </option>
            </select>
            <input type="hidden" name="statsStart" value="<?php echo Display::escHtml($statsStart); ?>">
            <input type="hidden" name="statsEnd" value="<?php echo Display::escHtml($statsEnd); ?>">
        </form>

        <p style="clear: both;">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static"
                    data-target="#newfeedinc">Add a new feed
            </button>
        </p>

    <?php } ?>

    <?php

    print '<div style="clear:both;"></div>';

    foreach ($feedCategories as $categoryKey => $categoryVal) {

        print "<h4>Incoming $categoryVal Feeds</h4>" . PHP_EOL;

        if (LeadsSession::isValid([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF])) {
            $incomingFeeds = $leads->getInboundFeeds(null, $status, $categoryKey, null);
        } else {
            $idCompany = LeadsSession::getCompanyId();
            if (empty($idCompany)) {
                $idCompany = -9999;
            }
            $incomingFeeds = $leads->getInboundFeeds($idCompany, $status, $categoryKey, null);
        }
        ?>
        <?php
        if ($incomingFeeds === false) {
            ?>
            <p>Error when trying to fetch feeds: database error.</p>
            <?php
        } elseif ($incomingFeeds == 0) {
            ?>
            <p>Error when trying to fetch feeds: there are no feeds.</p>
            <?php
        } else {
            //Go through each and compile the company list.
            $companyFeedLists = array();
            foreach ($incomingFeeds as $feed) {
                //Add company to the cache list of companies.
                if (!isset($companyCache[$feed->idCompany])) {
                    $company = $leads->getCompany($feed->idCompany);
                    if (is_object($company)) {
                        $companyCache[$feed->idCompany] = $company;
                        $companyFeedLists[$feed->idCompany] = array();
                    }
                }
                //Add feed to list of feeds for the specified company.
                $companyFeedLists[$feed->idCompany][] = $feed;
            }

            uksort($companyFeedLists, 'companyListSort');
            ?>
            <table class="table table-bordered table-condensed table-striped-custom">
                <thead>
                <tr class='bgGray'>
                    <th class="incoming-col-large" colspan="2">Company</th>
                    <th class="incoming-col-small text-right">Accepted</th>
                    <th class="incoming-col-small text-right">Rejected</th>
                    <th class="incoming-col-small">Actions</th>
                </tr>
                </thead>
                <?php
                $grandTotalFeeds = 0;
                $grandTotalAccepted = 0;
                $grandTotalRejected = 0;
                foreach ($companyFeedLists as $idCompany => $companyFeedList) {
                    $totalAccepted = 0;
                    $totalRejected = 0;
                    foreach ($companyFeedList as $keyFeed => $feed) {

                        $stats = $leads->getInboundStatsRange($feed->idFeedIn, date('Y-m-d', strtotime($statsStart)),
                            date('Y-m-d', strtotime($statsEnd)));

                        $companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
                        $totalAccepted += $stats['accepted'];
                        $grandTotalAccepted += $stats['accepted'];

                        $companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
                        $totalRejected += $stats['rejected'];
                        $grandTotalRejected += $stats['rejected'];

                    }
                    $grandTotalFeeds += count($companyFeedList);
                    ?>
                    <tr class="custom-master">
                        <td colspan="2"><?php echo $companyCache[$idCompany]->name; ?>
                            (<?php echo count($companyFeedList); ?>)
                        </td>
                        <td class="text-right"><?php echo number_format($totalAccepted, 0); ?></td>
                        <td class="text-right"><?php echo number_format($totalRejected, 0); ?></td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-xs" type="button" data-toggle="collapse"
                                    data-target=".feed-toggle-<?php echo $idCompany; ?>" aria-expanded="false"
                                    aria-controls="collapseExample">Show Feeds
                            </button>
                        </td>
                    </tr>
                    <?php
                    foreach ($companyFeedList as $feed) {
                        ?>
                        <tr class="collapse bg-gray feed-toggle feed-toggle-<?php echo $idCompany; ?>">
                            <td class="status-<?php print $feed->status; ?>"><?php echo $feed->idFeedIn; ?>
                                : <?php echo $feed->label; ?> (<?php echo htmlentities($feed->description); ?>)
                            </td>
                            <td>
                                <input class="paused-toggle" <?php if (empty($feed->paused)) {
                                    print 'checked="checked" ';
                                } ?>data-toggle="toggle" data-size="mini" data-width="80" data-on="Enabled"
                                       data-onstyle="success" data-off="Paused" data-offstyle="danger"
                                       data-feed-id="<?php echo $feed->idFeedIn; ?>" type="checkbox"/></td>
                            </td>
                            <td class="text-right"><a
                                        href="record-search.php?startDate=<?php echo urlencode($statsStart); ?>&amp;idFeedIn=<?php echo urlencode($feed->idFeedIn); ?>&amp;status=accepted&amp;viewType=condensed&amp;submit=Search"
                                        target="_blank"><?php echo $feed->dailyCount; ?></a></td>
                            <td class="text-right"><a
                                        href="record-search.php?startDate=<?php echo urlencode($statsStart); ?>&amp;idFeedIn=<?php echo urlencode($feed->idFeedIn); ?>&amp;status=rejected&amp;viewType=condensed&amp;submit=Search"
                                        target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></td>
                            <td class="text-center">
                                <?php if (LeadsSession::isValid([LEADS_SESSION_LEVEL_CLIENT_DASHBOARD, LEADS_SESSION_LEVEL_STAFF, LEADS_SESSION_LEVEL_PPC])) { ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-primary btn-xs" data-toggle="modal"
                                                data-backdrop="static" data-target="#editfeedinc"
                                                data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">Edit Feed
                                        </button>
                                        <button type="button" class="btn btn-primary btn-xs dropdown-toggle"
                                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <span class="caret"></span>
                                            <span class="sr-only">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a href="/leadadmin/apispec.php?idFeedIn=<?php echo $feed->idFeedIn; ?>&amp;h=<?php echo urlencode(hash('sha256', $feed->idFeedIn . HASH_SALT . $feed->password)) ?>"
                                                   target="_blank">API Spec</a></li>
                                            <li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-import" data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">Import data</a></li>
                                            <li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-export" data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">Export data</a></li>
                                            <li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-urlreport" data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">URL report</a></li>
                                            <li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-filter-zip-import" data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">Filter Zip import</a>
                                            </li>
                                        </ul>
                                    </div>
                                <?php } else { ?>
                                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal"
                                            data-backdrop="static" data-target="#modal-import"
                                            data-feedinc-id="<?php echo intval($feed->idFeedIn); ?>">Import data
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
                <tfoot>
                <tr>
                    <td colspan="2">GRAND TOTAL</td>
                    <td class="text-right"><?php echo number_format($grandTotalAccepted, 0); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalRejected, 0); ?></td>
                    <td></td>
                </tr>
                </tfoot>
            </table>
        <?php } ?>
    <?php } ?>
</div>

<div class="modal fade" id="newfeedinc" tabindex="-1" role="dialog" aria-labelledby="newfeedinc_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="newfeedinc_title">Add a new incoming feed</h4>
            </div>
            <div class="modal-body">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-newfeedinc" type="button" class="btn btn-primary">Add feed</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editfeedinc" tabindex="-1" role="dialog" aria-labelledby="editfeedinc_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="editfeedinc_title">Edit an incoming feed</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-editfeedinc" type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-import" tabindex="-1" role="dialog" aria-labelledby="modal-import_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-import_title">Import legacy data</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-import" type="button" class="btn btn-primary">Import Data</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-filter-zip-import" tabindex="-1" role="dialog" aria-labelledby="modal-filter-zip-import_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-filter-zip-import_title">Import Filter Zip Codes</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-filter-zip-import" type="button" class="btn btn-primary">Import Zip Codes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-export" tabindex="-1" role="dialog" aria-labelledby="modal-export_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-export_title">Export legacy data</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-export" type="button" class="btn btn-primary">Export Data</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-urlreport" tabindex="-1" role="dialog" aria-labelledby="modal-urlreport_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-urlreport_title">URL Report</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-urlreport" type="button" class="btn btn-primary">Run Report</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    $('#modal-save-newfeedinc').click(function (event) {
        event.preventDefault();

        var response = $.ajax({
            url: "mgr_feedinc.php",
            type: "POST",
            async: true,
            data: $("#new_feedinc").serialize()
        }).done(function (result) {
            if (result.status == 1) {
                window.location.reload(true);
            } else {
                alert(result.error);
            }
        });
    });

    $('#newfeedinc').on('show.bs.modal', function (e) {
        var modal = $(this);

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_newfeed'
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#newfeedinc').on('shown.bs.modal', function (e) {
        $("#newfeedinc select[name='timezone']").select2();
    });

    $('#modal-save-editfeedinc').click(function (event) {
        event.preventDefault();

        var response = $.ajax({
            url: "mgr_feedinc.php",
            type: "POST",
            async: true,
            data: $("#edit_feedinc").serialize()
        }).done(function (result) {
            if (result.status == 1) {
                // window.location.reload(true);
                $('#editfeedinc').modal('hide');
            } else {
                alert(result.error);
            }
        });
    });

    $('#editfeedinc').on('show.bs.modal', function (e) {
        var modal = $(this);
        var idFeedIn = $(e.relatedTarget).data('feedinc-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_editfeed',
                'idFeedIn': idFeedIn
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#editfeedinc').on('shown.bs.modal', function (e) {
        $("#editfeedinc select[name='timezone']").select2();
    });

    $('#modal-import').on('show.bs.modal', function (e) {
        var modal = $(this);
        var idFeedIn = $(e.relatedTarget).data('feedinc-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_import',
                'idFeedIn': idFeedIn
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-import').click(function (event) {
        event.preventDefault();
        if ($("#form-import select[name='field_url']").val() === '--') {
            alert('Please select the URL column.');
        } else if ($("#form-import select[name='field_ip']").val() === '--') {
            alert('Please select the IP column.');
        } else if ($("#form-import select[name='field_stamp']").val() === '--') {
            alert('Please select the stamp column.');
        } else if ($("#form-import select[name='field_email']").val() === '--') {
            alert('Please select the email column.');
        } else if (importUploader.getInProgress()) {
            alert('Please wait until your file has finished uploading before submitting this job.');
        } else if (importUploader.getNetUploads() === 0 || $("#form-import input[name='filename']").val() === '' || $("#form-import input[name='uuid']").val() === '') {
            alert('Please upload a file.');
        } else {

            var modal = $(this);

            var response = $.ajax({
                url: "/leadadmin/ajax/submitJob.php",
                type: "POST",
                async: true,
                data: $("#form-import").serialize()
            }).done(function (result) {
                if (result.success === true) {
                    if (result.link) {
                        window.location = result.link;
                    }
                } else {
                    alert("Error: " + (result.error || 'Unknown'));
                }
            });

            //$('#form-import').submit();
        }
    });

    $('#modal-filter-zip-import').on('show.bs.modal', function (e) {
        var modal = $(this);
        var idFeedIn = $(e.relatedTarget).data('feedinc-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_filter_zip_import',
                'idFeedIn': idFeedIn
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-filter-zip-import').click(function (event) {
        event.preventDefault();
        if (importUploader.getInProgress()) {
            alert('Please wait until your file has finished uploading before submitting this job.');
        } else if (importUploader.getNetUploads() === 0 || $("#form-import input[name='filename']").val() === '' || $("#form-import input[name='uuid']").val() === '') {
            alert('Please upload a file.');
        } else {

            var modal = $(this);

            var response = $.ajax({
                url: "/leadadmin/ajax/submitJob.php",
                type: "POST",
                async: true,
                data: $("#form-filter-zip-import").serialize()
            }).done(function (result) {
                if (result.success === true) {
                    if (result.link) {
                        window.location = result.link;
                    }
                } else {
                    alert("Error: " + (result.error || 'Unknown'));
                }
            });

            //$('#form-import').submit();
        }
    });

    $('#modal-export').on('show.bs.modal', function (e) {
        var modal = $(this);
        var idFeedIn = $(e.relatedTarget).data('feedinc-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_export',
                'idFeedIn': idFeedIn
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-export').click(function (event) {
        event.preventDefault();

        var response = $.ajax({
            url: "mgr_feedinc.php",
            type: "POST",
            async: true,
            data: $("#form-export").serialize()
        }).done(function (result) {
            alert(result.error);
            if (result.status == 1) {
                // window.location.reload(true);
                $('#modal-export').modal('hide');
            }
        });
    });

    $('#modal-urlreport').on('show.bs.modal', function (e) {
        var modal = $(this);
        var idFeedIn = $(e.relatedTarget).data('feedinc-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'd': 'dialog_urlreport',
                'idFeedIn': idFeedIn
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-urlreport').click(function (event) {
        event.preventDefault();

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: $("#form-urlreport").serialize(),
            success: function (data) {
                $('#modal-urlreport').find('.modal-body').html(data);
            }
        });
    });

    $('#newfeedinc, #editfeedinc').on('hide.bs.modal', function (e) {
        $(this).find('.modal-body').html('');
    });

    $('#status-select select').change(function (e) {
        e.preventDefault();
        $('#status-select').submit();
    });

    $('.feed-toggle').on('show.bs.collapse', function () {
        $(this).prev().find('button[data-toggle="collapse"]').html('Hide Feeds');
    });
    $('.feed-toggle').on('hide.bs.collapse', function () {
        $(this).prev().find('button[data-toggle="collapse"]').html('Show Feeds');
    });

    $('.paused-toggle').bootstrapToggle();

    $('.paused-toggle').change(function () {
        var idFeedIn = $(this).data('feed-id');

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'mgr_feedinc.php',
            data: {
                'a': 'managePaused',
                'idFeedIn': idFeedIn,
                'action': 'toggle',
                'param': 'enabled'
            }
        });
    });

    $('#statsQuick').on('change', function (e) {
        let myValue = $(this).val() || '';
        if (myValue !== '') {
            let dates = myValue.split('|', 2);
            $('input[name="statsStart"]').val(dates[0]);
            $('input[name="statsEnd"]').val(dates[1]);
        }
    });

    $('body').on('change', 'input[name="feedCategory"]', function (event) {
        var value = $(this).val();
        if (value === 'phone-preping') {
            $('.preping-row').slideDown();
        } else {
            $('.preping-row').slideUp();
        }
    });

    $('input[name="statsStart"], input[name="statsEnd"]').datepicker({
        // Consistent format with the HTML5 picker
        dateFormat: 'yy-mm-dd'
    });
</script>

</body>
</html>