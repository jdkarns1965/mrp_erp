<?php
/**
 * MRP (Material Requirements Planning) Engine
 * Core class for calculating material requirements based on customer orders
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Material.php';
require_once __DIR__ . '/BOM.php';
require_once __DIR__ . '/Inventory.php';

class MRP extends BaseModel {
    protected $table = 'mrp_requirements';
    protected $productModel;
    protected $materialModel;
    protected $bomModel;
    protected $inventoryModel;
    
    public function __construct() {
        parent::__construct();
        $this->productModel = new Product();
        $this->materialModel = new Material();
        $this->bomModel = new BOM();
        $this->inventoryModel = new Inventory();
    }
    
    /**
     * Run MRP calculation for a customer order
     * 
     * @param int $orderId
     * @return array MRP results
     */
    public function runMRP($orderId) {
        $this->db->beginTransaction();
        
        try {
            // Get order details
            $orderDetails = $this->getOrderDetails($orderId);
            if (empty($orderDetails)) {
                throw new Exception("Order not found or has no items");
            }
            
            // Clear previous MRP calculations for this order
            $this->clearPreviousCalculations($orderId);
            
            $mrpResults = [];
            $consolidatedRequirements = [];
            
            // Process each product in the order
            foreach ($orderDetails as $orderItem) {
                $productId = $orderItem['product_id'];
                $requiredQty = $orderItem['quantity'];
                
                // Explode BOM for this product
                $bomExplosion = $this->bomModel->explodeBOM($productId, $requiredQty);
                
                if (empty($bomExplosion)) {
                    $mrpResults[] = [
                        'product_id' => $productId,
                        'product_code' => $orderItem['product_code'],
                        'error' => 'No active BOM found for product'
                    ];
                    continue;
                }
                
                // Process each material requirement
                foreach ($bomExplosion as $requirement) {
                    $materialId = $requirement['material_id'];
                    $grossRequirement = $requirement['total_required'];
                    
                    // Consolidate requirements if material appears in multiple products
                    if (!isset($consolidatedRequirements[$materialId])) {
                        $consolidatedRequirements[$materialId] = [
                            'material_id' => $materialId,
                            'material_code' => $requirement['material_code'],
                            'material_name' => $requirement['material_name'],
                            'gross_requirement' => 0,
                            'uom_code' => $requirement['uom_code'],
                            'products' => []
                        ];
                    }
                    
                    $consolidatedRequirements[$materialId]['gross_requirement'] += $grossRequirement;
                    $consolidatedRequirements[$materialId]['products'][] = [
                        'product_id' => $productId,
                        'product_code' => $orderItem['product_code'],
                        'quantity' => $grossRequirement
                    ];
                }
            }
            
            // Calculate net requirements considering inventory
            foreach ($consolidatedRequirements as $materialId => &$requirement) {
                // Get available inventory
                $availableStock = $this->inventoryModel->getAvailableQuantity('material', $materialId);
                
                // Calculate net requirement
                $netRequirement = max(0, $requirement['gross_requirement'] - $availableStock);
                
                // Get material details for lead time and order quantity
                $material = $this->materialModel->find($materialId);
                
                // Calculate suggested order quantity (considering min/max stock levels)
                $suggestedOrderQty = $this->calculateSuggestedOrderQuantity(
                    $netRequirement,
                    $material['min_stock_qty'] ?? 0,
                    $material['max_stock_qty'] ?? 0,
                    $material['reorder_point'] ?? 0
                );
                
                // Calculate suggested order date based on lead time
                $suggestedOrderDate = $this->calculateOrderDate(
                    $orderDetails[0]['required_date'],
                    $material['lead_time_days'] ?? 0
                );
                
                // Add calculated values
                $requirement['available_stock'] = $availableStock;
                $requirement['net_requirement'] = $netRequirement;
                $requirement['suggested_order_qty'] = $suggestedOrderQty;
                $requirement['suggested_order_date'] = $suggestedOrderDate;
                $requirement['lead_time_days'] = $material['lead_time_days'] ?? 0;
                $requirement['supplier_name'] = $material['supplier_name'] ?? 'Not specified';
                $requirement['unit_cost'] = $material['cost_per_unit'] ?? 0;
                $requirement['total_cost'] = $suggestedOrderQty * ($material['cost_per_unit'] ?? 0);
                
                // Save MRP calculation to database
                $this->saveMRPRequirement($orderId, $orderDetails[0]['product_id'], $requirement);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'calculation_date' => date('Y-m-d H:i:s'),
                'requirements' => array_values($consolidatedRequirements),
                'summary' => $this->generateMRPSummary($consolidatedRequirements)
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get order details with products
     * 
     * @param int $orderId
     * @return array
     */
    protected function getOrderDetails($orderId) {
        $sql = "SELECT 
                    co.id as order_id,
                    co.order_number,
                    co.required_date,
                    cod.product_id,
                    cod.quantity,
                    p.product_code,
                    p.name as product_name
                FROM customer_orders co
                JOIN customer_order_details cod ON co.id = cod.order_id
                JOIN products p ON cod.product_id = p.id
                WHERE co.id = ?
                  AND co.status NOT IN ('cancelled', 'completed')";
        
        return $this->db->select($sql, [$orderId], ['i']);
    }
    
    /**
     * Clear previous MRP calculations for an order
     * 
     * @param int $orderId
     */
    protected function clearPreviousCalculations($orderId) {
        $sql = "DELETE FROM mrp_requirements WHERE order_id = ?";
        $this->db->delete($sql, [$orderId], ['i']);
    }
    
    /**
     * Save MRP requirement to database
     * 
     * @param int $orderId
     * @param int $productId
     * @param array $requirement
     */
    protected function saveMRPRequirement($orderId, $productId, $requirement) {
        $sql = "INSERT INTO mrp_requirements 
                (calculation_date, order_id, product_id, material_id,
                 gross_requirement, available_stock, net_requirement,
                 suggested_order_qty, suggested_order_date)
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $orderId,
            $productId,
            $requirement['material_id'],
            $requirement['gross_requirement'],
            $requirement['available_stock'],
            $requirement['net_requirement'],
            $requirement['suggested_order_qty'],
            $requirement['suggested_order_date']
        ];
        
        $types = ['i', 'i', 'i', 'd', 'd', 'd', 'd', 's'];
        
        $this->db->insert($sql, $params, $types);
    }
    
    /**
     * Calculate suggested order quantity
     * 
     * @param float $netRequirement
     * @param float $minStock
     * @param float $maxStock
     * @param float $reorderPoint
     * @return float
     */
    protected function calculateSuggestedOrderQuantity($netRequirement, $minStock, $maxStock, $reorderPoint) {
        // If no net requirement, no need to order
        if ($netRequirement <= 0) {
            return 0;
        }
        
        // Start with net requirement
        $orderQty = $netRequirement;
        
        // If min stock is set, ensure we order enough to meet it
        if ($minStock > 0) {
            $orderQty = max($orderQty, $minStock);
        }
        
        // If max stock is set, don't exceed it
        if ($maxStock > 0) {
            $orderQty = min($orderQty, $maxStock);
        }
        
        // Round up to nearest standard pack size (could be enhanced with actual pack sizes)
        // For now, round to nearest 10 for small quantities, 100 for larger
        if ($orderQty < 100) {
            $orderQty = ceil($orderQty / 10) * 10;
        } else {
            $orderQty = ceil($orderQty / 100) * 100;
        }
        
        return $orderQty;
    }
    
    /**
     * Calculate order date based on required date and lead time
     * 
     * @param string $requiredDate
     * @param int $leadTimeDays
     * @return string
     */
    protected function calculateOrderDate($requiredDate, $leadTimeDays) {
        $date = new DateTime($requiredDate);
        $date->sub(new DateInterval('P' . $leadTimeDays . 'D'));
        
        // If order date is in the past, set to today
        $today = new DateTime();
        if ($date < $today) {
            return $today->format('Y-m-d');
        }
        
        return $date->format('Y-m-d');
    }
    
    /**
     * Generate MRP summary
     * 
     * @param array $requirements
     * @return array
     */
    protected function generateMRPSummary($requirements) {
        $totalMaterials = count($requirements);
        $materialsWithShortage = 0;
        $totalCost = 0;
        $urgentOrders = 0;
        $today = new DateTime();
        
        foreach ($requirements as $req) {
            if ($req['net_requirement'] > 0) {
                $materialsWithShortage++;
                $totalCost += $req['total_cost'];
                
                $orderDate = new DateTime($req['suggested_order_date']);
                $daysDiff = $today->diff($orderDate)->days;
                
                if ($daysDiff <= 3) {
                    $urgentOrders++;
                }
            }
        }
        
        return [
            'total_materials' => $totalMaterials,
            'materials_with_shortage' => $materialsWithShortage,
            'total_purchase_cost' => round($totalCost, 2),
            'urgent_orders' => $urgentOrders,
            'can_fulfill' => $materialsWithShortage === 0
        ];
    }
    
    /**
     * Get MRP history for an order
     * 
     * @param int $orderId
     * @return array
     */
    public function getMRPHistory($orderId) {
        $sql = "SELECT 
                    mr.*,
                    m.material_code,
                    m.name as material_name,
                    p.product_code,
                    p.name as product_name,
                    uom.code as uom_code
                FROM mrp_requirements mr
                JOIN materials m ON mr.material_id = m.id
                JOIN products p ON mr.product_id = p.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                WHERE mr.order_id = ?
                ORDER BY mr.calculation_date DESC, m.material_code";
        
        return $this->db->select($sql, [$orderId], ['i']);
    }
    
    /**
     * Generate purchase order suggestions from MRP
     * 
     * @param int $orderId
     * @return array
     */
    public function generatePurchaseOrderSuggestions($orderId) {
        $sql = "SELECT 
                    mr.material_id,
                    m.material_code,
                    m.name as material_name,
                    m.default_supplier_id,
                    s.name as supplier_name,
                    SUM(mr.suggested_order_qty) as total_qty,
                    MIN(mr.suggested_order_date) as earliest_date,
                    m.cost_per_unit,
                    SUM(mr.suggested_order_qty * m.cost_per_unit) as total_cost,
                    uom.code as uom_code
                FROM mrp_requirements mr
                JOIN materials m ON mr.material_id = m.id
                LEFT JOIN suppliers s ON m.default_supplier_id = s.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                WHERE mr.order_id = ?
                  AND mr.net_requirement > 0
                GROUP BY mr.material_id, m.material_code, m.name, 
                         m.default_supplier_id, s.name, m.cost_per_unit, uom.code
                ORDER BY s.name, m.material_code";
        
        $results = $this->db->select($sql, [$orderId], ['i']);
        
        // Group by supplier
        $poSuggestions = [];
        foreach ($results as $item) {
            $supplierId = $item['default_supplier_id'] ?? 0;
            $supplierName = $item['supplier_name'] ?? 'No Supplier Assigned';
            
            if (!isset($poSuggestions[$supplierId])) {
                $poSuggestions[$supplierId] = [
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplierName,
                    'items' => [],
                    'total_cost' => 0,
                    'earliest_date' => $item['earliest_date']
                ];
            }
            
            $poSuggestions[$supplierId]['items'][] = $item;
            $poSuggestions[$supplierId]['total_cost'] += $item['total_cost'];
            
            // Keep track of earliest date
            if ($item['earliest_date'] < $poSuggestions[$supplierId]['earliest_date']) {
                $poSuggestions[$supplierId]['earliest_date'] = $item['earliest_date'];
            }
        }
        
        return array_values($poSuggestions);
    }
}