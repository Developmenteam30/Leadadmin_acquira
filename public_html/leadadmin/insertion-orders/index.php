<?php

include("../../../includes/c_config.php");

require_once(INCLUDES . 'session.php');
require_once(INCLUDES . 'f_site.php');
LeadsSession::requireAccess(LEADS_SESSION_LEVEL_STAFF);

require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

$filterStatus = !empty($_REQUEST['filterStatus']) ? $_REQUEST['filterStatus'] : null;
$filterUserId = !empty($_REQUEST['filterUserId']) ? $_REQUEST['filterUserId'] : null;

require_once(INCLUDES . 'display.php');

if (isset($_REQUEST['a'])) {
    Header('Content-Type: application/json');

    $result = array(
        'status' => 0,
        'error' => 'Action does not exist.',
    );
    switch ($_REQUEST['a']) {
        case "addNewOrder":
            $result['error'] = 'Failed when trying to add a new order';

            $userId = LeadsSession::getUserId();
            if (LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                if (empty($_REQUEST['userId'])) {
                    $result['error'] = 'Please select a salesperson from the list.';
                    break;
                }
                $userId = empty($_REQUEST['userId']) ? null : $_REQUEST['userId'];
            }

            if (empty($_REQUEST['companyId'])) {
                $result['error'] = 'Please select a company.';
                break;
            }

            if (empty($_REQUEST['verticalId'])) {
                $result['error'] = 'Please select the product type.';
                break;
            }

            if (empty($_REQUEST['startDate'])) {
                $result['error'] = 'Please select the start date.';
                break;
            } else {
                try {
                    $startDate = new DateTime($_REQUEST['startDate']);
                } catch (Exception $e) {
                    $result['error'] = 'Please enter a valid start date.';
                    break;
                }
            }

            if (empty($_REQUEST['endDate'])) {
                $result['error'] = 'Please select the end date.';
                break;
            } else {
                try {
                    $endDate = new DateTime($_REQUEST['endDate']);
                } catch (Exception $e) {
                    $result['error'] = 'Please enter a valid end date.';
                    break;
                }
            }

            if (empty($_REQUEST['paymentTerms'])) {
                $result['error'] = 'Please select the payment terms.';
                break;
            }

            if (empty($_REQUEST['costPerLead'])) {
                $result['error'] = 'Please enter the cost per lead.';
                break;
            }

            if (empty($_REQUEST['costPerLeadUOM'])) {
                $result['error'] = 'Please enter the cost per lead UOM.';
                break;
            }

            if (empty($_REQUEST['deliveryMethod'])) {
                $result['error'] = 'Please enter the delivery method.';
                break;
            }

            if (empty($_REQUEST['qty'])) {
                $result['error'] = 'Please enter the quantity ordered.';
                break;
            }

            if (empty($_REQUEST['did'])) {
                $result['error'] = 'Please enter the DID.';
                break;
            }

            if (empty($_REQUEST['deliveryDays'])) {
                $result['error'] = 'Please enter the delivery days.';
                break;
            }

            if (empty($_REQUEST['callHours'])) {
                $result['error'] = 'Please enter the call hours.';
                break;
            }

            if (strlen($_REQUEST['notes']) > 65535) {
                $result['error'] = 'Please limit your notes to 65,535 characters or less.';
                break;
            }

            $orderId = $leads->addInsertionOrder(array(
                'orderType' => $_REQUEST['orderType'] ?? 'publisher',
                'userId' => $userId,
                'companyId' => $_REQUEST['companyId'],
                'verticalId' => empty($_REQUEST['verticalId']) ? null : $_REQUEST['verticalId'],
                'startDate' => !isset($startDate) ? null : $startDate->format('Y-m-d'),
                'endDate' => !isset($endDate) ? null : $endDate->format('Y-m-d'),
                'paymentTerms' => empty($_REQUEST['paymentTerms']) ? null : $_REQUEST['paymentTerms'],
                'callReporting' => empty($_REQUEST['callReporting']) ? null : $_REQUEST['callReporting'],
                'costPerLead' => empty($_REQUEST['costPerLead']) ? null : $_REQUEST['costPerLead'],
                'costPerLeadUOM' => empty($_REQUEST['costPerLeadUOM']) ? null : $_REQUEST['costPerLeadUOM'],
                'deliveryMethod' => empty($_REQUEST['deliveryMethod']) ? null : $_REQUEST['deliveryMethod'],
                'qty' => empty($_REQUEST['qty']) ? null : $_REQUEST['qty'],
                'did' => empty($_REQUEST['did']) ? null : $_REQUEST['did'],
                'deliveryDays' => empty($_REQUEST['deliveryDays']) ? null : $_REQUEST['deliveryDays'],
                'callHours' => empty($_REQUEST['callHours']) ? null : $_REQUEST['callHours'],
                'notes' => empty($_REQUEST['notes']) ? null : $_REQUEST['notes'],
            ));
            if (null === $orderId) {
                $result['error'] = 'Error adding this order to the database.';
                break;
            } else {
                $leads->auditLog('ORDER:ADD', $orderId);
                if (!empty($_REQUEST['uid']) && !empty($_SESSION['insertionOrderFiles'][$_REQUEST['uid']]) && is_array($_SESSION['insertionOrderFiles'][$_REQUEST['uid']])) {
                    if (@mkdir(FILES_DIR . 'insertion-orders' . DIRECTORY_SEPARATOR . $orderId . DIRECTORY_SEPARATOR) !== true) {
                        $result['error'] = 'Error creating files directory for this order.';
                        break;
                    }
                    foreach ($_SESSION['insertionOrderFiles'][$_REQUEST['uid']] as $file => $blah) {
                        if (@rename(UPLOADS_DIR . basename($file), FILES_DIR . 'insertion-orders' . DIRECTORY_SEPARATOR . $orderId . DIRECTORY_SEPARATOR . basename($file)) !== true) {
                            $result['error'] = 'Error moving uploaded files for this order.';
                            break 2;
                        }
                    }
                }
            }

            $result['status'] = 1;
            $result['error'] = 'Successfully added new order.';
            break;

        case "alterOrder":
            $result['error'] = 'Failed when trying to edit an order';

            if (empty($_REQUEST['orderId'])) {
                $result['error'] = 'Please supply an order ID.';
                $c = false;
            }

            $entry = $leads->getInsertionOrder($_REQUEST['orderId']);
            if (empty($entry)) {
                $result['error'] = 'There is no order that exists by that ID.';
                break;
            }

            $userId = LeadsSession::getUserId();
            if (LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                if (empty($_REQUEST['userId'])) {
                    $result['error'] = 'Please select a salesperson from the list.';
                    break;
                }
                $userId = empty($_REQUEST['userId']) ? null : $_REQUEST['userId'];
            }

            if (empty($_REQUEST['companyId'])) {
                $result['error'] = 'Please select a company.';
                break;
            }

            if (empty($_REQUEST['verticalId'])) {
                $result['error'] = 'Please select the product type.';
                break;
            }

            if (empty($_REQUEST['startDate'])) {
                $result['error'] = 'Please select the start date.';
                break;
            } else {
                try {
                    $startDate = new DateTime($_REQUEST['startDate']);
                } catch (Exception $e) {
                    $result['error'] = 'Please enter a valid start date.';
                    break;
                }
            }

            if (empty($_REQUEST['endDate'])) {
                $result['error'] = 'Please select the end date.';
                break;
            } else {
                try {
                    $endDate = new DateTime($_REQUEST['endDate']);
                } catch (Exception $e) {
                    $result['error'] = 'Please enter a valid end date.';
                    break;
                }
            }

            if (empty($_REQUEST['paymentTerms'])) {
                $result['error'] = 'Please select the payment terms.';
                break;
            }

            if (empty($_REQUEST['costPerLead'])) {
                $result['error'] = 'Please enter the cost per lead.';
                break;
            }

            if (empty($_REQUEST['costPerLeadUOM'])) {
                $result['error'] = 'Please enter the cost per lead UOM.';
                break;
            }

            if (empty($_REQUEST['deliveryMethod'])) {
                $result['error'] = 'Please enter the delivery method.';
                break;
            }

            if (empty($_REQUEST['qty'])) {
                $result['error'] = 'Please enter the quantity ordered.';
                break;
            }

            if (empty($_REQUEST['did'])) {
                $result['error'] = 'Please enter the DID.';
                break;
            }

            if (empty($_REQUEST['deliveryDays'])) {
                $result['error'] = 'Please enter the delivery days.';
                break;
            }

            if (empty($_REQUEST['callHours'])) {
                $result['error'] = 'Please enter the call hours.';
                break;
            }

            if (strlen($_REQUEST['notes']) > 65535) {
                $result['error'] = 'Please limit your notes to 65,535 characters or less.';
                break;
            }

            $orderId = $leads->updateInsertionOrder($_REQUEST['orderId'], array(
                'orderType' => $_REQUEST['orderType'] ?? 'publisher',
                'userId' => $userId,
                'companyId' => $_REQUEST['companyId'],
                'verticalId' => empty($_REQUEST['verticalId']) ? null : $_REQUEST['verticalId'],
                'startDate' => !isset($startDate) ? null : $startDate->format('Y-m-d'),
                'endDate' => !isset($endDate) ? null : $endDate->format('Y-m-d'),
                'paymentTerms' => empty($_REQUEST['paymentTerms']) ? null : $_REQUEST['paymentTerms'],
                'callReporting' => empty($_REQUEST['callReporting']) ? null : $_REQUEST['callReporting'],
                'costPerLead' => empty($_REQUEST['costPerLead']) ? null : $_REQUEST['costPerLead'],
                'costPerLeadUOM' => empty($_REQUEST['costPerLeadUOM']) ? null : $_REQUEST['costPerLeadUOM'],
                'deliveryMethod' => empty($_REQUEST['deliveryMethod']) ? null : $_REQUEST['deliveryMethod'],
                'qty' => empty($_REQUEST['qty']) ? null : $_REQUEST['qty'],
                'did' => empty($_REQUEST['did']) ? null : $_REQUEST['did'],
                'deliveryDays' => empty($_REQUEST['deliveryDays']) ? null : $_REQUEST['deliveryDays'],
                'callHours' => empty($_REQUEST['callHours']) ? null : $_REQUEST['callHours'],
                'notes' => empty($_REQUEST['notes']) ? null : $_REQUEST['notes'],
                'isArchived' => !empty($_REQUEST['isArchived']) ? 1 : 0,
            ));
            if (null === $orderId) {
                $result['error'] = 'Error saving this order to the database.';
                break;
            } else {
                $leads->auditLog('ORDER:EDIT', $_REQUEST['orderId']);
            }

            $result['status'] = 1;
            $result['error'] = 'Successfully edited order.';

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

        case "dialog_neworder":

            $divisions = $leads->getDivisions();
            $verticals = array();
            foreach ($divisions as $key => $val) {
                $db_verticals = $leads->getDivisionVerticals($key);
                $verticals[$val] = $db_verticals;
            }

            $uid = uniqid('', true);

            $fields = array(
                array(
                    'id' => 'orderType',
                    'label' => 'Qatalyst Order Type',
                    'type' => 'radio',
                    'choices' => array(
                        'publisher' => 'Publisher (Seller)',
                        'advertiser' => 'Advertiser (Buyer)',
                    ),
                    'value' => 'publisher',
                    'choice_append' => '&nbsp;&nbsp;',
                    'required' => true,
                ),
                array(
                    'id' => 'userId',
                    'label' => 'Salesperson',
                    'type' => 'select',
                    'placeholder' => 'Select a salesperson',
                    'choices' => $leads->getStaffUsers(),
                    'active' => LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER) ? true : false,
                    'required' => true,
                ),
                array(
                    'id' => 'companyId',
                    'label' => 'Advertiser (Buyer)',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select a company',
                    'choices' => $leads->getCompaniesChoices('active'),
                ),
                array(
                    'id' => 'verticalId',
                    'label' => 'Product Type',
                    'type' => 'select',
                    'choices' => $verticals,
                ),
                array(
                    'id' => 'startDate',
                    'label' => 'Start Date',
                    'type' => 'text',
                    'autocomplete' => 'new-password',
                ),
                array(
                    'id' => 'endDate',
                    'label' => 'End Date',
                    'type' => 'text',
                    'autocomplete' => 'new-password',
                ),
                array(
                    'id' => 'paymentTerms',
                    'label' => 'Payment Terms',
                    'type' => 'select',
                    'choices' => array(
                        'prepayment' => 'Prepayment',
                        'weeklyNet7' => 'Weekly Net 7',
                        'weeklyNet14' => 'Weekly Net 14',
                        'weeklyNet30' => 'Weekly Net 30',
                        'monthlyNet7' => 'Monthly Net 7',
                        'monthlyNet14' => 'Monthly Net 14',
                        'monthlyNet30' => 'Monthly Net 30',
                    ),
                    'required' => true,
                ),
                array(
                    'id' => 'callReporting',
                    'label' => 'Call Reporting',
                    'type' => 'radio',
                    'choices' => array(
                        'publisher' => 'Provided by Publisher',
                        'advertiser' => 'Provided by Advertiser',
                    ),
                    'value' => 'publisher',
                    'choice_append' => '&nbsp;&nbsp;',
                    'required' => true,
                ),
                array(
                    'id' => 'costPerLead',
                    'label' => 'CPL Amount',
                    'type' => 'text',
                    'required' => true,
                ),
                array(
                    'id' => 'costPerLeadUOM',
                    'label' => 'CPL Per',
                    'type' => 'select',
                    'required' => true,
                    'choices' => array(
                        'lead' => 'Lead',
                        'hour' => 'Hour',
                        'call' => 'Call',
                    ),
                ),
                array(
                    'id' => 'deliveryMethod',
                    'label' => 'Delivery Method',
                    'type' => 'text',
                ),
                array(
                    'id' => 'qty',
                    'label' => 'Qty Ordered',
                    'type' => 'text',
                ),
                array(
                    'id' => 'did',
                    'label' => 'DID',
                    'type' => 'text',
                ),
                array(
                    'id' => 'deliveryDays',
                    'label' => 'Delivery Days',
                    'type' => 'text',
                ),
                array(
                    'id' => 'callHours',
                    'label' => 'Call Hours',
                    'type' => 'text',
                ),
                array(
                    'id' => 'notes',
                    'label' => 'Notes',
                    'type' => 'textarea',
                ),
                array(
                    'id' => 'uploader',
                    'label' => 'File Attachments',
                    'type' => '_html',
                    'value' => '<div id="import-uploader"></div>',
                ),
                array(
                    'id' => 'a',
                    'type' => 'hidden',
                    'value' => 'addNewOrder',
                ),
                array(
                    'id' => 'uid',
                    'type' => 'hidden',
                    'value' => $uid,
                ),
            );

            Display::displayForm('new_order', $fields);

            ?>
			<script type="text/template" id="qq-template">
				<div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop file here">
					<div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
						<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
					</div>
					<div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
						<span class="qq-upload-drop-area-text-selector"></span>
					</div>
					<div class="qq-upload-button-selector qq-upload-button">
						<div>Upload files</div>
					</div>
					<span class="qq-drop-processing-selector qq-drop-processing">
                    <span>Processing dropped file...</span>
                    <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
                </span>
					<ul class="qq-upload-list-selector qq-upload-list" aria-live="polite" aria-relevant="additions removals">
						<li>
							<div class="qq-progress-bar-container-selector">
								<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>
							</div>
							<span class="qq-upload-spinner-selector qq-upload-spinner"></span>
							<img class="qq-thumbnail-selector" qq-max-size="100" qq-server-scale>
							<span class="qq-upload-file-selector qq-upload-file"></span>
							<span class="qq-upload-size-selector qq-upload-size"></span>
							<button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel</button>
							<button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry</button>
							<button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">Delete</button>
							<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
						</li>
					</ul>

					<dialog class="qq-alert-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">Close</button>
						</div>
					</dialog>

					<dialog class="qq-confirm-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">No</button>
							<button type="button" class="qq-ok-button-selector">Yes</button>
						</div>
					</dialog>

					<dialog class="qq-prompt-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<input type="text">
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">Cancel</button>
							<button type="button" class="qq-ok-button-selector">Ok</button>
						</div>
					</dialog>
				</div>
			</script>

			<script type="text/javascript">
				var importUploader = new qq.FineUploader({
					chunking: {
						concurrent: {
							enabled: true
						},
						enabled: true,
						success: {
							endpoint: '/leadadmin/ajax/fileUpload.php?done=1'
						}
					},
					debug: <?php print ('development' === APPLICATION_ENV ? "true" : "false"); ?>,
					deleteFile: {
						enabled: true,
						endpoint: '/leadadmin/ajax/fileUpload.php',
						method: 'POST',
						params: {
							'type': 'insertion-order-add',
							'uid': '<?php echo $uid; ?>',
						},
					},
					element: document.getElementById("import-uploader"),
					failedUploadTextDisplay: {
						mode: 'custom'
					},
					multiple: true,
					request: {
						endpoint: '/leadadmin/ajax/fileUpload.php',
						params: {
							'type': 'insertion-order-add',
							'uid': '<?php echo $uid; ?>',
						},
					},
					retry: {
						enableAuto: true
					},
					template: 'qq-template',
					thumbnails: {
						placeholders: {
							waitingPath: '/leadadmin/libraries/fine-uploader/placeholders/waiting-generic.png',
							notAvailablePath: '/leadadmin/libraries/fine-uploader/placeholders/not_available-generic.png'
						}
					},
					validation: {
						allowedExtensions: ['gif', 'jpg', 'jpeg', 'png'],
					}
				});
			</script>

			<script type="text/javascript">
				$('#neworder input[name="orderType"]').on('change', function (e) {
					if ($(this).is(':checked') && $(this).val() === 'publisher') {
						$('#neworder label[data-for="companyId"]').html('Advertiser (Buyer)');
					} else if ($(this).is(':checked') && $(this).val() === 'advertiser') {
						$('#neworder label[data-for="companyId"]').html('Publisher (Seller)');
					}
				});
			</script>

            <?php

            break;

        case "dialog_editorder":
            $orderId = !empty($_REQUEST['orderId']) ? $_REQUEST['orderId'] : '';
            $order = $leads->getInsertionOrder($orderId);

            if (empty($order)) {
                ?>
				<p>There is no order that exists by that ID.</p>
                <?php

                break;

            }

            $divisions = $leads->getDivisions();
            $verticals = array();
            foreach ($divisions as $key => $val) {
                $db_verticals = $leads->getDivisionVerticals($key);
                $verticals[$val] = $db_verticals;
            }

            $uid = uniqid('', true);

            $fields = array(
                array(
                    'id' => 'orderType',
                    'label' => 'Qatalyst Order Type',
                    'type' => 'radio',
                    'choices' => array(
                        'publisher' => 'Publisher (Seller)',
                        'advertiser' => 'Advertiser (Buyer)',
                    ),
                    'value' => $order->orderType,
                    'choice_append' => '&nbsp;&nbsp;',
                    'required' => true,
                ),
                array(
                    'id' => 'userId',
                    'label' => 'Salesperson',
                    'type' => 'select',
                    'placeholder' => 'Select a salesperson',
                    'choices' => $leads->getStaffUsers(),
                    'active' => LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER) ? true : false,
                    'required' => true,
                    'value' => $order->userId,
                ),
                array(
                    'id' => 'companyId',
                    'label' => 'Advertiser (Buyer)',
                    'type' => 'select',
                    'required' => true,
                    'placeholder' => 'Select a company',
                    'choices' => $leads->getCompaniesChoices('active'),
                    'value' => $order->companyId,
                ),
                array(
                    'id' => 'verticalId',
                    'label' => 'Product Type',
                    'type' => 'select',
                    'choices' => $verticals,
                    'value' => $order->verticalId,

                ),
                array(
                    'id' => 'startDate',
                    'label' => 'Start Date',
                    'type' => 'text',
                    'autocomplete' => 'new-password',
                    'value' => $order->startDate,
                ),
                array(
                    'id' => 'endDate',
                    'label' => 'End Date',
                    'type' => 'text',
                    'autocomplete' => 'new-password',
                    'value' => $order->endDate,
                ),
                array(
                    'id' => 'paymentTerms',
                    'label' => 'Payment Terms',
                    'type' => 'select',
                    'choices' => array(
                        'prepayment' => 'Prepayment',
                        'weeklyNet7' => 'Weekly Net 7',
                        'weeklyNet14' => 'Weekly Net 14',
                        'weeklyNet30' => 'Weekly Net 30',
                        'monthlyNet7' => 'Monthly Net 7',
                        'monthlyNet14' => 'Monthly Net 14',
                        'monthlyNet30' => 'Monthly Net 30',
                    ),
                    'required' => true,
                    'value' => $order->paymentTerms,
                ),
                array(
                    'id' => 'callReporting',
                    'label' => 'Call Reporting',
                    'type' => 'radio',
                    'choices' => array(
                        'publisher' => 'Provided by Publisher',
                        'advertiser' => 'Provided by Advertiser',
                    ),
                    'choice_append' => '&nbsp;&nbsp;',
                    'required' => true,
                    'value' => $order->callReporting,

                ),
                array(
                    'id' => 'costPerLead',
                    'label' => 'CPL Amount',
                    'type' => 'text',
                    'required' => true,
                    'value' => $order->costPerLead,
                ),
                array(
                    'id' => 'costPerLeadUOM',
                    'label' => 'CPL Per',
                    'type' => 'select',
                    'required' => true,
                    'choices' => array(
                        'lead' => 'Lead',
                        'hour' => 'Hour',
                        'call' => 'Call',
                    ),
                    'value' => $order->costPerLeadUOM,
                ),
                array(
                    'id' => 'deliveryMethod',
                    'label' => 'Delivery Method',
                    'type' => 'text',
                    'value' => $order->deliveryMethod,

                ),
                array(
                    'id' => 'qty',
                    'label' => 'Qty Ordered',
                    'type' => 'text',
                    'value' => $order->qty,

                ),
                array(
                    'id' => 'did',
                    'label' => 'DID',
                    'type' => 'text',
                    'value' => $order->did,

                ),
                array(
                    'id' => 'deliveryDays',
                    'label' => 'Delivery Days',
                    'type' => 'text',
                    'value' => $order->deliveryDays,

                ),
                array(
                    'id' => 'callHours',
                    'label' => 'Call Hours',
                    'type' => 'text',
                    'value' => $order->callHours,

                ),
                array(
                    'id' => 'notes',
                    'label' => 'Notes',
                    'type' => 'textarea',
                    'value' => $order->notes,

                ),
                array(
                    'id' => 'isArchived',
                    'label' => 'Archived',
                    'type' => 'checkbox',
                    'choices' => array(
                        '1' => 'Archive/Hide this record',
                    ),
                    'value' => array(
                        '1' => !empty($order->isArchived) ? 1 : 0,
                    ),
                ),
                array(
                    'id' => 'uploader',
                    'label' => 'File Attachments',
                    'type' => '_html',
                    'value' => '<div id="import-uploader"></div>',
                ),
                array(
                    'id' => 'orderId',
                    'type' => 'hidden',
                    'value' => $order->orderId,
                ),
                array(
                    'id' => 'a',
                    'type' => 'hidden',
                    'value' => 'alterOrder',
                ),
                array(
                    'id' => 'uid',
                    'type' => 'hidden',
                    'value' => $uid,
                ),
            );

            Display::displayForm('edit_order', $fields);
            ?>

			<script>
				$('#editorder input[name="orderType"]').on('change', function (e) {
					if ($(this).is(':checked') && $(this).val() === 'publisher') {
						$('#editorder label[data-for="companyId"]').html('Advertiser (Buyer)');
					} else if ($(this).is(':checked') && $(this).val() === 'advertiser') {
						$('#editorder label[data-for="companyId"]').html('Publisher (Seller)');
					}
				});
			</script>

			<script type="text/template" id="qq-template">
				<div class="qq-uploader-selector qq-uploader" qq-drop-area-text="Drop file here">
					<div class="qq-total-progress-bar-container-selector qq-total-progress-bar-container">
						<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-total-progress-bar-selector qq-progress-bar qq-total-progress-bar"></div>
					</div>
					<div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
						<span class="qq-upload-drop-area-text-selector"></span>
					</div>
					<div class="qq-upload-button-selector qq-upload-button">
						<div>Upload files</div>
					</div>
					<span class="qq-drop-processing-selector qq-drop-processing">
                    <span>Processing dropped file...</span>
                    <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
                </span>
					<ul class="qq-upload-list-selector qq-upload-list" aria-live="polite" aria-relevant="additions removals">
						<li>
							<div class="qq-progress-bar-container-selector">
								<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>
							</div>
							<span class="qq-upload-spinner-selector qq-upload-spinner"></span>
							<img class="qq-thumbnail-selector" qq-max-size="100" qq-server-scale>
							<span class="qq-upload-file-selector qq-upload-file"></span>
							<span class="qq-upload-size-selector qq-upload-size"></span>
							<button type="button" class="qq-btn qq-upload-cancel-selector qq-upload-cancel">Cancel</button>
							<button type="button" class="qq-btn qq-upload-retry-selector qq-upload-retry">Retry</button>
							<button type="button" class="qq-btn qq-upload-delete-selector qq-upload-delete">Delete</button>
							<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>
						</li>
					</ul>

					<dialog class="qq-alert-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">Close</button>
						</div>
					</dialog>

					<dialog class="qq-confirm-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">No</button>
							<button type="button" class="qq-ok-button-selector">Yes</button>
						</div>
					</dialog>

					<dialog class="qq-prompt-dialog-selector">
						<div class="qq-dialog-message-selector"></div>
						<input type="text">
						<div class="qq-dialog-buttons">
							<button type="button" class="qq-cancel-button-selector">Cancel</button>
							<button type="button" class="qq-ok-button-selector">Ok</button>
						</div>
					</dialog>
				</div>
			</script>

			<script type="text/javascript">
				var importUploader = new qq.FineUploader({
					chunking: {
						concurrent: {
							enabled: true
						},
						enabled: true,
						success: {
							endpoint: '/leadadmin/ajax/fileUpload.php?done=1'
						}
					},
					debug: <?php print ('development' === APPLICATION_ENV ? "true" : "false"); ?>,
					deleteFile: {
						enabled: true,
						endpoint: '/leadadmin/ajax/fileUpload.php',
						method: 'POST',
						params: {
							'orderId': '<?php echo $order->orderId; ?>',
							'type': 'insertion-order-update',
							'uid': '<?php echo $uid; ?>',
						},
					},
					element: document.getElementById("import-uploader"),
					failedUploadTextDisplay: {
						mode: 'custom'
					},
					multiple: true,
					request: {
						endpoint: '/leadadmin/ajax/fileUpload.php',
						params: {
							'orderId': '<?php echo $order->orderId; ?>',
							'type': 'insertion-order-update',
							'uid': '<?php echo $uid; ?>',
						},
					},
					retry: {
						enableAuto: true
					},
					session: {
						endpoint: '/leadadmin/ajax/getUploads.php',
						params: {
							'orderId': '<?php echo $order->orderId; ?>',
							'type': 'insertion-order-update',
							'uid': '<?php echo $uid; ?>',
						},
					},
					template: 'qq-template',
					thumbnails: {
						placeholders: {
							waitingPath: '/leadadmin/libraries/fine-uploader/placeholders/waiting-generic.png',
							notAvailablePath: '/leadadmin/libraries/fine-uploader/placeholders/not_available-generic.png'
						}
					},
					validation: {
						allowedExtensions: ['gif', 'jpg', 'jpeg', 'png'],
					}
				});
			</script>

            <?php
            break;
    }
    exit;
}

