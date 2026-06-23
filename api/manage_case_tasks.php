<?php
/**
 * api/manage_case_tasks.php — Handles task board CRUD operations (add, edit, update_status, delete) for case-specific tasks.
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

    if ($currentUserRole === 'Citizen') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Citizens cannot access task boards.']);
        exit;
    }

    // Input collection
    $case_id = (int)($_POST['case_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');

    if (!$case_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid case ID']);
        exit;
    }

    // Fetch case to verify existence and check investigator assignment
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
        echo json_encode(['success' => false, 'error' => 'Access denied. Only the assigned investigator or managing officers can manage tasks.']);
        exit;
    }

    // Perform action
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;

        if ($title === '') {
            echo json_encode(['success' => false, 'error' => 'Task title is required.']);
            exit;
        }

        $stmtInsert = $db->prepare("
            INSERT INTO case_tasks (case_id, title, description, status, created_by, assigned_to)
            VALUES (?, ?, ?, 'todo', ?, ?)
        ");
        $stmtInsert->execute([$case_id, $title, $description, $currentUserId, $assigned_to]);

        if ($assigned_to !== null) {
            $stmtNotif = $db->prepare("
                INSERT INTO notifications (user_id, title, message)
                VALUES (?, 'New Task Assigned', ?)
            ");
            $caseNumber = $case['case_number'] ?? '';
            $stmtNotif->execute([
                $assigned_to,
                "You have been assigned a new task: '{$title}' on Case {$caseNumber}."
            ]);
        }

        $assigneeNameText = '';
        if ($assigned_to !== null) {
            $stmtUser = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtUser->execute([$assigned_to]);
            $assigneeName = $stmtUser->fetchColumn();
            if ($assigneeName) {
                $assigneeNameText = " (Assigned to {$assigneeName})";
            }
        }
        add_case_timeline_event($db, $case_id, 'other', 'Task Created', "Task created: {$title}{$assigneeNameText}", $currentUserName);

    } elseif ($action === 'update_status') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status option selected.']);
            exit;
        }

        // Fetch task to verify it belongs to this case
        $taskStmt = $db->prepare("SELECT * FROM case_tasks WHERE id = ? AND case_id = ?");
        $taskStmt->execute([$task_id, $case_id]);
        $task = $taskStmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'error' => 'Task not found or not associated with this case.']);
            exit;
        }

        $stmtUpdate = $db->prepare("
            UPDATE case_tasks 
            SET status = ?, updated_at = datetime('now') 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$status, $task_id]);

        $statusText = $status === 'todo' ? 'To Do' : ($status === 'in_progress' ? 'In Progress' : 'Done');
        add_case_timeline_event($db, $case_id, 'other', 'Task Updated', "Task '{$task['title']}' moved to {$statusText}", $currentUserName);

    } elseif ($action === 'edit') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;

        if ($title === '') {
            echo json_encode(['success' => false, 'error' => 'Task title is required.']);
            exit;
        }

        // Fetch task to verify it belongs to this case
        $taskStmt = $db->prepare("SELECT * FROM case_tasks WHERE id = ? AND case_id = ?");
        $taskStmt->execute([$task_id, $case_id]);
        $task = $taskStmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'error' => 'Task not found or not associated with this case.']);
            exit;
        }

        $old_assigned_to = $task['assigned_to'] ? (int)$task['assigned_to'] : null;

        $stmtUpdate = $db->prepare("
            UPDATE case_tasks 
            SET title = ?, description = ?, assigned_to = ?, updated_at = datetime('now') 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$title, $description, $assigned_to, $task_id]);

        if ($assigned_to !== null && $assigned_to !== $old_assigned_to) {
            $stmtNotif = $db->prepare("
                INSERT INTO notifications (user_id, title, message)
                VALUES (?, 'Task Assigned', ?)
            ");
            $caseNumber = $case['case_number'] ?? '';
            $stmtNotif->execute([
                $assigned_to,
                "You have been assigned the task: '{$title}' on Case {$caseNumber}."
            ]);
        }

        $assigneeNameText = '';
        if ($assigned_to !== null) {
            $stmtUser = $db->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtUser->execute([$assigned_to]);
            $assigneeName = $stmtUser->fetchColumn();
            if ($assigneeName) {
                $assigneeNameText = " (Assigned to {$assigneeName})";
            }
        } else {
            $assigneeNameText = " (Unassigned)";
        }
        add_case_timeline_event($db, $case_id, 'other', 'Task Edited', "Task updated: {$title}{$assigneeNameText}", $currentUserName);

    } elseif ($action === 'delete') {
        $task_id = (int)($_POST['task_id'] ?? 0);

        // Fetch task to verify it belongs to this case
        $taskStmt = $db->prepare("SELECT * FROM case_tasks WHERE id = ? AND case_id = ?");
        $taskStmt->execute([$task_id, $case_id]);
        $task = $taskStmt->fetch();

        if (!$task) {
            echo json_encode(['success' => false, 'error' => 'Task not found or not associated with this case.']);
            exit;
        }

        $stmtDelete = $db->prepare("DELETE FROM case_tasks WHERE id = ?");
        $stmtDelete->execute([$task_id]);

        add_case_timeline_event($db, $case_id, 'other', 'Task Deleted', "Task deleted: {$task['title']}", $currentUserName);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
        exit;
    }

    // Update modified_by / modified_at in cases table
    $stmtUpdateCase = $db->prepare("UPDATE cases SET modified_by = ?, modified_at = datetime('now') WHERE id = ?");
    $stmtUpdateCase->execute([$currentUserId, $case_id]);

    // Fetch updated tasks list
    $taskStmt = $db->prepare("
        SELECT t.*, u.full_name AS assignee_name 
        FROM case_tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.case_id = ? 
        ORDER BY t.id ASC
    ");
    $taskStmt->execute([$case_id]);
    $tasks = $taskStmt->fetchAll();

    // Fetch updated timeline events
    $tlStmt = $db->prepare("SELECT * FROM case_timeline WHERE case_id = ? ORDER BY created_at ASC, id ASC");
    $tlStmt->execute([$case_id]);
    $timeline = $tlStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Task operation completed successfully',
        'tasks' => $tasks,
        'timeline' => $timeline
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
