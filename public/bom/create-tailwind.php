<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../classes/BOM.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$bomModel = new BOM();

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
$selectedProductId = $_GET['product_id'] ?? null;

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
            $headerData = [
                'product_id' => $_POST['product_id'],
                'version' => $_POST['version'],
                'description' => $_POST['description'] ?? '',
                'effective_date' => $_POST['effective_date'],
                'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'approved_by' => $_POST['approved_by'] ?? 'System',
                'approved_date' => date('Y-m-d')
            ];
            
            $details = [];
            foreach ($_POST['materials'] as $material) {
                if (!empty($material['material_id']) && !empty($material['quantity_per'])) {
                    $details[] = [
                        'material_id' => $material['material_id'],
                        'quantity_per' => $material['quantity_per'],
                        'uom_id' => $material['uom_id'],
                        'scrap_percentage' => $material['scrap_percentage'] ?? 0,
                        'notes' => $material['notes'] ?? ''
                    ];
                }
            }
            
            $bomHeaderId = $bomModel->createBOMWithDetails($headerData, $details);
            $_SESSION['success'] = 'BOM created successfully';
            header('Location: view.php?id=' . $bomHeaderId);
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Error creating BOM: ' . $e->getMessage();
        }
    }
}

// Get selected product details if product_id is provided
$selectedProduct = null;
if ($selectedProductId) {
    $selectedProduct = $db->selectOne("SELECT * FROM products WHERE id = ?", [$selectedProductId], ['i']);
}
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Bill of Materials (BOM)</h1>
                <p class="mt-1 text-sm text-gray-600">Define the materials and quantities required to manufacture a product</p>
            </div>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to BOMs
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium">Please correct the following errors:</h3>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="space-y-8" id="bomForm">
        <!-- BOM Header Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
                <h2 class="text-lg font-medium text-gray-900">BOM Information</h2>
            </div>
            <div class="px-6 py-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="product_id" class="block text-sm font-medium text-gray-700">
                            Product <span class="text-red-500">*</span>
                        </label>
                        <select id="product_id" 
                                name="product_id" 
                                required
                                onchange="this.form.submit()"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" 
                                        <?php echo ($selectedProductId == $product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select the product this BOM is for</p>
                    </div>

                    <div>
                        <label for="version" class="block text-sm font-medium text-gray-700">
                            Version <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="version" 
                               name="version" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               placeholder="e.g., 1.0, 2.1"
                               value="<?php echo htmlspecialchars($_POST['version'] ?? '1.0'); ?>">
                        <p class="mt-1 text-xs text-gray-500">Version number for this BOM revision</p>
                    </div>
                </div>

                <!-- Selected Product Info -->
                <?php if ($selectedProduct): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium">Selected Product</h3>
                                <p class="text-sm"><?php echo htmlspecialchars($selectedProduct['product_code'] . ' - ' . $selectedProduct['name']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                              placeholder="Describe the purpose or notes for this BOM..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="effective_date" class="block text-sm font-medium text-gray-700">
                            Effective Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               id="effective_date" 
                               name="effective_date" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               value="<?php echo $_POST['effective_date'] ?? date('Y-m-d'); ?>">
                    </div>

                    <div>
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700">
                            Expiry Date
                        </label>
                        <input type="date" 
                               id="expiry_date" 
                               name="expiry_date"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>">
                        <p class="mt-1 text-xs text-gray-500">Optional expiration date</p>
                    </div>

                    <div>
                        <label for="approved_by" class="block text-sm font-medium text-gray-700">
                            Approved By
                        </label>
                        <input type="text" 
                               id="approved_by" 
                               name="approved_by"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               placeholder="Production Manager"
                               value="<?php echo htmlspecialchars($_POST['approved_by'] ?? 'Production Manager'); ?>">
                    </div>
                </div>

                <div class="relative flex items-start">
                    <div class="flex items-center h-5">
                        <input type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1"
                               <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>
                               class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active" class="font-medium text-gray-700">Active BOM</label>
                        <p class="text-gray-500">This BOM is available for use in production planning</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Materials Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">Required Materials</h2>
                    <button type="button" 
                            id="addMaterialBtn"
                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-primary bg-primary/10 hover:bg-primary/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        <svg class="mr-1 -ml-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Material
                    </button>
                </div>
            </div>
            <div class="px-6 py-6">
                <div id="materials-list" class="space-y-4">
                    <?php 
                    $materialItems = $_POST['materials'] ?? [['material_id' => '', 'quantity_per' => '', 'uom_id' => '', 'scrap_percentage' => '0', 'notes' => '']];
                    foreach ($materialItems as $index => $item): 
                    ?>
                    <div class="material-item bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-4">
                            <h4 class="text-sm font-medium text-gray-900">Material #<?php echo $index + 1; ?></h4>
                            <button type="button" 
                                    class="remove-material-btn text-red-600 hover:text-red-800 focus:outline-none"
                                    onclick="removeMaterial(this)">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Material <span class="text-red-500">*</span>
                                </label>
                                <div class="mt-1 autocomplete-wrapper">
                                    <input type="text" 
                                           name="materials[<?php echo $index; ?>][material_search]" 
                                           class="material-autocomplete block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                           placeholder="Search materials..."
                                           data-autocomplete-preset="materials-form"
                                           autocomplete="off"
                                           <?php if (!empty($item['material_id'])): ?>
                                               <?php
                                               // Find the selected material
                                               $selectedMaterial = null;
                                               foreach ($materials as $mat) {
                                                   if ($mat['id'] == $item['material_id']) {
                                                       $selectedMaterial = $mat;
                                                       break;
                                                   }
                                               }
                                               if ($selectedMaterial):
                                               ?>
                                               value="<?php echo htmlspecialchars($selectedMaterial['material_code'] . ' - ' . $selectedMaterial['name']); ?>"
                                               data-value="<?php echo $selectedMaterial['id']; ?>"
                                               data-uom="<?php echo $selectedMaterial['uom_code']; ?>"
                                               data-cost="<?php echo $selectedMaterial['cost_per_unit']; ?>"
                                               <?php endif; ?>
                                           <?php endif; ?>
                                           required>
                                    <input type="hidden" 
                                           name="materials[<?php echo $index; ?>][material_id]" 
                                           value="<?php echo htmlspecialchars($item['material_id'] ?? ''); ?>">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Quantity per Unit <span class="text-red-500">*</span>
                                </label>
                                <input type="number" 
                                       name="materials[<?php echo $index; ?>][quantity_per]" 
                                       step="0.000001" 
                                       min="0.000001" 
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                       placeholder="0.000001"
                                       value="<?php echo htmlspecialchars($item['quantity_per'] ?? ''); ?>">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">UOM</label>
                                <select name="materials[<?php echo $index; ?>][uom_id]"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                                    <option value="">Auto</option>
                                    <?php foreach ($uoms as $uom): ?>
                                        <option value="<?php echo $uom['id']; ?>" 
                                                <?php echo ($item['uom_id'] ?? '') == $uom['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($uom['code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Scrap Percentage</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="materials[<?php echo $index; ?>][scrap_percentage]" 
                                           step="0.1" 
                                           min="0" 
                                           max="50"
                                           class="block w-full rounded-md border-gray-300 pr-8 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                           placeholder="0.0"
                                           value="<?php echo htmlspecialchars($item['scrap_percentage'] ?? '0'); ?>">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">%</span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Notes</label>
                                <input type="text" 
                                       name="materials[<?php echo $index; ?>][notes]" 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                       placeholder="Optional notes..."
                                       value="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-x-4">
            <a href="index.php" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                Create BOM
            </button>
        </div>
    </form>
</div>

<!-- JavaScript for dynamic material management -->
<script>
let materialIndex = <?php echo count($materialItems); ?>;

document.getElementById('addMaterialBtn').addEventListener('click', function() {
    addMaterialRow();
});

function addMaterialRow() {
    const materialsContainer = document.getElementById('materials-list');
    const newRow = document.createElement('div');
    newRow.className = 'material-item bg-gray-50 border border-gray-200 rounded-lg p-4';
    newRow.innerHTML = `
        <div class="flex justify-between items-start mb-4">
            <h4 class="text-sm font-medium text-gray-900">Material #${materialIndex + 1}</h4>
            <button type="button" 
                    class="remove-material-btn text-red-600 hover:text-red-800 focus:outline-none"
                    onclick="removeMaterial(this)">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700">
                    Material <span class="text-red-500">*</span>
                </label>
                <div class="mt-1 autocomplete-wrapper">
                    <input type="text" 
                           name="materials[${materialIndex}][material_search]" 
                           class="material-autocomplete block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                           placeholder="Search materials..."
                           data-autocomplete-preset="materials-form"
                           autocomplete="off"
                           required>
                    <input type="hidden" 
                           name="materials[${materialIndex}][material_id]">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">
                    Quantity per Unit <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="materials[${materialIndex}][quantity_per]" 
                       step="0.000001" 
                       min="0.000001" 
                       required
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                       placeholder="0.000001">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">UOM</label>
                <select name="materials[${materialIndex}][uom_id]"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                    <option value="">Auto</option>
                    <?php foreach ($uoms as $uom): ?>
                        <option value="<?php echo $uom['id']; ?>">
                            <?php echo htmlspecialchars($uom['code']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Scrap Percentage</label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <input type="number" 
                           name="materials[${materialIndex}][scrap_percentage]" 
                           step="0.1" 
                           min="0" 
                           max="50"
                           class="block w-full rounded-md border-gray-300 pr-8 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                           placeholder="0.0"
                           value="0">
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">%</span>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Notes</label>
                <input type="text" 
                       name="materials[${materialIndex}][notes]" 
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                       placeholder="Optional notes...">
            </div>
        </div>
    `;
    
    materialsContainer.appendChild(newRow);
    materialIndex++;
    
    // Initialize autocomplete for the new row if AutocompleteManager exists
    if (typeof AutocompleteManager !== 'undefined') {
        const newInput = newRow.querySelector('.material-autocomplete');
        AutocompleteManager.init('materials-form', newInput);
    }
}

function removeMaterial(button) {
    const materialItem = button.closest('.material-item');
    const materialsContainer = document.getElementById('materials-list');
    
    if (materialsContainer.children.length > 1) {
        materialItem.remove();
        updateMaterialNumbers();
    } else {
        alert('At least one material is required for the BOM.');
    }
}

function updateMaterialNumbers() {
    const materialItems = document.querySelectorAll('.material-item');
    materialItems.forEach((item, index) => {
        const header = item.querySelector('h4');
        if (header) {
            header.textContent = `Material #${index + 1}`;
        }
    });
}
</script>

<?php 
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php'; 
?>