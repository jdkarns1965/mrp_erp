/**
 * Document Manager - Handles document uploads, listing, and management
 */
class DocumentManager {
    constructor(entityType, entityId, containerId = 'documents-container') {
        this.entityType = entityType;
        this.entityId = entityId;
        this.container = document.getElementById(containerId);
        this.apiUrl = '../api/documents.php';
        this.downloadUrl = '../api/document-download.php';
        
        this.init();
    }
    
    init() {
        this.createInterface();
        this.bindEvents();
        this.loadDocuments();
        this.loadCategories();
    }
    
    bindEvents() {
        document.getElementById('upload-btn').addEventListener('click', () => this.showUploadForm());
        document.getElementById('cancel-btn').addEventListener('click', () => this.hideUploadForm());
    }
    
    createInterface() {
        this.container.innerHTML = `
            <div class="document-manager">
                <div class="document-header">
                    <h3>Documents</h3>
                    <button class="btn-primary" id="upload-btn">
                        <span class="icon icon-upload"></span> Upload Document
                    </button>
                </div>
                
                <div class="upload-form" id="upload-form" style="display: none;">
                    <div class="form-group">
                        <label>Select File</label>
                        <input type="file" id="file-input" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.tiff,.txt,.zip,.dwg,.dxf" required>
                        <small class="form-help">Max size: 50MB. Allowed: PDF, Office docs, Images, CAD files</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" id="doc-title" placeholder="Document title (optional)">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select id="doc-category">
                                <option value="">Select category...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="doc-description" rows="2" placeholder="Brief description (optional)"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Tags</label>
                        <input type="text" id="doc-tags" placeholder="Comma-separated tags (e.g., critical, latest, approved)">
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-primary" onclick="documentManager.uploadDocument()">
                            <span class="icon icon-upload"></span> Upload
                        </button>
                        <button type="button" class="btn-secondary" id="cancel-btn">Cancel</button>
                    </div>
                    
                    <div class="upload-progress" id="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <span class="progress-text" id="progress-text">Uploading...</span>
                    </div>
                </div>
                
                <div class="documents-list" id="documents-list">
                    <div class="loading">Loading documents...</div>
                </div>
                
                <!-- Edit Modal -->
                <div class="modal" id="edit-modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Document Details</h3>
                            <button class="modal-close" onclick="documentManager.hideEditModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="edit-doc-id">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" id="edit-doc-title" placeholder="Document title">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select id="edit-doc-category">
                                    <option value="">Select category...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea id="edit-doc-description" rows="3" placeholder="Brief description"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-primary" onclick="documentManager.saveDocumentEdit()">Save</button>
                            <button class="btn btn-outline" onclick="documentManager.hideEditModal()">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    async loadCategories() {
        try {
            const response = await fetch(`${this.apiUrl}?categories=1`);
            const categories = await response.json();
            
            // Populate upload form category dropdown
            const select = document.getElementById('doc-category');
            select.innerHTML = '<option value="">Select category...</option>';
            
            // Populate edit modal category dropdown
            const editSelect = document.getElementById('edit-doc-category');
            editSelect.innerHTML = '<option value="">Select category...</option>';
            
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
                
                const editOption = document.createElement('option');
                editOption.value = category.id;
                editOption.textContent = category.name;
                editSelect.appendChild(editOption);
            });
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }
    
    async loadDocuments() {
        try {
            const response = await fetch(`${this.apiUrl}?entity_type=${this.entityType}&entity_id=${this.entityId}`);
            const documents = await response.json();
            
            this.renderDocuments(documents);
        } catch (error) {
            console.error('Error loading documents:', error);
            document.getElementById('documents-list').innerHTML = 
                '<div class="error">Error loading documents</div>';
        }
    }
    
    renderDocuments(documents) {
        const listContainer = document.getElementById('documents-list');
        
        if (documents.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon"></div>
                    <p>No documents uploaded yet</p>
                    <small>Upload your first document using the button above</small>
                </div>
            `;
            return;
        }
        
