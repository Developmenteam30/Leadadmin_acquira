<?php

require_once( 'leads.php' );

class Legacy extends Leads
{
	public function __construct() {
		parent::__construct();
	}

	public function fixInboundStats() {
		try {
			$query = $this->db->prepare( "SELECT idFeedIn,label FROM feedinc" );
			$query->execute( );
			$tables = $query->fetchAll( );

			foreach( $tables as $table ) {
				print "{$table['label']}<br/>\n";

//				$this->lockTables( "data_inbound READ, stats_inbound WRITE, errorlog WRITE" );
				$query = $this->db->prepare( "DELETE FROM stats_inbound WHERE idFeedIn = ?" );
				$query->execute( array( $table['idFeedIn'] ) );

				$query = $this->db->prepare( "SELECT url,LEFT(received,10) AS stamp,COUNT(*) AS cnt FROM feedinc_" . $table['label'] . " GROUP BY url,LEFT(received,10)" );
				$query->execute();
				$stats = $query->fetchAll();

				foreach( $stats as $stat ) {
					$query = $this->db->prepare( "REPLACE INTO stats_inbound(idFeedIn,url,stamp,accepted) VALUES(?,?,?,?)" );
					$query->execute( array( $table['idFeedIn'], $this->parseUrl( $stat['url'] ), $stat['stamp'], $stat['cnt'] ) );
				}

//				$this->unlockTables();
				sleep(5);
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get tables: ' . $e->getMessage() );
			$this->unlockTables();
		}

		$this->unlockTables();
	}

	public function fixOutboundStats() {
		try {
			$query = $this->db->prepare( "SELECT idFeedOut,label FROM feedout WHERE idFeedOut = 364" );
			$query->execute( );
			$tables = $query->fetchAll( );

			foreach( $tables as $table ) {
				print "{$table['label']}<br/>\n";

				$this->lockTables( "data_outbound READ, stats_outbound WRITE, errorlog WRITE" );

				$query = $this->db->prepare( "SELECT i.url,LEFT(o.timestamp,10) AS stamp,SUM(IF(o.result IS NULL,1,0)) AS accepted,SUM(IF(o.result IS NOT NULL,1,0)) AS rejected FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 1 AND o.idFeedOut = ? AND o.timestamp >= '2015-02-09'" );
				$query->execute( array( $table['idFeedOut'] ) );
				$stats = $query->fetchAll();

				foreach( $stats as $stat ) {
					$query = $this->db->prepare( "REPLACE INTO stats_outbound(idFeedOut,url,stamp,accepted,rejected) VALUES(?,?,?,?,?)" );
					$query->execute( array( $table['idFeedIn'], $this->parseUrl( $stat['url'] ), $stat['stamp'], $stat['accepted'], $stat['rejected'] ) );
				}

				$this->unlockTables();
				sleep(5);
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get tables: ' . $e->getMessage() );
			$this->unlockTables();
		}

		$this->unlockTables();
	}

	public function fixInboundStatsRejected() {
		try {
			$query = $this->db->prepare( "SELECT idFeedIn,label FROM feedinc" );
			$query->execute( );
			$tables = $query->fetchAll( );

			foreach( $tables as $table ) {
				print "{$table['label']}_invalid<br/>\n";

				$this->lockTables( "feedinc_" . $table['label'] . "_invalid READ, stats_inbound WRITE, errorlog WRITE" );

				$query = $this->db->prepare( "SELECT url,LEFT(received,10) AS stamp,COUNT(*) AS cnt FROM feedinc_" . $table['label'] . "_invalid GROUP BY url,LEFT(received,10)" );
				$query->execute();
				$stats = $query->fetchAll();

				foreach( $stats as $stat ) {
					$query = $this->db->prepare( "INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted,rejected) VALUES(?,?,?,0,?) ON DUPLICATE KEY UPDATE rejected = ?" );
					$query->execute( array( $table['idFeedIn'], $this->parseUrl( $stat['url'] ), $stat['stamp'], $stat['cnt'], $stat['cnt'] ) );
				}

				$this->unlockTables();
				sleep(5);
			}

		} catch( PDOException $e ) {
			$this->logError( 'Unable to get tables: ' . $e->getMessage() );
			$this->unlockTables();
		}

		$this->unlockTables();
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

	public function getQueued( $idFeedOut ) {
		$queued = -9999;

		try {
			$query = $this->db->prepare( "SELECT queued FROM feedout WHERE idFeedOut = ?" );
			$query->execute( array( $idFeedOut ) );
			$queued = $query->fetchColumn( );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to get queued stats: ' . $e->getMessage() );
		}

		return $queued;
	}

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

	public function addMapping( $idFeedIn, $idFeedOut, $url, $time ) {
			try {
				$query = $this->db->prepare( "REPLACE INTO url_mapping(timestamp,idFeedIn,idFeedOut,url) VALUES(?, ?, ?, ?)" );
				$query->execute( array( $time, $idFeedIn, $idFeedOut, $this->parseUrl( $url ) ) );
			} catch( PDOException $e ) {
				$this->logError( 'Unable to add URL mapping: ' . $e->getMessage() );
				return $status;
			}

	}

	public function updateQueueStats( $idFeedOut, $queued ) {
		try {
			$query = $this->db->prepare( "UPDATE feedout SET queued = ? WHERE idFeedOut = ?" );
			$query->execute( array( $queued, $idFeedOut ) );
		} catch( PDOException $e ) {
			$this->logError( 'Unable to reset queued stats: ' . $e->getMessage() );
		}
	}

}
