-- Migration 005: Document Management System
-- Description: Create tables for document storage and management
-- Author: Claude Code
-- Date: 2025-01-20

-- Document categories for organizing documents by type
CREATE TABLE document_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    icon VARCHAR(20) DEFAULT 'document',
    color VARCHAR(7) DEFAULT '#6B7280',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active),
    INDEX idx_name (name)
);

-- Main documents table
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    category_id INT UNSIGNED,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_hash VARCHAR(64) NOT NULL UNIQUE,
    version INT UNSIGNED DEFAULT 1,
    parent_document_id INT UNSIGNED NULL,
    uploaded_by_user VARCHAR(100),
    download_count INT UNSIGNED DEFAULT 0,
    last_accessed_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (category_id) REFERENCES document_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_document_id) REFERENCES documents(id) ON DELETE SET NULL,
    
    INDEX idx_filename (filename),
    INDEX idx_category (category_id),
    INDEX idx_hash (file_hash),
    INDEX idx_active (is_active),
    INDEX idx_deleted (deleted_at),
    INDEX idx_parent (parent_document_id),
    INDEX idx_created (created_at)
);

-- Entity document relationships (flexible linking system)
CREATE TABLE entity_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    entity_type ENUM('material', 'product', 'supplier', 'customer', 'bom', 'production_order') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    relationship_type ENUM('primary', 'secondary', 'reference', 'archive') DEFAULT 'primary',
    sort_order INT UNSIGNED DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_entity_document (entity_type, entity_id, document_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_document (document_id),
    INDEX idx_relationship (relationship_type),
    INDEX idx_sort (sort_order)
);

-- Document tags for flexible categorization
CREATE TABLE document_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#8B5CF6',
    usage_count INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_usage (usage_count)
);

-- Many-to-many relationship between documents and tags
CREATE TABLE document_tag_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES document_tags(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_document_tag (document_id, tag_id),
    INDEX idx_document (document_id),
    INDEX idx_tag (tag_id)
);

-- Document access log for audit trail
CREATE TABLE document_access_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    action ENUM('view', 'download', 'upload', 'delete', 'update') NOT NULL,
    user_identifier VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    
    INDEX idx_document (document_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_user (user_identifier)
);

-- Insert default document categories
INSERT INTO document_categories (name, description, icon, color) VALUES
('Datasheet', 'Technical datasheets and specifications', 'file-text', '#3B82F6'),
('Drawing', 'Engineering drawings and CAD files', 'image', '#10B981'),
('Certificate', 'Quality certificates and test reports', 'award', '#F59E0B'),
('SDS', 'Safety Data Sheets', 'shield', '#EF4444'),
('Manual', 'User manuals and instructions', 'book', '#8B5CF6'),
('Packaging', 'Packaging specifications and documents', 'package', '#EC4899'),
('Quality', 'Quality control and inspection documents', 'check-circle', '#06B6D4'),
('Specification', 'Product and material specifications', 'clipboard-list', '#84CC16'),
('Other', 'Miscellaneous documents', 'document', '#6B7280');

-- Insert common document tags
INSERT INTO document_tags (name, color) VALUES
('Critical', '#EF4444'),
('Latest', '#10B981'),
('Draft', '#F59E0B'),
('Archived', '#6B7280'),
('Approved', '#3B82F6'),
('Confidential', '#7C3AED'),
('External', '#EC4899'),
('Internal', '#06B6D4');