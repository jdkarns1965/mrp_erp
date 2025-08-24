<?php
session_start();
require_once '../../includes/header.php';
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

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Materials Management</h2>
        </div>
    
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Search Bar with Add Button -->
        <div class="search-bar">
            <div class="search-bar-header">
                <div class="search-form-container">
                    <form method="GET" action="" id="searchForm">
                        <!-- Search Input Field -->
                        <div class="search-input-section">
                            <input type="text" 
                                   id="searchInput"
                                   name="search" 
                                   placeholder="Search materials by code, name, or category..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   data-autocomplete-preset="materials-search"
                                   autocomplete="off">
                            
                            <!-- Recent Searches -->
                            <div class="recent-searches" id="recentSearches" style="display: none;">
                                <div class="recent-searches-label">Recent:</div>
                                <div class="recent-searches-list" id="recentSearchesList"></div>
                            </div>
                        </div>
                        
                        <!-- Search Controls -->
                        <div class="search-controls">
                            <div class="search-buttons">
                                <button type="submit" class="btn btn-secondary">Search</button>
                                <?php if (!empty($_GET['search']) || $showInactive): ?>
                                    <a href="index.php" class="btn btn-outline">Clear</a>
                                <?php endif; ?>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       name="show_inactive" 
                                       value="1"
                                       <?php echo $showInactive ? 'checked' : ''; ?>
                                       onchange="this.form.submit();">
                                Include Inactive Materials
                            </label>
                        </div>
                    </form>
                </div>
                
                <div class="search-actions">
                    <a href="create.php" class="btn btn-primary">Add Material</a>
                </div>
            </div>
        </div>
        
        <!-- Materials Content -->
        <?php if (empty($materials)): ?>
            <div class="materials-list-modern">
                <div class="empty-state-modern">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M9 1v6m6-6v6"/>
                        </svg>
                    </div>
                    <h3>No Materials Found</h3>
                    <?php if ($search): ?>
                        <p>No materials match your search criteria.<br>
                        <a href="index.php" class="btn btn-outline btn-sm">Clear search</a> or 
                        <a href="create.php" class="btn btn-primary btn-sm">add a new material</a></p>
                    <?php else: ?>
                        <p>Get started by creating your first material for inventory tracking and BOM management.</p>
                        <a href="create.php" class="btn btn-primary">Create First Material</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Modern Materials List -->
            <div class="materials-list-modern">
                <div class="materials-list-header">
                    <h2 class="list-title">Materials Inventory</h2>
                    <div class="list-meta"><?php echo count($materials); ?> materials found</div>
                </div>
                
                <!-- Filter Panel -->
                <div class="filter-panel">
                    <div class="quick-filters" id="quickFilters">
                        <button class="filter-btn active" data-filter="all" onclick="filterMaterials('all')">All Materials</button>
                        <button class="filter-btn alert" data-filter="low-stock" onclick="filterMaterials('low-stock')">
                            Low Stock
                            <?php 
                            $lowStock = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point'] && $m['current_stock'] > 0);
                            if (count($lowStock) > 0) echo '<span class="badge">' . count($lowStock) . '</span>';
                            ?>
                        </button>
                        <button class="filter-btn alert" data-filter="out-of-stock" onclick="filterMaterials('out-of-stock')">
                            Out of Stock
                            <?php 
                            $outOfStock = array_filter($materials, fn($m) => $m['current_stock'] <= 0);
                            if (count($outOfStock) > 0) echo '<span class="badge">' . count($outOfStock) . '</span>';
                            ?>
                        </button>
                        <button class="filter-btn" data-filter="need-reorder" onclick="filterMaterials('need-reorder')">
                            Need Reorder
                            <?php 
                            $needReorder = array_filter($materials, fn($m) => $m['current_stock'] < $m['reorder_point']);
                            if (count($needReorder) > 0) echo '<span class="badge">' . count($needReorder) . '</span>';
                            ?>
                        </button>
                    </div>
                    
                </div>
                
                <!-- Bulk Actions Bar (Hidden by default) -->
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <div class="bulk-info">
                        <span id="selectedCount">0</span> materials selected
                    </div>
                    <div class="bulk-actions">
                        <button class="bulk-btn" onclick="bulkExport()">Export</button>
                        <button class="bulk-btn" onclick="bulkStockAdjust()">Stock Adjust</button>
                        <button class="bulk-btn primary" onclick="bulkReorder()">Create PO</button>
                    </div>
                </div>
                
                <!-- Materials List -->
                <div class="materials-list" id="materialsList">
                    <?php foreach ($materials as $material): ?>
                    <?php 
                    $stockLevel = 'good';
                    if ($material['current_stock'] <= 0) {
                        $stockLevel = 'critical';
                    } elseif ($material['current_stock'] < $material['reorder_point']) {
                        $stockLevel = 'warning';
                    }
                    ?>
                    
                    <div class="list-item <?php echo !$material['is_active'] ? 'inactive' : ''; ?> <?php echo $stockLevel; ?>" 
                         data-id="<?php echo $material['id']; ?>" 
                         data-type="<?php echo strtolower($material['material_type']); ?>"
                         data-stock-level="<?php echo $stockLevel; ?>"
                         data-name="<?php echo strtolower($material['name']); ?>"
                         data-code="<?php echo strtolower($material['material_code']); ?>"
                         data-cost="<?php echo $material['cost_per_unit']; ?>">
                         
                        <div class="item-selector">
                            <input type="checkbox" class="item-checkbox" value="<?php echo $material['id']; ?>" onchange="updateBulkActions()">
                        </div>
                        
                        <div class="item-primary" onclick="window.location.href='view.php?id=<?php echo $material['id']; ?>'">
                            <div class="item-header">
                                <span class="material-code"><?php echo htmlspecialchars($material['material_code']); ?></span>
                                <div class="status-indicators">
                                    <span class="stock-status <?php echo $stockLevel; ?>"></span>
                                    <span class="type-badge <?php echo strtolower($material['material_type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $material['material_type'])); ?>
                                    </span>
                                    <?php if (!$material['is_active']): ?>
                                    <span class="status-badge inactive">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3 class="material-name"><?php echo htmlspecialchars($material['name']); ?></h3>
                            <div class="item-meta">
                                <span>Category: <?php echo htmlspecialchars($material['category'] ?? 'General'); ?></span>
                                <span>UOM: <?php echo htmlspecialchars($material['uom_code']); ?></span>
                                <?php if (isset($material['supplier_name'])): ?>
                                <span>Supplier: <?php echo htmlspecialchars($material['supplier_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="item-metrics">
                            <div class="metric">
                                <label>Current Stock</label>
                                <span class="value <?php echo $stockLevel; ?>">
                                    <?php echo number_format($material['current_stock'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?>
                                </span>
                            </div>
                            
                            <div class="metric">
                                <label>Reorder Point</label>
                                <span class="value">
                                    <?php echo number_format($material['reorder_point'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?>
                                </span>
                            </div>
                            
                            <div class="metric">
                                <label>Cost/Unit</label>
                                <span class="value">$<?php echo number_format($material['cost_per_unit'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="item-actions">
                            <?php if ($material['current_stock'] < $material['reorder_point']): ?>
                            <button class="action-quick primary" title="Quick Reorder" onclick="quickReorder(<?php echo $material['id']; ?>)">
                                ⚡
                            </button>
                            <?php else: ?>
                            <button class="action-quick" title="Quick Stock Adjust" onclick="quickStockAdjust(<?php echo $material['id']; ?>)">
                                ⚡
                            </button>
                            <?php endif; ?>
                            
                            <button class="action-menu-toggle" onclick="toggleActionMenu(<?php echo $material['id']; ?>)" type="button">
                                ⋮
                            </button>
                            <div class="action-menu" id="menu-<?php echo $material['id']; ?>">
                                <a href="view.php?id=<?php echo $material['id']; ?>" class="action-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Details
                                </a>
                                <a href="edit.php?id=<?php echo $material['id']; ?>" class="action-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                                    </svg>
                                    Edit Material
                                </a>
                                <a href="../inventory/adjust.php?type=material&id=<?php echo $material['id']; ?>" class="action-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                                    </svg>
                                    Stock History
                                </a>
                                <a href="../bom/index.php?material_id=<?php echo $material['id']; ?>" class="action-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                                    </svg>
                                    BOM Usage
                                </a>
                                <?php if ($material['current_stock'] < $material['reorder_point']): ?>
                                <a href="../purchase/create.php?material_id=<?php echo $material['id']; ?>" class="action-item reorder">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1"/>
                                        <circle cx="20" cy="21" r="1"/>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    Create Purchase Order
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../css/autocomplete.css">
<link rel="stylesheet" href="../css/materials-modern.css">
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
});

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
    
    // Hide recent searches when clicking outside (but not on autocomplete)
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.search-input-section') && 
            !event.target.closest('.autocomplete-dropdown')) {
            recentSearches.style.display = 'none';
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
        recentSearches.style.display = 'none';
        return;
    }
    
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
    
    recentSearches.style.display = 'block';
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
    } catch (e) {
        // Fail silently if localStorage is not available
    }
}

// Action Menu Toggle
function toggleActionMenu(materialId) {
    const menu = document.getElementById('menu-' + materialId);
    const allMenus = document.querySelectorAll('.action-menu');
    
    // Close all other menus
    allMenus.forEach(m => {
        if (m !== menu) {
            m.classList.remove('show');
        }
    });
    
    // Toggle current menu
    menu.classList.toggle('show');
    
    // Prevent event bubbling
    event.stopPropagation();
}

// Close menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.action-menu-toggle')) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

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
    } else {
        bulkBar.classList.remove('show');
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



<?php require_once '../../includes/footer.php'; ?>