<?php
declare(strict_types=1);

require_once __DIR__ . '/../../classes/Document.php';

try {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo 'Document ID required';
        exit;
    }
    
    $documentId = (int)$_GET['id'];
    $user = $_GET['user'] ?? 'anonymous';
    
    $document = new Document();
    $fileInfo = $document->downloadDocument($documentId, $user);
    
    // Security check: ensure file exists and is within allowed directory
    if (!file_exists($fileInfo['path']) || strpos(realpath($fileInfo['path']), realpath(Document::UPLOAD_PATH)) !== 0) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    
    // Set appropriate headers
    header('Content-Type: ' . $fileInfo['mime_type']);
    header('Content-Length: ' . $fileInfo['file_size']);
    header('Content-Disposition: inline; filename="' . addslashes($fileInfo['filename']) . '"');
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($fileInfo['path'])) . ' GMT');
    
    // Output file
    readfile($fileInfo['path']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}