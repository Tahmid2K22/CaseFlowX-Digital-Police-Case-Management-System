<?php
// api/admin_fir.php - Admin actions for FIR records
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// Only Admins allowed
if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = $_POST['action'] ?? '';
$fir_id = intval($_POST['fir_id'] ?? 0);

if (!$fir_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid FIR ID.']);
    exit;
}

try {
    $db = get_db();

    // Fetch FIR record
    $stmt = $db->prepare("SELECT * FROM fir_records WHERE id = ?");
    $stmt->execute([$fir_id]);
    $fir = $stmt->fetch();

    if (!$fir) {
        echo json_encode(['success' => false, 'message' => 'FIR record not found.']);
        exit;
    }

    if ($action === 'approve') {
        // Update fir_records status to 'Registered'
        $stmt = $db->prepare("UPDATE fir_records SET status = 'Registered', modified_by = ?, modified_at = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $fir_id]);

        // Update corresponding case status in cases table to 'Registered'
        $stmtCase = $db->prepare("UPDATE cases SET status = 'Registered', modified_by = ?, modified_at = datetime('now') WHERE fir_number = ?");
        $stmtCase->execute([$_SESSION['user_id'], $fir['fir_number']]);

        // Fetch corresponding case info for notifications
        $stmtCaseId = $db->prepare("SELECT id, citizen_id, officer_id FROM cases WHERE fir_number = ?");
        $stmtCaseId->execute([$fir['fir_number']]);
        $caseInfo = $stmtCaseId->fetch();

        // Create notifications
        if ($caseInfo) {
            $title = "FIR Approved";
            $msg = "FIR " . $fir['fir_number'] . " has been approved by the Administrator and is now registered.";
            
            // Notify Citizen
            if (!empty($caseInfo['citizen_id'])) {
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmtNotif->execute([$caseInfo['citizen_id'], $title, $msg]);
            }
            // Notify filing Officer
            if (!empty($caseInfo['officer_id'])) {
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmtNotif->execute([$caseInfo['officer_id'], $title, $msg]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'FIR approved and registered successfully.']);
        exit;

    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        
        // Update fir_records status to 'Rejected'
        $stmt = $db->prepare("UPDATE fir_records SET status = 'Rejected', modified_by = ?, modified_at = datetime('now') WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $fir_id]);

        // Update corresponding case status in cases table to 'Rejected'
        $stmtCase = $db->prepare("UPDATE cases SET status = 'Rejected', modified_by = ?, modified_at = datetime('now') WHERE fir_number = ?");
        $stmtCase->execute([$_SESSION['user_id'], $fir['fir_number']]);

        // Fetch corresponding case info for notifications
        $stmtCaseId = $db->prepare("SELECT id, citizen_id, officer_id FROM cases WHERE fir_number = ?");
        $stmtCaseId->execute([$fir['fir_number']]);
        $caseInfo = $stmtCaseId->fetch();

        // Create notifications
        if ($caseInfo) {
            $title = "FIR Rejected";
            $msg = "FIR " . $fir['fir_number'] . " was rejected by the Administrator." . ($reason ? " Reason: " . $reason : "");
            
            // Notify Citizen
            if (!empty($caseInfo['citizen_id'])) {
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmtNotif->execute([$caseInfo['citizen_id'], $title, $msg]);
            }
            // Notify filing Officer
            if (!empty($caseInfo['officer_id'])) {
                $stmtNotif = $db->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                $stmtNotif->execute([$caseInfo['officer_id'], $title, $msg]);
            }
        }

        echo json_encode(['success' => true, 'message' => 'FIR rejected successfully.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
}
