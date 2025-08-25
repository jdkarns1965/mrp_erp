<?php
session_start();
require_once '../../includes/header.php';
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

<div class="container">
    <div class="card">
        <div class="card-header">
            <?php if ($filterProduct): ?>
                <h2>BOMs for Product: <?php echo htmlspecialchars($filterProduct['product_code'] . ' - ' . $filterProduct['name']); ?></h2>
            <?php else: ?>
                <h2>Bill of Materials (BOM) Management</h2>
            <?php endif; ?>
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
            <div class="alert alert-error">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <div class="search-bar-header">
                <div class="search-form-container">
                    <form method="GET" action="" id="searchForm">
                        <?php if ($productId): ?>
                            <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <?php endif; ?>
                        
                        <!-- Search Input Field -->
                        <div class="search-input-section">
                            <input type="text" 
                                   id="searchInput"
                                   name="search" 
                                   placeholder="Search BOMs by product code, name, or description..." 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   data-autocomplete-preset="bom-search"
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
                                <?php if (!empty($_GET['search']) || $showInactive || $filterStatus !== 'all'): ?>
                                    <a href="<?php echo $productId ? 'index.php?product_id=' . $productId : 'index.php'; ?>" class="btn btn-outline">Clear</a>
                                <?php endif; ?>
                            </div>
                            <label class="checkbox-label">
                                <input type="checkbox" 
                                       name="show_inactive" 
                                       value="1"
                                       <?php echo $showInactive ? 'checked' : ''; ?>
                                       onchange="this.form.submit();">
                                Include Inactive BOMs
                            </label>
                        </div>
                    </form>
                </div>
                
                <div class="search-actions">
                    <?php if ($filterProduct): ?>
                        <a href="create.php?product_id=<?php echo $productId; ?>" class="btn btn-primary">Add New Version</a>
                        <a href="index.php" class="btn btn-secondary">View All BOMs</a>
                    <?php else: ?>
                        <a href="create.php" class="btn btn-primary">Create BOM</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Products without BOMs Alert -->
        <?php if (!empty($productsWithoutBOM)): ?>
        <div class="alert alert-warning">
            <div class="alert-header">
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <h4>Products Missing BOMs</h4>
                    <p>These products don't have BOMs yet - they need them for MRP calculations:</p>
                </div>
            </div>
            <div class="missing-bom-products">
                <?php foreach ($productsWithoutBOM as $product): ?>
                    <a href="create.php?product_id=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">
                        <span class="product-code"><?php echo htmlspecialchars($product['product_code']); ?></span>
                        <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (count($productsWithoutBOM) >= 10): ?>
                    <div class="more-products">...and possibly more</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- BOM List Content -->
        <?php if (!empty($boms)): ?>
        <div class="materials-list-modern">
            <div class="materials-list-header">
                <h2 class="list-title">BOM Inventory</h2>
                <div class="list-meta"><?php echo count($boms); ?> BOMs found</div>
            </div>
            
            <div class="filter-panel">
                <div class="quick-filters">
                    <button class="filter-btn <?php echo ($filterStatus === 'all' && !$showInactive) ? 'active' : ''; ?>"
                            onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId : '?'; ?>'">
                        All Active
                    </button>
                    <button class="filter-btn <?php echo ($showInactive) ? 'active' : ''; ?>"
                            onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>show_inactive=1'">
                        All BOMs
                    </button>
                    <?php if ($stats['inactive_boms'] > 0): ?>
                    <button class="filter-btn alert <?php echo ($filterStatus === 'inactive') ? 'active' : ''; ?>"
                            onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>status=inactive'">
                        Inactive <span class="badge"><?php echo $stats['inactive_boms']; ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($stats['draft_boms'] > 0): ?>
                    <button class="filter-btn alert <?php echo ($filterStatus === 'draft') ? 'active' : ''; ?>"
                            onclick="window.location.href='<?php echo $productId ? '?product_id=' . $productId . '&' : '?'; ?>status=draft'">
                        Draft <span class="badge"><?php echo $stats['draft_boms']; ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                <div class="bulk-info">
                    <span id="selectedCount">0</span> BOMs selected
                </div>
                <div class="bulk-actions">
                    <button class="bulk-btn" id="bulkExport">Export</button>
                    <button class="bulk-btn" id="bulkActivate">Activate</button>
                    <button class="bulk-btn" id="bulkDeactivate">Deactivate</button>
                </div>
            </div>
            
            <div class="materials-list">
                <?php foreach ($boms as $bom): ?>
                <div class="list-item" data-bom-id="<?php echo $bom['id']; ?>" data-status="<?php echo $bom['is_active'] ? 'active' : 'inactive'; ?>">
                    <div class="item-selector">
                        <input type="checkbox" class="item-checkbox" value="<?php echo $bom['id']; ?>">
                    </div>
                    <div class="item-primary">
                        <div class="item-header">
                            <span class="entity-code"><?php echo htmlspecialchars($bom['product_code']); ?></span>
                            <div class="status-indicators">
                                <?php if ($bom['is_active']): ?>
                                    <span class="stock-status good" title="Active BOM"></span>
                                <?php else: ?>
                                    <span class="stock-status critical" title="Inactive BOM"></span>
                                <?php endif; ?>
                                <span class="type-badge">v<?php echo htmlspecialchars($bom['version']); ?></span>
                                <?php if ($bom['approval_status'] === 'approved'): ?>
                                    <span class="type-badge approved">Approved</span>
                                <?php else: ?>
                                    <span class="type-badge draft">Draft</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <h3 class="entity-name"><?php echo htmlspecialchars($bom['product_name']); ?></h3>
                        <div class="item-meta">
                            <span>Description: <?php echo htmlspecialchars($bom['description'] ?: 'No description'); ?></span>
                            <span>Materials: <?php echo $bom['material_count']; ?></span>
                            <span>Effective: <?php echo date('M j, Y', strtotime($bom['effective_date'])); ?></span>
                        </div>
                    </div>
                    <div class="item-metrics">
                        <div class="metric">
                            <label>Status</label>
                            <span class="value <?php echo $bom['is_active'] ? 'good' : 'critical'; ?>">
                                <?php echo $bom['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <?php if ($bom['approved_by']): ?>
                        <div class="metric">
                            <label>Approved By</label>
                            <span class="value"><?php echo htmlspecialchars($bom['approved_by']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="item-actions">
                        <button class="action-quick" title="Toggle Status" onclick="toggleBomStatus(<?php echo $bom['id']; ?>)">⚡</button>
                        <button class="action-menu-toggle" data-bom-id="<?php echo $bom['id']; ?>">⋮</button>
                        <div class="action-menu" id="actionMenu<?php echo $bom['id']; ?>">
                            <a href="view.php?id=<?php echo $bom['id']; ?>" class="action-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View Details
                            </a>
                            <a href="edit.php?id=<?php echo $bom['id']; ?>" class="action-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Edit BOM
                            </a>
                            <a href="create.php?product_id=<?php echo $bom['product_id']; ?>" class="action-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                                    <path d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    <line x1="12" y1="12" x2="12" y2="12"/>
                                </svg>
                                New Version
                            </a>
                            <div class="action-divider"></div>
                            <button class="action-item danger" onclick="confirmDelete(<?php echo $bom['id']; ?>)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="3,6 5,6 21,6"/>
                                    <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2V6"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="materials-list-modern">
            <div class="empty-state-modern">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <h3>No BOMs Found</h3>
                <?php if ($search): ?>
                    <p>No BOMs match your search criteria.<br>
                    <a href="<?php echo $productId ? 'index.php?product_id=' . $productId : 'index.php'; ?>" class="btn btn-outline btn-sm">Clear search</a> or 
                    <a href="create.php" class="btn btn-primary btn-sm">Create New BOM</a></p>
                <?php else: ?>
                    <p>🎯 Ready to Create Your First BOM!<br>
                    Create a Bill of Materials to define which materials are needed for each product.</p>
                    <div class="empty-actions">
                        <a href="create.php" class="btn btn-primary">Create BOM</a>
                        <a href="../products/" class="btn btn-outline">View Products</a>
                    </div>
                    <p class="tip"><strong>Tip:</strong> Start with one of your key products to test the MRP system.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Bottom Navigation -->
        <div class="page-actions">
            <?php if ($filterProduct): ?>
                <a href="create.php?product_id=<?php echo $productId; ?>" class="btn btn-primary">Create New Version</a>
                <a href="../products/" class="btn btn-secondary">Back to Products</a>
                <a href="../" class="btn btn-outline">Dashboard</a>
            <?php else: ?>
                <a href="create.php" class="btn btn-primary">Create New BOM</a>
                <a href="../materials/" class="btn btn-secondary">View Materials</a>
                <a href="../products/" class="btn btn-secondary">View Products</a>
                <a href="../" class="btn btn-outline">Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../js/autocomplete.js"></script>
<script src="../js/autocomplete-manager.js"></script>
<script>
// Initialize autocomplete for BOM search
AutocompleteManager.init('bom-search', '#searchInput');

// Setup action menus
function setupActionMenus() {
    document.addEventListener('click', function(e) {
        // Handle action menu toggle
        if (e.target.classList.contains('action-menu-toggle')) {
            e.preventDefault();
            e.stopPropagation();
            
            const bomId = e.target.dataset.bomId;
            const menu = document.getElementById('actionMenu' + bomId);
            
            // Close all other menus
            document.querySelectorAll('.action-menu').forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });
            
            // Toggle current menu
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        
        // Close menus when clicking outside
        if (!e.target.closest('.item-actions')) {
            document.querySelectorAll('.action-menu').forEach(m => {
                m.style.display = 'none';
            });
        }
    });
}

// Setup bulk selection
function setupBulkSelection() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActions);
    });
    
    function updateBulkActions() {
        const selected = document.querySelectorAll('.item-checkbox:checked');
        selectedCount.textContent = selected.length;
        bulkActionsBar.style.display = selected.length > 0 ? 'flex' : 'none';
    }
}

