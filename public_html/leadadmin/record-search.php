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
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Lead Timestamp</th>
                    <th>IP Address</th>
                    <th>DOB</th>
                </tr>
                <tr>
                    <th colspan="9">Incoming and Outgoing Responses</th>
                </tr>
                </thead>
                <tbody>

                <?php $counter = 0; foreach ($records as $key => $value) { $counter++; ?>
                    <tr>
                        <td><?php echo Display::escHtml($value->companyName); ?> - <?php echo Display::escHtml($value->label); ?> (#<?php echo Display::escHtml($value->idFeedIn); ?>)</td>
                        <td><?php echo Display::escHtml($value->email); ?></td>
                        <td><?php echo Display::escHtml($value->fname); ?></td>
                        <td><?php echo Display::escHtml($value->lname); ?></td>
                        <td><?php echo Display::escHtml($value->leadstamp); ?></td>
                        <td><?php echo Display::escHtml($value->ip); ?></td>
                        <td><?php echo Display::escHtml($value->dob); ?></td>
                        <td>

                            <button type="button" value="<?php echo $counter ?>" data-toggle="modal" data-backdrop="static" data-target="#search">View Details</button>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="9">
                            <p><strong>Incoming Response</strong>: <?php echo Display::escHtml($value->result ?? 'Success'); ?></p>
                            <?php
                            $outboundRecords = $leads->outboundRecordSearchById($value->idRecord);
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
                    <div style="display:none" class="modal fade" id="search" tabindex="-1" role="dialog" aria-labelledby="search_title">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                                    </button>
                                    <h4 class="modal-title" id="search_title">Search Results</h4>
                                </div>
                                <div class="modal-search">
                                    <?php 
                                        foreach ($records[$counter] as $key => $value){
                                            echo "<div class='row cell'>
                                                        <div class='col-sm-4 text-right px-3 py-5'>
                                                            <div>".$key."</div>
                                                        </div>
                                                        <div class='col-sm-8 px-3 py-5'>
                                                            <div>".$value."</div>
                                                        </div>
                                                    </div>";
                                        }
                                    ?>
                                   
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
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


<script type="text/javascript">
    $('#search').on('click', function (e) {
        var modal = $(this);
    });
</script>
</body>
</html>
