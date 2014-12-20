<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

$mysqlErrorSource = 'Manager - Incoming Feeds';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_feedinc"; 
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

if(isset($_REQUEST['a'])){ 
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
				$result['error'] = 'Label cannot be empty.';
			}

			if( empty( $_REQUEST['idCompany'] ) ) {
				$c = false;
				$result['error'] = 'Company cannot be empty.';
			}

			if( $c ) {
				//Label cannot have invalid characters
				$pattern = '/^[a-z][a-z0-9_]*$/';
				if(!preg_match($pattern, $_REQUEST['label'])){ 
					$c = false;
					$result['error'] = 'Labels must start with a letter, can contain lowercase letters, numbers, and underscore only.';
				}
			}

			if( $c && empty( $_REQUEST['allowedFields'] ) ) {
				// Must allow some fields, or the feed is worthless isn't it
				$c = false;
				$result['error'] = 'You must allow at least one field to be processed.'; 
			}

			if( $c ) {
				//Make sure that any required fields are also allowed
				$selectedRequired = explode( ";", $_REQUEST['required'] );
				$selectedAllowedFields = explode( ";", $_REQUEST['allowedFields'] );
				foreach( $selectedRequired as $f ) {

					switch( $f ) {
						case "phone":
							if( !in_array( 'landline', $selectedAllowedFields ) || !in_array( 'cellphone', $selectedAllowedFields ) ) {
								$c = false;
								$result['error'] = 'If phone is selected, both landline and cellphone must be allowed fields.';
							}
							break;

						default: 
							if( !in_array( $f, $selectedAllowedFields ) ) {
								$c = false;
								$result['error'] = "If {$f} is a required field, then that field must be allowed as well.";
							}
					}
					if( !$c ) {
						break; 
					}
				}
			}

			if( 'new' == $action ) {

				if( $c ) {
					//Label can not be already used
					$checkResult = $leads->checkInboundFeedLabelExists( $_REQUEST['label'] );
					if( true === $checkResult ) {
						$c = false;
						$result['error'] = 'Label is already in use.'; 
					}					
				}

				if( $c ) { //Add entry to the database.
					$idFeedIn = $leads->addInboundFeed( array(
						'label' => empty( $_REQUEST['label'] ) ? null : $_REQUEST['label'],
						'description' => empty( $_REQUEST['description'] ) ? null : $_REQUEST['description'],
						'idCompany' => empty( $_REQUEST['idCompany'] ) ? null : $_REQUEST['idCompany'],
						'required' => empty( $_REQUEST['required'] ) ? null : $_REQUEST['required'],
						'allowedFields' => empty( $_REQUEST['allowedFields'] ) ? null : $_REQUEST['allowedFields'],
						'password' => genFeedPass(),
						'dedupeEmail' => empty( $_REQUEST['dedupeEmail'] ) ? null : $_REQUEST['dedupeEmail'],
						'dedupeLandline' => empty( $_REQUEST['dedupeLandline'] ) ? null : $_REQUEST['dedupeLandline'],
						'dedupeCellphone' => empty( $_REQUEST['dedupeCellphone'] ) ? null : $_REQUEST['dedupeCellphone'],
						'dedupeAcross' => empty( $_REQUEST['dedupeAcross'] ) ? null : $_REQUEST['dedupeAcross'],
						'filterTypeUrl' => empty( $_REQUEST['filterTypeUrl'] ) ? null : $_REQUEST['filterTypeUrl'],
						'filterUrl' => empty( $_REQUEST['filterUrl'] ) ? null : $_REQUEST['filterUrl'],
						'filterTypeSiftLogic' => empty( $_REQUEST['filterTypeSiftLogic'] ) ? null : $_REQUEST['filterTypeSiftLogic'],
						'filterSiftLogic' => empty( $_REQUEST['filterSiftLogic'] ) ? null : $_REQUEST['filterSiftLogic'],
						'notifications' => empty( $_REQUEST['notifications'] ) ? null : $_REQUEST['notifications'],
						'rejectOldLeadsMaxAge' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
					) );

					if( null === $idFeedIn ) {
						$c = false;
						$result['status'] = 0;
						$result['error'] = 'Failed to create new feed.';
					} else {
						$result['status'] = 1;
						$result['error'] = 'Successfully created new feed #{$idFeedIn}.';
						$leads->auditLog( 'FEEDINC:ADD', $idFeedIn );
					}

				}
			} else {			

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
						'required' => empty( $_REQUEST['required'] ) ? null : $_REQUEST['required'],
						'retired' => !empty( $_REQUEST['retired'] ) ? 1 : 0,
						'allowedFields' => empty( $_REQUEST['allowedFields'] ) ? null : $_REQUEST['allowedFields'],
						'dedupeEmail' => empty( $_REQUEST['dedupeEmail'] ) ? null : $_REQUEST['dedupeEmail'],
						'dedupeLandline' => empty( $_REQUEST['dedupeLandline'] ) ? null : $_REQUEST['dedupeLandline'],
						'dedupeCellphone' => empty( $_REQUEST['dedupeCellphone'] ) ? null : $_REQUEST['dedupeCellphone'],
						'dedupeAcross' => empty( $_REQUEST['dedupeAcross'] ) ? null : $_REQUEST['dedupeAcross'],
						'filterTypeUrl' => empty( $_REQUEST['filterTypeUrl'] ) ? null : $_REQUEST['filterTypeUrl'],
						'filterUrl' => empty( $_REQUEST['filterUrl'] ) ? null : $_REQUEST['filterUrl'],
						'filterTypeSiftLogic' => empty( $_REQUEST['filterTypeSiftLogic'] ) ? null : $_REQUEST['filterTypeSiftLogic'],
						'filterSiftLogic' => empty( $_REQUEST['filterSiftLogic'] ) ? null : $_REQUEST['filterSiftLogic'],
						'notifications' => empty( $_REQUEST['notifications'] ) ? null : $_REQUEST['notifications'],
						'rejectOldLeadsMaxAge' => empty( $_REQUEST['rejectOldLeadsMaxAge'] ) ? null : $_REQUEST['rejectOldLeadsMaxAge'],
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
				if($_REQUEST['exportColumns'] == ''){ 
					$c = false; $result['error'] = 'Error - you need to select data columns to export.';
				}
				$exportColumns = explode(";", $_REQUEST['exportColumns']);
			}
			if($c){
				if( !empty( $_REQUEST['exportUrlList'] ) ) {
					$exportUrlList = explode(";", $_REQUEST['exportUrlList']);
				} else {
					$exportUrlList = array();
				}
			}
			if($c){ 
				if( !empty( $_REQUEST['exportEmailList'] ) ) {
					$exportEmailList = explode(";", $_REQUEST['exportEmailList']);
				} else {
					$exportEmailList = array();
				}
			}
			if($c){
				$settings = array(
					'columns' => $exportColumns
					, 'dateStart' => $_REQUEST['exportDateStart']
					, 'dateEnd' => $_REQUEST['exportDateEnd']
					, 'limit' => $_REQUEST['exportLimit']
					, 'urlList' => $exportUrlList
					, 'emailList' => $exportEmailList
				);
				$exportResult = $leads->exportInboundRecords( $_REQUEST['idFeedIn'], $settings );
			}
			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully exported file.';
				$result['query'] = $exportResult['query'];
				$result['link'] = $exportResult['fileLink'];
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

		case 'incomingFeeds':
		if( isset( $_REQUEST['retired'] ) ) $retired = true;
		else $retired = false;
