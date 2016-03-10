<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch($_REQUEST['a']){

		case 'copy_revenue':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['status'] = 0;
				$result['error'] = 'Not authorized.';
				break;
			}

			$result['status'] = 0;
			$result['error'] = 'Invalid revenue value.';

			if( empty( $_REQUEST['fromDate'] ) || empty( $_REQUEST['toDate'] ) || empty( $_REQUEST['idCompany'] ) ) {
				$result['error'] = 'Not all parameters were given.';
				break;
			}

			$leads->copyRevenueValues( $_REQUEST['fromDate'], $_REQUEST['toDate'], $_REQUEST['idCompany'] );
			$result['status'] = 1;
			$result['error'] = 'Values copied.';
			break;

		case 'save_revenue':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['status'] = 0;
				$result['error'] = 'Not authorized.';
				break;
			}

			$result['status'] = 0;
			$result['error'] = 'Invalid revenue value.';

			if( ( $string = base64_decode( $_REQUEST['field'] ) ) !== FALSE ) {

				list( $date, $idFeedIn, $idFeedOut, $url ) = explode( '|', $string );
				$value = $_REQUEST['value'];
				if( empty( $value ) || !is_numeric( $value ) ) {
					$value = null;
				}

				$leads->setRevenueValue( $date, $idFeedIn, $idFeedOut, $url, $value );
				$result['status'] = 1;
				$result['error'] = 'Saved';

			}
			break;

		case 'send_report_ready':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['status'] = 0;
				$result['error'] = 'Not authorized.';
				break;
			}

			if( empty( $_REQUEST['idCompany'] ) ) {
				$result['status'] = 0;
				$result['error'] = 'Company ID is missing.';
				break;
			}

			if( empty( $_REQUEST['date'] ) ) {
				$result['status'] = 0;
				$result['error'] = 'Date is missing.';
				break;
			}

			$company = $leads->getCompany( $_REQUEST['idCompany'] );
			if( empty( $company ) ) {
				$result['status'] = 0;
				$result['error'] = 'Invalid company ID.';
				break;
			}

			if( empty( $company->acct_email ) ) {
				$result['status'] = 0;
				$result['error'] = 'Accounting contact email is not setup.';
				break;
			}

			if( empty( $company->acct_name ) ) {
				$result['status'] = 0;
				$result['error'] = 'Accounting contact name is not setup.';
				break;
			}

			$user = $leads->findClientUser( $_REQUEST['idCompany'] );
			if( empty( $user ) ) {
				$result['status'] = 0;
				$result['error'] = 'Client username/password is not setup.';
				break;
			}

			$date = date( 'F Y', strtotime( $_REQUEST['date'] . '01' ) );
			list( $first, $garbage ) = explode( ' ', $company->acct_name, 2 );

			$message  = "Hi {$first},\r\n";
			$message .= "\r\n";
			$message .= "Your {$date} List Management Revenue Report is now available.  Your login credentials are listed below:\r\n";
			$message .= "\r\n";
			$message .= "Link: https://www." . SITE_URL . "/leadadmin/client_reports.php\r\n";
			$message .= "Username: {$user->username}\r\n";
			$message .= "Password: [If you forgot your password, please contact your " . CONFIG_COMPANY_NAME . " Account Manager]\r\n";
			$message .= "\r\n";
			$message .= "To ensure prompt payment, please be sure to email all invoices to " . PAYMENT_EMAIL . ".\r\n";
			$message .= "\r\n";
			$message .= "Thank you for your business and we look forward to growing our partnership.\r\n";
			$message .= "\r\n";
			$message .= "Warmly,\r\n";
			$message .= "\r\n";
			$message .= "Accounting\r\n";
			$message .= COMPANY_LEGAL_NAME . "\r\n";
			$message .= COMPANY_ADDRESS_1 . "\r\n";
			$message .= COMPANY_ADDRESS_2 . "\r\n";

			if( mail( $company->acct_email, "{$date} List Management Report Available | " . CONFIG_COMPANY_NAME, $message, "From: \"" . CONFIG_COMPANY_NAME . "\" <" . PAYMENT_EMAIL . ">\r\nBCC: " . PAYMENT_EMAIL, '-f' . PAYMENT_EMAIL ) ) {
				$result['status'] = 1;
				$result['error'] = 'Message sent!';
				break;
			}

			$result['status'] = 0;
			$result['error'] = 'Unable to send message.';

			break;

		case 'invoice_status':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$result['status'] = 0;
				$result['error'] = 'Not authorized.';
				break;
			}

			if( empty( $_REQUEST['idCompany'] ) ) {
				$result['status'] = 0;
				$result['error'] = 'Company ID is missing.';
				break;
			}

			if( empty( $_REQUEST['date'] ) ) {
				$result['status'] = 0;
				$result['error'] = 'Date is missing.';
				break;
			}

			$company = $leads->getCompany( $_REQUEST['idCompany'] );
			if( empty( $company ) ) {
				$result['status'] = 0;
				$result['error'] = 'Invalid company ID.';
				break;
			}

			$leads->setInvoiceNumber( $_REQUEST['date'], $_REQUEST['idCompany'], !empty( $_REQUEST['invoiceNumber'] ) ? $_REQUEST['invoiceNumber'] : '' );
			$result['status'] = 1;
			$result['error'] = 'Invoice number updated.';

			if( !empty( $_REQUEST['email' ] ) && !empty( $_REQUEST['invoiceNumber'] ) ) {
				if( empty( $company->acct_email ) ) {
					$result['status'] = 0;
					$result['error'] = 'Accounting contact email is not setup. No notification sent.';
					break;
				}

				if( empty( $company->acct_name ) ) {
					$result['status'] = 0;
					$result['error'] = 'Accounting contact name is not setup. No notification sent.';
					break;
				}

				$date = date( 'F Y', strtotime( $_REQUEST['date'] . '01' ) );
				list( $first, $garbage ) = explode( ' ', $company->acct_name, 2 );

				$amounts = $leads->getRevenueInboundClientMonthTotal( $_REQUEST['idCompany'], $_REQUEST['date'] );
				if( empty( $amounts[0]['partner'] ) ) {
					$result['status'] = 0;
					$result['error'] = 'Partner revenue share is zero. No notification sent.';
					break;
				}

				$message  = "Hi {$first},\r\n";
				$message .= "\r\n";
				$message .= "The invoice below has been paid via ACH. Please let us know if you do not see the money within 24-48 hours.\r\n";
				$message .= "\r\n";
				$message .= "Month: {$date}\r\n";
				$message .= "Invoice #: " . $_REQUEST['invoiceNumber'] . "\r\n";
				$message .= "Amount: \$" . number_format( $amounts[0]['partner'], 2 ) . "\r\n";
				$message .= "\r\n";
				$message .= "\r\n";
				$message .= "Thank you and we appreciate your business.\r\n";
				$message .= "\r\n";
				$message .= "Warmly,\r\n";
				$message .= "\r\n";
				$message .= "Accounting\r\n";
				$message .= COMPANY_LEGAL_NAME . "\r\n";
				$message .= COMPANY_ADDRESS_1 . "\r\n";
				$message .= COMPANY_ADDRESS_2 . "\r\n";

				if( mail( $company->acct_email, "Invoice #" . $_REQUEST['invoiceNumber'] . " PAID | " . CONFIG_COMPANY_NAME, $message, "From: \"" . CONFIG_COMPANY_NAME . "\" <" . PAYMENT_EMAIL . ">\r\nBCC: " . PAYMENT_EMAIL, '-f' . PAYMENT_EMAIL ) ) {
					$result['status'] = 1;
					$result['error'] = 'Invoice number updated AND notification email sent.';
					break;
				}

				$result['status'] = 0;
				$result['error'] = 'Unable to send message.';
			}

			break;
	}
	echo json_encode($result);
	exit;
}

