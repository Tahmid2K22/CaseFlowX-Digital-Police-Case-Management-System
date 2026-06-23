<?php
/**
 * api/get_case_details.php — Securely fetches full case details with timeline events.
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

$case_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$case_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid case ID']);
    exit;
}

try {
    $db = get_db();

    // Fetch case with officer and citizen names
    $stmt = $db->prepare("
        SELECT c.*, 
               COALESCE(o.full_name, u_off.full_name) as officer_name,
               COALESCE(ct.full_name, u_cit.full_name) as citizen_name,
               COALESCE(ct.phone, u_cit.phone) as citizen_phone,
               COALESCE(ct.email, u_cit.email) as citizen_email,
               COALESCE(ct.national_id, u_cit.national_id) as citizen_nid,
               COALESCE(ct.address, u_cit.address) as citizen_address
        FROM cases c
        LEFT JOIN officers o ON c.officer_id = o.id
        LEFT JOIN citizens ct ON c.citizen_id = ct.id
        LEFT JOIN users u_cit ON c.citizen_id = u_cit.id
        LEFT JOIN users u_off ON c.officer_id = u_off.id
        WHERE c.id = ?
    ");
    $stmt->execute([$case_id]);
    $case = $stmt->fetch();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Case not found']);
        exit;
    }

    // Access control check
    $allowed = false;
    $role = $_SESSION['role'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    $officerId = $_SESSION['officer_id'] ?? 0;
    $citizenId = $_SESSION['citizen_id'] ?? 0;
    $currentName = '';

    if (!empty($role)) {
        if ($role === 'Admin' || $role === 'Officer') {
            $allowed = true;
        } elseif ($role === 'Investigator') {
            if ($case['investigator_id'] == $userId) {
                $allowed = true;
            }
        } elseif ($role === 'Citizen') {
            if ($case['citizen_id'] == $userId) {
                $allowed = true;
            }
        }
        $currentName = $_SESSION['username'] ?? '';
    } elseif ($officerId > 0) {
        $allowed = true;
        $role = $_SESSION['officer_role'] ?? 'Officer';
        $currentName = $_SESSION['officer_name'] ?? '';
    } elseif ($citizenId > 0) {
        if ($case['citizen_id'] == $citizenId) {
            $allowed = true;
        }
        $role = 'Citizen';
        $currentName = $_SESSION['citizen_name'] ?? '';
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Fetch evidence files
    $evStmt = $db->prepare("SELECT * FROM fir_evidence WHERE case_id = ?");
    $evStmt->execute([$case_id]);
    $evidence = $evStmt->fetchAll();

    // Fetch suspect profiles
    $suspStmt = $db->prepare("SELECT * FROM suspect_profiles WHERE case_id = ? ORDER BY id ASC");
    $suspStmt->execute([$case_id]);
    $suspects = $suspStmt->fetchAll();

    // Fetch case tasks
    $taskStmt = $db->prepare("
        SELECT t.*, u.full_name AS assignee_name 
        FROM case_tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.case_id = ? 
        ORDER BY t.id ASC
    ");
    $taskStmt->execute([$case_id]);
    $tasks = $taskStmt->fetchAll();

    // Fetch timeline events ordered chronologically
    $tlStmt = $db->prepare("
        SELECT * FROM case_timeline 
        WHERE case_id = ? 
        ORDER BY created_at ASC, id ASC
    ");
    $tlStmt->execute([$case_id]);
    $timeline = $tlStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'case' => $case,
        'evidence' => $evidence,
        'timeline' => $timeline,
        'suspects' => $suspects,
        'tasks' => $tasks,
        'session_user' => [
            'role' => $role,
            'id' => !empty($role) && $role !== 'Citizen' ? $userId : ($officerId ?: $citizenId),
            'name' => $currentName
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