        const html = documents.map(doc => this.createDocumentCard(doc)).join('');
        listContainer.innerHTML = html;
    }
    
    createDocumentCard(doc) {
        const fileSize = this.formatFileSize(doc.file_size);
        const uploadDate = new Date(doc.created_at).toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
        
        return `
            <div class="document-item" data-id="${doc.id}">
                <div class="document-icon">
                    ${this.getFileIcon(doc.mime_type)}
                </div>
                <div class="document-info">
                    <h4>${doc.title || doc.original_filename}</h4>
                    <div class="document-meta">
                        <span>${fileSize}</span>
                        <span>•</span>
                        <span>${uploadDate}</span>
                        ${doc.category_name ? `<span>•</span><span>${doc.category_name}</span>` : ''}
                        ${doc.download_count > 0 ? `<span>•</span><span>${doc.download_count} views</span>` : ''}
                    </div>
                    ${doc.description ? `<div class="document-description">${doc.description}</div>` : ''}
                </div>
                <div class="document-actions">
                    <button class="btn btn-outline btn-sm" onclick="documentManager.viewDocument(${doc.id})" title="View/Download">
                        View
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="documentManager.editDocument(${doc.id})" title="Edit Details">
                        Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="documentManager.deleteDocument(${doc.id})" title="Delete">
                        Delete
                    </button>
                </div>
            </div>
        `;
    }
    
    showUploadForm() {
        document.getElementById('upload-form').style.display = 'block';
        document.getElementById('file-input').focus();
    }
    
    hideUploadForm() {
        document.getElementById('upload-form').style.display = 'none';
        this.clearForm();
    }
    
    clearForm() {
        document.getElementById('file-input').value = '';
        document.getElementById('doc-title').value = '';
        document.getElementById('doc-description').value = '';
        document.getElementById('doc-tags').value = '';
        document.getElementById('doc-category').value = '';
    }
    
    async uploadDocument() {
        const fileInput = document.getElementById('file-input');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Please select a file to upload');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('entity_type', this.entityType);
        formData.append('entity_id', this.entityId);
        formData.append('title', document.getElementById('doc-title').value);
        formData.append('description', document.getElementById('doc-description').value);
        formData.append('category_id', document.getElementById('doc-category').value);
        formData.append('tags', document.getElementById('doc-tags').value);
        formData.append('user', 'current_user'); // TODO: Get actual user
        
        this.showProgress();
        
        try {
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.hideProgress();
                this.hideUploadForm();
                this.loadDocuments(); // Refresh list
                this.showMessage('Document uploaded successfully!', 'success');
            } else {
                throw new Error(result.error || 'Upload failed');
            }
        } catch (error) {
            this.hideProgress();
            this.showMessage(`Upload failed: ${error.message}`, 'error');
        }
    }
    
    async viewDocument(documentId) {
        window.open(`${this.downloadUrl}?id=${documentId}&user=current_user`, '_blank');
    }
    
    async deleteDocument(documentId) {
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiUrl}?id=${documentId}&user=current_user`, {
                method: 'DELETE'
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.loadDocuments(); // Refresh list
                this.showMessage('Document deleted successfully!', 'success');
            } else {
                throw new Error(result.error || 'Delete failed');
            }
        } catch (error) {
            this.showMessage(`Delete failed: ${error.message}`, 'error');
        }
    }
    
    async editDocument(documentId) {
        try {
            // Fetch document details
            const response = await fetch(`${this.apiUrl}?id=${documentId}`);
            const documentData = await response.json();
            
            if (response.ok) {
                // Populate edit form
                document.getElementById('edit-doc-id').value = documentData.id;
                document.getElementById('edit-doc-title').value = documentData.title || '';
                document.getElementById('edit-doc-description').value = documentData.description || '';
                document.getElementById('edit-doc-category').value = documentData.category_id || '';
                
                // Show edit modal
                this.showEditModal();
            } else {
                throw new Error(documentData.error || 'Failed to load document details');
            }
        } catch (error) {
            this.showMessage(`Error loading document: ${error.message}`, 'error');
        }
    }
    
    showEditModal() {
        document.getElementById('edit-modal').style.display = 'flex';
    }
    
    hideEditModal() {
        document.getElementById('edit-modal').style.display = 'none';
    }
    
    async saveDocumentEdit() {
        const documentId = document.getElementById('edit-doc-id').value;
        const title = document.getElementById('edit-doc-title').value;
        const description = document.getElementById('edit-doc-description').value;
        const categoryId = document.getElementById('edit-doc-category').value;
        
        try {
            const response = await fetch(`${this.apiUrl}?id=${documentId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    title: title,
                    description: description,
                    category_id: categoryId || null,
                    user: 'current_user'
                })
            });
            
            const result = await response.json();
            
            if (response.ok) {
                this.hideEditModal();
                this.loadDocuments(); // Refresh list
                this.showMessage('Document updated successfully!', 'success');
            } else {
                throw new Error(result.error || 'Update failed');
            }
        } catch (error) {
            this.showMessage(`Update failed: ${error.message}`, 'error');
        }
    }
    
    showProgress() {
        document.getElementById('upload-progress').style.display = 'block';
        // Simulate progress for now - in real implementation, track actual upload progress
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress >= 100) {
                progress = 100;
                clearInterval(interval);
            }
            document.getElementById('progress-fill').style.width = progress + '%';
            document.getElementById('progress-text').textContent = `Uploading... ${Math.round(progress)}%`;
        }, 100);
    }
    
    hideProgress() {
        document.getElementById('upload-progress').style.display = 'none';
        document.getElementById('progress-fill').style.width = '0%';
    }
    
    showMessage(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 4px;
            color: white;
            z-index: 1000;
            background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#3B82F6'};
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
    
    formatFileSize(bytes) {
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    getFileIcon(mimeType) {
        if (mimeType.includes('pdf')) return '📄';
        if (mimeType.includes('image')) return '🖼️';
        if (mimeType.includes('word')) return '📝';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return '📊';
        if (mimeType.includes('zip')) return '🗜️';
        return '📎';
    }
    
    getIcon(iconName) {
        const iconMap = {
            'file-text': '<span class="icon icon-file-text"></span>',
            'image': '<span class="icon icon-image"></span>',
            'award': '<span class="icon icon-file" style="color: #f59e0b"></span>',
            'shield': '<span class="icon icon-file" style="color: #ef4444"></span>',
            'book': '<span class="icon icon-file" style="color: #8b5cf6"></span>',
            'package': '<span class="icon icon-file" style="color: #ec4899"></span>',
            'check-circle': '<span class="icon icon-file" style="color: #06b6d4"></span>',
            'clipboard-list': '<span class="icon icon-file" style="color: #84cc16"></span>',
            'document': '<span class="icon icon-file"></span>'
        };
        return iconMap[iconName] || '<span class="icon icon-file"></span>';
    }
}

// Global instance holder
let documentManager;