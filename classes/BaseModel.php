<?php
/**
 * Base Model Class
 * Abstract class that provides common database operations for all models
 */

require_once __DIR__ . '/Database.php';

abstract class BaseModel {
    public $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Find a record by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? AND deleted_at IS NULL";
        return $this->db->selectOne($sql, [$id], ['i']);
    }
    
    /**
     * Get all records
     * 
     * @param array $conditions
     * @param string $orderBy
     * @param int $limit
     * @return array
     */
    public function all($conditions = [], $orderBy = null, $limit = null) {
        $sql = "SELECT * FROM {$this->table} WHERE deleted_at IS NULL";
        $params = [];
        $types = [];
        
        foreach ($conditions as $field => $value) {
            $sql .= " AND {$field} = ?";
            $params[] = $value;
            $types[] = is_int($value) ? 'i' : 's';
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        return $this->db->select($sql, $params, $types);
    }
    
    /**
     * Create a new record
     * 
     * @param array $data
     * @return int Insert ID
     */
    public function create($data) {
        $fields = [];
        $placeholders = [];
        $values = [];
        $types = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $this->fillable)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $value;
                $types[] = $this->getParameterType($value);
            }
        }
        
        if ($this->timestamps) {
            $fields[] = 'created_at';
            $fields[] = 'updated_at';
            $placeholders[] = 'CURRENT_TIMESTAMP';
            $placeholders[] = 'CURRENT_TIMESTAMP';
        }
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        return $this->db->insert($sql, $values, $types);
    }
    
    /**
     * Update a record
     * 
     * @param int $id
     * @param array $data
     * @return int Affected rows
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        $types = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $this->fillable)) {
                $fields[] = "{$field} = ?";
                $values[] = $value;
                $types[] = $this->getParameterType($value);
            }
        }
        
        if ($this->timestamps) {
            $fields[] = "updated_at = CURRENT_TIMESTAMP";
        }
        
        $values[] = $id;
        $types[] = 'i';
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
        
        return $this->db->update($sql, $values, $types);
    }
    
    /**
     * Soft delete a record
     * 
     * @param int $id
     * @return int Affected rows
     */
    public function delete($id) {
        $sql = "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP WHERE {$this->primaryKey} = ?";
        return $this->db->update($sql, [$id], ['i']);
    }
    
    /**
     * Hard delete a record
     * 
     * @param int $id
     * @return int Affected rows
     */
    public function forceDelete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->delete($sql, [$id], ['i']);
    }
    
    /**
     * Check if record exists
     * 
     * @param array $conditions
     * @return bool
     */
    public function exists($conditions) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE deleted_at IS NULL";
        $params = [];
        $types = [];
        
        foreach ($conditions as $field => $value) {
            $sql .= " AND {$field} = ?";
            $params[] = $value;
            $types[] = $this->getParameterType($value);
        }
        
        $result = $this->db->selectOne($sql, $params, $types);
        return $result['count'] > 0;
    }
    
    /**
     * Get parameter type for prepared statement
     * 
     * @param mixed $value
     * @return string
     */
    protected function getParameterType($value) {
        if (is_int($value)) {
            return 'i';
        } elseif (is_float($value)) {
            return 'd';
        } else {
            return 's';
        }
    }
    
    /**
     * Execute raw SQL query
     * 
     * @param string $sql
     * @param array $params
     * @param array $types
     * @return mixed
     */
    public function raw($sql, $params = [], $types = []) {
        if (stripos($sql, 'SELECT') === 0) {
            return $this->db->select($sql, $params, $types);
        } elseif (stripos($sql, 'INSERT') === 0) {
            return $this->db->insert($sql, $params, $types);
        } else {
            return $this->db->update($sql, $params, $types);
        }
    }
    
    /**
     * Get the database instance
     * 
     * @return Database
     */
    public function getDb() {
        return $this->db;
    }
}