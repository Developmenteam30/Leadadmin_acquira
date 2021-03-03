<?php

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

include(__DIR__ . "/../includes/c_config.php");

require_once(INCLUDES . 'leads.php');

// PHPSPREADSHEET
include(INCLUDES . "vendor/autoload.php");

set_time_limit(0);

$leads = Leads::getInstance();
$leads->setNetTimeouts(3600);

$dateStart = new DateTime('Monday last week');
$dateEnd = new DateTime('Sunday last week');

$spreadsheet = new Spreadsheet();
$spreadsheet->setActiveSheetIndex(0);
$spreadsheet->getActiveSheet()
    ->setCellValue('A1', 'Company Name')
    ->setCellValue('B1', 'Feed ID')
    ->setCellValue('C1', 'Feed Label')
    ->setCellValue('D1', 'Accepted')
    ->setCellValue('E1', 'CPL')
    ->setCellValue('F1', 'Cost');

$feeds = $leads->getInboundFeeds(null, 'active');

$rows = [];
$totalAccepted = 0;
$totalCost = 0;
foreach ($feeds as $feed) {
    $stats = $leads->getInboundStatsRange($feed->idFeedIn, $dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d'));

    $totalAccepted += $stats['accepted'];
    $totalCost += $feed->costPerLead * $stats['accepted'];

    $rows[] = [
        $feed->name,
        $feed->idFeedIn,
        $feed->label,
        $stats['accepted'],
        $feed->costPerLead,
        $feed->costPerLead * $stats['accepted'],
    ];
}

$rows[] = [
    'TOTAL',
    '',
    '',
    $totalAccepted,
    $totalAccepted > 0 ? $totalCost / $totalAccepted : 0,
    $totalCost,
];

$spreadsheet->getActiveSheet()->fromArray($rows, null, 'A2');

$totalRows = count($rows);
if ($totalRows) {
    $spreadsheet->getActiveSheet()
        ->getStyle('D2:D' . ($totalRows + 1))
        ->getNumberFormat()
        ->setFormatCode('#,##0');

    $spreadsheet->getActiveSheet()
        ->getStyle('E2:F' . ($totalRows + 1))
        ->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
}

// Bold the last row
$spreadsheet->getActiveSheet()->getStyle('A' . ($totalRows + 1) . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(1))) . ($totalRows + 1))->getFont()->setBold(true);

try {
    // Add header colors and formatting
    $spreadsheet->getActiveSheet()->getStyle('A1:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(1))) . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0095FF');
    $spreadsheet->getActiveSheet()->getStyle('A1:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(1))) . '1')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
    $spreadsheet->getActiveSheet()->getStyle('A1:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(1))) . '1')->getFont()->setBold(true);

    // Add header filters
    $spreadsheet->getActiveSheet()->setAutoFilter($spreadsheet->getActiveSheet()->calculateWorksheetDimension());

    // Auto size columns
    for ($col = 1; $col <= Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn()); ++$col) {
        $spreadsheet->getActiveSheet()->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    // Freeze top row
    $spreadsheet->getActiveSheet()->freezePane('A2');

    // Reset selected cell
    $spreadsheet->getActiveSheet()->setSelectedCell('A2');

} catch (Exception $e) {
    echo "PHPSpreadsheet Exception: " . $e->getMessage();
}

$filename = tempnam("/tmp", "WDCR");

$writer = new Xlsx($spreadsheet);
$writer->save($filename);

try {
    $mail = new PHPMailer;
    $mail->CharSet = 'UTF-8';
    $mail->XMailer = ' ';
    $mail->Hostname = SITE_URL;
    $mail->setFrom(SYSTEM_FROM_EMAIL, CONFIG_COMPANY_NAME . ' List Management System');
    $mail->Subject = COMPANY_INITIALS . ' Weekly Data Cost Report (' . $dateStart->format('m/d/Y') . ' - ' . $dateEnd->format('m/d/Y') . ')';
    $mail->isHTML(false);
    $mail->Body = 'Please find last week\'s data cost report attached.';

    $emails = explode(',', WEEKLY_DATA_COST_EMAILS);
    foreach ($emails as $email) {
        $mail->addAddress($email);
    }
    $mail->addAttachment($filename, COMPANY_INITIALS . ' Weekly Data Cost Report ' . $dateEnd->format('Y-m-d') . '.xlsx');

    $mail->send();
} catch (PHPMailerException $e) {
    echo "PHPMailer Exception: " . $e->getMessage();
}

@unlink($filename);