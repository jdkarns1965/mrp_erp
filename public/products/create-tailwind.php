<?php
session_start();
require_once '../../includes/header-tailwind.php';
require_once '../../includes/tailwind-form-components.php';
require_once '../../classes/Product.php';

$product = new Product();
$errors = [];

// Get categories and UOMs for dropdowns
$categories = $product->db->select("SELECT * FROM product_categories ORDER BY name");
$uoms = $product->db->select("SELECT * FROM units_of_measure ORDER BY code");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    if (empty($_POST['product_code'])) {
        $errors[] = "Product code is required";
    }
    if (empty($_POST['name'])) {
        $errors[] = "Product name is required";
    }
    if (empty($_POST['uom_id'])) {
        $errors[] = "Unit of measure is required";
    }
    
    // Check if product code already exists
    if (!empty($_POST['product_code']) && $product->codeExists($_POST['product_code'])) {
        $errors[] = "Product code already exists";
    }
    
    // Validate numeric fields
    $numericFields = ['weight_kg', 'cycle_time_seconds', 'cavity_count', 'min_stock_qty', 
                      'max_stock_qty', 'safety_stock_qty', 'standard_cost', 'selling_price'];
    
    foreach ($numericFields as $field) {
        if (!empty($_POST[$field]) && !is_numeric($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be a number";
        }
    }
    
    if (empty($errors)) {
        $data = [
            'product_code' => $_POST['product_code'],
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? null,
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'uom_id' => $_POST['uom_id'],
            'weight_kg' => !empty($_POST['weight_kg']) ? $_POST['weight_kg'] : null,
            'cycle_time_seconds' => !empty($_POST['cycle_time_seconds']) ? $_POST['cycle_time_seconds'] : null,
            'cavity_count' => !empty($_POST['cavity_count']) ? $_POST['cavity_count'] : 1,
            'min_stock_qty' => !empty($_POST['min_stock_qty']) ? $_POST['min_stock_qty'] : 0,
            'max_stock_qty' => !empty($_POST['max_stock_qty']) ? $_POST['max_stock_qty'] : 0,
            'safety_stock_qty' => !empty($_POST['safety_stock_qty']) ? $_POST['safety_stock_qty'] : 0,
            'standard_cost' => !empty($_POST['standard_cost']) ? $_POST['standard_cost'] : 0,
            'selling_price' => !empty($_POST['selling_price']) ? $_POST['selling_price'] : 0,
            'is_lot_controlled' => isset($_POST['is_lot_controlled']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        try {
            $productId = $product->create($data);
            if ($productId) {
                $_SESSION['success'] = "Product created successfully";
                header('Location: index.php');
                exit;
            } else {
                $errors[] = "Failed to create product";
            }
        } catch (Exception $e) {
            $errors[] = "Error creating product: " . $e->getMessage();
        }
    }
}

// Prepare category options
$categoryOptions = [];
foreach ($categories as $category) {
    $categoryOptions[$category['id']] = $category['name'];
}

// Prepare UOM options
$uomOptions = [];
foreach ($uoms as $uom) {
    $uomOptions[$uom['id']] = $uom['code'] . ' - ' . $uom['description'];
}
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php echo TailwindFormComponents::pageHeader(
        'Create Product',
        'Add a new product to your inventory and production system'
    ); ?>

    <?php echo TailwindFormComponents::errorAlert($errors); ?>

    <!-- Form -->
    <form method="POST" class="space-y-8">
        <!-- Basic Information Section -->
        <?php echo TailwindFormComponents::sectionStart('Basic Information'); ?>
        
        <?php echo TailwindFormComponents::gridStart(2); ?>
            <?php echo TailwindFormComponents::textInput(
                'product_code',
                'Product Code',
                $_POST['product_code'] ?? '',
                [
                    'required' => true,
                    'placeholder' => 'e.g., PROD-001',
                    'help' => 'Unique identifier for this product'
                ]
            ); ?>

            <?php echo TailwindFormComponents::textInput(
                'name',
                'Product Name',
                $_POST['name'] ?? '',
                [
                    'required' => true,
                    'placeholder' => 'e.g., Widget Assembly Type A'
                ]
            ); ?>
        <?php echo TailwindFormComponents::gridEnd(); ?>

        <?php echo TailwindFormComponents::textarea(
            'description',
            'Description',
            $_POST['description'] ?? '',
            [
                'placeholder' => 'Detailed description of the product...',
                'rows' => 3
            ]
        ); ?>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Classification Section -->
        <?php echo TailwindFormComponents::sectionStart('Classification'); ?>
        
        <?php echo TailwindFormComponents::gridStart(2); ?>
            <?php echo TailwindFormComponents::select(
                'category_id',
                'Category',
                $categoryOptions,
                $_POST['category_id'] ?? '',
                [
                    'empty_option' => 'Select Category',
                    'help' => 'Product classification category'
                ]
            ); ?>

            <?php echo TailwindFormComponents::select(
                'uom_id',
                'Unit of Measure',
                $uomOptions,
                $_POST['uom_id'] ?? '',
                [
                    'required' => true,
                    'empty_option' => 'Select UOM',
                    'help' => 'Primary unit for this product'
                ]
            ); ?>
        <?php echo TailwindFormComponents::gridEnd(); ?>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Manufacturing Details Section -->
        <?php echo TailwindFormComponents::sectionStart('Manufacturing Details'); ?>
        
        <?php echo TailwindFormComponents::gridStart(3); ?>
            <?php echo TailwindFormComponents::inputWithSuffix(
                'weight_kg',
                'Weight',
                'kg',
                $_POST['weight_kg'] ?? '',
                [
                    'type' => 'number',
                    'step' => '0.001',
                    'min' => '0',
                    'placeholder' => '0.000',
                    'help' => 'Product weight in kilograms'
                ]
            ); ?>

            <?php echo TailwindFormComponents::inputWithSuffix(
                'cycle_time_seconds',
                'Cycle Time',
                'seconds',
                $_POST['cycle_time_seconds'] ?? '',
                [
                    'type' => 'number',
                    'step' => '0.1',
                    'min' => '0',
                    'placeholder' => '0.0',
                    'help' => 'Production time per unit'
                ]
            ); ?>

            <?php echo TailwindFormComponents::inputWithSuffix(
                'cavity_count',
                'Cavity Count',
                'cavities',
                $_POST['cavity_count'] ?? '1',
                [
                    'type' => 'number',
                    'step' => '1',
                    'min' => '1',
                    'placeholder' => '1',
                    'help' => 'Number of units produced per cycle'
                ]
            ); ?>
        <?php echo TailwindFormComponents::gridEnd(); ?>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Inventory Management Section -->
        <?php echo TailwindFormComponents::sectionStart('Inventory Management'); ?>
        
        <?php echo TailwindFormComponents::gridStart(3); ?>
            <?php echo TailwindFormComponents::inputWithSuffix(
                'min_stock_qty',
                'Minimum Stock',
                'units',
                $_POST['min_stock_qty'] ?? '0',
                [
                    'type' => 'number',
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00'
                ]
            ); ?>

            <?php echo TailwindFormComponents::inputWithSuffix(
                'max_stock_qty',
                'Maximum Stock',
                'units',
                $_POST['max_stock_qty'] ?? '0',
                [
                    'type' => 'number',
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00'
                ]
            ); ?>

            <?php echo TailwindFormComponents::inputWithSuffix(
                'safety_stock_qty',
                'Safety Stock',
                'units',
                $_POST['safety_stock_qty'] ?? '0',
                [
                    'type' => 'number',
                    'step' => '0.01',
                    'min' => '0',
                    'placeholder' => '0.00',
                    'help' => 'Buffer stock to prevent shortages'
                ]
            ); ?>
        <?php echo TailwindFormComponents::gridEnd(); ?>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Cost and Pricing Section -->
        <?php echo TailwindFormComponents::sectionStart('Cost and Pricing'); ?>
        
        <?php echo TailwindFormComponents::gridStart(2); ?>
            <?php echo TailwindFormComponents::currencyInput(
                'standard_cost',
                'Standard Cost',
                $_POST['standard_cost'] ?? '0',
                [
                    'placeholder' => '0.00',
                    'help' => 'Expected production cost per unit'
                ]
            ); ?>

            <?php echo TailwindFormComponents::currencyInput(
                'selling_price',
                'Selling Price',
                $_POST['selling_price'] ?? '0',
                [
                    'placeholder' => '0.00',
                    'help' => 'Standard selling price per unit'
                ]
            ); ?>
        <?php echo TailwindFormComponents::gridEnd(); ?>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Settings Section -->
        <?php echo TailwindFormComponents::sectionStart('Settings'); ?>
        
        <div class="space-y-4">
            <?php echo TailwindFormComponents::checkbox(
                'is_lot_controlled',
                'Lot Controlled',
                isset($_POST['is_lot_controlled']),
                [
                    'help' => 'Track this product by lot/batch numbers for traceability'
                ]
            ); ?>

            <?php echo TailwindFormComponents::checkbox(
                'is_active',
                'Active Product',
                !isset($_POST['is_active']) || $_POST['is_active'],
                [
                    'help' => 'Product is available for use in orders and production'
                ]
            ); ?>
        </div>

        <?php echo TailwindFormComponents::sectionEnd(); ?>

        <!-- Form Actions -->
        <?php echo TailwindFormComponents::actionButtons('Create Product'); ?>
    </form>
</div>

<?php 
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php'; 
?>