<?php
require_once '../../classes/Database.php';

header('Content-Type: application/json');

$db = Database::getInstance();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

// Search customer orders
$searchTerm = '%' . $search . '%';
$query = "
    SELECT 
        co.id,
        co.order_number as code,
        co.customer_name as name,
        CONCAT(co.order_number, ' - ', co.customer_name) as label,
        co.status,
        DATE_FORMAT(co.order_date, '%Y-%m-%d') as order_date,
        DATE_FORMAT(co.required_date, '%Y-%m-%d') as required_date,
        COUNT(cod.id) as item_count,
        SUM(cod.quantity * cod.unit_price) as total_amount,
        CASE 
            WHEN co.order_number LIKE ? THEN 100
            WHEN co.customer_name LIKE ? THEN 80
            ELSE 60
        END as relevance
    FROM customer_orders co
    LEFT JOIN customer_order_details cod ON co.id = cod.order_id
    WHERE co.order_number LIKE ? 
       OR co.customer_name LIKE ?
    GROUP BY co.id
    ORDER BY relevance DESC, co.created_at DESC
    LIMIT 20
";

$results = $db->select($query, [
    $search . '%',
    $search . '%',
    $searchTerm,
    $searchTerm
], 'ssss');

// Format for autocomplete
$output = [];
foreach ($results as $row) {
    $output[] = [
        'id' => $row['id'],
        'value' => $row['id'],
        'label' => $row['label'],
        'code' => $row['code'],
        'name' => $row['name'],
        'status' => $row['status'],
        'order_date' => $row['order_date'],
        'required_date' => $row['required_date'],
        'item_count' => $row['item_count'],
        'total_amount' => $row['total_amount'] ? number_format($row['total_amount'], 2) : null,
        'type' => 'order'
    ];
}

echo json_encode($output);