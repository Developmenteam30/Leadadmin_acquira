#!/bin/sh
cd $(dirname $0)
mkdir uploads error public_html/leadadmin/exports pushLead/lockfiles
chown -R rscs:webpriv * .svn
chmod -R g+rw,o= * .svn
chown -R nginx:webpriv error public_html/leadadmin/exports/ uploads
chmod -R g+rw error public_html/leadadmin/exports/
chmod -R o+rwx pushLead/lockfiles/
find public_html/leadadmin/exports/* -mtime +90 -exec rm -rf {} \;
find uploads/* -mtime +90 -exec rm -rf {} \;
