<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_ADMIN );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch( $_REQUEST['a'] ) {

		case 'copy_revenue':
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
			$result['status'] = 0;
			$result['error'] = 'Invalid revenue value.';

			if( ( $string = base64_decode( $_REQUEST['field'] ) ) !== false ) {

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

			$message = "Hi {$first},\r\n";
			$message .= "\r\n";
			$message .= "Your {$date} List Management Revenue Report is now available.  Your login credentials are listed below:\r\n";
			$message .= "\r\n";
			$message .= "Link: https://" . POSTING_URL . "/leadadmin/client_reports.php\r\n";
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

			$leads->setInvoiceDetails( $_REQUEST['date'], $_REQUEST['idCompany'], !empty( $_REQUEST['invoiceNumber'] ) ? $_REQUEST['invoiceNumber'] : '', !empty( $_REQUEST['paymentDate'] ) ? $_REQUEST['paymentDate'] : '', !empty( $_REQUEST['userId'] ) ? $_REQUEST['userId'] : '' );
			$result['status'] = 1;
			$result['error'] = 'Invoice details updated.';

			if( !empty( $_REQUEST['email'] ) && !empty( $_REQUEST['invoiceNumber'] ) ) {
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

				$message = "Hi {$first},\r\n";
				$message .= "\r\n";
				$message .= "The invoice below has been paid via ACH. Please let us know if you do not see the money within 24-48 hours.\r\n";
				$message .= "\r\n";
				$message .= "Month: {$date}\r\n";
				$message .= "Invoice #: " . $_REQUEST['invoiceNumber'] . "\r\n";
				$message .= "Amount: \$" . number_format( $amounts[0]['partner'], 2 ) . "\r\n";
				if( !empty( $_REQUEST['paymentDate'] ) ) {
					$message .= "Payment Date: " . date( 'n/j/Y', strtotime( $_REQUEST['paymentDate'] ) ) . "\r\n";
				}
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

                // #1891: Add account manager to the payment notification emails.
                $BCCText = "BCC: " . PAYMENT_EMAIL . "\r\n";
                if (!empty($company->accountManager) && !empty($user = $leads->getUser($company->accountManager)) && !empty($user->email)) {
                    $BCCText .= "BCC: {$user->email}\r\n";
                }

                if( mail( $company->acct_email, "Invoice #" . $_REQUEST['invoiceNumber'] . " PAID | " . CONFIG_COMPANY_NAME, $message, "From: \"" . CONFIG_COMPANY_NAME . "\" <" . PAYMENT_EMAIL . ">\r\n" . $BCCText, '-f' . PAYMENT_EMAIL ) ) {
					$result['status'] = 1;
					$result['error'] = 'Invoice number updated AND notification email sent.';
					break;
				}

				$result['status'] = 0;
				$result['error'] = 'Unable to send message.';
			}

			break;
	}
	echo json_encode( $result );
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

		case 'dialog_revenue_listowners':
			if( empty( $_REQUEST['options']['report_date'] ) || strlen( $_REQUEST['options']['report_date'] ) != 6 ) {
				$reportDate = date( 'Ym' );
			} else {
				$reportDate = $_REQUEST['options']['report_date'];
			}

			if( empty( $_REQUEST['options']['idCompany'] ) ) {
				$idCompany = null;
			} else {
				$idCompany = $_REQUEST['options']['idCompany'];
			}

			if( empty( $_REQUEST['options']['idFeedIn'] ) ) {
				$idFeedIn = null;
			} else {
				$idFeedIn = $_REQUEST['options']['idFeedIn'];
			}

			if( empty( $_REQUEST['options']['url'] ) ) {
				$urlFilter = null;
			} else {
				$urlFilter = $_REQUEST['options']['url'];
			}

			?>
			<p><strong>Report Date:</strong>
				<select name="report_date" id="dialog_revenue_listowners_date" class="dialog_revenue_listowners_change">
					<?php
					for( $y = date( 'Y' ); $y >= 2012; $y-- ) {
						for( $m = 12; $m > 0; $m-- ) {
							$format_month = str_pad( $m, 2, '0', STR_PAD_LEFT );
							printf( ' <option value="%s"%s>%s</option>',
								$y . $format_month, ( $y == substr( $reportDate, 0, 4 ) && $format_month == substr( $reportDate, 4, 2 ) ) ? ' selected="selected"' : '', $y . '-' . $format_month );
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
							printf( ' <option value="%s"%s>%s</option>',
								$company['idCompany'], ( $idCompany == $company['idCompany'] ? ' selected="selected"' : '' ), $company['name'] );
						}
					}
					?>
				</select>
				<?php if( !empty( $idCompany ) ) { ?>
					<select name="idFeedIn" id="dialog_revenue_listowners_feed" class="dialog_revenue_listowners_change">
						<?php
						printf( '<option value=""%s>SHOW ALL FEEDS</option>',
							( empty( $idFeedIn ) ? ' selected="selected"' : '' ) );
						$feeds = $leads->getRevenueInboundFeeds( $idCompany );
						if( $feeds ) {
							foreach( $feeds as $feed ) {
								printf( ' <option value="%s"%s>%s</option>',
									$feed['idFeedIn'], ( $idFeedIn == $feed['idFeedIn'] ? ' selected="selected"' : '' ), $feed['idFeedIn'] . ': ' . htmlspecialchars( $feed['inDescription'] ) );
							}
						}
						?>
					</select>
				<?php } else { ?>
					<input type="hidden" id="dialog_revenue_listowners_feed" value=""/>
				<?php } ?>
				<?php if( !empty( $idFeedIn ) ) { ?>
					<select name="url" id="dialog_revenue_listowners_url" class="dialog_revenue_listowners_change">
						<?php
						printf( '<option value=""%s>SHOW ALL URLS</option>',
							( empty( $url ) ? ' selected="selected"' : '' ) );
						$urls = $leads->getRevenueInboundURLs( $idFeedIn );
						if( $urls ) {
							foreach( $urls as $url ) {
								printf( ' <option value="%s"%s>%s</option>',
									$url['url'], ( $urlFilter == $url['url'] ? ' selected="selected"' : '' ), $url['url'] );
							}
						}
						?>
					</select>
				<?php } else { ?>
					<input type="hidden" id="dialog_revenue_listowners_url" value=""/>
				<?php } ?>

				<?php
				if( !empty( $idCompany ) ) {
					print '<div class="pull-right">' . PHP_EOL;

					print '<p class="text-right">' . PHP_EOL;
					$reportDateObj = new DateTime( $reportDate . '01' );
					$reportDateObj->sub( new DateInterval( 'P1M' ) );
					printf( '<input class="btn btn-primary btn-sm" type="button" value="Copy values from last month" onclick="copyRevenue( \'%s\', \'%s\', \'%s\' )" /> ', $reportDateObj->format( 'Ym' ), $reportDate, $idCompany );
					print '<input class="btn btn-primary btn-sm" type="button" value="Send Report Ready Email" onclick="sendReportReady(' . $idCompany . ',' . $reportDate . ')" />';
					print '</p>' . PHP_EOL;

					print '<p class="text-right form-inline">';
					$invoice = $leads->getInvoiceDetails( $reportDate, $idCompany );
					printf( 'Invoice #: <input class="form-control" type="text" value="%s" id="invoice_number" /> ',
						!empty( $invoice->invoiceNumber ) ? htmlspecialchars( $invoice->invoiceNumber, ENT_HTML5 | ENT_NOQUOTES ) : ''
					);
					printf( 'Payment Date: <input class="form-control" type="text" value="%s" id="paymentDate" /> ',
						!empty( $invoice->paymentDate ) ? htmlspecialchars( $invoice->paymentDate, ENT_HTML5 | ENT_NOQUOTES ) : ''
					);
					$users = $leads->getStaffUsers();
					print 'Salesperson: <select class="form-control" id="userId">';
					print '<option></option>' . PHP_EOL;
					foreach( $users as $userId => $name ) {
						printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
							$userId,
							( isset( $invoice->userId ) && $invoice->userId == $userId ) ? ' selected="selected"' : '',
							$name
						);
					}
					print '</select> ';
					print 'Send Email? <input class="form-control" type="checkbox" value="1" id="invoice_email" /> ';
					print '<input class="btn btn-primary btn-sm" type="button" value="Save" onclick="invoiceStatus(' . $idCompany . ',' . $reportDate . ', 0, ' . ( empty( $idFeedIn ) ? 0 : $idFeedIn ) . ' , \'' . ( empty( $urlFilter ) ? 0 : $urlFilter ) . '\' )" />';
					print '</p>';

					print '</div>';
					?>
					<script type="text/javascript">
						$('#paymentDate').datepicker({
							// Consistent format with the HTML5 picker
							dateFormat: 'yy-mm-dd'
						});
					</script>

					<?php
				}
				?>
			</p>

			<?php
			$mappings = $leads->getRevenueInboundMappings( $reportDate, $idCompany, $idFeedIn, $urlFilter );
			if( $mappings ) {
				$colspan = 5;
				print '<table class="table table-bordered table-condensed table-striped" id="revenue_report">' . PHP_EOL;
				print "\t<thead>\n";
				print "\t<tr>\n";
				if( empty( $idCompany ) ) {
					print "\t\t<th>Incoming Company</th>\n";
					$colspan++;
				}
				if( empty( $idFeedIn ) ) {
					print "\t\t<th>Incoming Feed</th>\n";
					$colspan++;
				}
				print "\t\t<th>Incoming URL</th>\n";
				print "\t\t<th>Outgoing Company</th>\n";
				print "\t\t<th>Outgoing Feed</th>\n";
				print "\t\t<th>First Out</th>\n";
				print "\t\t<th>Last Out</th>\n";
				print "\t\t<th>Gross</th>\n";
				print "\t\t<th>Partner</th>\n";
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
					printf( "\t\t<td class=\"revenue\" data-tf-sortKey=\"%s\"><input type=\"number\" min=\"0\" max=\"9999\" step=\"0.01\" id=\"%s\" name=\"%s\" value=\"%s\" /></td>\n", ( empty( $mapping['revenue'] ) ? '0' : htmlspecialchars( $mapping['revenue'] ) ), 'A' . ++$row, htmlspecialchars( base64_encode( $reportDate . '|' . $mapping['idFeedIn'] . '|' . $mapping['idFeedOut'] . '|' . $mapping['url'] ) ), ( empty( $mapping['revenue'] ) ? '' : htmlspecialchars( $mapping['revenue'] ) ) );
					printf( "\t\t<td class=\"revenue\" id=\"B%s\" data-format=\"$0,0.00\" data-formula=\"ROUND((%s*0.5)*100)/100\">%s</td>\n", $row, '$A' . $row, htmlspecialchars( $mapping['lastDate'] ) );
					print "\t</tr>\n";

				}
				print "\t</tbody>\n";
				print "\t<tfoot>\n";
				print "\t<tr class=\"bgGray subtotal\">\n";
				print "\t\t<td colspan=\"" . $colspan . "\">TOTAL</td>\n";
				printf( "\t\t<td class=\"revenue\" id=\"A%s\" data-format=\"$0,0.00\" data-formula=\"SUM(\$A1,\$A%s)\"></td>\n", ++$row, ( sizeOf( $mappings ) ) );
				printf( "\t\t<td class=\"revenue\" id=\"B%s\" data-format=\"$0,0.00\" data-formula=\"ROUND((\$A%s*0.5)*100)/100\"></td>\n", $row, $row );
				print "\t</tr>\n";
				print "\t</tfoot>\n";
				print "</table>\n";
			}

			?>
			<script type="text/javascript">

				var myTable = document.querySelector('#revenue_report');

				if (myTable) {
					var tf = new TableFilter(myTable, {
						base_path: '/leadadmin/libraries/tablefilter/',
						state: {
							types: ['local_storage'],
							sort: true,
							filters: false,
							page_number: false,
							page_length: false,
							columns_visibility: false,
							filters_visibility: false
						},
						grid: false,
						filters_row_index: 1,
						extensions: [{
							name: 'sort',
							types: [
								'caseinsensitivestring', // Incoming Company
								'caseinsensitivestring', // Incoming Feed
								'caseinsensitivestring', // Incoming URL
								'caseinsensitivestring', // Outgoing Company
								'caseinsensitivestring', // Outgoing Feed
								'date', // First Out
								'date', // Last Out
								'formatted-number', // Gross
								'formatted-number' // Partner
							],
						}],
						sort: true
					});
					tf.init();
				}

				$(document).ready(function () {

					$('.dialog_revenue_listowners_change').change(function () {
						var date = $('#dialog_revenue_listowners_date').val();
						var company = $('#dialog_revenue_listowners_company').val();
						var feed = "";
						var url = "";

						if ("dialog_revenue_listowners_url" == $(this).attr("id") || "dialog_revenue_listowners_date" == $(this).attr("id")) {
							feed = $('#dialog_revenue_listowners_feed').val();
							url = $('#dialog_revenue_listowners_url').val();
						} else if ("dialog_revenue_listowners_feed" == $(this).attr("id")) {
							feed = $('#dialog_revenue_listowners_feed').val();
						}

						display('dialog_revenue_listowners', {'report_date': date, 'idCompany': company, 'idFeedIn': feed, 'url': url});
					});

					$("#revenue_report input").each(function () {
						$(this).focusout(function () {
							$.ajax({
								url: "list-owner.php",
								type: "POST",
								async: true,
								data: ({
									"a": "save_revenue",
									"field": $(this).attr("name"),
									"value": $(this).val()
								})
							});
						});
					});

					$('#revenue_report').calx();
				});

				function sendReportReady(idCompany, date) {
					var response = $.ajax({
						url: "list-owner.php",
						type: "POST",
						async: true,
						data: ({
							"a": "send_report_ready",
							"idCompany": idCompany,
							"date": date
						})
					}).done(function (responseText) {
						var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
						if (result === null) {
							alert("JSON Failed: " + responseText);
							return false;
						}
						if (result.status == 1) {
							alert("Report email sent.");
						} else {
							alert(result.error);
						}
					});
				}

				function copyRevenue(fromDate, toDate, idCompany) {

					if (confirm("Are you sure you want to copy all values from last month?")) {

						var response = $.ajax({
							url: "list-owner.php",
							type: "POST",
							async: true,
							data: ({
								"a": "copy_revenue",
								"idCompany": idCompany,
								"fromDate": fromDate,
								"toDate": toDate
							})
						}).done(function (responseText) {
							var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
							if (result === null) {
								alert("JSON Failed: " + responseText);
								return false;
							}
							if (result.status == 1) {
								alert("Values copied from last month.");
							} else {
								alert(result.error);
							}
							display('dialog_revenue_listowners', {'report_date': toDate, 'idCompany': idCompany});

						});
					}
				}

				function invoiceStatus(idCompany, date, paid, idFeedIn, url) {
					var response = $.ajax({
						url: "list-owner.php",
						type: "POST",
						async: true,
						data: ({
							"a": "invoice_status",
							"idCompany": idCompany,
							"date": date,
							"invoiceNumber": $("#invoice_number").val(),
							"paymentDate": $("#paymentDate").val(),
							"userId": $("#userId").val(),
							"email": $("#invoice_email").prop("checked") ? 1 : 0
						})
					}).done(function (responseText) {
						var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
						if (result === null) {
							alert("JSON Failed: " + responseText);
							return false;
						}
						alert(result.error);
						display('dialog_revenue_listowners', {'report_date': date, 'idCompany': idCompany, 'idFeedIn': idFeedIn, 'url': url});
					});
				}
			</script>

			<?php
			break;

	}
	exit;
}

$title = 'Reports';
include( INCLUDES . "c_header.php" );
?>
<body>
<script type="text/javascript">
	$(document).ready(function () {
		display('dialog_revenue_listowners');
	});
</script>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>List Owner - Online</h2>

	<div class="hidden-custom" id="dialog_revenue_listowners"></div>
</div>

</body>
</html>
