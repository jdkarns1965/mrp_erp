-- MRP/ERP Database Schema
-- Version: 1.0
-- Author: System
-- Date: 2025-08-16
-- Description: Normalized database schema for MRP/ERP manufacturing system

-- Drop database if exists and create new
DROP DATABASE IF EXISTS mrp_erp;
CREATE DATABASE mrp_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mrp_erp;

-- =====================================================
-- LOOKUP/REFERENCE TABLES (for normalization)
-- =====================================================

-- Unit of Measure lookup table
CREATE TABLE units_of_measure (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    description VARCHAR(50) NOT NULL,
    type ENUM('weight', 'volume', 'count', 'length', 'area') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_uom_type (type)
) ENGINE=InnoDB;

-- Material categories (normalized from materials table)
CREATE TABLE material_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Product categories (normalized from products table)
CREATE TABLE product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Warehouse/Location master
CREATE TABLE warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_warehouse_active (is_active)
) ENGINE=InnoDB;

-- Storage locations within warehouses
CREATE TABLE storage_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL,
    description VARCHAR(100),
    location_type ENUM('raw_material', 'wip', 'finished_goods', 'quarantine') DEFAULT 'raw_material',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_location (warehouse_id, code),
    INDEX idx_location_type (location_type),
    INDEX idx_location_active (is_active)
) ENGINE=InnoDB;

-- Suppliers (for future purchasing module)
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    payment_terms INT DEFAULT 30 COMMENT 'Days',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_active (is_active)
) ENGINE=InnoDB;

-- =====================================================
-- CORE TABLES
-- =====================================================

-- Materials table (raw materials, components, packaging)
CREATE TABLE materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT UNSIGNED,
    material_type ENUM('resin', 'insert', 'packaging', 'component', 'consumable', 'other') NOT NULL,
    uom_id INT UNSIGNED NOT NULL,
    min_stock_qty DECIMAL(15,4) DEFAULT 0,
    max_stock_qty DECIMAL(15,4) DEFAULT 0,
    reorder_point DECIMAL(15,4) DEFAULT 0,
    lead_time_days INT DEFAULT 0,
    default_supplier_id INT UNSIGNED,
    cost_per_unit DECIMAL(15,4) DEFAULT 0,
    is_lot_controlled BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES material_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    FOREIGN KEY (default_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_material_type (material_type),
    INDEX idx_material_active (is_active),
    INDEX idx_material_deleted (deleted_at)
) ENGINE=InnoDB;

-- Products table (finished goods)
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT UNSIGNED,
    uom_id INT UNSIGNED NOT NULL,
    weight_kg DECIMAL(10,4),
    cycle_time_seconds INT,
    cavity_count INT DEFAULT 1,
    min_stock_qty DECIMAL(15,4) DEFAULT 0,
    max_stock_qty DECIMAL(15,4) DEFAULT 0,
    safety_stock_qty DECIMAL(15,4) DEFAULT 0,
    standard_cost DECIMAL(15,4) DEFAULT 0,
    selling_price DECIMAL(15,4) DEFAULT 0,
    is_lot_controlled BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    INDEX idx_product_active (is_active),
    INDEX idx_product_deleted (deleted_at)
) ENGINE=InnoDB;

-- Bill of Materials (BOM) header
CREATE TABLE bom_headers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    version VARCHAR(10) NOT NULL DEFAULT '1.0',
    description TEXT,
    effective_date DATE NOT NULL,
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    approved_by VARCHAR(100),
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_bom (product_id, version),
    INDEX idx_bom_active (is_active),
    INDEX idx_bom_dates (effective_date, expiry_date)
) ENGINE=InnoDB;

-- BOM details (components/materials for each product)
CREATE TABLE bom_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bom_header_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    quantity_per DECIMAL(15,6) NOT NULL COMMENT 'Quantity needed per unit of finished product',
    uom_id INT UNSIGNED NOT NULL,
    scrap_percentage DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bom_header_id) REFERENCES bom_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_bom_material (bom_header_id, material_id),
    INDEX idx_material_lookup (material_id)
) ENGINE=InnoDB;

-- Inventory table (current stock levels with lot tracking)
CREATE TABLE inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('material', 'product') NOT NULL,
    item_id INT UNSIGNED NOT NULL COMMENT 'References either materials.id or products.id',
    lot_number VARCHAR(50) NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    reserved_quantity DECIMAL(15,4) DEFAULT 0,
    uom_id INT UNSIGNED NOT NULL,
    manufacture_date DATE,
    expiry_date DATE,
    received_date DATE NOT NULL,
    supplier_id INT UNSIGNED,
    po_number VARCHAR(50),
    unit_cost DECIMAL(15,4),
    status ENUM('available', 'reserved', 'quarantine', 'expired', 'consumed') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES storage_locations(id) ON DELETE RESTRICT,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_item_type_id (item_type, item_id),
    INDEX idx_lot_number (lot_number),
    INDEX idx_status (status),
    INDEX idx_location (location_id),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB;

