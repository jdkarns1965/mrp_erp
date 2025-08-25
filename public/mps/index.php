<?php
session_start();
require_once '../../includes/help-system.php';

// Debug: Check if we can access basic functions
echo "<!-- Debug: Starting MPS page -->\n";

try {
    require_once '../../includes/header-tailwind.php';
    echo "<!-- Debug: Header included successfully -->\n";
} catch (Exception $e) {
    die("Error including header: " . $e->getMessage());
}

try {
    require_once '../../classes/Database.php';
    echo "<!-- Debug: Database class included -->\n";
} catch (Exception $e) {
    die("Error including Database class: " . $e->getMessage());
}

try {
    $db = Database::getInstance();
    echo "<!-- Debug: Database connected -->\n";
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage());
}

// Test if planning_calendar table exists
try {
    $sql = "SHOW TABLES LIKE 'planning_calendar'";
    $result = $db->select($sql);
    if (empty($result)) {
        echo "<div class='container'>";
        echo "<div class='alert alert-warning'>";
        echo "<h3>⚠️ MPS Setup Required</h3>";
        echo "<p>The Master Production Schedule requires additional database tables. Please run:</p>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "mysql -u root -p mrp_erp < database/create_planning_tables.sql</pre>";
        echo "<p>This will create the planning calendar and MPS tables with initial data.</p>";
        echo "<p><strong>After running the script, refresh this page.</strong></p>";
        echo "</div>";
        echo "</div>";
        require_once '../../includes/footer-tailwind.php';
        exit;
    }
    echo "<!-- Debug: Planning calendar table exists -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer-tailwind.php';
    exit;
}

// Get planning periods
try {
    $sql = "SELECT * FROM planning_calendar 
            WHERE period_end >= CURDATE() 
            ORDER BY period_start 
            LIMIT 12";
    $periods = $db->select($sql);
    echo "<!-- Debug: Found " . count($periods) . " planning periods -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching periods: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer-tailwind.php';
    exit;
}

// Get products
try {
    $sql = "SELECT 
                p.id,
                p.product_code,
                p.name,
                COALESCE(p.safety_stock_qty, 0) as safety_stock_qty,
                COALESCE(p.lead_time_days, 0) as lead_time_days
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.product_code
            LIMIT 10";
    $products = $db->select($sql);
    echo "<!-- Debug: Found " . count($products) . " products -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching products: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer-tailwind.php';
    exit;
}

// Get existing MPS data
$mpsData = [];
if (!empty($products) && !empty($periods)) {
    try {
        $productIds = implode(',', array_column($products, 'id'));
        $periodIds = implode(',', array_column($periods, 'id'));
        
        $sql = "SELECT product_id, period_id, firm_planned_qty, demand_qty 
                FROM master_production_schedule 
                WHERE product_id IN ($productIds) AND period_id IN ($periodIds)";
        $mpsEntries = $db->select($sql);
        
        foreach ($mpsEntries as $entry) {
            $mpsData[$entry['product_id']][$entry['period_id']] = $entry;
        }
        echo "<!-- Debug: Loaded " . count($mpsEntries) . " MPS entries -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading MPS data: " . $e->getMessage() . " -->\n";
        $mpsData = [];
    }
}