$incomingFeeds = $leads->getInboundFeeds( $retired );
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
<p>Incoming Feeds</p>
<table class='standard'>
	<thead>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p>Company</p></td>
		<td class='fTI_feedOverview'><p>Total Feeds</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Total Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Total Rejected</p></td>
		<td class='fTI_options'><p>Actions</p></td>
	</tr>
	</thead>
<?php
	foreach($companyFeedLists as $idCompany => $companyFeedList){ 
		$totalAccepted = 0;
		$totalRejected = 0;
		foreach($companyFeedList as $keyFeed => $feed){ 

			$stats = $leads->getInboundStats( $feed->idFeedIn );

			$companyFeedList[$keyFeed]->dailyCount = $stats['accepted'];
			$totalAccepted += $stats['accepted'];

			$companyFeedList[$keyFeed]->dailyCountInvalid = $stats['rejected'];
			$totalRejected += $stats['rejected'];

		}
?>
	<tr class='bgGray'>
		<td class='fTI_companyName' colspan='2'><p><?php echo $companyCache[$idCompany]->name; ?></p></td>
		<td class='fTI_feedOverview'>
			<p>
				<?php echo count($companyFeedList); ?>
			</p>
		</td>
		<td class='fTI_accepted'><p class='aRight'><?php echo $totalAccepted; ?></p></td>
		<td class='fTI_rejected'><p class='aRight'><?php echo $totalRejected; ?></p></td>
		<td class='fTI_options'>
			<p>
				<a href='#' class='nonLink'
					id='link_companyFeedList_<?php echo $idCompany; ?>'
					onclick="toggleHidden('companyFeedList', {'sub':<?php echo $idCompany; ?>, 'hiddenText':'Show Feeds', 'shownText':'Close' });"
				>Show Feeds</a>
			</p>
		</td>
	</tr>
	<tbody id='companyFeedList_<?php echo $idCompany; ?>' class='hidden'>
	<tr>
		<td colspan="3" class='fTI_description'><p>ID / Label</p></td>
		<td class='fTI_accepted'><p class='aCenter'>Accepted</p></td>
		<td class='fTI_rejected'><p class='aCenter'>Rejected</p></td>
		<td class='fTI_options'><p>Options</p></td>
	</tr>
<?php
		foreach($companyFeedList as $feed){ 
?>
	<tr>
        <td colspan="3" class='fTI_description<?php if('1' == $feed->retired) print " retired";?>'><p><?php echo $feed->idFeedIn; ?>: <?php echo $feed->label; ?> (<?php echo $feed->description; ?>)</p></td>
		<td class='fTI_accepted'><p class='aRight'><?php echo $feed->dailyCount; ?></p></td>
		<td class='fTI_rejected'><p class='aRight'><a href="mgr_rejections.php?type=inbound&amp;id=<?php echo urlencode($feed->idFeedIn);?>&amp;label=<?php echo urlencode($feed->label);?>" target="_blank"><?php echo $feed->dailyCountInvalid; ?></a></p></td>
		<td class='fTI_options'>
			<p>
				<a href='apispec.php?idFeedIn=<?php echo $feed->idFeedIn; ?>' 
					target='_blank'
				>API Spec</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_editfeed", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>Edit Feed</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_import", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>Import legacy data</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_export", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>Export Data to File</a> |
				<a href='#' class='nonLink'
onclick='display("dialog_urlreport", { "sub":"<?php echo $feed->idFeedIn; ?>", "idFeedIn": <?php echo $feed->idFeedIn; ?> });'
				>URL Report</a>
			</p>
		</td>
	</tr>
	<tr><td class='hidden' id='dialog_listcodes_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_editfeed_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_export_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_import_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_urlreport_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
	<tr><td class='hidden' id='dialog_urlreportdetails_<?php echo $feed->idFeedIn; ?>' colspan='6'></td></tr>
<?php
		}
?>
	</tbody>
<?php
	}
