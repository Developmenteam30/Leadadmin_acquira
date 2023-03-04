<?php

use setasign\Fpdi\Fpdi;

include("../../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
require_once(INCLUDES . 'f_site.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

require_once(INCLUDES . 'leads.php');
require_once(INCLUDES . 'fpdf185/fpdf.php');
require_once(INCLUDES . 'FPDI-2.2.0/src/autoload.php');
require_once(INCLUDES . 'FPDI_PDF-Parser-2.0.4/src/autoload.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

if (empty($_REQUEST['orderId'])) {
    die('No order ID specified.');
}

$order = $leads->getInsertionOrder($_REQUEST['orderId']);
if (empty($order)) {
    die('Cannot find order ID ' . $_REQUEST['orderId']);
}

function setLightOnDark($pdf)
{
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFillColor(BRANDING_RGB1, BRANDING_RGB2, BRANDING_RGB3);
}

function setDarkOnLight($pdf)
{
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
}

function getMultiCellHeight($text, $w, $h)
{
    $pdfTest = new FPDF('P', 'mm', 'Letter');
    $pdfTest->AddPage();
    $pdfTest->SetFont('Arial', '', 10);
    $pdfTest->SetXY(0, 0);
    $pdfTest->MultiCell($w, $h, $text, 1, 'C');
    $height = $pdfTest->GetY();
    $pdfTest->Close();

    return $height;
}

function generateCellBlocks($pdf, $fields, $cellHeight, $cellWidth)
{
    $y = $pdf->GetY();
    $maxHeight = 0;
    foreach ($fields as $field) {
        $h = getMultiCellHeight($field, $cellWidth, $cellHeight);
        if ($h > $maxHeight) {
            $maxHeight = $h;
        }
    }

    $cnt = 1;
    foreach ($fields as $field) {
        $h = getMultiCellHeight($field, $cellWidth, $cellHeight);
        if ($h < $maxHeight) {
            $field .= str_repeat(PHP_EOL, ($maxHeight / $cellHeight) - ($h / $cellHeight) + 1);
        }

        $pdf->MultiCell($cellWidth, $cellHeight, $field, 1, 'C');
        $pdf->SetXY(10 + $cellWidth * $cnt++, $y);
    }

    $pdf->SetXY(10, $y + $maxHeight + 1);

}

// Height: 279.4 (280)
// Width: 215.9 (216)
// Side Margins: 10
// Top and Bottom Margins: 12

define('H1_SIZE', 36);
define('H2_SIZE', 24);
define('H3_SIZE', 18);
define('H4_SIZE', 16);
define('LARGER_TEXT_SIZE', 18);
define('REGULAR_TEXT_SIZE', 15);
define('SMALLER_TEXT_SIZE', 12);

$pdf = new Fpdi('P', 'mm', 'Letter');
$pdf->SetAutoPageBreak(true);

$pdf->AddPage();

$pdf->Image(SITE_ROOT . '/public_html/leadadmin/images/logo.png', 83, null, 50);

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, 'Lead Data', 0, 1, 'C');
$pdf->Cell(0, 7, 'INSERTION ORDER', 0, 1, 'C');

$pdf->Ln();

$pdf->SetFont('Arial', '', 11);

// ROW
setLightOnDark($pdf);
$pdf->Cell(98, 7, 'PUBLISHER (SELLER)', 1, 0, 'L', 'true');
$pdf->Cell(98, 7, 'ADVERTISER (BUYER)', 1, 1, 'L', 'true');

if ('publisher' === $order->orderType) {
    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, COMPANY_LEGAL_NAME, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->companyName, 1, 1, 'L');

    $pdf->Cell(98, 7, COMPANY_ADDRESS_1, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->address, 1, 1, 'L');

    $pdf->Cell(98, 7, COMPANY_ADDRESS_2, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->city . ', ' . $order->state . ' ' . $order->zipcode, 1, 1, 'L');

    // ROW
    setLightOnDark($pdf);
    $pdf->Cell(98, 7, 'Contact', 1, 0, 'L', 'true');
    $pdf->Cell(98, 7, 'Contact', 1, 1, 'L', 'true');

    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, $order->fullName, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->main_name, 1, 1, 'L');

    $pdf->Cell(98, 7, $order->email, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->main_email, 1, 1, 'L');

    $pdf->Cell(98, 7, COMPANY_PHONE, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->main_phone, 1, 1, 'L');
} else {
    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, $order->companyName, 1, 0, 'L');
    $pdf->Cell(98, 7, COMPANY_LEGAL_NAME, 1, 1, 'L');

    $pdf->Cell(98, 7, $order->address, 1, 0, 'L');
    $pdf->Cell(98, 7, COMPANY_ADDRESS_1, 1, 1, 'L');

    $pdf->Cell(98, 7, $order->city . ', ' . $order->state . ' ' . $order->zipcode, 1, 0, 'L');
    $pdf->Cell(98, 7, COMPANY_ADDRESS_2, 1, 1, 'L');

    // ROW
    setLightOnDark($pdf);
    $pdf->Cell(98, 7, 'Contact', 1, 0, 'L', 'true');
    $pdf->Cell(98, 7, 'Contact', 1, 1, 'L', 'true');

    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, $order->main_name, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->fullName, 1, 1, 'L');

    $pdf->Cell(98, 7, $order->main_email, 1, 0, 'L');
    $pdf->Cell(98, 7, $order->email, 1, 1, 'L');

    $pdf->Cell(98, 7, $order->main_phone, 1, 0, 'L');
    $pdf->Cell(98, 7, COMPANY_PHONE, 1, 1, 'L');
}

