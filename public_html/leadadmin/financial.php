<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$v = !empty( $_REQUEST['v'] ) ? $_REQUEST['v'] : 'p';

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['d'])){
	switch($_REQUEST['d']){
		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;
	}
	exit;
}

$title = 'Profit & Loss Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Financial Reports</h2>

<p><a href="/leadadmin/income.php">*Income Ledger</a></p>

<p><a href="/leadadmin/payments.php">*Payment Ledger</a></p>

<p><a href="/leadadmin/profit-loss.php">*Profit &amp; Loss Report</a></p>

</div>

</body>
</html>
