-- Test Data for MRP/ERP System
-- Comprehensive test scenarios for Phase 1 validation
-- Date: 2025-08-18

USE mrp_erp;

-- Insert test suppliers
INSERT INTO suppliers (code, name, contact_person, email, phone, payment_terms) VALUES
('SUP001', 'Acme Plastics Inc.', 'John Smith', 'john@acmeplastics.com', '555-1234', 30),
('SUP002', 'Quality Components Ltd.', 'Sarah Johnson', 'sarah@qualitycomp.com', '555-5678', 45),
('SUP003', 'Packaging Solutions Corp.', 'Mike Davis', 'mike@packagingsol.com', '555-9012', 30),
('SUP004', 'Metal Inserts Co.', 'Lisa Chen', 'lisa@metalinserts.com', '555-3456', 60);

-- Insert test materials with realistic manufacturing scenarios
INSERT INTO materials (material_code, name, description, category_id, material_type, uom_id, 
                      min_stock_qty, max_stock_qty, reorder_point, lead_time_days, 
                      default_supplier_id, cost_per_unit, is_lot_controlled) VALUES
-- Resins (main materials)
('RES-001', 'ABS Plastic Resin', 'High-impact ABS resin for injection molding', 1, 'resin', 1, 500, 2000, 750, 14, 1, 2.50, TRUE),
('RES-002', 'PP Polypropylene', 'Food-grade polypropylene resin', 1, 'resin', 1, 300, 1500, 500, 21, 1, 1.85, TRUE),
('RES-003', 'PET Plastic Resin', 'Crystal clear PET for bottles', 1, 'resin', 1, 400, 1800, 600, 18, 1, 3.20, TRUE),
-- Metal inserts
('INS-001', 'Brass Thread Insert M6', 'Brass threaded insert 6mm', 3, 'insert', 7, 1000, 5000, 1500, 10, 4, 0.15, FALSE),
('INS-002', 'Steel Pin Insert 4mm', 'Hardened steel pin insert', 3, 'insert', 7, 2000, 8000, 3000, 12, 4, 0.08, FALSE),
-- Packaging materials
('PKG-001', 'Cardboard Box Small', 'Small shipping box 10x8x6', 2, 'packaging', 7, 100, 500, 200, 7, 3, 1.25, FALSE),
('PKG-002', 'Bubble Wrap Roll', 'Protective bubble wrap 24" wide', 2, 'packaging', 11, 10, 50, 20, 5, 3, 15.00, FALSE),
('PKG-003', 'Shipping Labels', 'Adhesive shipping labels 4x6', 2, 'packaging', 7, 500, 2000, 1000, 3, 3, 0.05, FALSE),
-- Components
('CMP-001', 'Rubber Gasket Type A', 'Silicone rubber gasket', 3, 'component', 7, 200, 1000, 400, 14, 2, 0.75, TRUE),
('CMP-002', 'Spring Assembly', 'Compression spring with guide', 3, 'component', 7, 100, 500, 200, 21, 2, 2.30, FALSE);

-- Insert test products with different complexity levels
INSERT INTO products (product_code, name, description, category_id, uom_id, weight_kg, 
                     cycle_time_seconds, cavity_count, min_stock_qty, max_stock_qty, 
                     safety_stock_qty, standard_cost, selling_price, is_lot_controlled) VALUES
-- Simple single-material products
('PROD-001', 'Plastic Container Small', 'Small storage container with lid', 1, 7, 0.125, 45, 4, 50, 200, 25, 5.50, 12.99, TRUE),
('PROD-002', 'Food Storage Bowl', 'Microwave-safe food bowl', 1, 7, 0.200, 60, 2, 30, 150, 15, 4.25, 9.99, TRUE),
-- Complex multi-material products
('PROD-003', 'Premium Tool Case', 'Professional tool case with inserts', 1, 7, 1.250, 180, 1, 20, 100, 10, 18.75, 49.99, TRUE),
('PROD-004', 'Medical Device Housing', 'Precision housing for medical equipment', 1, 7, 0.750, 120, 1, 10, 50, 5, 25.40, 89.99, TRUE);

