<?php

include( "../../includes/c_config.php" );

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

require_once( INCLUDES . 'leads.php' );
$leads = Leads::getInstance();

$divisions = $leads->getDivisions();
$status = !empty( $_REQUEST['status'] ) ? $_REQUEST['status'] : null;

require_once( INCLUDES . 'display.php' );

if( isset( $_REQUEST['a'] ) ) {
	Header( 'Content-Type: application/json' );

	$result = array(
		'status' => 0,
		'error' => 'Action does not exist.',
	);
	switch( $_REQUEST['a'] ) {
		case "addNewCompany":
			$c = true;
			$result['error'] = 'Failed when trying to add a new company';

			if( empty( trim( $_REQUEST['name'] ) ) ) {
				$result['error'] = 'Company name cannot be blank.';
				$c = false;
			}

			if( $c ) {
				if( $leads->checkCompanyName( trim( $_REQUEST['name'] ) ) ) {
					$c = false;
					$result['error'] = 'That company name already exists in the database.';
				}
			}

			if( $c && !empty( $_REQUEST['main_email'] ) && !filter_var( $_REQUEST['main_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Main Contact.';
			}

			if( $c && !empty( $_REQUEST['returns_email'] ) && !filter_var( $_REQUEST['returns_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Returns Contact.';
			}

			if( $c && !empty( $_REQUEST['acct_email'] ) && !filter_var( $_REQUEST['acct_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Accounting Contact.';
			}

			if( $c && !empty( $_REQUEST['tech_email'] ) && !filter_var( $_REQUEST['tech_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Technical Contact.';
			}

			if( $c ) {
				$isPublisher = false;
				$isAdvertiser = false;
				if( !empty( $_REQUEST['companyType'] ) && is_array( $_REQUEST['companyType'] ) ) {
					foreach( $_REQUEST['companyType'] as $key => $val ) {
						if( 'isPublisher' === $val ) {
							$isPublisher = true;
						} else if( 'isAdvertiser' === $val ) {
							$isAdvertiser = true;
						}
					}
				}

				$idCompany = $leads->addCompany( array(
					'name' => trim( $_REQUEST['name'] ),
					'note' => empty( $_REQUEST['note'] ) ? null : trim( $_REQUEST['note'] ),
					'url' => empty( $_REQUEST['url'] ) ? null : trim( $_REQUEST['url'] ),
					'address' => empty( $_REQUEST['address'] ) ? null : trim( $_REQUEST['address'] ),
					'city' => empty( $_REQUEST['city'] ) ? null : trim( $_REQUEST['city'] ),
					'state' => empty( $_REQUEST['state'] ) ? null : trim( $_REQUEST['state'] ),
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : trim( $_REQUEST['zipcode'] ),
					'country' => empty( $_REQUEST['country'] ) ? null : trim( $_REQUEST['country'] ),
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
					'accountManager' => empty( $_REQUEST['accountManager'] ) ? null : $_REQUEST['accountManager'],
					'accountOpener' => empty( $_REQUEST['accountOpener'] ) ? null : $_REQUEST['accountOpener'],
					'salesperson' => empty( $_REQUEST['salesperson'] ) ? null : $_REQUEST['salesperson'],
					'isPublisher' => $isPublisher ? 1 : 0,
					'isAdvertiser' => $isAdvertiser ? 1 : 0,
				) );
				if( null === $idCompany ) {
					$c = false;
					$result['error'] = 'Error adding this company to the database.';
				} else {
					$leads->auditLog( 'COMPANIES:ADD', $idCompany );
					$leads->clearCompanyDivisions( $idCompany );
					if( !empty( $_REQUEST['divisions'] ) && is_array( $_REQUEST['divisions'] ) ) {
						foreach( $_REQUEST['divisions'] as $division ) {
							$leads->addCompanyDivision( $idCompany, $division );
						}
					}
					$leads->clearCompanyVerticals( $idCompany );
					if( !empty( $_REQUEST['verticals'] ) && is_array( $_REQUEST['verticals'] ) ) {
						foreach( $_REQUEST['verticals'] as $vertical ) {
							$leads->addCompanyVertical( $idCompany, $vertical );
						}
					}
				}
			}

			if( $c ) {
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

			if( $c ) {
				if( $leads->checkCompanyName( trim( $_REQUEST['name'] ), $_REQUEST['idCompany'] ) ) {
					$c = false;
					$result['error'] = 'That company name already exists in the database.';
				}
			}

			if( $c && empty( $_REQUEST['status'] ) ) {
				$c = false;
				$result['error'] = 'Please select a company status.';
			}

			if( $c && !empty( $_REQUEST['main_email'] ) && !filter_var( $_REQUEST['main_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Main Contact.';
			}

			if( $c && !empty( $_REQUEST['returns_email'] ) && !filter_var( $_REQUEST['returns_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Returns Contact.';
			}

			if( $c && !empty( $_REQUEST['acct_email'] ) && !filter_var( $_REQUEST['acct_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Accounting Contact.';
			}

			if( $c && !empty( $_REQUEST['tech_email'] ) && !filter_var( $_REQUEST['tech_email'], FILTER_VALIDATE_EMAIL ) ) {
				$c = false;
				$result['error'] = 'Please enter a valid email address for the Technical Contact.';
			}

			if( $c ) {
				$isPublisher = false;
				$isAdvertiser = false;
				if( !empty( $_REQUEST['companyType'] ) && is_array( $_REQUEST['companyType'] ) ) {
					foreach( $_REQUEST['companyType'] as $key => $val ) {
						if( 'isPublisher' === $val ) {
							$isPublisher = true;
						} else if( 'isAdvertiser' === $val ) {
							$isAdvertiser = true;
						}
					}
				}

				$alterCompanyResult = $leads->updateCompany( $_REQUEST['idCompany'], array(
					'name' => trim( $_REQUEST['name'] ),
					'note' => empty( $_REQUEST['note'] ) ? null : trim( $_REQUEST['note'] ),
					'url' => empty( $_REQUEST['url'] ) ? null : trim( $_REQUEST['url'] ),
					'address' => empty( $_REQUEST['address'] ) ? null : trim( $_REQUEST['address'] ),
					'city' => empty( $_REQUEST['city'] ) ? null : trim( $_REQUEST['city'] ),
					'state' => empty( $_REQUEST['state'] ) ? null : trim( $_REQUEST['state'] ),
					'zipcode' => empty( $_REQUEST['zipcode'] ) ? null : trim( $_REQUEST['zipcode'] ),
					'country' => empty( $_REQUEST['country'] ) ? null : trim( $_REQUEST['country'] ),
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
					'accountManager' => empty( $_REQUEST['accountManager'] ) ? null : $_REQUEST['accountManager'],
					'accountOpener' => empty( $_REQUEST['accountOpener'] ) ? null : $_REQUEST['accountOpener'],
					'salesperson' => empty( $_REQUEST['salesperson'] ) ? null : $_REQUEST['salesperson'],
					'status' => empty( $_REQUEST['status'] ) ? 'active' : $_REQUEST['status'],
					'isPublisher' => $isPublisher ? 1 : 0,
					'isAdvertiser' => $isAdvertiser ? 1 : 0,
				) );

				if( $alterCompanyResult === false ) {
					$c = false;
					$result['error'] = 'Database failure, could not alter company.';
				} else {
					$leads->auditLog( 'COMPANIES:EDIT', $_REQUEST['idCompany'] );
					$leads->clearCompanyDivisions( $_REQUEST['idCompany'] );
					if( !empty( $_REQUEST['divisions'] ) && is_array( $_REQUEST['divisions'] ) ) {
						foreach( $_REQUEST['divisions'] as $division ) {
							$leads->addCompanyDivision( $_REQUEST['idCompany'], $division );
						}
					}
					$leads->clearCompanyVerticals( $_REQUEST['idCompany'] );
					if( !empty( $_REQUEST['verticals'] ) && is_array( $_REQUEST['verticals'] ) ) {
						foreach( $_REQUEST['verticals'] as $vertical ) {
							$leads->addCompanyVertical( $_REQUEST['idCompany'], $vertical );
						}
					}
				}

			}

			if( $c ) {
				$result['status'] = 1;
				$result['error'] = 'Successfully altered existing company.';
			}
			break;
	}
	echo json_encode( $result );
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

		case "dialog_newcompany":

			$verticals = array();
			$db_divisions = $leads->getDivisions();
			foreach( $db_divisions as $key => $val ) {
				$db_verticals = $leads->getDivisionVerticals( $key );
				$verticals[$val] = $db_verticals;
			}

			$fields = array(
				array(
					'id' => 'name',
					'label' => 'Company Name',
					'type' => 'text',
					'required' => true,
				),
				array(
					'id' => 'country',
					'label' => 'Country',
					'type' => 'select',
					'choices' => $leads->getCountries(),
					'value' => 236,
					'placeholder' => 'Select a country',
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
					'label' => 'State/Province',
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
					'label' => 'Zip/Postal Code',
					'type' => 'text',
				),
				array(
					'id' => 'url',
					'label' => 'Web Site',
					'type' => 'url',
				),
				array(
					'id' => 'companyType',
					'label' => 'Company Type',
					'type' => 'checkbox',
					'choices' => array(
						'isPublisher' => 'Publisher / Affiliate',
						'isAdvertiser' => 'Advertiser',
					),
					'choice_append' => '<br/>',
				),
				array(
					'id' => 'accountManager',
					'label' => 'Account Manager',
					'type' => 'select',
					'placeholder' => 'Select an account manager',
					'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
				),
				array(
					'id' => 'accountOpener',
					'label' => 'Account Opener',
					'type' => 'select',
					'placeholder' => 'Select an account opener',
					'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
				),
				array(
					'id' => 'salesperson',
					'label' => 'Sales Person',
					'type' => 'select',
					'placeholder' => 'Select a sales person',
					'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
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
					'id' => 'verticals',
					'label' => 'Verticals',
					'type' => 'select',
					'multiple' => true,
					'placeholder' => false,
					'choices' => $verticals,
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
					'id' => 'returns_copy',
					'label' => '',
					'type' => 'checkbox',
					'choices' => array(
						'1' => 'Copy info from Main Contact',
					),
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
					'id' => 'acct_copy',
					'label' => '',
					'type' => 'checkbox',
					'choices' => array(
						'1' => 'Copy info from Main Contact',
					),
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
					'id' => 'tech_copy',
					'label' => '',
					'type' => 'checkbox',
					'choices' => array(
						'1' => 'Copy info from Main Contact',
					),
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

			?>
			<script type="text/javascript">
				$('input[name="returns_copy"]').click(function (event) {
					$('#returns_name').val($('#main_name').val());
					$('#returns_phone').val($('#main_phone').val());
					$('#returns_email').val($('#main_email').val());
				});
				$('input[name="acct_copy"]').click(function (event) {
					$('#acct_name').val($('#main_name').val());
					$('#acct_phone').val($('#main_phone').val());
					$('#acct_email').val($('#main_email').val());
				});
				$('input[name="tech_copy"]').click(function (event) {
					$('#tech_name').val($('#main_name').val());
					$('#tech_phone').val($('#main_phone').val());
					$('#tech_email').val($('#main_email').val());
				});
				$('#country').on('change', function (event) {
					var country = $(this).val();
					if (country != '236') {
						$('#state').replaceWith('<input class="form-control" id="state" name="state" type="text" value="" />');
					}
				});
			</script>
			<?php

			break;

		case "dialog_editcompany":
			$companyId = !empty( $_REQUEST['companyId'] ) ? $_REQUEST['companyId'] : '';
			$company = $leads->getCompany( $companyId );

			if( empty( $company ) ) {
				?>
				<p>There is no company that exists by that ID.</p>
				<?php
			} else {

				$verticals = array();
				$db_divisions = $leads->getDivisions();
				foreach( $db_divisions as $key => $val ) {
					$db_verticals = $leads->getDivisionVerticals( $key );
					$verticals[$val] = $db_verticals;
				}

				$set_divisions = array();
				$db_divisions = $leads->getCompanyDivisions( $company->idCompany );
				foreach( $db_divisions as $division ) {
					$set_divisions[$division->divisionId] = true;
				}

				$set_verticals = array();
				$db_verticals = $leads->getCompanyVerticals( $company->idCompany );
				foreach( $db_verticals as $vertical ) {
					$set_verticals[$vertical->verticalId] = true;
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
						'id' => 'status',
						'label' => 'Status',
						'type' => 'select',
						'choices' => array(
							'active' => 'Active',
							'hidden' => 'Hidden',
							'retired' => 'Retired',
						),
						'required' => true,
						'value' => $company->status,
					),
					array(
						'id' => 'country',
						'label' => 'Country',
						'type' => 'select',
						'choices' => $leads->getCountries(),
						'value' => $company->country,
						'placeholder' => 'Select a country',
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
						'label' => 'State/Province',
						'type' => $company->country == '236' ? 'select' : 'text',
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
						'label' => 'Zip/Postal Code',
						'type' => 'text',
						'value' => $company->zipcode,
					),
					array(
						'id' => 'url',
						'label' => 'Web Site',
						'type' => 'url',
						'value' => $company->url,
					),
					array(
						'id' => 'companyType',
						'label' => 'Company Type',
						'type' => 'checkbox',
						'choices' => array(
							'isPublisher' => 'Publisher / Affiliate',
							'isAdvertiser' => 'Advertiser',
						),
						'choice_append' => '<br/>',
						'value' => array(
							'isPublisher' => !empty( $company->isPublisher ) ? true : false,
							'isAdvertiser' => !empty( $company->isAdvertiser ) ? true : false,
						),
					),
					array(
						'id' => 'accountManager',
						'label' => 'Account Manager',
						'type' => 'select',
						'placeholder' => 'Select an account manager',
						'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true, $company->accountManager ),
						'value' => $company->accountManager,
					),
					array(
						'id' => 'accountOpener',
						'label' => 'Account Opener',
						'type' => 'select',
						'placeholder' => 'Select an account opener',
						'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true, $company->accountOpener ),
						'value' => $company->accountOpener,
					),
					array(
						'id' => 'salesperson',
						'label' => 'Sales Person',
						'type' => 'select',
						'placeholder' => 'Select a sales person',
						'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true, $company->salesperson ),
						'value' => $company->salesperson,
					),
					array(
						'id' => 'note',
						'label' => 'Campaign Overview',
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
						'id' => 'verticals',
						'label' => 'Verticals',
						'type' => 'select',
						'multiple' => true,
						'placeholder' => false,
						'choices' => $verticals,
						'choice_append' => '<br/>',
						'value' => $set_verticals,
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
						'id' => 'returns_copy',
						'label' => '',
						'type' => 'checkbox',
						'choices' => array(
							'1' => 'Copy info from Main Contact',
						),
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
						'id' => 'acct_copy',
						'label' => '',
						'type' => 'checkbox',
						'choices' => array(
							'1' => 'Copy info from Main Contact',
						),
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
						'id' => 'tech_copy',
						'label' => '',
						'type' => 'checkbox',
						'choices' => array(
							'1' => 'Copy info from Main Contact',
						),
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

				?>
				<script type="text/javascript">
					$('input[name="returns_copy"]').click(function (event) {
						$('#returns_name').val($('#main_name').val());
						$('#returns_phone').val($('#main_phone').val());
						$('#returns_email').val($('#main_email').val());
					});
					$('input[name="acct_copy"]').click(function (event) {
						$('#acct_name').val($('#main_name').val());
						$('#acct_phone').val($('#main_phone').val());
						$('#acct_email').val($('#main_email').val());
					});
					$('input[name="tech_copy"]').click(function (event) {
						$('#tech_name').val($('#main_name').val());
						$('#tech_phone').val($('#main_phone').val());
						$('#tech_email').val($('#main_email').val());
					});
					$('#country').on('change', function (event) {
						var country = $(this).val();
						if (country != '236') {
							$('#state').replaceWith('<input class="form-control" id="state" name="state" type="text" value="" />');
						}
					});
				</script>
				<?php

			}
			break;

		case "dialog_companynotes":
			$companyId = !empty( $_REQUEST['companyId'] ) ? $_REQUEST['companyId'] : '';
			$company = $leads->getCompany( $companyId );

			if( empty( $company ) ) {
				?>
				<p>There is no company that exists by that ID.</p>
				<?php
				break;

			}

			$fields = array(
				array(
					'id' => 'note',
					'label' => 'Add a Note',
					'type' => 'textarea',
				),
				array(
					'id' => 'companyId',
					'type' => 'hidden',
					'value' => $company->idCompany,
				),
				array(
					'id' => 'a',
					'type' => 'hidden',
					'value' => 'addNewNote',
				),
			);

			Display::displayForm( 'note_company', $fields );

			$notes = $leads->getCompanyNotes( $company->idCompany );
			if( empty( $notes ) || !is_array( $notes ) ) {
				print '<p>There are no notes on file for this company.</p>' . PHP_EOL;
			} else {
				foreach( $notes as $note ) {
					printf( '<hr/><p>On <strong>%s</strong> at %s, <strong>%s</strong> wrote:<br/>%s</p>' . PHP_EOL,

						date( 'D, M jS, Y', strtotime( $note->timestamp ) ),
						date( 'g:ia', strtotime( $note->timestamp ) ),
						htmlentities( $note->fullName ),
						nl2br( htmlentities( $note->note ) )
					);
				}
			}

        break;
	}
	exit;
}

$title = 'Company Manager';
include( INCLUDES . "c_header.php" );
?>
<body>

<?php include( INCLUDES . 'c_nav.php' ); ?>

<div class="container-fluid">

	<h2>Companies</h2>

	<p>
		<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#newcompany">Add a new company</button>
		<a class="btn btn-primary" href="/leadadmin/companies.php?searchStatus=active">Reset All Filters</a>
	</p>

	<?php

	$divisions = $leads->getDivisions();
	$verticals = array();
	foreach( $divisions as $key => $val ) {
		$db_verticals = $leads->getDivisionVerticals( $key );
		$verticals[$val] = $db_verticals;
	}

	$fields = array(
		array(
			'id' => 'html_start',
			'type' => '_html',
			'value' => '<div class="row"><div class="col-md-4">',
		),
		array(
			'id' => 'searchText',
			'label' => 'Text Search',
			'type' => 'text',
			'value' => $_REQUEST['searchText'] ?? '',
		),
		array(
			'id' => 'searchAcountManager',
			'label' => 'Account Manager',
			'type' => 'select',
			'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
			'value' => $_REQUEST['searchAccountManager'] ?? '',
		),
		array(
			'id' => 'searchDivisions',
			'label' => 'Division(s)',
			'choices' => $divisions,
			'choice_append' => '<br/>',
			'type' => 'select',
			'multiple' => true,
			'placeholder' => false,
			'value' => !empty( $_REQUEST['searchDivisions'] ) && is_array( $_REQUEST['searchDivisions'] ) ? array_combine( $_REQUEST['searchDivisions'], $_REQUEST['searchDivisions'] ) : array(),
		),
		array(
			'id' => 'html_start',
			'type' => '_html',
			'value' => '</div><div class="col-md-4">',
		),
		array(
			'id' => 'searchStatus',
			'label' => 'Status',
			'type' => 'select',
			'choices' => array(
				'active' => 'Active companies',
				'hidden' => 'Hidden companies',
				'retired' => 'Retired companies',
			),
			'value' => $_REQUEST['searchStatus'] ?? '',
		),
		array(
			'id' => 'searchAccountOpener',
			'label' => 'Account Opener',
			'type' => 'select',
			'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
			'value' => $_REQUEST['searchAccountOpener'] ?? '',
		),
		array(
			'id' => 'searchVerticals',
			'label' => 'Verticals',
			'type' => 'select',
			'multiple' => true,
			'placeholder' => false,
			'choices' => $verticals,
			'choice_append' => '<br/>',
			'value' => !empty( $_REQUEST['searchVerticals'] ) && is_array( $_REQUEST['searchVerticals'] ) ? array_combine( $_REQUEST['searchVerticals'], $_REQUEST['searchVerticals'] ) : array(),
		),
		array(
			'id' => 'html_start',
			'type' => '_html',
			'value' => '</div><div class="col-md-4">',
		),
		array(
			'id' => 'searchCompanyType',
			'label' => 'Company Type',
			'type' => 'select',
			'choices' => array(
				'isPublisher' => 'Publisher / Affiliate',
				'isAdvertiser' => 'Advertiser',
			),
			'value' => $_REQUEST['searchCompanyType'] ?? '',
		),
		array(
			'id' => 'searchSalesperson',
			'label' => 'Sales Person',
			'type' => 'select',
			'choices' => $leads->getStaffUsers( \PDO::FETCH_KEY_PAIR, true ),
			'value' => $_REQUEST['searchSalesperson'] ?? '',
		),
		array(
			'id' => 'submit',
			'label' => 'Search',
			'type' => 'submit',
			'class' => 'btn btn-primary',
		),
		array(
			'id' => 'html_start',
			'type' => '_html',
			'value' => '</div></div>',
		),
	);

	Display::displayForm( 'company_search', $fields, '' );

	?>

	<?php
	$companies = $leads->searchCompanies( array(
		'status' => $_REQUEST['searchStatus'] ?? null,
		'text' => $_REQUEST['searchText'] ?? null,
		'salesperson' => $_REQUEST['searchSalesperson'] ?? null,
		'accountManager' => $_REQUEST['searchAccountManager'] ?? null,
		'accountOpener' => $_REQUEST['searchAccountOpener'] ?? null,
		'companyType' => $_REQUEST['searchCompanyType'] ?? null,
		'divisions' => $_REQUEST['searchDivisions'] ?? null,
		'verticals' => $_REQUEST['searchVerticals'] ?? null,
	) );
	if( empty( $companies ) ) {

		print '<p>No companies exist in the database with this search criteria.</p>';

	} else {
		?>

		<table class="table table-bordered table-condensed table-striped">
			<thead>
			<tr class="bgGray header">
				<th>ID</th>
				<th>Company Name</th>
				<th class="hidden-xs">Campaign Overview</th>
				<th>Options</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach( $companies as $company ) {
				?>
				<tr>
					<td><?php echo $company->idCompany; ?></td>
					<td><?php echo Display::escHtml( $company->name ); ?></td>
					<td class="hidden-xs"><?php echo Display::escHtml( $company->note ); ?></td>
					<td class="text-center">
						<div class="btn-group">
							<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editcompany" data-company-id="<?php echo $company->idCompany; ?>">Edit</button>
							<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<span class="caret"></span>
								<span class="sr-only">Toggle Dropdown</span>
							</button>
							<ul class="dropdown-menu">
								<li><a href="#" data-toggle="modal" data-backdrop="static" data-target="#companynotes" data-company-id="<?php echo $company->idCompany; ?>">Notes</a></li>
							</ul>
						</div>
					</td>
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

<div class="modal fade" id="companynotes" tabindex="-1" role="dialog" aria-labelledby="companynotes_title">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="companynotes_title">Company Notes</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-companynotes" type="button" class="btn btn-primary">Add A New Note</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	var refreshTimeout;
	$(document).ready(function () {
		refreshTimeout = setTimeout(function () {
			location.reload();
		}, 120000);
	});

	$('#modal-save-newcompany').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "companies.php",
			type: "POST",
			async: true,
			data: $("#new_company").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#newcompany').on('show.bs.modal', function (e) {
		var modal = $(this);

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'companies.php',
			data: {
				'd': 'dialog_newcompany'
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-editcompany').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "companies.php",
			type: "POST",
			async: true,
			data: $("#edit_company").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#editcompany').on('show.bs.modal', function (e) {
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
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#newcompany, #editcompany').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});

	$('#status-select select').change(function (e) {
		e.preventDefault();
		$('#status-select').submit();
	});

	$('#companynotes').on('show.bs.modal', function (e) {
		var modal = $(this);
		var companyId = $(e.relatedTarget).data('company-id');

		if(refreshTimeout) {
			clearTimeout(refreshTimeout);
		}

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'companies.php',
			data: {
				'd': 'dialog_companynotes',
				'companyId': companyId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-companynotes').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "dashboard.php",
			type: "POST",
			async: true,
			data: $("#note_company").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#companynotes').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
		refreshTimeout = setTimeout(function () {
			location.reload();
		}, 120000);
	});
</script>

<style>
td.hidden-xs {
	word-break: break-word;
}
.btn-group {
	min-width: 60px;
}
.dropdown-menu {
	min-width: 80px;
}
</style>

</body>
</html>
