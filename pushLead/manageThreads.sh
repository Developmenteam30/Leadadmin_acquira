#!/bin/sh

cd $(dirname $0)

LCK="/tmp/flock-manage-threads";
exec 8>$LCK;

if flock -n -x 8; then
	php -f manageThreads.php
fi
