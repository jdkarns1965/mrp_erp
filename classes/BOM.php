<?php
/**
 * BOM (Bill of Materials) Model Class
 * Handles BOM headers and details operations
 */

require_once __DIR__ . '/BaseModel.php';

class BOM extends BaseModel {
    protected $table = 'bom_headers';
    protected $fillable = [
        'product_id',
        'version',
        'description',
        'effective_date',
        'expiry_date',
        'is_active',
        'approved_by',
        'approved_date'
    ];
    
    /**
     * Get active BOM for a product
     * 
     * @param int $productId
     * @return array|null
     */
    public function getActiveBOM($productId) {
        $sql = "SELECT bh.*, p.product_code, p.name as product_name
                FROM bom_headers bh
                JOIN products p ON bh.product_id = p.id
                WHERE bh.product_id = ?
                  AND bh.is_active = 1
                  AND bh.effective_date <= CURRENT_DATE
                  AND (bh.expiry_date IS NULL OR bh.expiry_date >= CURRENT_DATE)
                ORDER BY bh.version DESC
                LIMIT 1";
        
        return $this->db->selectOne($sql, [$productId], ['i']);
    }
    
    /**
     * Get BOM details with material information
     * 
     * @param int $bomHeaderId
     * @return array
     */
    public function getBOMDetails($bomHeaderId) {
        $sql = "SELECT 
                    bd.*,
                    m.material_code,
                    m.name as material_name,
                    m.material_type,
                    m.cost_per_unit,
                    uom.code as uom_code,
                    (bd.quantity_per * m.cost_per_unit * (1 + bd.scrap_percentage/100)) as extended_cost
                FROM bom_details bd
                JOIN materials m ON bd.material_id = m.id
                LEFT JOIN units_of_measure uom ON bd.uom_id = uom.id
                WHERE bd.bom_header_id = ?
                ORDER BY extended_cost DESC";
        
        return $this->db->select($sql, [$bomHeaderId], ['i']);
    }
    
