<?php

require_once( 'c_config.php' );
require_once( 'processFunctions.php' );

class Leads
{
	private $db;
	private static $instance;

	public static function getInstance() {

		if(!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {

		// Connect to the database
		try {
			$this->db = new PDO( 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME, $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p'] );
		} catch( PDOException $e ) {

			$this->logError( 'Database connection error: ' . $e->getMessage() );
			print "Error connecting to the database";
			die();

		}  
	}

	public function parseUrl( $url ) {
		if( empty( $url ) ) {
			return null;
		}

		$url = strtolower( $url );

		if( strpos( $url, 'http' ) === false ) {
			$url = 'http://' . $url;
		}

		if( ( $hostname = parse_url( $url, PHP_URL_HOST ) ) !== false ) {
			return str_replace( 'www.', '', $hostname );
		}

		return $url;
	}

	private function quoteIdentifier( $value ) {
		$q = '`';
		return ( $q . str_replace( "$q", "$q$q", $value ) . $q );
	}

	public function insertRow( $table, array $data ) {
		$cols = array();
		$vals = array();

		foreach ( $data as $col => $val ) {
			$cols[] = $this->quoteIdentifier( $col );
			$vals[] = '?';
		}

		try {
			$query = $this->db->prepare( 'INSERT INTO ' . $this->quoteIdentifier( $table ) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')' );
			$query->execute( array_values( $data ) );
			return $this->db->lastInsertId();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to insert record: ' . $e->getMessage() );
			return null;
		}

		return null;
	}

	public function verifyUser( $username, $password ) {
		try {
			$query = $this->db->prepare( "SELECT idUser,username,password FROM users WHERE username = ?" );
			$query->execute( array( $username ) );
			$results = $query->fetch( );

			if( $results ) {

				if( password_verify( $password, $results['password'] ) ) {
            
					// If the password hash is outdated, rehash and save to the database
					if( password_needs_rehash( $results['password'], PASSWORD_DEFAULT, array( 'cost' => 11 ) ) ) {
						$hash = password_hash( $password, PASSWORD_DEFAULT, array( 'cost' => 11 ) );

						$query = $this->db->prepare( "UPDATE users SET password = ? WHERE username = ?" );
						$query->execute( array( $username, $hash ) );
            
					}

					return true;
				}
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to verify user password: ' . $e->getMessage() );
		}

		$this->logError( 'Failed login for user [' . $username . '] from [' . $_SERVER['REMOTE_ADDR'] . ']', true );

		return false;
	}

	public function getCompany( $idCompany ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM companies WHERE idCompany = ?" );
			$query->execute( array( $idCompany ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundFeed( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedinc WHERE idFeedIn = ?" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound feed info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundFeeds( $retired = null ) {
		$results = array();

		try {
			if( $retired === null ) {
				$query = $this->db->prepare( "SELECT f.*,c.name FROM feedinc f LEFT JOIN companies c ON f.idCompany = c.idCompany ORDER BY c.name,f.idFeedIn" );
				$query->execute( );
			} else {
				$query = $this->db->prepare( "SELECT f.*,c.name FROM feedinc f LEFT JOIN companies c ON f.idCompany = c.idCompany WHERE f.retired = ? ORDER BY c.name,f.idFeedIn" );
				$query->execute( array( $retired ? '1' : '0' ) );
			}
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound feed list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundFeeds( $retired = null ) {
		$results = array();

		try {
			if( $retired === null ) {
				$query = $this->db->prepare( "SELECT f.*,c.name FROM feedout f LEFT JOIN companies c ON f.idCompany = c.idCompany ORDER BY c.name,f.idFeedOut" );
				$query->execute( );
			} else {
				$query = $this->db->prepare( "SELECT f.*,c.name FROM feedout f LEFT JOIN companies c ON f.idCompany = c.idCompany WHERE f.retired = ? ORDER BY c.name,f.idFeedOut" );
				$query->execute( array( $retired ? '1' : '0' ) );
			}
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feed list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundStats( $idFeedOut ) {
		$results = array( 'accepted' => 0, 'rejected' => 0 );

		try {
			$query = $this->db->prepare( "SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_outbound WHERE stamp = DATE_FORMAT(NOW(), '%Y-%m-%d') AND idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound stats: ' . $e->getMessage() );
		}

		return $results;
	}

	public function inboundAdd( $idFeedIn, $fields, $error = null, $jobId = null ) {

		$idRecord = $this->insertRow( 'data_inbound', array(
			'timestamp' => date( 'c' ),
			'idFeedIn' => $idFeedIn,
			'listcode' => empty( $fields['listcode'] ) ? null : $fields['listcode'],
			'leadstamp' => empty( $fields['stamp'] ) ? null : date( 'c', strtotime( $fields['stamp'] ) ),
			'url' => empty( $fields['url'] ) ? null : $this->parseUrl( $fields['url'] ),
			'ip' => empty( $fields['ip'] ) ? null : $fields['ip'],
			'email' => empty( $fields['email'] ) ? null : $fields['email'],
			'fname' => empty( $fields['fname'] ) ? null : $fields['fname'],
			'lname' => empty( $fields['lname'] ) ? null : $fields['lname'],
			'addr' => empty( $fields['addr'] ) ? null : $fields['addr'],
			'addr2' => empty( $fields['addr2'] ) ? null : $fields['addr2'],
			'city' => empty( $fields['city'] ) ? null : $fields['city'],
			'state' => empty( $fields['state'] ) ? null : $fields['state'],
			'zip' => empty( $fields['zip'] ) ? null : $fields['zip'],
			'dob' => ( empty( $fields['dob'] ) || '0000-00-00' == $fields['dob'] ) ? null : $fields['dob'],
			'gender' => empty( $fields['gender'] ) ? null : $fields['gender'],
			'landline' => empty( $fields['landline'] ) ? null : $fields['landline'],
			'cellphone' => empty( $fields['cellphone'] ) ? null : $fields['cellphone'],
			'country' => empty( $fields['country'] ) ? null : $fields['country'],
			'result' => empty( $error ) ? null : $error,
			'jobId' => empty( $jobId ) ? null : $jobId,
		) );

		try {
			if( !empty( $fields['url'] ) ) {
				if( empty( $error ) ) {
					$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1' );
				} else {
					$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1' );
				}
				$query->execute( array( $idFeedIn, $this->parseUrl( $fields['url'] ) , date('Y-m-d') ) );
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to insert stats_inbound record: ' . $e->getMessage() );
			return $idRecord;
		}

		return $idRecord;
	}

	public function inboundProcess( $idRecord, $idFeedIn, $url, $error = null ) {
		try {
			$query = $this->db->prepare( 'UPDATE data_inbound SET result = ? WHERE idRecord = ?' );
			$query->execute( array( $error, $idRecord ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update data_inbound record: ' . $e->getMessage() );
			return;
		}

		try {
			if( empty( $error ) ) {
				$query = $this->db->prepare( 'UPDATE stats_inbound SET accepted = accepted + 1, rejected = rejected - 1 WHERE idFeedIn = ? AND url = ?' );
			} else {
				$query = $this->db->prepare( 'UPDATE stats_inbound SET accepted = accepted - 1, rejected = rejected + 1 WHERE idFeedIn = ? AND url = ?' );
			}
			$query->execute( array( $idFeedIn, $this->parseUrl( $url ) ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update stats_inbound record: ' . $e->getMessage() );
			return;
		}
	}

	public function outboundAdd( $idRecord, $idRecordLegacy, $idFeedIn, $idFeedOut, $url ) {
		$status = $this->insertRow( 'data_outbound', array(
			'idRecord' => $idRecord,
			'idRecordLegacy' => $idRecordLegacy,
			'idFeedIn' => $idFeedIn,
			'idFeedOut' => $idFeedOut,
		) );

		if( $status !== null ) {
			try {
				$query = $this->db->prepare( "REPLACE INTO url_mapping(timestamp,idFeedIn,idFeedOut,url) VALUES(NOW(), ?, ?, ?)" );
				$query->execute( array( $idFeedIn, $idFeedOut, $this->parseUrl( $url ) ) );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to add URL mapping: ' . $e->getMessage() );
				return $status;
			}

			try {
				$query = $this->db->prepare( "UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?" );
				$query->execute( array( $idFeedOut ) );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to add to queue count: ' . $e->getMessage() );
				return $status;
			}
		}

		return $status;
	}

//REMOVE
	public function getLastTime( $label, $url ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT MAX(stamp) FROM feedout_{$label} WHERE urlTrim = ?" );
			$query->execute( array( $url ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get last URL time: ' . $e->getMessage() );
		}

		return $results;
	}

//REMOVE
	public function addMapping( $idFeedIn, $idFeedOut, $url, $time ) {
			try {
				$query = $this->db->prepare( "REPLACE INTO url_mapping(timestamp,idFeedIn,idFeedOut,url) VALUES(?, ?, ?, ?)" );
				$query->execute( array( $time, $idFeedIn, $idFeedOut, $this->parseUrl( $url ) ) );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to add URL mapping: ' . $e->getMessage() );
				return $status;
			}

	}

	public function outboundProcess( $idRecord, $idFeedOut, $url, $error = null ) {
		try {
			//$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), result = ? WHERE idRecord = ?' );
			$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), result = ? WHERE idRecordLegacy = ? AND idFeedOut = ?' );
			$query->execute( array( $error, $idRecord, $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update data_outbound record: ' . $e->getMessage() );
			return;
		}

		try {
			if( empty( $error ) ) {
				$query = $this->db->prepare( 'INSERT INTO stats_outbound(idFeedOut,url,stamp,accepted) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1' );
			} else {
				$query = $this->db->prepare( 'INSERT INTO stats_outbound(idFeedOut,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1' );
			}
			$query->execute( array( $idFeedOut, $this->parseUrl( $url ), date('Y-m-d') ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to insert stats_outbound record: ' . $e->getMessage() );
			return;
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = queued - 1 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to subtract from queue count: ' . $e->getMessage() );
			return $status;
		}
	}

	public function getUrlMappings() {
		$results = array();

		$query  = "( SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,IF(m.timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY),1,0) AS active ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ) ";

		$query .= "UNION ALL ";

		$query .= "( SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,s.url,'-' AS outName,'X' AS idFeedOut,'-' AS outDescription, 0 AS active ";
		$query .= "FROM stats_inbound s ";
		$query .= "INNER JOIN feedinc i ON s.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "LEFT JOIN url_mapping m ON ( m.url = s.url AND m.idFeedIn = s.idFeedIn ) ";
		$query .= "WHERE m.url IS NULL ";
		$query .= "GROUP BY 4 ) ";

		$query .= "ORDER BY 1,2,4,5,6 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get URL mappings: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundRejections( $idFeedIn, $offset = 0 ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT timestamp,result,leadstamp,listcode,url,fname,lname,addr,addr2,city,state,zip,country,dob,gender,landline,cellphone,email,ip FROM data_inbound WHERE idFeedIn = ? AND result IS NOT NULL ORDER BY timestamp DESC LIMIT " . intval( $offset ) . ",100" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound rejections: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundRejections( $idFeedOut, $offset = 0 ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT o.timestamp,o.result,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord INNER JOIN feedout f ON f.idFeedOut = o.idFeedOut WHERE o.idFeedOut = ? AND o.timestamp IS NOT NULL AND o.result NOT LIKE CONCAT('%',f.successString,'%') ORDER BY o.timestamp DESC LIMIT " . intval( $offset ) . ",100" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound rejections: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundStats( $idFeedIn ) {
		$results = array( 'accepted' => 0, 'rejected' => 0 );

		try {
			$query = $this->db->prepare( "SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = DATE_FORMAT(NOW(), '%Y-%m-%d') AND idFeedIn = ?" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound stats: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundURLStats( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT url,IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = DATE_FORMAT(NOW(), '%Y-%m-%d') AND idFeedIn = ? GROUP BY url" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL stats: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundURLDates( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT url,MIN(stamp) AS date FROM stats_inbound WHERE idFeedIn = ? GROUP BY url" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL dates: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundURLStatsReport( $idFeedIn, $urlList, $breakdown, $dateStart, $dateEnd, $sort ) {
		$results = array();

		if( !empty( $urlList ) && is_array( $urlList ) ) {
			$urlList =  implode(',', array_map( 'add_quotes', $urlList ) );
		}

		if( !empty( $breakdown ) && $breakdown == 'month' ) {
			$query  = "SELECT url,LEFT(stamp,7) date,SUM(accepted) accepted,SUM(rejected) rejected ";
		} else if( !empty( $breakdown ) && $breakdown == 'year' ) {
			$query  = "SELECT url,LEFT(stamp,4) date,SUM(accepted) accepted,SUM(rejected) rejected ";
		} else if( !empty( $breakdown ) && $breakdown == 'total' ) {
			$query  = "SELECT url,'TOTAL' as date,SUM(accepted) accepted,SUM(rejected) rejected ";
		} else {
			$query  = "SELECT url,stamp AS date,SUM(accepted) accepted,SUM(rejected) rejected ";
		}

		$query .= "FROM stats_inbound ";
		$query .= "WHERE idFeedIn = ? ";
		if( !empty( $urlList ) ) {
			$query .= "AND url IN (" . $urlList . ") ";
		}

		if( !empty( $dateStart ) && !empty( $dateEnd ) ) {
			if( strtotime($dateStart) > strtotime($dateEnd) ) {
				$dateStart = date("Y-m-d", strtotime($dateEnd));
				$dateEnd = date("Y-m-d", strtotime($dateStart));
			} else {
				$dateStart = date("Y-m-d", strtotime($dateStart));
				$dateEnd = date("Y-m-d", strtotime($dateEnd));
			}
			$query .= "AND stamp >= '".$dateStart."' AND stamp < '".$dateEnd."' ";
		}
			
		$query .= "GROUP BY 1,2 ";
		if( !empty( $sort ) && 'url' == $sort ) {
			$query .= "ORDER BY 1,2";
		} elseif( !empty( $sort ) && 'count' == $sort ) {
			$query .= "ORDER BY 3,1";
		} else {
			$query .= "ORDER BY 2,1";
		}

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL dates: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addNotification( $idFeedIn, $url ) {
		try {
			$query = $this->db->prepare( "REPLACE INTO notifications (lastTime, notifyTime, idFeedIn, url) VALUES(NOW(), 0, ?, ?)" );
			$query->execute( array( $idFeedIn, $this->parseUrl( $url ) ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to add notification record: ' . $e->getMessage() );
			return;
		}
	}

	public function deleteNotifications( $idFeedIn ) {
		try {
			$query = $this->db->prepare( "DELETE FROM notifications WHERE idFeedIn = ?" );
			$query->execute( array( $idFeedIn ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete notification records: ' . $e->getMessage() );
			return;
		}
	}

	public function archiveErrors() {
		try {
			$query = $this->db->prepare( "DELETE FROM errorlog WHERE stamp <= DATE_SUB(NOW(), INTERVAL 15 DAY)" );
			$query->execute( );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old errorlog entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function archiveInbound( ) {
		try {
			$query = $this->db->prepare( "DELETE FROM data_inbound WHERE result IS NOT NULL" );
			$query->execute( );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old data_inbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function getLegacyInboundTables() {
		try {
			$query = $this->db->prepare( "SHOW TABLES LIKE 'feedinc_%_invalid'" );
			$query->execute( );
			return $query->fetchAll();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get old legacy inbound tables: ' . $e->getMessage() );
		}

		return null;
	}

	public function archiveLegacyInbound( $table ) {
		try {
			$query = $this->db->prepare( "DELETE FROM " . $this->quoteIdentifier( $table ) . " WHERE received <= DATE_SUB(NOW(), INTERVAL 15 DAY)" );
			$query->execute( );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old legacy inbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function archiveOutbound( $idFeedOut, $success ) {
		try {
			$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idFeedOut = ? AND timestamp IS NOT NULL AND timestamp <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND result NOT LIKE ?" );
			$query->execute( array( $idFeedOut, $success ) );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old data_outbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function getOutboundTables() {
		try {
			$query = $this->db->prepare( "SELECT label,successString,idFeedOut FROM feedout" );
			$query->execute( );
			return $query->fetchAll();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get old legacy outbound tables: ' . $e->getMessage() );
		}

		return null;
	}

	public function archiveLegacyOutbound( $table, $success ) {
		try {
			$query = $this->db->prepare( "DELETE FROM " . $this->quoteIdentifier( 'feedout_' . $table ) . " WHERE poststamp <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND processed = '1' AND postresponse NOT LIKE ?" );
			$query->execute( array( '%' . $success . '%' ) );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old legacy outbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function resetQueuedStats() {
		try {
			$this->db->query( "LOCK TABLES feedout WRITE, data_outbound WRITE" );

			$this->db->query( "UPDATE feedout SET queued = 0" );

			$query = $this->db->prepare( "SELECT idFeedOut,COUNT(*) AS cnt FROM data_outbound WHERE timestamp IS NULL GROUP BY idFeedOut" );
			$query->execute( );
			$rows = $query->fetchAll();

			foreach( $rows as $row ) {
				print "Setting queued to {$row['cnt']} for ID: {$row['idFeedOut']}\n";
				$query = $this->db->prepare( "UPDATE feedout SET queued = ? WHERE idFeedOut = ?" );
				$query->execute( array( $row['cnt'], $row['idFeedOut'] ) );
			}

			$this->db->query( "UNLOCK TABLES" );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to reset queued stats: ' . $e->getMessage() );
		}

		return null;
	}

	public function logError( $message, $db = false ) {

		$stamp = date('Y-m-d H:i:s');
		$errfile = fopen( SITE_ROOT . 'error' . FD . 'leads-log', 'a' );
		if( $errfile ) {
			fwrite( $errfile, $stamp . ' ' . $message . PHP_EOL );
			fclose( $errfile );
		}

		if( $db ) {
			$this->insertRow( 'errorlog', array( 
				'origination' => 'LEADS',
				'description' => $message,
				'stamp' => date( 'c' ),
			) );
		}

		// Limit notification emails to one per minute to prevent flooding
		$time = @file_get_contents( SITE_ROOT."error".FD."email-stamp" );
		if( $time === FALSE || ( $time < ( time() - 60 ) ) ) {
			file_put_contents( SITE_ROOT."error".FD."email-stamp", time() );
		} else {
			return;
		}

		$from = 'lmsalerts@'.SITE_URL;
		$body = $stamp . ' ' . $message . PHP_EOL;
		$fromName = CONFIG_COMPANY_NAME.' List Management System';
		$to = ADMINISTRATOR_EMAIL;
		$subject = 'List Management ERROR';
		$header = "From:" . $fromName . " <" . $from . ">\n";
		$header .= "Content-type: text/html; charset=iso-8859-1\n";
		$header .= "Reply-To: <" . $from . ">\n";
		$header .= "X-Sender: <" . $from . ">\n";
		$header .= "X-Mailer: PHP5\n";
		$header .= "X-Priority: 3\n";
		$header .= "Return-Path: <" . $from . ">\n";
		$sent = @mail( $to, $subject, $body, $header );
	}

	public function getErrorCount() {
		try {
			$query = $this->db->prepare( "SELECT COUNT(*) AS cnt FROM errorlog WHERE stamp LIKE ?" );
			$query->execute( array( date( 'Y-m-d' ) . '%' ) );
			return $query->fetchColumn( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get error count: ' . $e->getMessage() );
		}

		return null;
	}

	public function getErrors() {
		try {
			$query = $this->db->prepare( "SELECT * FROM errorlog WHERE stamp LIKE ? ORDER BY stamp DESC" );
			$query->execute( array( date( 'Y-m-d' ) . '%' ) );
			return $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get error log: ' . $e->getMessage() );
		}

		return null;
	}
}
