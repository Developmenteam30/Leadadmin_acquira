<?php

require_once( 'c_config.php' );
require_once( 'processFunctions.php' );

class Leads
{
	protected $db;
	protected static $instance;

	public static function getInstance() {

		if(!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {

		// Connect to the database
		try {
			$this->db = new PDO( 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME, $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p'] );
			$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
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

	protected function quoteIdentifier( $value ) {
		$q = '`';
		return ( $q . str_replace( "$q", "$q$q", $value ) . $q );
	}

	private function insertRow( $table, array $data, $logError = true ) {
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
			if( $logError ) {
				$this->logError( 'Unable to insert record: ' . $e->getMessage() );
			}
			return null;
		}

		return null;
	}

	private function update( $table, array $data, array $where = array() ) {
		$cols = array();
		$where_cols = array();

		if( empty( $data ) ) {
			return null;
		}
		foreach ( $data as $col => $val ) {
			$cols[] = $this->quoteIdentifier( $col ) . ' = ?';
		}

		if( !empty( $where ) ) {
			foreach ( $where as $col => $val ) {
				$where_cols[] = $this->quoteIdentifier( $col ) . ' = ?';
			}
		}

		try {
			$sql = 'UPDATE ' . $this->quoteIdentifier( $table ) . ' SET ' . implode( ', ', $cols );
			if( !empty( $where_cols ) ) {
				$sql .= ' WHERE ' . implode(' AND ', $where_cols );
			}

			$query = $this->db->prepare( $sql );
			$query->execute( array_merge( array_values( $data ), array_values( $where ) ) );
			return true;
		} catch( PDOException $e ) {
			if( $logError ) {
				$this->logError( 'Unable to update table: ' . $e->getMessage() );
			}
			return null;
		}

		return null;
	}

	public function lockTables( $tables ) {
		if( !empty( $tables ) ) {
			try {
				$this->db->query( "LOCK TABLES " . $tables );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to lock tables: ' . $e->getMessage() );
			}
		}
	}

	public function unlockTables() {
		try {
			$this->db->query( "UNLOCK TABLES" );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to unlock tables: ' . $e->getMessage() );
		}
	}

	public function addUser( $username, $password, $idCompany, $level ) {

		$this->insertRow( 'users', array(
			'username' => $username,
			'idCompany' => $idCompany,
			'level' => $level,
		) );

		$this->setPasswordHash( $username, $password );
	}

	public function getUser( $idUser ) {
		try {
			$query = $this->db->prepare( "SELECT username,password,idCompany,level FROM users WHERE idUser = ?" );
			$query->execute( array( $idUser ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get user information: ' . $e->getMessage() );
		}

		return $results;
	}

	public function verifyUser( $username, $password ) {
		try {
			$query = $this->db->prepare( "SELECT idUser,username,password,idCompany,level FROM users WHERE username = ?" );
			$query->execute( array( $username ) );
			$results = $query->fetch( );

			if( $results ) {

				if( password_verify( $password, $results['password'] ) ) {

					// If the password hash is outdated, rehash and save to the database
					if( password_needs_rehash( $results['password'], PASSWORD_DEFAULT, array( 'cost' => 11 ) ) ) {
						$this->setPasswordHash( $username, $password );
					}

					return array(
						'idUser' => $results['idUser'],
						'level' => $results['level'],
						'idCompany' => $results['idCompany'],
					);
				}
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to verify user password: ' . $e->getMessage() );
		}

		$this->logError( 'Failed login for user [' . $username . '] from [' . $_SERVER['REMOTE_ADDR'] . ']', true );

		return null;
	}

	public function setPasswordHash( $username, $password ) {
		try {
			$hash = password_hash( $password, PASSWORD_DEFAULT, array( 'cost' => 11 ) );

			$query = $this->db->prepare( "UPDATE users SET password = ? WHERE username = ?" );
			$query->execute( array( $hash, $username ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company info: ' . $e->getMessage() );
		}
	}

	public function auditLog( $action, $notes = null ) {

		require_once( INCLUDES . 'session.php' );
		$this->insertRow( 'auditlog', array(
			'userId' => LeadsSession::getUserId(),
			'ipaddress' => $_SERVER['REMOTE_ADDR'], 
			'action' => $action,
			'notes' => $notes,
		) );
	}

	public function checkCompanyName( $name, $idCompany = null ) {
		$result = false;

		try {
			if( !empty( $idCompany ) ) {
				$query = $this->db->prepare( "SELECT 1 FROM companies WHERE name = ? AND idCompany != ?" );
				$query->execute( array( $name, $idCompany ) );
			} else {
				$query = $this->db->prepare( "SELECT 1 FROM companies WHERE name = ?" );
				$query->execute( array( $name ) );
			}
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company info: ' . $e->getMessage() );
		}

		return $result;
	}

	public function addCompany( $fields ) {

		if( empty( $fields['name'] ) ) {
			return null;
		}

		$idCompany = $this->insertRow( 'companies', array(
			'name' => $fields['name'],
			'note' => empty( $fields['note'] ) ? null : $fields['note'],
			'address' => empty( $fields['address'] ) ? null : $fields['address'],
			'city' => empty( $fields['city'] ) ? null : $fields['city'],
			'state' => empty( $fields['state'] ) ? null : $fields['state'],
			'zipcode' => empty( $fields['zipcode'] ) ? null : $fields['zipcode'],
			'main_name' => empty( $fields['main_name'] ) ? null : $fields['main_name'],
			'main_phone' => empty( $fields['main_phone'] ) ? null : $fields['main_phone'],
			'main_email' => empty( $fields['main_email'] ) ? null : $fields['main_email'],
			'acct_name' => empty( $fields['acct_name'] ) ? null : $fields['acct_name'],
			'acct_phone' => empty( $fields['acct_phone'] ) ? null : $fields['acct_phone'],
			'acct_email' => empty( $fields['acct_email'] ) ? null : $fields['acct_email'],
			'tech_name' => empty( $fields['tech_name'] ) ? null : $fields['tech_name'],
			'tech_phone' => empty( $fields['tech_phone'] ) ? null : $fields['tech_phone'],
			'tech_email' => empty( $fields['tech_email'] ) ? null : $fields['tech_email'],
		) );

		if( null === $idCompany ) {
			return null;
		}

		$query = $this->db->prepare( "CREATE TABLE " . $this->quoteIdentifier( "suppresssion_" . $idCompany ) . " LIKE suppression_global" );
		$query->execute( );

		return $idCompany;
	}

	public function updateCompany( $idCompany, $fields ) {
		return $this->update( 'companies', $fields, array(
			'idCompany' => $idCompany,
		) );
	}

	public function getCompany( $idCompany ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM companies WHERE idCompany = ?" );
			$query->execute( array( $idCompany ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getCompanies( ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM companies ORDER BY name" );
			$query->execute( );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addInboundFeed( $fields ) {

		if( empty( $fields['label'] ) ) {
			return null;
		}

		$idFeedIn = $this->insertRow( 'feedinc', array(
			'label' => $fields['label'],
			'description' => empty( $fields['description'] ) ? null : $fields['description'],
			'idCompany' => $fields['idCompany'],
			'required' => empty( $fields['required'] ) ? null : $fields['required'],
			'allowedFields' => empty( $fields['allowedFields'] ) ? null : $fields['allowedFields'],
			'password' => empty( $fields['password'] ) ? null : $fields['password'],
			'dedupeEmail' => empty( $fields['dedupeEmail'] ) ? 0 : 1,
			'dedupeLandline' => empty( $fields['dedupeLandline'] ) ? 0 : 1,
			'dedupeCellphone' => empty( $fields['dedupeCellphone'] ) ? 0 : 1,
			'rejectOldLeads' => empty( $fields['rejectOldLeads'] ) ? null : $fields['rejectOldLeads'],
			'rejectOldLeadsMaxAge' => empty( $fields['rejectOldLeadsMaxAge'] ) ? null : $fields['rejectOldLeadsMaxAge'],
			'retired' => empty( $fields['retired'] ) ? 0 : 1,
			'dedupeAcross' => empty( $fields['dedupeAcross'] ) ? null : $fields['dedupeAcross'],
			'filterTypeUrl' => empty( $fields['filterTypeUrl'] ) ? null : $fields['filterTypeUrl'],
			'filterTypeSiftLogic' => empty( $fields['filterTypeSiftLogic'] ) ? null : $fields['filterTypeSiftLogic'],
			'notifications' => empty( $fields['notifications'] ) ? 0 : 1,
		) );

		if( null === $idFeedIn ) {
			return null;
		}

		if( LEGACY_DB ) {
			try {
				$query = $this->db->prepare( "CREATE TABLE " . $this->quoteIdentifier( "feedinc_" . $fields['label'] ) . " LIKE feedinc_empty" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to create new inbound table: ' . $e->getMessage() );
				return null;
			}

			try {
				$query = $this->db->prepare( "CREATE TABLE " . $this->quoteIdentifier( "feedinc_" . $fields['label'] . "_invalid" ) . " LIKE feedinc_empty_invalid" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to create new inbound invalid table: ' . $e->getMessage() );
				return null;
			}
		}

		return $idFeedIn;
	}

	public function updateInboundFeed( $idFeedIn, $fields ) {
		return $this->update( 'feedinc', $fields, array(
			'idFeedIn' => $idFeedIn,
		) );
	}

	public function renameInboundTables( $old, $new ) {

		if( LEGACY_DB ) {
			try {
				$query = $this->db->prepare( "RENAME TABLE " . $this->quoteIdentifier( "feedinc_" . $old ) . " TO " . $this->quoteIdentifier( "feedinc_" . $new ) );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to rename inbound table: ' . $e->getMessage() );
				return null;
			}

			try {
				$query = $this->db->prepare( "RENAME TABLE " . $this->quoteIdentifier( "feedinc_" . $old . "_invalid" ) . " TO " . $this->quoteIdentifier( "feedinc_" . $new . "_invalid" ) );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to rename inbound invalid table: ' . $e->getMessage() );
				return null;
			}
		}

		return true;
	}

	public function getInboundFeed( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedinc WHERE idFeedIn = ?" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
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

	public function checkInboundFeedLabelExists( $label ) {
		$result = false;

		try {
			$query = $this->db->prepare( "SELECT 1 FROM feedinc WHERE label = ?" );
			$query->execute( array( $label ) );
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check inbound feed label: ' . $e->getMessage() );
		}

		return $result;
	}

	public function getOutboundFeed( $idFeedOut ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedout WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feed info: ' . $e->getMessage() );
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

	public function inboundAdd( $idFeedIn, $fields, $statsDay, $error = null, $jobId = null ) {
		$this->lockTables( "data_inbound WRITE, stats_inbound WRITE, errorlog WRITE" );

		$status = $idRecord = $this->insertRow( 'data_inbound', array(
			'timestamp' => date( 'Y-m-d H:i:s' ),
			'idFeedIn' => $idFeedIn,
			'listcode' => empty( $fields['listcode'] ) ? null : $fields['listcode'],
			'leadstamp' => empty( $fields['stamp'] ) ? null : date( 'Y-m-d H:i:s', strtotime( $fields['stamp'] ) ),
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
			'dob' => ( empty( $fields['dob'] ) || '0000-00-00' == $fields['dob'] ) ? null : date( 'Y-m-d', strtotime( $fields['dob'] ) ),
			'gender' => empty( $fields['gender'] ) ? null : $fields['gender'],
			'landline' => empty( $fields['landline'] ) ? null : $fields['landline'],
			'cellphone' => empty( $fields['cellphone'] ) ? null : $fields['cellphone'],
			'country' => empty( $fields['country'] ) ? null : $fields['country'],
			'result' => empty( $error ) ? null : $error,
			'jobId' => empty( $jobId ) ? null : $jobId,
		) );

		if( $status !== null ) {
			try {
				if( !empty( $fields['url'] ) ) {
					if( empty( $error ) ) {
						$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1' );
					} else {
						$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1' );
					}
					$query->execute( array( $idFeedIn, $this->parseUrl( $fields['url'] ) , $statsDay ) );
				}
			} catch( PDOException $e ) {
				$this->logError( 'Unable to insert stats_inbound record: ' . $e->getMessage() );
				$this->unlockTables();
				return $idRecord;
			}
		}

		$this->unlockTables();
		return $idRecord;
	}

	public function inboundProcess( $idRecord, $idFeedIn, $url, $statsDay, $error = null ) {
		$this->lockTables( "data_inbound WRITE, stats_inbound WRITE, errorlog WRITE" );

		try {
			$query = $this->db->prepare( 'UPDATE data_inbound SET result = ? WHERE idRecord = ?' );
			$query->execute( array( $error, $idRecord ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update data_inbound record: ' . $e->getMessage() );
			$this->unlockTables();
			return;
		}

		try {
			if( !empty( $error ) ) {
				$query = $this->db->prepare( 'UPDATE stats_inbound SET accepted = accepted - 1, rejected = rejected + 1 WHERE idFeedIn = ? AND url = ? AND stamp = ?' );
			}
			$query->execute( array( $idFeedIn, $this->parseUrl( $url ), $statsDay ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update stats_inbound record: ' . $e->getMessage() );
			$this->unlockTables();
			return;
		}

		$this->unlockTables();
	}

	public function inboundCheckDuplicates( $idFeedIn, $column, $requestValues, $dedupeAcross ) {

		$days = 120;

		// Override duplicate time check period for InstantCheckMate.com feeds
		if( 'email' == $column && !empty( $requestValues['email'] ) && !empty( $requestValues['url'] ) && strpos( $requestValues['url'], 'instantcheckmate.com' ) !== false ) {
			$status = $this->globalEmailSearch( $requestValues['email'] );
			if( !empty( $status ) ) {
				return true;
			}
		}
    
		try {
			switch( $dedupeAcross ) {
				case 'global':
					$query = $this->db->prepare( "SELECT COUNT(*) AS cnt FROM data_inbound WHERE idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
					) );
				break;
				case 'url':
					$query = $this->db->prepare( "SELECT COUNT(*) AS cnt FROM data_inbound WHERE idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND url = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['url'] ) ? $this->parseUrl( $requestValues['url'] ) : '',
					) );
				break;
				case 'listcode':
					$query = $this->db->prepare( "SELECT COUNT(*) AS cnt FROM data_inbound WHERE idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND listcode = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY)" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['listcode'] ) ? $requestValues['listcode'] : '',
					) );
				break;
			}

			if( $query && $query->fetchColumn() >= 1 ) {
				return true;
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to check for inbound duplicates: ' . $e->getMessage() );
			return null;
		}

		return false;
	}

	public function outboundAdd( $idRecord, $idRecordLegacy, $idFeedIn, $idFeedOut, $url ) {
		$this->lockTables( "data_outbound WRITE, url_mapping WRITE, feedout WRITE, errorlog WRITE" );

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
				$this->unlockTables();
				return $status;
			}

			try {
				$query = $this->db->prepare( "UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?" );
				$query->execute( array( $idFeedOut ) );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to add to queue count: ' . $e->getMessage() );
				$this->unlockTables();
				return $status;
			}
		}

		$this->unlockTables();

		return $status;
	}

	public function outboundProcess( $idRecord, $idFeedOut, $url, $error = null ) {
		$this->lockTables( "data_outbound WRITE, stats_outbound WRITE, feedout WRITE, errorlog WRITE" );

		try {
			if( LEGACY_DB ) {
				$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), processed = 1, result = ? WHERE idRecordLegacy = ? AND idFeedOut = ?' );
				$query->execute( array( $error, $idRecord, $idFeedOut ) );
			} else {
				$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), processed = 1, result = ? WHERE idRecord = ? AND idFeedOut = ?' );
				$query->execute( array( $error, $idRecord, $idFeedOut ) );
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update data_outbound record: ' . $e->getMessage() );
			$this->unlockTables();
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
			$this->unlockTables();
			return;
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = queued - 1 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to subtract from queue count: ' . $e->getMessage() );
			$this->unlockTables();
			return $status;
		}

		$this->unlockTables();
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

	public function getRevenueInboundMappings( $date, $idCompany, $idFeedIn, $url ) {
		$results = array();
		$fields = array();
		$fields[] = $date;

		$query  = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,r.value AS revenue,MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ";
		$query .= "LEFT JOIN stats_outbound s ON s.url = m.url AND s.idFeedOut = m.idFeedOut ";
		$query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
		$query .= "AND m.url = r.url ";
		$query .= "AND m.idFeedOut = r.idFeedOut ";
		$query .= "AND r.date = ? ";
		$query .= "WHERE 1=1 ";
		if( !empty( $idCompany ) ) {
			$query .= "AND i.idCompany = ? ";
			$fields[] = $idCompany;
		}
		if( !empty( $idFeedIn ) ) {
			$query .= "AND i.idFeedIn = ? ";
			$fields[] = $idFeedIn;
		}
		if( !empty( $url ) ) {
			$query .= "AND m.url = ? ";
			$fields[] = $url;
		}
		$query .= "GROUP BY 2,4,6 ";
		$query .= "ORDER BY 1,2,4,5,6 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( $fields );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound revenue mappings: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueInboundClientMappings( $date, $idCompany, $idFeedIn, $url ) {
		$results = array();
		$fields = array();
		$fields[] = $date;

		$query  = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,SUM(DISTINCT r.value) AS revenue,SUM(DISTINCT ROUND(r.value*0.50,2)) AS partner,IF(SUM(r.value)>0,'0','1'),MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "LEFT JOIN stats_outbound s ON s.url = m.url ";
		$query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
		$query .= "AND m.url = r.url ";
		$query .= "AND m.idFeedOut = r.idFeedOut ";
		$query .= "AND r.date = ? ";
		$query .= "WHERE 1=1 ";
		if( !empty( $idCompany ) ) {
			$query .= "AND i.idCompany = ? ";
			$fields[] = $idCompany;
		}
		if( !empty( $idFeedIn ) ) {
			$query .= "AND i.idFeedIn = ? ";
			$fields[] = $idFeedIn;
		}
		if( !empty( $url ) ) {
			$query .= "AND m.url = ? ";
			$fields[] = $url;
		}
		$query .= "GROUP BY 4 ";
		$query .= "ORDER BY 7,1,2,4 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( $fields );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound client revenue mappings: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueInboundClientMonthMappings( $idCompany ) {
		$results = array();
		$fields = array();

		$query  = "SELECT ci.name AS inName,r.date AS month,SUM(r.value) AS revenue,SUM(ROUND(r.value*0.50,2)) AS partner,i.idCompany AS idCompany ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
		$query .= "AND m.url = r.url ";
		$query .= "AND m.idFeedOut = r.idFeedOut ";
		$query .= "WHERE r.value IS NOT NULL ";
		$query .= "AND r.value > 0.00 ";
		if( !empty( $idCompany ) ) {
			$query .= "AND i.idCompany = ? ";
			$fields[] = $idCompany;
		}
		$query .= "GROUP BY 1,2 ";
		$query .= "ORDER BY 2 DESC,1 ASC";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( $fields );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound client revenue month mappings: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueInboundCompanies( ) {
		$results = array();

		$query  = "SELECT ci.name AS name,ci.idCompany AS idCompany ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "GROUP BY 2 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound revenue companies: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueInboundFeeds( $idCompany ) {
		$results = array();

		$query  = "SELECT i.idFeedIn,i.description AS inDescription ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "WHERE i.idCompany = ? ";
		$query .= "GROUP BY 1 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $idCompany ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound revenue feeds: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueInboundURLs( $idFeedIn ) {
		$results = array();

		$query  = "SELECT url ";
		$query .= "FROM url_mapping ";
		$query .= "WHERE idFeedIn = ? ";
		$query .= "GROUP BY 1 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound revenue URLs: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueOutboundMappings( $date, $idCompany, $idFeedOut, $url ) {
		$results = array();
		$fields = array();
		$fields[] = $date;

		$query  = "SELECT m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,r.value AS revenue,MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
		$query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ";
		$query .= "LEFT JOIN stats_outbound s ON s.url = m.url AND s.idFeedOut = m.idFeedOut ";
		$query .= "LEFT JOIN revenue r ON r.idFeedOut = m.idFeedOut ";
		$query .= "AND m.url = r.url ";
		$query .= "AND r.date = ? ";
		$query .= "AND r.idFeedIn = 0 ";
		$query .= "WHERE 1=1 ";
		if( !empty( $idCompany ) ) {
			$query .= "AND o.idCompany = ? ";
			$fields[] = $idCompany;
		}
		if( !empty( $idFeedOut ) ) {
			$query .= "AND o.idFeedOut = ? ";
			$fields[] = $idFeedOut;
		}
		if( !empty( $url ) ) {
			$query .= "AND m.url = ? ";
			$fields[] = $url;
		}
		$query .= "GROUP BY 1,3 ";
		$query .= "ORDER BY 2,3,1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( $fields );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound revenue mappings: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueOutboundCompanies( ) {
		$results = array();

		$query  = "SELECT co.name AS name,co.idCompany AS idCompany ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedout i ON m.idFeedOut = i.idFeedOut ";
		$query .= "INNER JOIN companies co ON i.idCompany = co.idCompany ";
		$query .= "GROUP BY 2 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound revenue companies: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueOutboundFeeds( $idCompany ) {
		$results = array();

		$query  = "SELECT o.idFeedOut,o.description AS outDescription ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
		$query .= "INNER JOIN companies ci ON o.idCompany = ci.idCompany ";
		$query .= "WHERE o.idCompany = ? ";
		$query .= "GROUP BY 1 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $idCompany ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound revenue feeds: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getRevenueOutboundURLs( $idFeedOut ) {
		$results = array();

		$query  = "SELECT url ";
		$query .= "FROM url_mapping ";
		$query .= "WHERE idFeedOut = ? ";
		$query .= "GROUP BY 1 ";
		$query .= "ORDER BY 1 ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound revenue URLs: ' . $e->getMessage() );
		}

		return $results;
	}

	public function setRevenueValue( $date, $idFeedIn, $idFeedOut, $url, $value ) {

		try {
			$query = $this->db->prepare( "REPLACE INTO revenue( date, idFeedIn, idFeedOut, url, value ) VALUES( ?, ?, ?, ?, ? )" );
			$query->execute( array( $date, $idFeedIn, $idFeedOut, $url, $value ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update revenue value: ' . $e->getMessage() );
			return;
		}

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
			$query = $this->db->prepare( "SELECT o.timestamp,o.result,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord INNER JOIN feedout f ON f.idFeedOut = o.idFeedOut WHERE o.idFeedOut = ? AND o.processed = 1 AND o.result NOT LIKE CONCAT('%',f.successString,'%') ORDER BY o.timestamp DESC LIMIT " . intval( $offset ) . ",100" );
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

	public function getOutboundURLDates( $idFeedOut ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT url,MIN(stamp) AS date FROM stats_outbound WHERE idFeedOut = ? GROUP BY url" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound URL dates: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundURLStatsReport( $idFeedOut, $urlList, $breakdown, $dateStart, $dateEnd, $sort ) {
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

		$query .= "FROM stats_outbound ";
		$query .= "WHERE idFeedOut = ? ";
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
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound URL dates: ' . $e->getMessage() );
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

	public function checkURLNotifications( $idFeedIn, $url ) {
		$cnt = null;

		try {
			$query = $this->db->prepare( "SELECT COUNT(*) FROM notifications WHERE idFeedIn = ? AND url = ?" );
			$query->execute( array( $idFeedIn, $this->parseUrl( $url ) ) );
			$cnt = $query->fetchColumn();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check URL notification records: ' . $e->getMessage() );
			return $cnt;
		}

		return $cnt;
	}

	public function inboundEmailSearch( $email ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT i.*,f.label FROM data_inbound i INNER JOIN feedinc f ON i.idFeedIn = f.idFeedIn WHERE email = ?" );
			$query->execute( array( $email ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound email search results: ' . $e->getMessage() );
		}

		return $results;
	}

	public function globalEmailSearch( $email ) {
		$results = null;

		try {
			$query = $this->db->prepare( "SELECT MIN(timestamp) FROM data_inbound WHERE email = ?" );
			$query->execute( array( $email ) );
			$results = $query->fetchColumn( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound email search results: ' . $e->getMessage() );
		}

		return $results;
	}

	public function outboundEmailSearch( $email ) {
		$results = array();

		$query  = "SELECT i.*,o.idFeedOut,f.label ";
		$query .= "FROM data_inbound i ";
		$query .= "INNER JOIN data_outbound o ON o.idRecord = i.idRecord ";
		$query .= "LEFT JOIN feedout f ON o.idFeedOut = f.idFeedOut ";
		$query .= "WHERE i.email = ? ";

		try {
			$query = $this->db->prepare( $query );
			$query->execute( array( $email ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound email search results: ' . $e->getMessage() );
		}

		return $results;
	}

	public function checkInboundURLExists( $idFeedIn, $url ) {
		try {
			$query = $this->db->prepare( "SELECT COUNT(*) AS cnt FROM data_inbound WHERE url = ? AND idFeedIn = ?" );
			$query->execute( array( $this->parseUrl( $url ), $idFeedIn ) );
			if( $query && $query->fetchColumn() > 1 ) {
				return true;
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL exists results: ' . $e->getMessage() );
			return null;
		}

		return false;
	}

	public function inboundURLSearch( $url ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT f.label,s.idFeedIn,MAX(s.stamp) AS timestamp, SUM(s.accepted) AS cnt FROM stats_inbound s LEFT JOIN feedinc f ON f.idFeedIn = s.idFeedIn WHERE url = ? GROUP BY idFeedIn" );
			$query->execute( array( $url ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL search results: ' . $e->getMessage() );
		}

		return $results;
	}

	public function outboundURLSearch( $url ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT f.label,s.idFeedOut,MAX(s.stamp) AS timestamp,SUM(s.accepted) AS cnt FROM stats_outbound s LEFT JOIN feedout f ON f.idFeedOut = s.idFeedOut WHERE url = ? GROUP BY idFeedOut" );
			$query->execute( array( $url ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound URL search results: ' . $e->getMessage() );
		}

		return $results;
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
			$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idFeedOut = ? AND processed = 1 AND timestamp <= DATE_SUB(NOW(), INTERVAL 15 DAY) AND result NOT LIKE ?" );
			$query->execute( array( $idFeedOut, $success ) );
			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old data_outbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function clearOutboundQueue( $idFeedOut, $label ) {
		if( LEGACY_DB ) {
			$this->lockTables( "feedout WRITE, data_outbound WRITE, " . $this->quoteIdentifier( 'feedout_' . $label ) . " WRITE" );
		} else {
			$this->lockTables( "feedout WRITE, data_outbound WRITE" );
		}

		try {
			$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idFeedOut = ? AND processed = 0" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete queued records (1): ' . $e->getMessage() );
			return;
		}

		if( LEGACY_DB ) {
			try {
				$query = $this->db->prepare( "DELETE FROM " . $this->quoteIdentifier( 'feedout_' . $label ) . " WHERE processed = '0'" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to delete queued records (2): ' . $e->getMessage() );
				return;
			}
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = 0 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete queued records (3): ' . $e->getMessage() );
			return;
		}

		$this->unlockTables();
	}

	public function exportInboundRecords( $idFeedIn, $settings ) {

		$result = array(
			'success' => false,
			'reason' => 'None.',
			'fileLink' => null,
		);

		$feed = $this->getInboundFeed( $idFeedIn );
		if( !$feed ) {
			$result['reason'] = 'Not a valid incoming feed.';
			return;
		}

		$jobId = time();

		$fileLink = 'exports/' . $feed->label."_".$jobId.".csv";
		$filePath = ADMIN_ROOT . $fileLink;
		$file = fopen( $filePath, 'w' );
		if( !$file ) {
			$result['reason'] = 'Unable to create CSV file.';
			return;
		}

		fputcsv( $file, $settings['columns'] );

		try {

			$fields = array();

			$query  = "SELECT ";
			$comma = false;
			foreach( $settings['columns'] as $column ) {
				if( $comma ) {
					$query .= ', ';
				}
				$query .= $this->quoteIdentifier( $column );
				$comma = true;
			}
			$query .= " FROM data_inbound WHERE idFeedIn = ? ";
			$fields[] = $idFeedIn;

			if( !empty( $settings['dateStart'] ) && strtotime( $settings['dateStart'] ) !== FALSE ) {
				$query .= "AND timestamp >= ? ";
				$fields[] = date( 'Y-m-d', strtotime( $settings['dateStart'] ) ) . ' 00:00:00';
			}

			if( !empty( $settings['dateEnd'] ) && strtotime( $settings['dateEnd'] ) !== FALSE ) {
				$query .= "AND timestamp <= ? ";
				$fields[] = date( 'Y-m-d', strtotime( $settings['dateEnd'] ) ) . ' 23:59:59';
			}

			if( !empty( $settings['urlList'] ) && is_array( $settings['urlList'] ) ) {
				$orFlag = false;

				$query .= "AND (";
				foreach( $settings['urlList'] as $url ) {
					if( !empty( $url ) ) {
						if( $orFlag ) {
							$query .= " OR ";
						}
						$query .= "url LIKE ?";
						$fields[] = '%' . $url . '%';
						$orFlag = true;
					}
				}
				$query .= ")";
			}

			if( !empty( $settings['emailList'] ) && is_array( $settings['emailList'] ) ) {
				$orFlag = false;

				$query .= "AND (";
				foreach( $settings['emailList'] as $email ) {
					if( !empty( $email ) ) {
						if( $orFlag ) {
							$query .= " OR ";
						}
						$query .= "email LIKE ?";
						$fields[] = '%@' . $email;
						$orFlag = true;
					}
				}
				$query .= ")";
			}

			if( !empty( $settings['limit'] ) ) {
				$query .= "LIMIT " . intval( $settings['limit'] );
			}

			$query = $this->db->Prepare( $query );

			$query->execute( $fields );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fputcsv( $file, $row );
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export inbound records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );

        $this->auditLog( 'FEEDINC:EXPORT', $idFeedIn );

        $result['success'] = true;
        $result['reason'] = 'Successfully exported data to file.';
        $result['fileLink'] = $fileLink;

		return $result;
	}

	public function exportOutboundQueue( $idFeedOut ) {

		$feed = $this->getOutboundFeed( $idFeedOut );
		if( !$feed ) {
			return;
		}

		$jobId = time();

		$fileLink = 'exports/' . $feed->label."_".$jobId.".csv";
		$filePath = ADMIN_ROOT . $fileLink;
		$file = fopen( $filePath, 'w' );
		if( !$file ) {
			return;
		}

		fputcsv( $file, array(
			'url',
			'ip',
			'lead timestamp',
			'first name',
			'last name',
			'address',
			'addr2',
			'city',
			'state',
			'zip',
			'country',
			'dob',
			'gender',
			'landline',
			'cellphone',
		) );

		try {

			$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fputcsv( $file, array(
					$row['url'],
					$row['ip'],
					$row['leadstamp'],
					$row['fname'],
					$row['lname'],
					$row['addr'],
					$row['addr2'],
					$row['city'],
					$row['state'],
					$row['zip'],
					$row['country'],
					$row['dob'],
					$row['gender'],
					$row['landline'],
					$row['cellphone'],
				) );

				$this->outboundProcess( $row['idRecord'], $idFeedOut, $row['url'], null );

			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export queued records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );
	}

	public function getOutboundQueue( $idFeedOut ) {

		$feed = $this->getOutboundFeed( $idFeedOut );
		if( !$feed ) {
			return;
		}

		try {

			if( LEGACY_DB ) {
				$query = $this->db->prepare( "SELECT *,urlTrim AS url,stamp AS leadstamp FROM " . $this->quoteIdentifier( 'feedout_' . $feed->label ) . " WHERE processed = '0' ORDER BY stamp DESC LIMIT 500" );
				$query->execute( );
			} else {
				$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? ORDER BY leadstamp DESC LIMIT 500" );
				$query->execute( array( $idFeedOut ) );
			}
			return $query;

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get queued records: ' . $e->getMessage() );
			return null;
		}

		return null;
	}

	public function exportRejected( $idFeedOut ) {

		$feed = $this->getOutboundFeed( $idFeedOut );
		if( !$feed ) {
			return;
		}

		$jobId = time();

		$fileLink = 'exports/' . $feed->label."_".$jobId.".csv";
		$filePath = ADMIN_ROOT . $fileLink;
		$file = fopen( $filePath, 'w' );
		if( !$file ) {
			return;
		}

		fputcsv( $file, array(
			'url',
			'ip',
			'lead timestamp',
			'first name',
			'last name',
			'address',
			'addr2',
			'city',
			'state',
			'zip',
			'country',
			'dob',
			'gender',
			'landline',
			'cellphone',
		) );

		try {

			$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE processed = 1 AND o.idFeedOut = ? AND o.result NOT LIKE ?" );
			$query->execute( array( '%' . $feed->successString . '%' ) );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fputcsv( $file, array(
					$row['url'],
					$row['ip'],
					$row['leadstamp'],
					$row['fname'],
					$row['lname'],
					$row['addr'],
					$row['addr2'],
					$row['city'],
					$row['state'],
					$row['zip'],
					$row['country'],
					$row['dob'],
					$row['gender'],
					$row['landline'],
					$row['cellphone'],
				) );

				$this->outboundProcess( $row['idRecord'], $idFeedOut, $row['url'], null );
				$q_query = $this->db->prepare( "UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?" );
				$q_query->execute( array( $idFeedOut ) );

			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get rejected records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );
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

	public function exportSuppressions( $idCompany ) {
		$result = array();

		$result['file'] = 'exports/suppression_'.$idCompany."_".time().".csv";
		$filePath = ADMIN_ROOT . $result['file'];
		$fh = fopen( $filePath, 'w' );
		if( !$fh ) {
			$result['reason'] = 'Failed to create CSV file.';
			return $result;
		}

		try {
			$query = $this->db->prepare( "SELECT email FROM " . $this->quoteIdentifier( 'suppression_' . $idCompany ) );
			$query->execute( array( $idCompany ) );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fwrite( $fh, $row['email'] . PHP_EOL );
			}
			$result['reason'] = 'Success';
		} catch( PDOException $e ) {
			$result['reason'] = 'DB query error.';
			$this->logError( 'Unable to get get supression records for export: ' . $e->getMessage() );
		}

		fclose( $fh );
		return $result;
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
			$this->lockTables( "feedout WRITE, data_outbound WRITE" );

			$this->db->query( "UPDATE feedout SET queued = 0" );

			$query = $this->db->prepare( "SELECT idFeedOut,COUNT(*) AS cnt FROM data_outbound WHERE processed = 0 GROUP BY idFeedOut" );
			$query->execute( );
			$rows = $query->fetchAll();

			foreach( $rows as $row ) {
				print "Setting queued to {$row['cnt']} for ID: {$row['idFeedOut']}\n";
				$query = $this->db->prepare( "UPDATE feedout SET queued = ? WHERE idFeedOut = ?" );
				$query->execute( array( $row['cnt'], $row['idFeedOut'] ) );
			}

			$this->unlockTables();
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
				'stamp' => date( 'Y-m-d H:i:s' ),
			), false );
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