// Recent searches for BOM search
function setupRecentSearches() {
    const storageKey = 'bom-recent-searches';
    const maxRecent = 5;
    const maxStored = 10;
    
    function loadRecentSearches() {
        const recent = JSON.parse(localStorage.getItem(storageKey) || '[]');
        const recentList = document.getElementById('recentSearchesList');
        const recentContainer = document.getElementById('recentSearches');
        
        if (recent.length === 0) {
            recentContainer.style.display = 'none';
            return;
        }
        
        recentContainer.style.display = 'block';
        recentList.innerHTML = recent.slice(0, maxRecent).map(term => 
            `<a href="?search=${encodeURIComponent(term)}" class="recent-search-link">${escapeHtml(term)}</a>`
        ).join('');
    }
    
    function addRecentSearch(term) {
        if (!term.trim()) return;
        
        let recent = JSON.parse(localStorage.getItem(storageKey) || '[]');
        recent = recent.filter(r => r !== term);
        recent.unshift(term);
        recent = recent.slice(0, maxStored);
        
        localStorage.setItem(storageKey, JSON.stringify(recent));
        loadRecentSearches();
    }
    
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        const searchTerm = document.getElementById('searchInput').value.trim();
        if (searchTerm) {
            addRecentSearch(searchTerm);
        }
    });
    
    loadRecentSearches();
}

// Utility function
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
    setupBulkSelection();
    setupRecentSearches();
});
</script>

<?php require_once '../../includes/footer.php'; ?>