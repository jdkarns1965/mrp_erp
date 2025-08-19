<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = [];
$params = [];
$types = '';

if ($status !== 'all') {
    $where[] = "co.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($search) {
    $where[] = "(co.order_number LIKE ? OR co.customer_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch orders
$orders = $db->select("
    SELECT co.*, 
           DATE_FORMAT(co.order_date, '%Y-%m-%d') as order_date_formatted,
           DATE_FORMAT(co.required_date, '%Y-%m-%d') as required_date_formatted,
           COUNT(cod.id) as item_count,
           SUM(cod.quantity * cod.unit_price) as total_amount
    FROM customer_orders co
    LEFT JOIN customer_order_details cod ON co.id = cod.order_id
    $whereClause
    GROUP BY co.id
    ORDER BY co.created_at DESC
", $params, $types);

// Get status counts
$statusCounts = $db->select("
    SELECT status, COUNT(*) as count
    FROM customer_orders
    GROUP BY status
");

$statusMap = [];
foreach ($statusCounts as $row) {
    $statusMap[$row['status']] = $row['count'];
}

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

<div class="container">
    <div class="card">
        <div class="card-header">
            <h3 style="margin: 0;">Customer Orders</h3>
            <div style="float: right;">
                <a href="create.php" class="btn btn-primary">Create Order</a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="grid grid-3">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Order # or Customer" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="all">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>
                                Pending (<?php echo $statusMap['pending'] ?? 0; ?>)
                            </option>
                            <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>
                                Confirmed (<?php echo $statusMap['confirmed'] ?? 0; ?>)
                            </option>
                            <option value="in_production" <?php echo $status === 'in_production' ? 'selected' : ''; ?>>
                                In Production (<?php echo $statusMap['in_production'] ?? 0; ?>)
                            </option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>
                                Completed (<?php echo $statusMap['completed'] ?? 0; ?>)
                            </option>
                            <option value="on_hold" <?php echo $status === 'on_hold' ? 'selected' : ''; ?>>
                                On Hold (<?php echo $statusMap['on_hold'] ?? 0; ?>)
                            </option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                                Cancelled (<?php echo $statusMap['cancelled'] ?? 0; ?>)
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="index.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($orders)): ?>
            <div class="alert alert-info">
                <?php if ($search || $status !== 'all'): ?>
                    No orders found matching your filters. 
                    <a href="index.php">Clear filters</a> or 
                    <a href="create.php">create a new order</a>.
                <?php else: ?>
                    No orders found. <a href="create.php">Create your first customer order</a> to get started with MRP calculations.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Orders Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Order Date</th>
                            <th>Required Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <a href="view.php?id=<?php echo $order['id']; ?>" class="link-primary">
                                    <?php echo htmlspecialchars($order['order_number']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo $order['order_date_formatted']; ?></td>
                            <td>
                                <?php 
                                $daysUntil = (strtotime($order['required_date']) - strtotime('today')) / 86400;
                                $urgencyClass = $daysUntil < 0 ? 'text-danger' : ($daysUntil <= 3 ? 'text-warning' : '');
                                ?>
                                <span class="<?php echo $urgencyClass; ?>">
                                    <?php echo $order['required_date_formatted']; ?>
                                    <?php if ($daysUntil < 0): ?>
                                        <small>(Overdue)</small>
                                    <?php elseif ($daysUntil <= 3): ?>
                                        <small>(<?php echo round($daysUntil); ?> days)</small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo $order['item_count']; ?></td>
                            <td class="text-right">
                                <?php if ($order['total_amount']): ?>
                                    $<?php echo number_format($order['total_amount'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo getStatusColor($order['status']); ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="view.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info" title="View">
                                        View
                                    </a>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <a href="../mrp/run.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary" title="Run MRP">
                                            MRP
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="summary mt-3">
                Showing <?php echo count($orders); ?> order(s)
                <?php if ($search): ?>
                    matching "<?php echo htmlspecialchars($search); ?>"
                <?php endif; ?>
                <?php if ($status !== 'all'): ?>
                    with status "<?php echo htmlspecialchars($status); ?>"
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="btn-group mt-3">
            <a href="create.php" class="btn btn-primary">Create New Order</a>
            <a href="../" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
</div>

<style>
.filters {
    background: var(--bg-secondary);
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 0.25rem;
}
.filter-form {
    margin: 0;
}
.summary {
    padding: 0.5rem;
    background: var(--bg-secondary);
    border-radius: 0.25rem;
    color: var(--text-secondary);
}
</style>

<?php require_once '../../includes/footer.php'; ?>