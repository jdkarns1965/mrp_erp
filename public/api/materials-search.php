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
                    m.id,
                    m.material_code,
                    m.name,
                    m.material_type,
                    mc.name as category_name,
                    uom.code as uom_code,
                    m.cost_per_unit
                FROM materials m
                LEFT JOIN material_categories mc ON m.category_id = mc.id
                LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
                WHERE (m.material_code LIKE ? OR m.name LIKE ?)
                  AND m.is_active = 1 
                  AND m.deleted_at IS NULL
                ORDER BY 
                    CASE 
                        WHEN m.material_code LIKE ? THEN 1
                        WHEN m.name LIKE ? THEN 2
                        ELSE 3
                    END,
                    LENGTH(m.material_code),
                    m.material_code
                LIMIT 10";
        
        $results = $db->select($sql, [
            $startsWithPattern, // WHERE material_code LIKE term%
            $startsWithPattern, // WHERE name LIKE term%
            $startsWithPattern, // ORDER BY material_code LIKE term%
            $startsWithPattern  // ORDER BY name LIKE term%
        ], ['s', 's', 's', 's']);
        
        foreach ($results as $row) {
            $suggestions[] = [
                'id' => $row['id'],
                'value' => $row['id'],
                'label' => $row['material_code'] . ' - ' . $row['name'],
                'code' => $row['material_code'],
                'name' => $row['name'],
                'type' => $row['material_type'],
                'category' => $row['category_name'],
                'uom' => $row['uom_code'],
                'cost' => (float)$row['cost_per_unit']
            ];
        }
    } catch (Exception $e) {
        error_log('Materials search error: ' . $e->getMessage());
        $suggestions = [];
    }
}

echo json_encode($suggestions);
?>