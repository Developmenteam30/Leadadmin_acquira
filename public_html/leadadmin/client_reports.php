<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT );

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
			$mappings = $leads->getRevenueInboundClientMonthMappings( $idCompany );
			if( $mappings ) {
				$colspan = 1;
				print "<table id=\"revenue_report\" class=\"standard revenue-report\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				if( empty( $idCompany ) ) {
					print "\t\t<td>Company</td>\n";
					$colspan++;
				}
				print "\t\t<td>Month</td>\n";
				print "\t\t<td>Gross Revenue</td>\n";
				print "\t\t<td>Partner Revenue</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					if( empty( $idCompany ) ) {
						printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['inName'] ) );
					}
					printf( "\t\t<td><a href=\"#\" onclick=\"display('dialog_revenue_listowners_detail', { 'report_date': '%s', 'idCompany': '%s' });\">%s</a></td>\n", $mapping['month'], $mapping['idCompany'], date( 'Y F', strtotime( $mapping['month'] . "01" ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'], 2 ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'] * 0.5, 2 ) ) );
					print "\t</tr>\n";
					$gross += floatval( $mapping['revenue'] );
					$partner += floatval( $mapping['partner'] );
				}
				print "\t<tr class=\"bgGray subtotal\">\n";
				printf( "\t\t<td colspan=\"" . $colspan . "\">TOTAL REVENUE</td>\n" );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $gross, 2 ) );
				printf( "\t\t<td class=\"revenue\">%s</td>\n", '$' . number_format( $partner, 2 ) );
				print "\t</tr>\n";
				print "\t</tbody>\n";
				print "</table>\n";
			}

			break;


		case 'dialog_revenue_listowners_detail':
?>
<div class="fr">
    <a href="#" class="nonLink" onclick="closeContent('dialog_revenue_listowners_detail');">Close [X]</a>
</div>
<h2>Report Date: <?php echo date( 'Y F', strtotime( $reportDate . "01" ) ); ?></h2>

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
				print "<table id=\"revenue_report\" class=\"standard revenue-report\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Feed Name</td>\n";
				print "\t\t<td>URL</td>\n";
				print "\t\t<td>Gross Revenue</td>\n";
				print "\t\t<td>Partner Revenue</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedIn'] . ': ' . $mapping['inDescription'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'], 2 ) ) );
					printf( "\t\t<td class=\"revenue\">%s</td>\n", ( empty( $mapping['revenue'] ) ? '' : '$' . number_format( $mapping['revenue'] * 0.5, 2 ) ) );
					print "\t</tr>\n";
					$gross += floatval( $mapping['revenue'] );
					$partner += floatval( $mapping['partner'] );
				}
				print "\t<tr class=\"bgGray subtotal\">\n";
				printf( "\t\t<td colspan=\"2\">TOTAL REVENUE</td>\n" );
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
		var feed = "";
		var url = "";

		if( "dialog_revenue_listowners_url" == $(this).attr("id") || "dialog_revenue_listowners_date" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_listowners_feed').val();
			url = $('#dialog_revenue_listowners_url').val();
		} else if( "dialog_revenue_listowners_feed" == $(this).attr("id") ) {
			feed = $('#dialog_revenue_listowners_feed').val();
		}

		display('dialog_revenue_listowners_detail', { 'report_date': date, 'idCompany': company, 'idFeedIn': feed, 'url': url });
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
	<a href="#" class="nonLink" onclick="closeContent('dialog_revenue_mailers');">Close [X]</a>
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
				print "<table id=\"revenue_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Outgoing Company</td>\n";
				print "\t\t<td>Outgoing Feed</td>\n";
				print "\t\t<td>Outgoing URL</td>\n";
				print "\t\t<td>First Lead</td>\n";
				print "\t\t<td>Last Lead</td>\n";
				print "\t\t<td>Amount</td>\n";
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
<div class="mainContainer client">
	<?php include(INCLUDES.'c_nav_client.php'); ?>
	<div class="content">
		<div class="hidden" id="dialog_revenue_listowners"></div>
		<div class="hidden" id="dialog_revenue_listowners_detail"></div>
		<div class="hidden" id="dialog_revenue_mailers"></div>
	</div>
	<div class="footer">
		<p>Copyright &copy; 2014 Qatalyst Media, LLC.  All rights reserved.</p>
	</div>
</div>
</body>
</html>
