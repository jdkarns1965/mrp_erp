<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/tailwind-form-components.php';
require_once '../../classes/Inventory.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';

$inventoryModel = new Inventory();
$materialModel = new Material();
$db = Database::getInstance();

// Get lookup data
$materials = $materialModel->getAllActive();
$locations = $db->select("SELECT * FROM storage_locations ORDER BY code");
$suppliers = $db->select("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");
$uoms = $db->select("SELECT * FROM units_of_measure ORDER BY code");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['item_type', 'item_id', 'quantity', 'uom_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Validate quantity is positive
    if (!empty($_POST['quantity']) && $_POST['quantity'] <= 0) {
        $errors[] = 'Quantity must be greater than 0';
    }
    
    if (empty($errors)) {
        try {
            $data = [
                'item_type' => $_POST['item_type'],
                'item_id' => $_POST['item_id'],
                'lot_number' => $_POST['lot_number'] ?: date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'location_id' => !empty($_POST['location_id']) ? $_POST['location_id'] : null,
                'quantity' => (float)$_POST['quantity'],
                'reserved_quantity' => 0,
                'uom_id' => $_POST['uom_id'],
                'manufacture_date' => !empty($_POST['manufacture_date']) ? $_POST['manufacture_date'] : null,
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'received_date' => date('Y-m-d H:i:s'),
                'supplier_id' => !empty($_POST['supplier_id']) ? $_POST['supplier_id'] : null,
                'po_number' => $_POST['po_number'] ?: null,
                'unit_cost' => !empty($_POST['unit_cost']) ? (float)$_POST['unit_cost'] : 0,
                'status' => 'available',
                'reference_type' => 'Receipt',
                'notes' => $_POST['notes'] ?: null,
                'performed_by' => 'User' // In a real system, this would be the logged-in user
            ];
            
            $transactionId = $inventoryModel->receiveInventory($data);
            $_SESSION['success'] = 'Inventory received successfully';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error receiving inventory: ' . $e->getMessage();
        }
    }
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            Receive Inventory
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
                    <select id="item_type" name="item_type" required onchange="updateItemOptions()">
                        <option value="">Select Item Type</option>
                        <option value="material" <?php echo ($_POST['item_type'] ?? '') === 'material' ? 'selected' : ''; ?>>Material</option>
                        <option value="product" <?php echo ($_POST['item_type'] ?? '') === 'product' ? 'selected' : ''; ?>>Product</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="item_id">Item *</label>
                    <select id="item_id" name="item_id" required>
                        <option value="">Select Item</option>
                        <?php foreach ($materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>" 
                                    data-type="material"
                                    data-uom="<?php echo $material['uom_id']; ?>"
                                    <?php echo ($_POST['item_id'] ?? '') == $material['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="lot_number">Lot Number</label>
                    <input type="text" id="lot_number" name="lot_number" 
                           placeholder="Auto-generated if empty"
                           value="<?php echo htmlspecialchars($_POST['lot_number'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" required
                           value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="uom_id">Unit of Measure *</label>
                    <select id="uom_id" name="uom_id" required>
                        <option value="">Select UOM</option>
                        <?php foreach ($uoms as $uom): ?>
                            <option value="<?php echo $uom['id']; ?>" 
                                    <?php echo ($_POST['uom_id'] ?? '') == $uom['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($uom['code'] . ' - ' . $uom['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="location_id">Storage Location</label>
                    <select id="location_id" name="location_id">
                        <option value="">Select Location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo $location['id']; ?>" 
                                    <?php echo ($_POST['location_id'] ?? '') == $location['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['code'] . ' - ' . $location['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="supplier_id">Supplier</label>
                    <select id="supplier_id" name="supplier_id">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo ($_POST['supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="manufacture_date">Manufacture Date</label>
                    <input type="date" id="manufacture_date" name="manufacture_date"
                           value="<?php echo htmlspecialchars($_POST['manufacture_date'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="expiry_date">Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date"
                           value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="unit_cost">Unit Cost</label>
                    <input type="number" id="unit_cost" name="unit_cost" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['unit_cost'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="po_number">PO Number</label>
                <input type="text" id="po_number" name="po_number"
                       value="<?php echo htmlspecialchars($_POST['po_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Receive Inventory</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function updateItemOptions() {
    const itemType = document.getElementById('item_type').value;
    const itemSelect = document.getElementById('item_id');
    const options = itemSelect.querySelectorAll('option[data-type]');
    
    // Reset selection
    itemSelect.value = '';
    
    // Show/hide options based on type
    options.forEach(option => {
        if (itemType === '' || option.dataset.type === itemType) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
}

// Auto-select UOM when item is selected
document.getElementById('item_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.dataset.uom) {
        document.getElementById('uom_id').value = selectedOption.dataset.uom;
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateItemOptions();
});
</script>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>