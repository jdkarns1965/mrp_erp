<?php
session_start();
require_once '../../classes/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (empty($query)) {
        echo json_encode([]);
        exit;
    }
    
    // Search BOMs by product code, product name, or BOM description
    $sql = "
        SELECT 
            bh.id,
            bh.product_id,
            bh.version,
            bh.description,
            bh.effective_date,
            bh.expiry_date,
            bh.is_active,
            bh.approved_by,
            p.product_code,
            p.name as product_name,
            COUNT(bd.id) as material_count,
            CASE 
                WHEN bh.approved_by IS NOT NULL THEN 'approved'
                ELSE 'draft'
            END as approval_status
        FROM bom_headers bh
        JOIN products p ON bh.product_id = p.id
        LEFT JOIN bom_details bd ON bh.id = bd.bom_header_id
        WHERE (
            p.product_code LIKE ? OR 
            p.name LIKE ? OR 
            bh.description LIKE ?
        )
        AND p.deleted_at IS NULL
        GROUP BY bh.id, bh.product_id, bh.version, bh.description, bh.effective_date, 
                 bh.expiry_date, bh.is_active, bh.approved_by, p.product_code, p.name
        ORDER BY 
            CASE WHEN p.product_code LIKE ? THEN 1 ELSE 2 END,
            p.product_code,
            bh.version DESC
        LIMIT 15
    ";
    
    $searchTerm = '%' . $query . '%';
    $exactSearchTerm = $query . '%'; // For prioritizing exact matches
    
    $boms = $db->select($sql, [
        $searchTerm,      // product_code LIKE
        $searchTerm,      // name LIKE
        $searchTerm,      // description LIKE
        $exactSearchTerm  // ORDER BY priority
    ], ['s', 's', 's', 's']);
    
    $results = [];
    foreach ($boms as $bom) {
        $results[] = [
            'id' => $bom['id'],
            'product_id' => $bom['product_id'],
            'product_code' => $bom['product_code'],
            'product_name' => $bom['product_name'],
            'version' => $bom['version'],
            'description' => $bom['description'],
            'is_active' => (bool)$bom['is_active'],
            'material_count' => (int)$bom['material_count'],
            'approval_status' => $bom['approval_status'],
            'approved_by' => $bom['approved_by'],
            'effective_date' => $bom['effective_date'],
            'label' => $bom['product_code'] . ' v' . $bom['version'] . ' - ' . $bom['product_name'],
            'value' => $bom['product_code'], // For search form submission
            'category' => $bom['is_active'] ? 'Active BOM' : 'Inactive BOM'
        ];
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    error_log("BOM Search API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?>