<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['d'])){
	switch($_REQUEST['d']){
		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;
	}
	exit;
}

$title = 'Phone Leads Report';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">
<h2>Phone Leads Report</h2>

<?php
	$dateStart = !empty( $_REQUEST['dateStart'] ) ? $_REQUEST['dateStart'] : date( 'Y-m-d', strtotime( 'yesterday' ) );
	$dateEnd = !empty( $_REQUEST['dateEnd'] ) ? $_REQUEST['dateEnd'] : date( 'Y-m-d' );
?>

<form class="form-inline">
<input type="text" name="dateStart" class="dateSelector" value="<?php echo htmlentities( $dateStart, ENT_QUOTES | ENT_HTML5 ); ?>" /> to <input type="text" name="dateEnd" class="dateSelector" value="<?php echo htmlentities( $dateEnd, ENT_QUOTES | ENT_HTML5 ); ?>" /> <input class="btn btn-primary" type="submit" name="submit" value="Update" />
</form>

<?php

$outgoingFeeds = $leads->getOutboundFeeds( null, 'active', 'phone' );

if( empty( $outgoingFeeds ) ) {

	print '<p>Sorry, there were no incoming phone feeds found.</p>' . PHP_EOL;

} else {

	$lastCompany = '';
	$companyAccepted = $companyRejected = 0;
	foreach( $outgoingFeeds as $outgoingFeed ) {
		if( $outgoingFeed->name !== $lastCompany ) {
			if( !empty( $lastCompany ) ) {
				print '</tbody>' . PHP_EOL;

				print '<tfoot>' . PHP_EOL;
				print '<tr>' . PHP_EOL;
				printf( '<td colspan="2">COMPANY TOTAL</td>' . PHP_EOL,
					htmlentities( $outgoingFeed->label )
				);
				printf( '<td>%s</td>' . PHP_EOL,
					number_format( $companyAccepted, 0 )
				);
				printf( '<td>%s</td>' . PHP_EOL,
					number_format( $companyRejected, 0 )
				);
				print '</tr>' . PHP_EOL;
				print '</tfoot>' . PHP_EOL;
				print '</table>' . PHP_EOL;
			}

			$companyAccepted = $companyRejected = 0;

			printf( '<h3>%s</h3>' . PHP_EOL,
				htmlentities( $outgoingFeed->name )
			);
			print '<table class="table table-bordered table-condensed table-striped">' . PHP_EOL;
			print '<thead>' . PHP_EOL;
			print '<tr>' . PHP_EOL;
			print '<th style="width:40%;">Feed</th>' . PHP_EOL;
			print '<th style="width:40%;">URL</th>' . PHP_EOL;
			print '<th style="width:10%;">Accepted</th>' . PHP_EOL;
			print '<th style="width:10%;">Rejected</th>' . PHP_EOL;
			print '</tr>' . PHP_EOL;
			print '</thead>' . PHP_EOL;
			$lastCompany = $outgoingFeed->name;
			print '<tbody>' . PHP_EOL;
		}

		$stats = $leads->getOutboundURLStatsReport( $outgoingFeed->idFeedOut, array(), 'total', $dateStart, $dateEnd, 'url' );

		$feedAccepted = $feedRejected = 0;

		$saveHtml = '';
		foreach( $stats as $stat ) {
			$saveHtml .= sprintf( '<tr class="collapse leads-toggle-%s">' . PHP_EOL,
				htmlentities( $outgoingFeed->idFeedOut )
			);
			$saveHtml .= sprintf( '<td>----&gt; %s</td>' . PHP_EOL,
				htmlentities( $outgoingFeed->label )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				htmlentities( $stat['url'] )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				number_format( $stat['accepted'], 0 )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				number_format( $stat['rejected'], 0 )
			);
			$saveHtml .= '</tr>' . PHP_EOL;

			$companyAccepted += $stat['accepted'];
			$companyRejected += $stat['rejected'];
			$feedAccepted += $stat['accepted'];
			$feedRejected += $stat['rejected'];
		}

		printf( '<tr class="warning" data-toggle="collapse" data-target=".leads-toggle-%s">' . PHP_EOL,
				htmlentities( $outgoingFeed->idFeedOut )
		);
		printf( '<td>%s (%s)</td>' . PHP_EOL,
			htmlentities( $outgoingFeed->label ),
			htmlentities( $outgoingFeed->description )
		);
		print '<td><strong>FEED TOTAL</strong></td>' . PHP_EOL;
		printf( '<td><strong>%s</strong></td>' . PHP_EOL,
				number_format( $feedAccepted, 0 )
		);
		printf( '<td><strong>%s</strong></td>' . PHP_EOL,
				number_format( $feedRejected, 0 )
		);
		print '</tr>' . PHP_EOL;

		echo $saveHtml;

	}

	print '</tbody>' . PHP_EOL;

	print '<tfoot>' . PHP_EOL;
	print '<tr>' . PHP_EOL;
	printf( '<td colspan="2">COMPANY TOTAL</td>' . PHP_EOL,
		htmlentities( $outgoingFeed->label )
	);
	printf( '<td>%s</td>' . PHP_EOL,
		number_format( $companyAccepted, 0 )
	);
	printf( '<td>%s</td>' . PHP_EOL,
		number_format( $companyRejected, 0 )
	);
	print '</tr>' . PHP_EOL;
	print '</tfoot>' . PHP_EOL;
	print '</table>' . PHP_EOL;

}


