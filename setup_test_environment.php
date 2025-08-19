<?php
/**
 * Setup Test Environment Script
 * Creates minimal test data for MRP validation
 */

require_once 'classes/Database.php';

try {
    $db = Database::getInstance();
    
    echo "Setting up test environment...\n";
    
    // Insert test suppliers
    $suppliersAdded = 0;
    $suppliers = [
        ['SUP001', 'Test Supplier 1', 'John Smith', 'john@test.com', '555-1234', 30],
        ['SUP002', 'Test Supplier 2', 'Jane Doe', 'jane@test.com', '555-5678', 45]
    ];
    
    foreach ($suppliers as $supplier) {
        try {
            $sql = "INSERT INTO suppliers (code, name, contact_person, email, phone, payment_terms) VALUES (?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $supplier, ['s', 's', 's', 's', 's', 'i']);
            $suppliersAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $suppliersAdded suppliers\n";
    
    // Insert test materials
    $materialsAdded = 0;
    $materials = [
        ['RES-002', 'PP Polypropylene', 'Test PP resin', 1, 'resin', 1, 300, 1500, 500, 21, 1, 1.85, true],
        ['INS-001', 'Brass Insert', 'Test brass insert', 3, 'insert', 7, 1000, 5000, 1500, 10, 2, 0.15, false],
        ['PKG-001', 'Test Box', 'Test packaging box', 2, 'packaging', 7, 100, 500, 200, 7, 1, 1.25, false]
    ];
    
    foreach ($materials as $material) {
        try {
            $sql = "INSERT INTO materials (material_code, name, description, category_id, material_type, uom_id, 
                    min_stock_qty, max_stock_qty, reorder_point, lead_time_days, default_supplier_id, cost_per_unit, is_lot_controlled) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $material, ['s', 's', 's', 'i', 's', 'i', 'd', 'd', 'd', 'i', 'i', 'd', 'i']);
            $materialsAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $materialsAdded materials\n";
    
    // Insert test products
    $productsAdded = 0;
    $products = [
        ['PROD-002', 'Test Complex Product', 'Multi-material product', 1, 7, 1.250, 180, 1, 20, 100, 10, 18.75, 49.99, true]
    ];
    
    foreach ($products as $product) {
        try {
            $sql = "INSERT INTO products (product_code, name, description, category_id, uom_id, weight_kg, 
                    cycle_time_seconds, cavity_count, min_stock_qty, max_stock_qty, 
                    safety_stock_qty, standard_cost, selling_price, is_lot_controlled) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $product, ['s', 's', 's', 'i', 'i', 'd', 'i', 'i', 'd', 'd', 'd', 'd', 'd', 'i']);
            $productsAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $productsAdded products\n";
    
    // Create BOM headers
    $bomAdded = 0;
    $boms = [
        [1, '1.0', 'BOM for Container', '2025-01-01', true, 'Engineering', '2025-01-15'],
        [2, '1.0', 'BOM for Complex Product', '2025-01-01', true, 'Engineering', '2025-01-15']
    ];
    
    foreach ($boms as $bom) {
        try {
            $sql = "INSERT INTO bom_headers (product_id, version, description, effective_date, is_active, approved_by, approved_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $bom, ['i', 's', 's', 's', 'i', 's', 's']);
            $bomAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $bomAdded BOM headers\n";
    
    // Create BOM details
    $bomDetailsAdded = 0;
    $bomDetails = [
        [1, 1, 0.120, 1, 5.0],
        [2, 1, 1.100, 1, 8.0],
        [2, 2, 4, 7, 2.0]
    ];
    
    foreach ($bomDetails as $detail) {
        try {
            $sql = "INSERT INTO bom_details (bom_header_id, material_id, quantity_per, uom_id, scrap_percentage) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->insert($sql, $detail, ['i', 'i', 'd', 'i', 'd']);
            $bomDetailsAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $bomDetailsAdded BOM details\n";
    
    // Insert inventory
    $inventoryAdded = 0;
    $inventory = [
        ['material', 1, 'ABS-2025-001', 1, 850.0, 1, '2025-01-15', '2026-01-15', '2025-01-20', 1, 'PO-001', 2.45, 'available'],
        ['material', 2, 'PP-2025-001', 1, 200.0, 1, '2025-02-01', '2026-02-01', '2025-02-05', 1, 'PO-002', 1.80, 'available'],
        ['material', 3, 'INS-2025-001', 1, 2500.0, 7, null, null, '2025-01-25', 2, 'PO-003', 0.14, 'available']
    ];
    
    foreach ($inventory as $inv) {
        try {
            $sql = "INSERT INTO inventory (item_type, item_id, lot_number, location_id, quantity, uom_id, 
                    manufacture_date, expiry_date, received_date, supplier_id, po_number, unit_cost, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $inv, ['s', 'i', 's', 'i', 'd', 'i', 's', 's', 's', 'i', 's', 'd', 's']);
            $inventoryAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $inventoryAdded inventory records\n";
    
    // Insert test orders
    $ordersAdded = 0;
    $orders = [
        ['SO-001', 'Test Customer', '2025-08-18', '2025-08-30', 'confirmed', 'Test order'],
        ['SO-002', 'Large Customer', '2025-08-18', '2025-09-15', 'confirmed', 'Large test order']
    ];
    
    foreach ($orders as $order) {
        try {
            $sql = "INSERT INTO customer_orders (order_number, customer_name, order_date, required_date, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $db->insert($sql, $order, ['s', 's', 's', 's', 's', 's']);
            $ordersAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $ordersAdded customer orders\n";
    
    // Insert order details
    $orderDetailsAdded = 0;
    $orderDetails = [
        [1, 1, 25.0, 7, 12.99],
        [2, 2, 35.0, 7, 49.99]
    ];
    
    foreach ($orderDetails as $detail) {
        try {
            $sql = "INSERT INTO customer_order_details (order_id, product_id, quantity, uom_id, unit_price) 
                    VALUES (?, ?, ?, ?, ?)";
            $db->insert($sql, $detail, ['i', 'i', 'd', 'i', 'd']);
            $orderDetailsAdded++;
        } catch (Exception $e) {
            // Skip if already exists
        }
    }
    echo "Added $orderDetailsAdded order details\n";
    
    echo "\nTest environment setup complete!\n";
    echo "Summary:\n";
    echo "- Materials: " . ($materialsAdded + 1) . " (including existing RES-001)\n";
    echo "- Products: " . ($productsAdded + 1) . " (including existing PROD-001)\n";
    echo "- Orders: $ordersAdded\n";
    echo "- Ready for MRP testing\n";
    
} catch (Exception $e) {
    echo "Error setting up test environment: " . $e->getMessage() . "\n";
    exit(1);
}
?>