<?php 
//ADMIN_ROOT/mgr_feedout.php
//Version 1.0
//ES20130726 Version 1.0: Outgoing Feed Manager created.
session_start();
$mysqlErrorSource = 'Manager - Suppression';
include("../c_config.php");
include(SITE_ROOT."_connx.php");
include(ADMIN_ROOT."loginCheck.php");
include(ADMIN_ROOT."f_site.php");
include(ADMIN_ROOT."c_loginRequired.php"); //Login is required for this page.
include(LIVE_ROOT."processFunctions.php");

function legacyPopulate( $feedId, $file = false ) {

	$populations = getPopulation( $feedId );
	if( $populations === false ) {
		print "Database error";
	} else if( $populations == 0 ) {
		print "No populations for this feed";
	} else {

		foreach( $populations as $population ) {

			if( $file ) {
				$fileName = 'exports/' . $population->outLabel . '_' . time() . '.tsv';
				$filePath = ADMIN_ROOT . $fileName;
				$handle = fopen( $filePath, 'w' );
				if( $handle === false ) {
					print "Unable to create output file: {$fileName}\n";
					continue;
				}

				$processed = '1';
				$postStamp = date( 'Y-m-d H:i:s' );
				$postRequest = null;
				$postResponse = "File output: {$fileName}";

			} else {

				$processed = '0';
				$postStamp = null;
				$postRequest = null;
				$postResponse = null;

			}
				
			$stamp = getOutboundStamp( $population->outLabel );

	  		$query  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` ";
    		$query .= "WHERE stamp >= DATE_SUB(NOW(), INTERVAL 365 DAY) ";
			if( $stamp ) {
				$query .= "AND stamp <= '" . $stamp . "' ";
			}
			$query .= "ORDER BY stamp DESC";

			dbCon();
    		$result = dbQry( $query, 'Getting inbound records', true );

    		if( $result === false ) { return false; }
    		if( $result->num_rows == 0 ) { return 0; }
	    	$values = array();

print "Total records found: {$result->num_rows}\n";

			$cnt = 0;
	    	while( $row = $result->fetch_object() ) {

				// Ensure the record passes the population parameter filters for this feed
				if( checkPopulationFilters( $population, $row->url, $row->email, $row->listcode ) ) {

					if( '0000-00-00' == $row->dob ) {
						$row->dob = null;
					}

					if( $file ) {
						if( fputs( $handle, implode( array( 
								$row->email,
								$row->urlTrim,
								$row->ip,
								$row->stamp,
								$row->fname,
								$row->lname,
								$row->addr,
								$row->addr2,
								$row->city,
								$row->state,
								$row->zip,
								$row->country,
								$row->landline,
								'',
								$row->dob,
								$row->gender,
								'',
								'',
								'',
								'',
							), "\t" ) . "\n" ) === FALSE ) {
							print "Unable to write record to file {$fileName}\n";
							continue;
						}
					}

					addOutboundRecord( $population->outLabel, $row->listcode, $row->urlTrim, $row->url, $row->ip, $row->stamp, $row->email, $row->fname, $row->lname, $row->addr, $row->addr2, $row->city, $row->state, $row->zip, $row->country, $row->dob, $row->gender, $row->landline, $row->cellphone, $processed, $postStamp, $postRequest, $postResponse );
					$cnt++;

				}

				//print_r( $row );
    		}
print "Records that match population filters: {$cnt}\n";

			if( $file ) {
				fclose( $handle );
			}

		}

	}

}

legacyPopulate( 64, false );
