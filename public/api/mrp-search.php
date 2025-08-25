<?php
/**
 * MRP Search API
 * Provides search functionality for customer orders and MRP calculations
 */

session_start();
require_once '../../classes/Database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $db = Database::getInstance();
    
    $query = $_GET['q'] ?? '';
    $type = $_GET['type'] ?? 'orders'; // 'orders', 'calculations', or 'all'
    
    if (strlen(trim($query)) < 1) {
        echo json_encode([]);
        exit;
    }
    
    $results = [];
    
    // Search customer orders
    if ($type === 'orders' || $type === 'all') {
        $orders = $db->select("
            SELECT 
                co.id,
                co.order_number as code,
                CONCAT(co.order_number, ' - ', co.customer_name) as name,
                co.customer_name,
                co.order_date,
                co.required_date,
                co.status,
                COUNT(cod.id) as item_count,
                CASE 
                    WHEN co.required_date < CURDATE() THEN 'overdue'
                    WHEN co.required_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'urgent'
                    ELSE 'normal'
                END as priority,
                'order' as search_type
            FROM customer_orders co
            LEFT JOIN customer_order_details cod ON co.id = cod.order_id
            WHERE (
                co.order_number LIKE ? OR 
                co.customer_name LIKE ? OR
                co.status LIKE ?
            )
            AND co.status IN ('pending', 'confirmed', 'in_progress')
            GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date, co.status
            ORDER BY 
                CASE 
                    WHEN co.order_number LIKE ? THEN 1
                    WHEN co.customer_name LIKE ? THEN 2
                    ELSE 3
                END,
                co.required_date ASC
            LIMIT 10
        ", [
            "%$query%", "%$query%", "%$query%", 
            "$query%", "$query%"
        ]);
        
        foreach ($orders as $order) {
            $results[] = [
                'id' => $order['id'],
                'value' => $order['id'],
                'label' => $order['code'] . ' - ' . $order['customer_name'],
                'code' => $order['code'],
                'name' => $order['customer_name'],
                'category' => ucfirst($order['status']) . ' Order',
                'type' => 'order',
                'priority' => $order['priority'],
                'order_date' => $order['order_date'],
                'required_date' => $order['required_date'],
                'item_count' => $order['item_count']
            ];
        }
    }
    
    // Search MRP calculations
    if ($type === 'calculations' || $type === 'all') {
        $calculations = $db->select("
            SELECT 
                mr.order_id,
                co.order_number as code,
                CONCAT(co.order_number, ' - ', co.customer_name, ' (', DATE_FORMAT(mr.calculation_date, '%b %d'), ')') as name,
                co.customer_name,
                mr.calculation_date,
                COUNT(mr.id) as material_count,
                SUM(CASE WHEN mr.net_requirement > 0 THEN 1 ELSE 0 END) as shortage_count,
                'calculation' as search_type
            FROM mrp_requirements mr
            JOIN customer_orders co ON mr.order_id = co.id
            WHERE (
                co.order_number LIKE ? OR 
                co.customer_name LIKE ?
            )
            GROUP BY mr.order_id, co.order_number, co.customer_name, mr.calculation_date
            ORDER BY 
                CASE 
                    WHEN co.order_number LIKE ? THEN 1
                    WHEN co.customer_name LIKE ? THEN 2
                    ELSE 3
                END,
                mr.calculation_date DESC
            LIMIT 10
        ", [
            "%$query%", "%$query%", 
            "$query%", "$query%"
        ]);
        
        foreach ($calculations as $calc) {
            $results[] = [
                'id' => $calc['order_id'],
                'value' => $calc['order_id'],
                'label' => $calc['code'] . ' - ' . $calc['customer_name'] . ' (Calculated)',
                'code' => $calc['code'],
                'name' => $calc['customer_name'],
                'category' => $calc['shortage_count'] > 0 ? 'With Shortages' : 'Complete',
                'type' => 'calculation',
                'calculation_date' => $calc['calculation_date'],
                'material_count' => $calc['material_count'],
                'shortage_count' => $calc['shortage_count']
            ];
        }
    }
    
    // Sort results by relevance (exact matches first, then partial matches)
    usort($results, function($a, $b) use ($query) {
        $queryLower = strtolower($query);
        $aCode = strtolower($a['code']);
        $bCode = strtolower($b['code']);
        
        // Exact code matches first
        if ($aCode === $queryLower && $bCode !== $queryLower) return -1;
        if ($bCode === $queryLower && $aCode !== $queryLower) return 1;
        
        // Code starts with query
        $aStarts = strpos($aCode, $queryLower) === 0;
        $bStarts = strpos($bCode, $queryLower) === 0;
        if ($aStarts && !$bStarts) return -1;
        if ($bStarts && !$aStarts) return 1;
        
        // Orders before calculations
        if ($a['type'] === 'order' && $b['type'] === 'calculation') return -1;
        if ($b['type'] === 'order' && $a['type'] === 'calculation') return 1;
        
        // Alphabetical by code
        return strcmp($aCode, $bCode);
    });
    
    echo json_encode(array_slice($results, 0, 10));
    
} catch (Exception $e) {
    error_log("MRP Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?>