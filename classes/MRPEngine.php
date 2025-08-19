<?php
/**
 * Enhanced MRP Engine with Time-Phased Planning
 * Implements complete MRP logic including lead times, lot sizing, and safety stock
 */

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/MRP.php';

class MRPEngine extends MRP {
    
    private $planningHorizon = 90; // days
    private $mrpRunId;
    private $planningPeriods = [];
    private $includesSafetyStock = true;
    
    /**
     * Run complete time-phased MRP calculation
     * 
     * @param array $options Configuration options
     * @return array MRP results
     */
    public function runTimePhasedMRP($options = []) {
        $this->db->beginTransaction();
        
        try {
            // Initialize MRP run
            $this->initializeMRPRun($options);
            
            // Load planning periods
            $this->loadPlanningPeriods();
            
            // Get demand sources
            $demands = $this->collectDemands($options);
            
            // Process each item through MRP logic
            $mrpResults = $this->processMRPItems($demands);
            
            // Generate order suggestions
            $this->generateOrderSuggestions($mrpResults);
            
            // Update MRP run statistics
            $this->finalizeMRPRun();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'mrp_run_id' => $this->mrpRunId,
                'results' => $mrpResults,
                'summary' => $this->getMRPRunSummary()
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->logMRPError($e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize MRP run session
     */
    private function initializeMRPRun($options) {
        $runType = $options['run_type'] ?? 'regenerative';
        $this->planningHorizon = $options['planning_horizon'] ?? 90;
        $this->includesSafetyStock = $options['include_safety_stock'] ?? true;
        
        $sql = "INSERT INTO mrp_runs 
                (run_date, run_type, planning_horizon_days, status, run_by, parameters)
                VALUES (NOW(), ?, ?, 'running', ?, ?)";
        
        $params = [
            $runType,
            $this->planningHorizon,
            $options['user'] ?? 'SYSTEM',
            json_encode($options)
        ];
        
        $this->mrpRunId = $this->db->insert($sql, $params, ['s', 'i', 's', 's']);
    }
    
    /**
     * Load planning periods from calendar
     */
    private function loadPlanningPeriods() {
        $endDate = date('Y-m-d', strtotime("+{$this->planningHorizon} days"));
        
        $sql = "SELECT * FROM planning_calendar 
                WHERE period_start <= ? 
                  AND period_end >= CURDATE()
                  AND is_working_period = 1
                ORDER BY period_start";
        
        $this->planningPeriods = $this->db->select($sql, [$endDate], ['s']);
        
        if (empty($this->planningPeriods)) {
            throw new Exception("No planning periods defined for the planning horizon");
        }
    }
    
    /**
     * Collect all demand sources
     */
    private function collectDemands($options) {
        $demands = [];
        
        // 1. Customer Orders
        if ($options['include_orders'] ?? true) {
            $demands['orders'] = $this->getCustomerOrderDemands();
        }
        
        // 2. Master Production Schedule
        if ($options['include_mps'] ?? true) {
            $demands['mps'] = $this->getMPSDemands();
        }
        
        // 3. Safety Stock Requirements
        if ($this->includesSafetyStock) {
            $demands['safety_stock'] = $this->getSafetyStockDemands();
        }
        
        return $demands;
    }
    
    /**
     * Get customer order demands
     */
    private function getCustomerOrderDemands() {
        $sql = "SELECT 
                    co.id as order_id,
                    co.order_number,
                    co.required_date,
                    cod.product_id,
                    cod.quantity,
                    p.product_code,
                    p.name as product_name,
                    p.lead_time_days
                FROM customer_orders co
                JOIN customer_order_details cod ON co.id = cod.order_id
                JOIN products p ON cod.product_id = p.id
                WHERE co.status IN ('pending', 'confirmed', 'in_production')
                  AND co.required_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY co.required_date";
        
        return $this->db->select($sql, [$this->planningHorizon], ['i']);
    }
    
    /**
     * Get MPS demands
     */
    private function getMPSDemands() {
        $sql = "SELECT 
                    mps.id as mps_id,
                    mps.product_id,
                    mps.firm_planned_qty as quantity,
                    pc.period_end as required_date,
                    p.product_code,
                    p.name as product_name,
                    p.lead_time_days
                FROM master_production_schedule mps
                JOIN planning_calendar pc ON mps.period_id = pc.id
                JOIN products p ON mps.product_id = p.id
                WHERE mps.status IN ('firm', 'released')
                  AND pc.period_end <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  AND mps.firm_planned_qty > 0
                ORDER BY pc.period_end";
        
        return $this->db->select($sql, [$this->planningHorizon], ['i']);
    }
    
    /**
     * Get safety stock demands
     */
    private function getSafetyStockDemands() {
        $demands = [];
        
        // Check materials below safety stock
        $sql = "SELECT 
                    'material' as item_type,
                    m.id as item_id,
                    m.material_code as item_code,
                    m.name as item_name,
                    m.safety_stock_qty,
                    COALESCE(SUM(i.quantity - i.reserved_quantity), 0) as current_stock,
                    m.lead_time_days
                FROM materials m
                LEFT JOIN inventory i ON i.item_type = 'material' 
                    AND i.item_id = m.id 
                    AND i.status = 'available'
                WHERE m.safety_stock_qty > 0
                  AND m.is_active = 1
                GROUP BY m.id
                HAVING current_stock < m.safety_stock_qty";
        
        $materials = $this->db->select($sql);
        foreach ($materials as $mat) {
            $demands[] = [
                'item_type' => 'material',
                'item_id' => $mat['item_id'],
                'quantity' => $mat['safety_stock_qty'] - $mat['current_stock'],
                'required_date' => date('Y-m-d', strtotime("+{$mat['lead_time_days']} days")),
                'demand_type' => 'safety_stock'
            ];
        }
        
        return $demands;
    }
    
    /**
     * Process items through MRP logic
     */
    private function processMRPItems($demands) {
        $results = [];
        $processedItems = [];
        
        // Process customer orders and MPS
        foreach (['orders', 'mps'] as $demandType) {
            if (!isset($demands[$demandType])) continue;
            
            foreach ($demands[$demandType] as $demand) {
                $itemKey = "product_{$demand['product_id']}";
                
                if (!isset($processedItems[$itemKey])) {
                    $processedItems[$itemKey] = [
                        'type' => 'product',
                        'id' => $demand['product_id'],
                        'code' => $demand['product_code'],
                        'name' => $demand['product_name'],
                        'demands' => []
                    ];
                }
                
                $processedItems[$itemKey]['demands'][] = [
                    'date' => $demand['required_date'],
                    'quantity' => $demand['quantity'],
                    'source' => $demandType
                ];
                
                // Explode BOM and process materials
                $this->explodeAndProcessBOM(
                    $demand['product_id'], 
                    $demand['quantity'], 
                    $demand['required_date'],
                    $processedItems
                );
            }
        }
        
        // Calculate net requirements and generate time-phased plan
        foreach ($processedItems as $key => $item) {
            $results[$key] = $this->calculateTimePhasedRequirements($item);
        }
        
        return $results;
    }
    
    /**
     * Explode BOM and process material requirements
     */
    private function explodeAndProcessBOM($productId, $quantity, $requiredDate, &$processedItems) {
        $bomItems = $this->bomModel->explodeBOM($productId, $quantity);
        
        foreach ($bomItems as $bomItem) {
            $itemKey = "material_{$bomItem['material_id']}";
            
            if (!isset($processedItems[$itemKey])) {
                $material = $this->materialModel->find($bomItem['material_id']);
                $processedItems[$itemKey] = [
                    'type' => 'material',
                    'id' => $bomItem['material_id'],
                    'code' => $bomItem['material_code'],
                    'name' => $bomItem['material_name'],
                    'lead_time' => $material['lead_time_days'] ?? 0,
                    'lot_size_rule' => $material['lot_size_rule'] ?? 'lot-for-lot',
                    'lot_size_qty' => $material['lot_size_qty'] ?? 0,
                    'lot_size_multiple' => $material['lot_size_multiple'] ?? 1,
                    'safety_stock' => $material['safety_stock_qty'] ?? 0,
                    'demands' => []
                ];
            }
            
            // Calculate when material is needed (offset by production lead time)
            $product = $this->productModel->find($productId);
            $productLeadTime = $product['lead_time_days'] ?? 0;
            $materialNeededDate = date('Y-m-d', strtotime($requiredDate . " -{$productLeadTime} days"));
            
            $processedItems[$itemKey]['demands'][] = [
                'date' => $materialNeededDate,
                'quantity' => $bomItem['total_required'],
                'source' => 'bom',
                'parent_product' => $productId
            ];
        }
    }
    
    /**
     * Calculate time-phased requirements for an item
     */
    private function calculateTimePhasedRequirements($item) {
        $timePhasedPlan = [];
        
        // Get current inventory
        $currentStock = $this->inventoryModel->getAvailableQuantity($item['type'], $item['id']);
        $onHandInventory = $currentStock;
        
        // Sort demands by date
        usort($item['demands'], function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        // Group demands by period
        $periodDemands = $this->groupDemandsByPeriod($item['demands']);
        
        // Process each period
        foreach ($this->planningPeriods as $period) {
            $periodKey = $period['id'];
            $periodDemand = $periodDemands[$periodKey] ?? 0;
            
            // Calculate projected available
            $projectedAvailable = $onHandInventory - $periodDemand;
            
            // Check if we need to order
            $plannedReceipts = 0;
            $plannedOrders = 0;
            
            if ($projectedAvailable < ($item['safety_stock'] ?? 0)) {
                // Calculate order quantity based on lot sizing rule
                $netRequirement = ($item['safety_stock'] ?? 0) - $projectedAvailable + $periodDemand;
                $plannedOrders = $this->calculateLotSize($netRequirement, $item);
                
                // Calculate when to release order (considering lead time)
                $orderReleaseDate = $this->calculateOrderReleaseDate(
                    $period['period_start'], 
                    $item['lead_time'] ?? 0
                );
                
                $plannedReceipts = $plannedOrders; // Will be received in future period
                $projectedAvailable += $plannedReceipts;
            }
            
            $timePhasedPlan[] = [
                'period_id' => $periodKey,
                'period_name' => $period['period_name'],
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
                'gross_requirements' => $periodDemand,
                'scheduled_receipts' => 0, // From existing POs
                'on_hand' => $onHandInventory,
                'projected_available' => $projectedAvailable,
                'net_requirements' => max(0, $periodDemand - $onHandInventory),
                'planned_receipts' => $plannedReceipts,
                'planned_orders' => $plannedOrders,
                'order_release_date' => $orderReleaseDate ?? null
            ];
            
            // Update on-hand for next period
            $onHandInventory = $projectedAvailable;
        }
        
        return [
            'item' => $item,
            'current_stock' => $currentStock,
            'time_phased_plan' => $timePhasedPlan
        ];
    }
    
    /**
     * Group demands by planning period
     */
    private function groupDemandsByPeriod($demands) {
        $periodDemands = [];
        
        foreach ($demands as $demand) {
            $demandDate = $demand['date'];
            
            // Find which period this demand falls into
            foreach ($this->planningPeriods as $period) {
                if ($demandDate >= $period['period_start'] && $demandDate <= $period['period_end']) {
                    $periodKey = $period['id'];
                    if (!isset($periodDemands[$periodKey])) {
                        $periodDemands[$periodKey] = 0;
                    }
                    $periodDemands[$periodKey] += $demand['quantity'];
                    break;
                }
            }
        }
        
        return $periodDemands;
    }
    
    /**
     * Calculate lot size based on lot sizing rule
     */
    private function calculateLotSize($netRequirement, $item) {
        $rule = $item['lot_size_rule'] ?? 'lot-for-lot';
        
        switch ($rule) {
            case 'fixed':
                // Fixed order quantity
                return $item['lot_size_qty'] ?? $netRequirement;
                
            case 'lot-for-lot':
                // Order exactly what's needed
                return $netRequirement;
                
            case 'min-max':
                // Order up to maximum when below minimum
                $minQty = $item['lot_size_qty'] ?? 0;
                $maxQty = $item['lot_size_multiple'] ?? $netRequirement * 2;
                return $netRequirement < $minQty ? $maxQty : $netRequirement;
                
            case 'economic':
                // Economic Order Quantity (EOQ)
                return $this->calculateEOQ($item, $netRequirement);
                
            default:
                return $netRequirement;
        }
    }
    
    /**
     * Calculate Economic Order Quantity
     */
    private function calculateEOQ($item, $annualDemand) {
        // Get cost parameters
        $sql = "SELECT order_cost, carrying_cost_percent, cost_per_unit 
                FROM materials 
                WHERE id = ? LIMIT 1";
        
        $costData = $this->db->selectOne($sql, [$item['id']], ['i']);
        
        if (!$costData || !$costData['order_cost'] || !$costData['carrying_cost_percent']) {
            return $annualDemand; // Fall back to demand if costs not defined
        }
        
        $orderCost = $costData['order_cost'];
        $carryingCostPercent = $costData['carrying_cost_percent'] / 100;
        $unitCost = $costData['cost_per_unit'];
        
        // EOQ = sqrt((2 * D * S) / (H * C))
        $eoq = sqrt((2 * $annualDemand * $orderCost) / ($carryingCostPercent * $unitCost));
        
        // Round to lot multiple if specified
        $lotMultiple = $item['lot_size_multiple'] ?? 1;
        return ceil($eoq / $lotMultiple) * $lotMultiple;
    }
    
    /**
     * Calculate order release date considering lead time
     */
    private function calculateOrderReleaseDate($needDate, $leadTimeDays) {
        if ($leadTimeDays <= 0) {
            return $needDate;
        }
        
        // Use the database function to calculate working days
        $sql = "SELECT get_working_date(?, ?) as release_date";
        $result = $this->db->selectOne($sql, [$needDate, -$leadTimeDays], ['s', 'i']);
        
        return $result['release_date'] ?? $needDate;
    }
    
    /**
     * Generate purchase and production order suggestions
     */
    private function generateOrderSuggestions($mrpResults) {
        foreach ($mrpResults as $result) {
            $item = $result['item'];
            
            foreach ($result['time_phased_plan'] as $period) {
                if ($period['planned_orders'] > 0) {
                    if ($item['type'] === 'material') {
                        $this->createPurchaseOrderSuggestion($item, $period);
                    } elseif ($item['type'] === 'product') {
                        $this->createProductionOrderSuggestion($item, $period);
                    }
                }
            }
        }
    }
    
    /**
     * Create purchase order suggestion
     */
    private function createPurchaseOrderSuggestion($item, $period) {
        // Get material details including supplier
        $material = $this->materialModel->find($item['id']);
        
        $sql = "INSERT INTO purchase_order_suggestions
                (mrp_run_id, material_id, supplier_id, suggested_order_date, 
                 required_date, quantity, uom_id, unit_cost, total_cost, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'suggested')";
        
        // Determine priority based on how soon it's needed
        $daysUntilNeeded = (strtotime($period['period_start']) - time()) / 86400;
        $priority = $daysUntilNeeded <= 3 ? 'urgent' : 
                   ($daysUntilNeeded <= 7 ? 'high' : 'normal');
        
        $params = [
            $this->mrpRunId,
            $item['id'],
            $material['default_supplier_id'] ?? null,
            $period['order_release_date'],
            $period['period_start'],
            $period['planned_orders'],
            $material['uom_id'],
            $material['cost_per_unit'] ?? 0,
            $period['planned_orders'] * ($material['cost_per_unit'] ?? 0),
            $priority
        ];
        
        $this->db->insert($sql, $params, ['i', 'i', 'i', 's', 's', 'd', 'i', 'd', 'd', 's']);
    }
    
    /**
     * Create production order suggestion
     */
    private function createProductionOrderSuggestion($item, $period) {
        $product = $this->productModel->find($item['id']);
        
        $sql = "INSERT INTO production_order_suggestions
                (mrp_run_id, product_id, suggested_start_date, suggested_end_date,
                 required_date, quantity, uom_id, priority, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'suggested')";
        
        // Calculate production dates
        $startDate = $period['order_release_date'];
        $endDate = $period['period_start'];
        
        // Determine priority
        $daysUntilNeeded = (strtotime($period['period_start']) - time()) / 86400;
        $priority = $daysUntilNeeded <= 3 ? 'urgent' : 
                   ($daysUntilNeeded <= 7 ? 'high' : 'normal');
        
        $params = [
            $this->mrpRunId,
            $item['id'],
            $startDate,
            $endDate,
            $period['period_end'],
            $period['planned_orders'],
            $product['uom_id'],
            $priority
        ];
        
        $this->db->insert($sql, $params, ['i', 'i', 's', 's', 's', 'd', 'i', 's']);
    }
    
    /**
     * Finalize MRP run and update statistics
     */
    private function finalizeMRPRun() {
        // Count generated suggestions
        $sql = "SELECT 
                (SELECT COUNT(*) FROM purchase_order_suggestions WHERE mrp_run_id = ?) as po_count,
                (SELECT COUNT(*) FROM production_order_suggestions WHERE mrp_run_id = ?) as prod_count,
                (SELECT COUNT(DISTINCT material_id) FROM purchase_order_suggestions WHERE mrp_run_id = ?) as material_count,
                (SELECT COUNT(DISTINCT product_id) FROM production_order_suggestions WHERE mrp_run_id = ?) as product_count";
        
        $stats = $this->db->selectOne($sql, [$this->mrpRunId, $this->mrpRunId, $this->mrpRunId, $this->mrpRunId], 
                                      ['i', 'i', 'i', 'i']);
        
        // Update MRP run record
        $sql = "UPDATE mrp_runs 
                SET status = 'completed',
                    total_products = ?,
                    total_materials = ?,
                    total_po_suggestions = ?,
                    total_prod_suggestions = ?,
                    execution_time_seconds = TIMESTAMPDIFF(SECOND, created_at, NOW())
                WHERE id = ?";
        
        $this->db->update($sql, [
            $stats['product_count'],
            $stats['material_count'],
            $stats['po_count'],
            $stats['prod_count'],
            $this->mrpRunId
        ], ['i', 'i', 'i', 'i', 'i']);
    }
    
    /**
     * Get MRP run summary
     */
    private function getMRPRunSummary() {
        $sql = "SELECT * FROM mrp_runs WHERE id = ?";
        $runData = $this->db->selectOne($sql, [$this->mrpRunId], ['i']);
        
        // Get urgent actions
        $sql = "SELECT COUNT(*) as urgent_count 
                FROM v_mrp_actions 
                WHERE urgency IN ('OVERDUE', 'URGENT')";
        $urgentData = $this->db->selectOne($sql);
        
        return [
            'mrp_run_id' => $this->mrpRunId,
            'status' => $runData['status'],
            'total_materials' => $runData['total_materials'],
            'total_products' => $runData['total_products'],
            'po_suggestions' => $runData['total_po_suggestions'],
            'production_suggestions' => $runData['total_prod_suggestions'],
            'urgent_actions' => $urgentData['urgent_count'] ?? 0,
            'execution_time' => $runData['execution_time_seconds'] . ' seconds'
        ];
    }
    
    /**
     * Log MRP error
     */
    private function logMRPError($errorMessage) {
        if ($this->mrpRunId) {
            $sql = "UPDATE mrp_runs 
                    SET status = 'failed', 
                        error_log = ?,
                        execution_time_seconds = TIMESTAMPDIFF(SECOND, created_at, NOW())
                    WHERE id = ?";
            
            $this->db->update($sql, [$errorMessage, $this->mrpRunId], ['s', 'i']);
        }
    }
    
    /**
     * Get time-phased MRP report
     */
    public function getTimePhasedReport($itemType, $itemId) {
        // Get latest MRP run
        $sql = "SELECT id FROM mrp_runs 
                WHERE status = 'completed' 
                ORDER BY run_date DESC 
                LIMIT 1";
        
        $latestRun = $this->db->selectOne($sql);
        
        if (!$latestRun) {
            return null;
        }
        
        // This would retrieve stored time-phased data
        // For now, we'll re-calculate for the specific item
        $item = [];
        if ($itemType === 'material') {
            $material = $this->materialModel->find($itemId);
            $item = [
                'type' => 'material',
                'id' => $itemId,
                'code' => $material['material_code'],
                'name' => $material['name'],
                'lead_time' => $material['lead_time_days'],
                'demands' => []
            ];
        } else {
            $product = $this->productModel->find($itemId);
            $item = [
                'type' => 'product',
                'id' => $itemId,
                'code' => $product['product_code'],
                'name' => $product['name'],
                'lead_time' => $product['lead_time_days'],
                'demands' => []
            ];
        }
        
        return $this->calculateTimePhasedRequirements($item);
    }
}