<?php
session_start();
require_once '../../includes/header-tailwind.php';
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

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Add New Material</h1>
                <p class="mt-1 text-sm text-gray-600">Create a new material for inventory tracking and BOM management</p>
            </div>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Materials
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
    <form method="POST" class="space-y-8">
        <!-- Basic Information Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 sm:rounded-t-xl">
                <h2 class="text-lg font-medium text-gray-900">Basic Information</h2>
            </div>
            <div class="px-6 py-6 space-y-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <label for="material_code" class="block text-sm font-medium text-gray-700">
                            Material Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="material_code" 
                               name="material_code" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               placeholder="e.g., MAT-001"
                               value="<?php echo htmlspecialchars($_POST['material_code'] ?? ''); ?>">
                        <p class="mt-1 text-xs text-gray-500">Unique identifier for this material</p>
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Material Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                               placeholder="e.g., Plastic Resin Grade A"
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                              placeholder="Detailed description of the material..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Classification Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-900">Classification</h2>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="material_type" class="block text-sm font-medium text-gray-700">
                            Material Type <span class="text-red-500">*</span>
                        </label>
                        <select id="material_type" 
                                name="material_type" 
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                            <?php 
                            $materialTypes = getEnumValues('materials', 'material_type');
                            echo generateEnumOptions($materialTypes, $_POST['material_type'] ?? '', true);
                            ?>
                        </select>
                    </div>

                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700">
                            Category
                        </label>
                        <select id="category_id" 
                                name="category_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="uom_id" class="block text-sm font-medium text-gray-700">
                            Unit of Measure <span class="text-red-500">*</span>
                        </label>
                        <select id="uom_id" 
                                name="uom_id" 
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
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
            </div>
        </div>

        <!-- Inventory Management Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-900">Inventory Management</h2>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="min_stock_qty" class="block text-sm font-medium text-gray-700">
                            Minimum Stock
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" 
                                   id="min_stock_qty" 
                                   name="min_stock_qty" 
                                   step="0.01" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['min_stock_qty'] ?? '0'); ?>">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">units</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="max_stock_qty" class="block text-sm font-medium text-gray-700">
                            Maximum Stock
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" 
                                   id="max_stock_qty" 
                                   name="max_stock_qty" 
                                   step="0.01" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['max_stock_qty'] ?? '0'); ?>">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">units</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="reorder_point" class="block text-sm font-medium text-gray-700">
                            Reorder Point
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" 
                                   id="reorder_point" 
                                   name="reorder_point" 
                                   step="0.01" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['reorder_point'] ?? '0'); ?>">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">units</span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Alert when stock falls below this level</p>
                    </div>

                    <div>
                        <label for="safety_stock_qty" class="block text-sm font-medium text-gray-700">
                            Safety Stock
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" 
                                   id="safety_stock_qty" 
                                   name="safety_stock_qty" 
                                   step="0.01" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['safety_stock_qty'] ?? '0'); ?>">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">units</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Procurement Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-900">Procurement</h2>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div>
                        <label for="default_supplier_id" class="block text-sm font-medium text-gray-700">
                            Default Supplier
                        </label>
                        <select id="default_supplier_id" 
                                name="default_supplier_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" 
                                        <?php echo ($_POST['default_supplier_id'] ?? '') == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="lead_time_days" class="block text-sm font-medium text-gray-700">
                            Lead Time
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <input type="number" 
                                   id="lead_time_days" 
                                   name="lead_time_days" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pr-12 focus:border-primary focus:ring-primary sm:text-sm px-3 py-2 border"
                                   placeholder="0"
                                   value="<?php echo htmlspecialchars($_POST['lead_time_days'] ?? '0'); ?>">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">days</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="cost_per_unit" class="block text-sm font-medium text-gray-700">
                            Cost Per Unit
                        </label>
                        <div class="mt-1 relative rounded-md shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500 sm:text-sm">$</span>
                            </div>
                            <input type="number" 
                                   id="cost_per_unit" 
                                   name="cost_per_unit" 
                                   step="0.01" 
                                   min="0"
                                   class="block w-full rounded-md border-gray-300 pl-7 pr-12 focus:border-primary focus:ring-primary sm:text-sm py-2 border"
                                   placeholder="0.00"
                                   value="<?php echo htmlspecialchars($_POST['cost_per_unit'] ?? '0'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Section -->
        <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-medium text-gray-900">Settings</h2>
            </div>
            <div class="px-6 py-6">
                <div class="space-y-4">
                    <div class="relative flex items-start">
                        <div class="flex items-center h-5">
                            <input type="checkbox" 
                                   id="is_lot_controlled" 
                                   name="is_lot_controlled" 
                                   value="1"
                                   <?php echo isset($_POST['is_lot_controlled']) ? 'checked' : ''; ?>
                                   class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="is_lot_controlled" class="font-medium text-gray-700">Lot Controlled</label>
                            <p class="text-gray-500">Track this material by lot/batch numbers for traceability</p>
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
                            <label for="is_active" class="font-medium text-gray-700">Active</label>
                            <p class="text-gray-500">Material is available for use in transactions</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-x-4">
            <a href="index.php" class="text-sm font-semibold text-gray-700 hover:text-gray-900">
                Cancel
            </a>
            <button type="submit" class="inline-flex justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-dark focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                Create Material
            </button>
        </div>
    </form>
</div>

<?php 
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php'; 
?>