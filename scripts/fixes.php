<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
require_once( INCLUDES . 'legacy.php' );
$legacy = new Legacy();

include(INCLUDES."c_header.php");
?>

<body>

<div class='mainContainer'>
<?php

$legacy->fixInboundStatsRejected();

?>
</div>

</body>
</html>
