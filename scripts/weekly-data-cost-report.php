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
    ->setCellValue('A1', COMPANY_INITIALS . ' Weekly Data Cost Report (' . $dateStart->format('m/d/Y') . ' - ' . $dateEnd->format('m/d/Y') . ') - Week ' . $dateStart->format('W'));
$spreadsheet->getActiveSheet()
    ->setCellValue('A2', 'Company Name')
    ->setCellValue('B2', 'Feed ID')
    ->setCellValue('C2', 'Feed Label')
    ->setCellValue('D2', 'Feed Description')
    ->setCellValue('E2', 'EQ Accepted')
    ->setCellValue('F2', 'CPL')
    ->setCellValue('G2', 'Net Cost');

$feeds = $leads->getInboundFeeds(null, 'active');

$rows = [];
$totalInboundAccepted = 0;
$totalOutboundAccepted = 0;
$totalNetCost = 0;
foreach ($feeds as $feed) {
    $inboundStats = $leads->getInboundStatsRange($feed->idFeedIn, $dateStart->format('Y-m-d'), $dateEnd->format('Y-m-d'));
    $totalInboundAccepted += $inboundStats['accepted'];
    $netCost = $feed->costPerLead * $inboundStats['accepted'];
    $totalNetCost += $netCost;

    $rows[] = [
        $feed->name,
        $feed->idFeedIn,
        $feed->label,
        $feed->description,
        $inboundStats['accepted'],
        $feed->costPerLead,
        $netCost,
    ];
}

$rows[] = [
    'NET TOTAL',
    '',
    '',
    '',
    $totalInboundAccepted,
    $totalInboundAccepted > 0 ? $totalNetCost / $totalInboundAccepted : 0,
    $totalNetCost,
];

$spreadsheet->getActiveSheet()->fromArray($rows, null, 'A3');

$totalRows = count($rows);
if ($totalRows) {
    $spreadsheet->getActiveSheet()
        ->getStyle('E3:E' . ($totalRows + 2))
        ->getNumberFormat()
        ->setFormatCode('#,##0');

    $spreadsheet->getActiveSheet()
        ->getStyle('F3:G' . ($totalRows + 2))
        ->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
}

// Bold the last row
$spreadsheet->getActiveSheet()->getStyle('A' . ($totalRows + 2) . ':' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . ($totalRows + 2))->getFont()->setBold(true);

try {
    $spreadsheet->getActiveSheet()->mergeCells('A1:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . '1');
    $spreadsheet->getActiveSheet()->getStyle('A1')->getFont()->setSize(20)->setBold(true);
    $spreadsheet->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Add header colors and formatting
    $spreadsheet->getActiveSheet()->getStyle('A2:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . '2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0095FF');
    $spreadsheet->getActiveSheet()->getStyle('A2:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . '2')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
    $spreadsheet->getActiveSheet()->getStyle('A2:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . '2')->getFont()->setBold(true);

    // Add header filters
    $spreadsheet->getActiveSheet()->setAutoFilter('A2:' . Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2))) . '2');

    // Auto size columns
    for ($col = 1; $col <= Coordinate::columnIndexFromString($spreadsheet->getActiveSheet()->getHighestDataColumn(2)); ++$col) {
        $spreadsheet->getActiveSheet()->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    // Freeze top row
    $spreadsheet->getActiveSheet()->freezePane('A3');

    // Reset selected cell
    $spreadsheet->getActiveSheet()->setSelectedCell('A3');

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