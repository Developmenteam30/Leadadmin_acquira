<?php //livefeed.php
$feedLabel = basename(__DIR__);
include("../../includes/c_config.php");
$mysqlErrorSource = 'Feed '.$feedLabel;
include(INCLUDES."processLead.php");
?>
