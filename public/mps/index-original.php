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

// Get demand forecast data (customer orders)
$demandData = [];
if (!empty($products) && !empty($periods)) {
    try {
        $sql = "SELECT 
                    cod.product_id,
                    DATE(co.required_date) as required_date,
                    SUM(cod.quantity) as total_demand
                FROM customer_order_details cod
                JOIN customer_orders co ON cod.order_id = co.id
                WHERE cod.product_id IN (" . implode(',', array_column($products, 'id')) . ")
                  AND co.status IN ('pending', 'confirmed', 'in_production')
                  AND co.required_date BETWEEN (SELECT MIN(period_start) FROM planning_calendar WHERE id IN (" . implode(',', array_column($periods, 'id')) . "))
                                           AND (SELECT MAX(period_end) FROM planning_calendar WHERE id IN (" . implode(',', array_column($periods, 'id')) . "))
                GROUP BY cod.product_id, DATE(co.required_date)";
        
        $demands = $db->select($sql);
        
        // Map demands to periods
        foreach ($demands as $demand) {
            foreach ($periods as $period) {
                if ($demand['required_date'] >= $period['period_start'] && 
                    $demand['required_date'] <= $period['period_end']) {
                    if (!isset($demandData[$demand['product_id']][$period['id']])) {
                        $demandData[$demand['product_id']][$period['id']] = 0;
                    }
                    $demandData[$demand['product_id']][$period['id']] += $demand['total_demand'];
                    break;
                }
            }
        }
        echo "<!-- Debug: Loaded demand forecast data -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading demand data: " . $e->getMessage() . " -->\n";
    }
}

// Get current inventory levels
$inventoryData = [];
if (!empty($products)) {
    try {
        $sql = "SELECT 
                    item_id as product_id,
                    SUM(quantity - COALESCE(reserved_quantity, 0)) as current_stock
                FROM inventory
                WHERE item_type = 'product' 
                  AND item_id IN (" . implode(',', array_column($products, 'id')) . ")
                  AND status = 'available'
                GROUP BY item_id";
        
        $inventory = $db->select($sql);
        foreach ($inventory as $inv) {
            $inventoryData[$inv['product_id']] = $inv['current_stock'];
        }
        echo "<!-- Debug: Loaded inventory data -->\n";
    } catch (Exception $e) {
        echo "<!-- Debug: Error loading inventory: " . $e->getMessage() . " -->\n";
    }
}
?>

