<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

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
					'email' => htmlspecialchars( $record['email'], ENT_NOQUOTES ),
					'url' => htmlspecialchars( $record['url'], ENT_NOQUOTES ),
					'result' => htmlspecialchars( $result, ENT_NOQUOTES ),
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

		case 'displayJob':
			if( empty( $_REQUEST['options']['jobId'] )) {
				print '<p>Error: No job ID specified!</p>';
			} else if( empty( $_REQUEST['options']['count'] )) {
				print '<p>Error: No record count specified!</p>';
			} else {
?>
		<div class='fr'>
			<a href="#" class="nonLink" onclick="request.abort(); closeContent( 'displayJob' ); display('displayAllJobs');">Close [X]</a>
		</div>
		<h1>Upload Job Status</h1>
		<p><strong>Job ID:</strong> <?php echo htmlentities( $_REQUEST['options']['jobId'] ); ?></p>
		<p><strong>Status:</strong> <span id="status">Pending - please wait</span></p>

		<table class="standard">
			<thead>
				<tr>
					<td>Accepted</td>
					<td>Rejected - Invalid</td>
					<td>Rejected - Duplicate</td>
					<td>Rejected - Suppressed</td>
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
		request = $.ajax({
			method: 'GET',
			async: true,
			data: ({
				'a': 'jobResults',
				'jobId': <?php echo intval( $_REQUEST['options']['jobId'] ) ?>,
				'idRecord': lastRecord
			}),
			success: function(data) {
				if (data.status == "success") {
					var lastRecord = 0;
					var html = [];
					$.each(data.results, function(index) {
						html.push( '<tr class="' + data.results[index].class + '"><td>' + data.results[index].idRecord + '</td><td>' + data.results[index].email + '</td><td>' + data.results[index].url + '</td><td>' + data.results[index].result + '</td></tr>' );
						resultCount++;
						lastRecord = data.results[index].idRecord;
						if( $('#status').html() != 'Processing' ) {
							$('#status').html('Processing');
						}
					});
					$('#results tbody').append( html.join('') );
					$('#count-accepted').html( parseInt( $('#count-accepted').html() ) + parseInt( data.counts.accepted ) );
					$('#count-rejected').html( parseInt( $('#count-rejected').html() ) + parseInt( data.counts.rejected ) );
					$('#count-duplicate').html( parseInt( $('#count-duplicate').html() ) + parseInt( data.counts.duplicate ) );
					$('#count-suppressed').html( parseInt( $('#count-suppressed').html() ) + parseInt( data.counts.suppressed ) );

					if( resultCount < <?php echo intval( $_REQUEST['options']['count'] ); ?> ) {
						if( lastRecord == 0 ) {
							setTimeout(function() {
								getNextResult( lastRecord );
							}, 2000 );
						} else {
							setTimeout(function() {
								getNextResult( lastRecord );
							}, 500 );
						}
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
<?php
			}
		break;

		case 'displayAllJobs':
			$jobs = $leads->getJobs();
			if( empty( $jobs ) || !is_array( $jobs ) ) {
				print "No jobs found.";
			} else {
?>
		<table class="standard" id="jobs">
			<thead>
				<tr>
					<td>Job ID</td>
					<td>Type</td>
					<td>Timestamp</td>
					<td>Status</td>
					<td>Feed</td>
					<td>Records</td>
					<td>Username</td>
				</tr>
			</thead>
			<tbody>
<?php
				foreach( $jobs as $job ) {
					$timestamp = new DateTime( $job->timestamp, new DateTimeZone( DB_TIMEZONE ) );
					$timestamp->setTimezone( new DateTimeZone( LOCAL_TIMEZONE ) );

					if( 'finished' === $job->status ) {
						$class = 'accepted';
					} else if( 'processing' === $job->status ) {
						$class = 'duplicate';
					} else {
						$class = 'rejected';
					}
?>
				<tr class="<?php echo $class; ?>">
					<td><a class="nonLink" href="#" onclick="closeContent( 'displayAllJobs' ); display( 'displayJob', { 'jobId': <?php echo $job->jobId; ?>, 'count': <?php echo $job->records; ?> });"><?php echo $job->jobId; ?></a></td>
					<td><?php echo $job->type; ?></td>
					<td><?php echo $timestamp->format( 'Y-m-d H:i:s' ); ?></td>
					<td><?php echo $job->status; ?></td>
					<td><?php echo $job->label; ?></td>
					<td><?php echo $job->records; ?></td>
					<td><?php echo $job->username; ?></td>
				</tr>
<?php
				}
			}
		break;
	}
	exit;
}

$title = 'Upload Job Status';
include(INCLUDES."c_header.php");

?>

<body>

<script>
$(document).ready(function(){
	var request;
<?php if( !empty( $_REQUEST['jobId'] ) && !empty( $_REQUEST['count'] ) ) { ?>
    display( 'displayJob', { 'jobId': <?php echo intval( $_REQUEST['jobId'] ); ?>, 'count': <?php echo intval( $_REQUEST['count'] ); ?> } );
<?php } else { ?>
    display( 'displayAllJobs' );
<?php } ?>
});
</script>

<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div class='fl' style='width: 100%;'>
		<div id='displayAllJobs'></div>
		<div id='displayJob'></div>
	</div>
	<div class='clr'></div>
</div>

</body>
</html>