// Get demand forecast data (customer orders)
$demandData = [];
if (!empty($products) && !empty($periods)) {
    try {
        $sql = "SELECT 
                    cod.product_id,
                    DATE(co.required_date) as required_date,
                    SUM(cod.quantity) as total_demand
                FROM customer_order_details cod
                JOIN customer_orders co ON cod.order_id = co.id
                WHERE cod.product_id IN (" . implode(',', array_column($products, 'id')) . ")
                  AND co.status IN ('pending', 'confirmed', 'in_production')
                  AND co.required_date BETWEEN (SELECT MIN(period_start) FROM planning_calendar WHERE id IN (" . implode(',', array_column($periods, 'id')) . "))
                                           AND (SELECT MAX(period_end) FROM planning_calendar WHERE id IN (" . implode(',', array_column($periods, 'id')) . "))
                GROUP BY cod.product_id, DATE(co.required_date)";
        
        $demands = $db->select($sql);
        
        // Map demands to periods
        foreach ($demands as $demand) {
            foreach ($periods as $period) {
                if ($demand['required_date'] >= $period['period_start'] && 
                    $demand['required_date'] <= $period['period_end']) {
                    if (!isset($demandData[$demand['product_id']][$period['id']])) {
                        $demandData[$demand['product_id']][$period['id']] = 0;
                    }
                    $demandData[$demand['product_id']][$period['id']] += $demand['total_demand'];
                    break;
                }
            }
        }
        echo "<!-- Debug: Loaded demand forecast data -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading demand data: " . $e->getMessage() . " -->\n";
    }
}

// Get current inventory levels
$inventoryData = [];
if (!empty($products)) {
    try {
        $sql = "SELECT 
                    item_id as product_id,
                    SUM(quantity - COALESCE(reserved_quantity, 0)) as current_stock
                FROM inventory
                WHERE item_type = 'product' 
                  AND item_id IN (" . implode(',', array_column($products, 'id')) . ")
                  AND status = 'available'
                GROUP BY item_id";
        
        $inventory = $db->select($sql);
        foreach ($inventory as $inv) {
            $inventoryData[$inv['product_id']] = $inv['current_stock'];
        }
        echo "<!-- Debug: Loaded inventory data -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading inventory: " . $e->getMessage() . " -->\n";
    }
}
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<link rel="stylesheet" href="../css/materials-modern.css">

<style>
/* MPS-Specific Enhancements */
.mps-input {
    transition: all 0.2s ease;
}

.mps-input:focus {
    transform: scale(1.05);
    z-index: 10;
    position: relative;
}

/* Sticky headers for large tables */
.sticky {
    position: sticky;
    background: white;
}

/* Smooth transitions for input highlighting */
.mps-input.border-yellow-400 {
    animation: pulse-yellow 2s infinite;
}

.mps-input.border-green-400 {
    animation: pulse-green 1s ease-out;
}

@keyframes pulse-yellow {
    0%, 100% { 
        border-color: #fbbf24; 
        background-color: #fefce8;
    }
    50% { 
        border-color: #f59e0b; 
        background-color: #fef3c7;
    }
}

@keyframes pulse-green {
    0% { 
        border-color: #10b981; 
        background-color: #d1fae5;
    }
    100% { 
        border-color: #34d399; 
        background-color: #ecfdf5;
    }
}

/* Enhanced mobile responsiveness */
@media (max-width: 1024px) {
    .mps-input {
        min-height: 44px; /* Touch-friendly */
        font-size: 16px; /* Prevent iOS zoom */
    }
}

/* Loading states */
.saving .mps-input {
    opacity: 0.6;
    pointer-events: none;
}

/* Capacity status indicators */
.capacity-ok {
    border-left: 3px solid #10b981;
}

.capacity-warning {
    border-left: 3px solid #f59e0b;
}

.capacity-critical {
    border-left: 3px solid #ef4444;
    animation: pulse-red 2s infinite;
}

@keyframes pulse-red {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Horizontal scroll indicators */
.scroll-container {
    position: relative;
}

.scroll-container::before,
.scroll-container::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 20px;
    pointer-events: none;
    z-index: 2;
}

.scroll-container::before {
    left: 0;
    background: linear-gradient(to right, rgba(255,255,255,1), rgba(255,255,255,0));
}

