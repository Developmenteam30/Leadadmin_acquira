<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$statsStart = !empty( $_REQUEST['statsStart'] ) ? $_REQUEST['statsStart'] : date( 'Y-m-d' );
$statsEnd = !empty( $_REQUEST['statsEnd'] ) ? $_REQUEST['statsEnd'] : date( 'Y-m-d' );

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);

	switch( $_REQUEST['a'] ) {
		case "addNewNote":
			$c = true;
			$result['error'] = 'Failed when trying to add a new company note.';

			if( $c && empty( $_REQUEST['companyId'] ) ) {
				$result['error'] = 'Please supply an company ID.';
				$c = false;
			}

			if( $c ) {
				$entry = $leads->getCompany( $_REQUEST['companyId'] );
				if( empty( $entry ) ) {
					$result['error'] = 'There is no company that exists by that ID.';
					break;
				}
			}

			if( $c && empty( $_REQUEST['note'] ) ) {
				$result['error'] = 'Please type your note.';
				$c = false;
			}

			if( $c ) {
				$noteId = $leads->addCompanyNote( array(
					'companyId' => $_REQUEST['companyId'],
					'userId' => LeadsSession::getUserId(),
					'timestamp' => date( 'Y-m-d H:i:s' ),
					'note' => trim( $_REQUEST['note'] ),
				) );
				if( null === $noteId ) {
					$c = false;
					$result['error'] = 'Error adding this company note to the database.';
				}
			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully added new company note.';
			}
			break;
	}

	echo json_encode( $result );
	exit;
}

if( isset( $_REQUEST['d'] ) ) {
	switch( $_REQUEST['d'] ) {

		case 'feedinc':

			$idFeedIn = intval( $_REQUEST['options']['sub'] );

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
					$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed' );
				}
			}

			$urls = $leads->getInboundURLStats( $idFeedIn );
			?>
			<?php
			if( empty( $urls ) ) {
				?>
				<p>No URLs received today.</p>
				<?php
			} else {
				?>
				<table class="table table-bordered table-condensed">
					<tbody>
					<?php
					foreach( $urls as $url ) {
						?>
						<tr>
							<td><?php echo $url['url']; ?></td>
							<td class="aRight dashboard-incoming-col-small"><?php echo number_format( $url['accepted'], 0 ); ?></td>
							<td class="aRight dashboard-incoming-col-small"><?php echo number_format( $url['rejected'], 0 ); ?></td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
				<?php
			}
			?>
			<?php

			break;

		case "dialog_companynotes":
			$companyId = !empty( $_REQUEST['companyId'] ) ? $_REQUEST['companyId'] : '';
			$company = $leads->getCompany( $companyId );

			if( empty( $company ) ) {
				?>
				<p>There is no company that exists by that ID.</p>
				<?php
				break;

			}

			$fields = array(
				array(
					'id' => 'note',
					'label' => 'Add a Note',
					'type' => 'textarea',
				),
				array(
					'id' => 'companyId',
					'type' => 'hidden',
					'value' => $company->idCompany,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewNote',
				),
			);

			Display::displayForm( 'note_company', $fields );

			$notes = $leads->getCompanyNotes( $company->idCompany );
			if( empty( $notes ) || !is_array( $notes ) ) {
				print '<p>There are no notes on file for this company.</p>' . PHP_EOL;
			} else {
				foreach( $notes as $note ) {
					printf( '<hr/><p>On <strong>%s</strong> at %s, <strong>%s</strong> wrote:<br/>%s</p>' . PHP_EOL,

						date( 'D, M jS, Y', strtotime( $note->timestamp ) ),
						date( 'g:ia', strtotime( $note->timestamp ) ),
						htmlentities( $note->fullName ),
						nl2br( htmlentities( $note->note ) )
					);
				}
			}

			break;

		case 'errorCount':
			Display::errorCount();
			break;

		case 'errorList':
			Display::errorList();
			break;

	}
	exit;
}
$title = 'Dashboard';
include( INCLUDES . "c_header.php" );
?>
<body>
<style>
	table.urlTable th, table.urlTable td {
		padding: 3px;
	}

	table.feedTable th, table.feedTable td {
		padding: 3px;
	}

	table.populationTable {
		margin-bottom: 20px;
	}

	table.populationTable th, table.populationTable td {
		padding: 3px;
	}

	div.chartLabel {
		float: left;
		width: 100px;
	}

	div.chartValue {
		float: left;
		width: 75px;
	}
