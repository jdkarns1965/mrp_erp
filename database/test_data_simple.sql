-- Simple test data for MRP validation
USE mrp_erp;

-- Insert test suppliers
INSERT INTO suppliers (code, name, contact_person, email, phone, payment_terms) VALUES
('SUP001', 'Test Supplier 1', 'John Smith', 'john@test.com', '555-1234', 30),
('SUP002', 'Test Supplier 2', 'Jane Doe', 'jane@test.com', '555-5678', 45);

-- Insert test materials
INSERT INTO materials (material_code, name, description, category_id, material_type, uom_id, 
                      min_stock_qty, max_stock_qty, reorder_point, lead_time_days, 
                      default_supplier_id, cost_per_unit, is_lot_controlled) VALUES
('RES-001', 'ABS Plastic Resin', 'Test ABS resin', 1, 'resin', 1, 500, 2000, 750, 14, 1, 2.50, TRUE),
('RES-002', 'PP Polypropylene', 'Test PP resin', 1, 'resin', 1, 300, 1500, 500, 21, 1, 1.85, TRUE),
('INS-001', 'Brass Insert', 'Test brass insert', 3, 'insert', 7, 1000, 5000, 1500, 10, 2, 0.15, FALSE);

-- Insert test products
INSERT INTO products (product_code, name, description, category_id, uom_id, weight_kg, 
                     cycle_time_seconds, cavity_count, min_stock_qty, max_stock_qty, 
                     safety_stock_qty, standard_cost, selling_price, is_lot_controlled) VALUES
('PROD-001', 'Test Container', 'Simple test container', 1, 7, 0.125, 45, 4, 50, 200, 25, 5.50, 12.99, TRUE),
('PROD-002', 'Test Complex Product', 'Multi-material product', 1, 7, 1.250, 180, 1, 20, 100, 10, 18.75, 49.99, TRUE);

-- Create BOM headers
INSERT INTO bom_headers (product_id, version, description, effective_date, is_active, approved_by, approved_date) VALUES
(1, '1.0', 'BOM for Test Container', '2025-01-01', TRUE, 'Engineering', '2025-01-15'),
(2, '1.0', 'BOM for Complex Product', '2025-01-01', TRUE, 'Engineering', '2025-01-15');

-- Create BOM details
INSERT INTO bom_details (bom_header_id, material_id, quantity_per, uom_id, scrap_percentage) VALUES
(1, 1, 0.120, 1, 5.0),
(2, 1, 1.100, 1, 8.0),
(2, 3, 4, 7, 2.0);

-- Insert inventory
INSERT INTO inventory (item_type, item_id, lot_number, location_id, quantity, uom_id, 
                      manufacture_date, expiry_date, received_date, supplier_id, 
                      po_number, unit_cost, status) VALUES
('material', 1, 'ABS-2025-001', 1, 850.0, 1, '2025-01-15', '2026-01-15', '2025-01-20', 1, 'PO-001', 2.45, 'available'),
('material', 2, 'PP-2025-001', 1, 200.0, 1, '2025-02-01', '2026-02-01', '2025-02-05', 1, 'PO-002', 1.80, 'available'),
('material', 3, 'INS-2025-001', 1, 2500.0, 7, NULL, NULL, '2025-01-25', 2, 'PO-003', 0.14, 'available');

-- Insert test orders
INSERT INTO customer_orders (order_number, customer_name, order_date, required_date, status, notes) VALUES
('SO-001', 'Test Customer', '2025-08-18', '2025-08-30', 'confirmed', 'Test order for validation'),
('SO-002', 'Large Customer', '2025-08-18', '2025-09-15', 'confirmed', 'Large test order');

-- Insert order details  
INSERT INTO customer_order_details (order_id, product_id, quantity, uom_id, unit_price) VALUES
(1, 1, 25.0, 7, 12.99),
(2, 2, 35.0, 7, 49.99);

COMMIT;