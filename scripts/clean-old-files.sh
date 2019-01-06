#!/usr/bin/env bash
find /var/www/html/production/qmleads.com/public_html/leadadmin/exports -ctime +7 -type f -delete
find /var/www/html/production/qmleads.com/uploads -ctime +7 \( -type f -or -type d \) -not -path /var/www/html/production/qmleads.com/uploads/insertion-orders -not -path /var/www/html/production/qmleads.com/uploads/chunks -ls