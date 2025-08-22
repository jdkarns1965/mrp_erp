<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../classes/Document.php';

try {
    $document = new Document();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($document);
            break;
            
        case 'POST':
            handlePost($document);
            break;
            
        case 'PUT':
            handlePut($document);
            break;
            
        case 'DELETE':
            handleDelete($document);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGet(Document $document): void {
    if (isset($_GET['id'])) {
        // Get single document with details
        $doc = $document->getDocumentWithTags((int)$_GET['id']);
        if ($doc) {
            echo json_encode($doc);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Document not found']);
        }
        return;
    }
    
    if (isset($_GET['entity_type']) && isset($_GET['entity_id'])) {
        // Get documents for specific entity
        $docs = $document->getEntityDocuments($_GET['entity_type'], (int)$_GET['entity_id']);
        echo json_encode($docs);
        return;
    }
    
    if (isset($_GET['search'])) {
        // Search documents
        $filters = [];
        if (isset($_GET['category_id'])) {
            $filters['category_id'] = (int)$_GET['category_id'];
        }
        if (isset($_GET['entity_type']) && isset($_GET['entity_id'])) {
            $filters['entity_type'] = $_GET['entity_type'];
            $filters['entity_id'] = (int)$_GET['entity_id'];
        }
        
        $docs = $document->searchDocuments($_GET['search'], $filters);
        echo json_encode($docs);
        return;
    }
    
    if (isset($_GET['categories'])) {
        // Get categories
        $categories = $document->getCategories();
        echo json_encode($categories);
        return;
    }
    
    // Default: get recent documents
    $docs = $document->searchDocuments('', ['limit' => 20]);
    echo json_encode($docs);
}

function handlePost(Document $document): void {
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        return;
    }
    
    if (!isset($_POST['entity_type']) || !isset($_POST['entity_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Entity type and ID required']);
        return;
    }
    
    $file = $_FILES['file'];
    $entityType = $_POST['entity_type'];
    $entityId = (int)$_POST['entity_id'];
    
    $options = [
        'title' => $_POST['title'] ?? null,
        'description' => $_POST['description'] ?? null,
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'category' => $_POST['category'] ?? 'other',
        'relationship_type' => $_POST['relationship_type'] ?? 'primary',
        'user' => $_POST['user'] ?? 'system'
    ];
    
    // Handle tags
    if (!empty($_POST['tags'])) {
        $options['tags'] = is_array($_POST['tags']) ? $_POST['tags'] : explode(',', $_POST['tags']);
    }
    
    try {
        $result = $document->uploadDocument($file, $entityType, $entityId, $options);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'document' => $result
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handlePut(Document $document): void {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Document ID required']);
        return;
    }
    
    $documentId = (int)$_GET['id'];
    
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        return;
    }
    
    try {
        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
        
        $result = $document->update($documentId, $updateData);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Document updated successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Document not found']);
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleDelete(Document $document): void {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Document ID required']);
        return;
    }
    
    $documentId = (int)$_GET['id'];
    $user = $_GET['user'] ?? 'system';
    
    if ($document->deleteDocument($documentId, $user)) {
        echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Document not found']);
    }
}