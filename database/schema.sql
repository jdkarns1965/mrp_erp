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

-- Create indexes for performance
CREATE INDEX idx_inventory_available ON inventory(status, item_type, item_id) WHERE status = 'available';