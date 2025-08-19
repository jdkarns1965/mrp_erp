<?php
/**
 * Deploy Phase 2 Database Schema
 * Adds production scheduling tables to existing database
 */

require_once 'classes/Database.php';

try {
    $db = Database::getInstance();
    
    echo "=== DEPLOYING PHASE 2: PRODUCTION SCHEDULING ===\n";
    echo "Starting deployment at " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check if Phase 2 tables already exist
    $existingTables = $db->select("SHOW TABLES LIKE 'work_centers'");
    if (!empty($existingTables)) {
        echo "⚠️  Phase 2 tables already exist. Skipping deployment.\n";
        echo "If you need to redeploy, drop the tables first.\n";
        exit(0);
    }
    
    echo "1. Creating work centers table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "2. Creating work center calendar table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "3. Creating production routes table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "4. Creating production orders table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "5. Creating production order operations table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "6. Creating production order materials table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "7. Creating production order status history table...\n";
    $db->getConnection()->query("
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
        ) ENGINE=InnoDB
    ");
    
    echo "8. Creating database views...\n";
    $db->getConnection()->query("
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
        WHERE wc.is_active = TRUE
    ");
    
    $db->getConnection()->query("
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
                 po.scheduled_end_date, po.priority_level, po.status
    ");
    
    echo "9. Inserting default work centers...\n";
    $db->getConnection()->query("
        INSERT INTO work_centers (code, name, description, work_center_type, capacity_units_per_hour, setup_time_minutes, efficiency_percentage) VALUES
        ('INJ-01', 'Injection Molding Machine 1', '100-ton injection molding machine', 'machine', 240, 30, 85.0),
        ('INJ-02', 'Injection Molding Machine 2', '150-ton injection molding machine', 'machine', 200, 45, 90.0),
        ('ASM-01', 'Assembly Station 1', 'Manual assembly workstation', 'assembly', 50, 10, 95.0),
        ('PKG-01', 'Packaging Line 1', 'Automated packaging line', 'packaging', 300, 15, 92.0),
        ('QC-01', 'Quality Control Station', 'Inspection and testing station', 'quality', 100, 5, 98.0)
    ");
    
    echo "10. Creating work center calendar entries...\n";
    $db->getConnection()->query("
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
        WHERE WEEKDAY(DATE_ADD(CURDATE(), INTERVAL d.day_offset DAY)) < 5
    ");
    
    echo "11. Creating production routes for existing products...\n";
    $db->getConnection()->query("
        INSERT INTO production_routes (product_id, work_center_id, operation_sequence, operation_description, setup_time_minutes, run_time_per_unit_seconds) VALUES
        (1, 1, 10, 'Injection molding', 30, 45),
        (1, 3, 20, 'Assembly and insert installation', 10, 60),
        (1, 4, 30, 'Packaging', 15, 30),
        (1, 5, 40, 'Final inspection', 5, 45),
        (2, 2, 10, 'Injection molding (complex)', 45, 180),
        (2, 3, 20, 'Multi-component assembly', 20, 300),
        (2, 4, 30, 'Custom packaging', 20, 120),
        (2, 5, 40, 'Quality testing', 10, 180)
    ");
    
    echo "\n✅ PHASE 2 DEPLOYMENT COMPLETED SUCCESSFULLY!\n";
    echo "===========================================\n";
    echo "Tables created: 7\n";
    echo "Views created: 2\n";
    echo "Work centers added: 5\n";
    echo "Calendar entries created: " . (5 * 25) . " (5 work centers × 25 business days)\n";
    echo "Production routes created: 8\n";
    echo "\nPhase 2 Production Scheduling is now ready for use!\n";
    echo "Access via: /production/\n\n";
    
} catch (Exception $e) {
    echo "❌ DEPLOYMENT FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
?>