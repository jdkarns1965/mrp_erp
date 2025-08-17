<?php
session_start();
require_once 'classes/Database.php';

$db = Database::getInstance();

echo "<h2>Clear Delivery Schedule Data</h2>";

if ($_POST && isset($_POST['confirm'])) {
    try {
        $db->beginTransaction();
        
        // Delete order details first (foreign key constraint)
        $detailsDeleted = $db->delete("DELETE FROM customer_order_details", [], []);
        echo "<p>✅ Deleted {$detailsDeleted} order detail records</p>";
        
        // Delete orders
        $ordersDeleted = $db->delete("DELETE FROM customer_orders", [], []);
        echo "<p>✅ Deleted {$ordersDeleted} customer orders</p>";
        
        // Clear any MRP calculations
        $mrpDeleted = $db->delete("DELETE FROM mrp_requirements", [], []);
        echo "<p>✅ Deleted {$mrpDeleted} MRP calculation records</p>";
        
        $db->commit();
        echo "<div style='color: green; font-weight: bold; margin: 20px 0;'>✅ All delivery schedule data cleared successfully!</div>";
        
        echo "<p><a href='public/orders/create.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Create New Order</a></p>";
        
    } catch (Exception $e) {
        $db->rollback();
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
} else {
    // Show confirmation form
    echo "<div style='background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 4px; margin: 20px 0;'>";
    echo "<h3>⚠️ Warning</h3>";
    echo "<p>This will permanently delete:</p>";
    echo "<ul>";
    echo "<li>All customer orders (including IMP-20250816-045)</li>";
    echo "<li>All order details</li>";
    echo "<li>All MRP calculation results</li>";
    echo "</ul>";
    echo "<p><strong>This cannot be undone!</strong></p>";
    echo "</div>";
    
    echo "<form method='POST'>";
    echo "<button type='submit' name='confirm' value='1' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>Yes, Delete All Order Data</button> ";
    echo "<a href='public/' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Cancel</a>";
    echo "</form>";
}
?>