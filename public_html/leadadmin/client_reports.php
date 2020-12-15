<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( [LEADS_SESSION_LEVEL_CLIENT_REPORTS, LEADS_SESSION_LEVEL_ADMIN] );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['d'] ) ) {

	if( empty( $_REQUEST['options']['report_date'] ) || strlen( $_REQUEST['options']['report_date'] ) != 6 ) $reportDate = null;
	else $reportDate = $_REQUEST['options']['report_date'];

	if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
		if( empty( $_REQUEST['options']['idCompany'] ) ) $idCompany = null;
		else $idCompany = $_REQUEST['options']['idCompany'];
	} else {
		$idCompany = LeadsSession::getCompanyId();
		if( empty( $idCompany ) ) {
			$idCompany = -9999;
		}
	}

	if( empty( $_REQUEST['options']['idFeedIn'] ) ) $idFeedIn = null;
	else $idFeedIn = $_REQUEST['options']['idFeedIn'];

	if( empty( $_REQUEST['options']['url'] ) ) $urlFilter = null;
	else $urlFilter = $_REQUEST['options']['url'];

	switch( $_REQUEST['d'] ) {

		case 'dialog_revenue_listowners':
			$gross = $partner = 0;
			$m_gross = $m_partner = 0;
			$last_month = '';
			$mappings = $leads->getRevenueInboundClientMonthMappings( $idCompany );
			if( $mappings ) {
				$colspan = 1;
				print "<table id=\"revenue_report\" class=\"table table-bordered table-condensed table-striped revenue-report\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				if( empty( $idCompany ) ) {
					print "\t\t<th>Company</th>\n";
					$colspan++;
				}
				print "\t\t<th>Month</th>\n";
				print "\t\t<th>Gross Revenue</th>\n";
				print "\t\t<th>Partner Revenue</th>\n";
				print "\t\t<th>Invoice Paid</th>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				$cnt = 0;
				foreach( $mappings as $mapping ) {
					if( empty( $last_month ) ) {
						$last_month = $mapping['month'];
					}
					if( $mapping['month'] !== $last_month ) {
						if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
							print "\t<tr class=\"bgGray subtotal\">\n";
							printf( "\t\t<td colspan=\"" . $colspan . "\">MONTHLY TOTAL - " . date( 'Y F', strtotime( $last_month . "01" ) ) . "</td>\n" );
							printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $m_gross, 2 ) );
							printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $m_partner, 2 ) );
							print "\t\t<td></td>\n";
							print "\t</tr>\n";
						}
						$last_month = $mapping['month'];
						$m_gross = $m_partner = 0;
					}
					printf( "\t<tr class=\"bgGray%s\">\n", $cnt % 2 ? ' reverse' : '' );
					if( empty( $idCompany ) ) {
						printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['inName'] ) );
					}
					printf( "\t\t<td><a class=\"nonLink\" onclick=\"display('dialog_revenue_listowners_detail', { 'sub': '%d', 'report_date': '%s', 'idCompany': '%s' });\">%s</a></td>\n", $cnt, $mapping['month'], $mapping['idCompany'], date( 'Y F', strtotime( $mapping['month'] . "01" ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'], 2 ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'] * 0.5, 2 ) ) );
					if( $leads->getInvoiceStatus( $mapping['month'], $mapping['idCompany'] ) ) {
						print "\t\t<td class=\"greencheck\"><img alt=\"Green checkmark\" height=\"13\" src=\"images/green_check.png\" width=\"12\" /></td>\n";
					} else {
						print "\t\t<td></td>\n";
					}
					print "\t</tr>\n";
					$gross += floatval( $mapping['revenue'] );
					$m_gross += floatval( $mapping['revenue'] );
					$partner += floatval( $mapping['partner'] );
					$m_partner += floatval( $mapping['partner'] );

					print "\t<tr>\n";
					printf( "\t\t<td colspan=\"5\" class=\"hidden-custom\" id=\"dialog_revenue_listowners_detail_%d\">&nbsp;</td>\n", $cnt );
					print "\t</tr>\n";

					$cnt++;
				}
				if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
					print "\t<tr class=\"bgGray subtotal\">\n";
					printf( "\t\t<td colspan=\"" . $colspan . "\">MONTHLY TOTAL - " . date( 'Y F', strtotime( $last_month . "01" ) ) . "</td>\n" );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $m_gross, 2 ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $m_partner, 2 ) );
					print "\t\t<td></td>\n";
					print "\t</tr>\n";
				}

				print "\t<tr class=\"bgGray subtotal\">\n";
				printf( "\t\t<td colspan=\"" . $colspan . "\">GRAND TOTAL REVENUE</td>\n" );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $gross, 2 ) );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $partner, 2 ) );
				print "\t\t<td></td>\n";
				print "\t</tr>\n";
				print "\t</tbody>\n";
				print "</table>\n";
			}

			break;


		case 'dialog_revenue_listowners_detail':
