<?php

chdir(dirname(__FILE__));

require_once("../includes/c_config.php");
require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

$verbose = false;
$recordsPerRun = 1000;
$maxThreads = 20;

// Check to see if the manageThreads process is already running and it's not this process.
exec("pgrep -f manageThreads.php", $processes);
if (!empty($processes) && is_array($processes) && sizeOf($processes)) {
    foreach ($processes as $process) {
        if (getmypid() != $process) {
            if ($verbose) {
                print 'Already running.';
            }
            die();
        }
    }
}

function countProcesses($idFeedOut)
{

    exec(sprintf("ps auxwww|grep '[p]ushLeads.php %d$'", intval($idFeedOut)), $output);

    return !empty($output) && is_array($output) ? sizeOf($output) : 0;

}

while (true) {

    $feeds = $leads->getOutboundFeedsCron(null);

    if (!$feeds || !is_array($feeds)) {
        if ($verbose) {
            print "Unable to get feed list\n";
        }
        die();
    }

    if ($verbose) {
        print "\n====================================\n";
    }

    foreach ($feeds as $feed) {

        $cnt = countProcesses($feed->idFeedOut);
        if ($verbose) {
            print "Feed: {$feed->idFeedOut}, Processes: {$cnt}\n";
        }

        $threads = round($feed->queued / $recordsPerRun);
        if ($threads < 1) {
            $threads = 1;
        } else {
            if ($threads > $maxThreads) {
                $threads = $maxThreads;
            }
        }

        if ($verbose) {
            print "\tThreads: {$threads}\n";
        }

        while ($cnt < $threads) {
            if ($verbose) {
                print "\tSpawning new\n";
            }

            exec(sprintf('php -f pushLeads.php %s>/dev/null 2>&1 &',
                    escapeshellarg($feed->idFeedOut)
                )
            );
            usleep(500000);

            $cnt++;
        }
    }

    sleep(30);
}