-- Inventory transactions (audit trail)
CREATE TABLE inventory_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('receipt', 'issue', 'adjustment', 'transfer', 'return', 'scrap') NOT NULL,
    transaction_date DATETIME NOT NULL,
    item_type ENUM('material', 'product') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    lot_number VARCHAR(50),
    from_location_id INT UNSIGNED,
    to_location_id INT UNSIGNED,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT UNSIGNED NOT NULL,
    reference_type VARCHAR(50) COMMENT 'PO, Production Order, Sales Order, etc.',
    reference_number VARCHAR(50),
    notes TEXT,
    performed_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_location_id) REFERENCES storage_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (to_location_id) REFERENCES storage_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_item_lookup (item_type, item_id),
    INDEX idx_reference (reference_type, reference_number)
) ENGINE=InnoDB;

-- Customer orders (drives MRP calculations)
CREATE TABLE customer_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_name VARCHAR(100) NOT NULL,
    order_date DATE NOT NULL,
    required_date DATE NOT NULL,
    status ENUM('pending', 'confirmed', 'in_production', 'completed', 'shipped', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_status (status),
    INDEX idx_order_dates (order_date, required_date)
) ENGINE=InnoDB;

-- Customer order details
CREATE TABLE customer_order_details (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT UNSIGNED NOT NULL,
    unit_price DECIMAL(15,4),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (uom_id) REFERENCES units_of_measure(id) ON DELETE RESTRICT,
    INDEX idx_order_product (order_id, product_id)
) ENGINE=InnoDB;

-- MRP calculation results (material requirements)
CREATE TABLE mrp_requirements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    calculation_date DATETIME NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    gross_requirement DECIMAL(15,4) NOT NULL,
    available_stock DECIMAL(15,4) NOT NULL,
    net_requirement DECIMAL(15,4) NOT NULL,
    suggested_order_qty DECIMAL(15,4),
    suggested_order_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    INDEX idx_calculation_date (calculation_date),
    INDEX idx_order_lookup (order_id)
) ENGINE=InnoDB;

-- =====================================================
-- INITIAL DATA
-- =====================================================

-- Insert default units of measure
INSERT INTO units_of_measure (code, description, type) VALUES
('KG', 'Kilogram', 'weight'),
('G', 'Gram', 'weight'),
('LB', 'Pound', 'weight'),
('L', 'Liter', 'volume'),
('ML', 'Milliliter', 'volume'),
('GAL', 'Gallon', 'volume'),
('PC', 'Piece', 'count'),
('EA', 'Each', 'count'),
('BOX', 'Box', 'count'),
('CASE', 'Case', 'count'),
('M', 'Meter', 'length'),
('CM', 'Centimeter', 'length'),
('IN', 'Inch', 'length'),
('FT', 'Feet', 'length'),
('SQM', 'Square Meter', 'area'),
('SQFT', 'Square Feet', 'area');

-- Insert default material categories
INSERT INTO material_categories (name, description) VALUES
('Raw Materials', 'Primary materials used in production'),
('Packaging', 'Packaging materials'),
('Components', 'Purchased components and parts'),
('Consumables', 'Consumable supplies');

-- Insert default product categories
INSERT INTO product_categories (name, description) VALUES
('Finished Goods', 'Completed products ready for sale'),
('Semi-Finished', 'Partially completed products'),
('Assemblies', 'Product assemblies');

-- Insert default warehouse
INSERT INTO warehouses (code, name, address) VALUES
('MAIN', 'Main Warehouse', '123 Industrial Ave');

-- Insert default storage locations
INSERT INTO storage_locations (warehouse_id, code, description, location_type) VALUES
(1, 'RM-01', 'Raw Material Storage 1', 'raw_material'),
(1, 'RM-02', 'Raw Material Storage 2', 'raw_material'),
(1, 'WIP-01', 'Work in Progress Area 1', 'wip'),
(1, 'FG-01', 'Finished Goods Storage 1', 'finished_goods'),
(1, 'QC-01', 'Quality Control Quarantine', 'quarantine');

