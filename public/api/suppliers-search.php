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
                    supplier_code,
                    name,
                    contact_person,
                    city,
                    country
                FROM suppliers
                WHERE (supplier_code LIKE ? OR name LIKE ? OR contact_person LIKE ?)
                  AND is_active = 1
                ORDER BY 
                    CASE 
                        WHEN supplier_code LIKE ? THEN 1
                        WHEN name LIKE ? THEN 2
                        ELSE 3
                    END,
                    name
                LIMIT 10";
        
        $results = $db->select($sql, [
            $searchPattern, 
            $searchPattern,
            $searchPattern,
            $startsWithPattern,
            $startsWithPattern
        ], ['s', 's', 's', 's', 's']);
        
        foreach ($results as $row) {
            $location = trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ');
            
            $suggestions[] = [
                'id' => $row['id'],
                'value' => $row['id'],
                'label' => $row['name'],
                'code' => $row['supplier_code'],
                'name' => $row['name'],
                'contact' => $row['contact_person'],
                'location' => $location ?: null
            ];
        }
    } catch (Exception $e) {
        error_log('Suppliers search error: ' . $e->getMessage());
        $suggestions = [];
    }
}

echo json_encode($suggestions);
?>