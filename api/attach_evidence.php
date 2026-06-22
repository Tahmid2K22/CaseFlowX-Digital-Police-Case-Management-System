<?php
/**
 * api/attach_evidence.php — Handles uploading and attaching evidence directly to an existing case.
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

$caseId = (int)($_POST['case_id'] ?? 0);
if (!$caseId) {
    echo json_encode(['success' => false, 'error' => 'Invalid case ID']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

try {
    $db = get_db();

    // Authorization check: Officer must have accepted the case
    $stmt = $db->prepare("SELECT id, officer_id FROM cases WHERE id = ?");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        echo json_encode(['success' => false, 'error' => 'Case not found']);
        exit;
    }

    if ((int)$case['officer_id'] !== (int)$officer['id']) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized. This case is not assigned to you.']);
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
        $fsize = filesize($filePath);
        $ftype = mime_content_type($filePath);

        $stmtEv = $db->prepare("
            INSERT INTO fir_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtEv->execute([$caseId, basename($file['name']), $relativeVisitPath, $ftype, $fsize, $officer['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Evidence attached successfully',
            'path' => $relativeVisitPath
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
