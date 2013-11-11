<?php 
session_start();
$mysqlErrorSource = 'Manager - Reports';
include("../c_config.php");
$forceMysqlLogFile = SITE_ROOT."error".FD."log_reports"; 
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.
include(LIVE_ROOT."processFunctions.php");

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){

		case 'save_revenue':

			if( ( $string = base64_decode( $_REQUEST['field'] ) ) !== FALSE ) {
				list( $date, $idFeedIn, $urlTrim, $idCompany ) = explode( '|', $string );
				$value = $_REQUEST['value'];
				if( empty( $value ) ) $value = null;
				setRevenueValue( $date, $idFeedIn, $urlTrim, $idCompany, $value );
			}
			$result['status'] = 1;
			break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
		case 'errorCount':		
			$errorCount = getErrorCount();
			if($errorCount === false){ echo "X"; } else { echo $errorCount; }
		break;
		case 'errorList':
			$errorList = getErrors();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("errorList");' >Close [X]</a>
</div>
<?php
if($errorList === false){ echo "Error fetching errors list."; } 
elseif($errorList == 0){ echo "No errors listed for today."; } 
else { 
	foreach($errorList as $error){ 
?>
<p>(<?php echo $error->stamp; ?>) [<?php echo $error->origination; ?>] : <?php echo $error->description; ?></p>
<?php
	}
}
		break;
        case 'reports':
?>

<p><a href="#" class="nonLink" onclick="display('dialog_mapping'); closeContent('dialog_revenue');">Mapping Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_revenue'); closeContent('dialog_mapping');">Revenue Report</a></p>
<div class="hidden" id="dialog_mapping"></div>
<div class="hidden" id="dialog_revenue"></div>

<?php
		break;

		case 'dialog_mapping':
?>
<div class="aRight">
    <a href="#" class="nonLink" onclick="closeContent('dialog_mapping');">Close [X]</a>
</div>
<?php
			$feeds = getIncomingFeeds();
			if( $feeds ) {
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
				foreach( $feeds as $feed ) {

					$populations = getPopulationMappingIn( $feed->idFeedIn );
					$urls = getIncomingUrls( $feed->label );
					if( $urls ) {
						foreach( $urls as $url ) {
							$found = false;
							if( $populations ) {
								foreach( $populations as $population ) {
									if( $population->enabled && ( empty( $population->filterTypeUrl ) ||  checkPopulationFilters( $population, $url->urlTrim, '', '' ) ) ) {
										print "\t<tr class=\"bgGray\">\n";
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->name ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $population->outName ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $population->outLabel ) );
										print "\t\t<td>Y</td>\n";
										print "\t</tr>\n";
										$found = true;
									}
								}
							}
							if( !$found ) {
								print "\t<tr class=\"bgGray\">\n";
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->name ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
								printf( "\t\t<td>-</td>\n" );
								printf( "\t\t<td>-</td>\n" );
								print "\t\t<td>N</td>\n";
								print "\t</tr>\n";
							}
						}
					}
				}
				print "\t</tbody>\n";
				print "</table>\n";
?>
<script type="text/javascript">
    var props = {  
		base_path: '/leadadmin/js/TableFilter/',
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
    }  
    var tf = setFilterGrid("mapping_report",props);  
</script>
<?php
			} else {
				print "Cannot load list of incoming feeds.";
			}

		break;

		case 'dialog_revenue':
			if( empty( $_REQUEST['options']['report_date'] ) || strlen( $_REQUEST['options']['report_date'] ) != 6 ) $reportDate = date('Ym');
			else $reportDate = $_REQUEST['options']['report_date'];
?>
<div class="aRight">
    <a href="#" class="nonLink" onclick="closeContent('dialog_revenue');">Close [X]</a>
</div>
<p><strong>Report Date:</strong>
<select name="report_date">
<?php 
	for($y = date('Y'); $y >= 2012; $y--) {
		for($m = 12; $m > 0; $m--) {
			$format_month = str_pad( $m, 2, '0', STR_PAD_LEFT );
			printf(' <option onclick="display(\'dialog_revenue\', { \'report_date\': \'%s\' });" value="%s"%s>%s</option>',
					$y . $format_month, $y . $format_month, ( $y == substr( $reportDate, 0, 4) && $format_month == substr( $reportDate, 4, 2 ) ) ? ' selected="selected"' : '', $y . '-' . $format_month );
		}
	}
?>
</select>
</p>

