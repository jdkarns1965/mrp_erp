<?php
session_start();
require_once '../../includes/header.php';
require_once '../../includes/help-system.php';
require_once '../../classes/Database.php';
require_once '../../classes/MRPEngine.php';

// Get order ID from URL parameter
$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    $_SESSION['error'] = 'No order ID specified.';
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

try {
    // Get customer order details
    $sql = "SELECT co.*, c.name as customer_name 
            FROM customer_orders co 
            LEFT JOIN customers c ON co.customer_id = c.id 
            WHERE co.id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        header('Location: index.php');
        exit;
    }
    
    // Get order items
    $sql = "SELECT coi.*, p.name as product_name, p.part_number
            FROM customer_order_items coi
            LEFT JOIN products p ON coi.product_id = p.id
            WHERE coi.customer_order_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Run MRP calculation
    $mrpEngine = new MRPEngine();
    $mrp_results = [];
    $total_shortages = 0;
    $total_cost = 0;
    
    foreach ($order_items as $item) {
        $result = $mrpEngine->calculateRequirements($item['product_id'], $item['quantity']);
        $mrp_results[$item['product_id']] = $result;
        
        // Count shortages and costs
        foreach ($result as $material) {
            if ($material['shortage'] > 0) {
                $total_shortages++;
                $total_cost += $material['total_cost'];
            }
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error calculating MRP results: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

function getStatusBadge($shortage) {
    if ($shortage > 0) {
        return 'badge-danger';
    }
    return 'badge-success';
}

function getStatusText($shortage) {
    if ($shortage > 0) {
        return 'Shortage';
    }
    return 'Available';
}
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<div class="container">
    <div class="page-header">
        <h2>MRP Results - Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">← Back to MRP</a>
            <a href="run.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">Re-run MRP</a>
            <?php if ($total_shortages > 0): ?>
            <a href="purchase-suggestions.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">Generate Purchase Orders</a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php echo HelpSystem::renderHelpPanel('mrp'); ?>
    <?php echo HelpSystem::renderHelpButton(); ?>
    
    <!-- Order Summary -->
    <div class="card">
        <h3 class="card-header">Order Summary</h3>
        <div class="grid grid-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($order_items); ?></div>
                <div class="stat-label">Product Lines</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_shortages; ?></div>
                <div class="stat-label">Material Shortages</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($total_cost, 2); ?></div>
                <div class="stat-label">Purchase Cost</div>
            </div>
        </div>
        
        <div class="order-info">
            <div class="grid grid-2">
                <div>
                    <h4>Order Information</h4>
                    <table class="info-table">
                        <tr><td><strong>Customer:</strong></td><td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td></tr>
                        <tr><td><strong>Order Date:</strong></td><td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td></tr>
                        <tr><td><strong>Due Date:</strong></td><td><?php echo date('M j, Y', strtotime($order['due_date'])); ?></td></tr>
                        <tr><td><strong>Status:</strong></td><td><span class="badge badge-primary"><?php echo ucfirst($order['status']); ?></span></td></tr>
                    </table>
                </div>
                <div>
                    <h4>MRP Analysis</h4>
                    <table class="info-table">
                        <tr><td><strong>Calculation Date:</strong></td><td><?php echo date('M j, Y g:i A'); ?></td></tr>
                        <tr><td><strong>Planning Horizon:</strong></td><td>30 days</td></tr>
                        <tr><td><strong>Safety Stock:</strong></td><td>Included</td></tr>
                        <tr><td><strong>Lead Times:</strong></td><td>Considered</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Requirements -->
    <?php foreach ($order_items as $item): ?>
    <div class="card">
        <h3 class="card-header">
            <?php echo htmlspecialchars($item['product_name']); ?>
            <small class="text-muted">(<?php echo htmlspecialchars($item['part_number']); ?>)</small>
            - Quantity: <?php echo number_format($item['quantity']); ?>
        </h3>
        
        <?php if (isset($mrp_results[$item['product_id']])): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Required</th>
                        <th>Available</th>
                        <th>Shortage</th>
                        <th>Unit</th>
                        <th>Lead Time</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th>Action Required</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mrp_results[$item['product_id']] as $material): ?>
                    <tr class="<?php echo $material['shortage'] > 0 ? 'row-shortage' : 'row-ok'; ?>">
                        <td><strong><?php echo htmlspecialchars($material['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo number_format($material['gross_requirement'], 2); ?></td>
                        <td><?php echo number_format($material['available_quantity'], 2); ?></td>
                        <td class="<?php echo $material['shortage'] > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php if ($material['shortage'] > 0): ?>
                                <?php echo number_format($material['shortage'], 2); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($material['unit_of_measure']); ?></td>
                        <td><?php echo $material['lead_time_days']; ?> days</td>
                        <td>$<?php echo number_format($material['total_cost'], 2); ?></td>
                        <td>
                            <span class="badge <?php echo getStatusBadge($material['shortage']); ?>">
                                <?php echo getStatusText($material['shortage']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($material['shortage'] > 0): ?>
                                <span class="text-danger">Order <?php echo number_format($material['shortage'], 2); ?> <?php echo $material['unit_of_measure']; ?></span>
                            <?php else: ?>
                                <span class="text-success">No action needed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="alert alert-warning">No BOM found for this product. Cannot calculate material requirements.</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Summary and Actions -->
    <div class="card">
        <h3 class="card-header">Summary & Next Steps</h3>
        
        <?php if ($total_shortages > 0): ?>
        <div class="alert alert-warning">
            <h4>⚠️ Action Required</h4>
            <p>This order has <?php echo $total_shortages; ?> material shortage(s) that need to be resolved before production can begin.</p>
        </div>
        
        <h4>Recommended Actions:</h4>
        <ol>
            <li><strong>Review Shortages:</strong> Check all red-highlighted materials above</li>
            <li><strong>Generate Purchase Orders:</strong> Click the "Generate Purchase Orders" button to create supplier orders</li>
            <li><strong>Expedite Critical Items:</strong> Contact suppliers for items with short lead times</li>
            <li><strong>Consider Alternatives:</strong> Check if substitute materials are available</li>
            <li><strong>Update Schedule:</strong> Adjust production timeline based on material availability</li>
        </ol>
        
        <?php else: ?>
        <div class="alert alert-success">
            <h4>✅ Ready for Production</h4>
            <p>All materials are available for this order. Production can proceed as scheduled.</p>
        </div>
        
        <h4>Next Steps:</h4>
        <ol>
            <li><strong>Create Production Order:</strong> Convert this customer order to a production order</li>
            <li><strong>Schedule Operations:</strong> Assign work centers and set production timeline</li>
            <li><strong>Reserve Materials:</strong> Allocate inventory to this production order</li>
            <li><strong>Begin Production:</strong> Start manufacturing according to schedule</li>
        </ol>
        <?php endif; ?>
        
        <div class="btn-group mt-3">
            <a href="run.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">Re-run Analysis</a>
            <?php if ($total_shortages > 0): ?>
            <a href="purchase-suggestions.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">Purchase Orders</a>
            <a href="shortage-report.php?order_id=<?php echo $order_id; ?>" class="btn btn-warning">Shortage Report</a>
            <?php else: ?>
            <a href="../production/create.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">Create Production Order</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-secondary">Print Results</button>
        </div>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.page-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.info-table {
    width: 100%;
    margin: 0;
}

.info-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
}

.info-table td:first-child {
    width: 40%;
}

.order-info {
    margin-top: 20px;
}

.row-shortage {
    background-color: #fff5f5;
}

.row-ok {
    background-color: #f0fff4;
}

.text-danger {
    color: #dc3545;
    font-weight: bold;
}

.text-success {
    color: #28a745;
}

.stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.stat-value {
    font-size: 2em;
    font-weight: bold;
    color: #333;
}

.stat-label {
    color: #666;
    margin-top: 5px;
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .page-actions {
        justify-content: center;
    }
    
    .grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .page-actions,
    .help-button,
    .help-panel,
    .btn,
    .btn-group {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        margin-bottom: 20px;
    }
}
</style>

<?php echo HelpSystem::getHelpScript(); ?>
<?php require_once '../../includes/footer.php'; ?>