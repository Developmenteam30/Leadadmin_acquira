<?php

require_once(INCLUDES . 'leads.php');

require_once(INCLUDES . 'sessions-database.php');
$pdo = new PDO('mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME,
    $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$session = new PdoSessionHandler($pdo);
session_set_save_handler($session);

/*
define('LEADS_SESSION_LEVEL_ADMIN', 90);
define('LEADS_SESSION_LEVEL_MANAGER', 75);
define('LEADS_SESSION_LEVEL_STAFF', 50);
define('LEADS_SESSION_LEVEL_PPC', 45);
define('LEADS_SESSION_LEVEL_CRM', 40);
define('LEADS_SESSION_LEVEL_CLIENT_DASHBOARD', 30);
define('LEADS_SESSION_LEVEL_CLIENT_IMPORT', 20);
define('LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS', 15);
define('LEADS_SESSION_LEVEL_CLIENT_REPORTS', 10);
*/

define('LEADS_SESSION_LEVEL_CALL_CENTER_HR_MANAGER', 0x2000);
define('LEADS_SESSION_LEVEL_CALL_CENTER_AGENT', 0x1000);
define('LEADS_SESSION_LEVEL_CALL_CENTER_QA', 0x800);
define('LEADS_SESSION_LEVEL_CALL_CENTER', 0x400);
define('LEADS_SESSION_LEVEL_SALESPERSON', 0x200);
define('LEADS_SESSION_LEVEL_ADMIN', 0x100);
define('LEADS_SESSION_LEVEL_MANAGER', 0x80);
define('LEADS_SESSION_LEVEL_STAFF', 0x40);
define('LEADS_SESSION_LEVEL_PPC', 0x20);
define('LEADS_SESSION_LEVEL_CRM', 0x10);
define('LEADS_SESSION_LEVEL_CLIENT_DASHBOARD', 0x8);
define('LEADS_SESSION_LEVEL_CLIENT_IMPORT', 0x4);
define('LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS', 0x2);
define('LEADS_SESSION_LEVEL_CLIENT_REPORTS', 0x1);

// Add new entries to bit getAccessBits below

class LeadsSession
{
    const EMAIL_BITS_DEVELOPER = 0x01;
    const EMAIL_BITS_DORMANT_URL = 0x02;
    const EMAIL_BITS_NEW_URL = 0x04;
    const EMAIL_BITS_LEAD_THRESHOLD = 0x08;
    const EMAIL_BITS_JOB_STATUS = 0x10;
    const EMAIL_BITS_NEW_USER = 0x20;
    const EMAIL_BITS_PAYROLL = 0x40;
    const EMAIL_BITS_ACCOUNTING = 0x80;
    const EMAIL_BITS_CRM = 0x100;
    const EMAIL_BITS_DIALER_LICENSING_REPORT = 0x200;
    const EMAIL_BITS_DIALER_STATS_REPORT = 0x400;

    // Add new entries to bit getEmailBits below

    public static function login($userId, $accessBits, $idCompany)
    {
        LeadsSession::start();

        $_SESSION['userId'] = $userId;
        $_SESSION['accessBits'] = intval($accessBits);
        $_SESSION['idCompany'] = intval($idCompany);

        //session_write_close();

        $leads = Leads::getInstance();
        $leads->auditLog('LOGIN', null);
    }

    public static function logout()
    {
        if (!empty(LeadsSession::getUserId())) {
            $leads = Leads::getInstance();
            $leads->auditLog('LOGOUT', null);
        }

        LeadsSession::start();

        unset($_SESSION['userId']);
        unset($_SESSION['accessBits']);
        unset($_SESSION['idCompany']);

        session_unset();
        session_destroy();
        $_SESSION = array();
        //session_write_close();
    }

    public static function getCompanyId()
    {
        LeadsSession::start();

        if (empty($_SESSION['idCompany'])) {
            session_write_close();

            return null;
        }

        //session_write_close();
        return $_SESSION['idCompany'];
    }

    public static function getUserId()
    {
        LeadsSession::start();

        if (empty($_SESSION['userId'])) {
            //session_write_close();
            return null;
        }

        //session_write_close();
        return $_SESSION['userId'];
    }

    public static function isValid($accessBits)
    {
        LeadsSession::start();

        if (empty($_SESSION['accessBits'])) {
            //session_write_close();
            return false;
        }

        // If we pass an array, iterate through each level and any matching level passes.
        // If we didn't pass an array, fake it.
        if (!is_array($accessBits)) {
            $accessBits = [$accessBits];
        }

        foreach ($accessBits as $accessBit) {
            if (($_SESSION['accessBits'] & $accessBit) == $accessBit) {
                return true;
            }
        }

        //session_write_close();
        return false;
    }

    public static function checkBit($accessBits, $bit)
    {
        if (($accessBits & $bit) == $bit) {
            return true;
        }

        return false;
    }

    public static function requireAccess($accessBits)
    {
        LeadsSession::start();

        if (!LeadsSession::isValid($accessBits)) {
            LeadsSession::deny();
        }

        //session_write_close();
    }

    private static function start()
    {
        if (!headers_sent() && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();

        }
    }

    private static function deny()
    {

        //session_write_close();

        if (isset($_REQUEST['a'])) {

            Header('Content-Type: application/json');
            $result = array(
                'status' => 0,
                'error' => 'Sorry, you do not have access to this page. Log back in and try again.',
            );
            echo json_encode($result);
            exit;

        } else {
            if (isset($_REQUEST['d'])) {

                echo "Sorry, you do not have access to this page. Log back in and try again.";
                exit;

            } else {

                header("Location: /leadadmin/");
                exit;

            }
        }

    }

    public static function getAccessBits(): array
    {
        return [
            LEADS_SESSION_LEVEL_ADMIN => 'Administrator',
            LEADS_SESSION_LEVEL_STAFF => 'Staff Member',

            LEADS_SESSION_LEVEL_CLIENT_DASHBOARD => 'Publisher Dashboard Access',
            LEADS_SESSION_LEVEL_CLIENT_IMPORT => 'Publisher Import Access',
            LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS => 'Publisher Phone Leads Report',
            LEADS_SESSION_LEVEL_CLIENT_REPORTS => 'Publisher Reporting Access',
            LEADS_SESSION_LEVEL_CRM => 'CRM Access',
            LEADS_SESSION_LEVEL_PPC => 'PPC Feed Access',

            LEADS_SESSION_LEVEL_SALESPERSON => 'Show in salesperson list',

            LEADS_SESSION_LEVEL_CALL_CENTER => 'Call Center Reporting Access',
            LEADS_SESSION_LEVEL_CALL_CENTER_AGENT => 'Call Center Agent Access',
            LEADS_SESSION_LEVEL_CALL_CENTER_HR_MANAGER => 'Call Center HR Manager Access',
            LEADS_SESSION_LEVEL_CALL_CENTER_QA => 'Call Center QA Access',
        ];
    }

    public static function getAccessBitDescription($bit): string
    {
        $bits = self::getAccessBits();

        return $bits[$bit] ?? 'Unknown access level';
    }

    public static function getEmailBits(): array
    {
        return [
            LeadsSession::EMAIL_BITS_DORMANT_URL => 'Dormant URL Notifications',
            LeadsSession::EMAIL_BITS_LEAD_THRESHOLD => 'Lead Threshold Notifications',
            LeadsSession::EMAIL_BITS_NEW_URL => 'New URL Notifications',
            LeadsSession::EMAIL_BITS_NEW_USER => 'New User Added Notifications',
            LeadsSession::EMAIL_BITS_ACCOUNTING => 'BCC Accounting Notifications',
            LeadsSession::EMAIL_BITS_CRM => 'BCC CRM Followup Notifications',

            LeadsSession::EMAIL_BITS_JOB_STATUS => 'BCC Job Status Notifications',
            LeadsSession::EMAIL_BITS_DIALER_LICENSING_REPORT => 'Dialer Agent Licensing Report',
            LeadsSession::EMAIL_BITS_DIALER_STATS_REPORT => 'Dialer Stats Report',
            LeadsSession::EMAIL_BITS_PAYROLL => 'Dialer Payroll Report',

            LeadsSession::EMAIL_BITS_DEVELOPER => 'Developer Notifications',
        ];
    }

    public static function getEmailBitDescription($bit): string
    {
        $bits = self::getEmailBits();

        return $bits[$bit] ?? 'Unknown email notification';
    }
}
