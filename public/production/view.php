<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/help-system.php';
require_once '../../classes/Database.php';

// Get production order ID
$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header('Location: index.php');
    exit;
}

$db = Database::getInstance();

try {
    // Get production order details
    $sql = "SELECT po.*, co.customer_name, co.order_number as customer_order_number, 
                   p.name as product_name, p.part_number
            FROM production_orders po
            LEFT JOIN customer_orders co ON po.customer_order_id = co.id
            LEFT JOIN products p ON po.product_id = p.id
            WHERE po.id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        $_SESSION['error'] = 'Production order not found.';
        header('Location: index.php');
        exit;
    }
    
    // Get production operations
    $sql = "SELECT poo.*, wc.name as work_center_name
            FROM production_order_operations poo
            LEFT JOIN work_centers wc ON poo.work_center_id = wc.id
            WHERE poo.production_order_id = ?
            ORDER BY poo.sequence_number";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $operations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get material requirements
    $sql = "SELECT pom.*, m.code, m.name as material_name, m.unit_of_measure
            FROM production_order_materials pom
            LEFT JOIN materials m ON pom.material_id = m.id
            WHERE pom.production_order_id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get status history
    $sql = "SELECT * FROM production_order_status_history 
            WHERE production_order_id = ? 
            ORDER BY created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $status_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error loading production order: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Status badge helper function
function getStatusBadge($status) {
    $badges = [
        'planned' => 'badge-secondary',
        'released' => 'badge-primary', 
        'in_progress' => 'badge-warning',
        'completed' => 'badge-success',
        'on_hold' => 'badge-danger',
        'cancelled' => 'badge-dark'
    ];
    return $badges[$status] ?? 'badge-secondary';
}
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="page-header">
        <h2>Production Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
        <div class="page-actions">
            <a href="index.php" class="btn btn-secondary">← Back to Production</a>
            <a href="operations.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary">Manage Operations</a>
            <?php if ($order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
            <a href="edit.php?id=<?php echo $order['id']; ?>" class="btn btn-warning">Edit Order</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Production Order Summary -->
    <div class="card">
        <h3 class="card-header">Order Summary</h3>
        <div class="grid grid-3 mb-3">
            <div class="form-group">
                <label>Status</label>
                <span class="badge <?php echo getStatusBadge($order['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                </span>
            </div>
            <div class="form-group">
                <label>Priority</label>
                <span class="priority-<?php echo $order['priority']; ?>">
                    <?php echo $order['priority']; ?>
                </span>
            </div>
            <div class="form-group">
                <label>Progress</label>
                <?php 
                $completed_ops = array_filter($operations, fn($op) => $op['status'] === 'completed');
                $progress = count($operations) > 0 ? (count($completed_ops) / count($operations)) * 100 : 0;
                ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                </div>
                <span class="progress-text"><?php echo round($progress, 1); ?>% Complete</span>
            </div>
        </div>
        
        <div class="grid grid-2">
            <div>
                <h4>Product Information</h4>
                <table class="info-table">
                    <tr><td><strong>Product:</strong></td><td><?php echo htmlspecialchars($order['product_name']); ?></td></tr>
                    <tr><td><strong>Part Number:</strong></td><td><?php echo htmlspecialchars($order['part_number']); ?></td></tr>
                    <tr><td><strong>Quantity:</strong></td><td><?php echo number_format($order['quantity']); ?></td></tr>
                    <tr><td><strong>Completed:</strong></td><td><?php echo number_format($order['quantity_completed']); ?></td></tr>
                    <tr><td><strong>Remaining:</strong></td><td><?php echo number_format($order['quantity'] - $order['quantity_completed']); ?></td></tr>
                </table>
            </div>
            <div>
                <h4>Schedule Information</h4>
                <table class="info-table">
                    <tr><td><strong>Start Date:</strong></td><td><?php echo $order['scheduled_start'] ? date('M j, Y g:i A', strtotime($order['scheduled_start'])) : 'Not scheduled'; ?></td></tr>
                    <tr><td><strong>End Date:</strong></td><td><?php echo $order['scheduled_end'] ? date('M j, Y g:i A', strtotime($order['scheduled_end'])) : 'Not scheduled'; ?></td></tr>
                    <tr><td><strong>Customer Order:</strong></td><td><?php echo $order['customer_order_number'] ? '#' . htmlspecialchars($order['customer_order_number']) : 'Direct order'; ?></td></tr>
                    <tr><td><strong>Customer:</strong></td><td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Operations -->
    <?php if (!empty($operations)): ?>
    <div class="card">
        <h3 class="card-header">Production Operations</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Seq</th>
                        <th>Operation</th>
                        <th>Work Center</th>
                        <th>Status</th>
                        <th>Setup Time</th>
                        <th>Run Time</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operations as $op): ?>
                    <tr>
                        <td><?php echo $op['sequence_number']; ?></td>
                        <td><?php echo htmlspecialchars($op['operation_name']); ?></td>
                        <td><?php echo htmlspecialchars($op['work_center_name']); ?></td>
                        <td><span class="badge <?php echo getStatusBadge($op['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $op['status'])); ?>
                        </span></td>
                        <td><?php echo $op['setup_time']; ?> min</td>
                        <td><?php echo $op['run_time']; ?> min</td>
                        <td>
                            <?php if ($op['quantity_completed'] > 0): ?>
                                <?php echo number_format($op['quantity_completed']); ?> / <?php echo number_format($op['quantity_required']); ?>
                            <?php else: ?>
                                Not started
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="operations.php?order_id=<?php echo $order['id']; ?>#op-<?php echo $op['id']; ?>" class="btn-sm btn-primary">Update</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Material Requirements -->
    <?php if (!empty($materials)): ?>
    <div class="card">
        <h3 class="card-header">Material Requirements</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Required</th>
                        <th>Reserved</th>
                        <th>Consumed</th>
                        <th>Unit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $mat): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($mat['code']); ?></strong><br>
                            <small><?php echo htmlspecialchars($mat['material_name']); ?></small>
                        </td>
                        <td><?php echo number_format($mat['quantity_required'], 2); ?></td>
                        <td><?php echo number_format($mat['quantity_reserved'], 2); ?></td>
                        <td><?php echo number_format($mat['quantity_consumed'], 2); ?></td>
                        <td><?php echo htmlspecialchars($mat['unit_of_measure']); ?></td>
                        <td>
                            <?php if ($mat['quantity_reserved'] >= $mat['quantity_required']): ?>
                                <span class="badge badge-success">Reserved</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Partial</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Status History -->
    <?php if (!empty($status_history)): ?>
    <div class="card">
        <h3 class="card-header">Status History</h3>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>From Status</th>
                        <th>To Status</th>
                        <th>User</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_history as $history): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($history['created_at'])); ?></td>
                        <td>
                            <?php if ($history['from_status']): ?>
                                <span class="badge <?php echo getStatusBadge($history['from_status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $history['from_status'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Initial</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo getStatusBadge($history['to_status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $history['to_status'])); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($history['changed_by'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($history['notes'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
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

.progress-bar {
    width: 100%;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 5px;
}

.progress-fill {
    height: 100%;
    background: #28a745;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 0.9em;
    color: #666;
}

.priority-1 { color: #dc3545; font-weight: bold; }
.priority-2 { color: #fd7e14; font-weight: bold; }
.priority-3 { color: #ffc107; }
.priority-4 { color: #6c757d; }
.priority-5 { color: #6c757d; }

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

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .page-actions {
        justify-content: center;
    }
}
</style>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>