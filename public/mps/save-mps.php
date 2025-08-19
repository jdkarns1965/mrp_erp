<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../../classes/Database.php';
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['mps_data'])) {
        throw new Exception('Invalid JSON data format');
    }
    
    $db = Database::getInstance();
    $mpsEntries = $data['mps_data'];
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        $savedCount = 0;
        
        // Get all existing MPS entries for the products/periods being updated
        if (!empty($mpsEntries)) {
            $productIds = array_unique(array_column($mpsEntries, 'product_id'));
            $periodIds = array_unique(array_column($mpsEntries, 'period_id'));
            
            $productIdList = implode(',', array_map('intval', $productIds));
            $periodIdList = implode(',', array_map('intval', $periodIds));
            
            // Clear existing entries for these combinations
            $sql = "DELETE FROM master_production_schedule 
                    WHERE product_id IN ($productIdList) AND period_id IN ($periodIdList)";
            $db->execute($sql);
        }
        
        // Insert new entries
        foreach ($mpsEntries as $entry) {
            $productId = intval($entry['product_id']);
            $periodId = intval($entry['period_id']);
            $firmPlannedQty = floatval($entry['firm_planned_qty']);
            
            // Skip zero quantities
            if ($firmPlannedQty <= 0) {
                continue;
            }
            
            // Validate that product and period exist
            $productExists = $db->selectOne("SELECT id FROM products WHERE id = ?", [$productId]);
            $periodExists = $db->selectOne("SELECT id FROM planning_calendar WHERE id = ?", [$periodId]);
            
            if (!$productExists || !$periodExists) {
                throw new Exception("Invalid product ID ($productId) or period ID ($periodId)");
            }
            
            $sql = "INSERT INTO master_production_schedule 
                    (product_id, period_id, firm_planned_qty, demand_qty, status, created_by) 
                    VALUES (?, ?, ?, 0, 'firm', ?)";
            
            $params = [
                $productId,
                $periodId, 
                $firmPlannedQty,
                $_SESSION['user'] ?? 'SYSTEM'
            ];
            
            $db->execute($sql, $params);
            $savedCount++;
        }
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['message'] = "MPS data saved successfully ($savedCount entries)";
        $_SESSION['message_type'] = 'success';
        
        echo json_encode([
            'success' => true,
            'message' => "MPS data saved successfully",
            'entries_saved' => $savedCount
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}