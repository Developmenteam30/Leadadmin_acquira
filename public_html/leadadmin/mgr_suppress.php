<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

$mysqlErrorSource = 'Manager - Suppression';
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");
include(INCLUDES."_f_validEmail.php");

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

function getSuppressions($idCompany){
    dbCon();
    $query = "SELECT email ";
    $query.= "FROM `" . DATABASE_NAME . "`.`suppression_" . $idCompany . "`";

    $result = dbQry( $query, 'Getting suppressions', true );
    dbDcon();

    if( $result === false ) { return false; }
    if( $result->num_rows == 0 ) { return 0; }
    $values = array();
    while( $row = $result->fetch_object() ){
        $values[] = $row;
    }
    return $values;
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

		case 'Add':
			$c = true;
			$result['error'] = 'Failed when trying to import file.';

			if( empty( $_REQUEST['list'] ) ) {
				$c = false;
				$result['error'] = 'No list selected!';
			}

			$lists = array();

			if( $c ) {
				if( 'multiple' == $_REQUEST['list'] ) {
					foreach( $_REQUEST as $key => $val ) {
						if( strpos( $key,'suppress_multiselect_' ) !== FALSE && isset( $val ) ) {
							$lists[] = intval( $val );
						}
					}
				} else if( 'global' == $_REQUEST['list'] ) {
					$lists[] = 'global';
				} else {
					$lists[] = intval( $_REQUEST['list'] );
				}
			}

			if( $c && sizeOf( $lists ) == 0 ) {
				$c = false;
				$result['error'] = 'No list selected!';
			}

			if( $c && empty( $_FILES['suppress_file']['tmp_name'] ) ) {
				$c = false;
				$result['error'] = 'No file uploaded!';
			}

			if( $c && !is_uploaded_file( $_FILES['suppress_file']['tmp_name'] ) ) {
				$c = false;
				$result['error'] = 'Possible file upload attack!';
			}

			if( $c && $_FILES['suppress_file']['size'] > 2097152 ) {
				$c = false;
				$result['error'] = 'File size cannot exceed 2MB.';
			}

			$counts = array(
				'success' => 0,
				'invalid' => 0,
				'failures' => 0,
				'dupe' => 0,
			);

			if( $c ) {
				$handle = @fopen( $_FILES['suppress_file']['tmp_name'], "r" );
				if ( !$handle ) {
					$c = false;
					$result['error'] = 'Cannot open file for reading';
				}
				if( $c ) {
    				while ( ( $buffer = fgets($handle, 4096) ) !== false ) {

						$buffer = trim ( $buffer );

						if( strpos( $buffer, '@' ) !== FALSE && !filter_var( $buffer, FILTER_VALIDATE_EMAIL ) ) {
							$counts['invalid']++;
						} else {
							foreach($lists as $list){
								$addResult = addToSuppressionList( $list, $buffer );
								if(!$addResult['result']){
									if(strpos($addResult['error'], "Duplicate") === false){ //Not a duplicate
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
			    	if ( !feof( $handle ) ) {
			        	$c = false;
						$result['error'] = 'Error: unexpected fgets() fail';
			    	}
			    	fclose( $handle );
				}
			}

			if( $c ){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully added new suppressions.';
				$result['counts'] = $counts;
			}

		break;

        case 'exportData':
            $c = true; $result['error'] = 'Failed when trying to export data.';

			if( empty( $_REQUEST['idCompany'] ) ) {
				$idCompany = 'global';
			} else {
				$idCompany = intval ( $_REQUEST['idCompany'] );
			}

            if($c){
                $records = getSuppressions( $idCompany );
                if($records === false){
                    $c = false; $result['error'] = 'Database failure - could not fetch suppression information.';
                }
                if($c && $records == 0){
                    $c = false; $result['error'] = 'Error - no suppression records exist.';
                }
            }
            if($c){
              $fileLink = 'exports/suppression_'.$idCompany."_".time().".csv";
              $filePath = ADMIN_ROOT.$fileLink;
              $file = fopen($filePath, "w");
              if(!file_exists($filePath)){
                $c = false; $result['reason'] = 'Failed to create CSV file.';
              }
            }
            if($c){
              foreach( $records as $record ) {
				fwrite( $file, $record->email . "\n" );
              }
              fclose($file);
            }
            if($c){
                $result['status'] = 1;
                $result['error'] = 'Successfully exported file.';
                $result['link'] = $fileLink;
            }
        break;

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
				switch($_REQUEST['type']){
					case 'single':
						if($c && ( //email must not be empty.
							!isset($_REQUEST['email'])
							|| $_REQUEST['email'] == ''
						)){
							$c = false; $result['error'] = 'Email field cannot be empty.';
						}
						if($c){ //Passed initial validation
							if( strpos( $_REQUEST['email'], '@' ) !== FALSE && !filter_var( $_REQUEST['email'], FILTER_VALIDATE_EMAIL ) ) {
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
								if( strpos( $email, '@' ) !== FALSE && !filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
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

	if( 'Add' != $_REQUEST['a'] ) {
		echo json_encode($result);
		exit;
	}
}

if(isset($_REQUEST['d'])){ 
	include(INCLUDES."d_shared.php");
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
<table class="standard">
	<thead>
		<tr class="bgGray">
			<td><p>Suppression List</p></td>
			<td><p>Record Count</p></td>
			<td><p>Options</p></td>
		</tr>
	</thead>
	<tbody>
<?php
			foreach($lists as $suppressionList){ 
				if($suppressionList == 'global'){ $display_listName = 'Global'; $idCompany = 0; }
				else { $display_listName = $companyCache[$suppressionList]->name; $idCompany = $suppressionList; }
				$suppressionCount = getSuppressionCount($suppressionList);
				if($suppressionCount === false){ $display_suppressionCount = 'Error'; }
				else{ $display_suppressionCount = $suppressionCount; }
?>
		<tr class="bgGray">
			<td><p><?php echo $display_listName; ?></p></td>
			<td><p class='aRight'><?php echo $display_suppressionCount; ?></p></td>
			<td>
				<p>
                	<input type='button' value='Export Data' onclick='exportFile(<?php echo $idCompany; ?>);'/>
                	<a href='#' id='resultExport_<?php echo $idCompany; ?>'></a>
                	<span id='resultQuery_<?php echo $idCompany; ?>'></span>
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
	<select name="list" id='suppress_list' onchange='checkIfMulti();'>
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
					if($companies === false){ 
?><p>Database error: could not fetch companies.</p><?php
					} else {
?>
<p>Upload Suppression File</p>
<p><strong>Suppression file must be saved in CSV format.  Excel format will not work.  There should only be one column in the spreadsheet and that column will contain the list of email addresses to be added.  Maximum file size is 2MB.</strong></p>
<p>
<form enctype="multipart/form-data" action="mgr_suppress.php" method="post">
	<input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
	File: <input type="file" name="suppress_file" multiple="false" accept="text/csv" />
	to Suppression List 
	<select id="suppress_list" name="list" onchange="checkIfMulti();">
		<option value="global">Global Suppression</option>
		<option value="multiple">Multiple Separate Lists</option>
<?php
						foreach($companies as $company){ 
?>
		<option value="<?php echo $company->idCompany; ?>"><?php echo $company->name; ?> Suppression</option>
<?php
						}
?>
	</select>
	<input type="submit" name="a" value="Add" />
</p>
<div id="dialog_multiselect" class="hidden">
	<p>Select suppression lists to add this email to.</p>
<?php
						foreach($companies as $company){ 
?>
	<div class="fl">
		<input type="checkbox" value="<?php echo $company->idCompany; ?>" name="suppress_multiselect_<?php echo $company->idCompany; ?>" 
			id="suppress_multiselect_<?php echo $company->idCompany; ?>"
		> <?php echo $company->name; ?>
	</div>
<?php
						}
?>
	<div class="clr"></div>
</form>
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
include(INCLUDES."c_header.php");
?>
<body>
<script type="text/javascript">
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

function exportFile(idCompany){
    var response = $.ajax({
        url: "mgr_suppress.php",
        type: "POST",
        async: true,
        data: ({
            "a" : "exportData"
            , "idCompany": idCompany
        })
    }).done(function(responseText){
        var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
        if(result===null) {
            alert("JSON Failed: "+responseText);
            return false;
        }
        if(result.status == 1){
            $('#resultExport_'+idCompany).html('Download File');
            $('#resultExport_'+idCompany).attr('href', result.link);
        } else {
            $('#resultExport_'+idCompany).html('');
            alert(result.error);
        }
    });
    $('#resultExport_'+idCompany).html("Processing...");
}

$(document).ready(function(){
	display('suppressionCounts');
});
</script>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls' class='fl50'>
			<p>Suppression Manager</p>
			<p>
				<a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'single' });">Add Single Email</a>
				| <a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'multiple' });">Add Multiple Emails</a>
				| <a href='#' class='nonLink' onclick="display('dialog_import',{ 'type': 'file' });">Add File</a>
			</p>
			<div id='resultImport'><?php  if( !empty( $_REQUEST['a'] ) && 'Add' == $_REQUEST['a'] ) { print "<p style=\"color: blue;\">File import status: {$result['error']}</p><p>Successes: {$counts['success']}</p><p>Invalid emails: {$counts['invalid']}</p><p>Duplicates: {$counts['dupe']}</p><p>Failures: {$counts['failures']}</p>" ; } ?></div>
			<div id='dialog_import'></div>
		</div>
		<div id='suppressionCounts' class='fl50'></div>
		<div class='clr'></div>
	</div>
</div>

</body>
</html>
