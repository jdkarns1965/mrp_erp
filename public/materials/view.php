<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Material.php';
require_once '../../classes/Inventory.php';
require_once '../../classes/BOM.php';

// Get material ID from URL
$materialId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$materialId) {
    $_SESSION['error'] = "Invalid material ID";
    header('Location: index.php');
    exit;
}

$materialModel = new Material();
$inventoryModel = new Inventory();
$bomModel = new BOM();

try {
    // Get material details with full information
    $material = $materialModel->findWithDetails($materialId);
    
    if (!$material) {
        $_SESSION['error'] = "Material not found";
        header('Location: index.php');
        exit;
    }
    
    // Add any missing fields with default values
    $material['category'] = $material['category_name'] ?? null;
    $material['reorder_quantity'] = $material['reorder_quantity'] ?? $material['safety_stock_qty'] ?? 0;
    $material['supplier_name'] = $material['supplier_name'] ?? null;
    $material['supplier_part_number'] = null; // Not in current schema
    
    // Get current stock information
    $currentStock = $inventoryModel->getAvailableQuantity('material', $materialId);
    $stockMovements = $inventoryModel->getRecentMovements('material', $materialId, 10);
    
    // Get products using this material
    $productsUsingMaterial = $bomModel->getProductsUsingMaterial($materialId);
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading material details: " . $e->getMessage();
    header('Location: index.php');
    exit;
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                Material Details: <?php echo htmlspecialchars($material['material_code']); ?>
            </div>
            <div class="header-actions">
                <a href="edit.php?id=<?php echo $materialId; ?>" class="btn btn-secondary">Edit</a>
                <a href="index.php" class="btn btn-outline">Back to List</a>
            </div>
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
        
        <!-- Material Information -->
        <div class="detail-section">
            <h3>Basic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Material Code:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['material_code']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Name:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Type:</label>
                    <span class="detail-value">
                        <span class="badge badge-secondary"><?php echo ucfirst($material['material_type']); ?></span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Category:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['category'] ?: 'Not specified'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Unit of Measure:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['uom_code']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="detail-value">
                        <?php if ($material['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($material['description']): ?>
                <div class="detail-item full-width">
                    <label>Description:</label>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($material['description'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Inventory Information -->
        <div class="detail-section">
            <h3>Inventory Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Current Stock:</label>
                    <span class="detail-value <?php echo $currentStock < $material['reorder_point'] ? 'text-danger' : ''; ?>">
                        <?php echo number_format($currentStock, 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?>
                        <?php if ($currentStock < $material['reorder_point']): ?>
                            <span class="badge badge-danger">Below Reorder Point</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Reorder Point:</label>
                    <span class="detail-value"><?php echo number_format($material['reorder_point'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Reorder Quantity:</label>
                    <span class="detail-value"><?php echo number_format($material['reorder_quantity'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Lead Time:</label>
                    <span class="detail-value"><?php echo $material['lead_time_days']; ?> days</span>
                </div>
            </div>
        </div>
        
        <!-- Cost Information -->
        <div class="detail-section">
            <h3>Cost Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Cost per Unit:</label>
                    <span class="detail-value">$<?php echo number_format($material['cost_per_unit'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <label>Total Inventory Value:</label>
                    <span class="detail-value">$<?php echo number_format($currentStock * $material['cost_per_unit'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Supplier Information -->
        <?php if ($material['supplier_name'] || $material['supplier_part_number']): ?>
        <div class="detail-section">
            <h3>Supplier Information</h3>
            <div class="detail-grid">
                <?php if ($material['supplier_name']): ?>
                <div class="detail-item">
                    <label>Supplier:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['supplier_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($material['supplier_part_number']): ?>
                <div class="detail-item">
                    <label>Supplier Part Number:</label>
                    <span class="detail-value"><?php echo htmlspecialchars($material['supplier_part_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Products Using This Material -->
        <?php if (!empty($productsUsingMaterial)): ?>
        <div class="detail-section">
            <h3>Products Using This Material</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Quantity Required</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productsUsingMaterial as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo number_format($product['quantity_required'], 4); ?> <?php echo htmlspecialchars($material['uom_code']); ?></td>
                            <td>
                                <a href="../products/view.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary btn-sm">View Product</a>
                                <a href="../bom/view.php?product_id=<?php echo $product['product_id']; ?>" class="btn btn-secondary btn-sm">View BOM</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Stock Movements -->
        <?php if (!empty($stockMovements)): ?>
        <div class="detail-section">
            <h3>Recent Stock Movements</h3>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Lot Number</th>
                            <th>Reference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockMovements as $movement): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i', strtotime($movement['created_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo $movement['movement_type'] === 'in' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($movement['movement_type']); ?>
                                </span>
                            </td>
                            <td><?php echo number_format($movement['quantity'], 2); ?> <?php echo htmlspecialchars($material['uom_code']); ?></td>
                            <td><?php echo htmlspecialchars($movement['lot_number'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($movement['reference_number'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($movement['notes'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Audit Information -->
        <div class="detail-section">
            <h3>Audit Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Created:</label>
                    <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($material['created_at'])); ?></span>
                </div>
                <?php if ($material['updated_at']): ?>
                <div class="detail-item">
                    <label>Last Updated:</label>
                    <span class="detail-value"><?php echo date('Y-m-d H:i', strtotime($material['updated_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="form-actions">
            <a href="edit.php?id=<?php echo $materialId; ?>" class="btn btn-secondary">Edit Material</a>
            <a href="../inventory/add.php?type=material&id=<?php echo $materialId; ?>" class="btn btn-primary">Add Stock</a>
            <?php if ($material['is_active']): ?>
                <button type="button" onclick="confirmDeactivate(<?php echo $materialId; ?>)" class="btn btn-danger">Deactivate</button>
            <?php else: ?>
                <button type="button" onclick="confirmActivate(<?php echo $materialId; ?>)" class="btn btn-success">Activate</button>
            <?php endif; ?>
            <a href="index.php" class="btn btn-outline">Back to List</a>
        </div>
    </div>
</div>

<style>
.header-title {
    font-size: 1.25rem;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.detail-section {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.detail-section:last-child {
    border-bottom: none;
}

.detail-section h3 {
    margin: 0 0 15px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-item.full-width {
    grid-column: 1 / -1;
}

.detail-item label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.875rem;
}

.detail-value {
    font-size: 1rem;
    color: #111827;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 3px;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.text-danger {
    color: #dc3545 !important;
}

.alert {
    padding: 15px;
    margin: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

table th {
    background-color: #f9fafb;
    padding: 10px;
    text-align: left;
    font-weight: 600;
    font-size: 0.875rem;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.form-actions {
    padding: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<script>
function confirmDeactivate(materialId) {
    if (confirm('Are you sure you want to deactivate this material? It will no longer be available for selection in new BOMs.')) {
        window.location.href = 'deactivate.php?id=' + materialId;
    }
}

function confirmActivate(materialId) {
    if (confirm('Are you sure you want to activate this material?')) {
        window.location.href = 'activate.php?id=' + materialId;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>