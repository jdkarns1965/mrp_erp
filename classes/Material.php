<?php
/**
 * Material Model Class
 * Handles all material-related database operations
 */

require_once __DIR__ . '/BaseModel.php';

class Material extends BaseModel {
    protected $table = 'materials';
    protected $fillable = [
        'material_code',
        'name',
        'description',
        'category_id',
        'material_type',
        'uom_id',
        'min_stock_qty',
        'max_stock_qty',
        'reorder_point',
        'lead_time_days',
        'default_supplier_id',
        'cost_per_unit',
        'supplier_moq',
        'is_lot_controlled',
        'is_active'
    ];
    
    /**
     * Get material with category and UOM details
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithDetails($id) {
        $sql = "SELECT m.*, 
                       mc.name as category_name,
                       uom.code as uom_code,
                       uom.description as uom_description,
                       s.name as supplier_name,
                       m.supplier_moq as reorder_quantity,
                       m.reorder_point,
                       m.lead_time_days,
                       m.cost_per_unit
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                WHERE m.id = ? AND m.deleted_at IS NULL";
        
        return $this->db->selectOne($sql, [$id], ['i']);
    }
    
    /**
     * Get all active materials with details
     * 
     * @return array
     */
    public function getAllActive() {
        $sql = "SELECT m.*, 
                       mc.name as category_name,
                       uom.code as uom_code,
                       s.name as supplier_name
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                WHERE m.is_active = 1 AND m.deleted_at IS NULL
                ORDER BY m.material_code";
        
        return $this->db->select($sql);
    }
    
    /**
     * Get all materials (active and inactive) with details
     * 
     * @return array
     */
    public function getAll() {
        $sql = "SELECT m.*, 
                       mc.name as category_name,
                       uom.code as uom_code,
                       s.name as supplier_name
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                WHERE m.deleted_at IS NULL
                ORDER BY m.is_active DESC, m.material_code";
        
        return $this->db->select($sql);
    }
    
    /**
     * Get materials by type
     * 
     * @param string $type
     * @return array
     */
    public function getByType($type) {
        $sql = "SELECT m.*, uom.code as uom_code
                FROM materials m
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                WHERE m.material_type = ? AND m.is_active = 1 AND m.deleted_at IS NULL
                ORDER BY m.name";
        
        return $this->db->select($sql, [$type], ['s']);
    }
    
    /**
     * Get materials below reorder point
     * 
     * @return array
     */
    public function getBelowReorderPoint() {
        $sql = "SELECT m.*, 
                       COALESCE(inv.available_quantity, 0) as current_stock,
                       m.reorder_point,
                       (m.reorder_point - COALESCE(inv.available_quantity, 0)) as shortage_qty,
                       uom.code as uom_code,
                       s.name as supplier_name
                FROM materials m
                LEFT JOIN v_current_inventory inv ON inv.item_type = 'material' AND inv.item_id = m.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                WHERE m.is_active = 1 
                  AND m.deleted_at IS NULL
                  AND m.reorder_point > 0
                  AND COALESCE(inv.available_quantity, 0) < m.reorder_point
                ORDER BY shortage_qty DESC";
        
        return $this->db->select($sql);
    }
    
    /**
     * Check if material code exists
     * 
     * @param string $code
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists($code, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM materials WHERE material_code = ? AND deleted_at IS NULL";
        $params = [$code];
        $types = ['s'];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types[] = 'i';
        }
        
        $result = $this->db->selectOne($sql, $params, $types);
        return $result['count'] > 0;
    }
    
    /**
     * Get current stock level for a material
     * 
     * @param int $materialId
     * @return float
     */
    public function getCurrentStock($materialId) {
        $sql = "SELECT COALESCE(SUM(quantity - reserved_quantity), 0) as available_stock
                FROM inventory
                WHERE item_type = 'material' 
                  AND item_id = ?
                  AND status = 'available'";
        
        $result = $this->db->selectOne($sql, [$materialId], ['i']);
        return (float)$result['available_stock'];
    }
    
    /**
     * Get material cost history
     * 
     * @param int $materialId
     * @param int $days
     * @return array
     */
    public function getCostHistory($materialId, $days = 90) {
        $sql = "SELECT 
                    DATE(received_date) as date,
                    AVG(unit_cost) as avg_cost,
                    MIN(unit_cost) as min_cost,
                    MAX(unit_cost) as max_cost,
                    COUNT(*) as receipt_count
                FROM inventory
                WHERE item_type = 'material' 
                  AND item_id = ?
                  AND unit_cost > 0
                  AND received_date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
                GROUP BY DATE(received_date)
                ORDER BY date DESC";
        
        return $this->db->select($sql, [$materialId, $days], ['i', 'i']);
    }
    
    /**
     * Search materials by code or name
     * 
     * @param string $searchTerm
     * @param bool $includeInactive
     * @return array
     */
    public function search($searchTerm, $includeInactive = false) {
        $searchTerm = '%' . $searchTerm . '%';
        $sql = "SELECT m.*, 
                       mc.name as category_name,
                       uom.code as uom_code,
                       s.name as supplier_name
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                WHERE (m.material_code LIKE ? OR m.name LIKE ?)
                  AND m.deleted_at IS NULL";
        
        if (!$includeInactive) {
            $sql .= " AND m.is_active = 1";
        }
        
        $sql .= " ORDER BY m.is_active DESC, m.material_code LIMIT 50";
        
        return $this->db->select($sql, [$searchTerm, $searchTerm], ['s', 's']);
    }
    
    /**
     * Update material cost
     * 
     * @param int $materialId
     * @param float $newCost
     * @return bool
     */
    public function updateCost($materialId, $newCost) {
        $sql = "UPDATE materials SET cost_per_unit = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->update($sql, [$newCost, $materialId], ['d', 'i']);
        return $result > 0;
    }
}