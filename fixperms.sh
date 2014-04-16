#!/bin/sh
chown -R rscs:webdev * .svn
chmod -R g+rw * .svn
chown -R apache:webdev error public_html/leadadmin/exports/
chown -R rscs:webdev public_html/leadadmin/exports/.svn
chown -R rscs:webdev error/.svn
chmod -R o+rwx pushLead/lockfiles/
