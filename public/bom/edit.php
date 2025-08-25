<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/tailwind-form-components.php';
require_once '../../classes/BOM.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$bomModel = new BOM();

$bomId = $_GET['id'] ?? null;
if (!$bomId) {
    header('Location: index.php');
    exit;
}

// Get BOM header with product info
$bom = $db->selectOne("
    SELECT 
        bh.*,
        p.product_code,
        p.name as product_name
    FROM bom_headers bh
    JOIN products p ON bh.product_id = p.id
    WHERE bh.id = ?
", [$bomId], ['i']);

if (!$bom) {
    $_SESSION['error'] = 'BOM not found';
    header('Location: index.php');
    exit;
}

// Get BOM details
$bomDetails = $bomModel->getBOMDetails($bomId);

// Get products and materials for dropdowns
$products = $db->select("
    SELECT p.id, p.product_code, p.name 
    FROM products p 
    WHERE p.is_active = 1 AND p.deleted_at IS NULL 
    ORDER BY p.product_code
");

$materials = $db->select("
    SELECT m.id, m.material_code, m.name, m.material_type, uom.code as uom_code, m.cost_per_unit
    FROM materials m
    LEFT JOIN units_of_measure uom ON m.uom_id = uom.id
    WHERE m.is_active = 1 AND m.deleted_at IS NULL 
    ORDER BY m.material_type, m.material_code
");

$uoms = $db->select("SELECT * FROM units_of_measure ORDER BY code");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (empty($_POST['product_id'])) {
        $errors[] = 'Product selection is required';
    }
    if (empty($_POST['version'])) {
        $errors[] = 'Version is required';
    }
    if (empty($_POST['effective_date'])) {
        $errors[] = 'Effective date is required';
    }
    if (empty($_POST['materials']) || !is_array($_POST['materials'])) {
        $errors[] = 'At least one material is required';
    }
    
    // Validate materials
    if (!empty($_POST['materials'])) {
        foreach ($_POST['materials'] as $index => $material) {
            if (empty($material['material_id'])) {
                $errors[] = "Material selection is required for item " . ($index + 1);
            }
            if (empty($material['quantity_per']) || $material['quantity_per'] <= 0) {
                $errors[] = "Valid quantity is required for item " . ($index + 1);
            }
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update header
            $headerData = [
                'product_id' => $_POST['product_id'],
                'version' => $_POST['version'],
                'description' => $_POST['description'] ?? '',
                'effective_date' => $_POST['effective_date'],
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'approved_by' => $_POST['approved_by'] ?? 'System',
                'approved_date' => date('Y-m-d'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $bomModel->updateBOMHeader($bomId, $headerData);
            
            // Delete existing details
            $db->delete("DELETE FROM bom_details WHERE bom_header_id = ?", [$bomId], ['i']);
            
            // Insert new details
            $details = [];
            error_log("BOM Edit: Processing materials: " . print_r($_POST['materials'], true));
            
            foreach ($_POST['materials'] as $material) {
                if (!empty($material['material_id']) && !empty($material['quantity_per'])) {
                    $details[] = [
                        'bom_header_id' => $bomId,
                        'material_id' => $material['material_id'],
                        'quantity_per' => $material['quantity_per'],
                        'uom_id' => $material['uom_id'],
                        'scrap_percentage' => $material['scrap_percentage'] ?? 0,
                        'notes' => $material['notes'] ?? ''
                    ];
                }
            }
            
            error_log("BOM Edit: Final details array: " . print_r($details, true));
            
            foreach ($details as $detail) {
                $bomModel->addBOMDetail($detail);
            }
            
            $db->commit();
            $_SESSION['success'] = 'BOM updated successfully - ' . count($details) . ' materials updated';
            
            // Simple redirect
            header('Location: view.php?id=' . $bomId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Error updating BOM: ' . $e->getMessage();
        }
    }
}

// Prepare materials data for form
$materialItems = [];
if (!empty($bomDetails)) {
    foreach ($bomDetails as $detail) {
        $materialItems[] = [
            'material_id' => $detail['material_id'],
            'quantity_per' => $detail['quantity_per'],
            'uom_id' => $detail['uom_id'],
            'scrap_percentage' => $detail['scrap_percentage'],
            'notes' => $detail['notes']
        ];
    }
} else {
    $materialItems = [['material_id' => '', 'quantity_per' => '', 'uom_id' => '', 'scrap_percentage' => '0', 'notes' => '']];
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            Edit Bill of Materials (BOM) - <?php echo htmlspecialchars($bom['product_code']); ?>
            <div style="float: right;">
                <a href="view.php?id=<?php echo $bomId; ?>" class="btn btn-secondary">View BOM</a>
                <a href="index.php" class="btn btn-primary">Back to BOMs</a>
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
        
        <form method="POST" data-validate>
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="product_id">Product *</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    <?php echo ($bom['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="version">Version *</label>
                    <input type="text" id="version" name="version" required 
                           value="<?php echo htmlspecialchars($bom['version']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="2"><?php echo htmlspecialchars($bom['description']); ?></textarea>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="effective_date">Effective Date *</label>
                    <input type="date" id="effective_date" name="effective_date" required 
                           value="<?php echo $bom['effective_date']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="expiry_date">Expiry Date</label>
                    <input type="date" id="expiry_date" name="expiry_date" 
                           value="<?php echo $bom['expiry_date']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="approved_by">Approved By</label>
                    <input type="text" id="approved_by" name="approved_by" 
                           value="<?php echo htmlspecialchars($bom['approved_by']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo $bom['is_active'] ? 'checked' : ''; ?>>
                    Active BOM
                </label>
            </div>
            
            <h4>Materials Required</h4>
            <div id="materials-list">
                <?php foreach ($materialItems as $index => $item): ?>
                <div class="material-item" style="border: 1px solid var(--border-color); padding: 1rem; margin-bottom: 1rem; border-radius: 0.25rem;">
                    <div class="grid grid-4">
                        <div class="form-group">
                            <label>Material *</label>
                            <select name="materials[<?php echo $index; ?>][material_id]" required>
                                <option value="">Select Material</option>
                                <?php 
                                $currentType = '';
                                foreach ($materials as $material): 
                                    if ($material['material_type'] !== $currentType):
                                        if ($currentType !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . ucfirst($material['material_type']) . '">';
                                        $currentType = $material['material_type'];
                                    endif;
                                ?>
                                    <option value="<?php echo $material['id']; ?>" 
                                            data-uom="<?php echo $material['uom_code']; ?>"
                                            data-cost="<?php echo $material['cost_per_unit']; ?>"
                                            <?php echo ($item['material_id'] ?? '') == $material['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($material['material_code'] . ' - ' . $material['name'] . ' ($' . number_format($material['cost_per_unit'], 2) . '/' . $material['uom_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($currentType !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity per Unit *</label>
                            <input type="number" name="materials[<?php echo $index; ?>][quantity_per]" 
                                   step="0.000001" min="0.000001" required
                                   value="<?php echo htmlspecialchars($item['quantity_per'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>UOM</label>
                            <select name="materials[<?php echo $index; ?>][uom_id]">
                                <?php foreach ($uoms as $uom): ?>
                                    <option value="<?php echo $uom['id']; ?>" 
                                            <?php echo ($item['uom_id'] ?? '') == $uom['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($uom['code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Scrap %</label>
                            <input type="number" name="materials[<?php echo $index; ?>][scrap_percentage]" 
                                   step="0.1" min="0" max="50"
                                   value="<?php echo htmlspecialchars($item['scrap_percentage'] ?? '0'); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes</label>
                        <input type="text" name="materials[<?php echo $index; ?>][notes]"
                               value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                    </div>
                    
                    <button type="button" class="btn btn-danger btn-sm remove-material">Remove Material</button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="btn-group">
                <button type="button" id="add-material" class="btn btn-secondary">Add Another Material</button>
            </div>
            
            <div class="btn-group mt-3">
                <button type="submit" class="btn btn-primary">Update BOM</button>
                <a href="view.php?id=<?php echo $bomId; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let materialIndex = <?php echo count($materialItems); ?>;
    
    document.getElementById('add-material').addEventListener('click', function() {
        const container = document.getElementById('materials-list');
        const newItem = document.querySelector('.material-item').cloneNode(true);
        
        // Update field names and clear values
        const inputs = newItem.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name) {
                input.setAttribute('name', name.replace(/\[\d+\]/, '[' + materialIndex + ']'));
                if (input.type !== 'button') {
                    input.value = input.type === 'number' ? '0' : '';
                    input.selectedIndex = 0;
                }
            }
        });
        
        // Add remove button if not present
        if (!newItem.querySelector('.remove-material')) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-danger btn-sm remove-material';
            removeBtn.textContent = 'Remove Material';
            newItem.appendChild(removeBtn);
        }
        
        container.appendChild(newItem);
        materialIndex++;
    });
    
    // Remove material functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-material')) {
            const materialItems = document.querySelectorAll('.material-item');
            if (materialItems.length > 1) {
                e.target.closest('.material-item').remove();
            } else {
                alert('At least one material is required. Add another material before removing this one.');
            }
        }
    });
});
</script>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>