if( isset( $_REQUEST['d'] ) ) {
	switch( $_REQUEST['d'] ) {

		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

		case 'reports':
?>

<p><a href="#" class="nonLink" onclick="display('dialog_mapping'); closeContent('dialog_revenue_mailers'); closeContent('dialog_revenue_listowners');">Mapping Report</a></p>
<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) { ?>
<p><a href="#" class="nonLink" onclick="display('dialog_revenue_listowners'); closeContent('dialog_revenue_mailers'); closeContent('dialog_mapping');">Revenue Report - List Owners</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_revenue_mailers'); closeContent('dialog_revenue_listowners'); closeContent('dialog_mapping');">Revenue Report - Mailers</a></p>
<p><a href="/leadadmin/client_reports.php" target="_blank">Master Client Revenue Report</a></p>
<?php } ?>
<p><a href="#" class="nonLink" onclick="display('dialog_search_email'); closeContent('dialog_search_email_results');">Email Search Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_search_url'); closeContent('dialog_search_url_results');">URL Search Report</a></p>
<div class="hidden" id="dialog_mapping"></div>
<div class="hidden" id="dialog_revenue_listowners"></div>
<div class="hidden" id="dialog_revenue_mailers"></div>
<div class="hidden" id="dialog_search_email"></div>
<div class="hidden" id="dialog_search_email_results"></div>
<div class="hidden" id="dialog_search_url"></div>
<div class="hidden" id="dialog_search_url_results"></div>

<?php
		break;

		case 'dialog_mapping':
?>
<div class="aRight">
	<a href="#" class="nonLink" onclick="closeContent('dialog_mapping');">Close [X]</a>
</div>
<?php

			$mappings = $leads->getUrlMappings();
			if( $mappings ) {
				print "<table id=\"mapping_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Incoming Company</td>\n";
				print "\t\t<td>Incoming Feed</td>\n";
				print "\t\t<td>Incoming URL</td>\n";
				print "\t\t<td>Outgoing Company</td>\n";
				print "\t\t<td>Outgoing Feed</td>\n";
				print "\t\t<td>Active</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['inName'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedIn'] . ': ' . $mapping['inDescription'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['outName'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedOut'] . ': ' . $mapping['outDescription'] ) );
					if( '1' == $mapping['active'] ) {
						print "\t\t<td>Y</td>\n";
					} else {
						print "\t\t<td>N</td>\n";
					}
					print "\t</tr>\n";

				}
				print "\t</tbody>\n";
				print "</table>\n";
?>
<script type="text/javascript">
	var tf = new TableFilter(document.querySelector('#mapping_report'), {
		base_path: '/leadadmin/js/tablefilter/',
		filters_row_index: 1,
		sort: true,
		sort_config: {
			sort_types:['String','String','String','String','String','String']
		},
		remember_grid_values: true,
		alternate_rows: true,
		btn_reset: true,
		btn_reset_text: "Clear",
		btn_text: " > ",
		loader: true,
		loader_text: "Filtering data...",
		col_0: "select",
		col_1: "select",
		col_2: "select",
		col_3: "select",
		col_4: "select",
		col_5: "select",
		display_all_text: "< Show all >"
	});
	tf.init();
</script>
<?php
			} else {
				print "Cannot load list of incoming feeds.";
			}

		break;

		case 'dialog_revenue_listowners':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				print "You are not authorized to access this section.";
				break;
			}

			if( empty( $_REQUEST['options']['report_date'] ) || strlen( $_REQUEST['options']['report_date'] ) != 6 ) $reportDate = date('Ym');
			else $reportDate = $_REQUEST['options']['report_date'];

			if( empty( $_REQUEST['options']['idCompany'] ) ) $idCompany = null;
			else $idCompany = $_REQUEST['options']['idCompany'];

			if( empty( $_REQUEST['options']['idFeedIn'] ) ) $idFeedIn = null;
			else $idFeedIn = $_REQUEST['options']['idFeedIn'];

			if( empty( $_REQUEST['options']['url'] ) ) $urlFilter = null;
			else $urlFilter = $_REQUEST['options']['url'];

