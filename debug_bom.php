<?php
session_start();
require_once 'classes/Database.php';
require_once 'classes/BOM.php';

$db = Database::getInstance();
$bomModel = new BOM();

echo "<h2>BOM Debug for Product 20638</h2>";

// Check all BOMs for product 20638
echo "<h3>All BOMs for Product 20638:</h3>";
$boms = $db->select("
    SELECT bh.*, p.product_code, p.name as product_name
    FROM bom_headers bh
    JOIN products p ON bh.product_id = p.id
    WHERE p.product_code = '20638'
    ORDER BY bh.id DESC
");

if (empty($boms)) {
    echo "<p>No BOMs found for product 20638</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Version</th><th>Active</th><th>Effective Date</th><th>Updated At</th></tr>";
    foreach ($boms as $bom) {
        echo "<tr>";
        echo "<td>{$bom['id']}</td>";
        echo "<td>{$bom['version']}</td>";
        echo "<td>" . ($bom['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$bom['effective_date']}</td>";
        echo "<td>{$bom['updated_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check active BOM
echo "<h3>Active BOM for Product 20638:</h3>";
$productResult = $db->selectOne("SELECT id FROM products WHERE product_code = '20638'");
if ($productResult) {
    $productId = $productResult['id'];
    $activeBOM = $bomModel->getActiveBOM($productId);
    
    if ($activeBOM) {
        echo "<p>Active BOM ID: {$activeBOM['id']}, Version: {$activeBOM['version']}</p>";
        
        // Get BOM details
        echo "<h3>BOM Details for Active BOM:</h3>";
        $details = $bomModel->getBOMDetails($activeBOM['id']);
        
        if (empty($details)) {
            echo "<p>No materials found in this BOM</p>";
        } else {
            echo "<table border='1'>";
            echo "<tr><th>Material Code</th><th>Material Name</th><th>Quantity</th><th>UOM</th><th>Cost</th></tr>";
            foreach ($details as $detail) {
                echo "<tr>";
                echo "<td>{$detail['material_code']}</td>";
                echo "<td>{$detail['material_name']}</td>";
                echo "<td>{$detail['quantity_per']}</td>";
                echo "<td>{$detail['uom_code']}</td>";
                echo "<td>\${$detail['cost_per_unit']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p>No active BOM found for product 20638</p>";
    }
} else {
    echo "<p>Product 20638 not found in database</p>";
}

// Check all materials in bom_details table
echo "<h3>All BOM Details for Product 20638 BOMs:</h3>";
$allDetails = $db->select("
    SELECT bd.*, m.material_code, m.name as material_name, bh.version, bh.is_active
    FROM bom_details bd
    JOIN bom_headers bh ON bd.bom_header_id = bh.id
    JOIN products p ON bh.product_id = p.id
    JOIN materials m ON bd.material_id = m.id
    WHERE p.product_code = '20638'
    ORDER BY bh.id, bd.id
");

if (empty($allDetails)) {
    echo "<p>No BOM details found</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>BOM ID</th><th>Version</th><th>Active</th><th>Material Code</th><th>Material Name</th><th>Quantity</th></tr>";
    foreach ($allDetails as $detail) {
        echo "<tr>";
        echo "<td>{$detail['bom_header_id']}</td>";
        echo "<td>{$detail['version']}</td>";
        echo "<td>" . ($detail['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$detail['material_code']}</td>";
        echo "<td>{$detail['material_name']}</td>";
        echo "<td>{$detail['quantity_per']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>