<?php

die('UNUSED');

require('../includes/c_config.php');
require_once(INCLUDES . 'leads.php');

$running = true;

function signalHandler($signal)
{
    global $running;
    // Tell the main loop to stop running so we can exit gracefully
    $running = false;
}

pcntl_signal(SIGTERM, 'signalHandler');// Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

$leads = Leads::getInstance();


$records = $leads->getInboundMissingTimestamps();
foreach ($records as $record) {
    $seconds = (($record['idRecord'] - 1000425601) * 1125273) / 9247643;

    $date = new \DateTime('2018-11-15 18:17:09', new \DateTimeZone('UTC'));
    $date->modify("+" . round($seconds) . " seconds");

    echo $record['idRecord'] . ' ' . round($seconds) . ' ' . $date->format('c') . PHP_EOL;

    $leads->fixInboundRecordTimestamp($record['idRecord'], $date->format('Y-m-d H:i:s'));
}


die();

$date = new \DateTime('2018-11-17 12:36:14', new \DateTimeZone('UTC'));

$leads->beginTransaction();
for ($i = 1001678945; $i <= 1009679665; $i++) {
    printf("%s %s\n",
        $i,
        $date->format('c')
    );
    $leads->fixInboundRecordTimestamp($i, $date->format('Y-m-d H:i:s'));
    $date->modify("+121600 microseconds");

    if ($i % 5000 === 0) {
        print "Interim commit ...\n";
        $leads->commit();
        $leads->beginTransaction();
    }

    if (!$running) {
        break;
    }
}

print "Final commit ...\n";
$leads->commit();

