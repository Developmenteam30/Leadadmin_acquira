<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

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

        case 'dialog_search_email_results':
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
    <p>Searches will be performed against the entire archive of data back to June 2014.</p>
    <?php if ('Q' === COMPANY_INITIALS) { ?>
        <p>We started keeping inbound rejected records on 5/1/2018 and outbound rejected records on 9/1/2018. Before these dates, only accepted records were logged and available for searching.</p>
    <?php } ?>
    <p>Results are limited to the first 500 matching entries and are sorted with the most recent entries on top.</p>

    <?php

    $fields = array(
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
            'id' => 'submit',
            'type' => 'submit',
            'label' => 'Search',
        ),
    );

    Display::displayForm('record_search', $fields);

    $email = trim($_REQUEST['email'] ?? '');
    $phone = trim(preg_replace('/[^0-9]/', '', $_REQUEST['phone'] ?? ''));
    $url = trim($_REQUEST['url'] ?? '');
    $ip = trim($_REQUEST['ip'] ?? '');

    if (!empty($email) || !empty($phone) || !empty($url) || !empty($ip)) {

        $leads->auditLog('SEARCH:EMAIL', json_encode(array('email' => $email, 'phone' => $phone, 'url' => $url, 'ip' => $ip)));

        ?>
        <p>Searching incoming feeds ...</p>

        <?php
        $records = $leads->inboundRecordSearch($email, $phone, $url, $ip);
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
                    <th colspan="9">Incoming and Outgoing Responses</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach ($records as $record) { ?>
                    <tr>
                        <td><?php echo Display::escHtml($record->companyName); ?> - <?php echo Display::escHtml($record->label); ?> (#<?php echo Display::escHtml($record->idFeedIn); ?>)</td>
                        <td><?php echo Display::escHtml($record->email); ?></td>
                        <td><?php echo Display::escHtml($record->timestampConverted); ?></td>
                        <td><?php echo Display::escHtml($record->url); ?></td>
                        <td><?php echo Display::escHtml($record->fname); ?></td>
                        <td><?php echo Display::escHtml($record->lname); ?></td>
                        <td><?php echo Display::escHtml($record->leadstamp); ?></td>
                        <td><?php echo Display::escHtml($record->ip); ?></td>
                        <td><?php echo Display::escHtml($record->dob); ?></td>
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
                            <p><strong>Incoming Response</strong>: <?php echo Display::escHtml($record->result ?? 'Success'); ?></p>
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
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <?php
        } else {
            print '<p>No records found.</p>' . PHP_EOL;
        }
    }
    ?>
</div>
</body>
</html>
