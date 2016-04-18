<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$divisions = $leads->getDivisions();

require_once( INCLUDES . 'display.php' );

if(isset($_REQUEST['a'])){
	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch($_REQUEST['a']){
		case "addNewCompany":
			$c = true;
			$result['error'] = 'Failed when trying to add a new company';

			if( empty( trim( $_REQUEST['name'] ) ) ) {
				$result['error'] = 'Company name cannot be blank.';
				$c = false;
			}

			if($c){
				if( $leads->checkCompanyName( trim( $_REQUEST['name'] ) ) ) {
					$c = false;
					$result['error'] = 'That company name already exists in the database.';
				}
			}

			if($c){
				$idCompany = $leads->addCompany( array(
					'name' => trim( $_REQUEST['name'] ),
					'note' => empty( $_REQUEST['note'] ) ? null : trim( $_REQUEST['note'] ),
					'url' => empty( $_REQUEST['url'] ) ? null : trim( $_REQUEST['url'] ),
					'address' => empty( $_REQUEST['address'] ) ? null : trim( $_REQUEST['address'] ),
					'city' => empty( $_REQUEST['city'] ) ? null : trim( $_REQUEST['city'] ),
					'state' => empty( $_REQUEST['state'] ) ? null : trim( $_REQUEST['state'] ),
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : trim( $_REQUEST['zipcode'] ),
					'main_name' => empty( $_REQUEST['main_name'] ) ? null : trim( $_REQUEST['main_name'] ),
					'main_phone' => empty( $_REQUEST['main_phone'] ) ? null : trim( $_REQUEST['main_phone'] ),
					'main_email' => empty( $_REQUEST['main_email'] ) ? null : trim( $_REQUEST['main_email'] ),
					'returns_name' => empty( $_REQUEST['returns_name'] ) ? null : trim( $_REQUEST['returns_name'] ),
					'returns_phone' => empty( $_REQUEST['returns_phone'] ) ? null : trim( $_REQUEST['returns_phone'] ),
					'returns_email' => empty( $_REQUEST['returns_email'] ) ? null : trim( $_REQUEST['returns_email'] ),
					'acct_name' => empty( $_REQUEST['acct_name'] ) ? null : trim( $_REQUEST['acct_name'] ),
					'acct_phone' => empty( $_REQUEST['acct_phone'] ) ? null : trim( $_REQUEST['acct_phone'] ),
					'acct_email' => empty( $_REQUEST['acct_email'] ) ? null : trim( $_REQUEST['acct_email'] ),
					'tech_name' => empty( $_REQUEST['tech_name'] ) ? null : trim( $_REQUEST['tech_name'] ),
					'tech_phone' => empty( $_REQUEST['tech_phone'] ) ? null : trim( $_REQUEST['tech_phone'] ),
					'tech_email' => empty( $_REQUEST['tech_email'] ) ? null : trim( $_REQUEST['tech_email'] ),
				) );
				if( null === $idCompany ) {
					$c = false;
					$result['error'] = $newCompanyResult['error'];
				} else {
					$leads->clearCompanyDivisions( $idCompany );
					if( !empty( $_REQUEST['divisions'] ) && is_array( $_REQUEST['divisions'] ) ) {
						foreach( $_REQUEST['divisions'] as $division ) {
							$leads->addCompanyDivision( $idCompany, $division );
						}
					}
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

			if( empty( trim( $_REQUEST['name'] ) ) ) {
				$result['error'] = 'Company name cannot be blank.';
				$c = false;
			}

			if($c){
				if( $leads->checkCompanyName( trim( $_REQUEST['name'] ), $_REQUEST['idCompany'] ) ) {
					$c = false;
					$result['error'] = 'That company name already exists in the database.';
				}
			}

			if($c){
				$alterCompanyResult = $leads->updateCompany( $_REQUEST['idCompany'], array(
					'name' => trim( $_REQUEST['name'] ),
					'note' => empty( $_REQUEST['note'] ) ? null : trim( $_REQUEST['note'] ),
					'url' => empty( $_REQUEST['url'] ) ? null : trim( $_REQUEST['url'] ),
					'address' => empty( $_REQUEST['address'] ) ? null : trim( $_REQUEST['address'] ),
					'city' => empty( $_REQUEST['city'] ) ? null : trim( $_REQUEST['city'] ),
					'state' => empty( $_REQUEST['state'] ) ? null : trim( $_REQUEST['state'] ),
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : trim( $_REQUEST['zipcode'] ),
					'main_name' => empty( $_REQUEST['main_name'] ) ? null : trim( $_REQUEST['main_name'] ),
					'main_phone' => empty( $_REQUEST['main_phone'] ) ? null : trim( $_REQUEST['main_phone'] ),
					'main_email' => empty( $_REQUEST['main_email'] ) ? null : trim( $_REQUEST['main_email'] ),
					'returns_name' => empty( $_REQUEST['returns_name'] ) ? null : trim( $_REQUEST['returns_name'] ),
					'returns_phone' => empty( $_REQUEST['returns_phone'] ) ? null : trim( $_REQUEST['returns_phone'] ),
					'returns_email' => empty( $_REQUEST['returns_email'] ) ? null : trim( $_REQUEST['returns_email'] ),
					'acct_name' => empty( $_REQUEST['acct_name'] ) ? null : trim( $_REQUEST['acct_name'] ),
					'acct_phone' => empty( $_REQUEST['acct_phone'] ) ? null : trim( $_REQUEST['acct_phone'] ),
					'acct_email' => empty( $_REQUEST['acct_email'] ) ? null : trim( $_REQUEST['acct_email'] ),
					'tech_name' => empty( $_REQUEST['tech_name'] ) ? null : trim( $_REQUEST['tech_name'] ),
					'tech_phone' => empty( $_REQUEST['tech_phone'] ) ? null : trim( $_REQUEST['tech_phone'] ),
					'tech_email' => empty( $_REQUEST['tech_email'] ) ? null : trim( $_REQUEST['tech_email'] ),
				) );

				if($alterCompanyResult === false){
					$c = false;
					$result['error'] = 'Database failure, could not alter company.';
				} else {
					$leads->clearCompanyDivisions( $_REQUEST['idCompany'] );
					if( !empty( $_REQUEST['divisions'] ) && is_array( $_REQUEST['divisions'] ) ) {
						foreach( $_REQUEST['divisions'] as $division ) {
							$leads->addCompanyDivision( $_REQUEST['idCompany'], $division );
						}
					}
				}
			}

			if($c){
				$result['status'] = 1;
				$result['error'] = 'Successfully altered existing company.';
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
					'id' => 'divisions',
					'label' => 'Divisions',
					'type' => 'checkbox',
					'choices' => $divisions,
					'choice_append' => '<br/>',
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
					'label' => 'Returns Contact',
				),
				array(
					'id' => 'returns_name',
					'label' => 'Name',
					'type' => 'text',
				),
				array(
					'id' => 'returns_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
				),
				array(
					'id' => 'returns_email',
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
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewCompany',
				),
			);

			Display::displayForm( 'new_company', $fields );

		break;

		case "dialog_editcompany":
			$companyId = !empty( $_REQUEST['companyId'] ) ? $_REQUEST['companyId'] : '';
			$company = $leads->getCompany($companyId);

			if( empty( $company ) ) {
?>
<p>There is no company that exists by that ID.</p>
<?php
			} else {

				$set_divisions = array();
				$db_divisions = $leads->getCompanyDivisions( $company->idCompany );
				foreach( $db_divisions as $division ) {
					$set_divisions[$division->divisionId] = true;
				}

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
					'id' => 'divisions',
					'label' => 'Divisions',
					'type' => 'checkbox',
					'choices' => $divisions,
					'choice_append' => '<br/>',
					'value' => $set_divisions,
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
					'label' => 'Returns Contact',
				),
				array(
					'id' => 'returns_name',
					'label' => 'Name',
					'type' => 'text',
					'value' => $company->returns_name,
				),
				array(
					'id' => 'returns_phone',
					'label' => 'Phone Number',
					'type' => 'tel',
					'value' => $company->returns_phone,
				),
				array(
					'id' => 'returns_email',
					'label' => 'Email Address',
					'type' => 'email',
					'value' => $company->returns_email,
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
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'alterCompany',
				),
			);

			Display::displayForm( 'edit_company', $fields );

			}
		break;
	}
	exit;
}