?>
</table>
<?php
}

			
			
		break;
		
		case 'dialog_editfeed':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$e = 'edit_'.$idFeedIn.'_'; $d = 'edit';
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
			if(!isset($e)){ $e = 'new_'; $d = 'new'; }
			$feedProps = array('idFeedIn', 'label', 'description', 'idCompany'
				, 'dedupeEmail', 'dedupeLandline', 'dedupeCellphone', 'dedupeAcross', 'filterTypeUrl', 'filterTypeSiftLogic', 'notifications', 'retired', 'rejectOldLeadsMaxAge', 
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
			$companies = $leads->getCompanies();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='<?php
		?>closeContent(<?php
			?>"dialog_<?php echo $d; ?>feed<?php 
				if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
		?>);' >Close [X]</a>
</div>
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td><p>Feed Label</p></td>
		<td>
			<p>	
				<input type='hidden' name='<?php echo $e; ?>feed_idFeedIn'
					id='<?php echo $e; ?>feed_idFeedIn'
					value='<?php echo $feed_idFeedIn; ?>' 
				/>
				<input type='text' name='<?php echo $e; ?>feed_label'
					id='<?php echo $e; ?>feed_label'
					value='<?php echo $feed_label; ?>' 
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Description</p></td>
		<td>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_description'
					id='<?php echo $e; ?>feed_description'
					value='<?php echo $feed_description; ?>' 
				class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Company</p></td>
		<td>
			<p>
				<?php if($companies === false){ ?>
				Database failure - could not fetch company list
				<?php } elseif(!is_object($companies) && $companies == 0){ ?>
				There are no companies in the database. Please create a company before
				creating a feed.
				<?php } else { ?>
				<select name='<?php echo $e; ?>feed_idCompany' 
					id='<?php echo $e; ?>feed_idCompany'
				>
				<?php foreach($companies as $company){ ?>
					<option value='<?php echo $company->idCompany; ?>'
					<?php if($company->idCompany == $feed_idCompany){ 
					?>selected='selected'<?php } ?>
					><?php echo $company->name; ?></option>
				<?php } ?>
				</select>
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Required Fields</p></td>
		<td>
			<p>
				<?php foreach($recordFields as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedRequired)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>			
				<?php } ?>
				<?php foreach($incomingAdditionalRequirementSettings as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_required_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedRequired)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>				
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Allowed Fields</p></td>
		<td>
			<p>
				<?php foreach($recordFields as $f){ ?>
				<input type='checkbox' 
					name='<?php echo $e; ?>feed_allowedFields_<?php echo $f; ?>'
					id='<?php echo $e; ?>feed_allowedFields_<?php echo $f; ?>'
					value='<?php echo $f; ?>'
					<?php if(in_array($f, $selectedAllowedFields)){ ?>
					checked='checked'
					<?php } ?>
				/> <?php echo $f; ?>
				<?php } ?>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Duplicate Filters</p></td>
		<td>
			<p>
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeEmail'
					id='<?php echo $e; ?>feed_dedupeEmail'
					value='1'
					<?php if($feed_dedupeEmail){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Emails
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeLandline'
					id='<?php echo $e; ?>feed_dedupeLandline'
					value='1'
					<?php if($feed_dedupeLandline){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Landline Numbers
				<input type='checkbox'
					name='<?php echo $e; ?>feed_dedupeCellphone'
					id='<?php echo $e; ?>feed_dedupeCellphone'
					value='1'
					<?php if($feed_dedupeCellphone){ ?>
					checked='checked'
					<?php } ?>
				/> Reject Duplicate Cellphone Numbers
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Duplicate Options</p></td>
		<td>
			<p>
				<input type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_global'
					value='global'
					<?php if($feed_dedupeAcross == 'global'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe Across all feeds
				<input type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_url'
					value='url'
					<?php if( empty( $feed_dedupeAcross ) || $feed_dedupeAcross == 'url'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe across same URL
				<input  type='radio'
					name='<?php echo $e; ?>feed_dedupeAcross'
					id='<?php echo $e; ?>feed_dedupeAcross_listcode'
					value='listcode'
					<?php if($feed_dedupeAcross == 'listcode'){ ?>
					checked='checked'
					<?php } ?>
				/> Dedupe across same Listcode
			</p>
		</td>
	</tr>
		<tr>
				<td><p>URL Filter Options</p></td>
				<td>
						<p>
								Using the 'Accept' option, urls that are listed here are the only ones that will be accepted into
								the feed. Using the 'Reject' option, all urls will be accepted, except the ones listed here.
						</p>
						<p>
								<input type='radio'
										name='<?php echo $e; ?>feed_filterTypeUrl'
										id='<?php echo $e; ?>feed_filterTypeUrl_disabled'
										value='true'
										<?php if(
												empty($feed_filterTypeUrl)
										){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').hide(); <?php
										?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Do nothing with');"
								/> Disabled<br />
								<input type='radio'
										name='<?php echo $e; ?>feed_filterTypeUrl'
										id='<?php echo $e; ?>feed_filterTypeUrl_accept'
										value='true'
										<?php if($feed_filterTypeUrl == 'accept'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').show(); <?php
										?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Accept');"
								/> Accept<br />
								<input type='radio'
										name='<?php echo $e; ?>feed_filterTypeUrl'
										id='<?php echo $e; ?>feed_filterTypeUrl_reject'
										value='true'
										<?php if($feed_filterTypeUrl == 'reject'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeUrl').show(); <?php
										?>$('#<?php echo $e; ?>feed_filterUrl_descriptor').html('Reject');"
								/> Reject<br />
						</p>
						<div id='<?php echo $e; ?>feed_toggler_filterTypeUrl'
								style='display:<?php
										if(empty($feed_filterTypeUrl)){ echo "none"; }
										else { echo "block"; }
								?>;'
						>
								<p>The following urls:</p>
								<p>
										<a href='#' class='nonLink'
				onclick='element("<?php echo $e; ?>feed_filterUrl_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "Url" });'
										>Add New URL to <span id='<?php echo $e; ?>feed_filterUrl_descriptor'></span></a>
										| <a href='#' class='nonLink'
												onclick='element("<?php echo $e; ?>feed_filterUrl_multipleInsert"<?php
												?>, "element_multifilter"<?php
												?>, { "e": "<?php echo $e; ?>"<?php
												?>, "type": "Url" });'
										>Add Multiple</a>
								</p>
								<div id='<?php echo $e; ?>feed_filterUrl_multipleInsert'></div>
								<div id='<?php echo $e; ?>feed_filterUrl_container'>
								<?php foreach($feed_filterUrl as $filterUrl){ ?>
										<div>
												<input type='text'
														name='<?php echo $e; ?>feed_filterUrl[]'
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
										name='<?php echo $e; ?>feed_filterTypeSiftLogic'
										id='<?php echo $e; ?>feed_filterTypeSiftLogic_disabled'
										value='true'
										<?php if(
												empty($feed_filterTypeSiftLogic)
										){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeSiftLogic').hide();"
								/> Disabled<br />
								<input type='radio'
										name='<?php echo $e; ?>feed_filterTypeSiftLogic'
										id='<?php echo $e; ?>feed_filterTypeSiftLogic_accept'
										value='true'
										<?php if($feed_filterTypeSiftLogic == 'accept'){ ?>
										checked='checked'
										<?php } ?>
										onclick="$('#<?php echo $e; ?>feed_toggler_filterTypeSiftLogic').show();"
								/> Enabled<br />
						</p>
						<div id='<?php echo $e; ?>feed_toggler_filterTypeSiftLogic'
								style='display:<?php
										if(empty($feed_filterTypeSiftLogic)){ echo "none"; }
										else { echo "block"; }
								?>;'
						>
								<p>The following urls:</p>
								<p>
										<a href='#' class='nonLink'
				onclick='element("<?php echo $e; ?>feed_filterSiftLogic_container", "element_filter", { "e": "<?php echo $e; ?>", "type": "SiftLogic" });'
										>Add New URL to filter</a>
										| <a href='#' class='nonLink'
												onclick='element("<?php echo $e; ?>feed_filterSiftLogic_multipleInsert"<?php
												?>, "element_multifilter"<?php
												?>, { "e": "<?php echo $e; ?>"<?php
												?>, "type": "SiftLogic" });'
										>Add Multiple</a>
								</p>
								<div id='<?php echo $e; ?>feed_filterSiftLogic_multipleInsert'></div>
								<div id='<?php echo $e; ?>feed_filterSiftLogic_container'>
								<?php foreach($feed_filterSiftLogic as $filterSiftLogic){ ?>
										<div>
												<input type='text'
														name='<?php echo $e; ?>feed_filterSiftLogic[]'
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
		<td><p>Lead Rejections</p></td>
		<td>
			<p>How old are leads allowed to be before we reject them?  This should be a text string like "7 Days Ago" or "30 Days Ago".  Do not enter just a number.</p>
			<p>
				<input type='text' name='<?php echo $e; ?>feed_rejectOldLeadsMaxAge' id='<?php echo $e; ?>feed_rejectOldLeadsMaxAge' value='<?php echo $feed_rejectOldLeadsMaxAge; ?>' class='long' />
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Notifications</p></td>
		<td>
			<p>Should we send dormant URL notifications for URLs in this feed?</p>
			<p>
				<input type='radio' name='<?php echo $e; ?>feed_notifications' id='<?php echo $e; ?>feed_notifications_yes' value='1' <?php if( '1' == $feed_notifications ) { ?>checked='checked'<?php } ?>/> Enabled
				<input type='radio' name='<?php echo $e; ?>feed_notifications' id='<?php echo $e; ?>feed_notifications_no' value='0' <?php if( $feed_notifications != '1' ) { ?>checked='checked'<?php } ?>/> Disabled
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Feed Status</p></td>
		<td>
			<p>
				<input type='radio' name='<?php echo $e; ?>feed_retired' id='<?php echo $e; ?>feed_retired_no' value='0' <?php if( $feed_retired != '1' ) { ?>checked='checked'<?php } ?>/> Active
				<input type='radio' name='<?php echo $e; ?>feed_retired' id='<?php echo $e; ?>feed_retired_yes' value='1' <?php if( '1' == $feed_retired ) { ?>checked='checked'<?php } ?>/> Retired
			</p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
		<?php if(isset($idFeedIn) && $e == 'edit_'.$idFeedIn.'_'){ ?>
				<input type='button' value='Cancel Changes'  
					onclick='<?php
						?>closeContent(<?php
							?>"dialog_<?php echo $d; ?>feed<?php 
								if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
						?>);'
				/> 
				<input type='button' value='Save Changes' 
					onclick='manageFeed("update", <?php echo $idFeedIn; ?>);'
				/>
		<?php } else { ?>
				<input type='button' value='Cancel'  
					onclick='<?php
						?>closeContent(<?php
							?>"dialog_<?php echo $d; ?>feed<?php 
								if($d == 'edit'){ ?>_<?php echo $idFeedIn; ?><?php } ?>"<?php
						?>);'
				/> 
				<input type='button' value='Add New Feed' 
					onclick='manageFeed("new");'
				/>
		<?php } ?>
			</p>
		</td>
	</tr>
</table>
<?php
		break;
		case 'dialog_import':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_import", {"sub": <?php echo $idFeedIn; ?>});'>Close [X]</a>
</div>
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
<form enctype="multipart/form-data" action="mgr_import.php" method="post" target="_blank">
<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_UPLOAD_SIZE; ?>" />
<input type="hidden" name="idFeedIn" value="<?php echo intval($idFeedIn); ?>" />

<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td colspan='2'><p class='aCenter'>Import Settings</p></td>
	</tr>
	<tr>
		<td><p>URL</p></td>
		<td><input type="text" name="url" value="" /></td>
	</tr>
	<tr>
		<td><p>Listcode</p></td>
		<td><input type="text" name="listcode" value="" /></td>
	</tr>
	<tr>
		<td><p>File</p></td>
		<td><p>Please select the file to upload from your computer.  File must be in CSV format.  Limit <?php echo (MAX_UPLOAD_SIZE / 1024000);?>MB.</p><p><input type="file" name="import_file" multiple="false" accept="text/csv" /></p></td>
	</tr>
	<tr>
		<td><p>Field mapping</p></td>
		<td>
<?php
		$allowedFields = explode(";", $feed->allowedFields);
		$requiredFields = explode(";", $feed->required);

		// Add a separate time field in case the file uses separate columns
		if( ( $key = array_search( 'stamp', $allowedFields ) ) !== FALSE ) {
			array_splice( $allowedFields, $key+1, 0, 'time' );			
		}

		foreach( $allowedFields as $field) {
			if( 'listcode' != $field && 'url' != $field) {
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
		}

?>
		</td>
	</tr>
	<tr>
		<td colspan='2'><p class='aRight'><input type="submit" name="a" value="Upload" /></p></td>
	</tr>
</table>
</form>
<?php
			}
		break;
		case 'dialog_export':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_export", {"sub": <?php echo $idFeedIn; ?>});' 
	>Close [X]</a>
</div>
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
<input type='hidden' id='export_idFeedIn' value='<?php echo $feed->idFeedIn; ?>' />
<table class='feedTable' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td colspan='2'><p class='aCenter'>Export Settings</p></td>
	</tr>
	<tr>
		<td>
			<p>Columns</p>
		</td>
		<td>
			<?php foreach($recordFields as $f){ ?>
			<input type='checkbox' 
				name='export_<?php echo $idFeedIn; ?>_column_<?php echo $f; ?>'
				id='export_<?php echo $idFeedIn; ?>_column_<?php echo $f; ?>'
				value='<?php echo $f; ?>'
			/> <?php echo $f; ?>
			<?php } ?>
		</td>
	</tr>
	<tr>
		<td>
			<p>Period</p>
		</td>
		<td>
			<p>
				Period goes from midnight of the first date to midnight of the second date. Leave blank to select
				from all time records. (This could take a long time.)
			</p>
			<p>
				<input type='text' 
					name='export_<?php echo $idFeedIn; ?>_dateStart' 
					id='export_<?php echo $idFeedIn; ?>_dateStart' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d"); ?>'
				/>
				to <input type='text' 
					name='export_<?php echo $idFeedIn; ?>_dateEnd' 
					id='export_<?php echo $idFeedIn; ?>_dateEnd' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				URLs
			</p>
		</td>
		<td>
			<p>
				URLs to limit the selection by. Leave blank to select all records regardless of URL.
			</p>
			<p>
				<a href='#' class='nonLink' 
	onclick='element("export_<?php echo $idFeedIn; ?>_urls", "urlField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'
				>Add URL</a>
			</p>
			<div>
				<div id='export_<?php echo $idFeedIn; ?>_urls' >
				</div>
			</div>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Email domains
			</p>
		</td>
		<td>
			<p>
				Email domains to limit the selection by. Leave blank to select all records regardless of email address.  Do not include the @ symbol.
			</p>
			<p>
				<a href='#' class='nonLink' onclick='element("export_<?php echo $idFeedIn; ?>_emails", "emailField", {"idFeedIn": <?php echo $idFeedIn; ?>} );'>Add email domain</a>
			</p>
			<div>
				<div id='export_<?php echo $idFeedIn; ?>_emails' >
				</div>
			</div>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>Limit</p>
		</td>
		<td>
			<p>
				Set a limit on the number of records that are returned.  Leave blank to return ALL records.
			</p>
			<p>
				<input type="text" name="export_<?php echo $idFeedIn; ?>_limit" id="export_<?php echo $idFeedIn; ?>_limit" value="" />
			</p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
				<a href='#' id='resultExport_<?php echo $idFeedIn; ?>'></a>
				<input type='button' value='Export Data' onclick='exportFile(<?php echo $idFeedIn; ?>);'/>
				<br />
				<span id='resultQuery_<?php echo $idFeedIn; ?>'></span>
			</p>
		</td>
	</tr>
</table>
<?php
			}
		break;
		case 'dialog_urlreport':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
			$feed = $leads->getInboundFeed( $idFeedIn );
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("dialog_urlreport", {"sub": <?php echo $idFeedIn; ?>}); closeContent("dialog_urlreportdetails", {"sub": <?php echo $idFeedIn; ?>});' 
	>Close [X]</a>
</div>
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
<input type='hidden' id='urlreport_idFeedIn' value='<?php echo $feed->idFeedIn; ?>' />
<table class='feedTable table-striped' border='1' cellpadding='0' cellspacing='0'>
	<tr>
		<td colspan='2'><p class='aCenter'>Report Settings</p></td>
	</tr>
	<tr>
		<td>
			<p>Period</p>
		</td>
		<td>
			<p>
				Period goes from midnight of the first date to midnight of the second date. Leave blank to select
				from all time records. (This could take a long time.)
			</p>
			<p>
				<input type='text' 
					name='urlreport_<?php echo $idFeedIn; ?>_dateStart' 
					id='urlreport_<?php echo $idFeedIn; ?>_dateStart' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d"); ?>'
				/>
				to <input type='text' 
					name='urlreport_<?php echo $idFeedIn; ?>_dateEnd' 
					id='urlreport_<?php echo $idFeedIn; ?>_dateEnd' 
					class='dateSelector' 
					value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				URLs
			</p>
		</td>
		<td>
			<p>
				URLs to limit the selection by. Leave blank to select all records regardless of URL.
			</p>
			<p>
<?php
				$urls = $leads->getInboundURLDates( $idFeedIn );
				if( $urls && is_array( $urls ) ) {
					printf( "<select multiple=\"multiple\" id=\"urlreport_%s_urls\" size=\"%d\">\n", $idFeedIn, sizeOf( $urls ) );
					foreach( $urls as $url ) {
						printf( "<option value=\"%s\">%s (%s)</option>\n", htmlspecialchars( $url['url'] ), htmlspecialchars( $url['url'] ), $url['date'] );
					}
					print "</select>\n";
				}
?>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Count By
			</p>
		</td>
		<td>
			<p><select id="urlreport_<?php echo $idFeedIn; ?>_breakdown"><option value="day" selected="selected">Day</option><option value="month">Month</option><option value="year">Year</option><option value="total">Total</option</select></p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				Sort By
			</p>
		</td>
		<td>
			<p><select id="urlreport_<?php echo $idFeedIn; ?>_sort"><option value="date" selected="selected">Date</option><option value="url">URL</option><option value="count">Count</option></select></p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
				<input type="button" value="Run Report" onclick="display( 'dialog_urlreportdetails', { 'sub': <?php echo $idFeedIn; ?>, 
					'idFeedIn': <?php echo $idFeedIn; ?>, 
					'dateStart': $('#urlreport_<?php echo $idFeedIn; ?>_dateStart').val(),
					'dateEnd': $('#urlreport_<?php echo $idFeedIn; ?>_dateEnd').val(),
					'urlList': $('#urlreport_<?php echo $idFeedIn; ?>_urls').val(),
					'sort': $('#urlreport_<?php echo $idFeedIn; ?>_sort').val(),
					'breakdown': $('#urlreport_<?php echo $idFeedIn; ?>_breakdown').val() });" />
			</p>
		</td>
	</tr>
</table>
<?php
			}
		break;
		case 'dialog_urlreportdetails':
			$feed = $leads->getInboundFeed( $_REQUEST['options']['idFeedIn'] );
			if($feed === false){ 
?>
<p>Database failure - could not fetch feed information.</p>
<?php 

			} else if( !is_object($feed) && $feed == 0 ) { 
?>
<p>Error - could not fetch feed. Feed does not exist.</p>
<?php 
			} else {

				$stats = $leads->getInboundURLStatsReport( $_REQUEST['options']['idFeedIn'], $_REQUEST['options']['urlList'], $_REQUEST['options']['breakdown'], $_REQUEST['options']['dateStart'], $_REQUEST['options']['dateEnd'], $_REQUEST['options']['sort'] );

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
						print "<table class='urlTable'>\n";
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
						printf( '<p><a href="%s">Download this report</a></p>', $fileLink );
					}
				}
			}
		break;
		case 'urlField':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
?>
<div>
	URL: <input type='text' 
		name='export_<?php echo $idFeedIn; ?>_urlList[]'
		value=''
	/> 
	<a href='#' class='nonLink' onclick='$(this).parent().remove();' >[X]</a>
</div>
<?php
		break;
		case 'emailField':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
?>
<div>
	Email domain: <input type='text' 
		name='export_<?php echo $idFeedIn; ?>_emailList[]'
		value=''
	/> (do not include @ symbol)
	<a href='#' class='nonLink' onclick='$(this).parent().remove();' >[X]</a>
</div>
<?php
		break;
		case 'dialog_listcodes':
			$idFeedIn = $_REQUEST['options']['idFeedIn'];
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
				name='<?php echo $e; ?>feed_filter<?php echo $t; ?>[]'
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
<textarea name='<?php echo $e; ?>feed_filter<?php echo $t; ?>Multi'
id='<?php echo $e; ?>feed_filter<?php echo $t; ?>Multi' ></textarea>
<input type='button' value='Add Multiple Urls'
		onclick="splitMultiFilter('<?php echo $e; ?>', '<?php echo $t; ?>');"
/>
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
<script type="text/javascript">
function splitMultiFilter(e, t){
	values = $('#'+e+'feed_filter'+t+'Multi').val();
	//alert(values);
	valueArray = values.match(/[^\r\n]+/g);
	for(count = 0; count < valueArray.length; count++){
		element(
			e+"feed_filter"+t+"_container"
			, "element_filter"
			, {
				"e": e
				, "type": t
				, "value": valueArray[count]
			}
		);
	}
	//alert('#'+e+'feed_filter'+t+'_multipleInsert');
	$('#'+e+'feed_filter'+t+'_multipleInsert').html("");
}

function manageFeed(action, idFeedIn){ 
	if(action == "new"){ e = "#new_feed_"; c = 'new'; } else { e = "#edit_"+idFeedIn+"_feed_"; c = 'edit'; }
	idFeedIn = $(e+'idFeedIn').val();
	label = $(e+'label').val();
	description = $(e+'description').val();
	rejectOldLeadsMaxAge = $(e+'rejectOldLeadsMaxAge').val();
	idCompany = $(e+'idCompany').val();
	required = '';
	allowedFields = '';
	recordFields = new Array( <?php 
$comma = false; 
foreach($recordFields as $f){ 
	if($comma) echo ','; 
	echo "'".$f."'";
	$comma = true; 
} 
foreach($incomingAdditionalRequirementSettings as $f){ 
	if($comma) echo ','; 
	echo "'".$f."'";
	$comma = true; 
}
	?> );
	requiredFlag = false; allowedFieldsFlag = false;
	for(count = 0; count < recordFields.length; count++){ 
		if($(e+'required_'+recordFields[count]).is(":checked")){
			if(requiredFlag) { required += ';'; }
			required += recordFields[count];
			requiredFlag = true;
		}
		if($(e+'allowedFields_'+recordFields[count]).is(":checked")){
			if(allowedFieldsFlag) { allowedFields += ';'; }
			allowedFields += recordFields[count];
			allowedFieldsFlag = true;
		}
	}
	if($(e+'filterTypeUrl_disabled').is(":checked")){
		filterTypeUrl = null;
	}else if($(e+'filterTypeUrl_accept').is(":checked")){
		filterTypeUrl = 'accept';
	}else if($(e+'filterTypeUrl_reject').is(":checked")){
		filterTypeUrl = 'reject';
	}
	filterUrl = $("input[name='"+c+"_"+idFeedIn+"_feed_filterUrl\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");

	if($(e+'filterTypeSiftLogic_disabled').is(":checked")){
		filterTypeSiftLogic = null;
	}else if($(e+'filterTypeSiftLogic_accept').is(":checked")){
		filterTypeSiftLogic = 'accept';
	}else if($(e+'filterTypeSiftLogic_reject').is(":checked")){
		filterTypeSiftLogic = 'reject';
	}
	filterSiftLogic = $("input[name='"+c+"_"+idFeedIn+"_feed_filterSiftLogic\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");

	if($(e+'dedupeEmail').is(":checked")){ dedupeEmail = 1;	} else { dedupeEmail = 0; }
	if($(e+'dedupeLandline').is(":checked")){ dedupeLandline = 1;	} else { dedupeLandline = 0; }
	if($(e+'dedupeCellphone').is(":checked")){ dedupeCellphone = 1;	} else { dedupeCellphone = 0; }
	if($(e+'retired_yes').is(":checked")){ retired = 1; } else { retired = 0; }
	if($(e+'notifications_yes').is(":checked")){ notifications = 1; } else { notifications = 0; }
	if(c == 'new'){ 
		dedupeAcross = $('input[name="'+c+'_feed_dedupeAcross"]:checked').val();
	} else { 
		dedupeAcross = $('input[name="'+c+'_'+idFeedIn+'_feed_dedupeAcross"]:checked').val();
	}

	var rejectFilter = /^\d+ Days Ago$/i;
	if( !rejectFilter.test( rejectOldLeadsMaxAge ) ) {
		alert( 'Invalid Lead Rejection Age' );
		return false;
	}
	/* alert(
		"label: "+label
		+"\n"+"description: "+description
		+"\n"+"idCompany: "+idCompany
		+"\n"+"required: "+required
		+"\n"+"allowedFields: "+allowedFields
		+"\n"+"dedupeAcross: "+dedupeAcross
		+"\n"+"filterUrl: "+filterUrl
		+"\n"+"filterSiftLogic: "+filterSiftLogic
	);  
	return false; */
	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "manageFeed"
			, "action" : action
			, "idFeedIn": idFeedIn
			, "label": label
			, "description": description
			, "idCompany": idCompany
			, "required": required
			, "allowedFields": allowedFields
			, "dedupeEmail": dedupeEmail
			, "dedupeLandline": dedupeLandline
			, "dedupeCellphone": dedupeCellphone
			, "dedupeAcross": dedupeAcross
			, "filterTypeUrl": filterTypeUrl
			, "filterUrl": filterUrl
			, "filterTypeSiftLogic": filterTypeSiftLogic
			, "filterSiftLogic": filterSiftLogic
			, "retired": retired
			, "notifications": notifications
			, "rejectOldLeadsMaxAge": rejectOldLeadsMaxAge
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { 
			alert("JSON Failed: "+responseText); 
			if(c == 'new'){ 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);				
			} else { 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'sub' : idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);
			}
			return false; 
		}
		if(result.status == 1){ 
			if(c == 'new'){ 
				alert("Successfully added new feed.");
				closeContent('dialog_'+c+'feed');
			} else { 
				alert("Feed settings updated.");
				closeContent('dialog_'+c+'feed', {'sub' : idFeedIn} );
			}
			display('incomingFeeds', {'callbackParams': {'sub': idCompany} }, true, function(callbackParams){ 
				toggleHidden(
					'companyFeedList'
					, {
						'sub': callbackParams.sub
						, 'hiddenText':'Show Feeds'
						, 'shownText':'Close' 
					}
				);
			});
		} else { 
			alert(result.error);
			if(c == 'new'){ 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);				
			} else { 
				display('dialog_'+c+'feed'
					, { 
						'idFeedIn': idFeedIn
						, 'sub' : idFeedIn
						, 'label': label
						, 'description': description
						, 'idCompany' : idCompany
						, 'required' : required
						, 'allowedFields' : allowedFields
						, "dedupeEmail": dedupeEmail
						, "dedupeLandline": dedupeLandline
						, "dedupeCellphone": dedupeCellphone
						, "dedupeAcross": dedupeAcross
					} 
				);
			}
		}
	});
}
function exportFile(idFeedIn){ 
	recordFields = new Array( <?php $comma = false; 
	foreach($recordFields as $f){ if($comma) echo ','; 
	?>'<?php echo $f; ?>'<?php $comma = true; } ?> );
	comma = false; 
	exportColumns = '';
	for(count = 0; count < recordFields.length; count++){ 
		if($('#export_'+idFeedIn+'_column_'+recordFields[count]).is(":checked")){
			if(comma) { exportColumns += ';'; }
			exportColumns += recordFields[count];
			comma = true;
		}
	}
	exportDateStart = $('#export_'+idFeedIn+'_dateStart').val();
	exportDateEnd = $('#export_'+idFeedIn+'_dateEnd').val();
	exportUrlList = $("input[name='export_"+idFeedIn+"_urlList\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");
	exportEmailList = $("input[name='export_"+idFeedIn+"_emailList\\[\\]']")
		.map(function(){return $(this).val().trim();}).get().join(";");
	exportLimit = $('#export_'+idFeedIn+'_limit').val();
	alert(
		"idFeedIn: "+idFeedIn
		+"\n"+"exportColumns: "+exportColumns
		+"\n"+"exportDateStart: "+exportDateStart
		+"\n"+"exportDateEnd: "+exportDateEnd
		+"\n"+"exportUrlList: "+exportUrlList
		+"\n"+"exportEmailList: "+exportEmailList
		+"\n"+"exportLimit: "+exportLimit
	);  
	var response = $.ajax({
		url: "mgr_feedinc.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "exportData"
			, "idFeedIn": idFeedIn
			, "exportColumns": exportColumns
			, "exportDateStart": exportDateStart
			, "exportDateEnd": exportDateEnd
			, "exportUrlList": exportUrlList
			, "exportEmailList": exportEmailList
			, "exportLimit": exportLimit
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { 
			alert("JSON Failed: "+responseText); 
			return false; 
		}
		if(result.status == 1){ 
			$('#resultExport_'+idFeedIn).html('Download File');
			//$('#resultQuery_'+idFeedIn).html(result.query);
			$('#resultExport_'+idFeedIn).attr('href', result.link);
		} else { 
			alert(result.error);
		}
	});
	$('#resultExport_'+idFeedIn).html("Processing...");
}
$(document).ready(function(){ 
	display('incomingFeeds');
});
</script>
<style type="text/css">
table.urlTable th, table.urlTable td { padding: 3px; }
table.feedTable { margin-bottom: 20px; }
table.feedTable th, table.feedTable td { padding: 3px; }
</style>
<body>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls'>
			<a href='#' class='nonLink' onclick="display('dialog_newfeed');" 
			>Add New Feed</a>
		</div>
		<div id='dialogs'>
			<div id='dialog_newfeed' style='display:none;'></div>
			<div id='dialog_editfeed' style='display:none;'></div>
		</div>
		<div class='fl' style='width: 100%;'>
			<div id='incomingFeeds'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>
</body>
</html>
