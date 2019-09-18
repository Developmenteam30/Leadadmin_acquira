<?php

require_once('c_config.php');

class Leads_PDOException extends PDOException
{
}

class Leads
{
    protected $db;
    protected static $instance;

    public static function getInstance()
    {

        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct($persistent = true)
    {

        // Connect to the database
        try {
            $this->db = new PDO('mysql:host=' . DATABASE_HOST . ';dbname=' . DATABASE_NAME, $GLOBALS['connxSettings']['insertUpdate']['u'], $GLOBALS['connxSettings']['insertUpdate']['p'], array(\PDO::ATTR_PERSISTENT => $persistent));
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {

            $this->logError('Database connection error: ' . $e->getMessage());
            print "Error connecting to the database";
            die();

        }
    }

    public function parseUrl($url)
    {
        if (empty($url)) {
            return '';
        }

        $url = strtolower($url);

        if (strpos($url, 'http') === false) {
            $url = 'http://' . $url;
        }

        if (($hostname = parse_url($url, PHP_URL_HOST)) !== false) {
            return str_replace('www.', '', $hostname);
        }

        return $url;
    }

    protected function quoteIdentifier($value)
    {
        $q = '`';
        return ($q . str_replace("$q", "$q$q", $value) . $q);
    }

    private function insertRow($table, array $data, $logError = true)
    {
        $cols = array();
        $vals = array();

        foreach ($data as $col => $val) {
            $cols[] = $this->quoteIdentifier($col);
            $vals[] = '?';
        }

        try {
            $query = $this->db->prepare('INSERT INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');
            $query->execute(array_values($data));
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Leads_PDOException('Unable to insert record', null, $e);
        }

        return null;
    }

    private function replaceRow($table, array $data, $logError = true)
    {
        $cols = array();
        $vals = array();

        foreach ($data as $col => $val) {
            $cols[] = $this->quoteIdentifier($col);
            $vals[] = '?';
        }

        try {
            $query = $this->db->prepare('REPLACE INTO ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')');
            $query->execute(array_values($data));
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Leads_PDOException('Unable to replace record', null, $e);
        }

        return null;
    }

    private function update($table, array $data, array $where = array())
    {
        $cols = array();
        $where_cols = array();

        if (empty($data)) {
            return null;
        }
        foreach ($data as $col => $val) {
            $cols[] = $this->quoteIdentifier($col) . ' = ?';
        }

        if (!empty($where)) {
            foreach ($where as $col => $val) {
                $where_cols[] = $this->quoteIdentifier($col) . ' = ?';
            }
        }

        try {
            $sql = 'UPDATE ' . $this->quoteIdentifier($table) . ' SET ' . implode(', ', $cols);
            if (!empty($where_cols)) {
                $sql .= ' WHERE ' . implode(' AND ', $where_cols);
            }

            $query = $this->db->prepare($sql);
            $query->execute(array_merge(array_values($data), array_values($where)));
            return true;
        } catch (PDOException $e) {
            throw new Leads_PDOException('Unable to update table', null, $e);
        }

        return null;
    }

    public function setBufferedQuery()
    {
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }

    public function unsetBufferedQuery()
    {
        $this->db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    }

    public function lockTables($tables)
    {
        if (!empty($tables)) {
            try {
                $this->db->query("LOCK TABLES " . $tables);
            } catch (PDOException $e) {
                throw new Leads_PDOException('Unable to lock tables', null, $e);
            }
        }
    }

    public function unlockTables()
    {
        try {
            $this->db->query("UNLOCK TABLES");
        } catch (PDOException $e) {
            throw new Leads_PDOException('Unable to unlock tables', null, $e);
        }
    }

    public function setNetTimeouts($timeout = 60)
    {
        try {
            $this->db->query("SET @@local.net_read_timeout = " . intval($timeout));
            $this->db->query("SET @@local.net_write_timeout = " . intval($timeout));
        } catch (PDOException $e) {
            throw new Leads_PDOException('Unable to set net timeouts', null, $e);
        }
    }

    public function getConnectionId()
    {

        $results = null;
        try {
            $query = $this->db->prepare("SELECT CONNECTION_ID()");
            $query->execute();
            $results = $query->fetchColumn();
        } catch (\PDOException $e) {
            $this->logError('Unable to get connection ID: ' . $e->getMessage());
        }

        return $results;
    }

    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->inTransaction() ? $this->db->commit() : false;
    }

    public function inTransaction()
    {
        return $this->db->inTransaction();
    }

    public function rollBack()
    {
        return $this->db->inTransaction() ? $this->db->rollBack() : false;
    }

    public function getConfiguration($config_key)
    {
        $value = null;

        $defaults = array(
            'notify_interval_1' => 12,
            'notify_interval_2' => 24,
        );

        try {
            $query = $this->db->prepare("SELECT config_value FROM configuration WHERE config_key = ?");
            $query->execute(array($config_key));
            $value = $query->fetchColumn();

            // If the value was not found in the database, check the hard-coded defaults
            if ($value === false) {
                if (isset($defaults[$config_key])) {
                    $value = $defaults[$config_key];
                } else {
                    $value = null;
                }
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get configuration value for (' . $config_key . '): ' . $e->getMessage());
        }

        return $value;
    }

    public function addUser($username, $password, $fullName, $idCompany, $level, $email)
    {

        try {
            $idUser = $this->insertRow('users', array(
                'username' => $username,
                'fullName' => $fullName,
                'idCompany' => $idCompany,
                'level' => $level,
                'email' => $email,
            ));
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add user: ' . $pdoException->getMessage());
        }

        $this->setPasswordHash($username, $password);

        return $idUser;
    }

    public function updateUser($idUser, $fields)
    {

        try {
            $status = $this->update('users', $fields, array(
                'idUser' => $idUser,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update user: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getUser($idUser)
    {
        try {
            $query = $this->db->prepare("SELECT idUser,username,password,fullName,idCompany,level,email FROM users WHERE idUser = ?");
            $query->execute(array($idUser));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get user information (idUser): ' . $e->getMessage());
        }

        return $results;
    }

    public function getUsername($username)
    {
        try {
            $query = $this->db->prepare("SELECT username,password,fullName,idCompany,level FROM users WHERE username = ?");
            $query->execute(array($username));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get user information (username): ' . $e->getMessage());
        }

        return $results;
    }

    public function getUsers($status = 'active')
    {

        $results = null;

        try {

            $sql = "SELECT idUser,username,fullName,idCompany,level ";
            $sql .= "FROM users ";
            if (!empty($status) && 'active' === $status) {
                $sql .= "WHERE isArchived = 0 ";
            } elseif (!empty($status) && 'archived' === $status) {
                $sql .= "WHERE isArchived = 1 ";
            }
            $sql .= "ORDER BY username";

            $query = $this->db->prepare($sql);
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get user list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getStaffUsers($format = \PDO::FETCH_KEY_PAIR, $forceAll = false, $idUser = null)
    {
        $results = null;

        try {
            if ($forceAll || LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                if (!empty($idUser)) {
                    // Sometimes we have a former employee set who would no longer show up in the user list. Force them to show if the value is currently set to their userId.
                    $query = $this->db->prepare("SELECT idUser,fullName FROM users WHERE ( level >= ? AND level < ? ) OR idUser = ? ORDER BY username");
                    $query->execute(array(LEADS_SESSION_LEVEL_STAFF, LEADS_SESSION_LEVEL_ADMIN, $idUser));
                } else {
                    $query = $this->db->prepare("SELECT idUser,fullName FROM users WHERE level >= ? AND level < ? ORDER BY username");
                    $query->execute(array(LEADS_SESSION_LEVEL_STAFF, LEADS_SESSION_LEVEL_ADMIN));
                }
            } else {
                $query = $this->db->prepare("SELECT idUser,fullName FROM users WHERE idUser = ?");
                $query->execute(array(LeadsSession::getUserId()));
            }
            $results = $query->fetchAll($format);
        } catch (PDOException $e) {
            $this->logError('Unable to get user staff list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getDashboardRevenueUsers()
    {
        $results = null;

        try {
            $query = $this->db->prepare("SELECT idUser,fullName FROM users WHERE level = ? AND idUser NOT IN (2, 5, 63, 67) ORDER BY username");
            $query->execute(array(LEADS_SESSION_LEVEL_STAFF));
            $results = $query->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $this->logError('Unable to get dashboard revenue users: ' . $e->getMessage());
        }

        return $results;
    }

    public function findClientUser($idCompany)
    {
        try {
            $query = $this->db->prepare("SELECT username FROM users WHERE idCompany = ? AND level = ?");
            $query->execute(array($idCompany, LEADS_SESSION_LEVEL_CLIENT_REPORTS));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get client user information: ' . $e->getMessage());
        }

        return $results;
    }

    public function verifyUser($username, $password)
    {
        try {
            $query = $this->db->prepare("SELECT idUser,username,password,idCompany,level FROM users WHERE username = ?");
            $query->execute(array($username));
            $results = $query->fetch();

            if ($results) {

                if (password_verify($password, $results['password'])) {

                    // If the password hash is outdated, rehash and save to the database
                    if (password_needs_rehash($results['password'], PASSWORD_DEFAULT, array('cost' => 11))) {
                        $this->setPasswordHash($username, $password);
                    }

                    return array(
                        'idUser' => $results['idUser'],
                        'level' => $results['level'],
                        'idCompany' => $results['idCompany'],
                    );
                }
            }

        } catch (PDOException $e) {
            $this->logError('Unable to verify user password: ' . $e->getMessage());
        }

        $this->logError('Failed login for user [' . $username . '] from [' . $_SERVER['REMOTE_ADDR'] . ']', true);

        return null;
    }

    public function setPasswordHash($username, $password)
    {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT, array('cost' => 11));

            $query = $this->db->prepare("UPDATE users SET password = ? WHERE username = ?");
            $query->execute(array($hash, $username));
        } catch (PDOException $e) {
            $this->logError('Unable to set password hash: ' . $e->getMessage());
        }
    }

    public function auditLog($action, $notes = null)
    {

        require_once(INCLUDES . 'session.php');
        try {
            $this->insertRow('auditlog', array(
                'userId' => LeadsSession::getUserId(),
                'ipaddress' => $_SERVER['REMOTE_ADDR'],
                'action' => $action,
                'notes' => $notes,
            ));
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add audit log: ' . $pdoException->getMessage());
        }
    }

    public function getAuditLog()
    {
        try {
            $query = $this->db->prepare("SELECT a.logId,a.timestamp,a.ipaddress,a.userId,u.username,a.action,a.notes FROM auditlog a LEFT JOIN users u ON a.userId = u.idUser WHERE a.timestamp >= DATE_SUB(NOW(),INTERVAL 60 DAY) ORDER BY a.logId DESC");
            $query->execute();
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get auditlog: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function setExpectationValues($userId, $month, $existingBusinessAmount, $newBusinessAmount)
    {

        try {
            $query = $this->db->prepare("REPLACE INTO forecast_expectations( userId, expectationMonth, existingBusinessAmount, newBusinessAmount ) VALUES( ?, ?, ?, ? )");
            $query->execute(array($userId, $month . '01', $existingBusinessAmount, $newBusinessAmount));
        } catch (PDOException $e) {
            $this->logError('Unable to update expectation value: ' . $e->getMessage());
            return;
        }

    }

    public function getExpectationValues($userId, $month)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT existingBusinessAmount,newBusinessAmount FROM forecast_expectations WHERE userId = ? AND expectationMonth = ?");
            $query->execute(array($userId, $month . '01'));
            $results = $query->fetch(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get expectation value: ' . $e->getMessage());
        }

        return $results;
    }

    public function getForecasts($startDate, $endDate, $offline = false)
    {
        $results = null;
        $params = array();

        try {

            $sql = "SELECT idUser,fullName,SUM(existingRevenueMTD) AS existingRevenueMTD,SUM(newRevenueMTD) AS newRevenueMTD,SUM(accuralCostMTD) AS accuralCostMTD ";
            $sql .= "FROM ( ";

            $sql .= "(SELECT u.idUser,u.fullName,0 AS existingRevenueMTD,SUM(sc.revenuePerLead*sc.billable*0.5) AS newRevenueMTD,SUM(sc.costPerLead*sc.billable*0.5) AS accuralCostMTD ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fo.idCompany ";
            $sql .= "LEFT JOIN users u ON u.idUser = c.salesperson ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fo.launchDate >= CAST(? AS DATE) ";
            $params[] = $startDate;
            $sql .= "AND c.salesperson IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND c.salesperson = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,0 AS existingRevenueMTD,SUM(sc.revenuePerLead*sc.billable*0.5) AS newRevenueMTD,SUM(sc.costPerLead*sc.billable*0.5) AS accuralCostMTD ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fi.idCompany ";
            $sql .= "LEFT JOIN users u ON u.idUser = c.salesperson ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fo.launchDate >= CAST(? AS DATE) ";
            $params[] = $startDate;
            $sql .= "AND c.salesperson IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND c.salesperson = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(sc.revenuePerLead*sc.billable*0.5) AS existingRevenueMTD,0 AS newRevenueMTD,SUM(sc.costPerLead*sc.billable*0.5) AS accuralCostMTD ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fo.idCompany ";
            $sql .= "LEFT JOIN users u ON u.idUser = c.salesperson ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND ( fo.launchDate IS NULL OR fo.launchDate < CAST(? AS DATE) ) ";
            $params[] = $startDate;
            $sql .= "AND c.salesperson IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND c.salesperson = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(sc.revenuePerLead*sc.billable*0.5) AS existingRevenueMTD,0 AS newRevenueMTD,SUM(sc.costPerLead*sc.billable*0.5) AS accuralCostMTD ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fi.idCompany ";
            $sql .= "LEFT JOIN users u ON u.idUser = c.salesperson ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND ( fo.launchDate IS NULL OR fo.launchDate < CAST(? AS DATE) ) ";
            $params[] = $startDate;
            $sql .= "AND c.salesperson IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND c.salesperson = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            // Table: ledger

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue1='existing',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue1='new',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(l.paymentAmount*IF(l.userId2 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger AS l ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId1 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue1 IS NOT NULL ";
            $sql .= "AND l.userId1 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId1 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue2='existing',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue2='new',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(l.paymentAmount*IF(l.userId1 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger AS l ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId2 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue2 IS NOT NULL ";
            $sql .= "AND l.userId2 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId2 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            // Table: ledger_phones

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue1='existing',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue1='new',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(lpv.loInvoiceAmount*IF(l.userId2 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger_phones AS l ";
            $sql .= "LEFT JOIN ( ";
            $sql .= "    SELECT ledgerId, SUM(loInvoiceAmount) AS loInvoiceAmount ";
            $sql .= "    FROM ledger_phones_vendors ";
            $sql .= "    GROUP  BY 1 ";
            $sql .= ") AS lpv ON lpv.ledgerId = l.ledgerId ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId1 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue1 IS NOT NULL ";
            $sql .= "AND l.userId1 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId1 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue2='existing',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue2='new',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(lpv.loInvoiceAmount*IF(l.userId1 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger_phones AS l ";
            $sql .= "LEFT JOIN ( ";
            $sql .= "    SELECT ledgerId, SUM(loInvoiceAmount) AS loInvoiceAmount ";
            $sql .= "    FROM ledger_phones_vendors ";
            $sql .= "    GROUP  BY 1 ";
            $sql .= ") AS lpv ON lpv.ledgerId = l.ledgerId ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId2 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue2 IS NOT NULL ";
            $sql .= "AND l.userId2 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId2 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            // Table: ledger_offline

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue1='existing',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue1='new',l.invoiceAmount,0)*IF(l.userId2 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(l.loInvoiceAmount*IF(l.userId2 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger_offline AS l ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId1 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue1 IS NOT NULL ";
            $sql .= "AND l.userId1 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId1 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= "UNION ALL ";

            $sql .= "(SELECT u.idUser,u.fullName,SUM(IF(l.commissionRevenue2='existing',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS existingRevenueMTD,SUM(IF(l.commissionRevenue2='new',l.invoiceAmount,0)*IF(l.userId1 IS NOT NULL,0.5,1)) AS newRevenueMTD,SUM(l.loInvoiceAmount*IF(l.userId1 IS NOT NULL,0.5,1)) AS accuralCostMTD ";
            $sql .= "FROM ledger_offline AS l ";
            $sql .= "LEFT JOIN users u ON u.idUser = l.userId2 ";
            $sql .= "WHERE l.ledgerMonth BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND l.commissionRevenue2 IS NOT NULL ";
            $sql .= "AND l.userId2 IS NOT NULL ";
            if (!$offline && !LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
                $sql .= "AND l.userId2 = ? ";
                $params[] = LeadsSession::getUserId();
            }
            $sql .= "GROUP BY u.idUser) ";

            $sql .= ") AS t1 GROUP BY idUser";

            //echo $sql;

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get forecasts: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueStatsCompany($startDate, $endDate, $idUser = null)
    {
        $results = null;
        $params = array();

        try {

            $sql = "SELECT co.idCompany,co.name,SUM(sc.accepted) AS accepted,SUM(sc.revenuePerLead*sc.billable) AS revenue,SUM(sc.costPerLead*sc.billable) AS expense ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies co ON co.idCompany = fo.idCompany ";
            $sql .= "LEFT JOIN companies ci ON ci.idCompany = fi.idCompany ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fo.feedCategory = 'phone' ";
            if (!empty($idUser)) {
                $sql .= "AND ( ( ( fo.salesperson IS NULL AND co.salesperson = ? ) OR ( fo.salesperson IS NOT NULL and fo.salesperson = ? ) ) OR ( ( fi.salesperson IS NULL AND ci.salesperson = ? ) OR ( fi.salesperson IS NOT NULL and fi.salesperson = ? ) ) ) ";
                $params[] = $idUser;
                $params[] = $idUser;
                $params[] = $idUser;
                $params[] = $idUser;
            }
            $sql .= "GROUP BY co.idCompany ";
            $sql .= "HAVING (SUM(sc.revenuePerLead*sc.billable) > 0 OR SUM(sc.costPerLead*sc.billable) > 0 ) ";
            $sql .= "ORDER BY co.name ";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get revenue stats company: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueStatsUser($startDate, $endDate, $idCompany, $idUser, $idFeedOut = null, $idFeedIn = null)
    {
        $results = null;
        $params = array();

        try {

            $sql = "SELECT SUM(revenue-expense) AS gross ";
            $sql .= "FROM ( ";

            $sql .= "SELECT SUM(sc.accepted) AS accepted,SUM(sc.revenuePerLead*sc.billable*0.5) AS revenue,SUM(sc.costPerLead*sc.billable*0.5) AS expense ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies co ON co.idCompany = fo.idCompany ";
            $sql .= "LEFT JOIN companies ci ON ci.idCompany = fi.idCompany ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fo.feedCategory = 'phone' ";
            if (!empty($idFeedOut)) {
                $sql .= "AND sc.idFeedOut = ? ";
                $params[] = $idFeedOut;
            } else {
                $sql .= "AND co.idCompany = ? ";
                $params[] = $idCompany;
            }
            if (!empty($idFeedIn)) {
                $sql .= "AND sc.idFeedIn = ? ";
                $params[] = $idFeedIn;
            }
            $sql .= "AND ( ( fo.salesperson IS NULL AND co.salesperson = ? ) OR ( fo.salesperson IS NOT NULL and fo.salesperson = ? ) ) ";
            $params[] = $idUser;
            $params[] = $idUser;
            $sql .= "GROUP BY co.idCompany ";

            $sql .= "UNION ALL ";

            $sql .= "SELECT SUM(sc.accepted) AS accepted,SUM(sc.revenuePerLead*sc.billable*0.5) AS revenue,SUM(sc.costPerLead*sc.billable*0.5) AS expense ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies co ON co.idCompany = fo.idCompany ";
            $sql .= "LEFT JOIN companies ci ON ci.idCompany = fi.idCompany ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fi.feedCategory = 'phone' ";
            if (!empty($idFeedOut)) {
                $sql .= "AND sc.idFeedOut = ? ";
                $params[] = $idFeedOut;
            } else {
                $sql .= "AND co.idCompany = ? ";
                $params[] = $idCompany;
            }
            if (!empty($idFeedIn)) {
                $sql .= "AND sc.idFeedIn = ? ";
                $params[] = $idFeedIn;
            }
            $sql .= "AND ( ( fi.salesperson IS NULL AND ci.salesperson = ? ) OR ( fi.salesperson IS NOT NULL and fi.salesperson = ? ) ) ";
            $params[] = $idUser;
            $params[] = $idUser;
            $sql .= "GROUP BY ci.idCompany ";

            $sql .= ") AS aggstats ";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get revenue stats user: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueStatsOutbound($idCompany, $startDate, $endDate)
    {
        $results = null;
        $params = array();

        try {

            $sql = "SELECT fo.idFeedOut,fo.label,fo.description,SUM(sc.accepted) AS accepted,SUM(sc.revenuePerLead*sc.billable) AS revenue,SUM(sc.costPerLead*sc.billable) AS expense ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedout AS fo ON fo.idFeedOut = sc.idFeedOut ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fo.idCompany ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fo.feedCategory = 'phone' ";
            $sql .= "AND c.idCompany = ? ";
            $params[] = $idCompany;
            $sql .= "GROUP BY sc.idFeedOut ";
            $sql .= "HAVING (SUM(sc.revenuePerLead*sc.billable) > 0 OR SUM(sc.costPerLead*sc.billable) > 0 ) ";
            $sql .= "ORDER BY fo.idFeedOut ";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get revenue stats outbound: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueStatsInbound($idFeedOut, $startDate, $endDate)
    {
        $results = null;
        $params = array();

        try {

            $sql = "SELECT c.name,fi.idFeedIn,fi.label,fi.description,SUM(sc.accepted) AS accepted,SUM(sc.revenuePerLead*sc.billable) AS revenue,SUM(sc.costPerLead*sc.billable) AS expense ";
            $sql .= "FROM stats_correlated AS sc ";
            $sql .= "JOIN feedinc AS fi ON fi.idFeedIn = sc.idFeedIn ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fi.idCompany ";
            $sql .= "WHERE sc.stamp BETWEEN CAST(? AS DATE) AND CAST(? AS DATE) ";
            $params[] = $startDate;
            $params[] = $endDate;
            $sql .= "AND fi.feedCategory = 'phone' ";
            $sql .= "AND sc.idFeedOut = ? ";
            $params[] = $idFeedOut;
            $sql .= "GROUP BY sc.idFeedIn ";
            $sql .= "HAVING (SUM(sc.revenuePerLead*sc.billable) > 0 OR SUM(sc.costPerLead*sc.billable) > 0 ) ";
            $sql .= "ORDER BY fi.idFeedIn ";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get revenue stats inbound: ' . $e->getMessage());
        }

        return $results;
    }

    public function checkCompanyName($name, $idCompany = null)
    {
        $result = false;

        try {
            if (!empty($idCompany)) {
                $query = $this->db->prepare("SELECT 1 FROM companies WHERE name = ? AND idCompany != ?");
                $query->execute(array($name, $idCompany));
            } else {
                $query = $this->db->prepare("SELECT 1 FROM companies WHERE name = ?");
                $query->execute(array($name));
            }
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get company info: ' . $e->getMessage());
        }

        return $result;
    }

    public function addLedger($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->insertRow('ledger', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add ledger entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function addLedgerVendor($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->insertRow('ledger_vendors', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add ledger vendor entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function replaceLedgerVendor($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->replaceRow('ledger_vendors', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to replace ledger vendor entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function addOfflineLedger($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->insertRow('ledger_offline', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add offline ledger entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function deleteLedger($ledgerId)
    {

        try {
            $query = $this->db->prepare("DELETE FROM ledger WHERE ledgerId = ?");
            $query->execute(array($ledgerId));
        } catch (\Exception $e) {
            $this->logError('Unable to delete ledger entry: ' . $e->getMessage());
            return null;
        }

        return true;
    }

    public function deleteOfflineLedger($ledgerId)
    {

        try {
            $query = $this->db->prepare("DELETE FROM ledger_offline WHERE ledgerId = ?");
            $query->execute(array($ledgerId));
        } catch (\Exception $e) {
            $this->logError('Unable to delete offline ledger entry: ' . $e->getMessage());
            return null;
        }

        return true;
    }

    public function deletePhoneLedger($ledgerId)
    {

        try {
            $query = $this->db->prepare("DELETE FROM ledger_phones WHERE ledgerId = ?");
            $query->execute(array($ledgerId));
        } catch (\Exception $e) {
            $this->logError('Unable to delete phones ledger entry: ' . $e->getMessage());
            return null;
        }

        return true;
    }

    public function updateLedger($ledgerId, $fields)
    {

        try {
            $status = $this->update('ledger', $fields, array(
                'ledgerId' => $ledgerId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update ledger: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function updateOfflineLedger($ledgerId, $fields)
    {

        try {
            $status = $this->update('ledger_offline', $fields, array(
                'ledgerId' => $ledgerId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update offline ledger: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function addPhoneLedger($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->insertRow('ledger_phones', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add phone ledger entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function updatePhoneLedger($ledgerId, $fields)
    {

        try {
            $status = $this->update('ledger_phones', $fields, array(
                'ledgerId' => $ledgerId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update phones ledger: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function addPhoneLedgerVendor($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->insertRow('ledger_phones_vendors', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add phone ledger vendor entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function replacePhoneLedgerVendor($fields)
    {

        $ledgerId = null;

        try {
            $ledgerId = $this->replaceRow('ledger_phones_vendors', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to replace phone ledger vendor entry: ' . $pdoException->getMessage());
            return null;
        }

        return $ledgerId;
    }

    public function getLedgerById($ledgerId)
    {
        $results = null;
        $params = array();

        $sql = "SELECT l.*,";
        for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
            $sql .= sprintf("lv%d.vendorCompanyId AS vendorCompanyId%d,lv%d.loInvoiceNum AS loInvoiceNum%d,lv%d.loInvoiceAmount AS loInvoiceAmount%d,lv%d.loPaymentDate AS loPaymentDate%d,lv%d.loPaymentMethod AS loPaymentMethod%d,lv%d.loPaymentAmount AS loPaymentAmount%d,",
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
        $sql .= "FROM ledger l ";
        for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
            $sql .= sprintf("LEFT JOIN ledger_vendors lv%d ON l.ledgerId = lv%d.ledgerId AND lv%d.indexId = %d ",
                $i,
                $i,
                $i,
                $i
            );
        }
        $sql .= "WHERE l.ledgerId = ? ";
        $params[] = $ledgerId;
        if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
            $sql .= "AND l.userId = ? ";
            $params[] = LeadsSession::getUserId();
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger entry: ' . $e->getMessage());
        }

        return $results;
    }

    public function getLedgerByIdIndex($ledgerId, $indexId)
    {
        $results = null;
        $params = array();

        $sql = "SELECT l.*,lv.vendorCompanyId AS vendorCompanyId,lv.loInvoiceNum AS loInvoiceNum,lv.loInvoiceAmount AS loInvoiceAmount,lv.loPaymentDate AS loPaymentDate,lv.loPaymentMethod AS loPaymentMethod,lv.loPaymentAmount AS loPaymentAmount ";
        $sql .= "FROM ledger l ";
        $sql .= "LEFT JOIN ledger_vendors lv ON l.ledgerId = lv.ledgerId AND lv.indexId = ? ";
        $params[] = $indexId;
        $sql .= "WHERE l.ledgerId = ? ";
        $params[] = $ledgerId;
        if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
            $sql .= "AND ( userId1 = ? OR userId2 = ? OR userId3 = ? )";
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger index entry: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOfflineLedgerById($ledgerId)
    {
        $results = null;
        $params = array();

        $sql = "SELECT * FROM ledger_offline WHERE ledgerId = ? ";
        $params[] = $ledgerId;
        if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
            $sql .= "AND ( userId1 = ? OR userId2 = ? OR userId3 = ? )";
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger offline index entry: ' . $e->getMessage());
        }

        return $results;
    }

    public function getPhoneLedgerById($ledgerId)
    {
        $results = null;
        $params = array();

        $sql = "SELECT l.*,";
        for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
            $sql .= sprintf("lv%d.vendorCompanyId AS vendorCompanyId%d,lv%d.loInvoiceNum AS loInvoiceNum%d,lv%d.loInvoiceAmount AS loInvoiceAmount%d,lv%d.loPaymentDate AS loPaymentDate%d,lv%d.loPaymentMethod AS loPaymentMethod%d,lv%d.loPaymentAmount AS loPaymentAmount%d,",
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
        for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
            $sql .= sprintf("LEFT JOIN ledger_phones_vendors lv%d ON l.ledgerId = lv%d.ledgerId AND lv%d.indexId = %d ",
                $i,
                $i,
                $i,
                $i
            );
        }
        $sql .= "WHERE l.ledgerId = ? ";
        $params[] = $ledgerId;
        if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
            $sql .= "AND ( userId1 = ? OR userId2 = ? OR userId3 = ? ) ";
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get phone ledger entry: ' . $e->getMessage());
        }

        return $results;
    }

    public function getPhoneLedgerByIdIndex($ledgerId, $indexId)
    {
        $results = null;
        $params = array();

        $sql = "SELECT l.*,lv.vendorCompanyId AS vendorCompanyId,lv.loInvoiceNum AS loInvoiceNum,lv.loInvoiceAmount AS loInvoiceAmount,lv.loPaymentDate AS loPaymentDate,lv.loPaymentMethod AS loPaymentMethod,lv.loPaymentAmount AS loPaymentAmount ";
        $sql .= "FROM ledger_phones l ";
        $sql .= "LEFT JOIN ledger_phones_vendors lv ON l.ledgerId = lv.ledgerId AND lv.indexId = ? ";
        $params[] = $indexId;
        $sql .= "WHERE l.ledgerId = ? ";
        $params[] = $ledgerId;
        if (!LeadsSession::isValid(LEADS_SESSION_LEVEL_MANAGER)) {
            $sql .= "AND ( userId1 = ? OR userId2 = ? OR userId3 = ? )";
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
            $params[] = LeadsSession::getUserId();
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get phone ledger index entry: ' . $e->getMessage());
        }

        return $results;
    }

    public function getLedgerByDivision($divisionId, $type)
    {
        $results = array();

        $sql = "SELECT l.*,c.name AS companyName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
        $sql .= "FROM ledger l ";
        $sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
        $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
        $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
        $sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
        $sql .= "WHERE l.divisionId = ? ";
        $sql .= "AND l.type = ? ";
        $sql .= "ORDER BY l.ledgerMonth,companyName";

        try {
            $query = $this->db->prepare($sql);
            $query->execute(array($divisionId, $type));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger: ' . $e->getMessage());
        }

        return $results;
    }

    public function getLedger($type, $onlyMonths = false, $month = null)
    {
        $results = array();
        $params = array();

        if (1 == $type) {
            if (!empty($onlyMonths)) {
                $sql = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
            } else {
                $sql = "SELECT l.*,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,";
                for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
                    $sql .= sprintf('vc%1$d.name AS vendorCompanyName%1$d,lv%1$d.loInvoiceNum AS loInvoiceNum%1$d,lv%1$d.loInvoiceAmount AS loInvoiceAmount%1$d,lv%1$d.loPaymentDate AS loPaymentDate%1$d,lv%1$d.loPaymentMethod AS loPaymentMethod%1$d,lv%1$d.loPaymentAmount AS loPaymentAmount%1$d,',
                        $i
                    );
                }
                $sql .= "c.name AS companyName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
            }
            $sql .= "FROM ledger l ";
            for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
                $sql .= sprintf('LEFT JOIN ledger_vendors lv%1$d ON l.ledgerId = lv%1$d.ledgerId AND lv%1$d.indexId = %1$d ',
                    $i
                );
                $sql .= sprintf('LEFT JOIN companies vc%1$d ON lv%1$d.vendorCompanyId = vc%1$d.idCompany ',
                    $i
                );
            }
        } else {
            if (!empty($onlyMonths)) {
                $sql = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
            } else {
                $sql = "SELECT l.*,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,c.name AS companyName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,c2.name AS vendorCompanyName1 ";
            }
            $sql .= "FROM ledger l ";
            $sql .= "LEFT JOIN companies c2 ON l.vendorCompanyId = c2.idCompany ";
        }
        $sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
        $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
        $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
        $sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
        $sql .= "WHERE l.type = ? ";
        $params[] = $type;
        if (!empty($month)) {
            if (strlen($month) == 4) {
                $sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
                $params[] = $month;
            } elseif (preg_match('/^(20[0-9]{2})-Q([1-4])$/', $month, $matches)) {
                $sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
                $params[] = $matches[1] . $matches[2];
            } else {
                $sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
                $params[] = $month;
            }
        }
        if (!empty($onlyMonths)) {
            $sql .= "GROUP BY l.ledgerMonth ";
            $sql .= "ORDER BY l.ledgerMonth DESC";
        } else {
            $sql .= "ORDER BY l.ledgerMonth,companyName";
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger: ' . $e->getMessage());
        }

        return $results;
    }

    public function getPhoneLedger($onlyMonths = false, $month = null)
    {
        $results = array();
        $params = array();

        if (!empty($onlyMonths)) {
            $sql = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
        } else {
            $sql = "SELECT l.*,CONCAT('L',l.ledgerId) AS entryId,";
            for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
                $sql .= sprintf("vc%d.name AS vendorCompanyName%d,lv%d.loInvoiceNum AS loInvoiceNum%d,lv%d.loInvoiceAmount AS loInvoiceAmount%d,lv%d.loPaymentDate AS loPaymentDate%d,lv%d.loPaymentMethod AS loPaymentMethod%d,lv%d.loPaymentAmount AS loPaymentAmount%d,",
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
        for ($i = 1; $i <= MAX_PHONE_LEADS_VENDORS; $i++) {
            $sql .= sprintf("LEFT JOIN ledger_phones_vendors lv%d ON l.ledgerId = lv%d.ledgerId AND lv%d.indexId = %d ",
                $i,
                $i,
                $i,
                $i
            );
            $sql .= sprintf("LEFT JOIN companies vc%d ON lv%d.vendorCompanyId = vc%d.idCompany ",
                $i,
                $i,
                $i
            );
        }
        $sql .= "LEFT JOIN companies cc ON l.clientCompanyId = cc.idCompany ";
        $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
        $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
        $sql .= "WHERE 1=1 ";
        if (!empty($month)) {
            if (strlen($month) == 4) {
                $sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
                $params[] = $month;
            } elseif (preg_match('/^(20[0-9]{2})-Q([1-4])$/', $month, $matches)) {
                $sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
                $params[] = $matches[1] . $matches[2];
            } else {
                $sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
                $params[] = $month;
            }
        }
        if (!empty($onlyMonths)) {
            $sql .= "GROUP BY l.ledgerMonth ";
            $sql .= "ORDER BY l.ledgerMonth DESC";
        } else {
            $sql .= "GROUP BY l.ledgerId ";
            $sql .= "ORDER BY l.ledgerMonth";
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get phone ledger: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOfflineLedger($onlyMonths = false, $month = null)
    {
        $results = array();
        $params = array();

        if (!empty($onlyMonths)) {
            $sql = "SELECT DISTINCT(LEFT(l.ledgerMonth,7)) AS month ";
        } else {
            $sql = "SELECT l.*,CONCAT('O',l.ledgerId) AS entryId,vc.name AS vendorCompanyName,cc.name AS clientCompanyName,u1.fullName AS fullName1,u2.fullName AS fullName2 ";
        }
        $sql .= "FROM ledger_offline l ";
        $sql .= "LEFT JOIN companies vc ON l.vendorCompanyId = vc.idCompany ";
        $sql .= "LEFT JOIN companies cc ON l.clientCompanyId = cc.idCompany ";
        $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
        $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
        $sql .= "WHERE 1=1 ";
        if (!empty($month)) {
            if (strlen($month) == 4) {
                $sql .= "AND LEFT(l.ledgerMonth,4) = ? ";
                $params[] = $month;
            } elseif (preg_match('/^(20[0-9]{2})-Q([1-4])$/', $month, $matches)) {
                $sql .= "AND CONCAT(LEFT(l.ledgerMonth,4),QUARTER(l.ledgerMonth)) = ? ";
                $params[] = $matches[1] . $matches[2];
            } else {
                $sql .= "AND LEFT(l.ledgerMonth,7) = ? ";
                $params[] = $month;
            }
        }
        if (!empty($onlyMonths)) {
            $sql .= "GROUP BY l.ledgerMonth ";
            $sql .= "ORDER BY l.ledgerMonth DESC";
        } else {
            $sql .= "ORDER BY l.ledgerMonth,vendorCompanyName";
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get offline ledger: ' . $e->getMessage());
        }

        return $results;
    }

    public function getPaidLedger($type, $userId = null, $distinctColumn = null, $distinctValue = null)
    {
        $results = array();
        $params = array();
        $sql = '';

        if ($type === 0) {

            // Advertiser ledger entries use separate ledger_vendors for expenses
            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
            } else {
                $sql .= "SELECT l.ledgerId,l.divisionId,l.companyId,l.verticalId,lv.loPaymentDate AS paymentDate,lv.loPaymentMethod AS paymentMethod,l.ledgerMonth,lv.loInvoiceAmount AS invoiceAmount,lv.loInvoiceNum AS invoiceNum,lv.loPaymentAmount AS paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,l.type,l.userId1,l.userId2,l.userId3,CONCAT(IF(l.type=1,'A','P'),l.ledgerId,'-',lv.indexId) AS entryId,c.name AS companyName,d.name AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger_vendors' AS source,lv.indexId,clv.name AS vendorCompanyName ";
            }
            $sql .= "FROM ledger l ";
            $sql .= "LEFT JOIN ledger_vendors lv ON l.ledgerId = lv.ledgerId ";
            $sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
            $sql .= "LEFT JOIN companies clv ON lv.vendorCompanyId = clv.idCompany ";
            $sql .= "LEFT JOIN divisions d ON l.divisionId = d.divisionId ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
            $sql .= "WHERE type = 1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND lv.loPaymentDate IS NOT NULL ";
                $sql .= "AND lv.loPaymentAmount IS NOT NULL ";
                $sql .= "AND lv.loPaymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . str_replace('paymentDate', 'lv.loPaymentDate', $distinctColumn) . " = ? ";
                $params[] = $distinctValue;
            }

            $sql .= "UNION ";

            // Publisher ledger entries use regular fields for expenses
            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
            } else {
                $sql .= "SELECT l.ledgerId,l.divisionId,l.companyId,l.verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,l.type,l.userId1,l.userId2,l.userId3,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,c.name AS companyName,d.name AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger' AS source,0 AS indexId,c.name AS vendorCompanyName ";
            }
            $sql .= "FROM ledger l ";
            $sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
            $sql .= "LEFT JOIN companies clv ON l.vendorCompanyId = clv.idCompany ";
            $sql .= "LEFT JOIN divisions d ON l.divisionId = d.divisionId ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
            $sql .= "WHERE type = 0 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND l.paymentDate IS NOT NULL ";
                $sql .= "AND l.paymentAmount IS NOT NULL ";
                $sql .= "AND l.paymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . $distinctColumn . " = ? ";
                $params[] = $distinctValue;
            }

            $sql .= "UNION ";

            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . str_replace('ledgerMonth', "CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01')", $distinctColumn) . ") AS month ";
            } else {
                $sql .= "SELECT r.date as ledgerId,1 AS divisionId,c.idCompany AS companyId,5 AS verticalId,i.paymentDate,'ACH' AS paymentMethod,CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01') AS ledgerMonth,ROUND(SUM(r.value)*0.50,2) AS invoiceAmount,i.invoiceNumber AS invoiceNum,ROUND(SUM(r.value)*0.50,2) AS paymentAmount,NULL AS commissionAmount1,NULL AS commissionAmount2,NULL AS commissionDate1,NULL AS commissionDate2,NULL as commissionAmount3,NULL AS commissionDate3,0 AS type,u.idUser AS userId1,NULL AS userId2,NULL AS userId3,CONCAT('E',r.date) AS entryId,c.name AS companyName,'E-mail' AS divisionName,'Email Marketing' AS verticalName,u.fullName AS fullname1,NULL AS fullName2,NULL AS fullName3,'email' AS source,0 AS indexId,c.name AS vendorCompanyName ";
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
            if (!empty($userId)) {
                $sql .= "AND i.userId = ? ";
                $params[] = $userId;
            } else {
                $sql .= "AND i.paymentDate IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . str_replace('ledgerMonth', "CONCAT_WS('-',SUBSTRING(r.date,1,4),SUBSTRING(r.date,5,2),'01')", $distinctColumn) . " = ? ";
                $params[] = $distinctValue;
            }
            $sql .= "GROUP BY c.idCompany,r.date ";

            $sql .= "UNION ";

            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
            } else {
                $sql .= "SELECT l.ledgerId,4 AS divisionId,c.idCompany AS companyId,6 AS verticalId,l.loPaymentDate AS paymentDate,l.loPaymentMethod AS paymentMethod,l.ledgerMonth,l.loInvoiceAmount AS invoiceAmount,l.loInvoiceNum AS invoiceNum,l.loPaymentAmount AS paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,0 AS type,l.userId1,l.userId2,l.userId3,CONCAT('O',l.ledgerId) AS entryId,c.name AS companyName,'Offline' AS divisionName,'Offline Vertical' AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger_offline' AS source,0 AS indexId,c.name AS vendorCompanyName ";
            }
            $sql .= "FROM ledger_offline l ";
            $sql .= "LEFT JOIN companies c ON l.vendorCompanyId = c.idCompany ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "WHERE 1=1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND l.loPaymentDate IS NOT NULL ";
                $sql .= "AND l.loPaymentAmount IS NOT NULL ";
                $sql .= "AND l.loPaymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . str_replace('paymentDate', 'loPaymentDate', $distinctColumn) . " = ? ";
                $params[] = $distinctValue;
            }

            $sql .= "UNION ";

            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
            } else {
                $sql .= "SELECT l.ledgerId,5 AS divisionId,c.idCompany AS companyId,l.verticalId,lv.loPaymentDate AS paymentDate,lv.loPaymentMethod AS paymentMethod,l.ledgerMonth,lv.loInvoiceAmount AS invoiceAmount,lv.loInvoiceNum AS invoiceNum,lv.loPaymentAmount AS paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,0 AS type,l.userId1,l.userId2,l.userId3,CONCAT('L',l.ledgerId,'-',lv.indexId) AS entryId,c.name AS companyName,'Leads' AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger_phones' AS source,lv.indexId,clv.name AS vendorCompanyName  ";
            }
            $sql .= "FROM ledger_phones l ";
            $sql .= "LEFT JOIN ledger_phones_vendors lv ON l.ledgerId = lv.ledgerId ";
            $sql .= "LEFT JOIN companies clv ON lv.vendorCompanyId = clv.idCompany ";
            $sql .= "LEFT JOIN companies c ON lv.vendorCompanyId = c.idCompany ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "LEFT JOIN verticals v ON divisionId = 5 AND l.verticalId = v.verticalId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND lv.loPaymentDate IS NOT NULL ";
                $sql .= "AND lv.loPaymentAmount IS NOT NULL ";
                $sql .= "AND lv.loPaymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . str_replace('paymentDate', 'lv.loPaymentDate', $distinctColumn) . " = ? ";
                $params[] = $distinctValue;
            }

        } else {

            // Advertiser ledger entries use regular fields for income
            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") AS month ";
            } else {
                $sql .= "SELECT l.ledgerId,l.divisionId,l.companyId,l.verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,l.type,l.userId1,l.userId2,l.userId3,CONCAT(IF(l.type=1,'A','P'),l.ledgerId) AS entryId,c.name AS companyName,d.name AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger' AS source,0 AS indexId,clv.name AS vendorCompanyName  ";
            }
            $sql .= "FROM ledger l ";
            $sql .= "LEFT JOIN companies c ON l.companyId = c.idCompany ";
            $sql .= "LEFT JOIN companies clv ON l.vendorCompanyId = clv.idCompany ";
            $sql .= "LEFT JOIN divisions d ON l.divisionId = d.divisionId ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "LEFT JOIN verticals v ON l.divisionId = v.divisionId AND l.verticalId = v.verticalId ";
            $sql .= "WHERE type = 1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND l.paymentDate IS NOT NULL ";
                $sql .= "AND l.paymentAmount IS NOT NULL ";
                $sql .= "AND l.paymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . $distinctColumn . " = ? ";
                $params[] = $distinctValue;
            }

            $sql .= "UNION ";

            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") as month ";
            } else {
                $sql .= "SELECT l.ledgerId,4 AS divisionId,c.idCompany AS companyId,6 AS verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,1 AS type,l.userId1,l.userId2,l.userId3,CONCAT('O',l.ledgerId) AS entryId,c.name AS companyName,'Offline' AS divisionName,'Offline Vertical' AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger_offline' AS source,0 AS indexId,'' AS vendorCompanyName  ";
            }
            $sql .= "FROM ledger_offline l ";
            $sql .= "LEFT JOIN companies c ON l.clientCompanyId = c.idCompany ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "WHERE 1=1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND l.paymentDate IS NOT NULL ";
                $sql .= "AND l.paymentAmount IS NOT NULL ";
                $sql .= "AND l.paymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . $distinctColumn . " = ? ";
                $params[] = $distinctValue;
            }

            $sql .= "UNION ";

            if (!empty($distinctColumn) && empty($distinctValue)) {
                $sql .= "SELECT DISTINCT(" . $distinctColumn . ") as month ";
            } else {
                $sql .= "SELECT l.ledgerId,5 AS divisionId,c.idCompany AS companyId,l.verticalId,l.paymentDate,l.paymentMethod,l.ledgerMonth,l.invoiceAmount,l.invoiceNum,l.paymentAmount,l.commissionAmount1,l.commissionDate1,l.commissionAmount2,l.commissionDate2,l.commissionAmount3,l.commissionDate3,1 AS type,l.userId1,l.userId2,l.userId3,CONCAT('L',l.ledgerId) AS entryId,c.name AS companyName,'Leads' AS divisionName,v.name AS verticalName,u1.fullName AS fullName1,u2.fullName AS fullName2,u3.fullName AS fullName3,'ledger_phones' AS source,0 AS indexId,'' AS vendorCompanyName  ";
            }
            $sql .= "FROM ledger_phones l ";
            $sql .= "LEFT JOIN companies c ON l.clientCompanyId = c.idCompany ";
            $sql .= "LEFT JOIN users u1 ON l.userId1 = u1.idUser ";
            $sql .= "LEFT JOIN users u2 ON l.userId2 = u2.idUser ";
            $sql .= "LEFT JOIN users u3 ON l.userId3 = u3.idUser ";
            $sql .= "LEFT JOIN verticals v ON divisionId = 5 AND l.verticalId = v.verticalId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($userId)) {
                $sql .= "AND ( l.userId1 = ? OR l.userId2 = ? OR l.userId3 = ? ) ";
                $params[] = $userId;
                $params[] = $userId;
                $params[] = $userId;
            } else {
                $sql .= "AND l.paymentDate IS NOT NULL ";
                $sql .= "AND l.paymentAmount IS NOT NULL ";
                $sql .= "AND l.paymentMethod IS NOT NULL ";
            }
            if (!empty($distinctColumn) && !empty($distinctValue)) {
                $sql .= "AND " . $distinctColumn . " = ? ";
                $params[] = $distinctValue;
            }

        }

        if (!empty($distinctColumn) && empty($distinctValue)) {
            $sql .= "GROUP BY month ";
            $sql .= "ORDER BY month DESC ";
        } else {
            $sql .= "ORDER BY paymentDate ";
        }

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get ledger list: ' . $e->getMessage());
        }

        return $results;
    }

    public function addOpportunity($fields)
    {

        $opportunityId = null;

        try {
            $opportunityId = $this->insertRow('opportunities', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add opportunity: ' . $pdoException->getMessage());
            return null;
        }

        return $opportunityId;
    }

    public function updateOpportunity($opportunityId, $fields)
    {

        try {
            $status = $this->update('opportunities', $fields, array(
                'opportunityId' => $opportunityId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update opportunity: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getOpportunities($status = null)
    {
        $results = array();
        $params = array();

        try {
            $sql = "SELECT o.*,c.name AS companyName,d.name AS divisionName,u.fullName,MAX(timestamp) AS lastDate ";
            $sql .= "FROM opportunities o ";
            $sql .= "LEFT JOIN companies c ON o.companyId = c.idCompany ";
            $sql .= "LEFT JOIN users u ON o.userId = u.idUser ";
            $sql .= "LEFT JOIN divisions d ON o.divisionId = d.divisionId ";
            $sql .= "LEFT JOIN opportunities_notes n ON o.opportunityId = n.opportunityId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($status) && 'active' == $status) {
                $sql .= "AND o.status != 'retired' ";
            } elseif (!empty($status)) {
                $sql .= "AND o.status = ? ";
                $params[] = $status;
            }
            $sql .= "GROUP BY o.opportunityId ";
            $sql .= "ORDER BY o.opportunityId DESC";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get opportunity list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOpportunity($opportunityId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM opportunities WHERE opportunityId = ?");
            $query->execute(array($opportunityId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get opportunity info: ' . $e->getMessage());
        }

        return $results;
    }

    public function addOpportunityNote($fields)
    {

        $noteId = null;

        try {
            $noteId = $this->insertRow('opportunities_notes', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add opportunity note: ' . $pdoException->getMessage());
            return false;
        }

        return $noteId;
    }

    public function getOpportunityNotes($opportunityId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT n.*,u.fullName FROM opportunities_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE opportunityId = ? ORDER BY timestamp DESC");
            $query->execute(array($opportunityId));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get opportunity notes: ' . $e->getMessage());
        }

        return $results;
    }

    public function addInsertionOrder($fields)
    {

        $prospectId = null;

        try {
            $prospectId = $this->insertRow('insertion_orders', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add insertion order: ' . $pdoException->getMessage());
            return null;
        }

        return $prospectId;
    }

    public function getInsertionOrder($orderId)
    {
        $results = array();

        try {
            $sql = "SELECT o.*,c.*,c.name AS companyName,v.name AS verticalName,u.fullName,u.email ";
            $sql .= "FROM insertion_orders AS o ";
            $sql .= "LEFT JOIN users u ON o.userId = u.idUser ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = o.companyId ";
            $sql .= "LEFT JOIN verticals v ON v.verticalId = o.verticalId ";
            $sql .= "WHERE orderId = ?";

            $query = $this->db->prepare($sql);
            $query->execute(array($orderId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get insertion order info: ' . $e->getMessage());
        }

        return $results;
    }

    public function searchInsertionOrders($filters = array())
    {
        $results = array();

        try {
            $params = array();

            $sql = "SELECT o.*,c.name AS companyName,v.name AS verticalName,u.fullName ";
            $sql .= "FROM insertion_orders AS o ";
            $sql .= "LEFT JOIN users u ON o.userId = u.idUser ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = o.companyId ";
            $sql .= "LEFT JOIN verticals v ON v.verticalId = o.verticalId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($filters['text'])) {
                $sql .= "AND ( c.name LIKE ? OR o.notes LIKE ? ) ";
                $params[] = '%' . $filters['text'] . '%';
                $params[] = '%' . $filters['text'] . '%';
            }
            if (isset($filters['isArchived'])) {
                $sql .= "AND o.isArchived = ? ";
                $params[] = $filters['isArchived'];
            }
            if (!empty($filters['salesperson'])) {
                $sql .= "AND o.userId = ? ";
                $params[] = $filters['salesperson'];
            }
            if (!empty($filters['orderType'])) {
                $sql .= "AND o.orderType = ? ";
                $params[] = $filters['orderType'];
            }
            if (!empty($filters['verticals']) && is_array($filters['verticals'])) {
                $sql .= "AND (";
                foreach ($filters['verticals'] as $vertical) {
                    $sql .= "o.verticalId = ? OR ";
                    $params[] = $vertical;
                }
                $sql = substr($sql, 0, -3); // Remove the last OR
                $sql .= ")";
            }
            $sql .= "GROUP BY o.orderId ";
            $sql .= "ORDER BY o.orderId DESC";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to search insertion orders: ' . $e->getMessage());
        }

        return $results;
    }

    public function updateInsertionOrder($orderId, $fields)
    {

        try {
            $status = $this->update('insertion_orders', $fields, array(
                'orderId' => $orderId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update insertion order: ' . $pdoException->getMessage());
            return null;
        }
    }

    public function addProspect($fields)
    {

        $prospectId = null;

        try {
            $prospectId = $this->insertRow('prospects', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add prospect: ' . $pdoException->getMessage());
            return null;
        }

        return $prospectId;
    }

    public function updateProspect($prospectId, $fields)
    {

        try {
            $status = $this->update('prospects', $fields, array(
                'prospectId' => $prospectId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update prospect: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getProspects($status = null, $userId = null)
    {
        $results = array();
        $params = array();

        try {
            $sql = "SELECT p.*,n.*,u.fullName ";
            $sql .= "FROM prospects AS p ";
            $sql .= "LEFT JOIN prospects_notes AS n ON n.prospectId = p.prospectId ";
            $sql .= "LEFT JOIN users u ON p.userId = u.idUser ";
            $sql .= "JOIN (";
            $sql .= "SELECT MAX(noteId) AS noteId FROM prospects_notes GROUP BY prospectId ";
            $sql .= ") AS nm ON nm.noteId = n.noteId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($status) && 'active' == $status) {
                $sql .= "AND p.isArchived = 0 ";
            } elseif (!empty($status) && 'archived' == $status) {
                $sql .= "AND p.isArchived = 1 ";
            }
            if (!empty($userId)) {
                $sql .= "AND p.userId = ? ";
                $params[] = $userId;
            }
            $sql .= "GROUP BY p.prospectId ";
            $sql .= "ORDER BY p.prospectId DESC";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get prospect list: ' . $e->getMessage());
        }

        return $results;
    }

    public function searchProspects($filters = array())
    {
        $results = array();

        try {
            $params = array();

            $sql = "SELECT p.*,n.*,u.fullName ";
            $sql .= "FROM prospects AS p ";
            $sql .= "LEFT JOIN prospects_notes AS n ON n.prospectId = p.prospectId ";
            $sql .= "LEFT JOIN prospects_notes pn ON pn.prospectId = p.prospectId ";
            $sql .= "LEFT JOIN users u ON p.userId = u.idUser ";
            $sql .= "JOIN (";
            $sql .= "SELECT MAX(noteId) AS noteId FROM prospects_notes GROUP BY prospectId ";
            $sql .= ") AS nm ON nm.noteId = n.noteId ";
            $sql .= "WHERE 1=1 ";
            if (!empty($filters['text'])) {
                $sql .= "AND ( p.company LIKE ? OR p.name LIKE ? OR pn.note LIKE ? OR p.opportunity LIKE ? OR pn.nextSteps LIKE ? ) ";
                $params[] = '%' . $filters['text'] . '%';
                $params[] = '%' . $filters['text'] . '%';
                $params[] = '%' . $filters['text'] . '%';
                $params[] = '%' . $filters['text'] . '%';
                $params[] = '%' . $filters['text'] . '%';
            }
            if (isset($filters['isArchived'])) {
                $sql .= "AND p.isArchived = ? ";
                $params[] = $filters['isArchived'];
            }
            if (!empty($filters['salesperson'])) {
                $sql .= "AND p.userId = ? ";
                $params[] = $filters['salesperson'];
            }
            if (isset($filters['percentage'])) {
                $sql .= "AND p.percentage = ? ";
                $params[] = $filters['percentage'];
            }
            if (!empty($filters['companyType']) && 'isPublisher' == $filters['companyType']) {
                $sql .= "AND p.isPublisher = 1 ";
            }
            if (!empty($filters['companyType']) && 'isAdvertiser' == $filters['companyType']) {
                $sql .= "AND p.isAdvertiser = 1 ";
            }
            if (!empty($filters['divisions']) && is_array($filters['divisions'])) {
                $sql .= "AND (";
                foreach ($filters['divisions'] as $division) {
                    $sql .= "FIND_IN_SET(?,divisions) OR ";
                    $params[] = $division;
                }
                $sql = substr($sql, 0, -3); // Remove the last OR
                $sql .= ")";
            }
            if (!empty($filters['verticals']) && is_array($filters['verticals'])) {
                $sql .= "AND (";
                foreach ($filters['verticals'] as $division) {
                    $sql .= "FIND_IN_SET(?,verticals) OR ";
                    $params[] = $division;
                }
                $sql = substr($sql, 0, -3); // Remove the last OR
                $sql .= ")";
            }
            $sql .= "GROUP BY p.prospectId ";
            $sql .= "ORDER BY p.prospectId DESC";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to search prospects: ' . $e->getMessage());
        }

        return $results;
    }

    public function getProspect($prospectId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM prospects WHERE prospectId = ?");
            $query->execute(array($prospectId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get prospect info: ' . $e->getMessage());
        }

        return $results;
    }

    public function addProspectNote($fields)
    {

        $noteId = null;

        try {
            $noteId = $this->insertRow('prospects_notes', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add prospect note: ' . $pdoException->getMessage());
            return false;
        }

        return $noteId;
    }

    public function getProspectNotes($prospectId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT n.*,u.fullName FROM prospects_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE prospectId = ? ORDER BY timestamp DESC");
            $query->execute(array($prospectId));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get prospect notes: ' . $e->getMessage());
        }

        return $results;
    }

    public function addCredential($fields)
    {

        $credentialId = null;

        try {
            $credentialId = $this->insertRow('credentials', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add credential: ' . $pdoException->getMessage());
            return null;
        }

        return $credentialId;
    }

    public function updateCredential($credentialId, $fields)
    {

        try {
            $status = $this->update('credentials', $fields, array(
                'credentialId' => $credentialId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update credential: ' . $pdoException->getMessage());
            return null;
        }
    }

    public function getCredential($credentialId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM credentials WHERE credentialId = ?");
            $query->execute(array($credentialId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get credential info: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCredentials($status = null)
    {
        $results = array();

        try {
            if (!empty($status)) {
                $query = $this->db->prepare("SELECT c.*,x.name FROM credentials c LEFT JOIN companies x ON x.idCompany = c.companyId WHERE c.status = ? ORDER BY x.name,c.url");
                $query->execute(array($status));
            } else {
                $query = $this->db->prepare("SELECT c.*,x.name FROM credentials c LEFT JOIN companies x ON x.idCompany = c.companyId ORDER BY x.name,c.url");
                $query->execute();
            }
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company list: ' . $e->getMessage());
        }

        return $results;
    }

    public function addCompany($fields)
    {

        $companyId = null;

        try {
            $companyId = $this->insertRow('companies', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add company: ' . $pdoException->getMessage());
            return null;
        }

        return $companyId;
    }

    public function updateCompany($idCompany, $fields)
    {

        try {
            $status = $this->update('companies', $fields, array(
                'idCompany' => $idCompany,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update company: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getCompany($idCompany)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM companies WHERE idCompany = ?");
            $query->execute(array($idCompany));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company info: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCompanies($status = null)
    {
        $results = array();

        try {
            if (!empty($status)) {
                $query = $this->db->prepare("SELECT * FROM companies WHERE status = ? ORDER BY name");
                $query->execute(array($status));
            } else {
                $query = $this->db->prepare("SELECT * FROM companies ORDER BY name");
                $query->execute();
            }
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCompaniesChoices($status = null)
    {
        $results = array();

        try {
            if (!empty($status)) {
                $query = $this->db->prepare("SELECT idCompany,name FROM companies WHERE status = ? ORDER BY name");
                $query->execute(array($status));
            } else {
                $query = $this->db->prepare("SELECT idCompany,name FROM companies ORDER BY name");
                $query->execute();
            }
            $results = $query->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $this->logError('Unable to get company choices list: ' . $e->getMessage());
        }

        return $results;
    }

    public function searchCompanies($filters = array())
    {
        $results = array();

        try {
            $params = array();
            $sql = "SELECT c.* FROM companies c ";
            $sql .= "LEFT JOIN companies_divisions cd ON cd.companyId = c.idCompany ";
            $sql .= "LEFT JOIN companies_verticals cv ON cv.companyId = c.idCompany ";
            $sql .= "LEFT JOIN companies_notes cn ON cn.companyId = c.idCompany ";
            $sql .= "WHERE 1 = 1 ";
            if (!empty($filters['textSearch'])) {
                $sql .= "AND ( c.name LIKE ? OR c.note LIKE ? OR c.url LIKE ? OR c.main_name LIKE ? OR c.returns_name LIKE ? OR c.tech_name LIKE ? OR cn.note LIKE ? ) ";
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
                $params[] = '%' . $filters['textSearch'] . '%';
            }
            if (!empty($filters['status'])) {
                $sql .= "AND c.status = ? ";
                $params[] = $filters['status'];
            }
            if (!empty($filters['salesperson'])) {
                $sql .= "AND c.salesperson = ? ";
                $params[] = $filters['salesperson'];
            }
            if (!empty($filters['accountManager'])) {
                $sql .= "AND c.accountManager = ? ";
                $params[] = $filters['accountManager'];
            }
            if (!empty($filters['accountOpener'])) {
                $sql .= "AND c.accountOpener = ? ";
                $params[] = $filters['accountOpener'];
            }
            if (!empty($filters['companyType']) && 'isPublisher' == $filters['companyType']) {
                $sql .= "AND c.isPublisher = 1 ";
            }
            if (!empty($filters['companyType']) && 'isAdvertiser' == $filters['companyType']) {
                $sql .= "AND c.isAdvertiser = 1 ";
            }
            if (!empty($filters['divisions']) && is_array($filters['divisions'])) {
                $sql .= "AND cd.divisionId IN (";
                foreach ($filters['divisions'] as $division) {
                    $sql .= "?,";
                    $params[] = $division;
                }
                $sql = substr($sql, 0, -1); // Remove the last comma
                $sql .= ")";
            }
            if (!empty($filters['verticals']) && is_array($filters['verticals'])) {
                $sql .= "AND cv.verticalId IN (";
                foreach ($filters['verticals'] as $vertical) {
                    $sql .= "?,";
                    $params[] = $vertical;
                }
                $sql = substr($sql, 0, -1); // Remove the last comma
                $sql .= ")";
            }
            $sql .= "GROUP BY c.idCompany ";
            $sql .= "ORDER BY c.name";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to search companies: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCompanyDivisions($companyId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT divisionId FROM companies_divisions WHERE companyId = ?");
            $query->execute(array($companyId));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company division list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getDivisionCompanies($divisionId, $companyId, $format = PDO::FETCH_KEY_PAIR)
    {
        $results = array();

        try {
            if (!empty($companyId)) {
                $query = $this->db->prepare("SELECT c.idCompany,c.name FROM companies_divisions cd LEFT JOIN companies c ON c.idCompany = cd.companyId WHERE ( cd.divisionId = ? AND c.status = 'active' ) OR c.idCompany = ? ORDER BY c.name");
                $query->execute(array($divisionId, $companyId));
            } else {
                $query = $this->db->prepare("SELECT c.idCompany,c.name FROM companies_divisions cd LEFT JOIN companies c ON c.idCompany = cd.companyId WHERE cd.divisionId = ? AND c.status = 'active' ORDER BY c.name");
                $query->execute(array($divisionId));
            }
            $results = $query->fetchAll($format);
        } catch (PDOException $e) {
            $this->logError('Unable to get division company list: ' . $e->getMessage());
        }

        return $results;
    }

    public function addCompanyNote($fields)
    {

        $noteId = null;

        try {
            $noteId = $this->insertRow('companies_notes', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add company note: ' . $pdoException->getMessage());
            return false;
        }

        return $noteId;
    }

    public function getCompanyNotes($companyId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT n.*,u.fullName FROM companies_notes n LEFT JOIN users u ON n.userId = u.idUser WHERE companyId = ? ORDER BY timestamp DESC");
            $query->execute(array($companyId));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company notes: ' . $e->getMessage());
        }

        return $results;
    }

    public function addVertical($fields)
    {

        $verticalId = null;

        try {
            $verticalId = $this->insertRow('verticals', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add vertical: ' . $pdoException->getMessage());
            return null;
        }

        return $verticalId;
    }

    public function checkVerticalName($name, $divisionId, $verticalId = null)
    {
        $result = false;

        try {
            if (!empty($verticalId)) {
                $query = $this->db->prepare("SELECT 1 FROM verticals WHERE name = ? AND divisionId = ? AND verticalId != ?");
                $query->execute(array($name, $divisionId, $verticalId));
            } else {
                $query = $this->db->prepare("SELECT 1 FROM verticals WHERE name = ? AND divisionId = ?");
                $query->execute(array($name, $divisionId));
            }
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to check vertical name: ' . $e->getMessage());
        }

        return $result;
    }

    public function getVertical($verticalId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM verticals WHERE verticalId = ?");
            $query->execute(array($verticalId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get Vertical info: ' . $e->getMessage());
        }

        return $results;
    }

    public function updateVertical($verticalId, $fields)
    {

        try {
            $status = $this->update('verticals', $fields, array(
                'verticalId' => $verticalId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update vertical: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getDivisionVerticals($divisionId, $format = PDO::FETCH_KEY_PAIR)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT verticalId,name FROM verticals WHERE divisionId = ? ORDER BY name");
            $query->execute(array($divisionId));
            $results = $query->fetchAll($format);
        } catch (PDOException $e) {
            $this->logError('Unable to get division vertical list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCompanyVerticals($companyId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT verticalId FROM companies_verticals WHERE companyId = ?");
            $query->execute(array($companyId));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get company vertical list: ' . $e->getMessage());
        }

        return $results;
    }

    public function addCompanyVertical($companyId, $verticalId)
    {
        try {
            $query = $this->db->prepare("REPLACE INTO companies_verticals(companyId, verticalId) VALUES(?, ?)");
            $query->execute(array($companyId, $verticalId));
        } catch (PDOException $e) {
            $this->logError('Unable to add company vertical mapping: ' . $e->getMessage());
            return;
        }
    }

    public function clearCompanyVerticals($companyId)
    {
        try {
            $query = $this->db->prepare("DELETE FROM companies_verticals WHERE companyId = ?");
            $query->execute(array($companyId));
        } catch (PDOException $e) {
            $this->logError('Unable to clear company verticals: ' . $e->getMessage());
            return;
        }
    }

    public function addCompanyDivision($companyId, $divisionId)
    {
        try {
            $query = $this->db->prepare("REPLACE INTO companies_divisions(companyId, divisionId) VALUES(?, ?)");
            $query->execute(array($companyId, $divisionId));
        } catch (PDOException $e) {
            $this->logError('Unable to add company division mapping: ' . $e->getMessage());
            return;
        }
    }

    public function clearCompanyDivisions($companyId)
    {
        try {
            $query = $this->db->prepare("DELETE FROM companies_divisions WHERE companyId = ?");
            $query->execute(array($companyId));
        } catch (PDOException $e) {
            $this->logError('Unable to clear company divisions: ' . $e->getMessage());
            return;
        }
    }

    public function getDivisionName($divisionId)
    {
        $results = '';

        try {
            $query = $this->db->prepare("SELECT name FROM divisions WHERE divisionId = ?");
            $query->execute(array($divisionId));
            $results = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get division: ' . $e->getMessage());
        }

        return $results;
    }

    public function getDivisions($format = PDO::FETCH_KEY_PAIR)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT divisionId,name FROM divisions ORDER BY name");
            $query->execute();
            $results = $query->fetchAll($format);
        } catch (PDOException $e) {
            $this->logError('Unable to get division list: ' . $e->getMessage());
        }

        return $results;
    }

    public function checkFieldName($fieldName, $fieldType = null, $fieldId = null)
    {
        $result = false;
        $params = [];

        try {
            $sql = "SELECT 1 ";
            $sql .= "FROM fields ";
            $sql .= "WHERE fieldName = ? ";
            $params[] = $fieldName;

            if (!empty($fieldId)) {
                $sql .= "AND fieldId != ? ";
                $params[] = $fieldId;
            }

            if (!empty($fieldType)) {
                $sql .= "AND fieldType = ? ";
                $params[] = $fieldType;
            }

            $query = $this->db->prepare($sql);
            $query->execute($params);
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to check field name: ' . $e->getMessage());
        }

        return $result;
    }

    public function getField($fieldId)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM fields WHERE fieldId = ?");
            $query->execute(array($fieldId));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get field info: ' . $e->getMessage());
        }

        return $results;
    }

    public function addField($fields)
    {

        $verticalId = null;

        try {
            $verticalId = $this->insertRow('fields', $fields);
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add field: ' . $pdoException->getMessage());
            return null;
        }

        return $verticalId;
    }

    public function updateField($fieldId, $fields)
    {

        try {
            $status = $this->update('fields', $fields, array(
                'fieldId' => $fieldId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update field: ' . $pdoException->getMessage());
            return null;
        }
    }

    public function getFields($fieldType = null)
    {
        $results = array();

        try {
            if (!empty($fieldType)) {
                $query = $this->db->prepare("SELECT * FROM fields WHERE fieldType = ? ORDER BY REPLACE(fieldName,'c_','')");
                $query->execute(array($fieldType));
            } else {
                $query = $this->db->prepare("SELECT * FROM fields ORDER BY REPLACE(fieldName,'c_','')");
                $query->execute();
            }
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get field list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundFields()
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM fields WHERE fieldType IN('system','custom') ORDER BY REPLACE(fieldName,'c_','')");
            $query->execute();
            $results = $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound field list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getCountries($format = PDO::FETCH_KEY_PAIR)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT id,short_name FROM countries ORDER BY short_name");
            $query->execute();
            $results = $query->fetchAll($format);
        } catch (PDOException $e) {
            $this->logError('Unable to get country list: ' . $e->getMessage());
        }

        return $results;
    }

    public function addInboundFeed($fields)
    {

        if (empty($fields['label'])) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            $idFeedIn = $this->insertRow('feedinc', $fields);
        } catch (Leads_PDOException $e) {
            $this->db->rollBack();
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add inbound record: ' . $pdoException->getMessage());
        }

        $this->db->commit();

        return $idFeedIn;
    }

    public function updateInboundFeed($idFeedIn, $fields)
    {
        try {
            $status = $this->update('feedinc', $fields, array(
                'idFeedIn' => $idFeedIn,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update inbound feed: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getInboundFeed($idFeedIn)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT f.*,c.name AS companyName,DATE_FORMAT(notifyThresholdTime,'%l:%i%p') AS notifyThresholdTimeFormatted FROM feedinc f LEFT JOIN companies c ON c.idCompany = f.idCompany WHERE f.idFeedIn = ?");
            $query->execute(array($idFeedIn));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound feed info: ' . $e->getMessage());
            return null;
        }

        return $results;
    }

    public function getInboundFeedLabel($label)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT f.*,c.name AS companyName FROM feedinc f LEFT JOIN companies c ON c.idCompany = f.idCompany WHERE f.label = ?");
            $query->execute(array($label));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound feed info: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundFeeds($idCompany = null, $status = null, $feedCategory = null, $idFeedIn = null)
    {
        $results = array();
        $params = array();

        $sql = "SELECT f.*,c.name,MAX(n.timestamp) AS lastDate ";
        $sql .= "FROM feedinc f ";
        $sql .= "LEFT JOIN companies c ON f.idCompany = c.idCompany ";
        $sql .= "LEFT JOIN companies_notes n ON n.companyId = c.idCompany ";
        $sql .= "WHERE 1=1 ";
        if (!empty($idCompany)) {
            $sql .= "AND c.idCompany = ? ";
            $params[] = $idCompany;
        }
        if (!empty($status)) {
            if (!empty($idFeedIn)) {
                $sql .= "AND ( f.status = ? OR f.idFeedIn = ? ) ";
                $params[] = $status;
                $params[] = $idFeedIn;
            } else {
                $sql .= "AND f.status = ? ";
                $params[] = $status;
            }
        }
        if (!empty($feedCategory)) {
            $sql .= "AND f.feedCategory = ? ";
            $params[] = $feedCategory;
        }
        $sql .= "GROUP BY f.idFeedIn ";
        $sql .= "ORDER BY c.name,f.idFeedIn";

        try {

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound feed list: ' . $e->getMessage());
        }

        return $results;
    }

    public function checkInboundFeedAccess($idCompany, $idFeedIn)
    {
        $result = false;

        try {
            $query = $this->db->prepare("SELECT 1 FROM feedinc f LEFT JOIN companies c ON f.idCompany = c.idCompany WHERE c.idCompany = ? AND f.idFeedIn = ?");
            $query->execute(array($idCompany, $idFeedIn));
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound feed access: ' . $e->getMessage());
        }

        return $result;
    }

    public function checkInboundFeedLabelExists($label)
    {
        $result = false;

        try {
            $query = $this->db->prepare("SELECT 1 FROM feedinc WHERE label = ?");
            $query->execute(array($label));
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to check inbound feed label: ' . $e->getMessage());
        }

        return $result;
    }

    public function getInboundPopulationSettings($idFeedIn, $enabled = true, $descending = true)
    {
        $results = array();

        try {
            $sql = "SELECT fp.*, fo.label, fo.dailyLimit, fo.delay, fo.description, fo.delayDump, fo.processingSchedule, fo.revenuePerLead, fo.costPerLeadOverride, fo.cron, fo.xmlDTD, c.name ";
            $sql .= "FROM feedPopulation fp ";
            $sql .= "LEFT JOIN feedout fo ON fp.idFeedOut = fo.idFeedOut ";
            $sql .= "LEFT JOIN companies c ON c.idCompany = fo.idCompany ";
            $sql .= "WHERE fp.idFeedIn = ? ";
            if ($enabled) {
                $sql .= " AND fp.enabled = '1' ";
            }
            $sql .= "ORDER BY fp.waterfallPriority " . ($descending ? "DESC" : "ASC") . ",FIELD(fp.queueType,'livedata','waterfallLimitLive','waterfall','waterfallLimit','queue')";
//			$sql .= "ORDER BY fp.waterfallPriority DESC";
            $query = $this->db->prepare($sql);
            $query->execute(array($idFeedIn));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound population settings: ' . $e->getMessage());
            return false;
        }

        return $results;
    }

    public function addOutboundFeed($fields)
    {

        if (empty($fields['label'])) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            $idFeedOut = $this->insertRow('feedout', $fields);
        } catch (Leads_PDOException $e) {
            $this->db->rollBack();
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add outbound feed: ' . $pdoException->getMessage());
        }

        $this->db->commit();

        return $idFeedOut;
    }

    public function updateOutboundFeed($idFeedOut, $fields)
    {
        try {
            $status = $this->update('feedout', $fields, array(
                'idFeedOut' => $idFeedOut,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update outbound feed: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getPopulationStatus($idFeedOut)
    {
        $results = array();
        $status = 'Error';

        try {
            $query = $this->db->prepare("SELECT enabled FROM feedPopulation WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
            $results = $query->fetchAll(PDO::FETCH_OBJ);

            if (empty($results)) {
                return 'No populations setup';
            } else {
                $enabled = 0;
                foreach ($results as $result) {
                    if ($result->enabled) {
                        $enabled++;
                    }
                }
                if ($enabled === 0) {
                    return 'Disabled';
                } elseif ($enabled < sizeOf($results)) {
                    return 'Partially enabled';
                } else {
                    return 'Enabled';
                }
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get population status: ' . $e->getMessage());
            return 'Error';
        }

        return $status;
    }

    public function getPopulationSetting($idAssoc)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM feedPopulation WHERE idAssoc = ?");
            $query->execute(array($idAssoc));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get feed population: ' . $e->getMessage());
            return false;
        }

        return $results;
    }

    public function getPopulations($idFeedOut)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT * FROM feedPopulation WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get feed populations: ' . $e->getMessage());
            return false;
        }

        return $results;
    }

    public function addPopulation($fields)
    {

        if (empty($fields['idFeedIn']) || empty($fields['idFeedOut'])) {
            return null;
        }

        try {
            $idAssoc = $this->insertRow('feedPopulation', $fields);
        } catch (Leads_PDOException $e) {
            $this->db->rollBack();
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add population: ' . $pdoException->getMessage());
        }

        return $idAssoc;
    }

    public function updatePopulation($idAssoc, $fields)
    {
        try {
            $status = $this->update('feedPopulation', $fields, array(
                'idAssoc' => $idAssoc,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update population: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function retireOutboundFeed($idFeedOut)
    {
        if (empty($idFeedOut)) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare("UPDATE feedout SET cron = '0', status = 'retired' WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update feedout retired status: ' . $e->getMessage());
            return false;
        }

        try {
            $query = $this->db->prepare("UPDATE feedPopulation SET enabled = '0' WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update feedPopulation retired status: ' . $e->getMessage());
            return false;
        }

        $this->db->commit();

        return true;
    }

    public function getOutboundFeed($idFeedOut)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT *,DATE_FORMAT(notifyThresholdTime,'%l:%i%p') AS notifyThresholdTimeFormatted FROM feedout WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feed info: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundFeedPopulation($idAssoc)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT p.*,o.* FROM feedPopulation p LEFT JOIN feedout o ON o.idFeedOut = p.idFeedOut WHERE idAssoc = ?");
            $query->execute(array($idAssoc));
            $results = $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feed population info: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundFeeds($idCompany = null, $status = null, $feedCategory = null)
    {
        $results = array();
        $params = array();

        $sql = "SELECT o.*,co.name,MAX(n.timestamp) AS lastDate ";
        $sql .= "FROM feedout o ";
        $sql .= "LEFT JOIN feedPopulation p ON p.idFeedOut = o.idFeedOut ";
        $sql .= "LEFT JOIN feedinc i ON i.idFeedIn = p.idFeedIn ";
        $sql .= "LEFT JOIN companies ci ON ci.idCompany = i.idCompany ";
        $sql .= "LEFT JOIN companies co ON co.idCompany = o.idCompany ";
        $sql .= "LEFT JOIN companies_notes n ON n.companyId = co.idCompany ";
        $sql .= "WHERE 1=1 ";
        if (!empty($idCompany)) {
            $sql .= "AND ci.idCompany = ? ";
            $params[] = $idCompany;
        }
        if (!empty($status)) {
            $sql .= "AND o.status = ? ";
            $params[] = $status;
        }
        if (!empty($feedCategory)) {
            $sql .= "AND o.feedCategory = ? ";
            $params[] = $feedCategory;
        }
        $sql .= "GROUP BY o.idFeedOut ";
        $sql .= "ORDER BY co.name,o.idFeedOut";

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feed list: ' . $e->getMessage());
        }

        return $results;
    }

    public function convertFields()
    {
        $results = array();

        $sql = "SELECT idFeedOut,staticFields,varFields,fieldMap FROM feedout";

        try {
            $query = $this->db->prepare($sql);
            $query->execute();
            while ($row = $query->fetch(\PDO::FETCH_OBJ)) {

                $staticFieldsJSON = new stdClass();
                $varFieldsJSON = new stdClass();

                if (!empty($row->staticFields)) {
                    $staticFields = explode(";", $row->staticFields);
                    foreach ($staticFields as $sF) {
                        if (!empty($sF)) {
                            $fieldValuePair = explode("=", $sF);
                            if (isset($fieldValuePair[0], $fieldValuePair[1])) {
                                $staticFieldsJSON->{$fieldValuePair[0]} = $fieldValuePair[1];
                            } else {
                                print "EMPTY FIELD {$row->idFeedOut}\n";
                                var_dump([
                                    $fieldValuePair[0] ?? null,
                                    $fieldValuePair[1] ?? null,
                                ]);
                            }
                        }
                    }
                }

                if (!empty($row->varFields) && !empty($row->fieldMap)) {
                    $varFields = explode(";", $row->varFields);
                    $fieldMap = explode(";", $row->fieldMap);
                    for ($count = 0; $count < count($varFields); $count++) {
                        if (isset($varFields[$count], $fieldMap[$count])) {
                            $varFieldsJSON->{$varFields[$count]} = $fieldMap[$count];
                        } else {
                            print "EMPTY FIELD {$row->idFeedOut}\n";
                            var_dump([
                                $varFields[$count] ?? null,
                                $fieldMap[$count] ?? null,
                            ]);
                        }
                    }
                }

                $fields = [
                    'staticFieldsJSON' => json_encode($staticFieldsJSON),
                    'varFieldsJSON' => json_encode($varFieldsJSON),
                ];

                //var_dump($fields);
                $this->updateOutboundFeed($row->idFeedOut, $fields);
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feed list: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundFeedsCron($mod = null)
    {

        $results = null;

        try {
            if (!empty($mod)) {
                $query = $this->db->prepare("SELECT idFeedOut,queued,delay FROM feedout WHERE cron = '1' AND queued > 0 AND status IN( 'active', 'hidden' ) AND MOD(idFeedOut,2) = ?");
                $query->execute(array('even' === $mod ? 0 : 1));
            } else {
                $query = $this->db->prepare("SELECT idFeedOut,queued,delay FROM feedout WHERE cron = '1' AND queued > 0 AND status IN( 'active', 'hidden' )");
                $query->execute();
            }
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feeds cron: ' . $e->getMessage());
        }

        return $results;

    }

    public function getOutboundFeedsDelayDump()
    {

        $results = null;

        try {
            $query = $this->db->prepare("SELECT idFeedOut,queued,delay FROM feedout WHERE delayDump = 1 AND cron = '1' AND status IN( 'active', 'hidden' )");
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feeds delay dump: ' . $e->getMessage());
        }

        return $results;

    }

    public function checkOutboundFeedAccess($idCompany, $idFeedOut)
    {
        $result = false;

        try {
            $query = $this->db->prepare("SELECT 1 FROM feedout o LEFT JOIN feedPopulation p ON p.idFeedOut = o.idFeedOut LEFT JOIN feedinc i ON i.idFeedIn = p.idFeedIn LEFT JOIN companies ci ON ci.idCompany = i.idCompany LEFT JOIN companies co ON co.idCompany = o.idCompany WHERE ci.idCompany = ? AND o.idFeedOut = ?");
            $query->execute(array($idCompany, $idFeedOut));
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound feed access: ' . $e->getMessage());
        }

        return $result;
    }

    public function checkOutboundFeedLabelExists($label)
    {
        $result = false;

        try {
            $query = $this->db->prepare("SELECT 1 FROM feedout WHERE label = ?");
            $query->execute(array($label));
            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to check outbound feed label: ' . $e->getMessage());
        }

        return $result;
    }


    public function getOutboundStats($idFeedOut)
    {
        $results = array('accepted' => 0, 'rejected' => 0);

        try {
            $query = $this->db->prepare("SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_outbound WHERE stamp = ? AND idFeedOut = ?");
            $query->execute(array(date('Y-m-d'), $idFeedOut));
            $results = $query->fetch();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundStatsRange($idFeedOut, $stampStart, $stampEnd)
    {
        $results = array('accepted' => 0, 'rejected' => 0);

        try {
            $query = $this->db->prepare("SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected,IFNULL(SUM(billable),0) billable FROM stats_outbound WHERE stamp >= ? AND stamp <= ? AND idFeedOut = ?");
            $query->execute(array($stampStart, $stampEnd, $idFeedOut));
            $results = $query->fetch();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function inboundAdd($idFeedIn, $fields, $statsDay, $error = null, $jobId = null)
    {
        $this->db->query("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
        $this->db->beginTransaction();

        try {
            $customFields = [];
            foreach ($fields as $key => $val) {
                if (strpos($key, 'c_') === 0) {
                    $customFields[$key] = $val;
                }
            }

            $idRecord = $this->insertRow('data_inbound', array(
                'idFeedIn' => $idFeedIn,
                'listcode' => empty($fields['listcode']) ? null : substr($fields['listcode'], 0, 20),
                'leadId' => empty($fields['leadId']) ? null : substr($fields['leadId'], 0, 255),
                'leadstamp' => empty($fields['stamp']) ? null : $fields['stamp'], // Already converted to UTC and formatted by ProcessLeads::validateField
                'url' => empty($fields['url']) ? null : substr($this->parseUrl($fields['url']), 0, 255),
                'ip' => empty($fields['ip']) ? null : substr($fields['ip'], 0, 45),
                'email' => empty($fields['email']) ? null : substr($fields['email'], 0, 255),
                'fname' => empty($fields['fname']) ? null : substr($fields['fname'], 0, 50),
                'lname' => empty($fields['lname']) ? null : substr($fields['lname'], 0, 50),
                'addr' => empty($fields['addr']) ? null : substr($fields['addr'], 0, 150),
                'addr2' => empty($fields['addr2']) ? null : substr($fields['addr2'], 0, 150),
                'city' => empty($fields['city']) ? null : substr($fields['city'], 0, 75),
                'state' => empty($fields['state']) ? null : substr($fields['state'], 0, 25),
                'zip' => empty($fields['zip']) ? null : substr($fields['zip'], 0, 20),
                'dob' => (empty($fields['dob']) || '0000-00-00' == $fields['dob']) ? null : gmdate('Y-m-d', strtotime($fields['dob'])),
                'gender' => empty($fields['gender']) ? null : substr($fields['gender'], 0, 10),
                'landline' => empty($fields['landline']) ? null : substr($fields['landline'], 0, 20),
                'cellphone' => empty($fields['cellphone']) ? null : substr($fields['cellphone'], 0, 20),
                'country' => empty($fields['country']) ? null : substr($fields['country'], 0, 75),
                'result' => empty($error) ? null : $error,
                'jobId' => empty($jobId) ? null : $jobId,
                'custom1' => empty($fields['custom1']) ? null : substr($fields['custom1'], 0, 255),
                'custom2' => empty($fields['custom2']) ? null : substr($fields['custom2'], 0, 255),
                'custom3' => empty($fields['custom3']) ? null : substr($fields['custom3'], 0, 255),
                'custom4' => empty($fields['custom4']) ? null : substr($fields['custom4'], 0, 255),
                'custom5' => empty($fields['custom5']) ? null : substr($fields['custom5'], 0, 255),
                'custom6' => empty($fields['custom6']) ? null : substr($fields['custom6'], 0, 255),
                'timestamp' => empty($fields['timestampOverride']) ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', strtotime($fields['timestampOverride'])),
                'customFields' => empty($customFields) ? null : json_encode($customFields),
            ));
        } catch (Leads_PDOException $e) {
            $this->db->rollBack();
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add inbound record: ' . $pdoException->getMessage());
            return null;
        }

        try {
            if (empty($error)) {
                $query = $this->db->prepare('INSERT INTO stats_inbound(idFeedIn,url,stamp,accepted) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1');
            } else {
                $query = $this->db->prepare('INSERT INTO stats_inbound(idFeedIn,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1');
            }
            $query->execute(array($idFeedIn, $this->parseUrl($fields['url'] ?? null), $statsDay));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to insert stats_inbound record: ' . $e->getMessage());
            return null;
        }

        $this->db->commit();
        return $idRecord;
    }

    public function inboundProcess($idRecord, $idFeedIn, $url, $statsDay, $error = null)
    {
        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare('UPDATE data_inbound SET result = ? WHERE idRecord = ?');
            $query->execute(array($error, $idRecord));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update data_inbound record: ' . $e->getMessage());
            return;
        }

        if (!empty($error)) {
            try {
                $query = $this->db->prepare('UPDATE stats_inbound SET accepted = accepted - 1, rejected = rejected + 1 WHERE idFeedIn = ? AND url = ? AND stamp = ?');
                $query->execute(array($idFeedIn, $this->parseUrl($url), $statsDay));
            } catch (PDOException $e) {
                $this->db->rollBack();
                $this->logError('Unable to update stats_inbound record: ' . $e->getMessage());
                return;
            }
        }

        $this->db->commit();
    }

    public function inboundCheckDuplicates($idFeedIn, $column, $requestValues, $dedupeAcross)
    {

        $days = 120;

        try {
            switch ($dedupeAcross) {
                case 'all':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier($column) . " = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        $idFeedIn,
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                    ));
                    break;
                case 'allGlobal':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier($column) . " = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                    ));
                    break;
                case 'url':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier($column) . " = ? AND url = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        $idFeedIn,
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                        !empty($requestValues['url']) ? $this->parseUrl($requestValues['url']) : '',
                    ));
                    break;
                case 'urlGlobal':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier($column) . " = ? AND url = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                        !empty($requestValues['url']) ? $this->parseUrl($requestValues['url']) : '',
                    ));
                    break;
                case 'listcode':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND idFeedIn = ? AND " . $this->quoteIdentifier($column) . " = ? AND listcode = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        $idFeedIn,
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                        !empty($requestValues['listcode']) ? $requestValues['listcode'] : '',
                    ));
                case 'listcodeGlobal':
                    $query = $this->db->prepare("SELECT 1 FROM data_inbound WHERE result IS NULL AND " . $this->quoteIdentifier($column) . " = ? AND listcode = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days} DAY) LIMIT 1");
                    $query->execute(array(
                        !empty($requestValues[$column]) ? $requestValues[$column] : '',
                        !empty($requestValues['listcode']) ? $requestValues['listcode'] : '',
                    ));
                    break;
            }

            if (!empty($query) && $query->fetchColumn()) {
                return true;
            }

        } catch (PDOException $e) {
            $this->logError('Unable to check for inbound duplicates: ' . $e->getMessage());
            return null;
        }

        return false;
    }

    public function outboundAdd($idRecord, $idRecordLegacy, $idFeedIn, $idFeedOut, $url, $processed = 0, $urlRewritten = false)
    {
        $this->db->beginTransaction();

        try {
            $status = $this->insertRow('data_outbound', array(
                'idRecord' => $idRecord,
                'idRecordLegacy' => $idRecordLegacy,
                'idFeedIn' => $idFeedIn,
                'idFeedOut' => $idFeedOut,
                'processed' => $processed,
                'url' => !empty($url) ? $this->parseUrl($url) : null,
            ));
        } catch (Leads_PDOException $e) {
            $this->db->rollBack();
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add outbound record: ' . $pdoException->getMessage());
            return null;
        }

        try {
            $query = $this->db->prepare("REPLACE INTO url_mapping(timestamp,idFeedIn,idFeedOut,url) VALUES(NOW(), ?, ?, ?)");
            $query->execute(array($idFeedIn, $idFeedOut, $this->parseUrl($url)));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to add URL mapping: ' . $e->getMessage());
            return null;
        }

        if ($processed !== 1) {
            try {
                // TODO: Use MySQL triggers instead of this method.
                $query = $this->db->prepare("UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?");
                $query->execute(array($idFeedOut));
            } catch (PDOException $e) {
                $this->db->rollBack();
                $this->logError('Unable to add to queue count: ' . $e->getMessage());
                return null;
            }
        }

        $this->db->commit();

        return $status;
    }


    public function incrementOutboundQueue($idFeedOut)
    {
        try {
            $query = $this->db->prepare("UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to add to queue count: ' . $e->getMessage());
            return null;
        }
    }

    public function outboundProcess($row, $feedOut, $result, $accepted)
    {

        if (empty($row) || empty($feedOut)) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare('UPDATE data_outbound SET timestamp = NOW(), processed = 1, accepted = ?, isBillable = ?, result = ? WHERE idRecord = ? AND idFeedOut = ?');
            $query->execute(array(!empty($accepted) ? 1 : 0, !empty($accepted) ? 1 : 0, $result, $row->idRecord, $feedOut->idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update data_outbound record: ' . $e->getMessage());
            return null;
        }

        try {
            if (!empty($accepted)) {
                $query = $this->db->prepare('INSERT INTO stats_outbound(idFeedOut,url,stamp,accepted,billable) VALUES(?,?,?,1,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1, billable = billable + 1');
            } else {
                $query = $this->db->prepare('INSERT INTO stats_outbound(idFeedOut,url,stamp,rejected) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1');
            }
            $query->execute(array($feedOut->idFeedOut, $this->parseUrl($row->url), date('Y-m-d')));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to insert stats_outbound record: ' . $e->getMessage());
            return null;
        }

        if (!empty($row->idFeedIn)) {

            $feedIn = $this->getInboundFeed($row->idFeedIn);
            if (!empty($feedIn)) {

                $costPerLead = !empty($feedOut->costPerLeadOverride) ? $feedOut->costPerLeadOverride : (!empty($feedIn->costPerLead) ? $feedIn->costPerLead : 0.00);
                $revenuePerLead = !empty($feedOut->revenuePerLead) ? $feedOut->revenuePerLead : 0.00;

                try {
                    if (!empty($accepted)) {
                        $query = $this->db->prepare('INSERT INTO stats_correlated(idFeedIn,idFeedOut,url,stamp,costPerLead,revenuePerLead,accepted,billable) VALUES(?,?,?,?,?,?,1,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1, billable = billable + 1, costPerLead = ?, revenuePerLead = ?');
                    } else {
                        $query = $this->db->prepare('INSERT INTO stats_correlated(idFeedIn,idFeedOut,url,stamp,costPerLead,revenuePerLead,rejected) VALUES(?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE rejected = rejected + 1, costPerLead = ?, revenuePerLead = ?');
                    }
                    $query->execute(array($row->idFeedIn, $feedOut->idFeedOut, $this->parseUrl($row->url), date('Y-m-d'), $costPerLead, $revenuePerLead, $costPerLead, $revenuePerLead));
                } catch (PDOException $e) {
                    $this->db->rollBack();
                    $this->logError('Unable to insert stats_correllated record: ' . $e->getMessage());
                    return null;
                }
            }
        }

        try {
            $query = $this->db->prepare("UPDATE feedout SET queued = queued - 1 WHERE idFeedOut = ?");
            $query->execute(array($feedOut->idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to subtract from queue count: ' . $e->getMessage());
            return null;
        }

        try {
            $table = $this->quoteIdentifier('data_outbound_' . date('Ym'));
            $this->db->query("CREATE TABLE IF NOT EXISTS archive." . $table . " LIKE data_outbound");

            $query = $this->db->prepare("INSERT IGNORE INTO archive." . $table . "(idRecord, idFeedIn, idFeedOut, `timestamp`, `result`, idRecordLegacy, processed, isBillable, url, accepted) SELECT idRecord, idFeedIn, idFeedOut, `timestamp`, `result`, idRecordLegacy, processed, isBillable, url, accepted FROM data_outbound WHERE idRecord = ? AND idFeedOut = ?");
            $query->execute(array($row->idRecord, $feedOut->idFeedOut));
            $rows = $query->rowCount();

            $query = $this->db->prepare("DELETE FROM data_outbound WHERE idRecord = ? AND idFeedOut = ?");
            $query->execute(array($row->idRecord, $feedOut->idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to archive record: ' . $e->getMessage());
            return null;
        }

        $this->db->commit();

        return true;
    }

    public function fixLiveStats($idFeedOut)
    {

        $feedOut = $this->getOutboundFeed($idFeedOut);
        if (empty($feedOut)) {
            die('Cannot find outbound feed');
        }

        $query = "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = -1 AND o.idFeedOut = ? AND i.timestamp >= '2018-07-18' AND i.timestamp < '2018-07-19 14:00'";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($feedOut->idFeedOut));

            while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
                print $row->idRecord . ':' . $row->result;

                if (empty($row->result) || preg_match('/Code: \d{3}1\]/', $row->result)) {
                    print ' ACCEPTED' . PHP_EOL;
                    $accepted = true;
                    $result = null;
                } else {
                    $accepted = false;
                    $result = $row->result;
                    print ' REJECTED' . PHP_EOL;
                }
                $this->outboundProcess($row, $feedOut, $result, $accepted);
            }

        } catch (PDOException $e) {
            $this->logError('Unable to export inbound records: ' . $e->getMessage());
            $result['reason'] = 'SQL ERROR: ' . $e->getMessage();
            return $result;
        }
    }

    public function fixWaterfallStats($idFeedOut)
    {

        $feedOut = $this->getOutboundFeed($idFeedOut);
        if (empty($feedOut)) {
            die('Cannot find outbound feed');
        }

        try {
            $findRecordQuery = $this->db->prepare("SELECT 1 FROM data_outbound WHERE idRecord = ? AND idFeedIn = ? AND idFeedOut = ?");

            $query = "SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = -1 AND o.idFeedOut = ? AND i.timestamp >= '2018-07-18' AND i.timestamp < '2018-07-19 14:00'";
            $query = $this->db->prepare($query);
            $query->execute(array($feedOut->idFeedOut));

            while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
                print "Record: [{$row->idRecord}]\n";

                $feedIn = $this->getInboundFeed($row->idFeedIn);
                if (empty($feedIn)) {
                    print 'Cannot find inbound feed' . PHP_EOL;
                    continue;
                }

                $feedPops = $this->getInboundPopulationSettings($row->idFeedIn, false, false);
                if (!empty($feedPops) && is_array($feedPops)) {
                    $foundSuccess = false;
                    $foundReject = false;
                    foreach ($feedPops as $feedPop) {

                        if ('livedata' != $feedPop->queueType && 'waterfall' != $feedPop->queueType && 'waterfallLimitLive' != $feedPop->queueType) {
                            continue;
                        }

                        print "\tidFeedOut: [{$feedPop->idFeedOut}] ";

                        if ($foundSuccess) {
                            print " REJECT PREVIOUS\n";
                            $this->outboundProcess($row, $feedPop, 'Rejected', 0);
                            continue;
                        }

                        $findRecordQuery->execute(array($row->idRecord, $row->idFeedIn, $feedPop->idFeedOut));
                        if (!$findRecordQuery || '1' !== $findRecordQuery->fetchColumn()) {
                            print " SKIP\n";
                            continue;
                        }

                        if (empty($row->result) || preg_match('/Code: \d{3}1\]/', $row->result)) {
                            print ' ACCEPTED' . PHP_EOL;
                            $foundSuccess = true;
                            $this->outboundProcess($row, $feedPop, null, 1);
                        } else {
                            if ($foundReject) {
                                print ' REJECTED GENERIC' . PHP_EOL;
                                $this->outboundProcess($row, $feedPop, 'Rejected', 0);
                            } else {
                                print " REJECTED ACTUAL: {$row->result}\n";
                                $this->outboundProcess($row, $feedPop, $row->result, 0);
                            }
                            $foundReject = true;
                        }
                    }
                }
            }

        } catch (PDOException $e) {
            $this->logError('Unable to export inbound records: ' . $e->getMessage());
            $result['reason'] = 'SQL ERROR: ' . $e->getMessage();
            return $result;
        }
    }

    public function toggleBillable($row, $billable)
    {

        if (empty($row) || empty($row->timestampConverted)) {
            return null;
        }

        $archiveDate = date('Ym', strtotime($row->timestampConverted));
        $statsDate = date('Y-m-d', strtotime($row->timestampConverted));
        if (empty($archiveDate) || empty($statsDate)) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare('UPDATE archive.' . $this->quoteIdentifier('data_outbound_' . $archiveDate) . ' SET isBillable = ? WHERE idRecord = ? AND idFeedOut = ?');
            $query->execute(array(!empty($billable) ? 1 : 0, $row->idRecord, $row->idFeedOut));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to toggle billable on data_outbound record: ' . $e->getMessage());
            return null;
        }

        try {
            if (empty($billable)) {
                $query = $this->db->prepare('UPDATE stats_outbound SET billable = billable - 1 WHERE billable >= 1 AND idFeedOut = ? AND url = ? AND stamp = ?');
            } else {
                $query = $this->db->prepare('UPDATE stats_outbound SET billable = billable + 1 WHERE idFeedOut = ? AND url = ? AND stamp = ?');
            }
            $query->execute(array($row->idFeedOut, $this->parseUrl(!empty($row->urlOutbound) ? $row->urlOutbound : $row->url), $statsDate));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to toggle billable on stats_outbound record: ' . $e->getMessage());
            return null;
        }

        try {
            if (empty($billable)) {
                $query = $this->db->prepare('UPDATE stats_correlated SET billable = billable - 1 WHERE billable >= 1 AND idFeedIn = ? AND idFeedOut = ? AND url = ? AND stamp = ?');
            } else {
                $query = $this->db->prepare('UPDATE stats_correlated SET billable = billable + 1 WHERE idFeedIn = ? AND idFeedOut = ? AND url = ? AND stamp = ?');
            }
            $query->execute(array($row->idFeedIn, $row->idFeedOut, $this->parseUrl(!empty($row->urlOutbound) ? $row->urlOutbound : $row->url), $statsDate));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to toggle billable on stats_correlated record: ' . $e->getMessage());
            return null;
        }

        $this->db->commit();

        return true;
    }

    public function getUrlMappings()
    {
        $results = array();

        $query = "( SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,IF(m.timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY),1,0) AS active ";
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
            $query = $this->db->prepare($query);
            $query->execute();
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get URL mappings: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInvoiceDetails($date, $idCompany)
    {
        try {
            $query = $this->db->prepare("SELECT * FROM invoices WHERE date = ? AND idCompany = ?");
            $query->execute(array($date, $idCompany));
            return $query->fetch(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get invoice details: ' . $e->getMessage());
            return false;
        }

        return null;
    }

    public function getInvoiceNumber($date, $idCompany)
    {
        $invoiceNumber = '';

        try {
            $query = $this->db->prepare("SELECT invoiceNumber FROM invoices WHERE date = ? AND idCompany = ?");
            $query->execute(array($date, $idCompany));
            $invoiceNumber = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get invoice number: ' . $e->getMessage());
        }

        return $invoiceNumber;
    }

    public function setInvoiceDetails($date, $idCompany, $invoiceNumber, $paymentDate, $userId)
    {

        try {
            $query = $this->db->prepare("REPLACE INTO invoices( date, idCompany, invoiceNumber, paymentDate, userId ) VALUES( ?, ?, ?, ?, ? )");
            $query->execute(array(
                $date,
                $idCompany,
                !empty($invoiceNumber) ? $invoiceNumber : null,
                !empty($paymentDate) ? $paymentDate : null,
                !empty($userId) ? $userId : null,
            ));
        } catch (PDOException $e) {
            $this->logError('Unable to update invoice value: ' . $e->getMessage());
            return;
        }
    }

    public function getInvoiceStatus($date, $idCompany)
    {
        $paid = false;

        try {
            $query = $this->db->prepare("SELECT paymentDate FROM invoices WHERE date = ? AND idCompany = ?");
            $query->execute(array($date, $idCompany));
            $paid = !empty($query->fetchColumn()) ? true : false;
        } catch (PDOException $e) {
            $this->logError('Unable to get invoice status: ' . $e->getMessage());
        }

        return $paid;
    }

    public function getRevenueInboundMappings($date, $idCompany, $idFeedIn, $url)
    {
        $results = array();
        $fields = array();
        //$fields[] = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-%';
        $fields[] = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-%';
        $fields[] = $date;

        $query = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,r.value AS revenue,MIN(so.stamp) AS firstDate,MAX(so.stamp) AS lastDate ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
        $query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ";
        //$query .= "INNER JOIN stats_inbound si ON si.url = m.url AND si.idFeedIn = m.idFeedIn AND si.stamp LIKE ? ";
        $query .= "INNER JOIN stats_outbound so ON so.url = m.url AND so.idFeedOut = m.idFeedOut AND so.stamp LIKE ? ";
        $query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn AND m.url = r.url AND m.idFeedOut = r.idFeedOut AND r.date = ? ";
        $query .= "WHERE 1=1 ";
        if (!empty($idCompany)) {
            $query .= "AND i.idCompany = ? ";
            $fields[] = $idCompany;
        }
        if (!empty($idFeedIn)) {
            $query .= "AND i.idFeedIn = ? ";
            $fields[] = $idFeedIn;
        }
        if (!empty($url)) {
            $query .= "AND m.url = ? ";
            $fields[] = $url;
        }
        $query .= "GROUP BY 2,4,6 ";
        $query .= "ORDER BY 4 ASC,10 DESC ";

        try {
            $query = $this->db->prepare($query);
            $query->execute($fields);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound revenue mappings: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundClientMappings($date, $idCompany, $idFeedIn, $url)
    {
        $results = array();
        $fields = array();
        $fields[] = $date;

        $query = "SELECT ci.name AS inName,i.idFeedIn,i.description AS inDescription,m.url,SUM(DISTINCT r.value) AS revenue,ROUND(SUM(DISTINCT r.value)*0.50,2) AS partner,IF(SUM(r.value)>0,'0','1'),MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
        $query .= "FROM url_mapping m ";
        $query .= "LEFT JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "LEFT JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "LEFT JOIN stats_outbound s ON s.url = m.url ";
        $query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
        $query .= "AND m.url = r.url ";
        $query .= "AND m.idFeedOut = r.idFeedOut ";
        $query .= "AND r.date = ? ";
        $query .= "WHERE 1=1 ";
        if (!empty($idCompany)) {
            $query .= "AND i.idCompany = ? ";
            $fields[] = $idCompany;
        }
        if (!empty($idFeedIn)) {
            $query .= "AND i.idFeedIn = ? ";
            $fields[] = $idFeedIn;
        }
        if (!empty($url)) {
            $query .= "AND m.url = ? ";
            $fields[] = $url;
        }
        $query .= "GROUP BY 4 ";
        $query .= "ORDER BY 7,1,2,4 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute($fields);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound client revenue mappings: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundClientMonthMappings($idCompany)
    {
        $results = array();
        $fields = array();

        $query = "SELECT ci.name AS inName,r.date AS month,SUM(r.value) AS revenue,ROUND(SUM(r.value)*0.50,2) AS partner,i.idCompany AS idCompany ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
        $query .= "AND m.url = r.url ";
        $query .= "AND m.idFeedOut = r.idFeedOut ";
        $query .= "WHERE r.value IS NOT NULL ";
        $query .= "AND r.value > 0.00 ";
        if (!empty($idCompany)) {
            $query .= "AND i.idCompany = ? ";
            $fields[] = $idCompany;
        }
        $query .= "GROUP BY 1,2 ";
        $query .= "ORDER BY 2 DESC,1 ASC";

        try {
            $query = $this->db->prepare($query);
            $query->execute($fields);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound client revenue month mappings: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundClientMonthTotal($idCompany, $month)
    {
        $results = array();
        $fields = array();

        $query = "SELECT SUM(r.value) AS revenue,ROUND(SUM(r.value)*0.50,2) AS partner ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "LEFT JOIN revenue r ON r.idFeedIn = m.idFeedIn ";
        $query .= "AND m.url = r.url ";
        $query .= "AND m.idFeedOut = r.idFeedOut ";
        $query .= "WHERE r.value IS NOT NULL ";
        $query .= "AND r.value > 0.00 ";
        if (!empty($idCompany)) {
            $query .= "AND i.idCompany = ? ";
            $fields[] = $idCompany;
        }
        if (!empty($idCompany)) {
            $query .= "AND r.date = ? ";
            $fields[] = $month;
        }

        try {
            $query = $this->db->prepare($query);
            $query->execute($fields);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound client revenue month total: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundCompanies()
    {
        $results = array();

        $query = "SELECT ci.name AS name,ci.idCompany AS idCompany ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "GROUP BY 2 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute();
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound revenue companies: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundFeeds($idCompany)
    {
        $results = array();

        $query = "SELECT i.idFeedIn,i.description AS inDescription ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedinc i ON m.idFeedIn = i.idFeedIn ";
        $query .= "INNER JOIN companies ci ON i.idCompany = ci.idCompany ";
        $query .= "WHERE i.idCompany = ? ";
        $query .= "GROUP BY 1 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($idCompany));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound revenue feeds: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueInboundURLs($idFeedIn)
    {
        $results = array();

        $query = "SELECT url ";
        $query .= "FROM url_mapping ";
        $query .= "WHERE idFeedIn = ? ";
        $query .= "GROUP BY 1 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($idFeedIn));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound revenue URLs: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueOutboundMappings($date, $idCompany, $idFeedOut, $url)
    {
        $results = array();
        $fields = array();
        $fields[] = $date;

        $query = "SELECT m.url,co.name AS outName,o.idFeedOut,o.description AS outDescription,r.value AS revenue,MIN(s.stamp) AS firstDate,MAX(s.stamp) AS lastDate ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
        $query .= "INNER JOIN companies co ON o.idCompany = co.idCompany ";
        $query .= "LEFT JOIN stats_outbound s ON s.url = m.url AND s.idFeedOut = m.idFeedOut ";
        $query .= "LEFT JOIN revenue r ON r.idFeedOut = m.idFeedOut ";
        $query .= "AND m.url = r.url ";
        $query .= "AND r.date = ? ";
        $query .= "AND r.idFeedIn = 0 ";
        $query .= "WHERE 1=1 ";
        if (!empty($idCompany)) {
            $query .= "AND o.idCompany = ? ";
            $fields[] = $idCompany;
        }
        if (!empty($idFeedOut)) {
            $query .= "AND o.idFeedOut = ? ";
            $fields[] = $idFeedOut;
        }
        if (!empty($url)) {
            $query .= "AND m.url = ? ";
            $fields[] = $url;
        }
        $query .= "GROUP BY 1,3 ";
        $query .= "ORDER BY 2,3,1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute($fields);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound revenue mappings: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueOutboundCompanies()
    {
        $results = array();

        $query = "SELECT co.name AS name,co.idCompany AS idCompany ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedout i ON m.idFeedOut = i.idFeedOut ";
        $query .= "INNER JOIN companies co ON i.idCompany = co.idCompany ";
        $query .= "GROUP BY 2 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute();
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound revenue companies: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueOutboundFeeds($idCompany)
    {
        $results = array();

        $query = "SELECT o.idFeedOut,o.description AS outDescription ";
        $query .= "FROM url_mapping m ";
        $query .= "INNER JOIN feedout o ON m.idFeedOut = o.idFeedOut ";
        $query .= "INNER JOIN companies ci ON o.idCompany = ci.idCompany ";
        $query .= "WHERE o.idCompany = ? ";
        $query .= "GROUP BY 1 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($idCompany));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound revenue feeds: ' . $e->getMessage());
        }

        return $results;
    }

    public function getRevenueOutboundURLs($idFeedOut)
    {
        $results = array();

        $query = "SELECT url ";
        $query .= "FROM url_mapping ";
        $query .= "WHERE idFeedOut = ? ";
        $query .= "GROUP BY 1 ";
        $query .= "ORDER BY 1 ";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($idFeedOut));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound revenue URLs: ' . $e->getMessage());
        }

        return $results;
    }

    public function copyRevenueValues($fromDate, $toDate, $idCompany)
    {

        try {
            $mappings = $this->getRevenueInboundMappings($toDate, $idCompany, null, null);
            if ($mappings && is_array($mappings)) {
                foreach ($mappings as $mapping) {
                    $query = $this->db->prepare("REPLACE INTO revenue( date, idFeedIn, idFeedOut, url, value ) SELECT ?,idFeedIn,idFeedOut,url,value FROM revenue WHERE date = ? AND idFeedIn = ? AND idFeedOut = ? AND url = ?");
                    $query->execute(array($toDate, $fromDate, $mapping['idFeedIn'], $mapping['idFeedOut'], $mapping['url']));
                }
            }

        } catch (PDOException $e) {
            $this->logError('Unable to copy revenue values: ' . $e->getMessage());
            return;
        }

    }

    public function setRevenueValue($date, $idFeedIn, $idFeedOut, $url, $value)
    {

        try {
            $query = $this->db->prepare("REPLACE INTO revenue( date, idFeedIn, idFeedOut, url, value ) VALUES( ?, ?, ?, ?, ? )");
            $query->execute(array($date, $idFeedIn, $idFeedOut, $url, $value));
        } catch (PDOException $e) {
            $this->logError('Unable to update revenue value: ' . $e->getMessage());
            return;
        }

    }

    public function getInboundRejections($idFeedIn, $offset = 0)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT CONVERT_TZ(timestamp,?,?) AS timestampConverted,result,leadstamp,listcode,url,fname,lname,addr,addr2,city,state,zip,country,dob,gender,landline,cellphone,email,ip FROM data_inbound WHERE idFeedIn = ? AND result IS NOT NULL ORDER BY timestamp DESC LIMIT " . intval($offset) . ",100");
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, $idFeedIn));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound rejections: ' . $e->getMessage());
        }

        return $results;
    }

    public function addJob($type, $destination, $fields, $filename, $records)
    {
        $jobId = null;

        try {
            $jobId = $this->insertRow('jobs', array(
                'type' => $type,
                'destination' => $destination,
                'fields' => $fields,
                'filename' => $filename,
                'records' => $records,
                'idUser' => LeadsSession::getUserId(),
            ));
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to add job: ' . $pdoException->getMessage());
        }

        return $jobId;
    }

    public function updateJob($jobId, $fields)
    {

        try {
            $status = $this->update('jobs', $fields, array(
                'jobId' => $jobId,
            ));
            return $status;
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to update job: ' . $pdoException->getMessage());
            return null;
        }

        return null;
    }

    public function getJob($jobId)
    {
        try {
            $query = $this->db->prepare("SELECT j.jobId,j.status,j.timestamp,f.label,j.fields,j.filename,j.records,u.username FROM jobs j LEFT JOIN users u ON j.idUser = u.idUser LEFT JOIN feedinc f ON j.destination = f.idFeedIn WHERE j.jobId = ?");
            $query->execute(array($jobId));
            return $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get job details: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function getJobs($idCompany = null)
    {
        try {
            $params = array();
            $sql = "SELECT j.jobId,j.type,j.status,j.timestamp,f.label,j.fields,j.filename,j.records,u.username ";
            $sql .= "FROM jobs j ";
            $sql .= "LEFT JOIN users u ON j.idUser = u.idUser ";
            $sql .= "LEFT JOIN feedinc f ON j.destination = f.idFeedIn ";
            if (!empty($idCompany)) {
                $sql .= "WHERE j.type = 'feedinc' ";
                $sql .= "AND destination IN (SELECT idFeedIn FROM feedinc WHERE idCompany = ?)";
                $params[] = $idCompany;
            }
            $sql .= "ORDER BY j.jobId DESC LIMIT 100";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get jobs: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function getJobsTimestamp()
    {
        try {
            $params = array();
            $sql = "SELECT j.jobId,j.type,j.status,j.timestamp,f.label,j.fields,j.filename,j.records,u.username ";
            $sql .= "FROM jobs j ";
            $sql .= "LEFT JOIN users u ON j.idUser = u.idUser ";
            $sql .= "LEFT JOIN feedinc f ON j.destination = f.idFeedIn ";
            $sql .= "WHERE timestamp >= '2018-10-01' ";
            $sql .= "AND type = 'feedinc' ";
            $sql .= "ORDER BY j.jobId DESC LIMIT 100";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get jobs: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function fixInboundJobTimestamp($jobId, $timestamp)
    {
        try {
            $query = $this->db->prepare('UPDATE data_inbound SET timestamp = ? WHERE timestamp IS NULL AND jobId = ?');
            $query->execute(array($timestamp, $jobId));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update data_inbound job timestamp: ' . $e->getMessage());
            return;
        }
    }

    public function fixInboundRecordTimestamp($idRecord, $timestamp)
    {
        try {
            $query = $this->db->prepare('UPDATE data_inbound SET timestamp = ? WHERE timestamp IS NULL AND idRecord = ?');
            $query->execute(array($timestamp, $idRecord));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update data_inbound record timestamp: ' . $e->getMessage());
            return;
        }
    }

    public function getInboundMissingTimestamps()
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT idRecord FROM dnrdmktg.data_inbound WHERE timestamp IS NULL");
            $query->execute();
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound missing timestamps: ' . $e->getMessage());
        }

        return $results;
    }

    public function getPendingJob()
    {
        try {
            $this->lockTables("jobs WRITE");
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to lock tables: ' . $pdoException->getMessage());
        }

        try {
            $query = $this->db->prepare("SELECT jobId,type,destination,fields,filename,records,idUser FROM jobs WHERE status = ?");
            $query->execute(array('pending'));
            $rows = $query->fetchAll(PDO::FETCH_OBJ);
            if ($rows && is_array($rows)) {
                foreach ($rows as $row) {
                    if (empty($row->filename) || file_exists($row->filename)) {

                        $this->updateJob($row->jobId, array(
                            'status' => 'processing',
                        ));

                        $this->unlockTables();
                        return $row;
                    }
                }
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get pending job: ' . $e->getMessage());
            return;
        }

        try {
            $this->unlockTables();
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to unlock tables: ' . $pdoException->getMessage());
        }

        return null;
    }

    public function getInboundJobRecords($jobId, $idRecord = 0, $idCompany = null)
    {
        $results = array();

        try {
            $params = array();
            $sql = "SELECT idRecord,email,url,result ";
            $sql .= "FROM data_inbound ";
            $sql .= "WHERE jobId = ? ";
            $params[] = $jobId;
            $sql .= "AND idRecord > ? ";
            $params[] = $idRecord;
            if (!empty($idCompany)) {
                $sql .= "AND idFeedIn IN (SELECT idFeedIn FROM feedinc WHERE idCompany = ?)";
                $params[] = $idCompany;
            }
            $sql .= "ORDER BY idRecord ASC LIMIT 500";

            $query = $this->db->prepare($sql);
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound job records: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundRejections($idFeedOut, $offset = 0)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,o.result,o.accepted,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip,i.idRecord FROM archive.data_outbound_" . date('Ym') . " o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.idFeedOut = ? AND o.processed = 1 AND o.accepted = 0 ORDER BY o.idRecord DESC LIMIT " . intval($offset) . ",100");
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, $idFeedOut));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound rejections: ' . $e->getMessage());
        }

        return $results;
    }

    public function retryOutboundRejections($idFeedOut, $date)
    {
        $count = 0;

        $localDate = new \DateTime($date, new DateTimeZone(LOCAL_TIMEZONE));

        // Timestamps in data_outbound may need to be converted to a different timezone
        $utcStart = new DateTime($date . ' 00:00:00', new DateTimeZone(LOCAL_TIMEZONE));
        $utcStart->setTimeZone(new DateTimeZone(DB_TIMEZONE));

        $utcEnd = new DateTime($date . ' 23:59:59', new DateTimeZone(LOCAL_TIMEZONE));
        $utcEnd->setTimeZone(new DateTimeZone(DB_TIMEZONE));

        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare("INSERT IGNORE INTO data_outbound(idRecord, idFeedIn, idFeedOut, `timestamp`, `result`, idRecordLegacy, processed, isBillable, url, accepted) SELECT idRecord, idFeedIn, idFeedOut, NULL, NULL, idRecordLegacy, 0, 0, url, 0 FROM archive.data_outbound_" . $localDate->format('Ym') . " WHERE accepted = 0 AND processed = 1 AND idFeedOut = ? AND timestamp >= ? AND timestamp <= ?");
            $query->execute(array($idFeedOut, $utcStart->format('c'), $utcEnd->format('c')));
            $count = $query->rowCount();
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to retry outbound rejections (1): ' . $e->getMessage());
            return null;
        }

        if ($count > 0) {
            try {
                $query = $this->db->prepare("DELETE FROM archive.data_outbound_" . $localDate->format('Ym') . " WHERE accepted = 0 AND processed = 1 AND idFeedOut = ? AND timestamp >= ? AND timestamp <= ?");
                $query->execute(array($idFeedOut, $utcStart->format('c'), $utcEnd->format('c')));
            } catch (PDOException $e) {
                $this->db->rollBack();
                $this->logError('Unable to retry outbound rejections (2): ' . $e->getMessage());
                return null;
            }

            try {
                $query = $this->db->prepare("UPDATE feedout SET queued = queued + ? WHERE idFeedOut = ?");
                $query->execute(array($count, $idFeedOut));
            } catch (PDOException $e) {
                $this->db->rollBack();
                $this->logError('Unable to retry outbound rejections (3): ' . $e->getMessage());
                return null;
            }

            try {
                $query = $this->db->prepare("UPDATE stats_outbound SET rejected = 0 WHERE idFeedOut = ? AND stamp = ?");
                $query->execute(array($idFeedOut, $date));
            } catch (PDOException $e) {
                $this->db->rollBack();
                $this->logError('Unable to retry outbound rejections (4): ' . $e->getMessage());
                return null;
            }
        }

        $this->db->commit();

        return $count;
    }

    public function getInboundStats($idFeedIn)
    {
        $results = array('accepted' => 0, 'rejected' => 0);

        try {
            $query = $this->db->prepare("SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = ? AND idFeedIn = ?");
            $query->execute(array(date('Y-m-d'), $idFeedIn));
            $results = $query->fetch();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundStatsRange($idFeedIn, $stampStart, $stampEnd)
    {
        $results = array('accepted' => 0, 'rejected' => 0);

        try {
            $query = $this->db->prepare("SELECT IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp >= ? AND stamp <= ? AND idFeedIn = ?");
            $query->execute(array($stampStart, $stampEnd, $idFeedIn));
            $results = $query->fetch();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundURLStats($idFeedIn)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT url,IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp = ? AND idFeedIn = ? GROUP BY url");
            $query->execute(array(date('Y-m-d'), $idFeedIn));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundURLStatsRange($idFeedIn, $stampStart, $stampEnd)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT url,IFNULL(SUM(accepted),0) accepted,IFNULL(SUM(rejected),0) rejected FROM stats_inbound WHERE stamp >= ? AND stamp <= ? AND idFeedIn = ? GROUP BY url");
            $query->execute(array($stampStart, $stampEnd, $idFeedIn));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL stats: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundURLDates($idFeedIn)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT url,MIN(stamp) AS date FROM stats_inbound WHERE idFeedIn = ? GROUP BY url");
            $query->execute(array($idFeedIn));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL dates: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundURLStatsReport($idFeedIn, $urlList, $breakdown, $dateStart, $dateEnd, $sort, $group)
    {
        $results = array();
        $params = array();

        if (!empty($breakdown) && $breakdown == 'month') {
            $query = "SELECT url,LEFT(stamp,7) date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } elseif (!empty($breakdown) && $breakdown == 'year') {
            $query = "SELECT url,LEFT(stamp,4) date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } elseif (!empty($breakdown) && $breakdown == 'total') {
            $query = "SELECT url,'TOTAL' as date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } else {
            $query = "SELECT url,stamp AS date,SUM(accepted) accepted,SUM(rejected) rejected ";
        }

        $query .= "FROM stats_inbound ";
        $query .= "WHERE idFeedIn = ? ";
        $params[] = $idFeedIn;
        if (!empty($urlList) && is_array($urlList)) {
            $query .= "AND url IN (" . substr(str_repeat('?,', sizeOf($urlList)), 0, -1) . ") ";
            foreach ($urlList as $url) {
                $params[] = $url;
            }
        }

        if (!empty($dateStart) && !empty($dateEnd)) {
            if (strtotime($dateStart) > strtotime($dateEnd)) {
                $dateStart = date("Y-m-d", strtotime($dateEnd));
                $dateEnd = date("Y-m-d", strtotime($dateStart));
            } else {
                $dateStart = date("Y-m-d", strtotime($dateStart));
                $dateEnd = date("Y-m-d", strtotime($dateEnd));
            }
            $query .= "AND stamp >= '" . $dateStart . "' AND stamp <= '" . $dateEnd . "' ";
        }

        if (empty($group) || 'url' == $group) {
            $query .= "GROUP BY 1,2 ";
        } elseif ('date' == $group) {
            $query .= "GROUP BY 2 ";
        }
        if (!empty($sort) && 'url' == $sort) {
            $query .= "ORDER BY 1,2";
        } elseif (!empty($sort) && 'count' == $sort) {
            $query .= "ORDER BY 3,1";
        } else {
            $query .= "ORDER BY 2,1";
        }

        try {
            $query = $this->db->prepare($query);
            $query->execute($params);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL dates: ' . $e->getMessage());
        }

        return $results;
    }

    public function getInboundStatsAverages()
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT s.idFeedIn,s.url,c.name,i.label FROM dnrdmktg.stats_inbound s LEFT JOIN feedinc i ON i.idFeedIn = s.idFeedIn LEFT JOIN companies c ON i.idCompany = c.idCompany WHERE s.stamp >= DATE_SUB(NOW(),INTERVAL 90 DAY) AND s.accepted > 0 AND s.url != '' GROUP BY s.url,s.idFeedIn");
            $query->execute();
            $urls = $query->fetchAll();

            if (!empty($urls) && is_array($urls)) {
                foreach ($urls as $url) {
                    $results[$url['idFeedIn']][$url['url']] = array(
                        'daily' => 0,
                        'weekly' => 0,
                        'monthly' => 0,
                        'idFeedIn' => $url['idFeedIn'],
                        'url' => $url['url'],
                        'label' => $url['label'],
                        'name' => $url['name'],
                    );

                    $query = $this->db->prepare("SELECT AVG(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 90 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn");
                    $query->execute(array($url['url'], $url['idFeedIn']));
                    $results[$url['idFeedIn']][$url['url']]['daily'] = $query->fetchColumn();

                    $query = $this->db->prepare("SELECT SUM(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 7 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn");
                    $query->execute(array($url['url'], $url['idFeedIn']));
                    $results[$url['idFeedIn']][$url['url']]['weekly'] = $query->fetchColumn();

                    $query = $this->db->prepare("SELECT SUM(accepted) FROM dnrdmktg.stats_inbound WHERE stamp >= DATE_SUB(NOW(),INTERVAL 30 DAY) AND url = ? AND idFeedIn = ? GROUP BY url,idFeedIn");
                    $query->execute(array($url['url'], $url['idFeedIn']));
                    $results[$url['idFeedIn']][$url['url']]['monthly'] = $query->fetchColumn();

                }
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound stats averages: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundURLDates($idFeedOut)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT url,MIN(stamp) AS date FROM stats_outbound WHERE idFeedOut = ? GROUP BY url");
            $query->execute(array($idFeedOut));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound URL dates: ' . $e->getMessage());
        }

        return $results;
    }

    public function getOutboundURLStatsReport($idFeedOut, $urlList, $breakdown, $dateStart, $dateEnd, $sort, $group)
    {
        $results = array();
        $params = array();

        if (!empty($breakdown) && $breakdown == 'month') {
            $query = "SELECT url,LEFT(stamp,7) date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } elseif (!empty($breakdown) && $breakdown == 'year') {
            $query = "SELECT url,LEFT(stamp,4) date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } elseif (!empty($breakdown) && $breakdown == 'total') {
            $query = "SELECT url,'TOTAL' as date,SUM(accepted) accepted,SUM(rejected) rejected ";
        } else {
            $query = "SELECT url,stamp AS date,SUM(accepted) accepted,SUM(rejected) rejected ";
        }

        $query .= "FROM stats_outbound ";
        $query .= "WHERE idFeedOut = ? ";
        $params[] = $idFeedOut;
        if (!empty($urlList) && is_array($urlList)) {
            $query .= "AND url IN (" . substr(str_repeat('?,', sizeOf($urlList)), 0, -1) . ") ";
            foreach ($urlList as $url) {
                $params[] = $url;
            }
        }

        if (!empty($dateStart) && !empty($dateEnd)) {
            if (strtotime($dateStart) > strtotime($dateEnd)) {
                $dateStart = date("Y-m-d", strtotime($dateEnd));
                $dateEnd = date("Y-m-d", strtotime($dateStart));
            } else {
                $dateStart = date("Y-m-d", strtotime($dateStart));
                $dateEnd = date("Y-m-d", strtotime($dateEnd));
            }
            $query .= "AND stamp >= '" . $dateStart . "' AND stamp <= '" . $dateEnd . "' ";
        }

        if (empty($group) || 'url' == $group) {
            $query .= "GROUP BY 1,2 ";
        } elseif ('date' == $group) {
            $query .= "GROUP BY 2 ";
        }
        if (!empty($sort) && 'url' == $sort) {
            $query .= "ORDER BY 1,2";
        } elseif (!empty($sort) && 'count' == $sort) {
            $query .= "ORDER BY 3,1";
        } else {
            $query .= "ORDER BY 2,1";
        }

        try {
            $query = $this->db->prepare($query);
            $query->execute($params);
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound URL dates: ' . $e->getMessage());
        }

        return $results;
    }

    public function addNotification($idFeedIn, $url)
    {
        try {
            $query = $this->db->prepare("REPLACE INTO notifications (lastTime, notifyTime, idFeedIn, url) VALUES(NOW(), 0, ?, ?)");
            $query->execute(array($idFeedIn, $this->parseUrl($url)));
        } catch (PDOException $e) {
            $this->logError('Unable to add notification record: ' . $e->getMessage());
            return;
        }
    }

    public function deleteNotifications($idFeedIn)
    {
        try {
            $query = $this->db->prepare("DELETE FROM notifications WHERE idFeedIn = ?");
            $query->execute(array($idFeedIn));
        } catch (PDOException $e) {
            $this->logError('Unable to delete notification records: ' . $e->getMessage());
            return;
        }
    }

    public function checkURLNotifications($idFeedIn, $url)
    {
        $cnt = null;

        try {
            $query = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE idFeedIn = ? AND url = ?");
            $query->execute(array($idFeedIn, $this->parseUrl($url)));
            $cnt = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to check URL notification records: ' . $e->getMessage());
            return $cnt;
        }

        return $cnt;
    }

    public function getCompanySalesNotifications()
    {
        $cnt = null;

        try {
            $sql = "SELECT c.idCompany,c.name,u.email,MAX(n.timestamp) AS lastDate FROM ( ";
            $sql .= "SELECT idCompany FROM dnrdmktg.feedinc WHERE status IN ('active') ";
            $sql .= "UNION ";
            $sql .= "SELECT idCompany FROM dnrdmktg.feedout WHERE status IN ('active') ";
            $sql .= ") AS f ";
            $sql .= "LEFT JOIN dnrdmktg.companies c ON c.idCompany = f.idCompany ";
            $sql .= "LEFT JOIN dnrdmktg.users u ON u.idUser = c.accountManager ";
            $sql .= "LEFT JOIN dnrdmktg.companies_notes n ON n.companyId = c.idCompany ";
            $sql .= "LEFT JOIN dnrdmktg.notifications_companies nc ON nc.idCompany = c.idCompany ";
            $sql .= "LEFT JOIN dnrdmktg.feedinc i ON i.idCompany = c.idCompany ";
            $sql .= "WHERE c.accountManager IS NOT NULL ";
            $sql .= "AND u.level > 0 ";
            $sql .= "AND ( nc.lastNotification IS NULL OR nc.lastNotification < DATE_SUB(NOW(),INTERVAL 7 DAY)) ";
            $sql .= "AND i.status IN ('active','hidden') ";
            $sql .= "GROUP BY c.idCompany HAVING ( lastDate IS NULL OR lastDate < DATE_SUB(NOW(),INTERVAL 1 MONTH)) ";
            $query = $this->db->prepare($sql);
            $query->execute();
            $cnt = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get company sales notifications: ' . $e->getMessage());
            return $cnt;
        }

        return $cnt;
    }

    public function updateCompanyNotificationDate($companyId)
    {
        try {
            $query = $this->db->prepare("REPLACE INTO notifications_companies VALUES (?,NOW())");
            $query->execute(array($companyId));
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to update company notification: ' . $e->getMessage());
            return null;
        }
    }

    public function inboundEmailSearch($email)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT i.*,f.label FROM data_inbound i INNER JOIN feedinc f ON i.idFeedIn = f.idFeedIn WHERE email = ?");
            $query->execute(array($email));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound email search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function inboundRecordSearch($email, $phone, $url, $ip)
    {
        $params = array();

        $dateStart = new \DateTime();
        $dateEnd = new \DateTime('2014-06-01');

        $checkSql = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE (TABLE_SCHEMA = 'archive') AND (TABLE_NAME = ?)");

        // Establish our baseline SQL that we'll change in the loop
        $baseSql = "( SELECT fi.label,i.idFeedIn,CONVERT_TZ(i.timestamp,?,?) AS timestampConverted,i.idRecord,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip,i.result ";
        $params[] = DB_TIMEZONE;
        $params[] = LOCAL_TIMEZONE;
        $baseSql .= "FROM data_inbound AS i ";
        $baseSql .= "LEFT JOIN feedinc fi ON fi.idFeedIn = i.idFeedIn ";
        $baseSql .= "WHERE 1=1 ";
        if (!empty($email)) {
            $baseSql .= "AND i.email = ? ";
            $params[] = $email;
        }
        if (!empty($url)) {
            $baseSql .= "AND i.url = ? ";
            $params[] = $url;
        }
        if (!empty($ip)) {
            $baseSql .= "AND i.ip = ? ";
            $params[] = $ip;
        }
        if (!empty($phone)) {
            $baseSql .= "AND ( i.cellphone = ? OR i.landline = ? ) ";
            $params[] = $phone;
            $params[] = $phone;
        }
        $baseSql .= "LIMIT 1000 ) " . PHP_EOL;

        $sql = "SELECT * FROM ( ";

        $sql .= $baseSql;

        do {

            // Check if the table actually exists first, since archive tables are not always created immediately.
            $checkSql->execute(array('data_inbound_' . $dateStart->format('Ym')));
            if ($checkSql && $checkSql->fetchColumn()) {

                $sql .= " UNION " . str_replace("FROM data_inbound", "FROM archive." . $this->quoteIdentifier('data_inbound_' . $dateStart->format('Ym')), $baseSql);
                $params[] = DB_TIMEZONE;
                $params[] = LOCAL_TIMEZONE;
                if (!empty($email)) {
                    $params[] = $email;
                }
                if (!empty($url)) {
                    $params[] = $url;
                }
                if (!empty($ip)) {
                    $params[] = $ip;
                }
                if (!empty($phone)) {
                    $params[] = $phone;
                    $params[] = $phone;
                }

            }

            try {
                $dateStart->sub(new \DateInterval(('P1M')));
            } catch (\Exception $e) {
                break;
            }
        } while ($dateStart->format('Ym') >= $dateEnd->format('Ym'));

        $sql .= " ) AS recs ";
        $sql .= "ORDER BY recs.timestampConverted DESC ";
        $sql .= "LIMIT 500";

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get inbound record search results: ' . $e->getMessage());
        }

        return array();
    }

    public function homeownerExportArchived()
    {
        $params = array();

        //$dateStart = new \DateTime('2014-06-01');
        $dateStart = new \DateTime('2018-01-01');
        //$dateEnd = new \DateTime();
        $dateEnd = new DateTime('2018-07-31');

        $checkSql = $this->db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE (TABLE_SCHEMA = 'archive') AND (TABLE_NAME = ?)");

        // Establish our baseline SQL that we'll change in the loop
        $baseSql = "SELECT i.leadstamp,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.email ";
        $baseSql .= "FROM data_inbound AS i ";
        $baseSql .= "WHERE result IS NULL ";
        $baseSql .= "AND timestamp >= CONVERT_TZ(:dayStart,:tzLocalStart,:tzServerStart) ";
        $baseSql .= "AND timestamp <= CONVERT_TZ(:dayEnd,:tzLocalEnd,:tzServerEnd) ";
        $baseSql .= "AND addr IS NOT NULL ";
        $baseSql .= "AND addr <> ''";

        try {
            do {

                // Check if the table actually exists first, since archive tables are not always created immediately.
                $checkSql->execute(array('data_inbound_' . $dateStart->format('Ym')));
                if ($checkSql && $checkSql->fetchColumn()) {

                    $sql = str_replace("FROM data_inbound", "FROM archive." . $this->quoteIdentifier('data_inbound_' . $dateStart->format('Ym')), $baseSql) . " " . PHP_EOL;

                    $file = fopen(ADMIN_ROOT . 'exports/homeowner_' . $dateStart->format('Ym') . '.csv', 'w');
                    if (!$file) {
                        print 'Unable to create CSV file.';
                    }

                    fputcsv($file, array(
                        'leadstamp',
                        'url',
                        'fname',
                        'lname',
                        'addr1',
                        'addr2',
                        'city',
                        'state',
                        'zip',
                        'country',
                        'email',
                    ));

                    $this->unsetBufferedQuery();

                    $query = $this->db->prepare($sql);
                    $query->bindValue(':tzLocalStart', LOCAL_TIMEZONE);
                    $query->bindValue(':tzServerStart', DB_TIMEZONE);
                    $query->bindValue(':tzLocalEnd', LOCAL_TIMEZONE);
                    $query->bindValue(':tzServerEnd', DB_TIMEZONE);
                    $query->bindValue(':dayStart', $dateStart->format('Y-m') . '-01 00:00:00');
                    $query->bindValue(':dayEnd', $dateStart->format('Y-m-t') . ' 23:59:59');

                    $query->execute();
                    while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                        fputcsv($file, $row);
                    }

                    fclose($file);
                }

                $this->setBufferedQuery();

                try {
                    $dateStart->add(new \DateInterval(('P1M')));
                } catch (\Exception $e) {
                    break;
                }
            } while ($dateStart->format('Ym') <= $dateEnd->format('Ym'));
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get archived inbound homeowner data: ' . $e->getMessage());
        } finally {
            $this->setBufferedQuery();
            if ($file) {
                fclose($file);
            }
        }
    }

    public function homeownerExportCurrent()
    {
        $params = array();

        //$dateStart = new \DateTime('2014-06-01');
        $dateStart = new \DateTime('2018-07-01');
        //$dateEnd = new \DateTime();
        $dateEnd = new DateTime('2018-09-30');

        // Establish our baseline SQL that we'll change in the loop
        $sql = "SELECT i.leadstamp,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.email ";
        $sql .= "FROM data_inbound AS i ";
        $sql .= "WHERE result IS NULL ";
        $sql .= "AND timestamp >= CONVERT_TZ(:dayStart,:tzLocalStart,:tzServerStart) ";
        $sql .= "AND timestamp <= CONVERT_TZ(:dayEnd,:tzLocalEnd,:tzServerEnd) ";
        $sql .= "AND addr IS NOT NULL ";
        $sql .= "AND addr <> ''";

        try {
            do {

                $file = fopen(ADMIN_ROOT . 'exports/homeowner_' . $dateStart->format('Ym') . '.csv', 'w');
                if (!$file) {
                    print 'Unable to create CSV file.';
                }

                fputcsv($file, array(
                    'leadstamp',
                    'url',
                    'fname',
                    'lname',
                    'addr1',
                    'addr2',
                    'city',
                    'state',
                    'zip',
                    'country',
                    'email',
                ));

                $this->unsetBufferedQuery();

                $query = $this->db->prepare($sql);
                $query->bindValue(':tzLocalStart', LOCAL_TIMEZONE);
                $query->bindValue(':tzServerStart', DB_TIMEZONE);
                $query->bindValue(':tzLocalEnd', LOCAL_TIMEZONE);
                $query->bindValue(':tzServerEnd', DB_TIMEZONE);
                $query->bindValue(':dayStart', $dateStart->format('Y-m') . '-01 00:00:00');
                $query->bindValue(':dayEnd', $dateStart->format('Y-m-t') . ' 23:59:59');

                $query->execute();
                while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                    fputcsv($file, $row);
                }

                fclose($file);

                try {
                    $dateStart->add(new \DateInterval(('P1M')));
                } catch (\Exception $e) {
                    break;
                }
            } while ($dateStart->format('Ym') <= $dateEnd->format('Ym'));
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get current inbound homeowner data: ' . $e->getMessage());
        } finally {
            $this->setBufferedQuery();
            if ($file) {
                fclose($file);
            }
        }
    }

    public function outboundRecordSearchById($recordId)
    {
        $params = array();

        $dateStart = new \DateTime();
        $dateStart->sub(new \DateInterval(('P1Y')));
        $dateEnd = new \DateTime();

        // Establish our baseline SQL that we'll change in the loop
        $baseSql = "( SELECT fo.label,o.idFeedOut,CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,o.result ";
        $params[] = DB_TIMEZONE;
        $params[] = LOCAL_TIMEZONE;
        $baseSql .= "FROM data_outbound AS o ";
        $baseSql .= "LEFT JOIN feedout fo ON fo.idFeedOut = o.idFeedOut ";
        $baseSql .= "WHERE o.idRecord = ? ";
        $params[] = $recordId;
        $baseSql .= ") UNION ";

        $sql = "SELECT * FROM ( ";
        $sql .= $baseSql;


        do {
            $sql .= str_replace("FROM data_outbound", "FROM archive." . $this->quoteIdentifier('data_outbound_' . $dateStart->format('Ym')), $baseSql);
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $params[] = $recordId;

            try {
                $dateStart->add(new \DateInterval(('P1M')));
            } catch (\Exception $e) {
                break;
            }
        } while ($dateStart->format('Ym') <= $dateEnd->format('Ym'));

        $sql = substr($sql, 0, -6); // Remove the last UNION statement
        $sql .= " ) AS recs ";
        $sql .= "ORDER BY recs.timestampConverted ";

        //echo($sql);

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            return $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get outbound record search by id results: ' . $e->getMessage());
        }

        return array();
    }

    public function archivedOutboundRecordsSearch($idFeedOut, $dateStartIn, $dateEndIn, $idRecord = null)
    {
        $results = array();
        $params = array();

        $archiveDate = new \DateTime($dateStartIn);
        $dateStart = new \DateTime($dateStartIn);
        $dateEnd = new \DateTime($dateEndIn);

        $sql = "SELECT * FROM ( ";

        do {
            $sql .= "( SELECT CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,o.result,o.idFeedOut,o.idFeedIn,o.idRecord,o.isBillable,o.url AS urlOutbound,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip ";
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $sql .= "FROM archive." . $this->quoteIdentifier('data_outbound_' . $archiveDate->format('Ym')) . " o ";
            $sql .= "INNER JOIN data_inbound i ON i.idRecord = o.idRecord ";
            $sql .= "WHERE o.idFeedOut = ? ";
            $params[] = $idFeedOut;
            $sql .= "AND o.processed = 1 ";
            $sql .= "AND o.accepted = 1 ";
            if (!empty($idRecord)) {
                $sql .= "AND o.idRecord = ? ";
                $params[] = $idRecord;
            } else {
                $sql .= "AND o.timestamp >= CONVERT_TZ(?,?,?) ";
                $params[] = $dateStart->format('Y-m-d H:i:s');
                $params[] = LOCAL_TIMEZONE;
                $params[] = DB_TIMEZONE;
                $sql .= "AND o.timestamp <= CONVERT_TZ(?,?,?) ";
                $params[] = $dateEnd->format('Y-m-d H:i:s');
                $params[] = LOCAL_TIMEZONE;
                $params[] = DB_TIMEZONE;
            }
            $sql .= ") UNION ";
            //$sql .= "LIMIT " . intval( $offset ) . ",100";

            try {
                $archiveDate->add(new \DateInterval(('P1M')));
            } catch (\Exception $e) {
                break;
            }
        } while ($archiveDate->format('Ym') <= $dateEnd->format('Ym'));

        $sql = substr($sql, 0, -6); // Remove the last UNION statement
        $sql .= " ) AS recs ";
        $sql .= "ORDER BY recs.timestampConverted ";

        //echo($sql);

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            if (!empty($idRecord)) {
                $results = $query->fetch(\PDO::FETCH_OBJ);
            } else {
                $results = $query->fetchAll(\PDO::FETCH_OBJ);
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get archived outbound records search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function archiveOutboundPhone($phone, $dateStartIn, $dateEndIn)
    {
        $results = array();
        $params = array();

        $archiveDate = new \DateTime($dateStartIn);
        $dateStart = new \DateTime($dateStartIn);
        $dateEnd = new \DateTime($dateEndIn);

        $sql = "SELECT * FROM ( ";

        do {
            $sql .= "( SELECT CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,o.result,o.idFeedOut,o.idFeedIn,o.idRecord,o.isBillable,o.url AS urlOutbound,i.leadstamp,i.listcode,i.url,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,i.email,i.ip ";
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $sql .= "FROM archive." . $this->quoteIdentifier('data_outbound_' . $archiveDate->format('Ym')) . " o ";
            $sql .= "INNER JOIN archive." . $this->quoteIdentifier('data_inbound_' . $archiveDate->format('Ym')) . " i ON i.idRecord = o.idRecord ";
            $sql .= "WHERE 1=1 ";
            $sql .= "AND (i.cellphone = ? OR i.landline = ? ) ";
            $params[] = $phone;
            $params[] = $phone;
            $sql .= "AND o.timestamp >= CONVERT_TZ(?,?,?) ";
            $params[] = $dateStart->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $sql .= "AND o.timestamp <= CONVERT_TZ(?,?,?) ";
            $params[] = $dateEnd->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $sql .= ") UNION ";
            //$sql .= "LIMIT " . intval( $offset ) . ",100";

            echo $archiveDate->format('Y-m-d') . ' ' . $dateStart->format('Y-m-d H:i:s') . ' - ' . $dateEnd->format('Y-m-d H:i:s') . PHP_EOL;

            try {
                $archiveDate->add(new \DateInterval(('P1M')));
            } catch (\Exception $e) {
                break;
            }
        } while ($archiveDate->format('Ym') <= $dateEnd->format('Ym'));

        $sql = substr($sql, 0, -6); // Remove the last UNION statement
        $sql .= " ) AS recs ";
        $sql .= "ORDER BY recs.timestampConverted ";

        //echo($sql);

        try {
            $query = $this->db->prepare($sql);
            $query->execute($params);
            if (!empty($idRecord)) {
                $results = $query->fetch(\PDO::FETCH_OBJ);
            } else {
                $results = $query->fetchAll(\PDO::FETCH_OBJ);
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
            $this->logError('Unable to get archived outbound records search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function globalEmailSearch($email)
    {
        $results = null;

        try {
            $query = $this->db->prepare("SELECT MIN(timestamp) FROM data_inbound WHERE email = ?");
            $query->execute(array($email));
            $results = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound email search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function outboundEmailSearch($email)
    {
        $results = array();

        $query = "SELECT i.*,o.idFeedOut,f.label,o.result ";
        $query .= "FROM data_inbound i ";
        $query .= "INNER JOIN data_outbound o ON o.idRecord = i.idRecord ";
        $query .= "LEFT JOIN feedout f ON o.idFeedOut = f.idFeedOut ";
        $query .= "WHERE i.email = ? ";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($email));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound email search results: ' . $e->getMessage());
        }

        return $results;
    }


    public function firstOutboundRecord($idFeedOut, $url)
    {
        $results = null;

        try {
            $query = $this->db->prepare("SELECT MIN(o.idRecord) FROM data_outbound o JOIN data_inbound i ON i.idRecord=o.idRecord WHERE o.idFeedOut = ? AND i.url = ?");
            $query->execute(array($idFeedOut, $url));
            $results = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get first outbound record: ' . $e->getMessage());
        }

        return $results;
    }

    public function checkInboundURLExists($idFeedIn, $url)
    {
        try {
            $query = $this->db->prepare("SELECT 1 FROM notifications WHERE url = ? AND idFeedIn = ? LIMIT 1");
            $query->execute(array($this->parseUrl($url), $idFeedIn));
            if ($query && $query->fetchColumn()) {
                return true;
            }

        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL exists results: ' . $e->getMessage());
            return null;
        }

        return false;
    }

    public function inboundURLSearch($url)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT f.label,s.idFeedIn,MAX(s.stamp) AS timestamp, SUM(s.accepted) AS cnt FROM stats_inbound s LEFT JOIN feedinc f ON f.idFeedIn = s.idFeedIn WHERE url = ? GROUP BY idFeedIn");
            $query->execute(array($url));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound URL search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function outboundURLSearch($url)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT f.label,s.idFeedOut,MAX(s.stamp) AS timestamp,SUM(s.accepted) AS cnt FROM stats_outbound s LEFT JOIN feedout f ON f.idFeedOut = s.idFeedOut WHERE url = ? GROUP BY idFeedOut");
            $query->execute(array($url));
            $results = $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound URL search results: ' . $e->getMessage());
        }

        return $results;
    }

    public function purgeInboundRejections()
    {
        $cnt = 0;
        $recordId = 0;

        try {

            $querySelect = $this->db->prepare("SELECT /*!40001 SQL_NO_CACHE */ idRecord FROM data_inbound WHERE timestamp <= CONVERT_TZ(DATE_SUB(NOW(),INTERVAL 90 DAY),:tzLocal,:tzServer) AND result IS NOT NULL AND result NOT LIKE 'Third-party rejection [%1]' AND idRecord > :idRecord ORDER BY idRecord LIMIT 1");
            $querySelect->bindValue(':tzLocal', LOCAL_TIMEZONE);
            $querySelect->bindValue(':tzServer', DB_TIMEZONE);
            $querySelect->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);
            $querySelect->bindColumn(1, $recordId);

            $queryDelete = $this->db->prepare("DELETE FROM data_inbound WHERE idRecord = :idRecord");
            $queryDelete->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);

            do {
                $querySelect->execute();
                $row = $querySelect->fetch(\PDO::FETCH_BOUND);

                if (true === $row) {
                    if ($cnt % 1000 === 0) {
                        print date('c') . " Purging inbound rejected {$recordId}\n";
                    }

                    $queryDelete->execute();
                }

                $cnt++;

                //usleep( 5000 );

            } while (!empty($row));

        } catch (PDOException $e) {
            $this->logError('Unable to purge inbound rejections: ' . $e->getMessage());
        }

        return $cnt;
    }

    public function purgeOutboundRejections()
    {
        $cnt = 0;
        $recordId = 0;
        $idFeedOut = 0;

        try {

            $querySelect = $this->db->prepare("SELECT /*!40001 SQL_NO_CACHE */ idRecord,idFeedOut FROM data_outbound FORCE INDEX(`recordid`) WHERE timestamp <= CONVERT_TZ(DATE_SUB(NOW(),INTERVAL 90 DAY),:tzLocal,:tzServer) AND processed = 1 AND accepted = 0 AND ( idRecord > :idRecordOne OR ( idRecord = :idRecordTwo AND idFeedOut >= :idFeedOut ) ) ORDER BY idRecord,idFeedOut LIMIT 1");
            $querySelect->bindValue(':tzLocal', LOCAL_TIMEZONE);
            $querySelect->bindValue(':tzServer', DB_TIMEZONE);
            $querySelect->bindParam(':idRecordOne', $recordId, \PDO::PARAM_INT);
            $querySelect->bindParam(':idRecordTwo', $recordId, \PDO::PARAM_INT);
            $querySelect->bindParam(':idFeedOut', $idFeedOut, \PDO::PARAM_INT);
            $querySelect->bindColumn(1, $recordId);
            $querySelect->bindColumn(2, $idFeedOut);

            $queryDelete = $this->db->prepare("DELETE FROM data_outbound WHERE idRecord = :idRecord AND idFeedOut = :idFeedOut");
            $queryDelete->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);
            $queryDelete->bindParam(':idFeedOut', $idFeedOut, \PDO::PARAM_INT);

            do {
                $querySelect->execute();
                $row = $querySelect->fetch(\PDO::FETCH_BOUND);

                if (true === $row) {
                    if ($cnt % 1000 === 0) {
                        print date('c') . " Purging outbound rejected {$recordId} {$idFeedOut}\n";
                    }

                    $queryDelete->execute();
                }

                $cnt++;

                //usleep( 5000 );

            } while (!empty($row));

        } catch (PDOException $e) {
            $this->logError('Unable to purge outbound rejections: ' . $e->getMessage());
        }

        return $cnt;
    }

    public function archiveInboundRecords()
    {
        $cnt = 0;
        $recordId = 0;

        $startDate = new \DateTime('now', new DateTimeZone(LOCAL_TIMEZONE));
        try {
            $startDate->sub(new \DateInterval('P3M'));
            $startDate->modify('first day of this month')->setTime(0, 0, 0);
            $endDate = clone $startDate;
            $endDate->modify('last day of this month')->setTime(23, 59, 59);
        } catch (Exception $e) {
            die('Date Error: ' . $e->getMessage());
        }

        try {

            $table = $this->quoteIdentifier('data_inbound_' . $startDate->format('Ym'));
            $this->db->query("CREATE TABLE IF NOT EXISTS archive." . $table . " LIKE data_inbound");

            $startDate->setTimeZone(new DateTimeZone(DB_TIMEZONE));
            $endDate->setTimeZone(new DateTimeZone(DB_TIMEZONE));
            echo $startDate->format('Y-m-d H:i:s') . ' to ' . $endDate->format('Y-m-d H:i:s') . PHP_EOL;

            //$querySelect = $this->db->prepare( "SELECT /*!40001 SQL_NO_CACHE */ idRecord FROM data_inbound WHERE result IS NULL AND timestamp >= :startDate AND timestamp <= :endDate AND idRecord > :idRecord ORDER BY idRecord LIMIT 1" );
            $querySelect = $this->db->prepare("SELECT /*!40001 SQL_NO_CACHE */ idRecord FROM data_inbound WHERE timestamp >= :startDate AND timestamp <= :endDate AND idRecord > :idRecord ORDER BY idRecord LIMIT 1");
            $querySelect->bindValue(':startDate', $startDate->format('Y-m-d H:i:s'));
            $querySelect->bindValue(':endDate', $endDate->format('Y-m-d H:i:s'));
            $querySelect->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);
            $querySelect->bindColumn(1, $recordId);

            $queryInsert = $this->db->prepare("INSERT IGNORE INTO archive." . $table . " SELECT * FROM data_inbound WHERE idRecord = :idRecord");
            $queryInsert->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);

            $queryDelete = $this->db->prepare("DELETE FROM data_inbound WHERE idRecord = :idRecord");
            $queryDelete->bindParam(':idRecord', $recordId, \PDO::PARAM_INT);

            $this->beginTransaction();

            do {
                $querySelect->execute();
                $row = $querySelect->fetch(\PDO::FETCH_BOUND);

                if (true === $row) {
                    if ($cnt % 1000 === 0) {
                        $this->db->commit();
                        print date('c') . " Archiving inbound {$recordId}\n";
                        $this->beginTransaction();
                    }

                    $queryInsert->execute();

                    $queryDelete->execute();

                }

                $cnt++;

                //usleep( 5000 );

            } while (!empty($row));

            $this->commit();

        } catch (PDOException $e) {
            $this->logError('Unable to archive inbound accepted: ' . $e->getMessage());
            $this->rollBack();
        }
        return $cnt;
    }

    public function archiveErrors()
    {
        try {
            $query = $this->db->prepare("DELETE FROM errorlog WHERE stamp <= DATE_SUB(NOW(), INTERVAL 15 DAY)");
            $query->execute();
            return $query->rowCount();
        } catch (PDOException $e) {
            $this->logError('Unable to delete old errorlog entries: ' . $e->getMessage());
        }

        return -1;
    }

    public function getLegacyInboundTables()
    {
        try {
            $query = $this->db->prepare("SHOW TABLES LIKE 'feedinc_%_invalid'");
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get old legacy inbound tables: ' . $e->getMessage());
        }

        return null;
    }

    public function clearOutboundQueue($idFeedOut, $label)
    {
        $rows = null;

        try {
            $this->lockTables("feedout WRITE, data_outbound WRITE");
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to lock tables: ' . $pdoException->getMessage());
            return null;
        }

        try {
            $query = $this->db->prepare("DELETE FROM data_outbound WHERE idFeedOut = ? AND processed = 0");
            $query->execute(array($idFeedOut));
            $rows = $query->rowCount();
        } catch (PDOException $e) {
            $this->logError('Unable to delete queued records (1): ' . $e->getMessage());
            return null;
        }

        try {
            $query = $this->db->prepare("UPDATE feedout SET queued = 0 WHERE idFeedOut = ?");
            $query->execute(array($idFeedOut));
        } catch (PDOException $e) {
            $this->logError('Unable to delete queued records (3): ' . $e->getMessage());
            return null;
        }

        try {
            $this->unlockTables();
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to unlock tables: ' . $pdoException->getMessage());
            return null;
        }

        return $rows;
    }

    public function clearOutboundQueueNibble($idFeedOut)
    {
        $cnt = 0;

        try {

            do {
                $this->db->beginTransaction();

                $query = $this->db->prepare("DELETE FROM data_outbound WHERE idFeedOut = ? AND processed = 0 LIMIT 1000");
                $query->execute(array($idFeedOut));
                $rowCount = $query->rowCount();
                $cnt += $rowCount;

                if ($rowCount > 0) {
                    $query = $this->db->prepare("UPDATE feedout SET queued = queued - :rowCount WHERE idFeedOut = :idFeedOut");
                    $query->bindValue(':rowCount', $rowCount, \PDO::PARAM_INT);
                    $query->bindValue(':idFeedOut', $idFeedOut, \PDO::PARAM_INT);
                    $query->execute();
                }

                $this->db->commit();

                usleep(50000);

            } while ($rowCount > 0);

        } catch (PDOException $e) {
            $this->logError('Unable to clear outbound queue slowly: ' . $e->getMessage());
        }

        return $cnt;
    }

    public function exportInboundRecords($idFeedIn, $settings)
    {

        $result = array(
            'success' => false,
            'reason' => 'None.',
            'fileLink' => null,
        );

        $feed = $this->getInboundFeed($idFeedIn);
        if (!$feed) {
            $result['reason'] = 'Not a valid incoming feed.';
            return $result;
        }

        if (!empty($settings['includeRejects'])) {
            $settings['columns'][] = 'result';
        }

        // Fix column names
        foreach ($settings['columns'] as $key => $column) {
            if ('stamp' == $column) {
                $settings['columns'][$key] = 'leadstamp';
            }
        }

        $jobId = time();

        $fileLink = 'exports/' . $idFeedIn . "_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            $result['reason'] = 'Unable to create CSV file.';
            return $result;
        }

        fputcsv($file, $settings['columns']);

        try {

            $fields = array();

            $query = "SELECT ";
            $comma = false;
            foreach ($settings['columns'] as $column) {
                if ($comma) {
                    $query .= ', ';
                }
                $query .= $this->quoteIdentifier($column);
                $comma = true;
            }
            $query .= " FROM data_inbound WHERE idFeedIn = ? ";
            $fields[] = $idFeedIn;

            if (empty($settings['includeRejects'])) {
                $query .= "AND result IS NULL ";
            }

            if (!empty($settings['dateStart']) && strtotime($settings['dateStart']) !== false) {
                $query .= "AND timestamp >= CONVERT_TZ(?,?,?) ";
                $fields[] = date('Y-m-d', strtotime($settings['dateStart'])) . ' 00:00:00';
                $fields[] = LOCAL_TIMEZONE;
                $fields[] = DB_TIMEZONE;
            }

            if (!empty($settings['dateEnd']) && strtotime($settings['dateEnd']) !== false) {
                $query .= "AND timestamp <= CONVERT_TZ(?,?,?) ";
                $fields[] = date('Y-m-d', strtotime($settings['dateEnd'])) . ' 23:59:59';
                $fields[] = LOCAL_TIMEZONE;
                $fields[] = DB_TIMEZONE;
            }

            if (!empty($settings['urlList']) && is_array($settings['urlList'])) {
                $orFlag = false;

                $query .= "AND (";
                foreach ($settings['urlList'] as $url) {
                    if (!empty($url)) {
                        if ($orFlag) {
                            $query .= " OR ";
                        }
                        $query .= "url = ?";
                        $fields[] = $url;
                        $orFlag = true;
                    }
                }
                $query .= ")";
            }

            if (!empty($settings['emailList']) && is_array($settings['emailList'])) {
                $orFlag = false;

                $query .= "AND (";
                foreach ($settings['emailList'] as $email) {
                    if (!empty($email)) {
                        if ($orFlag) {
                            $query .= " OR ";
                        }
                        $query .= "email LIKE ?";
                        $fields[] = '%@' . $email;
                        $orFlag = true;
                    }
                }
                $query .= ")";
            }

            if (!empty($settings['limit'])) {
                $query .= "LIMIT " . intval($settings['limit']);
            }

            $this->unsetBufferedQuery();

            $result['query'] = $query;
            $query = $this->db->Prepare($query);

            $query->execute($fields);
            $cnt = 0;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $cnt++;
                fputcsv($file, $row);
            }

        } catch (PDOException $e) {
            $this->logError('Unable to export inbound records: ' . $e->getMessage());
            $result['reason'] = 'SQL ERROR: ' . $e->getMessage();
            return $result;
        } finally {
            $this->setBufferedQuery();
        }

        fclose($file);

        $result['success'] = true;
        $result['reason'] = 'Successfully exported data to file.';
        $result['fileLink'] = $fileLink;
        $result['cnt'] = $cnt;

        return $result;
    }

    public function exportOutboundRecords($idFeedOut, $settings)
    {

        $result = array(
            'success' => false,
            'reason' => 'None.',
            'fileLink' => null,
        );

        $feed = $this->getOutboundFeed($idFeedOut);
        if (!$feed) {
            $result['reason'] = 'Not a valid outbound feed.';
            return $result;
        }

        $jobId = time();

        $fileLink = 'exports/' . $idFeedOut . "_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            $result['reason'] = 'Unable to create CSV file.';
            return $result;
        }

        fputcsv($file, array(
            'url',
            'email',
            'fname',
            'lname',
            'addr',
            'addr2',
            'city',
            'state',
            'zip',
            'country',
            'dob',
            'gender',
            'landline',
            'cellphone',
            'timestamp',
            'status',
            'response',
            'leadstamp',
            'listcode',
        ));

        $archiveDate = new \DateTime($settings['dateStart'] . ' 00:00:00');
        $dateStart = new \DateTime($settings['dateStart'] . ' 00:00:00');
        $dateEnd = new \DateTime($settings['dateEnd'] . ' 23:59:59');

        $sql = "SELECT * FROM ( ";

        if (!empty($settings['includeRejects'])) {
            $sql .= "( SELECT IFNULL(o.url,i.url) AS urlOutbound,i.email,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,IF(o.accepted = 1,'Accepted','Rejected') AS status,o.result,CONVERT_TZ(i.leadstamp,?,?),i.listcode ";
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $sql .= "FROM data_outbound AS o ";
            $sql .= "INNER JOIN data_inbound i ON i.idRecord = o.idRecord ";
            $sql .= "WHERE o.idFeedOut = ? ";
            $params[] = $idFeedOut;
            $sql .= "AND o.timestamp >= CONVERT_TZ(?,?,?) ";
            $params[] = $dateStart->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $sql .= "AND o.timestamp <= CONVERT_TZ(?,?,?) ";
            $params[] = $dateEnd->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;

            if (!empty($settings['urlList']) && is_array($settings['urlList'])) {
                $orFlag = false;

                $sql .= "AND (";
                foreach ($settings['urlList'] as $url) {
                    if (!empty($url)) {
                        if ($orFlag) {
                            $sql .= " OR ";
                        }
                        $sql .= "url = ?";
                        $params[] = $url;
                        $orFlag = true;
                    }
                }
                $sql .= ")";
            }

            if (!empty($settings['emailList']) && is_array($settings['emailList'])) {
                $orFlag = false;

                $sql .= "AND (";
                foreach ($settings['emailList'] as $email) {
                    if (!empty($email)) {
                        if ($orFlag) {
                            $sql .= " OR ";
                        }
                        $sql .= "email LIKE ?";
                        $params[] = ' % @' . $email;
                        $orFlag = true;
                    }
                }
                $sql .= ")";
            }

            $sql .= ") UNION ";
        }

        do {
            $sql .= "( SELECT IFNULL(o.url,i.url) AS urlOutbound,i.email,i.fname,i.lname,i.addr,i.addr2,i.city,i.state,i.zip,i.country,i.dob,i.gender,i.landline,i.cellphone,CONVERT_TZ(o.timestamp,?,?) AS timestampConverted,IF(o.accepted = 1,'Accepted','Rejected') AS status,o.result,CONVERT_TZ(i.leadstamp,?,?) AS leadstampConverted,i.listcode ";
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $params[] = LOCAL_TIMEZONE;
            $sql .= "FROM archive." . $this->quoteIdentifier('data_outbound_' . $archiveDate->format('Ym')) . " o ";
            $sql .= "INNER JOIN data_inbound i ON i.idRecord = o.idRecord ";
            $sql .= "WHERE o.idFeedOut = ? ";
            $params[] = $idFeedOut;
            $sql .= "AND o.timestamp >= CONVERT_TZ(?,?,?) ";
            $params[] = $dateStart->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;
            $sql .= "AND o.timestamp <= CONVERT_TZ(?,?,?) ";
            $params[] = $dateEnd->format('Y-m-d H:i:s');
            $params[] = LOCAL_TIMEZONE;
            $params[] = DB_TIMEZONE;

            if (!empty($settings['urlList']) && is_array($settings['urlList'])) {
                $orFlag = false;

                $sql .= "AND (";
                foreach ($settings['urlList'] as $url) {
                    if (!empty($url)) {
                        if ($orFlag) {
                            $sql .= " OR ";
                        }
                        $sql .= "url = ?";
                        $params[] = $url;
                        $orFlag = true;
                    }
                }
                $sql .= ")";
            }

            if (!empty($settings['emailList']) && is_array($settings['emailList'])) {
                $orFlag = false;

                $sql .= "AND (";
                foreach ($settings['emailList'] as $email) {
                    if (!empty($email)) {
                        if ($orFlag) {
                            $sql .= " OR ";
                        }
                        $sql .= "email LIKE ?";
                        $params[] = ' % @' . $email;
                        $orFlag = true;
                    }
                }
                $sql .= ")";
            }

            $sql .= ") UNION ";

            try {
                $archiveDate->add(new \DateInterval(('P1M')));
            } catch (\Exception $e) {
                break;
            }
        } while ($archiveDate->format('Ym') <= $dateEnd->format('Ym'));

        $sql = substr($sql, 0, -6); // Remove the last UNION statement
        $sql .= " ) AS recs ";
        $sql .= "ORDER BY recs.timestampConverted ";
        if (!empty($settings['limit'])) {
            $sql .= "LIMIT " . intval($settings['limit']);
        }

        try {
            $this->unsetBufferedQuery();

            $result['query'] = $sql;
            $query = $this->db->Prepare($sql);
            $query->execute($params);
            $cnt = 0;
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $cnt++;
                fputcsv($file, $row);
            }
        } catch (PDOException $e) {
            $this->logError('Unable to export outbound records: ' . $e->getMessage());
            $result['reason'] = 'SQL ERROR: ' . $e->getMessage();
            return $result;
        } finally {
            $this->setBufferedQuery();
        }

        fclose($file);

        $result['success'] = true;
        $result['reason'] = 'Successfully exported data to file.';
        $result['fileLink'] = $fileLink;
        $result['cnt'] = $cnt;

        return $result;
    }

    public function exportComcast()
    {

        $jobId = time();

        $fileLink = 'exports/' . "comcast_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            $result['reason'] = 'Unable to create CSV file.';
            return;
        }

        try {

            $this->unsetBufferedQuery();

            $fields = array();

            $query = $this->db->query("SELECT url,ip,leadstamp,email FROM data_inbound WHERE email LIKE '%@comcast.net' AND result IS NULL");
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($file, $row);
            }

        } catch (PDOException $e) {
            $this->logError('Unable to export Comcast records: ' . $e->getMessage());
            return;
        } finally {
            $this->setBufferedQuery();
        }

        fclose($file);
    }

    public function exportCable()
    {

        $jobId = time();

        $fileLink = 'exports/' . "cable_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            $result['reason'] = 'Unable to create CSV file.';
            return;
        }

        try {

            $this->unsetBufferedQuery();

            $fields = array();

            $query = $this->db->query("SELECT url,ip,leadstamp,email FROM data_inbound WHERE ( email LIKE '%@att.net' OR email LIKE '%@bellsouth.net' OR email LIKE '%@earthlink.net' ) AND result IS NULL");
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($file, $row);
            }

        } catch (PDOException $e) {
            $this->logError('Unable to export cable records: ' . $e->getMessage());
            return;
        } finally {
            $this->setBufferedQuery();
        }

        fclose($file);
    }

    public function exportRecords($sql, $fields = array())
    {

        try {

            $this->unsetBufferedQuery();

            $query = $this->db->prepare($sql);
            $query->execute($fields);
            return $query;

        } catch (PDOException $e) {
            $this->logError('Unable to export records: ' . $e->getMessage());
            return null;
        } finally {
            $this->setBufferedQuery();
        }

        return null;
    }

    public function fixStatsCorrelated()
    {
        $queryIn = $this->db->prepare("UPDATE stats_correlated SET costPerLead = ? WHERE idFeedIn = ?");
        $queryOut = $this->db->prepare("UPDATE stats_correlated SET revenuePerLead = ? WHERE idFeedOut = ?");
        $queryOutCpl = $this->db->prepare("UPDATE stats_correlated SET costPerLead = ? WHERE idFeedOut = ?");

        $query = $this->db->prepare("SELECT idFeedIn,costPerLead FROM feedinc WHERE costPerLead > 0");
        $query->execute();
        while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
            $queryIn->execute(array($row->costPerLead, $row->idFeedIn));
        }

        $query = $this->db->prepare("SELECT idFeedOut,revenuePerLead FROM feedout WHERE revenuePerLead > 0");
        $query->execute();
        while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
            $queryOut->execute(array($row->revenuePerLead, $row->idFeedOut));
        }

        $query = $this->db->prepare("SELECT idFeedOut,costPerLeadOverride FROM feedout WHERE costPerLeadOverride IS NOT NULL");
        $query->execute();
        while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
            $queryOutCpl->execute(array($row->costPerLeadOverride, $row->idFeedOut));
        }

    }

    public function backfillStatsCorrelated()
    {

        $idRecord = 760666734;
        $rowCount = 0;

        $statsQuery = $this->db->prepare('INSERT INTO stats_correlated(idFeedIn,idFeedOut,url,stamp,costPerLead,revenuePerLead,accepted,billable) VALUES(?,?,?,?,?,?,1,1) ON DUPLICATE KEY UPDATE accepted = accepted + 1, billable = billable + 1, costPerLead = ?, revenuePerLead = ?');

        $sqlSelect = "SELECT a.idRecord,a.idFeedIn,a.idFeedOut,DATE_FORMAT(CONVERT_TZ(a.`timestamp`,?,?),'%Y-%m-%d') AS `timestamp`,di.url,fi.costPerLead,fo.revenuePerLead,fo.costPerLeadOverride ";
        $sqlSelect .= "FROM archive.data_outbound_201801 AS a ";
        $sqlSelect .= "LEFT JOIN dnrdmktg.data_inbound AS di ON di.idRecord = a.idRecord ";
        $sqlSelect .= "LEFT JOIN dnrdmktg.feedinc fi ON fi.idFeedIn = a.idFeedIn ";
        $sqlSelect .= "LEFT JOIN dnrdmktg.feedout fo ON fo.idFeedOut = a.idFeedOut ";
        $sqlSelect .= "WHERE a.idRecord > ? ";
        //$sqlSelect .= "WHERE a.timestamp >= CONVERT_TZ('2018-05-29 00:00:00',?,?) AND a.timestamp <= CONVERT_TZ('2018-05-29 23:59:59',?,?) ";
        //$sqlSelect .= "ORDER BY a.idRecord LIMIT 10000";
        $query = $this->db->prepare($sqlSelect);
        do {
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, $idRecord));
            $rowCount = $query->rowCount();
            while ($rowCount > 0 && $row = $query->fetch(\PDO::FETCH_OBJ)) {

                $costPerLead = !empty($row->costPerLeadOverride) ? $row->costPerLeadOverride : (!empty($row->costPerLead) ? $row->costPerLead : 0.00);
                $revenuePerLead = !empty($row->revenuePerLead) ? $row->revenuePerLead : 0.00;

                try {
                    $statsQuery->execute(array($row->idFeedIn, $row->idFeedOut, $this->parseUrl($row->url), $row->timestamp, $costPerLead, $revenuePerLead, $costPerLead, $revenuePerLead));
                } catch (PDOException $e) {
                    $this->db->rollBack();
                    $this->logError('Unable to insert stats_correllated backfill record: ' . $e->getMessage());
                    return null;
                }

                $idRecord = $row->idRecord;
            }

            print date('c') . " Last record processed: {$idRecord}\n";
            usleep(2000);
        } while ($rowCount > 0);
    }

    public function exportOutboundQueue($idFeedOut)
    {

        $feedOut = $this->getOutboundFeed($idFeedOut);
        if (!$feedOut) {
            return;
        }

        $jobId = time();

        $fileLink = 'exports/' . $idFeedOut . "_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            return;
        }

        fputcsv($file, array(
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
            'leadId',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
        ));

        try {

            $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ?");
            $query->execute(array($idFeedOut));
            while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
                fputcsv($file, array(
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
                    $row->dob,
                    $row->gender,
                    $row->landline,
                    $row->cellphone,
                    $row->leadId,
                    $row->custom1,
                    $row->custom2,
                    $row->custom3,
                    $row->custom4,
                    $row->custom5,
                    $row->custom6,
                ));

                $this->outboundProcess($row, $feedOut, null, 1);

            }

        } catch (PDOException $e) {
            $this->logError('Unable to export queued records: ' . $e->getMessage());
            return;
        }

        fclose($file);
    }

    public function getOutboundQueue($idFeedOut)
    {

        $feed = $this->getOutboundFeed($idFeedOut);
        if (!$feed) {
            return;
        }

        try {

            if (!empty($feed->delay)) {
                if (!empty($feed->delayDump)) {
                    $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp <= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL ? MINUTE ),'%Y-%m-%d 23:59:59') LIMIT 500");
                    $query->execute(array($idFeedOut, $feed->delay));
                } else {
                    $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp < DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 500");
                    $query->execute(array($idFeedOut, $feed->delay));
                }
            } else {
                $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? LIMIT 500");
                $query->execute(array($idFeedOut));
            }
            return $query;

        } catch (PDOException $e) {
            $this->logError('Unable to get queued records: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function getOutboundQueuePreview($idFeedOut)
    {
        try {
            $sql = "SELECT LEFT(CONVERT_TZ(i.timestamp,?,?),10) AS date,COUNT(*) AS cnt ";
            $sql .= "FROM data_outbound o ";
            $sql .= "JOIN data_inbound i ON i.idRecord = o.idRecord ";
            $sql .= "WHERE idFeedOut = ? ";
            $sql .= "AND processed = 0 ";
            $sql .= "GROUP BY 1";
            $query = $this->db->prepare($sql);
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, $idFeedOut));
            return $query->fetchAll(\PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound queue preview: ' . $e->getMessage());
            return [];
        }
    }

    public function getOutboundQueueRecord($idFeedOut)
    {

        $feed = $this->getOutboundFeed($idFeedOut);
        if (!$feed) {
            return;
        }

        $lockName = 'READQUEUE_' . $idFeedOut;

        try {
            //$this->lockTables( "data_outbound WRITE, data_outbound o WRITE, data_inbound i READ" );
            $query = $this->db->prepare("SELECT GET_LOCK(?,-1);");
            $query->execute(array($lockName));
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to lock tables: ' . $pdoException->getMessage());
        }

        try {
            if (!empty($feed->delay)) {
                if (!empty($feed->delayDump)) {
                    $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp <= DATE_FORMAT(DATE_SUB(CONVERT_TZ(NOW(),?,?), INTERVAL ? MINUTE ),'%Y-%m-%d 23:59:59') LIMIT 500");
                    $query->execute(array($idFeedOut, DB_TIMEZONE, LOCAL_TIMEZONE, $feed->delay));
                } else {
                    $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? AND i.timestamp < DATE_SUB(NOW(), INTERVAL ? MINUTE) LIMIT 1");
                    $query->execute(array($idFeedOut, $feed->delay));
                }
            } else {
                $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? LIMIT 1");
                $query->execute(array($idFeedOut));
            }

            $rows = $query->fetchAll(PDO::FETCH_OBJ);
            if ($rows && is_array($rows)) {
                foreach ($rows as $row) {

                    try {
                        $status = $this->update('data_outbound',
                            array(
                                'processed' => -2,
                            ),
                            array(
                                'idRecord' => $row->idRecord,
                                'idFeedOut' => $idFeedOut,
                            )
                        );
                    } catch (PDOException $e) {
                        $pdoException = $e->getPrevious();
                        $this->logError('Unable to update outbound record: ' . $pdoException->getMessage());
                        $query = $this->db->prepare("SELECT RELEASE_LOCK(?);");
                        $query->execute(array($lockName));
                        //$this->unlockTables();
                        return null;
                    }

                }

                $query = $this->db->prepare("SELECT RELEASE_LOCK(?);");
                $query->execute(array($lockName));
                //$this->unlockTables();
                return $rows;
            }

        } catch (PDOException $e) {
            $this->logError('Unable to get pending outbound queue record: ' . $e->getMessage());
            return null;
        } finally {
            try {
                $query = $this->db->prepare("SELECT RELEASE_LOCK(?);");
                $query->execute(array($lockName));
                //$this->unlockTables();
            } catch (Leads_PDOException $e) {
                $pdoException = $e->getPrevious();
                $this->logError('Unable to unlock tables: ' . $pdoException->getMessage());
            }
        }

        return null;
    }

    public function checkOutboundRecordExists($idRecord, $idFeedIn, $idFeedOut)
    {
        try {
            $query = $this->db->prepare("SELECT 1 FROM data_outbound WHERE idRecord = ? AND idFeedIn = ? AND idFeedOut = ?");
            $query->execute(array($idRecord, $idFeedIn, $idFeedOut));
            if ($query && $query->fetchColumn()) {
                return true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound record exists results: ' . $e->getMessage());
            return null;
        }

        $date = new \DateTime();
        for ($i = 0; $i < 6; $i++) {
            try {
                $table = $this->quoteIdentifier('data_outbound_' . $date->format('Ym'));
                $query = $this->db->prepare("SELECT 1 FROM archive." . $table . " WHERE idRecord = ? AND idFeedIn = ? AND idFeedOut = ?");
                $query->execute(array($idRecord, $idFeedIn, $idFeedOut));
                if ($query && $query->fetchColumn()) {
                    return true;
                }
            } catch (PDOException $e) {
                $this->logError('Unable to get outbound record archive exists results: ' . $e->getMessage());
                return null;
            }
            $date->sub(new \DateInterval('P1M'));
        }

        return false;
    }

    public function getOutboundRecord($idRecord, $idFeedOut, $processed = null)
    {

        try {
            if (!empty($processed)) {
                $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = ? AND o.idRecord = ? AND o.idFeedOut = ?");
                $query->execute(array(intval($processed), $idRecord, $idFeedOut));
            } else {
                $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.idRecord = ? AND o.idFeedOut = ?");
                $query->execute(array($idRecord, $idFeedOut));
            }
            return $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound record: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    public function getInboundRecord($idRecord)
    {
        try {
            $query = $this->db->prepare("SELECT * FROM data_inbound WHERE idRecord = ?");
            $query->execute(array($idRecord));
            return $query->fetch(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get inbound record: ' . $e->getMessage());
            return null;
        }
    }

    public function exportRejected($idFeedOut)
    {

        $feedOut = $this->getOutboundFeed($idFeedOut);
        if (!$feedOut) {
            return;
        }

        $jobId = time();

        $fileLink = 'exports/' . $idFeedOut . "_" . $jobId . ".csv";
        $filePath = ADMIN_ROOT . $fileLink;
        $file = fopen($filePath, 'w');
        if (!$file) {
            return;
        }

        fputcsv($file, array(
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
            'leadId',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'custom5',
            'custom6',
        ));

        try {

            $query = $this->db->prepare("SELECT i.* FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord WHERE processed = 1 AND o.idFeedOut = ? AND o.accepted = 0");
            $query->execute();
            while ($row = $query->fetch(\PDO::FETCH_OBJ)) {
                fputcsv($file, array(
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
                    $row->dob,
                    $row->gender,
                    $row->landline,
                    $row->cellphone,
                    $row->leadId,
                    $row->custom1,
                    $row->custom2,
                    $row->custom3,
                    $row->custom4,
                    $row->custom5,
                    $row->custom6,
                ));


                $this->outboundProcess($row, $feedOut, null, 1);
                $q_query = $this->db->prepare("UPDATE feedout SET queued = queued + 1 WHERE idFeedOut = ?");
                $q_query->execute(array($idFeedOut));

            }

        } catch (PDOException $e) {
            $this->logError('Unable to get rejected records: ' . $e->getMessage());
            return;
        }

        fclose($file);
    }

    public function getOutboundTables()
    {
        try {
            $query = $this->db->prepare("SELECT label,successString,idFeedOut FROM feedout");
            $query->execute();
            return $query->fetchAll();
        } catch (PDOException $e) {
            $this->logError('Unable to get old legacy outbound tables: ' . $e->getMessage());
        }

        return null;
    }

    public function getOutboundDailyCount($idFeedOut)
    {
        $cnt = null;

        try {
            $query = $this->db->prepare("SELECT SUM(accepted) FROM stats_outbound WHERE idFeedOut = ? AND stamp = ?");
            $query->execute(array($idFeedOut, date('Y-m-d')));
            $cnt = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to check outbound daily count: ' . $e->getMessage());
            return $cnt;
        }

        return $cnt;
    }

    public function getInboundDailyCount($idFeedIn)
    {
        $cnt = null;

        try {
            $query = $this->db->prepare("SELECT SUM(accepted) FROM stats_inbound WHERE idFeedIn = ? AND stamp = ?");
            $query->execute(array($idFeedIn, date('Y-m-d')));
            $cnt = $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to check inbound daily count: ' . $e->getMessage());
            return $cnt;
        }

        return $cnt;
    }

    public function addSuppression($idCompany, $email)
    {
        try {
            $idSuppression = $this->insertRow('suppression', array(
                'idCompany' => $idCompany,
                'email' => $email,
            ));
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            if (strpos($pdoException->getMessage(), 'SQLSTATE[23000]:') !== false) {
                return null;
            } else {
                $this->logError('Unable to add suppression: ' . $pdoException->getMessage());
                return false;
            }
        }

        return true;
    }

    public function getSuppressionCounts()
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT s.idCompany,c.name,COUNT(*) AS cnt FROM suppression s LEFT JOIN companies c ON s.idCompany = c.idCompany GROUP BY s.idCompany");
            $query->execute(array());
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get suppression counts: ' . $e->getMessage());
        }

        return $results;
    }

    public function checkSuppression($email, $idCompany = null)
    {
        $result = false;

        if (empty($email) || strpos($email, '@') === false) {
            return $result;
        }

        list($local, $domain) = explode('@', $email, 2);

        try {
            if (!empty($idCompany)) {
                $query = $this->db->prepare("SELECT 1 FROM suppression WHERE ( email = ? OR email = ? ) AND ( idCompany = 0 OR idCompany = ? )");
                $query->execute(array($email, $domain, $idCompany));
            } else {
                $query = $this->db->prepare("SELECT 1 FROM suppression WHERE ( email = ? OR email = ? ) AND idCompany = 0");
                $query->execute(array($email, $domain));
            }

            if ('1' == $query->fetchColumn()) {
                $result = true;
            }
        } catch (PDOException $e) {
            $this->logError('Unable to check suppression: ' . $e->getMessage());
        }

        return $result;
    }

    public function exportSuppressions($idCompany)
    {
        $result = array();

        if (empty($idCompany)) {
            $result['file'] = 'exports/suppression_global_' . time() . '.csv';
        } else {
            $result['file'] = 'exports/suppression_' . intval($idCompany) . '_' . time() . '.csv';
        }
        $filePath = ADMIN_ROOT . $result['file'];
        $fh = fopen($filePath, 'w');
        if (!$fh) {
            $result['reason'] = 'Failed to create CSV file.';
            return $result;
        }

        try {
            if (empty($idCompany)) {
                $query = $this->db->prepare("SELECT email FROM suppression WHERE idCompany = 0");
                $query->execute(array());
            } else {
                $query = $this->db->prepare("SELECT email FROM suppression WHERE idCompany = ?");
                $query->execute(array($idCompany));
            }
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                fwrite($fh, $row['email'] . PHP_EOL);
            }
            $result['reason'] = 'Success';
        } catch (PDOException $e) {
            $result['reason'] = 'DB query error.';
            $this->logError('Unable to get get supression records for export: ' . $e->getMessage());
        }

        fclose($fh);
        return $result;
    }

    public function validateSuppressions()
    {

        try {
            $query = $this->db->prepare("SELECT email FROM suppression");
            $query->execute();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                if (strpos($row['email'], '@') !== false && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $delete = $this->db->prepare("DELETE FROM suppression WHERE email = ?");
                    $delete->execute(array($row['email']));
                    print $row['email'] . PHP_EOL;
                }
            }
        } catch (PDOException $e) {
            $result['reason'] = 'DB query error.';
            $this->logError('Unable to get get supression records for validation: ' . $e->getMessage());
        }
    }

    public function getOutboundStatsDetail($stamp)
    {
        $results = array();

        try {
            $query = $this->db->prepare("SELECT idFeedOut,url FROM stats_outbound WHERE stamp = ?");
            $query->execute(array($stamp));
            $results = $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get outbound stats details: ' . $e->getMessage());
        }

        return $results;
    }

    public function resetOutboundStats($idFeedOut, $url, $date)
    {
        if (empty($idFeedOut) || empty($date)) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $query = $this->db->prepare("SELECT SUM(IF(o.accepted = 1,1,0)) AS accepted,SUM(IF(o.accepted = 0,1,0)) AS rejected,SUM(o.isBillable) AS billable FROM data_outbound o INNER JOIN data_inbound i ON i.idRecord = o.idRecord INNER JOIN feedout f ON f.idFeedOut = o.idFeedOut WHERE o.idFeedOut = ? AND o.processed = 1 AND o.timestamp >= ? AND o.timestamp <= ? AND i.url = ?");
            $query->execute(array($idFeedOut, $date . ' 00:00:00', $date . ' 23:59:59', $url));
            $records = $query->fetchAll();

            foreach ($records as $record) {
                $query = $this->db->prepare("REPLACE INTO stats_outbound(idFeedOut,url,stamp,accepted,rejected,billable) VALUES(?,?,?,?,?,?)");
                $query->execute(array($idFeedOut, $url, $date, $record['accepted'], $record['rejected'], $record['billable']));
            }
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Unable to reset outbound stats: ' . $e->getMessage());
        }

        $this->db->commit();

    }

    public function resetQueuedStats()
    {
        try {
            $query = $this->db->query("SELECT idFeedOut FROM feedout");
            $feeds = $query->fetchAll(PDO::FETCH_OBJ);
            $query->closeCursor();

            foreach ($feeds as $feed) {
                print "Resetting queue stats for feed: {$feed->idFeedOut}\n";
                $this->lockTables("feedout WRITE, data_outbound AS o WRITE, data_inbound AS i READ");
                $query = $this->db->prepare("UPDATE feedout SET queued = ( SELECT COUNT(o.idRecord) AS cnt FROM data_outbound o JOIN data_inbound i ON i.idRecord = o.idRecord WHERE o.processed = 0 AND o.idFeedOut = ? ) WHERE idFeedOut = ?");
                $query->execute(array($feed->idFeedOut, $feed->idFeedOut));
                $this->unlockTables();
                sleep(2);
            }

        } catch (PDOException $e) {
            $this->logError('Unable to reset queued stats: ' . $e->getMessage());
        } catch (Leads_PDOException $e) {
            $pdoException = $e->getPrevious();
            $this->logError('Unable to lock/unlock tables: ' . $pdoException->getMessage());
        } finally {
            $this->unlockTables();
        }

        return null;
    }

    public function checkInboundFeedThresholds()
    {

        try {
            $sql = <<<'SQL'
SELECT i.*,c.*,IFNULL(SUM(si.accepted+si.rejected),0) AS leadsPassed,DATE_FORMAT(i.notifyThresholdTime,'%l:%i%p') AS notifyThresholdTimeFormatted
FROM feedinc AS i
LEFT JOIN companies c ON c.idCompany = i.idCompany
LEFT JOIN stats_inbound AS si ON si.idFeedIn = i.idFeedIn AND si.stamp = DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%Y-%m-%d')
WHERE i.status != 'retired'
AND i.notifyThresholdCount > 0
AND i.notifyThresholdCount IS NOT NULL
AND i.notifyThresholdTime IS NOT NULL
AND i.notifyThresholdTime <= DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%H:%i')
AND FIND_IN_SET(DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%w'),i.notifyThresholdDays)
AND ( i.notifyThresholdLastSent < DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%Y-%m-%d 00:00:00') OR i.notifyThresholdLastSent IS NULL )
GROUP BY i.idFeedIn
HAVING IFNULL(SUM(si.accepted+si.rejected),0) < i.notifyThresholdCount;
SQL;

            $query = $this->db->prepare($sql);
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE));
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to check inbound feed thresholds: ' . $e->getMessage());
            throw new Leads_PDOException('Unable to check inbound feed thresholds: ', null, $e);
        }
    }

    public function checkOutboundFeedThresholds()
    {

        try {
            $sql = <<<'SQL'
SELECT o.*,c.*,IFNULL(SUM(so.accepted+so.rejected),0) AS leadsPassed,DATE_FORMAT(o.notifyThresholdTime,'%l:%i%p') AS notifyThresholdTimeFormatted
FROM feedout AS o
LEFT JOIN companies c ON c.idCompany = o.idCompany
LEFT JOIN stats_outbound AS so ON so.idFeedOut = o.idFeedOut AND so.stamp = DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%Y-%m-%d')
WHERE o.status != 'retired'
AND o.cron = '1'
AND o.notifyThresholdCount > 0
AND o.notifyThresholdCount IS NOT NULL
AND o.notifyThresholdTime IS NOT NULL
AND o.notifyThresholdTime <= DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%H:%i')
AND FIND_IN_SET(DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%w'),o.notifyThresholdDays)
AND ( o.notifyThresholdLastSent < DATE_FORMAT(CONVERT_TZ(NOW(),?,?),'%Y-%m-%d 00:00:00') OR o.notifyThresholdLastSent IS NULL )
GROUP BY o.idFeedOut
HAVING IFNULL(SUM(so.accepted+so.rejected),0) < o.notifyThresholdCount;
SQL;

            $query = $this->db->prepare($sql);
            $query->execute(array(DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE, DB_TIMEZONE, LOCAL_TIMEZONE));
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to check outbound feed thresholds: ' . $e->getMessage());
            throw new Leads_PDOException('Unable to check outbound feed thresholds: ', null, $e);
        }
    }

    public function alterArchiveTables()
    {
        $cnt = 0;
        $recordId = 0;

        $startDate = new \DateTime('2017-09-01 00:00:00');
        $endDate = new \DateTime('2014-06-01 00:00:00');
        $tableDate = clone $startDate;

        try {

            while ($tableDate >= $endDate) {

                $table = $this->quoteIdentifier('data_inbound_' . $tableDate->format('Ym'));

                print date('c') . ' ' . $table . PHP_EOL;

                //$query = $this->db->prepare( "ALTER TABLE archive.{$table} ADD INDEX cellphone (cellphone) USING BTREE, ADD INDEX landline (landline) USING BTREE, ADD COLUMN custom1 VARCHAR(255), ADD COLUMN custom2 VARCHAR(255),ADD COLUMN custom3 VARCHAR(255),ADD COLUMN custom4 VARCHAR(255),ADD COLUMN custom5 VARCHAR(255),ADD COLUMN custom6 VARCHAR(255), ADD COLUMN leadId VARCHAR(255);" );
                $query = $this->db->prepare("ALTER TABLE archive.{$table} ADD customFields json NULL");
//                $query = $this->db->prepare("ALTER TABLE archive.{$table} ADD COLUMN accepted TINYINT UNSIGNED DEFAULT 1, ADD COLUMN isBillable TINYINT UNSIGNED DEFAULT 1, ADD COLUMN url VARCHAR(255)");
//                $query = $this->db->prepare( "ALTER TABLE archive.{$table} ADD COLUMN accepted TINYINT UNSIGNED DEFAULT 1" );
                $query->execute();

                $tableDate->sub(new \DateInterval(('P1M')));

            }

        } catch (PDOException $e) {
            $this->logError('Unable to archive inbound accepted: ' . $e->getMessage());
        }

        return $cnt;
    }

    public function logError($message, $db = false, $email = true)
    {

        $stamp = date('Y-m-d H:i:s');
        $errfile = fopen(SITE_ROOT . 'error' . DIRECTORY_SEPARATOR . 'leads-log', 'a');
        if ($errfile) {
            fwrite($errfile, $stamp . ' ' . $message . PHP_EOL);
            fwrite($errfile, $stamp . ' REQUEST: ' . print_r($_REQUEST, true) . PHP_EOL);
            fclose($errfile);
        }

        if ($db) {
            try {
                $this->insertRow('errorlog', array(
                    'origination' => 'LEADS',
                    'description' => $message,
                    'stamp' => date('Y-m-d H:i:s'),
                ), false);
            } catch (Leads_PDOException $e) {
                // Do nothing
            }
        }

        if ($email) {
            // Limit notification emails to one per minute to prevent flooding
            $time = @file_get_contents(SITE_ROOT . "error" . DIRECTORY_SEPARATOR . "email-stamp");
            if ($time === false || ($time < (time() - 60))) {
                file_put_contents(SITE_ROOT . "error" . DIRECTORY_SEPARATOR . "email-stamp", time());
            } else {
                return;
            }

            $from = 'lmsalerts@' . SITE_URL;
            $body = $stamp . ' ' . $message . PHP_EOL;
            $fromName = CONFIG_COMPANY_NAME . ' List Management System';
            $to = ADMINISTRATOR_EMAIL;
            $subject = 'List Management ERROR';
            $header = "From:" . $fromName . " <" . $from . ">\n";
            $header .= "Content-type: text/html; charset=iso-8859-1\n";
            $header .= "Reply-To: <" . $from . ">\n";
            $header .= "X-Sender: <" . $from . ">\n";
            $header .= "X-Mailer: PHP5\n";
            $header .= "X-Priority: 3\n";
            $header .= "Return-Path: <" . $from . ">\n";
            $sent = @mail($to, $subject, $body, $header, "-f {$from}");
        }
    }

    public function clearAllSessions()
    {
        try {
            $query = $this->db->prepare("TRUNCATE php_sessions");
            $query->execute();
        } catch (\PDOException $e) {
            $this->logError('Unable to truncate sessions: ' . $e->getMessage());
            return null;
        }

        return true;
    }

    public function getErrorCount()
    {
        try {
            $query = $this->db->prepare("SELECT COUNT(*) AS cnt FROM errorlog WHERE stamp LIKE ?");
            $query->execute(array(date('Y-m-d') . '%'));
            return $query->fetchColumn();
        } catch (PDOException $e) {
            $this->logError('Unable to get error count: ' . $e->getMessage());
        }

        return null;
    }

    public function getErrors()
    {
        try {
            $query = $this->db->prepare("SELECT * FROM errorlog WHERE stamp LIKE ? ORDER BY stamp DESC");
            $query->execute(array(date('Y-m-d') . '%'));
            return $query->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $e) {
            $this->logError('Unable to get error log: ' . $e->getMessage());
        }

        return null;
    }
}
