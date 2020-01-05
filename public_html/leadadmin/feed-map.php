<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$feedCategory = !empty( $_REQUEST['feedCategory'] ) ? $_REQUEST['feedCategory'] : null;
$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

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

	<form class="pull-right" id="feedCategory-select" method="get">
		<select id="feedCategory" name="feedCategory">
			<option value="phone"<?php if( 'phone' === $feedCategory ) {
				print ' selected="selected"';
			} ?>>Show phone category
			</option>
			<option value="email"<?php if( 'email' === $feedCategory ) {
				print ' selected="selected"';
			} ?>>Show email category
			</option>
			<option value="ppc"<?php if( 'ppc' === $feedCategory ) {
				print ' selected="selected"';
			} ?>>Show PPC category
			</option>
			<option value=""<?php if( null === $feedCategory ) {
				print ' selected="selected"';
			} ?>>Show all categories
			</option>
		</select>
		<select id="status" name="status">
			<option value="active"<?php if( 'active' === $status ) {
				print ' selected="selected"';
			} ?>>Show active feeds
			</option>
			<option value="hidden"<?php if( 'hidden' === $status ) {
				print ' selected="selected"';
			} ?>>Show hidden feeds
			</option>
			<option value="retired"<?php if( 'retired' === $status ) {
				print ' selected="selected"';
			} ?>>Show retired feeds
			</option>
			<option value=""<?php if( null === $status ) {
				print ' selected="selected"';
			} ?>>Show all feeds
			</option>
		</select>
	</form>


	<?php

	$incomingFeeds = $leads->getInboundFeeds( null, $status, $feedCategory );
	if( $incomingFeeds ) {
		foreach( $incomingFeeds as $incomingFeed ) {
			$shownPopulation = false;
			$populations = $leads->getInboundPopulationSettings( $incomingFeed->idFeedIn );
			if( $populations ) {
				foreach( $populations as $population ) {
					if( !$shownPopulation ) {
						printf( "<p>%s - %s (%s: %s)%s%s</p>",
							htmlentities( $incomingFeed->name ),
							$incomingFeed->idFeedIn,
							htmlentities( $incomingFeed->label ),
							htmlentities( $incomingFeed->description ),
							( !empty( $incomingFeed->dailyLimit ) && !empty( $incomingFeed->chokePercent ) ) ? ( ' [<span style="color:brown;font-weight:bold;">Limit: ' . $incomingFeed->dailyLimit . ' Eff: ' . round( $incomingFeed->dailyLimit / ( ( 100 - $incomingFeed->chokePercent ) * .01 ) ) . '</span>]' ) : ( !empty( $incomingFeed->dailyLimit ) ? ' [<span style="color:brown;font-weight:bold;">Limit: ' . $incomingFeed->dailyLimit . '</span>]' : '' ),
							( !empty( $incomingFeed->chokePercent ) ? ' [<span style="color:red;font-weight:bold;">Choke: ' . $incomingFeed->chokePercent . '%</span>]' : '' )
						);
						$shownPopulation = true;
					}
					printf( "<p style=\"margin-left:40px;\">%s - %s (%s: %s) CPL: $%s RPL: $%s%s%s%s%s%s</p>" . PHP_EOL,
						htmlentities( $population->name ),
						$population->idFeedOut,
						htmlentities( $population->label ),
						htmlentities( $population->description ),
						$population->costPerLeadOverride !== null ? ( '<span style="color:red;">' . number_format( $population->costPerLeadOverride, 2 ) . '</span>*' ) : number_format( $incomingFeed->costPerLead, 2 ),
						number_format( $population->revenuePerLead, 2 ),
                        'category' === $population->populationType ? ' [<span style="color:#9b59b6;font-weight:bold;">POP CAT ' . strtoupper( $population->feedCategory ) . '</span>]' : '',
						$population->queueType == 'livedata' ? ' [<span style="color:blue;font-weight:bold">LIVEDATA</span>]' :
							( $population->queueType == 'waterfall' ? ( ' [<span style="color:deeppink;font-weight:bold">WATERFALL STD - Priority: ' . $population->waterfallPriority . '</span>]' ) :
								( $population->queueType == 'waterfallLimit' ? ( ' [<span style="color:deeppink;font-weight:bold">WATERFALL LIMIT - Priority: ' . $population->waterfallPriority . '</span>]' ) :
									( $population->queueType == 'waterfallLimitLive' ? ( ' [<span style="color:deeppink;font-weight:bold">WATERFALL LIMIT LIVE - Priority: ' . $population->waterfallPriority . '</span>]' ) :
										'' ) ) ),
                        ( !empty( $population->dailyLimit ) ? ' [<span style="color:brown;font-weight:bold;">Limit: ' . $population->dailyLimit . '</span>]' : '' ),
						( !empty( $population->delay ) ? ( ' [<span style="color:green;font-weight:bold;">Delay ' . ( !empty( $population->delayDump ) ? ' Dump' : '' ) . ': ' . ( ( $population->delay % ( 60 * 24 ) ) == 0 ? ( $population->delay / ( 60 * 24 ) . ' Days' ) : ( $population->delay . ' Minutes' ) ) . '</span>]' ) : '' ),
                        ( empty( $population->cron ) ? ' [<span style="color:orange;font-weight:bold;">PAUSED</span>]' : '' )
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

<script type="text/javascript">
	$('#feedCategory-select select').change(function (e) {
		e.preventDefault();
		$('#feedCategory-select').submit();
	});
</script>

</body>
</html>
