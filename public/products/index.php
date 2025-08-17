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

try {
    if ($search) {
        $products = $product->search($search);
    } else {
        $products = $product->getAllActive();
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
            <div style="float: right;">
                <a href="create.php" class="btn btn-primary">Add Product</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
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
        
        <div class="search-bar">
            <form method="GET" action="" id="searchForm">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" 
                               id="searchInput"
                               name="search" 
                               placeholder="Search by code or name..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               data-autocomplete-preset="products-search"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if ($search): ?>
                            <a href="index.php" class="btn btn-outline">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (empty($products)): ?>
            <div class="alert alert-info">
                <?php if ($search): ?>
                    No products found matching your search.
                <?php else: ?>
                    No products have been created yet. <a href="create.php">Create your first product</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>UOM</th>
                            <th>Current Stock</th>
                            <th>Safety Stock</th>
                            <th>Standard Cost</th>
                            <th>Selling Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($prod['product_code']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($prod['name']); ?></td>
                                <td><?php echo htmlspecialchars($prod['category_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($prod['uom_code'] ?? ''); ?></td>
                                <td class="text-right">
                                    <?php 
                                    $currentStock = (float)($prod['current_stock'] ?? 0);
                                    $safetyStock = (float)($prod['safety_stock_qty'] ?? 0);
                                    $stockClass = '';
                                    if ($safetyStock > 0 && $currentStock < $safetyStock) {
                                        $stockClass = 'text-danger';
                                    }
                                    ?>
                                    <span class="<?php echo $stockClass; ?>">
                                        <?php echo number_format($currentStock, 2); ?>
                                    </span>
                                </td>
                                <td class="text-right"><?php echo number_format($prod['safety_stock_qty'], 2); ?></td>
                                <td class="text-right">$<?php echo number_format($prod['standard_cost'], 2); ?></td>
                                <td class="text-right">$<?php echo number_format($prod['selling_price'], 2); ?></td>
                                <td>
                                    <?php if ($prod['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($prod['is_lot_controlled']): ?>
                                        <span class="badge badge-info">Lot</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group-small">
                                        <a href="view.php?id=<?php echo $prod['id']; ?>" 
                                           class="btn btn-sm btn-info" title="View Details">
                                            View
                                        </a>
                                        <a href="edit.php?id=<?php echo $prod['id']; ?>" 
                                           class="btn btn-sm btn-secondary" title="Edit">
                                            Edit
                                        </a>
                                        <?php if (!empty($prod['bom_count'])): ?>
                                            <a href="../bom/index.php?product_id=<?php echo $prod['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="View BOMs">
                                                BOM (<?php echo $prod['bom_count']; ?>)
                                            </a>
                                        <?php else: ?>
                                            <a href="../bom/create.php?product_id=<?php echo $prod['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Create BOM">
                                                + BOM
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?action=delete&id=<?php echo $prod['id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this product?')"
                                           title="Delete">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="summary-info">
                <p>Total Products: <strong><?php echo count($products); ?></strong></p>
                
                <?php
                // Calculate products below safety stock
                $belowSafety = 0;
                foreach ($products as $prod) {
                    if ($prod['safety_stock_qty'] > 0 && ($prod['current_stock'] ?? 0) < $prod['safety_stock_qty']) {
                        $belowSafety++;
                    }
                }
                if ($belowSafety > 0): ?>
                    <p class="text-danger">Products Below Safety Stock: <strong><?php echo $belowSafety; ?></strong></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="btn-group">
            <a href="../" class="btn btn-secondary">Back to Dashboard</a>
            <a href="../bom/" class="btn btn-primary">Manage BOMs</a>
            <a href="../inventory/" class="btn btn-primary">Manage Inventory</a>
        </div>
    </div>
</div>

<style>
.search-bar {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.search-bar .form-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-bar .form-group {
    margin-bottom: 0;
}

.search-bar input[type="text"] {
    width: 300px;
}

/* Custom autocomplete styles for products page */
.autocomplete-item .item-main {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.autocomplete-item .item-code {
    font-weight: 600;
    color: #333;
    font-size: 14px;
    font-family: monospace;
}

.autocomplete-item .item-name {
    color: #666;
    font-size: 13px;
    font-weight: normal;
}

.text-right {
    text-align: right;
}

.text-danger {
    color: #dc3545;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 3px;
    margin-left: 5px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

.btn-group-small {
    display: flex;
    gap: 5px;
}

.btn-info {
    background-color: #17a2b8;
    color: white;
}

.btn-info:hover {
    background-color: #138496;
}

.btn-warning {
    background-color: #ffc107;
    color: #212529;
}

.btn-warning:hover {
    background-color: #e0a800;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.summary-info {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.summary-info p {
    margin: 5px 0;
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
    }
    
    .search-bar .form-row {
        flex-direction: column;
    }
    
    .search-bar input[type="text"] {
        width: 100%;
    }
    
    .btn-group-small {
        flex-direction: column;
    }
    
    .autocomplete-dropdown {
        max-height: 200px;
    }
    
    .autocomplete-item {
        padding: 12px;
    }
    
    .autocomplete-item .item-code {
        font-size: 15px;
    }
    
    .autocomplete-item .item-name {
        font-size: 14px;
    }
}
</style>

<link rel="stylesheet" href="../css/autocomplete.css">
<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>

<?php require_once '../../includes/footer.php'; ?>