?>
<div class="pull-right">
    <a class="nonLink" onclick="closeContent('dialog_revenue_listowners_detail', { 'sub': '<?php echo intval( $_REQUEST['options']['sub'] ); ?>' } );">Close [X]</a>
</div>
<!--<h2>Report Date: <?php echo date( 'Y F', strtotime( $reportDate . "01" ) ); ?></h2>-->

<input type="hidden" id="dialog_revenue_listowners_id" value="<?php echo htmlspecialchars( $_REQUEST['options']['sub'] ); ?>" />
<input type="hidden" id="dialog_revenue_listowners_date" value="<?php echo htmlspecialchars( $reportDate ); ?>" />
<input type="hidden" id="dialog_revenue_listowners_company" value="<?php echo htmlspecialchars( $idCompany ); ?>" />

<?php if( !empty( $idCompany ) ) {  ?>
<select name="idFeedIn" id="dialog_revenue_listowners_feed" class="dialog_revenue_listowners_change">
<?php 
	printf( '<option value=""%s>SHOW ALL FEEDS</option>',
					( empty( $idFeedIn ) ? ' selected="selected"' : '' ) );
	$feeds = $leads->getRevenueInboundFeeds( $idCompany );
	if( $feeds ) {
		foreach( $feeds as $feed ) {
			printf(' <option value="%s"%s>%s</option>',
					$feed['idFeedIn'], ( $idFeedIn == $feed['idFeedIn'] ? ' selected="selected"' : '' ), $feed['idFeedIn'] . ': ' . htmlspecialchars( $feed['inDescription'] ) );
		}
	}
?>
</select>
<?php } else { ?>
<input type="hidden" id="dialog_revenue_listowners_feed" value="" />
<?php } ?>
<?php if( !empty( $idFeedIn ) ) {  ?>
<select name="url" id="dialog_revenue_listowners_url" class="dialog_revenue_listowners_change">
<?php 
	printf( '<option value=""%s>SHOW ALL URLS</option>',
					( empty( $url ) ? ' selected="selected"' : '' ) );
	$urls = $leads->getRevenueInboundURLs( $idFeedIn );
	if( $urls ) {
		foreach( $urls as $url ) {
			printf(' <option value="%s"%s>%s</option>',
					$url['url'], ( $urlFilter == $url['url'] ? ' selected="selected"' : '' ), $url['url'] );
		}
	}
?>
</select>
<?php } else { ?>
<input type="hidden" id="dialog_revenue_listowners_url" value="" />
<?php } ?>
</p>

<?php
			$gross = $partner = 0;
			$mappings = $leads->getRevenueInboundClientMappings( $reportDate, $idCompany, $idFeedIn, $urlFilter );
			if( $mappings ) {
				print "<table id=\"revenue_report\" class=\"table table-bordered table-condensed table-striped revenue-report\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<th>Feed Name</th>\n";
				print "\t\t<th>URL</th>\n";
				print "\t\t<th>First Seen</th>\n";
				print "\t\t<th>Last Seen</th>\n";
				print "\t\t<th>Gross Revenue</th>\n";
				print "\t\t<th>Partner Revenue</th>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedIn'] . ': ' . $mapping['inDescription'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", htmlspecialchars( $mapping['firstDate'] ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", htmlspecialchars( $mapping['lastDate'] ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'], 2 ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'] * 0.5, 2 ) ) );
					print "\t</tr>\n";
					$gross += floatval( $mapping['revenue'] );
					$partner += floatval( $mapping['partner'] );
				}
				print "\t<tr class=\"bgGray subtotal\">\n";
				printf( "\t\t<td colspan=\"4\">TOTAL REVENUE</td>\n" );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $gross, 2 ) );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $gross * 0.5, 2 ) );
				print "\t</tr>\n";
				print "\t</tbody>\n";
				print "</table>\n";
			}
