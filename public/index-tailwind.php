<?php
session_start();
require_once '../includes/header-tailwind.php';
require_once '../includes/help-system.php';

// Dashboard data
require_once '../classes/Material.php';
require_once '../classes/Product.php';
require_once '../classes/Inventory.php';

$materialModel = new Material();
$productModel = new Product();
$inventoryModel = new Inventory();

// Get dashboard statistics
$belowReorderMaterials = $materialModel->getBelowReorderPoint();
$belowSafetyProducts = $productModel->getBelowSafetyStock();
$expiringInventory = $inventoryModel->getExpiringInventory(30);

// Calculate pending orders (placeholder - implement when order system is ready)
$pendingOrders = 0;
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-2">
            <h1 class="text-2xl font-bold text-gray-900">MRP Dashboard</h1>
            <div class="relative group">
                <button class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </button>
                <div class="absolute left-0 top-8 w-64 bg-gray-900 text-white text-sm rounded-md px-3 py-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
                    View critical alerts and system status. Monitor materials below reorder points, expiring inventory, and pending orders.
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Materials Below Reorder -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">Materials Below Reorder</p>
                        <p class="text-2xl font-semibold <?php echo count($belowReorderMaterials) > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                            <?php echo count($belowReorderMaterials); ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 <?php echo count($belowReorderMaterials) > 0 ? 'bg-red-100' : 'bg-green-100'; ?> rounded-md flex items-center justify-center">
                            <?php if (count($belowReorderMaterials) > 0): ?>
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Below Safety Stock -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">Products Below Safety Stock</p>
                        <p class="text-2xl font-semibold <?php echo count($belowSafetyProducts) > 0 ? 'text-yellow-600' : 'text-green-600'; ?>">
                            <?php echo count($belowSafetyProducts); ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 <?php echo count($belowSafetyProducts) > 0 ? 'bg-yellow-100' : 'bg-green-100'; ?> rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 <?php echo count($belowSafetyProducts) > 0 ? 'text-yellow-600' : 'text-green-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Items -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">Expiring Items (30 days)</p>
                        <p class="text-2xl font-semibold <?php echo count($expiringInventory) > 0 ? 'text-orange-600' : 'text-green-600'; ?>">
                            <?php echo count($expiringInventory); ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 <?php echo count($expiringInventory) > 0 ? 'bg-orange-100' : 'bg-green-100'; ?> rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 <?php echo count($expiringInventory) > 0 ? 'text-orange-600' : 'text-green-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 truncate">Pending Orders</p>
                        <p class="text-2xl font-semibold <?php echo $pendingOrders > 0 ? 'text-blue-600' : 'text-gray-600'; ?>">
                            <?php echo $pendingOrders; ?>
                        </p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 <?php echo $pendingOrders > 0 ? 'bg-blue-100' : 'bg-gray-100'; ?> rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 <?php echo $pendingOrders > 0 ? 'text-blue-600' : 'text-gray-600'; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Tables -->
    <div class="space-y-8">
        <!-- Materials Below Reorder Point -->
        <?php if (!empty($belowReorderMaterials)): ?>
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center space-x-2">
                    <h2 class="text-lg font-medium text-gray-900">Materials Below Reorder Point</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <?php echo count($belowReorderMaterials); ?> Critical
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Material Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reorder Point</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shortage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($belowReorderMaterials as $material): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($material['material_code']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($material['name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">
                                <?php echo number_format($material['current_stock'], 2); ?> <?php echo $material['uom_code']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($material['reorder_point'], 2); ?> <?php echo $material['uom_code']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <?php echo number_format($material['shortage_qty'], 2); ?> <?php echo $material['uom_code']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($material['supplier_name'] ?? 'Not assigned'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products Below Safety Stock -->
        <?php if (!empty($belowSafetyProducts)): ?>
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center space-x-2">
                    <h2 class="text-lg font-medium text-gray-900">Products Below Safety Stock</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                        <?php echo count($belowSafetyProducts); ?> Low
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Safety Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shortage</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($belowSafetyProducts as $product): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($product['product_code']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-600 font-medium">
                                <?php echo number_format($product['current_stock'], 2); ?> <?php echo $product['uom_code']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($product['safety_stock_qty'], 2); ?> <?php echo $product['uom_code']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <?php echo number_format($product['shortage_qty'], 2); ?> <?php echo $product['uom_code']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inventory Expiring Soon -->
        <?php if (!empty($expiringInventory)): ?>
        <div class="bg-white shadow-sm rounded-lg border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center space-x-2">
                    <h2 class="text-lg font-medium text-gray-900">Inventory Expiring Soon</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                        <?php echo count($expiringInventory); ?> Items
                    </span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lot Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Until Expiry</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($expiringInventory as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($item['item_code']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($item['lot_number']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($item['quantity'], 2); ?> <?php echo $item['uom_code']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $item['expiry_date']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                $days = $item['days_until_expiry'];
                                $badgeClass = $days <= 7 ? 'bg-red-100 text-red-800' : ($days <= 20 ? 'bg-orange-100 text-orange-800' : 'bg-yellow-100 text-yellow-800');
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badgeClass; ?>">
                                    <?php echo $days; ?> days
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8 bg-white shadow-sm rounded-lg border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-medium text-gray-900">Quick Actions</h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                <a href="orders/create.php" class="bg-primary hover:bg-primary-dark text-white px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 min-h-[44px] flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Create Order</span>
                </a>
                <a href="mrp/run.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 min-h-[44px] flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span>Run MRP</span>
                </a>
                <a href="materials/create.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 min-h-[44px] flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Add Material</span>
                </a>
                <a href="products/create.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 min-h-[44px] flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <span>Add Product</span>
                </a>
                <a href="inventory/receive.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-md text-sm font-medium transition-colors duration-200 min-h-[44px] flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <span>Receive Inventory</span>
                </a>
            </div>
        </div>
    </div>
    
    <?php 
    // Show workflow guide for new users
    if (!isset($_COOKIE['hide_workflow_guide'])) {
        echo '<div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">';
        echo '<h3 class="text-lg font-medium text-blue-900 mb-3">Getting Started</h3>';
        echo '<ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">';
        echo '<li>Check dashboard for alerts and low stock warnings</li>';
        echo '<li>Create customer orders as they come in</li>';
        echo '<li>Run MRP to calculate material requirements</li>';
        echo '<li>Create production orders based on demand</li>';
        echo '<li>Monitor production progress on Gantt chart</li>';
        echo '</ol>';
        echo '</div>';
    }
    ?>
</div>

<?php require_once '../includes/footer-tailwind.php'; ?>