?>
<div class="aRight">
	<a href="#" class="nonLink" onclick="closeContent('dialog_revenue_listowners');">Close [X]</a>
</div>
<p><strong>Report Date:</strong>
<select name="report_date" id="dialog_revenue_listowners_date" class="dialog_revenue_listowners_change">
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
<select name="idCompany" id="dialog_revenue_listowners_company" class="dialog_revenue_listowners_change">
<?php 
	printf( '<option value=""%s>SHOW ALL COMPANIES</option>',
					( empty( $idCompany ) ? ' selected="selected"' : '' ) );
	$companies = $leads->getRevenueInboundCompanies();
	if( $companies ) {
		foreach( $companies as $company ) {
			printf(' <option value="%s"%s>%s</option>',
					$company['idCompany'], ( $idCompany == $company['idCompany'] ? ' selected="selected"' : '' ), $company['name'] );
		}
	}
?>
</select>
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

<?php
if( !empty( $idCompany ) ) {
	print '<input class="fr" type="button" value="Send Report Ready Email" onclick="sendReportReady(' . $idCompany . ',' . $reportDate . ')" />';

	$reportDateObj = new DateTime( $reportDate . '01' );
	$reportDateObj->sub( new DateInterval( 'P1M' ) );

	printf( '<input class="fr" type="button" value="Copy values from last month" onclick="copyRevenue( \'%s\', \'%s\', \'%s\' )" />', $reportDateObj->format( 'Ym' ), $reportDate, $idCompany );

	print '<p class="fr">';
	$invoiceNumber = $leads->getInvoiceNumber( $reportDate, $idCompany );
	print 'Invoice #<input type="text" value="' . htmlspecialchars( $invoiceNumber, ENT_HTML5 | ENT_NOQUOTES ) . '" id="invoice_number" /> ';
	print '<input type="checkbox" value="1" id="invoice_email" /> Send Email? ';
	print '<input type="button" value="Save" onclick="invoiceStatus(' . $idCompany . ',' . $reportDate . ', 0, ' . ( empty( $idFeedIn ) ? 0 : $idFeedIn ) . ' , \'' . ( empty( $urlFilter ) ? 0 : $urlFilter )  . '\' )" />';
	print '</p>';
}
?>
</p>

