<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get recent MRP calculations
$recentCalculations = $db->select("
    SELECT 
        mr.order_id,
        co.order_number,
        co.customer_name,
        mr.calculation_date,
        COUNT(mr.id) as material_count,
        SUM(CASE WHEN mr.net_requirement > 0 THEN 1 ELSE 0 END) as shortage_count
    FROM mrp_requirements mr
    JOIN customer_orders co ON mr.order_id = co.id
    GROUP BY mr.order_id, co.order_number, co.customer_name, mr.calculation_date
    ORDER BY mr.calculation_date DESC
    LIMIT 10
");

// Get pending orders
$pendingOrders = $db->select("
    SELECT 
        co.id,
        co.order_number,
        co.customer_name,
        co.order_date,
        co.required_date,
        COUNT(cod.id) as item_count
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id
    WHERE co.status IN ('pending', 'confirmed')
    GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date
    ORDER BY co.required_date ASC
    LIMIT 10
");
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            MRP (Material Requirements Planning)
            <div style="float: right;">
                <a href="run.php" class="btn btn-primary">Run New MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <p>The MRP system calculates material requirements based on customer orders and current inventory levels. 
           It identifies shortages and suggests purchase orders to fulfill demand.</p>
    </div>

    <!-- Pending Orders -->
    <?php if (!empty($pendingOrders)): ?>
    <div class="card">
        <h3 class="card-header">Pending Orders Ready for MRP</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Order Date</th>
                        <th>Required Date</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo $order['order_date']; ?></td>
                        <td><?php echo $order['required_date']; ?></td>
                        <td><?php echo $order['item_count']; ?></td>
                        <td>
                            <a href="run.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Run MRP</a>
                            <a href="../orders/view.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">View Order</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent MRP Calculations -->
    <?php if (!empty($recentCalculations)): ?>
    <div class="card">
        <h3 class="card-header">Recent MRP Calculations</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Calculation Date</th>
                        <th>Materials Analyzed</th>
                        <th>Shortages Found</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentCalculations as $calc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($calc['order_number']); ?></td>
                        <td><?php echo htmlspecialchars($calc['customer_name']); ?></td>
                        <td><?php echo date('M j, Y g:i A', strtotime($calc['calculation_date'])); ?></td>
                        <td><?php echo $calc['material_count']; ?></td>
                        <td>
                            <?php if ($calc['shortage_count'] > 0): ?>
                                <span class="badge badge-warning"><?php echo $calc['shortage_count']; ?></span>
                            <?php else: ?>
                                <span class="badge badge-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="results.php?order_id=<?php echo $calc['order_id']; ?>" class="btn btn-primary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">View Results</a>
                            <a href="run.php?order_id=<?php echo $calc['order_id']; ?>" class="btn btn-secondary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Re-run</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- MRP Process Overview -->
    <div class="card">
        <h3 class="card-header">MRP Process Overview</h3>
        <div class="grid grid-4">
            <div class="p-2" style="text-align: center;">
                <h4>1. Select Order</h4>
                <p>Choose a customer order to analyze</p>
            </div>
            <div class="p-2" style="text-align: center;">
                <h4>2. Explode BOM</h4>
                <p>Calculate material requirements from Bill of Materials</p>
            </div>
            <div class="p-2" style="text-align: center;">
                <h4>3. Check Inventory</h4>
                <p>Compare requirements against available stock</p>
            </div>
            <div class="p-2" style="text-align: center;">
                <h4>4. Generate Suggestions</h4>
                <p>Create purchase order recommendations</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>