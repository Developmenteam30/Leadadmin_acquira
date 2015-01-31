<?php
require_once( INCLUDES . 'session.php' );
?>
<div class="headerRow">
	<div class="logoutContainer fr">
		<a class="navButtonLogout navLast" href="logout.php"><img class="navIcon" height="11" width="11" src="images/icon_logout_1.gif" />Log Out</a>
		<div class="clr"></div>
	</div>
	<div class="logoContainer navLogo fl"><img alt="Logo" id="logo" src="images/logo.png" /></div>
	<div class="navContainer fl">
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>
		<a class="navButton" href="dashboard.php"><img class="navIcon" height="12" width="12" src="images/icon_dashboard_1.gif" />Dashboard</a>
		<a class="navButton" href="mgr_companies.php"><img class="navIcon" height="13" width="13" src="images/icon_companies_1.gif" />Companies</a>
		<a class="navButton" href="mgr_listcodes.php"><img class="navIcon" height="12" width="12" src="images/icon_listcodes.png" />Listcodes</a>
		<a class="navButton" href="mgr_feedinc.php"><img class="navIcon" height="10" width="10" src="images/icon_incoming_1.gif" />Incoming Feeds</a>
		<a class="navButton" href="mgr_feedout.php"><img class="navIcon" height="10" width="10" src="images/icon_outgoing_1.gif" />Outgoing Feeds</a>
		<a class="navButton" href="mgr_suppress.php"><img class="navIcon" height="13" width="13" src="images/icon_suppressions_1.gif" />Suppressions</a>
		<a class="navButton" href="mgr_reports.php"><img class="navIcon" height="12" width="12" src="images/icon_reports.png" />Reports</a>
		<a class="navButton navLast" href="#" class="nonLink" onclick="display('errorList', {}, true);"><img class="navIcon" height="12" width="13" src="images/icon_warning_1.gif" />Errors Today: <span id="errorCount"></span></a>
<?php } else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ) ) { ?>
		<a class="navButton" href="dashboard.php"><img class="navIcon" height="12" width="12" src="images/icon_dashboard_1.gif" />Dashboard</a>
		<a class="navButton" href="mgr_feedinc.php"><img class="navIcon" height="10" width="10" src="images/icon_incoming_1.gif" />Incoming Feeds</a>
		<a class="navButton" href="mgr_feedout.php"><img class="navIcon" height="10" width="10" src="images/icon_outgoing_1.gif" />Outgoing Feeds</a>
<?php } else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_REPORTS ) ) { ?>
		<a class="navButton" href="client_reports.php"><img class="navIcon" height="12" width="12" src="images/icon_reports.png" />Reports</a>
<?php } ?>
		<div class="clr"></div>
	</div>
	<div class="clr"></div>
</div>
<div id="errorList" style="display:none; font-size: .8em;"></div>