.scroll-container::after {
    right: 0;
    background: linear-gradient(to left, rgba(255,255,255,1), rgba(255,255,255,0));
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .mps-input {
        border: none !important;
        background: transparent !important;
        font-weight: bold;
    }
    
    table {
        font-size: 10px;
    }
}
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Modern Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Master Production Schedule</h1>
                    <p class="text-sm text-gray-600">Plan production quantities for each time period. The MPS drives MRP calculations and balances demand with production capacity.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="reports.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        View Reports
                    </a>
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors" onclick="checkCapacity()">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Check Capacity
                    </button>
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors" onclick="saveMPS()">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        Save MPS
                    </button>
                    <a href="../mrp/run-enhanced.php?include_mps=1" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Run Enhanced MRP
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php echo HelpSystem::renderHelpPanel('mps'); ?>
    <?php echo HelpSystem::renderHelpButton(); ?>

    <?php if (empty($periods)): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">No planning periods found</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>The planning calendar needs to be initialized. Please run the MRP enhancement schema or contact your system administrator.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif (empty($products)): ?>
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">No products found</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Please create some products first before using the Master Production Schedule.</p>
                    </div>
                    <div class="mt-4">
                        <a href="../products/create.php" class="inline-flex items-center px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 border border-transparent rounded-md hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create Product
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- MPS Planning Grid -->
        <form id="mpsForm">
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl mb-6">
                <!-- Planning Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-xl">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Production Planning Grid</h3>
                            <p class="text-sm text-gray-600 mt-1">Enter planned quantities for each product and time period</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <button type="button" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium" onclick="showBulkFillDialog()">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 1.79 4 4 4h8c2.21 0 4-1.79 4-4V7M4 7V4c0-2.21 1.79-4 4-4h8c2.21 0 4 1.79 4 4v3M4 7h16m-1 4l-3-3m3 3l-3 3"></path>
                                </svg>
                                Bulk Fill
                            </button>
                            <button type="button" class="text-sm text-gray-600 hover:text-gray-700 font-medium" onclick="clearAllInputs()">
                                Clear All
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Responsive Planning Grid -->
                <div class="overflow-x-auto">
                    <!-- Desktop View -->
                    <div class="hidden lg:block">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <!-- Period Headers -->
                                <tr>
                                    <th rowspan="2" class="sticky left-0 z-10 px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200" style="min-width: 200px;">
                                        Product
                                    </th>
                                    <th rowspan="2" class="px-3 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Current<br>Stock
                                    </th>
                                    <th rowspan="2" class="px-3 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Safety<br>Stock
                                    </th>
                                    <th rowspan="2" class="px-3 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Lead<br>Time
                                    </th>
                                    <?php foreach ($periods as $period): ?>
                                        <th colspan="2" class="px-4 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-l border-gray-200" style="min-width: 140px;">
                                            <div class="font-semibold text-gray-900"><?= htmlspecialchars($period['period_name']) ?></div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <?= date('M d', strtotime($period['period_start'])) ?> - <?= date('M d', strtotime($period['period_end'])) ?>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                <!-- Sub Headers -->
                                <tr>
                                    <?php foreach ($periods as $period): ?>
                                        <th class="px-2 py-2 bg-gray-100 text-center text-xs font-medium text-gray-600 border-l border-gray-200">
                                            Demand
                                        </th>
                                        <th class="px-2 py-2 bg-indigo-50 text-center text-xs font-medium text-indigo-700">
                                            Plan
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($products as $product): ?>
                                    <?php 
                                    $currentStock = $inventoryData[$product['id']] ?? 0;
                                    $isLowStock = $currentStock < $product['safety_stock_qty'];
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- Product Info -->
                                        <td class="sticky left-0 z-10 px-6 py-4 bg-white border-r border-gray-200">
                                            <div class="flex items-center">
                                                <div class="flex-1">
                                                    <div class="text-sm font-semibold text-gray-900 mb-1">
                                                        <?= htmlspecialchars($product['product_code']) ?>
                                                    </div>
                                                    <div class="text-sm text-gray-600 line-clamp-2">
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Current Stock -->
                                        <td class="px-3 py-4 text-center">
                                            <div class="flex flex-col items-center">
                                                <span class="text-sm font-medium <?= $isLowStock ? 'text-red-600' : 'text-gray-900' ?>">
                                                    <?= number_format($currentStock, 0) ?>
                                                </span>
                                                <?php if ($isLowStock): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mt-1">
                                                        Low Stock
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <!-- Safety Stock -->
                                        <td class="px-3 py-4 text-center text-sm text-gray-600">
                                            <?= number_format($product['safety_stock_qty'], 0) ?>
                                        </td>
                                        <!-- Lead Time -->
                                        <td class="px-3 py-4 text-center text-sm text-gray-600">
                                            <?= $product['lead_time_days'] ?>d
                                        </td>
                                        <!-- Period Data -->
                                        <?php foreach ($periods as $period): ?>
                                            <?php 
                                            $mpsEntry = $mpsData[$product['id']][$period['id']] ?? null;
                                            $currentValue = $mpsEntry ? $mpsEntry['firm_planned_qty'] : '';
                                            $demand = $demandData[$product['id']][$period['id']] ?? 0;
                                            ?>
                                            <!-- Demand -->
                                            <td class="px-2 py-4 text-center bg-gray-50 border-l border-gray-200">
                                                <div class="text-sm <?= $demand > 0 ? 'font-semibold text-gray-900' : 'text-gray-400' ?>">
                                                    <?= $demand > 0 ? number_format($demand, 0) : '—' ?>
                                                </div>
                                                <?php if ($demand > 0): ?>
                                                    <div class="text-xs text-gray-500 mt-1">req'd</div>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Planning Input -->
                                            <td class="px-2 py-4 text-center bg-indigo-50">
                                                <input type="number" 
                                                       class="mps-input w-16 px-2 py-1 text-center text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= $demand > 0 && empty($currentValue) ? 'border-yellow-400 bg-yellow-50' : '' ?>"
                                                       data-product-id="<?= $product['id'] ?>"
                                                       data-period-id="<?= $period['id'] ?>"
                                                       data-demand="<?= $demand ?>"
                                                       data-current-stock="<?= $currentStock ?>"
                                                       value="<?= $currentValue ?>"
                                                       placeholder="0"
                                                       min="0"
                                                       step="1"
                                                       title="Demand: <?= $demand ?>, Current Stock: <?= $currentStock ?>">
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile/Tablet Card View -->
                    <div class="lg:hidden">
                        <?php foreach ($products as $product): ?>
                            <?php 
                            $currentStock = $inventoryData[$product['id']] ?? 0;
                            $isLowStock = $currentStock < $product['safety_stock_qty'];
                            ?>
                            <div class="border-t border-gray-200 p-4 hover:bg-gray-50">
                                <!-- Product Header -->
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-gray-900 mb-1">
                                            <?= htmlspecialchars($product['product_code']) ?>
                                        </h4>
                                        <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($product['name']) ?></p>
                                        <div class="flex gap-4 text-xs text-gray-500">
                                            <span>Stock: <strong class="<?= $isLowStock ? 'text-red-600' : 'text-gray-900' ?>"><?= number_format($currentStock, 0) ?></strong></span>
                                            <span>Safety: <strong><?= number_format($product['safety_stock_qty'], 0) ?></strong></span>
                                            <span>Lead: <strong><?= $product['lead_time_days'] ?>d</strong></span>
                                        </div>
                                    </div>
                                    <?php if ($isLowStock): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Low Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Planning Periods -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($periods as $period): ?>
                                        <?php 
                                        $mpsEntry = $mpsData[$product['id']][$period['id']] ?? null;
                                        $currentValue = $mpsEntry ? $mpsEntry['firm_planned_qty'] : '';
                                        $demand = $demandData[$product['id']][$period['id']] ?? 0;
                                        ?>
                                        <div class="bg-white border border-gray-200 rounded-lg p-3">
                                            <div class="flex justify-between items-start mb-2">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($period['period_name']) ?></div>
                                                    <div class="text-xs text-gray-500">
                                                        <?= date('M d', strtotime($period['period_start'])) ?> - <?= date('M d', strtotime($period['period_end'])) ?>
                                                    </div>
                                                </div>
                                                <?php if ($demand > 0): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        Demand: <?= number_format($demand, 0) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <label class="text-xs font-medium text-gray-700">Plan:</label>
                                                <input type="number" 
                                                       class="mps-input flex-1 px-3 py-2 text-center border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 <?= $demand > 0 && empty($currentValue) ? 'border-yellow-400 bg-yellow-50' : '' ?>"
                                                       data-product-id="<?= $product['id'] ?>"
                                                       data-period-id="<?= $period['id'] ?>"
                                                       data-demand="<?= $demand ?>"
                                                       data-current-stock="<?= $currentStock ?>"
                                                       value="<?= $currentValue ?>"
                                                       placeholder="0"
                                                       min="0"
                                                       step="1"
                                                       title="Demand: <?= $demand ?>, Current Stock: <?= $currentStock ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- Instructions and Planning Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Quick Guide -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Guide</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold text-indigo-600">1</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-600"><strong>Enter planned quantities</strong> for each product in each time period</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold text-indigo-600">2</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-600"><strong>Consider lead times</strong> - production must start before the required date</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold text-indigo-600">3</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-600"><strong>Check safety stock levels</strong> - ensure you maintain minimum stock levels</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-xs font-semibold text-green-600">4</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-gray-600"><strong>Save and run MRP</strong> to calculate material requirements</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Visual Indicators</h4>
                        <div class="space-y-2">
                            <div class="flex items-center text-sm">
                                <div class="w-4 h-4 bg-yellow-100 border border-yellow-400 rounded mr-2"></div>
                                <span class="text-gray-600">Yellow highlight = Demand without plan</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <div class="w-4 h-4 bg-red-100 rounded mr-2"></div>
                                <span class="text-gray-600">Red text = Below safety stock</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Planning Periods -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Planning Periods</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-3">
                        <?php foreach (array_slice($periods, 0, 6) as $index => $period): ?>
                            <div class="flex justify-between items-center py-2 <?= $index < count($periods) - 1 ? 'border-b border-gray-100' : '' ?>">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($period['period_name']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= date('M j', strtotime($period['period_start'])) ?> - <?= date('M j, Y', strtotime($period['period_end'])) ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500"><?= date('D', strtotime($period['period_start'])) ?> to <?= date('D', strtotime($period['period_end'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($periods) > 6): ?>
                            <div class="text-center text-sm text-gray-500 pt-2">
                                ... and <?= count($periods) - 6 ?> more periods
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Quick Actions Floating Panel (for large datasets) -->
    <div class="fixed bottom-4 right-4 z-40 lg:block hidden">
        <div class="bg-white shadow-lg rounded-lg border border-gray-200 p-3">
            <div class="flex items-center gap-2">
                <button type="button" onclick="scrollToTop()" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded" title="Scroll to top">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                    </svg>
                </button>
                <button type="button" onclick="showBulkFillDialog()" class="p-2 text-indigo-600 hover:text-indigo-700 hover:bg-indigo-50 rounded" title="Bulk fill">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 1.79 4 4 4h8c2.21 0 4-1.79 4-4V7M4 7V4c0-2.21 1.79-4 4-4h8c2.21 0 4 1.79 4 4v3M4 7h16m-1 4l-3-3m3 3l-3 3"></path>
                    </svg>
                </button>
                <button type="button" onclick="saveMPS()" class="p-2 text-green-600 hover:text-green-700 hover:bg-green-50 rounded" title="Save MPS">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll to top function
function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Show/hide floating panel based on scroll position
window.addEventListener('scroll', function() {
    const floatingPanel = document.querySelector('.fixed.bottom-4.right-4');
    if (floatingPanel) {
        if (window.scrollY > 300) {
            floatingPanel.style.opacity = '1';
            floatingPanel.style.pointerEvents = 'auto';
        } else {
            floatingPanel.style.opacity = '0.7';
        }
    }
});
</script>

<script>
// Enhanced MPS Interface JavaScript

// Bulk Fill Dialog
function showBulkFillDialog() {
    // Remove existing dialog
    const existingDialog = document.getElementById('bulkFillDialog');
    if (existingDialog) {
        existingDialog.remove();
    }
    
    const dialog = document.createElement('div');
    dialog.id = 'bulkFillDialog';
    dialog.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
    dialog.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Bulk Fill Planning Quantities</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fill Type:</label>
                        <select id="bulkFillType" class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="all">All products, all periods</option>
                            <option value="product">Selected product, all periods</option>
                            <option value="period">All products, selected period</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity:</label>
                        <input type="number" id="bulkFillQuantity" class="w-full border border-gray-300 rounded-md px-3 py-2" placeholder="Enter quantity" min="0">
                    </div>
                    <div class="text-xs text-gray-500">
                        <p>This will fill empty planning fields only. Existing values will not be overwritten.</p>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeBulkFillDialog()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="button" onclick="applyBulkFill()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(dialog);
    
    // Close on outside click
    dialog.addEventListener('click', function(e) {
        if (e.target === dialog) {
            closeBulkFillDialog();
        }
    });
}

function closeBulkFillDialog() {
    const dialog = document.getElementById('bulkFillDialog');
    if (dialog) {
        dialog.remove();
    }
}

function applyBulkFill() {
    const fillType = document.getElementById('bulkFillType').value;
    const quantity = parseFloat(document.getElementById('bulkFillQuantity').value) || 0;
    
    if (quantity < 0) {
        alert('Please enter a valid quantity');
        return;
    }
    
    const inputs = document.querySelectorAll('.mps-input');
    let fillCount = 0;
    
    inputs.forEach(input => {
        // Only fill empty inputs
        if (!input.value || input.value === '0') {
            if (fillType === 'all') {
                input.value = quantity;
                fillCount++;
            }
            // Add product/period specific logic here if needed
        }
    });
    
    showMessage(`Filled ${fillCount} planning quantities with ${quantity}`, 'success');
    closeBulkFillDialog();
    
    // Highlight changed inputs briefly
    inputs.forEach(input => {
        if (input.value == quantity) {
            input.classList.add('bg-green-100', 'border-green-400');
            setTimeout(() => {
                input.classList.remove('bg-green-100', 'border-green-400');
            }, 2000);
        }
    });
}

function clearAllInputs() {
    if (confirm('Clear all planning quantities? This action cannot be undone.')) {
        const inputs = document.querySelectorAll('.mps-input');
        let clearCount = 0;
        
        inputs.forEach(input => {
            if (input.value && input.value !== '0') {
                input.value = '';
                clearCount++;
            }
        });
        
        showMessage(`Cleared ${clearCount} planning quantities`, 'success');
    }
}

// Enhanced keyboard navigation
document.addEventListener('keydown', function(e) {
    const activeElement = document.activeElement;
    if (activeElement && activeElement.classList.contains('mps-input')) {
        switch(e.key) {
            case 'Tab':
                // Tab navigation is handled by browser
                break;
            case 'Enter':
                // Move to next row, same column
                e.preventDefault();
                const allInputs = Array.from(document.querySelectorAll('.mps-input'));
                const currentIndex = allInputs.indexOf(activeElement);
                const periodsPerRow = <?= count($periods) ?>;
                const nextIndex = currentIndex + periodsPerRow;
                
                if (nextIndex < allInputs.length) {
                    allInputs[nextIndex].focus();
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                const allInputsUp = Array.from(document.querySelectorAll('.mps-input'));
                const currentIndexUp = allInputsUp.indexOf(activeElement);
                const prevIndex = currentIndexUp - <?= count($periods) ?>;
                
                if (prevIndex >= 0) {
                    allInputsUp[prevIndex].focus();
                }
                break;
            case 'ArrowDown':
                e.preventDefault();
                const allInputsDown = Array.from(document.querySelectorAll('.mps-input'));
                const currentIndexDown = allInputsDown.indexOf(activeElement);
                const nextIndexDown = currentIndexDown + <?= count($periods) ?>;
                
                if (nextIndexDown < allInputsDown.length) {
                    allInputsDown[nextIndexDown].focus();
                }
                break;
        }
    }
});

// Input validation and highlighting
document.addEventListener('input', function(e) {
    if (e.target && e.target.classList.contains('mps-input')) {
        const input = e.target;
        const value = parseFloat(input.value) || 0;
        const demand = parseFloat(input.getAttribute('data-demand')) || 0;
        
        // Remove existing classes
        input.classList.remove('border-yellow-400', 'bg-yellow-50', 'border-green-400', 'bg-green-50', 'border-red-400', 'bg-red-50');
        
        if (value > 0) {
            if (value >= demand) {
                input.classList.add('border-green-400', 'bg-green-50');
            } else if (demand > 0) {
                input.classList.add('border-yellow-400', 'bg-yellow-50');
            }
        } else if (demand > 0) {
            input.classList.add('border-yellow-400', 'bg-yellow-50');
        }
    }
});

// Auto-save functionality
let saveTimeout;
function autoSave() {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        // Could implement auto-save here
        console.log('Auto-save triggered');
    }, 5000); // 5 seconds after last change
}