-- Create views for easier querying
CREATE VIEW v_current_inventory AS
SELECT 
    i.item_type,
    i.item_id,
    CASE 
        WHEN i.item_type = 'material' THEN m.material_code
        WHEN i.item_type = 'product' THEN p.product_code
    END AS item_code,
    CASE 
        WHEN i.item_type = 'material' THEN m.name
        WHEN i.item_type = 'product' THEN p.name
    END AS item_name,
    SUM(i.quantity - i.reserved_quantity) AS available_quantity,
    SUM(i.quantity) AS total_quantity,
    SUM(i.reserved_quantity) AS reserved_quantity,
    uom.code AS uom_code
FROM inventory i
LEFT JOIN materials m ON i.item_type = 'material' AND i.item_id = m.id
LEFT JOIN products p ON i.item_type = 'product' AND i.item_id = p.id
LEFT JOIN units_of_measure uom ON i.uom_id = uom.id
WHERE i.status = 'available'
GROUP BY i.item_type, i.item_id, item_code, item_name, uom.code;

-- =====================================================
-- PHASE 2: PRODUCTION SCHEDULING TABLES
-- =====================================================

-- Work centers (machines, assembly stations, etc.)
CREATE TABLE work_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    location VARCHAR(100),
    work_center_type ENUM('machine', 'assembly', 'packaging', 'quality', 'other') NOT NULL,
    capacity_units_per_hour DECIMAL(10,2) DEFAULT 0,
    setup_time_minutes INT DEFAULT 0,
    teardown_time_minutes INT DEFAULT 0,
    efficiency_percentage DECIMAL(5,2) DEFAULT 100.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_work_center_type (work_center_type),
    INDEX idx_work_center_active (is_active)
) ENGINE=InnoDB;

-- Work center availability/calendar (planned downtime, shifts)
CREATE TABLE work_center_calendar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    work_center_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    shift_start TIME NOT NULL,
    shift_end TIME NOT NULL,
    available_hours DECIMAL(4,2) NOT NULL,
    planned_downtime_hours DECIMAL(4,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_work_center_date_shift (work_center_id, date, shift_start),
    INDEX idx_calendar_date (date)
) ENGINE=InnoDB;

-- Production routes (which work centers to use for each product)
CREATE TABLE production_routes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    work_center_id INT UNSIGNED NOT NULL,
    operation_sequence INT NOT NULL,
    operation_description VARCHAR(200),
    setup_time_minutes INT DEFAULT 0,
    run_time_per_unit_seconds DECIMAL(8,2) NOT NULL,
    teardown_time_minutes INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_product_sequence (product_id, operation_sequence),
    INDEX idx_route_work_center (work_center_id)
) ENGINE=InnoDB;

-- Production orders (converted from MRP requirements)
CREATE TABLE production_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_order_id INT UNSIGNED,
    product_id INT UNSIGNED NOT NULL,
    quantity_ordered DECIMAL(15,4) NOT NULL,
    quantity_completed DECIMAL(15,4) DEFAULT 0,
    quantity_scrapped DECIMAL(15,4) DEFAULT 0,
    scheduled_start_date DATE,
    scheduled_end_date DATE,
    actual_start_date DATE,
    actual_end_date DATE,
    priority_level ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    status ENUM('planned', 'released', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'planned',
    notes TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_order_id) REFERENCES customer_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_production_status (status),
    INDEX idx_production_dates (scheduled_start_date, scheduled_end_date),
    INDEX idx_production_priority (priority_level)
) ENGINE=InnoDB;

-- Production order operations (detailed scheduling per work center)
CREATE TABLE production_order_operations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT UNSIGNED NOT NULL,
    route_id INT UNSIGNED NOT NULL,
    work_center_id INT UNSIGNED NOT NULL,
    operation_sequence INT NOT NULL,
    scheduled_start_datetime DATETIME,
    scheduled_end_datetime DATETIME,
    actual_start_datetime DATETIME,
    actual_end_datetime DATETIME,
    quantity_to_produce DECIMAL(15,4) NOT NULL,
    quantity_completed DECIMAL(15,4) DEFAULT 0,
    quantity_scrapped DECIMAL(15,4) DEFAULT 0,
    setup_completed BOOLEAN DEFAULT FALSE,
    status ENUM('planned', 'ready', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    notes TEXT,
    operator_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES production_routes(id) ON DELETE RESTRICT,
    FOREIGN KEY (work_center_id) REFERENCES work_centers(id) ON DELETE RESTRICT,
    INDEX idx_operation_schedule (scheduled_start_datetime, scheduled_end_datetime),
    INDEX idx_operation_work_center (work_center_id),
    INDEX idx_operation_status (status)
) ENGINE=InnoDB;

