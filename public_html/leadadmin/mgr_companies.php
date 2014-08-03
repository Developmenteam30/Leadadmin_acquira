<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'display.php' );

$mysqlErrorSource = 'Manager - Companies';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_companies"; 
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

function checkExistsCompanyName($name){ 
	//Returns quantity of matching records, or false if it fails.
	dbCon();
	$checkCompany = "SELECT * FROM `".DATABASE_NAME."`.`companies` "
		."WHERE "
			."`name` = '".$GLOBALS['dbconnx']->escape_string($name)."' "
		.";";
	$docheckCompany = dbQry($checkCompany, 'Checking if company name exists', true);
	dbDcon();
	if($docheckCompany === false){ return false; }
	return $docheckCompany->num_rows;
}

function newCompany($name, $note){ 
	$result = array(
		'success' => false
		, 'error' => 'None.'
	);
	//Returns false on failure, true on success.
	$c = true;
	dbCon();
	if($c){
		$addCompany = "INSERT INTO `".DATABASE_NAME."`.`companies` "
			."(`name`,`note`) VALUES ( "
			."  '".$GLOBALS['dbconnx']->escape_string($name)."' "
			.", '".$GLOBALS['dbconnx']->escape_string($note)."' "
			.");";
		$doaddCompany = dbQry($addCompany, 'Adding new company to database', true);
		if($doaddCompany === false){ 
			$c = false; $result['error'] = 'Database failure, could not create company entry.';
		} else { 
			$idCompany = $GLOBALS['dbconnx']->insert_id;
		}
	}
	if($c){
		$addSuppressionList = "CREATE TABLE `".DATABASE_NAME."`.`suppression_".$idCompany."` "
			."LIKE `".DATABASE_NAME."`.`suppression_global`;";
		$doaddSuppressionList = dbQry($addSuppressionList, 'Creating new suppression list table.', true);
		if($doaddSuppressionList === false){
			$c = false; $result['error'] = 'Database failure, could not create suppression list table.';
		}
	}
	if($c){
		$result['success'] = true;
		$result['error' ] = 'Successfully created new company.';
	}
	dbDcon();
	return $result;
}

