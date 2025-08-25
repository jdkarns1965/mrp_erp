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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold text-gray-900">Materials Management</h1>
                <a href="create.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Material
                </a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="px-6 py-4">
            <form method="GET" action="" id="searchForm" class="space-y-4">
                <!-- Search Input -->
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
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <!-- Recent Searches -->
                <div id="recentSearches" class="hidden">
                    <div class="text-xs font-medium text-gray-500 mb-2">Recent searches:</div>
                    <div id="recentSearchesList" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- Search Controls -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                            Search
                        </button>
                        <?php if (!empty($_GET['search']) || $showInactive): ?>
                            <a href="index.php" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                                Clear
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <label class="inline-flex items-center">
                        <input type="checkbox" 
                               name="show_inactive" 
                               value="1"
                               <?php echo $showInactive ? 'checked' : ''; ?>
                               onchange="this.form.submit();"
                               class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary">
                        <span class="ml-2 text-sm text-gray-600">Include Inactive Materials</span>
                    </label>
                </div>
            </form>
        </div>
    </div>

    <!-- Materials Content -->
    <?php if (empty($materials)): ?>
        <!-- Empty State -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.713-3.714M14 40v-4c0-1.313.253-2.566.713-3.714m0 0A10.003 10.003 0 0124 26c4.21 0 7.813 2.602 9.288 6.286M30 14a6 6 0 11-12 0 6 6 0 0112 0zm12 6a4 4 0 11-8 0 4 4 0 018 0zm-28 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No materials found</h3>
                <?php if ($search): ?>
                    <p class="mt-1 text-sm text-gray-500">
                        No materials match your search criteria.<br>
                        <a href="index.php" class="text-primary hover:text-primary-dark">Clear search</a> or 
                        <a href="create.php" class="text-primary hover:text-primary-dark">add a new material</a>
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first material for inventory tracking.</p>
                    <div class="mt-6">
                        <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create First Material
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Materials List -->
        <div class="space-y-6">
            <!-- List Header & Filters -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900">Materials Inventory</h2>
                            <p class="text-sm text-gray-500"><?php echo count($materials); ?> materials found</p>
                        </div>
                        
                        <!-- Filter Buttons -->
                        <div class="flex flex-wrap gap-2" id="quickFilters">
                            <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn active" data-filter="all" onclick="filterMaterials('all')">
                                All Materials
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-yellow-200 text-yellow-800 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 filter-btn" data-filter="low-stock" onclick="filterMaterials('low-stock')">
                                Low Stock
                                <?php 
                                $lowStock = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point'] && $m['current_stock'] > 0);
                                if (count($lowStock) > 0): ?>
                                    <span class="ml-1.5 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <?php echo count($lowStock); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-red-200 text-red-800 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200 filter-btn" data-filter="out-of-stock" onclick="filterMaterials('out-of-stock')">
                                Out of Stock
                                <?php 
                                $outOfStock = array_filter($materials, fn($m) => $m['current_stock'] <= 0);
                                if (count($outOfStock) > 0): ?>
                                    <span class="ml-1.5 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <?php echo count($outOfStock); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn" data-filter="need-reorder" onclick="filterMaterials('need-reorder')">
                                Need Reorder
                                <?php 
                                $needReorder = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point']);
                                if (count($needReorder) > 0): ?>
                                    <span class="ml-1.5 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <?php echo count($needReorder); ?>
                                    </span>
                                <?php endif; ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions Bar (Hidden by default) -->
                <div id="bulkActionsBar" class="hidden px-6 py-3 bg-blue-50 border-b border-blue-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-blue-900" id="selectedCount">0</span>
                            <span class="ml-1 text-sm text-blue-700">materials selected</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" onclick="bulkExport()">
                                Export
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200" onclick="bulkReorder()">
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
                    
                    $stockLevelClasses = [
                        'critical' => 'border-l-4 border-red-500 bg-red-50',
                        'warning' => 'border-l-4 border-yellow-500 bg-yellow-50',
                        'good' => 'border-l-4 border-transparent'
                    ];
                    
                    $stockDotClasses = [
                        'critical' => 'bg-red-400',
                        'warning' => 'bg-yellow-400', 
                        'good' => 'bg-green-400'
                    ];
                    ?>
                    
                    <div class="<?php echo $stockLevelClasses[$stockLevel]; ?> hover:bg-gray-50 transition-colors duration-200 list-item <?php echo !$material['is_active'] ? 'opacity-60' : ''; ?>" 
                         data-id="<?php echo $material['id']; ?>" 
                         data-type="<?php echo strtolower($material['material_type']); ?>"
                         data-stock-level="<?php echo $stockLevel; ?>"
                         data-name="<?php echo strtolower($material['name']); ?>"
                         data-code="<?php echo strtolower($material['material_code']); ?>"
                         data-cost="<?php echo $material['cost_per_unit']; ?>">
                         
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <!-- Checkbox -->
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           class="rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary item-checkbox" 
                                           value="<?php echo $material['id']; ?>" 
                                           onchange="updateBulkActions()">
                                </div>

                                <!-- Material Info -->
                                <div class="flex-1 min-w-0 ml-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <!-- Stock Status Dot -->
                                            <div class="flex-shrink-0">
                                                <span class="inline-block h-2 w-2 rounded-full <?php echo $stockDotClasses[$stockLevel]; ?>"></span>
                                            </div>
                                            
                                            <!-- Material Details -->
                                            <div class="min-w-0">
                                                <div class="flex items-center space-x-2">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        <?php echo htmlspecialchars($material['material_code']); ?>
                                                    </p>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                        <?php echo htmlspecialchars($material['material_type']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-900 font-medium">
                                                    <?php echo htmlspecialchars($material['name']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    Category: <?php echo htmlspecialchars($material['category_name'] ?? 'Uncategorized'); ?> • 
                                                    UOM: <?php echo htmlspecialchars($material['uom_code']); ?>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Stock Metrics -->
                                        <div class="flex items-center space-x-6 text-right">
                                            <div>
                                                <p class="text-xs font-medium text-gray-500">Current Stock</p>
                                                <p class="text-sm font-semibold <?php echo $stockLevel === 'critical' ? 'text-red-600' : ($stockLevel === 'warning' ? 'text-yellow-600' : 'text-gray-900'); ?>">
                                                    <?php echo number_format($material['current_stock'], 2); ?> <?php echo $material['uom_code']; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-500">Reorder Point</p>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo number_format($material['reorder_point'], 2); ?> <?php echo $material['uom_code']; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-gray-500">Cost/Unit</p>
                                                <p class="text-sm text-gray-900">
                                                    $<?php echo number_format($material['cost_per_unit'], 2); ?>
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center space-x-2 ml-4">
                                            <button class="p-1.5 text-gray-400 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full transition-colors duration-200" title="Quick Action">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                            </button>
                                            <div class="relative">
                                                <button class="action-menu-toggle p-1.5 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-full transition-colors duration-200">
                                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                                    </svg>
                                                </button>
                                                <div class="action-menu absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 hidden">
                                                    <div class="py-1">
                                                        <a href="view.php?id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <svg class="mr-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                            </svg>
                                                            View Details
                                                        </a>
                                                        <a href="edit.php?id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <svg class="mr-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                            </svg>
                                                            Edit
                                                        </a>
                                                        <a href="../inventory/receive.php?material_id=<?php echo $material['id']; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <svg class="mr-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M9 1v6m6-6v6"></path>
                                                            </svg>
                                                            Receive Stock
                                                        </a>
                                                    </div>
                                                </div>
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
        </div>
    <?php endif; ?>
</div>

<!-- Include existing JavaScript for functionality -->
<script src="../js/autocomplete.js"></script>
<script src="../js/autocomplete-manager.js"></script>

<script>
// Initialize autocomplete
document.addEventListener('DOMContentLoaded', function() {
    AutocompleteManager.init('materials-search', '#searchInput');
});

// Material filtering functions (preserved from original)
function filterMaterials(filter) {
    const materials = document.querySelectorAll('.list-item');
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    // Update active filter button
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
        btn.classList.remove('ring-2', 'ring-primary');
    });
    
    const activeBtn = document.querySelector(`[data-filter="${filter}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
        activeBtn.classList.add('ring-2', 'ring-primary');
    }
    
    materials.forEach(material => {
        let show = true;
        
        switch (filter) {
            case 'low-stock':
                show = material.dataset.stockLevel === 'warning';
                break;
            case 'out-of-stock':
                show = material.dataset.stockLevel === 'critical';
                break;
            case 'need-reorder':
                show = ['warning', 'critical'].includes(material.dataset.stockLevel);
                break;
            case 'all':
            default:
                show = true;
                break;
        }
        
        material.style.display = show ? 'block' : 'none';
    });
}

// Action menu functionality (preserved from original)
function setupActionMenus() {
    document.addEventListener('click', function(e) {
        if (e.target.closest('.action-menu-toggle')) {
            e.preventDefault();
            const button = e.target.closest('.action-menu-toggle');
            const menu = button.nextElementSibling;
            
            // Close all other menus
            document.querySelectorAll('.action-menu').forEach(m => {
                if (m !== menu) m.classList.add('hidden');
            });
            
            // Toggle this menu
            menu.classList.toggle('hidden');
        } else if (!e.target.closest('.action-menu')) {
            // Close all menus when clicking outside
            document.querySelectorAll('.action-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
}

// Bulk actions functionality (preserved from original)
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkBar.classList.remove('hidden');
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkBar.classList.add('hidden');
    }
}

function bulkExport() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    console.log('Export materials:', selected);
    // Implement export functionality
}

function bulkReorder() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    console.log('Create PO for materials:', selected);
    // Implement bulk reorder functionality
}

// Initialize all functionality
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
});
</script>

<?php 
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php'; 
?>