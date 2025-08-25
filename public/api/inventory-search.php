<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once '../../classes/Database.php';

// Get query parameter
$query = $_GET['q'] ?? '';
$query = trim($query);

// Return empty array if query is too short
if (strlen($query) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Enhanced query to search inventory with intelligent ranking
    $sql = "SELECT 
                i.id,
                i.item_id,
                i.item_type,
                i.lot_number,
                i.quantity,
                i.reserved_quantity,
                i.unit_cost,
                i.expiry_date,
                CASE 
                    WHEN i.item_type = 'material' THEN m.material_code
                    WHEN i.item_type = 'product' THEN p.product_code
                END AS code,
                CASE 
                    WHEN i.item_type = 'material' THEN m.name
                    WHEN i.item_type = 'product' THEN p.name
                END AS name,
                CASE 
                    WHEN i.item_type = 'material' THEN mc.name
                    WHEN i.item_type = 'product' THEN pc.name
                END AS category,
                sl.code as location_code,
                uom.code as uom_code,
                s.name as supplier_name,
                CASE 
                    WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= CURRENT_DATE THEN 'expired'
                    WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'expiring'
                    WHEN (i.quantity - i.reserved_quantity) <= 0 THEN 'out_of_stock'
                    WHEN (i.quantity - i.reserved_quantity) <= 10 THEN 'low_stock'
                    ELSE 'normal'
                END AS stock_status,
                -- Ranking logic for intelligent search results
                CASE
                    WHEN (i.item_type = 'material' AND m.material_code LIKE ?) THEN 1
                    WHEN (i.item_type = 'product' AND p.product_code LIKE ?) THEN 1
                    WHEN i.lot_number LIKE ? THEN 2
                    WHEN (i.item_type = 'material' AND m.name LIKE ?) THEN 3
                    WHEN (i.item_type = 'product' AND p.name LIKE ?) THEN 3
                    WHEN sl.code LIKE ? THEN 4
                    ELSE 5
                END AS search_rank
            FROM inventory i
            LEFT JOIN materials m ON i.item_type = 'material' AND i.item_id = m.id
            LEFT JOIN products p ON i.item_type = 'product' AND i.item_id = p.id
            LEFT JOIN material_categories mc ON i.item_type = 'material' AND m.category_id = mc.id
            LEFT JOIN product_categories pc ON i.item_type = 'product' AND p.category_id = pc.id
            LEFT JOIN storage_locations sl ON i.location_id = sl.id
            LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
            LEFT JOIN suppliers s ON i.supplier_id = s.id
            WHERE i.status = 'available'
            AND (
                (i.item_type = 'material' AND (m.material_code LIKE ? OR m.name LIKE ?)) OR
                (i.item_type = 'product' AND (p.product_code LIKE ? OR p.name LIKE ?)) OR
                i.lot_number LIKE ? OR
                sl.code LIKE ?
            )
            ORDER BY search_rank ASC, 
                     CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 0 ELSE 1 END,
                     i.expiry_date ASC,
                     name ASC
            LIMIT 15";
    
    $searchTerm = "%{$query}%";
    $exactMatch = $query . "%";
    
    $params = [
        $exactMatch, // material_code exact match
        $exactMatch, // product_code exact match  
        $searchTerm, // lot_number
        $searchTerm, // material name
        $searchTerm, // product name
        $searchTerm, // location code
        $searchTerm, // material search
        $searchTerm, // material name search
        $searchTerm, // product search  
        $searchTerm, // product name search
        $searchTerm, // lot number search
        $searchTerm  // location search
    ];
    
    $types = array_fill(0, 12, 's');
    $results = $db->select($sql, $params, $types);
    
    // Format results for autocomplete
    $autocompleteResults = [];
    foreach ($results as $row) {
        $available = (float)$row['quantity'] - (float)$row['reserved_quantity'];
        $totalValue = (float)$row['quantity'] * (float)($row['unit_cost'] ?? 0);
        
        $label = $row['code'] . ' - ' . $row['name'];
        if ($row['lot_number']) {
            $label .= ' (Lot: ' . $row['lot_number'] . ')';
        }
        
        $autocompleteResults[] = [
            'id' => $row['id'],
            'value' => $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'label' => $label,
            'category' => $row['category'] ?? 'N/A',
            'item_type' => ucfirst($row['item_type']),
            'lot_number' => $row['lot_number'],
            'location' => $row['location_code'],
            'quantity' => number_format((float)$row['quantity'], 2),
            'available' => number_format($available, 2),
            'unit_cost' => number_format((float)($row['unit_cost'] ?? 0), 2),
            'total_value' => number_format($totalValue, 2),
            'uom' => $row['uom_code'],
            'supplier' => $row['supplier_name'],
            'stock_status' => $row['stock_status'],
            'expiry_date' => $row['expiry_date'] ? date('M j, Y', strtotime($row['expiry_date'])) : null,
            'search_rank' => $row['search_rank']
        ];
    }
    
    echo json_encode($autocompleteResults);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}