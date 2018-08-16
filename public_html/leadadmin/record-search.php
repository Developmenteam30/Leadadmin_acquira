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

		case 'dialog_search_email_results':
			break;

	}
	exit;
}

$title = 'Email Search';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Record Search</h2>
	<p>Fill out any or all of the fields below to perform an "AND" search against all of the fields that are filled in.</p>
	<p>Searches will be performed against the last 1 year of data. Results are limited to the first 500 matching entries.</p>

	<?php

	$fields = array(
		array(
			'id' => 'email',
			'type' => 'email',
			'label' => 'Email Address',
			'value' => $_REQUEST['email'] ?? '',
		),
		array(
			'id' => 'phone',
			'type' => 'text',
			'label' => 'Phone Number',
			'value' => $_REQUEST['phone'] ?? '',
		),
		array(
			'id' => 'url',
			'type' => 'text',
			'label' => 'URL',
			'value' => $_REQUEST['url'] ?? '',
		),
		array(
			'id' => 'submit',
			'type' => 'submit',
			'label' => 'Search',
		),
	);

	Display::displayForm( 'record_search', $fields );

	$email = trim( $_REQUEST['email'] ?? '' );
	$phone = trim( preg_replace( '/[^0-9]/', '', $_REQUEST['phone'] ?? '' ) );
	$url = trim( $_REQUEST['url'] ?? '' );

	if( !empty( $email ) || !empty( $phone ) || !empty( $url ) ) {

		$leads->auditLog( 'SEARCH:EMAIL', json_encode( array( 'email' => $email, 'phone' => $phone, 'url' => $url ) ) );

		?>
		<p>Searching incoming feeds ...</p>

		<?php
		$records = $leads->inboundRecordSearch( $email, $phone, $url );
		if( is_array( $records ) && sizeOf( $records ) > 0 ) {
			?>

			<table class="table table-bordered table-striped-triple table-condensed">
				<thead>
				<tr>
					<th>Incoming Feed</th>
					<th>Email</th>
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
				<tr>
					<th colspan="9">Outbound Records</th>
				</tr>
				</thead>
				<tbody>

				<?php
				foreach( $records as $record ) {
					?>
					<tr>
						<td><?php echo Display::escHtml( $record->label ); ?> (#<?php echo Display::escHtml( $record->idFeedIn ); ?>)</td>
						<td><?php echo Display::escHtml( $record->email ); ?></td>
						<td><?php echo Display::escHtml( $record->timestampConverted ); ?></td>
						<td><?php echo Display::escHtml( $record->url ); ?></td>
						<td><?php echo Display::escHtml( $record->fname ); ?></td>
						<td><?php echo Display::escHtml( $record->lname ); ?></td>
						<td><?php echo Display::escHtml( $record->leadstamp ); ?></td>
						<td><?php echo Display::escHtml( $record->ip ); ?></td>
						<td><?php echo Display::escHtml( $record->dob ); ?></td>
					</tr>
					<tr>
						<td><?php echo Display::escHtml( $record->addr ); ?>&nbsp;</td>
						<td><?php echo Display::escHtml( $record->addr2 ); ?></td>
						<td><?php echo Display::escHtml( $record->city ); ?></td>
						<td><?php echo Display::escHtml( $record->state ); ?></td>
						<td><?php echo Display::escHtml( $record->zip ); ?></td>
						<td><?php echo Display::escHtml( $record->country ); ?></td>
						<td><?php echo Display::escHtml( $record->landline ); ?></td>
						<td><?php echo Display::escHtml( $record->cellphone ); ?></td>
						<td><?php echo Display::escHtml( $record->gender ); ?></td>
					</tr>
					<?php
					$outboundRecords = $leads->outboundRecordSearchById( $record->idRecord );
					if( !empty( $outboundRecords ) ) {
						print '<tr><td colspan="9">';
						foreach( $outboundRecords as $outboundRecord ) {
							printf( '<p>%s: %s (#%s) Response: %s',
								Display::escHtml( $outboundRecord->timestampConverted ),
								Display::escHtml( $outboundRecord->label ),
								Display::escHtml( $outboundRecord->idFeedOut ),
								Display::escHtml( !empty( $outboundRecord->result ) ? $outboundRecord->result : '<LEGACY SUCCESS RESPONSE>' )
							);
						}
						print '</td></tr>';
					} else {
						print '<tr><td colspan="9">No outbound records found.</td></tr>';
					}
				}
				?>

				</tbody>
			</table>

			<?php
		} else {
			print '<p>No records found.</p>' . PHP_EOL;
		}
	}
	?>
</div>
</body>
</html>
