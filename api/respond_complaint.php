<?php
/**
 * api/respond_complaint.php — CaseFlowX
 * Backend endpoint for FIR Officers to accept or reject citizen-submitted complaints.
 * SCRUM-160: Develop backend logic for complaint retrieval and response submission
 * SCRUM-158: Integrate authentication and authorization for FIR Officer complaint access
 * SCRUM-159: Implement notification system for citizen upon officer response
 * SCRUM-161: Implement error handling and feedback
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Auth and Auth Check (SCRUM-158)
if (empty($_SESSION['logged_in']) && !isset($_SESSION['user_id']) && empty($_SESSION['officer_id']) && empty($_SESSION['citizen_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
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
        echo json_encode(['success' => false, 'message' => 'Invalid session or account inactive']);
        exit;
    }

    // Only FIR Officer (role = 'Officer' in users, or 'FIR Officer'/'Supervisor'/'Admin' in officers)
    $allowedRoles = ['Officer', 'FIR Officer', 'Supervisor', 'Admin'];
    if (!in_array($currentUserRole, $allowedRoles, true)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Only FIR Officers can respond to complaints.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    // 2. Input Collection & Validation (SCRUM-161)
    $case_id = (int)($_POST['case_id'] ?? 0);
    $action = trim($_POST['action'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (!$case_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing case ID.']);
        exit;
    }

    if (!in_array($action, ['accept', 'reject'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action. Must be accept or reject.']);
        exit;
    }

    if ($action === 'reject' && $reason === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
        exit;
    }

    // Fetch case details to ensure it exists and is unassigned/open
    $stmtCase = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmtCase->execute([$case_id]);
    $case = $stmtCase->fetch();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found.']);
        exit;
    }

    // Check if complaint is already responded to (must be unassigned and open/Submitted)
    if ($case['officer_id'] !== null || !in_array($case['status'], ['open', 'Submitted'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This complaint has already been processed.']);
        exit;
    }

    // 3. Perform Response (SCRUM-160)
    if ($action === 'accept') {
        // Transition status to in_progress and assign officer_id
        $stmtUpdate = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'in_progress', modified_by = ?, modified_at = datetime('now')
            WHERE id = ?
        ");
        $stmtUpdate->execute([$currentUserId, $currentUserId, $case_id]);

        // Add timeline event
        add_case_timeline_event($db, $case_id, 'status_change', 'Complaint Accepted', "Complaint accepted by FIR Officer: {$currentUserName}.", $currentUserName);

        // Citizen Notification (SCRUM-159)
        if (!empty($case['citizen_id'])) {
            $notifTitle = "Complaint Accepted";
            $notifMsg = "Your complaint '{$case['title']}' (#{$case['case_number']}) has been accepted and is now in progress.";
            $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmtNotif->execute([$case['citizen_id'], $notifTitle, $notifMsg]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Complaint accepted and registered successfully.'
        ]);
        exit;

    } else {
        // Rejection: transition status to Rejected and assign officer_id
        $stmtUpdate = $db->prepare("
            UPDATE cases 
            SET officer_id = ?, status = 'Rejected', modified_by = ?, modified_at = datetime('now')
            WHERE id = ?
        ");
        $stmtUpdate->execute([$currentUserId, $currentUserId, $case_id]);

        // Add timeline event with rejection reason
        add_case_timeline_event($db, $case_id, 'status_change', 'Complaint Rejected', "Complaint rejected by FIR Officer: {$currentUserName}. Reason: {$reason}", $currentUserName);

        // Citizen Notification (SCRUM-159)
        if (!empty($case['citizen_id'])) {
            $notifTitle = "Complaint Rejected";
            $notifMsg = "Your complaint '{$case['title']}' (#{$case['case_number']}) was rejected. Reason: {$reason}";
            $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $stmtNotif->execute([$case['citizen_id'], $notifTitle, $notifMsg]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Complaint rejected successfully.'
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
