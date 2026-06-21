<?php
/**
 * api/delete_case_evidence.php — Handles deleting an uploaded case evidence file.
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

    $evidence_id = (int)($_POST['evidence_id'] ?? 0);
    if (!$evidence_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid evidence ID']);
        exit;
    }

    // Fetch evidence record
    $stmtEv = $db->prepare("SELECT * FROM fir_evidence WHERE id = ?");
    $stmtEv->execute([$evidence_id]);
    $evidence = $stmtEv->fetch();

    if (!$evidence) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Evidence record not found']);
        exit;
    }

    $case_id = (int)$evidence['case_id'];

    // Fetch case record
    $stmtCase = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmtCase->execute([$case_id]);
    $case = $stmtCase->fetch();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Associated case not found']);
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
        echo json_encode(['success' => false, 'error' => 'Access denied. Only the assigned investigator or managing officers can delete evidence.']);
        exit;
    }

    // Delete file from disk
    $fullPath = __DIR__ . '/../' . $evidence['file_path'];
    if (file_exists($fullPath) && is_file($fullPath)) {
        unlink($fullPath);
    }

    // Delete record from DB
    $stmtDel = $db->prepare("DELETE FROM fir_evidence WHERE id = ?");
    $stmtDel->execute([$evidence_id]);

    // Log timeline event
    add_case_timeline_event($db, $case_id, 'other', 'Evidence Deleted', 'Forensic evidence file deleted: ' . $evidence['file_name'], $currentUserName);

    // Update modified_by / modified_at in cases table
    $stmtUpdateCase = $db->prepare("UPDATE cases SET modified_by = ?, modified_at = datetime('now') WHERE id = ?");
    $stmtUpdateCase->execute([$currentUserId, $case_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Evidence file deleted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
