<?php 
//ADMIN_ROOT/mgr_feedout.php
//Version 1.0
//ES20130726 Version 1.0: Outgoing Feed Manager created.
session_start();
$mysqlErrorSource = 'Manager - Suppression';
include("../c_config.php");
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.

function getSuppressionCount($idCompany){
	$getCount = "SELECT COUNT(*) FROM `".DATABASE_NAME."`.`suppression_".$idCompany."`;";
	dbCon();
	$dogetCount = dbQry($getCount, 'Fetching count of suppressed records.', true);
	dbDcon();
	if($dogetCount === false){ return false; }
	$queryObject = $dogetCount->fetch_assoc();
	$count = $queryObject['COUNT(*)'];
	return $count;
}

function addToSuppressionList($idCompany, $email){
	dbCon();
	$insert = "INSERT INTO `".DATABASE_NAME."`.`suppression_".$idCompany."` "
		."(`email`) VALUES ('".$GLOBALS['dbconnx']->escape_string($email)."');";
	$doinsert = dbQry($insert, 'Inserting email into suppression', true, true, true); //Verbose result turned on.
	dbDcon();
	if($doinsert['result'] === false){
		return array(
			'result' => false
			, 'error' => $doinsert['error']
		);
	} else {
		return array(
			'result' => true
			, 'error' => 'Successfully added email to suppression list.'
		);
	}
}

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case 'processSuppression':
			$c = true; $result['error'] = 'Failed when processing new suppression.';
			if($c && (
				$_REQUEST['list'] == 'multiple'
				&& count($_REQUEST['lists']) == 0
			)){
				$c = false; $result['error'] = 'If multiple separate lists is selected, you must select at '
				.'least one list.';
			}
			if($c){ 
				$counts = array(
					'success' => 0
					, 'invalid' => 0
					, 'failures' => 0
					, 'dupe' => 0
				);
				include(LIVE_ROOT."_f_validEmail.php");
				switch($_REQUEST['type']){
					case 'single':
						if($c && ( //email must not be empty.
							!isset($_REQUEST['email'])
							|| $_REQUEST['email'] == ''
						)){
							$c = false; $result['error'] = 'Email field cannot be empty.';
						}
						if($c){ //Passed initial validation
							if(!valid_email($_REQUEST['email'])){
								$counts['invalid']++;
							} else {
								if($_REQUEST['list'] == 'multiple'){
									foreach($_REQUEST['lists'] as $list){
										$result = addToSuppressionList($list, $_REQUEST['email']);
										if(!$result['result']){
											if(strpos($result['error'], "Duplicate") === false){ //Not a duplicate
												$counts['failures']++;
											} else { 
												$counts['dupe']++;
											}
										} else { 
											$counts['success']++;
										}
									}
								} else { 
									$result = addToSuppressionList($_REQUEST['list'], $_REQUEST['email']);
									if(!$result['result']){
										if(strpos($result['error'], "Duplicate") === false){ //Not a duplicate
											$counts['failures']++;
										} else { 
											$counts['dupe']++;
										}
									} else { 
										$counts['success']++;
									}
								}
							}
						}
					break;
					case 'multiple':
						if($c && ( //email array must not be empty.
							count($_REQUEST['emails']) == 0
						)){
							$c = false; $result['error'] = 'Emails field cannot be empty.';
						}
						if($c){ //Passed initial validation
							foreach($_REQUEST['emails'] as $email){
								if(!valid_email($email)){
									$counts['invalid']++;
								} else {
									if($_REQUEST['list'] == 'multiple'){
										foreach($_REQUEST['lists'] as $list){
											$result = addToSuppressionList($list, $email);
											if(!$result['result']){
												if(strpos($result['error'], "Duplicate") === false){ //Not a duplicate
													$counts['failures']++;
												} else { 
													$counts['dupe']++;
												}
											} else { 
												$counts['success']++;
											}
										}
									} else { 
										$result = addToSuppressionList($_REQUEST['list'], $email);
										if(!$result['result']){
											if(strpos($result['error'], "Duplicate") === false){ //Not a duplicate
												$counts['failures']++;
											} else { 
												$counts['dupe']++;
											}
										} else { 
											$counts['success']++;
										}
									}
								}
							}
						}
					break;
				}
			}
			if($c){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully added new suppressions.';
				$result['counts'] = $counts;
			}
		break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	include("d_shared.php");
	switch($_REQUEST['d']){
		case 'suppressionCounts':
			$lists = array('global');
			$companies = getCompanies();
			if($companies === false){ 
			} elseif($companies == 0){
			} else { 
				foreach($companies as $company){ 
					$companyCache[$company->idCompany] = $company;
					$lists[] = $company->idCompany;
				}
			}
?>
<table>
	<thead>
		<tr>
			<td><p>Suppression List</p></td>
			<td><p>Record Count</p></td>
			<td><p>Options</p></td>
		</tr>
	</thead>
	<tbody>
<?php
			foreach($lists as $suppressionList){ 
				if($suppressionList == 'global'){ $display_listName = 'Global'; }
				else { $display_listName = $companyCache[$suppressionList]->name; }
				$suppressionCount = getSuppressionCount($suppressionList);
				if($suppressionCount === false){ $display_suppressionCount = 'Error'; }
				else{ $display_suppressionCount = $suppressionCount; }
?>
		<tr>
			<td><p><?php echo $display_listName; ?></p></td>
			<td><p class='aRight'><?php echo $display_suppressionCount; ?></p></td>
			<td>
				<p>
					Export
				</p>
			</td>
		</tr>
<?php
			}
?>
	</tbody>
</table>
<?php
		break;
		case 'dialog_import':
			$companies = getCompanies();
			switch($_REQUEST['options']['type']){
				case "single":
				
					if($companies === false){ 
?><p>Database error: could not fetch companies.</p><?php
					} else {
?>
<p>Add Single Email to Suppression</p>
<p>
	Email: <input type='text' id='suppress_emailS' /> 
	to Suppression List 
	<select id='suppress_list' onchange='checkIfMulti();'>
		<option value='global'>Global Suppression</option>
		<option value='multiple'>Multiple Separate Lists</option>
<?php
						foreach($companies as $company){ 
?>
		<option value='<?php echo $company->idCompany; ?>'><?php echo $company->name; ?> Suppression</option>
<?php
						}
?>
	</select>
	<input type='button' value='Add' onclick="processSuppression('single');" />
</p>
<div id='dialog_multiselect' class='hidden'>
	<p>Select suppression lists to add this email to.</p>
<?php
						foreach($companies as $company){ 
?>
	<div class='fl'>
		<input type='checkbox' value='<?php echo $company->idCompany; ?>' name='suppress_multiselect' 
			id='suppress_multiselect_<?php echo $company->idCompany; ?>'
		> <?php echo $company->name; ?>
	</div>
<?php
						}
?>
	<div class='clr'></div>
</div>
<?php
					}
			
				break;
				case 'multiple':
				
					if($companies === false){ 
?><p>Database error: could not fetch companies.</p><?php
					} else {
?>
<p>Add Multiple Emails to Suppression</p>
<p>Add each email on its own line.</p>
<p>
	Emails: <textarea id='suppress_emailM' ></textarea> 
	to Suppression List 
	<select id='suppress_list' onchange='checkIfMulti();'>
		<option value='global'>Global Suppression</option>
		<option value='multiple'>Multiple Separate Lists</option>
<?php
						foreach($companies as $company){ 
?>
		<option value='<?php echo $company->idCompany; ?>'><?php echo $company->name; ?> Suppression</option>
<?php
						}
?>
	</select>
	<input type='button' value='Add' onclick="processSuppression('multiple');" />
</p>
<div id='dialog_multiselect' class='hidden'>
	<p>Select suppression lists to add this email to.</p>
<?php
						foreach($companies as $company){ 
?>
	<div class='fl'>
		<input type='checkbox' value='<?php echo $company->idCompany; ?>' name='suppress_multiselect' 
			id='suppress_multiselect_<?php echo $company->idCompany; ?>'
		> <?php echo $company->name; ?>
	</div>
<?php
						}
?>
	<div class='clr'></div>
</div>
<?php
					}
		
				break;
				case 'file':
					echo "<p>Feature not yet available. Coming soon.</p>";
					exit;
				
					if($companies === false){ 
?><p>Database error: could not fetch companies.</p><?php
					} else {
?>
<p>Add Multiple Emails to Suppression</p>
<p>
	File: <input type='file' id='suppress_file' multiple='false' accept='text/csv' 
		onchange='handleFile(this.files);' 
	/>
	to Suppression List 
	<select id='suppress_list' onchange='checkIfMulti();'>
		<option value='global'>Global Suppression</option>
		<option value='multiple'>Multiple Separate Lists</option>
<?php
						foreach($companies as $company){ 
?>
		<option value='<?php echo $company->idCompany; ?>'><?php echo $company->name; ?> Suppression</option>
<?php
						}
?>
	</select>
	<input type='button' value='Add' onclick="processSuppression('multiple');" />
</p>
<div id='dialog_multiselect' class='hidden'>
	<p>Select suppression lists to add this email to.</p>
<?php
						foreach($companies as $company){ 
?>
	<div class='fl'>
		<input type='checkbox' value='<?php echo $company->idCompany; ?>' name='suppress_multiselect' 
			id='suppress_multiselect_<?php echo $company->idCompany; ?>'
		> <?php echo $company->name; ?>
	</div>
<?php
						}
?>
	<div class='clr'></div>
</div>
<?php
					}
		
				break;
			}
		break;
	}
	exit;
}