<?php
			$mappings = $leads->getRevenueInboundMappings( $reportDate, $idCompany, $idFeedIn, $urlFilter );
			if( $mappings ) {
				$colspan = 5;
				print "<table id=\"revenue_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				if( empty( $idCompany ) ) {
					print "\t\t<td>Incoming Company</td>\n";
					$colspan++;
				}
				if( empty( $idFeedIn ) ) {
					print "\t\t<td>Incoming Feed</td>\n";
					$colspan++;
				}
				print "\t\t<td>Incoming URL</td>\n";
				print "\t\t<td>Outgoing Company</td>\n";
				print "\t\t<td>Outgoing Feed</td>\n";
				print "\t\t<td>First Out</td>\n";
				print "\t\t<td>Last Out</td>\n";
				print "\t\t<td>Gross</td>\n";
				print "\t\t<td>Partner</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				$row = 0;
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					if( empty( $idCompany ) ) {
						printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['inName'] ) );
					}
					if( empty( $idFeedIn ) ) {
						printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedIn'] . ': ' . $mapping['inDescription'] ) );
					}
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['outName'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedOut'] . ': ' . $mapping['outDescription'] ) );
					//printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['firstInDate'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['firstDate'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['lastDate'] ) );
					printf( "\t\t<td class=\"revenue\"><input type=\"number\" min=\"0\" max=\"9999\" step=\"0.01\" id=\"%s\" name=\"%s\" value=\"%s\" /></td>\n", 'A' . ++$row, htmlspecialchars( base64_encode( $reportDate . '|' . $mapping['idFeedIn'] . '|' . $mapping['idFeedOut'] . '|' . $mapping['url'] ) ), ( empty( $mapping['revenue'] ) ? '' : htmlspecialchars( $mapping['revenue'] ) ) );
					printf( "\t\t<td class=\"revenue\" id=\"B%s\" data-format=\"$0,0.00\" data-formula=\"ROUND((%s*0.5)*100)/100\">%s</td>\n", $row, '$A' . $row, htmlspecialchars( $mapping['lastDate'] ) );
					print "\t</tr>\n";

				}
				print "\t</tbody>\n";
				print "\t<tfoot>\n";
				print "\t<tr class=\"bgGray subtotal\">\n";
				print "\t\t<td colspan=\"" . $colspan . "\">TOTAL</td>\n";
				printf( "\t\t<td class=\"revenue\" id=\"A%s\" data-format=\"$0,0.00\" data-formula=\"SUM(\$A1,\$A%s)\"></td>\n", ++$row, (sizeOf( $mappings )) );
				printf( "\t\t<td class=\"revenue\" id=\"B%s\" data-format=\"$0,0.00\" data-formula=\"ROUND((\$A%s*0.5)*100)/100\"></td>\n", $row, $row );
				print "\t</tr>\n";
				print "\t</tfoot>\n";
				print "</table>\n";
			}

?>
<script type="text/javascript">

var tf = new TableFilter(document.querySelector('#revenue_report'), {
	base_path: '/leadadmin/js/tablefilter/',
	grid: false,
	filters_row_index: 1,
	extensions: [{ name: 'sort' }],
	widgets: ['staticRow'],
	sort: true,
	sort_config: {
		sort_types:['String','String','String','String','String','String']
	}
});
tf.init();


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

		display('dialog_revenue_listowners', { 'report_date': date, 'idCompany': company, 'idFeedIn': feed, 'url': url });
	});

	$("#revenue_report input").each(function() {
		$(this).focusout(function(){
			$.ajax({
				url: "mgr_reports.php",
				type: "POST",
				async: true,
				data: ({
					"a" : "save_revenue",
					"field" : $(this).attr("name"),
					"value" : $(this).val()
				})
			});
		});
	});

	$('#revenue_report').calx();
});