document.addEventListener('input', function(e) {
    if (e.target && e.target.classList.contains('mps-input')) {
        autoSave();
    }
});

async function checkCapacity() {
    const checkBtn = document.querySelector('button[onclick="checkCapacity()"]');
    const originalText = checkBtn.textContent;
    
    try {
        checkBtn.textContent = 'Checking...';
        checkBtn.disabled = true;
        
        // Collect all MPS input data
        const mpsData = [];
        const inputs = document.querySelectorAll('.mps-input');
        
        inputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            if (value > 0) {
                mpsData.push({
                    product_id: input.getAttribute('data-product-id'),
                    period_id: input.getAttribute('data-period-id'),
                    firm_planned_qty: value
                });
            }
        });
        
        if (mpsData.length === 0) {
            showMessage('Enter some planned quantities first to check capacity', 'warning');
            return;
        }
        
        // Send to capacity check endpoint
        const response = await fetch('check-capacity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mps_data: mpsData })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (result.has_issues) {
                let message = `⚠️ Capacity Issues Found (${result.summary.total_issues}):\\n\\n`;
                result.issues.forEach(issue => {
                    message += `• ${issue.period} - ${issue.work_center || 'Product'}: ${issue.issue}\\n`;
                    if (issue.required_hours && issue.available_hours) {
                        message += `  Required: ${issue.required_hours}h, Available: ${issue.available_hours}h\\n`;
                    }
                });
                
                showMessage(message, 'warning');
                showCapacityDetails(result);
            } else {
                let message = '✅ Capacity Check Passed!\\n\\n';
                Object.keys(result.utilization).forEach(period => {
                    const util = result.utilization[period];
                    message += `${period}: ${util.utilization}% utilized (${util.used}/${util.available} hours)\\n`;
                });
                showMessage(message, 'success');
            }
        } else {
            showMessage('Error checking capacity: ' + (result.error || 'Unknown error'), 'danger');
        }
        
    } catch (error) {
        console.error('Capacity check error:', error);
        showMessage('Network error while checking capacity', 'danger');
    } finally {
        checkBtn.textContent = originalText;
        checkBtn.disabled = false;
    }
}

