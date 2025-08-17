<?php
// Sample Data Generator for MRP/ERP System
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Material.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/BOM.php';
require_once __DIR__ . '/../classes/Inventory.php';

$db = Database::getInstance();

echo "<h1>Creating Sample Data...</h1>";

try {
    $db->beginTransaction();
    
    // Create sample materials
    echo "<p>Creating materials...</p>";
    $materialModel = new Material();
    
    $materials = [
        [
            'material_code' => 'RESIN-PP-001',
            'name' => 'Polypropylene Resin - Natural',
            'description' => 'Virgin PP resin for injection molding',
            'category_id' => 1,
            'material_type' => 'resin',
            'uom_id' => 1, // KG
            'min_stock_qty' => 100,
            'max_stock_qty' => 1000,
            'reorder_point' => 200,
            'lead_time_days' => 14,
            'cost_per_unit' => 2.50,
            'is_lot_controlled' => 1,
            'is_active' => 1
        ],
        [
            'material_code' => 'INSERT-M6-001',
            'name' => 'M6 Threaded Insert - Brass',
            'description' => 'M6 brass threaded insert for plastic molding',
            'category_id' => 3,
            'material_type' => 'insert',
            'uom_id' => 7, // PC
            'min_stock_qty' => 500,
            'max_stock_qty' => 5000,
            'reorder_point' => 1000,
            'lead_time_days' => 7,
            'cost_per_unit' => 0.15,
            'is_lot_controlled' => 1,
            'is_active' => 1
        ],
        [
            'material_code' => 'PKG-BOX-001',
            'name' => 'Cardboard Box - Small',
            'description' => 'Small shipping box for products',
            'category_id' => 2,
            'material_type' => 'packaging',
            'uom_id' => 7, // PC
            'min_stock_qty' => 50,
            'max_stock_qty' => 500,
            'reorder_point' => 100,
            'lead_time_days' => 3,
            'cost_per_unit' => 1.25,
            'is_lot_controlled' => 0,
            'is_active' => 1
        ]
    ];
    
    $materialIds = [];
    foreach ($materials as $material) {
        $materialIds[] = $materialModel->create($material);
    }
    
    // Create sample products
    echo "<p>Creating products...</p>";
    $productModel = new Product();
    
    $products = [
        [
            'product_code' => 'WIDGET-001',
            'name' => 'Plastic Widget Assembly',
            'description' => 'Standard plastic widget with threaded insert',
            'category_id' => 1,
            'uom_id' => 7, // PC
            'weight_kg' => 0.25,
            'cycle_time_seconds' => 45,
            'cavity_count' => 4,
            'min_stock_qty' => 50,
            'max_stock_qty' => 500,
            'safety_stock_qty' => 75,
            'standard_cost' => 5.50,
            'selling_price' => 12.00,
            'is_lot_controlled' => 1,
            'is_active' => 1
        ]
    ];
    
    $productIds = [];
    foreach ($products as $product) {
        $productIds[] = $productModel->create($product);
    }
    
    // Create sample BOM
    echo "<p>Creating BOM...</p>";
    $bomModel = new BOM();
    
    $bomHeaderData = [
        'product_id' => $productIds[0],
        'version' => '1.0',
        'description' => 'Standard BOM for Widget-001',
        'effective_date' => date('Y-m-d'),
        'is_active' => 1,
        'approved_by' => 'System',
        'approved_date' => date('Y-m-d')
    ];
    
    $bomDetails = [
        [
            'material_id' => $materialIds[0], // PP Resin
            'quantity_per' => 0.2, // 200g per widget
            'uom_id' => 1, // KG
            'scrap_percentage' => 5,
            'notes' => 'Main body material'
        ],
        [
            'material_id' => $materialIds[1], // M6 Insert
            'quantity_per' => 2, // 2 inserts per widget
            'uom_id' => 7, // PC
            'scrap_percentage' => 2,
            'notes' => 'Threaded inserts for assembly'
        ],
        [
            'material_id' => $materialIds[2], // Packaging
            'quantity_per' => 0.1, // 1 box per 10 widgets
            'uom_id' => 7, // PC
            'scrap_percentage' => 0,
            'notes' => 'Individual packaging'
        ]
    ];
    
    $bomHeaderId = $bomModel->createBOMWithDetails($bomHeaderData, $bomDetails);
    
    // Create sample inventory
    echo "<p>Creating inventory...</p>";
    $inventoryModel = new Inventory();
    
    $inventoryItems = [
        [
            'item_type' => 'material',
            'item_id' => $materialIds[0], // PP Resin
            'lot_number' => 'LOT-PP-20250815-001',
            'location_id' => 1, // RM-01
            'quantity' => 150.0,
            'reserved_quantity' => 0,
            'uom_id' => 1, // KG
            'manufacture_date' => date('Y-m-d', strtotime('-30 days')),
            'expiry_date' => date('Y-m-d', strtotime('+365 days')),
            'received_date' => date('Y-m-d', strtotime('-5 days')),
            'unit_cost' => 2.50,
            'status' => 'available'
        ],
        [
            'item_type' => 'material',
            'item_id' => $materialIds[1], // M6 Inserts
            'lot_number' => 'LOT-INS-20250810-001',
            'location_id' => 1, // RM-01
            'quantity' => 800.0,
            'reserved_quantity' => 0,
            'uom_id' => 7, // PC
            'received_date' => date('Y-m-d', strtotime('-10 days')),
            'unit_cost' => 0.15,
            'status' => 'available'
        ],
        [
            'item_type' => 'material',
            'item_id' => $materialIds[2], // Packaging
            'lot_number' => 'LOT-PKG-20250812-001',
            'location_id' => 1, // RM-01
            'quantity' => 75.0,
            'reserved_quantity' => 0,
            'uom_id' => 7, // PC
            'received_date' => date('Y-m-d', strtotime('-3 days')),
            'unit_cost' => 1.25,
            'status' => 'available'
        ]
    ];
    
    foreach ($inventoryItems as $item) {
        $inventoryModel->receiveInventory($item);
    }
    
    // Create sample customer order
    echo "<p>Creating customer order...</p>";
    $sql = "INSERT INTO customer_orders (order_number, customer_name, order_date, required_date, notes, status)
            VALUES (?, ?, ?, ?, ?, 'pending')";
    
    $orderId = $db->insert($sql, [
        'ORD-20250816-001',
        'ABC Manufacturing Corp',
        date('Y-m-d'),
        date('Y-m-d', strtotime('+14 days')),
        'Rush order for new product launch'
    ], ['s', 's', 's', 's', 's']);
    
    // Create order details
    $sql = "INSERT INTO customer_order_details (order_id, product_id, quantity, uom_id, unit_price, notes)
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $db->insert($sql, [
        $orderId,
        $productIds[0],
        500, // Order for 500 widgets
        7, // PC
        12.00,
        '500 units needed for customer launch'
    ], ['i', 'i', 'd', 'i', 'd', 's']);
    
    $db->commit();
    
    echo "<div style='background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;'>";
    echo "<h2>✅ Sample Data Created Successfully!</h2>";
    echo "<p><strong>Created:</strong></p>";
    echo "<ul>";
    echo "<li>3 Materials (PP Resin, M6 Inserts, Packaging)</li>";
    echo "<li>1 Product (Plastic Widget Assembly)</li>";
    echo "<li>1 BOM linking the product to materials</li>";
    echo "<li>Inventory stock for all materials</li>";
    echo "<li>1 Customer Order for 500 widgets</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='background: #dbeafe; color: #1e40af; padding: 1rem; border-radius: 0.5rem; margin: 1rem 0;'>";
    echo "<h3>🚀 Next Steps:</h3>";
    echo "<p>1. <a href='index.php'>View the Dashboard</a> - You should now see data in the statistics</p>";
    echo "<p>2. <a href='materials/'>Browse Materials</a> - See the materials we created</p>";
    echo "<p>3. <a href='mrp/run.php?order_id={$orderId}'>Run MRP Calculation</a> - Calculate material requirements for the order</p>";
    echo "</div>";
    
} catch (Exception $e) {
    $db->rollback();
    echo "<div style='background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem;'>";
    echo "<h2>❌ Error Creating Sample Data</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 2rem; }
h1, h2, h3 { color: #333; }
p { margin: 0.5rem 0; }
ul { margin: 0.5rem 0; padding-left: 2rem; }
a { color: #2563eb; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>