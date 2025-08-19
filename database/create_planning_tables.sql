-- Create MRP Planning Tables
-- Simplified version to ensure they get created

USE mrp_erp;

-- Planning Calendar (defines planning periods/buckets)
CREATE TABLE IF NOT EXISTS planning_calendar (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'weekly',
    period_name VARCHAR(50) NOT NULL,
    is_working_period BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (period_start, period_end),
    INDEX idx_period_dates (period_start, period_end),
    INDEX idx_period_type (period_type)
) ENGINE=InnoDB;

-- Master Production Schedule (MPS)
CREATE TABLE IF NOT EXISTS master_production_schedule (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    period_id INT UNSIGNED NOT NULL,
    demand_qty DECIMAL(15,4) NOT NULL DEFAULT 0,
    firm_planned_qty DECIMAL(15,4) NOT NULL DEFAULT 0,
    scheduled_qty DECIMAL(15,4) NOT NULL DEFAULT 0,
    available_to_promise DECIMAL(15,4) DEFAULT 0,
    status ENUM('draft', 'firm', 'released', 'completed') DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by VARCHAR(100),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES planning_calendar(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mps (product_id, period_id),
    INDEX idx_mps_status (status),
    INDEX idx_mps_period (period_id)
) ENGINE=InnoDB;

-- Add lead_time_days to products if it doesn't exist
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS lead_time_days INT DEFAULT 0 AFTER safety_stock_qty;

-- Add safety_stock_qty to materials if it doesn't exist  
ALTER TABLE materials
ADD COLUMN IF NOT EXISTS safety_stock_qty DECIMAL(15,4) DEFAULT 0 AFTER reorder_point;

-- Generate initial planning calendar (next 13 weeks)
INSERT IGNORE INTO planning_calendar (period_start, period_end, period_type, period_name, is_working_period)
SELECT 
    DATE_ADD(CURDATE(), INTERVAL (week_num * 7 - WEEKDAY(CURDATE())) DAY) as period_start,
    DATE_ADD(CURDATE(), INTERVAL (week_num * 7 - WEEKDAY(CURDATE()) + 6) DAY) as period_end,
    'weekly' as period_type,
    CONCAT('Week ', week_num + 1, ' - ', YEAR(DATE_ADD(CURDATE(), INTERVAL (week_num * 7) DAY))) as period_name,
    TRUE as is_working_period
FROM (
    SELECT 0 as week_num UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 
    UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7
    UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
) weeks;

-- Show what was created
SELECT 'Planning periods created:' as message, COUNT(*) as count FROM planning_calendar;
SELECT 'Tables created successfully' as status;