?>
<script type="text/javascript">
$(document).ready(function(){
	$('.dialog_revenue_listowners_change').change(function() {
		var date = $('#dialog_revenue_listowners_date').val();
		var company = $('#dialog_revenue_listowners_company').val();
		var sub = $('#dialog_revenue_listowners_id').val();
		var feed = "";
		var url = "";

		if( "dialog_revenue_listowners_url" == $(this).attr("id") || "dialog_revenue_listowners_date" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_listowners_feed').val();
			url = $('#dialog_revenue_listowners_url').val();
		} else if( "dialog_revenue_listowners_feed" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_listowners_feed').val();
		}

		display('dialog_revenue_listowners_detail', { 'sub': sub, 'report_date': date, 'idCompany': company, 'idFeedIn': feed, 'url': url });
	});
});
</script>
<?php
		break;

		case 'dialog_revenue_mailers':
			if( empty( $_REQUEST['options']['report_date'] ) || strlen( $_REQUEST['options']['report_date'] ) != 6 ) $reportDate = date('Ym');
			else $reportDate = $_REQUEST['options']['report_date'];

			if( empty( $_REQUEST['options']['idCompany'] ) ) $idCompany = null;
			else $idCompany = $_REQUEST['options']['idCompany'];

			if( empty( $_REQUEST['options']['idFeedOut'] ) ) $idFeedOut = null;
			else $idFeedOut = $_REQUEST['options']['idFeedOut'];

			if( empty( $_REQUEST['options']['url'] ) ) $urlFilter = null;
			else $urlFilter = $_REQUEST['options']['url'];

?>
<div class="aRight">
	<a class="nonLink" onclick="closeContent('dialog_revenue_mailers');">Close [X]</a>
</div>
<p><strong>Report Date:</strong>
<select name="report_date" id="dialog_revenue_mailers_date" class="dialog_revenue_mailers_change">
<?php 
	for($y = date('Y'); $y >= 2012; $y--) {
		for($m = 12; $m > 0; $m--) {
			$format_month = str_pad( $m, 2, '0', STR_PAD_LEFT );
			printf(' <option value="%s"%s>%s</option>',
					$y . $format_month, ( $y == substr( $reportDate, 0, 4) && $format_month == substr( $reportDate, 4, 2 ) ) ? ' selected="selected"' : '', $y . '-' . $format_month );
		}
	}
?>
</select>
<select name="idCompany" id="dialog_revenue_mailers_company" class="dialog_revenue_mailers_change">
<?php 
	printf( '<option value=""%s>SHOW ALL COMPANIES</option>',
					( empty( $idCompany ) ? ' selected="selected"' : '' ) );
	$companies = $leads->getRevenueOutboundCompanies();
	if( $companies ) {
		foreach( $companies as $company ) {
			printf(' <option value="%s"%s>%s</option>',
					$company['idCompany'], ( $idCompany == $company['idCompany'] ? ' selected="selected"' : '' ), $company['name'] );
		}
	}
