<?php
/**
 * api/upload_evidence.php — Handles asynchronous file uploads for FIR evidence.
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

// Auth check
$officer = require_officer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'video/mp4'];
$maxSize = 10 * 1024 * 1024; // 10MB

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF, JPG, PNG, and MP4 are allowed.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum 10MB allowed.']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = time() . '_' . basename($file['name']);
$filePath = $uploadDir . $fileName;
$relativeVisitPath = 'uploads/' . $fileName;

if (move_uploaded_file($file['tmp_name'], $filePath)) {
    try {
        $db = get_db();
        // Note: fir_evidence table has case_id, not fir_id.
        // During the wizard, we don't have a case_id yet if it's a new FIR.
        // This is a design issue. We might need a temporary session or 
        // allow case_id to be NULL or use a temporary table.
        // For now, I'll allow case_id to be NULL in the schema if needed, 
        // or just return the file path and save it later.
        
        // Let's check the schema again.
        // case_id INTEGER NOT NULL
        // We need to fix the schema to allow case_id to be NULL initially, or 
        // store it in session.
        
        echo json_encode([
            'success' => true,
            'id' => $fileName, // Using fileName as temp ID
            'path' => $relativeVisitPath
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
