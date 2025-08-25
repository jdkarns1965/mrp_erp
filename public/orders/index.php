<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/search-component.php';
require_once '../../classes/Database.php';
require_once '../../includes/enum-helper.php';

$db = Database::getInstance();

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$urgency = isset($_GET['urgency']) ? $_GET['urgency'] : 'all';

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

// Add urgency filter
if ($urgency === 'overdue') {
    $where[] = "co.required_date < CURDATE()";
} elseif ($urgency === 'urgent') {
    $where[] = "co.required_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND co.required_date >= CURDATE()";
} elseif ($urgency === 'upcoming') {
    $where[] = "co.required_date > DATE_ADD(CURDATE(), INTERVAL 3 DAY)";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Fetch orders
$orders = $db->select("
    SELECT co.*, 
           DATE_FORMAT(co.order_date, '%Y-%m-%d') as order_date_formatted,
           DATE_FORMAT(co.required_date, '%Y-%m-%d') as required_date_formatted,
           COUNT(cod.id) as item_count,
           SUM(cod.quantity * cod.unit_price) as total_amount,
           DATEDIFF(co.required_date, CURDATE()) as days_until_due
    FROM customer_orders co
    LEFT JOIN customer_order_details cod ON co.id = cod.order_id
    $whereClause
    GROUP BY co.id
    ORDER BY co.created_at DESC
", $params, $types);

// Get status counts
$statusCounts = $db->select("
    SELECT 
        status, 
        COUNT(*) as count
    FROM customer_orders
    GROUP BY status
");

$statusMap = [];
foreach ($statusCounts as $row) {
    $statusMap[$row['status']] = $row['count'];
}

// Get urgency counts
$urgencyCounts = $db->select("
    SELECT 
        CASE 
            WHEN required_date < CURDATE() THEN 'overdue'
            WHEN required_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND required_date >= CURDATE() THEN 'urgent'
            ELSE 'upcoming'
        END as urgency_status,
        COUNT(*) as count
    FROM customer_orders
    WHERE status NOT IN ('completed', 'cancelled')
    GROUP BY urgency_status
");

$urgencyMap = [];
foreach ($urgencyCounts as $row) {
    $urgencyMap[$row['urgency_status']] = $row['count'];
}

// Calculate total active orders
$totalActive = array_sum(array_map(function($s) use ($statusMap) {
    return in_array($s, ['completed', 'cancelled']) ? 0 : ($statusMap[$s] ?? 0);
}, array_keys($statusMap)));

// Get status color class
function getStatusColorClass($status) {
    switch($status) {
        case 'pending': return 'bg-gray-500';
        case 'confirmed': return 'bg-blue-500';
        case 'in_production': return 'bg-cyan-500';
        case 'completed': return 'bg-green-500';
        case 'cancelled': return 'bg-red-500';
        case 'on_hold': return 'bg-yellow-500';
        default: return 'bg-gray-400';
    }
}

// Get urgency class
function getUrgencyClass($daysUntil) {
    if ($daysUntil === null) return '';
    if ($daysUntil < 0) return 'text-red-600 font-semibold';
    if ($daysUntil <= 3) return 'text-yellow-600 font-semibold';
    return 'text-gray-600';
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900">Customer Orders</h2>
        </div>
        
        <!-- Standardized Search Component -->
        <div class="px-6 py-4">
            <?php 
            echo renderSearchComponent([
                'entity' => 'orders',
                'placeholder' => 'Search by order number or customer name...',
                'current_search' => $search,
                'show_filters' => [
                    [
                        'name' => 'active_only',
                        'value' => '1',
                        'label' => 'Active orders only'
                    ]
                ]
            ]);
            ?>
        </div>
        
        <!-- Action Button -->
        <div class="px-6 pb-4">
            <a href="create.php" class="btn btn-primary flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Order
            </a>
        </div>
    </div>

    <!-- Orders List -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <!-- List Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">
                    Order Management
                </h3>
                <div class="text-sm text-gray-600">
                    <?php echo count($orders); ?> order<?php echo count($orders) !== 1 ? 's' : ''; ?> found
                </div>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="px-6 py-3 border-b border-gray-200 bg-gray-50">
            <div class="flex flex-wrap gap-2">
                <a href="?status=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $status === 'all' ? 'bg-blue-100 text-blue-700' : 'bg-white text-gray-700 hover:bg-gray-100'; ?> border">
                    All Orders
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-200 text-gray-700">
                        <?php echo array_sum(array_map(function($v) { return $v; }, $statusMap)); ?>
                    </span>
                </a>
                
                <?php if ($urgencyMap['overdue'] ?? 0 > 0): ?>
                <a href="?urgency=overdue<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $urgency === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-red-50 text-red-700 hover:bg-red-100'; ?> border border-red-200">
                    Overdue
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-red-600 text-white">
                        <?php echo $urgencyMap['overdue'] ?? 0; ?>
                    </span>
                </a>
                <?php endif; ?>
                
                <?php if ($urgencyMap['urgent'] ?? 0 > 0): ?>
                <a href="?urgency=urgent<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $urgency === 'urgent' ? 'bg-yellow-100 text-yellow-700' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100'; ?> border border-yellow-200">
                    Urgent (≤3 days)
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-yellow-600 text-white">
                        <?php echo $urgencyMap['urgent'] ?? 0; ?>
                    </span>
                </a>
                <?php endif; ?>
                
                <a href="?status=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $status === 'pending' ? 'bg-gray-100 text-gray-700' : 'bg-white text-gray-700 hover:bg-gray-100'; ?> border">
                    Pending
                    <?php if ($statusMap['pending'] ?? 0 > 0): ?>
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-gray-500 text-white">
                        <?php echo $statusMap['pending']; ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <a href="?status=in_production<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $status === 'in_production' ? 'bg-cyan-100 text-cyan-700' : 'bg-white text-cyan-700 hover:bg-cyan-50'; ?> border">
                    In Production
                    <?php if ($statusMap['in_production'] ?? 0 > 0): ?>
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-cyan-500 text-white">
                        <?php echo $statusMap['in_production']; ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <a href="?status=completed<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors <?php echo $status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-white text-green-700 hover:bg-green-50'; ?> border">
                    Completed
                    <?php if ($statusMap['completed'] ?? 0 > 0): ?>
                    <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-green-500 text-white">
                        <?php echo $statusMap['completed']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Bulk Actions Bar (hidden by default) -->
        <div id="bulkActionsBar" class="hidden px-6 py-3 bg-blue-50 border-b border-blue-200">
            <div class="flex items-center justify-between">
                <div class="text-sm font-medium text-blue-700">
                    <span id="selectedCount">0</span> order(s) selected
                </div>
                <div class="flex gap-2">
                    <button onclick="bulkExport()" class="px-3 py-1.5 text-sm bg-white text-gray-700 rounded-md hover:bg-gray-50 border border-gray-300">
                        Export
                    </button>
                    <button onclick="bulkUpdateStatus()" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Update Status
                    </button>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="p-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No orders found</h3>
                <?php if ($search || $status !== 'all'): ?>
                    <p class="text-gray-600 mb-4">Try adjusting your filters to find what you're looking for.</p>
                    <div class="flex gap-2 justify-center">
                        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            Clear Filters
                        </a>
                        <a href="create.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Create Order
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 mb-4">Get started by creating your first customer order.</p>
                    <a href="create.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors inline-block">
                        Create Your First Order
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($orders as $order): ?>
                <div class="p-4 hover:bg-gray-50 transition-colors" data-order-id="<?php echo $order['id']; ?>">
                    <div class="flex items-start gap-4">
                        <!-- Checkbox -->
                        <div class="flex items-center pt-1">
                            <input type="checkbox" 
                                   class="order-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                   value="<?php echo $order['id']; ?>"
                                   onchange="updateBulkActions()">
                        </div>
                        
                        <!-- Order Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <a href="view.php?id=<?php echo $order['id']; ?>" 
                                       class="text-sm font-medium text-blue-600 hover:text-blue-700">
                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                    </a>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium text-white <?php echo getStatusColorClass($order['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                        <?php if ($order['days_until_due'] !== null && $order['status'] !== 'completed' && $order['status'] !== 'cancelled'): ?>
                                            <?php if ($order['days_until_due'] < 0): ?>
                                                <span class="text-xs text-red-600 font-medium">
                                                    Overdue by <?php echo abs($order['days_until_due']); ?> day<?php echo abs($order['days_until_due']) !== 1 ? 's' : ''; ?>
                                                </span>
                                            <?php elseif ($order['days_until_due'] === 0): ?>
                                                <span class="text-xs text-red-600 font-medium">Due Today</span>
                                            <?php elseif ($order['days_until_due'] <= 3): ?>
                                                <span class="text-xs text-yellow-600 font-medium">
                                                    Due in <?php echo $order['days_until_due']; ?> day<?php echo $order['days_until_due'] !== 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <h3 class="text-base font-medium text-gray-900 mb-2">
                                <?php echo htmlspecialchars($order['customer_name']); ?>
                            </h3>
                            
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600">
                                <span>Ordered: <?php echo $order['order_date_formatted']; ?></span>
                                <span class="<?php echo getUrgencyClass($order['days_until_due']); ?>">
                                    Required: <?php echo $order['required_date_formatted']; ?>
                                </span>
                                <span><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?></span>
                                <?php if ($order['total_amount']): ?>
                                    <span class="font-medium">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Metrics -->
                        <div class="hidden sm:flex flex-col items-end gap-2 text-sm">
                            <?php if ($order['priority'] ?? null): ?>
                            <div class="text-right">
                                <div class="text-gray-500 text-xs">Priority</div>
                                <div class="font-medium"><?php echo ucfirst($order['priority']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                            <a href="../mrp/run.php?order_id=<?php echo $order['id']; ?>" 
                               class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                               title="Run MRP Analysis">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </a>
                            <?php endif; ?>
                            
                            <button onclick="toggleActionMenu(event, <?php echo $order['id']; ?>)" 
                                    class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors action-menu-toggle">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                </svg>
                            </button>
                            
                            <!-- Action Menu -->
                            <div class="action-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                                <a href="view.php?id=<?php echo $order['id']; ?>" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    View Details
                                </a>
                                <a href="edit.php?id=<?php echo $order['id']; ?>" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Edit Order
                                </a>
                                <?php if ($order['status'] === 'pending' || $order['status'] === 'confirmed'): ?>
                                <a href="../production/create.php?order_id=<?php echo $order['id']; ?>" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Create Production Order
                                </a>
                                <?php endif; ?>
                                <hr class="my-1">
                                <button onclick="duplicateOrder(<?php echo $order['id']; ?>)" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    Duplicate
                                </button>
                                <?php if ($order['status'] === 'pending'): ?>
                                <button onclick="cancelOrder(<?php echo $order['id']; ?>)" 
                                        class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    Cancel Order
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Action menu positioning */
.action-menu-toggle {
    position: relative;
}

.action-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
}

/* Status badge in autocomplete */
.status-badge {
    display: inline-block;
    text-transform: capitalize;
}

/* Ensure menus aren't clipped */
.divide-y > div {
    position: relative;
    overflow: visible;
}

</style>

<script>
// Setup action menus with event delegation
function setupActionMenus() {
    document.addEventListener('click', function(e) {
        const toggleButton = e.target.closest('.action-menu-toggle');
        if (toggleButton) {
            e.preventDefault();
            e.stopPropagation();
            
            const menu = toggleButton.nextElementSibling;
            if (menu && menu.classList.contains('action-menu')) {
                // Close all other menus
                document.querySelectorAll('.action-menu').forEach(otherMenu => {
                    if (otherMenu !== menu) {
                        otherMenu.classList.add('hidden');
                    }
                });
                
                // Toggle this menu
                menu.classList.toggle('hidden');
            }
            return;
        }
        
        // Close all menus when clicking outside
        if (!e.target.closest('.action-menu')) {
            document.querySelectorAll('.action-menu').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
}

// Toggle action menu (fallback for inline onclick)
function toggleActionMenu(event, orderId) {
    event.stopPropagation();
    const button = event.currentTarget;
    const menu = button.nextElementSibling;
    
    // Close all other menus
    document.querySelectorAll('.action-menu').forEach(otherMenu => {
        if (otherMenu !== menu) {
            otherMenu.classList.add('hidden');
        }
    });
    
    // Toggle this menu
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Update bulk actions bar
function updateBulkActions() {
    const checkboxes = document.querySelectorAll('.order-checkbox:checked');
    const bulkBar = document.getElementById('bulkActionsBar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkBar.classList.remove('hidden');
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkBar.classList.add('hidden');
    }
}

// Bulk export function
function bulkExport() {
    const selected = Array.from(document.querySelectorAll('.order-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selected.length > 0) {
        window.location.href = 'export.php?ids=' + selected.join(',');
    }
}

// Bulk update status
function bulkUpdateStatus() {
    const selected = Array.from(document.querySelectorAll('.order-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selected.length > 0) {
        // Implement bulk status update modal/functionality
        alert('Bulk status update for orders: ' + selected.join(', '));
    }
}

// Duplicate order
function duplicateOrder(orderId) {
    if (confirm('Duplicate this order?')) {
        window.location.href = 'duplicate.php?id=' + orderId;
    }
}

// Cancel order
function cancelOrder(orderId) {
    if (confirm('Are you sure you want to cancel this order?')) {
        // Implement order cancellation
        window.location.href = 'cancel.php?id=' + orderId;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
});
</script>

<!-- Standardized Search Component -->
<?php echo getSearchComponentCSS(); ?>
<?php includeSearchComponentJS(); ?>

<script>
// AutocompleteManager will auto-initialize based on data-autocomplete-preset attribute
// No manual initialization needed - fully modular approach
document.addEventListener('DOMContentLoaded', function() {
    setupActionMenus();
    console.log('Orders page initialized with modular search system');
});
</script>

<?php
// Include footer without autocomplete scripts (already loaded above)
$include_autocomplete = false;
require_once '../../includes/footer-tailwind.php';
?>