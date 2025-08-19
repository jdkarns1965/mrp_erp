-- Add Production Scheduling Data for Gantt Chart Demo
-- This script adds the Phase 2 tables and sample data to existing MRP database

USE mrp_erp;

-- =====================================================
-- PHASE 2: PRODUCTION SCHEDULING TABLES
-- =====================================================

-- Work centers (machines, assembly stations, etc.)
CREATE TABLE IF NOT EXISTS work_centers (
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
CREATE TABLE IF NOT EXISTS work_center_calendar (
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
CREATE TABLE IF NOT EXISTS production_routes (
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
CREATE TABLE IF NOT EXISTS production_orders (
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
CREATE TABLE IF NOT EXISTS production_order_operations (
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

-- Create views for scheduling and capacity planning
CREATE OR REPLACE VIEW v_work_center_capacity AS
SELECT 
    wc.id,
    wc.code,
    wc.name,
    wc.work_center_type,
    wc.capacity_units_per_hour,
    wcc.date,
    wcc.shift_start,
    wcc.shift_end,
    wcc.available_hours,
    wcc.planned_downtime_hours,
    (wcc.available_hours - wcc.planned_downtime_hours) AS effective_hours,
    (wc.capacity_units_per_hour * (wcc.available_hours - wcc.planned_downtime_hours)) AS daily_capacity,
    -- Calculate utilization percentage (placeholder - would need actual scheduled time)
    ROUND(RAND() * 85 + 10, 1) AS utilization_percentage
FROM work_centers wc
LEFT JOIN work_center_calendar wcc ON wc.id = wcc.work_center_id
WHERE wc.is_active = TRUE;

-- Insert sample work centers
INSERT IGNORE INTO work_centers (code, name, description, work_center_type, capacity_units_per_hour, setup_time_minutes, efficiency_percentage) VALUES
('INJ-01', 'Injection Molding Machine 1', '100-ton injection molding machine', 'machine', 240, 30, 85.0),
('INJ-02', 'Injection Molding Machine 2', '150-ton injection molding machine', 'machine', 200, 45, 90.0),
('ASM-01', 'Assembly Station 1', 'Manual assembly workstation', 'assembly', 50, 10, 95.0),
('PKG-01', 'Packaging Line 1', 'Automated packaging line', 'packaging', 300, 15, 92.0),
('QC-01', 'Quality Control Station', 'Inspection and testing station', 'quality', 100, 5, 98.0);

-- Insert work center calendar for next 30 days (weekdays only)
INSERT IGNORE INTO work_center_calendar (work_center_id, date, shift_start, shift_end, available_hours, planned_downtime_hours) 
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
    SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25 UNION ALL
    SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
) d
WHERE WEEKDAY(DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY)) < 5; -- Monday to Friday only

-- Insert sample production routes (assuming some products exist)
INSERT IGNORE INTO production_routes (product_id, work_center_id, operation_sequence, operation_description, setup_time_minutes, run_time_per_unit_seconds)
SELECT p.id, wc.id, op.sequence, op.description, op.setup_time, op.run_time
FROM products p
CROSS JOIN (
    SELECT 1 as wc_id, 10 as sequence, 'Injection molding' as description, 30 as setup_time, 45 as run_time UNION ALL
    SELECT 3 as wc_id, 20 as sequence, 'Assembly and insert installation' as description, 10 as setup_time, 60 as run_time UNION ALL
    SELECT 4 as wc_id, 30 as sequence, 'Packaging' as description, 15 as setup_time, 30 as run_time UNION ALL
    SELECT 5 as wc_id, 40 as sequence, 'Final inspection' as description, 5 as setup_time, 45 as run_time
) op
JOIN work_centers wc ON op.wc_id = wc.id
WHERE p.id <= 3 -- Limit to first 3 products
ORDER BY p.id, op.sequence;

-- Insert sample production orders
INSERT IGNORE INTO production_orders (order_number, product_id, quantity_ordered, scheduled_start_date, scheduled_end_date, priority_level, status, created_by)
SELECT 
    CONCAT('PO-', LPAD(p.id * 1000 + FLOOR(RAND() * 999), 6, '0')) as order_number,
    p.id,
    ROUND(RAND() * 500 + 100, 0) as quantity_ordered,
    DATE_ADD(CURDATE(), INTERVAL FLOOR(RAND() * 7) DAY) as scheduled_start_date,
    DATE_ADD(CURDATE(), INTERVAL (FLOOR(RAND() * 7) + FLOOR(RAND() * 14) + 2) DAY) as scheduled_end_date,
    ELT(FLOOR(RAND() * 4) + 1, 'low', 'normal', 'high', 'urgent') as priority_level,
    ELT(FLOOR(RAND() * 4) + 1, 'planned', 'released', 'in_progress', 'completed') as status,
    'system' as created_by
FROM products p
WHERE p.id <= 3  -- First 3 products
UNION ALL
SELECT 
    CONCAT('PO-', LPAD(p.id * 1000 + FLOOR(RAND() * 999) + 500, 6, '0')) as order_number,
    p.id,
    ROUND(RAND() * 300 + 50, 0) as quantity_ordered,
    DATE_ADD(CURDATE(), INTERVAL (FLOOR(RAND() * 7) + 7) DAY) as scheduled_start_date,
    DATE_ADD(CURDATE(), INTERVAL (FLOOR(RAND() * 7) + 14) DAY) as scheduled_end_date,
    ELT(FLOOR(RAND() * 4) + 1, 'low', 'normal', 'high', 'urgent') as priority_level,
    ELT(FLOOR(RAND() * 3) + 1, 'planned', 'released', 'in_progress') as status,
    'system' as created_by
FROM products p
WHERE p.id <= 2;  -- Extra orders for first 2 products

-- Insert production order operations (scheduled operations)
INSERT IGNORE INTO production_order_operations (
    production_order_id, route_id, work_center_id, operation_sequence, 
    scheduled_start_datetime, scheduled_end_datetime, quantity_to_produce, status
)
SELECT 
    po.id,
    pr.id,
    pr.work_center_id,
    pr.operation_sequence,
    TIMESTAMP(po.scheduled_start_date, TIME('08:00:00')) + INTERVAL ((pr.operation_sequence - 10) * 4) HOUR as scheduled_start_datetime,
    TIMESTAMP(po.scheduled_start_date, TIME('08:00:00')) + INTERVAL ((pr.operation_sequence - 10) * 4 + 6) HOUR as scheduled_end_datetime,
    po.quantity_ordered,
    CASE 
        WHEN po.status = 'completed' THEN 'completed'
        WHEN po.status = 'in_progress' AND pr.operation_sequence <= 20 THEN 'completed'
        WHEN po.status = 'in_progress' AND pr.operation_sequence = 30 THEN 'in_progress'
        WHEN po.status = 'released' THEN 'ready'
        ELSE 'planned'
    END as status
FROM production_orders po
JOIN production_routes pr ON po.product_id = pr.product_id
WHERE pr.is_active = TRUE
ORDER BY po.id, pr.operation_sequence;

-- Show summary of created data
SELECT 'Work Centers' as table_name, COUNT(*) as records FROM work_centers
UNION ALL
SELECT 'Production Orders' as table_name, COUNT(*) as records FROM production_orders
UNION ALL
SELECT 'Production Operations' as table_name, COUNT(*) as records FROM production_order_operations
UNION ALL
SELECT 'Work Center Calendar' as table_name, COUNT(*) as records FROM work_center_calendar;