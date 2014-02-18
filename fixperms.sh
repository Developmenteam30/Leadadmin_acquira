#!/bin/sh
chown -R rscs:rscs * .svn
chmod -R g+rw *
chown -R apache:rscs error public_html/leadadmin/exports/
chmod -R o+rwx pushLead/lockfiles/
