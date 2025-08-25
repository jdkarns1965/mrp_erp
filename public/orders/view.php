<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/Database.php';
require_once '../../includes/ui-helpers.php';

$db = Database::getInstance();

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$orderId) {
    $_SESSION['error'] = 'Invalid order ID';
    header('Location: index.php');
    exit;
}

// Fetch order details
$order = $db->selectOne("
    SELECT co.*, 
           DATE_FORMAT(co.order_date, '%Y-%m-%d') as order_date_formatted,
           DATE_FORMAT(co.required_date, '%Y-%m-%d') as required_date_formatted,
           DATE_FORMAT(co.created_at, '%Y-%m-%d %H:%i') as created_at_formatted
    FROM customer_orders co
    WHERE co.id = ?
", [$orderId]);

// Calculate total separately
$totalResult = $db->selectOne("SELECT SUM(quantity * unit_price) as total_amount FROM customer_order_details WHERE order_id = ?", [$orderId]);
$order['total_amount'] = $totalResult['total_amount'] ?? 0;

if (!$order) {
    echo "<div class='container'><div class='alert alert-error'>Order not found</div></div>";
    require_once '../../includes/footer-tailwind.php';
    exit;
}

// Fetch order items
$orderItems = $db->select("
    SELECT cod.*, 
           p.product_code, 
           p.name as product_name,
           p.description as product_description,
           uom.code as uom_code,
           uom.description as uom_name,
           (cod.quantity * cod.unit_price) as line_total
    FROM customer_order_details cod
    LEFT JOIN products p ON cod.product_id = p.id
    LEFT JOIN units_of_measure uom ON cod.uom_id = uom.id
    WHERE cod.order_id = ?
    ORDER BY cod.id
", [$orderId]);

// Check if there are any production orders for this customer order
$productionOrders = $db->select("
    SELECT po.*
    FROM production_orders po
    WHERE po.customer_order_id = ?
    ORDER BY po.created_at DESC
", [$orderId]);

// Format dates separately
foreach ($productionOrders as &$po) {
    $po['start_formatted'] = $po['scheduled_start'] ? date('Y-m-d H:i', strtotime($po['scheduled_start'])) : '';
    $po['end_formatted'] = $po['scheduled_end'] ? date('Y-m-d H:i', strtotime($po['scheduled_end'])) : '';
}

// Check MRP results if any (skip for now - table structure needs verification)
$mrpResults = [];

// Get status color
function getStatusColor($status) {
    switch($status) {
        case 'pending': return 'secondary';
        case 'confirmed': return 'primary';
        case 'in_production': return 'info';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        case 'on_hold': return 'warning';
        default: return 'secondary';
    }
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Order Details: <?php echo htmlspecialchars($order['order_number']); ?></h3>
            <div style="float: right;">
                <span class="badge badge-<?php echo getStatusColor($order['status']); ?>">
                    <?php echo ucfirst($order['status']); ?>
                </span>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Order Information -->
        <div class="grid grid-2">
            <div>
                <h4>Order Information</h4>
                <table class="info-table">
                    <tr>
                        <th>Order Number:</th>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Customer:</th>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Order Date:</th>
                        <td><?php echo $order['order_date_formatted']; ?></td>
                    </tr>
                    <tr>
                        <th>Required Date:</th>
                        <td><?php echo $order['required_date_formatted']; ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="badge badge-<?php echo getStatusColor($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($order['total_amount']): ?>
                    <tr>
                        <th>Total Amount:</th>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Created:</th>
                        <td><?php echo $order['created_at_formatted']; ?></td>
                    </tr>
                </table>
            </div>
            
            <div>
                <h4>Notes</h4>
                <div class="notes-box">
                    <?php echo nl2br(htmlspecialchars($order['notes'] ?: 'No notes')); ?>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <h4>Order Items</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>UOM</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orderItems)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No items found</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                            <td>
                                <?php echo renderEntityName('product', $item['product_id'], $item['product_name']); ?>
                                <?php if ($item['product_description']): ?>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($item['product_description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?php echo number_format($item['quantity'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['uom_code']); ?></td>
                            <td class="text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right">$<?php echo number_format($item['line_total'], 2); ?></td>
                            <td><?php echo htmlspecialchars($item['notes'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($order['total_amount']): ?>
                        <tr class="table-footer">
                            <td colspan="5" class="text-right"><strong>Total:</strong></td>
                            <td class="text-right"><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Production Orders Section -->
        <?php if (!empty($productionOrders)): ?>
        <h4>Related Production Orders</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Production Order #</th>
                        <th>Status</th>
                        <th>Scheduled Start</th>
                        <th>Scheduled End</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productionOrders as $po): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($po['order_number']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo getStatusColor($po['status']); ?>">
                                <?php echo ucfirst($po['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $po['start_formatted']; ?></td>
                        <td><?php echo $po['end_formatted']; ?></td>
                        <td>
                            <a href="../production/view.php?id=<?php echo $po['id']; ?>" class="btn btn-sm btn-primary">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- MRP Results Section -->
        <?php if (!empty($mrpResults)): ?>
        <h4>MRP Analysis Results</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Required Qty</th>
                        <th>Available Qty</th>
                        <th>Shortage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mrpResults as $result): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($result['material_code']); ?></td>
                        <td><?php echo renderEntityName('material', $result['material_id'], $result['material_name']); ?></td>
                        <td class="text-right"><?php echo number_format($result['required_quantity'], 2); ?></td>
                        <td class="text-right"><?php echo number_format($result['available_quantity'], 2); ?></td>
                        <td class="text-right">
                            <?php 
                            $shortage = $result['required_quantity'] - $result['available_quantity'];
                            if ($shortage > 0): ?>
                                <span class="text-danger"><?php echo number_format($shortage, 2); ?></span>
                            <?php else: ?>
                                <span class="text-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($shortage > 0): ?>
                                <span class="badge badge-danger">Shortage</span>
                            <?php else: ?>
                                <span class="badge badge-success">Available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="btn-group mt-3">
            <a href="edit.php?id=<?php echo $orderId; ?>" class="btn btn-primary">Edit Order</a>
            <?php if ($order['status'] === 'pending'): ?>
                <a href="../mrp/run.php?order_id=<?php echo $orderId; ?>" class="btn btn-info">Run MRP Analysis</a>
                <?php if (empty($productionOrders)): ?>
                    <a href="../production/create.php?order_id=<?php echo $orderId; ?>" class="btn btn-success">Create Production Order</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">Back to Orders</a>
        </div>
    </div>
</div>

<style>
.info-table {
    width: 100%;
    margin-bottom: 1rem;
}
.info-table th {
    text-align: left;
    padding: 0.5rem;
    width: 40%;
    font-weight: 600;
    color: var(--text-secondary);
}
.info-table td {
    padding: 0.5rem;
}
.notes-box {
    background: var(--bg-secondary);
    padding: 1rem;
    border-radius: 0.25rem;
    min-height: 100px;
    white-space: pre-wrap;
}
.table-footer {
    background: var(--bg-secondary);
    font-weight: 600;
}
</style>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>