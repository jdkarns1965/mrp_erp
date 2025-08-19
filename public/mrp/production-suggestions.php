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

// Get production order suggestions
$suggestions = [];
try {
    $sql = "SELECT 
                pros.id,
                pros.suggested_start_date,
                pros.suggested_end_date,
                pros.required_date,
                pros.quantity,
                pros.priority,
                pros.status,
                p.product_code,
                p.name as product_name,
                p.lead_time_days,
                uom.code as uom_code,
                DATEDIFF(pros.suggested_start_date, CURDATE()) as days_until_start,
                CASE 
                    WHEN DATEDIFF(pros.suggested_start_date, CURDATE()) < 0 THEN 'OVERDUE'
                    WHEN DATEDIFF(pros.suggested_start_date, CURDATE()) <= 3 THEN 'URGENT'
                    WHEN DATEDIFF(pros.suggested_start_date, CURDATE()) <= 7 THEN 'SOON'
                    ELSE 'NORMAL'
                END as urgency
            FROM production_order_suggestions pros
            JOIN products p ON pros.product_id = p.id
            JOIN units_of_measure uom ON pros.uom_id = uom.id" .
            ($runId ? " WHERE pros.mrp_run_id = ?" : "") .
            " ORDER BY 
                CASE urgency 
                    WHEN 'OVERDUE' THEN 1 
                    WHEN 'URGENT' THEN 2 
                    WHEN 'SOON' THEN 3 
                    ELSE 4 
                END,
                pros.suggested_start_date";
    
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
            <h2>Production Order Suggestions</h2>
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
            <h4>No Production Suggestions Found</h4>
            <p>Either no MRP run has been executed yet, or the latest run didn't generate any production order suggestions.</p>
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
                        <?= $urgency ?> Production Orders (<?= count($groupedSuggestions[$urgency]) ?>)
                    </h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Required Date</th>
                                    <th>Quantity</th>
                                    <th>Lead Time</th>
                                    <th>Days Until Start</th>
                                    <th>Priority</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedSuggestions[$urgency] as $suggestion): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($suggestion['product_code']) ?></strong><br>
                                            <small style="color: #666;"><?= htmlspecialchars($suggestion['product_name']) ?></small>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($suggestion['suggested_start_date'])) ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($suggestion['suggested_end_date'])) ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($suggestion['required_date'])) ?>
                                        </td>
                                        <td>
                                            <?= number_format($suggestion['quantity'], 2) ?> <?= htmlspecialchars($suggestion['uom_code']) ?>
                                        </td>
                                        <td>
                                            <?= $suggestion['lead_time_days'] ?> days
                                        </td>
                                        <td>
                                            <?php 
                                            $days = $suggestion['days_until_start'];
                                            if ($days < 0): ?>
                                                <span style="color: var(--danger-color);"><?= abs($days) ?> days overdue</span>
                                            <?php elseif ($days == 0): ?>
                                                <span style="color: var(--warning-color);">Start today</span>
                                            <?php else: ?>
                                                <?= $days ?> days
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $suggestion['priority'] === 'urgent' ? 'danger' : 
                                                                         ($suggestion['priority'] === 'high' ? 'warning' : 'info') ?>">
                                                <?= ucfirst($suggestion['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="alert('Create Work Order feature coming soon')"
                                                        title="Create Production Order">
                                                    Create WO
                                                </button>
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="alert('View BOM feature coming soon')"
                                                        title="View Bill of Materials">
                                                    BOM
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="alert('Schedule feature coming soon')"
                                                        title="Schedule Production">
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

        <!-- Capacity Analysis -->
        <div class="card">
            <h3 class="card-header">Capacity Analysis</h3>
            <div style="padding: 1rem;">
                <div class="alert alert-info">
                    <strong>Note:</strong> Capacity planning will be implemented in Phase 2. 
                    Currently showing suggested production orders based on lead times only.
                </div>
                
                <h4>Production Schedule Overview</h4>
                <p>Total production orders suggested: <strong><?= count($suggestions) ?></strong></p>
                
                <?php
                // Simple analysis of production timeline
                $weeklyProduction = [];
                foreach ($suggestions as $suggestion) {
                    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($suggestion['suggested_start_date'])));
                    if (!isset($weeklyProduction[$weekStart])) {
                        $weeklyProduction[$weekStart] = [];
                    }
                    $weeklyProduction[$weekStart][] = $suggestion;
                }
                ksort($weeklyProduction);
                ?>
                
                <div style="margin-top: 1rem;">
                    <h5>Production by Week:</h5>
                    <?php foreach (array_slice($weeklyProduction, 0, 8) as $week => $orders): ?>
                        <div style="margin-bottom: 0.5rem; padding: 0.5rem; background-color: var(--light-color); border-radius: 4px;">
                            <strong>Week of <?= date('M j, Y', strtotime($week)) ?>:</strong>
                            <?= count($orders) ?> production orders
                            (<?= array_sum(array_column($orders, 'quantity')) ?> units total)
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <h3 class="card-header">Bulk Actions</h3>
            <div style="padding: 1rem;">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="alert('Release all urgent coming soon')">
                        Release All Urgent
                    </button>
                    <button class="btn btn-success" onclick="alert('Export to CSV coming soon')">
                        Export to CSV
                    </button>
                    <button class="btn btn-info" onclick="alert('Capacity check coming soon')">
                        Check Capacity
                    </button>
                    <button class="btn btn-warning" onclick="alert('Material availability coming soon')">
                        Check Materials
                    </button>
                </div>
                
                <div style="margin-top: 1rem;">
                    <p><strong>Next Steps:</strong></p>
                    <ol>
                        <li>Review overdue and urgent production orders first</li>
                        <li>Verify material availability for each production order</li>
                        <li>Check machine/resource capacity for suggested dates</li>
                        <li>Create work orders for approved suggestions</li>
                        <li>Update production schedules and capacity planning</li>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>