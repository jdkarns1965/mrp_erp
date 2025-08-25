<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Inventory.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';

$inventoryModel = new Inventory();
$materialModel = new Material();
$db = Database::getInstance();

// Get lookup data
$materials = $materialModel->getAllActive();

$errors = [];
$availableStock = [];
$selectedMaterial = null;

// If material is selected, get available stock
if (!empty($_GET['material_id']) || !empty($_POST['item_id'])) {
    $materialId = $_GET['material_id'] ?? $_POST['item_id'];
    $selectedMaterial = $materialModel->findWithDetails($materialId);
    if ($selectedMaterial) {
        $availableStock = $inventoryModel->getCurrentStock('material', $materialId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['item_type', 'item_id', 'quantity'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Validate quantity is positive
    if (!empty($_POST['quantity']) && $_POST['quantity'] <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    // Check if sufficient stock is available
    if (!empty($_POST['item_type']) && !empty($_POST['item_id']) && !empty($_POST['quantity'])) {
        $availableQty = $inventoryModel->getAvailableQuantity($_POST['item_type'], $_POST['item_id']);
        if ($availableQty < (float)$_POST['quantity']) {
            $errors[] = "Insufficient stock. Available: {$availableQty}, Requested: {$_POST['quantity']}";
        }
    }
    
    if (empty($errors)) {
        try {
            $options = [
                'reference_type' => $_POST['reference_type'] ?: 'Manual Issue',
                'reference_number' => $_POST['reference_number'] ?: null,
                'notes' => $_POST['notes'] ?: null,
                'performed_by' => 'User', // In a real system, this would be the logged-in user
                'location_id' => !empty($_POST['location_id']) ? $_POST['location_id'] : null
            ];
            
            $success = $inventoryModel->issueInventory(
                $_POST['item_type'],
                $_POST['item_id'],
                (float)$_POST['quantity'],
                $options
            );
            
            if ($success) {
                $_SESSION['success'] = 'Inventory issued successfully';
                header('Location: index.php');
                exit;
            }
            
        } catch (Exception $e) {
            $errors[] = 'Error issuing inventory: ' . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Issue Inventory
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">Back to Inventory</a>
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
                    <label for="item_type">Item Type *</label>
                    <select id="item_type" name="item_type" required onchange="this.form.submit()">
                        <option value="">Select Item Type</option>
                        <option value="material" <?php echo ($_POST['item_type'] ?? 'material') === 'material' ? 'selected' : ''; ?>>Material</option>
                        <option value="product" <?php echo ($_POST['item_type'] ?? '') === 'product' ? 'selected' : ''; ?>>Product</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select id="item_id" name="item_id" required onchange="this.form.submit()">
                        <option value="">Select Item</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>" 
                                    <?php echo ($_POST['item_id'] ?? $_GET['material_id'] ?? '') == $material['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <?php if ($selectedMaterial): ?>
                <div class="alert alert-info">
                    <strong>Selected:</strong> <?php echo htmlspecialchars($selectedMaterial['material_code'] . ' - ' . $selectedMaterial['name']); ?><br>
                    <strong>Available Quantity:</strong> <?php echo number_format($inventoryModel->getAvailableQuantity('material', $selectedMaterial['id']), 2); ?> <?php echo htmlspecialchars($selectedMaterial['uom_code']); ?>
                </div>
                
                <?php if (!empty($availableStock)): ?>
                    <div class="card" style="margin-bottom: 1rem;">
                        <div class="card-header">Available Stock Lots</div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Lot Number</th>
                                        <th>Location</th>
                                        <th>Quantity</th>
                                        <th>Available</th>
                                        <th>Expiry Date</th>
                                        <th>Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availableStock as $stock): ?>
                                        <?php 
                                        $available = $stock['quantity'] - $stock['reserved_quantity'];
                                        $isExpiring = $stock['expiry_date'] && strtotime($stock['expiry_date']) <= strtotime('+30 days');
                                        ?>
                                        <tr style="<?php echo $isExpiring ? 'background-color: #fff3e0;' : ''; ?>">
                                            <td><?php echo htmlspecialchars($stock['lot_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($stock['location_code'] ?? 'N/A'); ?></td>
                                            <td><?php echo number_format($stock['quantity'], 2); ?></td>
                                            <td><?php echo number_format($available, 2); ?></td>
                                            <td>
                                                <?php if ($stock['expiry_date']): ?>
                                                    <span style="color: <?php echo $isExpiring ? '#f39c12' : '#333'; ?>;">
                                                        <?php echo date('M j, Y', strtotime($stock['expiry_date'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #999;">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($stock['supplier_name'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="quantity">Quantity to Issue *</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" required
                           value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reference_type">Reference Type</label>
                    <select id="reference_type" name="reference_type">
                        <option value="Manual Issue" <?php echo ($_POST['reference_type'] ?? '') === 'Manual Issue' ? 'selected' : ''; ?>>Manual Issue</option>
                        <option value="Production" <?php echo ($_POST['reference_type'] ?? '') === 'Production' ? 'selected' : ''; ?>>Production Order</option>
                        <option value="Transfer" <?php echo ($_POST['reference_type'] ?? '') === 'Transfer' ? 'selected' : ''; ?>>Transfer</option>
                        <option value="Adjustment" <?php echo ($_POST['reference_type'] ?? '') === 'Adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                        <option value="Scrap" <?php echo ($_POST['reference_type'] ?? '') === 'Scrap' ? 'selected' : ''; ?>>Scrap/Waste</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="reference_number">Reference Number</label>
                <input type="text" id="reference_number" name="reference_number" 
                       placeholder="Work order, transfer ID, etc."
                       value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3" 
                          placeholder="Reason for issue, additional details..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Issue Inventory</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-submit form when item selection changes to show stock info
document.addEventListener('DOMContentLoaded', function() {
    const itemTypeSelect = document.getElementById('item_type');
    const itemSelect = document.getElementById('item_id');
    
    // If both are selected, enable quantity input
    function toggleQuantityInput() {
        const quantityInput = document.getElementById('quantity');
        if (itemTypeSelect.value && itemSelect.value) {
            quantityInput.removeAttribute('disabled');
        } else {
            quantityInput.setAttribute('disabled', 'disabled');
        }
    }
    
    itemTypeSelect.addEventListener('change', toggleQuantityInput);
    itemSelect.addEventListener('change', toggleQuantityInput);
    
    // Initial state
    toggleQuantityInput();
});
</script>

<?php require_once '../../includes/footer.php'; ?>