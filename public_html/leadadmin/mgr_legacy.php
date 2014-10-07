<?php 

include("../../includes/c_config.php");

require_once( INCLUDES . 'session.php' );
LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

$mysqlErrorSource = 'Manager - Suppression';
include(INCLUDES."_connx.php");
include(INCLUDES."f_site.php");
require_once(INCLUDES."processFunctions.php");

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
    		$query .= "WHERE stamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
			if( $stamp ) {
				$query .= "AND stamp <= '" . $stamp . "' ";
			}
			$query .= "ORDER BY stamp DESC";

	  		//$query  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE jobId = '1392144291'";
	  		//$query  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE urlTrim = 'http://www.rewardcorporation.com'";
	  		//$query  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE received >= '2014-04-28' AND listcode = '1346'";
			//$query = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE stamp >= '2014-06-16 22:59' AND urlTrim = 'http://www.instantcheckmate.com'";
			$query = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE stamp >= '2014-08-01' AND stamp < '2014-08-10 10:16:02' AND listcode = '1382329'";
			$query = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE jobId IN (1410894753,1410895983)";

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

					$legacyId = addOutboundRecord( $population->outLabel, $row->listcode, $row->urlTrim, $row->url, $row->ip, $row->stamp, $row->email, $row->fname, $row->lname, $row->addr, $row->addr2, $row->city, $row->state, $row->zip, $row->country, $row->dob, $row->gender, $row->landline, $row->cellphone, $processed, $postStamp, $postRequest, $postResponse );
					if( !empty( $legacyId ) ) {
						$leads = Leads::getInstance();
//						$leads->outboundAdd( $inboundId, $legacyId, $population->idFeedIn, $population->idFeedOut, $row->url );
					}

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

legacyPopulate( 211, false );
