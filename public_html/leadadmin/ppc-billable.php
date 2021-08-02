<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess([LEADS_SESSION_LEVEL_MANAGER, LEADS_SESSION_LEVEL_ADMIN]);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

$idFeedOut = !empty($_REQUEST['idFeedOut']) ? $_REQUEST['idFeedOut'] : null;
$dateStart = !empty($_REQUEST['dateStart']) ? $_REQUEST['dateStart'] : date('Y-m-d');
$dateEnd = !empty($_REQUEST['dateEnd']) ? $_REQUEST['dateEnd'] : date('Y-m-d');
$statsQuick = $_REQUEST['statsQuick'] ?? '';

// Check for invalid date inputs
try {
    $dateStart = new \DateTime($dateStart);
} catch (\Exception $e) {
    $dateStart = new \DateTime();
}

try {
    $dateEnd = new \DateTime($dateEnd);
} catch (\Exception $e) {
    $dateEnd = new \DateTime();
}

// Ensure the end date after the start date
if ($dateEnd < $dateStart) {
    $dateEnd = $dateStart;
}

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0,
        'error' => 'Action does not exist.',
    );
    switch ($_REQUEST['a']) {
        case "toggleBillable":

            if (empty($_REQUEST['timestamp']) || empty($_REQUEST['idFeedOut']) || empty($_REQUEST['idRecord'])) {
                $result['error'] = 'Missing required parameters.';
                break;
            }

            $record = $leads->archivedOutboundRecordsSearch($_REQUEST['idFeedOut'], $_REQUEST['timestamp'], $_REQUEST['timestamp'], $_REQUEST['idRecord']);
            if (empty($record)) {
                $result['error'] = 'Cannot find requested record.';
                break;
            }

            $leads->toggleBillable($record, !empty($_REQUEST['isBillable'] ? true : false));

            $result['status'] = 1;
            $result['error'] = 'Successfully toggled billable flag.';
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
    }
    exit;
}

$title = 'PPC Billable Leads Manager';
include(INCLUDES . "c_header.php");