function sendReportReady( idCompany, date ){
	var response = $.ajax({
		url: "mgr_reports.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "send_report_ready",
			"idCompany" : idCompany,
			"date" : date
		})
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) {
			alert("JSON Failed: "+responseText);
			return false;
		}
		if(result.status == 1){
			alert("Report email sent.");
		} else {
			alert(result.error);
		}
	});
}

function copyRevenue( fromDate, toDate, idCompany ){

	if( confirm("Are you sure you want to copy all values from last month?") ) {

		var response = $.ajax({
			url: "mgr_reports.php",
			type: "POST",
			async: true,
			data: ({
				"a" : "copy_revenue",
				"idCompany" : idCompany,
				"fromDate" : fromDate,
				"toDate" : toDate
			})
		}).done(function(responseText){
			var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
			if(result===null) {
				alert("JSON Failed: "+responseText);
				return false;
			}
			if(result.status == 1){
				alert("Values copied from last month.");
			} else {
				alert(result.error);
			}
			display('dialog_revenue_listowners', { 'report_date': toDate, 'idCompany': idCompany });

		});
	}
}

function invoiceStatus( idCompany, date, paid, idFeedIn, url ){
	var response = $.ajax({
		url: "mgr_reports.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "invoice_status",
			"idCompany" : idCompany,
			"date" : date,
			"invoiceNumber" : $("#invoice_number").val(),
			"email" : $("#invoice_email").prop( "checked" ) ? 1 : 0
		})
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) {
			alert("JSON Failed: "+responseText);
			return false;
		}
		alert(result.error);
		display('dialog_revenue_listowners', { 'report_date': date, 'idCompany': idCompany, 'idFeedIn': idFeedIn, 'url': url });
	});
}
</script>

<?php
		break;

		case 'dialog_revenue_mailers':
			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				print "You are not authorized to access this section.";
				break;
			}

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
					$feed['idFeedOut'], ( $idFeedOut == $feed['idFeedOut'] ? ' selected="selected"' : '' ), $feed['idFeedOut'] . ': ' . htmlspecialchars( $feed['outDescription'] ) );
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

	$("#revenue_report input").each(function() {
		$(this).focusout(function(){
			$.ajax({
				url: "mgr_reports.php",
				type: "POST",
				async: true,
				data: ({
					"a" : "save_revenue",
					"field" : $(this).attr("name"),
					"value" : $(this).val()
				})
			});
		});
	});
});
</script>

<?php
		break;

		case 'dialog_search_email':
?>
<div class="aRight">
	<a href="#" class="nonLink" onclick="closeContent('dialog_search_email'); closeContent('dialog_search_email_results');">Close [X]</a>
</div>
<table class="feedTable" border="1" cellpadding="0" cellspacing="0">
	<tr>
		<td><p>Email Address</p></td>
		<td>
			<p><input type="text" name="search_email" id="search_email" value="" /></p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<p class="aRight"><input type="button" value="Search" onclick="display( 'dialog_search_email_results', { 'email': $('#search_email').val() });" /></p>
		</td>
	</tr>
</table>
<?php
		break;

		case 'dialog_search_email_results':
			$email = $_REQUEST['options']['email'];
?>
<p>Searching incoming feeds for <strong><?php echo htmlspecialchars( $email ); ?></strong> ...</p>
<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Incoming Feed</th>
			<th>Listcode</th>
			<th>Timestamp</th>
			<th>URL</th>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Lead Timestamp</th>
			<th>IP Address</th>
			<th>DOB</th>
		</tr>
		<tr>
			<th>Address 1</th>
			<th>Address 2</th>
			<th>City</th>
			<th>State</th>
			<th>Zipcode</th>
			<th>Country</th>
			<th>Landline</th>
			<th>Cellphone</th>
			<th>Gender</th>
		</tr>
	</thead>
	<tbody>
