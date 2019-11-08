<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0,
        'error' => 'Action does not exist.',
    );

    switch ($_REQUEST['a']) {
        case "getOutboundCompanies":
            $result['status'] = 1;
            $result['error'] = '';
            $result['companies'] = !empty($_REQUEST['idCompany']) ? $leads->getOutboundCompanyMappingsByInboundCompany($_REQUEST['idCompany']) : [];
            break;
    }

    echo json_encode($result);
    exit;
}

if (isset($_REQUEST['d'])) {
    switch ($_REQUEST['d']) {
        case 'errorCount':
            Display::errorCount();
            break;

        case 'errorList':
            Display::errorList();
            break;
    }
    exit;
}

$title = 'URL Mapping Report';
include(INCLUDES . "c_header.php");

?>
<body>
<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

    <h2>URL Mapping Report</h2>

    <!-- ADD FILTER (DZ) -->
    <?php $idCompany_in = isset($_REQUEST['idCompany_in']) ? $_REQUEST['idCompany_in'] : '';
    $idCompany_out = isset($_REQUEST['idCompany_out']) ? $_REQUEST['idCompany_out'] : '';

    $inCompanies = $leads->getInboundCompaniesMapping('active');
    $outCompanies = !empty($idCompany_in) ? $leads->getOutboundCompanyMappingsByInboundCompany($idCompany_in) : [] ?>

    <?php if ($inCompanies === false || $outCompanies === false) { ?>
        Database failure - could not fetch company list
    <?php } elseif ((!is_object($inCompanies) && $inCompanies == 0) || (!is_object($outCompanies) && $outCompanies == 0)) { ?>
        There are no companies in the database. Please create a company before creating a report.
    <?php } else { ?>
        <form id="urlreport" method="GET" action="reports-mapping.php">
            <label for="idCompany_in">Incoming Company:</label> <select name="idCompany_in" id="idCompany_in">
                <option value=""<?php if ($idCompany_in == '') {
                    echo ' selected="selected"';
                } ?>>Select an Incoming Company
                </option>
                <?php foreach ($inCompanies as $company) { ?>
                    <option value='<?php echo $company->idCompany; ?>'
                            <?php if ($company->idCompany == $idCompany_in){
                            ?>selected='selected'<?php } ?>
                    ><?php echo Display::escHtml($company->name); ?></option>
                <?php } ?>
            </select>
            &nbsp; <label for="idCompany_out">Outgoing Company:</label> <select name="idCompany_out" id="idCompany_out">
                <option value=""<?php if ($idCompany_out == '') {
                    echo ' selected="selected"';
                } ?>>Select an Outgoing Company
                </option>
                <?php foreach ($outCompanies as $company) { ?>
                    <option value='<?php echo $company->idCompany; ?>'
                            <?php if ($company->idCompany == $idCompany_out){
                            ?>selected='selected'<?php } ?>
                    ><?php echo Display::escHtml($company->name); ?></option>
                <?php } ?>
            </select>
            <input class="btn btn-primary" type="submit" value="Generate Report">
        </form>
    <?php } ?>

    <?php if (!empty($idCompany_in) || !empty($idCompany_out)) { ?>

        <?php $mappings = $leads->getUrlMappings($idCompany_in, $idCompany_out);
    if ($mappings) {
        print "<table id=\"mapping_report\" class=\"table table-bordered table-condensed table-striped\">\n";
        print "\t<thead>\n";
        print "\t<tr class=\"bgGray\">\n";
        print "\t\t<th>Incoming Company</th>\n";
        print "\t\t<th>Incoming Feed</th>\n";
        print "\t\t<th>Incoming URL</th>\n";
        print "\t\t<th>Outgoing Company</th>\n";
        print "\t\t<th>Outgoing Feed</th>\n";
        print "\t\t<th>Active</th>\n";
        print "\t</tr>\n";
        print "\t</thead>\n";
        print "\t<tbody>\n";
        foreach ($mappings as $mapping) {
            print "\t<tr class=\"bgGray\">\n";
            printf("\t\t<td>%s</td>\n", htmlspecialchars($mapping['inName']));
            printf("\t\t<td>%s</td>\n", htmlspecialchars($mapping['idFeedIn'] . ': ' . $mapping['inDescription']));
            printf("\t\t<td>%s</td>\n", htmlspecialchars($mapping['url']));
            printf("\t\t<td>%s</td>\n", htmlspecialchars($mapping['outName']));
            printf("\t\t<td>%s</td>\n", htmlspecialchars($mapping['idFeedOut'] . ': ' . $mapping['outDescription']));
            if ('1' == $mapping['active']) {
                print "\t\t<td>Y</td>\n";
            } else {
                print "\t\t<td>N</td>\n";
            }
            print "\t</tr>\n";

        }
        print "\t</tbody>\n";
        print "</table>\n";
        ?>

        <script type="text/javascript">
            /*
                var tf = new TableFilter(document.querySelector('#mapping_report'), {
                    base_path: '/leadadmin/libraries/tablefilter/',
                    filters_row_index: 1,
                    sort: true,
                    sort_config: {
                        sort_types:['String','String','String','String','String','String']
                    },
                    remember_grid_values: true,
                    alternate_rows: true,
                    btn_reset: true,
                    btn_reset_text: "Clear",
                    btn_text: " > ",
                    loader: true,
                    loader_text: "Filtering data...",
                    col_0: "select",
                    col_1: "select",
                    col_2: "select",
                    col_3: "select",
                    col_4: "select",
                    col_5: "select",
                    display_all_text: "< Show all >"
                });
                tf.init();
            */
        </script>
    <?php
    } else {
        print "No matches found for the search criteria specified.";
    } ?>

    <?php } else { ?>

        <h4 style="padding-top: 25px;">Choose an incoming company to view this report.</h4>

    <?php } ?>

</div>

<script type="text/javascript">
    $("#idCompany_in").select2({
        placeholder: "Select an incoming company",
        allowClear: true
    });

    $("#idCompany_out").select2({
        placeholder: "Select an outgoing company",
        allowClear: true
    });

    $("#idCompany_in").on('change', function () {
        $.ajax({
            type: "POST",
            url: "/leadadmin/reports-mapping.php",
            data: {
                a: 'getOutboundCompanies',
                idCompany: $("#idCompany_in").val(),
            },
            dataType: "json",
        }).done(function (result) {
            var companyOut = $("#idCompany_out");
            if (companyOut && result.companies) {
                companyOut.empty();
                companyOut.append('<option></option>');
                $.each(result.companies, function (i, obj) {
                    if (result.companies.length === 1) {
                        companyOut.append('<option value="' + obj.idCompany + '" selected="selected">' + obj.name + '</option>');
                    } else {
                        companyOut.append('<option value="' + obj.idCompany + '">' + obj.name + '</option>');
                    }
                });
                companyOut.select2({
                    placeholder: "Select an outgoing company",
                    allowClear: true
                });
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $("#idCompany_out").select2({});
        }); //close $.ajax()
    });
</script>

</body>
</html>
