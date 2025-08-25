<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/Material.php';
require_once '../../classes/Inventory.php';

$materialModel = new Material();
$inventoryModel = new Inventory();
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';

try {
    if ($search) {
        $materials = $materialModel->search($search, $showInactive);
    } else {
        if ($showInactive) {
            $materials = $materialModel->getAll(); // Will need to create this method
        } else {
            $materials = $materialModel->getAllActive();
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading materials: " . $e->getMessage();
    $materials = [];
}

// Get current stock for each material
foreach ($materials as &$material) {
    $material['current_stock'] = $inventoryModel->getAvailableQuantity('material', $material['id']);
}
unset($material);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Materials Management</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage raw materials, components, and inventory</p>
                </div>
                <div class="mt-4 sm:mt-0 sm:ml-4">
                    <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Material
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Search and Filters Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-6">
            <form method="GET" action="" id="searchForm" class="space-y-4">
                <!-- Search Input with Recent Searches -->
                <div class="relative">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" 
                               id="searchInput"
                               name="search" 
                               placeholder="Search materials by code, name, or category..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               data-autocomplete-preset="materials-search"
                               autocomplete="off"
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary focus:border-primary sm:text-sm transition-colors duration-200">
                    </div>
                    
                    <!-- Recent Searches -->
                    <div class="recent-searches hidden mt-2 p-3 bg-gray-50 rounded-md border border-gray-200" id="recentSearches">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-medium text-gray-500">Recent:</span>
                            <div class="recent-searches-list" id="recentSearchesList"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Search Controls -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center space-x-3">
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                            <svg class="-ml-1 mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Search
                        </button>
                        <?php if (!empty($_GET['search']) || $showInactive): ?>
                            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Include Inactive Toggle -->
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="show_inactive"
                               name="show_inactive" 
                               value="1"
                               <?php echo $showInactive ? 'checked' : ''; ?>
                               onchange="this.form.submit();"
                               class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                        <label for="show_inactive" class="ml-2 text-sm text-gray-700">
                            Include Inactive Materials
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Materials Content -->
    <?php if (empty($materials)): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="text-center py-16 px-6">
                <div class="mx-auto h-16 w-16 text-gray-400 mb-6">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="h-16 w-16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M9 1v6m6-6v6"/>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Materials Found</h3>
                <?php if ($search): ?>
                    <p class="text-gray-500 mb-6">No materials match your search criteria.</p>
                    <div class="space-x-3">
                        <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                            Clear Search
                        </a>
                        <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                            Add New Material
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 mb-6">Get started by creating your first material for inventory tracking and BOM management.</p>
                    <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create First Material
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Materials List Container -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <!-- List Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Materials Inventory</h3>
                    <span class="text-sm text-gray-500 list-meta"><?php echo count($materials); ?> materials found</span>
                </div>
            </div>
            
            <!-- Filter Panel -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex flex-wrap items-center gap-2" id="quickFilters">
                    <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors duration-200 filter-btn active bg-primary text-white border-primary" data-filter="all" onclick="filterMaterials('all')">
                        All Materials
                    </button>
                    <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors duration-200 filter-btn bg-yellow-50 text-yellow-800 border-yellow-200 hover:bg-yellow-100" data-filter="low-stock" onclick="filterMaterials('low-stock')">
                        Low Stock
                        <?php 
                        $lowStock = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point'] && $m['current_stock'] > 0);
                        if (count($lowStock) > 0) echo '<span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">' . count($lowStock) . '</span>';
                        ?>
                    </button>
                    <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors duration-200 filter-btn bg-red-50 text-red-800 border-red-200 hover:bg-red-100" data-filter="out-of-stock" onclick="filterMaterials('out-of-stock')">
                        Out of Stock
                        <?php 
                        $outOfStock = array_filter($materials, fn($m) => $m['current_stock'] <= 0);
                        if (count($outOfStock) > 0) echo '<span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">' . count($outOfStock) . '</span>';
                        ?>
                    </button>
                    <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors duration-200 filter-btn bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100" data-filter="need-reorder" onclick="filterMaterials('need-reorder')">
                        Need Reorder
                        <?php 
                        $needReorder = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point']);
                        if (count($needReorder) > 0) echo '<span class="ml-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full">' . count($needReorder) . '</span>';
                        ?>
                    </button>
                </div>
            </div>
            
            <!-- Bulk Actions Bar (Hidden by default) -->
            <div class="hidden px-6 py-3 bg-gray-900 text-white border-b border-gray-200" id="bulkActionsBar">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium">
                        <span id="selectedCount">0</span> materials selected
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="bulkExport()" class="px-3 py-1.5 text-xs font-medium border border-gray-600 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            Export
                        </button>
                        <button onclick="bulkStockAdjust()" class="px-3 py-1.5 text-xs font-medium border border-gray-600 rounded-md hover:bg-gray-700 transition-colors duration-200">
                            Stock Adjust
                        </button>
                        <button onclick="bulkReorder()" class="px-3 py-1.5 text-xs font-medium bg-primary border border-primary rounded-md hover:bg-primary-dark transition-colors duration-200">
                            Create PO
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Materials List -->
            <div class="divide-y divide-gray-200" id="materialsList">
                <?php foreach ($materials as $material): ?>
                <?php 
                $stockLevel = 'good';
                if ($material['current_stock'] <= 0) {
                    $stockLevel = 'critical';
                } elseif ($material['current_stock'] < $material['reorder_point']) {
                    $stockLevel = 'warning';
                }
                
                // Stock status classes
                $stockBgClass = 'bg-white';
                $stockBorderClass = '';
                if ($stockLevel === 'critical') {
                    $stockBgClass = 'bg-red-50';
                    $stockBorderClass = 'border-l-4 border-red-500';
                } elseif ($stockLevel === 'warning') {
                    $stockBgClass = 'bg-yellow-50';
                    $stockBorderClass = 'border-l-4 border-yellow-500';
                }
                ?>
                
                <div class="relative list-item <?php echo $stockBgClass; ?> <?php echo $stockBorderClass; ?> <?php echo !$material['is_active'] ? 'opacity-60' : ''; ?> hover:bg-gray-50 transition-colors duration-150" 
                     data-id="<?php echo $material['id']; ?>" 
                     data-type="<?php echo strtolower($material['material_type']); ?>"
                     data-stock-level="<?php echo $stockLevel; ?>"
                     data-name="<?php echo strtolower($material['name']); ?>"
                     data-code="<?php echo strtolower($material['material_code']); ?>"
                     data-cost="<?php echo $material['cost_per_unit']; ?>">
                     
                    <div class="px-6 py-4">
                        <div class="flex items-center space-x-4">
                            <!-- Checkbox -->
                            <div class="flex-shrink-0">
                                <input type="checkbox" 
                                       class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded item-checkbox" 
                                       value="<?php echo $material['id']; ?>" 
                                       onchange="updateBulkActions()">
                            </div>
                            
                            <!-- Main Content -->
                            <div class="flex-1 min-w-0 cursor-pointer" onclick="window.location.href='view.php?id=<?php echo $material['id']; ?>'">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <!-- Header Row -->
                                        <div class="flex items-center space-x-3 mb-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 font-mono">
                                                <?php echo htmlspecialchars($material['material_code']); ?>
                                            </span>
                                            
                                            <!-- Stock Status Indicator -->
                                            <div class="flex items-center">
                                                <?php if ($stockLevel === 'critical'): ?>
                                                    <div class="flex items-center space-x-1">
                                                        <div class="h-2 w-2 bg-red-500 rounded-full animate-pulse"></div>
                                                        <span class="text-xs font-medium text-red-600">Out of Stock</span>
                                                    </div>
                                                <?php elseif ($stockLevel === 'warning'): ?>
                                                    <div class="flex items-center space-x-1">
                                                        <div class="h-2 w-2 bg-yellow-500 rounded-full"></div>
                                                        <span class="text-xs font-medium text-yellow-600">Low Stock</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="flex items-center space-x-1">
                                                        <div class="h-2 w-2 bg-green-500 rounded-full"></div>
                                                        <span class="text-xs font-medium text-green-600">In Stock</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Type Badge -->
                                            <?php 
                                            $typeBadgeClass = 'bg-blue-100 text-blue-800';
                                            switch(strtolower($material['material_type'])) {
                                                case 'raw_material':
                                                    $typeBadgeClass = 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'component':
                                                    $typeBadgeClass = 'bg-green-100 text-green-800';
                                                    break;
                                                case 'packaging':
                                                    $typeBadgeClass = 'bg-purple-100 text-purple-800';
                                                    break;
                                                case 'consumable':
                                                    $typeBadgeClass = 'bg-yellow-100 text-yellow-800';
                                                    break;
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $typeBadgeClass; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $material['material_type'])); ?>
                                            </span>
                                            
                                            <?php if (!$material['is_active']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Material Name -->
                                        <h3 class="text-sm font-semibold text-gray-900 mb-1 truncate">
                                            <?php echo htmlspecialchars($material['name']); ?>
                                        </h3>
                                        
                                        <!-- Meta Information -->
                                        <div class="flex flex-wrap items-center text-xs text-gray-500 space-x-4">
                                            <span>Category: <?php echo htmlspecialchars($material['category'] ?? 'General'); ?></span>
                                            <span>UOM: <?php echo htmlspecialchars($material['uom_code']); ?></span>
                                            <?php if (isset($material['supplier_name'])): ?>
                                            <span>Supplier: <?php echo htmlspecialchars($material['supplier_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Metrics -->
                                    <div class="flex items-center space-x-6 text-right">
                                        <div class="text-center">
                                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Current Stock</div>
                                            <div class="mt-1 text-sm font-semibold <?php echo $stockLevel === 'critical' ? 'text-red-600' : ($stockLevel === 'warning' ? 'text-yellow-600' : 'text-green-600'); ?>">
                                                <?php echo number_format($material['current_stock'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Reorder Point</div>
                                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                                <?php echo number_format($material['reorder_point'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">Cost/Unit</div>
                                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                                $<?php echo number_format($material['cost_per_unit'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                <!-- Quick Action Button -->
                                <?php if ($material['current_stock'] < $material['reorder_point']): ?>
                                <button onclick="quickReorder(<?php echo $material['id']; ?>)" 
                                        title="Quick Reorder"
                                        class="inline-flex items-center p-2 text-white bg-primary hover:bg-primary-dark rounded-md transition-colors duration-200">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </button>
                                <?php else: ?>
                                <button onclick="quickStockAdjust(<?php echo $material['id']; ?>)" 
                                        title="Quick Stock Adjust"
                                        class="inline-flex items-center p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors duration-200">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </button>
                                <?php endif; ?>
                                
                                <!-- Menu Toggle -->
                                <div class="relative">
                                    <button class="inline-flex items-center p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-md transition-colors duration-200 action-menu-toggle" 
                                            type="button">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                        </svg>
                                    </button>
                                    
                                    <!-- Dropdown Menu -->
                                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-50 hidden action-menu" id="menu-<?php echo $material['id']; ?>">
                                        <div class="py-1">
                                            <a href="view.php?id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 action-item">
                                                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                View Details
                                            </a>
                                            <a href="edit.php?id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 action-item">
                                                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Edit Material
                                            </a>
                                            <a href="../inventory/adjust.php?type=material&id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 action-item">
                                                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                                </svg>
                                                Stock History
                                            </a>
                                            <a href="../bom/index.php?material_id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 action-item">
                                                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h2a2 2 0 002-2z"></path>
                                                </svg>
                                                BOM Usage
                                            </a>
                                            <?php if ($material['current_stock'] < $material['reorder_point']): ?>
                                            <div class="border-t border-gray-100"></div>
                                            <a href="../purchase/create.php?material_id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50 action-item">
                                                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17M17 13v4a2 2 0 01-2 2H9a2 2 0 01-2-2v-4m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                                                </svg>
                                                Create Purchase Order
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Custom Styles for Recent Searches -->
<style>
.recent-searches {
    display: none;
}

.recent-searches.show {
    display: block;
}

.recent-search-item {
    display: inline-block;
    margin-right: 12px;
    padding: 2px 0;
    color: #6b7280;
    text-decoration: underline;
    font-size: 0.75rem;
    cursor: pointer;
    transition: color 0.15s ease;
}

.recent-search-item:hover {
    color: #2563eb;
}

.recent-search-item:last-child {
    margin-right: 0;
}

/* Bulk actions visibility */
.bulk-actions-bar.show {
    display: flex !important;
}

/* Filter button active states */
.filter-btn.active {
    background-color: #2563eb !important;
    color: white !important;
    border-color: #2563eb !important;
}

/* Action menu visibility */
.action-menu.show {
    display: block !important;
}

/* Animation for stock status */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}
</style>

<link rel="stylesheet" href="../css/autocomplete.css">
<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>

<script>
// Modern Materials List JavaScript
let selectedMaterials = new Set();
let currentFilter = 'all';
let allMaterials = [];

// Initialize materials data on page load
document.addEventListener('DOMContentLoaded', function() {
    // Store all materials for client-side filtering
    allMaterials = Array.from(document.querySelectorAll('.list-item')).map(item => ({
        element: item,
        id: item.dataset.id,
        type: item.dataset.type,
        stockLevel: item.dataset.stockLevel,
        name: item.dataset.name,
        code: item.dataset.code,
        cost: parseFloat(item.dataset.cost || 0)
    }));
    
    // Initialize recent searches
    initializeRecentSearches();
    
    // Setup action menu toggles using event delegation
    setupActionMenus();
});

// Setup action menus with proper event delegation
function setupActionMenus() {
    console.log('Setting up action menus...');
    
    // Remove any existing inline onclick handlers and use event delegation instead
    document.addEventListener('click', function(e) {
        // Handle action menu toggle clicks
        if (e.target.closest('.action-menu-toggle')) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Action menu toggle clicked');
            
            const toggleBtn = e.target.closest('.action-menu-toggle');
            const listItem = toggleBtn.closest('.list-item');
            
            if (listItem && listItem.dataset.id) {
                const materialId = listItem.dataset.id;
                const menu = document.getElementById('menu-' + materialId);
                
                console.log('Material ID:', materialId, 'Menu element:', menu);
                
                if (!menu) {
                    console.error('Menu not found for material ID:', materialId);
                    // Try to debug what IDs exist
                    const allMenus = document.querySelectorAll('[id^="menu-"]');
                    console.log('Available menu IDs:', Array.from(allMenus).map(m => m.id));
                    return;
                }
                
                // Close all other menus
                document.querySelectorAll('.action-menu').forEach(m => {
                    if (m !== menu) {
                        m.classList.remove('show');
                    }
                });
                
                // Toggle current menu
                menu.classList.toggle('show');
                
                console.log('Toggled menu for material:', materialId, 'Menu visible:', menu.classList.contains('show'));
            } else {
                console.error('Could not find list item or material ID');
            }
        }
        // Handle clicks outside menus to close them
        else if (!e.target.closest('.action-menu')) {
            document.querySelectorAll('.action-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    
    // Log how many action menu toggles we found
    const toggleButtons = document.querySelectorAll('.action-menu-toggle');
    console.log('Found', toggleButtons.length, 'action menu toggle buttons');
}

// Recent searches functionality
function initializeRecentSearches() {
    const searchInput = document.getElementById('searchInput');
    const recentSearches = document.getElementById('recentSearches');
    const recentSearchesList = document.getElementById('recentSearchesList');
    
    if (!searchInput || !recentSearches || !recentSearchesList) return;
    
    // Show recent searches on input focus
    searchInput.addEventListener('focus', function() {
        updateRecentSearchesDisplay();
    });
    
    // Hide recent searches when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !recentSearches.contains(e.target)) {
            recentSearches.classList.remove('show');
        }
    });
    
    // Save search when form is submitted
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const query = searchInput.value.trim();
            if (query) {
                saveRecentSearch(query);
            }
        });
    }
}

function updateRecentSearchesDisplay() {
    const recentSearches = document.getElementById('recentSearches');
    const recentSearchesList = document.getElementById('recentSearchesList');
    
    if (!recentSearches || !recentSearchesList) return;
    
    const searches = getRecentSearches();
    
    if (searches.length === 0) {
        recentSearches.classList.remove('show');
        return;
    }
    
    // Show recent searches
    recentSearches.classList.add('show');
    
    // Clear existing items
    recentSearchesList.innerHTML = '';
    
    // Add recent search items (limit to 5 most recent)
    searches.slice(0, 5).forEach(search => {
        const item = document.createElement('span');
        item.className = 'recent-search-item';
        item.textContent = search;
        item.onclick = function() {
            document.getElementById('searchInput').value = search;
            document.getElementById('searchForm').submit();
        };
        recentSearchesList.appendChild(item);
    });
}

function getRecentSearches() {
    try {
        const searches = localStorage.getItem('materialRecentSearches');
        return searches ? JSON.parse(searches) : [];
    } catch (e) {
        return [];
    }
}

function saveRecentSearch(query) {
    try {
        let searches = getRecentSearches();
        
        // Remove if already exists
        searches = searches.filter(s => s !== query);
        
        // Add to beginning
        searches.unshift(query);
        
        // Keep only last 10
        searches = searches.slice(0, 10);
        
        localStorage.setItem('materialRecentSearches', JSON.stringify(searches));
        
        // Update display immediately after saving
        updateRecentSearchesDisplay();
    } catch (e) {
        // Fail silently if localStorage is not available
    }
}

// Note: Action menu toggle is handled by setupActionMenus() using event delegation

// Bulk Actions Management
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const countSpan = document.getElementById('selectedCount');
    
    selectedMaterials.clear();
    checkboxes.forEach(cb => selectedMaterials.add(cb.value));
    
    countSpan.textContent = selectedMaterials.size;
    
    if (selectedMaterials.size > 0) {
        bulkBar.classList.add('show');
        bulkBar.classList.remove('hidden');
    } else {
        bulkBar.classList.remove('show');
        bulkBar.classList.add('hidden');
    }
}

// Filter Materials
function filterMaterials(filter) {
    currentFilter = filter;
    
    // Update filter button states
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    
    // Filter materials
    allMaterials.forEach(material => {
        let show = true;
        
        switch(filter) {
            case 'low-stock':
                show = material.stockLevel === 'warning';
                break;
            case 'out-of-stock':
                show = material.stockLevel === 'critical';
                break;
            case 'need-reorder':
                show = material.stockLevel === 'warning' || material.stockLevel === 'critical';
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        material.element.style.display = show ? 'flex' : 'none';
    });
    
    updateListMeta();
}



// Update list metadata
function updateListMeta() {
    const visibleCount = allMaterials.filter(m => m.element.style.display !== 'none').length;
    const metaElement = document.querySelector('.list-meta');
    if (metaElement) {
        metaElement.textContent = `${visibleCount} materials found`;
    }
}

// Quick Actions
function quickStockAdjust(materialId) {
    // For now, redirect to stock adjust page
    // TODO: Implement modal for quick stock adjustment
    window.location.href = `../inventory/adjust.php?type=material&id=${materialId}`;
}

function quickReorder(materialId) {
    // For now, redirect to purchase order creation
    // TODO: Implement quick reorder modal
    window.location.href = `../purchase/create.php?material_id=${materialId}`;
}

// Bulk Operations
function bulkExport() {
    if (selectedMaterials.size === 0) {
        alert('Please select materials to export');
        return;
    }
    
    // TODO: Implement bulk export functionality
    alert(`Exporting ${selectedMaterials.size} materials (Feature coming soon)`);
}

function bulkStockAdjust() {
    if (selectedMaterials.size === 0) {
        alert('Please select materials for stock adjustment');
        return;
    }
    
    // TODO: Implement bulk stock adjustment modal
    alert(`Adjusting stock for ${selectedMaterials.size} materials (Feature coming soon)`);
}

function bulkReorder() {
    if (selectedMaterials.size === 0) {
        alert('Please select materials to reorder');
        return;
    }
    
    // TODO: Implement bulk purchase order creation
    alert(`Creating PO for ${selectedMaterials.size} materials (Feature coming soon)`);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Escape key closes all menus
    if (event.key === 'Escape') {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
    
    // Ctrl/Cmd + A selects all visible materials
    if ((event.ctrlKey || event.metaKey) && event.key === 'a') {
        event.preventDefault();
        const visibleCheckboxes = Array.from(document.querySelectorAll('.item-checkbox'))
            .filter(cb => cb.closest('.list-item').style.display !== 'none');
        
        const allChecked = visibleCheckboxes.every(cb => cb.checked);
        visibleCheckboxes.forEach(cb => cb.checked = !allChecked);
        updateBulkActions();
    }
});
</script>



<?php 
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php'; 
?>