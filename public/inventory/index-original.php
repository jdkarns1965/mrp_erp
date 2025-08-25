<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Inventory.php';
require_once '../../classes/Material.php';
require_once '../../classes/Database.php';

$inventoryModel = new Inventory();
$materialModel = new Material();
$db = Database::getInstance();

// Get filter parameters
$itemType = $_GET['item_type'] ?? 'material';
$search = $_GET['search'] ?? '';

// Get inventory data with material/product details
$sql = "SELECT 
            i.*,
            CASE 
                WHEN i.item_type = 'material' THEN m.material_code
                WHEN i.item_type = 'product' THEN p.product_code
            END AS item_code,
            CASE 
                WHEN i.item_type = 'material' THEN m.name
                WHEN i.item_type = 'product' THEN p.name
            END AS item_name,
            sl.code as location_code,
            sl.description as location_name,
            uom.code as uom_code,
            s.name as supplier_name
        FROM inventory i
        LEFT JOIN materials m ON i.item_type = 'material' AND i.item_id = m.id
        LEFT JOIN products p ON i.item_type = 'product' AND i.item_id = p.id
        LEFT JOIN storage_locations sl ON i.location_id = sl.id
        LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
        LEFT JOIN suppliers s ON i.supplier_id = s.id
        WHERE i.status = 'available'
          AND i.quantity > 0";

$params = [];
$types = [];

if ($itemType !== 'all') {
    $sql .= " AND i.item_type = ?";
    $params[] = $itemType;
    $types[] = 's';
}

if (!empty($search)) {
    $sql .= " AND (
        (i.item_type = 'material' AND (m.material_code LIKE ? OR m.name LIKE ?)) OR
        (i.item_type = 'product' AND (p.product_code LIKE ? OR p.name LIKE ?)) OR
        i.lot_number LIKE ?
    )";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types = array_merge($types, ['s', 's', 's', 's', 's']);
}

$sql .= " ORDER BY 
            CASE WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) THEN 0 ELSE 1 END,
            i.expiry_date ASC,
            item_name ASC";

$inventory = $db->select($sql, $params, $types);

// Get summary statistics
$totalItems = count($inventory);
$totalValue = array_sum(array_map(function($item) {
    return $item['quantity'] * ($item['unit_cost'] ?? 0);
}, $inventory));

$expiringItems = array_filter($inventory, function($item) {
    return $item['expiry_date'] && strtotime($item['expiry_date']) <= strtotime('+30 days');
});
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Inventory Management
            <div style="float: right;">
                <a href="receive.php" class="btn btn-primary">Receive Inventory</a>
                <a href="issue.php" class="btn btn-secondary">Issue Inventory</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <!-- Summary Cards -->
        <div class="grid grid-3" style="margin-bottom: 1rem;">
            <div class="card" style="text-align: center; padding: 1rem;">
                <h3 style="margin: 0; color: #333;"><?php echo $totalItems; ?></h3>
                <p style="margin: 0; color: #666;">Total Lots</p>
            </div>
            <div class="card" style="text-align: center; padding: 1rem;">
                <h3 style="margin: 0; color: #333;">$<?php echo number_format($totalValue, 2); ?></h3>
                <p style="margin: 0; color: #666;">Total Value</p>
            </div>
            <div class="card" style="text-align: center; padding: 1rem;">
                <h3 style="margin: 0; color: <?php echo count($expiringItems) > 0 ? '#e74c3c' : '#27ae60'; ?>;">
                    <?php echo count($expiringItems); ?>
                </h3>
                <p style="margin: 0; color: #666;">Expiring Soon</p>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="GET" style="margin-bottom: 1rem;">
            <div class="grid grid-3">
                <div class="form-group">
                    <label for="item_type">Item Type</label>
                    <select id="item_type" name="item_type" onchange="this.form.submit()">
                        <option value="all" <?php echo $itemType === 'all' ? 'selected' : ''; ?>>All Items</option>
                        <option value="material" <?php echo $itemType === 'material' ? 'selected' : ''; ?>>Materials</option>
                        <option value="product" <?php echo $itemType === 'product' ? 'selected' : ''; ?>>Products</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Code, name, or lot number..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group" style="display: flex; align-items: end;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 0.5rem;">Clear</a>
                </div>
            </div>
        </form>
        
        <?php if (count($expiringItems) > 0): ?>
            <div class="alert alert-warning">
                <strong>Warning:</strong> <?php echo count($expiringItems); ?> lots are expiring within 30 days!
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Lot Number</th>
                        <th>Location</th>
                        <th>Quantity</th>
                        <th>Reserved</th>
                        <th>Available</th>
                        <th>UOM</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Expiry Date</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 2rem; color: #666;">
                                No inventory found
                                <?php if (!empty($search) || $itemType !== 'material'): ?>
                                    for the selected filters
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <?php 
                            $available = $item['quantity'] - $item['reserved_quantity'];
                            $totalValue = $item['quantity'] * ($item['unit_cost'] ?? 0);
                            $isExpiring = $item['expiry_date'] && strtotime($item['expiry_date']) <= strtotime('+30 days');
                            $isExpired = $item['expiry_date'] && strtotime($item['expiry_date']) <= time();
                            ?>
                            <tr style="<?php echo $isExpired ? 'background-color: #ffebee;' : ($isExpiring ? 'background-color: #fff3e0;' : ''); ?>">
                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['lot_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['location_code'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($item['quantity'], 2); ?></td>
                                <td><?php echo number_format($item['reserved_quantity'], 2); ?></td>
                                <td><?php echo number_format($available, 2); ?></td>
                                <td><?php echo htmlspecialchars($item['uom_code']); ?></td>
                                <td>$<?php echo number_format($item['unit_cost'] ?? 0, 2); ?></td>
                                <td>$<?php echo number_format($totalValue, 2); ?></td>
                                <td>
                                    <?php if ($item['expiry_date']): ?>
                                        <span style="color: <?php echo $isExpired ? '#e74c3c' : ($isExpiring ? '#f39c12' : '#333'); ?>;">
                                            <?php echo date('M j, Y', strtotime($item['expiry_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="adjust.php?id=<?php echo $item['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Adjust</a>
                                    <a href="transfer.php?id=<?php echo $item['id']; ?>" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Transfer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>