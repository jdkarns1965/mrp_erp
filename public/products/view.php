<?php
session_start();
require_once '../../classes/Product.php';
require_once '../../classes/BOM.php';

$product = new Product();
$bom = new BOM();

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    $_SESSION['error'] = "Invalid product ID";
    header('Location: index.php');
    exit;
}

// Get product details
$productData = $product->findWithDetails($productId);

if (!$productData) {
    $_SESSION['error'] = "Product not found";
    header('Location: index.php');
    exit;
}

// Get current stock level
try {
    $currentStock = $product->getCurrentStock($productId);
} catch (Exception $e) {
    $currentStock = 0;
}

// Get active BOM if exists
$activeBOM = null;
try {
    $bomHeaders = $bom->getActiveByProduct($productId);
    if (!empty($bomHeaders)) {
        $activeBOM = $bomHeaders[0];
        // Get BOM details
        $bomDetails = $bom->getDetailsByHeaderId($activeBOM['id']);
    }
} catch (Exception $e) {
    // No BOM exists
}

// Get cost breakdown if BOM exists
$costBreakdown = [];
$totalMaterialCost = 0;
if ($activeBOM) {
    try {
        $costBreakdown = $product->getCostBreakdown($productId);
        foreach ($costBreakdown as $item) {
            $totalMaterialCost += $item['total_cost'];
        }
    } catch (Exception $e) {
        // Error getting cost breakdown
    }
}