<?php echo HelpSystem::getHelpStyles(); ?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Master Production Schedule (MPS) <?php echo help_tooltip('mps', 'Plan production quantities for each time period'); ?></h2>
            <div style="float: right;">
                <a href="reports.php" class="btn btn-info">View Reports</a>
                <button type="button" class="btn btn-secondary" onclick="checkCapacity()">Check Capacity</button>
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
                                <th rowspan="2" style="min-width: 200px;">Product</th>
                                <th rowspan="2" style="text-align: center;">Current<br>Stock</th>
                                <th rowspan="2" style="text-align: center;">Safety<br>Stock</th>
                                <th rowspan="2" style="text-align: center;">Lead<br>Time</th>
                                <?php foreach ($periods as $period): ?>
                                    <th colspan="2" style="text-align: center; min-width: 150px; border-bottom: 1px solid #dee2e6;">
                                        <?= htmlspecialchars($period['period_name']) ?><br>
                                        <small style="color: #666;">
                                            <?= date('M d', strtotime($period['period_start'])) ?>
                                            -
                                            <?= date('M d', strtotime($period['period_end'])) ?>
                                        </small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($periods as $period): ?>
                                    <th style="text-align: center; font-size: 0.85rem; padding: 0.25rem;">Demand</th>
                                    <th style="text-align: center; font-size: 0.85rem; padding: 0.25rem;">Plan</th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php 
                                $currentStock = $inventoryData[$product['id']] ?? 0;
                                $stockClass = $currentStock < $product['safety_stock_qty'] ? 'text-danger' : '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($product['product_code']) ?></strong><br>
                                        <small style="color: #666;"><?= htmlspecialchars($product['name']) ?></small>
                                    </td>
                                    <td style="text-align: center;" class="<?= $stockClass ?>">
                                        <?= number_format($currentStock, 0) ?>
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
                                        $demand = $demandData[$product['id']][$period['id']] ?? 0;
                                        $demandClass = $demand > 0 ? 'font-weight-bold' : 'text-muted';
                                        ?>
                                        <td style="padding: 0.25rem; text-align: center; background-color: #f8f9fa;" class="<?= $demandClass ?>">
                                            <?= $demand > 0 ? number_format($demand, 0) : '-' ?>
                                        </td>
                                        <td style="padding: 0.25rem; text-align: center;">
                                            <input type="number" 
                                                   class="mps-input"
                                                   data-product-id="<?= $product['id'] ?>"
                                                   data-period-id="<?= $period['id'] ?>"
                                                   data-demand="<?= $demand ?>"
                                                   data-current-stock="<?= $currentStock ?>"
                                                   value="<?= $currentValue ?>"
                                                   placeholder="0"
                                                   min="0"
                                                   step="1"
                                                   title="Demand: <?= $demand ?>, Current Stock: <?= $currentStock ?>"
                                                   style="width: 60px; padding: 0.25rem; text-align: center; <?= $demand > 0 && empty($currentValue) ? 'border: 2px solid #ffc107;' : '' ?>">
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
async function checkCapacity() {
    const checkBtn = document.querySelector('button[onclick="checkCapacity()"]');
    const originalText = checkBtn.textContent;
    
    try {
        checkBtn.textContent = 'Checking...';
        checkBtn.disabled = true;
        
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
        
        if (mpsData.length === 0) {
            showMessage('Enter some planned quantities first to check capacity', 'warning');
            return;
        }
        
        // Send to capacity check endpoint
        const response = await fetch('check-capacity.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mps_data: mpsData })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (result.has_issues) {
                let message = `⚠️ Capacity Issues Found (${result.summary.total_issues}):\\n\\n`;
                result.issues.forEach(issue => {
                    message += `• ${issue.period} - ${issue.work_center || 'Product'}: ${issue.issue}\\n`;
                    if (issue.required_hours && issue.available_hours) {
                        message += `  Required: ${issue.required_hours}h, Available: ${issue.available_hours}h\\n`;
                    }
                });
                
                showMessage(message, 'warning');
                showCapacityDetails(result);
            } else {
                let message = '✅ Capacity Check Passed!\\n\\n';
                Object.keys(result.utilization).forEach(period => {
                    const util = result.utilization[period];
                    message += `${period}: ${util.utilization}% utilized (${util.used}/${util.available} hours)\\n`;
                });
                showMessage(message, 'success');
            }
        } else {
            showMessage('Error checking capacity: ' + (result.error || 'Unknown error'), 'danger');
        }
        
    } catch (error) {
        console.error('Capacity check error:', error);
        showMessage('Network error while checking capacity', 'danger');
    } finally {
        checkBtn.textContent = originalText;
        checkBtn.disabled = false;
    }
}

function showCapacityDetails(result) {
    // Create detailed capacity report modal/section
    const detailsHtml = `
        <div class="capacity-details" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 4px;">
            <h4>Capacity Analysis</h4>
            <div class="row">
                <div class="col-md-6">
                    <h5>Utilization by Period</h5>
                    <ul>
                        ${Object.keys(result.utilization).map(period => {
                            const util = result.utilization[period];
                            const statusClass = util.utilization > 100 ? 'text-danger' : 
                                              util.utilization > 80 ? 'text-warning' : 'text-success';
                            return `<li class="${statusClass}">
                                ${period}: ${util.utilization}% (${util.used}/${util.available} hours)
                            </li>`;
                        }).join('')}
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Issues Found</h5>
                    <ul>
                        ${result.issues.map(issue => `
                            <li class="text-danger">
                                <strong>${issue.period}</strong> - ${issue.work_center}: ${issue.issue}
                                ${issue.overrun_hours ? `<br><small>Overrun: ${issue.overrun_hours} hours</small>` : ''}
                            </li>
                        `).join('')}
                    </ul>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" onclick="hideCapacityDetails()">Hide Details</button>
        </div>
    `;
    
    // Remove existing details
    const existing = document.querySelector('.capacity-details');
    if (existing) existing.remove();
    
    // Insert after message
    const messages = document.querySelectorAll('.mps-message');
    if (messages.length > 0) {
        messages[messages.length - 1].insertAdjacentHTML('afterend', detailsHtml);
    }
}

function hideCapacityDetails() {
    const details = document.querySelector('.capacity-details');
    if (details) details.remove();
}

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