function alterCompany($idCompany, $name, $note){ 
	//Returns false on failure, true on success.
	dbCon();
	$alterCompany = "UPDATE `".DATABASE_NAME."`.`companies` "
		."SET "
			."`name` = '".$GLOBALS['dbconnx']->escape_string($name)."' "
			.", `note` = '".$GLOBALS['dbconnx']->escape_string($note)."' "
		."WHERE `idCompany` = '".$idCompany."'; ";
	$doalterCompany = dbQry($alterCompany, 'Altering company', true);
	dbDcon();
	if($doalterCompany === false){ return false; }
	return true;
}

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addNewCompany": 
			$c = true; $result['error'] = 'Failed when trying to add a new company';
			if($c){ 
				$exists = checkExistsCompanyName($_REQUEST['name']);
				if($exists === false){ 
					$c = false; $result['error'] = 'Database failure, could not check '
						.'if company name exists.';
				}
				if($exists > 0){ 
					$c = false; $result['error'] = 'Company already exists in the '
						.'database.';
				}
			}
			if($c){ 
				$newCompanyResult = newCompany($_REQUEST['name'], $_REQUEST['note']);
				if($newCompanyResult['success'] === false){ 
					$c = false; $result['error'] = $newCompanyResult['error'];
				}
			}
			if($c){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully added new company.';
			}
		break;
		case "alterCompany":
			$c = true; $result['error'] = 'Failed when trying to add a new company';
			if($c){ 
				$exists = checkExistsCompanyName($_REQUEST['name']);
				if($exists === false){ 
					$c = false; $result['error'] = 'Database failure, could not check '
						.'if company name exists.';
				}
				if($exists > 0){ 
					$c = false; $result['error'] = 'Company already exists in the '
						.'database.';
				}
			}
			if($c){ 
				$alterCompanyResult = alterCompany(
					$_REQUEST['idCompany'], $_REQUEST['name'], $_REQUEST['note']
				);
				if($alterCompanyResult === false){ 
					$c = false; $result['error'] = 'Database failure, could not alter '
						.'new company.';
				}
			}
			if($c){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully altered new company.';
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

		case 'companyList':
			$companies = getCompanies();
			if($companies === false){ 
?>
<p>Error getting company list.</p>
<?php
			} elseif($companies == 0){ 
?>
<p>No companies exist in the database.</p>
<?php
			} else { 
?>
<table class="standard">
	<thead>
		<tr class="bgGray header">
			<td>
				<p>ID</p>
			</td>
			<td>
				<p>Company Name</p>
			</td>
			<td>
				<p>Notes</p>
			</td>
			<td>
				<p>Options</p>
			</td>
		</tr>
	</thead>
	<tbody>
<?php
				foreach($companies as $company){ 
?>
		<tr class="bgGray">
			<td>
				<?php echo $company->idCompany; ?>
			</td>
			<td>
				<?php echo $company->name; ?>
			</td>
			<td>
				<?php echo $company->note; ?>
			</td>
			<td>
				<a href='#' class='nonLink' 
onclick="display('dialog_editcompany', {'idCompany':<?php echo $company->idCompany; ?>});" 
				>Edit</a>
			</td>
		</tr>
<?php
				}
?>
	</tbody>
</table>
<?php
			}
		
		break;
		case "dialog_newcompany":
?>
<table border='1' cellpadding='0' cellspacing='0' class='tCompany'>
	<tr>
		<td colspan='2' ><p>Add a New Company</p></td>
	</tr>
	<tr>
		<td><p>Company Name: </p></td>
		<td>
			<p>
				<input type='text' name='new_company_name' id='new_company_name' 
value='<?php if(isset($_REQUEST['options']['name'])){ echo $_REQUEST['options']['name']; } ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Notes (Optional): </p></td>
		<td>
			<p>
				<input type='text' name='new_company_note' id='new_company_note' 
value='<?php if(isset($_REQUEST['options']['note'])){ echo $_REQUEST['options']['note']; } ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td colspan='2'>
			<p class='aRight'>
				<input type='button' value='Add Company' onclick='addNewCompany();' />
			</p>
		</td>
	</tr>
</table>
<?php
		break;
		case "dialog_editcompany":
			$idCompany = $_REQUEST['options']['idCompany'];
			$company = getCompany($idCompany);
			if($company === false){ 
?>
<p>There was an error fetching the company to edit.</p>
<?php
			} elseif(!is_object($company) && $company == 0){ 
?>
<p>There is no company that exists by that ID.</p>
<?php
			} else { 
				if(isset($_REQUEST['options']['name'])){ 
					$company_name = $_REQUEST['options']['name'];
				} else { 
					$company_name = $company->name;
				}
				if(isset($_REQUEST['options']['note'])){ 
					$company_note = $_REQUEST['options']['note'];
				} else { 
					$company_note = $company->note;
				}
?>
<input type='hidden' name='edit_company_idCompany' id='edit_company_idCompany'
	value='<?php echo $company->idCompany; ?>' 
/>
<table border='1' cellpadding='0' cellspacing='0' class='tCompany'>
	<tr>
		<td colspan='2' ><p>Edit Company</p></td>
	</tr>
	<tr>
		<td><p>Company Name: </p></td>
		<td>
			<p>
				<input type='text' name='edit_company_name' id='edit_company_name' 
					value='<?php echo $company_name; ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td><p>Notes (Optional): </p></td>
		<td>
			<p>
				<input type='text' name='edit_company_note' id='edit_company_note' 
					value='<?php echo $company_note; ?>'
				/>
			</p>
		</td>
	</tr>
	<tr>
		<td>
			<p>
				<input type='button' value='Cancel Edits' 
					onclick="closeContent('dialog_editcompany');"
				/>
			</p>
		</td>
		<td>
			<p>
				<input type='button' value='Finish Edits' onclick='editCompany();' />
			</p>
		</td>
	</tr>
</table>
<?php
			}
		break;
	}
	exit;
}

$title = 'Company Manager';
include(INCLUDES."c_header.php");
?>
<script>
function addNewCompany(){ 
	name = $('#new_company_name').val();
	note = $('#new_company_note').val();
	if(name == ''){ 
		alert('You must enter a company name.'); return false;
	}
	var response = $.ajax({
		url: "mgr_companies.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "addNewCompany"
			, "name": name
			, "note": note
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){ 
			closeContent('dialog_newcompany');
			display('companyList');
		} else { 
			alert(result.error);
			display('dialog_newcompany', { 'name': name, 'note' : note } );
		}
	});
	$('#dialog_newcompany').html("Processing...");
}

function editCompany(){ 
	idCompany = $('#edit_company_idCompany').val();
	name = $('#edit_company_name').val();
	note = $('#edit_company_note').val();
	if(name == ''){ 
		alert('You must enter a company name.'); return false;
	}
	var response = $.ajax({
		url: "mgr_companies.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "alterCompany"
			, "idCompany" : idCompany
			, "name": name
			, "note": note
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){ 
			closeContent('dialog_editcompany');
			display('companyList');
		} else { 
			alert(result.error);
			display('dialog_editcompany'
				, { 'idCompany': idCompany, 'name': name, 'note' : note } 
			);
		}
	});
	$('#dialog_newcompany').html("Processing...");
}

$(document).ready(function(){ 
	display('companyList');
});
</script>
<style>
table.tCompany { margin-bottom: 20px; }
table.tCompany th, table.tCompany td { padding: 3px; }
</style>
<body>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
		<div id='controls'>
			<a href='#' class='nonLink' onclick="display('dialog_newcompany');" 
			>Add New Company</a>
		</div>
		<div id='dialogs'>
			<div id='dialog_newcompany' style='display:none;'></div>
			<div id='dialog_editcompany' style='display:none;'></div>
		</div>
		<div id='companyList'></div>
	</div>
	<div class='clr'></div>
</div>

</body>
</html>
