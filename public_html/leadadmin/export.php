<?php

include("../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_ADMIN);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

require_once(INCLUDES . 'display.php');

include(INCLUDES . "f_site.php");

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0,
        'error' => 'Action does not exist.',
    );
    switch ($_REQUEST['a']) {
        case 'exportIncomingData':
            $c = true;
            $result['error'] = 'Failed when trying to export data.';

            if ($c && !LeadsSession::isValid(LEADS_SESSION_LEVEL_ADMIN)) {
                $c = false;
                $result['error'] = 'Sorry, you do not have permission to export data.';
            }

            if ($c) {
                if (empty($_REQUEST['columns'])) {
                    $c = false;
                    $result['error'] = 'Error - you need to select data columns to export.';
                }
            }

            if ($c) {
                $jobId = $leads->addJob('export-incoming', 0, serialize($_REQUEST), '', 0);
                if (null === $jobId) {
                    $c = false;
                    $result['error'] = 'Error adding this job to the database.';
                } else {
                    $leads->auditLog('FEEDINC:EXPORT', $jobId);
                    $result['status'] = 1;
                    $result['error'] = 'Export job #' . $jobId . ' submitted successfully. You will be notified by email when your download is ready.';
                }
            }

            break;

        case 'exportOutgoingData':
            $c = true;
            $result['error'] = 'Failed when trying to export data.';

            if ($c && !LeadsSession::isValid(LEADS_SESSION_LEVEL_ADMIN)) {
                $c = false;
                $result['error'] = 'Sorry, you do not have permission to export data.';
            }

            if ($c) {
                $jobId = $leads->addJob('export-outgoing', 0, serialize($_REQUEST), '', 0);
                if (null === $jobId) {
                    $c = false;
                    $result['error'] = 'Error adding this job to the database.';
                } else {
                    $leads->auditLog('FEEDOUT:EXPORT', $jobId);
                    $result['status'] = 1;
                    $result['error'] = 'Export job #' . $jobId . ' submitted successfully. You will be notified by email when your download is ready.';
                }
            }

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

        case 'dialog_export_incoming':

            if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_ADMIN)) {
                die('Sorry, you do not have permission to export data.');
            }
            ?>

            <form id="form-export-incoming">
                <input type="hidden" name="a" value="exportIncomingData"/>
                <table class="table table-bordered table-condensed table-striped">
                    <tr>
                        <td colspan='2'><p class='aCenter'>Export Settings</p></td>
                    </tr>
                    <tr>
                        <td>
                            Columns
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary" id="exp-incoming-check-all" style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Check All</button>
                            <button type="button" class="btn btn-primary" id="exp-incoming-uncheck-all" style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Uncheck All</button>
                            <br>
                            <?php $fields = $leads->getInboundFields(); ?>
                            <?php foreach ($fields as $f) { ?>
                                <label class="checkbox-label">
                                    <input class="export-incoming-check" type='checkbox' name='columns[]' value='<?php echo Display::escHtml($f->fieldName); ?>'/>&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <script>
                        $('#exp-incoming-check-all').click(function () {
                            $('.export-incoming-check').prop('checked', true);
                        });
                        $('#exp-incoming-uncheck-all').click(function () {
                            $('.export-incoming-check').prop('checked', false);
                        });
                    </script>
                    <tr>
                        <td>
                            Period
                        </td>
                        <td>
                            <p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
                            <p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date("Y-m-d"); ?>'/>
                                to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'/></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            URLs
                        </td>
                        <td>
                            <p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
                            <p><a href='#' class='nonLink' onclick='element("export_incoming_urls", "urlField", {} );'>Add URL</a></p>
                            <div>
                                <div id='export_incoming_urls'></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Email domains
                        </td>
                        <td>
                            <p>Email domains to limit the selection by. Leave blank to select all records regardless of email address. Do not include the @ symbol.</p>
                            <p><a href='#' class='nonLink'
                                  onclick='element("export_incoming_emails", "emailField", {} );'>Add email domain</a></p>
                            <div>
                                <div id='export_incoming_emails'></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Limit
                        </td>
                        <td>
                            <p>Set a limit on the number of records that are returned. Leave blank to return ALL records.</p>
                            <p><input type="text" name="limit" value=""/></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Rejects
                        </td>
                        <td>
                            <p><input type="checkbox" name="includeRejects" value="1"/> Include rejected records in the export.</p>
                        </td>
                    </tr>
                </table>
            </form>
            <?php
            break;

        case 'dialog_export_outgoing':

            if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_ADMIN)) {
                die('Sorry, you do not have permission to export data.');
            }
            ?>
            <form id="form-export-outgoing">
                <input type="hidden" name="a" value="exportOutgoingData"/>
                <table class="table table-bordered table-condensed table-striped">
                    <tr>
                        <td colspan='2'><p class='aCenter'>Export Settings</p></td>
                    </tr>
                    <tr>
                        <td>
                            Columns
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary" id="exp-outgoing-check-all" style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Check All</button>
                            <button type="button" class="btn btn-primary" id="exp-outgoing-uncheck-all" style="margin-bottom: 10px; padding: 3px 8px; background: #281840;">Uncheck All</button>
                            <br>
                            <?php $fields = $leads->getOutboundExportableFields(); ?>
                            <?php foreach ($fields as $f) { ?>
                                <label class="checkbox-label">
                                    <input class="export-outgoing-check" type='checkbox' name='columns[]' value='<?php echo Display::escHtml($f->fieldName); ?>'/>&nbsp;<?php echo Display::escHtml($f->fieldName); ?>
                                </label>
                            <?php } ?>
                        </td>
                    </tr>
                    <script>
                        $('#exp-outgoing-check-all').click(function () {
                            $('.export-outgoing-check').prop('checked', true);
                        });
                        $('#exp-outgoing-uncheck-all').click(function () {
                            $('.export-outgoing-check').prop('checked', false);
                        });
                    </script>
                    <tr>
                        <td>
                            Period
                        </td>
                        <td>
                            <p>Period goes from midnight of the first date to midnight of the second date. Leave blank to select from all time records. (This could take a long time.)</p>
                            <p><input type='text' name='dateStart' class='dateSelector' value='<?php echo date("Y-m-d"); ?>'/>
                                to <input type='text' name='dateEnd' class='dateSelector' value='<?php echo date("Y-m-d", strtotime('Tomorrow')); ?>'/></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            URLs
                        </td>
                        <td>
                            <p>URLs to limit the selection by. Leave blank to select all records regardless of URL.</p>
                            <p><a href='#' class='nonLink' onclick='element("export_outgoing_urls", "urlField", {} );'>Add URL</a></p>
                            <div>
                                <div id='export_outgoing_urls'></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Email domains
                        </td>
                        <td>
                            <p>Email domains to limit the selection by. Leave blank to select all records regardless of email address. Do not include the @ symbol.</p>
                            <p><a href='#' class='nonLink' onclick='element("export_outgoing_emails", "emailField", {} );'>Add email domain</a></p>
                            <div>
                                <div id='export_outgoing_emails'></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Limit
                        </td>
                        <td>
                            <p>Set a limit on the number of records that are returned. Leave blank to return ALL records.</p>
                            <p><input type="text" name="limit" value=""/></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            Rejects
                        </td>
                        <td>
                            <p><input type="checkbox" name="includeRejects" value="1"/> Include rejected records in the export.</p>
                        </td>
                    </tr>
                </table>
            </form>
            <?php
            break;

        case 'urlField':
            ?>
            <div>
                URL: <input type='text' name='urlList[]' value=''/>
                <a href='#' class='nonLink' onclick='$(this).parent().remove();'>[X]</a>
            </div>
            <?php
            break;

        case 'emailField':
            ?>
            <div>
                Email domain: <input type='text' name='emailList[]' value=''/> (do not include @ symbol)
                <a href='#' class='nonLink' onclick='$(this).parent().remove();'>[X]</a>
            </div>
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

