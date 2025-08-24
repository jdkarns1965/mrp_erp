<?php
session_start();
require_once '../../includes/header.php';
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

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Products Management</h2>
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
                                   placeholder="Search products by code, name, or category..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   data-autocomplete-preset="products-search"
                                   autocomplete="off">
                            
                            <!-- Recent Searches -->
                            <div class="recent-searches" id="recentSearches">
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
                                Include Inactive Products
                            </label>
                        </div>
                    </form>
                </div>
                
                <div class="search-actions">
                    <a href="create.php" class="btn btn-primary">Add Product</a>
                </div>
            </div>
        </div>
        
        <!-- Products Content -->
        <?php if (empty($products)): ?>
            <div class="products-list-modern">
                <div class="empty-state-modern">
                    <div class="icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M7 8h10m-10 4h8m-8 4h6M3 3h18v18H3z"/>
                        </svg>
                    </div>
                    <h3>No Products Found</h3>
                    <?php if ($search): ?>
                        <p>No products match your search criteria.<br>
                        <a href="index.php" class="btn btn-outline btn-sm">Clear search</a> or 
                        <a href="create.php" class="btn btn-primary btn-sm">add a new product</a></p>
                    <?php else: ?>
                        <p>Get started by creating your first product for inventory tracking and BOM management.</p>
                        <a href="create.php" class="btn btn-primary">Create First Product</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Modern Products List -->
            <div class="products-list-modern">
                <div class="materials-list-header">
                    <h2 class="list-title">Products Inventory</h2>
                    <div class="list-meta"><?php echo count($products); ?> products found</div>
                </div>
                
                <!-- Filter Panel -->
                <div class="filter-panel">
                    <div class="quick-filters" id="quickFilters">
                        <button class="filter-btn active" data-filter="all" onclick="filterProducts('all')">All Products</button>
                        <button class="filter-btn alert" data-filter="low-stock" onclick="filterProducts('low-stock')">
                            Low Stock
                            <?php 
                            $lowStock = array_filter($products, fn($p) => $p['current_stock'] < $p['safety_stock_qty'] && $p['current_stock'] > 0);
                            if (count($lowStock) > 0) echo '<span class="badge">' . count($lowStock) . '</span>';
                            ?>
                        </button>
                        <button class="filter-btn alert" data-filter="out-of-stock" onclick="filterProducts('out-of-stock')">
                            Out of Stock
                            <?php 
                            $outOfStock = array_filter($products, fn($p) => $p['current_stock'] <= 0);
                            if (count($outOfStock) > 0) echo '<span class="badge">' . count($outOfStock) . '</span>';
                            ?>
                        </button>
                        <button class="filter-btn" data-filter="no-bom" onclick="filterProducts('no-bom')">
                            No BOM
                            <?php 
                            $noBom = array_filter($products, fn($p) => empty($p['bom_count']));
                            if (count($noBom) > 0) echo '<span class="badge">' . count($noBom) . '</span>';
                            ?>
                        </button>
                        <?php if ($showInactive): ?>
                        <button class="filter-btn" data-filter="inactive" onclick="filterProducts('inactive')">
                            Inactive
                            <?php 
                            $inactive = array_filter($products, fn($p) => !$p['is_active']);
                            if (count($inactive) > 0) echo '<span class="badge">' . count($inactive) . '</span>';
                            ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bulk Actions Bar (Hidden by default) -->
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <div class="bulk-info">
                        <span id="selectedCount">0</span> products selected
                    </div>
                    <div class="bulk-actions">
                        <button class="bulk-btn" onclick="bulkExport()">Export</button>
                        <button class="bulk-btn" onclick="bulkStockAdjust()">Stock Adjust</button>
                        <button class="bulk-btn primary" onclick="bulkCreateBom()">Create BOMs</button>
                    </div>
                </div>
                
                <!-- Products List -->
                <div class="materials-list" id="productsList">
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
                    
                    <div class="list-item <?php echo !$prod['is_active'] ? 'inactive' : ''; ?> <?php echo $stockLevel; ?>" 
                         data-id="<?php echo $prod['id']; ?>" 
                         data-category="<?php echo strtolower($prod['category_name'] ?? ''); ?>"
                         data-stock-level="<?php echo $stockLevel; ?>"
                         data-name="<?php echo strtolower($prod['name']); ?>"
                         data-code="<?php echo strtolower($prod['product_code']); ?>"
                         data-cost="<?php echo $prod['standard_cost']; ?>"
                         data-has-bom="<?php echo !empty($prod['bom_count']) ? 'yes' : 'no'; ?>">
                         
                        <div class="item-selector">
                            <input type="checkbox" class="item-checkbox" value="<?php echo $prod['id']; ?>" onchange="updateBulkActions()">
                        </div>
                        
                        <div class="item-primary">
                            <div class="item-header">
                                <span class="entity-code"><?php echo htmlspecialchars($prod['product_code']); ?></span>
                                <div class="status-indicators">
                                    <?php if ($stockLevel === 'critical'): ?>
                                        <span class="stock-status critical" title="Out of Stock"></span>
                                    <?php elseif ($stockLevel === 'warning'): ?>
                                        <span class="stock-status warning" title="Below Safety Stock"></span>
                                    <?php else: ?>
                                        <span class="stock-status good" title="Stock OK"></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($prod['category_name'])): ?>
                                        <span class="type-badge"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!$prod['is_active']): ?>
                                        <span class="type-badge inactive">Inactive</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($prod['is_lot_controlled']): ?>
                                        <span class="type-badge lot">Lot Controlled</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3 class="entity-name"><?php echo htmlspecialchars($prod['name']); ?></h3>
                            <div class="item-meta">
                                <span>UOM: <?php echo htmlspecialchars($prod['uom_code'] ?? 'N/A'); ?></span>
                                <span>Cost: $<?php echo number_format($prod['standard_cost'], 2); ?></span>
                                <span>Price: $<?php echo number_format($prod['selling_price'], 2); ?></span>
                                <?php if (!empty($prod['bom_count'])): ?>
                                    <span>BOMs: <?php echo $prod['bom_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="item-metrics">
                            <div class="metric">
                                <label>Current Stock</label>
                                <span class="value <?php echo $stockLevel === 'critical' ? 'critical' : ($stockLevel === 'warning' ? 'warning' : ''); ?>">
                                    <?php echo number_format($currentStock, 2); ?>
                                </span>
                            </div>
                            <div class="metric">
                                <label>Safety Stock</label>
                                <span class="value"><?php echo number_format($safetyStock, 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="item-actions">
                            <button class="action-quick" title="Quick View" onclick="window.location.href='view.php?id=<?php echo $prod['id']; ?>';">⚡</button>
                            <button class="action-menu-toggle" title="More Actions">⋮</button>
                            <div class="action-menu">
                                <a href="view.php?id=<?php echo $prod['id']; ?>" class="action-item">
                                    <span class="action-icon">👁️</span>
                                    <span class="action-text">View Details</span>
                                </a>
                                <a href="edit.php?id=<?php echo $prod['id']; ?>" class="action-item">
                                    <span class="action-icon">✏️</span>
                                    <span class="action-text">Edit Product</span>
                                </a>
                                <?php if (!empty($prod['bom_count'])): ?>
                                    <a href="../bom/index.php?product_id=<?php echo $prod['id']; ?>" class="action-item">
                                        <span class="action-icon">📋</span>
                                        <span class="action-text">View BOMs (<?php echo $prod['bom_count']; ?>)</span>
                                    </a>
                                <?php else: ?>
                                    <a href="../bom/create.php?product_id=<?php echo $prod['id']; ?>" class="action-item">
                                        <span class="action-icon">➕</span>
                                        <span class="action-text">Create BOM</span>
                                    </a>
                                <?php endif; ?>
                                <a href="../inventory/" class="action-item">
                                    <span class="action-icon">📦</span>
                                    <span class="action-text">Manage Inventory</span>
                                </a>
                                <div class="action-separator"></div>
                                <a href="index.php?action=delete&id=<?php echo $prod['id']; ?>" 
                                   class="action-item danger"
                                   onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                    <span class="action-icon">🗑️</span>
                                    <span class="action-text">Delete Product</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Info -->
                <div class="summary-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($products); ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    
                    <?php
                    $belowSafety = count(array_filter($products, fn($p) => $p['safety_stock_qty'] > 0 && ($p['current_stock'] ?? 0) < $p['safety_stock_qty']));
                    $outOfStock = count(array_filter($products, fn($p) => ($p['current_stock'] ?? 0) <= 0));
                    $withBom = count(array_filter($products, fn($p) => !empty($p['bom_count'])));
                    ?>
                    
                    <?php if ($belowSafety > 0): ?>
                    <div class="stat-card warning">
                        <div class="stat-value"><?php echo $belowSafety; ?></div>
                        <div class="stat-label">Below Safety Stock</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($outOfStock > 0): ?>
                    <div class="stat-card critical">
                        <div class="stat-value"><?php echo $outOfStock; ?></div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $withBom; ?></div>
                        <div class="stat-label">With BOMs</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="btn-group">
            <a href="../" class="btn btn-secondary">Back to Dashboard</a>
            <a href="../bom/" class="btn btn-primary">Manage BOMs</a>
            <a href="../inventory/" class="btn btn-primary">Manage Inventory</a>
        </div>
    </div>
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

// Setup action menus using the established pattern
function setupActionMenus() {
    document.addEventListener('click', function(e) {
        console.log('Click detected:', e.target.className, e.target.textContent.trim());
        
        // Handle action menu toggles
        if (e.target.classList.contains('action-menu-toggle')) {
            console.log('Action menu toggle clicked');
            e.preventDefault();
            e.stopPropagation();
            
            // Close all other menus
            document.querySelectorAll('.action-menu').forEach(menu => {
                if (menu !== e.target.nextElementSibling) {
                    menu.style.display = 'none';
                }
            });
            
            // Toggle this menu
            const menu = e.target.nextElementSibling;
            if (menu && menu.classList.contains('action-menu')) {
                const isVisible = menu.style.display === 'block';
                menu.style.display = isVisible ? 'none' : 'block';
                console.log('Menu toggled to:', menu.style.display);
            }
        }
        // Close menus when clicking outside
        else if (!e.target.closest('.item-actions')) {
            document.querySelectorAll('.action-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
}

// Recent searches functionality (copied from Materials page)
function initializeRecentSearches() {
    const searchInput = document.getElementById('searchInput');
    const recentSearches = document.getElementById('recentSearches');
    const recentSearchesList = document.getElementById('recentSearchesList');
    
    if (!searchInput || !recentSearches || !recentSearchesList) return;
    
    // Show recent searches immediately on page load
    updateRecentSearchesDisplay();
    
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
        // Hide only if there are no searches
        recentSearches.style.display = 'none';
        return;
    }
    
    // Always show if there are searches
    recentSearches.style.display = 'block';
    
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
        const searches = localStorage.getItem('productRecentSearches');
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
        
        localStorage.setItem('productRecentSearches', JSON.stringify(searches));
        
        // Update display immediately after saving
        updateRecentSearchesDisplay();
    } catch (e) {
        // Fail silently if localStorage is not available
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
    initializeRecentSearches();
    console.log('Products page initialized with action menus and recent searches');
});
</script>

<link rel="stylesheet" href="../css/autocomplete.css">
<link rel="stylesheet" href="../css/materials-modern.css">
<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>

<?php require_once '../../includes/footer.php'; ?>