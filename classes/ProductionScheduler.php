<?php
/**
 * Production Scheduler Class
 * Phase 2: Production scheduling and capacity planning
 * Handles forward/backward scheduling, capacity allocation, and Gantt chart data
 */

require_once 'Database.php';

class ProductionScheduler {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create production orders from MRP requirements
     * @param int $customerOrderId Customer order to schedule
     * @param array $options Scheduling options (priority, start_date, etc.)
     * @return array Result with success status and created orders
     */
    public function createProductionOrders($customerOrderId, $options = []) {
        try {
            $this->db->beginTransaction();
            
            // Get customer order details
            $customerOrder = $this->getCustomerOrder($customerOrderId);
            if (!$customerOrder) {
                throw new Exception("Customer order not found: $customerOrderId");
            }
            
            $createdOrders = [];
            $orderNumber = $this->generateProductionOrderNumber();
            
            foreach ($customerOrder['details'] as $detail) {
                $productionOrder = [
                    'order_number' => $orderNumber . '-' . str_pad(count($createdOrders) + 1, 2, '0', STR_PAD_LEFT),
                    'customer_order_id' => $customerOrderId,
                    'product_id' => $detail['product_id'],
                    'quantity_ordered' => $detail['quantity'],
                    'priority_level' => $options['priority'] ?? 'normal',
                    'status' => 'planned',
                    'created_by' => $options['created_by'] ?? 'System'
                ];
                
                $productionOrderId = $this->createProductionOrder($productionOrder);
                $createdOrders[] = $productionOrderId;
                
                // Create material reservations
                $this->createMaterialReservations($productionOrderId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'orders_created' => count($createdOrders),
                'production_order_ids' => $createdOrders,
                'message' => "Created " . count($createdOrders) . " production orders"
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
     * Schedule production orders using forward scheduling
     * @param array $productionOrderIds Array of production order IDs to schedule
     * @param DateTime $startDate Earliest start date
     * @return array Scheduling result
     */
    public function forwardSchedule($productionOrderIds, $startDate = null) {
        try {
            if (!$startDate) {
                $startDate = new DateTime();
            }
            
            $scheduledOrders = [];
            $currentDate = clone $startDate;
            
            foreach ($productionOrderIds as $orderId) {
                $result = $this->scheduleProductionOrder($orderId, $currentDate, 'forward');
                if ($result['success']) {
                    $scheduledOrders[] = $result;
                    // Move current date forward based on the scheduled end date
                    if ($result['scheduled_end_date']) {
                        $endDate = new DateTime($result['scheduled_end_date']);
                        if ($endDate > $currentDate) {
                            $currentDate = $endDate;
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'scheduled_orders' => count($scheduledOrders),
                'orders' => $scheduledOrders,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $currentDate->format('Y-m-d')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule production orders using backward scheduling
     * @param array $productionOrderIds Array of production order IDs to schedule
     * @param DateTime $endDate Latest end date (usually customer required date)
     * @return array Scheduling result
     */
    public function backwardSchedule($productionOrderIds, $endDate) {
        try {
            $scheduledOrders = [];
            $currentDate = clone $endDate;
            
            // Schedule in reverse order
            foreach (array_reverse($productionOrderIds) as $orderId) {
                $result = $this->scheduleProductionOrder($orderId, $currentDate, 'backward');
                if ($result['success']) {
                    $scheduledOrders[] = $result;
                    // Move current date backward based on the scheduled start date
                    if ($result['scheduled_start_date']) {
                        $startDate = new DateTime($result['scheduled_start_date']);
                        if ($startDate < $currentDate) {
                            $currentDate = $startDate;
                        }
                    }
                }
            }
            
            return [
                'success' => true,
                'scheduled_orders' => count($scheduledOrders),
                'orders' => array_reverse($scheduledOrders), // Return in original order
                'start_date' => $currentDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule a single production order
     * @param int $productionOrderId Production order ID
     * @param DateTime $referenceDate Reference date for scheduling
     * @param string $direction 'forward' or 'backward'
     * @return array Scheduling result
     */
    private function scheduleProductionOrder($productionOrderId, $referenceDate, $direction = 'forward') {
        try {
            // Get production order details
            $productionOrder = $this->getProductionOrder($productionOrderId);
            if (!$productionOrder) {
                throw new Exception("Production order not found: $productionOrderId");
            }
            
            // Get production routes for this product
            $routes = $this->getProductionRoutes($productionOrder['product_id']);
            if (empty($routes)) {
                throw new Exception("No production routes found for product: " . $productionOrder['product_id']);
            }
            
            $operations = [];
            $totalDuration = 0;
            
            // Calculate operation times and find available capacity
            foreach ($routes as $route) {
                $operationDuration = $this->calculateOperationDuration($route, $productionOrder['quantity_ordered']);
                $totalDuration += $operationDuration;
                
                $availableSlot = $this->findAvailableCapacity(
                    $route['work_center_id'], 
                    $referenceDate, 
                    $operationDuration, 
                    $direction
                );
                
                if (!$availableSlot) {
                    throw new Exception("No available capacity for work center: " . $route['work_center_code']);
                }
                
                $operations[] = [
                    'route_id' => $route['id'],
                    'work_center_id' => $route['work_center_id'],
                    'operation_sequence' => $route['operation_sequence'],
                    'scheduled_start_datetime' => $availableSlot['start'],
                    'scheduled_end_datetime' => $availableSlot['end'],
                    'quantity_to_produce' => $productionOrder['quantity_ordered']
                ];
                
                // Update reference date for next operation
                if ($direction === 'forward') {
                    $referenceDate = new DateTime($availableSlot['end']);
                } else {
                    $referenceDate = new DateTime($availableSlot['start']);
                }
            }
            
            // Update production order with calculated dates
            $startDate = $direction === 'forward' ? $operations[0]['scheduled_start_datetime'] : $operations[count($operations)-1]['scheduled_start_datetime'];
            $endDate = $direction === 'forward' ? $operations[count($operations)-1]['scheduled_end_datetime'] : $operations[0]['scheduled_end_datetime'];
            
            $this->updateProductionOrderSchedule($productionOrderId, $startDate, $endDate);
            
            // Create operation records
            foreach ($operations as $operation) {
                $this->createProductionOrderOperation($productionOrderId, $operation);
            }
            
            return [
                'success' => true,
                'production_order_id' => $productionOrderId,
                'scheduled_start_date' => substr($startDate, 0, 10),
                'scheduled_end_date' => substr($endDate, 0, 10),
                'total_duration_hours' => round($totalDuration / 60, 2),
                'operations_count' => count($operations)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get work center capacity for Gantt chart visualization
     * @param DateTime $startDate Start date for capacity view
     * @param DateTime $endDate End date for capacity view
     * @return array Capacity data for Gantt chart
     */
    public function getWorkCenterCapacity($startDate, $endDate) {
        $sql = "
            SELECT 
                wc.id,
                wc.code,
                wc.name,
                wc.work_center_type,
                DATE(wcc.date) as date,
                wcc.available_hours,
                wcc.planned_downtime_hours,
                (wcc.available_hours - wcc.planned_downtime_hours) as effective_hours,
                COALESCE(SUM(TIMESTAMPDIFF(MINUTE, poo.scheduled_start_datetime, poo.scheduled_end_datetime)) / 60, 0) as scheduled_hours,
                ROUND(((COALESCE(SUM(TIMESTAMPDIFF(MINUTE, poo.scheduled_start_datetime, poo.scheduled_end_datetime)) / 60, 0)) / 
                       (wcc.available_hours - wcc.planned_downtime_hours)) * 100, 1) as utilization_percentage
            FROM work_centers wc
            LEFT JOIN work_center_calendar wcc ON wc.id = wcc.work_center_id
            LEFT JOIN production_order_operations poo ON wc.id = poo.work_center_id 
                AND DATE(poo.scheduled_start_datetime) = wcc.date
                AND poo.status NOT IN ('cancelled')
            WHERE wc.is_active = TRUE
                AND wcc.date BETWEEN ? AND ?
            GROUP BY wc.id, wc.code, wc.name, wc.work_center_type, wcc.date, 
                     wcc.available_hours, wcc.planned_downtime_hours
            ORDER BY wc.code, wcc.date
        ";
        
        return $this->db->select($sql, [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    }
    
    /**
     * Get production schedule for Gantt chart
     * @param DateTime $startDate Start date for schedule view
     * @param DateTime $endDate End date for schedule view
     * @return array Production schedule data
     */
    public function getProductionSchedule($startDate, $endDate) {
        $sql = "
            SELECT 
                po.id as production_order_id,
                po.order_number,
                po.product_id,
                p.product_code,
                p.name as product_name,
                po.quantity_ordered,
                po.priority_level,
                po.status as order_status,
                poo.id as operation_id,
                poo.operation_sequence,
                pr.operation_description,
                wc.code as work_center_code,
                wc.name as work_center_name,
                poo.scheduled_start_datetime,
                poo.scheduled_end_datetime,
                poo.status as operation_status,
                TIMESTAMPDIFF(MINUTE, poo.scheduled_start_datetime, poo.scheduled_end_datetime) as duration_minutes
            FROM production_orders po
            JOIN products p ON po.product_id = p.id
            JOIN production_order_operations poo ON po.id = poo.production_order_id
            JOIN production_routes pr ON poo.route_id = pr.id
            JOIN work_centers wc ON poo.work_center_id = wc.id
            WHERE po.status NOT IN ('completed', 'cancelled')
                AND poo.scheduled_start_datetime BETWEEN ? AND ?
            ORDER BY wc.code, poo.scheduled_start_datetime, poo.operation_sequence
        ";
        
        return $this->db->select($sql, [
            $startDate->format('Y-m-d H:i:s'), 
            $endDate->format('Y-m-d 23:59:59')
        ]);
    }
    
    /**
     * Calculate operation duration including setup, run time, and teardown
     * @param array $route Production route data
     * @param float $quantity Quantity to produce
     * @return float Duration in minutes
     */
    private function calculateOperationDuration($route, $quantity) {
        $setupTime = $route['setup_time_minutes'] ?? 0;
        $runTimePerUnit = $route['run_time_per_unit_seconds'] ?? 0;
        $teardownTime = $route['teardown_time_minutes'] ?? 0;
        
        $totalRunTime = ($runTimePerUnit * $quantity) / 60; // Convert to minutes
        
        return $setupTime + $totalRunTime + $teardownTime;
    }
    
    /**
     * Find available capacity slot for an operation
     * @param int $workCenterId Work center ID
     * @param DateTime $referenceDate Reference date for scheduling
     * @param float $durationMinutes Required duration in minutes
     * @param string $direction 'forward' or 'backward'
     * @return array|null Available slot with start/end times
     */
    private function findAvailableCapacity($workCenterId, $referenceDate, $durationMinutes, $direction = 'forward') {
        // Get work center calendar for the reference date
        $calendar = $this->getWorkCenterCalendar($workCenterId, $referenceDate);
        if (!$calendar) {
            return null;
        }
        
        // Get existing scheduled operations for this work center on this date
        $existingOperations = $this->getScheduledOperations($workCenterId, $referenceDate);
        
        // Find available time slot
        $shiftStart = new DateTime($referenceDate->format('Y-m-d') . ' ' . $calendar['shift_start']);
        $shiftEnd = new DateTime($referenceDate->format('Y-m-d') . ' ' . $calendar['shift_end']);
        
        // For simplicity, schedule at shift start if no conflicts
        // In a full implementation, this would check for gaps between existing operations
        if (empty($existingOperations)) {
            if ($direction === 'forward') {
                $startTime = max($shiftStart, $referenceDate);
                $endTime = clone $startTime;
                $endTime->add(new DateInterval('PT' . ceil($durationMinutes) . 'M'));
            } else {
                $endTime = min($shiftEnd, $referenceDate);
                $startTime = clone $endTime;
                $startTime->sub(new DateInterval('PT' . ceil($durationMinutes) . 'M'));
            }
            
            return [
                'start' => $startTime->format('Y-m-d H:i:s'),
                'end' => $endTime->format('Y-m-d H:i:s')
            ];
        }
        
        // If there are conflicts, move to next available day
        $nextDate = clone $referenceDate;
        if ($direction === 'forward') {
            $nextDate->add(new DateInterval('P1D'));
        } else {
            $nextDate->sub(new DateInterval('P1D'));
        }
        
        return $this->findAvailableCapacity($workCenterId, $nextDate, $durationMinutes, $direction);
    }
    
    // Helper methods
    private function getCustomerOrder($orderId) {
        $sql = "
            SELECT co.*, cod.id as detail_id, cod.product_id, cod.quantity, cod.uom_id
            FROM customer_orders co
            JOIN customer_order_details cod ON co.id = cod.order_id
            WHERE co.id = ?
        ";
        
        $rows = $this->db->select($sql, [$orderId]);
        if (empty($rows)) return null;
        
        $order = [
            'id' => $rows[0]['id'],
            'order_number' => $rows[0]['order_number'],
            'customer_name' => $rows[0]['customer_name'],
            'required_date' => $rows[0]['required_date'],
            'details' => []
        ];
        
        foreach ($rows as $row) {
            $order['details'][] = [
                'detail_id' => $row['detail_id'],
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'uom_id' => $row['uom_id']
            ];
        }
        
        return $order;
    }
    
    private function generateProductionOrderNumber() {
        $prefix = 'PO-' . date('Y');
        $sql = "SELECT MAX(CAST(SUBSTRING(order_number, 8) AS UNSIGNED)) as max_num 
                FROM production_orders 
                WHERE order_number LIKE ?";
        
        $result = $this->db->select($sql, [$prefix . '%']);
        $nextNum = ($result[0]['max_num'] ?? 0) + 1;
        
        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }
    
    private function createProductionOrder($data) {
        $sql = "
            INSERT INTO production_orders 
            (order_number, customer_order_id, product_id, quantity_ordered, priority_level, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->db->insert($sql, [
            $data['order_number'],
            $data['customer_order_id'],
            $data['product_id'],
            $data['quantity_ordered'],
            $data['priority_level'],
            $data['status'],
            $data['created_by']
        ]);
    }
    
    private function createMaterialReservations($productionOrderId) {
        // Get BOM for this production order's product
        $sql = "
            SELECT po.product_id, po.quantity_ordered,
                   bd.material_id, bd.quantity_per, bd.scrap_percentage
            FROM production_orders po
            JOIN bom_headers bh ON po.product_id = bh.product_id AND bh.is_active = TRUE
            JOIN bom_details bd ON bh.id = bd.bom_header_id
            WHERE po.id = ?
        ";
        
        $bomDetails = $this->db->select($sql, [$productionOrderId]);
        
        foreach ($bomDetails as $detail) {
            $quantityRequired = $detail['quantity_ordered'] * $detail['quantity_per'] * (1 + ($detail['scrap_percentage'] / 100));
            
            $materialSql = "
                INSERT INTO production_order_materials 
                (production_order_id, material_id, quantity_required)
                VALUES (?, ?, ?)
            ";
            
            $this->db->insert($materialSql, [$productionOrderId, $detail['material_id'], $quantityRequired]);
        }
    }
    
    private function getProductionOrder($id) {
        $sql = "SELECT * FROM production_orders WHERE id = ?";
        $result = $this->db->select($sql, [$id]);
        return $result ? $result[0] : null;
    }
    
    private function getProductionRoutes($productId) {
        $sql = "
            SELECT pr.*, wc.code as work_center_code, wc.name as work_center_name
            FROM production_routes pr
            JOIN work_centers wc ON pr.work_center_id = wc.id
            WHERE pr.product_id = ? AND pr.is_active = TRUE AND wc.is_active = TRUE
            ORDER BY pr.operation_sequence
        ";
        
        return $this->db->select($sql, [$productId]);
    }
    
    private function updateProductionOrderSchedule($id, $startDate, $endDate) {
        $sql = "
            UPDATE production_orders 
            SET scheduled_start_date = DATE(?), scheduled_end_date = DATE(?)
            WHERE id = ?
        ";
        
        $this->db->update($sql, [$startDate, $endDate, $id]);
    }
    
    private function createProductionOrderOperation($productionOrderId, $operation) {
        $sql = "
            INSERT INTO production_order_operations 
            (production_order_id, route_id, work_center_id, operation_sequence, 
             scheduled_start_datetime, scheduled_end_datetime, quantity_to_produce)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        return $this->db->insert($sql, [
            $productionOrderId,
            $operation['route_id'],
            $operation['work_center_id'],
            $operation['operation_sequence'],
            $operation['scheduled_start_datetime'],
            $operation['scheduled_end_datetime'],
            $operation['quantity_to_produce']
        ]);
    }
    
    private function getWorkCenterCalendar($workCenterId, $date) {
        $sql = "
            SELECT * FROM work_center_calendar 
            WHERE work_center_id = ? AND date = ?
        ";
        
        $result = $this->db->select($sql, [$workCenterId, $date->format('Y-m-d')]);
        return $result ? $result[0] : null;
    }
    
    private function getScheduledOperations($workCenterId, $date) {
        $sql = "
            SELECT * FROM production_order_operations 
            WHERE work_center_id = ? 
            AND DATE(scheduled_start_datetime) = ?
            AND status NOT IN ('cancelled')
            ORDER BY scheduled_start_datetime
        ";
        
        return $this->db->select($sql, [$workCenterId, $date->format('Y-m-d')]);
    }
}
?>