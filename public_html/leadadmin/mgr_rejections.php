<?php

include("../../includes/c_config.php");
$title = 'Rejection Log';
include(INCLUDES . "c_header.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_CLIENT_IMPORT);

require_once(INCLUDES . 'display.php');
require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

?>

<body>
<div class="mainContainer">

    <?php

    $label = isset($_REQUEST['label']) ? $_REQUEST['label'] : '';
    $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
    $offset = isset($_REQUEST['offset']) ? $_REQUEST['offset'] : 0;
    $id = isset($_REQUEST['id']) ? $_REQUEST['id'] : 0;

    if (empty($id) || empty($type)) {
        print "<p>ERROR: Invalid parameters specified.</p>\n";
    } else {

        if ($type == 'inbound') {
            // If this a client, ensure they have access for this feed
            if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_STAFF)) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkInboundFeedAccess($idCompany, $id)) {
                    die('Sorry, you do not have access to view this feed');
                }
            }

            $records = $leads->getInboundRejections($id, $offset);
        } elseif ($type == 'outbound') {
            // If this a client, ensure they have access for this feed
            if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_STAFF)) {
                $idCompany = LeadsSession::getCompanyId();
                if (empty($idCompany)) {
                    $idCompany = -9999;
                }
                if (!$leads->checkOutboundFeedAccess($idCompany, $id)) {
                    die('Sorry, you do not have access to view this feed');
                }
            }

            $records = $leads->getOutboundRejections($id, $offset);
        } else {
            print "<p>ERROR: Invalid type specified</p>\n";
        }

        if ($records === false) {
            print "<p>ERROR: Cannot get records from database</p>\n";
        } elseif (sizeOf($records) == 0) {
            print "<p>ERROR: No rejections exist for this feed</p>\n";
        } else {
            ?>

			<h3>Last 100 rejections for <?php echo Display::escHtml($type); ?> feed: <?php echo Display::escHtml($label); ?></h3>

			<?php if ( $offset > 0 ) { ?>
				<p style="display: inline-block;margin-right:10px;"><?php printf('<a href="mgr_rejections.php?type=%s&amp;id=%s&amp;label=%s&amp;offset=%d">Previous page</a>', urlencode($type), urlencode($id), urlencode($label), intval($offset - 100)); ?></p>
			<?php } ?>
			<p style="display: inline-block;"><?php printf('<a href="mgr_rejections.php?type=%s&amp;id=%s&amp;label=%s&amp;offset=%d">Next page</a>', urlencode($type), urlencode($id), urlencode($label), intval($offset + 100)); ?></p>
			<br>

			<table class="rejectionsTable">
				<thead>
				<tr>
					<th>Error Timestamp</th>
					<th colspan="8">Error Message</th>
					<th>LeadID</th>
					<th>URL</th>
					<th>Email</th>
					<th>Lead Timestamp</th>
				</tr>
				<tr>
					<th>First Name</th>
					<th>Last Name</th>
					<th>Address 1</th>
					<th>Address 2</th>
					<th>City</th>
					<th>State</th>
					<th>Zipcode</th>
					<th>Country</th>
					<th>DOB</th>
					<th>Gender</th>
					<th>Landline</th>
					<th>Cellphone</th>
					<th>IP Address</th>
				</tr>
				</thead>
				<tbody>
                <?php foreach ($records as $record) { ?>
					<tr>
                        <?php if ('outbound' == $type) { ?>
	                        <td><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-postparams" data-feed-id="<?php echo intval($id); ?>" data-record-id="<?php echo intval($record['idRecord']); ?>" data-accepted="<?php echo intval($record['accepted']); ?>" data-result="<?php echo Display::escHtml($record['result']); ?>"><?php echo Display::escHtml($record['timestampConverted']); ?></a></td>
                        <?php } else { ?>
	                        <td><?php echo Display::escHtml($record['timestampConverted']); ?></td>
                        <?php } ?>
						<td class="error" colspan="8"><?php echo Display::escHtml($record['result']); ?></td>
						<td><?php echo Display::escHtml($record['leadId']); ?></td>
						<td><?php echo Display::escHtml($record['url']); ?></td>
						<td><?php echo Display::escHtml($record['email']); ?></td>
						<td><?php echo Display::escHtml($record['leadstamp']); ?></td>
					</tr>
					<tr>
						<td><?php echo Display::escHtml($record['fname']); ?></td>
						<td><?php echo Display::escHtml($record['lname']); ?></td>
						<td><?php echo Display::escHtml($record['addr']); ?></td>
						<td><?php echo Display::escHtml($record['addr2']); ?></td>
						<td><?php echo Display::escHtml($record['city']); ?></td>
						<td><?php echo Display::escHtml($record['state']); ?></td>
						<td><?php echo Display::escHtml($record['zip']); ?></td>
						<td><?php echo Display::escHtml($record['country']); ?></td>
						<td><?php echo Display::escHtml($record['dob']); ?></td>
						<td><?php echo Display::escHtml($record['gender']); ?></td>
						<td><?php echo Display::escHtml($record['landline']); ?></td>
						<td><?php echo Display::escHtml($record['cellphone']); ?></td>
						<td><?php echo Display::escHtml($record['ip']); ?></td>
					</tr>
                <?php } //foreach ?>
				</tbody>
			</table>

			<?php if ( $offset > 0 ) { ?>
				<p style="display: inline-block;margin-right:10px;"><?php printf('<a href="mgr_rejections.php?type=%s&amp;id=%s&amp;label=%s&amp;offset=%d">Previous page</a>', urlencode($type), urlencode($id), urlencode($label), intval($offset - 100)); ?></p>
			<?php } ?>
			<p style="display: inline-block;"><?php printf('<a href="mgr_rejections.php?type=%s&amp;id=%s&amp;label=%s&amp;offset=%d">Next page</a>', urlencode($type), urlencode($id), urlencode($label), intval($offset + 100)); ?></p>
			<br/>

        <?php } ?>
    <?php } ?>

</div><!-- #.mainContainer -->

<div class="modal fade" id="modal-postparams" tabindex="-1" role="dialog" aria-labelledby="postparams_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="postparams_title">View posting parameters</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
	$('#modal-postparams').on('show.bs.modal', function (e) {
		var modal = $(this);
		var idFeedOut = $(e.relatedTarget).data('feed-id');
		var idRecord = $(e.relatedTarget).data('record-id');
		var accepted = $(e.relatedTarget).data('accepted');
		var result = $(e.relatedTarget).attr('data-result'); // Prevent JSON error responses from being parsed

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'mgr_feedout.php',
			data: {
				'd': 'dialog_postparams',
				'idFeedOut': idFeedOut,
				'idRecord': idRecord,
				'accepted': accepted,
				'result': result
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});
</script>

</body>
</html>
