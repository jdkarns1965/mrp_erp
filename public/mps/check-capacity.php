<?php
session_start();
header('Content-Type: application/json');

try {
    require_once '../../classes/Database.php';
    require_once '../../classes/ProductionScheduler.php';
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['mps_data'])) {
        throw new Exception('Invalid JSON data format');
    }
    
    $db = Database::getInstance();
    $scheduler = new ProductionScheduler();
    $mpsEntries = $data['mps_data'];
    
    $capacityIssues = [];
    $totalCapacityUsed = [];
    $totalCapacityAvailable = [];
    
    // Group MPS entries by period
    $entriesByPeriod = [];
    foreach ($mpsEntries as $entry) {
        $periodId = $entry['period_id'];
        if (!isset($entriesByPeriod[$periodId])) {
            $entriesByPeriod[$periodId] = [];
        }
        $entriesByPeriod[$periodId][] = $entry;
    }
    
    // Check capacity for each period
    foreach ($entriesByPeriod as $periodId => $periodEntries) {
        // Get period dates
        $periodSql = "SELECT period_start, period_end, period_name 
                      FROM planning_calendar 
                      WHERE id = ?";
        $period = $db->selectOne($periodSql, [$periodId]);
        
        if (!$period) continue;
        
        // Calculate total capacity available in this period
        $capacitySql = "SELECT 
                            wc.id as work_center_id,
                            wc.name as work_center_name,
                            wc.capacity_units_per_hour as capacity_per_hour,
                            SUM(TIMESTAMPDIFF(HOUR, 
                                GREATEST(wcc.shift_start, ?), 
                                LEAST(wcc.shift_end, ?))) as available_hours
                        FROM work_centers wc
                        LEFT JOIN work_center_calendar wcc ON wc.id = wcc.work_center_id
                        WHERE wcc.date BETWEEN ? AND ?
                          AND wcc.available_hours > 0
                        GROUP BY wc.id";
        
        $workCenters = $db->select($capacitySql, [
            $period['period_start'] . ' 00:00:00',
            $period['period_end'] . ' 23:59:59',
            $period['period_start'],
            $period['period_end']
        ]);
        
        $periodCapacity = [];
        foreach ($workCenters as $wc) {
            $periodCapacity[$wc['work_center_id']] = [
                'name' => $wc['work_center_name'],
                'available_hours' => $wc['available_hours'] ?: 0,
                'capacity_per_hour' => $wc['capacity_per_hour'],
                'total_capacity' => ($wc['available_hours'] ?: 0) * $wc['capacity_per_hour'],
                'used_hours' => 0,
                'used_capacity' => 0
            ];
        }
        
        // Calculate capacity requirements for each product in this period
        foreach ($periodEntries as $entry) {
            $productId = $entry['product_id'];
            $quantity = $entry['firm_planned_qty'];
            
            if ($quantity <= 0) continue;
            
            // Get production route for this product
            $routeSql = "SELECT 
                            pr.work_center_id,
                            pr.operation_name,
                            pr.setup_time_hours,
                            pr.run_time_per_unit,
                            pr.sequence_number,
                            wc.name as work_center_name,
                            wc.capacity_units_per_hour
                        FROM production_routes pr
                        JOIN work_centers wc ON pr.work_center_id = wc.id
                        WHERE pr.product_id = ?
                        ORDER BY pr.sequence_number";
            
            $operations = $db->select($routeSql, [$productId]);
            
            foreach ($operations as $op) {
                $wcId = $op['work_center_id'];
                $totalTimeRequired = $op['setup_time_hours'] + ($op['run_time_per_unit'] * $quantity);
                
                if (!isset($periodCapacity[$wcId])) {
                    // Work center not available in this period
                    $capacityIssues[] = [
                        'period' => $period['period_name'],
                        'product_id' => $productId,
                        'work_center' => $op['work_center_name'],
                        'issue' => 'Work center not available in this period',
                        'required_hours' => $totalTimeRequired,
                        'available_hours' => 0
                    ];
                } else {
                    $periodCapacity[$wcId]['used_hours'] += $totalTimeRequired;
                    $periodCapacity[$wcId]['used_capacity'] += $totalTimeRequired * $op['capacity_units_per_hour'];
                }
            }
        }
        
        // Check for capacity overruns
        foreach ($periodCapacity as $wcId => $capacity) {
            if ($capacity['used_hours'] > $capacity['available_hours']) {
                $utilizationPercent = ($capacity['used_hours'] / max($capacity['available_hours'], 1)) * 100;
                
                $capacityIssues[] = [
                    'period' => $period['period_name'],
                    'work_center' => $capacity['name'],
                    'issue' => 'Capacity exceeded',
                    'required_hours' => round($capacity['used_hours'], 2),
                    'available_hours' => round($capacity['available_hours'], 2),
                    'utilization_percent' => round($utilizationPercent, 1),
                    'overrun_hours' => round($capacity['used_hours'] - $capacity['available_hours'], 2)
                ];
            }
            
            // Store summary data
            if (!isset($totalCapacityUsed[$period['period_name']])) {
                $totalCapacityUsed[$period['period_name']] = 0;
                $totalCapacityAvailable[$period['period_name']] = 0;
            }
            $totalCapacityUsed[$period['period_name']] += $capacity['used_hours'];
            $totalCapacityAvailable[$period['period_name']] += $capacity['available_hours'];
        }
    }
    
    // Calculate overall utilization
    $periodUtilization = [];
    foreach ($totalCapacityUsed as $periodName => $used) {
        $available = $totalCapacityAvailable[$periodName];
        if ($available > 0) {
            $periodUtilization[$periodName] = [
                'used' => round($used, 2),
                'available' => round($available, 2),
                'utilization' => round(($used / $available) * 100, 1)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'has_issues' => count($capacityIssues) > 0,
        'issues' => $capacityIssues,
        'utilization' => $periodUtilization,
        'summary' => [
            'total_issues' => count($capacityIssues),
            'periods_checked' => count($entriesByPeriod),
            'can_proceed' => count($capacityIssues) === 0
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}