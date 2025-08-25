<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/MRP.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$mrpEngine = new MRP();

$orderId = $_GET['order_id'] ?? null;
$results = null;
$errors = [];

// Get available orders with product details
$orders = $db->select("
    SELECT 
        co.id,
        co.order_number,
        co.customer_name,
        co.order_date,
        co.required_date,
        COUNT(cod.id) as item_count,
        GROUP_CONCAT(DISTINCT p.product_code ORDER BY p.product_code SEPARATOR ', ') as product_codes,
        SUM(cod.quantity) as total_quantity
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id
    JOIN products p ON cod.product_id = p.id
    WHERE co.status IN ('pending', 'confirmed')
    GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date
    ORDER BY co.required_date ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId = $_POST['order_id'];
    
    try {
        $results = $mrpEngine->runMRP($orderId);
        
        if ($results['success']) {
            $_SESSION['success'] = 'MRP calculation completed successfully';
        } else {
            $errors[] = $results['error'];
        }
        
    } catch (Exception $e) {
        $errors[] = 'Error running MRP: ' . $e->getMessage();
    }
}

// If order_id is provided in URL, get order details
$selectedOrder = null;
if ($orderId) {
    $selectedOrder = $db->selectOne("
        SELECT co.*, COUNT(cod.id) as item_count
        FROM customer_orders co
        JOIN customer_order_details cod ON co.id = cod.order_id
        WHERE co.id = ?
        GROUP BY co.id
    ", [$orderId], ['i']);
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" style="padding-top: 2rem;">
    <!-- Page Header -->
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Run MRP Calculation</h1>
                    <p class="mt-1 text-sm text-gray-600">Execute Material Requirements Planning for a specific customer order</p>
                </div>
                <div class="flex gap-3">
                    <a href="index.php" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to MRP
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="px-6 py-4 bg-red-50 border-b border-red-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">MRP Calculation Error</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <ul class="list-disc pl-5 space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <div class="px-6 py-4">
            <?php if (empty($orders)): ?>
            <!-- Empty State -->
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No pending orders available</h3>
                <p class="mt-1 text-sm text-gray-500">
                    No pending customer orders found for MRP calculation.<br>
                    <a href="../orders/create.php" class="text-blue-600 hover:text-blue-500">Create a new order</a> to get started with MRP.
                </p>
                <div class="mt-6">
                    <a href="../orders/create.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Create Customer Order
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Order Selection Form -->
            <form method="POST" class="space-y-6">
                <div class="space-y-4">
                    <div>
                        <label for="order_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Order to Analyze <span class="text-red-500">*</span>
                        </label>
                        <select id="order_id" name="order_id" required class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <option value="">Choose an order to analyze...</option>
                            <?php foreach ($orders as $order): ?>
                                <option value="<?php echo $order['id']; ?>" 
                                        <?php echo $orderId == $order['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($order['customer_name'] . ' - Parts: ' . $order['product_codes'] . ' (Qty: ' . number_format($order['total_quantity']) . ') - Due: ' . $order['required_date']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select a pending customer order to calculate material requirements</p>
                    </div>
                
                    <?php if ($selectedOrder): ?>
                    <!-- Selected Order Details -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-blue-800 mb-2">Selected Order Details</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-blue-700">
                                    <div>
                                        <span class="font-medium">Order Number:</span> <?php echo htmlspecialchars($selectedOrder['order_number']); ?>
                                    </div>
                                    <div>
                                        <span class="font-medium">Customer:</span> <?php echo htmlspecialchars($selectedOrder['customer_name']); ?>
                                    </div>
                                    <div>
                                        <span class="font-medium">Required Date:</span> <?php echo date('M j, Y', strtotime($selectedOrder['required_date'])); ?>
                                    </div>
                                    <div>
                                        <span class="font-medium">Items:</span> <?php echo $selectedOrder['item_count']; ?> products
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 flex-1 sm:flex-initial">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Run MRP Calculation
                    </button>
                    <a href="index.php" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Cancel
                    </a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- MRP Results -->
    <?php if ($results && $results['success']): ?>
    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">MRP Calculation Results</h2>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    Completed
                </span>
            </div>
        </div>
            
        <div class="px-6 py-4">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-gray-900"><?php echo $results['summary']['total_materials']; ?></div>
                    <div class="text-sm text-gray-600 mt-1">Total Materials</div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-700"><?php echo $results['summary']['materials_with_shortage']; ?></div>
                    <div class="text-sm text-yellow-600 mt-1">Materials with Shortage</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-700">$<?php echo number_format($results['summary']['total_purchase_cost'], 2); ?></div>
                    <div class="text-sm text-blue-600 mt-1">Total Purchase Cost</div>
                </div>
                <div class="bg-red-50 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-red-700"><?php echo $results['summary']['urgent_orders']; ?></div>
                    <div class="text-sm text-red-600 mt-1">Urgent Orders</div>
                </div>
            </div>
            
            <!-- Fulfillment Status -->
            <?php if ($results['summary']['can_fulfill']): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Order can be fulfilled!</h3>
                        <p class="text-sm text-green-700 mt-1">All required materials are available in current inventory.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Material shortages identified</h3>
                        <p class="text-sm text-yellow-700 mt-1">Purchase orders are required to fulfill this order completely.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Material Requirements Table -->
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Material</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Gross Required</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Available</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Net Required</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Suggested Order</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Order Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Lead Time</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Total Cost</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results['requirements'] as $req): ?>
                        <tr class="<?php echo $req['net_requirement'] > 0 ? 'bg-yellow-50' : 'hover:bg-gray-50'; ?>">
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex flex-col">
                                    <div class="text-sm font-medium text-gray-900 font-mono"><?php echo htmlspecialchars($req['material_code']); ?></div>
                                    <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars($req['material_name']); ?></div>
                                </div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($req['gross_requirement'], 2); ?> <span class="text-gray-500"><?php echo $req['uom_code']; ?></span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo number_format($req['available_stock'], 2); ?> <span class="text-gray-500"><?php echo $req['uom_code']; ?></span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <?php if ($req['net_requirement'] > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <?php echo number_format($req['net_requirement'], 2); ?> <?php echo $req['uom_code']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Complete
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $req['suggested_order_qty'] > 0 ? number_format($req['suggested_order_qty'], 2) . ' ' . $req['uom_code'] : '-'; ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $req['suggested_order_date'] ? date('M j, Y', strtotime($req['suggested_order_date'])) : '-'; ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $req['lead_time_days']; ?> days
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                $<?php echo number_format($req['total_cost'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200 mt-6">
                <a href="results.php?order_id=<?php echo $orderId; ?>" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 flex-1 sm:flex-initial">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    View Detailed Results
                </a>
                <a href="purchase_orders.php?order_id=<?php echo $orderId; ?>" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200 flex-1 sm:flex-initial">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M8 11v6a2 2 0 002 2h4a2 2 0 002-2v-6M8 11h8"></path>
                    </svg>
                    Generate Purchase Orders
                </a>
                <a href="index.php" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to MRP
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Include CSS for modern MRP styling -->
<link rel="stylesheet" href="../css/materials-modern.css">

<script>
// Form enhancements
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    const submitButton = form?.querySelector('button[type="submit"]');
    const orderSelect = document.getElementById('order_id');
    
    if (form && submitButton && orderSelect) {
        // Add loading state on form submission
        form.addEventListener('submit', function() {
            submitButton.disabled = true;
            submitButton.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Calculating MRP...
            `;
        });
        
        // Enable submit button only when order is selected
        function updateSubmitButton() {
            if (orderSelect.value) {
                submitButton.disabled = false;
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        orderSelect.addEventListener('change', updateSubmitButton);
        updateSubmitButton(); // Initial state
    }
    
    // Mobile touch enhancements
    if (window.innerWidth <= 768) {
        // Add touch feedback for action buttons
        const actionButtons = document.querySelectorAll('a[href], button');
        actionButtons.forEach(button => {
            button.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.95)';
            });
            button.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });
        
        // Add haptic feedback for mobile (if supported)
        const addHapticFeedback = (element) => {
            element.addEventListener('click', function() {
                if (navigator.vibrate) {
                    navigator.vibrate(10); // Very short haptic feedback
                }
            });
        };
        
        actionButtons.forEach(addHapticFeedback);
    }
    
    console.log('MRP run page initialized');
});
</script>

<?php
$include_autocomplete = false; // No autocomplete needed on this page
require_once '../../includes/footer-tailwind.php';
?>