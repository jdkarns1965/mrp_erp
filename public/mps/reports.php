<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get date range for report
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months')); // Last day of 2 months from now

// Get MPS summary data
$mpsSummary = [];
try {
    $sql = "SELECT 
                p.product_code,
                p.name as product_name,
                pc.period_name,
                pc.period_start,
                pc.period_end,
                mps.firm_planned_qty,
                mps.demand_qty,
                mps.status,
                mps.created_at,
                mps.updated_at
            FROM master_production_schedule mps
            JOIN products p ON mps.product_id = p.id
            JOIN planning_calendar pc ON mps.period_id = pc.id
            WHERE pc.period_start BETWEEN ? AND ?
            ORDER BY p.product_code, pc.period_start";
    
    $mpsSummary = $db->select($sql, [$startDate, $endDate]);
} catch (Exception $e) {
    $error = "Error loading MPS data: " . $e->getMessage();
}

// Get capacity utilization data
$capacityData = [];
try {
    $sql = "SELECT 
                wc.name as work_center_name,
                pc.period_name,
                pc.period_start,
                pc.period_end,
                SUM(TIMESTAMPDIFF(HOUR, wcc.shift_start, wcc.shift_end)) as available_hours,
                AVG(wc.capacity_units_per_hour) as capacity_per_hour
            FROM work_centers wc
            LEFT JOIN work_center_calendar wcc ON wc.id = wcc.work_center_id
            LEFT JOIN planning_calendar pc ON wcc.date BETWEEN pc.period_start AND pc.period_end
            WHERE pc.period_start BETWEEN ? AND ?
              AND wcc.available_hours > 0
            GROUP BY wc.id, pc.id
            ORDER BY wc.name, pc.period_start";
    
    $capacityData = $db->select($sql, [$startDate, $endDate]);
} catch (Exception $e) {
    $capacityError = "Error loading capacity data: " . $e->getMessage();
}

