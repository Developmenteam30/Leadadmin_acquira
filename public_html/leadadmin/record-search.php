<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess([LEADS_SESSION_LEVEL_PPC, LEADS_SESSION_LEVEL_STAFF, LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS]);

$hasFullAccess = LeadsSession::isValid([LEADS_SESSION_LEVEL_STAFF]);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

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

$title = 'Record Search';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

    <h2>Record Search</h2>
    <p>Fill out any or all of the fields below to perform an "AND" search against all of the fields that are filled in.</p>
    <?php if ('EQ' === COMPANY_INITIALS) { ?>
        <p>Searches will be performed against the entire archive of data back to July 2020.</p>
    <?php } ?>
    <?php if ('Q' === COMPANY_INITIALS) { ?>
        <p>Searches will be performed against the entire archive of data back to June 2014.</p>
        <p>We started keeping inbound rejected records on 5/1/2018 and outbound rejected records on 9/1/2018. Before these dates, only accepted records were logged and available for searching.</p>
    <?php } ?>
    <p>Results are limited to the first 500 matching entries and are sorted with the most recent entries on top.</p>

    <?php
    if ($hasFullAccess) {
        $inboundFeeds = $leads->getInboundFeeds(null, 'active', null, $_REQUEST['idFeedIn'] ?? null);
    } else {
        $idCompany = LeadsSession::getCompanyId();
        if (empty($idCompany)) {
            $idCompany = -9999;
        }
        $inboundFeeds = $leads->getInboundFeeds($idCompany, 'active', null, null);
    }


    $inboundCompanies = [];
    foreach ($inboundFeeds as $feed) {
        if (!in_array($feed->idCompany, $inboundCompanies)) {
            $inboundCompanies[$feed->idCompany] = $feed->name;
        }
    }
    asort($inboundCompanies, SORT_FLAG_CASE | SORT_STRING);

    $feedChoices = [];
    foreach ($inboundCompanies as $key => $value) {
        foreach ($inboundFeeds as $inboundFeed) {
            if ($inboundFeed->idCompany == $key) {
                $feedChoices[$value][$inboundFeed->idFeedIn] = "({$inboundFeed->idFeedIn}) {$inboundFeed->label} [{$inboundFeed->description}]";
            }
        }
    }

    $fields = array(
        array(
            'id' => 'startDate',
            'type' => 'text',
            'autocomplete' => 'off',
            'label' => 'Start Date',
            'value' => $_REQUEST['startDate'] ?? '',
        ),
        array(
            'id' => 'endDate',
            'type' => 'text',
            'autocomplete' => 'off',
            'label' => 'End Date',
            'value' => $_REQUEST['endDate'] ?? '',
        ),
        array(
            'id' => 'status',
            'label' => 'Record Status',
            'type' => 'radio',
            'required' => true,
            'choices' => array(
                'all' => 'All Records',
                'accepted' => 'Accepted Records',
                'rejected' => 'Rejected Records',
            ),
            'value' => !empty($_REQUEST['status']) ? $_REQUEST['status'] : 'all',
            'choice_append' => '&nbsp;&nbsp;',
        ),
        array(
            'id' => 'idFeedIn',
            'label' => 'Incoming Feed',
            'type' => 'select',
            'choices' => $feedChoices,
            'value' => $_REQUEST['idFeedIn'] ?? '',
        ),
        array(
            'id' => 'email',
            'type' => 'email',
            'label' => 'Email Address',
            'value' => $_REQUEST['email'] ?? '',
        ),
        array(
            'id' => 'phone',
            'type' => 'text',
            'label' => 'Phone Number',
            'value' => $_REQUEST['phone'] ?? '',
        ),
        array(
            'id' => 'url',
            'type' => 'text',
            'label' => 'URL',
            'value' => $_REQUEST['url'] ?? '',
        ),
        array(
            'id' => 'ip',
            'type' => 'text',
            'label' => 'IP Address',
            'value' => $_REQUEST['ip'] ?? '',
        ),
        array(
            'id' => 'viewType',
            'label' => 'View Type',
            'type' => 'radio',
            'required' => true,
            'choices' => array(
                'expanded' => 'Show outbound results',
                'condensed' => 'Hide outbound results',
            ),
            'value' => !empty($_REQUEST['viewType']) ? $_REQUEST['viewType'] : 'expanded',
            'choice_append' => '&nbsp;&nbsp;',
            'active' => $hasFullAccess,
        ),
        array(
            'id' => 'submit',
            'type' => 'submit',
            'label' => 'Search',
        ),
    );

    Display::displayForm('record_search', $fields);

    $startDate = trim($_REQUEST['startDate'] ?? '');
    $endDate = trim($_REQUEST['endDate'] ?? '');
    $status = trim($_REQUEST['status'] ?? '');
    $idFeedIn = trim($_REQUEST['idFeedIn'] ?? '');
    $email = trim($_REQUEST['email'] ?? '');
    $phone = trim(preg_replace('/[^0-9]/', '', $_REQUEST['phone'] ?? ''));
    $url = trim($_REQUEST['url'] ?? '');
    $ip = trim($_REQUEST['ip'] ?? '');
    if ($hasFullAccess) {
        $viewType = trim($_REQUEST['viewType'] ?? 'expanded');
    } else {
        $viewType = 'condensed';
    }

    if (!empty($_REQUEST['submit'])) {
        if (!$hasFullAccess && empty($idFeedIn)) {

            print "<p class='errors'>You must select a feed from the list.</p>";

        } elseif (empty($idFeedIn) && empty($email) && empty($phone) && empty($url) && empty($ip)) {

            print "<p class='errors'>You must select a feed from the list OR fill out at least one of the email, phone, URL, or IP fields.</p>";

        } elseif (!$hasFullAccess && !$leads->checkInboundFeedAccess($idCompany, $idFeedIn)) {

            print "<p class='errors'>Sorry, you do not have access to view this feed.</p>";

        } else {

            $leads->auditLog('SEARCH:RECORD', json_encode(array(
                'startDate' => $startDate,
                'endDate' => $endDate,
                'status' => $status,
                'idFeedIn' => $idFeedIn,
                'email' => $email,
                'phone' => $phone,
                'url' => $url,
                'ip' => $ip,
                'viewType' => $viewType,
            )));

            ?>
            <p>Searching incoming feeds ...</p>

            <?php
            $records = $leads->inboundRecordSearch($startDate, $endDate, $status, $idFeedIn, $email, $phone, $url, $ip);
            if (is_array($records) && sizeOf($records) > 0) {
                ?>

                <table class="table table-bordered table-striped-triple table-condensed">
                    <thead>
                    <tr>
                        <th>Incoming Feed</th>
                        <th>Email</th>
                        <th>Timestamp</th>
                        <th>URL</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Lead Timestamp</th>
                        <th>IP Address</th>
                        <th>DOB</th>
                        <th rowspan="3">Actions</th>
                    </tr>
                    <tr>
                        <th>Address 1</th>
                        <th>Address 2</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Zipcode</th>
                        <th>Country</th>
                        <th>Landline</th>
                        <th>Cellphone</th>
                        <th>Gender</th>
                    </tr>
                    <tr>
                        <?php if (!empty($viewType) && 'expanded' === $viewType) { ?>
                            <th colspan="9">Incoming and Outgoing Responses</th>
                        <?php } else { ?>
                            <th colspan="9">Incoming Response</th>
                        <?php } ?>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($records as $record) { ?>
                        <tr>
                            <td><?php echo Display::escHtml($record->companyName); ?> - (<?php echo Display::escHtml($record->idFeedIn); ?>) <?php echo Display::escHtml($record->label); ?>
                                [<?php echo Display::escHtml($record->description); ?>]
                            </td>
                            <td><?php echo Display::escHtml($record->email); ?></td>
                            <td><?php echo Display::escHtml($record->timestampConverted); ?></td>
                            <td><?php echo Display::escHtml($record->url); ?></td>
                            <td><?php echo Display::escHtml($record->fname); ?></td>
                            <td><?php echo Display::escHtml($record->lname); ?></td>
                            <td><?php echo Display::escHtml($record->leadstamp); ?></td>
                            <td><?php echo Display::escHtml($record->ip); ?></td>
                            <td><?php echo Display::escHtml($record->dob); ?></td>
                            <td rowspan="3" class="text-center">
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-backdrop="static" data-target="#record-details-<?= Display::escHtml($record->idRecord); ?>">View Details
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td><?php echo Display::escHtml($record->addr); ?>&nbsp;</td>
                            <td><?php echo Display::escHtml($record->addr2); ?></td>
                            <td><?php echo Display::escHtml($record->city); ?></td>
                            <td><?php echo Display::escHtml($record->state); ?></td>
                            <td><?php echo Display::escHtml($record->zip); ?></td>
                            <td><?php echo Display::escHtml($record->country); ?></td>
                            <td><?php echo Display::escHtml($record->landline); ?></td>
                            <td><?php echo Display::escHtml($record->cellphone); ?></td>
                            <td><?php echo Display::escHtml($record->gender); ?></td>
                        </tr>
                        <tr>
                            <td colspan="9">
                                <?php if (!empty($viewType) && 'expanded' === $viewType) { ?>
                                    <p><strong>Incoming Response</strong>: <span
                                                style="font-weight: bold; color: <?php echo $record->result ? 'red' : 'green'; ?>"><?php echo Display::escHtml($record->result ?? 'Success'); ?></span></p>
                                    <?php
                                    $outboundRecords = $leads->outboundRecordSearchById($record->idRecord);
                                    if (!empty($outboundRecords)) {
                                        print '<p><strong>Outgoing Responses:</strong></p>';
                                        print '<ul>';
                                        foreach ($outboundRecords as $outboundRecord) {
                                            printf('<li>%s: %s - %s (#%s) Response: %s</li>',
                                                Display::escHtml($outboundRecord->timestampConverted),
                                                Display::escHtml($outboundRecord->companyName),
                                                Display::escHtml($outboundRecord->label),
                                                Display::escHtml($outboundRecord->idFeedOut),
                                                Display::escHtml(!empty($outboundRecord->result) ? $outboundRecord->result : '<LEGACY SUCCESS RESPONSE>')
                                            );
                                        }
                                        print '</ul>';
                                    } else {
                                        print '<p>No outgoing records found.</p>';
                                    }
                                    ?>
                                <?php } else { ?>
                                    <span style="font-weight: bold; color: <?php echo $record->result ? 'red' : 'green'; ?>"><?php echo Display::escHtml($record->result ?? 'Success'); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>

                <?php foreach ($records as $record) { ?>
                    <div style="display:none" class="modal fade" id="record-details-<?= Display::escHtml($record->idRecord); ?>" tabindex="-1" role="dialog"
                         aria-labelledby="record-details-title-<?= Display::escHtml($record->idRecord); ?>">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                                    </button>
                                    <h4 class="modal-title" id="record-details-title-<?= Display::escHtml($record->idRecord); ?>">Record Details</h4>
                                </div>
                                <div class="modal-search">
                                    <?php
                                    if (!empty($record->jobId) && !empty($record->rawData)) {
                                        $json = json_decode($record->rawData);
                                        echo "<h4>Raw Header Fields</h4>";

                                        foreach ($json as $key => $value) {
                                            if (is_array($value) || is_object($value)) {
                                                continue;
                                            }
                                            echo "<div class='row cell'>
                                                        <div class='col-sm-4 text-right px-3 py-5'>
                                                            <div>" . Display::escHtml($key) . "</div>
                                                        </div>
                                                        <div class='col-sm-8 px-3 py-5'>
                                                            <div>" . Display::escHtml($value) . "</div>
                                                        </div>
                                                    </div>";
                                        }

                                        if (!empty($json->getParams)) {
                                            echo "<h4>Raw GET Fields</h4>";
                                        }
                                        foreach ($json->getParams as $key => $value) {
                                            echo "<div class='row cell'>
                                                        <div class='col-sm-4 text-right px-3 py-5'>
                                                            <div>" . Display::escHtml($key) . "</div>
                                                        </div>
                                                        <div class='col-sm-8 px-3 py-5'>
                                                            <div>" . Display::escHtml($value) . "</div>
                                                        </div>
                                                    </div>";
                                        }

                                        if (!empty($json->postParams)) {
                                            echo "<h4>Raw POST Fields</h4>";
                                        }
                                        foreach ($json->postParams as $key => $value) {
                                            echo "<div class='row cell'>
                                                        <div class='col-sm-4 text-right px-3 py-5'>
                                                            <div>" . Display::escHtml($key) . "</div>
                                                        </div>
                                                        <div class='col-sm-8 px-3 py-5'>
                                                            <div>" . Display::escHtml($value) . "</div>
                                                        </div>
                                                    </div>";
                                        }
                                        echo "<h4>JSON String</h4>
                                           <code>" . Display::escHtml($record->rawData) . "</code><br/>";
                                    } else {
                                        echo "<h4>Database Fields (Legacy Record)</h4>";
                                        foreach ($record as $key => $value) {
                                            echo "<div class='row cell'>
                                                <div class='col-sm-4 text-right px-3 py-5'>
                                                    <div>" . Display::escHtml($key) . "</div>
                                                </div>
                                                <div class='col-sm-8 px-3 py-5'>
                                                    <div>" . Display::escHtml($value) . "</div>
                                                </div>
                                            </div>";
                                        }
                                    }
                                    ?>
                                    <br/>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <?php
            } else {
                print '<p>No records found.</p>' . PHP_EOL;
            }
        }
    }
    ?>
</div>
<script type="text/javascript">
    $("select[name='idFeedIn']").select2({
        placeholder: "Select an incoming feed",
        allowClear: true
    });

    $('input[name="startDate"], input[name="endDate"]').datepicker({
        // Consistent format with the HTML5 picker
        dateFormat: 'yy-mm-dd'
    });
</script>
</body>
</html>
