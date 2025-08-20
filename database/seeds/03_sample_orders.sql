-- Create Sample Production Orders and Operations for Testing
USE mrp_erp;

-- First, create production routes for the existing product
INSERT IGNORE INTO production_routes (product_id, work_center_id, operation_sequence, operation_description, setup_time_minutes, run_time_per_unit_seconds)
SELECT 
    1 as product_id,  -- Assuming product ID 1 exists
    wc.id as work_center_id,
    (ROW_NUMBER() OVER (ORDER BY wc.id)) * 10 as operation_sequence,
    CASE wc.code
        WHEN 'INJ-01' THEN 'Injection molding'
        WHEN 'INJ-02' THEN 'Secondary molding'
        WHEN 'ASM-01' THEN 'Assembly and insert installation'
        WHEN 'PKG-01' THEN 'Packaging'
        WHEN 'QC-01' THEN 'Final inspection'
    END as operation_description,
    CASE wc.code
        WHEN 'INJ-01' THEN 30
        WHEN 'INJ-02' THEN 45
        WHEN 'ASM-01' THEN 15
        WHEN 'PKG-01' THEN 10
        WHEN 'QC-01' THEN 5
    END as setup_time_minutes,
    CASE wc.code
        WHEN 'INJ-01' THEN 45
        WHEN 'INJ-02' THEN 60
        WHEN 'ASM-01' THEN 90
        WHEN 'PKG-01' THEN 30
        WHEN 'QC-01' THEN 45
    END as run_time_per_unit_seconds
FROM work_centers wc
WHERE wc.code IN ('INJ-01', 'ASM-01', 'PKG-01', 'QC-01')
ORDER BY wc.id;

-- Create sample production orders
INSERT IGNORE INTO production_orders (order_number, product_id, quantity_ordered, scheduled_start_date, scheduled_end_date, priority_level, status, created_by)
VALUES
    ('PO-2025-0001', 1, 500, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'urgent', 'in_progress', 'system'),
    ('PO-2025-0002', 1, 300, DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'high', 'released', 'system'),
    ('PO-2025-0003', 1, 750, DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'normal', 'planned', 'system'),
    ('PO-2025-0004', 1, 200, DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'high', 'planned', 'system'),
    ('PO-2025-0005', 1, 400, DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 9 DAY), 'normal', 'planned', 'system'),
    ('PO-2025-0006', 1, 600, DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'low', 'planned', 'system');

-- Create production order operations (scheduled tasks for each work center)
INSERT IGNORE INTO production_order_operations (
    production_order_id, 
    route_id, 
    work_center_id, 
    operation_sequence,
    scheduled_start_datetime, 
    scheduled_end_datetime, 
    quantity_to_produce, 
    status
)
SELECT 
    po.id,
    pr.id,
    pr.work_center_id,
    pr.operation_sequence,
    CASE pr.operation_sequence
        WHEN 10 THEN TIMESTAMP(po.scheduled_start_date, '08:00:00')
        WHEN 20 THEN TIMESTAMP(po.scheduled_start_date, '13:00:00')
        WHEN 30 THEN TIMESTAMP(DATE_ADD(po.scheduled_start_date, INTERVAL 1 DAY), '09:00:00')
        WHEN 40 THEN TIMESTAMP(DATE_ADD(po.scheduled_start_date, INTERVAL 1 DAY), '14:00:00')
    END as scheduled_start,
    CASE pr.operation_sequence
        WHEN 10 THEN TIMESTAMP(po.scheduled_start_date, '12:00:00')
        WHEN 20 THEN TIMESTAMP(po.scheduled_start_date, '17:00:00')
        WHEN 30 THEN TIMESTAMP(DATE_ADD(po.scheduled_start_date, INTERVAL 1 DAY), '13:00:00')
        WHEN 40 THEN TIMESTAMP(DATE_ADD(po.scheduled_start_date, INTERVAL 1 DAY), '16:00:00')
    END as scheduled_end,
    po.quantity_ordered,
    CASE 
        WHEN po.status = 'in_progress' AND pr.operation_sequence = 10 THEN 'completed'
        WHEN po.status = 'in_progress' AND pr.operation_sequence = 20 THEN 'in_progress'
        WHEN po.status = 'in_progress' AND pr.operation_sequence > 20 THEN 'planned'
        WHEN po.status = 'released' AND pr.operation_sequence = 10 THEN 'ready'
        WHEN po.status = 'released' AND pr.operation_sequence > 10 THEN 'planned'
        ELSE 'planned'
    END as status
FROM production_orders po
CROSS JOIN production_routes pr
WHERE pr.product_id = po.product_id
ORDER BY po.id, pr.operation_sequence;

-- Add some variety to the schedule - spread operations across different days and times
UPDATE production_order_operations poo
JOIN production_orders po ON poo.production_order_id = po.id
SET 
    poo.scheduled_start_datetime = DATE_ADD(poo.scheduled_start_datetime, INTERVAL (poo.operation_sequence DIV 20) DAY),
    poo.scheduled_end_datetime = DATE_ADD(poo.scheduled_end_datetime, INTERVAL (poo.operation_sequence DIV 20) DAY)
WHERE po.order_number IN ('PO-2025-0003', 'PO-2025-0004', 'PO-2025-0005', 'PO-2025-0006');

-- Show summary
SELECT 'Production Routes' as table_name, COUNT(*) as records FROM production_routes
UNION ALL
SELECT 'Production Orders', COUNT(*) FROM production_orders
UNION ALL
SELECT 'Production Operations', COUNT(*) FROM production_order_operations;