?>
<body>
<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

    <h2>PPC Billable Leads Manager</h2>

    <form method="get">
        <p>

            <?php
            print 'Quick Jump: <select id="statsQuick" name="statsQuick">' . PHP_EOL;
            print '<option value=""></option>' . PHP_EOL;
            $years = array();
            $quarters = array();

            $startDate = new \DateTime();
            $value = $startDate->format('Y-m-d') . '|' . $startDate->format('Y-m-d');
            printf('<option value="%s"%s>Today</option>' . PHP_EOL,
                $value,
                $statsQuick == $value ? ' selected="selected"' : ''
            );

            $startDate = new \DateTime();
            $startDate->sub(new DateInterval(('P1D')));
            $value = $startDate->format('Y-m-d') . '|' . $startDate->format('Y-m-d');
            printf('<option value="%s"%s>Yesterday</option>' . PHP_EOL,
                $value,
                $statsQuick == $value ? ' selected="selected"' : ''
            );

            $startDate = new \DateTime("monday this week");
            $endDate = new \DateTime("sunday this week");
            $value = $startDate->format('Y-m-d') . '|' . $endDate->format('Y-m-d');
            printf('<option value="%s"%s>This Week</option>' . PHP_EOL,
                $value,
                $statsQuick == $value ? ' selected="selected"' : ''
            );

            $startDate = new \DateTime("monday last week");
            $endDate = new \DateTime("sunday last week");
            $value = $startDate->format('Y-m-d') . '|' . $endDate->format('Y-m-d');
            printf('<option value="%s"%s>Last Week</option>' . PHP_EOL,
                $value,
                $statsQuick == $value ? ' selected="selected"' : ''
            );

            $startDate = new \DateTime();
            $endDate = new DateTime((date('Y') - 3) . '-01-01');
            do {
                $year = $startDate->format('Y');
                $quarter = $year . '-Q' . ceil($startDate->format('m') / 3);
                if (empty($years[$year])) {
                    $value = $year . '-01-01' . '|' . $year . '-12-31';
                    printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                        $value,
                        $statsQuick == $value ? ' selected="selected"' : '',
                        htmlentities($year . ' Year')
                    );
                    $years[$year] = true;
                }
                if (empty($quarters[$quarter])) {
                    $value = Display::getQuarterStart($year, ceil($startDate->format('m') / 3)) . '|' . Display::getQuarterEnd($year, ceil($startDate->format('m') / 3));
                    printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                        $value,
                        $statsQuick == $value ? ' selected="selected"' : '',
                        htmlentities(str_replace('-Q', ' Qtr ', $quarter))
                    );
                    $quarters[$quarter] = true;
                }

                $value = $startDate->format('Y-m-01') . '|' . $startDate->format('Y-m-t');
                printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                    $value,
                    $statsQuick == $value ? ' selected="selected"' : '',
                    htmlentities($startDate->format('Y-m'))
                );
                $startDate->sub(new \DateInterval('P1M'));
            } while ($startDate >= $endDate);
            print '</select>' . PHP_EOL;
            ?>
            <select name="idFeedOut">
                <?php

                $outboundFeeds = $leads->getOutboundFeeds(null, 'active', 'ppc');
                if (!empty($outboundFeeds) && is_array($outboundFeeds)) {
                    foreach ($outboundFeeds as $outboundFeed) {
                        printf('<option value="%s"%s>%s - #%s (%s)</option>' . PHP_EOL,
                            Display::escHtml($outboundFeed->idFeedOut),
                            $idFeedOut == $outboundFeed->idFeedOut ? ' selected="selected"' : '',
                            Display::escHtml($outboundFeed->name),
                            Display::escHtml($outboundFeed->idFeedOut),
                            Display::escHtml($outboundFeed->label)
                        );
                    }
                }
                ?>
            </select>
            <input type="text" name="dateStart" value="<?php echo Display::escHtml($dateStart->format('Y-m-d')); ?>"> to
            <input type="text" name="dateEnd" value="<?php echo Display::escHtml($dateEnd->format('Y-m-d')); ?>">
            <input class="btn btn-primary btn-xs nonLink" type="submit" name="submit" value="Update"/>
        </p>
    </form>

    <?php

    $records = $leads->archivedOutboundRecordsSearch($idFeedOut, $dateStart->format('Y-m-d 00:00:00'), $dateEnd->format('Y-m-d 23:59:59'));
    if (empty($records) || !is_array($records)) {
        print '<p class="errors">No accepted records were found for this feed and date range combination.<p>' . PHP_EOL;
    } else {
    ?>
    <table class="table table-bordered table-condensed table-striped-custom table-small-font">
        <thead>
        <tr>
            <th>Timestamp</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Cellphone</th>
            <th>Zip Code</th>
            <th>Billable</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($records as $record) {
            ?>
            <tr>
                <td><?php echo Display::escHtml($record->timestampConverted); ?></td>
                <td><?php echo Display::escHtml($record->fname); ?></td>
                <td><?php echo Display::escHtml($record->lname); ?></td>
                <td><?php echo Display::escHtml($record->email); ?></td>
                <td><?php echo Display::escHtml($record->cellphone); ?></td>
                <td><?php echo Display::escHtml($record->zip); ?></td>
                <td class="revenue"><input class="billable-toggle" type="checkbox" data-timestamp="<?php echo Display::escHtml(date('Ymd', strtotime($record->timestampConverted))); ?>"
                                           data-id-feed-out="<?php echo Display::escHtml($idFeedOut); ?>" data-id-record="<?php echo Display::escHtml($record->idRecord); ?>"
                                           value="1"<?php echo !empty($record->isBillable) ? ' checked="checked"' : ''; ?>/></td>
            </tr>
            <?php
        }
        print '</tbody>' . PHP_EOL;
        print '</table>' . PHP_EOL;
        }

        ?>
</div>

<script>
    $('input[name="dateStart"], input[name="dateEnd"]').datepicker({
        // Consistent format with the HTML5 picker
        dateFormat: 'yy-mm-dd'
    });

    $('.billable-toggle').change(function () {
        var timestamp = $(this).data('timestamp');
        var idFeedOut = $(this).data('id-feed-out');
        var idRecord = $(this).data('id-record');
        var isBillable = $(this).is(':checked') ? '1' : '0';

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'ppc-billable.php',
            data: {
                'a': 'toggleBillable',
                'timestamp': timestamp,
                'idFeedOut': idFeedOut,
                'idRecord': idRecord,
                'isBillable': isBillable
            }
        });
    });

    $('#statsQuick').on('change', function (e) {
        let myValue = $(this).val() || '';
        if (myValue !== '') {
            let dates = myValue.split('|', 2);
            $('input[name="dateStart"]').val(dates[0]);
            $('input[name="dateEnd"]').val(dates[1]);
        }
    });
</script>

</body>
</html>
