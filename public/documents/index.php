<?php
session_start();
require_once '../../includes/header.php';
require_once '../../classes/Document.php';

$documentModel = new Document();

// Simple search parameter
$searchQuery = $_GET['search'] ?? '';

// Get recent documents (simplified query)
$documents = [];
if ($searchQuery) {
    $documents = $documentModel->searchDocuments($searchQuery, ['limit' => 50]);
} else {
    // Show recent documents
    $documents = $documentModel->searchDocuments('', ['limit' => 20]);
}
?>

<div class="container">
    <div class="page-header">
        <h1>Documents</h1>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="alert('Upload feature coming soon')">
                + Upload Document
            </button>
        </div>
    </div>

    <!-- Simple Search Bar -->
    <div class="search-bar">
        <form method="GET" class="search-form">
            <input type="text" 
                   name="search" 
                   value="<?php echo htmlspecialchars($searchQuery); ?>" 
                   placeholder="Search documents..."
                   class="search-input">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($searchQuery): ?>
                <a href="index.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Documents Section -->
    <div class="documents-section">
        <h2 class="section-title">
            <?php if ($searchQuery): ?>
                Search Results (<?php echo count($documents); ?>)
            <?php else: ?>
                Recent Documents
            <?php endif; ?>
        </h2>
        
        <!-- Documents List -->
        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <p>No documents found</p>
                <button class="btn btn-outline" onclick="alert('Upload feature coming soon')">
                    Upload your first document
                </button>
            </div>
        <?php else: ?>
            <div class="documents-list">
                <?php foreach ($documents as $doc): ?>
                    <div class="document-item">
                        <div class="document-icon">
                            <?php echo getFileIcon($doc['mime_type']); ?>
                        </div>
                        <div class="document-info">
                            <h3><?php echo htmlspecialchars($doc['title'] ?: $doc['original_filename']); ?></h3>
                            <div class="document-meta">
                                <span><?php echo formatFileSize($doc['file_size']); ?></span>
                                <span>•</span>
                                <span><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
                                <?php if ($doc['category_name']): ?>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($doc['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="document-actions">
                            <a href="../api/document-download.php?id=<?php echo $doc['id']; ?>&user=current_user" 
                               class="btn btn-outline btn-sm" target="_blank">
                                View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Page Layout */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.page-header h1 {
    margin: 0;
    font-size: 2rem;
    color: #111827;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

/* Search Bar */
.search-bar {
    margin-bottom: 2rem;
}

.search-form {
    display: flex;
    gap: 0.5rem;
    max-width: 600px;
}

.search-input {
    flex: 1;
    padding: 0.625rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Documents Section */
.documents-section {
    margin-top: 2rem;
}

.section-title {
    font-size: 1.25rem;
    color: #374151;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

/* Documents List */
.documents-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.document-item:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.document-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    border-radius: 8px;
}

.document-info {
    flex: 1;
    min-width: 0;
}

.document-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 0.95rem;
    font-weight: 500;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.document-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #6b7280;
}

.document-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #6b7280;
}

.empty-state p {
    margin: 0 0 1rem 0;
    font-size: 1rem;
}

/* Icons */
.icon {
    width: 24px;
    height: 24px;
    display: inline-block;
}

.icon-pdf { color: #dc2626; }
.icon-image { color: #10b981; }
.icon-document { color: #3b82f6; }
.icon-spreadsheet { color: #059669; }
.icon-zip { color: #7c3aed; }
.icon-file-text { color: #6b7280; }
.icon-file { color: #6b7280; }

/* Mobile Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .search-form {
        flex-direction: column;
    }
    
    .document-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .document-actions {
        width: 100%;
    }
    
    .document-actions .btn {
        flex: 1;
        text-align: center;
    }
}
</style>

<?php
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return '📄';
    if (strpos($mimeType, 'image') !== false) return '🖼️';
    if (strpos($mimeType, 'word') !== false) return '📝';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'spreadsheet') !== false) return '📊';
    if (strpos($mimeType, 'zip') !== false) return '🗜️';
    return '📎';
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}
?>

<?php require_once '../../includes/footer.php'; ?>