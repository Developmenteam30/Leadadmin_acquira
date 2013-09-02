<?php //livefeed.php
$feedLabel = basename(__DIR__);
include("../../c_config.php");
$mysqlErrorSource = 'Feed '.$feedLabel;
include(LIVE_ROOT."processLead.php");
?>