<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

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

$title = 'Feed Map';
include( INCLUDES . "c_header.php" );

?>
<body>
<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Feed Mapping Report</h2>

	<?php

	$incomingFeeds = $leads->getInboundFeeds();
	if( $incomingFeeds ) {
		foreach( $incomingFeeds as $incomingFeed ) {
			$shownPopulation = false;
			$populations = $leads->getInboundPopulationSettings( $incomingFeed->idFeedIn );
			if( $populations ) {
				foreach( $populations as $population ) {
					if( empty( $population->cron )) {
						continue;
					}
					if( !$shownPopulation ) {
						printf( "<p>%s - %s (%s)</p>",
							htmlentities( $incomingFeed->name ),
							$incomingFeed->idFeedIn,
							htmlentities( $incomingFeed->label )
						);
						$shownPopulation = true;
					}
					printf( "<p style=\"margin-left:40px;\">%s - %s (%s) CPL: $%s RPL: $%s%s</p>" . PHP_EOL,
						htmlentities( $population->name ),
						$population->idFeedOut,
						htmlentities( $population->label ),
						$population->costPerLeadOverride !== null ? ( '<span style="color:red;">' . number_format( $population->costPerLeadOverride, 2 ) . '</span>*' ) : number_format( $incomingFeed->costPerLead, 2 ),
						number_format( $population->revenuePerLead, 2 ),
						!empty( $population->livedata ) ? ' [<span style="color:blue;font-weight:bold">LIVEDATA</span>]' : ( !empty( $population->waterfall ) ? ( ' [<span style="color:deeppink;font-weight:bold">WATERFALL - Priority: ' . $population->waterfallPriority . '</span>]' ) : '' )
					);
				}
			}
			if( $shownPopulation ) {
				print '<hr/>' . PHP_EOL;
			}

		}
	} else {
		print "Cannot load list of incoming feeds.";
	}
	?>

</div>

</body>
</html>
