<?php

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

$mysqlErrorSource = 'Manager - Listcodes';
$forceMysqlLogFile = SITE_ROOT."error".FD."log_listcodes"; 
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");

function addListcode( $companyId, $description ) {
	dbCon();
	$query = "INSERT ";
	$query.= "INTO `" . DATABASE_NAME . "`.`listcode` (idCompany,description) ";
    $query.= "VALUES(";
    $query.=   "'" . intval( $companyId ) . "'";
    $query.=   ",'" . $GLOBALS['dbconnx']->escape_string( $description ) . "'";
    $query.= ")";

	$result = dbQry( $query, 'Adding listcode', true );
	dbDcon();

	if( $result === false ) { return false; }
	return true;
}

function getListcode( $listcodeId ) {
	dbCon();
	$query = "SELECT idCompany,idListcode,description ";
	$query.= "FROM `" . DATABASE_NAME . "`.`listcode` ";
    $query.= "WHERE idListcode = '" . intval( $listcodeId ) . "'";

	$result = dbQry( $query, 'Getting listcodes', true );
	dbDcon();

	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
    return $result->fetch_object();
}

function updateListcode( $listcodeId, $companyId, $description ) {
	dbCon();
	$query = "UPDATE ";
	$query.= "`" . DATABASE_NAME . "`.`listcode` ";
    $query.= "SET ";
    $query.=   "idCompany = '" . intval( $companyId ) . "'";
    $query.=   ", description = '" . $GLOBALS['dbconnx']->escape_string( $description ) . "'";
    $query.= "WHERE idListcode = '" . intval( $listcodeId ) . "'";

	$result = dbQry( $query, 'Updating listcode', true );
	dbDcon();

	if( $result === false ) { return false; }
	return true;
}

function getListcodes( $companyId ) {
	dbCon();
	$query = "SELECT idListcode,description ";
	$query.= "FROM `" . DATABASE_NAME . "`.`listcode` ";
    $query.= "WHERE idCompany = '" . intval( $companyId ) . "' ";
	$query.= "ORDER BY idListcode";

	$result = dbQry( $query, 'Getting listcodes', true );
	dbDcon();

	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
	$values = array();
	while( $row = $result->fetch_object() ){
		$values[] = $row;
	}
	return $values;
}

function getListcodeURLs( $listcodeId ) {
	dbCon();
	$query = "SELECT idUrl,url ";
	$query.= "FROM `" . DATABASE_NAME . "`.`listcode_url` ";
    $query.= "WHERE idListcode = '" . intval( $listcodeId ) . "' ";
	$query.= "ORDER BY url";

	$result = dbQry( $query, 'Getting listcode URLs', true );
	dbDcon();

	if( $result === false ) { return false; }
	if( $result->num_rows == 0 ) { return 0; }
	$values = array();
	while( $row = $result->fetch_object() ){
		$values[] = $row;
	}
	return $values;
}

function addListcodeURL( $listcodeId, $url ) {
	dbCon();
	$query = "REPLACE ";
	$query.= "INTO `" . DATABASE_NAME . "`.`listcode_url` (idListcode,url) ";
    $query.= "VALUES(";
    $query.=   "'" . intval( $listcodeId ) . "'";
    $query.=   ",'" . $GLOBALS['dbconnx']->escape_string( $url ) . "'";
    $query.= ")";

	$result = dbQry( $query, 'Adding listcode URL', true );
	dbDcon();

	if( $result === false ) { return false; }
	return true;
}

function removeListcodeURL( $urlId ) {
	dbCon();
	$query = "DELETE ";
	$query.= "FROM `" . DATABASE_NAME . "`.`listcode_url` ";
    $query.= "WHERE idUrl = '" . intval( $urlId ) . "'";

	$result = dbQry( $query, 'Removing listcode URL', true );
	dbDcon();

	if( $result === false ) { return false; }
	return true;
}

