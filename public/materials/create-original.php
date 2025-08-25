<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';
require_once '../../includes/enum-helper.php';

$materialModel = new Material();
$db = Database::getInstance();

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
    
    // Check if material code already exists
    if (!empty($_POST['material_code']) && $materialModel->codeExists($_POST['material_code'])) {
        $errors[] = 'Material code already exists';
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
                'safety_stock_qty' => $_POST['safety_stock_qty'] ?? 0,
                'is_lot_controlled' => isset($_POST['is_lot_controlled']) ? 1 : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $materialId = $materialModel->create($data);
            $_SESSION['success'] = 'Material created successfully';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error creating material: ' . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Add New Material
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
                    <label for="material_code">Material Code *</label>
                    <input type="text" id="material_code" name="material_code" required 
                           value="<?php echo htmlspecialchars($_POST['material_code'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="material_type">Material Type *</label>
                    <select id="material_type" name="material_type" required>
                        <?php 
                        $materialTypes = getEnumValues('materials', 'material_type');
                        echo generateEnumOptions($materialTypes, $_POST['material_type'] ?? '', true);
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="min_stock_qty">Minimum Stock</label>
                    <input type="number" id="min_stock_qty" name="min_stock_qty" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['min_stock_qty'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_stock_qty">Maximum Stock</label>
                    <input type="number" id="max_stock_qty" name="max_stock_qty" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['max_stock_qty'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reorder_point">Reorder Point</label>
                    <input type="number" id="reorder_point" name="reorder_point" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['reorder_point'] ?? '0'); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="cost_per_unit">Cost per Unit</label>
                    <input type="number" id="cost_per_unit" name="cost_per_unit" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['cost_per_unit'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="safety_stock_qty">Safety Stock Quantity</label>
                    <input type="number" id="safety_stock_qty" name="safety_stock_qty" step="0.01" min="0"
                           placeholder="Buffer stock to maintain"
                           value="<?php echo htmlspecialchars($_POST['safety_stock_qty'] ?? '0'); ?>">
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="lead_time_days">Lead Time (Days)</label>
                    <input type="number" id="lead_time_days" name="lead_time_days" min="0"
                           value="<?php echo htmlspecialchars($_POST['lead_time_days'] ?? '0'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="default_supplier_id">Default Supplier</label>
                    <select id="default_supplier_id" name="default_supplier_id">
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>" 
                                    <?php echo ($_POST['default_supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
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
                               <?php echo isset($_POST['is_lot_controlled']) ? 'checked' : 'checked'; ?>>
                        Lot Controlled
                    </label>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                        Active
                    </label>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Create Material</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>