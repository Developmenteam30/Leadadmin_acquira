# Notes

Store ping rejections in the stats.  No need to store ping accepted records or stats.
Whoever POSTs the lead first gets credit.  POST reject the other lead as a duplicate.

# Database changes

    ALTER TABLE feedinc ADD pingTimeout INT UNSIGNED DEFAULT 300 NOT NULL;
    ALTER TABLE feedinc MODIFY COLUMN required varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'email;ip;url;stamp' NULL;
    ALTER TABLE feedinc ADD requiredPingFields varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL;
    ALTER TABLE feedinc ADD allowedPingFields varchar(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL;
    ALTER TABLE data_inbound ADD ping TINYINT UNSIGNED DEFAULT 0;

# TODO

- Is allowed fields even being checked properly?

# Testing

https://dev.qmleads.com/live/64/livefeed.php?pswd=v3fB9PnwQNVm9NVo&url=www.rscs.org&ip=1.2.3.4&stamp=2018-08-16&email=test@test1.com&landline=2125551212&cellphone=2125551212&ping=1&email=ham@ham.com

https://dev.qmleads.com/live/64/livefeed.php?pswd=v3fB9PnwQNVm9NVo&url=www.rscs.org&ip=1.2.3.4&stamp=2018-08-16&email=test@test1.com&landline=2125551212&cellphone=2125551212&ping=0&email=ham@ham.com&authorization=