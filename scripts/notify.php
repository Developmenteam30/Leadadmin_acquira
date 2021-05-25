<?php

chdir(__DIR__);

require('../includes/c_config.php');

$mysqlErrorSource = 'Notification script';
require_once(INCLUDES . 'leads.php');

function sendNotification($feed, $hours)
{
    $to = MANAGER_EMAIL;
    $subject = 'Dormant URL notification - ' . str_replace('.', '*', $feed->url);
    $body = "\nThe following URL has gone dormant for more than {$hours} hours:\n\n";
    $body .= "URL: " . str_replace('.', '*', $feed->url) . "\n\n";
    $body .= "Company: {$feed->companyName}\r\n";
    $body .= "Feed ID: {$feed->idFeedIn}\r\n";
    $body .= "Feed Label: {$feed->label}\r\n";
    $body .= "Feed Description: {$feed->description}\r\n";
    $body .= "Last seen time: {$feed->lastTime}\n\n";

    $from = SYSTEM_FROM_EMAIL;
    $fromName = CONFIG_COMPANY_NAME . ' List Management System';
    $header = "From: " . $fromName . " <" . $from . ">\r\n";
    //$header .= "BCC: " . ADMINISTRATOR_EMAIL . "\r\n";
    $header .= "Content-type: text/plain; charset=iso-8859-1\r\n";

    $sent = @mail($to, $subject, $body, $header, "-f {$from}");

    if (!$sent) {
        $leads = Leads::getInstance();
        $leads->logError('Failed to send error report notification to administrator');
    }
}

$leads = Leads::getInstance();

$notifyInterval1 = $leads->getConfiguration('notify_interval_1');
if (!empty($notifyInterval1)) {
    $feeds = $leads->getNotifyInterval1Feeds();
    if (!empty($feeds) && is_array($feeds)) {
        foreach ($feeds as $feed) {
            sendNotification($feed, $notifyInterval1);
            $leads->updateNotification($feed->idFeedIn, $feed->url);
        }
    }
}

$notifyInterval2 = $leads->getConfiguration('notify_interval_2');
if (!empty($notifyInterval2)) {
    $feeds = $leads->getNotifyInterval2Feeds();
    if (!empty($feeds) && is_array($feeds)) {
        foreach ($feeds as $feed) {
            sendNotification($feed, $notifyInterval2);
            $leads->updateNotification($feed->idFeedIn, $feed->url);
        }
    }
}