<?php
		$records = $leads->inboundEmailSearch( $email );
		if( is_array( $records ) ) {
			foreach( $records as $record ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedIn'] ); ?>)</td>
		<td><?php echo htmlspecialchars( $record['listcode'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['url'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['fname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['lname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['leadstamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['ip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['dob'] ); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars( $record['addr'] ); ?>&nbsp;</td>
		<td><?php echo htmlspecialchars( $record['addr2'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['city'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['state'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['zip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['country'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['landline'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['cellphone'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['gender'] ); ?></td>
	</tr>
<?php
			}

		}
?>
	</tbody>
</table>

<p>Searching outgoing feeds for <strong><?php echo htmlspecialchars( $email ); ?></strong> ...</p>
<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Outgoing Feed</th>
			<th>Listcode</th>
			<th>Timestamp</th>
			<th>URL</th>
			<th>First Name</th>
			<th>Last Name</th>
			<th>Lead Timestamp</th>
			<th>IP Address</th>
			<th>DOB</th>
		</tr>
		<tr>
			<th>Address 1</th>
			<th>Address 2</th>
			<th>City</th>
			<th>State</th>
			<th>Zipcode</th>
			<th>Country</th>
			<th>Landline</th>
			<th>Cellphone</th>
			<th>Gender</th>
		</tr>
	</thead>
	<tbody>
<?php
		$records = $leads->outboundEmailSearch( $email );
		if( is_array( $records ) ) {
			foreach( $records as $record ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedOut'] ); ?>)</td>
		<td><?php echo htmlspecialchars( $record['listcode'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['url'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['fname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['lname'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['leadstamp'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['ip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['dob'] ); ?></td>
	</tr>
	<tr>
		<td><?php echo htmlspecialchars( $record['addr'] ); ?>&nbsp;</td>
		<td><?php echo htmlspecialchars( $record['addr2'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['city'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['state'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['zip'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['country'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['landline'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['cellphone'] ); ?></td>
		<td><?php echo htmlspecialchars( $record['gender'] ); ?></td>
	</tr>
<?php
			}
		}
?>
	</tbody>
</table>
<?php
		break;

		case 'dialog_search_url':
?>
<div class="aRight">
	<a href="#" class="nonLink" onclick="closeContent('dialog_search_url'); closeContent('dialog_search_url_results');">Close [X]</a>
</div>
<table class="feedTable" border="1" cellpadding="0" cellspacing="0">
	<tr>
		<td><p>URL</p></td>
		<td>
			<p><input type="text" name="search_email" id="search_url" value="" /></p>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<p class="aRight"><input type="button" value="Search" onclick="display( 'dialog_search_url_results', { 'url': $('#search_url').val() });" /></p>
		</td>
	</tr>
</table>
<?php
		break;

		case 'dialog_search_url_results':
			$url = $_REQUEST['options']['url'];
?>
<p>Searching incoming feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Incoming feed</th>
			<th>Total records</th>
			<th>Last record received on</th>
		</tr>
	</thead>
	<tbody>
<?php
			$records = $leads->inboundURLSearch( $url );
			if( is_array( $records ) ) {
				foreach( $records as $record ) {
					if( $record['cnt'] > 0 ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedIn'] ); ?>)</td>
		<td><?php echo number_format( htmlspecialchars( $record['cnt'] ) ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
	</tr>
<?php
					}
				}

			}
?>
	</tbody>
</table>

<p>Searching outgoing feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="rejectionsTable">
	<thead>
		<tr>
			<th>Outgoing feed</th>
			<th>Total records</th>
			<th>Last record sent on</th>
		</tr>
	</thead>
	<tbody>
<?php
			$records = $leads->outboundURLSearch( $url );
			if( is_array( $records ) ) {
				foreach( $records as $record ) {
					if( $record['cnt'] > 0 ) {
?>
	<tr>
		<td><?php echo htmlspecialchars( $record['label'] ); ?> (#<?php echo htmlspecialchars( $record['idFeedOut'] ); ?>)</td>
		<td><?php echo number_format( htmlspecialchars( $record['cnt'] ) ); ?></td>
		<td><?php echo htmlspecialchars( $record['timestamp'] ); ?></td>
	</tr>
<?php
					}
				}
			}
?>
	</tbody>
</table>
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
	display('reports');
});
</script>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
		<div class='fl' style='width: 100%;'>
			<div id='reports'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>
<script src="/leadadmin/js/calx-1.1.4/jquery-calx-1.1.4.min.js" type="text/javascript"></script>
<script src="/leadadmin/js/tablefilter/tablefilter.js" type="text/javascript"></script>
</body>
</html>
