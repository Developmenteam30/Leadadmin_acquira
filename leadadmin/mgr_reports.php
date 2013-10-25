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

		case 'manageListcode':
			$c = true;
			$result['error'] = 'Failed when attempting to add listcode.';
			$action = $_REQUEST['action'];
            
			if( 'new' == $action ){

				$queryResult = addListcode( $_REQUEST['idCompany'], $_REQUEST['description'] );

			} else {

				$queryResult = updateListcode( $_REQUEST['idListcode'], $_REQUEST['idCompany'], $_REQUEST['description'] );
			}

			if($c){
				$result['status'] = 1;
			}

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
<div class="hidden" id="dialog_mapping"></div>

<?php
		break;

        
		case 'dialog_mapping':
			$feeds = getIncomingFeeds();
			if( $feeds ) {
				print "<table class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr>\n";
				print "\t\t<td>Incoming Company</td>\n";
				print "\t\t<td>Incoming Feed</td>\n";
				print "\t\t<td>Incoming URL</td>\n";
				print "\t\t<td>Outgoing Company</td>\n";
				print "\t\t<td>Outgoing Feed</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				foreach( $feeds as $feed ) {

					$urls = getIncomingUrls( $feed->label );
					if( $urls ) {
						foreach( $urls as $url ) {
							$found = false;
							$populations = getPopulationMappingIn( $feed->idFeedIn );
							if( $populations ) {
								foreach( $populations as $population ) {
									if( $population->enabled && ( empty( $population->filterTypeUrl ) ||  checkPopulationFilters( $population, $url->urlTrim, '', '' ) ) ) {
										print "\t<tr>\n";
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
								print "\t<tr>\n";
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->name ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $feed->description ) );
								printf( "\t\t<td>%s</td>\n", htmlspecialchars( $url->urlTrim ) );
								printf( "\t\t<td colspan=\"2\">NONE</td>\n" );
								print "\t</tr>\n";
							}
						}
					}
				}
				print "</table>\n";
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
</body>
</html>
