<?php
session_start();
require_once 'classes/Database.php';
require_once 'classes/BOM.php';
require_once 'classes/Product.php';

$db = Database::getInstance();
$bomModel = new BOM();

echo "<h2>MRP Debug for Order IMP-20250816-045</h2>";

// Get the order details
echo "<h3>1. Order Details:</h3>";
$order = $db->selectOne("
    SELECT co.*, cod.product_id, cod.quantity, p.product_code, p.name as product_name
    FROM customer_orders co
    JOIN customer_order_details cod ON co.id = cod.order_id  
    JOIN products p ON cod.product_id = p.id
    WHERE co.order_number = 'IMP-20250816-045'
");

if ($order) {
    echo "<p>Order ID: {$order['id']}</p>";
    echo "<p>Product: {$order['product_code']} - {$order['product_name']}</p>";
    echo "<p>Quantity: {$order['quantity']}</p>";
    
    // Test BOM explosion for this specific product
    echo "<h3>2. BOM Explosion Test:</h3>";
    $productId = $order['product_id'];
    $quantity = $order['quantity'];
    
    echo "<p>Testing explodeBOM({$productId}, {$quantity})</p>";
    
    $bomExplosion = $bomModel->explodeBOM($productId, $quantity);
    
    if (empty($bomExplosion)) {
        echo "<p style='color: red;'>❌ No BOM explosion results!</p>";
        
        // Check if there's an active BOM
        $activeBOM = $bomModel->getActiveBOM($productId);
        if ($activeBOM) {
            echo "<p>✅ Active BOM found: ID {$activeBOM['id']}, Version {$activeBOM['version']}</p>";
        } else {
            echo "<p style='color: red;'>❌ No active BOM found!</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ BOM explosion successful!</p>";
        echo "<table border='1'>";
        echo "<tr><th>Material Code</th><th>Material Name</th><th>Qty per Unit</th><th>Total Required</th><th>Cost</th></tr>";
        foreach ($bomExplosion as $item) {
            echo "<tr>";
            echo "<td>{$item['material_code']}</td>";
            echo "<td>{$item['material_name']}</td>";
            echo "<td>{$item['quantity_per']}</td>";
            echo "<td>{$item['total_required']}</td>";
            echo "<td>\${$item['cost_per_unit']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check what the MRP class would do
    echo "<h3>3. MRP Engine Test:</h3>";
    require_once 'classes/MRP.php';
    $mrpEngine = new MRP();
    
    try {
        $results = $mrpEngine->runMRP($order['id']);
        echo "<p>✅ MRP calculation completed</p>";
        echo "<pre>" . print_r($results, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ MRP Error: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>Order IMP-20250816-045 not found!</p>";
}
?>