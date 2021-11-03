<?php

require_once(INCLUDES . 'leads.php');

class Display
{
    public static function errorCount()
    {
        $leads = Leads::getInstance();
        $errorCount = $leads->getErrorCount();
        if ($errorCount === false) {
            print "X";
        } else {
            print $errorCount;
        }
    }

    public static function errorList()
    {
        $leads = Leads::getInstance();
        $errorList = $leads->getErrors();
        ?>
        <div class="fr">
            <a href="#" class="nonLink" onclick="closeContent(" errorList");" >Close [X]</a>
        </div>
        <?php

        if ($errorList === null) {
            print "Error fetching the errors list.";
        } elseif (sizeOf($errorList) == 0) {
            print "No errors on file today.";
        } else {
            foreach ($errorList as $error) {
                printf('<p>(%s) [%s] %s</p>',
                    htmlentities($error->stamp),
                    htmlentities($error->origination),
                    htmlentities($error->description));
            }
        }
    }

    public static function getQuarterEnd($year, $quarter)
    {
        if ($quarter == 1) {
            return $year . '-03-31';
        } elseif ($quarter == 2) {
            return $year . '-06-30';
        } elseif ($quarter == 3) {
            return $year . '-09-30';
        } else {
            return $year . '-12-31';
        }
    }

    public static function getQuarterStart($year, $quarter)
    {
        if ($quarter == 1) {
            return $year . '-01-01';
        } elseif ($quarter == 2) {
            return $year . '-04-01';
        } elseif ($quarter == 3) {
            return $year . '-07-01';
        } else {
            return $year . '-10-01';
        }
    }

