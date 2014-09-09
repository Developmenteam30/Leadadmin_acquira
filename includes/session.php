<?php

require_once( INCLUDES . 'leads.php' );

define( 'LEADS_SESSION_LEVEL_ADMIN', 90 );
define( 'LEADS_SESSION_LEVEL_STAFF', 50 );
define( 'LEADS_SESSION_LEVEL_CLIENT', 10 );

class LeadsSession
{
	public static function login( $userId, $level ) {
		LeadsSession::start();

		$_SESSION['userId'] = $userId;
		$_SESSION['level'] = intval( $level );

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

	public static function getUserId() {
		LeadsSession::start();

		if( empty( $_SESSION['userId'] ) ) {
			return null;
		}

		return $_SESSION['userId'];
	}

	public static function isValid( $level ) {
		LeadsSession::start();

		if( empty( $_SESSION['level'] ) ) {
			return false;
		}

		if( intval( $_SESSION['level'] ) < $level ) {
			return false;
		}

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

			$result = array('status' => 0, 'error'=> 'You are no longer logged in. Log back in and try again.');
			echo json_encode($result);
			exit;

    	} else if( isset( $_REQUEST['d'] ) ) {

			echo "You are no longer logged in. Log back in and try again.";
			exit;

		} else {
        
			header("Location: /leadadmin/");
			exit;
    
		}

	}
}
