<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_CLIENT_DASHBOARD );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );

$mysqlErrorSource = 'Manager - Incoming Feeds';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_feedinc";
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

if(isset($_REQUEST['a'])){
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "manageFeed":
			$c = true;
			$result['error'] = 'Failed when attempting to manage feeds.';
			$action = $_REQUEST['action'];

			//Validate Input
			if( empty( $_REQUEST['label'] ) ) {
				$c = false;
				$result['error'] = 'Feed label cannot be empty.';
			}

			if( empty( $_REQUEST['idCompany'] ) ) {
				$c = false;
				$result['error'] = 'Company cannot be empty.';
			}

			if( $c ) {
				//Label cannot have invalid characters
				$pattern = '/^[a-z][a-z0-9_]*$/';
				if( !preg_match($pattern, $_REQUEST['label'] ) ) {
					$c = false;
					$result['error'] = 'Labels must start with a letter, can contain lowercase letters, numbers, and underscore only.';
				}
			}

			if( $c && empty( $_REQUEST['allowedFields'] ) || !is_array( $_REQUEST['allowedFields'] ) ) {
				// Must allow some fields, or the feed is worthless isn't it
				$c = false;
				$result['error'] = 'You must allow at least one field to be processed.';
			}

			if( $c ) {
				//Make sure that any required fields are also allowed
				foreach( $_REQUEST['required'] as $f ) {

					switch( $f ) {
						case "phone":
							if( !in_array( 'landline', $_REQUEST['allowedFields'] ) || !in_array( 'cellphone', $_REQUEST['allowedFields'] ) ) {
								$c = false;
								$result['error'] = 'If phone is selected, both landline and cellphone must be allowed fields.';
							}
							break;

						default:
							if( !in_array( $f, $_REQUEST['allowedFields'] ) ) {
								$c = false;
								$result['error'] = "If {$f} is a required field, then that field must be allowed as well.";
							}
					}
					if( !$c ) {
						break;
					}
				}
			}

			$filterUrl = '';
			$filterUrlMulti = array();
			if( !empty( $_REQUEST['filterUrlMulti'] ) ) {
				$filterUrlMulti = explode( "\n", $_REQUEST['filterUrlMulti'] );
			}
			$_REQUEST['filterUrl'] = array_merge( $_REQUEST['filterUrl'], $filterUrlMulti );
			if( !empty( $_REQUEST['filterUrl'] ) && is_array( $_REQUEST['filterUrl'] ) ) {
				$_REQUEST['filterUrl'] = array_map( 'trim', $_REQUEST['filterUrl'] );
				$filterUrl = implode( ';', $_REQUEST['filterUrl'] );
			}

			$filterSiftLogic = '';
			$filterSiftLogicMulti = array();
			if( !empty( $_REQUEST['filterSiftLogicMulti'] ) ) {
				$filterSiftLogicMulti = explode( "\n", $_REQUEST['filterSiftLogicMulti'] );
			}
			$_REQUEST['filterSiftLogic'] = array_merge( $_REQUEST['filterSiftLogic'], $filterSiftLogicMulti );
			if( !empty( $_REQUEST['filterSiftLogic'] ) && is_array( $_REQUEST['filterSiftLogic'] ) ) {
				$_REQUEST['filterSiftLogic'] = array_map( 'trim', $_REQUEST['filterSiftLogic'] );
				$filterSiftLogic = implode( ';', $_REQUEST['filterSiftLogic'] );
			}

			if( 'new' == $action ) {

				if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have permission to add new feeds.';
				}

				if( $c ) {
					//Label can not be already used
					$checkResult = $leads->checkInboundFeedLabelExists( $_REQUEST['label'] );
					if( true === $checkResult ) {
						$c = false;
						$result['error'] = 'That feed label is already being used.';
					}
				}

				if( $c ) { //Add entry to the database.
					$idFeedIn = $leads->addInboundFeed( array(
						'label' => empty( $_REQUEST['label'] ) ? null : $_REQUEST['label'],
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'],
						'required' => empty( $_REQUEST['required'] ) ? null : implode( ';', $_REQUEST['required'] ),
						'allowedFields' => empty( $_REQUEST['allowedFields'] ) ? null : implode( ';', $_REQUEST['allowedFields'] ),
						'password' => genFeedPass(),
						'dedupeEmail' => empty( $_REQUEST['dedupeEmail'] ) ? 0 : 1,
						'dedupeLandline' => empty( $_REQUEST['dedupeLandline'] ) ? 0 : 1,
						'dedupeCellphone' => empty( $_REQUEST['dedupeCellphone'] ) ? 0 : 1,
						'dedupeAcross' => empty( $_REQUEST['dedupeAcross'] ) ? null : $_REQUEST['dedupeAcross'],
						'filterTypeUrl' => empty( $_REQUEST['filterTypeUrl'] ) ? null : $_REQUEST['filterTypeUrl'],
						'filterUrl' => empty( $filterUrl ) ? null : $filterUrl,
						'filterTypeSiftLogic' => empty( $_REQUEST['filterTypeSiftLogic'] ) ? null : 'accept',
						'filterSiftLogic' => empty( $filterSiftLogic ) ? null : $filterSiftLogic,
						'notifications' => empty( $_REQUEST['notifications'] ) ? 0 : 1,
						'rejectOldLeads' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? 0 : 1,
						'rejectOldLeadsMaxAge' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
					) );

					if( null === $idFeedIn ) {
						$c = false;
						$result['status'] = 0;
						$result['error'] = 'Failed to create new feed.';
					} else {
						$result['status'] = 1;
						$result['error'] = "Successfully created new feed #{$idFeedIn}.";
						$leads->auditLog( 'FEEDINC:ADD', $idFeedIn );
					}

				}
			} else {
				if( $c ) {
					if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
						$idCompany = LeadsSession::getCompanyId();
						if( empty( $idCompany ) ) {
						$idCompany = -9999;
						}
						if( !$leads->checkInboundFeedAccess( $idCompany, $_REQUEST['idFeedIn'] ) ) {
							$c = false;
							$result['error'] = 'Sorry, you do not have access to this feed.';
						}
					} else {
						$idCompany = empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'];
					}
				}

				if( $c ) {
					$feed = $leads->getInboundFeed( $_REQUEST['idFeedIn'] );

					if( $feed === false ) {
						$c = false;
						$result['error'] = 'Database failure - could not fetch feed information for editing.';
					}
				}
				if( $c && $_REQUEST['label'] != $feed->label ) { //Label is being altered.

					if( $c ) {
						//Label can not be already used
						$checkResult = $leads->checkInboundFeedLabelExists( $_REQUEST['label'] );
						if( true === $checkResult ) {
							$c = false;
							$result['error'] = 'Label is already in use.';
						}
					}

					if( $c ) {
						$alterResult = $leads->renameInboundTables( $feed->label, $_REQUEST['label'] );
						if( null === $alterResult ) {
							$c = false;
							$result['error'] = "Error renaming database tables to new label.";
						}
					}
				}

				if( $c ) {
					// Remove old notifications from the database if we've now disabled them
					if( empty( $_REQUEST['notifications'] ) ) {
						$leads->deleteNotifications( $_REQUEST['idFeedIn'] );
					}
				}

				if( $c ) {
					$status = $leads->updateInboundFeed( $_REQUEST['idFeedIn'], array(
						'label' => empty( $_REQUEST['label'] ) ? null : $_REQUEST['label'],
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'],
						'required' => empty( $_REQUEST['required'] ) ? null : implode( ';', $_REQUEST['required'] ),
						'allowedFields' => empty( $_REQUEST['allowedFields'] ) ? null : implode( ';', $_REQUEST['allowedFields'] ),
						'password' => genFeedPass(),
						'dedupeEmail' => empty( $_REQUEST['dedupeEmail'] ) ? 0 : 1,
						'dedupeLandline' => empty( $_REQUEST['dedupeLandline'] ) ? 0 : 1,
						'dedupeCellphone' => empty( $_REQUEST['dedupeCellphone'] ) ? 0 : 1,
						'dedupeAcross' => empty( $_REQUEST['dedupeAcross'] ) ? null : $_REQUEST['dedupeAcross'],
						'filterTypeUrl' => empty( $_REQUEST['filterTypeUrl'] ) ? null : $_REQUEST['filterTypeUrl'],
						'filterUrl' => empty( $filterUrl ) ? null : $filterUrl,
						'filterTypeSiftLogic' => empty( $_REQUEST['filterTypeSiftLogic'] ) ? null : 'accept',
						'filterSiftLogic' => empty( $filterSiftLogic ) ? null : $filterSiftLogic,
						'notifications' => empty( $_REQUEST['notifications'] ) ? 0 : 1,
						'rejectOldLeads' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? 0 : 1,
						'rejectOldLeadsMaxAge' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
						'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
					) );

					if( null === $status ) {
						$c = false;
						$result['error'] = 'Error updating feed settings.';
					} else {
						$leads->auditLog( 'FEEDINC:EDIT', $_REQUEST['idFeedIn'] );
					}

				}

				if( $c ) {
					$result['status'] = 1;
					$result['error'] = 'Successfully updated feed.';
				}
			}
		break;

		case 'exportData':
			$c = true; $result['error'] = 'Failed when trying to export data.';

			if( $c && !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $_REQUEST['idFeedIn'] ) ) {
					$c = false;
					$result['error'] = 'Sorry, you do not have access to this feed.';
				}
			}

			if($c){
				$feed = $leads->getInboundFeed( $_REQUEST['idFeedIn'] );
				if($feed === false){
					$c = false; $result['error'] = 'Database failure - could not fetch feed information.';
				}
				if($c && !is_object($feed) && $feed == 0){
					$c = false; $result['error'] = 'Error - could not fetch feed. Feed does not exist.';
				}
			}
			if($c){
				if( empty( $_REQUEST['columns'] ) ) {
					$c = false; $result['error'] = 'Error - you need to select data columns to export.';
				}
			}

			if( $c ) {
				$jobId = $leads->addJob( 'export-incoming', $feed->idFeedIn, serialize( $_REQUEST ), '', 0 );
				if( null === $jobId ) {
					$c = false;
					$result['error'] = 'Error adding this job to the database.';
				} else {
					$leads->auditLog( 'FEEDINC:EXPORT', $jobId );
					$result['status'] = 1;
					$result['error'] = 'Export job #' . $jobId . ' submitted succesfully. You will be notified by email when your download is ready.';
				}
			}

		break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){
	switch($_REQUEST['d']){
		case 'errorCount':
			Display::errorCount();
		break;

		case 'errorList':
			Display::errorList();
		break;

		case 'dialog_editfeed':
			$id = 'edit_feedinc';
			$mode = 'edit';
			$idFeedIn = $_REQUEST['idFeedIn'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getInboundFeed( $idFeedIn );
			if($feed === false){
?>
<p>Database failure - could not fetch requested feed information.</p>
<?php
				exit;
			} elseif(!is_object($feed) && $feed == 0){
?>
<p>Could not fetch requested feed information - feed does not exist.</p>
<?php
				exit;
			}
			$selectedRequired = explode(";", $feed->required);
			$selectedAllowedFields = explode(";", $feed->allowedFields);

		case 'dialog_newfeed':
			if( empty( $id ) ) {
				$id = 'new_feedinc';
			}
			if( empty( $mode ) ) {
				$mode = 'new';
			}
			$feedProps = array('idFeedIn', 'label', 'description', 'idCompany'
				, 'dedupeEmail', 'dedupeLandline', 'dedupeCellphone', 'dedupeAcross', 'filterTypeUrl', 'filterTypeSiftLogic', 'notifications', 'status', 'rejectOldLeadsMaxAge',
			);
			foreach($feedProps as $feedProp){
				if(isset($feed)){
					${"feed_".$feedProp} = $feed->$feedProp;
				}elseif(isset($_REQUEST['options'][$feedProp])){
					${"feed_".$feedProp} = $_REQUEST['options'][$feedProp];
				}else {
					if(in_array($feedProp, array('dedupeEmail', 'dedupeLandline', 'dedupeCellphone'))){
						${"feed_".$feedProp} = '0';
					} else if(in_array($feedProp, array('notifications'))) {
						${"feed_".$feedProp} = '1';
					} else if(in_array($feedProp, array('rejectOldLeadsMaxAge'))) {
						${"feed_".$feedProp} = '7 Days Ago';
					} else {
						${"feed_".$feedProp} = '';
					}
				}
			}
			$explodableProperties = array(
				'filterUrl',
				'filterSiftLogic',
			);
			foreach($explodableProperties as $eP){
				if( !isset($_REQUEST['options'][$eP]) ){
					if(!isset($feed->$eP)){
						${"feed_".$eP} = array();
					} else {
						${"feed_".$eP} = explode(";", $feed->$eP);
					}
				} else {
					if($_REQUEST['options'][$eP] == ''){
						${"feed_".$eP} = array();
					} else {
						${"feed_".$eP} = explode(";", $_REQUEST['options'][$eP]);
					}
				}
			}

			if(!isset($selectedRequired)){
				$selectedRequired = array('email', 'ip', 'url', 'stamp');
			}
			if(!isset($selectedAllowedFields)){
				$selectedAllowedFields = $recordFields;
			}
			$companies = $leads->getCompanies( 'active' );
?>

<form id="<?php echo $id; ?>">
<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td>Feed Label</p></td>
		<td>
				<input type='hidden' name='idFeedIn'
					id='idFeedIn'
					value='<?php echo $feed_idFeedIn; ?>'
				/>
				<input type='text' name='label'
					id='label'
					value='<?php echo $feed_label; ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td>Description</p></td>
		<td>
				<input type='text' name='description'
					id='description'
					value='<?php echo $feed_description; ?>'
				class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td>Company</p></td>
		<td>

<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>
				<?php if($companies === false){ ?>
				Database failure - could not fetch company list
				<?php } elseif(!is_object($companies) && $companies == 0){ ?>
				There are no companies in the database. Please create a company before
				creating a feed.
				<?php } else { ?>
				<select name='idCompany' 
					id='idCompany'
				>
				<?php foreach($companies as $company){ ?>
					<option value='<?php echo $company->idCompany; ?>'
					<?php if($company->idCompany == $feed_idCompany){ 
					?>selected='selected'<?php } ?>
					><?php echo $company->name; ?></option>
				<?php } ?>
				</select>
				<?php } ?>
<?php } else { ?>
				<?php echo $idCompany; ?>
				<input type="hidden" name="idCompany" id="idCompany" value="<?php echo $idCompany; ?>" />
<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td>Required Fields</p></td>
		<td>
<?php foreach($recordFields as $f){ ?>
			<input type='checkbox' name='required[]' value='<?php echo $f; ?>' <?php if(in_array($f, $selectedRequired)){ ?>checked='checked'<?php } ?>	/> <?php echo $f; ?>
<?php } ?>
<?php foreach($incomingAdditionalRequirementSettings as $f){ ?>
			<input type='checkbox' name='required[]' value='<?php echo $f; ?>' <?php if(in_array($f, $selectedRequired)){ ?>checked='checked'<?php } ?>	/> <?php echo $f; ?>
<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td>Allowed Fields</p></td>
		<td>
<?php foreach($recordFields as $f){ ?>
			<input type='checkbox' name='allowedFields[]' value='<?php echo $f; ?>'	<?php if(in_array($f, $selectedAllowedFields)){ ?>checked='checked'<?php } ?> /> <?php echo $f; ?>
<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td>Duplicate Filters</p></td>
		<td>
			<input type='checkbox' name='dedupeEmail' value='1'	<?php if($feed_dedupeEmail){ ?>checked='checked'<?php } ?> /> Reject Duplicate Emails
			<input type='checkbox' name='dedupeLandline' value='1' <?php if($feed_dedupeLandline){ ?>checked='checked'<?php } ?> /> Reject Duplicate Landline Numbers
			<input type='checkbox' name='dedupeCellphone' value='1'	<?php if($feed_dedupeCellphone){ ?>checked='checked'<?php } ?> /> Reject Duplicate Cellphone Numbers
			</p>
		</td>
	</tr>
	<tr>
		<td>Duplicate Options</p></td>
		<td>
				DISABLED: <input type='radio' name='dedupeAcross' id='dedupeAcross_none' value='none' <?php if($feed_dedupeAcross == 'none'){ ?>checked='checked'<?php } ?> /> Allow duplicate records<br/>
				THIS FEED: <input type='radio' name='dedupeAcross' id='dedupeAcross_all' value='all' <?php if($feed_dedupeAcross == 'all'){ ?>checked='checked'<?php } ?> />  Dedupe across all records of this feed
				<input type='radio'	name='dedupeAcross' id='dedupeAcross_url' value='url' <?php if( $feed_dedupeAcross == 'url'){ ?>checked='checked'<?php } ?> /> Dedupe across same URL of this feed
				<input  type='radio' name='dedupeAcross' id='dedupeAcross_listcode' value='listcode' <?php if($feed_dedupeAcross == 'listcode'){ ?>checked='checked'<?php } ?>	/> Dedupe across same listcode of this feed<br/>
				ALL FEEDS: <input type='radio' name='dedupeAcross' id='dedupeAcross_global' value='allGlobal' <?php if($feed_dedupeAcross == 'allGlobal'){ ?>checked='checked'<?php } ?> />  Dedupe across all records of all feeds
				<input type='radio'	name='dedupeAcross' id='dedupeAcross_url' value='urlGlobal' <?php if( empty( $feed_dedupeAcross ) || $feed_dedupeAcross == 'urlGlobal'){ ?>checked='checked'<?php } ?> /> Dedupe across same URL of all feeds
				<input  type='radio' name='dedupeAcross' id='dedupeAcross_listcode' value='listcodeGlobal' <?php if($feed_dedupeAcross == 'listcodeGlobal'){ ?>checked='checked'<?php } ?>	/> Dedupe across same listcode of all feeds
			</p>
		</td>
	</tr>
		<tr>
				<td>URL Filter Options</p></td>
				<td>

								Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into
								the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.
						</p>

								<input type='radio'
										name='filterTypeUrl'
										id='filterTypeUrl_disabled'
										value=''
										<?php if(
												empty($feed_filterTypeUrl)
										){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#toggler_filterTypeUrl').hide(); <?php
										?>$('#filterUrl_descriptor').html('Do nothing with');"
								/> Disabled<br />
								<input type='radio'
										name='filterTypeUrl'
										id='filterTypeUrl_accept'
										value='accept'
										<?php if($feed_filterTypeUrl == 'accept'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#toggler_filterTypeUrl').show(); <?php
										?>$('#filterUrl_descriptor').html('Accept');"
								/> Accept<br />
								<input type='radio'
										name='filterTypeUrl'
										id='filterTypeUrl_reject'
										value='reject'
										<?php if($feed_filterTypeUrl == 'reject'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#toggler_filterTypeUrl').show(); <?php
										?>$('#filterUrl_descriptor').html('Reject');"
								/> Reject<br />
						</p>
						<div id='toggler_filterTypeUrl'
								style='display:<?php
										if(empty($feed_filterTypeUrl)){ echo "none"; }
										else { echo "block"; }
								?>;'
						>
								<p>The following urls:</p>
								<p>
										<a href='#' class='nonLink'
				onclick='element("filterUrl_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Url" });'
										>Add New URL to <span id='filterUrl_descriptor'></span></a>
										| <a href='#' class='nonLink'
												onclick='element("filterUrl_multipleInsert"<?php
												?>, "element_multifilter"<?php
												?>, { "e": "<?php echo $e; ?>"<?php
												?>, "type": "Url" });'
										>Add Multiple</a>
								</p>
								<div id='filterUrl_multipleInsert'></div>
								<div id='filterUrl_container'>
								<?php foreach($feed_filterUrl as $filterUrl){ ?>
										<div>
												<input type='text'
														name='filterUrl[]'
														value='<?php echo $filterUrl; ?>'
												/>
												<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
										</div>
								<?php } ?>
								</div>
						</div>
				</td>
		</tr>
		<tr>
				<td><p>SiftLogic Filter Options</p></td>
				<td>
						<p>
								Using the 'Enabled' option, urls that are listed here will be filtered through SiftLogic.
						</p>
						<p>
								<input type='radio'
										name='filterTypeSiftLogic'
										id='filterTypeSiftLogic_disabled'
										value=''
										<?php if(
												empty($feed_filterTypeSiftLogic)
										){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#toggler_filterTypeSiftLogic').hide();"
								/> Disabled<br />
								<input type='radio'
										name='filterTypeSiftLogic'
										id='filterTypeSiftLogic_accept'
										value='accept'
										<?php if($feed_filterTypeSiftLogic == 'accept'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#toggler_filterTypeSiftLogic').show();"
								/> Enabled<br />
						</p>
						<div id='toggler_filterTypeSiftLogic'
								style='display:<?php
										if(empty($feed_filterTypeSiftLogic)){ echo "none"; }
										else { echo "block"; }
								?>;'
						>
								<p>The following urls:</p>
								<p>
										<a href='#' class='nonLink'
				onclick='element("filterSiftLogic_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "SiftLogic" });'
										>Add New URL to filter</a>
										| <a href='#' class='nonLink'
												onclick='element("filterSiftLogic_multipleInsert"<?php
												?>, "element_multifilter"<?php
												?>, { "e": "<?php echo $e; ?>"<?php
												?>, "type": "SiftLogic" });'
										>Add Multiple</a>
								</p>
								<div id='filterSiftLogic_multipleInsert'></div>
								<div id='filterSiftLogic_container'>
								<?php foreach($feed_filterSiftLogic as $filterSiftLogic){ ?>
										<div>
												<input type='text'
														name='filterSiftLogic[]'
														value='<?php echo $filterSiftLogic; ?>'
												/>
												<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
										</div>
								<?php } ?>
								</div>
						</div>
				</td>
		</tr>
	<tr>
		<td>Lead Rejections</p></td>
		<td>
			<p>How old are leads allowed to be before we reject them?  This should be a text string like "7 Days Ago" or "30 Days Ago".  Do not enter just a number. A blank value disables this feature.</p>
			<p>
				<input type='text' name='rejectOldLeadsMaxAge' id='rejectOldLeadsMaxAge' value='<?php echo $feed_rejectOldLeadsMaxAge; ?>' class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td>Notifications</p></td>
		<td>
			<p>Should we send dormant URL notifications for URLs in this feed?</p>
			<p>
				<input type='radio' name='notifications' id='notifications_yes' value='1' <?php if( '1' == $feed_notifications ) { ?>checked='checked'<?php } ?>/> Enabled
				<input type='radio' name='notifications' id='notifications_no' value='0' <?php if( $feed_notifications != '1' ) { ?>checked='checked'<?php } ?>/> Disabled
			</p>
		</td>
	</tr>
	<tr>
		<td>Feed Status</p></td>
		<td>
			<p>
				<input type='radio' name='status' id='status_active' value='active' <?php if( empty( $feed_status ) || 'active' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Visible)<br/>
				<input type='radio' name='status' id='status_hidden' value='hidden' <?php if( 'hidden' == $feed_status ) { ?>checked='checked'<?php } ?>/> Active (Hidden)<br/>
				<input type='radio' name='status' id='status_retired' value='retired' <?php if( 'retired' == $feed_status ) { ?>checked='checked'<?php } ?>/> Retired
			</p>
		</td>
	</tr>
</table>
<input type="hidden" name="a" value="manageFeed" />
<input type="hidden" name="action" value="<?php echo $mode; ?>" />
</form>
<?php
		break;

		case 'dialog_import':
			$idFeedIn = $_REQUEST['idFeedIn'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getInboundFeed( $idFeedIn );

			if($feed === false){
?>
<p>Database failure - could not fetch feed information.</p>
<?php
			} elseif(!is_object($feed) && $feed == 0){ 
?>
<p>Error fetching feed information - feed does not exist.</p>
<?php
			} else {

$company = $leads->getCompany( $feed->idCompany );

?>
<p><strong>Company:</strong> <?php echo htmlentities( $company->name ); ?></p>
<p><strong>Feed:</strong> <?php echo htmlentities( $feed->label ); ?> (#<?php echo $feed->idFeedIn; ?>)</p>

<form enctype="multipart/form-data" id="form-import" action="mgr_import.php" method="post" target="_blank">
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE; ?>" />
<input type="hidden" name="destination" value="<?php echo intval( $idFeedIn ); ?>" />
<input type="hidden" name="type" value="feedinc" />
<input type="hidden" name="a" value="Upload" />

<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td>File</p></td>
		<td>Please select the file to upload from your computer.  File must be in CSV format.  Limit <?php echo (MAX_UPLOAD_SIZE / 1024000); ?>MB.</p><input type="file" name="import_file" multiple="false" accept="text/csv" /></p></td>
	</tr>
	<tr>
		<td>Field mapping</p></td>
		<td>
<?php
		$allowedFields = explode(";", $feed->allowedFields);
		$requiredFields = explode(";", $feed->required);

		// Add a separate time field in case the file uses separate columns
		if( ( $key = array_search( 'stamp', $allowedFields ) ) !== FALSE ) {
			array_splice( $allowedFields, $key+1, 0, 'time' );
		}

		foreach( $allowedFields as $field) {
			printf ("<p>%s%s <select name=\"field_%s\">",
				$field, in_array($field, $requiredFields) ? '*' : '', $field);
			print "<option>--</option>\n";
			for($i = 0; $i < 26; $i++) {
				print "<option value=\"{$i}\">" . chr(65+$i) . "</option>\n";
			}
			print "</select>";
			if( 'stamp' == $field ) {
				print " (Use for either a full date+time stamp or just a date stamp field)";
			} else if( 'time' == $field ) {
				print " (Use for just a time stamp field)";
			}
			print "</p>\n";
		}

?>
		</td>
	</tr>
</table>
</form>
<?php
			}
		break;
		case 'dialog_export':
			$idFeedIn = $_REQUEST['idFeedIn'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<?php
			if($feed === false){
?>
<p>Database failure - could not fetch feed information.</p>
<?php
			} elseif(!is_object($feed) && $feed == 0){
?>
<p>Error fetching feed information - feed does not exist.</p>
<?php
			} else {
?>
<p>Exporting Data from Feed (ID:<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
<form id="form-export">
<input type="hidden" name="idFeedIn" value="<?php echo $feed->idFeedIn; ?>" />
<input type="hidden" name="a" value="exportData" />
<input type="hidden" name="label" value="<?php echo htmlspecialchars( $feed->label, ENT_QUOTES ); ?>" />
<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td colspan='2'><p class='aCenter'>Export Settings</p></td>
	</tr>
	<tr>
		<td>
			Columns
		</td>
		<td>
			<?php foreach($recordFields as $f){ ?>
				<input type='checkbox' name='columns[]' value='<?php echo $f; ?>' /> <?php echo $f; ?>
			<?php } ?>
		</td>
	</tr>
	<tr>
		<td>
			Period
		</td>
		<td>
				<p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
				<p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date("Y-m-d"); ?>' />
				to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>' /></p>
		</td>
	</tr>
	<tr>
		<td>
				URLs
		</td>
		<td>
				<p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
				<p><a href='#' class='nonLink' onclick='element("export_<?php echo $idFeedIn; ?>_urls", "urlField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'>Add URL</a></p>
			<div>
				<div id='export_<?php echo $idFeedIn; ?>_urls' >
				</div>
			</div>
			</p>
		</td>
	</tr>
	<tr>
		<td>
				Email domains
		</td>
		<td>
				<p>Email domains to limit the selection by. Leave blank to select all records regardless of email address.  Do not include the @ symbol.</p>
				<p><a href='#' class='nonLink' onclick='element("export_<?php echo $idFeedIn; ?>_emails", "emailField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'>Add email domain</a></p>
			<div>
				<div id='export_<?php echo $idFeedIn; ?>_emails' >
				</div>
			</div>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			Limit</p>
		</td>
		<td>
				<p>Set a limit on the number of records that are returned.  Leave blank to return ALL records.</p>
				<p><input type="text" name="limit" value="" /></p>
			</p>
		</td>
	</tr>
</table>
</form>
<?php
			}
		break;

		case 'dialog_urlreport':
			$idFeedIn = !empty( $_REQUEST['idFeedIn'] ) ? $_REQUEST['idFeedIn'] : 0;

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			if( empty( $_REQUEST['submit'] ) ) {
				$_REQUEST['dateStart'] = date("Y-m-d");
				$_REQUEST['dateEnd'] = date("Y-m-d", strtotime('Tomorrow'));
				$_REQUEST['urlList'] = array();
				$_REQUEST['breakdown'] = 'day';
				$_REQUEST['sort'] = 'date';
			}
			$_REQUEST['dateStart'] = !empty( $_REQUEST['dateStart'] ) ? $_REQUEST['dateStart'] : '';
			$_REQUEST['dateEnd'] = !empty( $_REQUEST['dateEnd'] ) ? $_REQUEST['dateEnd'] : '';
			$_REQUEST['urlList'] = !empty( $_REQUEST['urlList'] ) && is_array( $_REQUEST['urlList'] ) ? $_REQUEST['urlList'] : array();
			$_REQUEST['breakdown'] = !empty( $_REQUEST['breakdown'] ) ? $_REQUEST['breakdown'] : 'day';
			$_REQUEST['sort'] = !empty( $_REQUEST['sort'] ) ? $_REQUEST['sort'] : 'date';

			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<?php
			if($feed === false){
?>
<p>Database failure - could not fetch feed information.</p>
<?php 
			} elseif(!is_object($feed) && $feed == 0){
?>
<p>Error fetching feed information - feed does not exist.</p>
<?php
			} else {
?>
<p>URL Report from Feed (ID:<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
<form id="form-urlreport">
<input type="hidden" name="idFeedIn" value="<?php echo $feed->idFeedIn; ?>" />
<input type="hidden" name="d" value="dialog_urlreport" />
<input type="hidden" name="submit" value="submit" />
<table class="table table-bordered table-condensed table-striped">
	<tr>
		<td>
			Period
		</td>
		<td>
				<p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
				<p><input type='text' name='dateStart' class='dateSelector' value='<?php echo htmlspecialchars(  $_REQUEST['dateStart'], ENT_QUOTES ); ?>' />
				to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo htmlspecialchars(  $_REQUEST['dateEnd'], ENT_QUOTES ); ?>' /></p>
		</td>
	</tr>
	<tr>
		<td>
				URLs
		</td>
		<td>
				<p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
<?php
				$urls = $leads->getInboundURLDates( $idFeedIn );
				if( $urls && is_array( $urls ) ) {
					printf( "<select multiple=\"multiple\" name=\"urlList[]\" size=\"%d\">\n", sizeOf( $urls ) );
					foreach( $urls as $url ) {
						printf( "<option value=\"%s\"%s>%s (%s)</option>\n", htmlspecialchars( $url['url'], ENT_QUOTES ), in_array( $url['url'], $_REQUEST['urlList'] ) ? ' selected="selected"' : '', htmlspecialchars( $url['url'] ), $url['date'] );
					}
					print "</select>\n";
				}
?>
		</td>
	</tr>
	<tr>
		<td>
				Count By
		</td>
		<td>
			<select name="breakdown">
<?php
			$choices = array(
				'day' => 'Day',
				'month' => 'Month',
				'year' => 'Year',
				'total' => 'Total',
			);
			foreach( $choices as $key => $val ) {
				printf( "<option value=\"%s\"%s>%s</option>\n",
					htmlspecialchars( $key, ENT_QUOTES ),
					$_REQUEST['breakdown'] === $key ? ' selected="selected"' : '',
					htmlspecialchars( $val )
				);
			}
?>
			</select>
		</td>
	</tr>
	<tr>
		<td>
				Sort By
		</td>
		<td>
			<select name="sort">
<?php
			$choices = array(
				'date' => 'Date',
				'url' => 'URL',
				'count' => 'Count',
			);
			foreach( $choices as $key => $val ) {
				printf( "<option value=\"%s\"%s>%s</option>\n",
					htmlspecialchars( $key, ENT_QUOTES ),
					$_REQUEST['sort'] === $key ? ' selected="selected"' : '',
					htmlspecialchars( $val )
				);
			}
?>
			</select>
		</td>
	</tr>
</table>
</form>
<?php

				if( !empty( $_REQUEST['submit'] ) ) {

					$stats = $leads->getInboundURLStatsReport( $_REQUEST['idFeedIn'], $_REQUEST['urlList'], $_REQUEST['breakdown'], $_REQUEST['dateStart'], $_REQUEST['dateEnd'], $_REQUEST['sort'] );

					if( empty( $stats ) ) {
?>
<p>No records found.</p>
<?php 
					} else {

						$fileLink = 'exports/' . $feed->label."_".time().".csv";
						$filePath = ADMIN_ROOT.$fileLink;
						$file = fopen($filePath, "w");
						if(!file_exists($filePath)){
?>
<p>Failed to create CSV report file.</p>
<?php 
						} else {
							fputcsv( $file, array( 'URL', 'Date', 'Accepted', 'Rejected' ) );
							print "<table class=\"table table-bordered table-condensed table-striped\">\n";
							print "<thead>\n";
							print "\t<tr>\n";
							print "\t<td>URL</td>\n";
							print "\t<td>Date</td>\n";
							print "\t<td>Accepted</td>\n";
							print "\t<td>Rejected</td>\n";
							print "\t</tr>\n";
							print "</thead>\n";
							print "<tbody>\n";
							print "\t<tr>\n";
							foreach( $stats as $stat ) {
								print "\t<tr>\n";
								printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['url'] ) );
								printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['date'] ) );
								printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['accepted'] ) );
								printf("\t\t<td>%s</td>\n", htmlspecialchars( $stat['rejected'] ) );
								print "\t</tr>\n";
								fputcsv( $file, array( $stat['url'], $stat['date'], $stat['accepted'], $stat['rejected'] ) );
							}
							fclose($file);
							print "</tbody>\n";
							print "</table>\n";
							printf( '<p><a <a class="btn btn-primary" href="%s">Export this report</a></p>', $fileLink );
						}
					}

				}

			}
		break;

		case 'urlField':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
?>
<div>
	URL: <input type='text' name='urlList[]' value='' />
	<a href='#' class='nonLink' onclick='$(this).parent().remove();' >[X]</a>
</div>
<?php
		break;
		case 'emailField':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
?>
<div>
	Email domain: <input type='text' name='emailList[]' value='' /> (do not include @ symbol)
	<a href='#' class='nonLink' onclick='$(this).parent().remove();' >[X]</a>
</div>
<?php
		break;
		case 'dialog_listcodes':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];

			if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
				$idCompany = LeadsSession::getCompanyId();
				if( empty( $idCompany ) ) {
				$idCompany = -9999;
				}
				if( !$leads->checkInboundFeedAccess( $idCompany, $idFeedIn ) ) {
					die( 'Sorry, you do not have access to this feed.' );
				}
			}

			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<p>Generate New Listcode for (<?php echo $feed->idFeedIn; ?>) <?php echo $feed->label; ?></p>
<p>
	Select an Option:
	<select id='' name='' 
		onchange="display('dialog_listcodeManager', {'sub': <?php echo $feed->idFeedIn; ?>, 'type': $(this).val() });"
	>
		<option value='0' >Choose: </option>
		<option value='1' >Generate Listcode for Single Url</option>
		<option value='2' >Generate Individual Listcodes for Multiple Urls</option>
		<option value='3' >Generate Listcode for URL Group</option>
		<option value='4' >Browse existing listcodes</option>
	</select>
</p>
<div id='dialog_listcodeManager_<?php echo $feed->idFeedIn; ?>'>
</div>
<?php
		break;
		case 'dialog_listcodeManager':
			switch($_REQUEST['options']['type']){
				case 0:
?>
<p>Please choose an option.</p>
<?php
				break;
				case 1:
?>
<p>Individual URL Listcode</p>
<div>
	<input type='text' 
		name='<?php echo $e; ?>popset_filterUrl[]'
		value='<?php echo $filterUrl; ?>'
	/>
	<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
				break;
				case 2:
?>
<p>Multiple Individual URL Listcodes</p>
<?php
				break;
				case 3:
?>
<p>URL Group Listcode</p>
<?php
				break;
				case 4:
?>
<p>Browse Listcodes</p>
<?php
				break;
			}			
		break;
				case 'element_filter':
						$e = $_REQUEST['options']['e'];
						$t = $_REQUEST['options']['type'];
?>
<div>
		<input type='text'
				name='filter<?php echo $t; ?>[]'
				value='<?php if(isset($_REQUEST['options']['value'])){
						echo $_REQUEST['options']['value'];
				} ?>'
		/>
		<a href='#' class='nonLink' onclick='$(this).parent().remove(); return false;' >[X]</a>
</div>
<?php
				break;
				case 'element_multifilter':
						$e = $_REQUEST['options']['e'];
						$t = $_REQUEST['options']['type'];
?>
<textarea name='filter<?php echo $t; ?>Multi' id='filter<?php echo $t; ?>Multi' ></textarea>
<?php
				break;

		default:
?>
<p>Requested information doesn't exist.</p>
<?php
		break;
	}
	exit;
}

$title = 'Incoming Feed Manager';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<h2>Incoming Feeds</h2>

<?php if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) { ?>

<form class="pull-right" id="status-select" method="get">
<select id="status" name="status">
	<option value="active"<?php if( 'active' === $status ) { print ' selected="selected"'; } ?>>Show active feeds</option>
	<option value="hidden"<?php if( 'hidden' === $status ) { print ' selected="selected"'; } ?>>Show hidden feeds</option>
	<option value="retired"<?php if( 'retired' === $status ) { print ' selected="selected"'; } ?>>Show retired feeds</option>
	<option value=""<?php if( null === $status ) { print ' selected="selected"'; } ?>>Show all feeds</option>
</select>
</form>

<p><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newfeedinc">Add a new feed</button></p>

<?php } ?>

<?php
if( LeadsSession::isValid( LEADS_SESSION_LEVEL_STAFF ) ) {
	$incomingFeeds = $leads->getInboundFeeds( null, $status );
} else {
	$idCompany = LeadsSession::getCompanyId();
	if( empty( $idCompany ) ) {
		$idCompany = -9999;
	}
	$incomingFeeds = $leads->getInboundFeeds( $idCompany, $status );
}
?>
<?php
if($incomingFeeds === false){
?>
<p>Error when trying to fetch feeds: database error.</p>
<?php
} else if($incomingFeeds == 0){
?>
<p>Error when trying to fetch feeds: there are no feeds.</p>
<?php
} else {
	//Go through each and compile the company list.
	$companyFeedLists = array();
	foreach($incomingFeeds as $feed){
		//Add company to the cache list of companies.
		if(!isset($companyCache[$feed->idCompany])){
			$company = $leads->getCompany( $feed->idCompany );
			if(	is_object($company) ){
				$companyCache[$feed->idCompany] = $company;
				$companyFeedLists[$feed->idCompany] = array();
			}
		}
		//Add feed to list of feeds for the specified company.
		$companyFeedLists[$feed->idCompany][] = $feed;
	}
	//print_r($companyFeedLists);
	function companyListSort($item1,$item2)
	{
		global $companyCache;
		//print_r($companyCache[$item1]->name);
		//print_r($companyCache[$item2]->name);
		return strcasecmp ( $companyCache[$item1]->name , $companyCache[$item2]->name );
	}
	uksort($companyFeedLists,'companyListSort');
?>
<table class="table table-bordered table-condensed table-striped-custom">
	<thead>
	<tr class='bgGray'>
		<th>Company</td>
		<th class="text-right">Accepted</td>
		<th class="text-right">Rejected</td>
		<th>Actions</td>
	</tr>
	</thead>
<?php
	$grandTotalFeeds = 0;
	$grandTotalAccepted = 0;
	$grandTotalRejected = 0;
	foreach($companyFeedLists as $idCompany => $companyFeedList){
		$totalAccepted = 0;
		$totalRejected = 0;
		foreach($companyFeedList as $keyFeed => $feed){

			$stats = $leads->getInboundStats( $feed->idFeedIn );

			$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
			$totalAccepted += $stats['accepted'];
			$grandTotalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];
			$grandTotalRejected += $stats['rejected'];

		}
		$grandTotalFeeds += count($companyFeedList);
?>
	<tr class="custom-master">
		<td><?php echo $companyCache[$idCompany]->name; ?> (<?php echo count($companyFeedList); ?>)</td>
		<td class="text-right"><?php echo number_format( $totalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $totalRejected, 0 ); ?></td>
		<td class="text-center"><button class="btn btn-primary btn-xs" type="button" data-toggle="collapse" data-target=".feed-toggle-<?php echo $idCompany; ?>" aria-expanded="false" aria-controls="collapseExample">Show Feeds</button></td>
	</tr>
<?php
		foreach( $companyFeedList as $feed ) {
?>
	<tr class="collapse bg-gray feed-toggle feed-toggle-<?php echo $idCompany; ?>">
		<td class="status-<?php print $feed->status; ?>"><?php echo $feed->idFeedIn; ?>: <?php echo $feed->label; ?> (<?php echo htmlentities( $feed->description ); ?>)</td>
		<td class="text-right"><?php echo $feed->dailyCount; ?></td>
		<td class="text-right"><a href="mgr_rejections.php?type=inbound&amp;id=<?php echo urlencode( $feed->idFeedIn ); ?>&amp;label=<?php echo urlencode( $feed->label ); ?>" target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></td>
		<td class="text-center">
<div class="btn-group">
  <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editfeedinc" data-feedinc-id="<?php echo intval( $feed->idFeedIn ); ?>">Edit Feed</button>
  <button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
	<span class="caret"></span>
	<span class="sr-only">Toggle Dropdown</span>
  </button>
  <ul class="dropdown-menu">
	<li><a href="/leadadmin/apispec.php?idFeedIn=<?php echo $feed->idFeedIn; ?>" target="_blank">API Spec</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-import" data-feedinc-id="<?php echo intval( $feed->idFeedIn ); ?>">Import data</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-export" data-feedinc-id="<?php echo intval( $feed->idFeedIn ); ?>">Export data</a></li>
	<li><a href="#" data-toggle="modal" data-target="#modal-urlreport" data-feedinc-id="<?php echo intval( $feed->idFeedIn ); ?>">URL report</a></li>
  </ul>
</div>
</td>
	</tr>
<?php
		}
	}
?>
	<tfoot>
	<tr>
		<td>GRAND TOTAL</td>
		<td class="text-right"><?php echo number_format( $grandTotalAccepted, 0 ); ?></td>
		<td class="text-right"><?php echo number_format( $grandTotalRejected, 0 ); ?></td>
		<td></td>
	</tr>
	</tfoot>
</table>
<?php } ?>

