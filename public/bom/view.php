<?php
session_start();

// Debug: Log page access
error_log("BOM view.php accessed at " . date('Y-m-d H:i:s') . " with ID: " . ($_GET['id'] ?? 'none'));

require_once '../../includes/header-tailwind.php';
require_once '../../includes/ui-helpers.php';
require_once '../../classes/BOM.php';
require_once '../../classes/Database.php';

$db = Database::getInstance();
$bomModel = new BOM();

$bomId = $_GET['id'] ?? null;
if (!$bomId) {
    error_log("BOM view.php: No ID provided, redirecting to index");
    header('Location: index.php');
    exit;
}

error_log("BOM view.php: Looking for BOM ID: " . $bomId);

// Get BOM header with product info
$bom = $db->selectOne("
    SELECT 
        bh.*,
        p.product_code,
        p.name as product_name,
        p.description as product_description
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

// Calculate total cost
$totalCost = 0;
foreach ($bomDetails as $detail) {
    $totalCost += $detail['extended_cost'];
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card">
        <div class="card-header">
            Bill of Materials - <?php echo htmlspecialchars($bom['product_code']); ?>
            <div style="float: right;">
                <a href="edit.php?id=<?php echo $bomId; ?>" class="btn btn-secondary">Edit BOM</a>
                <a href="index.php" class="btn btn-primary">Back to BOMs</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                BOM was just updated at <?php echo date('Y-m-d H:i:s'); ?> - Refresh timestamp: <?php echo $_GET['updated']; ?>
            </div>
        <?php endif; ?>
        
        <!-- BOM Header Information -->
        <div class="grid grid-2 mb-3">
            <div>
                <h4>Product Information</h4>
                <p><strong>Product Code:</strong> <?php echo htmlspecialchars($bom['product_code']); ?></p>
                <p><strong>Product Name:</strong> <?php echo htmlspecialchars($bom['product_name']); ?></p>
                <p><strong>BOM Version:</strong> <?php echo htmlspecialchars($bom['version']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($bom['description']); ?></p>
            </div>
            <div>
                <h4>BOM Details</h4>
                <p><strong>Effective Date:</strong> <?php echo $bom['effective_date']; ?></p>
                <p><strong>Expiry Date:</strong> <?php echo $bom['expiry_date'] ?: 'None'; ?></p>
                <p><strong>Status:</strong> 
                    <?php if ($bom['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Inactive</span>
                    <?php endif; ?>
                </p>
                <p><strong>Approved By:</strong> <?php echo htmlspecialchars($bom['approved_by']); ?></p>
                <p><strong>Approved Date:</strong> <?php echo $bom['approved_date']; ?></p>
            </div>
        </div>

        <!-- Debug Info -->
        <?php if (isset($_GET['debug'])): ?>
            <div class="alert alert-info">
                <h4>Debug Information</h4>
                <p><strong>BOM ID:</strong> <?php echo $bomId; ?></p>
                <p><strong>BOM Updated At:</strong> <?php echo $bom['updated_at'] ?? 'Not set'; ?></p>
                <p><strong>Materials Found:</strong> <?php echo count($bomDetails); ?></p>
                <p><strong>Page Load Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <?php if (!empty($bomDetails)): ?>
                    <p><strong>Material Codes:</strong> 
                    <?php foreach ($bomDetails as $detail): ?>
                        <?php echo $detail['material_code']; ?> (<?php echo $detail['quantity_per']; ?>), 
                    <?php endforeach; ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Cost Summary -->
        <div class="card" style="background-color: #dbeafe; border-left: 4px solid var(--primary-color); margin-bottom: 1rem;">
            <div style="padding: 1rem;">
                <h4>💰 Cost Summary</h4>
                <p><strong>Total Material Cost per Unit:</strong> $<?php echo number_format($totalCost, 4); ?></p>
                <p><strong>Number of Materials:</strong> <?php echo count($bomDetails); ?></p>
            </div>
        </div>

        <!-- BOM Details Table -->
        <?php if (!empty($bomDetails)): ?>
        <h4>Materials Required</h4>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Material Code</th>
                        <th>Material Name</th>
                        <th>Type</th>
                        <th>Qty per Unit</th>
                        <th>UOM</th>
                        <th>Scrap %</th>
                        <th>Unit Cost</th>
                        <th>Extended Cost</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bomDetails as $detail): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detail['material_code']); ?></td>
                        <td><?php echo renderEntityName('material', $detail['material_id'], $detail['material_name']); ?></td>
                        <td><span class="badge badge-secondary"><?php echo ucfirst($detail['material_type']); ?></span></td>
                        <td><?php echo number_format($detail['quantity_per'], 6); ?></td>
                        <td><?php echo htmlspecialchars($detail['uom_code']); ?></td>
                        <td><?php echo number_format($detail['scrap_percentage'], 1); ?>%</td>
                        <td>$<?php echo number_format($detail['cost_per_unit'], 4); ?></td>
                        <td><strong>$<?php echo number_format($detail['extended_cost'], 4); ?></strong></td>
                        <td><?php echo htmlspecialchars($detail['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: var(--light-color); font-weight: bold;">
                        <td colspan="7" style="text-align: right;">Total Cost per Unit:</td>
                        <td><strong>$<?php echo number_format($totalCost, 4); ?></strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <p>No materials found for this BOM.</p>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="btn-group mt-3">
            <a href="../mrp/run.php" class="btn btn-success">Run MRP with this BOM</a>
            <a href="edit.php?id=<?php echo $bomId; ?>" class="btn btn-secondary">Edit BOM</a>
            <a href="create.php?copy_from=<?php echo $bomId; ?>" class="btn btn-warning">Copy BOM</a>
            <a href="index.php" class="btn btn-primary">Back to BOMs</a>
        </div>
    </div>
</div>

<style>
.edit-link-small {
    font-size: 0.75rem;
    color: #666;
    text-decoration: none;
    margin-left: 0.5rem;
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.edit-link-small:hover {
    opacity: 1;
    color: var(--primary-color);
    text-decoration: none;
}

.entity-name {
    display: inline;
}

/* Mobile-friendly touch targets */
@media (max-width: 768px) {
    .edit-link-small {
        font-size: 1rem;
        padding: 0.25rem;
        margin-left: 0.25rem;
    }
}
</style>

<?php
$include_autocomplete = true;
require_once '../../includes/footer-tailwind.php';
?>