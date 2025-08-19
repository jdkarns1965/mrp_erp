<?php
/**
 * Create Production Order from Customer Order
 * Phase 2: Production order creation interface
 */

session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';
require_once '../../classes/ProductionScheduler.php';

$db = Database::getInstance();
$scheduler = new ProductionScheduler();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customerOrderId = $_POST['customer_order_id'] ?? null;
        $priority = $_POST['priority'] ?? 'normal';
        $createdBy = $_POST['created_by'] ?? 'User';
        $schedulingType = $_POST['scheduling_type'] ?? 'none';
        $startDate = $_POST['start_date'] ?? null;
        
        if (!$customerOrderId) {
            throw new Exception('Customer order is required');
        }
        
        // Create production orders
        $result = $scheduler->createProductionOrders($customerOrderId, [
            'priority' => $priority,
            'created_by' => $createdBy
        ]);
        
        if (!$result['success']) {
            throw new Exception($result['error']);
        }
        
        $productionOrderIds = $result['production_order_ids'];
        
        // Handle scheduling if requested
        if ($schedulingType !== 'none' && !empty($productionOrderIds)) {
            if ($schedulingType === 'forward' && $startDate) {
                $scheduleResult = $scheduler->forwardSchedule($productionOrderIds, new DateTime($startDate));
            } elseif ($schedulingType === 'backward') {
                // Get customer required date
                $customerOrder = $db->select("SELECT required_date FROM customer_orders WHERE id = ?", [$customerOrderId]);
                if (!empty($customerOrder)) {
                    $scheduleResult = $scheduler->backwardSchedule($productionOrderIds, new DateTime($customerOrder[0]['required_date']));
                }
            }
            
            if (isset($scheduleResult) && !$scheduleResult['success']) {
                $error = "Production orders created but scheduling failed: " . $scheduleResult['error'];
            }
        }
        
        if (empty($error)) {
            $success = $result['message'];
            if ($schedulingType !== 'none') {
                $success .= " and scheduled successfully";
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get available customer orders (confirmed orders without production orders)
$availableOrders = $db->select("
    SELECT 
        co.id,
        co.order_number,
        co.customer_name,
        co.order_date,
        co.required_date,
        co.status,
        COUNT(cod.id) as item_count,
        SUM(cod.quantity * cod.unit_price) as order_value
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id
    LEFT JOIN production_orders po ON co.id = po.customer_order_id
    WHERE co.status IN ('confirmed', 'pending') AND po.id IS NULL
    GROUP BY co.id, co.order_number, co.customer_name, co.order_date, co.required_date, co.status
    ORDER BY co.required_date ASC, co.order_date ASC
");

?>

<style>
        .form-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        
        .order-preview {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 1rem;
            color: #111827;
            font-weight: 600;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .items-table th,
        .items-table td {
            text-align: left;
            padding: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .items-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        
        .scheduling-options {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #fef3c7;
            border-radius: 6px;
            border-left: 4px solid #f59e0b;
        }
        
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .hidden {
            display: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
    </style>

<div class="container">
    <div class="card">
        <div class="card-header">
            Create Production Order
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">← Back to Production Orders</a>
            </div>
            <div style="clear: both;"></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>Success:</strong> <?= htmlspecialchars($success) ?>
                <a href="index.php" class="btn btn-primary" style="margin-left: 1rem;">View Production Orders</a>
            </div>
        <?php endif; ?>

        <form method="POST" class="form">
            <!-- Customer Order Selection -->
            <div class="form-section">
                <h2 class="section-title">1. Select Customer Order</h2>
                
                <?php if (empty($availableOrders)): ?>
                    <div class="alert alert-error">
                        No customer orders available for production. 
                        <a href="../orders/create.php">Create a customer order first</a>.
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="customer_order_id">Customer Order *</label>
                        <select 
                            id="customer_order_id" 
                            name="customer_order_id" 
                            required 
                            onchange="loadOrderDetails(this.value)">
                            <option value="">Select a customer order...</option>
                            <?php foreach ($availableOrders as $order): ?>
                                <option value="<?= $order['id'] ?>" 
                                        data-order='<?= json_encode($order) ?>'>
                                    <?= htmlspecialchars($order['order_number']) ?> - 
                                    <?= htmlspecialchars($order['customer_name']) ?> 
                                    (Due: <?= date('M j, Y', strtotime($order['required_date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="orderPreview" class="order-preview hidden">
                        <div class="order-details">
                            <div class="detail-item">
                                <span class="detail-label">Customer</span>
                                <span class="detail-value" id="previewCustomer">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Order Date</span>
                                <span class="detail-value" id="previewOrderDate">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Required Date</span>
                                <span class="detail-value" id="previewRequiredDate">-</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Order Value</span>
                                <span class="detail-value" id="previewOrderValue">-</span>
                            </div>
                        </div>
                        
                        <div id="orderItems"></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Production Order Settings -->
            <div class="form-section">
                <h2 class="section-title">2. Production Order Settings</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority Level</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="created_by">Created By</label>
                        <input 
                            type="text" 
                            id="created_by" 
                            name="created_by" 
                            value="Production Manager"
                            required>
                    </div>
                </div>
            </div>

            <!-- Scheduling Options -->
            <div class="form-section">
                <h2 class="section-title">3. Scheduling Options</h2>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="scheduleNow" onchange="toggleScheduling()">
                        Schedule production orders immediately
                    </label>
                </div>
                
                <div id="schedulingOptions" class="scheduling-options">
                    <p><strong>Scheduling Method:</strong></p>
                    <div class="radio-group">
                        <label class="radio-item">
                            <input type="radio" name="scheduling_type" value="forward" checked>
                            <span>Forward Scheduling - Start as soon as possible</span>
                        </label>
                        <label class="radio-item">
                            <input type="radio" name="scheduling_type" value="backward">
                            <span>Backward Scheduling - Work backward from customer due date</span>
                        </label>
                    </div>
                    
                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="start_date">Earliest Start Date (for forward scheduling)</label>
                        <input 
                            type="date" 
                            id="start_date" 
                            name="start_date" 
                            value="<?= date('Y-m-d') ?>"
                            min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280;">
                        <strong>Note:</strong> Scheduling will consider work center capacity and availability. 
                        If scheduling fails, production orders will still be created but will need manual scheduling.
                    </p>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= empty($availableOrders) ? 'disabled' : '' ?>>
                    Create Production Order(s)
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
        function loadOrderDetails(orderId) {
            const select = document.getElementById('customer_order_id');
            const preview = document.getElementById('orderPreview');
            
            if (!orderId) {
                preview.classList.add('hidden');
                return;
            }
            
            const selectedOption = select.querySelector(`option[value="${orderId}"]`);
            if (!selectedOption) return;
            
            const orderData = JSON.parse(selectedOption.dataset.order);
            
            // Update preview details
            document.getElementById('previewCustomer').textContent = orderData.customer_name;
            document.getElementById('previewOrderDate').textContent = 
                new Date(orderData.order_date).toLocaleDateString();
            document.getElementById('previewRequiredDate').textContent = 
                new Date(orderData.required_date).toLocaleDateString();
            document.getElementById('previewOrderValue').textContent = 
                '$' + parseFloat(orderData.order_value).toLocaleString('en-US', {minimumFractionDigits: 2});
            
            // Load order items via AJAX
            fetch(`get-order-details.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderItems(data.items);
                    }
                })
                .catch(error => {
                    console.error('Error loading order details:', error);
                });
            
            preview.classList.remove('hidden');
        }
        
        function displayOrderItems(items) {
            const container = document.getElementById('orderItems');
            
            if (items.length === 0) {
                container.innerHTML = '<p>No items found for this order.</p>';
                return;
            }
            
            let html = `
                <h4 style="margin-top: 1rem;">Order Items</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            items.forEach(item => {
                const total = item.quantity * item.unit_price;
                html += `
                    <tr>
                        <td>
                            <strong>${item.product_code}</strong><br>
                            <small style="color: #6b7280;">${item.product_name}</small>
                        </td>
                        <td>${parseFloat(item.quantity).toLocaleString()} ${item.uom_code}</td>
                        <td>$${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td>$${total.toFixed(2)}</td>
                    </tr>
                `;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        }
        
        function toggleScheduling() {
            const checkbox = document.getElementById('scheduleNow');
            const options = document.getElementById('schedulingOptions');
            
            if (checkbox.checked) {
                options.style.display = 'block';
                // Set scheduling_type to none if not scheduling
                document.querySelector('input[name="scheduling_type"][value="forward"]').checked = true;
            } else {
                options.style.display = 'none';
                // Add hidden input to indicate no scheduling
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'scheduling_type';
                hiddenInput.value = 'none';
                options.appendChild(hiddenInput);
            }
        }
        
        // Initialize with no scheduling
        document.addEventListener('DOMContentLoaded', function() {
            toggleScheduling();
        });
    </script>

<?php require_once '../../includes/footer.php'; ?>