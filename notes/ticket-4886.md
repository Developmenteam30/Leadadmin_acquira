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