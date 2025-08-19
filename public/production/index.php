<?php
/**
 * Production Orders Management Interface
 * Phase 2: Production scheduling and order management
 */

session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';
require_once '../../classes/ProductionScheduler.php';

$db = Database::getInstance();
$scheduler = new ProductionScheduler();

// Handle search
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$priorityFilter = $_GET['priority'] ?? '';

// Build query with filters
$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(po.order_number LIKE ? OR p.product_code LIKE ? OR p.name LIKE ? OR co.customer_name LIKE ?)";
    $params = array_merge($params, ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
}

if (!empty($statusFilter)) {
    $whereConditions[] = "po.status = ?";
    $params[] = $statusFilter;
}

if (!empty($priorityFilter)) {
    $whereConditions[] = "po.priority_level = ?";
    $params[] = $priorityFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$sql = "
    SELECT 
        po.*,
        p.product_code,
        p.name as product_name,
        co.customer_name,
        co.order_number as customer_order_number,
        co.required_date as customer_required_date,
        uom.code as uom_code,
        (po.quantity_ordered - po.quantity_completed) as quantity_remaining,
        CASE 
            WHEN po.scheduled_end_date < CURDATE() AND po.status NOT IN ('completed', 'cancelled') THEN 'overdue'
            WHEN po.scheduled_end_date = CURDATE() AND po.status NOT IN ('completed', 'cancelled') THEN 'due_today'
            ELSE 'on_schedule'
        END as schedule_status
    FROM production_orders po
    LEFT JOIN products p ON po.product_id = p.id
    LEFT JOIN customer_orders co ON po.customer_order_id = co.id
    LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
    $whereClause
    ORDER BY 
        CASE po.priority_level 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
        END,
        po.scheduled_start_date ASC,
        po.created_at DESC
";

$productionOrders = $db->select($sql, $params);

// Get summary statistics
$stats = $db->select("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned,
        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'on_hold' THEN 1 ELSE 0 END) as on_hold,
        SUM(CASE WHEN scheduled_end_date < CURDATE() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue
    FROM production_orders
")[0];

?>

<style>
        .production-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2563eb;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-planned { background: #e5e7eb; color: #374151; }
        .status-released { background: #dbeafe; color: #1d4ed8; }
        .status-in_progress { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-on_hold { background: #fde2e7; color: #be185d; }
        
        .priority-urgent { color: #dc2626; font-weight: bold; }
        .priority-high { color: #ea580c; font-weight: 600; }
        .priority-normal { color: #6b7280; }
        .priority-low { color: #9ca3af; }
        
        .schedule-overdue { color: #dc2626; font-weight: bold; }
        .schedule-due_today { color: #ea580c; font-weight: 600; }
        .schedule-on_schedule { color: #059669; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary { background: #2563eb; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-success { background: #059669; color: white; }
        .btn-warning { background: #d97706; color: white; }
        
        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .filters {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                justify-content: center;
            }
        }
    </style>

<div class="container">
    <div class="card">
        <div class="card-header">
            Production Orders
            <div style="float: right;">
                <a href="create.php" class="btn btn-primary">+ Create Production Order</a>
                <a href="gantt.php" class="btn btn-secondary">📊 Gantt Chart</a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Statistics Summary -->
        <div class="production-stats">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_orders'] ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['planned'] ?></div>
                <div class="stat-label">Planned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['overdue'] ?></div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-bar">
            <form method="GET" class="filters">
            <div>
                <label for="search">Search Orders</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?= htmlspecialchars($searchTerm) ?>"
                    placeholder="Order number, product, customer..."
                    autocomplete="off">
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    <option value="planned" <?= $statusFilter === 'planned' ? 'selected' : '' ?>>Planned</option>
                    <option value="released" <?= $statusFilter === 'released' ? 'selected' : '' ?>>Released</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="on_hold" <?= $statusFilter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label for="priority">Priority</label>
                <select id="priority" name="priority">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
        </div>

        <!-- Production Orders Table -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Quantity</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Scheduled</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productionOrders)): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($priorityFilter)): ?>
                                    No production orders found matching your criteria.
                                    <a href="index.php">Clear filters</a>
                                <?php else: ?>
                                    No production orders found. <a href="create.php">Create your first production order</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productionOrders as $order): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?= $order['id'] ?>" class="link">
                                        <?= htmlspecialchars($order['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($order['product_code']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($order['product_name']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($order['customer_name']): ?>
                                        <div>
                                            <?= htmlspecialchars($order['customer_name']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($order['customer_order_number']) ?></small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Internal Order</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <?= number_format($order['quantity_ordered'], 2) ?> <?= htmlspecialchars($order['uom_code']) ?><br>
                                        <?php if ($order['quantity_completed'] > 0): ?>
                                            <small class="text-muted">
                                                Completed: <?= number_format($order['quantity_completed'], 2) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-<?= $order['priority_level'] ?>">
                                        <?= ucfirst($order['priority_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['scheduled_start_date'] && $order['scheduled_end_date']): ?>
                                        <div class="schedule-<?= $order['schedule_status'] ?>">
                                            <?= date('M j', strtotime($order['scheduled_start_date'])) ?> - 
                                            <?= date('M j', strtotime($order['scheduled_end_date'])) ?>
                                            <?php if ($order['schedule_status'] === 'overdue'): ?>
                                                <br><small>⚠️ Overdue</small>
                                            <?php elseif ($order['schedule_status'] === 'due_today'): ?>
                                                <br><small>📅 Due Today</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not Scheduled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $progressPercent = $order['quantity_ordered'] > 0 ? 
                                        ($order['quantity_completed'] / $order['quantity_ordered']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar" style="background: #e5e7eb; border-radius: 4px; height: 8px;">
                                        <div style="background: #059669; height: 100%; width: <?= $progressPercent ?>%; border-radius: 4px;"></div>
                                    </div>
                                    <small><?= round($progressPercent, 1) ?>%</small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?= $order['id'] ?>" class="btn-sm btn-primary">View</a>
                                        <?php if ($order['status'] === 'planned'): ?>
                                            <a href="edit.php?id=<?= $order['id'] ?>" class="btn-sm btn-secondary">Edit</a>
                                        <?php endif; ?>
                                        <?php if (in_array($order['status'], ['planned', 'released'])): ?>
                                            <button onclick="changeStatus(<?= $order['id'] ?>, 'in_progress')" 
                                                    class="btn-sm btn-success">Start</button>
                                        <?php endif; ?>
                                        <?php if ($order['status'] === 'in_progress'): ?>
                                            <a href="operations.php?id=<?= $order['id'] ?>" class="btn-sm btn-warning">Operations</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
        function changeStatus(orderId, newStatus) {
            if (confirm(`Are you sure you want to change the status to "${newStatus.replace('_', ' ')}"?`)) {
                fetch('update-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        new_status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the status.');
                });
            }
        }
    </script>

<?php require_once '../../includes/footer.php'; ?>