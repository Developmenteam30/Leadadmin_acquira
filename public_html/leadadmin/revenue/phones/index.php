<?php

include("../../../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_ADMIN);

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

$title = 'Phone Revenue Report';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">
	<h2>Phone Revenue Report</h2>

    <?php
    $dateStart = !empty($_REQUEST['dateStart']) ? $_REQUEST['dateStart'] : date('Y-m-d');
    $dateEnd = !empty($_REQUEST['dateEnd']) ? $_REQUEST['dateEnd'] : date('Y-m-d');
    $companyId = !empty($_REQUEST['companyId']) ? $_REQUEST['companyId'] : null;
    $userId = !empty($_REQUEST['userId']) ? $_REQUEST['userId'] : null;
    ?>

	<form class="form-inline">
		<input type="text" name="dateStart" class="dateSelector" value="<?php echo Display::escHtml($dateStart, ENT_QUOTES | ENT_HTML5); ?>"/>
		to
		<input type="text" name="dateEnd" class="dateSelector" value="<?php echo Display::escHtml($dateEnd, ENT_QUOTES | ENT_HTML5); ?>"/>

		<!--
		<select name="companyId">
			<option value="">Filter by company</option>
            <?php
        $companies = $leads->getCompanies('active');
        foreach ($companies as $company) {
            printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                Display::escHtml($company->idCompany),
                $companyId == $company->idCompany ? ' selected="selected"' : '',
                Display::escHtml($company->name)
            );
        }
        ?>
		</select>
		-->

		<select name="userId">
			<option value="">Filter by salesperson</option>
            <?php
            $users = $leads->getStaffUsers();
            foreach ($users as $id => $name) {
                printf('<option value="%s"%s>%s</option>' . PHP_EOL,
                    Display::escHtml($id),
                    $userId == $id ? ' selected="selected"' : '',
                    Display::escHtml($name)
                );
            }
            ?>
		</select>
		<input class="btn btn-primary" type="submit" name="submit" value="Update"/>
	</form>

    <?php

    $companyStats = $leads->getRevenueStatsCompany($dateStart, $dateEnd, $userId);

    if (empty($companyStats)) {

        print '<p>Sorry, there were no records found matching the specified filters.</p>' . PHP_EOL;

    } else {

        $totals = [
            'accepted' => 0,
            'revenue' => 0,
            'expense' => 0,
        ];

        foreach ($users as $idUser => $name) {
            if (!empty($userId) && $userId != $idUser) {
                continue;
            }
            $totals[$idUser] = 0;
        }

        foreach ($companyStats as $companyStat) {

            printf('<h3>%s</h3>' . PHP_EOL,
                Display::escHtml($companyStat->name)
            );
            print '<table class="table table-bordered table-condensed table-striped">' . PHP_EOL;
            print '<thead>' . PHP_EOL;
            print '<tr>' . PHP_EOL;
            print '<th style="width:50%;">Feed</th>' . PHP_EOL;
            printf('<th width="%f%%">Qty</th>' . PHP_EOL,
                round(50 / (4 + sizeOf($users)), 2)
            );
            printf('<th width="%f%%">Revenue</th>' . PHP_EOL,
                round(50 / (4 + sizeOf($users)), 2)
            );
            printf('<th width="%f%%">Expense</th>' . PHP_EOL,
                round(50 / (4 + sizeOf($users)), 2)
            );
            printf('<th width="%f%%">Net Income</th>' . PHP_EOL,
                round(50 / (4 + sizeOf($users)), 2)
            );
            foreach ($users as $idUser => $name) {
                if (!empty($userId) && $userId != $idUser) {
                    continue;
                }
                list($first, $garbage) = explode(' ', $name, 2);
                printf('<th width="%f%%">%s</th>' . PHP_EOL,
                    round(50 / (4 + sizeOf($users)), 2),
                    Display::escHtml($first)
                );
            }
            print '</tr>' . PHP_EOL;
            print '</thead>' . PHP_EOL;

            $outboundStats = $leads->getRevenueStatsOutbound($companyStat->idCompany, $dateStart, $dateEnd);
            foreach ($outboundStats as $outboundStat) {
                print '<tbody>' . PHP_EOL;
                printf('<tr class="success clickable" data-toggle="collapse" data-target="#feedout_%s">' . PHP_EOL,
                    Display::escHtml($outboundStat->idFeedOut)
                );
                printf('<td><span class="glyphicon glyphicon-upload" aria-hidden="true"></span> %s: %s (%s)</td>' . PHP_EOL,
                    Display::escHtml($outboundStat->idFeedOut),
                    Display::escHtml($outboundStat->label),
                    Display::escHtml($outboundStat->description)
                );

                printf('<td class="text-right">%s</td>' . PHP_EOL,
                    number_format($outboundStat->accepted, 0)
                );
//                printf('<td class="text-right">$%s</td>' . PHP_EOL,
//                    number_format($outboundStat->revenuePerLead, 3)
//                );
                printf('<td class="text-right">$%s</td>' . PHP_EOL,
                    number_format($outboundStat->revenue, 2)
                );
                printf('<td class="text-right">$%s</td>' . PHP_EOL,
                    number_format($outboundStat->expense, 2)
                );
                printf('<td class="text-right">$%s</td>' . PHP_EOL,
                    number_format($outboundStat->revenue - $outboundStat->expense, 2)
                );
                foreach ($users as $idUser => $name) {
                    if (!empty($userId) && $userId != $idUser) {
                        continue;
                    }
                    $userStats = $leads->getRevenueStatsUser($dateStart, $dateEnd, $companyStat->idCompany, $idUser, $outboundStat->idFeedOut);
                    printf('<td class="text-right">$%s</td>' . PHP_EOL,
                        number_format($userStats, 2)
                    );
                }
                print '</tr>' . PHP_EOL;
                print '</tbody>' . PHP_EOL;

                $inboundStats = $leads->getRevenueStatsInbound($outboundStat->idFeedOut, $dateStart, $dateEnd);
                printf('<tbody id="feedout_%s" class="collapse">' . PHP_EOL,
                    Display::escHtml($outboundStat->idFeedOut)
                );
                foreach ($inboundStats as $inboundStat) {
                    print '<tr class="danger">' . PHP_EOL;
                    printf('<td><span class="glyphicon glyphicon-download" aria-hidden="true" style="margin-left:2em;"></span> %s - %s: %s (%s)</td>' . PHP_EOL,
                        Display::escHtml($inboundStat->name),
                        Display::escHtml($inboundStat->idFeedIn),
                        Display::escHtml($inboundStat->label),
                        Display::escHtml($inboundStat->description)
                    );

                    printf('<td class="text-right">%s</td>' . PHP_EOL,
                        number_format($inboundStat->accepted, 0)
                    );
//                    printf('<td class="text-right">$%s</td>' . PHP_EOL,
//                        number_format($inboundStat->revenuePerLead, 3)
//                    );
                    printf('<td class="text-right">$%s</td>' . PHP_EOL,
                        number_format($inboundStat->revenue, 2)
                    );
                    printf('<td class="text-right">$%s</td>' . PHP_EOL,
                        number_format($inboundStat->expense, 2)
                    );
                    printf('<td class="text-right">$%s</td>' . PHP_EOL,
                        number_format($inboundStat->revenue - $inboundStat->expense, 2)
                    );
                    foreach ($users as $idUser => $name) {
                        if (!empty($userId) && $userId != $idUser) {
                            continue;
                        }
                        $userStats = $leads->getRevenueStatsUser($dateStart, $dateEnd, $companyStat->idCompany, $idUser, $outboundStat->idFeedOut, $inboundStat->idFeedIn);
                        printf('<td class="text-right">$%s</td>' . PHP_EOL,
                            number_format($userStats, 2)
                        );
                    }
                    print '</tr>' . PHP_EOL;
                }
                print '</tbody>' . PHP_EOL;
            }

            print '<tfoot>' . PHP_EOL;
            print '<tr>' . PHP_EOL;
            print '<td>COMPANY TOTAL</td>' . PHP_EOL;
            printf('<td class="text-right">%s</td>' . PHP_EOL,
                number_format($companyStat->accepted, 0)
            );
            printf('<td class="text-right">$%s</td>' . PHP_EOL,
                number_format($companyStat->revenue, 2)
            );
            printf('<td class="text-right">$%s</td>' . PHP_EOL,
                number_format($companyStat->expense, 2)
            );
            printf('<td class="text-right">$%s</td>' . PHP_EOL,
                number_format($companyStat->revenue - $companyStat->expense, 2)
            );
            $totals['accepted'] += $companyStat->accepted;
            $totals['revenue'] += $companyStat->revenue;
            $totals['expense'] += $companyStat->expense;
            foreach ($users as $idUser => $name) {
                if (!empty($userId) && $userId != $idUser) {
                    continue;
                }
                $userStats = $leads->getRevenueStatsUser($dateStart, $dateEnd, $companyStat->idCompany, $idUser);
                printf('<td class="text-right">$%s</td>' . PHP_EOL,
                    number_format($userStats, 2)
                );
                $totals[$idUser] += $userStats;

            }
            print '</tr>' . PHP_EOL;
            print '</tfoot>' . PHP_EOL;
            print '</table>' . PHP_EOL;

        }

        print '<table class="table table-bordered table-condensed table-striped">' . PHP_EOL;
        print '<thead>' . PHP_EOL;
        print '<tr>' . PHP_EOL;
        print '<th style="width:50%;">Description</th>' . PHP_EOL;
        printf('<th width="%f%%">Qty</th>' . PHP_EOL,
            round(50 / (4 + sizeOf($users)), 2)
        );
        printf('<th width="%f%%">Revenue</th>' . PHP_EOL,
            round(50 / (4 + sizeOf($users)), 2)
        );
        printf('<th width="%f%%">Expense</th>' . PHP_EOL,
            round(50 / (4 + sizeOf($users)), 2)
        );
        printf('<th width="%f%%">Net Income</th>' . PHP_EOL,
            round(50 / (4 + sizeOf($users)), 2)
        );
        foreach ($users as $idUser => $name) {
            if (!empty($userId) && $userId != $idUser) {
                continue;
            }
            list($first, $garbage) = explode(' ', $name, 2);
            printf('<th width="%f%%">%s</th>' . PHP_EOL,
                round(50 / (4 + sizeOf($users)), 2),
                Display::escHtml($first)
            );
        }
        print '</tr>' . PHP_EOL;
        print '</thead>' . PHP_EOL;
        print '<tfoot>' . PHP_EOL;
        print '<tr class="warning">' . PHP_EOL;
        print '<td>GRAND TOTAL OF ALL COMPANIES</td>' . PHP_EOL;
        printf('<td class="text-right">%s</td>' . PHP_EOL,
            number_format($totals['accepted'], 0)
        );
        printf('<td class="text-right">$%s</td>' . PHP_EOL,
            number_format($totals['revenue'], 2)
        );
        printf('<td class="text-right">$%s</td>' . PHP_EOL,
            number_format($totals['expense'], 2)
        );
        printf('<td class="text-right">$%s</td>' . PHP_EOL,
            number_format($totals['revenue'] - $totals['expense'], 2)
        );
        foreach ($users as $idUser => $name) {
            if (!empty($userId) && $userId != $idUser) {
                continue;
            }
            printf('<td class="text-right">$%s</td>' . PHP_EOL,
                number_format($totals[$idUser], 2)
            );
        }
        print '</tr>' . PHP_EOL;
        print '</tfoot>' . PHP_EOL;
        print '</table>' . PHP_EOL;

    }
    ?>

</div>

</body>
</html>
