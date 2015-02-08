<?php

require_once( INCLUDES . 'leads.php' );

require_once( INCLUDES . 'sessions-database.php' );
$pdo = new PDO( 'mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME, $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p'] );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
$session = new PdoSessionHandler( $pdo );
session_set_save_handler( $session );

define( 'LEADS_SESSION_LEVEL_ADMIN', 90 );
define( 'LEADS_SESSION_LEVEL_STAFF', 50 );
define( 'LEADS_SESSION_LEVEL_CLIENT_DASHBOARD', 20 );
define( 'LEADS_SESSION_LEVEL_CLIENT', 10 );
define( 'LEADS_SESSION_LEVEL_CLIENT_REPORTS', 10 );

class LeadsSession
{
	public static function login( $userId, $level, $idCompany ) {
		LeadsSession::start();

		$_SESSION['userId'] = $userId;
		$_SESSION['level'] = intval( $level );
		$_SESSION['idCompany'] = intval( $idCompany );

		session_write_close();

		$leads = Leads::getInstance();
		$leads->auditLog( 'LOGIN', null );
	}

	public static function logout() {
		LeadsSession::start();

		$leads = Leads::getInstance();
		$leads->auditLog( 'LOGOUT', null );

		unset( $_SESSION['userId'] );
		unset( $_SESSION['level'] );

		session_write_close();
	}

	public static function getCompanyId() {
		LeadsSession::start();

		if( empty( $_SESSION['idCompany'] ) ) {
			session_write_close();
			return null;
		}

		session_write_close();
		return $_SESSION['idCompany'];
	}

	public static function getUserId() {
		LeadsSession::start();

		if( empty( $_SESSION['userId'] ) ) {
			session_write_close();
			return null;
		}

		session_write_close();
		return $_SESSION['userId'];
	}

	public static function isValid( $level ) {
		LeadsSession::start();

		if( empty( $_SESSION['level'] ) ) {
			session_write_close();
			return false;
		}

		if( intval( $_SESSION['level'] ) < $level ) {
			session_write_close();
			return false;
		}

		session_write_close();
		return true;
	}

	public static function requireAccess( $level ) {
		LeadsSession::start();

		if( empty( $_SESSION['level'] ) ) {
			return LeadsSession::deny();
		}

		if( intval( $_SESSION['level'] ) < $level ) {
			return LeadsSession::deny();
		}

		session_write_close();
	}

	private static function start() {
		if( session_status() !== PHP_SESSION_ACTIVE ) {
			session_start();
		}
	}

	private static function deny() {
    
		session_write_close(); 

		if( isset( $_REQUEST['a'] ) ) {

			$result = array('status' => 0, 'error'=> 'Sorry, you do not have access to this page. Log back in and try again.');
			echo json_encode($result);
			exit;

    	} else if( isset( $_REQUEST['d'] ) ) {

			echo "Sorry, you do not have access to this page. Log back in and try again.";
			exit;

		} else {
        
			header("Location: /leadadmin/");
			exit;
    
		}

	}
}