$title = 'Company Manager';
include(INCLUDES."c_header.php");
?>
<body>

<?php include(INCLUDES.'c_nav.php'); ?>

<div class="container-fluid">

<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newcompany">Add a new company</button>

<?php
	$companies = $leads->getCompanies();
	if( empty( $companies ) ) {

		print '<p>No companies exist in the database.</p>';

	} else {
?>

<table class="table table-bordered table-condensed table-striped">
	<thead>
		<tr class="bgGray header">
			<th>ID</th>
			<th>Company Name</th>
			<th class="hidden-xs">Notes</th>
			<th>Options</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach($companies as $company){
?>
		<tr>
			<td><?php echo $company->idCompany; ?></td>
			<td><?php echo htmlentities( $company->name ); ?></td>
			<td class="hidden-xs"><?php echo htmlentities( $company->note ); ?></td>
			<td class="text-center"><button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editcompany" data-company-id="<?php echo $company->idCompany; ?>">Edit</button></td>
		</tr>
<?php
		}
?>
	</tbody>
</table>

<?php
	}

?>
</div>

<div class="modal fade" id="newcompany" tabindex="-1" role="dialog" aria-labelledby="newcompany_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="newcompany_title">Add a new company</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-newcompany" type="button" class="btn btn-primary">Add Company</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editcompany" tabindex="-1" role="dialog" aria-labelledby="editcompany_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editcompany_title">Edit a company</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editcompany" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
$('#modal-save-newcompany').click( function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "companies.php",
		type: "POST",
		async: true,
		data: $("#new_company").serialize()
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			$('#newcompany').modal('toggle');
			$('#new_company').trigger('reset');
			window.location.reload(true);
		} else {
			alert(result.error);
			display('dialog_newcompany', { 'name': name, 'note' : note } );
		}
	});
});

$('#newcompany').on('show.bs.modal', function(e) {
	var modal = $(this);

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'companies.php',
		data: {
			'd': 'dialog_newcompany'
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#modal-save-editcompany').click(function(event) {
	event.preventDefault();

	var response = $.ajax({
		url: "companies.php",
		type: "POST",
		async: true,
		data: $("#edit_company").serialize()
	}).done(function(responseText){
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) { alert("JSON Failed: "+responseText); return false; }
		if(result.status == 1){
			$('#editcompany_<?php echo $company->idCompany; ?>').modal('toggle');
			window.location.reload(true);
		} else {
			alert(result.error);
		}
	});
});

$('#editcompany').on('show.bs.modal', function(e) {
	var modal = $(this);
	var companyId = $(e.relatedTarget).data('company-id');

	$.ajax({
		cache: false,
		type: 'POST',
		url: 'companies.php',
		data: {
			'd': 'dialog_editcompany',
			'companyId': companyId
		},
		success: function(data) {
			modal.find('.modal-body').html(data);
		}
	});
});

$('#newcompany, #editcompany').on('hide.bs.modal', function(e) {
	$(this).find('.modal-body').html('');
});
</script>

</body>
</html>
