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
				setRevenueValue( $date, $idFeedIn, $urlTrim, $idCompany, $_REQUEST['value'] );
			}
			$result['status'] = 1;
			break;
        
		case 'removeUrl':
			$c = true;
			$result['error'] = 'Failed when attempting to remove URL.';
			$queryResult = removeListcodeURL( $_REQUEST['idUrl'] );
			if( !$queryResult ) {
				$c = false;
			}

			if($c){
				$result['status'] = 1;
			}
			break;

		case 'saveUrls':
			$c = true;
			$result['error'] = 'Failed when attempting to add URL.';

			if( empty ( $_REQUEST['idListcode']) || empty( $_REQUEST['urls'] ) ) {
				$c = false;
				$result['error'] = 'No values provided';
			}

			if( $c ) {
				$urls = explode( "\n", $_REQUEST['urls'] );

				// First pass to validate the data
				foreach( $urls as $url ) {
					if( strlen( trim( $url ) ) > 0 && !filter_var( 'http://' . trim( $url ), FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED ) ) {
						$c = false;
						$result['error'] = "Invalid URL: {$url}";
						break;
					}
				}

				if( $c ) {
					// If validation passed, try to add the data to the DB
					foreach( $urls as $url ) {
						if( strlen( trim( $url ) ) > 0 ) {
							$queryResult = addListcodeURL( $_REQUEST['idListcode'], trim ( $url ) );
							if( !$queryResult ) {
								$c = false;
							}
						}
					}
				}
			}

			if( $c ) {
				$result['status'] = 1;
			}
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

<p><a href="#" class="nonLink" onclick="display('dialog_mapping');">Mapping Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_revenue');">Revenue Report</a></p>
<div class="hidden" id="dialog_mapping"></div>
<div class="hidden" id="dialog_revenue"></div>

<?php
		break;

		case 'dialog_mapping':
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
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $feeds as $feed ) {

					$urls = getIncomingUrls( $feed->label );
					if( $urls ) {
						foreach( $urls as $url ) {
							$found = false;
							$populations = getPopulationMappingIn( $feed->idFeedIn );
							if( $populations ) {
								foreach( $populations as $population ) {
									if( $population->enabled && ( empty( $population->filterTypeUrl ) ||  checkPopulationFilters( $population, $url->urlTrim, '', '' ) ) ) {
										print "\t<tr class=\"bgGray\">\n";
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->name ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $population->outName ) );
										printf( "\t\t<td>%s</td>\n", htmlspecialchars( $population->outLabel ) );
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
            sort_types:['String','String','String','String','String']
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
			$feeds = getIncomingFeeds();
			if( $feeds ) {
				print "<table id=\"mapping_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Company</td>\n";
				print "\t\t<td>Feed</td>\n";
				print "\t\t<td>URL</td>\n";
				print "\t\t<td>TOTAL</td>\n";
				$companies = getOutgoingCompanies();
				if( $companies ) {
					foreach( $companies as $company ) {
						printf( "\t\t<td>%s</td>\n", htmlspecialchars( $company->name ) );
					}
				}
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $feeds as $feed ) {

					$urls = getIncomingUrls( $feed->label );
					if( $urls ) {
						foreach( $urls as $url ) {
							print "\t<tr class=\"bgGray\">\n";
							printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->name ) );
							printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
							printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
							printf( "\t\t<td class=\"revenue calculate\">%s</td>\n", htmlspecialchars( 't' ) );
							if( $companies ) {
								foreach( $companies as $company ) {
									$value = getRevenueValue( '201310', $feed->idFeedIn, $url->urlTrim, $company->idCompany );
									printf( "\t\t<td class=\"revenue sum\"><input type=\"text\" name=\"%s\" value=\"%s\" /></td>\n", htmlspecialchars( base64_encode( '201310' . '|' . $feed->idFeedIn . '|' . $url->urlTrim . '|' . $company->idCompany ) ), htmlspecialchars( $value ) );
								}
							}
							print "\t</tr>\n";
						}
					}
				}
				print "\t</tbody>\n";
				print "</table>\n";
?>
<script type="text/javascript">
$(document).ready(function(){
    $("input").each(function() {
        var that = this; // fix a reference to the <input> element selected
		newSum.call(that);
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

            newSum.call(that); // pass in a context for newsum():
                               // call() redefines what "this" means
                               // so newSum() sees 'this' as the <input> element
        });
    });
});
function newSum() {
  var sum = 0.00;
  var thisRow = $(this).closest('tr');

  thisRow.find('td.sum input:text').each( function(){
	if(this.value != '' && this.value != null && isNaN(this.value) == false) {
 	   sum += parseFloat(this.value); // or parseInt(this.value,10) if appropriate
	}
  });

  thisRow.find('td.calculate').html('$' + sum); // It is an <input>, right?
}
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

function manageListcode(action, idListcode){
        
	if(action == "new"){ e = "#new_listcode_"; c = 'new'; } else { e = "#edit_"+idListcode+"_listcode_"; c = 'edit'; }
	idListcode = $(e+'idListcode').val();
	description = $(e+'description').val();
	idCompany = $(e+'idCompany').val();

	var response = $.ajax({
		url: "mgr_listcodes.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "manageListcode"
			, "action" : action
			, "idListcode": idListcode
			, "description":description
			, "idCompany":idCompany
		})
                
	}).done(function(responseText){
                        
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) {
			alert("JSON Failed: "+responseText);
		}
		if(result.status == 1){
			if(c == 'new'){
				alert("Successfully created new listcode.");
				closeContent('dialog_newlistcode');
			} else {
				alert("Successfully saved updated listcode settings.");
				closeContent('dialog_editlistcode');
			}

			display(
				'companyList'
				, {
					'callbackParams': {
						'idCompany': idCompany
					}
				}
				, true
				, function(o) {
					toggleHidden(
						'companyListcodes'
						, {'sub':o.idCompany, 'hiddenText':'Show Listcodes', 'shownText':'Close' }
					);
				}
			);

		} else {
			alert(result.error);
		}
	});
}

function removeUrl(idUrl, options){
        var response = $.ajax({
            url: "mgr_listcodes.php",
            type: "POST",
            async: true,
            data: ({
                "a" : "removeUrl"
                , "idUrl": idUrl
            })
        }).done(function(responseText){
            var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
            if(result===null) {
                alert("JSON Failed: "+responseText);
            } else {
                if(result.status == 1){
					toggleHidden('url_' + idUrl, '');
                } else {
                    alert(result.error);
                }
            }
        });
}

function saveUrls(idListcode, idCompany){

		urls = $('#add_urls_' + idListcode).val();

        var response = $.ajax({
            url: "mgr_listcodes.php",
            type: "POST",
            async: true,
            data: ({
                "a" : "saveUrls"
				, "idListcode": idListcode
                , "urls": urls
            })
        }).done(function(responseText){
            var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
            if(result===null) {
                alert("JSON Failed: "+responseText);
            } else {
                if(result.status == 1){
                    display(
                        'companyList'
                        , {
                            'callbackParams': {
                                'idCompany': idCompany
                            }
                        }
                        , true
                        , function(o){
                            toggleHidden(
                                'companyListcodes'
                                , {'sub':o.idCompany, 'hiddenText':'Show Listcodes', 'shownText':'Close' }
                            );
                        }
					);

                } else {
                    alert(result.error);
                }
            }
        });
}


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
<script src="/leadadmin/js/TableFilter/tablefilter_all_min.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/sortabletable.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/tfAdapter.sortabletable.js" language="javascript" type="text/javascript"></script> 
</body>
</html>
