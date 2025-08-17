<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Database.php';
require_once '../../classes/Product.php';

$db = Database::getInstance();
$productModel = new Product();

// Check if filtering by product
$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$filterProduct = null;

if ($productId) {
    $filterProduct = $productModel->find($productId);
}

// Build query based on filter
$sql = "
    SELECT 
        bh.id,
        bh.product_id,
        bh.version,
        bh.description,
        bh.effective_date,
        bh.expiry_date,
        bh.is_active,
        bh.approved_by,
        p.product_code,
        p.name as product_name,
        COUNT(bd.id) as material_count
    FROM bom_headers bh
    JOIN products p ON bh.product_id = p.id
    LEFT JOIN bom_details bd ON bh.id = bd.bom_header_id
    WHERE 1=1
";

$params = [];
$types = [];

if ($productId) {
    $sql .= " AND bh.product_id = ?";
    $params[] = $productId;
    $types[] = 'i';
}

$sql .= " GROUP BY bh.id, bh.product_id, bh.version, bh.description, bh.effective_date, bh.expiry_date, 
             bh.is_active, bh.approved_by, p.product_code, p.name
    ORDER BY p.product_code, bh.version DESC";

// Get BOMs with product information
$boms = $params ? $db->select($sql, $params, $types) : $db->select($sql);

// Get products without BOMs
$productsWithoutBOM = $db->select("
    SELECT p.id, p.product_code, p.name
    FROM products p
    LEFT JOIN bom_headers bh ON p.id = bh.product_id AND bh.is_active = 1
    WHERE p.is_active = 1 AND p.deleted_at IS NULL AND bh.id IS NULL
    ORDER BY p.product_code
    LIMIT 10
");
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <?php if ($filterProduct): ?>
                BOMs for Product: <?php echo htmlspecialchars($filterProduct['product_code'] . ' - ' . $filterProduct['name']); ?>
            <?php else: ?>
                Bill of Materials (BOM) Management
            <?php endif; ?>
            <div style="float: right;">
                <?php if ($filterProduct): ?>
                    <a href="create.php?product_id=<?php echo $productId; ?>" class="btn btn-primary">Add New Version</a>
                    <a href="index.php" class="btn btn-secondary">View All BOMs</a>
                <?php else: ?>
                    <a href="create.php" class="btn btn-primary">Create BOM</a>
                <?php endif; ?>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if (!$productId && !empty($productsWithoutBOM)): ?>
        <div class="alert alert-warning">
            <h4>⚠️ Products Missing BOMs</h4>
            <p>These products don't have BOMs yet - they need them for MRP calculations:</p>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem;">
                <?php foreach ($productsWithoutBOM as $product): ?>
                    <a href="create.php?product_id=<?php echo $product['id']; ?>" class="btn btn-warning btn-sm">
                        <?php echo htmlspecialchars($product['product_code']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($boms)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Product Code</th>
                        <th>Product Name</th>
                        <th>Version</th>
                        <th>Description</th>
                        <th>Materials</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th>Approved By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($boms as $bom): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($bom['product_code']); ?></td>
                        <td><?php echo htmlspecialchars($bom['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($bom['version']); ?></td>
                        <td><?php echo htmlspecialchars($bom['description']); ?></td>
                        <td><?php echo $bom['material_count']; ?></td>
                        <td><?php echo $bom['effective_date']; ?></td>
                        <td>
                            <?php if ($bom['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($bom['approved_by']); ?></td>
                        <td>
                            <a href="view.php?id=<?php echo $bom['id']; ?>" class="btn btn-primary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">View</a>
                            <a href="edit.php?id=<?php echo $bom['id']; ?>" class="btn btn-secondary" 
                               style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <h4>🎯 Ready to Create Your First BOM!</h4>
            <p>No BOMs found. Create a Bill of Materials to define which materials are needed for each product.</p>
            <p><strong>Tip:</strong> Start with one of your key products to test the MRP system.</p>
        </div>
        <?php endif; ?>
        
        <div class="btn-group">
            <?php if ($filterProduct): ?>
                <a href="create.php?product_id=<?php echo $productId; ?>" class="btn btn-primary">Create New Version</a>
                <a href="../products/" class="btn btn-secondary">Back to Products</a>
            <?php else: ?>
                <a href="create.php" class="btn btn-primary">Create New BOM</a>
                <a href="../materials/" class="btn btn-secondary">View Materials</a>
                <a href="../" class="btn btn-secondary">Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>