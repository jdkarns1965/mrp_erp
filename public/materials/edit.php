<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';

$materialModel = new Material();
$db = Database::getInstance();

// Get material ID from URL
$materialId = $_GET['id'] ?? null;
if (!$materialId) {
    $_SESSION['error'] = 'Material ID is required';
    header('Location: index.php');
    exit;
}

// Get material data
$material = $materialModel->findWithDetails($materialId);
if (!$material) {
    $_SESSION['error'] = 'Material not found';
    header('Location: index.php');
    exit;
}

// Get lookup data
$categories = $db->select("SELECT * FROM material_categories ORDER BY name");
$uoms = $db->select("SELECT * FROM units_of_measure ORDER BY code");
$suppliers = $db->select("SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required = ['material_code', 'name', 'material_type', 'uom_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Check if material code already exists (excluding current material)
    if (!empty($_POST['material_code']) && $_POST['material_code'] !== $material['material_code']) {
        if ($materialModel->codeExists($_POST['material_code'])) {
            $errors[] = 'Material code already exists';
        }
    }
    
    if (empty($errors)) {
        try {
            $data = [
                'material_code' => $_POST['material_code'],
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? null,
                'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
                'material_type' => $_POST['material_type'],
                'uom_id' => $_POST['uom_id'],
                'min_stock_qty' => $_POST['min_stock_qty'] ?? 0,
                'max_stock_qty' => $_POST['max_stock_qty'] ?? 0,
                'reorder_point' => $_POST['reorder_point'] ?? 0,
                'lead_time_days' => $_POST['lead_time_days'] ?? 0,
                'default_supplier_id' => !empty($_POST['default_supplier_id']) ? $_POST['default_supplier_id'] : null,
                'cost_per_unit' => $_POST['cost_per_unit'] ?? 0,
                'supplier_moq' => $_POST['supplier_moq'] ?? 0,
                'is_lot_controlled' => isset($_POST['is_lot_controlled']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $materialModel->update($materialId, $data);
            $_SESSION['success'] = 'Material updated successfully';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error updating material: ' . $e->getMessage();
        }
    }
}

// Use POST data if available, otherwise use existing material data
$formData = $_POST ?: $material;
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Edit Material: <?php echo htmlspecialchars($material['material_code']); ?>
            <div style="float: right;">
                <a href="index.php" class="btn btn-secondary">Back to Materials</a>
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
                    <label for="material_code">
                        Material Code *
                        <span class="field-help" data-help="Unique identifier for this material (e.g., RES001, PKG045)" tabindex="0"></span>
                    </label>
                    <input type="text" id="material_code" name="material_code" required 
                           value="<?php echo htmlspecialchars($formData['material_code'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="material_type">
                        Material Type *
                        <span class="field-help" data-help="Classification of material (resin, insert, packaging, etc.)" tabindex="0"></span>
                    </label>
                    <select id="material_type" name="material_type" required>
                        <option value="">Select Type</option>
                        <option value="resin" <?php echo ($formData['material_type'] ?? '') === 'resin' ? 'selected' : ''; ?>>Resin</option>
                        <option value="insert" <?php echo ($formData['material_type'] ?? '') === 'insert' ? 'selected' : ''; ?>>Insert</option>
                        <option value="packaging" <?php echo ($formData['material_type'] ?? '') === 'packaging' ? 'selected' : ''; ?>>Packaging</option>
                        <option value="component" <?php echo ($formData['material_type'] ?? '') === 'component' ? 'selected' : ''; ?>>Component</option>
                        <option value="consumable" <?php echo ($formData['material_type'] ?? '') === 'consumable' ? 'selected' : ''; ?>>Consumable</option>
                        <option value="other" <?php echo ($formData['material_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="uom_id">
                        Unit of Measure *
                        <span class="field-help" data-help="How this material is counted/measured (kg, lbs, pieces, etc.)" tabindex="0"></span>
                    </label>
                    <select id="uom_id" name="uom_id" required>
                        <option value="">Select UOM</option>
                        <?php foreach ($uoms as $uom): ?>
                            <option value="<?php echo $uom['id']; ?>" 
                                    <?php echo ($formData['uom_id'] ?? '') == $uom['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($uom['code'] . ' - ' . $uom['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="min_stock_qty">
                        Minimum Stock
                        <span class="field-help" data-help="Lowest acceptable inventory level before ordering" tabindex="0"></span>
                    </label>
                    <input type="number" id="min_stock_qty" name="min_stock_qty" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($formData['min_stock_qty'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_stock_qty">
                        Maximum Stock
                        <span class="field-help" data-help="Maximum inventory level to avoid overstocking and waste" tabindex="0"></span>
                    </label>
                    <input type="number" id="max_stock_qty" name="max_stock_qty" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($formData['max_stock_qty'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reorder_point">
                        Reorder Point
                        <span class="field-help" data-help="Stock level that triggers automatic purchase order generation" tabindex="0"></span>
                    </label>
                    <input type="number" id="reorder_point" name="reorder_point" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($formData['reorder_point'] ?? '0'); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="cost_per_unit">Cost per Unit</label>
                    <input type="number" id="cost_per_unit" name="cost_per_unit" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($formData['cost_per_unit'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="supplier_moq">
                        Supplier Min Order Qty (MOQ)
                        <span class="field-help" data-help="Minimum quantity supplier requires for orders" tabindex="0"></span>
                    </label>
                    <input type="number" id="supplier_moq" name="supplier_moq" step="0.01" min="0"
                           placeholder="Minimum qty supplier will sell"
                           value="<?php echo htmlspecialchars($formData['supplier_moq'] ?? '0'); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="lead_time_days">
                        Lead Time (Days)
                        <span class="field-help" data-help="Time between placing order and receiving material from supplier" tabindex="0"></span>
                    </label>
                    <input type="number" id="lead_time_days" name="lead_time_days" min="0"
                           value="<?php echo htmlspecialchars($formData['lead_time_days'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="default_supplier_id">Default Supplier</label>
                    <select id="default_supplier_id" name="default_supplier_id">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo ($formData['default_supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_lot_controlled" value="1" 
                               <?php echo ($formData['is_lot_controlled'] ?? 0) ? 'checked' : ''; ?>>
                        Lot Controlled
                        <span class="field-help" data-help="Track inventory by specific lots/batches for quality control" tabindex="0"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo ($formData['is_active'] ?? 0) ? 'checked' : ''; ?>>
                        Active
                    </label>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Update Material</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>