$title = 'Data Export';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

    <h2>Data Export</h2>

    <p>Use this page to export records across ALL feeds for a given date range, either on the incoming side or the outgoing side.</p>

    <p><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-export-incoming">Export Incoming Data</a></p>

    <p><a href="#" data-toggle="modal" data-backdrop="static" data-target="#modal-export-outgoing">Export Outgoing Data</a></p>

</div>

<div class="modal fade" id="modal-export-incoming" tabindex="-1" role="dialog" aria-labelledby="modal-export_incoming_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-export_incoming_title">Export Incoming Data</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-export-incoming" type="button" class="btn btn-primary">Export Data</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-export-outgoing" tabindex="-1" role="dialog" aria-labelledby="modal-export_outgoing_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-export_outgoing_title">Export Outgoing Data</h4>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="modal-save-export-outgoing" type="button" class="btn btn-primary">Export Data</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">

    $('#modal-export-incoming').on('show.bs.modal', function (e) {
        var modal = $(this);

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'export.php',
            data: {
                'd': 'dialog_export_incoming',
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-export-incoming').click(function (event) {
        event.preventDefault();

        var response = $.ajax({
            url: "export.php",
            type: "POST",
            async: true,
            data: $("#form-export-incoming").serialize()
        }).done(function (result) {
            alert(result.error);
            if (result.status == 1) {
                $('#modal-export-incoming').modal('hide');
            }
        });
    });

    $('#modal-export-outgoing').on('show.bs.modal', function (e) {
        var modal = $(this);

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'export.php',
            data: {
                'd': 'dialog_export_outgoing',
            },
            success: function (data) {
                modal.find('.modal-body').html(data);
            }
        });
    });

    $('#modal-save-export-outgoing').click(function (event) {
        event.preventDefault();

        $.ajax({
            cache: false,
            type: 'POST',
            url: 'export.php',
            data: $("#form-export-outgoing").serialize(),
            success: function (result) {
                if (result.status == 1) {
                    $('#modal-export-outgoing').modal('hide');
                } else {
                    alert(result.error);
                }
            }
        });
    });

</script>

</body>
</html>