// ROW
$pdf->Ln();
setDarkOnLight($pdf);

if ('Q' === COMPANY_INITIALS) {
    $pdf->SetFont('Arial', 'U');
    $pdf->Cell(0, 7, 'By signing this Insertion Order, you agree to accept and be bound to all', 0, 1, 'C');
    $pdf->Cell(0, 7, 'the terms and conditions set forth in the Qatalyst Ping Post Purchase', 0, 1, 'C');
    $pdf->Cell(0, 7, 'Terms and Condition Agreement located at https://www.qatalystinc.com/ping-post-terms/', 0, 1, 'C');
    $pdf->SetFont('Arial', '');
}

// ROW
$pdf->Ln();
setLightOnDark($pdf);
$pdf->Cell(39.2, 7, 'Product Type', 1, 0, 'C', 'true');
$pdf->Cell(39.2, 7, 'Start Date', 1, 0, 'C', 'true');
$pdf->Cell(39.2, 7, 'End Date', 1, 0, 'C', 'true');
$pdf->Cell(39.2, 7, 'Payment Terms', 1, 0, 'C', 'true');
$pdf->Cell(39.2, 7, 'Reporting', 1, 1, 'C', 'true');

$pdf->SetFont('Arial', '', 10);
setDarkOnLight($pdf);
$fields = [
    $order->verticalName,
    $order->startDate,
    $order->endDate,
    ucfirst($order->paymentTerms),
    ucfirst($order->callReporting),
];
generateCellBlocks($pdf, $fields, 6, 39.2);

// ROW
$pdf->Ln();
setLightOnDark($pdf);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(32.6666, 7, 'Cost Per Lead', 1, 0, 'C', 'true');
$pdf->Cell(32.6666, 7, 'Delivery Method', 1, 0, 'C', 'true');
$pdf->Cell(32.6666, 7, 'Qty Ordered', 1, 0, 'C', 'true');
$pdf->Cell(32.6666, 7, 'DID', 1, 0, 'C', 'true');
$pdf->Cell(32.6666, 7, 'Call Hours', 1, 0, 'C', 'true');
$pdf->Cell(32.6666, 7, 'Delivery Days', 1, 1, 'C', 'true');

setDarkOnLight($pdf);
$pdf->SetFont('Arial', '', 10);

$fields = [
    $order->costPerLead . ' / ' . $order->costPerLeadUOM,
    $order->deliveryMethod,
    $order->qty,
    $order->did,
    $order->callHours,
    $order->deliveryDays,
];
generateCellBlocks($pdf, $fields, 6, 32.6666);

// ROW
$pdf->Ln();
setLightOnDark($pdf);
$pdf->Cell(0, 7, 'Other Requirements / State Omits / Qualifications / Notes', 1, 1, 'L', 'true');

setDarkOnLight($pdf);
$pdf->MultiCell(0, 7, $order->notes, 1, 'L');

$files = Display::findFilesRecurse(FILES_DIR . 'insertion-orders' . DIRECTORY_SEPARATOR . $order->orderId);

