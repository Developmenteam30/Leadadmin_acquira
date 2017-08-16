<?php

require_once( 'c_config.php' );

class Leads_PDOException extends PDOException {
}

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

	public function __construct() {

		// Connect to the database
		try {
			$this->db = new PDO( 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME, $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p'], array( \PDO::ATTR_PERSISTENT => true ) );
			$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		} catch( PDOException $e ) {

			$this->logError( 'Database connection error: ' . $e->getMessage() );
			print "Error connecting to the database";
			die();

		}
	}

	public function parseUrl( $url ) {
		if( empty( $url ) ) {
			return '';
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
			throw new Leads_PDOException( 'Unable to insert record', null, $e );
		}

		return null;
	}

	private function replaceRow( $table, array $data, $logError = true ) {
		$cols = array();
		$vals = array();

		foreach ( $data as $col => $val ) {
			$cols[] = $this->quoteIdentifier( $col );
			$vals[] = '?';
		}

		try {
			$query = $this->db->prepare( 'REPLACE INTO ' . $this->quoteIdentifier( $table ) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')' );
			$query->execute( array_values( $data ) );
			return $this->db->lastInsertId();
		} catch( PDOException $e ) {
			throw new Leads_PDOException( 'Unable to replace record', null, $e );
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
			throw new Leads_PDOException( 'Unable to update table', null, $e );
		}

		return null;
	}

	public function setBufferedQuery() {
		$this->db->setAttribute( PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false );
	}

	public function lockTables( $tables ) {
		if( !empty( $tables ) ) {
			try {
				$this->db->query( "LOCK TABLES " . $tables );
			} catch( PDOException $e ) {
				throw new Leads_PDOException( 'Unable to lock tables', null, $e );
			}
		}
	}

	public function unlockTables() {
		try {
			$this->db->query( "UNLOCK TABLES" );
		} catch( PDOException $e ) {
			throw new Leads_PDOException( 'Unable to unlock tables', null, $e );
		}
	}

	public function getConfiguration( $config_key ) {
		$value = null;

		$defaults = array(
			'notify_interval_1' => 12,
			'notify_interval_2' => 24,
		);

		try {
			$query = $this->db->prepare( "SELECT config_value FROM configuration WHERE config_key = ?" );
			$query->execute( array( $config_key ) );
			$value = $query->fetchColumn();

			// If the value was not found in the database, check the hard-coded defaults
			if( $value === false ) {
				if( isset( $defaults[$config_key] ) ) {
					$value = $defaults[$config_key];
				} else {
					$value = null;
				}
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get configuration value for (' . $config_key . '): ' . $e->getMessage() );
		}

		return $value;
	}

	public function addUser( $username, $password, $fullName, $idCompany, $level, $email ) {

		try {
			$idUser = $this->insertRow( 'users', array(
				'username' => $username,
				'fullName' => $fullName,
				'idCompany' => $idCompany,
				'level' => $level,
				'email' => $email,
			) );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add user: ' . $pdoException->getMessage() );
		}

		$this->setPasswordHash( $username, $password );

		return $idUser;
	}

	public function updateUser( $idUser, $fields ) {

		try {
			$status = $this->update( 'users', $fields, array(
				'idUser' => $idUser,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update user: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getUser( $idUser ) {
		try {
			$query = $this->db->prepare( "SELECT idUser,username,password,fullName,idCompany,level,email FROM users WHERE idUser = ?" );
			$query->execute( array( $idUser ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get user information (idUser): ' . $e->getMessage() );
		}

		return $results;
	}

	public function getUsername( $username ) {
		try {
			$query = $this->db->prepare( "SELECT username,password,fullName,idCompany,level FROM users WHERE username = ?" );
			$query->execute( array( $username ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get user information (username): ' . $e->getMessage() );
		}

		return $results;
	}

	public function getUsers() {
		$results = null;

		try {
			$query = $this->db->prepare( "SELECT idUser,username,fullName,idCompany,level FROM users ORDER BY username" );
			$query->execute();
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get user list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getStaffUsers( $format = PDO::FETCH_KEY_PAIR ) {
		$results = null;

		try {
			if( LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
				$query = $this->db->prepare( "SELECT idUser,fullName FROM users WHERE level = ? ORDER BY username" );
				$query->execute( array( LEADS_SESSION_LEVEL_STAFF ) );
			} else {
				$query = $this->db->prepare( "SELECT idUser,fullName FROM users WHERE idUser = ?" );
				$query->execute( array( LeadsSession::getUserId() ) );
			}
			$results = $query->fetchAll( $format );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get user staff list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function findClientUser( $idCompany ) {
		try {
			$query = $this->db->prepare( "SELECT username FROM users WHERE idCompany = ? AND level = ?" );
			$query->execute( array( $idCompany, LEADS_SESSION_LEVEL_CLIENT_REPORTS ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get client user information: ' . $e->getMessage() );
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
			$this->logError( 'Unable to set password hash: ' . $e->getMessage() );
		}
	}

	public function auditLog( $action, $notes = null ) {

		require_once( INCLUDES . 'session.php' );
		try {
			$this->insertRow( 'auditlog', array(
				'userId' => LeadsSession::getUserId(),
				'ipaddress' => $_SERVER['REMOTE_ADDR'], 
				'action' => $action,
				'notes' => $notes,
			) );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add audit log: ' . $pdoException->getMessage() );
		}
	}

	public function getAuditLog() {
		try {
			$query = $this->db->prepare( "SELECT a.logId,a.timestamp,a.ipaddress,a.userId,u.username,a.action,a.notes FROM auditlog a LEFT JOIN users u ON a.userId = u.idUser WHERE a.timestamp >= DATE_SUB(NOW(),INTERVAL 60 DAY) ORDER BY a.logId DESC" );
			$query->execute( );
			return $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get auditlog: ' . $e->getMessage() );
			return null;
		}

		return null;
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

	public function addLedger( $fields ) {

		$ledgerId = null;

		try {
			$ledgerId = $this->insertRow( 'ledger', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return $ledgerId;
	}

	public function addOfflineLedger( $fields ) {

		$ledgerId = null;

		try {
			$ledgerId = $this->insertRow( 'ledger_offline', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add offline ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return $ledgerId;
	}

	public function deleteLedger( $ledgerId ) {

		try {
			$query = $this->db->prepare( "DELETE FROM ledger WHERE ledgerId = ?" );
			$query->execute( array( $ledgerId ) );
		} catch( Leads_PDOException $e ) {
			$this->logError( 'Unable to delete ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return true;
	}

	public function deleteOfflineLedger( $ledgerId ) {

		try {
			$query = $this->db->prepare( "DELETE FROM ledger_offline WHERE ledgerId = ?" );
			$query->execute( array( $ledgerId ) );
		} catch( Leads_PDOException $e ) {
			$this->logError( 'Unable to delete offline ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return true;
	}

	public function deletePhoneLedger( $ledgerId ) {

		try {
			$query = $this->db->prepare( "DELETE FROM ledger_phones WHERE ledgerId = ?" );
			$query->execute( array( $ledgerId ) );
		} catch( Leads_PDOException $e ) {
			$this->logError( 'Unable to delete phones ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return true;
	}

	public function updateLedger( $ledgerId, $fields ) {

		try {
			$status = $this->update( 'ledger', $fields, array(
				'ledgerId' => $ledgerId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update ledger: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function updateOfflineLedger( $ledgerId, $fields ) {

		try {
			$status = $this->update( 'ledger_offline', $fields, array(
				'ledgerId' => $ledgerId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update offline ledger: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function addPhoneLedger( $fields ) {

		$ledgerId = null;

		try {
			$ledgerId = $this->insertRow( 'ledger_phones', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add phone ledger entry: ' . $pdoException->getMessage() );
			return null;
		}

		return $ledgerId;
	}

	public function updatePhoneLedger( $ledgerId, $fields ) {

		try {
			$status = $this->update( 'ledger_phones', $fields, array(
				'ledgerId' => $ledgerId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update phones ledger: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function addPhoneLedgerVendor( $fields ) {

		$ledgerId = null;

		try {
			$ledgerId = $this->insertRow( 'ledger_phones_vendors', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add phone ledger vendor entry: ' . $pdoException->getMessage() );
			return null;
		}

		return $ledgerId;
	}

	public function replacePhoneLedgerVendor( $fields ) {

		$ledgerId = null;

		try {
			$ledgerId = $this->replaceRow( 'ledger_phones_vendors', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to replace phone ledger vendor entry: ' . $pdoException->getMessage() );
			return null;
		}

		return $ledgerId;
	}

	public function updatePhoneLedgerVendor( $ledgerId, $indexId, $fields ) {

		try {
			$status = $this->update( 'ledger_phones_vendors', $fields, array(
				'ledgerId' => $ledgerId,
				'indexId' => $indexId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update phones vendor ledger: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}


	public function getLedgerById( $ledgerId ) {
		$results = null;
		$params = array();

		$sql = "SELECT * FROM ledger WHERE ledgerId = ? ";
		$params[] = $ledgerId;
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND userId = ? ";
			$params[] = LeadsSession::getUserId();
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetch( PDO::FETCH_OBJ  );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get ledger entry: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOfflineLedgerById( $ledgerId ) {
		$results = null;
		$params = array();

		$sql = "SELECT * FROM ledger_offline WHERE ledgerId = ? ";
		$params[] = $ledgerId;
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( userId1 = ? OR userId2 = ? ) ";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetch( PDO::FETCH_OBJ  );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get offline ledger entry: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getPhoneLedgerById( $ledgerId ) {
		$results = null;
		$params = array();

		$sql  = "SELECT l.*,";
		for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
			$sql .= sprintf( "lv%d.vendorCompanyId AS vendorCompanyId%d,lv%d.loInvoiceNum AS loInvoiceNum%d,lv%d.loInvoiceAmount AS loInvoiceAmount%d,lv%d.loPaymentDate AS loPaymentDate%d,lv%d.loPaymentMethod AS loPaymentMethod%d,lv%d.loPaymentAmount AS loPaymentAmount%d,",
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i,
				$i
			);
		}
		$sql .= "1 AS dummy ";
		$sql .= "FROM ledger_phones l ";
		for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
			$sql .= sprintf( "LEFT JOIN ledger_phones_vendors lv%d ON l.ledgerId = lv%d.ledgerId AND lv%d.indexId = %d ",
				$i,
				$i,
				$i,
				$i
			);
		}
		$sql .= "WHERE l.ledgerId = ? ";
		$params[] = $ledgerId;
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( userId1 = ? OR userId2 = ? ) ";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetch( PDO::FETCH_OBJ  );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get phone ledger entry: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getPhoneLedgerByIdIndex( $ledgerId, $indexId ) {
		$results = null;
		$params = array();

		$sql  = "SELECT l.*,lv.vendorCompanyId AS vendorCompanyId,lv.loInvoiceNum AS loInvoiceNum,lv.loInvoiceAmount AS loInvoiceAmount,lv.loPaymentDate AS loPaymentDate,lv.loPaymentMethod AS loPaymentMethod,lv.loPaymentAmount AS loPaymentAmount ";
		$sql .= "FROM ledger_phones l ";
		$sql .= "LEFT JOIN ledger_phones_vendors lv ON l.ledgerId = lv.ledgerId AND lv.indexId = ? ";
		$params[] = $indexId;
		$sql .= "WHERE l.ledgerId = ? ";
		$params[] = $ledgerId;
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( userId1 = ? OR userId2 = ? )";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetch( PDO::FETCH_OBJ  );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get phone ledger index entry: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getLedgerByDivision( $divisionId, $type ) {
		$results = array();

		$sql  = "SELECT l.*,c.name AS companyName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
		$sql .= "FROM ledger l ";
		$sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
		$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
		$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
		$sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
		$sql .= "WHERE l.divisionId = ? ";
		$sql .= "AND l.type = ? ";
		$sql .= "ORDER BY l.ledgerMonth,companyName";

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( array( $divisionId, $type ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get ledger: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getLedger( $type, $onlyMonths = false, $month = null ) {
		$results = array();
		$params = array();

		if( !empty( $onlyMonths ) ) {
			$sql  = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
		} else {
			$sql  = "SELECT l.*,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,c.name AS companyName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
		}
		$sql .= "FROM ledger l ";
		$sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
		$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
		$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
		$sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
		$sql .= "WHERE l.type = ? ";
		$params[] = $type;
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? )";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}
		if( !empty( $month ) ) {
			if( strlen( $month ) == 4 ) {
				$sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
				$params[] = $month;
			} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $month, $matches ) ) {
				$sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
				$params[] = $matches[1] . $matches[2];
			} else {
				$sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
				$params[] = $month;
			}
		}
		if( !empty( $onlyMonths ) ) {
			$sql .= "GROUP BY l.ledgerMonth ";
			$sql .= "ORDER BY l.ledgerMonth DESC";
		} else {
			$sql .= "ORDER BY l.ledgerMonth,companyName";
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get ledger: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getPhoneLedger( $onlyMonths = false, $month = null ) {
		$results = array();
		$params = array();

		if( !empty( $onlyMonths ) ) {
			$sql  = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
		} else {
			$sql  = "SELECT l.*,CONCAT('O',l.ledgerId) AS entryId,";
			for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
				$sql .= sprintf( "vc%d.name AS vendorCompanyName%d,lv%d.loInvoiceNum AS loInvoiceNum%d,lv%d.loInvoiceAmount AS loInvoiceAmount%d,lv%d.loPaymentDate AS loPaymentDate%d,lv%d.loPaymentMethod AS loPaymentMethod%d,lv%d.loPaymentAmount AS loPaymentAmount%d,",
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i,
					$i
				);
			}
			$sql .= "cc.name AS clientCompanyName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
		}
		$sql .= "FROM ledger_phones l ";
		for( $i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++ ) {
			$sql .= sprintf( "LEFT JOIN ledger_phones_vendors lv%d ON l.ledgerId = lv%d.ledgerId AND lv%d.indexId = %d ",
				$i,
				$i,
				$i,
				$i
			);
			$sql .= sprintf( "LEFT JOIN companies vc%d ON lv%d.vendorCompanyId = vc%d.idCompany ",
				$i,
				$i,
				$i
			);
		}
		$sql .= "LEFT JOIN companies cc ON l.clientCompanyId = cc.idCompany ";
		$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
		$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
		$sql .= "WHERE 1=1 ";
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}
		if( !empty( $month ) ) {
			if( strlen( $month ) == 4 ) {
				$sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
				$params[] = $month;
			} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $month, $matches ) ) {
				$sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
				$params[] = $matches[1] . $matches[2];
			} else {
				$sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
				$params[] = $month;
			}
		}
		if( !empty( $onlyMonths ) ) {
			$sql .= "GROUP BY l.ledgerMonth ";
			$sql .= "ORDER BY l.ledgerMonth DESC";
		} else {
			$sql .= "GROUP BY l.ledgerId ";
			$sql .= "ORDER BY l.ledgerMonth";
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get phone ledger: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOfflineLedger( $onlyMonths = false, $month = null ) {
		$results = array();
		$params = array();

		if( !empty( $onlyMonths ) ) {
			$sql  = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
		} else {
			$sql  = "SELECT l.*,CONCAT('O',l.ledgerId) AS entryId,vc.name AS vendorCompanyName,cc.name AS clientCompanyName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
		}
		$sql .= "FROM ledger_offline l ";
		$sql .= "LEFT JOIN companies vc ON l.vendorCompanyId = vc.idCompany ";
		$sql .= "LEFT JOIN companies cc ON l.clientCompanyId = cc.idCompany ";
		$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
		$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
		$sql .= "WHERE 1=1 ";
		if( !LeadsSession::isValid( LEADS_SESSION_LEVEL_ADMIN ) ) {
			$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? )";
			$params[] = LeadsSession::getUserId();
			$params[] = LeadsSession::getUserId();
		}
		if( !empty( $month ) ) {
			if( strlen( $month ) == 4 ) {
				$sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
				$params[] = $month;
			} else if( preg_match( '/^(20[0-9]{2})-Q([1-4])$/', $month, $matches ) ) {
				$sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
				$params[] = $matches[1] . $matches[2];
			} else {
				$sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
				$params[] = $month;
			}
		}
		if( !empty( $onlyMonths ) ) {
			$sql .= "GROUP BY l.ledgerMonth ";
			$sql .= "ORDER BY l.ledgerMonth DESC";
		} else {
			$sql .= "ORDER BY l.ledgerMonth,vendorCompanyName";
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get offline ledger: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getPaidLedger( $type, $userId = null, $distinctColumn = null, $distinctValue = null ) {
		$results = array();
		$params = array();

		if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
			$sql  = "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
		} else {
			$sql  = "SELECT l.ledgerId,l.divisionId,l.companyId,l.verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.type,l.userId1,l.userId2,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,c.name AS companyName,d.name AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,'ledger' AS source,0 AS indexId ";
		}
		$sql .= "FROM ledger l ";
		$sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
		$sql .= "LEFT JOIN divisions d ON l.divisionId = d.divisionId ";
		$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
		$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
		$sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
		$sql .= "WHERE 1=1 ";
		if( $type !== null ) {
			$sql .= "AND type = ? ";
			$params[] = $type;
		}
		if( !empty( $userId ) ) {
			$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
			$params[] = $userId;
			$params[] = $userId;
		} else {
			$sql .= "AND l.paymentDate IS NOT NULL ";
			$sql .= "AND l.paymentAmount IS NOT NULL ";
			$sql .= "AND l.paymentMethod IS NOT NULL ";
		}
		if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
			$sql .= "AND " . $distinctColumn . " = ? ";
			$params[] = $distinctValue;
		}

		if( $type === 0 ) {

			$sql .= "UNION ";

			if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
				$sql .= "SELECT DISTINCT(" . str_replace( 'ledgerMonth', "CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01')", $distinctColumn ) . ") AS month ";
			} else {
				$sql .= "SELECT r.date as ledgerId,1 AS divisionId,c.idCompany AS companyId,5 AS verticalId,i.paymentDate,'ACH' AS paymentMethod,CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01') AS ledgerMonth,ROUND(SUM(r.value)*0.50,2) AS invoiceAmount,i.invoiceNumber AS invoiceNum,ROUND(SUM(r.value)*0.50,2) AS paymentAmount,NULL AS commissionAmount1,NULL AS commissionAmount2,NULL AS commissionDate1,NULL AS commissionDate2,0 AS type,u.idUser AS userId1,NULL AS userId2,CONCAT('E',r.date) AS entryId,c.name AS companyName,'E-mail' AS divisionName,'Email Marketing' AS verticalName,u.fullName AS fullname1,NULL AS fullName2,'email' AS source,0 AS indexId ";
			}
			$sql .= "FROM url_mapping m ";
			$sql .= "INNER JOIN feedinc fi ON m.idFeedIn = fi.idFeedIn ";
			$sql .= "INNER JOIN companies c ON fi.idCompany = c.idCompany ";
			$sql .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
			$sql .= "AND m.url = r.url ";
			$sql .= "AND m.idFeedOut = r.idFeedOut ";
			$sql .= "LEFT JOIN invoices i ON i.date = r.date AND i.idCompany = c.idCompany ";
			$sql .= "LEFT JOIN users u ON i.userId = u.idUser ";
			$sql .= "WHERE r.value IS NOT NULL ";
			$sql .= "AND r.value > 0.00 ";
			$sql .= "AND r.date >= '201601' ";
			if( !empty( $userId ) ) {
				$sql .= "AND i.userId = ? ";
				$params[] = $userId;
			} else {
				$sql .= "AND i.paymentDate IS NOT NULL ";
			}
			if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
				$sql .= "AND " . str_replace( 'ledgerMonth', "CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01')", $distinctColumn ) . " = ? ";
				$params[] = $distinctValue;
			}
			$sql .= "GROUP BY c.idCompany,r.date ";

			$sql .= "UNION ";

			if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
				$sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
			} else {
				$sql .= "SELECT l.ledgerId,4 AS divisionId,c.idCompany AS companyId,6 AS verticalId,l.loPaymentDate AS paymentDate,l.loPaymentMethod AS paymentMethod,l.ledgerMonth,l.loInvoiceAmount AS invoiceAmount,l.loInvoiceNum AS invoiceNum,l.loPaymentAmount AS paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,0 AS type,l.userId1,l.userId2,CONCAT('O',l.ledgerId) AS entryId,c.name AS companyName,'Offline' AS divisionName,'Offline Vertical' AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,'ledger_offline' AS source,0 AS indexId ";
			}
			$sql .= "FROM ledger_offline l ";
			$sql .= "LEFT JOIN companies c ON l.vendorCompanyId = c.idCompany ";
			$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
			$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $userId ) ) {
				$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
				$params[] = $userId;
				$params[] = $userId;
			} else {
				$sql .= "AND l.loPaymentDate IS NOT NULL ";
				$sql .= "AND l.loPaymentAmount IS NOT NULL ";
				$sql .= "AND l.loPaymentMethod IS NOT NULL ";
			}
			if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
				$sql .= "AND " . str_replace( 'paymentDate', 'loPaymentDate', $distinctColumn ) . " = ? ";
				$params[] = $distinctValue;
			}

			$sql .= "UNION ";

			if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
				$sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
			} else {
				$sql .= "SELECT l.ledgerId,5 AS divisionId,c.idCompany AS companyId,l.verticalId,lv.loPaymentDate AS paymentDate,lv.loPaymentMethod AS paymentMethod,l.ledgerMonth,lv.loInvoiceAmount AS invoiceAmount,lv.loInvoiceNum AS invoiceNum,lv.loPaymentAmount AS paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,0 AS type,l.userId1,l.userId2,CONCAT('L',l.ledgerId,'-',lv.indexId) AS entryId,c.name AS companyName,'Leads' AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,'ledger_phones' AS source,lv.indexId ";
			}
			$sql .= "FROM ledger_phones l ";
			$sql .= "LEFT JOIN ledger_phones_vendors lv ON l.ledgerId = lv.ledgerId ";
			$sql .= "LEFT JOIN companies c ON lv.vendorCompanyId = c.idCompany ";
			$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
			$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
			$sql .= "LEFT JOIN verticals v ON divisionId = 5 AND l.verticalId = v.verticalId ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $userId ) ) {
				$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
				$params[] = $userId;
				$params[] = $userId;
			} else {
				$sql .= "AND lv.loPaymentDate IS NOT NULL ";
				$sql .= "AND lv.loPaymentAmount IS NOT NULL ";
				$sql .= "AND lv.loPaymentMethod IS NOT NULL ";
			}
			if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
				$sql .= "AND " . str_replace( 'paymentDate', 'loPaymentDate', $distinctColumn ) . " = ? ";
				$params[] = $distinctValue;
			}

		} else {

			$sql .= "UNION ";

			if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
				$sql .= "SELECT DISTINCT(" . $distinctColumn . ") as month ";
			} else {
				$sql .= "SELECT l.ledgerId,4 AS divisionId,c.idCompany AS companyId,6 AS verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,1 AS type,l.userId1,l.userId2,CONCAT('O',l.ledgerId) AS entryId,c.name AS companyName,'Offline' AS divisionName,'Offline Vertical' AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,'ledger_offline' AS source,0 AS indexId ";
			}
			$sql .= "FROM ledger_offline l ";
			$sql .= "LEFT JOIN companies c ON l.clientCompanyId = c.idCompany ";
			$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
			$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $userId ) ) {
				$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
				$params[] = $userId;
				$params[] = $userId;
			} else {
				$sql .= "AND l.paymentDate IS NOT NULL ";
				$sql .= "AND l.paymentAmount IS NOT NULL ";
				$sql .= "AND l.paymentMethod IS NOT NULL ";
			}
			if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
				$sql .= "AND " . $distinctColumn . " = ? ";
				$params[] = $distinctValue;
			}

			$sql .= "UNION ";

			if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
				$sql .= "SELECT DISTINCT(" . $distinctColumn . ") as month ";
			} else {
				$sql .= "SELECT l.ledgerId,5 AS divisionId,c.idCompany AS companyId,l.verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,1 AS type,l.userId1,l.userId2,CONCAT('L',l.ledgerId) AS entryId,c.name AS companyName,'Leads' AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,'ledger_phones' AS source,0 AS indexId ";
			}
			$sql .= "FROM ledger_phones l ";
			$sql .= "LEFT JOIN companies c ON l.clientCompanyId = c.idCompany ";
			$sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
			$sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
			$sql .= "LEFT JOIN verticals v ON divisionId = 5 AND l.verticalId = v.verticalId ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $userId ) ) {
				$sql .= "AND ( l.userId1 = ? OR l.userId2 = ? ) ";
				$params[] = $userId;
				$params[] = $userId;
			} else {
				$sql .= "AND l.paymentDate IS NOT NULL ";
				$sql .= "AND l.paymentAmount IS NOT NULL ";
				$sql .= "AND l.paymentMethod IS NOT NULL ";
			}
			if( !empty( $distinctColumn ) && !empty( $distinctValue ) ) {
				$sql .= "AND " . $distinctColumn . " = ? ";
				$params[] = $distinctValue;
			}

		}

		if( !empty( $distinctColumn ) && empty( $distinctValue ) ) {
			$sql .= "GROUP BY month ";
			$sql .= "ORDER BY month DESC ";
		} else {
			$sql .= "ORDER BY paymentDate ";
		}

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get ledger list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addOpportunity( $fields ) {

		$opportunityId = null;

		try {
			$opportunityId = $this->insertRow( 'opportunities', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add opportunity: ' . $pdoException->getMessage() );
			return null;
		}

		return $opportunityId;
	}

	public function updateOpportunity( $opportunityId, $fields ) {

		try {
			$status = $this->update( 'opportunities', $fields, array(
				'opportunityId' => $opportunityId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update opportunity: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getOpportunities( $status = null ) {
		$results = array();
		$params = array();

		try {
			$sql  = "SELECT o.*,c.name AS companyName,d.name AS divisionName,u.fullName,MAX(timestamp) AS lastDate ";
			$sql .= "FROM opportunities o ";
			$sql .= "LEFT JOIN companies c ON o.companyId = c.idCompany ";
			$sql .= "LEFT JOIN users u ON o.userId = u.idUser ";
			$sql .= "LEFT JOIN divisions d ON o.divisionId = d.divisionId ";
			$sql .= "LEFT JOIN opportunities_notes n ON o.opportunityId = n.opportunityId ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $status ) && 'active' == $status ) {
				$sql .= "AND o.status != 'retired' ";
			} else if( !empty( $status ) ) {
				$sql .= "AND o.status = ? ";
				$params[] = $status;
			}
			$sql .= "GROUP BY o.opportunityId ";
			$sql .= "ORDER BY o.opportunityId DESC";

			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get opportunity list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOpportunity( $opportunityId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM opportunities WHERE opportunityId = ?" );
			$query->execute( array( $opportunityId ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get opportunity info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addOpportunityNote( $fields ) {

		$noteId = null;

		try {
			$noteId = $this->insertRow( 'opportunities_notes', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add opportunity note: ' . $pdoException->getMessage() );
			return false;
		}

		return $noteId;
	}

	public function getOpportunityNotes( $opportunityId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT n.*,u.fullName FROM opportunities_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE opportunityId = ? ORDER BY timestamp DESC" );
			$query->execute( array( $opportunityId ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get opportunity notes: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addProspect( $fields ) {

		$prospectId = null;

		try {
			$prospectId = $this->insertRow( 'prospects', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add prospect: ' . $pdoException->getMessage() );
			return null;
		}

		return $prospectId;
	}

	public function updateProspect( $prospectId, $fields ) {

		try {
			$status = $this->update( 'prospects', $fields, array(
				'prospectId' => $prospectId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update prospect: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getProspects( $status = null ) {
		$results = array();
		$params = array();

		try {
			$sql  = "SELECT p.*,u.fullName,MAX(timestamp) AS lastDate ";
			$sql .= "FROM prospects p ";
			$sql .= "LEFT JOIN users u ON p.userId = u.idUser ";
			$sql .= "LEFT JOIN prospects_notes n ON p.prospectId = n.prospectId ";
			$sql .= "WHERE 1=1 ";
			if( !empty( $status ) && 'active' == $status ) {
				$sql .= "AND p.status != 'retired' ";
			} else if( !empty( $status ) ) {
				$sql .= "AND p.status = ? ";
				$params[] = $status;
			}
			$sql .= "GROUP BY p.prospectId ";
			$sql .= "ORDER BY p.prospectId DESC";

			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get prospect list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getProspect( $prospectId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM prospects WHERE prospectId = ?" );
			$query->execute( array( $prospectId ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get prospect info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addProspectNote( $fields ) {

		$noteId = null;

		try {
			$noteId = $this->insertRow( 'prospects_notes', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add prospect note: ' . $pdoException->getMessage() );
			return false;
		}

		return $noteId;
	}

	public function getProspectNotes( $prospectId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT n.*,u.fullName FROM prospects_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE prospectId = ? ORDER BY timestamp DESC" );
			$query->execute( array( $prospectId ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get prospect notes: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addCompany( $fields ) {

		$companyId = null;

		try {
			$companyId = $this->insertRow( 'companies', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add company: ' . $pdoException->getMessage() );
			return null;
		}

		return $companyId;
	}

	public function updateCompany( $idCompany, $fields ) {

		try {
			$status = $this->update( 'companies', $fields, array(
				'idCompany' => $idCompany,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update company: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
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

	public function getCompanies( $status = null ) {
		$results = array();

		try {
			if( !empty( $status ) ) {
				$query = $this->db->prepare( "SELECT * FROM companies WHERE status = ? ORDER BY name" );
				$query->execute( array( $status ) );
			} else {
				$query = $this->db->prepare( "SELECT * FROM companies ORDER BY name" );
				$query->execute( );
			}
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getCompanyDivisions( $companyId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT divisionId FROM companies_divisions WHERE companyId = ?" );
			$query->execute( array( $companyId ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company division list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getDivisionCompanies( $divisionId, $companyId, $format = PDO::FETCH_KEY_PAIR ) {
		$results = array();

		try {
			if( !empty( $companyId ) ) {
				$query = $this->db->prepare( "SELECT c.idCompany,c.name FROM companies_divisions cd LEFT JOIN companies c ON c.idCompany = cd.companyId WHERE ( cd.divisionId = ? AND c.status = 'active' ) OR c.idCompany = ? ORDER BY c.name" );
				$query->execute( array( $divisionId, $companyId ) );
			} else {
				$query = $this->db->prepare( "SELECT c.idCompany,c.name FROM companies_divisions cd LEFT JOIN companies c ON c.idCompany = cd.companyId WHERE cd.divisionId = ? AND c.status = 'active' ORDER BY c.name" );
				$query->execute( array( $divisionId ) );
			}
			$results = $query->fetchAll( $format );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get division company list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addCompanyNote( $fields ) {

		$noteId = null;

		try {
			$noteId = $this->insertRow( 'companies_notes', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add company note: ' . $pdoException->getMessage() );
			return false;
		}

		return $noteId;
	}

	public function getCompanyNotes( $companyId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT n.*,u.fullName FROM companies_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE companyId = ? ORDER BY timestamp DESC" );
			$query->execute( array( $companyId ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company notes: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addVertical( $fields ) {

		$verticalId = null;

		try {
			$verticalId = $this->insertRow( 'verticals', $fields );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add vertical: ' . $pdoException->getMessage() );
			return null;
		}

		return $verticalId;
	}

	public function checkVerticalName( $name, $divisionId, $verticalId = null ) {
		$result = false;

		try {
			if( !empty( $verticalId ) ) {
				$query = $this->db->prepare( "SELECT 1 FROM verticals WHERE name = ? AND divisionId = ? AND verticalId != ?" );
				$query->execute( array( $name, $divisionId, $verticalId ) );
			} else {
				$query = $this->db->prepare( "SELECT 1 FROM verticals WHERE name = ? AND divisionId = ?" );
				$query->execute( array( $name, $divisionId ) );
			}
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check vertical name: ' . $e->getMessage() );
		}

		return $result;
	}

	public function getVertical( $verticalId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM verticals WHERE verticalId = ?" );
			$query->execute( array( $verticalId ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get Vertical info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function updateVertical( $verticalId, $fields ) {

		try {
			$status = $this->update( 'verticals', $fields, array(
				'verticalId' => $verticalId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update vertical: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getDivisionVerticals( $divisionId, $format = PDO::FETCH_KEY_PAIR ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT verticalId,name FROM verticals WHERE divisionId = ? ORDER BY name" );
			$query->execute( array( $divisionId ) );
			$results = $query->fetchAll( $format );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get division vertical list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getCompanyVerticals( $companyId ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT verticalId FROM companies_verticals WHERE companyId = ?" );
			$query->execute( array( $companyId ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get company vertical list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addCompanyVertical( $companyId, $verticalId ) {
		try {
			$query = $this->db->prepare( "REPLACE INTO companies_verticals(companyId, verticalId) VALUES(?, ?)" );
			$query->execute( array( $companyId, $verticalId ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to add company vertical mapping: ' . $e->getMessage() );
			return;
		}
	}

	public function clearCompanyVerticals( $companyId ) {
		try {
			$query = $this->db->prepare( "DELETE FROM companies_verticals WHERE companyId = ?" );
			$query->execute( array( $companyId ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to clear company verticals: ' . $e->getMessage() );
			return;
		}
	}

	public function addCompanyDivision( $companyId, $divisionId ) {
		try {
			$query = $this->db->prepare( "REPLACE INTO companies_divisions(companyId, divisionId) VALUES(?, ?)" );
			$query->execute( array( $companyId, $divisionId ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to add company division mapping: ' . $e->getMessage() );
			return;
		}
	}

	public function clearCompanyDivisions( $companyId ) {
		try {
			$query = $this->db->prepare( "DELETE FROM companies_divisions WHERE companyId = ?" );
			$query->execute( array( $companyId ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to clear company divisions: ' . $e->getMessage() );
			return;
		}
	}

	public function getDivisionName( $divisionId ) {
		$results = '';

		try {
			$query = $this->db->prepare( "SELECT name FROM divisions WHERE divisionId = ?" );
			$query->execute( array( $divisionId ) );
			$results = $query->fetchColumn();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get division: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getDivisions( $format = PDO::FETCH_KEY_PAIR ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT divisionId,name FROM divisions ORDER BY name" );
			$query->execute( );
			$results = $query->fetchAll( $format );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get division list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getCountries( $format = PDO::FETCH_KEY_PAIR ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT id,short_name FROM countries ORDER BY short_name" );
			$query->execute( );
			$results = $query->fetchAll( $format );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get country list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function addInboundFeed( $fields ) {

		if( empty( $fields['label'] ) ) {
			return null;
		}

		$this->db->beginTransaction();

		try {
			$idFeedIn = $this->insertRow( 'feedinc', $fields );
		} catch( Leads_PDOException $e ) {
			$this->db->rollBack();
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add inbound record: ' . $pdoException->getMessage() );
		}

		if( LEGACY_DB ) {
			try {
				$query = $this->db->prepare( "CREATE TABLE " . $this->quoteIdentifier( "feedinc_" . $fields['label'] ) . " LIKE feedinc_empty" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to create new inbound table: ' . $e->getMessage() );
				return null;
			}

			try {
				$query = $this->db->prepare( "CREATE TABLE " . $this->quoteIdentifier( "feedinc_" . $fields['label'] . "_invalid" ) . " LIKE feedinc_empty_invalid" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to create new inbound invalid table: ' . $e->getMessage() );
				return null;
			}
		}

		$this->db->commit();

		return $idFeedIn;
	}

	public function updateInboundFeed( $idFeedIn, $fields ) {
		try {
			$status = $this->update( 'feedinc', $fields, array(
				'idFeedIn' => $idFeedIn,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update inbound feed: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function renameInboundTables( $old, $new ) {

		if( LEGACY_DB ) {

			$this->db->beginTransaction();

			try {
				$query = $this->db->prepare( "RENAME TABLE " . $this->quoteIdentifier( "feedinc_" . $old ) . " TO " . $this->quoteIdentifier( "feedinc_" . $new ) );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to rename inbound table: ' . $e->getMessage() );
				return null;
			}

			try {
				$query = $this->db->prepare( "RENAME TABLE " . $this->quoteIdentifier( "feedinc_" . $old . "_invalid" ) . " TO " . $this->quoteIdentifier( "feedinc_" . $new . "_invalid" ) );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to rename inbound invalid table: ' . $e->getMessage() );
				return null;
			}

			$this->db->commit();

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
			return null;
		}

		return $results;
	}

	public function getInboundFeedLabel( $label ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedinc WHERE label = ?" );
			$query->execute( array( $label ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound feed info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundFeeds( $idCompany = null, $status = null, $feedCategory = null ) {
		$results = array();
		$params = array();

		$sql  = "SELECT f.*,c.name,MAX(n.timestamp) AS lastDate ";
		$sql .= "FROM feedinc f ";
		$sql .= "LEFT JOIN companies c ON f.idCompany = c.idCompany ";
		$sql .= "LEFT JOIN companies_notes n ON n.companyId = c.idCompany ";
		$sql .= "WHERE 1=1 ";
		if( !empty( $idCompany ) ) {
			$sql .= "AND c.idCompany = ? ";
			$params[] = $idCompany;
		}
		if( !empty( $status ) ) {
			$sql .= "AND f.status = ? ";
			$params[] = $status;
		}
		if( !empty( $feedCategory ) ) {
			$sql .= "AND ( f.feedCategory = 'both' OR f.feedCategory = ? ) ";
			$params[] = $feedCategory;
		}
		$sql .= "GROUP BY f.idFeedIn ";
		$sql .= "ORDER BY c.name,f.idFeedIn";

		try {

			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound feed list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function checkInboundFeedAccess( $idCompany, $idFeedIn ) {
		$result = false;

		try {
			$query = $this->db->prepare( "SELECT 1 FROM feedinc f LEFT JOIN companies c ON f.idCompany = c.idCompany WHERE c.idCompany = ? AND f.idFeedIn = ?" );
			$query->execute( array( $idCompany, $idFeedIn ) );
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound feed access: ' . $e->getMessage() );
		}

		return $result;
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

	public function getInboundPopulationSettings( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT fp.*, fo.label, fo.dailyLimit FROM feedPopulation fp LEFT JOIN feedout fo ON fp.idFeedOut = fo.idFeedOut WHERE fp.idFeedIn = ? AND fp.enabled = '1'" );
			$query->execute( array( $idFeedIn ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound population settings: ' . $e->getMessage() );
			return false;
		}

		return $results;
	}

	public function addOutboundFeed( $fields ) {

		if( empty( $fields['label'] ) ) {
			return null;
		}

		$this->db->beginTransaction();

		try {
			$idFeedOut = $this->insertRow( 'feedout', $fields );
		} catch( Leads_PDOException $e ) {
			$this->db->rollBack();
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add outbound feed: ' . $pdoException->getMessage() );
		}

		$this->db->commit();

		return $idFeedOut;
	}

	public function updateOutboundFeed( $idFeedOut, $fields ) {
		try {
			$status = $this->update( 'feedout', $fields, array(
				'idFeedOut' => $idFeedOut,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update outbound feed: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getPopulationStatus( $idFeedOut ) {
		$results = array();
		$status = 'Error';

		try {
			$query = $this->db->prepare( "SELECT enabled FROM feedPopulation WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );

			if( empty( $results ) ) {
				return 'No populations setup';
			} else {
				$enabled = 0;
				foreach( $results as $result ) {
					if( $result->enabled ) {
						$enabled++;
					}
				}
				if( $enabled === 0 ) {
					return 'Disabled';
				} else if( $enabled < sizeOf( $results ) ) {
					return 'Partially enabled';
				} else {
					return 'Enabled';
				}
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get population status: ' . $e->getMessage() );
			return 'Error';
		}

		return $status;
	}

	public function getPopulationSetting( $idAssoc ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedPopulation WHERE idAssoc = ?" );
			$query->execute( array( $idAssoc ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get feed population: ' . $e->getMessage() );
			return false;
		}

		return $results;
	}

	public function getPopulations( $idFeedOut ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT * FROM feedPopulation WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get feed populations: ' . $e->getMessage() );
			return false;
		}

		return $results;
	}

	public function addPopulation( $fields ) {

		if( empty( $fields['idFeedIn'] ) || empty( $fields['idFeedOut'] ) ) {
			return null;
		}

		try {
			$idAssoc = $this->insertRow( 'feedPopulation', $fields );
		} catch( Leads_PDOException $e ) {
			$this->db->rollBack();
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add population: ' . $pdoException->getMessage() );
		}

		return $idAssoc;
	}

	public function updatePopulation( $idAssoc, $fields ) {
		try {
			$status = $this->update( 'feedPopulation', $fields, array(
				'idAssoc' => $idAssoc,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update population: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function retireOutboundFeed( $idFeedOut ) {
		if( empty( $idFeedOut ) ) {
			return false;
		}

		$this->db->beginTransaction();

		try {
			$query = $this->db->prepare( "UPDATE feedout SET cron = '0', status = 'retired' WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to update feedout retired status: ' . $e->getMessage() );
			return false;
		}

		try {
			$query = $this->db->prepare( "UPDATE feedPopulation SET enabled = '0' WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to update feedPopulation retired status: ' . $e->getMessage() );
			return false;
		}

		$this->db->commit();

		return true;
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

	public function getOutboundFeedPopulation( $idAssoc ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT p.*,o.* FROM feedPopulation p LEFT JOIN feedout o ON o.idFeedOut = p.idFeedOut WHERE idAssoc = ?" );
			$query->execute( array( $idAssoc ) );
			$results = $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feed population info: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundFeeds( $idCompany = null, $status = null, $feedCategory = null ) {
		$results = array();
		$params = array();

		$sql  = "SELECT o.*,co.name,MAX(n.timestamp) AS lastDate ";
		$sql .= "FROM feedout o ";
		$sql .= "LEFT JOIN feedPopulation p ON p.idFeedOut = o.idFeedOut ";
		$sql .= "LEFT JOIN feedinc i ON i.idFeedIn = p.idFeedIn ";
		$sql .= "LEFT JOIN companies ci ON ci.idCompany = i.idCompany ";
		$sql .= "LEFT JOIN companies co ON co.idCompany = o.idCompany ";
		$sql .= "LEFT JOIN companies_notes n ON n.companyId = co.idCompany ";
		$sql .= "WHERE 1=1 ";
		if( !empty( $idCompany ) ) {
			$sql .= "AND ci.idCompany = ? ";
			$params[] = $idCompany;
		}
		if( !empty( $status ) ) {
			$sql .= "AND o.status = ? ";
			$params[] = $status;
		}
		if( !empty( $feedCategory ) ) {
			$sql .= "AND ( o.feedCategory = 'both' OR o.feedCategory = ? ) ";
			$params[] = $feedCategory;
		}
		$sql .= "GROUP BY o.idFeedOut ";
		$sql .= "ORDER BY co.name,o.idFeedOut";

		try {
			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feed list: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundFeedsCron( $mod = null ) {

		$results = null;

		try {
			if( !empty( $mod ) ) {
				$query = $this->db->prepare( "SELECT idFeedOut,queued FROM feedout WHERE cron = '1' AND queued > 0 AND status IN( 'active', 'hidden' ) AND MOD(idFeedOut,2) = ?" );
				$query->execute( array( 'even' === $mod ? 0 : 1 ) );
			} else {
				$query = $this->db->prepare( "SELECT idFeedOut,queued FROM feedout WHERE cron = '1' AND queued > 0 AND status IN( 'active', 'hidden' )" );
				$query->execute();
			}
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feeds cron: ' . $e->getMessage() );
		}

		return $results;

	}

	public function checkOutboundFeedAccess( $idCompany, $idFeedOut ) {
		$result = false;

		try {
			$query = $this->db->prepare( "SELECT 1 FROM feedout o LEFT JOIN feedPopulation p ON p.idFeedOut = o.idFeedOut LEFT JOIN feedinc i ON i.idFeedIn = p.idFeedIn LEFT JOIN companies ci ON ci.idCompany = i.idCompany LEFT JOIN companies co ON co.idCompany = o.idCompany WHERE ci.idCompany = ? AND o.idFeedOut = ?" );
			$query->execute( array( $idCompany, $idFeedOut ) );
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound feed access: ' . $e->getMessage() );
		}

		return $result;
	}

	public function checkOutboundFeedLabelExists( $label ) {
		$result = false;

		try {
			$query = $this->db->prepare( "SELECT 1 FROM feedout WHERE label = ?" );
			$query->execute( array( $label ) );
			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check outbound feed label: ' . $e->getMessage() );
		}

		return $result;
	}


	public function getOutboundStats( $idFeedOut ) {
		$results = array( 'accepted' => 0, 'rejected' => 0 );

		try {
			$query = $this->db->prepare( "SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_outbound WHERE stamp = ? AND idFeedOut = ?" );
			$query->execute( array( date( 'Y-m-d' ), $idFeedOut ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound stats: ' . $e->getMessage() );
		}

		return $results;
	}

	public function inboundAdd( $idFeedIn, $fields, $statsDay, $error = null, $jobId = null ) {
		$this->db->beginTransaction();

		try {
			$idRecord = $this->insertRow( 'data_inbound', array(
				'idFeedIn' => $idFeedIn,
				'listcode' => empty( $fields['listcode'] ) ? null : substr( $fields['listcode'], 0, 20 ),
				'leadstamp' => empty( $fields['stamp'] ) ? null : date( 'Y-m-d H:i:s', strtotime( $fields['stamp'] ) ),
				'url' => empty( $fields['url'] ) ? null : substr( $this->parseUrl( $fields['url'] ), 0, 255 ),
				'ip' => empty( $fields['ip'] ) ? null : substr( $fields['ip'], 0, 45 ),
				'email' => empty( $fields['email'] ) ? null : substr( $fields['email'], 0, 255 ),
				'fname' => empty( $fields['fname'] ) ? null : substr( $fields['fname'], 0, 50 ),
				'lname' => empty( $fields['lname'] ) ? null : substr( $fields['lname'], 0, 50 ),
				'addr' => empty( $fields['addr'] ) ? null : substr( $fields['addr'], 0, 150 ),
				'addr2' => empty( $fields['addr2'] ) ? null : substr( $fields['addr2'], 0, 150 ),
				'city' => empty( $fields['city'] ) ? null : substr( $fields['city'], 0, 75 ),
				'state' => empty( $fields['state'] ) ? null : substr( $fields['state'], 0, 25 ),
				'zip' => empty( $fields['zip'] ) ? null : substr( $fields['zip'], 0, 20 ),
				'dob' => ( empty( $fields['dob'] ) || '0000-00-00' == $fields['dob'] ) ? null : date( 'Y-m-d', strtotime( $fields['dob'] ) ),
				'gender' => empty( $fields['gender'] ) ? null : substr( $fields['gender'], 0, 10 ),
				'landline' => empty( $fields['landline'] ) ? null : substr( $fields['landline'], 0, 20 ),
				'cellphone' => empty( $fields['cellphone'] ) ? null : substr( $fields['cellphone'], 0, 20 ),
				'country' => empty( $fields['country'] ) ? null : substr( $fields['country'], 0, 75 ),
				'result' => empty( $error ) ? null : $error,
				'jobId' => empty( $jobId ) ? null : $jobId,
			) );
		} catch( Leads_PDOException $e ) {
			$this->db->rollBack();
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add inbound record: ' . $pdoException->getMessage() );
			return null;
		}

		try {
			if( empty( $error ) ) {
				$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1' );
			} else {
				$query = $this->db->prepare( 'INSERT INTO stats_inbound(idFeedIn,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1' );
			}
			$query->execute( array( $idFeedIn, $this->parseUrl( $fields['url'] ) , $statsDay ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to insert stats_inbound record: ' . $e->getMessage() );
			return null;
		}

		$this->db->commit();
		return $idRecord;
	}

	public function inboundProcess( $idRecord, $idFeedIn, $url, $statsDay, $error = null ) {
		$this->db->beginTransaction();

		try {
			$query = $this->db->prepare( 'UPDATE data_inbound SET result = ? WHERE idRecord = ?' );
			$query->execute( array( $error, $idRecord ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to update data_inbound record: ' . $e->getMessage() );
			return;
		}

		if( !empty( $error ) ) {
			try {
				$query = $this->db->prepare( 'UPDATE stats_inbound SET accepted = accepted - 1, rejected = rejected + 1 WHERE idFeedIn = ? AND url = ? AND stamp = ?' );
				$query->execute( array( $idFeedIn, $this->parseUrl( $url ), $statsDay ) );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to update stats_inbound record: ' . $e->getMessage() );
				return;
			}
		}

		$this->db->commit();
	}

	public function inboundCheckDuplicates( $idFeedIn, $column, $requestValues, $dedupeAcross ) {

		$days = 120;

		try {
			switch( $dedupeAcross ) {
				case 'all':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
					) );
				break;
				case 'allGlobal':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier( $column ) . " = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
					) );
				break;
				case 'url':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND url = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['url'] ) ? $this->parseUrl( $requestValues['url'] ) : '',
					) );
				break;
				case 'urlGlobal':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier( $column ) . " = ? AND url = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['url'] ) ? $this->parseUrl( $requestValues['url'] ) : '',
					) );
				break;
				case 'listcode':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier( $column ) . " = ? AND listcode = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						$idFeedIn,
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['listcode'] ) ? $requestValues['listcode'] : '',
					) );
				case 'listcodeGlobal':
					$query = $this->db->prepare( "SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier( $column ) . " = ? AND listcode = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1" );
					$query->execute( array(
						!empty( $requestValues[$column] ) ? $requestValues[$column] : '',
						!empty( $requestValues['listcode'] ) ? $requestValues['listcode'] : '',
					) );
				break;
			}

			if( !empty( $query ) && $query->fetchColumn() ) {
				return true;
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to check for inbound duplicates: ' . $e->getMessage() );
			return null;
		}

		return false;
	}

	public function outboundAdd( $idRecord, $idRecordLegacy, $idFeedIn, $idFeedOut, $url, $processed = 0, $urlRewritten = false ) {
		$this->db->beginTransaction();

		try {
			$status = $this->insertRow( 'data_outbound', array(
				'idRecord' => $idRecord,
				'idRecordLegacy' => $idRecordLegacy,
				'idFeedIn' => $idFeedIn,
				'idFeedOut' => $idFeedOut,
				'processed' => $processed,
				//'url' => $urlRewritten ? $this->parseUrl( $url ) : null,
			) );
		} catch( Leads_PDOException $e ) {
			$this->db->rollBack();
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add outbound record: ' . $pdoException->getMessage() );
			return null;
		}

		try {
			$query = $this->db->prepare( "REPLACE INTO url_mapping(timestamp,idFeedIn,idFeedOut,url) VALUES(NOW(), ?, ?, ?)" );
			$query->execute( array( $idFeedIn, $idFeedOut, $this->parseUrl( $url ) ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to add URL mapping: ' . $e->getMessage() );
			return null;
		}

		if( $processed !== 1 ) {
			try {
				$query = $this->db->prepare( "UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?" );
				$query->execute( array( $idFeedOut ) );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to add to queue count: ' . $e->getMessage() );
				return null;
			}
		}

		$this->db->commit();

		return $status;
	}


	public function incrementOutboundQueue( $idFeedOut ) {
		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to add to queue count: ' . $e->getMessage() );
			return null;
		}
	}

	public function outboundProcess( $idRecord, $idFeedOut, $url, $error = null ) {
		$this->db->beginTransaction();

		try {
			if( LEGACY_DB ) {
				$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), processed = 1, result = ? WHERE idRecordLegacy = ? AND idFeedOut = ?' );
				$query->execute( array( $error, $idRecord, $idFeedOut ) );
			} else {
				$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), processed = 1, result = ? WHERE idRecord = ? AND idFeedOut = ?' );
				$query->execute( array( $error, $idRecord, $idFeedOut ) );
			}
		} catch( PDOException $e ) {
			$this->db->rollBack();
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
			$this->db->rollBack();
			$this->logError( 'Unable to insert stats_outbound record: ' . $e->getMessage() );
			return;
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = queued - 1 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to subtract from queue count: ' . $e->getMessage() );
			return $status;
		}

		// Only archive successful records. Errors will get deleted after a few days.
		if( empty( $error ) ) {

			try {
				$table = $this->quoteIdentifier( 'data_outbound_' . date( 'Ym' ) );
				$this->db->query( "CREATE TABLE IF NOT EXISTS archive." . $table . " LIKE data_outbound" );

				$query = $this->db->prepare( "INSERT IGNORE INTO archive." . $table . " SELECT * FROM data_outbound WHERE idRecord = ? AND idFeedOut = ?" );
				$query->execute( array( $idRecord, $idFeedOut ) );
				$rows = $query->rowCount();

				$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idRecord = ? AND idFeedOut = ?" );
				$query->execute( array( $idRecord, $idFeedOut ) );
			} catch( PDOException $e ) {
				$this->db->rollBack();
				$this->logError( 'Unable to archive record: ' . $e->getMessage() );
				return $status;
			}
		}

		$this->db->commit();
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

	public function getInvoiceDetails( $date, $idCompany ) {
		try {
			$query = $this->db->prepare( "SELECT * FROM invoices WHERE date = ? AND idCompany = ?" );
			$query->execute( array( $date, $idCompany ) );
			return $query->fetch( \PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get invoice details: ' . $e->getMessage() );
			return false;
		}

		return null;
	}

	public function getInvoiceNumber( $date, $idCompany ) {
		$invoiceNumber = '';

		try {
			$query = $this->db->prepare( "SELECT invoiceNumber FROM invoices WHERE date = ? AND idCompany = ?" );
			$query->execute( array( $date, $idCompany ) );
			$invoiceNumber = $query->fetchColumn();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get invoice number: ' . $e->getMessage() );
		}

		return $invoiceNumber;
	}

	public function setInvoiceDetails( $date, $idCompany, $invoiceNumber, $paymentDate, $userId ) {

		try {
			$query = $this->db->prepare( "REPLACE INTO invoices( date, idCompany, invoiceNumber, paymentDate, userId ) VALUES( ?, ?, ?, ?, ? )" );
			$query->execute( array(
				$date,
				$idCompany,
				!empty( $invoiceNumber ) ? $invoiceNumber : null,
				!empty( $paymentDate ) ? $paymentDate : null,
				!empty( $userId ) ? $userId : null,
			) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to update invoice value: ' . $e->getMessage() );
			return;
		}
	}

	public function getInvoiceStatus( $date, $idCompany ) {
		$paid = false;

		try {
			$query = $this->db->prepare( "SELECT paymentDate FROM invoices WHERE date = ? AND idCompany = ?" );
			$query->execute( array( $date, $idCompany ) );
			$paid = !empty( $query->fetchColumn() ) ? true : false;
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get invoice status: ' . $e->getMessage() );
		}

		return $paid;
	}

	public function getRevenueInboundMappings( $date, $idCompany, $idFeedIn, $url ) {
		$results = array();
		$fields = array();
		//$fields[] = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-%';
		$fields[] = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-%';
		$fields[] = $date;

		$query  = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,r.value AS revenue,MIN(so.stamp) AS firstDate,MAX(so.stamp) AS lastDate ";
		$query .= "FROM url_mapping m ";
		$query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
		$query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
		$query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ";
		//$query .= "INNER JOIN stats_inbound si ON si.url = m.url AND si.idFeedIn = m.idFeedIn AND si.stamp LIKE ? ";
		$query .= "INNER JOIN stats_outbound so ON so.url = m.url AND so.idFeedOut = m.idFeedOut AND so.stamp LIKE ? ";
		$query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn AND m.url = r.url AND m.idFeedOut = r.idFeedOut AND r.date = ? ";
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
		$query .= "ORDER BY 4 ASC,10 DESC ";

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

		$query  = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,SUM(DISTINCT r.value) AS revenue,ROUND(SUM(DISTINCT r.value)*0.50,2) AS partner,IF(SUM(r.value)>0,'0','1'),MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
		$query .= "FROM url_mapping m ";
		$query .= "LEFT JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
		$query .= "LEFT JOIN companies ci ON i.idCompany = ci.idCompany ";
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

		$query  = "SELECT ci.name AS inName,r.date AS month,SUM(r.value) AS revenue,ROUND(SUM(r.value)*0.50,2) AS partner,i.idCompany AS idCompany ";
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

	public function getRevenueInboundClientMonthTotal( $idCompany, $month ) {
		$results = array();
		$fields = array();

		$query  = "SELECT SUM(r.value) AS revenue,ROUND(SUM(r.value)*0.50,2) AS partner ";
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
		if( !empty( $idCompany ) ) {
			$query .= "AND r.date = ? ";
			$fields[] = $month;
		}

		try {
			$query = $this->db->prepare( $query );
			$query->execute( $fields );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound client revenue month total: ' . $e->getMessage() );
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

	public function copyRevenueValues( $fromDate, $toDate, $idCompany ) {

		try {
			$mappings = $this->getRevenueInboundMappings( $fromDate, $idCompany, null, null );
			if( $mappings && is_array( $mappings ) ) {
				foreach( $mappings as $mapping ) {
					$query = $this->db->prepare( "REPLACE INTO revenue( date, idFeedIn, idFeedOut, url, value ) SELECT ?,idFeedIn,idFeedOut,url,value FROM revenue WHERE date = ? AND idFeedIn = ? AND idFeedOut = ? AND url = ?" );
					$query->execute( array( $toDate, $fromDate, $mapping['idFeedIn'], $mapping['idFeedOut'], $mapping['url'] ) );
				}
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to copy revenue values: ' . $e->getMessage() );
			return;
		}

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

	public function addJob( $type, $destination, $fields, $filename, $records ) {
		$jobId = null;

		try {
			$jobId = $this->insertRow( 'jobs', array(
				'type' => $type,
				'destination' => $destination,
				'fields' => $fields,
				'filename' => $filename,
				'records' => $records,
				'idUser' => LeadsSession::getUserId(),
			) );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to add job: ' . $pdoException->getMessage() );
		}

		return $jobId;
	}

	public function updateJob( $jobId, $fields ) {

		try {
			$status = $this->update( 'jobs', $fields, array(
				'jobId' => $jobId,
			) );
			return $status;
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to update job: ' . $pdoException->getMessage() );
			return null;
		}

		return null;
	}

	public function getJob( $jobId ) {
		try {
			$query = $this->db->prepare( "SELECT j.jobId,j.status,j.timestamp,f.label,j.fields,j.filename,j.records,u.username FROM jobs j LEFT JOIN users u ON j.idUser = u.idUser LEFT JOIN feedinc f ON j.destination = f.idFeedIn WHERE j.jobId = ?" );
			$query->execute( array( $jobId ) );
			return $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get job details: ' . $e->getMessage() );
			return null;
		}

		return null;
	}

	public function getJobs( $idCompany = null ) {
		try {
			$params = array();
			$sql  = "SELECT j.jobId,j.type,j.status,j.timestamp,f.label,j.fields,j.filename,j.records,u.username ";
			$sql .= "FROM jobs j ";
			$sql .= "LEFT JOIN users u ON j.idUser = u.idUser ";
			$sql .= "LEFT JOIN feedinc f ON j.destination = f.idFeedIn ";
			if( !empty( $idCompany ) ) {
				$sql .= "WHERE j.type = 'feedinc' ";
				$sql .= "AND destination IN (SELECT idFeedIn FROM feedinc WHERE idCompany = ?)";
				$params[] = $idCompany;
			}
			$sql .= "ORDER BY j.jobId DESC LIMIT 100";

			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			return $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get jobs: ' . $e->getMessage() );
			return null;
		}

		return null;
	}

	public function getPendingJob() {
		try {
			$this->lockTables( "jobs WRITE" );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to lock tables: ' . $pdoException->getMessage() );
		}

		try {
			$query = $this->db->prepare( "SELECT jobId,type,destination,fields,filename,records,idUser FROM jobs WHERE status = ?" );
			$query->execute( array( 'pending' ) );
			$rows = $query->fetchAll( PDO::FETCH_OBJ );
			if( $rows && is_array( $rows ) ) {
				foreach( $rows as $row ) {
					if( empty( $row->filename ) || file_exists( $row->filename ) ) {

						$this->updateJob( $row->jobId, array(
							'status' => 'processing',
						) );

						$this->unlockTables();
						return $row;
					}
				}
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get pending job: ' . $e->getMessage() );
			return;
		}

		try {
			$this->unlockTables();
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to unlock tables: ' . $pdoException->getMessage() );
		}

		return null;
	}

	public function getInboundJobRecords( $jobId, $idRecord = 0, $idCompany = null ) {
		$results = array();

		try {
			$params = array();
			$sql  = "SELECT idRecord,email,url,result ";
			$sql .= "FROM data_inbound ";
			$sql .= "WHERE jobId = ? ";
			$params[] = $jobId;
			$sql .= "AND idRecord > ? ";
			$params[] = $idRecord;
			if( !empty( $idCompany ) ) {
				$sql .= "AND idFeedIn IN (SELECT idFeedIn FROM feedinc WHERE idCompany = ?)";
				$params[] = $idCompany;
			}
			$sql .= "ORDER BY idRecord ASC LIMIT 500";

			$query = $this->db->prepare( $sql );
			$query->execute( $params );
			$results = $query->fetchAll( PDO::FETCH_ASSOC );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound job records: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getOutboundRejections( $idFeedOut, $offset = 0 ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT o.timestamp,o.result,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip FROM data_outbound o USE INDEX (result) INNER JOIN data_inbound i ON i.idRecord = o.idRecord INNER JOIN feedout f ON f.idFeedOut = o.idFeedOut WHERE o.idFeedOut = ? AND o.processed = 1 AND o.result IS NOT NULL ORDER BY o.timestamp DESC LIMIT " . intval( $offset ) . ",100" );
			$query->execute( array( $idFeedOut ) );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound rejections: ' . $e->getMessage() );
		}

		return $results;
	}

	public function retryOutboundRejections( $idFeedOut, $date ) {
		$count = 0;

		// Timestamps in data_outbound may need to be converted to a different timezone
		$utcStart = new DateTime( $date . ' 00:00:00', new DateTimeZone( LOCAL_TIMEZONE ) );
		$utcStart->setTimeZone( new DateTimeZone( DB_TIMEZONE ) );

		$utcEnd = new DateTime( $date . ' 23:59:59', new DateTimeZone( LOCAL_TIMEZONE ) );
		$utcEnd->setTimeZone( new DateTimeZone( DB_TIMEZONE ) );

		$this->db->beginTransaction();

		try {
			$query = $this->db->prepare( "UPDATE data_outbound SET timestamp = NULL, processed = 0, result = NULL WHERE result IS NOT NULL AND processed = 1 AND idFeedOut = ? AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedOut, $utcStart->format('c'), $utcEnd->format('c') ) );

			$count = $query->rowCount();

		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to retry outbound rejections (1): ' . $e->getMessage() );
			return null;
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = queued + ? WHERE idFeedOut = ?" );
			$query->execute( array( $count, $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to retry outbound rejections (2): ' . $e->getMessage() );
			return null;
		}

		try {
			$query = $this->db->prepare( "UPDATE stats_outbound SET rejected = 0 WHERE idFeedOut = ? AND stamp = ?" );
			$query->execute( array( $idFeedOut, $date ) );
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to retry outbound rejections (3): ' . $e->getMessage() );
			return null;
		}

		$this->db->commit();

		return $count;
	}

	public function getInboundStats( $idFeedIn ) {
		$results = array( 'accepted' => 0, 'rejected' => 0 );

		try {
			$query = $this->db->prepare( "SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = ? AND idFeedIn = ?" );
			$query->execute( array( date( 'Y-m-d' ), $idFeedIn ) );
			$results = $query->fetch( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound stats: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundURLStats( $idFeedIn ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT url,IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = ? AND idFeedIn = ? GROUP BY url" );
			$query->execute( array( date( 'Y-m-d' ), $idFeedIn ) );
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
		$params = array();

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
		$params[] = $idFeedIn;
		if( !empty( $urlList ) && is_array( $urlList ) ) {
			$query .= "AND url IN (" . substr( str_repeat( '?,', sizeOf( $urlList ) ), 0, -1 ) . ") ";
			foreach( $urlList as $url ) {
				$params[] = $url;
			}
		}

		if( !empty( $dateStart ) && !empty( $dateEnd ) ) {
			if( strtotime($dateStart) > strtotime($dateEnd) ) {
				$dateStart = date("Y-m-d", strtotime($dateEnd));
				$dateEnd = date("Y-m-d", strtotime($dateStart));
			} else {
				$dateStart = date("Y-m-d", strtotime($dateStart));
				$dateEnd = date("Y-m-d", strtotime($dateEnd));
			}
			$query .= "AND stamp >= '".$dateStart."' AND stamp <= '".$dateEnd."' ";
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
			$query->execute( $params );
			$results = $query->fetchAll( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound URL dates: ' . $e->getMessage() );
		}

		return $results;
	}

	public function getInboundStatsAverages() {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT s.idFeedIn,s.url,c.name,i.label FROM dnrdmktg.stats_inbound s LEFT JOIN feedinc i ON i.idFeedIn = s.idFeedIn LEFT JOIN companies c ON i.idCompany = c.idCompany WHERE s.stamp >= DATE_SUB(NOW(),INTERVAL 90 DAY) AND s.accepted > 0 AND s.url != '' GROUP BY s.url,s.idFeedIn" );
			$query->execute();
			$urls = $query->fetchAll();

			if( !empty( $urls ) && is_array( $urls ) ) {
				foreach( $urls as $url ) {
					$results[$url['idFeedIn']][$url['url']] = array(
						'daily' => 0,
						'weekly' => 0,
						'monthly' => 0,
						'idFeedIn' => $url['idFeedIn'],
						'url' => $url['url'],
						'label' => $url['label'],
						'name' => $url['name'],
					);

					$query = $this->db->prepare( "SELECT AVG(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 90 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn" );
					$query->execute( array( $url['url'], $url['idFeedIn'] ) );
					$results[$url['idFeedIn']][$url['url']]['daily'] = $query->fetchColumn();

					$query = $this->db->prepare( "SELECT SUM(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn" );
					$query->execute( array( $url['url'], $url['idFeedIn'] ) );
					$results[$url['idFeedIn']][$url['url']]['weekly'] = $query->fetchColumn();

					$query = $this->db->prepare( "SELECT SUM(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 30 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn" );
					$query->execute( array( $url['url'], $url['idFeedIn'] ) );
					$results[$url['idFeedIn']][$url['url']]['monthly'] = $query->fetchColumn();

				}
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get inbound stats averages: ' . $e->getMessage() );
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
		$params = array();

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
		$params[] = $idFeedOut;
		if( !empty( $urlList ) && is_array( $urlList ) ) {
			$query .= "AND url IN (" . substr( str_repeat( '?,', sizeOf( $urlList ) ), 0, -1 ) . ") ";
			foreach( $urlList as $url ) {
				$params[] = $url;
			}
		}

		if( !empty( $dateStart ) && !empty( $dateEnd ) ) {
			if( strtotime($dateStart) > strtotime($dateEnd) ) {
				$dateStart = date("Y-m-d", strtotime($dateEnd));
				$dateEnd = date("Y-m-d", strtotime($dateStart));
			} else {
				$dateStart = date("Y-m-d", strtotime($dateStart));
				$dateEnd = date("Y-m-d", strtotime($dateEnd));
			}
			$query .= "AND stamp >= '".$dateStart."' AND stamp <= '".$dateEnd."' ";
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
			$query->execute( $params );
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


	public function firstOutboundRecord( $idFeedOut, $url ) {
		$results = null;

		try {
			$query = $this->db->prepare( "SELECT MIN(o.idRecord) FROM data_outbound o JOIN data_inbound i ON i.idRecord=o.idRecord WHERE o.idFeedOut = ? AND i.url = ?" );
			$query->execute( array( $idFeedOut, $url ) );
			$results = $query->fetchColumn( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get first outbound record: ' . $e->getMessage() );
		}

		return $results;
	}

	public function checkInboundURLExists( $idFeedIn, $url ) {
		try {
			$query = $this->db->prepare( "SELECT 1 FROM notifications WHERE url = ? AND idFeedIn = ? LIMIT 1" );
			$query->execute( array( $this->parseUrl( $url ), $idFeedIn ) );
			if( $query && $query->fetchColumn() ) {
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

	public function archiveInbound( $idFeedIn, $datetime ) {
		$rows = -1;

		try {
			$table = $this->quoteIdentifier( 'data_inbound_' . $datetime->format( 'Ym' ) );
			$this->db->query( "CREATE TABLE IF NOT EXISTS archive." . $table . " LIKE data_inbound" );

			$query = $this->db->prepare( "INSERT IGNORE INTO archive." . $table . " SELECT * FROM data_inbound WHERE idFeedIn = ? AND result IS NULL AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedIn, $datetime->format( 'Y-m-\0\1 \0\0:\0\0:\0\0' ), $datetime->format( 'Y-m-t \2\3:\5\9:\5\9' ) ) );
			$rows = $query->rowCount();

			$query = $this->db->prepare( "DELETE FROM data_inbound WHERE idFeedIn = ? AND result IS NULL AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedIn, $datetime->format( 'Y-m-\0\1 \0\0:\0\0:\0\0' ), $datetime->format( 'Y-m-t \2\3:\5\9:\5\9' ) ) );

			$query = $this->db->prepare( "DELETE FROM data_inbound WHERE idFeedIn = ? AND result IS NOT NULL AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedIn, $datetime->format( 'Y-m-\0\1 \0\0:\0\0:\0\0' ), $datetime->format( 'Y-m-t \2\3:\5\9:\5\9' ) ) );

		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old data_inbound entries: ' . $e->getMessage() );
		}

		return $rows;
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

	public function archiveOutbound( $idFeedOut, $datetime ) {
		try {

			$table = $this->quoteIdentifier( 'data_outbound_' . $datetime->format( 'Ym' ) );
			$this->db->query( "CREATE TABLE IF NOT EXISTS archive." . $table . " LIKE data_outbound" );

			$query = $this->db->prepare( "INSERT IGNORE INTO archive." . $table . " SELECT * FROM data_outbound WHERE idFeedOut = ? AND processed = 1 AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedOut, $datetime->format( 'Y-m-\0\1 \0\0:\0\0:\0\0' ), $datetime->format( 'Y-m-t \2\3:\5\9:\5\9' ) ) );
			$rows = $query->rowCount();

			$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idFeedOut = ? AND timestamp >= ? AND timestamp <= ?" );
			$query->execute( array( $idFeedOut, $datetime->format( 'Y-m-\0\1 \0\0:\0\0:\0\0' ), $datetime->format( 'Y-m-t \2\3:\5\9:\5\9' ) ) );

			return $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete old data_outbound entries: ' . $e->getMessage() );
		}

		return -1;
	}

	public function clearOutboundQueue( $idFeedOut, $label ) {
		$rows = null;

		try {
			if( LEGACY_DB ) {
				$this->lockTables( "feedout WRITE, data_outbound WRITE, " . $this->quoteIdentifier( 'feedout_' . $label ) . " WRITE" );
			} else {
				$this->lockTables( "feedout WRITE, data_outbound WRITE" );
			}
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to lock tables: ' . $pdoException->getMessage() );
			return null;
		}

		try {
			$query = $this->db->prepare( "DELETE FROM data_outbound WHERE idFeedOut = ? AND processed = 0" );
			$query->execute( array( $idFeedOut ) );
			$rows = $query->rowCount();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete queued records (1): ' . $e->getMessage() );
			return null;
		}

		if( LEGACY_DB ) {
			try {
				$query = $this->db->prepare( "DELETE FROM " . $this->quoteIdentifier( 'feedout_' . $label ) . " WHERE processed = '0'" );
				$query->execute( );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to delete queued records (2): ' . $e->getMessage() );
				return null;
			}
		}

		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = 0 WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to delete queued records (3): ' . $e->getMessage() );
			return null;
		}

		try {
			$this->unlockTables();
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to unlock tables: ' . $pdoException->getMessage() );
			return null;
		}

		return $rows;
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

		if( !empty( $settings['includeRejects'] ) ) {
			$settings['columns'][] = 'result';
		}

		// Fix column names
		foreach( $settings['columns'] as $key => $column ) {
			if( 'stamp' == $column ) {
				$settings['columns'][$key] = 'leadstamp';
			}
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

			if( empty( $settings['includeRejects'] ) ) {
				$query .= "AND result IS NULL ";
			}

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
						$query .= "url = ?";
						$fields[] = $url;
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

			$this->setBufferedQuery();

			$result['query'] = $query;
			$query = $this->db->Prepare( $query );

			$query->execute( $fields );
			$cnt = 0;
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				$cnt++;
				fputcsv( $file, $row );
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export inbound records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );

		$result['success'] = true;
		$result['reason'] = 'Successfully exported data to file.';
		$result['fileLink'] = $fileLink;
		$result['cnt'] = $cnt;

		return $result;
	}

	public function exportComcast() {

		$jobId = time();

		$fileLink = 'exports/' . "comcast_".$jobId.".csv";
		$filePath = ADMIN_ROOT . $fileLink;
		$file = fopen( $filePath, 'w' );
		if( !$file ) {
			$result['reason'] = 'Unable to create CSV file.';
			return;
		}

		try {

			$this->setBufferedQuery();

			$fields = array();

			$query  = $this->db->query( "SELECT url,ip,leadstamp,email FROM data_inbound WHERE email LIKE '%@comcast.net' AND result IS NULL" );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fputcsv( $file, $row );
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export Comcast records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );
	}

	public function exportCable() {

		$jobId = time();

		$fileLink = 'exports/' . "cable_".$jobId.".csv";
		$filePath = ADMIN_ROOT . $fileLink;
		$file = fopen( $filePath, 'w' );
		if( !$file ) {
			$result['reason'] = 'Unable to create CSV file.';
			return;
		}

		try {

			$this->setBufferedQuery();

			$fields = array();

			$query  = $this->db->query( "SELECT url,ip,leadstamp,email FROM data_inbound WHERE ( email LIKE '%@att.net' OR email LIKE '%@bellsouth.net' OR email LIKE '%@earthlink.net' ) AND result IS NULL" );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				fputcsv( $file, $row );
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export cable records: ' . $e->getMessage() );
			return;
		}

		fclose( $file );
	}

	public function exportRecords( $sql, $fields = array() ) {

		try {

			$this->setBufferedQuery();

			$query = $this->db->prepare( $sql );
			$query->execute( $fields );
			return $query;

		} catch( PDOException $e ) {
			$this->logError( 'Unable to export records: ' . $e->getMessage() );
			return null;
		}

		return null;
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
				if( !empty( $feed->delay ) ) {
					$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp < DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 500" );
					$query->execute( array( $idFeedOut, $feed->delay ) );
				} else {
					$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? LIMIT 500" );
					$query->execute( array( $idFeedOut ) );
				}
			}
			return $query;

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get queued records: ' . $e->getMessage() );
			return null;
		}

		return null;
	}

	public function getOutboundQueueRecord( $idFeedOut ) {

		$feed = $this->getOutboundFeed( $idFeedOut );
		if( !$feed ) {
			return;
		}

		$lockName = 'READQUEUE_' . $idFeedOut;

		try {
			//$this->lockTables( "data_outbound WRITE, data_outbound o WRITE, data_inbound i READ" );
			$query = $this->db->prepare( "SELECT GET_LOCK(?,-1);" );
			$query->execute( array( $lockName ) );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to lock tables: ' . $pdoException->getMessage() );
		}

		try {
			if( !empty( $feed->delay ) ) {
				$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp < DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1" );
				$query->execute( array( $idFeedOut, $feed->delay ) );
			} else {
				$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? LIMIT 1" );
				$query->execute( array( $idFeedOut ) );
			}

			$rows = $query->fetchAll( PDO::FETCH_OBJ );
			if( $rows && is_array( $rows ) ) {
				foreach( $rows as $row ) {

					try {
						$status = $this->update( 'data_outbound',
							array(
								'processed' => -2,
							),
							array(
								'idRecord' => $row->idRecord,
								'idFeedOut' => $idFeedOut,
							)
						);
					} catch( PDOException $e ) {
						$pdoException = $e->getPrevious();
						$this->logError( 'Unable to update outbound record: ' . $pdoException->getMessage() );
						$query = $this->db->prepare( "SELECT RELEASE_LOCK(?);" );
						$query->execute( array( $lockName ) );
						//$this->unlockTables();
						return;
					}

				}

				$query = $this->db->prepare( "SELECT RELEASE_LOCK(?);" );
				$query->execute( array( $lockName ) );
				//$this->unlockTables();
				return $rows;
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get pending outbound queue record: ' . $e->getMessage() );
			return;
		}

		try {
			$query = $this->db->prepare( "SELECT RELEASE_LOCK(?);" );
			$query->execute( array( $lockName ) );
			//$this->unlockTables();
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to unlock tables: ' . $pdoException->getMessage() );
		}

		return null;
	}

	public function checkOutboundRecordExists( $idRecord, $idFeedIn, $idFeedOut ) {
		try {
			$query = $this->db->prepare( "SELECT 1 FROM data_outbound WHERE idRecord = ? AND idFeedIn = ? AND idFeedOut = ?" );
			$query->execute( array( $idRecord, $idFeedIn, $idFeedOut ) );
			if( $query && $query->fetchColumn() ) {
				return true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound record exists results: ' . $e->getMessage() );
			return null;
		}

		$date = new \DateTime();
		for( $i = 0; $i < 6; $i++ ) {
			try {
				$table = $this->quoteIdentifier( 'data_outbound_' . $date->format( 'Ym' ) );
				$query = $this->db->prepare( "SELECT 1 FROM archive." . $table . " WHERE idRecord = ? AND idFeedIn = ? AND idFeedOut = ?" );
				$query->execute( array( $idRecord, $idFeedIn, $idFeedOut ) );
				if( $query && $query->fetchColumn() ) {
					return true;
				}
			} catch( PDOException $e ) {
				$this->logError( 'Unable to get outbound record archive exists results: ' . $e->getMessage() );
				return null;
			}
			$date->sub( new \DateInterval( 'P1M' ) );
		}

		return false;
	}

	public function getOutboundRecord( $idRecord, $idFeedOut, $processed = null ) {

		try {
			if( !empty( $processed ) ) {
				$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = ? AND o.idRecord = ? AND o.idFeedOut = ?" );
				$query->execute( array( intval( $processed ), $idRecord, $idFeedOut ) );
			} else {
				$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.idRecord = ? AND o.idFeedOut = ?" );
				$query->execute( array( $idRecord, $idFeedOut ) );
			}
			return $query->fetch( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound record: ' . $e->getMessage() );
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

			$query = $this->db->prepare( "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE processed = 1 AND o.idFeedOut = ? AND o.result IS NOT NULL" );
			$query->execute( );
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

	public function getOutboundDailyCount( $idFeedOut ) {
		$cnt = null;

		// Timestamps in data_outbound may need to be converted to a different timezone
		$utcDate = new DateTime( 'now', new DateTimeZone( LOCAL_TIMEZONE ) );
		$utcDate->setTimeZone( new DateTimeZone( DB_TIMEZONE ) );

		try {
			$query = $this->db->prepare( "SELECT SUM(accepted) FROM stats_outbound WHERE idFeedOut = ? AND stamp = ?" );
			$query->execute( array( $idFeedOut, $utcDate->format( 'Y-m-d' ) ) );
			$cnt = $query->fetchColumn();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check outbound daily count: ' . $e->getMessage() );
			return $cnt;
		}

		return $cnt;
	}

	public function getInboundDailyCount( $idFeedIn ) {
		$cnt = null;

		// Timestamps in data_inbound may need to be converted to a different timezone
		$utcDate = new DateTime( 'now', new DateTimeZone( LOCAL_TIMEZONE ) );
		$utcDate->setTimeZone( new DateTimeZone( DB_TIMEZONE ) );

		try {
			$query = $this->db->prepare( "SELECT SUM(accepted) FROM stats_inbound WHERE idFeedIn = ? AND stamp = ?" );
			$query->execute( array( $idFeedIn, $utcDate->format( 'Y-m-d' ) ) );
			$cnt = $query->fetchColumn();
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check inbound daily count: ' . $e->getMessage() );
			return $cnt;
		}

		return $cnt;
	}

	public function addSuppression( $idCompany, $email ) {
		try {
			$idSuppression = $this->insertRow( 'suppression', array(
				'idCompany' => $idCompany,
				'email' => $email,
			) );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			if( strpos( $pdoException->getMessage(), 'SQLSTATE[23000]:' ) !== false ) {
				return null;
			} else {
				$this->logError( 'Unable to add suppression: ' . $pdoException->getMessage() );
				return false;
			}
		}

		return true;
	}

	public function getSuppressionCounts() {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT s.idCompany,c.name,COUNT(*) AS cnt FROM suppression s LEFT JOIN companies c ON s.idCompany = c.idCompany GROUP BY s.idCompany" );
			$query->execute( array( ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get suppression counts: ' . $e->getMessage() );
		}

		return $results;
	}

	public function checkSuppression( $email, $idCompany = null ) {
		$result = false;

		if( empty( $email ) || strpos( $email, '@' ) === false ) {
			return $result;			
		}

		list( $local, $domain ) = explode( '@', $email, 2 );

		try {
			if( !empty( $idCompany ) ) {
				$query = $this->db->prepare( "SELECT 1 FROM suppression WHERE ( email = ? OR email = ? ) AND ( idCompany = 0 OR idCompany = ? )" );
				$query->execute( array( $email, $domain, $idCompany ) );
			} else {
				$query = $this->db->prepare( "SELECT 1 FROM suppression WHERE ( email = ? OR email = ? ) AND idCompany = 0" );
				$query->execute( array( $email, $domain ) );
			}

			if( '1' == $query->fetchColumn( ) ) {
				$result = true;
			}
		} catch( PDOException $e ) {
			$this->logError( 'Unable to check suppression: ' . $e->getMessage() );
		}

		return $result;
	}

	public function exportSuppressions( $idCompany ) {
		$result = array();

		if( empty( $idCompany ) ) {
			$result['file'] = 'exports/suppression_global_' . time() . '.csv';
		} else {
			$result['file'] = 'exports/suppression_' . intval( $idCompany ) . '_' . time() . '.csv';
		}
		$filePath = ADMIN_ROOT . $result['file'];
		$fh = fopen( $filePath, 'w' );
		if( !$fh ) {
			$result['reason'] = 'Failed to create CSV file.';
			return $result;
		}

		try {
			if( empty( $idCompany ) ) {
				$query = $this->db->prepare( "SELECT email FROM suppression WHERE idCompany = 0" );
				$query->execute( array( ) );
			} else {
				$query = $this->db->prepare( "SELECT email FROM suppression WHERE idCompany = ?" );
				$query->execute( array( $idCompany ) );
			}
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

	public function validateSuppressions() {

		try {
			$query = $this->db->prepare( "SELECT email FROM suppression" );
			$query->execute( );
			while ( $row = $query->fetch( PDO::FETCH_ASSOC ) ) {
				if( strpos( $row['email'], '@' ) !== FALSE && !filter_var( $row['email'], FILTER_VALIDATE_EMAIL ) ) {
					$delete = $this->db->prepare( "DELETE FROM suppression WHERE email = ?" );
					$delete->execute( array( $row['email'] ) );
					print $row['email'] . PHP_EOL;
				}
			}
		} catch( PDOException $e ) {
			$result['reason'] = 'DB query error.';
			$this->logError( 'Unable to get get supression records for validation: ' . $e->getMessage() );
		}
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

	public function getOutboundStatsDetail( $stamp ) {
		$results = array();

		try {
			$query = $this->db->prepare( "SELECT idFeedOut,url FROM stats_outbound WHERE stamp = ?" );
			$query->execute( array( $stamp ) );
			$results = $query->fetchAll( PDO::FETCH_OBJ );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get outbound stats details: ' . $e->getMessage() );
		}

		return $results;
	}

	public function resetOutboundStats( $idFeedOut, $url, $date ) {
		if( empty( $idFeedOut ) || empty( $date ) ) {
			return;
		}

		$this->db->beginTransaction();

		try {
			$query = $this->db->prepare( "SELECT SUM(IF(o.result IS NULL,1,0)) AS accepted,SUM(IF(o.result IS NOT NULL,1,0)) AS rejected FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord INNER JOIN feedout f ON f.idFeedOut = o.idFeedOut WHERE o.idFeedOut = ? AND o.processed = 1 AND o.timestamp >= ? AND o.timestamp <= ? AND i.url = ?" );
			$query->execute( array( $idFeedOut, $date . ' 00:00:00', $date . ' 23:59:59', $url ) );
			$records = $query->fetchAll( );

			foreach( $records as $record ) {
				$query = $this->db->prepare( "REPLACE INTO stats_outbound(idFeedOut,url,stamp,accepted,rejected) VALUES(?,?,?,?,?)" );
				$query->execute( array( $idFeedOut, $url, $date, $record['accepted'], $record['rejected'] ) );
			}
		} catch( PDOException $e ) {
			$this->db->rollBack();
			$this->logError( 'Unable to reset outbound stats: ' . $e->getMessage() );
		}

		$this->db->commit();

	}

	public function resetQueuedStats() {
		try {
			$query = $this->db->query( "SELECT idFeedOut FROM feedout" );
			$feeds = $query->fetchAll( PDO::FETCH_OBJ );
			$query->closeCursor();

			foreach( $feeds as $feed ) {
				print "Resetting queue stats for feed: {$feed->idFeedOut}\n";
				$this->lockTables( "feedout WRITE, data_outbound WRITE" );
				$query = $this->db->prepare( "UPDATE feedout SET queued = ( SELECT COUNT(*) AS cnt FROM data_outbound WHERE processed = 0 AND idFeedOut = ? ) WHERE idFeedOut = ?" );
				$query->execute( array( $feed->idFeedOut, $feed->idFeedOut ) );
				$this->unlockTables();
				sleep( 2 );
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to reset queued stats: ' . $e->getMessage() );
		} catch( Leads_PDOException $e ) {
			$pdoException = $e->getPrevious();
			$this->logError( 'Unable to lock/unlock tables: ' . $pdoException->getMessage() );
		} finally {
			$this->unlockTables();
		}

		return null;
	}

	public function logError( $message, $db = false, $email = true ) {

		$stamp = date('Y-m-d H:i:s');
		$errfile = fopen( SITE_ROOT . 'error' . FD . 'leads-log', 'a' );
		if( $errfile ) {
			fwrite( $errfile, $stamp . ' ' . $message . PHP_EOL );
			fwrite( $errfile, $stamp . ' REQUEST: ' . print_r( $_REQUEST, true ) . PHP_EOL );
			fclose( $errfile );
		}

		if( $db ) {
			try {
				$this->insertRow( 'errorlog', array(
					'origination' => 'LEADS',
					'description' => $message,
					'stamp' => date( 'Y-m-d H:i:s' ),
				), false );
			} catch( Leads_PDOException $e ) {
				// Do nothing
			}
		}

		if( $email ) {
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
			$sent = @mail( $to, $subject, $body, $header, "-f {$from}" );
		}
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
