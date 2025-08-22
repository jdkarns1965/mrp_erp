<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Document extends BaseModel {
    protected $table = 'documents';
    protected $fillable = [
        'filename', 'original_filename', 'title', 'description', 'category_id', 
        'file_path', 'file_size', 'mime_type', 'file_hash', 'version', 
        'parent_document_id', 'uploaded_by_user', 'is_active'
    ];
    
    const UPLOAD_PATH = '/var/www/html/mrp_erp/storage/documents/';
    const TEMP_PATH = '/var/www/html/mrp_erp/storage/temp/';
    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    
    const ALLOWED_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/zip' => 'zip',
        'image/tiff' => 'tiff',
        'application/x-dwg' => 'dwg',
        'application/dxf' => 'dxf'
    ];
    
    public function uploadDocument(array $file, string $entityType, int $entityId, array $options = []): array {
        $validation = $this->validateFile($file);
        if ($validation !== true) {
            throw new Exception('File validation failed: ' . $validation);
        }
        
        $fileHash = hash_file('sha256', $file['tmp_name']);
        
        // Check for duplicate
        if ($this->exists(['file_hash' => $fileHash])) {
            throw new Exception('Document already exists');
        }
        
        $filename = $this->generateUniqueFilename($file['name']);
        $relativePath = $this->generateFilePath($entityType, $options['category'] ?? 'other', $filename);
        $fullPath = self::UPLOAD_PATH . $relativePath;
        
        // Create directory if it doesn't exist
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true)) {
                throw new Exception('Failed to create upload directory: ' . $dir);
            }
        }
        
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to move uploaded file to storage directory');
        }
        
        $documentData = [
            'filename' => $filename,
            'original_filename' => $file['name'],
            'title' => $options['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME),
            'description' => $options['description'] ?? null,
            'category_id' => $options['category_id'] ?? null,
            'file_path' => $relativePath,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'file_hash' => $fileHash,
            'uploaded_by_user' => $options['user'] ?? 'system',
            'is_active' => true
        ];
        
        $documentId = $this->create($documentData);
        
        // Link to entity
        $this->linkToEntity($documentId, $entityType, $entityId, $options['relationship_type'] ?? 'primary');
        
        // Add tags if provided
        if (!empty($options['tags'])) {
            $this->addTags($documentId, $options['tags']);
        }
        
        // Log access
        $this->logAccess($documentId, 'upload', $options['user'] ?? 'system');
        
        return [
            'id' => $documentId,
            'filename' => $filename,
            'original_filename' => $file['name'],
            'file_size' => $file['size']
        ];
    }
    
    public function getEntityDocuments(string $entityType, int $entityId): array {
        $sql = "
            SELECT d.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color,
                   ed.relationship_type, ed.sort_order, ed.notes
            FROM documents d
            LEFT JOIN document_categories dc ON d.category_id = dc.id
            INNER JOIN entity_documents ed ON d.id = ed.document_id
            WHERE ed.entity_type = ? AND ed.entity_id = ? AND d.deleted_at IS NULL
            ORDER BY ed.sort_order ASC, d.created_at DESC
        ";
        
        return $this->db->select($sql, [$entityType, $entityId], ['s', 'i']);
    }
    
    public function getDocumentWithTags(int $documentId): ?array {
        $document = $this->find($documentId);
        if (!$document) {
            return null;
        }
        
        // Get tags
        $sql = "
            SELECT dt.name, dt.color
            FROM document_tags dt
            INNER JOIN document_tag_assignments dta ON dt.id = dta.tag_id
            WHERE dta.document_id = ?
        ";
        $document['tags'] = $this->db->select($sql, [$documentId], ['i']);
        
        // Get category
        if ($document['category_id']) {
            $category = $this->db->selectOne(
                "SELECT name, icon, color FROM document_categories WHERE id = ?",
                [$document['category_id']],
                ['i']
            );
            $document['category'] = $category;
        }
        
        return $document;
    }
    
    public function downloadDocument(int $documentId, string $user = 'anonymous'): array {
        $document = $this->find($documentId);
        if (!$document) {
            throw new Exception('Document not found');
        }
        
        $fullPath = self::UPLOAD_PATH . $document['file_path'];
        if (!file_exists($fullPath)) {
            throw new Exception('File not found on disk');
        }
        
        // Update download count and last accessed
        $this->db->update(
            "UPDATE documents SET download_count = download_count + 1, last_accessed_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$documentId],
            ['i']
        );
        
        // Log access
        $this->logAccess($documentId, 'download', $user);
        
        return [
            'path' => $fullPath,
            'filename' => $document['original_filename'],
            'mime_type' => $document['mime_type'],
            'file_size' => $document['file_size']
        ];
    }
    
    public function deleteDocument(int $documentId, string $user = 'system'): bool {
        $document = $this->find($documentId);
        if (!$document) {
            return false;
        }
        
        // Soft delete the document
        $this->delete($documentId);
        
        // Log access
        $this->logAccess($documentId, 'delete', $user);
        
        return true;
    }
    
    public function searchDocuments(string $query, array $filters = []): array {
        $sql = "
            SELECT d.*, dc.name as category_name, dc.icon as category_icon, dc.color as category_color
            FROM documents d
            LEFT JOIN document_categories dc ON d.category_id = dc.id
            WHERE d.deleted_at IS NULL
        ";
        
        $params = [];
        $types = [];
        
        if (!empty($query)) {
            $sql .= " AND (d.title LIKE ? OR d.description LIKE ? OR d.original_filename LIKE ?)";
            $searchTerm = "%{$query}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types = array_merge($types, ['s', 's', 's']);
        }
        
        if (!empty($filters['category_id'])) {
            $sql .= " AND d.category_id = ?";
            $params[] = $filters['category_id'];
            $types[] = 'i';
        }
        
        if (!empty($filters['entity_type']) && !empty($filters['entity_id'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM entity_documents ed WHERE ed.document_id = d.id AND ed.entity_type = ? AND ed.entity_id = ?)";
            $params = array_merge($params, [$filters['entity_type'], $filters['entity_id']]);
            $types = array_merge($types, ['s', 'i']);
        }
        
        $sql .= " ORDER BY d.created_at DESC LIMIT 50";
        
        return $this->db->select($sql, $params, $types);
    }
    
    public function getCategories(): array {
        return $this->db->select("SELECT * FROM document_categories WHERE is_active = 1 ORDER BY name");
    }
    
    private function validateFile(array $file) {
        // Check for upload errors
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error'] ?? -1) {
                case UPLOAD_ERR_INI_SIZE:
                    return 'File exceeds PHP upload_max_filesize (' . ini_get('upload_max_filesize') . ')';
                case UPLOAD_ERR_FORM_SIZE:
                    return 'File exceeds HTML form MAX_FILE_SIZE';
                case UPLOAD_ERR_PARTIAL:
                    return 'File was only partially uploaded';
                case UPLOAD_ERR_NO_FILE:
                    return 'No file was uploaded';
                case UPLOAD_ERR_NO_TMP_DIR:
                    return 'Missing temporary folder';
                case UPLOAD_ERR_CANT_WRITE:
                    return 'Failed to write file to disk';
                case UPLOAD_ERR_EXTENSION:
                    return 'File upload stopped by extension';
                default:
                    return 'Unknown upload error (' . ($file['error'] ?? 'undefined') . ')';
            }
        }
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return 'Invalid upload - file not found or not uploaded via HTTP POST';
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return 'File size (' . $this->formatFileSize($file['size']) . ') exceeds maximum allowed (' . $this->formatFileSize(self::MAX_FILE_SIZE) . ')';
        }
        
        if (!isset(self::ALLOWED_TYPES[$file['type']])) {
            return 'File type "' . $file['type'] . '" is not allowed. Allowed types: ' . implode(', ', array_keys(self::ALLOWED_TYPES));
        }
        
        return true;
    }
    
    private function formatFileSize(int $bytes): string {
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if ($bytes === 0) return '0 Bytes';
        $i = floor(log($bytes) / log(1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
    }
    
    private function generateUniqueFilename(string $originalName): string {
        $pathInfo = pathinfo($originalName);
        $extension = $pathInfo['extension'] ?? '';
        $name = $pathInfo['filename'];
        
        // Sanitize filename
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $timestamp = date('YmdHis');
        $random = substr(str_shuffle('0123456789abcdef'), 0, 6);
        
        return "{$name}_{$timestamp}_{$random}.{$extension}";
    }
    
    private function generateFilePath(string $entityType, string $category, string $filename): string {
        return "{$entityType}/{$category}/{$filename}";
    }
    
    private function linkToEntity(int $documentId, string $entityType, int $entityId, string $relationshipType = 'primary'): int {
        $sql = "INSERT INTO entity_documents (document_id, entity_type, entity_id, relationship_type) VALUES (?, ?, ?, ?)";
        return $this->db->insert($sql, [$documentId, $entityType, $entityId, $relationshipType], ['i', 's', 'i', 's']);
    }
    
    private function addTags(int $documentId, array $tags): void {
        foreach ($tags as $tagName) {
            // Find or create tag
            $tag = $this->db->selectOne("SELECT id FROM document_tags WHERE name = ?", [$tagName], ['s']);
            if (!$tag) {
                $tagId = $this->db->insert("INSERT INTO document_tags (name) VALUES (?)", [$tagName], ['s']);
            } else {
                $tagId = $tag['id'];
                // Update usage count
                $this->db->update("UPDATE document_tags SET usage_count = usage_count + 1 WHERE id = ?", [$tagId], ['i']);
            }
            
            // Link tag to document
            try {
                $this->db->insert(
                    "INSERT INTO document_tag_assignments (document_id, tag_id) VALUES (?, ?)",
                    [$documentId, $tagId],
                    ['i', 'i']
                );
            } catch (Exception $e) {
                // Ignore duplicate key errors
            }
        }
    }
    
    private function logAccess(int $documentId, string $action, string $user, string $ipAddress = null): void {
        $ip = $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $sql = "INSERT INTO document_access_log (document_id, action, user_identifier, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
        $this->db->insert($sql, [$documentId, $action, $user, $ip, $userAgent], ['i', 's', 's', 's', 's']);
    }
}