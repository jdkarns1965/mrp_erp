<?php
declare(strict_types=1);

/**
 * MRP Calculation with Progress Indicators
 * Uses PHP 8.2 features for real-time progress updates
 */

session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

require_once '../../classes/Database.php';
require_once '../../classes/MRP.php';
require_once '../../classes/Product.php';
require_once '../../classes/BOM.php';

// Prevent timeout
set_time_limit(0);
ob_implicit_flush(true);

// Get parameters
$orderId = (int)($_GET['order_id'] ?? 0);
$productId = (int)($_GET['product_id'] ?? 0);
$quantity = (float)($_GET['quantity'] ?? 0);

if (!$orderId && (!$productId || !$quantity)) {
    sendEvent('error', ['message' => 'Invalid parameters']);
    exit;
}

/**
 * Send progress event to client
 */
function sendEvent(string $event, mixed $data): void
{
    echo "event: {$event}\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

/**
 * Calculate MRP with progress updates using Fibers (PHP 8.2)
 */
function calculateMRPWithProgress(int $orderId = 0, int $productId = 0, float $quantity = 0): \Generator
{
    $db = Database::getInstance();
    $steps = [
        'init' => 'Initializing MRP calculation',
        'load_order' => 'Loading order details',
        'explode_bom' => 'Exploding Bill of Materials',
        'check_inventory' => 'Checking inventory levels',
        'calculate_requirements' => 'Calculating net requirements',
        'generate_suggestions' => 'Generating purchase suggestions',
        'complete' => 'Calculation complete'
    ];
    
    $totalSteps = count($steps);
    $currentStep = 0;
    
    // Initialize
    yield ['step' => 'init', 'progress' => 0, 'message' => $steps['init']];
    $currentStep++;
    
    try {
        // Load order or use direct parameters
        if ($orderId) {
            yield ['step' => 'load_order', 'progress' => round(($currentStep / $totalSteps) * 100), 'message' => $steps['load_order']];
            
            $orderQuery = "SELECT co.*, p.id as product_id, p.name as product_name 
                          FROM customer_orders co
                          JOIN products p ON co.product_id = p.id
                          WHERE co.id = ?";
            $order = $db->selectOne($orderQuery, [$orderId], ['i']);
            
            if (!$order) {
                throw new \Exception('Order not found');
            }
            
            $productId = $order['product_id'];
            $quantity = $order['quantity'];
            $currentStep++;
        }
        
        // Explode BOM
        yield ['step' => 'explode_bom', 'progress' => round(($currentStep / $totalSteps) * 100), 'message' => $steps['explode_bom']];
        
        $bomQuery = "WITH RECURSIVE bom_explosion AS (
                        SELECT 
                            bd.material_id,
                            bd.quantity * ? as required_quantity,
                            1 as level,
                            m.name,
                            m.material_code,
                            m.current_stock,
                            m.reorder_point,
                            m.cost_per_unit
                        FROM bom_headers bh
                        JOIN bom_details bd ON bh.id = bd.bom_header_id
                        JOIN materials m ON bd.material_id = m.id
                        WHERE bh.product_id = ? AND bh.is_active = 1
                        
                        UNION ALL
                        
                        SELECT 
                            bd.material_id,
                            bd.quantity * be.required_quantity,
                            be.level + 1,
                            m.name,
                            m.material_code,
                            m.current_stock,
                            m.reorder_point,
                            m.cost_per_unit
                        FROM bom_explosion be
                        JOIN materials m2 ON be.material_id = m2.id
                        JOIN bom_headers bh ON bh.product_id = m2.id
                        JOIN bom_details bd ON bh.id = bd.bom_header_id
                        JOIN materials m ON bd.material_id = m.id
                        WHERE bh.is_active = 1
                    )
                    SELECT 
                        material_id,
                        name,
                        material_code,
                        SUM(required_quantity) as total_required,
                        MAX(current_stock) as current_stock,
                        MAX(reorder_point) as reorder_point,
                        MAX(cost_per_unit) as cost_per_unit,
                        MAX(level) as max_level
                    FROM bom_explosion
                    GROUP BY material_id, name, material_code
                    ORDER BY max_level DESC, material_code";
        
        $materials = $db->select($bomQuery, [$quantity, $productId], ['d', 'i']);
        $currentStep++;
        
        // Check inventory
        yield ['step' => 'check_inventory', 'progress' => round(($currentStep / $totalSteps) * 100), 'message' => $steps['check_inventory']];
        
        $shortages = [];
        $totalMaterials = count($materials);
        
        foreach ($materials as $index => $material) {
            // Send sub-progress for inventory check
            if ($index % 5 === 0) { // Update every 5 items
                yield [
                    'step' => 'check_inventory',
                    'progress' => round(($currentStep / $totalSteps) * 100),
                    'message' => "Checking {$material['name']} ({$index}/{$totalMaterials})",
                    'sub_progress' => round(($index / $totalMaterials) * 100)
                ];
            }
            
            $netRequired = $material['total_required'] - $material['current_stock'];
            if ($netRequired > 0) {
                $material['shortage'] = $netRequired;
                $material['shortage_cost'] = $netRequired * $material['cost_per_unit'];
                $shortages[] = $material;
            }
        }
        $currentStep++;
        
        // Calculate requirements
        yield ['step' => 'calculate_requirements', 'progress' => round(($currentStep / $totalSteps) * 100), 'message' => $steps['calculate_requirements']];
        
        $totalShortageCount = count($shortages);
        $totalShortageCost = array_sum(array_column($shortages, 'shortage_cost'));
        
        $requirements = [
            'product_id' => $productId,
            'quantity' => $quantity,
            'total_materials' => $totalMaterials,
            'materials_short' => $totalShortageCount,
            'total_shortage_cost' => $totalShortageCost,
            'shortages' => $shortages,
            'all_materials' => $materials
        ];
        $currentStep++;
        
        // Generate purchase suggestions
        yield ['step' => 'generate_suggestions', 'progress' => round(($currentStep / $totalSteps) * 100), 'message' => $steps['generate_suggestions']];
        
        $suggestions = [];
        foreach ($shortages as $shortage) {
            // Calculate suggested order quantity (consider reorder point)
            $suggestedQty = max(
                $shortage['shortage'],
                $shortage['reorder_point'] - $shortage['current_stock'] + $shortage['shortage']
            );
            
            $suggestions[] = [
                'material_id' => $shortage['material_id'],
                'material_code' => $shortage['material_code'],
                'material_name' => $shortage['name'],
                'required_qty' => $shortage['shortage'],
                'suggested_qty' => ceil($suggestedQty),
                'estimated_cost' => $suggestedQty * $shortage['cost_per_unit'],
                'priority' => $shortage['current_stock'] <= 0 ? 'critical' : 'normal'
            ];
        }
        
        $requirements['purchase_suggestions'] = $suggestions;
        $currentStep++;
        
        // Complete
        yield [
            'step' => 'complete',
            'progress' => 100,
            'message' => $steps['complete'],
            'results' => $requirements
        ];
        
    } catch (\Exception $e) {
        yield [
            'step' => 'error',
            'progress' => $currentStep / $totalSteps * 100,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Start calculation
sendEvent('start', ['timestamp' => time()]);

foreach (calculateMRPWithProgress($orderId, $productId, $quantity) as $update) {
    sendEvent('progress', $update);
    
    // Small delay to show progress (remove in production)
    usleep(100000); // 0.1 second
}

sendEvent('end', ['timestamp' => time()]);