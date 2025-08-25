<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Material.php';
require_once '../../classes/Inventory.php';

$materialModel = new Material();
$inventoryModel = new Inventory();
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$showInactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] === '1';

try {
    if ($search) {
        $materials = $materialModel->search($search, $showInactive);
    } else {
        if ($showInactive) {
            $materials = $materialModel->getAll(); // Will need to create this method
        } else {
            $materials = $materialModel->getAllActive();
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading materials: " . $e->getMessage();
    $materials = [];
}

// Get current stock for each material
foreach ($materials as &$material) {
    $material['current_stock'] = $inventoryModel->getAvailableQuantity('material', $material['id']);
}
unset($material);
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Materials Management
            <div style="float: right;">
                <a href="create.php" class="btn btn-primary">Add Material</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="search-bar">
            <form method="GET" action="" id="searchForm">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" 
                               id="searchInput"
                               name="search" 
                               placeholder="Search by code or name..." 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               data-autocomplete-preset="materials-search"
                               autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" 
                                   name="show_inactive" 
                                   value="1"
                                   <?php echo $showInactive ? 'checked' : ''; ?>
                                   onchange="this.form.submit();">
                            Show Inactive Materials
                        </label>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-secondary">Search</button>
                        <?php if (!empty($_GET['search']) || $showInactive): ?>
                            <a href="index.php" class="btn btn-outline">Clear All</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($materials)): ?>
            <div class="alert alert-info">
                <?php if ($search): ?>
                    No materials found matching your search.
                <?php else: ?>
                    No materials have been created yet. <a href="create.php">Create your first material</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>UOM</th>
                        <th>Status</th>
                        <th>Current Stock</th>
                        <th>Reorder Point</th>
                        <th>Cost/Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                    <tr<?php echo !$material['is_active'] ? ' class="inactive-row"' : ''; ?>>
                        <td><?php echo htmlspecialchars($material['material_code']); ?></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><span class="badge badge-secondary"><?php echo ucfirst($material['material_type']); ?></span></td>
                        <td><?php echo htmlspecialchars($material['uom_code']); ?></td>
                        <td>
                            <?php if ($material['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($material['current_stock'], 2); ?></td>
                        <td><?php echo number_format($material['reorder_point'], 2); ?></td>
                        <td>$<?php echo number_format($material['cost_per_unit'], 2); ?></td>
                    </tr>
                    <tr class="action-row" id="action-row-<?php echo $material['id']; ?>">
                        <td colspan="8">
                            <div class="actions-container">
                                <button class="actions-toggle" onclick="toggleActions(<?php echo $material['id']; ?>)" type="button">
                                    <span class="toggle-text">Actions</span>
                                    <span class="toggle-icon">▼</span>
                                </button>
                                <div class="action-buttons" id="actions-<?php echo $material['id']; ?>" style="display: none;">
                                    <a href="view.php?id=<?php echo $material['id']; ?>" class="btn-action btn-view" title="View Details">
                                        <span class="text">View</span>
                                    </a>
                                    <a href="edit.php?id=<?php echo $material['id']; ?>" class="btn-action btn-edit" title="Edit Material">
                                        <span class="text">Edit</span>
                                    </a>
                                    <a href="../inventory/adjust.php?type=material&id=<?php echo $material['id']; ?>" class="btn-action btn-inventory" title="Adjust Stock">
                                        <span class="text">Stock</span>
                                    </a>
                                    <a href="../bom/index.php?material_id=<?php echo $material['id']; ?>" class="btn-action btn-usage" title="View BOM Usage">
                                        <span class="text">Usage</span>
                                    </a>
                                    <?php if ($material['current_stock'] < $material['reorder_point']): ?>
                                    <a href="../purchase/create.php?material_id=<?php echo $material['id']; ?>" class="btn-action btn-reorder" title="Create Purchase Order">
                                        <span class="text">Reorder</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="summary-info">
            <p>Total Materials: <strong><?php echo count($materials); ?></strong></p>
            
            <?php
            // Calculate materials below reorder point
            $belowReorder = 0;
            foreach ($materials as $material) {
                if ($material['reorder_point'] > 0 && ($material['current_stock'] ?? 0) < $material['reorder_point']) {
                    $belowReorder++;
                }
            }
            if ($belowReorder > 0): ?>
                <p class="text-danger">Materials Below Reorder Point: <strong><?php echo $belowReorder; ?></strong></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<link rel="stylesheet" href="../css/autocomplete.css">
<link rel="stylesheet" href="../css/action-buttons.css">
<script src="../js/autocomplete.js"></script>
<script src="../js/search-history-manager.js"></script>
<script src="../js/autocomplete-manager.js"></script>
<script src="../js/action-buttons.js"></script>

<script>
function toggleActions(materialId) {
    const actionsDiv = document.getElementById('actions-' + materialId);
    const toggleBtn = document.querySelector('#action-row-' + materialId + ' .actions-toggle');
    
    if (actionsDiv.style.display === 'none' || actionsDiv.style.display === '') {
        // Show actions
        actionsDiv.style.display = 'flex';
        toggleBtn.classList.add('expanded');
        
        // Hide other open action menus
        document.querySelectorAll('.action-buttons').forEach(div => {
            if (div !== actionsDiv) {
                div.style.display = 'none';
            }
        });
        document.querySelectorAll('.actions-toggle').forEach(btn => {
            if (btn !== toggleBtn) {
                btn.classList.remove('expanded');
            }
        });
    } else {
        // Hide actions
        actionsDiv.style.display = 'none';
        toggleBtn.classList.remove('expanded');
    }
}

// Close actions when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.actions-container')) {
        document.querySelectorAll('.action-buttons').forEach(div => {
            div.style.display = 'none';
        });
        document.querySelectorAll('.actions-toggle').forEach(btn => {
            btn.classList.remove('expanded');
        });
    }
});
</script>

