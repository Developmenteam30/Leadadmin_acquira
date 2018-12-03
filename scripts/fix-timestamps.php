<?php

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

/*

$jobs = $leads->getJobsTimestamp();
foreach ($jobs as $job) {
    print $job->jobId . ' ' . $job->timestamp . PHP_EOL;
    $leads->fixInboundJobTimestamp($job->jobId, $job->timestamp);
}

$leads->fixWaterfallStats( 855 );
$leads->fixLiveStats( 664 );
$leads->fixLiveStats( 690 );
$leads->fixLiveStats( 731 );
$leads->fixLiveStats( 732 );
$leads->fixLiveStats( 815 );
$leads->fixLiveStats( 859 );
$leads->fixLiveStats( 863 );
$leads->fixLiveStats( 864 );
$leads->fixLiveStats( 632 );
$leads->fixLiveStats( 631 );
$leads->fixLiveStats( 642 );
$leads->fixLiveStats( 801 );
$leads->fixLiveStats( 843 );

*/