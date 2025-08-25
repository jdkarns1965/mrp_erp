<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/Database.php';
require_once '../../classes/Product.php';

$db = Database::getInstance();
$productModel = new Product();

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$filterProduct = null;

if ($productId) {
    $filterProduct = $productModel->find($productId);
}

// Build query based on search and filters
$sql = "
    SELECT 
        bh.id,
        bh.product_id,
        bh.version,
        bh.description,
        bh.effective_date,
        bh.expiry_date,
        bh.is_active,
        bh.approved_by,
        p.product_code,
        p.name as product_name,
        COUNT(bd.id) as material_count,
        CASE 
            WHEN bh.approved_by IS NOT NULL THEN 'approved'
            ELSE 'draft'
        END as approval_status
    FROM bom_headers bh
    JOIN products p ON bh.product_id = p.id
    LEFT JOIN bom_details bd ON bh.id = bd.bom_header_id
    WHERE 1=1
";

$params = [];
$types = [];

// Apply search filter
if ($search) {
    $sql .= " AND (p.product_code LIKE ? OR p.name LIKE ? OR bh.description LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types = array_merge($types, ['s', 's', 's']);
}

// Apply product filter
if ($productId) {
    $sql .= " AND bh.product_id = ?";
    $params[] = $productId;
    $types[] = 'i';
}

// Apply status filter
if (!$showInactive && $filterStatus !== 'inactive') {
    $sql .= " AND bh.is_active = 1";
} elseif ($filterStatus === 'inactive') {
    $sql .= " AND bh.is_active = 0";
} elseif ($filterStatus === 'active') {
    $sql .= " AND bh.is_active = 1";
}

$sql .= " GROUP BY bh.id, bh.product_id, bh.version, bh.description, bh.effective_date, bh.expiry_date, 
             bh.is_active, bh.approved_by, p.product_code, p.name
    ORDER BY p.product_code, bh.version DESC";

// Get BOMs with product information
try {
    $boms = $params ? $db->select($sql, $params, $types) : $db->select($sql);
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading BOMs: " . $e->getMessage();
    $boms = [];
}

