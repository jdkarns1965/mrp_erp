<?php
declare(strict_types=1);

/**
 * Streaming API for Large Inventory Datasets
 * Uses PHP 8.2 generators for memory-efficient data streaming
 */

header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

require_once '../../classes/Database.php';

// Get query parameters
$limit = (int)($_GET['limit'] ?? 100);
$offset = (int)($_GET['offset'] ?? 0);
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$lowStock = isset($_GET['low_stock']);

/**
 * Stream inventory data using generators (PHP 8.2 optimized)
 * @return \Generator
 */
function streamInventoryData(int $limit, int $offset, string $search, string $category, bool $lowStock): \Generator
{
    $db = Database::getInstance()->getConnection();
    
    $conditions = ['1=1'];
    $params = [];
    $types = '';
    
    if ($search) {
        $conditions[] = "(m.material_code LIKE ? OR m.name LIKE ? OR m.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
        $types .= 'sss';
    }
    
    if ($category) {
        $conditions[] = "m.category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    if ($lowStock) {
        $conditions[] = "m.current_stock < m.reorder_point";
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    $query = "SELECT 
                m.id,
                m.material_code,
                m.name,
                m.description,
                m.category,
                m.current_stock,
                m.reorder_point,
                m.safety_stock_qty,
                m.cost_per_unit,
                u.code as uom_code,
                u.name as uom_name,
                CASE 
                    WHEN m.current_stock < m.safety_stock_qty THEN 'critical'
                    WHEN m.current_stock < m.reorder_point THEN 'warning'
                    ELSE 'normal'
                END as stock_status,
                (SELECT COUNT(*) FROM inventory_transactions WHERE material_id = m.id) as transaction_count,
                (SELECT MAX(transaction_date) FROM inventory_transactions WHERE material_id = m.id) as last_movement
              FROM materials m
              LEFT JOIN units_of_measure u ON m.uom_id = u.id
              WHERE {$whereClause}
              ORDER BY m.material_code
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Calculate additional metrics using PHP 8.2 features
        $row['stock_percentage'] = match(true) {
            $row['reorder_point'] == 0 => 100,
            default => round(($row['current_stock'] / $row['reorder_point']) * 100, 2)
        };
        
        $row['stock_value'] = $row['current_stock'] * $row['cost_per_unit'];
        
        // Format dates
        if ($row['last_movement']) {
            $row['last_movement'] = (new DateTime($row['last_movement']))->format('Y-m-d H:i');
        }
        
        yield $row;
    }
    
    $stmt->close();
}

/**
 * Stream aggregated statistics
 */
function streamStatistics(): array
{
    $db = Database::getInstance()->getConnection();
    
    $stats = [];
    
    // Total inventory value
    $result = $db->query("SELECT SUM(current_stock * cost_per_unit) as total_value FROM materials");
    $stats['total_value'] = (float)$result->fetch_assoc()['total_value'];
    
    // Stock status counts using PHP 8.2 match
    $result = $db->query("
        SELECT 
            SUM(CASE WHEN current_stock < safety_stock_qty THEN 1 ELSE 0 END) as critical,
            SUM(CASE WHEN current_stock < reorder_point AND current_stock >= safety_stock_qty THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN current_stock >= reorder_point THEN 1 ELSE 0 END) as normal
        FROM materials
    ");
    $counts = $result->fetch_assoc();
    $stats['status_counts'] = $counts;
    
    return $stats;
}

// Output statistics first
echo json_encode(['type' => 'stats', 'data' => streamStatistics()]) . "\n";

// Stream inventory data
$rowCount = 0;
foreach (streamInventoryData($limit, $offset, $search, $category, $lowStock) as $row) {
    echo json_encode(['type' => 'row', 'data' => $row]) . "\n";
    
    // Flush output every 10 rows for responsive streaming
    if (++$rowCount % 10 === 0) {
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}

// Send end marker
echo json_encode(['type' => 'end', 'count' => $rowCount]) . "\n";