$title = 'Insertion Orders';
include(INCLUDES . "c_header.php");
?>
<body>

<?php include(INCLUDES . 'c_nav.php'); ?>

<div class="container-fluid">

	<h2>Insertion Orders</h2>

	<p>
		<button type="button" class="btn btn-primary" data-toggle="modal" data-backdrop="static" data-target="#neworder">Add a new I/O</button>
		<a class="btn btn-primary" href="/leadadmin/insertion-orders/?searchIsArchived=0">Reset All Filters</a>
	</p>

    <?php

    $divisions = $leads->getDivisions();
    $verticals = array();
    foreach ($divisions as $key => $val) {
        $db_verticals = $leads->getDivisionVerticals($key);
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
            'id' => 'searchSalesperson',
            'label' => 'Sales Person',
            'type' => 'select',
            'choices' => $leads->getStaffUsers(\PDO::FETCH_KEY_PAIR, true),
            'value' => $_REQUEST['searchSalesperson'] ?? '',
        ),
        array(
            'id' => 'html_start',
            'type' => '_html',
            'value' => '</div><div class="col-md-4">',
        ),
        array(
            'id' => 'searchIsArchived',
            'label' => 'Status',
            'type' => 'select',
            'choices' => array(
                '0' => 'Active orders',
                '1' => 'Archived orders',
            ),
            'value' => isset($_REQUEST['searchIsArchived']) && strlen($_REQUEST['searchIsArchived']) > 0 ? $_REQUEST['searchIsArchived'] : null,
        ),
        array(
            'id' => 'searchVerticals',
            'label' => 'Verticals',
            'type' => 'select',
            'multiple' => true,
            'placeholder' => false,
            'choices' => $verticals,
            'choice_append' => '<br/>',
            'value' => !empty($_REQUEST['searchVerticals']) && is_array($_REQUEST['searchVerticals']) ? array_combine($_REQUEST['searchVerticals'], $_REQUEST['searchVerticals']) : array(),
        ),
        array(
            'id' => 'html_start',
            'type' => '_html',
            'value' => '</div><div class="col-md-4">',
        ),
        array(
            'id' => 'searchOrderType',
            'label' => 'Order Type',
            'type' => 'select',
            'choices' => array(
                'publisher' => 'Publisher',
                'advertiser' => 'Advertiser',
            ),
            'value' => $_REQUEST['searchOrderType'] ?? '',
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

    Display::displayForm('crm_search', $fields, '');

    if (!empty($filterUserId) && !array_key_exists($filterUserId, $staffUsers)) {
        $filterUserId = LeadsSession::getUserId();
    }

    $orders = $leads->searchInsertionOrders(array(
        'isArchived' => isset($_REQUEST['searchIsArchived']) && strlen($_REQUEST['searchIsArchived']) > 0 ? $_REQUEST['searchIsArchived'] : null,
        'text' => $_REQUEST['searchText'] ?? null,
        'salesperson' => $_REQUEST['searchSalesperson'] ?? null,
        'verticals' => $_REQUEST['searchVerticals'] ?? null,
        'orderType' => $_REQUEST['searchOrderType'] ?? null,
    ));
    if (empty($orders)) {

        print '<p>No orders match your search criteria.</p>';

    } else {
        ?>

		<table class="table table-bordered table-condensed table-striped" id="crm-orders">
			<thead>
			<tr class="bgGray header">
				<th>Order #</th>
				<th>Type</th>
				<th>Company</th>
				<th>Vertical</th>
				<th>Options</th>
			</tr>
			</thead>
			<tbody>
            <?php
            foreach ($orders as $order) {
                ?>
				<tr>
					<td><?php echo Display::escHtml($order->orderId); ?></td>
					<td><?php echo Display::escHtml(ucfirst($order->orderType)); ?></td>
					<td><?php echo Display::escHtml($order->companyName); ?></td>
					<td><?php echo Display::escHtml($order->verticalName); ?></td>
					<td class="text-center" style="min-width:75px;">
						<div class="btn-group">
							<button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-backdrop="static" data-target="#editorder" data-order-id="<?php echo $order->orderId; ?>">Edit</button>
							<button type="button" class="btn btn-primary btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								<span class="caret"></span>
								<span class="sr-only">Toggle Dropdown</span>
							</button>
							<ul class="dropdown-menu">
								<li><a href="/leadadmin/insertion-orders/pdf.php?orderId=<?php echo urlencode( $order->orderId ); ?>">Download PDF</a></li>
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

<div class="modal fade" id="neworder" tabindex="-1" role="dialog" aria-labelledby="neworder_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="neworder_title">Add a new I/O</h4>
			</div>
			<div class="modal-body">
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-neworder" type="button" class="btn btn-primary">Add Order</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editorder" tabindex="-1" role="dialog" aria-labelledby="editorder_title">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="editorder_title">Edit an order</h4>
			</div>
			<div class="modal-body"></div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<button id="modal-save-editorder" type="button" class="btn btn-primary">Save changes</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	$('#modal-save-neworder').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "index.php",
			type: "POST",
			async: true,
			data: $("#new_order").serialize()
		}).done(function (result) {
			if (result.status === 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#neworder').on('show.bs.modal', function (e) {
		var modal = $(this);

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'index.php',
			data: {
				'd': 'dialog_neworder'
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#modal-save-editorder').click(function (event) {
		event.preventDefault();

		var response = $.ajax({
			url: "index.php",
			type: "POST",
			async: true,
			data: $("#edit_order").serialize()
		}).done(function (result) {
			if (result.status == 1) {
				window.location.reload(true);
			} else {
				alert(result.error);
			}
		});
	});

	$('#editorder').on('show.bs.modal', function (e) {
		var modal = $(this);
		var orderId = $(e.relatedTarget).data('order-id');

		$.ajax({
			cache: false,
			type: 'POST',
			url: 'index.php',
			data: {
				'd': 'dialog_editorder',
				'orderId': orderId
			},
			success: function (data) {
				modal.find('.modal-body').html(data);
			}
		});
	});

	$('#neworder, #editorder').on('hide.bs.modal', function (e) {
		$(this).find('.modal-body').html('');
	});

	$('#filter-select select').change(function (e) {
		e.preventDefault();
		$('#filter-select').submit();
	});

	$('#neworder, #editorder').on('shown.bs.modal', function (e) {
		$(".modal-body input[name=startDate], .modal-body input[name=endDate]").datepicker({
			// Consistent format with the HTML5 picker
			dateFormat: 'yy-mm-dd'
		});
	});

	$('table').each(function () {
		var tf = new TableFilter($(this).attr('id'), {
			base_path: '/leadadmin/libraries/tablefilter/',
			grid: false,
			filters_row_index: 1,
			extensions: [{
				name: 'sort',
				types: [
					'caseinsensitivestring', // Company
					'caseinsensitivestring', // Name
					'caseinsensitivestring', // Opportunity
					'caseinsensitivestring', // Phone
					'caseinsensitivestring', // Email
					'caseinsensitivestring', // Divisions
					'Number', // Percentage
					{type: 'date', locale: 'en-US'}, // Follow-Up
					{type: 'date', locale: 'en-US'} // Updated
				],
				image_asc_class_name: 'custom-ascending',
				image_desc_class_name: 'custom-descending'
			}],
			sort: true
		});
		tf.init();
	});
</script>

</body>
</html>
