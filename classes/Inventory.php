<?php
/**
 * Inventory Model Class
 * Handles inventory tracking, transactions, and stock movements
 */

require_once __DIR__ . '/BaseModel.php';

class Inventory extends BaseModel {
    protected $table = 'inventory';
    protected $fillable = [
        'item_type',
        'item_id',
        'lot_number',
        'location_id',
        'quantity',
        'reserved_quantity',
        'uom_id',
        'manufacture_date',
        'expiry_date',
        'received_date',
        'supplier_id',
        'po_number',
        'unit_cost',
        'status'
    ];
    
    /**
     * Get current stock for an item
     * 
     * @param string $itemType 'material' or 'product'
     * @param int $itemId
     * @param int|null $locationId Optional specific location
     * @return array
     */
    public function getCurrentStock($itemType, $itemId, $locationId = null) {
        $sql = "SELECT 
                    i.*,
                    sl.code as location_code,
                    sl.description as location_name,
                    w.name as warehouse_name,
                    uom.code as uom_code,
                    s.name as supplier_name
                FROM inventory i
                LEFT JOIN storage_locations sl ON i.location_id = sl.id
                LEFT JOIN warehouses w ON sl.warehouse_id = w.id
                LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.item_type = ?
                  AND i.item_id = ?
                  AND i.status = 'available'
                  AND i.quantity > 0";
        
        $params = [$itemType, $itemId];
        $types = ['s', 'i'];
        
        if ($locationId) {
            $sql .= " AND i.location_id = ?";
            $params[] = $locationId;
            $types[] = 'i';
        }
        
        $sql .= " ORDER BY i.expiry_date ASC, i.received_date ASC"; // FIFO with expiry consideration
        
        return $this->db->select($sql, $params, $types);
    }
    
    /**
     * Get available quantity for an item
     * 
     * @param string $itemType
     * @param int $itemId
     * @return float
     */
    public function getAvailableQuantity($itemType, $itemId) {
        $sql = "SELECT COALESCE(SUM(quantity - reserved_quantity), 0) as available
                FROM inventory
                WHERE item_type = ?
                  AND item_id = ?
                  AND status = 'available'";
        
        $result = $this->db->selectOne($sql, [$itemType, $itemId], ['s', 'i']);
        return (float)$result['available'];
    }
    
