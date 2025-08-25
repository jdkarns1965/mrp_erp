<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/search-component.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';

// Get recent MRP calculations with search and filter
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (co.order_number LIKE ? OR co.customer_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter === 'with_shortages') {
    $whereClause .= " AND SUM(CASE WHEN mr.net_requirement > 0 THEN 1 ELSE 0 END) > 0";
}

$recentCalculations = $db->select("
    SELECT 
        mr.order_id,
        co.order_number,
        co.customer_name,
        mr.calculation_date,
        COUNT(mr.id) as material_count,
        SUM(CASE WHEN mr.net_requirement > 0 THEN 1 ELSE 0 END) as shortage_count
    FROM mrp_requirements mr
    JOIN customer_orders co ON mr.order_id = co.id
    GROUP BY mr.order_id, co.order_number, co.customer_name, mr.calculation_date
    " . ($statusFilter === 'with_shortages' ? 'HAVING shortage_count > 0' : '') . "
    ORDER BY mr.calculation_date DESC
    LIMIT 50
", $params);

// Get pending orders with search
$pendingWhereClause = "WHERE co.status IN ('pending', 'confirmed')";
$pendingParams = [];

if ($search) {
    $pendingWhereClause .= " AND (co.order_number LIKE ? OR co.customer_name LIKE ?)";
    $pendingParams[] = "%$search%";
    $pendingParams[] = "%$search%";
}

$pendingOrders = $db->select("
    SELECT 
        co.id,
        co.order_number,
        co.customer_name,
        co.order_date,
        co.required_date,
        COUNT(cod.id) as item_count,
        CASE 
            WHEN co.required_date < CURDATE() THEN 'overdue'
            WHEN co.required_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'urgent'
            ELSE 'normal'
        END as priority
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id
    $pendingWhereClause
    GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date
    ORDER BY 
        CASE 
            WHEN co.required_date < CURDATE() THEN 1
            WHEN co.required_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
            ELSE 3
        END,
        co.required_date ASC
    LIMIT 50
", $pendingParams);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" style="padding-top: 2rem;">
    <!-- Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">MRP (Material Requirements Planning)</h1>
                    <p class="mt-1 text-sm text-gray-600">Calculate material requirements and identify shortages for customer orders</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="run.php" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v1a2 2 0 002 2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v3.5M9 9h3m-3 0v3h3m-3-3V6"></path>
                        </svg>
                        Basic MRP
                    </a>
                    <a href="run-enhanced.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Enhanced MRP
                    </a>
                </div>
            </div>
        </div>

        <!-- Enhanced MRP Features Alert -->
        <div class="px-6 py-4 bg-blue-50 border-b border-blue-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-blue-800">Enhanced MRP Available</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p class="mb-2">Advanced features now include:</p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Time-phased planning with lead time offsetting</li>
                            <li>Master Production Schedule (MPS) integration</li>
                            <li>Lot sizing rules (Fixed, Lot-for-Lot, Min-Max, EOQ)</li>
                            <li>Safety stock management and purchase order suggestions</li>
                        </ul>
                    </div>
                    <div class="mt-3">
                        <div class="-mx-2 -my-1.5 flex">
                            <a href="run-enhanced.php" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                Try Enhanced MRP
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Component -->
        <div class="px-6 py-4">
            <?php 
            echo renderSearchComponent([
                'entity' => 'mrp',
                'placeholder' => 'Search orders by number or customer name...',
                'current_search' => $search,
                'show_filters' => [
                    [
                        'name' => 'status_filter',
                        'value' => 'with_shortages',
                        'label' => 'Show only orders with shortages',
                        'onchange' => 'this.form.submit();'
                    ]
                ]
            ]);
            ?>
        </div>
    </div>

    <!-- Pending Orders -->
    <?php if (!empty($pendingOrders)): ?>
    <div class="space-y-6">
        <!-- Pending Orders List -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">Pending Orders Ready for MRP</h2>
                        <p class="text-sm text-gray-500"><?php echo count($pendingOrders); ?> orders found</p>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="overflow-x-auto pb-2 -mx-2" style="padding-top: 0.5rem; margin-top: 0.25rem;">
                        <div class="flex space-x-2 px-2 min-w-max" style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                            <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 filter-btn active" data-filter="all" onclick="filterOrders('all')">
                                <span class="whitespace-nowrap">All Orders</span>
                            </button>
                            <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-red-200 text-red-800 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200 filter-btn" data-filter="overdue" onclick="filterOrders('overdue')">
                                <span class="whitespace-nowrap">Overdue</span>
                                <?php 
                                $overdueCount = array_filter($pendingOrders, fn($o) => $o['priority'] === 'overdue');
                                if (count($overdueCount) > 0): ?>
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-red-200 text-red-900">
                                        <?php echo count($overdueCount); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-yellow-200 text-yellow-800 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 filter-btn" data-filter="urgent" onclick="filterOrders('urgent')">
                                <span class="whitespace-nowrap">Due Soon</span>
                                <?php 
                                $urgentCount = array_filter($pendingOrders, fn($o) => $o['priority'] === 'urgent');
                                if (count($urgentCount) > 0): ?>
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-yellow-200 text-yellow-900">
                                        <?php echo count($urgentCount); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders List -->
            <div class="divide-y divide-gray-200" id="ordersList">
                <?php foreach ($pendingOrders as $order): ?>
                <?php 
                $priorityClasses = [
                    'overdue' => 'border-l-4 border-red-500 bg-red-50',
                    'urgent' => 'border-l-4 border-yellow-500 bg-yellow-50',
                    'normal' => 'border-l-4 border-transparent'
                ];
                
                $priorityDotClasses = [
                    'overdue' => 'bg-red-400',
                    'urgent' => 'bg-yellow-400',
                    'normal' => 'bg-green-400'
                ];
                ?>
                
                <div class="<?php echo $priorityClasses[$order['priority']]; ?> hover:bg-gray-50 transition-colors duration-200 list-item" 
                     data-id="<?php echo $order['id']; ?>" 
                     data-priority="<?php echo $order['priority']; ?>"
                     data-customer="<?php echo strtolower($order['customer_name']); ?>"
                     data-order="<?php echo strtolower($order['order_number']); ?>">
                     
                    <div class="px-4 sm:px-6 py-4">
                        <!-- Mobile-optimized layout -->
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <!-- Row 1: Order Identity -->
                            <div class="flex items-center space-x-3 flex-1 min-w-0">
                                <!-- Priority Status Dot -->
                                <div class="flex-shrink-0">
                                    <span class="inline-block h-3 w-3 rounded-full <?php echo $priorityDotClasses[$order['priority']]; ?>"></span>
                                </div>
                                
                                <!-- Order Details -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <p class="text-sm font-semibold text-gray-900 truncate">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </p>
                                        <?php if ($order['priority'] === 'overdue'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Overdue
                                        </span>
                                        <?php elseif ($order['priority'] === 'urgent'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Due Soon
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-gray-900 font-medium mb-1 line-clamp-1">
                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </p>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                        <span>Order Date: <?php echo date('M j, Y', strtotime($order['order_date'])); ?></span>
                                        <span>Items: <?php echo $order['item_count']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Row 2: Date Metrics -->
                            <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-6 text-right sm:flex-shrink-0">
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-500 mb-1">Required Date</p>
                                    <p class="text-sm font-semibold truncate <?php echo $order['priority'] === 'overdue' ? 'text-red-600' : ($order['priority'] === 'urgent' ? 'text-yellow-600' : 'text-gray-900'); ?>">
                                        <?php echo date('M j, Y', strtotime($order['required_date'])); ?>
                                    </p>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-medium text-gray-500 mb-1">Days Until Due</p>
                                    <p class="text-sm text-gray-600 truncate">
                                        <?php 
                                        $daysUntil = ceil((strtotime($order['required_date']) - time()) / (60*60*24));
                                        if ($daysUntil < 0) {
                                            echo abs($daysUntil) . " days overdue";
                                        } else {
                                            echo $daysUntil . " days";
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Row 3: Actions -->
                            <div class="flex items-center justify-end space-x-2 sm:flex-shrink-0">
                                <a href="run.php?order_id=<?php echo $order['id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Run MRP
                                </a>
                                <a href="../orders/view.php?id=<?php echo $order['id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent MRP Calculations -->
    <?php if (!empty($recentCalculations)): ?>
    <!-- Recent Calculations List -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-medium text-gray-900">Recent MRP Calculations</h2>
                    <p class="text-sm text-gray-500"><?php echo count($recentCalculations); ?> calculations found</p>
                </div>
                
                <!-- Filter Buttons -->
                <div class="overflow-x-auto pb-2 -mx-2" style="padding-top: 0.5rem; margin-top: 0.25rem;">
                    <div class="flex space-x-2 px-2 min-w-max" style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 filter-btn active" data-filter="all" onclick="filterCalculations('all')">
                            <span class="whitespace-nowrap">All Calculations</span>
                        </button>
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-yellow-200 text-yellow-800 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 filter-btn" data-filter="with-shortages" onclick="filterCalculations('with-shortages')">
                            <span class="whitespace-nowrap">With Shortages</span>
                            <?php 
                            $withShortages = array_filter($recentCalculations, fn($c) => $c['shortage_count'] > 0);
                            if (count($withShortages) > 0): ?>
                                <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-yellow-200 text-yellow-900">
                                    <?php echo count($withShortages); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-green-200 text-green-800 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200 filter-btn" data-filter="no-shortages" onclick="filterCalculations('no-shortages')">
                            <span class="whitespace-nowrap">No Shortages</span>
                            <?php 
                            $noShortages = array_filter($recentCalculations, fn($c) => $c['shortage_count'] == 0);
                            if (count($noShortages) > 0): ?>
                                <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-green-200 text-green-900">
                                    <?php echo count($noShortages); ?>
                                </span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calculations List -->
        <div class="divide-y divide-gray-200" id="calculationsList">
            <?php foreach ($recentCalculations as $calc): ?>
            <?php 
            $shortageLevel = 'good';
            if ($calc['shortage_count'] > 5) {
                $shortageLevel = 'critical';
            } elseif ($calc['shortage_count'] > 0) {
                $shortageLevel = 'warning';
            }
            
            $shortageLevelClasses = [
                'critical' => 'border-l-4 border-red-500 bg-red-50',
                'warning' => 'border-l-4 border-yellow-500 bg-yellow-50',
                'good' => 'border-l-4 border-green-500 bg-green-50'
            ];
            
            $shortageDotClasses = [
                'critical' => 'bg-red-400',
                'warning' => 'bg-yellow-400', 
                'good' => 'bg-green-400'
            ];
            ?>
            
            <div class="<?php echo $shortageLevelClasses[$shortageLevel]; ?> hover:bg-gray-50 transition-colors duration-200 list-item" 
                 data-id="<?php echo $calc['order_id']; ?>" 
                 data-shortage-level="<?php echo $shortageLevel; ?>"
                 data-customer="<?php echo strtolower($calc['customer_name']); ?>"
                 data-order="<?php echo strtolower($calc['order_number']); ?>">
                 
                <div class="px-4 sm:px-6 py-4">
                    <!-- Mobile-optimized layout -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <!-- Row 1: Calculation Identity -->
                        <div class="flex items-center space-x-3 flex-1 min-w-0">
                            <!-- Shortage Status Dot -->
                            <div class="flex-shrink-0">
                                <span class="inline-block h-3 w-3 rounded-full <?php echo $shortageDotClasses[$shortageLevel]; ?>"></span>
                            </div>
                            
                            <!-- Calculation Details -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center space-x-2 mb-1">
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        <?php echo htmlspecialchars($calc['order_number']); ?>
                                    </p>
                                    <?php if ($calc['shortage_count'] > 0): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <?php echo $calc['shortage_count']; ?> Shortages
                                    </span>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Complete
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-900 font-medium mb-1 line-clamp-1">
                                    <?php echo htmlspecialchars($calc['customer_name']); ?>
                                </p>
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                    <span>Calculated: <?php echo date('M j, Y g:i A', strtotime($calc['calculation_date'])); ?></span>
                                    <span>Materials: <?php echo $calc['material_count']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Metrics -->
                        <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-6 text-right sm:flex-shrink-0">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-500 mb-1">Materials Analyzed</p>
                                <p class="text-sm font-semibold text-gray-900 truncate">
                                    <?php echo $calc['material_count']; ?>
                                </p>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-gray-500 mb-1">Shortages Found</p>
                                <p class="text-sm font-semibold truncate <?php echo $calc['shortage_count'] > 0 ? 'text-yellow-600' : 'text-green-600'; ?>">
                                    <?php echo $calc['shortage_count']; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Row 3: Actions -->
                        <div class="flex items-center justify-end space-x-2 sm:flex-shrink-0">
                            <a href="results.php?order_id=<?php echo $calc['order_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                View Results
                            </a>
                            <a href="run.php?order_id=<?php echo $calc['order_id']; ?>" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Re-run
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- MRP Process Overview -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">MRP Process Overview</h2>
            <p class="text-sm text-gray-500">How Material Requirements Planning works in your system</p>
        </div>
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">1. Select Order</h3>
                    <p class="text-sm text-gray-600">Choose a customer order to analyze for material requirements</p>
                </div>
                
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">2. Explode BOM</h3>
                    <p class="text-sm text-gray-600">Calculate material requirements from Bill of Materials recursively</p>
                </div>
                
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-3">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M9 1v6m6-6v6"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">3. Check Inventory</h3>
                    <p class="text-sm text-gray-600">Compare requirements against current available stock levels</p>
                </div>
                
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-purple-100 mb-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v1a2 2 0 002 2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v3.5M9 9h3m-3 0v3h3m-3-3V6"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">4. Generate Suggestions</h3>
                    <p class="text-sm text-gray-600">Create purchase order recommendations for material shortages</p>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Empty States -->
<?php if (empty($pendingOrders) && empty($recentCalculations)): ?>
<div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v1a2 2 0 002 2h2m0 0V6a2 2 0 012-2h2a2 2 0 012 2v3.5M9 9h3m-3 0v3h3m-3-3V6" />
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No MRP data available</h3>
        <p class="mt-1 text-sm text-gray-500">
            No pending orders or recent calculations found.<br>
            Create a <a href="../orders/create.php" class="text-blue-600 hover:text-blue-500">customer order</a> to get started with MRP.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Standardized Search Component -->
<?php echo getSearchComponentCSS(); ?>
<?php includeSearchComponentJS(); ?>

<!-- Include CSS for modern MRP list -->
<link rel="stylesheet" href="../css/materials-modern.css">

<script>
// Filter functions for orders and calculations
function filterOrders(filter) {
    const orders = document.querySelectorAll('#ordersList .list-item');
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    // Update active filter button
    filterBtns.forEach(btn => {
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
            btn.classList.add('ring-2', 'ring-blue-500');
        } else {
            btn.classList.remove('active');
            btn.classList.remove('ring-2', 'ring-blue-500');
        }
    });
    
    orders.forEach(order => {
        let show = true;
        
        switch (filter) {
            case 'overdue':
                show = order.dataset.priority === 'overdue';
                break;
            case 'urgent':
                show = order.dataset.priority === 'urgent';
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        order.style.display = show ? 'block' : 'none';
    });
}

function filterCalculations(filter) {
    const calculations = document.querySelectorAll('#calculationsList .list-item');
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    // Update active filter button
    filterBtns.forEach(btn => {
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
            btn.classList.add('ring-2', 'ring-blue-500');
        } else {
            btn.classList.remove('active');
            btn.classList.remove('ring-2', 'ring-blue-500');
        }
    });
    
    calculations.forEach(calc => {
        let show = true;
        
        switch (filter) {
            case 'with-shortages':
                show = ['warning', 'critical'].includes(calc.dataset.shortageLevel);
                break;
            case 'no-shortages':
                show = calc.dataset.shortageLevel === 'good';
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        calc.style.display = show ? 'block' : 'none';
    });
}

// Mobile touch enhancements
function enhanceMobileExperience() {
    // Add touch feedback for action buttons
    const actionButtons = document.querySelectorAll('.list-item a');
    actionButtons.forEach(button => {
        button.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.95)';
        });
        button.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Improve filter button scrolling for mobile
    const filterContainers = document.querySelectorAll('.overflow-x-auto');
    filterContainers.forEach(container => {
        if (window.innerWidth <= 480) {
            let isScrolling = false;
            container.addEventListener('scroll', function() {
                isScrolling = true;
                setTimeout(function() {
                    isScrolling = false;
                }, 100);
            });
        }
    });
    
    // Add haptic feedback for mobile (if supported)
    const addHapticFeedback = (element) => {
        element.addEventListener('click', function() {
            if (navigator.vibrate && window.innerWidth <= 480) {
                navigator.vibrate(10); // Very short haptic feedback
            }
        });
    };
    
    // Apply haptic feedback to interactive elements
    document.querySelectorAll('.filter-btn, .list-item a').forEach(addHapticFeedback);
}

// Initialize all functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing MRP page functions...');
    enhanceMobileExperience();
    console.log('MRP page initialization complete');
});
</script>

<?php 
$include_autocomplete = false; // Scripts already loaded above
require_once '../../includes/footer-tailwind.php'; 
?>