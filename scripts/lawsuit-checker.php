<?php

die('UNUSED');

ini_set('memory_limit', '3G');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require('../includes/c_config.php');
require_once(INCLUDES . "leads.php");
include(INCLUDES . "vendor/autoload.php");

$leads = Leads::getInstance();

$outboundFeeds = array_map(function ($blah) {
    return $blah->idFeedOut;
}, $leads->getOutboundFeedsByCompany(246));

try {
    $outputRows = [];
    $outputSheet = new Spreadsheet();
    $outputSheet->setActiveSheetIndex(0);
    $outputSheet->getActiveSheet()
        ->setCellValue('A1', 'Phone')
        ->setCellValue('B1', 'Feed ID')
        ->setCellValue('C1', 'Feed Label')
        ->setCellValue('D1', 'Timestamp')
        ->setCellValue('E1', 'Result');

    $file = "/tmp/HKREV_0011838.xlsx";
    $reader = IOFactory::createReaderForFile($file);
    $spreadsheet = $reader->load($file);

    $worksheet = $spreadsheet->getActiveSheet();
    foreach ($worksheet->getRowIterator() AS $row) {
        $phone = trim(preg_replace('/[^0-9]/', '',
            $worksheet->getCellByColumnAndRow(4, $row->getRowIndex())->getCalculatedValue() ?? ''));
        if (!empty($phone)) {
            print "Checking {$phone}\n";
            $records = $leads->inboundRecordSearch(null, $phone, null, null);
            print "\tInbound hits: " . sizeOf($records) . PHP_EOL;
            if (!empty($records)) {
                foreach ($records as $record) {
                    $outboundRecords = $leads->outboundRecordSearchById($record->idRecord);
                    //print "\tOutbound hits: " . sizeOf($outboundRecords) . PHP_EOL;
                    foreach ($outboundRecords as $outboundRecord) {
                        if (in_array($outboundRecord->idFeedOut, $outboundFeeds)) {
                            print "\t\tYodel: [{$outboundRecord->label}] [{$outboundRecord->timestampConverted}] [{$outboundRecord->result}]" . PHP_EOL;
                            $outputRows[] = [
                                $phone,
                                $outboundRecord->idFeedOut,
                                $outboundRecord->label,
                                $outboundRecord->timestampConverted,
                                $outboundRecord->result,
                            ];
                        }
                    }
                }
            }
        }
    }

    $outputSheet->getActiveSheet()->fromArray($outputRows, null, 'A2');
    $writer = new Xlsx($outputSheet);
    $writer->save('/tmp/yodel-results.xlsx');

} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    print "EXCEPTION: {$e->getMessage()}\n";
} catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
    print "EXCEPTION: {$e->getMessage()}\n";
}
