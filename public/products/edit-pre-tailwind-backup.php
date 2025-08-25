<?php
session_start();
require_once '../../classes/Product.php';

$product = new Product();
$errors = [];

// Get product ID from URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$productId) {
    $_SESSION['error'] = "Invalid product ID";
    header('Location: index.php');
    exit;
}

// Get product details
$productData = $product->findWithDetails($productId);

if (!$productData) {
    $_SESSION['error'] = "Product not found";
    header('Location: index.php');
    exit;
}

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
    
    // Check if product code already exists (excluding current product)
    if (!empty($_POST['product_code']) && $product->codeExists($_POST['product_code'], $productId)) {
        $errors[] = "Product code already exists";
    }
    
    // Validate numeric fields
    $numericFields = ['weight_kg', 'cycle_time_seconds', 'cavity_count', 'min_stock_qty', 
                      'max_stock_qty', 'safety_stock_qty', 'lead_time_days', 'lot_size_qty', 
                      'lot_size_multiple', 'standard_cost', 'selling_price'];
    
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
            'lead_time_days' => !empty($_POST['lead_time_days']) ? $_POST['lead_time_days'] : 0,
            'lot_size_rule' => $_POST['lot_size_rule'] ?? 'lot-for-lot',
            'lot_size_qty' => !empty($_POST['lot_size_qty']) ? $_POST['lot_size_qty'] : 0,
            'lot_size_multiple' => !empty($_POST['lot_size_multiple']) ? $_POST['lot_size_multiple'] : 1,
            'standard_cost' => !empty($_POST['standard_cost']) ? $_POST['standard_cost'] : 0,
            'selling_price' => !empty($_POST['selling_price']) ? $_POST['selling_price'] : 0,
            'is_lot_controlled' => isset($_POST['is_lot_controlled']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        try {
            $result = $product->update($productId, $data);
            if ($result) {
                $_SESSION['success'] = "Product updated successfully";
                header('Location: index.php');
                exit;
            } else {
                $errors[] = "Failed to update product";
            }
        } catch (Exception $e) {
            $errors[] = "Error updating product: " . $e->getMessage();
        }
    }
} else {
    // Pre-populate form with existing data
    $_POST = $productData;
}

