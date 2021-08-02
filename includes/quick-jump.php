<?php
$statsQuick = $_REQUEST['statsQuick'] ?? '';

$statsStart = !empty($_REQUEST['statsStart']) ? $_REQUEST['statsStart'] : date('Y-m-d');
$statsEnd = !empty($_REQUEST['statsEnd']) ? $_REQUEST['statsEnd'] : date('Y-m-d');

?>
<form class="form-inline" method="get">
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
    Set Dates:
    <input type="text" name="statsStart" class="dateSelector" value="<?php echo htmlentities($statsStart, ENT_QUOTES | ENT_HTML5); ?>"/> to
    <input type="text" name="statsEnd" class="dateSelector" value="<?php echo htmlentities($statsEnd, ENT_QUOTES | ENT_HTML5); ?>"/>
    <input class="btn btn-primary btn-xs nonLink" type="submit" name="submit" value="Update"/>
    <input type="hidden" name="status" value="<?php echo Display::escHtml($status ?? ''); ?>">
</form>

<script>
    $('#statsQuick').on('change', function (e) {
        let myValue = $(this).val() || '';
        if (myValue !== '') {
            let dates = myValue.split('|', 2);
            $('input[name="statsStart"]').val(dates[0]);
            $('input[name="statsEnd"]').val(dates[1]);
        }
    });

    $('input[name="statsStart"], input[name="statsEnd"]').datepicker({
        // Consistent format with the HTML5 picker
        dateFormat: 'yy-mm-dd'
    });
</script>
