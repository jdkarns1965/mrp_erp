<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get all actionable MRP items using the view
$actionableItems = [];
try {
    $sql = "SELECT * FROM v_mrp_actions ORDER BY urgency DESC, suggested_order_date ASC";
    $actionableItems = $db->select($sql);
} catch (Exception $e) {
    $error = "Error loading shortage data: " . $e->getMessage();
}

// Get current stock levels that are below safety stock
$stockShortages = [];
try {
    $sql = "SELECT 
                item_type,
                item_id,
                item_code,
                item_name,
                total_quantity as current_stock,
                available_quantity,
                safety_stock,
                reorder_point,
                lead_time_days,
                (safety_stock - available_quantity) as shortage_qty
            FROM v_current_stock 
            WHERE available_quantity < safety_stock
               OR (reorder_point > 0 AND available_quantity <= reorder_point)
            ORDER BY (safety_stock - available_quantity) DESC";
    
    $stockShortages = $db->select($sql);
} catch (Exception $e) {
    $stockError = "Error loading stock data: " . $e->getMessage();
}

// Group actionable items by urgency
$groupedActions = [];
foreach ($actionableItems as $item) {
    $urgency = $item['urgency'];
    if (!isset($groupedActions[$urgency])) {
        $groupedActions[$urgency] = [];
    }
    $groupedActions[$urgency][] = $item;
}

