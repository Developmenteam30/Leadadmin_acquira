<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
    switch( $_REQUEST['a'] ) {
        case 'jobResults':
			Header( 'Content-Type: application/json' );

			if( empty( $_REQUEST['jobId'] )) {
				json_encode( array( 'status' => 0, 'error' => 'No jobId specified' ) );
			    exit;
			}

			$jobId = $_REQUEST['jobId'];
			$idRecord = !empty( $_REQUEST['idRecord'] ) ? $_REQUEST['idRecord'] : 0;

			$response = array(
			    'status' => 'failed',
				'error' => 'Unknown error',
				'results' => array(),
				'counts' => array(
					'accepted' => 0,
					'rejected' => 0,
					'suppressed' => 0,
					'duplicate' => 0,
				),
			);

			$leads = Leads::getInstance();
			$records = $leads->getInboundJobRecords( $jobId, $idRecord );
			foreach( $records as $record ) {
				$result = $record['result'];
				if( 'Email exists in our global suppression file.' == $result ) {
					$class = 'suppressed';
					$response['counts']['suppressed']++;
				} else if( strpos( $result, 'Duplicate' ) === 0 ) {
					$class = 'duplicate';
					$response['counts']['duplicate']++;
				} else if( null === $result ) {
					$class = 'accepted';
					$result = '';
					$response['counts']['accepted']++;
				} else {
					$class = 'rejected';
					$response['counts']['rejected']++;
				}

				$response['results'][] = array(
					'idRecord' => $record['idRecord'],
					'email' => $record['email'],
					'url' => $record['url'],
					'result' => $result,
					'class' => $class,
				);
			}

			$response['status'] = 'success';
			$response['error'] = '';
			echo json_encode( $response );

        break;
	}
	exit;
}

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

$title = 'Upload Job Status';
include(INCLUDES."c_header.php");

?>

<body>

<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>

<?php if( empty( $_REQUEST['jobId'] )) { ?>
		<p>Error: No job ID specified!</p>
<?php } else if( empty( $_REQUEST['count'] )) { ?>
		<p>Error: No record count specified!</p>
<?php } else { ?>
		<h1>Upload Job Status</h1>
		<p><strong>Job ID:</strong> <?php echo htmlentities( $_REQUEST['jobId'] ); ?></p>
		<p><strong>Status:</strong> <span id="status">Pending - please wait</span></p>

		<table class="standard">
			<thead>
				<tr>
					<td>Accepted</td>
					<td>Rejected</td>
					<td>Duplicate</td>
					<td>Suppressed</td>
				</tr>
			</thead>
			<tbody>
				<tr class="aCenter">
					<td id="count-accepted">0</td>
					<td id="count-rejected">0</td>
					<td id="count-duplicate">0</td>
					<td id="count-suppressed">0</td>
				</tr>
			</tbody>
		</table>

		<br/>

		<table class="standard" id="results">
			<thead>
				<tr>
					<td>Record ID</td>
					<td>Email Address</td>
					<td>URL</td>
					<td>Result</td>
				</tr>
			</thead>
			<tbody>
			</tbody>
		</table>
<script>
	var resultCount = 0;
	function getNextResult( lastRecord ) {
		$.ajax({
			method: 'GET',
			async: true,
			data: ({
				'a': 'jobResults',
				'jobId': <?php echo intval( $_REQUEST['jobId'] ) ?>,
				'idRecord': lastRecord
			}),
			success: function(data) {
				if (data.status == "success") {
					var lastRecord = 0;
					$.each(data.results, function(index) {
						var row = $('<tr/>').attr({'class': data.results[index].class });
						var idRecord = $('<td/>').append( document.createTextNode( data.results[index].idRecord ) );
						var email = $('<td/>').append( document.createTextNode( data.results[index].email ) );
						var url = $('<td/>').append( document.createTextNode( data.results[index].url ) );
						var result = $('<td/>').append( document.createTextNode( data.results[index].result ) );
						row.append( idRecord );
						row.append( email );
						row.append( url );
						row.append( result );
						$('#results tbody').append(row);
						resultCount++;
						lastRecord = data.results[index].idRecord;
						$('#status').html('Processing');
					});
					$('#count-accepted').html( parseInt( $('#count-accepted').html() ) + parseInt( data.counts.accepted ) );
					$('#count-rejected').html( parseInt( $('#count-rejected').html() ) + parseInt( data.counts.rejected ) );
					$('#count-duplicate').html( parseInt( $('#count-duplicate').html() ) + parseInt( data.counts.duplicate ) );
					$('#count-suppressed').html( parseInt( $('#count-suppressed').html() ) + parseInt( data.counts.suppressed ) );

					if( resultCount < <?php echo intval( $_REQUEST['count'] ); ?> ) {
						getNextResult( lastRecord );
					} else {
						$('#status').html('Finished');
					}
				} else {
					$('#results').after( data.error );
				}
			}
		});
	}
	getNextResult( 0 );
</script>
<?php } ?>
	</div>
</div>

</body>
</html>