if (!empty($files)) {
    $pdf->Ln();
    setLightOnDark($pdf);
    $pdf->Cell(0, 7, 'File Attachments', 1, 1, 'L', 'true');

    foreach ($files as $file) {
        $pdf->Image($file, null, null, 196);
    }
}

//$pdf->SetAutoPageBreak( true, 100 );

// ROW
$pdf->Ln();
setDarkOnLight($pdf);
$pdf->SetFont('Arial', 'B');
$pdf->Cell(0, 7, 'IN WITNESS WHEREOF,', 0, 1, 'C');
$pdf->SetFont('Arial', '');
$pdf->Cell(0, 7, 'the parties hereto have caused this Agreement', 0, 1, 'C');
$pdf->Cell(0, 7, 'to be duly executed as of the date set forth below.', 0, 1, 'C');
$pdf->SetFont('Arial', '');

$pdf->Ln();

// ROW
setLightOnDark($pdf);
if ('publisher' === $order->orderType) {
    $pdf->Cell(98, 7, 'SELLER - ' . COMPANY_LEGAL_NAME, 1, 0, 'L', 'true');
    $pdf->Cell(98, 7, 'BUYER - ' . $order->companyName, 1, 1, 'L', 'true');

    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, 'Name: ' . $order->fullName, 1, 0, 'L');
    $pdf->Cell(98, 7, 'Name: ' . $order->main_name, 1, 1, 'L');
} else {
    $pdf->Cell(98, 7, 'SELLER - ' . $order->companyName, 1, 0, 'L', 'true');
    $pdf->Cell(98, 7, 'BUYER - ' . COMPANY_LEGAL_NAME, 1, 1, 'L', 'true');

    setDarkOnLight($pdf);
    $pdf->Cell(98, 7, 'Name: ' . $order->main_name, 1, 0, 'L');
    $pdf->Cell(98, 7, 'Name: ' . $order->fullName, 1, 1, 'L');
}

$pdf->Cell(98, 7, 'Title:', 1, 0, 'L');
$pdf->Cell(98, 7, 'Title:', 1, 1, 'L');

$pdf->Cell(98, 14, 'Signature:', 1, 0, 'L');
$pdf->Cell(98, 14, 'Signature:', 1, 1, 'L');

$pdf->Cell(98, 7, 'Date:', 1, 0, 'L');
$pdf->Cell(98, 7, 'Date:', 1, 1, 'L');


if (1 == $order->includeBankingInfo) {

    $pdf->AddPage();

    $pdf->Image(SITE_ROOT . '/public_html/leadadmin/images/logo.png', 83, null, 50);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'ACH/WIRE INSTRUCTIONS', 0, 1, 'C');

    $pdf->Ln();
    $pdf->SetFont('Arial', '', 11);

    if ('publisher' === $order->orderType) {

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'BANK', 1, 0, 'L', 'true');
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, BANK_NAME, 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'OFFICE', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, BANK_ADDRESS, 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'ROUTING NUMBER', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, BANK_ROUTING, 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'ACCOUNT NUMBER', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, BANK_ACCOUNT, 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'FOR BENEFIT OF', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, COMPANY_LEGAL_NAME, 1, 1, 'L');

    } else {

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'BANK', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, '', 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'OFFICE', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, '', 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'ROUTING NUMBER', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, '', 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'ACCOUNT NUMBER', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, '', 1, 1, 'L');

        setLightOnDark($pdf);
        $pdf->Cell(98, 7, 'FOR BENEFIT OF', 1, 0, 'L', true);
        setDarkOnLight($pdf);
        $pdf->Cell(98, 7, '', 1, 1, 'L');

    }

}

if (1 == $order->includeW9) {

    if ('publisher' === $order->orderType) {
        $file = SITE_ROOT . '/public_html/assets/pdf/W9-' . COMPANY_INITIALS . '.pdf';
    } else {
        $file = SITE_ROOT . '/public_html/assets/pdf/fw9.pdf';
    }

    if (file_exists($file)) {
        // get the page count
        $pageCount = $pdf->setSourceFile($file);
        // iterate through all pages
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // import a page
            $templateId = $pdf->importPage($pageNo);

            $pdf->AddPage();
            // use the imported page and adjust the page size
            $pdf->useTemplate($templateId, ['adjustPageSize' => true]);
        }
    }

}

$pdf->Output('D', sprintf('Insertion Order %d.pdf', $order->orderId));