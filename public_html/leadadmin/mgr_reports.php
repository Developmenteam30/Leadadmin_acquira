<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$mysqlErrorSource = 'Manager - Reports';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_reports"; 
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");
require_once(INCLUDES."processFunctions.php");

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){

		case 'save_revenue':

			$result['status'] = 0;
			$result['error'] = 'Invalid revenue value.';

			if( ( $string = base64_decode( $_REQUEST['field'] ) ) !== FALSE ) {
				list( $date, $idFeedIn, $idFeedOut, $url ) = explode( '|', $string );
				$value = $_REQUEST['value'];
				if( empty( $value ) || !is_numeric( $value ) ) $value = null;
				$leads->setRevenueValue( $date, $idFeedIn, $idFeedOut, $url, $value );
				$result['status'] = 1;
				$result['error'] = 'Saved';
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

<p><a href="#" class="nonLink" onclick="display('dialog_mapping'); closeContent('dialog_revenue');">Mapping Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_revenue'); closeContent('dialog_mapping');">Revenue Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_search_email'); closeContent('dialog_search_email_results');">Email Search Report</a></p>
<p><a href="#" class="nonLink" onclick="display('dialog_search_url'); closeContent('dialog_search_url_results');">URL Search Report</a></p>
<div class="hidden" id="dialog_mapping"></div>
<div class="hidden" id="dialog_revenue"></div>
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

			if( empty( $_REQUEST['options']['idCompany'] ) ) $idCompany = null;
			else $idCompany = $_REQUEST['options']['idCompany'];

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
			printf(' <option onclick="display(\'dialog_revenue\', { \'report_date\': \'%s\', \'idCompany\': \'%s\' });" value="%s"%s>%s</option>',
					$y . $format_month, $y . $format_month, $idCompany, ( $y == substr( $reportDate, 0, 4) && $format_month == substr( $reportDate, 4, 2 ) ) ? ' selected="selected"' : '', $y . '-' . $format_month );
		}
	}
?>
</select>
<select name="idCompany">
<?php 
	printf( '<option onclick="display(\'dialog_revenue\', { \'report_date\': \'%s\', \'idCompany\': \'\' });" value=""%s>SHOW ALL COMPANIES</option>',
					$reportDate, ( empty( $idCompany ) ? ' selected="selected"' : '' ) );
	$companies = $leads->getRevenueCompanies();
	if( $companies ) {
		foreach( $companies as $company ) {
			printf(' <option onclick="display(\'dialog_revenue\', { \'report_date\': \'%s\', \'idCompany\': \'%s\' });" value="%s"%s>%s</option>',
					$reportDate, $company['idCompany'], $company['idCompany'], ( $idCompany == $company['idCompany'] ? ' selected="selected"' : '' ), $company['name'] );
		}
	}
?>
</select>
</p>

<?php
			$mappings = $leads->getRevenueMappings( $idCompany, $reportDate );
			if( $mappings ) {
				print "<table id=\"revenue_report\" class=\"standard\">\n";
				print "\t<thead>\n";
				print "\t<tr class=\"bgGray\">\n";
				print "\t\t<td>Incoming Feed</td>\n";
				print "\t\t<td>Incoming URL</td>\n";
				print "\t\t<td>Outgoing Company</td>\n";
				print "\t\t<td>Outgoing Feed</td>\n";
				print "\t\t<td>Amount</td>\n";
				print "\t</tr>\n";
				print "\t</thead>\n";
				print "\t<tbody>\n";
				foreach( $mappings as $mapping ) {
					print "\t<tr class=\"bgGray\">\n";
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedIn'] . ': ' . $mapping['inDescription'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['url'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['outName'] ) );
					printf( "\t\t<td>%s</td>\n", htmlspecialchars( $mapping['idFeedOut'] . ': ' . $mapping['outDescription'] ) );
					printf( "\t\t<td class=\"revenue\"><input type=\"number\" min=\"0\" max=\"9999\" step=\"0.01\" name=\"%s\" value=\"%s\" /></td>\n", htmlspecialchars( base64_encode( $reportDate . '|' . $mapping['idFeedIn'] . '|' . $mapping['idFeedOut'] . '|' . $mapping['url'] ) ), ( empty( $mapping['revenue'] ) ? '' : htmlspecialchars( $mapping['revenue'] ) ) );
					print "\t</tr>\n";

				}
				print "\t</tbody>\n";
				print "</table>\n";
			}

