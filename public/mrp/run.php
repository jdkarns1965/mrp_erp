<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/MRP.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$mrpEngine = new MRP();

$orderId = $_GET['order_id'] ?? null;
$results = null;
$errors = [];

// Get available orders with product details
$orders = $db->select("
    SELECT 
        co.id,
        co.order_number,
        co.customer_name,
        co.order_date,
        co.required_date,
        COUNT(cod.id) as item_count,
        GROUP_CONCAT(DISTINCT p.product_code ORDER BY p.product_code SEPARATOR ', ') as product_codes,
        SUM(cod.quantity) as total_quantity
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id
    JOIN products p ON cod.product_id = p.id
    WHERE co.status IN ('pending', 'confirmed')
    GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date
    ORDER BY co.required_date ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    
    try {
        $results = $mrpEngine->runMRP($orderId);
        
        if ($results['success']) {
            $_SESSION['success'] = 'MRP calculation completed successfully';
        } else {
            $errors[] = $results['error'];
        }
        
    } catch (Exception $e) {
        $errors[] = 'Error running MRP: ' . $e->getMessage();
    }
}

// If order_id is provided in URL, get order details
$selectedOrder = null;
if ($orderId) {
    $selectedOrder = $db->selectOne("
        SELECT co.*, COUNT(cod.id) as item_count
        FROM customer_orders co
        JOIN customer_order_details cod ON co.id = cod.order_id
        WHERE co.id = ?
        GROUP BY co.id
    ", [$orderId], ['i']);
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            Run MRP Calculation
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">Back to MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
            <div class="alert alert-warning">
                No pending orders found. <a href="../orders/create.php">Create a new order</a> to run MRP.
            </div>
        <?php else: ?>
            <form method="POST" data-validate data-loading>
                <div class="form-group">
                    <label for="order_id">Select Order to Analyze *</label>
                    <select id="order_id" name="order_id" required>
                        <option value="">Choose an order...</option>
                        <?php foreach ($orders as $order): ?>
                            <option value="<?php echo $order['id']; ?>" 
                                    <?php echo $orderId == $order['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($order['customer_name'] . ' - Parts: ' . $order['product_codes'] . ' (Qty: ' . number_format($order['total_quantity']) . ') - Due: ' . $order['required_date']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selectedOrder): ?>
                    <div class="alert alert-info">
                        <strong>Selected Order:</strong> <?php echo htmlspecialchars($selectedOrder['order_number']); ?><br>
                        <strong>Customer:</strong> <?php echo htmlspecialchars($selectedOrder['customer_name']); ?><br>
                        <strong>Required Date:</strong> <?php echo $selectedOrder['required_date']; ?><br>
                        <strong>Items:</strong> <?php echo $selectedOrder['item_count']; ?>
                    </div>
                <?php endif; ?>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Run MRP Calculation</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- MRP Results -->
    <?php if ($results && $results['success']): ?>
        <div class="card">
            <h3 class="card-header">MRP Results</h3>
            
            <!-- Summary -->
            <div class="grid grid-4 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $results['summary']['total_materials']; ?></div>
                    <div class="stat-label">Total Materials</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-warning"><?php echo $results['summary']['materials_with_shortage']; ?></div>
                    <div class="stat-label">Materials with Shortage</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($results['summary']['total_purchase_cost'], 2); ?></div>
                    <div class="stat-label">Total Purchase Cost</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value text-danger"><?php echo $results['summary']['urgent_orders']; ?></div>
                    <div class="stat-label">Urgent Orders</div>
                </div>
            </div>
            
            <?php if ($results['summary']['can_fulfill']): ?>
                <div class="card" style="background-color: #d1fae5; border-left: 4px solid var(--success-color); margin-bottom: 1rem;">
                    <div style="padding: 1rem;">
                        ✅ <strong>Order can be fulfilled!</strong> All required materials are available in inventory.
                    </div>
                </div>
            <?php else: ?>
                <div class="card" style="background-color: #fed7aa; border-left: 4px solid var(--warning-color); margin-bottom: 1rem;">
                    <div style="padding: 1rem;">
                        ⚠️ <strong>Material shortages found.</strong> Purchase orders are needed to fulfill this order.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Requirements Table -->
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Material Code</th>
                            <th>Material Name</th>
                            <th>Gross Requirement</th>
                            <th>Available Stock</th>
                            <th>Net Requirement</th>
                            <th>Suggested Order Qty</th>
                            <th>Order Date</th>
                            <th>Lead Time</th>
                            <th>Total Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['requirements'] as $req): ?>
                        <tr <?php echo $req['net_requirement'] > 0 ? 'style="background-color: #fef3cd;"' : ''; ?>>
                            <td><?php echo htmlspecialchars($req['material_code']); ?></td>
                            <td><?php echo htmlspecialchars($req['material_name']); ?></td>
                            <td><?php echo number_format($req['gross_requirement'], 2); ?> <?php echo $req['uom_code']; ?></td>
                            <td><?php echo number_format($req['available_stock'], 2); ?> <?php echo $req['uom_code']; ?></td>
                            <td>
                                <?php if ($req['net_requirement'] > 0): ?>
                                    <span class="badge badge-warning"><?php echo number_format($req['net_requirement'], 2); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $req['suggested_order_qty'] > 0 ? number_format($req['suggested_order_qty'], 2) : '-'; ?></td>
                            <td><?php echo $req['suggested_order_date'] ?: '-'; ?></td>
                            <td><?php echo $req['lead_time_days']; ?> days</td>
                            <td>$<?php echo number_format($req['total_cost'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="btn-group mt-3">
                <a href="results.php?order_id=<?php echo $orderId; ?>" class="btn btn-primary">View Detailed Results</a>
                <a href="purchase_orders.php?order_id=<?php echo $orderId; ?>" class="btn btn-success">Generate Purchase Orders</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>