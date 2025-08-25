<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/search-component.php';
require_once '../../classes/Product.php';
$product = new Product();

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    
    try {
        // Check if product is used in any BOM
        $bomCount = $product->db->selectOne("
            SELECT COUNT(*) as count 
            FROM bom_headers 
            WHERE product_id = ? AND deleted_at IS NULL
        ", [$productId], ['i'])['count'];
        
        if ($bomCount > 0) {
            $_SESSION['error'] = "Cannot delete product - it is used in {$bomCount} BOM(s)";
        } else {
            // Check if product has inventory
            $invCount = $product->db->selectOne("
                SELECT COUNT(*) as count 
                FROM inventory 
                WHERE item_type = 'product' AND item_id = ?
            ", [$productId], ['i'])['count'];
            
            if ($invCount > 0) {
                $_SESSION['error'] = "Cannot delete product - it has inventory transactions";
            } else {
                // Safe to delete
                $result = $product->delete($productId);
                if ($result) {
                    $_SESSION['success'] = "Product deleted successfully";
                } else {
                    $_SESSION['error'] = "Failed to delete product";
                }
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
    }
    
    header('Location: index.php');
    exit;
}

// Get all active products
$products = [];
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';

try {
    if ($search) {
        $products = $product->search($search, $showInactive);
    } else {
        if ($showInactive) {
            $products = $product->getAll();
        } else {
            $products = $product->getAllActive();
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading products: " . $e->getMessage();
    $products = [];
}

?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" style="padding-top: 2rem;">
    <!-- Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-semibold text-gray-900">Products Management</h1>
                <a href="create.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Product
                </a>
            </div>
        </div>

        <!-- Search Section -->
        <div class="px-6 py-4">
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg border border-green-200">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg border border-red-200">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            <?php 
            echo renderSearchComponent([
                'entity' => 'products',
                'placeholder' => 'Search products by code, name, or category...',
                'current_search' => $search,
                'show_filters' => [
                    [
                        'name' => 'show_inactive',
                        'value' => '1',
                        'label' => 'Include Inactive Products',
                        'onchange' => 'this.form.submit();'
                    ]
                ]
            ]);
            ?>
        </div>
    </div>
    <!-- Products Content -->
    <?php if (empty($products)): ?>
        <!-- Empty State -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M34 40h10v-4a6 6 0 00-10.712-3.714M34 40H14m20 0v-4a9.971 9.971 0 00-.712-3.714M14 40H4v-4a6 6 0 0110.713-3.714M14 40v-4c0-1.313.253-2.566.713-3.714m0 0A10.003 10.003 0 0124 26c4.21 0 7.813 2.602 9.288 6.286M30 14a6 6 0 11-12 0 6 6 0 0112 0zm12 6a4 4 0 11-8 0 4 4 0 018 0zm-28 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                <?php if ($search): ?>
                    <p class="mt-1 text-sm text-gray-500">
                        No products match your search criteria.<br>
                        <a href="index.php" class="text-primary hover:text-primary-dark">Clear search</a> or 
                        <a href="create.php" class="text-primary hover:text-primary-dark">add a new product</a>
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first product for inventory tracking and BOM management.</p>
                    <div class="mt-6">
                        <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create First Product
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Products List -->
        <div class="space-y-6">
            <!-- List Header & Filters -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900">Products Inventory</h2>
                            <p class="text-sm text-gray-500"><?php echo count($products); ?> products found</p>
                        </div>
                        
                        <!-- Filter Buttons - Mobile Optimized -->
                        <div class="overflow-x-auto pb-2 -mx-2" style="padding-top: 0.5rem; margin-top: 0.25rem;">
                            <div class="flex space-x-2 px-2 min-w-max" id="quickFilters" style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn active" data-filter="all" onclick="filterProducts('all')">
                                    <span class="whitespace-nowrap">All Products</span>
                                </button>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-yellow-200 text-yellow-800 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 filter-btn" data-filter="low-stock" onclick="filterProducts('low-stock')">
                                    <span class="whitespace-nowrap">Low Stock</span>
                                    <?php 
                                    $lowStock = array_filter($products, fn($p) => ($p['current_stock'] ?? 0) < ($p['safety_stock_qty'] ?? 0) && ($p['current_stock'] ?? 0) > 0);
                                    if (count($lowStock) > 0): ?>
                                        <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-yellow-200 text-yellow-900">
                                            <?php echo count($lowStock); ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-red-200 text-red-800 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200 filter-btn" data-filter="out-of-stock" onclick="filterProducts('out-of-stock')">
                                    <span class="whitespace-nowrap">Out of Stock</span>
                                    <?php 
                                    $outOfStock = array_filter($products, fn($p) => ($p['current_stock'] ?? 0) <= 0);
                                    if (count($outOfStock) > 0): ?>
                                        <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-red-200 text-red-900">
                                            <?php echo count($outOfStock); ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn" data-filter="no-bom" onclick="filterProducts('no-bom')">
                                    <span class="whitespace-nowrap">No BOM</span>
                                    <?php 
                                    $noBom = array_filter($products, fn($p) => empty($p['bom_count']));
                                    if (count($noBom) > 0): ?>
                                        <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">
                                            <?php echo count($noBom); ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions Bar (Hidden by default) -->
                <div id="bulkActionsBar" class="hidden px-6 py-3 bg-blue-50 border-b border-blue-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-blue-900" id="selectedCount">0</span>
                            <span class="ml-1 text-sm text-blue-700">products selected</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" onclick="bulkExport()">
                                Export
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" onclick="bulkStockAdjust()">
                                Stock Adjust
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200" onclick="bulkCreateBom()">
                                Create BOMs
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Products List -->
                <div class="divide-y divide-gray-200" id="productsList">
                    <?php foreach ($products as $prod): ?>
                    <?php 
                    $currentStock = (float)($prod['current_stock'] ?? 0);
                    $safetyStock = (float)($prod['safety_stock_qty'] ?? 0);
                    $stockLevel = 'good';
                    if ($currentStock <= 0) {
                        $stockLevel = 'critical';
                    } elseif ($safetyStock > 0 && $currentStock < $safetyStock) {
                        $stockLevel = 'warning';
                    }
                    ?>
                    
                    <?php 
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
                    
                    <div class="<?php echo $stockLevelClasses[$stockLevel]; ?> hover:bg-gray-50 transition-colors duration-200 list-item <?php echo !$prod['is_active'] ? 'opacity-60' : ''; ?>" 
                         data-id="<?php echo $prod['id']; ?>" 
                         data-category="<?php echo strtolower($prod['category_name'] ?? ''); ?>"
                         data-stock-level="<?php echo $stockLevel; ?>"
                         data-name="<?php echo strtolower($prod['name']); ?>"
                         data-code="<?php echo strtolower($prod['product_code']); ?>"
                         data-cost="<?php echo $prod['standard_cost']; ?>"
                         data-has-bom="<?php echo !empty($prod['bom_count']) ? 'yes' : 'no'; ?>">
                         
                        <div class="px-4 sm:px-6 py-4">
                            <!-- Mobile-optimized layout -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <!-- Row 1: Checkbox + Product Identity -->
                                <div class="flex items-center space-x-3 flex-1 min-w-0">
                                    <!-- Checkbox -->
                                    <input type="checkbox" 
                                           class="w-4 h-4 rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary item-checkbox" 
                                           value="<?php echo $prod['id']; ?>" 
                                           onchange="updateBulkActions()">
                                    
                                    <!-- Stock Status Dot -->
                                    <div class="flex-shrink-0">
                                        <span class="inline-block h-3 w-3 rounded-full <?php echo $stockDotClasses[$stockLevel]; ?>"></span>
                                    </div>
                                    
                                    <!-- Product Details -->
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <p class="text-sm font-semibold text-gray-900 truncate">
                                                <?php echo htmlspecialchars($prod['product_code']); ?>
                                            </p>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 flex-shrink-0">
                                                <?php echo htmlspecialchars($prod['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                            <?php if (!empty($prod['bom_count'])): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 flex-shrink-0">
                                                    <?php echo $prod['bom_count']; ?> BOM<?php echo $prod['bom_count'] > 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-900 font-medium mb-1 line-clamp-1">
                                            <?php echo htmlspecialchars($prod['name']); ?>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                            <span>UOM: <?php echo htmlspecialchars($prod['uom_code'] ?? 'N/A'); ?></span>
                                            <span>Cost: $<?php echo number_format($prod['standard_cost'], 2); ?></span>
                                            <span>Price: $<?php echo number_format($prod['selling_price'], 2); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2: Stock Metrics (Mobile: Stacked, Desktop: Horizontal) -->
                                <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-6 text-right sm:flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-500 mb-1">Current Stock</p>
                                        <p class="text-sm font-semibold truncate <?php echo $stockLevel === 'critical' ? 'text-red-600' : ($stockLevel === 'warning' ? 'text-yellow-600' : 'text-gray-900'); ?>">
                                            <?php echo number_format($currentStock, 2); ?> <?php echo $prod['uom_code'] ?? 'N/A'; ?>
                                        </p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-500 mb-1">Safety Stock</p>
                                        <p class="text-sm text-gray-600 truncate">
                                            <?php echo number_format($safetyStock, 2); ?> <?php echo $prod['uom_code'] ?? 'N/A'; ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Row 3: Actions -->
                                <div class="flex items-center justify-end space-x-2 sm:flex-shrink-0">
                                    <button class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200" title="Quick View" onclick="window.location.href='view.php?id=<?php echo $prod['id']; ?>';">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    <div class="relative">
                                        <button class="action-menu-toggle w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                            </svg>
                                        </button>
                                        <div class="action-menu absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50 hidden">
                                            <div class="py-2">
                                                <a href="view.php?id=<?php echo $prod['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View Details
                                                </a>
                                                <a href="edit.php?id=<?php echo $prod['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit Product
                                                </a>
                                                <?php if (!empty($prod['bom_count'])): ?>
                                                    <a href="../bom/index.php?product_id=<?php echo $prod['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                        <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                        </svg>
                                                        View BOMs (<?php echo $prod['bom_count']; ?>)
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../bom/create.php?product_id=<?php echo $prod['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                        <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                        </svg>
                                                        Create BOM
                                                    </a>
                                                <?php endif; ?>
                                                <a href="../inventory/" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M9 1v6m6-6v6"></path>
                                                    </svg>
                                                    Manage Inventory
                                                </a>
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

<script>
// Filter functions for products
function filterProducts(filterType) {
    const items = document.querySelectorAll('#productsList .list-item');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Update active filter button
    filterButtons.forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[data-filter="${filterType}"]`).classList.add('active');
    
    items.forEach(item => {
        let show = true;
        
        switch(filterType) {
            case 'all':
                show = true;
                break;
            case 'low-stock':
                show = item.dataset.stockLevel === 'warning';
                break;
            case 'out-of-stock':
                show = item.dataset.stockLevel === 'critical';
                break;
            case 'no-bom':
                show = item.dataset.hasBom === 'no';
                break;
            case 'inactive':
                show = item.classList.contains('inactive');
                break;
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
    
    // Update counts
    updateFilterCounts();
}

// Bulk actions functions
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const countEl = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkBar.style.display = 'flex';
        countEl.textContent = checkboxes.length;
    } else {
        bulkBar.style.display = 'none';
    }
}

function bulkExport() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    console.log('Export products:', selected);
    alert('Export functionality will be implemented soon.');
}

function bulkStockAdjust() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    console.log('Stock adjust products:', selected);
    alert('Bulk stock adjustment functionality will be implemented soon.');
}

function bulkCreateBom() {
    const selected = Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value);
    console.log('Create BOMs for products:', selected);
    alert('Bulk BOM creation functionality will be implemented soon.');
}

function updateFilterCounts() {
    // This would update the badge counts on filter buttons
    // Implementation can be added if needed
}

// Action menu functionality with improved debugging
function setupActionMenus() {
    console.log('Setting up action menus...');
    
    // Use event delegation to handle both static and dynamic content
    document.addEventListener('click', function(e) {
        console.log('Click detected:', e.target);
        
        // Check if click is on or inside action menu toggle
        const toggleButton = e.target.closest('.action-menu-toggle');
        if (toggleButton) {
            e.preventDefault();
            console.log('Action menu toggle clicked:', toggleButton);
            
            const menu = toggleButton.nextElementSibling;
            console.log('Associated menu:', menu);
            
            if (menu && menu.classList.contains('action-menu')) {
                // Close all other menus first
                document.querySelectorAll('.action-menu').forEach(otherMenu => {
                    if (otherMenu !== menu) {
                        otherMenu.classList.add('hidden');
                    }
                });
                
                // Toggle this menu
                const isHidden = menu.classList.contains('hidden');
                if (isHidden) {
                    menu.classList.remove('hidden');
                    console.log('Menu opened');
                } else {
                    menu.classList.add('hidden');
                    console.log('Menu closed');
                }
            } else {
                console.error('Menu not found or invalid structure');
            }
            return;
        }
        
        // Close all menus when clicking outside
        if (!e.target.closest('.action-menu')) {
            const openMenus = document.querySelectorAll('.action-menu:not(.hidden)');
            if (openMenus.length > 0) {
                console.log('Closing menus due to outside click');
                openMenus.forEach(menu => menu.classList.add('hidden'));
            }
        }
    });
    
    // Log initial setup completion
    const toggles = document.querySelectorAll('.action-menu-toggle');
    const menus = document.querySelectorAll('.action-menu');
    console.log(`Action menu setup complete: ${toggles.length} toggles, ${menus.length} menus`);
}

// AutocompleteManager will auto-initialize search history based on data-autocomplete-preset attribute

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
    console.log('Products page initialized with action menus and automatic search history');
});
</script>

<!-- Standardized Search Component -->
<?php echo getSearchComponentCSS(); ?>
<?php includeSearchComponentJS(); ?>

<link rel="stylesheet" href="../css/materials-modern.css">

<?php 
$include_autocomplete = false; // Scripts already loaded above
require_once '../../includes/footer-tailwind.php'; 
?>