// Include header after all redirect logic
require_once '../../includes/header-tailwind.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            <h2>Edit Product: <?php echo htmlspecialchars($productData['product_code']); ?></h2>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="product_code">Product Code *</label>
                    <input type="text" id="product_code" name="product_code" 
                           value="<?php echo htmlspecialchars($_POST['product_code'] ?? ''); ?>" 
                           required maxlength="30">
                    <span class="tooltip">Unique product identifier</span>
                </div>
                
                <div class="form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" id="name" name="name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                           required maxlength="100">
                    <span class="tooltip">Product display name</span>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tooltip">Product category for organization</span>
                </div>
                
                <div class="form-group">
                    <label for="uom_id">Unit of Measure *</label>
                    <select id="uom_id" name="uom_id" required>
                        <option value="">-- Select UOM --</option>
                        <?php foreach ($uoms as $uom): ?>
                            <option value="<?php echo $uom['id']; ?>" 
                                    <?php echo (isset($_POST['uom_id']) && $_POST['uom_id'] == $uom['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($uom['code'] . ' - ' . $uom['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tooltip">How the product is measured</span>
                </div>
            </div>
            
            <div class="form-group full-width">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                <span class="tooltip">Detailed product description</span>
            </div>
            
            <fieldset>
                <legend>Manufacturing Details</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="weight_kg">Weight (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" 
                               value="<?php echo htmlspecialchars($_POST['weight_kg'] ?? ''); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Product weight in kilograms</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="cycle_time_seconds">Cycle Time (seconds)</label>
                        <input type="number" id="cycle_time_seconds" name="cycle_time_seconds" 
                               value="<?php echo htmlspecialchars($_POST['cycle_time_seconds'] ?? ''); ?>" 
                               min="0">
                        <span class="tooltip">Manufacturing cycle time per batch</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="cavity_count">Cavity Count</label>
                        <input type="number" id="cavity_count" name="cavity_count" 
                               value="<?php echo htmlspecialchars($_POST['cavity_count'] ?? '1'); ?>" 
                               min="1">
                        <span class="tooltip">Number of parts produced per cycle</span>
                    </div>
                </div>
            </fieldset>
            
            <fieldset>
                <legend>Inventory Settings</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="min_stock_qty">Minimum Stock</label>
                        <input type="number" id="min_stock_qty" name="min_stock_qty" 
                               value="<?php echo htmlspecialchars($_POST['min_stock_qty'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Trigger reorder when stock falls below this</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_stock_qty">Maximum Stock</label>
                        <input type="number" id="max_stock_qty" name="max_stock_qty" 
                               value="<?php echo htmlspecialchars($_POST['max_stock_qty'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Maximum inventory level to maintain</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="safety_stock_qty">Safety Stock</label>
                        <input type="number" id="safety_stock_qty" name="safety_stock_qty" 
                               value="<?php echo htmlspecialchars($_POST['safety_stock_qty'] ?? $productData['safety_stock_qty'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Buffer stock to prevent stockouts</span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="lead_time_days">Lead Time (days)</label>
                        <input type="number" id="lead_time_days" name="lead_time_days" 
                               value="<?php echo htmlspecialchars($_POST['lead_time_days'] ?? $productData['lead_time_days'] ?? '0'); ?>" 
                               min="0" step="1">
                        <span class="tooltip">Manufacturing lead time in working days</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="lot_size_rule">Lot Sizing Rule</label>
                        <select id="lot_size_rule" name="lot_size_rule">
                            <option value="lot-for-lot" <?php echo ($_POST['lot_size_rule'] ?? $productData['lot_size_rule'] ?? 'lot-for-lot') === 'lot-for-lot' ? 'selected' : ''; ?>>
                                Lot-for-Lot (Order exact quantity)
                            </option>
                            <option value="fixed" <?php echo ($_POST['lot_size_rule'] ?? $productData['lot_size_rule'] ?? '') === 'fixed' ? 'selected' : ''; ?>>
                                Fixed Order Quantity
                            </option>
                            <option value="min-max" <?php echo ($_POST['lot_size_rule'] ?? $productData['lot_size_rule'] ?? '') === 'min-max' ? 'selected' : ''; ?>>
                                Min-Max (Order up to max when below min)
                            </option>
                            <option value="economic" <?php echo ($_POST['lot_size_rule'] ?? $productData['lot_size_rule'] ?? '') === 'economic' ? 'selected' : ''; ?>>
                                Economic Order Quantity (EOQ)
                            </option>
                        </select>
                        <span class="tooltip">Method for determining production order quantities</span>
                    </div>
                </div>
                
                <div class="form-row" id="lot-size-params">
                    <div class="form-group">
                        <label for="lot_size_qty">Lot Size Quantity</label>
                        <input type="number" id="lot_size_qty" name="lot_size_qty" 
                               value="<?php echo htmlspecialchars($_POST['lot_size_qty'] ?? $productData['lot_size_qty'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Fixed quantity (for Fixed rule) or minimum quantity (for Min-Max rule)</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="lot_size_multiple">Lot Multiple</label>
                        <input type="number" id="lot_size_multiple" name="lot_size_multiple" 
                               value="<?php echo htmlspecialchars($_POST['lot_size_multiple'] ?? $productData['lot_size_multiple'] ?? '1'); ?>" 
                               step="0.0001" min="0.0001">
                        <span class="tooltip">Round order quantities to multiples of this value</span>
                    </div>
                </div>
                
                <?php 
                // Try to get current stock
                try {
                    $currentStock = $product->getCurrentStock($productId);
                    if ($currentStock > 0): ?>
                        <div class="alert alert-info">
                            Current Stock Level: <strong><?php echo number_format($currentStock, 2); ?> <?php echo htmlspecialchars($productData['uom_code'] ?? ''); ?></strong>
                        </div>
                    <?php endif;
                } catch (Exception $e) {
                    // Ignore stock level errors
                }
                ?>
            </fieldset>
            
            <fieldset>
                <legend>Pricing</legend>
                <div class="form-row">
                    <div class="form-group">
                        <label for="standard_cost">Standard Cost</label>
                        <input type="number" id="standard_cost" name="standard_cost" 
                               value="<?php echo htmlspecialchars($_POST['standard_cost'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Standard manufacturing cost per unit</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="selling_price">Selling Price</label>
                        <input type="number" id="selling_price" name="selling_price" 
                               value="<?php echo htmlspecialchars($_POST['selling_price'] ?? '0'); ?>" 
                               step="0.0001" min="0">
                        <span class="tooltip">Standard selling price per unit</span>
                    </div>
                </div>
            </fieldset>
            
            <div class="form-row">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_lot_controlled" value="1" 
                               <?php echo (!empty($_POST['is_lot_controlled'])) ? 'checked' : ''; ?>>
                        Lot Controlled
                    </label>
                    <span class="tooltip">Track inventory by lot numbers</span>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo (!empty($_POST['is_active'])) ? 'checked' : ''; ?>>
                        Active
                    </label>
                    <span class="tooltip">Product is currently in use</span>
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Update Product</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>