<?php
/**
 * Database Connection Class
 * Singleton pattern for database connection management
 */

class Database {
    private static $instance = null;
    private $connection;
    private $config;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->config = require dirname(__DIR__) . '/config/database.php';
        $this->connect();
    }
    
    /**
     * Get the singleton instance
     * 
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
     * 
     * @throws Exception if connection fails
     */
    private function connect() {
        mysqli_report($this->config['options']['error_mode']);
        
        try {
            $this->connection = new mysqli(
                $this->config['host'],
                $this->config['username'],
                $this->config['password'],
                $this->config['database'],
                $this->config['port']
            );
            
            if ($this->connection->connect_error) {
                throw new Exception('Database connection failed: ' . $this->connection->connect_error);
            }
            
            $this->connection->set_charset($this->config['charset']);
            
        } catch (mysqli_sql_exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get the database connection
     * 
     * @return mysqli
     */
    public function getConnection() {
        if (!$this->connection || $this->connection->ping() === false) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Execute a SELECT query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return array
     */
    public function select($sql, $params = [], $types = []) {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }
    
    /**
     * Execute a single row SELECT query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return array|null
     */
    public function selectOne($sql, $params = [], $types = []) {
        $result = $this->select($sql, $params, $types);
        return $result ? $result[0] : null;
    }
    
    /**
     * Execute INSERT query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return int Last insert ID
     */
    public function insert($sql, $params = [], $types = []) {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $insertId = $this->connection->insert_id;
        $stmt->close();
        return $insertId;
    }
    
    /**
     * Execute UPDATE query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return int Affected rows
     */
    public function update($sql, $params = [], $types = []) {
        $stmt = $this->prepare($sql, $params, $types);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
    
    /**
     * Execute DELETE query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return int Affected rows
     */
    public function delete($sql, $params = [], $types = []) {
        return $this->update($sql, $params, $types);
    }
    
    /**
     * Prepare a statement with parameters
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return mysqli_stmt
     */
    private function prepare($sql, $params = [], $types = []) {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        if (!empty($params)) {
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            } else {
                $types = implode('', $types);
            }
            
            $stmt->bind_param($types, ...$params);
        }
        
        return $stmt;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        $this->getConnection()->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        $this->getConnection()->rollback();
    }
    
    /**
     * Escape string for safe SQL usage
     * 
     * @param string $string
     * @return string
     */
    public function escape($string) {
        return $this->getConnection()->real_escape_string($string);
    }
    
    /**
     * Get last error
     * 
     * @return string
     */
    public function getLastError() {
        return $this->getConnection()->error;
    }
    
    /**
     * Close the connection
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}