// Include header after all redirect logic
require_once '../../includes/header-tailwind.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            <h2>Product Details: <?php echo htmlspecialchars($productData['product_code']); ?></h2>
            <div style="float: right;">
                <?php if ($productData['is_active']): ?>
                    <span class="badge badge-success">Active</span>
                <?php else: ?>
                    <span class="badge badge-danger">Inactive</span>
                <?php endif; ?>
                
                <?php if ($productData['is_lot_controlled']): ?>
                    <span class="badge badge-info">Lot Controlled</span>
                <?php endif; ?>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="product-details">
            <!-- Basic Information -->
            <fieldset class="view-fieldset">
                <legend>Basic Information</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Product Code:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($productData['product_code']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Product Name:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($productData['name']); ?></span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Category:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($productData['category_name'] ?? 'Not Assigned'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Unit of Measure:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($productData['uom_code'] . ' - ' . $productData['uom_description']); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($productData['description'])): ?>
                <div class="detail-row full-width">
                    <div class="detail-item">
                        <label>Description:</label>
                        <span class="detail-value"><?php echo nl2br(htmlspecialchars($productData['description'])); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </fieldset>
            
            <!-- Manufacturing Details -->
            <fieldset class="view-fieldset">
                <legend>Manufacturing Details</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Weight (kg):</label>
                        <span class="detail-value"><?php echo number_format($productData['weight_kg'], 4); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Cycle Time:</label>
                        <span class="detail-value"><?php echo $productData['cycle_time_seconds']; ?> seconds</span>
                    </div>
                    <div class="detail-item">
                        <label>Cavity Count:</label>
                        <span class="detail-value"><?php echo $productData['cavity_count']; ?></span>
                    </div>
                </div>
                
                <?php if ($productData['cycle_time_seconds'] > 0): ?>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Daily Capacity (8 hrs):</label>
                        <span class="detail-value">
                            <?php 
                            $dailyCapacity = $product->calculateDailyCapacity($productId, 8);
                            echo number_format($dailyCapacity) . ' ' . $productData['uom_code']; 
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </fieldset>
            
            <!-- Inventory Information -->
            <fieldset class="view-fieldset">
                <legend>Inventory Information</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Current Stock:</label>
                        <span class="detail-value <?php echo ($currentStock < $productData['safety_stock_qty']) ? 'text-danger' : ''; ?>">
                            <?php echo number_format($currentStock, 2) . ' ' . $productData['uom_code']; ?>
                            <?php if ($currentStock < $productData['safety_stock_qty']): ?>
                                <span class="badge badge-warning">Below Safety Stock</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Safety Stock:</label>
                        <span class="detail-value"><?php echo number_format($productData['safety_stock_qty'], 2) . ' ' . $productData['uom_code']; ?></span>
                    </div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Minimum Stock:</label>
                        <span class="detail-value"><?php echo number_format($productData['min_stock_qty'], 2) . ' ' . $productData['uom_code']; ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Maximum Stock:</label>
                        <span class="detail-value"><?php echo number_format($productData['max_stock_qty'], 2) . ' ' . $productData['uom_code']; ?></span>
                    </div>
                </div>
            </fieldset>
            
            <!-- Costing Information -->
            <fieldset class="view-fieldset">
                <legend>Costing Information</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Standard Cost:</label>
                        <span class="detail-value">$<?php echo number_format($productData['standard_cost'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Selling Price:</label>
                        <span class="detail-value">$<?php echo number_format($productData['selling_price'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Gross Margin:</label>
                        <span class="detail-value">
                            <?php 
                            $margin = $productData['selling_price'] - $productData['standard_cost'];
                            $marginPercent = $productData['selling_price'] > 0 ? ($margin / $productData['selling_price']) * 100 : 0;
                            echo '$' . number_format($margin, 2) . ' (' . number_format($marginPercent, 1) . '%)';
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($totalMaterialCost > 0): ?>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Material Cost (from BOM):</label>
                        <span class="detail-value">$<?php echo number_format($totalMaterialCost, 2); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </fieldset>
            
            <!-- BOM Information -->
            <?php if ($activeBOM): ?>
            <fieldset class="view-fieldset">
                <legend>Bill of Materials (Active)</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>BOM Version:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($activeBOM['version']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Effective Date:</label>
                        <span class="detail-value"><?php echo date('Y-m-d', strtotime($activeBOM['effective_date'])); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($bomDetails)): ?>
                <div class="bom-materials">
                    <h4>Materials Required:</h4>
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th>Material Code</th>
                                <th>Material Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Scrap %</th>
                                <th>Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($costBreakdown as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['material_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['material_name']); ?></td>
                                <td class="text-right"><?php echo number_format($item['quantity_per'], 4); ?></td>
                                <td><?php echo htmlspecialchars($item['uom_code']); ?></td>
                                <td class="text-right"><?php echo number_format(0, 1); ?>%</td>
                                <td class="text-right">$<?php echo number_format($item['total_cost'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-right">Total Material Cost:</th>
                                <th class="text-right">$<?php echo number_format($totalMaterialCost, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </fieldset>
            <?php else: ?>
            <fieldset class="view-fieldset">
                <legend>Bill of Materials</legend>
                <p class="no-data">No active BOM defined for this product. <a href="../bom/create.php?product_id=<?php echo $productId; ?>">Create BOM</a></p>
            </fieldset>
            <?php endif; ?>
            
            <!-- Timestamps -->
            <fieldset class="view-fieldset">
                <legend>Record Information</legend>
                <div class="detail-row">
                    <div class="detail-item">
                        <label>Created:</label>
                        <span class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($productData['created_at'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Last Updated:</label>
                        <span class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($productData['updated_at'])); ?></span>
                    </div>
                </div>
            </fieldset>
        </div>
        
        <div class="btn-group">
            <a href="edit.php?id=<?php echo $productId; ?>" class="btn btn-primary">Edit Product</a>
            <a href="../bom/create.php?product_id=<?php echo $productId; ?>" class="btn btn-secondary">Manage BOM</a>
            <a href="../inventory/?product_id=<?php echo $productId; ?>" class="btn btn-secondary">View Inventory</a>
            <a href="index.php" class="btn btn-outline">Back to List</a>
        </div>
    </div>
</div>

<style>
.product-details {
    padding: 20px;
}

.view-fieldset {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    background: #f9f9f9;
}

.view-fieldset legend {
    font-weight: 600;
    color: #333;
    padding: 0 10px;
    background: white;
    border-radius: 3px;
}

.detail-row {
    display: flex;
    gap: 30px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.detail-row.full-width {
    display: block;
}

.detail-item {
    flex: 1;
    min-width: 250px;
}

.detail-item label {
    font-weight: 600;
    color: #666;
    display: inline-block;
    margin-right: 10px;
    min-width: 140px;
}

.detail-value {
    color: #333;
    font-size: 14px;
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
    margin-left: 10px;
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

.badge-warning {
    background-color: #ffc107;
    color: #333;
}

.compact-table {
    width: 100%;
    margin-top: 15px;
    border-collapse: collapse;
}

.compact-table th,
.compact-table td {
    padding: 8px;
    border: 1px solid #ddd;
    font-size: 13px;
}

.compact-table th {
    background-color: #f0f0f0;
    font-weight: 600;
}

.compact-table tfoot th {
    background-color: #e0e0e0;
}

.text-right {
    text-align: right;
}

.bom-materials {
    margin-top: 15px;
}

.bom-materials h4 {
    margin-bottom: 10px;
    color: #333;
}

.no-data {
    color: #999;
    font-style: italic;
}

@media (max-width: 768px) {
    .detail-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .detail-item {
        min-width: 100%;
    }
    
    .detail-item label {
        display: block;
        margin-bottom: 5px;
    }
    
    .compact-table {
        font-size: 12px;
    }
    
    .compact-table th,
    .compact-table td {
        padding: 5px;
    }
}
</style>

<!-- Documents Section -->
<div class="product-details">
    <fieldset class="view-fieldset">
        <legend>Documents</legend>
        <div id="documents-container"></div>
    </fieldset>
</div>

<script src="../js/document-manager.js"></script>
<script>
// Initialize document manager
document.addEventListener('DOMContentLoaded', function() {
    documentManager = new DocumentManager('product', <?php echo $productId; ?>);
});
</script>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>