<?php

			$feeds = getIncomingFeeds();
			if( $feeds ) {
				print "<table id=\"mapping_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Feed</td>\n";
				print "\t\t<td>URL</td>\n";
				print "\t\t<td>TOTAL</td>\n";
				$companies = getOutgoingCompanies();
				if( $companies ) {
					foreach( $companies as $company ) {
						printf( "\t\t<td class=\"rotate\"><div><span>%s</span></div></td>\n", htmlspecialchars( $company->name ) );
					}
				}
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				$row = 0;
				$col = 65;
				$prevRow = 0;
				foreach( $feeds as $feed ) {

					if( !isset( $subtotal ) ) { $subtotal = $feed->name; $foundUrl = false; }
					if( $feed->name != $subtotal ) {
						if( $foundUrl ) {
							$col = 65;
							print "\t<tr class=\"bgGray subtotal\">\n";
							printf( "\t\t<td colspan=\"2\"><strong>%s</strong></td>\n", htmlspecialchars( $subtotal ) );
							printf( "\t\t<td class=\"revenue\" id=\"%s\" data-format=\"$0,0.00\" data-formula=\"SUM(%s,%s)\"></td>\n", chr( $col ) . ++$row, '$B' . ($prevRow+1), '$' . chr( 65 + sizeOf( $companies ) ) . ($row-1) );
							for( $i = 0; $i < sizeOf( $companies ); $i++ ) {
								printf( "\t\t<td class=\"revenue\" id=\"%s\" data-format=\"$0,0.00\" data-formula=\"SUM(%s,%s)\"></td>\n", chr( ++$col ) . $row, '$' . chr( $col ) . ($prevRow+1), '$' . chr( $col ) . ($row-1)  );
							}
							print "\t</tr>\n";
							$prevRow = $row;
						}
						$foundUrl = false;
						$subtotal = $feed->name;
					}

					$populations = getPopulationMappingIn( $feed->idFeedIn );
					$urls = getIncomingUrls( $feed->label );
					if( $urls ) {
						foreach( $urls as $url ) {

							$foundPopulation = false;
							if( $populations ) {
								foreach( $populations as $population ) {
									if( $population->enabled && ( empty( $population->filterTypeUrl ) ||  checkPopulationFilters( $population, $url->urlTrim, '', '' ) ) ) {
										$foundPopulation = true;
									}
								}
							}

							if( $foundPopulation ) {
								$col = 65;
								print "\t<tr class=\"bgGray\">\n";
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
								printf( "\t\t<td class=\"revenue\" id=\"%s\" data-format=\"$0,0.00\" data-formula=\"SUM(%s,%s)\"></td>\n", chr( $col ) . ++$row, '$B' . $row, '$' . chr( 65 + sizeOf( $companies ) ) . $row );
								if( $companies ) {
									foreach( $companies as $company ) {
										$value = getRevenueValue( $reportDate, $feed->idFeedIn, $url->urlTrim, $company->idCompany );
										printf( "\t\t<td class=\"revenue\"><input type=\"text\" id=\"%s\" data-format=\"$0,0.00\" name=\"%s\" value=\"%s\" /></td>\n", chr( ++$col ) . $row, htmlspecialchars( base64_encode( $reportDate . '|' . $feed->idFeedIn . '|' . $url->urlTrim . '|' . $company->idCompany ) ), htmlspecialchars( $value ) );
									}
								}
								print "\t</tr>\n";
								$foundUrl = true;
							}
						}
					}
				}

				if( $foundUrl ) {
					$col = 65;
					print "\t<tr class=\"bgGray subtotal\">\n";
					printf( "\t\t<td colspan=\"2\"><strong>%s</strong></td>\n", htmlspecialchars( $subtotal ) );
					printf( "\t\t<td class=\"revenue\" id=\"%s\" data-format=\"$0,0.00\" data-formula=\"SUM(%s,%s)\"></td>\n", chr( $col ) . ++$row, '$B' . ($prevRow+1), '$' . chr( 65 + sizeOf( $companies ) ) . ($row-1) );
					for( $i = 0; $i < sizeOf( $companies ); $i++ ) {
						printf( "\t\t<td class=\"revenue\" id=\"%s\" data-format=\"$0,0.00\" data-formula=\"SUM(%s,%s)\"></td>\n", chr( ++$col ) . $row, '$' . chr( $col ) . ($prevRow+1), '$' . chr( $col ) . ($row-1)  );
					}
					print "\t</tr>\n";
				}

				print "\t</tbody>\n";
				print "</table>\n";
?>
<script type="text/javascript">
$(document).ready(function(){
	$('#mapping_report').calx(); 

    $("#mapping_report input").each(function() {
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
			} else {
				print "Cannot load list of incoming feeds.";
			}

		break;

	}
	exit;
}

$title = 'Reports';
include("c_header.php");
?>
<body>
<script type="text/javascript">
$(document).ready(function(){
    display('reports');
});
</script>
<div class='mainContainer'>
	<?php include('c_nav.php'); ?>
	<div style='margin: auto;'>
		<div class='fl' style='width: 100%;'>
			<div id='reports'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>
<script src="/leadadmin/js/calx-1.1.4/jquery-calx-1.1.4.min.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/tablefilter_all_min.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/sortabletable.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/tfAdapter.sortabletable.js" language="javascript" type="text/javascript"></script> 
</body>
</html>