function showCapacityDetails(result) {
    // Create detailed capacity report modal/section
    const detailsHtml = `
        <div class="capacity-details" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
            <h4>Capacity Analysis</h4>
            <div class="row">
                <div class="col-md-6">
                    <h5>Utilization by Period</h5>
                    <ul>
                        ${Object.keys(result.utilization).map(period => {
                            const util = result.utilization[period];
                            const statusClass = util.utilization > 100 ? 'text-danger' : 
                                              util.utilization > 80 ? 'text-warning' : 'text-success';
                            return `<li class="${statusClass}">
                                ${period}: ${util.utilization}% (${util.used}/${util.available} hours)
                            </li>`;
                        }).join('')}
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Issues Found</h5>
                    <ul>
                        ${result.issues.map(issue => `
                            <li class="text-danger">
                                <strong>${issue.period}</strong> - ${issue.work_center}: ${issue.issue}
                                ${issue.overrun_hours ? `<br><small>Overrun: ${issue.overrun_hours} hours</small>` : ''}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="hideCapacityDetails()">Hide Details</button>
        </div>
    `;
    
    // Remove existing details
    const existing = document.querySelector('.capacity-details');
    if (existing) existing.remove();
    
    // Insert after message
    const messages = document.querySelectorAll('.mps-message');
    if (messages.length > 0) {
        messages[messages.length - 1].insertAdjacentHTML('afterend', detailsHtml);
    }
}

function hideCapacityDetails() {
    const details = document.querySelector('.capacity-details');
    if (details) details.remove();
}

async function saveMPS() {
    const saveBtn = document.querySelector('button[onclick="saveMPS()"]');
    const originalText = saveBtn.textContent;
    
    try {
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;
        
        // Collect all MPS input data
        const mpsData = [];
        const inputs = document.querySelectorAll('.mps-input');
        
        inputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            if (value > 0) {
                mpsData.push({
                    product_id: input.getAttribute('data-product-id'),
                    period_id: input.getAttribute('data-period-id'),
                    firm_planned_qty: value
                });
            }
        });
        
        // Send to save endpoint
        const response = await fetch('save-mps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mps_data: mpsData })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('MPS data saved successfully!', 'success');
        } else {
            showMessage('Error saving MPS: ' + (result.error || 'Unknown error'), 'danger');
        }
        
    } catch (error) {
        console.error('Save error:', error);
        showMessage('Network error while saving MPS data', 'danger');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

function showMessage(text, type) {
    // Remove existing messages
    const existing = document.querySelector('.mps-message');
    if (existing) {
        existing.remove();
    }
    
    // Create new message with modern Tailwind styling
    const messageDiv = document.createElement('div');
    const typeClasses = {
        success: 'bg-green-50 border-green-200 text-green-800',
        danger: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
    };
    
    messageDiv.className = `mps-message border rounded-lg p-4 mb-6 ${typeClasses[type] || typeClasses.info}`;
    
    const iconMap = {
        success: `<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>`,
        danger: `<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>`,
        warning: `<svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>`
    };
    
    messageDiv.innerHTML = `
        <div class="flex">
            <div class="flex-shrink-0">
                ${iconMap[type] || iconMap.info}
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">${text}</p>
            </div>
        </div>
    `;
    
    // Insert after the header
    const container = document.querySelector('.max-w-7xl');
    const headerCard = container.children[0]; // First child should be the header
    headerCard.insertAdjacentElement('afterend', messageDiv);
    
    // Auto-remove success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.style.transition = 'opacity 0.3s ease';
                messageDiv.style.opacity = '0';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 300);
            }
        }, 5000);
    }
    
    // Add dismiss button for non-success messages
    if (type !== 'success') {
        const dismissBtn = document.createElement('button');
        dismissBtn.type = 'button';
        dismissBtn.className = 'ml-auto flex-shrink-0 p-1.5 text-gray-400 hover:text-gray-600 focus:outline-none';
        dismissBtn.innerHTML = `
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
            </svg>
        `;
        dismissBtn.onclick = () => messageDiv.remove();
        messageDiv.querySelector('.flex').appendChild(dismissBtn);
    }
    
    // Scroll to message
    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<?php echo HelpSystem::getHelpScript(); ?>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>