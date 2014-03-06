#!/bin/sh
chown -R rscs:webdev * .svn
chmod -R g+rw * .svn
chown -R apache:webdev error public_html/leadadmin/exports/
chmod -R o+rwx pushLead/lockfiles/
