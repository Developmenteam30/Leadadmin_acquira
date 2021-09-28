#!/bin/sh
cd $(dirname $0)
mkdir uploads uploads/chunks error public_html/leadadmin/exports pushLead/lockfiles files files/insertion-orders
chown -R rscs:webdev * .git .gitignore
chmod -R g+rw,o= * .git .gitignore
chown -R nginx:webdev error public_html/leadadmin/exports/ uploads files
chmod -R g+rw error public_html/leadadmin/exports/ uploads files
chmod -R o+rwx pushLead/lockfiles/
find public_html/leadadmin/exports/* -mtime +90 -exec rm -rf {} \;
find uploads/* -mtime +90 -exec rm -rf {} \;