// Get products without BOMs (for alert panel)
$productsWithoutBOM = [];
if (!$search && !$productId) {
    $productsWithoutBOM = $db->select("
        SELECT p.id, p.product_code, p.name
        FROM products p
        LEFT JOIN bom_headers bh ON p.id = bh.product_id AND bh.is_active = 1
        WHERE p.is_active = 1 AND p.deleted_at IS NULL AND bh.id IS NULL
        ORDER BY p.product_code
        LIMIT 10
    ");
}

// Count statistics for filters
$statsQuery = "
    SELECT 
        COUNT(*) as total_boms,
        SUM(CASE WHEN bh.is_active = 1 THEN 1 ELSE 0 END) as active_boms,
        SUM(CASE WHEN bh.is_active = 0 THEN 1 ELSE 0 END) as inactive_boms,
        COUNT(DISTINCT CASE WHEN bh.approved_by IS NULL THEN bh.id END) as draft_boms
    FROM bom_headers bh
    JOIN products p ON bh.product_id = p.id
    WHERE 1=1
";

if ($search) {
    $statsQuery .= " AND (p.product_code LIKE ? OR p.name LIKE ? OR bh.description LIKE ?)";
    $stats = $db->selectOne($statsQuery, [$searchParam, $searchParam, $searchParam], ['s', 's', 's']);
} else {
    $stats = $db->selectOne($statsQuery);
}

// Provide safe defaults
$stats = $stats ?: [
    'total_boms' => 0,
    'active_boms' => 0, 
    'inactive_boms' => 0,
    'draft_boms' => 0
];
?>

<link rel="stylesheet" href="../css/materials-modern.css">
<link rel="stylesheet" href="../css/autocomplete.css">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" style="padding-top: 2rem;">
    <!-- Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex justify-between items-center">
                <?php if ($filterProduct): ?>
                    <h1 class="text-xl font-semibold text-gray-900">BOMs for Product: <?php echo htmlspecialchars($filterProduct['product_code'] . ' - ' . $filterProduct['name']); ?></h1>
                <?php else: ?>
                    <h1 class="text-xl font-semibold text-gray-900">Bill of Materials (BOM) Management</h1>
                <?php endif; ?>
                <a href="create.php" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add BOM
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
        
            <form method="GET" action="" id="searchForm" class="space-y-4">
                <?php if ($productId): ?>
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                <?php endif; ?>
                
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
                           placeholder="Search BOMs by product code, name, or description..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                           data-autocomplete-preset="bom-search"
                           autocomplete="off"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>

                <!-- Search Controls -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                            Search
                        </button>
                        <?php if (!empty($_GET['search']) || $showInactive || $filterStatus !== 'all'): ?>
                            <a href="<?php echo $productId ? 'index.php?product_id=' . $productId : 'index.php'; ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200">
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
                        <span class="ml-2 text-sm text-gray-600">Include Inactive BOMs</span>
                    </label>
                </div>
            </form>
        </div>
    </div>
    <!-- Products without BOMs Alert -->
    <?php if (!empty($productsWithoutBOM)): ?>
    <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-yellow-800">Products Missing BOMs</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <p>These products don't have BOMs yet - they need them for MRP calculations:</p>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?php foreach ($productsWithoutBOM as $product): ?>
                        <a href="create.php?product_id=<?php echo $product['id']; ?>" class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-yellow-800 bg-yellow-100 hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                            <strong><?php echo htmlspecialchars($product['product_code']); ?></strong>
                            <span class="ml-1"><?php echo htmlspecialchars($product['name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($productsWithoutBOM) >= 10): ?>
                        <span class="text-xs text-yellow-600">...and possibly more</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- BOM List Content -->
    <?php if (!empty($boms)): ?>
        <!-- BOMs List -->
        <div class="space-y-6">
            <!-- List Header & Filters -->
            <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium text-gray-900">BOM Inventory</h2>
                            <p class="text-sm text-gray-500"><?php echo count($boms); ?> BOMs found</p>
                        </div>
                        
                        <!-- Filter Buttons - Mobile Optimized -->
                        <div class="overflow-x-auto pb-2 -mx-2" style="padding-top: 0.5rem; margin-top: 0.25rem;">
                            <div class="flex space-x-2 px-2 min-w-max" id="quickFilters" style="padding-top: 0.25rem; padding-bottom: 0.25rem;">
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn <?php echo ($filterStatus === 'all' && !$showInactive) ? 'active ring-2 ring-primary' : ''; ?>"
                                        onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId : '?'; ?>'">
                                    <span class="whitespace-nowrap">Active BOMs</span>
                                </button>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200 filter-btn <?php echo ($showInactive) ? 'active ring-2 ring-primary' : ''; ?>"
                                        onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>show_inactive=1'">
                                    <span class="whitespace-nowrap">All BOMs</span>
                                </button>
                                <?php if ($stats['inactive_boms'] > 0): ?>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-yellow-200 text-yellow-800 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 filter-btn <?php echo ($filterStatus === 'inactive') ? 'active ring-2 ring-yellow-500' : ''; ?>"
                                        onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>status=inactive'">
                                    <span class="whitespace-nowrap">Inactive</span>
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-yellow-200 text-yellow-900">
                                        <?php echo $stats['inactive_boms']; ?>
                                    </span>
                                </button>
                                <?php endif; ?>
                                <?php if ($stats['draft_boms'] > 0): ?>
                                <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg border border-red-200 text-red-800 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200 filter-btn <?php echo ($filterStatus === 'draft') ? 'active ring-2 ring-red-500' : ''; ?>"
                                        onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>status=draft'">
                                    <span class="whitespace-nowrap">Draft</span>
                                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-medium bg-red-200 text-red-900">
                                        <?php echo $stats['draft_boms']; ?>
                                    </span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions Bar (Hidden by default) -->
                <div id="bulkActionsBar" class="hidden px-6 py-3 bg-blue-50 border-b border-blue-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-blue-900" id="selectedCount">0</span>
                            <span class="ml-1 text-sm text-blue-700">BOMs selected</span>
                        </div>
                        <div class="flex items-center space-x-3">
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" id="bulkExport">
                                Export
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" id="bulkActivate">
                                Activate
                            </button>
                            <button class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors duration-200" id="bulkDeactivate">
                                Deactivate
                            </button>
                        </div>
                    </div>
                </div>
                <!-- BOMs List -->
                <div class="divide-y divide-gray-200" id="bomsList">
                    <?php foreach ($boms as $bom): ?>
                    <?php 
                    $statusClasses = [
                        'active' => 'border-l-4 border-green-500 bg-green-50',
                        'inactive' => 'border-l-4 border-red-500 bg-red-50'
                    ];
                    
                    $statusDotClasses = [
                        'active' => 'bg-green-400',
                        'inactive' => 'bg-red-400'
                    ];
                    
                    $bomStatus = $bom['is_active'] ? 'active' : 'inactive';
                    ?>
                    
                    <div class="<?php echo $statusClasses[$bomStatus]; ?> hover:bg-gray-50 transition-colors duration-200 list-item" 
                         data-bom-id="<?php echo $bom['id']; ?>" 
                         data-status="<?php echo $bomStatus; ?>">
                         
                        <div class="px-4 sm:px-6 py-4">
                            <!-- Mobile-optimized layout -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                <!-- Row 1: Checkbox + BOM Identity -->
                                <div class="flex items-center space-x-3 flex-1 min-w-0">
                                    <!-- Checkbox -->
                                    <input type="checkbox" 
                                           class="w-4 h-4 rounded border-gray-300 text-primary shadow-sm focus:border-primary focus:ring-primary item-checkbox" 
                                           value="<?php echo $bom['id']; ?>" 
                                           onchange="updateBulkActions()">
                                    
                                    <!-- Status Dot -->
                                    <div class="flex-shrink-0">
                                        <span class="inline-block h-3 w-3 rounded-full <?php echo $statusDotClasses[$bomStatus]; ?>"></span>
                                    </div>
                                    
                                    <!-- BOM Details -->
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <p class="text-sm font-semibold text-gray-900 truncate">
                                                <?php echo htmlspecialchars($bom['product_code']); ?>
                                            </p>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 flex-shrink-0">
                                                v<?php echo htmlspecialchars($bom['version']); ?>
                                            </span>
                                            <?php if ($bom['approval_status'] === 'approved'): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 flex-shrink-0">
                                                    Approved
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 flex-shrink-0">
                                                    Draft
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-sm text-gray-900 font-medium mb-1 line-clamp-1">
                                            <?php echo htmlspecialchars($bom['product_name']); ?>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                            <span>Description: <?php echo htmlspecialchars($bom['description'] ?: 'No description'); ?></span>
                                            <span>Materials: <?php echo $bom['material_count']; ?></span>
                                            <span>Effective: <?php echo date('M j, Y', strtotime($bom['effective_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2: Status Metrics (Mobile: Stacked, Desktop: Horizontal) -->
                                <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-6 text-right sm:flex-shrink-0">
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-500 mb-1">Status</p>
                                        <p class="text-sm font-semibold truncate <?php echo $bom['is_active'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $bom['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </p>
                                    </div>
                                    <?php if ($bom['approved_by']): ?>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-gray-500 mb-1">Approved By</p>
                                        <p class="text-sm text-gray-600 truncate">
                                            <?php echo htmlspecialchars($bom['approved_by']); ?>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Row 3: Actions -->
                                <div class="flex items-center justify-end space-x-2 sm:flex-shrink-0">
                                    <button class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary rounded-lg border border-gray-200 hover:border-gray-300 transition-all duration-200" title="Toggle Status" onclick="toggleBomStatus(<?php echo $bom['id']; ?>)">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
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
                                                <a href="view.php?id=<?php echo $bom['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    View Details
                                                </a>
                                                <a href="edit.php?id=<?php echo $bom['id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit BOM
                                                </a>
                                                <a href="create.php?product_id=<?php echo $bom['product_id']; ?>" class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">
                                                    <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                                    </svg>
                                                    New Version
                                                </a>
                                                <hr class="my-1">
                                                <button class="flex items-center w-full px-4 py-3 text-sm text-red-700 hover:bg-red-50" onclick="confirmDelete(<?php echo $bom['id']; ?>)">
                                                    <svg class="mr-3 h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Delete
                                                </button>
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
    <?php else: ?>
        <!-- Empty State -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No BOMs found</h3>
                <?php if ($search): ?>
                    <p class="mt-1 text-sm text-gray-500">
                        No BOMs match your search criteria.<br>
                        <a href="<?php echo $productId ? 'index.php?product_id=' . $productId : 'index.php'; ?>" class="text-primary hover:text-primary-dark">Clear search</a> or 
                        <a href="create.php" class="text-primary hover:text-primary-dark">create new BOM</a>
                    </p>
                <?php else: ?>
                    <p class="mt-1 text-sm text-gray-500">Ready to create your first BOM! Define which materials are needed for each product.</p>
                    <div class="mt-6">
                        <a href="create.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create First BOM
                        </a>
                    </div>
                    <p class="mt-4 text-xs text-gray-500"><strong>Tip:</strong> Start with one of your key products to test the MRP system.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>
<script>
// AutocompleteManager will auto-initialize search history based on data-autocomplete-preset attribute

// Action menu functionality with improved debugging - FROM DOCUMENTATION
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
                
                // Toggle this menu using hidden classes
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

// Setup bulk selection
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.item-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const countEl = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkBar.classList.remove('hidden');
        countEl.textContent = checkboxes.length;
    } else {
        bulkBar.classList.add('hidden');
    }
}

// BOM-specific functions
function toggleBomStatus(bomId) {
    if (confirm('Toggle the status of this BOM?')) {
        // Implementation for status toggle
        window.location.href = `toggle-status.php?id=${bomId}`;
    }
}

function confirmDelete(bomId) {
    if (confirm('Are you sure you want to delete this BOM? This action cannot be undone.')) {
        window.location.href = `delete.php?id=${bomId}`;
    }
}

// Initialize everything
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
    console.log('BOM page initialized with action menus and automatic search history');
});
</script>

<?php 
$include_autocomplete = false; // Scripts already loaded above
require_once '../../includes/footer-tailwind.php'; 
?>