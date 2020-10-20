<?php

chdir(dirname(__FILE__));

require_once("../includes/c_config.php");
require_once(INCLUDES . 'leads.php');
$leads = Leads::getInstance();

$verbose = false;
$recordsPerRun = 1000;
$maxThreads = 20;

// Simple test to see if this script is already running by binding to a specific port
// Adapted from: https://www.timkay.com/solo/solo
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (@socket_bind($socket, '127.1.1.0', MANAGE_THREADS_SOCKET_PORT) === false) {
    if ($verbose) {
        die('Already running');
    } else {
        die();
    }
}

function countProcesses($idFeedOut)
{

    exec(sprintf("ps auxwww|grep '[p]ushLeads.php %s %d$'",
        escapeshellarg(SITE_URL),
        intval($idFeedOut)
    ), $output);

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

            exec(sprintf('php -f pushLeads.php %s %s>/dev/null 2>&1 &',
                    escapeshellarg(SITE_URL),
                    escapeshellarg($feed->idFeedOut)
                )
            );
            usleep(500000);

            $cnt++;
        }
    }

    sleep(30);
}
