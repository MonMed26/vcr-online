<?php
/**
 * Database Class for WiFi Voucher System
 * Handles database connection and CRUD operations using MySQLi
 */

class Database {
    private $connection;
    private static $instance = null;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Get singleton instance
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     * @throws Exception
     */
    private function connect() {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );

            $this->connection->set_charset(DB_CHARSET);

        } catch (mysqli_sql_exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Get database connection
     * @return mysqli
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Begin transaction
     * @return bool
     */
    public function beginTransaction() {
        return $this->connection->begin_transaction();
    }

    /**
     * Commit transaction
     * @return bool
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     * @return bool
     */
    public function rollback() {
        return $this->connection->rollback();
    }

    /**
     * Execute query with parameters
     * @param string $query
     * @param array $params
     * @param string $types
     * @return mysqli_stmt
     * @throws Exception
     */
    public function query($query, $params = [], $types = '') {
        try {
            $stmt = $this->connection->prepare($query);

            if ($stmt === false) {
                throw new Exception('Query preparation failed: ' . $this->connection->error);
            }

            if (!empty($params)) {
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }

                $stmt->bind_param($types, ...$params);
            }

            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }

            return $stmt;

        } catch (mysqli_sql_exception $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }

    /**
     * Get single row from query
     * @param string $query
     * @param array $params
     * @param string $types
     * @return array|null
     */
    public function fetchOne($query, $params = [], $types = '') {
        $stmt = $this->query($query, $params, $types);
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    /**
     * Get multiple rows from query
     * @param string $query
     * @param array $params
     * @param string $types
     * @return array
     */
    public function fetchAll($query, $params = [], $types = '') {
        $stmt = $this->query($query, $params, $types);
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Insert record and return ID
     * @param string $table
     * @param array $data
     * @return int
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $values = array_values($data);
        $types = $this->getParamTypes($values);

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->query($query, $values, $types);
        return $stmt->insert_id;
    }

    /**
     * Update record
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $whereParams
     * @return int
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        $values = [];

        foreach ($data as $column => $value) {
            $setClause[] = "$column = ?";
            $values[] = $value;
        }

        $values = array_merge($values, $whereParams);
        $types = $this->getParamTypes($values);

        $query = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setClause),
            $where
        );

        $stmt = $this->query($query, $values, $types);
        return $stmt->affected_rows;
    }

    /**
     * Delete record
     * @param string $table
     * @param string $where
     * @param array $params
     * @return int
     */
    public function delete($table, $where, $params = []) {
        $query = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $stmt = $this->query($query, $params);
        return $stmt->affected_rows;
    }

    /**
     * Get parameter types for mysqli bind_param
     * @param array $params
     * @return string
     */
    private function getParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    /**
     * Escape string for safe SQL
     * @param string $string
     * @return string
     */
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    /**
     * Get last insert ID
     * @return int
     */
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    /**
     * Get number of affected rows
     * @return int
     */
    public function affectedRows() {
        return $this->connection->affected_rows;
    }

    /**
     * Check if table exists
     * @param string $table
     * @return bool
     */
    public function tableExists($table) {
        $query = "SHOW TABLES LIKE ?";
        $result = $this->fetchOne($query, [$table]);
        return !empty($result);
    }

    /**
     * Get table structure
     * @param string $table
     * @return array
     */
    public function getTableStructure($table) {
        $query = "DESCRIBE `$table`";
        return $this->fetchAll($query);
    }

    /**
     * Execute raw SQL (for migrations, setup, etc.)
     * @param string $sql
     * @return bool
     * @throws Exception
     */
    public function executeRaw($sql) {
        try {
            return $this->connection->multi_query($sql);
        } catch (mysqli_sql_exception $e) {
            throw new Exception('Raw SQL execution failed: ' . $e->getMessage());
        }
    }

    /**
     * Get MySQL server version
     * @return string
     */
    public function getServerVersion() {
        return $this->connection->server_info;
    }

    /**
     * Check connection status
     * @return bool
     */
    public function isConnected() {
        return $this->connection && $this->connection->ping();
    }

    /**
     * Reconnect to database
     */
    public function reconnect() {
        if ($this->connection) {
            $this->connection->close();
        }
        $this->connect();
    }

    /**
     * Close database connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Destructor - close connection
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

// Database helper functions
if (!function_exists('db')) {
    /**
     * Get database instance
     * @return Database
     */
    function db() {
        return Database::getInstance();
    }
}

if (!function_exists('db_query')) {
    /**
     * Execute database query
     * @param string $query
     * @param array $params
     * @param string $types
     * @return mysqli_stmt
     */
    function db_query($query, $params = [], $types = '') {
        return db()->query($query, $params, $types);
    }
}

if (!function_exists('db_fetch_one')) {
    /**
     * Fetch single row
     * @param string $query
     * @param array $params
     * @param string $types
     * @return array|null
     */
    function db_fetch_one($query, $params = [], $types = '') {
        return db()->fetchOne($query, $params, $types);
    }
}

if (!function_exists('db_fetch_all')) {
    /**
     * Fetch multiple rows
     * @param string $query
     * @param array $params
     * @param string $types
     * @return array
     */
    function db_fetch_all($query, $params = [], $types = '') {
        return db()->fetchAll($query, $params, $types);
    }
}

?>