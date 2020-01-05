<?php

require('../includes/c_config.php');
require_once(INCLUDES . "leads.php");
require_once(INCLUDES . "processLeads.php");

$leads = Leads::getInstance();

$feedOut = $leads->getOutboundFeed(949);
$leaddata = $leads->getInboundRecord(1084630544);
if(empty($leaddata)) {
    die('Cannot find record');
}
$leaddata->testRecord = 1;

print "HTTP Method: " . $feedOut->feedType . "\n";

$response = ProcessLeads::pushOutboundData($feedOut, $leaddata);

if (!empty($response['headers'])) {
    print "Headers:\n";
    foreach ($response['headers'] as $header) {
        printf("- %s\n",
            $header
        );
    }
    print "\n";
}

print "Query String: " . $response['querystring'] . "\n";

print "Status: " . (true === $response['status'] ? 'ACCEPTED' : 'REJECTED') . "\n";

print "Response: " . $response['text'] . "\n";