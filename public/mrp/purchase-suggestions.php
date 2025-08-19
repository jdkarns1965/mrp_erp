<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get MRP run ID from URL
$runId = $_GET['run_id'] ?? null;

// Get MRP run details
$mrpRun = null;
if ($runId) {
    try {
        $sql = "SELECT * FROM mrp_runs WHERE id = ?";
        $mrpRun = $db->selectOne($sql, [$runId]);
    } catch (Exception $e) {
        // Handle error
    }
}

// Get purchase order suggestions
$suggestions = [];
try {
    $sql = "SELECT 
                pos.id,
                pos.suggested_order_date,
                pos.required_date,
                pos.quantity,
                pos.unit_cost,
                pos.total_cost,
                pos.priority,
                pos.status,
                m.material_code,
                m.name as material_name,
                m.lead_time_days,
                uom.code as uom_code,
                s.name as supplier_name,
                DATEDIFF(pos.suggested_order_date, CURDATE()) as days_until_order,
                CASE 
                    WHEN DATEDIFF(pos.suggested_order_date, CURDATE()) < 0 THEN 'OVERDUE'
                    WHEN DATEDIFF(pos.suggested_order_date, CURDATE()) <= 3 THEN 'URGENT'
                    WHEN DATEDIFF(pos.suggested_order_date, CURDATE()) <= 7 THEN 'SOON'
                    ELSE 'NORMAL'
                END as urgency
            FROM purchase_order_suggestions pos
            JOIN materials m ON pos.material_id = m.id
            JOIN units_of_measure uom ON pos.uom_id = uom.id
            LEFT JOIN suppliers s ON pos.supplier_id = s.id" .
            ($runId ? " WHERE pos.mrp_run_id = ?" : "") .
            " ORDER BY 
                CASE urgency 
                    WHEN 'OVERDUE' THEN 1 
                    WHEN 'URGENT' THEN 2 
                    WHEN 'SOON' THEN 3 
                    ELSE 4 
                END,
                pos.suggested_order_date";
    
    $params = $runId ? [$runId] : [];
    $suggestions = $db->select($sql, $params);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Group suggestions by urgency
$groupedSuggestions = [];
foreach ($suggestions as $suggestion) {
    $urgency = $suggestion['urgency'];
    if (!isset($groupedSuggestions[$urgency])) {
        $groupedSuggestions[$urgency] = [];
    }
    $groupedSuggestions[$urgency][] = $suggestion;
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
            <h2>Purchase Order Suggestions</h2>
            <div style="float: right;">
                <?php if ($mrpRun): ?>
                    <span class="badge badge-info">
                        MRP Run #<?= $mrpRun['id'] ?> - <?= date('M j, Y g:i A', strtotime($mrpRun['run_date'])) ?>
                    </span>
                <?php endif; ?>
                <a href="run-enhanced.php" class="btn btn-secondary">Back to MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            Error loading suggestions: <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($suggestions)): ?>
        <div class="alert alert-info">
            <h4>No Purchase Suggestions Found</h4>
            <p>Either no MRP run has been executed yet, or the latest run didn't generate any purchase order suggestions.</p>
            <a href="run-enhanced.php" class="btn btn-primary">Run Enhanced MRP</a>
        </div>
    <?php else: ?>
        <!-- Summary -->
        <div class="card">
            <h3 class="card-header">Summary</h3>
            <div style="padding: 1rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; text-align: center;">
                    <?php foreach (['OVERDUE', 'URGENT', 'SOON', 'NORMAL'] as $urgency): ?>
                        <?php $count = count($groupedSuggestions[$urgency] ?? []); ?>
                        <div>
                            <h3 style="color: <?= $urgencyColors[$urgency] ?>;"><?= $count ?></h3>
                            <p style="color: var(--secondary-color);"><?= $urgency ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Suggestions by urgency -->
        <?php foreach (['OVERDUE', 'URGENT', 'SOON', 'NORMAL'] as $urgency): ?>
            <?php if (!empty($groupedSuggestions[$urgency])): ?>
                <div class="card">
                    <h3 class="card-header" style="color: <?= $urgencyColors[$urgency] ?>;">
                        <?= $urgency ?> Actions (<?= count($groupedSuggestions[$urgency]) ?>)
                    </h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Supplier</th>
                                    <th>Order Date</th>
                                    <th>Required Date</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Cost</th>
                                    <th>Days Until Order</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedSuggestions[$urgency] as $suggestion): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($suggestion['material_code']) ?></strong><br>
                                            <small style="color: #666;"><?= htmlspecialchars($suggestion['material_name']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($suggestion['supplier_name'] ?? 'No supplier') ?>
                                            <?php if ($suggestion['lead_time_days'] > 0): ?>
                                                <br><small style="color: #666;"><?= $suggestion['lead_time_days'] ?> day lead time</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($suggestion['suggested_order_date'])) ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($suggestion['required_date'])) ?>
                                        </td>
                                        <td>
                                            <?= number_format($suggestion['quantity'], 2) ?> <?= htmlspecialchars($suggestion['uom_code']) ?>
                                        </td>
                                        <td>
                                            $<?= number_format($suggestion['unit_cost'], 2) ?>
                                        </td>
                                        <td>
                                            <strong>$<?= number_format($suggestion['total_cost'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $days = $suggestion['days_until_order'];
                                            if ($days < 0): ?>
                                                <span style="color: var(--danger-color);"><?= abs($days) ?> days overdue</span>
                                            <?php elseif ($days == 0): ?>
                                                <span style="color: var(--warning-color);">Order today</span>
                                            <?php else: ?>
                                                <?= $days ?> days
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="alert('Create PO feature coming soon')"
                                                        title="Create Purchase Order">
                                                    Create PO
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="alert('Approve suggestion feature coming soon')"
                                                        title="Mark as Approved">
                                                    ✓
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Actions -->
        <div class="card">
            <h3 class="card-header">Bulk Actions</h3>
            <div style="padding: 1rem;">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="alert('Approve all urgent coming soon')">
                        Approve All Urgent
                    </button>
                    <button class="btn btn-success" onclick="alert('Export to CSV coming soon')">
                        Export to CSV
                    </button>
                    <button class="btn btn-info" onclick="alert('Generate PO batch coming soon')">
                        Generate PO Batch
                    </button>
                    <button class="btn btn-warning" onclick="alert('Email suppliers coming soon')">
                        Email Suppliers
                    </button>
                </div>
                
                <div style="margin-top: 1rem;">
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Review overdue and urgent suggestions first</li>
                        <li>Verify supplier information and lead times</li>
                        <li>Check quantity requirements against supplier MOQ</li>
                        <li>Create purchase orders for approved suggestions</li>
                        <li>Update material safety stock levels based on shortages</li>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>