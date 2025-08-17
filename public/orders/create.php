<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();

// Get products for order details
$products = $db->select("
    SELECT p.id, p.product_code, p.name, uom.code as uom_code
    FROM products p
    LEFT JOIN units_of_measure uom ON p.uom_id = uom.id
    WHERE p.is_active = 1 AND p.deleted_at IS NULL
    ORDER BY p.product_code
");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (empty($_POST['order_number'])) {
        $errors[] = 'Order number is required';
    }
    if (empty($_POST['customer_name'])) {
        $errors[] = 'Customer name is required';
    }
    if (empty($_POST['order_date'])) {
        $errors[] = 'Order date is required';
    }
    if (empty($_POST['required_date'])) {
        $errors[] = 'Required date is required';
    }
    if (empty($_POST['products']) || !is_array($_POST['products'])) {
        $errors[] = 'At least one product is required';
    }
    
    // Validate order details
    if (!empty($_POST['products'])) {
        foreach ($_POST['products'] as $index => $product) {
            if (empty($product['product_id'])) {
                $errors[] = "Product selection is required for item " . ($index + 1);
            }
            if (empty($product['quantity']) || $product['quantity'] <= 0) {
                $errors[] = "Valid quantity is required for item " . ($index + 1);
            }
        }
    }
    
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            // Create order header
            $sql = "INSERT INTO customer_orders 
                    (order_number, customer_name, order_date, required_date, notes, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')";
            
            $orderId = $db->insert($sql, [
                $_POST['order_number'],
                $_POST['customer_name'],
                $_POST['order_date'],
                $_POST['required_date'],
                $_POST['notes'] ?? null
            ], ['s', 's', 's', 's', 's']);
            
            // Create order details
            foreach ($_POST['products'] as $product) {
                if (!empty($product['product_id']) && !empty($product['quantity'])) {
                    $sql = "INSERT INTO customer_order_details 
                            (order_id, product_id, quantity, uom_id, unit_price, notes)
                            VALUES (?, ?, ?, ?, ?, ?)";
                    
                    // Get product UOM
                    $productInfo = $db->selectOne("SELECT uom_id, selling_price FROM products WHERE id = ?", [$product['product_id']], ['i']);
                    
                    $db->insert($sql, [
                        $orderId,
                        $product['product_id'],
                        $product['quantity'],
                        $productInfo['uom_id'],
                        $product['unit_price'] ?? $productInfo['selling_price'] ?? 0,
                        $product['notes'] ?? null
                    ], ['i', 'i', 'd', 'i', 'd', 's']);
                }
            }
            
            $db->commit();
            $_SESSION['success'] = 'Customer order created successfully';
            header('Location: ../mrp/run.php?order_id=' . $orderId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error creating order: ' . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Create Customer Order
            <div style="float: right;">
                <a href="../" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin: 0; padding-left: 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-validate data-loading>
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="order_number">Order Number *</label>
                    <input type="text" id="order_number" name="order_number" required 
                           value="<?php echo htmlspecialchars($_POST['order_number'] ?? 'ORD-' . date('Ymd') . '-001'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="customer_name">Customer Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required 
                           value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="order_date">Order Date *</label>
                    <input type="date" id="order_date" name="order_date" required 
                           value="<?php echo $_POST['order_date'] ?? date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="required_date">Required Date *</label>
                    <input type="date" id="required_date" name="required_date" required 
                           value="<?php echo $_POST['required_date'] ?? date('Y-m-d', strtotime('+7 days')); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
            
            <h4>Order Items</h4>
            <div id="order-items">
                <?php 
                $productItems = $_POST['products'] ?? [['product_id' => '', 'quantity' => '', 'unit_price' => '', 'notes' => '']];
                foreach ($productItems as $index => $item): 
                ?>
                <div class="order-item" style="border: 1px solid var(--border-color); padding: 1rem; margin-bottom: 1rem; border-radius: 0.25rem;">
                    <div class="grid grid-4">
                        <div class="form-group">
                            <label>Product *</label>
                            <select name="products[<?php echo $index; ?>][product_id]" required>
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            <?php echo ($item['product_id'] ?? '') == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="products[<?php echo $index; ?>][quantity]" step="0.01" min="0.01" required
                                   value="<?php echo htmlspecialchars($item['quantity'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Unit Price</label>
                            <input type="number" name="products[<?php echo $index; ?>][unit_price]" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($item['unit_price'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Notes</label>
                            <input type="text" name="products[<?php echo $index; ?>][notes]"
                                   value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <?php if ($index > 0): ?>
                        <button type="button" class="btn btn-danger btn-sm remove-item">Remove Item</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="btn-group">
                <button type="button" id="add-item" class="btn btn-secondary">Add Another Item</button>
            </div>
            
            <div class="btn-group mt-3">
                <button type="submit" class="btn btn-primary">Create Order & Run MRP</button>
                <a href="../" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemIndex = <?php echo count($productItems ?? [1]); ?>;
    
    document.getElementById('add-item').addEventListener('click', function() {
        const container = document.getElementById('order-items');
        const newItem = document.querySelector('.order-item').cloneNode(true);
        
        // Update field names and clear values
        const inputs = newItem.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, '[' + itemIndex + ']'));
                if (input.type !== 'button') {
                    input.value = '';
                }
            }
        });
        
        // Add remove button if not present
        if (!newItem.querySelector('.remove-item')) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger btn-sm remove-item';
            removeBtn.textContent = 'Remove Item';
            newItem.appendChild(removeBtn);
        }
        
        container.appendChild(newItem);
        itemIndex++;
    });
    
    // Remove item functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item')) {
            e.target.closest('.order-item').remove();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>