if(isset($_REQUEST['a'])){ 
	$result = array(
		'status' => 0
		, 'error' => 'Action does not exist.'
	);
	switch($_REQUEST['a']){

		case 'manageListcode':
			$c = true;
			$result['error'] = 'Failed when attempting to add listcode.';
			$action = $_REQUEST['action'];
            
			if( 'new' == $action ){

				$queryResult = addListcode( $_REQUEST['idCompany'], $_REQUEST['description'] );

			} else {

				$queryResult = updateListcode( $_REQUEST['idListcode'], $_REQUEST['idCompany'], $_REQUEST['description'] );
			}

			if($c){
				$result['status'] = 1;
			}

			break;
        
		case 'removeUrl':
			$c = true;
			$result['error'] = 'Failed when attempting to remove URL.';
			$queryResult = removeListcodeURL( $_REQUEST['idUrl'] );
			if( !$queryResult ) {
				$c = false;
			}

			if($c){
				$result['status'] = 1;
			}
			break;

		case 'saveUrls':
			$c = true;
			$result['error'] = 'Failed when attempting to add URL.';

			if( empty ( $_REQUEST['idListcode']) || empty( $_REQUEST['urls'] ) ) {
				$c = false;
				$result['error'] = 'No values provided';
			}

			if( $c ) {
				$urls = explode( "\n", $_REQUEST['urls'] );

				// First pass to validate the data
				foreach( $urls as $url ) {
					if( strlen( trim( $url ) ) > 0 && !filter_var( 'http://' . trim( $url ), FILTER_VALIDATE_URL, FILTER_FLAG_HOST_REQUIRED ) ) {
						$c = false;
						$result['error'] = "Invalid URL: {$url}";
						break;
					}
				}

				if( $c ) {
					// If validation passed, try to add the data to the DB
					foreach( $urls as $url ) {
						if( strlen( trim( $url ) ) > 0 ) {
							$queryResult = addListcodeURL( $_REQUEST['idListcode'], trim ( $url ) );
							if( !$queryResult ) {
								$c = false;
							}
						}
					}
				}
			}

			if( $c ) {
				$result['status'] = 1;
			}
			break;
	}
	echo json_encode($result);
	exit;
}

