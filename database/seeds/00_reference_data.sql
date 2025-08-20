-- Reference Data Seed
-- Type: System Reference Data
-- Created: 2025-08-20
-- Description: Core reference data required for system operation

-- IMPORTANT: This seed is idempotent (safe to run multiple times)
-- Uses INSERT IGNORE to prevent duplicate key errors

-- =====================================================
-- UNITS OF MEASURE
-- =====================================================
INSERT IGNORE INTO units_of_measure (code, description, type) VALUES
('EA', 'Each', 'count'),
('KG', 'Kilogram', 'weight'),
('LB', 'Pound', 'weight'),
('G', 'Gram', 'weight'),
('OZ', 'Ounce', 'weight'),
('L', 'Liter', 'volume'),
('ML', 'Milliliter', 'volume'),
('GAL', 'Gallon', 'volume'),
('M', 'Meter', 'length'),
('CM', 'Centimeter', 'length'),
('IN', 'Inch', 'length'),
('FT', 'Foot', 'length'),
('M2', 'Square Meter', 'area'),
('SQ FT', 'Square Foot', 'area'),
('BOX', 'Box', 'count'),
('SET', 'Set', 'count'),
('PACK', 'Package', 'count'),
('ROLL', 'Roll', 'count'),
('SHEET', 'Sheet', 'count'),
('TUBE', 'Tube', 'count');

-- =====================================================
-- MATERIAL CATEGORIES
-- =====================================================
INSERT IGNORE INTO material_categories (name, description) VALUES
('Raw Materials', 'Base materials for manufacturing'),
('Components', 'Manufactured or purchased components'),
('Packaging', 'Packaging materials and supplies'),
('Hardware', 'Fasteners, screws, bolts, etc.'),
('Chemicals', 'Chemical compounds and solutions'),
('Consumables', 'Items consumed during manufacturing'),
('Tools', 'Manufacturing tools and equipment'),
('Electronics', 'Electronic components and parts'),
('Textiles', 'Fabric and textile materials'),
('Metals', 'Metal sheets, bars, and raw materials'),
('Plastics', 'Plastic resins and molded parts'),
('Adhesives', 'Glues, tapes, and bonding materials'),
('Fluids', 'Oils, lubricants, and liquid materials'),
('Maintenance', 'Maintenance and repair supplies');

-- =====================================================
-- PRODUCT CATEGORIES
-- =====================================================
INSERT IGNORE INTO product_categories (name, description) VALUES
('Finished Goods', 'Complete products ready for sale'),
('Assemblies', 'Sub-assemblies used in final products'),
('Custom Products', 'Made-to-order custom products'),
('Standard Products', 'Standard catalog products'),
('Prototype', 'Prototype and development products'),
('Spare Parts', 'Replacement and spare parts'),
('Accessories', 'Product accessories and add-ons'),
('Kits', 'Product kits and bundles'),
('Services', 'Service-based products'),
('Digital Products', 'Software and digital deliverables');

-- =====================================================
-- WAREHOUSES/LOCATIONS
-- =====================================================
INSERT IGNORE INTO warehouses (code, name, address, active) VALUES
('MAIN', 'Main Warehouse', '123 Industrial Ave, Manufacturing City, MC 12345', 1),
('RAW', 'Raw Materials Storage', 'Building A - Raw Materials Section', 1),
('WIP', 'Work in Progress', 'Building B - Production Floor', 1),
('FG', 'Finished Goods', 'Building C - Finished Goods Warehouse', 1),
('QC', 'Quality Control', 'Building B - QC Department', 1),
('SHIP', 'Shipping Area', 'Loading Dock - Shipping Department', 1),
('RET', 'Returns Processing', 'Building D - Returns Department', 1),
('MAINT', 'Maintenance Storage', 'Building E - Maintenance Shop', 1);

-- =====================================================
-- WORK CENTER TYPES (for production scheduling)
-- =====================================================
INSERT IGNORE INTO work_center_types (name, description) VALUES
('Machine', 'Manufacturing machines and equipment'),
('Assembly', 'Assembly stations and work areas'),
('Testing', 'Quality testing and inspection areas'),
('Packaging', 'Packaging and labeling stations'),
('Processing', 'Material processing operations'),
('Finishing', 'Finishing and surface treatment'),
('Inspection', 'Quality inspection points'),
('Storage', 'Temporary storage and staging areas');

-- =====================================================
-- CUSTOMER TYPES
-- =====================================================
INSERT IGNORE INTO customer_types (name, description) VALUES
('Retail', 'Retail customers and distributors'),
('Wholesale', 'Wholesale buyers and resellers'),
('OEM', 'Original Equipment Manufacturers'),
('Government', 'Government agencies and contracts'),
('International', 'International customers and exports'),
('Internal', 'Internal company orders and transfers'),
('Service', 'Service and repair customers'),
('Project', 'Project-based customers');

-- =====================================================
-- SUPPLIER TYPES
-- =====================================================
INSERT IGNORE INTO supplier_types (name, description) VALUES
('Raw Material', 'Raw material suppliers'),
('Component', 'Component and part suppliers'),
('Service', 'Service providers and contractors'),
('Packaging', 'Packaging material suppliers'),
('Equipment', 'Equipment and machinery suppliers'),
('Consumable', 'Consumable supplies and materials'),
('Transportation', 'Shipping and logistics providers'),
('Maintenance', 'Maintenance and repair services');