<style>
.search-bar {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.search-bar .form-row {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-bar input[type="text"] {
    width: 300px;
}

.badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 3px;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-info {
    color: #0c5460;
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.summary-info {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 5px;
}

.summary-info p {
    margin: 5px 0;
}

.text-danger {
    color: #dc3545;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0;
    cursor: pointer;
    font-size: 14px;
}

.checkbox-label input[type="checkbox"] {
    margin: 0;
}

.inactive-row {
    background-color: #f8f9fa;
    opacity: 0.7;
}

.inactive-row td {
    color: #6c757d;
}

/* Action Row Styling */
.action-row {
    background-color: #fafbfc;
    border-bottom: 1px solid #e1e4e8;
}

.action-row td {
    padding: 4px 12px;
    border-bottom: none;
}

.inactive-row + .action-row {
    background-color: #f6f8fa;
    opacity: 0.7;
}

/* Actions Container */
.actions-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Actions Toggle Button */
.actions-toggle {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background-color: #ffffff;
    border: 1px solid #d1d9e0;
    border-radius: 3px;
    color: #656d76;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    text-transform: uppercase;
    letter-spacing: 0.025em;
    min-height: 24px;
    width: fit-content;
}

.actions-toggle:hover {
    background-color: #f6f8fa;
    border-color: #8c959f;
}

.actions-toggle.expanded {
    background-color: #0969da;
    color: white;
    border-color: #0969da;
}

.actions-toggle.expanded .toggle-icon {
    transform: rotate(180deg);
}

.toggle-icon {
    font-size: 8px;
    transition: transform 0.15s ease;
    line-height: 1;
}

/* Action Buttons Styling */
.action-buttons {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    align-items: center;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    border-radius: 3px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: all 0.15s ease;
    white-space: nowrap;
    border: 1px solid;
    min-height: 24px;
    line-height: 1;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.btn-action .text {
    display: inline;
}

/* Button Color Schemes - Subtle and Polished */
.btn-view {
    background-color: #ffffff;
    color: #0969da;
    border-color: #d1d9e0;
}

.btn-view:hover {
    background-color: #0969da;
    color: white;
    border-color: #0969da;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn-edit {
    background-color: #ffffff;
    color: #fb8500;
    border-color: #d1d9e0;
}

.btn-edit:hover {
    background-color: #fb8500;
    color: white;
    border-color: #fb8500;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn-inventory {
    background-color: #ffffff;
    color: #2da44e;
    border-color: #d1d9e0;
}

.btn-inventory:hover {
    background-color: #2da44e;
    color: white;
    border-color: #2da44e;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn-usage {
    background-color: #ffffff;
    color: #8250df;
    border-color: #d1d9e0;
}

.btn-usage:hover {
    background-color: #8250df;
    color: white;
    border-color: #8250df;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.btn-reorder {
    background-color: #fff8dc;
    color: #9a6700;
    border-color: #f5d90a;
}

.btn-reorder:hover {
    background-color: #f5d90a;
    color: #000000;
    border-color: #e5c900;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

/* Mobile-optimized buttons */
@media (max-width: 640px) {
    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .btn-action {
        min-height: 28px;
        padding: 5px 10px;
    }
    
    .action-row td {
        padding: 6px 12px;
    }
}

@media (max-width: 768px) {
    .search-bar .form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .search-bar input[type="text"] {
        width: 100%;
    }
}

/* Custom autocomplete styles for materials page */
.autocomplete-item .item-main {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.autocomplete-item .item-code {
    font-weight: 600;
    color: #333;
    font-size: 14px;
    font-family: monospace;
}

.autocomplete-item .item-name {
    color: #666;
    font-size: 13px;
    font-weight: normal;
}
</style>


<?php require_once '../../includes/footer.php'; ?>