-- Create BOM headers for products
INSERT INTO bom_headers (product_id, version, description, effective_date, is_active, approved_by, approved_date) VALUES
(1, '1.0', 'Initial BOM for Plastic Container Small', '2025-01-01', TRUE, 'Engineering', '2025-01-15'),
(2, '1.0', 'Initial BOM for Food Storage Bowl', '2025-01-01', TRUE, 'Engineering', '2025-01-15'),
(3, '1.0', 'Initial BOM for Premium Tool Case', '2025-01-01', TRUE, 'Engineering', '2025-01-20'),
(4, '1.0', 'Initial BOM for Medical Device Housing', '2025-01-01', TRUE, 'Engineering', '2025-01-25');

-- Create BOM details with realistic material requirements
INSERT INTO bom_details (bom_header_id, material_id, quantity_per, uom_id, scrap_percentage, notes) VALUES
-- PROD-001: Simple container (ABS resin only)
(1, 1, 0.120, 1, 5.0, 'Main body material with 5% scrap allowance'),
-- PROD-002: Food bowl (PP resin only)
(2, 2, 0.185, 1, 3.0, 'Food-grade material with minimal scrap'),
-- PROD-003: Premium tool case (complex multi-material)
(3, 1, 1.100, 1, 8.0, 'Main case body - ABS resin'),
(3, 4, 4, 7, 2.0, 'Brass inserts for mounting points'),
(3, 9, 1, 7, 0.0, 'Rubber gasket for weather sealing'),
(3, 6, 1, 7, 0.0, 'Packaging box'),
(3, 8, 1, 7, 0.0, 'Shipping labels'),
-- PROD-004: Medical device (precision multi-material)
(4, 3, 0.680, 1, 2.0, 'Medical-grade PET resin'),
(4, 5, 2, 7, 1.0, 'Steel pin inserts for precision alignment'),
(4, 10, 1, 7, 0.0, 'Spring assembly for mechanism'),
(4, 6, 1, 7, 0.0, 'Protective packaging'),
(4, 7, 1, 11, 0.0, 'Bubble wrap protection');

-- Insert realistic inventory data with various scenarios
INSERT INTO inventory (item_type, item_id, lot_number, location_id, quantity, uom_id, 
                      manufacture_date, expiry_date, received_date, supplier_id, 
                      po_number, unit_cost, status) VALUES
-- Materials inventory - some with adequate stock, some below reorder
('material', 1, 'ABS-2025-001', 1, 850.0, 1, '2025-01-15', '2026-01-15', '2025-01-20', 1, 'PO-2025-001', 2.45, 'available'),
('material', 2, 'PP-2025-001', 1, 200.0, 1, '2025-02-01', '2026-02-01', '2025-02-05', 1, 'PO-2025-002', 1.80, 'available'), -- Below reorder
('material', 3, 'PET-2025-001', 1, 750.0, 1, '2025-01-10', '2026-01-10', '2025-01-15', 1, 'PO-2025-003', 3.15, 'available'),
-- Metal inserts
('material', 4, 'BRASS-2025-001', 1, 2500.0, 7, NULL, NULL, '2025-01-25', 4, 'PO-2025-004', 0.14, 'available'),
('material', 5, 'STEEL-2025-001', 1, 800.0, 7, NULL, NULL, '2025-02-01', 4, 'PO-2025-005', 0.07, 'available'), -- Below reorder
-- Packaging materials
('material', 6, 'BOX-2025-001', 1, 75.0, 7, NULL, NULL, '2025-02-10', 3, 'PO-2025-006', 1.20, 'available'), -- Below reorder
('material', 7, 'BUBBLE-2025-001', 1, 25.0, 11, NULL, NULL, '2025-01-30', 3, 'PO-2025-007', 14.50, 'available'),
('material', 8, 'LABEL-2025-001', 1, 1500.0, 7, NULL, NULL, '2025-02-05', 3, 'PO-2025-008', 0.04, 'available'),
-- Components
('material', 9, 'GASKET-2025-001', 1, 150.0, 7, '2025-01-20', '2027-01-20', '2025-01-25', 2, 'PO-2025-009', 0.72, 'available'), -- Below reorder
('material', 10, 'SPRING-2025-001', 1, 250.0, 7, NULL, NULL, '2025-02-01', 2, 'PO-2025-010', 2.25, 'available'),
-- Products inventory
('product', 1, 'CONT-2025-001', 4, 75.0, 7, '2025-02-15', NULL, '2025-02-15', NULL, 'PROD-001', 5.50, 'available'),
('product', 2, 'BOWL-2025-001', 4, 45.0, 7, '2025-02-10', NULL, '2025-02-10', NULL, 'PROD-002', 4.25, 'available'),
('product', 3, 'CASE-2025-001', 4, 15.0, 7, '2025-02-01', NULL, '2025-02-01', NULL, 'PROD-003', 18.75, 'available'), -- Below safety stock
('product', 4, 'HOUSING-2025-001', 4, 8.0, 7, '2025-01-25', NULL, '2025-01-25', NULL, 'PROD-004', 25.40, 'available'); -- Below safety stock