?>
<script type="text/javascript">
$(document).ready(function(){
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
			$feeds = getIncomingFeeds( null );
			if($feeds === false){
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
			} else if($feeds == 0){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
			} else {
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
			foreach( $feeds as $feed ) {
				$records = getIncomingEmails( $feed->label, $email );
				if( is_array( $records ) ) {
					foreach( $records as $record ) {
?>
    <tr>
        <td><?php echo htmlspecialchars($feed->label); ?></td>
        <td><?php echo htmlspecialchars($record->listcode); ?></td>
        <td><?php echo htmlspecialchars($record->received); ?></td>
        <td><?php echo htmlspecialchars($record->url); ?></td>
        <td><?php echo htmlspecialchars($record->fname); ?></td>
        <td><?php echo htmlspecialchars($record->lname); ?></td>
        <td><?php echo htmlspecialchars($record->stamp); ?></td>
        <td><?php echo htmlspecialchars($record->ip); ?></td>
        <td><?php echo htmlspecialchars($record->dob); ?></td>
    </tr>
    <tr>
        <td><?php echo htmlspecialchars($record->addr); ?></td>
        <td><?php echo htmlspecialchars($record->addr2); ?></td>
        <td><?php echo htmlspecialchars($record->city); ?></td>
        <td><?php echo htmlspecialchars($record->state); ?></td>
        <td><?php echo htmlspecialchars($record->zip); ?></td>
        <td><?php echo htmlspecialchars($record->country); ?></td>
        <td><?php echo htmlspecialchars($record->landline); ?></td>
        <td><?php echo htmlspecialchars($record->cellphone); ?></td>
        <td><?php echo htmlspecialchars($record->gender); ?></td>
    </tr>
<?php
					}
				}
			}

		}
?>
	</tbody>
</table>
<?php
			$feeds = getOutgoingFeeds( );
			if($feeds === false){
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
			} else if($feeds == 0){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
			} else {
?>
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
			foreach( $feeds as $feed ) {
				$records = getOutgoingEmails( $feed->label, $email );
				if( is_array( $records ) ) {
					foreach( $records as $record ) {
?>
    <tr>
        <td><?php echo htmlspecialchars($feed->label); ?></td>
        <td><?php echo htmlspecialchars($record->listcode); ?></td>
        <td><?php echo htmlspecialchars($record->poststamp); ?></td>
        <td><?php echo htmlspecialchars($record->url); ?></td>
        <td><?php echo htmlspecialchars($record->fname); ?></td>
        <td><?php echo htmlspecialchars($record->lname); ?></td>
        <td><?php echo htmlspecialchars($record->stamp); ?></td>
        <td><?php echo htmlspecialchars($record->ip); ?></td>
        <td><?php echo htmlspecialchars($record->dob); ?></td>
    </tr>
    <tr>
        <td><?php echo htmlspecialchars($record->addr); ?></td>
        <td><?php echo htmlspecialchars($record->addr2); ?></td>
        <td><?php echo htmlspecialchars($record->city); ?></td>
        <td><?php echo htmlspecialchars($record->state); ?></td>
        <td><?php echo htmlspecialchars($record->zip); ?></td>
        <td><?php echo htmlspecialchars($record->country); ?></td>
        <td><?php echo htmlspecialchars($record->landline); ?></td>
        <td><?php echo htmlspecialchars($record->cellphone); ?></td>
        <td><?php echo htmlspecialchars($record->gender); ?></td>
    </tr>
<?php
					}
				}
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
			$feeds = getIncomingFeeds( null );
			if($feeds === false){
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
			} else if($feeds == 0){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
			} else {
?>
<p>Searching incoming feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="rejectionsTable">
    <thead>
        <tr>
            <th>Incoming feed</th>
            <th>Total records</th>
            <th>Last record received at</th>
        </tr>
    </thead>
    <tbody>
<?php
			foreach( $feeds as $feed ) {
				$records = getIncomingUrlSearch( $feed->label, $url );
				if( is_array( $records ) ) {
					foreach( $records as $record ) {
						if( $record->cnt > 0 ) {
?>
    <tr>
        <td><?php echo htmlspecialchars( $feed->label ); ?></td>
        <td><?php echo number_format( htmlspecialchars( $record->cnt ) ); ?></td>
        <td><?php echo htmlspecialchars( $record->received ); ?></td>
    </tr>
<?php
						}
					}
				}
			}

		}
?>
	</tbody>
</table>
<?php
			$feeds = getOutgoingFeeds( 'active' );
			if($feeds === false){
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
			} else if($feeds == 0){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
			} else {
?>
<p>Searching outgoing feeds for <strong><?php echo htmlspecialchars( $url ); ?></strong> ...</p>
<table class="rejectionsTable">
    <thead>
        <tr>
            <th>Outgoing feed</th>
            <th>Total records</th>
            <th>Last record sent at</th>
        </tr>
    </thead>
    <tbody>
<?php
			foreach( $feeds as $feed ) {
				$records = getOutgoingUrlSearch( $feed->label, $url );
				if( is_array( $records ) ) {
					foreach( $records as $record ) {
						if( $record->cnt > 0 ) {
?>
    <tr>
        <td><?php echo htmlspecialchars( $feed->label ); ?></td>
        <td><?php echo number_format( htmlspecialchars( $record->cnt ) ); ?></td>
        <td><?php echo htmlspecialchars( $record->poststamp ); ?></td>
    </tr>
<?php
						}
					}
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
<script src="/leadadmin/js/calx-1.1.4/jquery-calx-1.1.4.min.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/tablefilter_all_min.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/sortabletable.js" language="javascript" type="text/javascript"></script>
<script src="/leadadmin/js/TableFilter/tfAdapter.sortabletable.js" language="javascript" type="text/javascript"></script> 
</body>
</html>
