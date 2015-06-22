<?php 

include("../../includes/c_config.php");

//require_once( INCLUDES . 'session.php' );
//LeadsSession::requireAccess( LEADS_SESSION_LEVEL_STAFF );

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

			$leads = Leads::getInstance();
			$idRecord = $leads->firstOutboundRecord( $feedId, 'instantcheckmate.com' );

	  		$sql  = "SELECT * FROM data_inbound ";
    		$sql .= "WHERE idFeedIn = " . intval( $population->idFeedIn ) . " ";
			$sql .= "AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";
			if( !empty( $idRecord ) ) {
				$sql .= "AND idRecord <= '" . $idRecord . "' ";
			}
			$sql .= "AND result IS NULL ";
			$sql .= "AND url = 'instantcheckmate.com' ";
			$sql .= "ORDER BY timestamp DESC";

	  		//$sql  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE jobId = '1392144291'";
	  		//$sql  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE urlTrim = 'http://www.rewardcorporation.com'";
	  		//$sql  = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE received >= '2014-04-28' AND listcode = '1346'";
			//$sql = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE stamp >= '2014-06-16 22:59' AND urlTrim = 'http://www.instantcheckmate.com'";
			//$sql = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE stamp >= '2014-08-01' AND stamp < '2014-08-10 10:16:02' AND listcode = '1382329'";
			//$sql = "SELECT * FROM `".DATABASE_NAME."`.`feedinc_" . $population->inLabel."` WHERE jobId IN (1410894753,1410895983)";

			$query = $leads->exportRecords( $sql );

			if( empty( $query ) ) {
				return false;
			}

			//print "Total records found: {$result->num_rows}\n";

	    	$values = array();
			$cnt = 0;
			while ( $row = $query->fetch( PDO::FETCH_OBJ ) ) {

				// Ensure the record passes the population parameter filters for this feed
				if( checkPopulationFilters( $population, $row->url, $row->email, $row->listcode ) ) {

					if( '0000-00-00' == $row->dob ) {
						$row->dob = null;
					}

					if( $file ) {
						if( fputcsv( $handle, array(
								$row->email,
								$row->url,
								$row->ip,
								$row->leadstamp,
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
							) ) === FALSE ) {
							print "Unable to write record to file {$fileName}\n";
							continue;
						}
					}

					$legacyId = null;
					if( LEGACY_DB ) {
						$legacyId = addOutboundRecord( $population->outLabel, $row->listcode, $row->urlTrim, $row->url, $row->ip, $row->leadstamp, $row->email, $row->fname, $row->lname, $row->addr, $row->addr2, $row->city, $row->state, $row->zip, $row->country, $row->dob, $row->gender, $row->landline, $row->cellphone, $processed, $postStamp, $postRequest, $postResponse );
					}
					$leads->outboundAdd( $row->idRecord, $legacyId, $population->idFeedIn, $population->idFeedOut, $row->url, $file ? 1 : 0 );

					$cnt++;

				}

    		}

			print "Records that match population filters: {$cnt}\n";

			if( $file ) {
				fclose( $handle );
			}

		}

	}

}

legacyPopulate( 448, true );
