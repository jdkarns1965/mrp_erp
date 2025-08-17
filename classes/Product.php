<?php
/**
 * Product Model Class
 * Handles all product-related database operations
 */

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel {
    protected $table = 'products';
    protected $fillable = [
        'product_code',
        'name',
        'description',
        'category_id',
        'uom_id',
        'weight_kg',
        'cycle_time_seconds',
        'cavity_count',
        'min_stock_qty',
        'max_stock_qty',
        'safety_stock_qty',
        'standard_cost',
        'selling_price',
        'is_lot_controlled',
        'is_active'
    ];
    
    /**
     * Get product with category and UOM details
     * 
     * @param int $id
     * @return array|null
     */
    public function findWithDetails($id) {
        $sql = "SELECT p.*, 
                       pc.name as category_name,
                       uom.code as uom_code,
                       uom.description as uom_description
                FROM products p
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                WHERE p.id = ? AND p.deleted_at IS NULL";
        
        return $this->db->selectOne($sql, [$id], ['i']);
    }
    
    /**
     * Get all active products with details
     * 
     * @return array
     */
    public function getAllActive() {
        $sql = "SELECT p.*, 
                       pc.name as category_name,
                       uom.code as uom_code,
                       COALESCE(inv.available_quantity, 0) as current_stock,
                       (SELECT COUNT(*) FROM bom_headers bh 
                        WHERE bh.product_id = p.id) as bom_count,
                       (SELECT bh.id FROM bom_headers bh 
                        WHERE bh.product_id = p.id 
                        AND bh.is_active = 1
                        LIMIT 1) as active_bom_id
                FROM products p
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                LEFT JOIN v_current_inventory inv ON inv.item_type = 'product' AND inv.item_id = p.id
                WHERE p.is_active = 1 AND p.deleted_at IS NULL
                ORDER BY p.product_code";
        
        return $this->db->select($sql);
    }
    
    /**
     * Check if product code exists
     * 
     * @param string $code
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists($code, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM products WHERE product_code = ? AND deleted_at IS NULL";
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
     * Get current stock level for a product
     * 
     * @param int $productId
     * @return float
     */
    public function getCurrentStock($productId) {
        $sql = "SELECT COALESCE(SUM(quantity - reserved_quantity), 0) as available_stock
                FROM inventory
                WHERE item_type = 'product' 
                  AND item_id = ?
                  AND status = 'available'";
        
        $result = $this->db->selectOne($sql, [$productId], ['i']);
        return (float)$result['available_stock'];
    }
    
    /**
     * Get products below safety stock
     * 
     * @return array
     */
    public function getBelowSafetyStock() {
        $sql = "SELECT p.*, 
                       COALESCE(inv.available_quantity, 0) as current_stock,
                       p.safety_stock_qty,
                       (p.safety_stock_qty - COALESCE(inv.available_quantity, 0)) as shortage_qty,
                       uom.code as uom_code
                FROM products p
                LEFT JOIN v_current_inventory inv ON inv.item_type = 'product' AND inv.item_id = p.id
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                WHERE p.is_active = 1 
                  AND p.deleted_at IS NULL
                  AND p.safety_stock_qty > 0
                  AND COALESCE(inv.available_quantity, 0) < p.safety_stock_qty
                ORDER BY shortage_qty DESC";
        
        return $this->db->select($sql);
    }
    
    /**
     * Calculate production capacity per day
     * 
     * @param int $productId
     * @param int $availableHours
     * @return float
     */
    public function calculateDailyCapacity($productId, $availableHours = 8) {
        $product = $this->find($productId);
        if (!$product || !$product['cycle_time_seconds']) {
            return 0;
        }
        
        $secondsPerDay = $availableHours * 3600;
        $cyclesPerDay = $secondsPerDay / $product['cycle_time_seconds'];
        $unitsPerDay = $cyclesPerDay * $product['cavity_count'];
        
        return floor($unitsPerDay);
    }
    
    /**
     * Get product cost breakdown (materials cost from BOM)
     * 
     * @param int $productId
     * @return array
     */
    public function getCostBreakdown($productId) {
        $sql = "SELECT 
                    m.material_code,
                    m.name as material_name,
                    bd.quantity_per,
                    uom.code as uom_code,
                    m.cost_per_unit,
                    (bd.quantity_per * m.cost_per_unit * (1 + bd.scrap_percentage/100)) as total_cost
                FROM bom_headers bh
                JOIN bom_details bd ON bh.id = bd.bom_header_id
                JOIN materials m ON bd.material_id = m.id
                LEFT JOIN units_of_measure uom ON bd.uom_id = uom.id
                WHERE bh.product_id = ?
                  AND bh.is_active = 1
                  AND (bh.effective_date <= CURRENT_DATE)
                  AND (bh.expiry_date IS NULL OR bh.expiry_date >= CURRENT_DATE)
                ORDER BY total_cost DESC";
        
        return $this->db->select($sql, [$productId], ['i']);
    }
    
    /**
     * Search products by code or name
     * 
     * @param string $searchTerm
     * @return array
     */
    public function search($searchTerm) {
        $searchTerm = '%' . $searchTerm . '%';
        $sql = "SELECT p.*, uom.code as uom_code
                FROM products p
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                WHERE (p.product_code LIKE ? OR p.name LIKE ?)
                  AND p.is_active = 1 
                  AND p.deleted_at IS NULL
                ORDER BY p.product_code
                LIMIT 50";
        
        return $this->db->select($sql, [$searchTerm, $searchTerm], ['s', 's']);
    }
    
    /**
     * Get products by category
     * 
     * @param int $categoryId
     * @return array
     */
    public function getByCategory($categoryId) {
        $sql = "SELECT p.*, uom.code as uom_code
                FROM products p
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                WHERE p.category_id = ?
                  AND p.is_active = 1 
                  AND p.deleted_at IS NULL
                ORDER BY p.product_code";
        
        return $this->db->select($sql, [$categoryId], ['i']);
    }
    
    /**
     * Update product costs based on BOM
     * 
     * @param int $productId
     * @return bool
     */
    public function updateStandardCost($productId) {
        $costBreakdown = $this->getCostBreakdown($productId);
        $totalMaterialCost = 0;
        
        foreach ($costBreakdown as $item) {
            $totalMaterialCost += $item['total_cost'];
        }
        
        $sql = "UPDATE products SET standard_cost = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $result = $this->db->update($sql, [$totalMaterialCost, $productId], ['d', 'i']);
        return $result > 0;
    }
}