    /**
     * Record inventory receipt
     * 
     * @param array $data
     * @return int Transaction ID
     */
    public function receiveInventory($data) {
        $this->db->beginTransaction();
        
        try {
            // Create inventory record
            $inventoryId = $this->create($data);
            
            // Record transaction
            $transactionData = [
                'transaction_type' => 'receipt',
                'transaction_date' => date('Y-m-d H:i:s'),
                'item_type' => $data['item_type'],
                'item_id' => $data['item_id'],
                'lot_number' => $data['lot_number'],
                'to_location_id' => $data['location_id'],
                'quantity' => $data['quantity'],
                'uom_id' => $data['uom_id'],
                'reference_type' => $data['reference_type'] ?? 'PO',
                'reference_number' => $data['po_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by' => $data['performed_by'] ?? 'System'
            ];
            
            $transactionId = $this->recordTransaction($transactionData);
            
            $this->db->commit();
            return $transactionId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Issue inventory (consume stock)
     * 
     * @param string $itemType
     * @param int $itemId
     * @param float $quantity
     * @param array $options
     * @return bool
     */
    public function issueInventory($itemType, $itemId, $quantity, $options = []) {
        $this->db->beginTransaction();
        
        try {
            $remainingQty = $quantity;
            $stocks = $this->getCurrentStock($itemType, $itemId, $options['location_id'] ?? null);
            
            foreach ($stocks as $stock) {
                if ($remainingQty <= 0) break;
                
                $availableInLot = $stock['quantity'] - $stock['reserved_quantity'];
                if ($availableInLot <= 0) continue;
                
                $issueQty = min($remainingQty, $availableInLot);
                
                // Update inventory
                $newQty = $stock['quantity'] - $issueQty;
                if ($newQty <= 0) {
                    // Fully consumed, update status
                    $sql = "UPDATE inventory SET quantity = 0, status = 'consumed' WHERE id = ?";
                    $this->db->update($sql, [$stock['id']], ['i']);
                } else {
                    // Partial consumption
                    $sql = "UPDATE inventory SET quantity = ? WHERE id = ?";
                    $this->db->update($sql, [$newQty, $stock['id']], ['d', 'i']);
                }
                
                // Record transaction
                $transactionData = [
                    'transaction_type' => 'issue',
                    'transaction_date' => date('Y-m-d H:i:s'),
                    'item_type' => $itemType,
                    'item_id' => $itemId,
                    'lot_number' => $stock['lot_number'],
                    'from_location_id' => $stock['location_id'],
                    'quantity' => $issueQty,
                    'uom_id' => $stock['uom_id'],
                    'reference_type' => $options['reference_type'] ?? 'Production',
                    'reference_number' => $options['reference_number'] ?? null,
                    'notes' => $options['notes'] ?? null,
                    'performed_by' => $options['performed_by'] ?? 'System'
                ];
                
                $this->recordTransaction($transactionData);
                
                $remainingQty -= $issueQty;
            }
            
            if ($remainingQty > 0) {
                throw new Exception("Insufficient stock. Short by {$remainingQty} units");
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Reserve inventory for future use
     * 
     * @param string $itemType
     * @param int $itemId
     * @param float $quantity
     * @param string $referenceType
     * @param string $referenceNumber
     * @return bool
     */
    public function reserveInventory($itemType, $itemId, $quantity, $referenceType, $referenceNumber) {
        $availableQty = $this->getAvailableQuantity($itemType, $itemId);
        
        if ($availableQty < $quantity) {
            throw new Exception("Insufficient stock for reservation. Available: {$availableQty}");
        }
        
        $this->db->beginTransaction();
        
        try {
            $remainingQty = $quantity;
            $stocks = $this->getCurrentStock($itemType, $itemId);
            
            foreach ($stocks as $stock) {
                if ($remainingQty <= 0) break;
                
                $availableInLot = $stock['quantity'] - $stock['reserved_quantity'];
                if ($availableInLot <= 0) continue;
                
                $reserveQty = min($remainingQty, $availableInLot);
                
                $sql = "UPDATE inventory 
                        SET reserved_quantity = reserved_quantity + ? 
                        WHERE id = ?";
                
                $this->db->update($sql, [$reserveQty, $stock['id']], ['d', 'i']);
                
                $remainingQty -= $reserveQty;
            }
            
            // Record reservation in transactions
            $transactionData = [
                'transaction_type' => 'adjustment',
                'transaction_date' => date('Y-m-d H:i:s'),
                'item_type' => $itemType,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'uom_id' => $stocks[0]['uom_id'],
                'reference_type' => $referenceType,
                'reference_number' => $referenceNumber,
                'notes' => "Reserved {$quantity} units",
                'performed_by' => 'System'
            ];
            
            $this->recordTransaction($transactionData);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Transfer inventory between locations
     * 
     * @param int $inventoryId
     * @param int $toLocationId
     * @param float $quantity
     * @param array $options
     * @return bool
     */
    public function transferInventory($inventoryId, $toLocationId, $quantity, $options = []) {
        $this->db->beginTransaction();
        
        try {
            // Get source inventory
            $sourceInventory = $this->find($inventoryId);
            if (!$sourceInventory) {
                throw new Exception("Source inventory not found");
            }
            
            $availableQty = $sourceInventory['quantity'] - $sourceInventory['reserved_quantity'];
            if ($availableQty < $quantity) {
                throw new Exception("Insufficient quantity for transfer");
            }
            
            // If transferring partial quantity, create new inventory record
            if ($quantity < $sourceInventory['quantity']) {
                // Reduce source quantity
                $sql = "UPDATE inventory SET quantity = quantity - ? WHERE id = ?";
                $this->db->update($sql, [$quantity, $inventoryId], ['d', 'i']);
                
                // Create new inventory at destination
                $newInventory = $sourceInventory;
                unset($newInventory['id']);
                $newInventory['location_id'] = $toLocationId;
                $newInventory['quantity'] = $quantity;
                $newInventory['reserved_quantity'] = 0;
                
                $this->create($newInventory);
            } else {
                // Transfer entire lot
                $sql = "UPDATE inventory SET location_id = ? WHERE id = ?";
                $this->db->update($sql, [$toLocationId, $inventoryId], ['i', 'i']);
            }
            
            // Record transaction
            $transactionData = [
                'transaction_type' => 'transfer',
                'transaction_date' => date('Y-m-d H:i:s'),
                'item_type' => $sourceInventory['item_type'],
                'item_id' => $sourceInventory['item_id'],
                'lot_number' => $sourceInventory['lot_number'],
                'from_location_id' => $sourceInventory['location_id'],
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'uom_id' => $sourceInventory['uom_id'],
                'reference_type' => $options['reference_type'] ?? 'Transfer',
                'reference_number' => $options['reference_number'] ?? null,
                'notes' => $options['notes'] ?? null,
                'performed_by' => $options['performed_by'] ?? 'System'
            ];
            
            $this->recordTransaction($transactionData);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Record inventory transaction
     * 
     * @param array $data
     * @return int
     */
    protected function recordTransaction($data) {
        $sql = "INSERT INTO inventory_transactions 
                (transaction_type, transaction_date, item_type, item_id, lot_number,
                 from_location_id, to_location_id, quantity, uom_id,
                 reference_type, reference_number, notes, performed_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['transaction_type'],
            $data['transaction_date'],
            $data['item_type'],
            $data['item_id'],
            $data['lot_number'] ?? null,
            $data['from_location_id'] ?? null,
            $data['to_location_id'] ?? null,
            $data['quantity'],
            $data['uom_id'],
            $data['reference_type'] ?? null,
            $data['reference_number'] ?? null,
            $data['notes'] ?? null,
            $data['performed_by']
        ];
        
        $types = ['s', 's', 's', 'i', 's', 'i', 'i', 'd', 'i', 's', 's', 's', 's'];
        
        return $this->db->insert($sql, $params, $types);
    }
    
    /**
     * Get inventory transactions history
     * 
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public function getTransactionHistory($filters = [], $limit = 100) {
        $sql = "SELECT 
                    it.*,
                    CASE 
                        WHEN it.item_type = 'material' THEN m.material_code
                        WHEN it.item_type = 'product' THEN p.product_code
                    END AS item_code,
                    CASE 
                        WHEN it.item_type = 'material' THEN m.name
                        WHEN it.item_type = 'product' THEN p.name
                    END AS item_name,
                    fl.code as from_location,
                    tl.code as to_location,
                    uom.code as uom_code
                FROM inventory_transactions it
                LEFT JOIN materials m ON it.item_type = 'material' AND it.item_id = m.id
                LEFT JOIN products p ON it.item_type = 'product' AND it.item_id = p.id
                LEFT JOIN storage_locations fl ON it.from_location_id = fl.id
                LEFT JOIN storage_locations tl ON it.to_location_id = tl.id
                LEFT JOIN units_of_measure uom ON it.uom_id = uom.id
                WHERE 1=1";
        
        $params = [];
        $types = [];
        
        if (!empty($filters['item_type'])) {
            $sql .= " AND it.item_type = ?";
            $params[] = $filters['item_type'];
            $types[] = 's';
        }
        
        if (!empty($filters['item_id'])) {
            $sql .= " AND it.item_id = ?";
            $params[] = $filters['item_id'];
            $types[] = 'i';
        }
        
        if (!empty($filters['transaction_type'])) {
            $sql .= " AND it.transaction_type = ?";
            $params[] = $filters['transaction_type'];
            $types[] = 's';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND it.transaction_date >= ?";
            $params[] = $filters['date_from'];
            $types[] = 's';
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND it.transaction_date <= ?";
            $params[] = $filters['date_to'];
            $types[] = 's';
        }
        
        $sql .= " ORDER BY it.transaction_date DESC LIMIT " . (int)$limit;
        
        return $this->db->select($sql, $params, $types);
    }
    
    /**
     * Get recent inventory movements for an item
     * 
     * @param string $itemType 'material' or 'product'
     * @param int $itemId
     * @param int $limit Number of records to return
     * @return array
     */
    public function getRecentMovements($itemType, $itemId, $limit = 10) {
        $sql = "SELECT 
                    it.*,
                    CASE it.transaction_type
                        WHEN 'receipt' THEN 'in'
                        WHEN 'issue' THEN 'out'
                        WHEN 'transfer' THEN 'transfer'
                        WHEN 'adjustment' THEN 'adjustment'
                        ELSE it.transaction_type
                    END AS movement_type,
                    fl.code as from_location,
                    tl.code as to_location,
                    uom.code as uom_code
                FROM inventory_transactions it
                LEFT JOIN storage_locations fl ON it.from_location_id = fl.id
                LEFT JOIN storage_locations tl ON it.to_location_id = tl.id
                LEFT JOIN units_of_measure uom ON it.uom_id = uom.id
                WHERE it.item_type = ?
                  AND it.item_id = ?
                ORDER BY it.transaction_date DESC, it.id DESC
                LIMIT ?";
        
        $result = $this->db->select($sql, [$itemType, $itemId, $limit], ['s', 'i', 'i']);
        
        // Map fields for compatibility with view.php
        foreach ($result as &$row) {
            $row['created_at'] = $row['transaction_date'];
        }
        
        return $result;
    }
    
    /**
     * Get expiring inventory
     * 
     * @param int $days Days until expiry
     * @return array
     */
    public function getExpiringInventory($days = 30) {
        $sql = "SELECT 
                    i.*,
                    CASE 
                        WHEN i.item_type = 'material' THEN m.material_code
                        WHEN i.item_type = 'product' THEN p.product_code
                    END AS item_code,
                    CASE 
                        WHEN i.item_type = 'material' THEN m.name
                        WHEN i.item_type = 'product' THEN p.name
                    END AS item_name,
                    sl.code as location_code,
                    uom.code as uom_code,
                    DATEDIFF(i.expiry_date, CURRENT_DATE) as days_until_expiry
                FROM inventory i
                LEFT JOIN materials m ON i.item_type = 'material' AND i.item_id = m.id
                LEFT JOIN products p ON i.item_type = 'product' AND i.item_id = p.id
                LEFT JOIN storage_locations sl ON i.location_id = sl.id
                LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
                WHERE i.status = 'available'
                  AND i.quantity > 0
                  AND i.expiry_date IS NOT NULL
                  AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL ? DAY)
                ORDER BY i.expiry_date ASC";
        
        return $this->db->select($sql, [$days], ['i']);
    }
}