-- =====================================================
-- ORDER STATUS TYPES
-- =====================================================
INSERT IGNORE INTO order_status_types (code, name, description, is_active, is_completed) VALUES
('DRAFT', 'Draft', 'Order is being created', 1, 0),
('SUBMITTED', 'Submitted', 'Order submitted for approval', 1, 0),
('APPROVED', 'Approved', 'Order approved for production', 1, 0),
('PLANNED', 'Planned', 'Order included in production plan', 1, 0),
('RELEASED', 'Released', 'Order released to production', 1, 0),
('IN_PROGRESS', 'In Progress', 'Order is being manufactured', 1, 0),
('COMPLETED', 'Completed', 'Order manufacturing completed', 0, 1),
('SHIPPED', 'Shipped', 'Order shipped to customer', 0, 1),
('DELIVERED', 'Delivered', 'Order delivered to customer', 0, 1),
('CANCELLED', 'Cancelled', 'Order cancelled', 0, 0),
('ON_HOLD', 'On Hold', 'Order temporarily on hold', 1, 0),
('RETURNED', 'Returned', 'Order returned by customer', 0, 0);

-- =====================================================
-- PRODUCTION ORDER STATUS TYPES
-- =====================================================
INSERT IGNORE INTO production_status_types (code, name, description, is_active, is_completed) VALUES
('PLANNED', 'Planned', 'Production order planned', 1, 0),
('RELEASED', 'Released', 'Released to production floor', 1, 0),
('IN_PROGRESS', 'In Progress', 'Manufacturing in progress', 1, 0),
('COMPLETED', 'Completed', 'Production completed', 0, 1),
('CANCELLED', 'Cancelled', 'Production cancelled', 0, 0),
('ON_HOLD', 'On Hold', 'Production temporarily on hold', 1, 0),
('QUALITY_HOLD', 'Quality Hold', 'Held for quality issues', 1, 0),
('REWORK', 'Rework', 'Requires rework or correction', 1, 0);

-- =====================================================
-- OPERATION STATUS TYPES
-- =====================================================
INSERT IGNORE INTO operation_status_types (code, name, description, is_active, is_completed) VALUES
('PLANNED', 'Planned', 'Operation planned but not started', 1, 0),
('READY', 'Ready', 'Ready to start operation', 1, 0),
('IN_PROGRESS', 'In Progress', 'Operation in progress', 1, 0),
('COMPLETED', 'Completed', 'Operation completed', 0, 1),
('SKIPPED', 'Skipped', 'Operation skipped', 0, 1),
('CANCELLED', 'Cancelled', 'Operation cancelled', 0, 0),
('ON_HOLD', 'On Hold', 'Operation on hold', 1, 0),
('QUALITY_ISSUE', 'Quality Issue', 'Quality problem identified', 1, 0);

-- =====================================================
-- INVENTORY TRANSACTION TYPES
-- =====================================================
INSERT IGNORE INTO transaction_types (code, name, description, affects_quantity) VALUES
('RECEIVE', 'Receipt', 'Material received into inventory', 1),
('ISSUE', 'Issue', 'Material issued from inventory', -1),
('ADJUST_IN', 'Adjustment In', 'Positive inventory adjustment', 1),
('ADJUST_OUT', 'Adjustment Out', 'Negative inventory adjustment', -1),
('TRANSFER_IN', 'Transfer In', 'Transfer into location', 1),
('TRANSFER_OUT', 'Transfer Out', 'Transfer out of location', -1),
('PRODUCTION_CONSUME', 'Production Consumption', 'Consumed in production', -1),
('PRODUCTION_OUTPUT', 'Production Output', 'Produced from manufacturing', 1),
('SCRAP', 'Scrap', 'Material scrapped', -1),
('RETURN', 'Return', 'Material returned', 1),
('CYCLE_COUNT', 'Cycle Count', 'Cycle count adjustment', 0),
('PHYSICAL_COUNT', 'Physical Count', 'Physical inventory count', 0);

-- =====================================================
-- QUALITY STATUS TYPES
-- =====================================================
INSERT IGNORE INTO quality_status_types (code, name, description, allows_use) VALUES
('PENDING', 'Pending Inspection', 'Awaiting quality inspection', 0),
('APPROVED', 'Approved', 'Passed quality inspection', 1),
('REJECTED', 'Rejected', 'Failed quality inspection', 0),
('CONDITIONAL', 'Conditional', 'Conditionally approved with restrictions', 1),
('QUARANTINE', 'Quarantine', 'Quarantined for investigation', 0),
('REWORK', 'Rework Required', 'Requires rework before approval', 0),
('SAMPLE', 'Sample', 'Sample material for testing', 0),
('EXPIRED', 'Expired', 'Material past expiration date', 0);

-- =====================================================
-- PRIORITY LEVELS
-- =====================================================
INSERT IGNORE INTO priority_levels (code, name, description, sort_order) VALUES
('LOW', 'Low', 'Low priority', 1),
('NORMAL', 'Normal', 'Normal priority', 2),
('HIGH', 'High', 'High priority', 3),
('URGENT', 'Urgent', 'Urgent priority', 4),
('CRITICAL', 'Critical', 'Critical priority', 5),
('EMERGENCY', 'Emergency', 'Emergency priority', 6);

-- =====================================================
-- SHIFT PATTERNS
-- =====================================================
INSERT IGNORE INTO shift_patterns (name, description, start_time, end_time, is_active) VALUES
('Day Shift', 'Standard day shift', '08:00:00', '17:00:00', 1),
('Evening Shift', 'Evening shift', '16:00:00', '01:00:00', 1),
('Night Shift', 'Night shift', '00:00:00', '09:00:00', 1),
('Weekend Day', 'Weekend day shift', '09:00:00', '18:00:00', 1),
('Maintenance', 'Maintenance window', '02:00:00', '06:00:00', 1),
('Extended Day', 'Extended day shift', '06:00:00', '18:00:00', 0),
('Split Shift', 'Split shift pattern', '08:00:00', '12:00:00', 0);

-- Summary of reference data created
SELECT 'Reference data seed completed successfully' AS status;