-- Insert test customer orders for MRP scenarios
INSERT INTO customer_orders (order_number, customer_name, order_date, required_date, status, notes) VALUES
('SO-2025-001', 'ABC Manufacturing Co.', '2025-08-18', '2025-08-30', 'confirmed', 'Standard production order'),
('SO-2025-002', 'XYZ Medical Devices', '2025-08-18', '2025-09-05', 'confirmed', 'Medical device order - priority'),
('SO-2025-003', 'Global Tools Inc.', '2025-08-18', '2025-09-15', 'pending', 'Large quantity order'),
('SO-2025-004', 'Emergency Order Corp.', '2025-08-18', '2025-08-25', 'confirmed', 'Rush order - test shortage scenario');

-- Insert customer order details with various scenarios
INSERT INTO customer_order_details (order_id, product_id, quantity, uom_id, unit_price, notes) VALUES
-- Order 1: Standard order that can be fulfilled
(1, 1, 25.0, 7, 12.99, 'Standard container order'),
(1, 2, 15.0, 7, 9.99, 'Food bowl order'),
-- Order 2: Medical device order requiring special materials
(2, 4, 8.0, 7, 89.99, 'Medical housing units'),
-- Order 3: Large order testing capacity
(3, 3, 35.0, 7, 49.99, 'Tool case bulk order'),
(3, 1, 100.0, 7, 12.99, 'Container bulk order'),
-- Order 4: Rush order creating material shortages
(4, 3, 20.0, 7, 49.99, 'Rush tool case order'),
(4, 4, 15.0, 7, 89.99, 'Rush medical housing order');

-- Insert inventory transactions for audit trail testing
INSERT INTO inventory_transactions (transaction_type, transaction_date, item_type, item_id, 
                                  lot_number, to_location_id, quantity, uom_id, 
                                  reference_type, reference_number, notes, performed_by) VALUES
('receipt', '2025-01-20 10:00:00', 'material', 1, 'ABS-2025-001', 1, 850.0, 1, 'PO', 'PO-2025-001', 'Initial stock receipt', 'Warehouse Staff'),
('receipt', '2025-02-05 14:30:00', 'material', 2, 'PP-2025-001', 1, 200.0, 1, 'PO', 'PO-2025-002', 'PP resin delivery', 'Warehouse Staff'),
('receipt', '2025-01-15 09:15:00', 'material', 3, 'PET-2025-001', 1, 750.0, 1, 'PO', 'PO-2025-003', 'PET resin stock', 'Warehouse Staff'),
('receipt', '2025-02-15 16:00:00', 'product', 1, 'CONT-2025-001', 4, 75.0, 7, 'Production', 'PROD-001', 'Container production completion', 'Production Manager'),
('receipt', '2025-02-10 11:30:00', 'product', 2, 'BOWL-2025-001', 4, 45.0, 7, 'Production', 'PROD-002', 'Bowl production batch', 'Production Manager');

-- Insert some expiring inventory for testing
INSERT INTO inventory (item_type, item_id, lot_number, location_id, quantity, uom_id, 
                      manufacture_date, expiry_date, received_date, supplier_id, 
                      po_number, unit_cost, status) VALUES
('material', 1, 'ABS-2025-EXPIRING', 1, 45.0, 1, '2024-09-01', '2025-09-01', '2024-09-05', 1, 'PO-2024-999', 2.40, 'available'),
('material', 9, 'GASKET-2024-OLD', 1, 25.0, 7, '2024-02-01', '2025-09-15', '2024-02-05', 2, 'PO-2024-888', 0.70, 'available');

COMMIT;