</style>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<form method="get">
		<p><input type="text" name="statsStart" value="<?php echo htmlentities( date( 'Y-m-d', strtotime( $statsStart ) ) ); ?>"> to <input type="text" name="statsEnd" value="<?php echo htmlentities( date( 'Y-m-d', strtotime( $statsEnd ) ) ); ?>"> <input class="btn btn-primary btn-xs nonLink" type="submit" name="submit" value="Update"/></p>
	</form>

	<?php
	$users = $leads->getStaffUsers();
	Display::displayDashboardRevenueTable( $leads, $users, $statsStart, $statsEnd );

	$feedCategories = array(
		'ppc' => 'PPC',
		'phone' => 'Phone',
		'email' => 'Email',
	);
	foreach( $feedCategories as $categoryKey => $categoryVal ) {
		?>
		<div class="row">
			<div class="col-md-6">
				<?php
				if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
					$incomingFeeds = $leads->getInboundFeeds( null, 'active', $categoryKey );
				} else {
					$idCompany = LeadsSession::getCompanyId();
					if( empty( $idCompany ) ) {
						$idCompany = -9999;
					}
					$incomingFeeds = $leads->getInboundFeeds( $idCompany, 'active', $categoryKey );
				}
				?>
				<h4>Incoming <?php echo $categoryVal; ?> Feeds (Last Updated: <?php echo date( "m-d g:i:s a" ); ?>) <a href="#" class="btn btn-primary btn-xs nonLink" onclick="automaticRefresh = true; autoRefresh();">Refresh</a></h4>
				<?php
				if( $incomingFeeds === false ) {
					?>
					<p>Error when trying to fetch feeds: database error.</p>
					<?php
				} else if( $incomingFeeds == 0 ) {
					?>
					<p>Error when trying to fetch feeds: there are no feeds.</p>
					<?php
				} else {
					$companyFeedLists = array();
					foreach( $incomingFeeds as $feed ) {
						//Add company to the cache list of companies.
						if( !isset( $companyCache[$feed->idCompany] ) ) {
							$companyCache[$feed->idCompany] = $feed->name;
							$companyFeedLists[$feed->idCompany] = array();
						}

						//Add feed to list of feeds for the specified company.
						$companyFeedLists[$feed->idCompany][] = $feed;
					}
					$grandTotalAccepted = 0;
					$grandTotalRejected = 0;
					$grandTotalFeeds = 0;
					?>
					<table class="table table-bordered table-condensed table-striped-custom table-small-font">
						<thead>
						<tr>
							<th>Company</th>
							<th>Last Contact</th>
							<th>Accepted</th>
							<th>Rejected</th>
						</tr>
						</thead>
						<?php
						foreach( $companyFeedLists as $idCompany => $companyFeedList ) {
							$totalAccepted = 0;
							$totalRejected = 0;

							foreach( $companyFeedList as $keyFeed => $feed ) {

								$stats = $leads->getInboundStatsRange( $feed->idFeedIn, date( 'Y-m-d', strtotime( $statsStart ) ), date( 'Y-m-d', strtotime( $statsEnd ) ) );

								$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
								$totalAccepted += $stats['accepted'];
								$grandTotalAccepted += $stats['accepted'];

								$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
								$totalRejected += $stats['rejected'];
								$grandTotalRejected += $stats['rejected'];

							}
							?>
							<tr class="clickable striped-master" onclick="toggleHidden('incoming_companyFeedList_<?php echo $categoryKey; ?>', {'sub':<?php echo $idCompany; ?>});">
								<td class=" dashboard-incoming-col-large"><?php echo $feed->name; ?> (<?php echo count( $companyFeedList ); ?>)</td>
								<td class="text-center dashboard-incoming-col-small">
									<button type="button" class="btn <?php echo ( empty( $feed->lastDate ) || strtotime( $feed->lastDate ) < strtotime( '1 month ago' ) ) ? 'btn-danger' : 'btn-primary'; ?> btn-xs" data-toggle="modal" data-backdrop="static" data-target="#companynotes" data-company-id="<?php echo $idCompany; ?>"><?php echo !empty( $feed->lastDate ) ? date( 'Y-m-d', strtotime( $feed->lastDate ) ) : 'None'; ?></button>
								</td>
								<td class="text-right dashboard-incoming-col-small"><?php echo number_format( $totalAccepted, 0 ); ?></td>
								<td class="text-right dashboard-incoming-col-small"><?php echo number_format( $totalRejected, 0 ); ?></td>
							</tr>
							<tr id="incoming_companyFeedList_<?php echo $categoryKey . '_' . $idCompany; ?>" class="hidden-custom">
								<td colspan="4">
									<table class="table table-bordered table-condensed table-striped">
										<?php
										foreach( $companyFeedList as $feed ) {
											?>
											<tr>
												<td class="dashboard-incoming-col-large"><?php echo $feed->idFeedIn; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</td>
												<td class="text-center dashboard-incoming-col-small"><a class="btn btn-primary btn-xs nonLink" href="#" id="link_feedinc_<?php echo $feed->idFeedIn; ?>" onclick="display('feedinc', { 'sub':'<?php echo $feed->idFeedIn; ?>', 'idFeedIn':'<?php echo $feed->idFeedIn; ?>', 'hiddenText': 'Show URLs', 'shownText': 'Close' } );">Show URLs</a></td>
												<td class="text-right dashboard-incoming-col-small"><?php echo number_format( $feed->dailyCount, 0 ); ?></td>
												<td class="text-right dashboard-incoming-col-small"><a href="mgr_rejections.php?type=inbound&amp;id=<?php echo urlencode( $feed->idFeedIn ); ?>&amp;label=<?php echo urlencode( $feed->label ); ?>" target="_blank"><?php echo number_format( $feed->dailyCountInvalid, 0 ); ?></a></td>
											</tr>
											<tr>
												<td class="hidden-custom" id="feedinc_<?php echo $feed->idFeedIn; ?>" colspan="4"></td>
											</tr>
											<?php
										}
										$grandTotalFeeds += count( $companyFeedList );
										?>
									</table>
								</td>
							</tr>
							<?php
						}
						?>
						<tfoot>
						<tr>
							<td>GRAND TOTAL</td>
							<td>&nbsp;</td>
							<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
							<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
						</tr>
						</tfoot>
					</table>
					<?php
				}
				?>
			</div>
			<div class="col-md-6">
				<?php
				if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
					$outgoingFeeds = $leads->getOutboundFeeds( null, 'active', $categoryKey );
				} else {
					$idCompany = LeadsSession::getCompanyId();
					if( empty( $idCompany ) ) {
						$idCompany = -9999;
					}
					$outgoingFeeds = $leads->getOutboundFeeds( $idCompany, 'active', $categoryKey );
				}
				?>
				<h4>Outgoing <?php echo $categoryVal; ?> Feeds (Last Updated: <?php echo date( "m-d g:i:s a" ); ?>) <a href="#" class="btn btn-primary btn-xs nonLink" onclick="automaticRefresh = true; autoRefresh();">Refresh</a></h4>
				<?php
				if( $outgoingFeeds === false ) {
					?>
					<p>Error when trying to fetch feeds: database error.</p>
					<?php
				} else if( $outgoingFeeds == 0 ) {
					?>
					<p>Error when trying to fetch feeds: there are no feeds.</p>
					<?php
				} else {
					$companyFeedLists = array();
					foreach( $outgoingFeeds as $feed ) {
						//Add company to the cache list of companies.
						if( !isset( $companyCache[$feed->idCompany] ) ) {
							$companyCache[$feed->idCompany] = $feed->name;
							$companyFeedLists[$feed->idCompany] = array();
						}

						//Add feed to list of feeds for the specified company.
						$companyFeedLists[$feed->idCompany][] = $feed;
					}

					$grandTotalAccepted = 0;
					$grandTotalRejected = 0;
					$grandTotalQueued = 0;
					$grandTotalFeeds = 0;
					?>
					<table class="table table-bordered table-condensed table-striped-custom table-small-font">
						<thead>
						<tr>
							<th>Company</th>
							<th>Last Contact</th>
							<th>Accepted</th>
							<th>Rejected</th>
							<th>Queued</th>
						</tr>
						</thead>
						<?php
						foreach( $companyFeedLists as $idCompany => $companyFeedList ) {
							$totalAccepted = 0;
							$totalRejected = 0;
							$totalActive = 0;
							$totalQueued = 0;

							foreach( $companyFeedList as $keyFeed => $feed ) {

								$stats = $leads->getOutboundStatsRange( $feed->idFeedOut, date( 'Y-m-d', strtotime( $statsStart ) ), date( 'Y-m-d', strtotime( $statsEnd ) ) );

								$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
								$totalAccepted += $stats['accepted'];
								$grandTotalAccepted += $stats['accepted'];

								$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
								$totalRejected += $stats['rejected'];
								$grandTotalRejected += $stats['rejected'];

								$companyFeedList[$keyFeed]->dailyCountQueued = $feed->queued;
								$totalQueued += $feed->queued;
								$grandTotalQueued += $feed->queued;
							}
							$grandTotalFeeds += count( $companyFeedList );
							?>
							<tr class="clickable striped-master" onclick="toggleHidden('outgoing_companyFeedList_<?php echo $categoryKey; ?>', {'sub':<?php echo $idCompany; ?>});">
								<td class="dashboard-outgoing-col-large"><?php echo $feed->name; ?> (<?php echo count( $companyFeedList ); ?>)</td>
								<td class="text-center dashboard-outgoing-col-small">
									<button type="button" class="btn <?php echo ( empty( $feed->lastDate ) || strtotime( $feed->lastDate ) < strtotime( '1 month ago' ) ) ? 'btn-danger' : 'btn-primary'; ?> btn-xs" data-toggle="modal" data-backdrop="static" data-target="#companynotes" data-company-id="<?php echo $idCompany; ?>"><?php echo !empty( $feed->lastDate ) ? date( 'Y-m-d', strtotime( $feed->lastDate ) ) : 'None'; ?></button>
								</td>
								<td class="text-right dashboard-outgoing-col-small"><?php echo number_format( $totalAccepted, 0 ); ?></td>
								<td class="text-right dashboard-outgoing-col-small"><?php echo number_format( $totalRejected, 0 ); ?></td>
								<?php
								if( $totalQueued > 5000 ) {
									$bg = 'bg-danger';
								} else if( $totalQueued > 1000 ) {
									$bg = 'bg-warning';
								} else {
									$bg = 'bg-success';
								}
								?>
								<td class="text-right dashboard-outgoing-col-small <?php print $bg; ?>"><?php echo number_format( $totalQueued, 0 ); ?></td>
							</tr>
							<tr id="outgoing_companyFeedList_<?php echo $categoryKey . '_' . $idCompany; ?>" class="hidden-custom">
								<td colspan="5">
									<table class="table table-bordered table-condensed table-striped">
										<?php
										foreach( $companyFeedList as $feed ) {
											?>
											<tr>
												<td class="dashboard-outgoing-col-large-embedded"><?php echo $feed->idFeedOut; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</td>
												<td class="text-right dashboard-outgoing-col-small"><?php echo number_format( $feed->dailyCount, 0 ); ?></td>
												<td class="text-right dashboard-outgoing-col-small"><a href="mgr_rejections.php?type=outbound&amp;id=<?php echo urlencode( $feed->idFeedOut ); ?>&amp;label=<?php echo urlencode( $feed->label ); ?>" target="_blank"><?php echo number_format( $feed->dailyCountInvalid, 0 ); ?></a></td>
												<td class="text-right dashboard-outgoing-col-small"><?php echo number_format( $feed->dailyCountQueued, 0 ); ?></td>
											</tr>
											<?php
										}
										?>
									</table>
								</td>
							</tr>
							<?php
						}
						?>
						<tfoot>
						<tr>
							<td>GRAND TOTAL</td>
							<td>&nbsp;</td>
							<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
							<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
							<td class="text-right"><?php echo number_format( $grandTotalQueued, 0 ); ?></td>
						</tr>
						</tfoot>
					</table>
					<?php
				}
				?>
			</div>
		</div>
	<?php } // $feedCategories ?>
</div>

<div class="modal fade" id="companynotes" tabindex="-1" role="dialog" aria-labelledby="companynotes_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="companynotes_title">Company Notes</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-companynotes" type="button" class="btn btn-primary">Add A New Note</button>
			</div>
		</div>
	</div>
</div>

<script>
	$(document).ready(function () {
		setTimeout(function () {
			location.reload();
		}, 120000);
	});

	$('input[name="statsStart"], input[name="statsEnd"]').datepicker({
		// Consistent format with the HTML5 picker
		dateFormat: 'yy-mm-dd'
	});

	$('#modal-save-companynotes').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "dashboard.php",
			type: "POST",
			async: true,
			data: $("#note_company").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#companynotes').on('show.bs.modal', function (e) {
		var modal = $(this);
		var companyId = $(e.relatedTarget).data('company-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'dashboard.php',
			data: {
				'd': 'dialog_companynotes',
				'companyId': companyId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#companynotes').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});
</script>

</body>
</html>
