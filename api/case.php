<?php
/**
 * api/case.php — Handles case-related actions (e.g., Accept).
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$officer = require_officer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$case_id = (int)($_POST['case_id'] ?? 0);

if (!$case_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid case ID']);
    exit;
}

try {
    $db = get_db();

    if ($action === 'accept') {
        // Assign the case to the current officer and update status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'in_progress', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND (officer_id IS NULL OR status = 'closed')
        ");
        $stmt->execute([$officer['id'], $officer['id'], $case_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Case accepted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Case already accepted or not found']);
        }
    } elseif ($action === 'reject') {
        // Reject the case and associate it with the current officer by setting status to 'closed'
        $stmt = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'closed', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND officer_id IS NULL
        ");
        $stmt->execute([$officer['id'], $officer['id'], $case_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Case rejected successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Case cannot be rejected or not found']);
        }
    } elseif ($action === 'assign_investigator') {
        $investigator = trim($_POST['investigating_officer'] ?? '');
        if ($investigator === '') {
            echo json_encode(['success' => false, 'message' => 'Investigating officer name is required.']);
            exit;
        }

        // Assign the investigator and set status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE cases 
            SET investigating_officer = ?, status = 'in_progress', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND officer_id = ?
        ");
        $stmt->execute([$investigator, $officer['id'], $case_id, $officer['id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Investigating officer assigned successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign investigator. Ensure the case is accepted by you.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