    public static function displayForm($name, $fields = array(), $title = '', $options = array())
    {
        print "<div class=\"form-input\">\n";
        if (!empty($title)) {
            printf('<h3>%s</h3>',
                htmlentities($title)
            );
        }

        if (empty($options['fieldOnly'])) {
            printf("<form class=\"form-inline\" id=\"%s\"%s>\n",
                htmlspecialchars($name, ENT_QUOTES | ENT_HTML5),
                !empty($options['disableAutocomplete']) ? ' autocomplete="off"' : ''
            );
        }

        foreach ($fields as $field) {

            if (isset($field['active']) && false === $field['active']) {
                continue;
            }

            if (!in_array($field['type'], array('_toggle_start', '_toggle_end', '_html', '_text', 'hidden'))) {
                printf("\t<div class='pnt-form-row'>\n");
            }

            if (in_array($field['type'], array('text', 'number', 'tel', 'date', 'email', 'password', 'url'))) {

                printf("\t<label data-for=\"%s\">%s%s</label>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlentities($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : '')
                );
                printf("\t<input class=\"form-control\" type=\"%s\" name=\"%s\" id=\"%s\" data-lpignore=\"true\" value=\"%s\"%s%s%s%s />\n",
                    htmlspecialchars($field['type'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    (!empty($field['value']) ? htmlspecialchars($field['value'], ENT_QUOTES | ENT_HTML5) : ''),
                    (!empty($field['autocomplete']) ? (' autocomplete="' . Display::escHtml($field['autocomplete']) . '"') : ''),
                    ('number' == $field['type'] ? ' pattern="[0-9]*"' : ''),
                    (!empty($field['required']) ? ' required' : ''),
                    (!empty($field['readonly']) ? ' readonly' : '')
                );

            } elseif ('currency' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlentities($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : '')
                );
                printf("\t<input class=\"form-control\" type=\"text\" name=\"%s\" id=\"%s\" pattern=\"^\\$?(([1-9](\\d*|\\d{0,2}(,\\d{3})*))|0)(\\.\\d{1,2})?$\" data-lpignore=\"true\" value=\"%s\"%s%s />\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    (!empty($field['value']) ? htmlspecialchars($field['value'], ENT_QUOTES | ENT_HTML5) : ''),
                    (!empty($field['required']) ? ' required' : ''),
                    (!empty($field['readonly']) ? ' readonly' : '')
                );

            } elseif ('checkbox' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>%s\n",
                    htmlspecialchars($field['id']),
                    htmlspecialchars($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : ''),
                    (!empty($field['label_append']) ? $field['label_append'] : '')
                );
                print '<div class="checkbox-choices">';
                if (!empty($field['choices']) && is_array($field['choices'])) {
                    foreach ($field['choices'] as $key => $val) {
                        printf("\t<input class=\"form-control\" type=\"checkbox\" name=\"%s%s\" value=\"%s\"%s /> %s%s\n",
                            htmlspecialchars($field['id']),
                            (sizeOf($field['choices']) > 1 ? '[]' : ''),
                            htmlspecialchars($key),
                            (!empty($field['value'][$key]) ? ' checked="checked"' : ''),
                            htmlspecialchars($val),
                            (!empty($field['choice_append']) ? $field['choice_append'] : '')
                        );
                    }
                }
                print '</div>';

            } elseif ('checkboxBits' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>%s\n",
                    htmlspecialchars($field['id']),
                    htmlspecialchars($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : ''),
                    (!empty($field['label_append']) ? $field['label_append'] : '')
                );
                print '<div class="checkbox-choices">';
                if (!empty($field['choices']) && is_array($field['choices'])) {
                    foreach ($field['choices'] as $key => $val) {
                        printf("\t<input class=\"form-control\" type=\"checkbox\" name=\"%s%s\" value=\"%s\"%s /> %s%s\n",
                            htmlspecialchars($field['id']),
                            (sizeOf($field['choices']) > 1 ? '[]' : ''),
                            htmlspecialchars($key),
                            isset($field['value']) && LeadsSession::checkBit($field['value'], $key) ? ' checked="checked"' : '',
                            htmlspecialchars($val),
                            (!empty($field['choice_append']) ? $field['choice_append'] : '')
                        );
                    }
                }
                print '</div>';

            } elseif ('radio' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>\n",
                    htmlspecialchars($field['id']),
                    htmlspecialchars($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : '')
                );
                if (!empty($field['choices']) && is_array($field['choices'])) {
                    foreach ($field['choices'] as $key => $val) {
                        printf("\t<input type=\"radio\" name=\"%s\" value=\"%s\"%s%s%s /> %s%s\n",
                            htmlspecialchars($field['id']),
                            htmlspecialchars($key),
                            (isset($field['value']) && $key == $field['value']) ? ' checked="checked"' : '',
                            (!empty($field['required']) ? ' required="required" ' : ''),
                            (!empty($field['readonly']) ? ' readonly' : ''),
                            htmlspecialchars($val),
                            (!empty($field['choice_append']) ? $field['choice_append'] : '')
                        );
                    }
                }

            } elseif ('textarea' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlentities($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : '')
                );
                printf("\t<textarea class=\"form-control\" name=\"%s\" id=\"%s\"%s>%s</textarea>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    (!empty($field['required']) ? ' required' : ''),
                    (!empty($field['value']) ? htmlentities($field['value']) : '')
                );

            } elseif ('select' == $field['type']) {

                printf("\t<label data-for=\"%s\">%s%s</label>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlentities($field['label']),
                    (!empty($field['required']) ? ' <span class="required">*</span> ' : '')
                );
                printf("\t<select class=\"form-control\" name=\"%s%s\" id=\"%s\"%s%s>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    (!empty($field['multiple']) ? '[]' : ''),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    (!empty($field['readonly']) ? ' readonly' : ''),
                    (!empty($field['multiple']) ? ' multiple' : '')
                );
                if (isset($field['placeholder'])) {
                    if (!empty($field['placeholder'])) {
                        printf("\t\t<option disabled=\"disabled\"%s value=\"\">%s</option>\n",
                            empty($field['value']) ? ' selected="selected"' : '',
                            htmlentities($field['placeholder'])
                        );
                    }
                } else {
                    printf("\t\t<option value=\"\"></option>\n");
                }
                foreach ($field['choices'] as $key => $val) {
                    if (is_array($val)) {
                        printf("\t\t<optgroup label=\"%s\">\n",
                            htmlentities($key, ENT_QUOTES | ENT_HTML5)
                        );
                        foreach ($val as $rec_key => $rec_val) {
                            $selected = false;
                            if (isset($field['value']) && is_array($field['value'])) {
                                if (array_key_exists($rec_key, $field['value'])) {
                                    $selected = true;
                                }
                            } elseif (isset($field['value']) && $rec_key == $field['value']) {
                                $selected = true;
                            }
                            printf("\t\t\t<option value=\"%s\"%s>%s</option>\n",
                                htmlentities($rec_key, ENT_QUOTES | ENT_HTML5),
                                $selected ? ' selected="selected"' : '',
                                htmlentities($rec_val, ENT_HTML5)
                            );
                        }
                        print "\t\t</optgroup>\n";
                    } else {
                        $selected = false;
                        if (isset($field['value']) && is_array($field['value'])) {
                            if (array_key_exists($key, $field['value'])) {
                                $selected = true;
                            }
                        } elseif (isset($field['value']) && $key == $field['value']) {
                            $selected = true;
                        }
                        printf("\t\t<option value=\"%s\"%s>%s</option>\n",
                            htmlentities($key, ENT_QUOTES | ENT_HTML5),
                            $selected ? ' selected="selected"' : '',
                            htmlentities($val, ENT_HTML5)
                        );
                    }
                }
                printf("\t</select>\n");

            } elseif ('button' == $field['type']) {

                printf("\t<input type=\"button\" value=\"%s\" />\n",
                    htmlspecialchars($field['label'], ENT_QUOTES | ENT_HTML5)
                );

            } elseif ('hidden' == $field['type']) {

                printf("\t<input type=\"hidden\" name=\"%s\" id=\"%s\" value=\"%s\" />\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['value'], ENT_QUOTES | ENT_HTML5)
                );

            } elseif ('submit' == $field['type']) {

                printf("\t<label></label>\n");
                printf("\t<input class=\"btn btn-primary\" name=\"%s\" id=\"%s\" type=\"submit\" value=\"%s\" />\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['label'], ENT_QUOTES | ENT_HTML5)
                );

            } elseif ('_divider' == $field['type']) {

                printf("\t<hr class=\"divider\" />\n");

            } elseif ('_header' == $field['type']) {

                printf("\t<label></label>\n");
                printf("\t<h3>%s</h3>\n",
                    htmlentities($field['label'])
                );

            } elseif ('_toggle_start' == $field['type']) {

                printf("\t<button class=\"btn btn-xs btn-info\" type=\"button\" data-toggle=\"collapse\" data-target=\"#%s\" aria-expanded=\"false\" aria-controls=\"%s\">%s</button>\n",
                    htmlspecialchars($field['id']),
                    htmlspecialchars($field['id']),
                    htmlspecialchars($field['value'])
                );
                printf("\t<div class=\"%s\" id=\"%s\">\n",
                    !empty($field['collapsed']) ? 'collapse' : 'collapse in',
                    htmlspecialchars($field['id'])
                );

            } elseif ('_toggle_end' == $field['type']) {

                printf("\t</div>\n");

            } elseif ('_html' == $field['type']) {

                print $field['value'];

            } elseif ('_text' == $field['type']) {

                print "<div>";
                printf("\t<label data-for=\"%s\">%s</label>\n",
                    htmlspecialchars($field['id'], ENT_QUOTES | ENT_HTML5),
                    htmlspecialchars($field['label'], ENT_QUOTES | ENT_HTML5)
                );
                printf("\t<span>%s</span>", htmlspecialchars($field['value'], ENT_QUOTES | ENT_HTML5));
                print "</div>\n";
            }

            if (!in_array($field['type'], array('_toggle_start', '_toggle_end', '_html', '_text', 'hidden'))) {
                printf("\t</div>\n");
            }

        }

        if (empty($options['fieldOnly'])) {
            print "</form>\n";
        }
        print "</div>\n";
    }

    public static function displayDashboardRevenueTable($leads, $users, $statsStart, $statsEnd, $offline = false)
    {

        $today = new \DateTime();
        $yesterday = new \DateTime();
        try {
            $yesterday->sub(new \DateInterval('P1D'));
        } catch (\Exception $e) {
            // Do nothing
        }

        // Check for invalid date inputs
        try {
            $statsStart = new \DateTime($statsStart);
        } catch (\Exception $e) {
            $statsStart = new \DateTime();
        }

        try {
            $statsEnd = new \DateTime($statsEnd);
        } catch (\Exception $e) {
            $statsEnd = new \DateTime();
        }

        // Ensure the end date after the start date
        if ($statsEnd < $statsStart) {
            $statsEnd = $statsStart;
        }

        try {
            $statsStartFOM = new DateTime($statsStart->format('Y-m-01'));
            $statsEndEOM = new DateTime($statsEnd->format('Y-m-t'));
        } catch (\Exception $e) {
            // Do nothing
        }


        if (!empty($users) && is_array($users)) {
            $totals = array(
                'prevDay' => 0,
                'today' => 0,
                'existingAccrual' => 0,
                'existingExpectation' => 0,
                'existingProjected' => 0,
                'newExpectation' => 0,
                'newAccural' => 0,
                'grossProfit' => 0,
            );

            ?>

            <table class="table table-bordered table-condensed table-striped-custom table-small-font dashboard-forecasts">
                <thead>
                <tr>
                    <th>&nbsp;</th>
                    <th colspan="5">Total Business</th>
                    <th colspan="2">New Business</th>
                    <th>&nbsp;</th>
                </tr>
                <tr>
                    <th>Employee</th>
                    <th>Prev Day</th>
                    <th>Today</th>
                    <th>Accrual MTD</th>
                    <th>Expectation</th>
                    <th>Projected MTD</th>
                    <th>Accrual MTD</th>
                    <th>Expectation</th>
                    <th>GP</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $diffRange = intval($statsStartFOM->diff($statsEnd)->format("%a"));
                $diffTotal = intval($statsStartFOM->diff($statsEndEOM)->format("%a")) + 1;
                $forecastsToday = $leads->getForecasts($today->format('Y-m-d'), $today->format('Y-m-d'), $offline);
                $forecastsYesterday = $leads->getForecasts($yesterday->format('Y-m-d'), $yesterday->format('Y-m-d'), $offline);
                $forecastsMTD = $leads->getForecasts($statsStartFOM->format('Y-m-d'), $statsEndEOM->format('Y-m-d'), $offline);
                try {
                    $statsEndEOM->sub(new \DateInterval('P1D'));
                } catch (\Exception $e) {
                    // Do nothing
                }
                $forecastsProjected = $leads->getForecasts($today->format('Y-m-01'), $yesterday->format('Y-m-d'), $offline);
                foreach ($users as $userId => $fullName) {
                    $amountToday = $amountYesterday = $existingRevenueMTD = $newRevenueMTD = $accuralCostMTD = $projectedRevenueMTD = 0;
                    if (!empty($forecastsToday) && is_array($forecastsToday)) {
                        foreach ($forecastsToday as $forecastToday) {
                            if ($userId == $forecastToday->idUser) {
                                $amountToday = $forecastToday->existingRevenueMTD;
                            }
                        }
                    }
                    if (!empty($forecastsYesterday) && is_array($forecastsYesterday)) {
                        foreach ($forecastsYesterday as $forecastYesterday) {
                            if ($userId == $forecastYesterday->idUser) {
                                $amountYesterday = $forecastYesterday->existingRevenueMTD;
                            }
                        }
                    }
                    if (!empty($forecastsMTD) && is_array($forecastsMTD)) {
                        foreach ($forecastsMTD as $forecastMTD) {
                            if ($userId == $forecastMTD->idUser) {
                                $existingRevenueMTD = $forecastMTD->existingRevenueMTD;
                                $accuralCostMTD = $forecastMTD->accuralCostMTD;
                                $newRevenueMTD = $forecastMTD->newRevenueMTD;
                            }
                        }
                    }
                    if (!empty($forecastsProjected) && is_array($forecastsProjected)) {
                        foreach ($forecastsProjected as $forecastProjected) {
                            if ($userId == $forecastProjected->idUser) {
                                $projectedRevenueMTD = $forecastProjected->existingRevenueMTD;
                            }
                        }
                    }

                    $expectationValues = $leads->getExpectationValues($userId, $statsStartFOM->format('Y-m-'));
                    $projected = $diffRange > 0 ? (($projectedRevenueMTD * $diffTotal) / $diffRange) : 0;

                    $totals['prevDay'] += round($amountYesterday);
                    $totals['today'] += round($amountToday);
                    $totals['existingAccrual'] += round($existingRevenueMTD + $newRevenueMTD);
                    $totals['existingExpectation'] += round($expectationValues->existingBusinessAmount ?? 0);
                    $totals['existingProjected'] += round($projected);
                    $totals['newExpectation'] += round($newRevenueMTD);
                    $totals['newAccural'] += round($expectationValues->newBusinessAmount ?? 0);
                    $totals['grossProfit'] += round(($existingRevenueMTD + $newRevenueMTD) - $accuralCostMTD);

                    ?>
                    <tr>
                        <td><?php echo htmlentities($fullName); ?></td>
                        <td class="text-right">$<?php echo number_format(round($amountYesterday), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round($amountToday), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round($existingRevenueMTD + $newRevenueMTD), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round($expectationValues->existingBusinessAmount ?? 0), 0); ?></td>
                        <td class="text-right<?php echo ($expectationValues->existingBusinessAmount ?? 0) > $projected ? ' bg-danger' : ''; ?>">$<?php echo number_format(round($projected), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round($newRevenueMTD), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round($expectationValues->newBusinessAmount ?? 0), 0); ?></td>
                        <td class="text-right">$<?php echo number_format(round(($existingRevenueMTD + $newRevenueMTD) - $accuralCostMTD), 0); ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
                <?php
                if (sizeOf($users) > 1) {
                    ?>
                    <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-right">$<?php echo number_format($totals['prevDay'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['today'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['existingAccrual'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['existingExpectation'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['existingProjected'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['newExpectation'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['newAccural'], 0); ?></td>
                        <td class="text-right">$<?php echo number_format($totals['grossProfit'], 0); ?></td>
                    </tr>
                    </tfoot>

                    <?php
                }
                ?>
            </table>

            <?php
        }
    }

    public static function escHtml($html, $flags = ENT_HTML5 | ENT_QUOTES)
    {
        return htmlspecialchars($html, $flags, 'UTF-8');
    }

    public static function decryptValue($data)
    {

        try {
            $data = \Cryptor::Decrypt($data, ENCRYPTION_KEY);
        } catch (\Exception $e) {
            // Do nothing
        }

        return $data;
    }

    public static function encryptValue($data)
    {

        try {
            $data = \Cryptor::Encrypt($data, ENCRYPTION_KEY);
        } catch (\Exception $e) {
            // Do nothing
        }

        return $data;
    }

    public static function findFilesRecurse($dir)
    {
        $values = array();

        if (!file_exists($dir)) {
            return $values;
        }

        $files = scandir($dir);
        foreach ($files as $file) {

            // Skip directories with dots
            if (!in_array($file, array('.', '..'))) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $file)) {
                    $values = array_merge(Display::findFilesRecurse($dir . DIRECTORY_SEPARATOR . $file), $values);
                } else {
                    $values[] = $dir . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        return $values;
    }

    public static function sendNoCacheHeaders($includeNoIndex = true)
    {
        Header('Pragma: no-cache'); // HTTP/1.0
        Header('Expires: ' . gmdate("D, d M Y H:i:s") . ' GMT'); // same date, expire immediately
        Header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . ' GMT'); // always modified
        Header('Cache-Control: no-store');  // HTTP/1.1
        Header('X-Nginx-Expires: off');
        if ($includeNoIndex) {
            Header('X-Robots-Tag: noindex');
        }
    }
}
