<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/Inventory.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';

$inventoryModel = new Inventory();
$materialModel = new Material();
$db = Database::getInstance();

// Get filter parameters
$itemType = $_GET['item_type'] ?? 'all';
$search = $_GET['search'] ?? '';
$stockStatus = $_GET['stock_status'] ?? 'all';

// Get inventory data with material/product details
$sql = "SELECT 
            i.*,
            CASE 
                WHEN i.item_type = 'material' THEN m.material_code
                WHEN i.item_type = 'product' THEN p.product_code
            END AS item_code,
            CASE 
                WHEN i.item_type = 'material' THEN m.name
                WHEN i.item_type = 'product' THEN p.name
            END AS item_name,
            CASE 
                WHEN i.item_type = 'material' THEN mc.name
                WHEN i.item_type = 'product' THEN pc.name
            END AS item_category,
            sl.code as location_code,
            sl.description as location_name,
            uom.code as uom_code,
            s.name as supplier_name,
            CASE 
                WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= CURRENT_DATE THEN 'expired'
                WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 'expiring'
                WHEN (i.quantity - i.reserved_quantity) <= 0 THEN 'out_of_stock'
                WHEN (i.quantity - i.reserved_quantity) <= 10 THEN 'low_stock'
                ELSE 'normal'
            END AS stock_status
        FROM inventory i
        LEFT JOIN materials m ON i.item_type = 'material' AND i.item_id = m.id
        LEFT JOIN products p ON i.item_type = 'product' AND i.item_id = p.id
        LEFT JOIN material_categories mc ON i.item_type = 'material' AND m.category_id = mc.id
        LEFT JOIN product_categories pc ON i.item_type = 'product' AND p.category_id = pc.id
        LEFT JOIN storage_locations sl ON i.location_id = sl.id
        LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        WHERE i.status = 'available'";

$params = [];
$types = [];

if ($itemType !== 'all') {
    $sql .= " AND i.item_type = ?";
    $params[] = $itemType;
    $types[] = 's';
}

if ($stockStatus !== 'all') {
    switch ($stockStatus) {
        case 'expiring':
            $sql .= " AND i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) AND i.expiry_date > CURRENT_DATE";
            break;
        case 'expired':
            $sql .= " AND i.expiry_date IS NOT NULL AND i.expiry_date <= CURRENT_DATE";
            break;
        case 'low_stock':
            $sql .= " AND (i.quantity - i.reserved_quantity) <= 10 AND (i.quantity - i.reserved_quantity) > 0";
            break;
        case 'out_of_stock':
            $sql .= " AND (i.quantity - i.reserved_quantity) <= 0";
            break;
        case 'available':
            $sql .= " AND i.quantity > 0";
            break;
    }
}

if (!empty($search)) {
    $sql .= " AND (
        (i.item_type = 'material' AND (m.material_code LIKE ? OR m.name LIKE ?)) OR
        (i.item_type = 'product' AND (p.product_code LIKE ? OR p.name LIKE ?)) OR
        i.lot_number LIKE ?
    )";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types = array_merge($types, ['s', 's', 's', 's', 's']);
}

$sql .= " ORDER BY 
            CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 0 ELSE 1 END,
            i.expiry_date ASC,
            item_name ASC";

$inventory = $db->select($sql, $params, $types);

// Get summary statistics with counts for filters
$totalItems = count($inventory);
$totalValue = array_sum(array_map(function($item) {
    return $item['quantity'] * ($item['unit_cost'] ?? 0);
}, $inventory));

// Count by status for filter badges
$statusCounts = [
    'expiring' => 0,
    'expired' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'available' => 0
];

foreach ($inventory as $item) {
    if (isset($statusCounts[$item['stock_status']])) {
        $statusCounts[$item['stock_status']]++;
    }
    if ($item['quantity'] > 0) {
        $statusCounts['available']++;
    }
}