-- Material reservations for production orders
CREATE TABLE production_order_materials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT UNSIGNED NOT NULL,
    material_id INT UNSIGNED NOT NULL,
    quantity_required DECIMAL(15,4) NOT NULL,
    quantity_reserved DECIMAL(15,4) DEFAULT 0,
    quantity_issued DECIMAL(15,4) DEFAULT 0,
    lot_number VARCHAR(50),
    issue_date DATETIME,
    issued_by VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE RESTRICT,
    INDEX idx_po_material (production_order_id, material_id)
) ENGINE=InnoDB;

-- Production order status tracking/history
CREATE TABLE production_order_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by VARCHAR(100),
    changed_at DATETIME NOT NULL,
    reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    INDEX idx_status_history_order (production_order_id),
    INDEX idx_status_history_date (changed_at)
) ENGINE=InnoDB;

-- Create views for scheduling and capacity planning
CREATE VIEW v_work_center_capacity AS
SELECT 
    wc.id,
    wc.code,
    wc.name,
    wc.capacity_units_per_hour,
    wcc.date,
    wcc.shift_start,
    wcc.shift_end,
    wcc.available_hours,
    wcc.planned_downtime_hours,
    (wcc.available_hours - wcc.planned_downtime_hours) AS effective_hours,
    (wc.capacity_units_per_hour * (wcc.available_hours - wcc.planned_downtime_hours)) AS daily_capacity
FROM work_centers wc
LEFT JOIN work_center_calendar wcc ON wc.id = wcc.work_center_id
WHERE wc.is_active = TRUE;

CREATE VIEW v_production_schedule AS
SELECT 
    po.id,
    po.order_number,
    po.product_id,
    p.product_code,
    p.name AS product_name,
    po.quantity_ordered,
    po.quantity_completed,
    po.scheduled_start_date,
    po.scheduled_end_date,
    po.priority_level,
    po.status,
    COUNT(poo.id) AS total_operations,
    SUM(CASE WHEN poo.status = 'completed' THEN 1 ELSE 0 END) AS completed_operations,
    ROUND((SUM(CASE WHEN poo.status = 'completed' THEN 1 ELSE 0 END) / COUNT(poo.id)) * 100, 1) AS completion_percentage
FROM production_orders po
LEFT JOIN products p ON po.product_id = p.id
LEFT JOIN production_order_operations poo ON po.id = poo.production_order_id
WHERE po.status NOT IN ('completed', 'cancelled')
GROUP BY po.id, po.order_number, po.product_id, p.product_code, p.name, 
         po.quantity_ordered, po.quantity_completed, po.scheduled_start_date, 
         po.scheduled_end_date, po.priority_level, po.status;

-- Insert default work centers
INSERT INTO work_centers (code, name, description, work_center_type, capacity_units_per_hour, setup_time_minutes, efficiency_percentage) VALUES
('INJ-01', 'Injection Molding Machine 1', '100-ton injection molding machine', 'machine', 240, 30, 85.0),
('INJ-02', 'Injection Molding Machine 2', '150-ton injection molding machine', 'machine', 200, 45, 90.0),
('ASM-01', 'Assembly Station 1', 'Manual assembly workstation', 'assembly', 50, 10, 95.0),
('PKG-01', 'Packaging Line 1', 'Automated packaging line', 'packaging', 300, 15, 92.0),
('QC-01', 'Quality Control Station', 'Inspection and testing station', 'quality', 100, 5, 98.0);

-- Insert default work center calendar (5-day work week)
INSERT INTO work_center_calendar (work_center_id, date, shift_start, shift_end, available_hours, planned_downtime_hours) 
SELECT 
    wc.id,
    DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY) AS date,
    '08:00:00' AS shift_start,
    '17:00:00' AS shift_end,
    8.0 AS available_hours,
    1.0 AS planned_downtime_hours
FROM work_centers wc
CROSS JOIN (
    SELECT 0 AS day_offset UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL
    SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL
    SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL
    SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
) d
WHERE WEEKDAY(DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY)) < 5; -- Monday to Friday only

-- Insert production routes for existing products
INSERT INTO production_routes (product_id, work_center_id, operation_sequence, operation_description, setup_time_minutes, run_time_per_unit_seconds) VALUES
(1, 1, 10, 'Injection molding', 30, 45),
(1, 3, 20, 'Assembly and insert installation', 10, 60),
(1, 4, 30, 'Packaging', 15, 30),
(1, 5, 40, 'Final inspection', 5, 45),
(2, 2, 10, 'Injection molding (complex)', 45, 180),
(2, 3, 20, 'Multi-component assembly', 20, 300),
(2, 4, 30, 'Custom packaging', 20, 120),
(2, 5, 40, 'Quality testing', 10, 180);

-- Create indexes for performance
CREATE INDEX idx_inventory_available ON inventory(status, item_type, item_id);