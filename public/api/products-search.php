<?php
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

// Search products
$searchTerm = '%' . $search . '%';
$query = "
    SELECT 
        p.id,
        p.product_code as code,
        p.name,
        CONCAT(p.product_code, ' - ', p.name) as label,
        p.description,
        CASE WHEN p.is_active = 1 THEN 'active' ELSE 'inactive' END as status,
        pc.name as category,
        u.code as uom,
        p.selling_price as list_price,
        COALESCE(bom_count.bom_count, 0) as bom_count,
        COALESCE(inv_summary.current_stock, 0) as current_stock,
        CASE 
            WHEN p.product_code LIKE ? THEN 100
            WHEN p.name LIKE ? THEN 80
            WHEN pc.name LIKE ? THEN 60
            WHEN p.description LIKE ? THEN 40
            ELSE 20
        END as relevance
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    LEFT JOIN units_of_measure u ON p.uom_id = u.id
    LEFT JOIN (
        SELECT 
            product_id, 
            COUNT(*) as bom_count
        FROM bom_headers 
        WHERE is_active = 1 
        GROUP BY product_id
    ) bom_count ON p.id = bom_count.product_id
    LEFT JOIN (
        SELECT 
            item_id,
            SUM(quantity) as current_stock
        FROM inventory 
        WHERE item_type = 'product'
        GROUP BY item_id
    ) inv_summary ON p.id = inv_summary.item_id
    WHERE (
        p.product_code LIKE ? 
        OR p.name LIKE ?
        OR pc.name LIKE ?
        OR p.description LIKE ?
    )
    AND p.deleted_at IS NULL
    ORDER BY relevance DESC, p.created_at DESC
    LIMIT 20
";

$results = $db->select($query, [
    $search . '%',        // Exact prefix match for code
    $search . '%',        // Exact prefix match for name  
    $searchTerm,          // Contains match for category
    $searchTerm,          // Contains match for description
    $searchTerm,          // Search term for code
    $searchTerm,          // Search term for name
    $searchTerm,          // Search term for category
    $searchTerm           // Search term for description
], 'ssssssss');

// Format for autocomplete
$output = [];
foreach ($results as $row) {
    $output[] = [
        'id' => $row['id'],
        'value' => $row['id'],
        'label' => $row['label'],
        'code' => $row['code'],
        'name' => $row['name'],
        'category' => $row['category'],
        'uom' => $row['uom'],
        'status' => $row['status'],
        'list_price' => $row['list_price'] ? number_format($row['list_price'], 2) : null,
        'bom_count' => $row['bom_count'],
        'current_stock' => $row['current_stock'] ? number_format($row['current_stock'], 2) : '0.00',
        'type' => 'product'
    ];
}

echo json_encode($output);