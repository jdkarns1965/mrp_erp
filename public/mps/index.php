<?php
session_start();
require_once '../../includes/help-system.php';

// Debug: Check if we can access basic functions
echo "<!-- Debug: Starting MPS page -->\n";

try {
    require_once '../../includes/header.php';
    echo "<!-- Debug: Header included successfully -->\n";
} catch (Exception $e) {
    die("Error including header: " . $e->getMessage());
}

try {
    require_once '../../classes/Database.php';
    echo "<!-- Debug: Database class included -->\n";
} catch (Exception $e) {
    die("Error including Database class: " . $e->getMessage());
}

try {
    $db = Database::getInstance();
    echo "<!-- Debug: Database connected -->\n";
} catch (Exception $e) {
    die("Error connecting to database: " . $e->getMessage());
}

// Test if planning_calendar table exists
try {
    $sql = "SHOW TABLES LIKE 'planning_calendar'";
    $result = $db->select($sql);
    if (empty($result)) {
        echo "<div class='container'>";
        echo "<div class='alert alert-warning'>";
        echo "<h3>⚠️ MPS Setup Required</h3>";
        echo "<p>The Master Production Schedule requires additional database tables. Please run:</p>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0;'>";
        echo "mysql -u root -p mrp_erp < database/create_planning_tables.sql</pre>";
        echo "<p>This will create the planning calendar and MPS tables with initial data.</p>";
        echo "<p><strong>After running the script, refresh this page.</strong></p>";
        echo "</div>";
        echo "</div>";
        require_once '../../includes/footer.php';
        exit;
    }
    echo "<!-- Debug: Planning calendar table exists -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Get planning periods
try {
    $sql = "SELECT * FROM planning_calendar 
            WHERE period_end >= CURDATE() 
            ORDER BY period_start 
            LIMIT 12";
    $periods = $db->select($sql);
    echo "<!-- Debug: Found " . count($periods) . " planning periods -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching periods: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Get products
try {
    $sql = "SELECT 
                p.id,
                p.product_code,
                p.name,
                COALESCE(p.safety_stock_qty, 0) as safety_stock_qty,
                COALESCE(p.lead_time_days, 0) as lead_time_days
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.product_code
            LIMIT 10";
    $products = $db->select($sql);
    echo "<!-- Debug: Found " . count($products) . " products -->\n";
} catch (Exception $e) {
    echo "<div class='container'><div class='alert alert-danger'>Error fetching products: " . $e->getMessage() . "</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Get existing MPS data
$mpsData = [];
if (!empty($products) && !empty($periods)) {
    try {
        $productIds = implode(',', array_column($products, 'id'));
        $periodIds = implode(',', array_column($periods, 'id'));
        
        $sql = "SELECT product_id, period_id, firm_planned_qty, demand_qty 
                FROM master_production_schedule 
                WHERE product_id IN ($productIds) AND period_id IN ($periodIds)";
        $mpsEntries = $db->select($sql);
        
        foreach ($mpsEntries as $entry) {
            $mpsData[$entry['product_id']][$entry['period_id']] = $entry;
        }
        echo "<!-- Debug: Loaded " . count($mpsEntries) . " MPS entries -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading MPS data: " . $e->getMessage() . " -->\n";
        $mpsData = [];
    }
}
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Master Production Schedule (MPS) <?php echo help_tooltip('mps', 'Plan production quantities for each time period'); ?></h2>
            <div style="float: right;">
                <button type="button" class="btn btn-primary" onclick="saveMPS()">Save MPS</button>
                <a href="../mrp/run-enhanced.php?include_mps=1" class="btn btn-success">Run Enhanced MRP</a>
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <p style="padding: 1rem;">Plan production quantities for each period. The MPS drives MRP calculations and helps balance demand with production capacity.</p>
    </div>
    
    <?php echo HelpSystem::renderHelpPanel('mps'); ?>
    <?php echo HelpSystem::renderHelpButton(); ?>

    <?php if (empty($periods)): ?>
        <div class="alert alert-warning">
            <strong>No planning periods found.</strong> 
            <p>The planning calendar needs to be initialized. Please run the MRP enhancement schema or contact your system administrator.</p>
        </div>
    <?php elseif (empty($products)): ?>
        <div class="alert alert-warning">
            <strong>No products found.</strong> 
            <p>Please create some products first before using the Master Production Schedule.</p>
            <a href="../products/create.php" class="btn btn-primary">Create Product</a>
        </div>
    <?php else: ?>
        <form id="mpsForm">
            <div class="card">
                <h3 class="card-header">Production Planning</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="min-width: 200px;">Product</th>
                                <th style="text-align: center;">Safety Stock</th>
                                <th style="text-align: center;">Lead Time</th>
                                <?php foreach ($periods as $period): ?>
                                    <th style="text-align: center; min-width: 100px;">
                                        <?= htmlspecialchars($period['period_name']) ?><br>
                                        <small style="color: #666;">
                                            <?= date('M d', strtotime($period['period_start'])) ?>
                                            -
                                            <?= date('M d', strtotime($period['period_end'])) ?>
                                        </small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($product['product_code']) ?></strong><br>
                                        <small style="color: #666;"><?= htmlspecialchars($product['name']) ?></small>
                                    </td>
                                    <td style="text-align: center;">
                                        <?= number_format($product['safety_stock_qty'], 0) ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?= $product['lead_time_days'] ?> days
                                    </td>
                                    <?php foreach ($periods as $period): ?>
                                        <?php 
                                        $mpsEntry = $mpsData[$product['id']][$period['id']] ?? null;
                                        $currentValue = $mpsEntry ? $mpsEntry['firm_planned_qty'] : '';
                                        ?>
                                        <td style="padding: 0.25rem; text-align: center;">
                                            <input type="number" 
                                                   class="mps-input"
                                                   data-product-id="<?= $product['id'] ?>"
                                                   data-period-id="<?= $period['id'] ?>"
                                                   value="<?= $currentValue ?>"
                                                   placeholder="0"
                                                   min="0"
                                                   step="1"
                                                   style="width: 80px; padding: 0.25rem; text-align: center;">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <div class="card" style="margin-top: 2rem;">
            <h3 class="card-header">Instructions</h3>
            <div style="padding: 1rem;">
                <ol>
                    <li><strong>Enter planned production quantities</strong> for each product in each period</li>
                    <li><strong>Consider lead times</strong> - production must start before the required date</li>
                    <li><strong>Check safety stock levels</strong> - ensure you maintain minimum stock</li>
                    <li><strong>Save the MPS</strong> when planning is complete</li>
                    <li><strong>Run Enhanced MRP</strong> to calculate material requirements based on this schedule</li>
                </ol>
                
                <div style="margin-top: 1rem;">
                    <h4>Planning Periods:</h4>
                    <ul>
                        <?php foreach ($periods as $period): ?>
                            <li><strong><?= htmlspecialchars($period['period_name']) ?>:</strong> 
                                <?= date('F j, Y', strtotime($period['period_start'])) ?> 
                                to 
                                <?= date('F j, Y', strtotime($period['period_end'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
async function saveMPS() {
    const saveBtn = document.querySelector('button[onclick="saveMPS()"]');
    const originalText = saveBtn.textContent;
    
    try {
        saveBtn.textContent = 'Saving...';
        saveBtn.disabled = true;
        
        // Collect all MPS input data
        const mpsData = [];
        const inputs = document.querySelectorAll('.mps-input');
        
        inputs.forEach(input => {
            const value = parseFloat(input.value) || 0;
            if (value > 0) {
                mpsData.push({
                    product_id: input.getAttribute('data-product-id'),
                    period_id: input.getAttribute('data-period-id'),
                    firm_planned_qty: value
                });
            }
        });
        
        // Send to save endpoint
        const response = await fetch('save-mps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mps_data: mpsData })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('MPS data saved successfully!', 'success');
        } else {
            showMessage('Error saving MPS: ' + (result.error || 'Unknown error'), 'danger');
        }
        
    } catch (error) {
        console.error('Save error:', error);
        showMessage('Network error while saving MPS data', 'danger');
    } finally {
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    }
}

function showMessage(text, type) {
    // Remove existing messages
    const existing = document.querySelector('.mps-message');
    if (existing) {
        existing.remove();
    }
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type} mps-message`;
    messageDiv.style.margin = '1rem 0';
    messageDiv.textContent = text;
    
    // Insert after the header card
    const container = document.querySelector('.container');
    const headerCard = container.querySelector('.card');
    headerCard.insertAdjacentElement('afterend', messageDiv);
    
    // Auto-remove success messages after 5 seconds
    if (type === 'success') {
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }
}
</script>

<?php echo HelpSystem::getHelpScript(); ?>

<?php require_once '../../includes/footer.php'; ?>