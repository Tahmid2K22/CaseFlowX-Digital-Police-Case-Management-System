<?php
/**
 * api/upload_case_evidence.php — Handles uploading digital forensic evidence linked to a case.
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_SESSION['user_id']) && empty($_SESSION['officer_id']) && empty($_SESSION['citizen_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = get_db();

    // Resolve user details
    $currentUserRole = $_SESSION['role'] ?? '';
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $currentUserName = $_SESSION['username'] ?? '';

    $isOfficerSession = !empty($_SESSION['officer_id']);

    if ($isOfficerSession) {
        $stmt = $db->prepare('SELECT id, badge_number, full_name, email, phone, station_code, role, status FROM officers WHERE id = ? AND status = ?');
        $stmt->execute([$_SESSION['officer_id'], 'active']);
        $officer = $stmt->fetch();
        if ($officer) {
            $currentUserRole = $officer['role'];
            $currentUserId = $officer['id'];
            $currentUserName = $officer['full_name'];
        }
    } else {
        $stmt = $db->prepare('SELECT id, full_name, role, status FROM users WHERE id = ? AND status = ?');
        $stmt->execute([$currentUserId, 'Active']);
        $user = $stmt->fetch();
        if ($user) {
            $currentUserRole = $user['role'];
            $currentUserName = $user['full_name'];
        }
    }

    if (empty($currentUserRole)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid session or account inactive']);
        exit;
    }

    $case_id = (int)($_POST['case_id'] ?? 0);
    if (!$case_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid case ID']);
        exit;
    }

    $stmtCase = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmtCase->execute([$case_id]);
    $case = $stmtCase->fetch();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Case not found']);
        exit;
    }

    // Access control check: Admins, Officers, and Assigned Investigators only
    $allowed = false;
    if ($currentUserRole === 'Admin' || $currentUserRole === 'Officer' || $currentUserRole === 'FIR Officer' || $currentUserRole === 'Supervisor') {
        $allowed = true;
    } elseif ($currentUserRole === 'Investigator') {
        if ((int)$case['investigator_id'] === (int)$currentUserId) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Only the assigned investigator or managing officers can upload evidence.']);
        exit;
    }

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['file'];
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'video/mp4'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    // Validate extension and mime type correlation
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']);
    
    $mimeMap = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'mp4'  => 'video/mp4'
    ];

    if (!isset($mimeMap[$ext]) || $mimeMap[$ext] !== $mime) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type or extension mismatch. Only PDF, JPG, PNG, and MP4 are allowed.']);
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

    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($file['name']));
    $filePath = $uploadDir . $fileName;
    $relativeVisitPath = 'uploads/' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Insert into DB
        $stmtEv = $db->prepare("
            INSERT INTO fir_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtEv->execute([$case_id, basename($file['name']), $relativeVisitPath, $mime, $file['size'], $currentUserId]);

        // Log timeline event
        add_case_timeline_event($db, $case_id, 'evidence_uploaded', 'Evidence Uploaded', 'Forensic evidence file uploaded: ' . basename($file['name']), $currentUserName);

        // Update modified_by / modified_at in cases table
        $stmtUpdateCase = $db->prepare("UPDATE cases SET modified_by = ?, modified_at = datetime('now') WHERE id = ?");
        $stmtUpdateCase->execute([$currentUserId, $case_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Evidence file uploaded successfully',
            'evidence' => [
                'file_name' => basename($file['name']),
                'file_path' => $relativeVisitPath,
                'file_type' => $mime,
                'file_size' => $file['size']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
