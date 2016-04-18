<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$title = 'Mapping Report';
include(INCLUDES."c_header.php");

if( isset( $_REQUEST['d'] ) ) {
    switch( $_REQUEST['d'] ) {
        case 'errorCount':
            Display::errorCount();
        break;

        case 'errorList':
            Display::errorList();
        break;
	}
	exit;
}

?>
<body>
<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Mapping Report</h2>

<?php

	$mappings = $leads->getUrlMappings();
	if( $mappings ) {
		print "<table id=\"mapping_report\" class=\"table table-bordered table-condensed table-striped\">\n";
		print "\t<thead>\n";
		print "\t<tr class=\"bgGray\">\n";
		print "\t\t<th>Incoming Company</th>\n";
		print "\t\t<th>Incoming Feed</th>\n";
		print "\t\t<th>Incoming URL</th>\n";
		print "\t\t<th>Outgoing Company</th>\n";
		print "\t\t<th>Outgoing Feed</th>\n";
		print "\t\t<th>Active</th>\n";
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
/*
	var tf = new TableFilter(document.querySelector('#mapping_report'), {
		base_path: '/leadadmin/libraries/tablefilter/',
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
*/
</script>
<?php
	} else {
		print "Cannot load list of incoming feeds.";
	}
?>

</div>

</body>
</html>