// Get demand vs supply analysis
$demandSupplyData = [];
try {
    $sql = "SELECT 
                p.product_code,
                p.name as product_name,
                pc.period_name,
                COALESCE(SUM(mps.firm_planned_qty), 0) as planned_supply,
                COALESCE(SUM(cod.quantity), 0) as customer_demand,
                COALESCE(SUM(mps.firm_planned_qty), 0) - COALESCE(SUM(cod.quantity), 0) as net_balance
            FROM products p
            CROSS JOIN planning_calendar pc
            LEFT JOIN master_production_schedule mps ON p.id = mps.product_id AND pc.id = mps.period_id
            LEFT JOIN customer_order_details cod ON p.id = cod.product_id
            LEFT JOIN customer_orders co ON cod.order_id = co.id
                AND co.required_date BETWEEN pc.period_start AND pc.period_end
                AND co.status IN ('pending', 'confirmed', 'in_production')
            WHERE pc.period_start BETWEEN ? AND ?
              AND p.is_active = 1
            GROUP BY p.id, pc.id
            HAVING planned_supply > 0 OR customer_demand > 0
            ORDER BY p.product_code, pc.period_start";
    
    $demandSupplyData = $db->select($sql, [$startDate, $endDate]);
} catch (Exception $e) {
    $demandSupplyError = "Error loading demand/supply data: " . $e->getMessage();
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>MPS Reports & Analytics</h2>
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">Back to MPS</a>
                <button onclick="window.print()" class="btn btn-primary">Print Report</button>
            </div>
            <div style="clear: both;"></div>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="card" style="margin-top: 1rem;">
        <h3 class="card-header">Report Parameters</h3>
        <div style="padding: 1rem;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
                <div>
                    <label for="start_date">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div>
                    <label for="end_date">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <button type="submit" class="btn btn-primary">Update Report</button>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- MPS Summary -->
    <div class="card" style="margin-top: 1rem;">
        <h3 class="card-header">MPS Summary (<?= date('M j, Y', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>)</h3>
        <?php if (!empty($mpsSummary)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Period</th>
                            <th>Period Dates</th>
                            <th style="text-align: center;">Planned Qty</th>
                            <th style="text-align: center;">Demand Qty</th>
                            <th style="text-align: center;">Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mpsSummary as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['product_code']) ?></strong><br>
                                    <small style="color: #666;"><?= htmlspecialchars($item['product_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['period_name']) ?></td>
                                <td>
                                    <?= date('M j', strtotime($item['period_start'])) ?> - 
                                    <?= date('M j, Y', strtotime($item['period_end'])) ?>
                                </td>
                                <td style="text-align: center;"><?= number_format($item['firm_planned_qty']) ?></td>
                                <td style="text-align: center;"><?= number_format($item['demand_qty']) ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-<?= $item['status'] === 'firm' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y g:i A', strtotime($item['updated_at'] ?? $item['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="padding: 1rem;">
                <p>No MPS data found for the selected date range.</p>
                <a href="index.php" class="btn btn-primary">Create MPS Entries</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Demand vs Supply Analysis -->
    <div class="card" style="margin-top: 1rem;">
        <h3 class="card-header">Demand vs Supply Analysis</h3>
        <?php if (isset($demandSupplyError)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($demandSupplyError) ?></div>
        <?php elseif (!empty($demandSupplyData)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Period</th>
                            <th style="text-align: center;">Customer Demand</th>
                            <th style="text-align: center;">Planned Supply</th>
                            <th style="text-align: center;">Net Balance</th>
                            <th style="text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($demandSupplyData as $item): ?>
                            <?php 
                            $balance = $item['net_balance'];
                            $statusClass = $balance < 0 ? 'text-danger' : ($balance == 0 ? 'text-warning' : 'text-success');
                            $statusText = $balance < 0 ? 'Shortage' : ($balance == 0 ? 'Balanced' : 'Surplus');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['product_code']) ?></strong><br>
                                    <small style="color: #666;"><?= htmlspecialchars($item['product_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['period_name']) ?></td>
                                <td style="text-align: center;"><?= number_format($item['customer_demand']) ?></td>
                                <td style="text-align: center;"><?= number_format($item['planned_supply']) ?></td>
                                <td style="text-align: center;" class="<?= $statusClass ?>">
                                    <?= $balance >= 0 ? '+' : '' ?><?= number_format($balance) ?>
                                </td>
                                <td style="text-align: center;" class="<?= $statusClass ?>">
                                    <?= $statusText ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="padding: 1rem;">
                <p>No demand/supply data found for the selected date range.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Capacity Utilization -->
    <div class="card" style="margin-top: 1rem;">
        <h3 class="card-header">Work Center Capacity Overview</h3>
        <?php if (isset($capacityError)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($capacityError) ?></div>
        <?php elseif (!empty($capacityData)): ?>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Work Center</th>
                            <th>Period</th>
                            <th style="text-align: center;">Available Hours</th>
                            <th style="text-align: center;">Capacity/Hour</th>
                            <th style="text-align: center;">Total Capacity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($capacityData as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['work_center_name']) ?></td>
                                <td><?= htmlspecialchars($item['period_name']) ?></td>
                                <td style="text-align: center;"><?= number_format($item['available_hours'], 1) ?></td>
                                <td style="text-align: center;"><?= number_format($item['capacity_per_hour'], 2) ?></td>
                                <td style="text-align: center;">
                                    <?= number_format($item['available_hours'] * $item['capacity_per_hour'], 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="padding: 1rem;">
                <p>No capacity data found for the selected date range.</p>
                <p>Ensure work centers are configured with calendar data.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary Statistics -->
    <div class="card" style="margin-top: 1rem;">
        <h3 class="card-header">Summary Statistics</h3>
        <div style="padding: 1rem;">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4><?= count($mpsSummary) ?></h4>
                        <p>MPS Entries</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4><?= count(array_unique(array_column($mpsSummary, 'product_code'))) ?></h4>
                        <p>Products Planned</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4><?= array_sum(array_column($mpsSummary, 'firm_planned_qty')) ?></h4>
                        <p>Total Planned Qty</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4><?= count(array_filter($demandSupplyData, function($item) { return $item['net_balance'] < 0; })) ?></h4>
                        <p>Shortages Identified</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-box {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 4px;
    margin-bottom: 1rem;
}

.stat-box h4 {
    font-size: 2rem;
    font-weight: bold;
    margin: 0;
    color: #007bff;
}

.stat-box p {
    margin: 0.5rem 0 0 0;
    color: #666;
    font-weight: 500;
}

.badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 3px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

@media print {
    .btn, .card-header button {
        display: none;
    }
    
    .container {
        max-width: 100%;
    }
    
    .card {
        border: 1px solid #ddd;
        box-shadow: none;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>