?>
</select>
<?php if( !empty( $idCompany ) ) {  ?>
<select name="idFeedOut" id="dialog_revenue_mailers_feed" class="dialog_revenue_mailers_change">
<?php 
	printf( '<option value=""%s>SHOW ALL FEEDS</option>',
					( empty( $idFeedOut ) ? ' selected="selected"' : '' ) );
	$feeds = $leads->getRevenueOutboundFeeds( $idCompany );
	if( $feeds ) {
		foreach( $feeds as $feed ) {
			printf(' <option value="%s"%s>%s</option>',
					$feed['idFeedOut'], ( $idFeedOut == $feed['idFeedOut'] ? ' selected="selected"' : '' ), $feed['idFeedOut'] . ': ' . htmlspecialchars( $feed['inDescription'] ) );
		}
	}
?>
</select>
<?php } else { ?>
<input type="hidden" id="dialog_revenue_mailers_feed" value="" />
<?php } ?>
<?php if( !empty( $idFeedOut ) ) {  ?>
<select name="url" id="dialog_revenue_mailers_url" class="dialog_revenue_mailers_change">
<?php 
	printf( '<option value=""%s>SHOW ALL URLS</option>',
					( empty( $url ) ? ' selected="selected"' : '' ) );
	$urls = $leads->getRevenueOutboundURLs( $idFeedOut );
	if( $urls ) {
		foreach( $urls as $url ) {
			printf(' <option value="%s"%s>%s</option>',
					$url['url'], ( $urlFilter == $url['url'] ? ' selected="selected"' : '' ), $url['url'] );
		}
	}
?>
</select>
<?php } else { ?>
<input type="hidden" id="dialog_revenue_mailers_url" value="" />
<?php } ?>
</p>

<?php
			$mappings = $leads->getRevenueOutboundMappings( $reportDate, $idCompany, $idFeedOut, $urlFilter );
			if( $mappings ) {
				print "<table id=\"revenue_report\" class=\"table table-bordered table-condensed table-striped\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<th>Outgoing Company</th>\n";
				print "\t\t<th>Outgoing Feed</th>\n";
				print "\t\t<th>Outgoing URL</th>\n";
				print "\t\t<th>First Lead</th>\n";
				print "\t\t<th>Last Lead</th>\n";
				print "\t\t<th>Amount</th>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['outName'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedOut'] . ': ' . $mapping['outDescription'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['firstDate'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['lastDate'] ) );
					printf( "\t\t<td class=\"revenue\"><input type=\"number\" min=\"0\" max=\"9999\" step=\"0.01\" name=\"%s\" value=\"%s\" /></td>\n", htmlspecialchars( base64_encode( $reportDate . '|0|' . $mapping['idFeedOut'] . '|' . $mapping['url'] ) ), ( empty( $mapping['revenue'] ) ? '' : htmlspecialchars( $mapping['revenue'] ) ) );
					print "\t</tr>\n";

				}
				print "\t</tbody>\n";
				print "</table>\n";
			}

?>
<script type="text/javascript">
$(document).ready(function(){
	$('.dialog_revenue_mailers_change').change(function() {
		var date = $('#dialog_revenue_mailers_date').val();
		var company = $('#dialog_revenue_mailers_company').val();
		var feed = "";
		var url = "";

		if( "dialog_revenue_mailers_url" == $(this).attr("id") || "dialog_revenue_mailers_date" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_mailers_feed').val();
			url = $('#dialog_revenue_mailers_url').val();
		} else if( "dialog_revenue_mailers_feed" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_mailers_feed').val();
		}

		display('dialog_revenue_mailers', { 'report_date': date, 'idCompany': company, 'idFeedOut': feed, 'url': url });
	});
});
</script>

<?php
		break;

	}
	exit;
}

$title = 'Reports';
include(INCLUDES."c_header.php");
?>
<body>
<script type="text/javascript">
$(document).ready(function(){
	display('dialog_revenue_listowners');
});
</script>

<div class="container-XXfluid client">
	<?php include(INCLUDES.'c_nav_client.php'); ?>
	<div class="content">
		<p class="payment">Please send all invoices to <a href="mailto:<?php echo PAYMENT_EMAIL;?>"><?php echo PAYMENT_EMAIL; ?></a> to ensure prompt payment.</p>
		<div class="hidden-custom" id="dialog_revenue_listowners"></div>
		<div class="hidden-custom" id="dialog_revenue_mailers"></div>
	</div>
	<div class="footer">
		<p>Copyright &copy; <?php echo date( 'Y' ); ?> <?php echo CONFIG_COMPANY_NAME; ?>, Inc.  All rights reserved.</p>
	</div>
</div>
</body>
</html>
