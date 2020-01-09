<?php

require('../includes/c_config.php');
require_once(INCLUDES . 'leads.php');

$phoneSuppression = [];
$emailSuppression = [];
$zipsNY = [];
$zipsNJ = [];

$file = fopen("/tmp/Suppression_12.17.csv", "r");
while (!feof($file)) {
    $data = trim(fgets($file));
    if (!empty($data)) {
        if (strpos($data, '@') !== false) {
            if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
                $emailSuppression[] = $data;
            } else {
                print "INVALID EMAIL: $data\n";
            }
        } else {
            $data = preg_replace('/[^0-9]/', '', $data);
            if (strlen($data) === 11) {
                $phoneSuppression[] = substr($data, 1, 10);
            } else {
                if (strlen($data) === 10) {
                    $phoneSuppression[] = $data;
                } else {
                    print "INVALID PHONE: $data\n";
                }
            }
        }
    }
}
fclose($file);

$file = fopen("/tmp/NY_ZIP_4-17-18.csv", "r");
while (!feof($file)) {
    $data = trim(fgets($file));
    if (!empty($data) && preg_match('/^"?(\d{5}) /', $data, $matches)) {
        $zipsNY[] = $matches[1];
    } else {
        echo "INVALID ZIP: $data\n";
    }
}
fclose($file);

$file = fopen("/tmp/NJ_ZIP_4-17-18.csv", "r");
while (!feof($file)) {
    $data = trim(fgets($file));
    if (!empty($data) && preg_match('/^"?(\d{5}) /', $data, $matches)) {
        $zipsNJ[] = $matches[1];
    } else {
        echo "INVALID ZIP: $data\n";
    }
}
fclose($file);

echo sizeof($phoneSuppression);
echo PHP_EOL;
echo sizeof($emailSuppression);
echo PHP_EOL;
echo sizeof($zipsNJ);
echo PHP_EOL;
echo sizeof($zipsNY);
echo PHP_EOL;

$leads = Leads::getInstance();
$leads->homeownerExportArchivedSpecial($phoneSuppression, $emailSuppression, $zipsNY, 19000);
$leads->homeownerExportArchivedSpecial($phoneSuppression, $emailSuppression, $zipsNJ, 7800);

