<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../classes/Database.php';

$db = Database::getInstance();
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$suggestions = [];

if (strlen($searchTerm) >= 1) {
    try {
        $searchPattern = '%' . $searchTerm . '%';
        $startsWithPattern = $searchTerm . '%';
        
        $sql = "SELECT 
                    id,
                    code,
                    description,
                    type
                FROM units_of_measure
                WHERE (code LIKE ? OR description LIKE ?)
                ORDER BY 
                    CASE 
                        WHEN code LIKE ? THEN 1
                        WHEN description LIKE ? THEN 2
                        ELSE 3
                    END,
                    code
                LIMIT 10";
        
        $results = $db->select($sql, [
            $searchPattern, 
            $searchPattern,
            $startsWithPattern,
            $startsWithPattern
        ], ['s', 's', 's', 's']);
        
        foreach ($results as $row) {
            $suggestions[] = [
                'id' => $row['id'],
                'value' => $row['id'],
                'label' => $row['code'] . ' - ' . $row['description'],
                'code' => $row['code'],
                'description' => $row['description'],
                'type' => $row['type']
            ];
        }
    } catch (Exception $e) {
        error_log('UOM search error: ' . $e->getMessage());
        $suggestions = [];
    }
}

echo json_encode($suggestions);
?>