$expiringItems = array_filter($inventory, function($item) {
    return $item['stock_status'] === 'expiring' || $item['stock_status'] === 'expired';
});
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            <h2>Inventory Management</h2>
        </div>
        
        <!-- Search Bar with Add Button -->
        <div class="search-bar">
            <div class="search-bar-header">
                <div class="search-form-container">
                    <form method="GET" id="searchForm">
                        <div class="search-input-section">
                            <input type="text" name="search" id="search" placeholder="Search inventory by item code, name, or lot number..."
                                   data-autocomplete-preset="inventory-search"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   autocomplete="off">
                            <div class="recent-searches" id="recentSearches">
                                <!-- Recent searches populated by JavaScript -->
                            </div>
                        </div>
                        <div class="search-controls">
                            <div class="search-buttons">
                                <button type="submit" class="btn btn-secondary">Search</button>
                                <a href="index.php" class="btn btn-outline">Clear</a>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox" name="include_reserved" value="1"
                                       <?php echo !empty($_GET['include_reserved']) ? 'checked' : ''; ?>>
                                Include Reserved
                            </label>
                        </div>
                        <!-- Hidden filters -->
                        <input type="hidden" name="item_type" value="<?php echo htmlspecialchars($itemType); ?>">
                        <input type="hidden" name="stock_status" value="<?php echo htmlspecialchars($stockStatus); ?>">
                    </form>
                </div>
                <div class="search-actions">
                    <a href="receive.php" class="btn btn-primary">Receive Inventory</a>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                <h3 class="text-2xl font-bold text-gray-900 mb-1"><?php echo $totalItems; ?></h3>
                <p class="text-sm text-gray-600">Total Lots</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                <h3 class="text-2xl font-bold text-gray-900 mb-1">$<?php echo number_format($totalValue, 2); ?></h3>
                <p class="text-sm text-gray-600">Total Value</p>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-6 text-center">
                <h3 class="text-2xl font-bold mb-1 <?php echo count($expiringItems) > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                    <?php echo count($expiringItems); ?>
                </h3>
                <p class="text-sm text-gray-600">Expiring Soon</p>
            </div>
        </div>
        
        <!-- Filter Panel -->
        <div class="filter-panel">
            <div class="quick-filters">
                <a href="?" class="filter-btn <?php echo ($itemType === 'all' && $stockStatus === 'all') ? 'active' : ''; ?>">
                    All Items
                </a>
                <a href="?item_type=material" class="filter-btn <?php echo $itemType === 'material' ? 'active' : ''; ?>">
                    Materials
                </a>
                <a href="?item_type=product" class="filter-btn <?php echo $itemType === 'product' ? 'active' : ''; ?>">
                    Products
                </a>
                <a href="?stock_status=expiring" class="filter-btn alert <?php echo $stockStatus === 'expiring' ? 'active' : ''; ?>">
                    Expiring Soon
                    <?php if ($statusCounts['expiring'] > 0): ?>
                        <span class="badge"><?php echo $statusCounts['expiring']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?stock_status=low_stock" class="filter-btn alert <?php echo $stockStatus === 'low_stock' ? 'active' : ''; ?>">
                    Low Stock
                    <?php if ($statusCounts['low_stock'] > 0): ?>
                        <span class="badge"><?php echo $statusCounts['low_stock']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="?stock_status=out_of_stock" class="filter-btn alert <?php echo $stockStatus === 'out_of_stock' ? 'active' : ''; ?>">
                    Out of Stock
                    <?php if ($statusCounts['out_of_stock'] > 0): ?>
                        <span class="badge"><?php echo $statusCounts['out_of_stock']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar hidden" id="bulkActionsBar">
            <div class="bulk-info">
                <span id="selectedCount">0</span> items selected
            </div>
            <div class="bulk-actions">
                <button type="button" class="bulk-btn" onclick="exportSelectedItems()">Export</button>
                <button type="button" class="bulk-btn primary" onclick="bulkAdjustStock()">Adjust Stock</button>
                <button type="button" class="bulk-btn" onclick="bulkTransferItems()">Transfer</button>
            </div>
        </div>
        
        <?php if (count($expiringItems) > 0): ?>
            <div class="alert alert-warning" style="margin-bottom: 1rem;">
                <strong>⚠️ Warning:</strong> <?php echo count($expiringItems); ?> lots are expiring within 30 days!
            </div>
        <?php endif; ?>
        
        <!-- Inventory List -->
        <div class="inventory-list-modern">
            <div class="inventory-list-header">
                <h2 class="list-title">Inventory Items</h2>
                <div class="list-meta"><?php echo $totalItems; ?> lots found</div>
            </div>
            
            <div class="inventory-list" id="inventoryList">
                <?php if (empty($inventory)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📦</div>
                        <h3>No inventory found</h3>
                        <p>
                            <?php if (!empty($search) || $itemType !== 'all' || $stockStatus !== 'all'): ?>
                                No inventory matches your current filters.
                            <?php else: ?>
                                Start by receiving inventory into the system.
                            <?php endif; ?>
                        </p>
                        <?php if (empty($search) && $itemType === 'all' && $stockStatus === 'all'): ?>
                            <a href="receive.php" class="btn btn-primary">Receive First Items</a>
                        <?php else: ?>
                            <a href="index.php" class="btn btn-outline">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventory as $item): ?>
                        <?php 
                        $available = $item['quantity'] - $item['reserved_quantity'];
                        $totalValue = $item['quantity'] * ($item['unit_cost'] ?? 0);
                        $statusClass = $item['stock_status'];
                        ?>
                        <div class="list-item" data-status="<?php echo $statusClass; ?>" data-item-type="<?php echo $item['item_type']; ?>">
                            <div class="item-selector">
                                <input type="checkbox" class="item-checkbox" data-id="<?php echo $item['id']; ?>">
                            </div>
                            <div class="item-primary">
                                <div class="item-header">
                                    <span class="entity-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
                                    <div class="status-indicators">
                                        <span class="stock-status <?php echo $statusClass; ?>"></span>
                                        <span class="type-badge"><?php echo ucfirst($item['item_type']); ?></span>
                                        <?php if ($item['lot_number']): ?>
                                            <span class="lot-badge">Lot: <?php echo htmlspecialchars($item['lot_number']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h3 class="entity-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                <div class="item-meta">
                                    <span>Category: <?php echo htmlspecialchars($item['item_category'] ?? 'N/A'); ?></span>
                                    <span>UOM: <?php echo htmlspecialchars($item['uom_code']); ?></span>
                                    <span>Location: <?php echo htmlspecialchars($item['location_code'] ?? 'N/A'); ?></span>
                                    <?php if ($item['supplier_name']): ?>
                                        <span>Supplier: <?php echo htmlspecialchars($item['supplier_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['expiry_date']): ?>
                                        <span class="expiry-date <?php echo $statusClass === 'expired' ? 'expired' : ($statusClass === 'expiring' ? 'expiring' : ''); ?>">
                                            Expires: <?php echo date('M j, Y', strtotime($item['expiry_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="item-metrics">
                                <div class="metric">
                                    <label>Quantity</label>
                                    <span class="value"><?php echo number_format($item['quantity'], 2); ?></span>
                                </div>
                                <div class="metric">
                                    <label>Available</label>
                                    <span class="value <?php echo $available <= 0 ? 'text-red' : ''; ?>"><?php echo number_format($available, 2); ?></span>
                                </div>
                                <div class="metric">
                                    <label>Unit Cost</label>
                                    <span class="value">$<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></span>
                                </div>
                                <div class="metric">
                                    <label>Total Value</label>
                                    <span class="value">$<?php echo number_format($totalValue, 2); ?></span>
                                </div>
                            </div>
                            <div class="item-actions">
                                <a href="adjust.php?id=<?php echo $item['id']; ?>" class="action-quick" title="Adjust Stock">⚡</a>
                                <button class="action-menu-toggle" type="button">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                        <path d="M8 9.5C8.825 9.5 9.5 8.825 9.5 8C9.5 7.175 8.825 6.5 8 6.5C7.175 6.5 6.5 7.175 6.5 8C6.5 8.825 7.175 9.5 8 9.5ZM8 2.5C8.825 2.5 9.5 1.825 9.5 1C9.5 0.175 8.825 -0.5 8 -0.5C7.175 -0.5 6.5 0.175 6.5 1C6.5 1.825 7.175 2.5 8 2.5ZM8 13.5C7.175 13.5 6.5 14.175 6.5 15C6.5 15.825 7.175 16.5 8 16.5C8.825 16.5 9.5 15.825 9.5 15C9.5 14.175 8.825 13.5 8 13.5Z" fill="currentColor"/>
                                    </svg>
                                </button>
                                <div class="action-menu hidden">
                                    <a href="adjust.php?id=<?php echo $item['id']; ?>" class="menu-item">
                                        <span class="menu-icon">📝</span>
                                        <span>Adjust Stock</span>
                                    </a>
                                    <a href="transfer.php?id=<?php echo $item['id']; ?>" class="menu-item">
                                        <span class="menu-icon">📦</span>
                                        <span>Transfer</span>
                                    </a>
                                    <a href="issue.php?id=<?php echo $item['id']; ?>" class="menu-item">
                                        <span class="menu-icon">📋</span>
                                        <span>Issue</span>
                                    </a>
                                    <div class="menu-divider"></div>
                                    <a href="view.php?id=<?php echo $item['id']; ?>" class="menu-item">
                                        <span class="menu-icon">👁️</span>
                                        <span>View Details</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../css/materials-modern.css">
<link rel="stylesheet" href="../css/autocomplete.css">
<style>
/* Inventory-specific styles */
.inventory-list-modern { overflow: visible; }
.inventory-list { overflow: visible; }

/* Stock status indicators */
.stock-status {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}
.stock-status.normal { background-color: #10b981; }
.stock-status.low_stock { background-color: #f59e0b; }
.stock-status.out_of_stock { background-color: #ef4444; }
.stock-status.expiring { background-color: #f97316; }
.stock-status.expired { background-color: #991b1b; }

/* Additional badges */
.lot-badge {
    background-color: #e5e7eb;
    color: #374151;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.expiry-date {
    color: #6b7280;
}
.expiry-date.expiring {
    color: #f97316;
    font-weight: 600;
}
.expiry-date.expired {
    color: #dc2626;
    font-weight: 600;
}

.text-red {
    color: #dc2626;
    font-weight: 600;
}

/* CSS alias for consistency */
.inventory-list-modern .materials-list { display: contents; }
</style>

<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>

<script>
// Inventory-specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inventory page loaded');
    
    // Setup bulk selection
    setupBulkSelection();
    
    // Setup action menus
    setupActionMenus();
});

// Bulk selection functionality
function setupBulkSelection() {
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllCheckbox = document.getElementById('selectAll');
    
    // Handle individual checkbox changes
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('item-checkbox')) {
            updateBulkActionsBar();
        }
    });
    
    function updateBulkActionsBar() {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        
        selectedCount.textContent = checkedBoxes.length;
        
        if (checkedBoxes.length > 0) {
            bulkActionsBar.classList.remove('hidden');
        } else {
            bulkActionsBar.classList.add('hidden');
        }
    }
}

// Setup action menus using the established pattern
function setupActionMenus() {
    console.log('Setting up inventory action menus...');
    
    document.addEventListener('click', function(e) {
        const toggleButton = e.target.closest('.action-menu-toggle');
        if (toggleButton) {
            e.preventDefault();
            
            const menu = toggleButton.nextElementSibling;
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
                } else {
                    menu.classList.add('hidden');
                }
            }
            return;
        }
        
        // Close all menus when clicking outside
        if (!e.target.closest('.action-menu')) {
            const openMenus = document.querySelectorAll('.action-menu:not(.hidden)');
            openMenus.forEach(menu => menu.classList.add('hidden'));
        }
    });
}

// Bulk action functions
function exportSelectedItems() {
    const selectedIds = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.dataset.id);
    
    if (selectedIds.length === 0) {
        alert('Please select items to export');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export.php';
    
    selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function bulkAdjustStock() {
    const selectedIds = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.dataset.id);
    
    if (selectedIds.length === 0) {
        alert('Please select items to adjust');
        return;
    }
    
    const params = selectedIds.map(id => `ids[]=${id}`).join('&');
    window.location.href = `bulk-adjust.php?${params}`;
}

function bulkTransferItems() {
    const selectedIds = Array.from(document.querySelectorAll('.item-checkbox:checked'))
        .map(cb => cb.dataset.id);
    
    if (selectedIds.length === 0) {
        alert('Please select items to transfer');
        return;
    }
    
    const params = selectedIds.map(id => `ids[]=${id}`).join('&');
    window.location.href = `bulk-transfer.php?${params}`;
}
</script>

<?php
$include_autocomplete = false; // Prevent duplicate script loading
require_once '../../includes/footer-tailwind.php';
?>