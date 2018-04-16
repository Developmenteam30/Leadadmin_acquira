#!/bin/sh
mysqladmin -hqmleads.ck44eyk7mgen.us-east-1.rds.amazonaws.com -uqmadmin -p pr | fgrep '`cellphone` =' | awk '{print "CALL mysql.rds_kill_query(" $2 ");" }'
