-- Drop table if exists to start fresh
DROP TABLE IF EXISTS inventory_transactions;

-- Create inventory_transactions table for tracking all inventory movements
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('receipt', 'issue', 'transfer', 'adjustment', 'return', 'scrap') NOT NULL,
    transaction_date DATETIME NOT NULL,
    item_type ENUM('material', 'product') NOT NULL,
    item_id INT NOT NULL,
    lot_number VARCHAR(50),
    from_location_id INT,
    to_location_id INT,
    quantity DECIMAL(15,4) NOT NULL,
    uom_id INT,
    reference_type VARCHAR(50),
    reference_number VARCHAR(100),
    notes TEXT,
    performed_by VARCHAR(100) NOT NULL DEFAULT 'System',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_item (item_type, item_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_lot_number (lot_number),
    INDEX idx_reference (reference_type, reference_number),
    INDEX idx_created_at (created_at),
    INDEX idx_transaction_type (transaction_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;