    /**
     * Create new BOM with details
     * 
     * @param array $headerData
     * @param array $details
     * @return int|false BOM header ID or false on failure
     */
    public function createBOMWithDetails($headerData, $details) {
        $this->db->beginTransaction();
        
        try {
            // Deactivate existing active BOMs for this product
            if (isset($headerData['is_active']) && $headerData['is_active']) {
                $sql = "UPDATE bom_headers SET is_active = 0 WHERE product_id = ?";
                $this->db->update($sql, [$headerData['product_id']], ['i']);
            }
            
            // Create BOM header
            $bomHeaderId = $this->create($headerData);
            
            // Add BOM details
            foreach ($details as $detail) {
                $this->addBOMDetail($bomHeaderId, $detail);
            }
            
            $this->db->commit();
            return $bomHeaderId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Add detail to BOM
     * 
     * @param int|array $bomHeaderIdOrDetail If array, assumes full detail data including bom_header_id
     * @param array $detail Optional detail data if first param is bomHeaderId
     * @return int
     */
    public function addBOMDetail($bomHeaderIdOrDetail, $detail = null) {
        // Handle both calling patterns for backward compatibility
        if (is_array($bomHeaderIdOrDetail)) {
            $detail = $bomHeaderIdOrDetail;
            $bomHeaderId = $detail['bom_header_id'];
        } else {
            $bomHeaderId = $bomHeaderIdOrDetail;
        }
        
        $sql = "INSERT INTO bom_details 
                (bom_header_id, material_id, quantity_per, uom_id, scrap_percentage, notes)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $params = [
            $bomHeaderId,
            $detail['material_id'],
            $detail['quantity_per'],
            $detail['uom_id'],
            $detail['scrap_percentage'] ?? 0,
            $detail['notes'] ?? null
        ];
        
        $types = ['i', 'i', 'd', 'i', 'd', 's'];
        
        return $this->db->insert($sql, $params, $types);
    }
    
    /**
     * Update BOM header
     * 
     * @param int $bomHeaderId
     * @param array $data
     * @return int
     */
    public function updateBOMHeader($bomHeaderId, $data) {
        return $this->update($bomHeaderId, $data);
    }
    
    /**
     * Update BOM detail
     * 
     * @param int $detailId
     * @param array $data
     * @return int
     */
    public function updateBOMDetail($detailId, $data) {
        $sql = "UPDATE bom_details 
                SET quantity_per = ?, scrap_percentage = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?";
        
        $params = [
            $data['quantity_per'],
            $data['scrap_percentage'] ?? 0,
            $data['notes'] ?? null,
            $detailId
        ];
        
        $types = ['d', 'd', 's', 'i'];
        
        return $this->db->update($sql, $params, $types);
    }
    
    /**
     * Delete BOM detail
     * 
     * @param int $detailId
     * @return int
     */
    public function deleteBOMDetail($detailId) {
        $sql = "DELETE FROM bom_details WHERE id = ?";
        return $this->db->delete($sql, [$detailId], ['i']);
    }
    
    /**
     * Copy BOM to create new version
     * 
     * @param int $sourceBomId
     * @param string $newVersion
     * @param string $effectiveDate
     * @return int|false
     */
    public function copyBOM($sourceBomId, $newVersion, $effectiveDate) {
        $this->db->beginTransaction();
        
        try {
            // Get source BOM header
            $sourceBOM = $this->find($sourceBomId);
            if (!$sourceBOM) {
                throw new Exception("Source BOM not found");
            }
            
            // Create new BOM header
            $newBOM = [
                'product_id' => $sourceBOM['product_id'],
                'version' => $newVersion,
                'description' => $sourceBOM['description'] . ' (Copied from v' . $sourceBOM['version'] . ')',
                'effective_date' => $effectiveDate,
                'is_active' => false
            ];
            
            $newBomId = $this->create($newBOM);
            
            // Copy BOM details
            $sql = "INSERT INTO bom_details 
                    (bom_header_id, material_id, quantity_per, uom_id, scrap_percentage, notes)
                    SELECT ?, material_id, quantity_per, uom_id, scrap_percentage, notes
                    FROM bom_details
                    WHERE bom_header_id = ?";
            
            $this->db->insert($sql, [$newBomId, $sourceBomId], ['i', 'i']);
            
            $this->db->commit();
            return $newBomId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Get active BOM headers by product
     * 
     * @param int $productId
     * @return array
     */
    public function getActiveByProduct($productId) {
        $sql = "SELECT * FROM bom_headers 
                WHERE product_id = ? 
                  AND is_active = 1 
                  AND effective_date <= CURRENT_DATE
                  AND (expiry_date IS NULL OR expiry_date >= CURRENT_DATE)
                ORDER BY version DESC";
        
        return $this->db->select($sql, [$productId], ['i']);
    }
    
    /**
     * Get BOM details by header ID
     * 
     * @param int $headerId
     * @return array
     */
    public function getDetailsByHeaderId($headerId) {
        $sql = "SELECT bd.*, 
                       m.material_code,
                       m.name as material_name,
                       uom.code as uom_code
                FROM bom_details bd
                JOIN materials m ON bd.material_id = m.id
                LEFT JOIN units_of_measure uom ON bd.uom_id = uom.id
                WHERE bd.bom_header_id = ?
                ORDER BY m.material_code";
        
        return $this->db->select($sql, [$headerId], ['i']);
    }
    
    /**
     * Explode BOM to get all materials needed
     * 
     * @param int $productId
     * @param float $quantity
     * @return array
     */
    public function explodeBOM($productId, $quantity) {
        $activeBOM = $this->getActiveBOM($productId);
        if (!$activeBOM) {
            return [];
        }
        
        $sql = "SELECT 
                    bd.material_id,
                    m.material_code,
                    m.name as material_name,
                    m.material_type,
                    bd.quantity_per,
                    bd.scrap_percentage,
                    uom.code as uom_code,
                    (bd.quantity_per * ? * (1 + bd.scrap_percentage/100)) as total_required,
                    m.cost_per_unit,
                    (bd.quantity_per * ? * (1 + bd.scrap_percentage/100) * m.cost_per_unit) as total_cost
                FROM bom_details bd
                JOIN materials m ON bd.material_id = m.id
                LEFT JOIN units_of_measure uom ON bd.uom_id = uom.id
                WHERE bd.bom_header_id = ?
                ORDER BY m.material_code";
        
        return $this->db->select($sql, [$quantity, $quantity, $activeBOM['id']], ['d', 'd', 'i']);
    }
    
    /**
     * Check where material is used (reverse BOM lookup)
     * 
     * @param int $materialId
     * @return array
     */
    public function whereUsed($materialId) {
        $sql = "SELECT 
                    p.id as product_id,
                    p.product_code,
                    p.name as product_name,
                    bh.version as bom_version,
                    bd.quantity_per,
                    uom.code as uom_code,
                    bh.is_active as bom_active
                FROM bom_details bd
                JOIN bom_headers bh ON bd.bom_header_id = bh.id
                JOIN products p ON bh.product_id = p.id
                LEFT JOIN units_of_measure uom ON bd.uom_id = uom.id
                WHERE bd.material_id = ?
                  AND p.deleted_at IS NULL
                ORDER BY p.product_code, bh.version";
        
        return $this->db->select($sql, [$materialId], ['i']);
    }
    
    /**
     * Get products that use a specific material
     * 
     * @param int $materialId
     * @return array
     */
    public function getProductsUsingMaterial($materialId) {
        $sql = "SELECT DISTINCT
                    p.id as product_id,
                    p.product_code,
                    p.name as product_name,
                    bd.quantity_per as quantity_required,
                    bh.version as bom_version,
                    bh.is_active
                FROM bom_details bd
                JOIN bom_headers bh ON bd.bom_header_id = bh.id
                JOIN products p ON bh.product_id = p.id
                WHERE bd.material_id = ?
                  AND bh.is_active = 1
                  AND p.deleted_at IS NULL
                ORDER BY p.product_code";
        
        return $this->db->select($sql, [$materialId], ['i']);
    }
    
    /**
     * Validate BOM (check for circular references, missing materials, etc.)
     * 
     * @param int $bomHeaderId
     * @return array Validation results
     */
    public function validateBOM($bomHeaderId) {
        $errors = [];
        $warnings = [];
        
        // Check for duplicate materials
        $sql = "SELECT material_id, COUNT(*) as count
                FROM bom_details
                WHERE bom_header_id = ?
                GROUP BY material_id
                HAVING count > 1";
        
        $duplicates = $this->db->select($sql, [$bomHeaderId], ['i']);
        if (!empty($duplicates)) {
            $errors[] = "BOM contains duplicate materials";
        }
        
        // Check for inactive materials
        $sql = "SELECT m.material_code, m.name
                FROM bom_details bd
                JOIN materials m ON bd.material_id = m.id
                WHERE bd.bom_header_id = ?
                  AND (m.is_active = 0 OR m.deleted_at IS NOT NULL)";
        
        $inactiveMaterials = $this->db->select($sql, [$bomHeaderId], ['i']);
        if (!empty($inactiveMaterials)) {
            foreach ($inactiveMaterials as $material) {
                $warnings[] = "Material {$material['material_code']} is inactive";
            }
        }
        
        // Check for materials with zero quantity
        $sql = "SELECT bd.*, m.material_code
                FROM bom_details bd
                JOIN materials m ON bd.material_id = m.id
                WHERE bd.bom_header_id = ?
                  AND bd.quantity_per <= 0";
        
        $zeroQuantity = $this->db->select($sql, [$bomHeaderId], ['i']);
        if (!empty($zeroQuantity)) {
            foreach ($zeroQuantity as $item) {
                $errors[] = "Material {$item['material_code']} has zero or negative quantity";
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}