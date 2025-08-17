<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../classes/Database.php';

$db = Database::getInstance();
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'product'; // 'product' or 'material'
$suggestions = [];

if (strlen($searchTerm) >= 1) {
    try {
        $tableName = $type === 'material' ? 'material_categories' : 'product_categories';
        $searchPattern = '%' . $searchTerm . '%';
        
        $sql = "SELECT 
                    id,
                    name,
                    description
                FROM {$tableName}
                WHERE name LIKE ?
                ORDER BY 
                    CASE WHEN name LIKE ? THEN 1 ELSE 2 END,
                    name
                LIMIT 10";
        
        $startsWithPattern = $searchTerm . '%';
        
        $results = $db->select($sql, [
            $searchPattern,
            $startsWithPattern
        ], ['s', 's']);
        
        foreach ($results as $row) {
            $suggestions[] = [
                'id' => $row['id'],
                'value' => $row['id'],
                'label' => $row['name'],
                'name' => $row['name'],
                'description' => $row['description']
            ];
        }
    } catch (Exception $e) {
        error_log('Categories search error: ' . $e->getMessage());
        $suggestions = [];
    }
}

echo json_encode($suggestions);
?>