</div>

<div class="modal fade" id="newfeedinc" tabindex="-1" role="dialog" aria-labelledby="newfeedinc_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newfeedinc_title">Add a new incoming feed</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newfeedinc" type="button" class="btn btn-primary">Add feed</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editfeedinc" tabindex="-1" role="dialog" aria-labelledby="editfeedinc_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editfeedinc_title">Edit an incoming feed</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editfeedinc" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-import" tabindex="-1" role="dialog" aria-labelledby="modal-import_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="modal-import_title">Import legacy data</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-import" type="button" class="btn btn-primary">Import Data</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-export" tabindex="-1" role="dialog" aria-labelledby="modal-export_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="modal-export_title">Export legacy data</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-export" type="button" class="btn btn-primary">Export Data</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="modal-urlreport" tabindex="-1" role="dialog" aria-labelledby="modal-urlreport_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="modal-urlreport_title">URL Report</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-urlreport" type="button" class="btn btn-primary">Run Report</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newfeedinc').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: $("#new_feedinc").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#newfeedinc').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: {
			'd': 'dialog_newfeed'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editfeedinc').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: $("#edit_feedinc").serialize()
	}).done(function(result){
		if(result.status == 1){
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editfeedinc').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedIn = $(e.relatedTarget).data('feedinc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: {
			'd': 'dialog_editfeed',
			'idFeedIn': idFeedIn
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-import').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedIn = $(e.relatedTarget).data('feedinc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: {
			'd': 'dialog_import',
			'idFeedIn': idFeedIn
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-import').click(function(event) {
	event.preventDefault();
	$('#form-import').submit();
});

$('#modal-export').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedIn = $(e.relatedTarget).data('feedinc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: {
			'd': 'dialog_export',
			'idFeedIn': idFeedIn
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-export').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: $("#form-export").serialize()
	}).done(function(result){
		alert(result.error);
		if(result.status == 1){
			window.location.reload(true);
		}
	});
});

$('#modal-urlreport').on('show.bs.modal', function(e) {
	var modal = $(this);
	var idFeedIn = $(e.relatedTarget).data('feedinc-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: {
			'd': 'dialog_urlreport',
			'idFeedIn': idFeedIn
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-urlreport').click(function(event) {
	event.preventDefault();

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'mgr_feedinc.php',
		data: $("#form-urlreport").serialize(),
		success: function(data) {
			$('#modal-urlreport').find('.modal-body').html(data);
		}
	});
});

$('#newfeedinc, #editfeedinc').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});

$('#status-select select').change(function(e) {
	e.preventDefault();
	$('#status-select').submit();
});

$('.feed-toggle').on('show.bs.collapse', function() {
	$(this).prev().find('button[data-toggle="collapse"]').html('Hide Feeds');
});
$('.feed-toggle').on('hide.bs.collapse', function() {
	$(this).prev().find('button[data-toggle="collapse"]').html('Show Feeds');
});
</script>

</body>
</html>