$incomingFeeds = $leads->getInboundFeeds( null, 'active', 'phone' );

if( empty( $incomingFeeds ) ) {

	print '<p>Sorry, there were no incoming phone feeds found.</p>' . PHP_EOL;

} else {

	$lastCompany = '';
	$companyAccepted = $companyRejected = 0;
	foreach( $incomingFeeds as $incomingFeed ) {
		if( $incomingFeed->name !== $lastCompany ) {
			if( !empty( $lastCompany ) ) {
				print '</tbody>' . PHP_EOL;

				print '<tfoot>' . PHP_EOL;
				print '<tr>' . PHP_EOL;
				printf( '<td colspan="2">COMPANY TOTAL</td>' . PHP_EOL,
					htmlentities( $incomingFeed->label )
				);
				printf( '<td>%s</td>' . PHP_EOL,
					number_format( $companyAccepted, 0 )
				);
				printf( '<td>%s</td>' . PHP_EOL,
					number_format( $companyRejected, 0 )
				);
				print '</tr>' . PHP_EOL;
				print '</tfoot>' . PHP_EOL;
				print '</table>' . PHP_EOL;
			}

			$companyAccepted = $companyRejected = 0;

			printf( '<h3>%s</h3>' . PHP_EOL,
				htmlentities( $incomingFeed->name )
			);
			print '<table class="table table-bordered table-condensed table-striped">' . PHP_EOL;
			print '<thead>' . PHP_EOL;
			print '<tr>' . PHP_EOL;
			print '<th style="width:40%;">Feed</th>' . PHP_EOL;
			print '<th style="width:40%;">URL</th>' . PHP_EOL;
			print '<th style="width:10%;">Accepted</th>' . PHP_EOL;
			print '<th style="width:10%;">Rejected</th>' . PHP_EOL;
			print '</tr>' . PHP_EOL;
			print '</thead>' . PHP_EOL;
			$lastCompany = $incomingFeed->name;
			print '<tbody>' . PHP_EOL;
		}

		$stats = $leads->getInboundURLStatsReport( $incomingFeed->idFeedIn, array(), 'total', $dateStart, $dateEnd, 'url' );

		$feedAccepted = $feedRejected = 0;

		$saveHtml = '';
		foreach( $stats as $stat ) {
			$saveHtml .= sprintf( '<tr class="collapse leads-toggle-%s">' . PHP_EOL,
				htmlentities( $incomingFeed->idFeedIn )
			);
			$saveHtml .= sprintf( '<td>----&gt; %s</td>' . PHP_EOL,
				htmlentities( $incomingFeed->label )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				htmlentities( $stat['url'] )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				number_format( $stat['accepted'], 0 )
			);
			$saveHtml .= sprintf( '<td>%s</td>' . PHP_EOL,
				number_format( $stat['rejected'], 0 )
			);
			$saveHtml .= '</tr>' . PHP_EOL;

			$companyAccepted += $stat['accepted'];
			$companyRejected += $stat['rejected'];
			$feedAccepted += $stat['accepted'];
			$feedRejected += $stat['rejected'];
		}

		printf( '<tr class="warning" data-toggle="collapse" data-target=".leads-toggle-%s">' . PHP_EOL,
				htmlentities( $incomingFeed->idFeedIn )
		);
		printf( '<td>%s (%s)</td>' . PHP_EOL,
			htmlentities( $incomingFeed->label ),
			htmlentities( $incomingFeed->description )
		);
		print '<td><strong>FEED TOTAL</strong></td>' . PHP_EOL;
		printf( '<td><strong>%s</strong></td>' . PHP_EOL,
				number_format( $feedAccepted, 0 )
		);
		printf( '<td><strong>%s</strong></td>' . PHP_EOL,
				number_format( $feedRejected, 0 )
		);
		print '</tr>' . PHP_EOL;

		echo $saveHtml;

	}

	print '</tbody>' . PHP_EOL;

	print '<tfoot>' . PHP_EOL;
	print '<tr>' . PHP_EOL;
	printf( '<td colspan="2">COMPANY TOTAL</td>' . PHP_EOL,
		htmlentities( $incomingFeed->label )
	);
	printf( '<td>%s</td>' . PHP_EOL,
		number_format( $companyAccepted, 0 )
	);
	printf( '<td>%s</td>' . PHP_EOL,
		number_format( $companyRejected, 0 )
	);
	print '</tr>' . PHP_EOL;
	print '</tfoot>' . PHP_EOL;
	print '</table>' . PHP_EOL;

}

?>

</div>

</body>
</html>
