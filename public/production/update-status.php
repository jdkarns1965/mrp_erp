<?php
/**
 * Update Production Order Status API
 * Updates production order status and logs changes
 */

require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = $input['order_id'] ?? null;
    $newStatus = $input['new_status'] ?? null;
    $reason = $input['reason'] ?? '';
    $changedBy = $input['changed_by'] ?? 'User';
    
    if (!$orderId || !$newStatus) {
        throw new Exception('Order ID and new status are required');
    }
    
    // Validate status
    $validStatuses = ['planned', 'released', 'in_progress', 'completed', 'cancelled', 'on_hold'];
    if (!in_array($newStatus, $validStatuses)) {
        throw new Exception('Invalid status value');
    }
    
    $db = Database::getInstance();
    $db->beginTransaction();
    
    // Get current status
    $currentOrder = $db->select("SELECT status FROM production_orders WHERE id = ?", [$orderId]);
    if (empty($currentOrder)) {
        throw new Exception('Production order not found');
    }
    
    $oldStatus = $currentOrder[0]['status'];
    
    // Validate status transition
    $validTransitions = [
        'planned' => ['released', 'cancelled', 'on_hold'],
        'released' => ['in_progress', 'on_hold', 'cancelled'],
        'in_progress' => ['completed', 'on_hold', 'cancelled'],
        'on_hold' => ['planned', 'released', 'in_progress', 'cancelled'],
        'completed' => [], // Cannot change from completed
        'cancelled' => [] // Cannot change from cancelled
    ];
    
    if (!in_array($newStatus, $validTransitions[$oldStatus] ?? [])) {
        throw new Exception("Cannot change status from '$oldStatus' to '$newStatus'");
    }
    
    // Update production order status
    $db->update("
        UPDATE production_orders 
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ", [$newStatus, $orderId]);
    
    // Log status change
    $db->insert("
        INSERT INTO production_order_status_history 
        (production_order_id, old_status, new_status, changed_by, changed_at, reason)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ", [$orderId, $oldStatus, $newStatus, $changedBy, $reason]);
    
    // Handle special status changes
    if ($newStatus === 'in_progress' && $oldStatus !== 'in_progress') {
        // Set actual start date
        $db->update("
            UPDATE production_orders 
            SET actual_start_date = CURDATE()
            WHERE id = ? AND actual_start_date IS NULL
        ", [$orderId]);
    }
    
    if ($newStatus === 'completed') {
        // Set actual end date and update quantities if not set
        $db->update("
            UPDATE production_orders 
            SET actual_end_date = CURDATE(),
                quantity_completed = CASE 
                    WHEN quantity_completed = 0 THEN quantity_ordered 
                    ELSE quantity_completed 
                END
            WHERE id = ?
        ", [$orderId]);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Status changed from '$oldStatus' to '$newStatus'"
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>