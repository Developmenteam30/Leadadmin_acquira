<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){
		case "addNewCompany": 
			$c = true;
			$result['error'] = 'Failed when trying to add a new company';

			if($c){ 
				if( $leads->checkCompanyName( $_REQUEST['name'] ) ) {
					$c = false;
					$result['error'] = 'Company already exists in the database.';
				}
			}

			if($c){ 
				$idCompany = $leads->addCompany( array(
					'name' => $_REQUEST['name'],
					'note' => empty( $_REQUEST['note'] ) ? null : $_REQUEST['note'],
					'url' => empty( $_REQUEST['url'] ) ? null : $_REQUEST['url'],
					'address' => empty( $_REQUEST['address'] ) ? null : $_REQUEST['address'],
					'city' => empty( $_REQUEST['city'] ) ? null : $_REQUEST['city'],
					'state' => empty( $_REQUEST['state'] ) ? null : $_REQUEST['state'],
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : $_REQUEST['zipcode'],
					'main_name' => empty( $_REQUEST['main_name'] ) ? null : $_REQUEST['main_name'],
					'main_phone' => empty( $_REQUEST['main_phone'] ) ? null : $_REQUEST['main_phone'],
					'main_email' => empty( $_REQUEST['main_email'] ) ? null : $_REQUEST['main_email'],
					'acct_name' => empty( $_REQUEST['acct_name'] ) ? null : $_REQUEST['acct_name'],
					'acct_phone' => empty( $_REQUEST['acct_phone'] ) ? null : $_REQUEST['acct_phone'],
					'acct_email' => empty( $_REQUEST['acct_email'] ) ? null : $_REQUEST['acct_email'],
					'tech_name' => empty( $_REQUEST['tech_name'] ) ? null : $_REQUEST['tech_name'],
					'tech_phone' => empty( $_REQUEST['tech_phone'] ) ? null : $_REQUEST['tech_phone'],
					'tech_email' => empty( $_REQUEST['tech_email'] ) ? null : $_REQUEST['tech_email'],
				) );
				if( null === $idCompany ) { 
					$c = false;
					$result['error'] = $newCompanyResult['error'];
				}
			}

			if($c){ 
				$result['status'] = 1;
				$result['error'] = 'Successfully added new company.';
			}
		break;

		case "alterCompany":
			$c = true;
			$result['error'] = 'Failed when trying to edit a company';

			if($c){ 
				if( $leads->checkCompanyName( $_REQUEST['name'], $_REQUEST['idCompany'] ) ) {
					$c = false;
					$result['error'] = 'Company already exists in the database.';
				}
			}

			if($c){ 
				$alterCompanyResult = $leads->updateCompany( $_REQUEST['idCompany'], array(
					'name' => $_REQUEST['name'],
					'note' => empty( $_REQUEST['note'] ) ? null : $_REQUEST['note'],
					'url' => empty( $_REQUEST['url'] ) ? null : $_REQUEST['url'],
					'address' => empty( $_REQUEST['address'] ) ? null : $_REQUEST['address'],
					'city' => empty( $_REQUEST['city'] ) ? null : $_REQUEST['city'],
					'state' => empty( $_REQUEST['state'] ) ? null : $_REQUEST['state'],
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : $_REQUEST['zipcode'],
					'main_name' => empty( $_REQUEST['main_name'] ) ? null : $_REQUEST['main_name'],
					'main_phone' => empty( $_REQUEST['main_phone'] ) ? null : $_REQUEST['main_phone'],
					'main_email' => empty( $_REQUEST['main_email'] ) ? null : $_REQUEST['main_email'],
					'acct_name' => empty( $_REQUEST['acct_name'] ) ? null : $_REQUEST['acct_name'],
					'acct_phone' => empty( $_REQUEST['acct_phone'] ) ? null : $_REQUEST['acct_phone'],
					'acct_email' => empty( $_REQUEST['acct_email'] ) ? null : $_REQUEST['acct_email'],
					'tech_name' => empty( $_REQUEST['tech_name'] ) ? null : $_REQUEST['tech_name'],
					'tech_phone' => empty( $_REQUEST['tech_phone'] ) ? null : $_REQUEST['tech_phone'],
					'tech_email' => empty( $_REQUEST['tech_email'] ) ? null : $_REQUEST['tech_email'],
				) );

				if($alterCompanyResult === false){ 
					$c = false;
					$result['error'] = 'Database failure, could not alter company.';
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
			$companies = $leads->getCompanies();
			if( empty( $companies ) ) {
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
				<?php echo htmlentities( $company->name ); ?>
			</td>
			<td>
				<?php echo htmlentities( $company->note ); ?>
			</td>
			<td>
				<a href='#' class='nonLink' onclick="display('dialog_editcompany', { 'sub': <?php echo $company->idCompany; ?>, 'idCompany':<?php echo $company->idCompany; ?>});">Edit</a>
			</td>
		</tr>
		<tr><td class='hidden' id='dialog_editcompany_<?php echo $company->idCompany; ?>' colspan='4'></td></tr>
<?php
				}
?>
	</tbody>
</table>
<?php
			}
		
		break;
		case "dialog_newcompany":

			$fields = array(
				array(
					'id' => 'name',
					'label' => 'Company Name',
					'type' => 'text',
					'required' => true,
				),
				array(
					'id' => 'address',
					'label' => 'Address',
					'type' => 'text',
				),
				array(
					'id' => 'city',
					'label' => 'City',
					'type' => 'text',
				),
				array(
					'id' => 'state',
					'label' => 'State',
					'type' => 'select',
					'choices' => array(
						'AL' => 'Alabama',
						'AK' => 'Alaska',
						'AZ' => 'Arizona',
						'AR' => 'Arkansas',
						'CA' => 'California',
						'CO' => 'Colorado',
						'CT' => 'Connecticut',
						'DE' => 'Delaware',
						'DC' => 'District of Columbia',
						'FL' => 'Florida',
						'GA' => 'Georgia',
						'HI' => 'Hawaii',
						'ID' => 'Idaho',
						'IL' => 'Illinois',
						'IN' => 'Indiana',
						'IA' => 'Iowa',
						'KS' => 'Kansas',
						'KY' => 'Kentucky',
						'LA' => 'Louisiana',
						'ME' => 'Maine',
						'MD' => 'Maryland',
						'MA' => 'Massachusetts',
						'MI' => 'Michigan',
						'MN' => 'Minnesota',
						'MS' => 'Mississippi',
						'MO' => 'Missouri',
						'MT' => 'Montana',
						'NE' => 'Nebraska',
						'NV' => 'Nevada',
						'NH' => 'New Hampshire',
						'NJ' => 'New Jersey',
						'NM' => 'New Mexico',
						'NY' => 'New York',
						'NC' => 'North Carolina',
						'ND' => 'North Dakota',
						'OH' => 'Ohio',
						'OK' => 'Oklahoma',
						'OR' => 'Oregon',
						'PA' => 'Pennsylvania',
						'RI' => 'Rhode Island',
						'SC' => 'South Carolina',
						'SD' => 'South Dakota',
						'TN' => 'Tennessee',
						'TX' => 'Texas',
						'UT' => 'Utah',
						'VT' => 'Vermont',
						'VA' => 'Virginia',
						'WA' => 'Washington',
						'WV' => 'West Virginia',
						'WI' => 'Wisconsin',
						'WY' => 'Wyoming',
					),
				),
				array(
					'id' => 'zipcode',
					'label' => 'Zip Code',
					'type' => 'number',
				),
				array(
					'id' => 'url',
					'label' => 'Web Site',
					'type' => 'url',
				),
				array(
					'id' => 'note',
					'label' => 'Notes',
					'type' => 'textarea',
				),
				array(
					'type' => '_header',
					'label' => 'Main Contact',
				),
				array(
					'id' => 'main_name',
					'label' => 'Name',
					'type' => 'text',
				),
				array(
					'id' => 'main_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
				),
				array(
					'id' => 'main_email',
					'label' => 'Email Address',
					'type' => 'email',
				),
				array(
					'type' => '_header',
					'label' => 'Accounting Contact',
				),
				array(
					'id' => 'acct_name',
					'label' => 'Name',
					'type' => 'text',
				),
				array(
					'id' => 'acct_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
				),
				array(
					'id' => 'acct_email',
					'label' => 'Email Address',
					'type' => 'email',
				),
				array(
					'type' => '_header',
					'label' => 'Technical Contact',
				),
				array(
					'id' => 'tech_name',
					'label' => 'Name',
					'type' => 'text',
				),
				array(
					'id' => 'tech_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
				),
				array(
					'id' => 'tech_email',
					'label' => 'Email Address',
					'type' => 'email',
				),
				array(
					'id' => 'submit',
					'type' => 'submit',
					'label' => 'Add Company',
				),
			);

			Display::displayForm( 'new_company', $fields, 'Add a New Company' );

?>
<script type="text/javascript">
$('#new_company').submit( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_companies.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "addNewCompany",
			"name": $("#new_company #name").val(),
			"note": $("#new_company #note").val(),
			"url": $("#new_company #url").val(),
			"address": $("#new_company #address").val(),
			"city": $("#new_company #city").val(),
			"state": $("#new_company #state").val(),
			"zipcode": $("#new_company #zipcode").val(),
			"main_name": $("#new_company #main_name").val(),
			"main_phone": $("#new_company #main_phone").val(),
			"main_email": $("#new_company #main_email").val(),
			"acct_name": $("#new_company #acct_name").val(),
			"acct_phone": $("#new_company #acct_phone").val(),
			"acct_email": $("#new_company #acct_email").val(),
			"tech_name": $("#new_company #tech_name").val(),
			"tech_phone": $("#new_company #tech_phone").val(),
			"tech_email": $("#new_company #tech_email").val(),
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
});
</script>

<?php
		break;
		case "dialog_editcompany":
			$idCompany = $_REQUEST['options']['idCompany'];
			$company = $leads->getCompany($idCompany);
?>
<div class='fr'>
    <a href='#' class='nonLink' onclick='closeContent("dialog_editcompany_<?php echo intval( $idCompany ); ?>");' >Close [X]</a>
</div>
<?php
			if( empty( $company ) ) {
?>
<p>There is no company that exists by that ID.</p>
<?php
			} else { 

			$fields = array(
				array(
					'id' => 'name',
					'label' => 'Company Name',
					'type' => 'text',
					'required' => true,
					'value' => $company->name,
				),
				array(
					'id' => 'address',
					'label' => 'Address',
					'type' => 'text',
					'value' => $company->address,
				),
				array(
					'id' => 'city',
					'label' => 'City',
					'type' => 'text',
					'value' => $company->city,
				),
				array(
					'id' => 'state',
					'label' => 'State',
					'type' => 'select',
					'choices' => array(
						'AL' => 'Alabama',
						'AK' => 'Alaska',
						'AZ' => 'Arizona',
						'AR' => 'Arkansas',
						'CA' => 'California',
						'CO' => 'Colorado',
						'CT' => 'Connecticut',
						'DE' => 'Delaware',
						'DC' => 'District of Columbia',
						'FL' => 'Florida',
						'GA' => 'Georgia',
						'HI' => 'Hawaii',
						'ID' => 'Idaho',
						'IL' => 'Illinois',
						'IN' => 'Indiana',
						'IA' => 'Iowa',
						'KS' => 'Kansas',
						'KY' => 'Kentucky',
						'LA' => 'Louisiana',
						'ME' => 'Maine',
						'MD' => 'Maryland',
						'MA' => 'Massachusetts',
						'MI' => 'Michigan',
						'MN' => 'Minnesota',
						'MS' => 'Mississippi',
						'MO' => 'Missouri',
						'MT' => 'Montana',
						'NE' => 'Nebraska',
						'NV' => 'Nevada',
						'NH' => 'New Hampshire',
						'NJ' => 'New Jersey',
						'NM' => 'New Mexico',
						'NY' => 'New York',
						'NC' => 'North Carolina',
						'ND' => 'North Dakota',
						'OH' => 'Ohio',
						'OK' => 'Oklahoma',
						'OR' => 'Oregon',
						'PA' => 'Pennsylvania',
						'RI' => 'Rhode Island',
						'SC' => 'South Carolina',
						'SD' => 'South Dakota',
						'TN' => 'Tennessee',
						'TX' => 'Texas',
						'UT' => 'Utah',
						'VT' => 'Vermont',
						'VA' => 'Virginia',
						'WA' => 'Washington',
						'WV' => 'West Virginia',
						'WI' => 'Wisconsin',
						'WY' => 'Wyoming',
					),
					'value' => $company->state,
				),
				array(
					'id' => 'zipcode',
					'label' => 'Zip Code',
					'type' => 'number',
					'value' => $company->zipcode,
				),
				array(
					'id' => 'url',
					'label' => 'Web Site',
					'type' => 'url',
					'value' => $company->url,
				),
				array(
					'id' => 'note',
					'label' => 'Notes',
					'type' => 'textarea',
					'value' => $company->note,
				),
				array(
					'type' => '_header',
					'label' => 'Main Contact',
				),
				array(
					'id' => 'main_name',
					'label' => 'Name',
					'type' => 'text',
					'value' => $company->main_name,
				),
				array(
					'id' => 'main_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
					'value' => $company->main_phone,
				),
				array(
					'id' => 'main_email',
					'label' => 'Email Address',
					'type' => 'email',
					'value' => $company->main_email,
				),
				array(
					'type' => '_header',
					'label' => 'Accounting Contact',
				),
				array(
					'id' => 'acct_name',
					'label' => 'Name',
					'type' => 'text',
					'value' => $company->acct_name,
				),
				array(
					'id' => 'acct_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
					'value' => $company->acct_phone,
				),
				array(
					'id' => 'acct_email',
					'label' => 'Email Address',
					'type' => 'email',
					'value' => $company->acct_email,
				),
				array(
					'type' => '_header',
					'label' => 'Technical Contact',
				),
				array(
					'id' => 'tech_name',
					'label' => 'Name',
					'type' => 'text',
					'value' => $company->tech_name,
				),
				array(
					'id' => 'tech_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
					'value' => $company->tech_phone,
				),
				array(
					'id' => 'tech_email',
					'label' => 'Email Address',
					'type' => 'email',
					'value' => $company->tech_email,
				),
				array(
					'id' => 'idCompany',
					'type' => 'hidden',
					'value' => $company->idCompany,
				),
				array(
					'id' => 'submit',
					'type' => 'submit',
					'label' => 'Save Changes',
				),
			);

			Display::displayForm( 'edit_company', $fields, 'Company Editor' );

?>
<script type="text/javascript">
$('#edit_company').submit( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "mgr_companies.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "alterCompany",
			"idCompany": $('#edit_company #idCompany').val(),
			"name": $("#edit_company #name").val(),
			"note": $("#edit_company #note").val(),
			"url": $("#edit_company #url").val(),
			"address": $("#edit_company #address").val(),
			"city": $("#edit_company #city").val(),
			"state": $("#edit_company #state").val(),
			"zipcode": $("#edit_company #zipcode").val(),
			"main_name": $("#edit_company #main_name").val(),
			"main_phone": $("#edit_company #main_phone").val(),
			"main_email": $("#edit_company #main_email").val(),
			"acct_name": $("#edit_company #acct_name").val(),
			"acct_phone": $("#edit_company #acct_phone").val(),
			"acct_email": $("#edit_company #acct_email").val(),
			"tech_name": $("#edit_company #tech_name").val(),
			"tech_phone": $("#edit_company #tech_phone").val(),
			"tech_email": $("#edit_company #tech_email").val(),
		})
	}).done(function(responseText){ 
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){ 
			closeContent('dialog_editcompany');
			display('companyList');
		} else { 
			alert(result.error);
			display('dialog_editcompany', { 'idCompany': $('#edit_company #idCompany').val() } );
		}
	});
	$('#dialog_newcompany').html("Processing...");
});
</script>

<?php
			}
		break;
	}
	exit;
}

$title = 'Company Manager';
include(INCLUDES."c_header.php");
?>
<script type="text/javascript">
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