$urgencyColors = [
    'OVERDUE' => 'var(--danger-color)',
    'URGENT' => 'var(--warning-color)', 
    'SOON' => 'var(--info-color)',
    'NORMAL' => 'var(--success-color)'
];
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>MRP Shortage Report</h2>
            <div style="float: right;">
                <a href="run-enhanced.php" class="btn btn-primary">Run MRP</a>
                <a href="index.php" class="btn btn-secondary">Back to MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <p style="padding: 1rem;">
            Comprehensive report showing current inventory shortages, safety stock violations, 
            and MRP-generated action items requiring immediate attention.
        </p>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Current Stock Shortages -->
    <div class="card">
        <h3 class="card-header">Current Inventory Shortages</h3>
        <?php if (isset($stockError)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($stockError) ?>
            </div>
        <?php elseif (empty($stockShortages)): ?>
            <div class="alert alert-success" style="margin: 1rem;">
                <strong>Good news!</strong> No current inventory shortages detected. All items are above safety stock levels.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Current Stock</th>
                            <th>Available</th>
                            <th>Safety Stock</th>
                            <th>Reorder Point</th>
                            <th>Shortage Qty</th>
                            <th>Lead Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockShortages as $shortage): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($shortage['item_code']) ?></strong><br>
                                    <small style="color: #666;"><?= htmlspecialchars($shortage['item_name']) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $shortage['item_type'] === 'material' ? 'warning' : 'info' ?>">
                                        <?= ucfirst($shortage['item_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= number_format($shortage['current_stock'], 2) ?>
                                </td>
                                <td>
                                    <span style="color: var(--danger-color);">
                                        <?= number_format($shortage['available_quantity'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= number_format($shortage['safety_stock'], 2) ?>
                                </td>
                                <td>
                                    <?= $shortage['reorder_point'] > 0 ? number_format($shortage['reorder_point'], 2) : '-' ?>
                                </td>
                                <td>
                                    <strong style="color: var(--danger-color);">
                                        <?= number_format($shortage['shortage_qty'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= $shortage['lead_time_days'] ?> days
                                </td>
                                <td>
                                    <?php if ($shortage['available_quantity'] <= 0): ?>
                                        <span class="badge badge-danger">OUT OF STOCK</span>
                                    <?php elseif ($shortage['reorder_point'] > 0 && $shortage['available_quantity'] <= $shortage['reorder_point']): ?>
                                        <span class="badge badge-warning">REORDER NOW</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">BELOW SAFETY</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- MRP Action Items -->
    <?php if (empty($actionableItems)): ?>
        <div class="alert alert-info">
            <h4>No MRP Action Items</h4>
            <p>No purchase or production order suggestions are currently available. Run an enhanced MRP calculation to generate action items.</p>
        </div>
    <?php else: ?>
        <!-- Summary -->
        <div class="card">
            <h3 class="card-header">MRP Action Items Summary</h3>
            <div style="padding: 1rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; text-align: center;">
                    <?php foreach (['OVERDUE', 'URGENT', 'SOON', 'NORMAL'] as $urgency): ?>
                        <?php $count = count($groupedActions[$urgency] ?? []); ?>
                        <div>
                            <h3 style="color: <?= $urgencyColors[$urgency] ?>;"><?= $count ?></h3>
                            <p style="color: var(--secondary-color);"><?= $urgency ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Action items by urgency -->
        <?php foreach (['OVERDUE', 'URGENT', 'SOON', 'NORMAL'] as $urgency): ?>
            <?php if (!empty($groupedActions[$urgency])): ?>
                <div class="card">
                    <h3 class="card-header" style="color: <?= $urgencyColors[$urgency] ?>;">
                        <?= $urgency ?> Actions (<?= count($groupedActions[$urgency]) ?>)
                    </h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Action Type</th>
                                    <th>Item</th>
                                    <th>Quantity</th>
                                    <th>Order Date</th>
                                    <th>Required Date</th>
                                    <th>Days Until Order</th>
                                    <th>Priority</th>
                                    <th>Quick Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedActions[$urgency] as $action): ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-<?= $action['action_type'] === 'Purchase' ? 'warning' : 'info' ?>">
                                                <?= $action['action_type'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($action['item_code']) ?></strong><br>
                                            <small style="color: #666;"><?= htmlspecialchars($action['item_name']) ?></small>
                                        </td>
                                        <td>
                                            <?= number_format($action['quantity'], 2) ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($action['suggested_order_date'])) ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($action['required_date'])) ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $days = $action['days_until_order'];
                                            if ($days < 0): ?>
                                                <span style="color: var(--danger-color);"><?= abs($days) ?> days overdue</span>
                                            <?php elseif ($days == 0): ?>
                                                <span style="color: var(--warning-color);">Today</span>
                                            <?php else: ?>
                                                <?= $days ?> days
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $action['priority'] === 'urgent' ? 'danger' : 
                                                                         ($action['priority'] === 'high' ? 'warning' : 'info') ?>">
                                                <?= ucfirst($action['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($action['action_type'] === 'Purchase'): ?>
                                                <a href="purchase-suggestions.php" class="btn btn-sm btn-warning">
                                                    View PO Suggestions
                                                </a>
                                            <?php else: ?>
                                                <a href="production-suggestions.php" class="btn btn-sm btn-info">
                                                    View Production Orders
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Action Plan -->
    <div class="card">
        <h3 class="card-header">Recommended Action Plan</h3>
        <div style="padding: 1rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <h4>Immediate Actions (Today)</h4>
                    <ol>
                        <li><strong>Address Overdue Items:</strong> 
                            <?= count($groupedActions['OVERDUE'] ?? []) ?> items need immediate attention
                        </li>
                        <li><strong>Process Urgent Orders:</strong> 
                            <?= count($groupedActions['URGENT'] ?? []) ?> items should be ordered today
                        </li>
                        <li><strong>Review Stock Shortages:</strong> 
                            <?= count($stockShortages) ?> items below safety stock
                        </li>
                        <li><strong>Contact Suppliers:</strong> Expedite critical materials if possible</li>
                    </ol>
                </div>
                <div>
                    <h4>This Week</h4>
                    <ol>
                        <li><strong>Plan Production:</strong> 
                            Review production order suggestions and capacity
                        </li>
                        <li><strong>Update Safety Stocks:</strong> 
                            Adjust safety stock levels based on shortage patterns
                        </li>
                        <li><strong>Supplier Performance:</strong> 
                            Review lead times and delivery reliability
                        </li>
                        <li><strong>Monitor Progress:</strong> 
                            Track order placement and delivery status
                        </li>
                    </ol>
                </div>
            </div>
            
            <div style="margin-top: 2rem; padding: 1rem; background-color: var(--light-color); border-radius: 4px;">
                <h4>Key Metrics</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong>Stock Shortage Items:</strong> <?= count($stockShortages) ?>
                    </div>
                    <div>
                        <strong>Total Action Items:</strong> <?= count($actionableItems) ?>
                    </div>
                    <div>
                        <strong>Overdue Actions:</strong> <?= count($groupedActions['OVERDUE'] ?? []) ?>
                    </div>
                    <div>
                        <strong>Urgent Actions:</strong> <?= count($groupedActions['URGENT'] ?? []) ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 1rem;">
                <a href="purchase-suggestions.php" class="btn btn-warning">View Purchase Suggestions</a>
                <a href="production-suggestions.php" class="btn btn-info">View Production Orders</a>
                <a href="run-enhanced.php" class="btn btn-primary">Run New MRP</a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>