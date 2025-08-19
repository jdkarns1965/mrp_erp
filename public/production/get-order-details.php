<?php
/**
 * Get Customer Order Details API
 * Returns order items for production order creation
 */

require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $orderId = $_GET['id'] ?? null;
    
    if (!$orderId) {
        throw new Exception('Order ID is required');
    }
    
    $db = Database::getInstance();
    
    // Get order details
    $items = $db->select("
        SELECT 
            cod.id,
            cod.product_id,
            cod.quantity,
            cod.unit_price,
            p.product_code,
            p.name as product_name,
            uom.code as uom_code,
            (cod.quantity * cod.unit_price) as line_total
        FROM customer_order_details cod
        JOIN products p ON cod.product_id = p.id
        JOIN units_of_measure uom ON cod.uom_id = uom.id
        WHERE cod.order_id = ?
        ORDER BY cod.id
    ", [$orderId]);
    
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>