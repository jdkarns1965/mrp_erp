<?php
session_start();

// Debug: Check if we can access basic functions
echo "<!-- Debug: Starting Enhanced MRP page -->\n";

try {
    require_once '../../includes/header.php';
    echo "<!-- Debug: Header included successfully -->\n";
} catch (Exception $e) {
    die("Error including header: " . $e->getMessage());
}

try {
    require_once '../../classes/Database.php';
    echo "<!-- Debug: Database class included -->\n";
} catch (Exception $e) {
    die("Error including Database class: " . $e->getMessage());
}

try {
    $db = Database::getInstance();
    echo "<!-- Debug: Database connected -->\n";
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage());
}

// Get run options from request
$options = [
    'run_type' => $_GET['run_type'] ?? 'regenerative',
    'planning_horizon' => intval($_GET['horizon'] ?? 90),
    'include_orders' => ($_GET['include_orders'] ?? '1') === '1',
    'include_mps' => ($_GET['include_mps'] ?? '0') === '1',
    'include_safety_stock' => ($_GET['include_safety'] ?? '1') === '1',
    'user' => $_SESSION['user'] ?? 'SYSTEM'
];

echo "<!-- Debug: Options configured -->\n";

// Run actual MRP calculation
$results = null;
$error = null;

if (isset($_GET['run']) && $_GET['run'] === '1') {
    try {
        require_once '../../classes/MRPEngine.php';
        
        $mrpEngine = new MRPEngine();
        echo "<!-- Debug: MRPEngine created -->\n";
        
        $results = $mrpEngine->runTimePhasedMRP($options);
        echo "<!-- Debug: MRP calculation completed -->\n";
        
        if ($results['success']) {
            $_SESSION['message'] = 'Enhanced MRP calculation completed successfully';
            $_SESSION['message_type'] = 'success';
        } else {
            $error = $results['error'] ?? 'Unknown error';
            $_SESSION['message'] = 'MRP calculation failed: ' . $error;
            $_SESSION['message_type'] = 'danger';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        $_SESSION['message'] = 'MRP calculation failed: ' . $error;
        $_SESSION['message_type'] = 'danger';
        echo "<!-- Debug: MRP calculation failed: " . $error . " -->\n";
    }
}

// Get recent customer orders to show as sample data
try {
    $sql = "SELECT 
                co.id,
                co.order_number,
                co.customer_name,
                co.required_date,
                co.status,
                COUNT(cod.id) as item_count
            FROM customer_orders co
            LEFT JOIN customer_order_details cod ON co.id = cod.order_id
            WHERE co.status IN ('pending', 'confirmed', 'in_production')
            GROUP BY co.id, co.order_number, co.customer_name, co.required_date, co.status
            ORDER BY co.required_date ASC
            LIMIT 10";
    $orders = $db->select($sql);
    echo "<!-- Debug: Found " . count($orders) . " orders -->\n";
} catch (Exception $e) {
    $orders = [];
    echo "<!-- Debug: Error fetching orders: " . $e->getMessage() . " -->\n";
}

// Check if MPS data exists
try {
    $sql = "SELECT COUNT(*) as mps_count FROM master_production_schedule WHERE firm_planned_qty > 0";
    $mpsCount = $db->selectOne($sql);
    echo "<!-- Debug: Found " . ($mpsCount['mps_count'] ?? 0) . " MPS entries -->\n";
} catch (Exception $e) {
    $mpsCount = ['mps_count' => 0];
    echo "<!-- Debug: Error checking MPS: " . $e->getMessage() . " -->\n";
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Enhanced MRP Calculation</h2>
            <div style="float: right;">
                <a href="../mps/" class="btn btn-secondary">Manage MPS</a>
                <a href="index.php" class="btn btn-secondary">Basic MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <p style="padding: 1rem;">
            Enhanced MRP with time-phased planning, lot sizing, and Master Production Schedule integration.
            <strong>Note:</strong> This is currently a preview version with simulated results.
        </p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?>">
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Configuration Form -->
    <div class="card">
        <h3 class="card-header">MRP Configuration</h3>
        <div style="padding: 1rem;">
            <form method="get" action="">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">Run Type</label>
                        <select name="run_type" class="form-control">
                            <option value="regenerative" <?= $options['run_type'] === 'regenerative' ? 'selected' : '' ?>>
                                Regenerative (Full)
                            </option>
                            <option value="net-change" <?= $options['run_type'] === 'net-change' ? 'selected' : '' ?>>
                                Net Change (Incremental)
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Planning Horizon (days)</label>
                        <input type="number" name="horizon" class="form-control" 
                               value="<?= $options['planning_horizon'] ?>" min="30" max="365">
                    </div>
                    <div>
                        <label class="form-label">Include Sources</label>
                        <div style="margin-top: 0.5rem;">
                            <label style="display: block; margin-bottom: 0.25rem;">
                                <input type="checkbox" name="include_orders" value="1" 
                                       <?= $options['include_orders'] ? 'checked' : '' ?>>
                                Customer Orders (<?= count($orders) ?> pending)
                            </label>
                            <label style="display: block; margin-bottom: 0.25rem;">
                                <input type="checkbox" name="include_mps" value="1" 
                                       <?= $options['include_mps'] ? 'checked' : '' ?>>
                                Master Production Schedule (<?= $mpsCount['mps_count'] ?> entries)
                            </label>
                            <label style="display: block;">
                                <input type="checkbox" name="include_safety" value="1" 
                                       <?= $options['include_safety_stock'] ? 'checked' : '' ?>>
                                Safety Stock Requirements
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">&nbsp;</label>
                        <div style="margin-top: 1.5rem;">
                            <button type="submit" name="run" value="1" class="btn btn-primary">
                                Run Enhanced MRP
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($results && $results['success']): ?>
    <!-- Results Summary -->
    <div class="card">
        <h3 class="card-header">MRP Run Results - ID: <?= $results['mrp_run_id'] ?></h3>
        <div style="padding: 1rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; text-align: center;">
                <div>
                    <h3 style="color: var(--primary-color);"><?= $results['summary']['total_materials'] ?></h3>
                    <p style="color: var(--secondary-color);">Materials Analyzed</p>
                </div>
                <div>
                    <h3 style="color: var(--primary-color);"><?= $results['summary']['total_products'] ?></h3>
                    <p style="color: var(--secondary-color);">Products Planned</p>
                </div>
                <div>
                    <h3 style="color: var(--warning-color);"><?= $results['summary']['po_suggestions'] ?></h3>
                    <p style="color: var(--secondary-color);">PO Suggestions</p>
                </div>
                <div>
                    <h3 style="color: var(--success-color);"><?= $results['summary']['production_suggestions'] ?></h3>
                    <p style="color: var(--secondary-color);">Production Orders</p>
                </div>
                <div>
                    <h3 style="color: var(--danger-color);"><?= $results['summary']['urgent_actions'] ?></h3>
                    <p style="color: var(--secondary-color);">Urgent Actions</p>
                </div>
                <div>
                    <h3 style="color: var(--dark-color);"><?= $results['summary']['execution_time'] ?></h3>
                    <p style="color: var(--secondary-color);">Execution Time</p>
                </div>
            </div>
            
            <div style="margin-top: 2rem;">
                <div class="alert alert-success">
                    <strong>Enhanced MRP Complete:</strong> Time-phased MRP calculation has processed BOMs, inventory levels, 
                    lead times, and generated purchase/production order suggestions.
                </div>
                
                <div style="margin-top: 1rem;">
                    <a href="index.php" class="btn btn-success">View Basic MRP Results</a>
                    <a href="../mps/" class="btn btn-info">Manage Production Schedule</a>
                    <a href="purchase-suggestions.php?run_id=<?= $results['mrp_run_id'] ?>" class="btn btn-warning">
                        View Purchase Suggestions
                    </a>
                    <a href="production-suggestions.php?run_id=<?= $results['mrp_run_id'] ?>" class="btn btn-primary">
                        View Production Orders
                    </a>
                    <a href="shortage-report.php" class="btn btn-danger">
                        Shortage Report
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Current Orders for MRP -->
    <?php if (!empty($orders)): ?>
    <div class="card">
        <h3 class="card-header">Current Orders for MRP Processing</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Required Date</th>
                        <th>Status</th>
                        <th>Items</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= date('M j, Y', strtotime($order['required_date'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $order['status'] === 'pending' ? 'warning' : 'info' ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td><?= $order['item_count'] ?></td>
                            <td>
                                <a href="../orders/index.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary">
                                    View Order
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Implementation Status -->
    <div class="card">
        <h3 class="card-header">Enhanced MRP Features</h3>
        <div style="padding: 1rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h4>✅ Completed Features:</h4>
                    <ul style="margin-left: 1.5rem;">
                        <li>Master Production Schedule interface</li>
                        <li>Planning calendar (weekly periods)</li>
                        <li>Database schema for time-phased planning</li>
                        <li>Lead time fields added to products/materials</li>
                        <li>Safety stock management structure</li>
                        <li>Basic MRP calculations (existing)</li>
                    </ul>
                </div>
                <div>
                    <h4>🔄 In Development:</h4>
                    <ul style="margin-left: 1.5rem;">
                        <li>Time-phased MRP calculation engine</li>
                        <li>Lot sizing rules (EOQ, Fixed, Min-Max)</li>
                        <li>Purchase order suggestions</li>
                        <li>Production order suggestions</li>
                        <li>MRP pegging and traceability</li>
                        <li>Capacity analysis reports</li>
                    </ul>
                </div>
            </div>
            
            <div style="margin-top: 2rem; padding: 1rem; background-color: var(--light-color); border-radius: 4px;">
                <h4>Next Steps:</h4>
                <ol style="margin-left: 1.5rem;">
                    <li><strong>Set up Master Production Schedule:</strong> <a href="../mps/">Go to MPS →</a></li>
                    <li><strong>Configure lead times:</strong> Edit your products to set manufacturing lead times</li>
                    <li><strong>Set safety stock levels:</strong> Update products and materials with safety stock quantities</li>
                    <li><strong>Test basic MRP:</strong> <a href="index.php">Run Basic MRP →</a></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>