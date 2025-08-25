<?php
session_start();
require_once '../includes/header.php';
require_once '../includes/help-system.php';

// Dashboard data
require_once '../classes/Material.php';
require_once '../classes/Product.php';
require_once '../classes/Inventory.php';

$materialModel = new Material();
$productModel = new Product();
$inventoryModel = new Inventory();

// Get dashboard statistics
$belowReorderMaterials = $materialModel->getBelowReorderPoint();
$belowSafetyProducts = $productModel->getBelowSafetyStock();
$expiringInventory = $inventoryModel->getExpiringInventory(30);
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<div class="container">
    <h2 class="mb-3">MRP Dashboard <?php echo help_tooltip('dashboard', 'Your command center for monitoring the MRP system'); ?></h2>
    
    <?php echo HelpSystem::renderHelpPanel('dashboard'); ?>
    <?php echo HelpSystem::renderHelpButton(); ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($belowReorderMaterials); ?></div>
            <div class="stat-label">Materials Below Reorder</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($belowSafetyProducts); ?></div>
            <div class="stat-label">Products Below Safety Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo count($expiringInventory); ?></div>
            <div class="stat-label">Expiring Items (30 days)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">0</div>
            <div class="stat-label">Pending Orders</div>
        </div>
    </div>

    <!-- Materials Below Reorder Point -->
    <?php if (!empty($belowReorderMaterials)): ?>
    <div class="card">
        <h3 class="card-header">Materials Below Reorder Point</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Name</th>
                        <th>Current Stock</th>
                        <th>Reorder Point</th>
                        <th>Shortage</th>
                        <th>Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($belowReorderMaterials as $material): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($material['material_code']); ?></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo number_format($material['current_stock'], 2); ?> <?php echo $material['uom_code']; ?></td>
                        <td><?php echo number_format($material['reorder_point'], 2); ?> <?php echo $material['uom_code']; ?></td>
                        <td><span class="badge badge-danger"><?php echo number_format($material['shortage_qty'], 2); ?></span></td>
                        <td><?php echo htmlspecialchars($material['supplier_name'] ?? 'Not assigned'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Products Below Safety Stock -->
    <?php if (!empty($belowSafetyProducts)): ?>
    <div class="card">
        <h3 class="card-header">Products Below Safety Stock</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Name</th>
                        <th>Current Stock</th>
                        <th>Safety Stock</th>
                        <th>Shortage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($belowSafetyProducts as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_code']); ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo number_format($product['current_stock'], 2); ?> <?php echo $product['uom_code']; ?></td>
                        <td><?php echo number_format($product['safety_stock_qty'], 2); ?> <?php echo $product['uom_code']; ?></td>
                        <td><span class="badge badge-warning"><?php echo number_format($product['shortage_qty'], 2); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Expiring Inventory -->
    <?php if (!empty($expiringInventory)): ?>
    <div class="card">
        <h3 class="card-header">Inventory Expiring Soon</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Lot Number</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Days Until Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expiringInventory as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['lot_number']); ?></td>
                        <td><?php echo number_format($item['quantity'], 2); ?> <?php echo $item['uom_code']; ?></td>
                        <td><?php echo $item['expiry_date']; ?></td>
                        <td>
                            <span class="badge <?php echo $item['days_until_expiry'] <= 7 ? 'badge-danger' : 'badge-warning'; ?>">
                                <?php echo $item['days_until_expiry']; ?> days
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <h3 class="card-header">Quick Actions</h3>
        <div class="btn-group">
            <a href="orders/create.php" class="btn btn-primary">Create Order</a>
            <a href="mrp/run.php" class="btn btn-success">Run MRP</a>
            <a href="materials/create.php" class="btn btn-secondary">Add Material</a>
            <a href="products/create.php" class="btn btn-secondary">Add Product</a>
            <a href="inventory/receive.php" class="btn btn-warning">Receive Inventory</a>
        </div>
    </div>
    
    <?php 
    // Show workflow guide for new users
    if (!isset($_COOKIE['hide_workflow_guide'])) {
        echo HelpSystem::workflowGuide([
            'Check dashboard for alerts and low stock warnings',
            'Create customer orders as they come in',
            'Run MRP to calculate material requirements',
            'Create production orders based on demand',
            'Monitor production progress on Gantt chart'
        ]);
    }
    ?>
</div>

<?php echo HelpSystem::getHelpScript(); ?>

<?php require_once '../includes/footer.php'; ?>