<?php

require( '../includes/c_config.php' );
require_once( INCLUDES."leads.php" );

$leads = Leads::getInstance();

print date('c' ) . " Pruning orphaned outbound records ...\n";
$leads->pruneOrphanedOutboundRecords();

print date('c' ) . " Archiving error messages ...\n";
$leads->archiveErrors();

print date('c' ) . " Archiving inbound records ...\n";
$leads->archiveInboundRecords();

// Only needed once a week
if(date('w') === 0) {
    print date('c') . " Freeing table space ...\n";
    $leads->freeTableSpace();
}

print date('c' ) . " Resetting queued stats ...\n";
$leads->resetQueuedStats();

