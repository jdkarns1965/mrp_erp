<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../classes/Product.php';

$product = new Product();
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$suggestions = [];

if (strlen($searchTerm) >= 1) {
    try {
        // Prepare search patterns
        $searchPattern = '%' . $searchTerm . '%';
        $startsWithPattern = $searchTerm . '%';
        
        $sql = "SELECT 
                    p.id,
                    p.product_code,
                    p.name,
                    pc.name as category_name,
                    uom.code as uom_code
                FROM products p
                LEFT JOIN product_categories pc ON p.category_id = pc.id
                LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
                WHERE (p.product_code LIKE ? OR p.name LIKE ?)
                  AND p.is_active = 1 
                  AND p.deleted_at IS NULL
                ORDER BY 
                    CASE 
                        WHEN p.product_code LIKE ? THEN 1
                        WHEN p.name LIKE ? THEN 2
                        ELSE 3
                    END,
                    LENGTH(p.product_code),
                    p.product_code
                LIMIT 10";
        
        $results = $product->db->select($sql, [
            $startsWithPattern, // WHERE product_code LIKE term%
            $startsWithPattern, // WHERE name LIKE term%
            $startsWithPattern, // ORDER BY product_code LIKE term%
            $startsWithPattern  // ORDER BY name LIKE term%
        ], ['s', 's', 's', 's']);
        
        foreach ($results as $row) {
            $suggestions[] = [
                'id' => $row['id'],
                'value' => $row['product_code'],
                'label' => $row['product_code'] . ' - ' . $row['name'],
                'code' => $row['product_code'],
                'name' => $row['name'],
                'category' => $row['category_name'],
                'uom' => $row['uom_code']
            ];
        }
    } catch (Exception $e) {
        // Return empty array on error
        $suggestions = [];
    }
}

echo json_encode($suggestions);
?>