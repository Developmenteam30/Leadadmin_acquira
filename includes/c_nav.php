<?php
require_once( INCLUDES . 'session.php' );

if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {

	$nav = array(
		array( 'name' => 'Dashboard', 'url' => '/leadadmin/dashboard.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Companies', 'url' => '/leadadmin/companies.php?status=active', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Incoming Feeds', 'url' => '/leadadmin/mgr_feedinc.php?status=active', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Outgoing Feeds', 'url' => '/leadadmin/mgr_feedout.php?status=active', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Suppressions', 'url' => '/leadadmin/mgr_suppress.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Jobs', 'url' => '/leadadmin/mgr_job.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
		array( 'name' => 'Reports', 'level' => LEADS_SESSION_LEVEL_STAFF, 'menu' => array(
			array( 'name' => 'Mapping Report', 'url' => '/leadadmin/reports-mapping.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'separator', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => '*List Owner - Email', 'url' => '/leadadmin/list-owner.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => 'Offline', 'url' => '/leadadmin/offline.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'separator', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'Publisher', 'url' => '/leadadmin/ledger.php?type=0', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'Advertiser', 'url' => '/leadadmin/ledger.php?type=1', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'separator', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'Commissions Paid', 'url' => '/leadadmin/commissions.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => '*Income Ledger', 'url' => '/leadadmin/income.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => '*Payment Ledger', 'url' => '/leadadmin/payments.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => '*Profit & Loss Report', 'url' => '/leadadmin/profit-loss.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => 'separator', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => '*Client Reports', 'url' => '/leadadmin/client_reports.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
		) ),
		array( 'name' => 'Admin', 'level' => LEADS_SESSION_LEVEL_STAFF, 'menu' => array(
			array( 'name' => 'URL Search', 'url' => '/leadadmin/url-search.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'Email Search', 'url' => '/leadadmin/email-search.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'Vertical Management', 'url' => '/leadadmin/verticals.php', 'level' => LEADS_SESSION_LEVEL_STAFF ),
			array( 'name' => 'separator', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => '*Audit Log', 'url' => '/leadadmin/audit-log.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
			array( 'name' => '*User Management', 'url' => '/leadadmin/mgr_users.php', 'level' => LEADS_SESSION_LEVEL_ADMIN ),
		) ),
	);

} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ) ) {

	$nav = array(
		array( 'name' => 'Dashboard', 'url' => '/leadadmin/dashboard.php', 'level' => LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ),
		array( 'name' => 'Incoming Feeds', 'url' => '/leadadmin/mgr_feedinc.php', 'level' => LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ),
		array( 'name' => 'Outgoing Feeds', 'url' => '/leadadmin/mgr_feedout.php', 'level' => LEADS_SESSION_LEVEL_CLIENT_DASHBOARD ),
	);

} else if( LeadsSession::isValid( LEADS_SESSION_LEVEL_CLIENT_REPORTS ) ) {

	$nav = array(
		array( 'name' => 'Reports', 'url' => '/leadadmin/client_reports.php', 'level' => LEADS_SESSION_LEVEL_CLIENT_REPORTS ),
	);

}


?>
<nav class="navbar navbar-default navbar-custom">
	<div class="container-fluid">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#primary-navbar" aria-expanded="false">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="dashboard.php"><img alt="Company logo" height="20" src="images/Q-isolated.jpg" width="20" /></a>
		</div>

		<div class="collapse navbar-collapse" id="primary-navbar">
			<ul class="nav navbar-nav">
<?php
	foreach( $nav as $item ) {

		if( !LeadsSession::isValid( $item['level'] ) ) {
			continue;
		}

		if( isset( $item['menu'] ) && is_array( $item['menu'] ) ) {

			$active = false;
			foreach( $item['menu'] as $sub_item ) {
				if( isset( $_SERVER["DOCUMENT_URI"], $sub_item['url'] ) &&  parse_url( $sub_item['url'], PHP_URL_PATH ) === $_SERVER["DOCUMENT_URI"] ) {
					$active = true;
				}
			}

			printf( '<li class="dropdown%s">' . PHP_EOL,
				$active ? ' active' : ''
			);
			printf( '<a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">%s <span class="caret"></span></a>',
				$item['name']
			);
			print '<ul class="dropdown-menu">' . PHP_EOL;
			foreach( $item['menu'] as $sub_item ) {
				if( !LeadsSession::isValid( $sub_item['level'] ) ) {
					continue;
				}

				if( 'separator' === $sub_item['name'] ) {
					print '<li role="separator" class="divider"></li>' . PHP_EOL;
				} else {
					printf( '<li><a href="%s">%s</a></li>' . PHP_EOL,
						$sub_item['url'],
						$sub_item['name']
					);
				}
			}
			print '</ul>' . PHP_EOL;
			print '</li>' . PHP_EOL;

		} else {

			printf( '<li%s><a href="%s">%s</a></li>' . PHP_EOL,
				isset( $_SERVER["DOCUMENT_URI"] ) && parse_url( $item['url'], PHP_URL_PATH ) === $_SERVER["DOCUMENT_URI"] ? ' class="active"' : '',
				$item['url'],
				$item['name']
			);

		}
	}
?>
			</ul>
			<ul class="nav navbar-nav navbar-right">
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>
				<li><a href="#" class="nonLink" onclick="display('errorList', {}, true);">Errors: <span id="errorCount"></span></a></li>
<?php } ?>
				<li><a href="logout.php">Log Out</a></li>
			</ul>
		</div><!-- .navbar-collapse -->
	</div><!-- .container-fluid -->
</nav>

<div id="errorList" style="display:none; font-size: .8em;"></div>
