#!/bin/sh
mkdir uploads error public_html/leadadmin/exports pushLead/lockfiles
chown -R rscs:rscs * .svn
chmod -R g+rw * .svn
chown -R apache:webdev error public_html/leadadmin/exports/ uploads
chmod -R g+rw error public_html/leadadmin/exports/
chmod -R o+rwx pushLead/lockfiles/