$title = 'Suppressions Manager';
include("c_header.php");
?>
<script>
function checkIfMulti(){
	suppress_list = $('#suppress_list').val();
	if(suppress_list == 'multiple'){
		$('#dialog_multiselect').show();
	} else { 
		$('#dialog_multiselect').hide();
	}
}

function processSuppression(type){
	switch(type){
		case 'single':
			email = $('#suppress_emailS').val();
			if(email == ''){ 
				alert("Email field must not be empty."); return false;
			}
			list = $('#suppress_list').val();
			if(list == 'multiple'){
				lists = new Array();
				checkedLists = $("input[name='suppress_multiselect']:checked");
				checkedLists.each(function(){
					lists.push($(this).val());
				});
				if(lists.length == 0){ 
					alert("If you want to assign this to multiple suppression lists, you must select at least one.");
					return false;
				}
			} else { 
				lists = list;
			}  
			var response = $.ajax({
				url: "mgr_suppress.php",
				type: "POST",
				async: true,
				data: ({
					"a" : "processSuppression"
					, "type": type
					, "email": email
					, "list": list
					, "lists": lists
				})
			}).done(function(responseText){ 
				var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
				if(result===null) { 
					alert("JSON Failed: "+responseText); 
					return false; 
				}
				if(result.status == 1){ 
					alert(
						result.error+"\n"
						+"Successes: "+result.counts.success+"\n"
						+"Invalid Emails: "+result.counts.invalid+"\n"
						+"Duplicates: "+result.counts.dupe+"\n"
						+"Failures: "+result.counts.failures+"\n"
					);
					closeContent('dialog_import');
					display('suppressionCounts');
				} else { 
					alert(result.error);
				}
			});
		break;
		case 'multiple':
			emaillist = $('#suppress_emailM').val();
			if(emaillist == ''){ 
				alert("Email list must not be empty."); return false;
			} else { 				
				emails = emaillist.match(/[^\r\n]+/g);
			}
			list = $('#suppress_list').val();
			if(list == 'multiple'){
				lists = new Array();
				checkedLists = $("input[name='suppress_multiselect']:checked");
				checkedLists.each(function(){
					lists.push($(this).val());
				});
				if(lists.length == 0){ 
					alert("If you want to assign this to multiple suppression lists, you must select at least one.");
					return false;
				}
			} else { 
				lists = list;
			} 
			var response = $.ajax({
				url: "mgr_suppress.php",
				type: "POST",
				async: true,
				data: ({
					"a" : "processSuppression"
					, "type": type
					, "emails": emails
					, "list": list
					, "lists": lists
				})
			}).done(function(responseText){ 
				var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
				if(result===null) { 
					alert("JSON Failed: "+responseText); 
					return false; 
				}
				if(result.status == 1){ 
					alert(
						result.error+"\n"
						+"Successes: "+result.counts.success+"\n"
						+"Invalid Emails: "+result.counts.invalid+"\n"
						+"Duplicates: "+result.counts.dupe+"\n"
						+"Failures: "+result.counts.failures+"\n"
					);
					closeContent('dialog_import');
					display('suppressionCounts');
				} else { 
					alert(result.error);
				}
			}); 
		break;
	}
}

$(document).ready(function(){
	display('suppressionCounts');
});
</script>
<body>
<div class='mainContainer'>
	<?php include('c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls' class='fl50'>
			<p>Suppression Manager</p>
			<p>
				<a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'single' });">Add Single Email</a>
				| <a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'multiple' });">Add Multiple Emails</a>
				| <a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'file' });">Add File</a>
			</p>
			<div id='dialog_import'></div>
		</div>
		<div id='suppressionCounts' class='fl50'></div>
		<div class='clr'></div>
	</div>
</div>

</body>
</html>