<?php
/**
 * api/case.php — Handles case-related actions (e.g., Accept, Reject, Assign Investigator, Add Timeline Event).
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_SESSION['user_id']) && empty($_SESSION['officer_id']) && empty($_SESSION['citizen_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

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

    // Custom authentication block to resolve session user role/name
    $currentUserRole = $_SESSION['role'] ?? '';
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $currentUserName = $_SESSION['username'] ?? '';

    $isOfficerSession = !empty($_SESSION['officer_id']);
    $officer = null;

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
        echo json_encode(['success' => false, 'message' => 'Invalid session or account inactive']);
        exit;
    }

    if ($action === 'accept') {
        if (!$isOfficerSession && $currentUserRole !== 'Officer') {
            echo json_encode(['success' => false, 'message' => 'Only officers can accept cases.']);
            exit;
        }

        // Assign the case to the current officer and update status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'in_progress', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND (officer_id IS NULL OR status = 'closed')
        ");
        $stmt->execute([$currentUserId, $currentUserId, $case_id]);

        if ($stmt->rowCount() > 0) {
            add_case_timeline_event($db, $case_id, 'status_change', 'Case Accepted', 'The handling officer accepted this case.', $currentUserName);
            echo json_encode(['success' => true, 'message' => 'Case accepted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Case already accepted or not found']);
        }

    } elseif ($action === 'reject') {
        if (!$isOfficerSession && $currentUserRole !== 'Officer') {
            echo json_encode(['success' => false, 'message' => 'Only officers can reject cases.']);
            exit;
        }

        // Reject the case and associate it with the current officer by setting status to 'closed'
        $stmt = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'closed', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND officer_id IS NULL
        ");
        $stmt->execute([$currentUserId, $currentUserId, $case_id]);

        if ($stmt->rowCount() > 0) {
            add_case_timeline_event($db, $case_id, 'status_change', 'Case Rejected', 'The case was rejected by the officer.', $currentUserName);
            echo json_encode(['success' => true, 'message' => 'Case rejected successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Case cannot be rejected or not found']);
        }

    } elseif ($action === 'assign_investigator') {
        if (!$isOfficerSession && $currentUserRole !== 'Officer') {
            echo json_encode(['success' => false, 'message' => 'Only officers can assign investigators.']);
            exit;
        }

        $investigatorName = trim($_POST['investigating_officer'] ?? '');
        if ($investigatorName === '') {
            echo json_encode(['success' => false, 'message' => 'Investigating officer name is required.']);
            exit;
        }

        // Find investigator user to get their id
        $stmtInvUser = $db->prepare("SELECT id FROM users WHERE role = 'Investigator' AND full_name = ? LIMIT 1");
        $stmtInvUser->execute([$investigatorName]);
        $invUser = $stmtInvUser->fetch();
        $investigatorId = $invUser ? (int)$invUser['id'] : null;

        // Assign the investigator and set status to 'in_progress'
        $stmt = $db->prepare("
            UPDATE cases 
            SET investigating_officer = ?, investigator_id = ?, status = 'in_progress', modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND officer_id = ?
        ");
        $stmt->execute([$investigatorName, $investigatorId, $case_id, $currentUserId]);

        if ($stmt->rowCount() > 0) {
            // Populate default tasks if task board is empty for this case
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM case_tasks WHERE case_id = ?");
            $checkStmt->execute([$case_id]);
            if ((int)$checkStmt->fetchColumn() === 0) {
                $defaultTasks = [
                    'Review FIR and case details',
                    'Visit incident scene and gather initial evidence',
                    'Identify and interview witnesses',
                    'Identify potential suspects',
                    'Draft and submit final investigation report'
                ];
                $insertTaskStmt = $db->prepare("
                    INSERT INTO case_tasks (case_id, title, description, status, created_by)
                    VALUES (?, ?, '', 'todo', ?)
                ");
                foreach ($defaultTasks as $taskTitle) {
                    $insertTaskStmt->execute([$case_id, $taskTitle, $investigatorId ?: $currentUserId]);
                }
            }

            add_case_timeline_event($db, $case_id, 'investigator_assigned', 'Investigator Assigned', "Investigator assigned: " . $investigatorName, $currentUserName);
            echo json_encode(['success' => true, 'message' => 'Investigating officer assigned successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to assign investigator. Ensure the case is accepted by you.']);
        }

    } elseif ($action === 'add_timeline_event') {
        $event_type = trim($_POST['event_type'] ?? 'note_added');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($title === '') {
            echo json_encode(['success' => false, 'message' => 'Title is required.']);
            exit;
        }

        if (!in_array($event_type, ['note_added', 'status_change', 'other'], true)) {
            $event_type = 'note_added';
        }

        // Access control check for adding timeline event
        if ($currentUserRole === 'Investigator') {
            // Check if case is assigned to this investigator
            $stmtCase = $db->prepare("SELECT investigator_id FROM cases WHERE id = ?");
            $stmtCase->execute([$case_id]);
            $caseInv = $stmtCase->fetch();
            if (!$caseInv || (int)$caseInv['investigator_id'] !== (int)$currentUserId) {
                echo json_encode(['success' => false, 'message' => 'You can only add notes to cases assigned to you.']);
                exit;
            }
        } elseif ($currentUserRole !== 'Admin' && $currentUserRole !== 'Officer' && $currentUserRole !== 'FIR Officer' && $currentUserRole !== 'Supervisor') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
            exit;
        }

        $success = add_case_timeline_event($db, $case_id, $event_type, $title, $description, $currentUserName);
        if ($success) {
            $stmtUpdateCase = $db->prepare("UPDATE cases SET modified_by = ?, modified_at = datetime('now') WHERE id = ?");
            $stmtUpdateCase->execute([$currentUserId, $case_id]);
            echo json_encode(['success' => true, 'message' => 'Timeline event added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add timeline event.']);
        }

    } elseif ($action === 'update_case_status') {
        $status = trim($_POST['status'] ?? '');
        if (!in_array($status, ['in_progress', 'resolved', 'closed'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status option selected.']);
            exit;
        }

        // Fetch case to check permissions
        $stmtCase = $db->prepare("SELECT investigator_id, officer_id, status FROM cases WHERE id = ?");
        $stmtCase->execute([$case_id]);
        $case = $stmtCase->fetch();

        if (!$case) {
            echo json_encode(['success' => false, 'message' => 'Case not found.']);
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
            echo json_encode(['success' => false, 'message' => 'Access denied. Only the assigned investigator or managing officers can change case status.']);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE cases 
            SET status = ?, modified_by = ?, modified_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$status, $currentUserId, $case_id]);

        if ($stmt->rowCount() > 0) {
            // Log timeline event
            $statusText = $status === 'in_progress' ? 'In Progress' : ($status === 'resolved' ? 'Resolved' : 'Closed');
            add_case_timeline_event($db, $case_id, 'status_change', 'Case Status Updated', "Case status updated to: {$statusText}", $currentUserName);
            echo json_encode(['success' => true, 'message' => "Case status updated to {$statusText} successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update case status.']);
        }

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
