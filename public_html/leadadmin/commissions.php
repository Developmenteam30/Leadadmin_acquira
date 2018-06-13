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

$title = 'Commissions Report';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">
    <h2>Commissions Report</h2>

	<?php

	$employeeId = ( !empty( $_REQUEST['userId'] ) && LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) ? $_REQUEST['userId'] : LeadsSession::getUserId();
	$monthId = !empty( $_REQUEST['month'] ) ? $_REQUEST['month'] : '000000';
	$users = $leads->getStaffUsers( PDO::FETCH_OBJ );
	if( empty( $users ) ) {

		?>
        <p>No users exist in the database.</p>
		<?php
	} else {

		print '<form class="form-inline" method="get">' . PHP_EOL;
		print '<div class="form-group">' . PHP_EOL;
		print '<label for="userId">Employee:</label>' . PHP_EOL;
		print '<select class="form-control" id="userId" name="userId">' . PHP_EOL;
		foreach( $users as $user ) {
			printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
				$user->idUser,
				$employeeId == $user->idUser ? ' selected="selected"' : '',
				htmlentities( $user->fullName )
			);
		}
		print '</select>' . PHP_EOL;
		print '</div>' . PHP_EOL;

		if( !empty( $employeeId ) ) {

			$user = $leads->getUser( $employeeId );
			if( !empty( $user ) ) {
				$entries = $leads->getPaidLedger( null, $user->idUser );
				if( !empty( $entries ) ) {
					$months = array();
					foreach( $entries as $entry ) {
						if( $entry->userId1 == $user->idUser ) {
							if( !empty( $entry->commissionDate1 ) ) {
								$month = substr( $entry->commissionDate1, 0, 7 );
								$months[$month][$entry->type] = true;
							} else {
								$months['000000'][$entry->type] = true;
							}
						}
						if( $entry->userId2 == $user->idUser ) {
							if( !empty( $entry->commissionDate2 ) ) {
								$month = substr( $entry->commissionDate2, 0, 7 );
								$months[$month][$entry->type] = true;
							} else {
								$months['000000'][$entry->type] = true;
							}
						}
					}
					krsort( $months );
				}

				$years = array();
				$quarters = array();
				if( !empty( $months ) ) {
					print '<div class="form-group">' . PHP_EOL;
					print '<label for="userId">Month:</label>' . PHP_EOL;
					print '<select class="form-control" id="month" name="month">' . PHP_EOL;
					foreach( $months as $month => $types ) {
						if( '000000' != $month ) {
							$year = substr( $month, 0, 4 );
							$quarter = $year . '-Q' . ceil( substr( $month, 5, 2 ) / 3 );
							if( empty( $years[$year] ) ) {
								printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
									$year,
									$monthId == $year ? ' selected="selected"' : '',
									htmlentities( $year . ' Year' )
								);
								$years[$year] = true;
							}
							if( empty( $quarters[$quarter] ) ) {
								printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
									$quarter,
									$monthId == $quarter ? ' selected="selected"' : '',
									htmlentities( str_replace( '-Q', ' Qtr ', $quarter ) )
								);
								$quarters[$quarter] = true;
							}
						}
						printf( '<option value="%s"%s>%s</option>' . PHP_EOL,
							$month,
							$monthId == $month ? ' selected="selected"' : '',
							'000000' == $month ? 'Pending' : htmlentities( $month )
						);
					}
					print '</select>' . PHP_EOL;
					print '</div>' . PHP_EOL;
				}
			}
		}

		print '</form>' . PHP_EOL;

	}

	if( empty( $user ) ) {

		print '<p>User not found in the database.</p>' . PHP_EOL;

	} else {

		printf( '<h3>%s</h3>' . PHP_EOL, htmlentities( $user->fullName ) );

		if( empty( $entries ) ) {
			?>
            <p>There are no commission entries for the selected period.</p>
			<?php
		} else {

			$found = false;
			ksort( $months );
			foreach( $months as $month => $types ) {
				if( ( strlen( $monthId ) == 6 && $month == $monthId ) || ( strlen( $monthId ) == 4 && substr( $month, 0, 4 ) == $monthId ) || ( strlen( $monthId ) == 7 && $month == $monthId ) || ( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $monthId ) && substr( $month, 0, 4 ) . ceil( substr( $month, 5, 2 ) / 3 ) == substr( $monthId, 0, 4 ) . substr( $monthId, -1 ) ) ) {
					foreach( $types as $type => $val ) {
						?>
                        <h4><?php echo ( '000000' == $month ? 'Pending Commissions' : date( 'F Y', strtotime( $month . '-01' ) ) ) . ' - ' . ( '0' == $type ? 'Publisher' : 'Advertiser' ); ?></h4>
                        <table class="table table-bordered table-condensed table-striped ledger-sort"
                               id="commissions_<?php echo $type; ?>_<?php echo $month ?>">
                            <thead>
                            <tr class="bgGray header">
                                <th>ID #</th>
                                <th>Division</th>
                                <th>Company</th>
                                <th>Ledger Month</th>
                                <th>Invoice #</th>
                                <th>Payment Amount</th>
                                <th>Payment Date</th>
                                <th>Commission</th>
								<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
                                    <th>Actions</th>
								<?php } ?>
                            </tr>
                            </thead>
                            <tbody>
							<?php
							$paymentTotal = 0;
							$commissionTotal = 0;
							foreach( $entries as $entry ) {
								if( $entry->userId1 == $user->idUser ) {
									$commissionDate = $entry->commissionDate1;
									$commissionAmount = $entry->commissionAmount1;
								} else if( $entry->userId2 == $user->idUser ) {
									$commissionDate = $entry->commissionDate2;
									$commissionAmount = $entry->commissionAmount2;
								} else {
								    continue;
                                }
								if( $type == $entry->type && ( ( '000000' == $month && empty( $commissionDate ) ) || substr( $commissionDate, 0, 7 ) == $month ) ) {
									$paymentTotal += $entry->paymentAmount;
									$commissionTotal += $commissionAmount;
									$found = true;
									?>
                                    <tr>
                                        <td><?php echo htmlentities( $entry->entryId ); ?></td>
                                        <td><?php echo htmlentities( $entry->divisionName ); ?></td>
                                        <td><?php echo htmlentities( $entry->companyName ); ?></td>
                                        <td data-tf-sortKey="<?php echo date( 'Y-m-01', strtotime( $entry->ledgerMonth ) ); ?>"><?php echo date( 'F Y', strtotime( $entry->ledgerMonth ) ); ?></td>
                                        <td><?php echo htmlentities( $entry->invoiceNum ); ?></td>
                                        <td>$<?php echo number_format( $entry->paymentAmount, 2 ); ?></td>
                                        <td><?php echo htmlentities( $entry->paymentDate ); ?></td>
                                        <td>$<?php echo number_format( $commissionAmount, 2 ); ?></td>
										<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
                                            <td class="text-center"><?php if( 'ledger_offline' === $entry->source ) { ?>
                                                    <button type="button" class="btn btn-primary btn-xs"
                                                            data-toggle="modal" data-backdrop="static" data-target="#editofflineledger"
                                                            data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit
                                                    </button>
												<?php } else if( 'ledger_phones' === $entry->source ) { ?>
                                                    <button type="button" class="btn btn-primary btn-xs"
                                                            data-toggle="modal" data-backdrop="static" data-target="#editphoneledger"
                                                            data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit
                                                    </button>
												<?php } else if( 'email' === $entry->source ) {
													print "&nbsp;"; ?>
												<?php } else { ?>
                                                    <button type="button" class="btn btn-primary btn-xs"
                                                            data-toggle="modal" data-backdrop="static" data-target="#editledger"
                                                            data-ledger-id="<?php echo $entry->ledgerId; ?>">Edit
                                                    </button>
												<?php } ?></td>
										<?php } ?>
                                    </tr>
									<?php
								}
							}
							?>
                            </tbody>
                            <tfoot>
                            <tr class="bgGray header">
                                <td colspan="5">Monthly Totals</td>
                                <td>$<?php echo number_format( $paymentTotal, 2 ); ?></td>
                                <td>&nbsp;</td>
                                <td>$<?php echo number_format( $commissionTotal, 2 ); ?></td>
								<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_MANAGER ) ) { ?>
                                    <td>&nbsp;</td>
								<?php } ?>
                            </tr>
                            </tfoot>
                        </table>
						<?php
					}
				}
			}

			if( !$found ) {
				print '<p>There are no commission entries for the selected period.</p>' . PHP_EOL;
			}
		}
	}
	?>

</div>

<script type="text/javascript">
	$('.form-inline select').change(function () {
		$('.form-inline').submit();
	});

	$('.ledger-sort').each(function (index) {
		var tf = new TableFilter($(this).attr('id'), {
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
					'number', // ID #
					'caseinsensitivestring', // Division
					'caseinsensitivestring', // Company
					'date', // Ledger Month
					'caseinsensitivestring', // Invoice #
					'formatted-number', // Payment Amount
					'date', // Payment Date
					'formatted-number' // Commission
				],
				image_asc_class_name: 'custom-ascending',
				image_desc_class_name: 'custom-descending'
			}],
			sort: true
		});
		tf.init();
	});
</script>

<?php require_once( INCLUDES . 'modals.php' ); ?>

</body>
</html>
