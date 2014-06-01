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

	public function outboundAdd( $idRecord, $idFeedIn, $idFeedOut, $url ) {
		return $this->insertRow( 'data_outbound', array(
			'idRecord' => $idRecord,
			'idFeedIn' => $idFeedIn,
			'idFeedOut' => $idFeedOut,
		) );
	}

	public function outboundProcess( $idRecord, $idFeedOut, $url, $error = null ) {
		try {
			$query = $this->db->prepare( 'UPDATE data_outbound SET timestamp = NOW(), result = ? WHERE idRecord = ? AND idFeedOut = ?' );
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

	public function logError( $message ) {

		$stamp = date('Y-m-d H:i:s');
		$errfile = fopen( SITE_ROOT . 'error' . FD . 'leads-log', 'a' );
		if( $errfile ) {
			fwrite( $errfile, $stamp . ' ' . $message . PHP_EOL );
			fclose( $errfile );
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
}