if(isset($_REQUEST['d'])){ 
	switch($_REQUEST['d']){
		case 'errorCount':		
			$errorCount = getErrorCount();
			if($errorCount === false){ echo "X"; } else { echo $errorCount; }
		break;
		case 'errorList':
			$errorList = getErrors();
?>
<div class='fr'>
	<a href='#' class='nonLink' onclick='closeContent("errorList");' >Close [X]</a>
</div>
<?php
if($errorList === false){ echo "Error fetching errors list."; } 
elseif($errorList == 0){ echo "No errors listed for today."; } 
else { 
	foreach($errorList as $error){ 
?>
<p>(<?php echo $error->stamp; ?>) [<?php echo $error->origination; ?>] : <?php echo $error->description; ?></p>
<?php
	}
}
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
<table class="standard bgWhite">
	<thead>
		<tr class="bgGray header">
			<td class="fTI_companyName" colspan="3"><p>Company</p></td>
			<td class="fTI_options"><p>Actions</p></td>
		</tr>
	</thead>
<?php
                foreach($companies as $company){
?>
		<tr class="bgGray">
            
			<td colspan="3"><?php echo $company->name; ?></td>
			<td><p>
                <a href='#' class='nonLink'
                    id='link_companyListcodes_<?php echo $company->idCompany; ?>'
                    onclick="toggleHidden('companyListcodes', {'sub':<?php echo $company->idCompany; ?>, 'hiddenText':'Show Listcodes', 'shownText':'Close' });"
                >Show Listcodes</a></p></td>
		</tr>
	<tbody id="companyListcodes_<?php echo $company->idCompany; ?>" class="hidden">
<?php
		$listcodes = getListcodes( $company->idCompany );
		if( empty( $listcodes ) ) {
?>
		<tr>
			<td colspan="3"><p>No listcodes setup</p></td>
			<td><p><a href='#' class='nonLink' onclick="display('dialog_newlistcode', { 'idCompany': <?php echo $company->idCompany; ?> });">Add New Listcode</a></p></td>
		</tr>
<?php
		} else {
?>
		<tr class="header">
			<td>Listcode</td>
			<td>Description</td>
			<td>URLs</td>
			<td>Options</td>
		</tr>
<?php
			foreach( $listcodes as $listcode ) {
?>
		<tr>
			<td><?php echo $company->idCompany . $listcode->idListcode; ?></td>
			<td><?php echo $listcode->description; ?></td>
			<td>
<?php
				$urls = getListcodeURLs( $listcode->idListcode );
				if( is_array( $urls ) ) {
					foreach( $urls as $url ) {
						print "<p id=\"url_{$url->idUrl}\">{$url->url} <a href=\"#\" class=\"nonLink\" onclick=\"removeUrl({$url->idUrl}, '')\">[X]</a></p>\n";
					}
				}
?>
				<p><textarea id="add_urls_<?php echo $listcode->idListcode;?>" style="width:95%; height:75px;"></textarea><button onclick="saveUrls(<?php echo $listcode->idListcode;?>, <?php echo $company->idCompany;?>)">Add new URLs</button></p>
			</td>
			<td>
				<p><a href="#" class="nonLink" onclick="display('dialog_editlistcode', { 'idListcode': <?php echo $listcode->idListcode; ?> });">Edit</a></p>
			</td>
		</tr>
<?php
			}
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

        
		case 'dialog_editlistcode':
			$idListcode = $_REQUEST['options']['idListcode'];
			$e = 'edit_'.$idListcode.'_';
			$listcode = getListcode($idListcode);
			if($listcode === false){
?>
<p>Database failure - could not fetch requested listcode information.</p>
<?php
				exit;
			} elseif(!is_object($listcode) && $listcode == 0){
?>
<p>Could not fetch requested listcode information - listcode does not exist.</p>
<?php
				exit;
			}
        
		case 'dialog_newlistcode':
			if(!isset($e)){
				$e = 'new_';
			}

            $props = array( 'idListcode', 'idCompany', 'description' );
            foreach($props as $prop){
                if(isset($listcode)){
                    ${"listcode_".$prop} = $listcode->$prop;
                }elseif(isset($_REQUEST['options'][$prop])){
                    ${"listcode_".$prop} = $_REQUEST['options'][$prop];
                }else {
                    ${"listcode_".$prop} = '';
                }
            }
            $companies = getCompanies();
?>
<table class="listcodeTable" border="1" cellpadding="0" cellspacing="0">
    <tr>
        <td><p>Company</p></td>
        <td>
            <p>
                <input type="hidden" name="<?php echo $e; ?>listcode_idListcode"
                    id="<?php echo $e; ?>listcode_idListcode"
                    value="<?php echo $listcode_idListcode; ?>"
                />
                <?php if($companies === false){ ?>
                	Database failure - could not fetch company list
                <?php } elseif(!is_object($companies) && $companies == 0){ ?>
                	There are no companies in the database. Please create a company before creating a listcode.
                <?php } else { ?>
                	<select name="<?php echo $e; ?>listcode_idCompany" id="<?php echo $e; ?>listcode_idCompany">
                <?php foreach($companies as $company){ ?>
                    <option value="<?php echo $company->idCompany; ?>"
                    	<?php if($company->idCompany == $listcode_idCompany){?>selected="selected"<?php } ?>><?php echo $company->name; ?></option>
                <?php } ?>
                </select>
                <?php } ?>
            </p>
        </td>
    </tr>
    <tr>
        <td><p>Description</p></td>
        <td>
            <p>
                <input type="text" name="<?php echo $e; ?>listcode_description"
                    id="<?php echo $e; ?>listcode_description"
                    value="<?php echo $listcode_description; ?>"
                class="long" />
            </p>
        </td>
    </tr>
    <tr>
        <td colspan='2'>
            <p class='aRight'>
        <?php if(isset($idListcode) && $e == 'edit_'.$idListcode.'_'){ ?>
                <input type='button' value='Cancel Changes'
                    onclick='closeContent("dialog_editlistcode");'
                />
                <input type='button' value='Save Changes'
                    onclick='manageListcode("update", <?php echo $listcode_idListcode; ?>);'
                />
        <?php } else { ?>
                <input type='button' value='Cancel'
                    onclick='closeContent("dialog_newlistcode");'
                />
                <input type='button' value='Add New Listcode'
                    onclick='manageListcode("new");'
                />
        <?php } ?>
            </p>
        </td>
    </tr>
</table>
<?php
		break;

	}
	exit;
}

$title = 'Listcode Manager';
include(INCLUDES."c_header.php");
?>
<body>
<script type="text/javascript">
$(document).ready(function(){ 
	display('companyList');
});

function manageListcode(action, idListcode){
        
	if(action == "new"){ e = "#new_listcode_"; c = 'new'; } else { e = "#edit_"+idListcode+"_listcode_"; c = 'edit'; }
	idListcode = $(e+'idListcode').val();
	description = $(e+'description').val();
	idCompany = $(e+'idCompany').val();

	var response = $.ajax({
		url: "mgr_listcodes.php",
		type: "POST",
		async: true,
		data: ({
			"a" : "manageListcode"
			, "action" : action
			, "idListcode": idListcode
			, "description":description
			, "idCompany":idCompany
		})
                
	}).done(function(responseText){
                        
		var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
		if(result===null) {
			alert("JSON Failed: "+responseText);
		}
		if(result.status == 1){
			if(c == 'new'){
				alert("Successfully created new listcode.");
				closeContent('dialog_newlistcode');
			} else {
				alert("Successfully saved updated listcode settings.");
				closeContent('dialog_editlistcode');
			}

			display(
				'companyList'
				, {
					'callbackParams': {
						'idCompany': idCompany
					}
				}
				, true
				, function(o) {
					toggleHidden(
						'companyListcodes'
						, {'sub':o.idCompany, 'hiddenText':'Show Listcodes', 'shownText':'Close' }
					);
				}
			);

		} else {
			alert(result.error);
		}
	});
}

function removeUrl(idUrl, options){
        var response = $.ajax({
            url: "mgr_listcodes.php",
            type: "POST",
            async: true,
            data: ({
                "a" : "removeUrl"
                , "idUrl": idUrl
            })
        }).done(function(responseText){
            var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
            if(result===null) {
                alert("JSON Failed: "+responseText);
            } else {
                if(result.status == 1){
					toggleHidden('url_' + idUrl, '');
                } else {
                    alert(result.error);
                }
            }
        });
}

function saveUrls(idListcode, idCompany){

		urls = $('#add_urls_' + idListcode).val();

        var response = $.ajax({
            url: "mgr_listcodes.php",
            type: "POST",
            async: true,
            data: ({
                "a" : "saveUrls"
				, "idListcode": idListcode
                , "urls": urls
            })
        }).done(function(responseText){
            var result = jQuery.parseJSON(responseText.charAt(0) != "{" ? null : responseText);
            if(result===null) {
                alert("JSON Failed: "+responseText);
            } else {
                if(result.status == 1){
                    display(
                        'companyList'
                        , {
                            'callbackParams': {
                                'idCompany': idCompany
                            }
                        }
                        , true
                        , function(o){
                            toggleHidden(
                                'companyListcodes'
                                , {'sub':o.idCompany, 'hiddenText':'Show Listcodes', 'shownText':'Close' }
                            );
                        }
					);

                } else {
                    alert(result.error);
                }
            }
        });
}


</script>
<div class='mainContainer'>
	<?php include(INCLUDES.'c_nav.php'); ?>
	<div style='margin: auto;'>
        <div id='controls'>
            <a href='#' class='nonLink' onclick="display('dialog_newlistcode');">Add New Listcode</a>
        </div>
        <div id='dialogs'>
            <div id='dialog_newlistcode' style='display:none;'></div>
            <div id='dialog_editlistcode' style='display:none;'></div>
        </div>
		<div class='fl' style='width: 100%;'>
			<div id='companyList'></div>
		</div>
		<div class='clr'></div>
	</div>
</div>
</body>
</html>
