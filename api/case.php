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
    } elseif ($action === 'update_status') {
        $status = trim($_POST['status'] ?? '');
        if (!in_array($status, ['Open', 'Closed', 'Pending'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid status value. Allowed: Open, Closed, Pending.']);
            exit;
        }

        // Update cases status
        $stmt = $db->prepare("
            UPDATE cases 
            SET status = ?, modified_by = ?, modified_at = datetime('now')
            WHERE id = ? AND officer_id = ?
        ");
        $stmt->execute([$status, $officer['id'], $case_id, $officer['id']]);

        if ($stmt->rowCount() > 0) {
            // Also sync with fir_records if this case was filed as an FIR
            $stmtCase = $db->prepare("SELECT fir_number FROM cases WHERE id = ?");
            $stmtCase->execute([$case_id]);
            $caseObj = $stmtCase->fetch();
            if ($caseObj && !empty($caseObj['fir_number'])) {
                $stmtFir = $db->prepare("
                    UPDATE fir_records 
                    SET status = ?, modified_by = ?, modified_at = datetime('now')
                    WHERE fir_number = ?
                ");
                $stmtFir->execute([$status, $officer['id'], $caseObj['fir_number']]);
            }
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status. Ensure the case is accepted by you.']);
        }
    } elseif ($action === 'log_physical_evidence') {
        // Auth check
        $stmtCheck = $db->prepare("SELECT id FROM cases WHERE id = ? AND officer_id = ?");
        $stmtCheck->execute([$case_id, $officer['id']]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Ensure the case is accepted by you.']);
            exit;
        }

        $item_name = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $serial_number = trim($_POST['serial_number'] ?? '');
        $recovered_at = trim($_POST['recovered_at'] ?? '');
        $recovered_location = trim($_POST['recovered_location'] ?? '');
        $recovered_by = trim($_POST['recovered_by'] ?? '');
        $current_custodian = trim($_POST['current_custodian'] ?? '');
        $status = trim($_POST['status'] ?? 'Secured');
        $initial_notes = trim($_POST['initial_notes'] ?? '');

        if ($item_name === '' || $recovered_at === '' || $recovered_location === '' || $recovered_by === '' || $current_custodian === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required evidence fields.']);
            exit;
        }

        // Start transaction
        $db->beginTransaction();
        try {
            $stmtInsert = $db->prepare("
                INSERT INTO physical_evidence (case_id, item_name, description, serial_number, recovered_at, recovered_location, recovered_by, current_custodian, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$case_id, $item_name, $description, $serial_number, $recovered_at, $recovered_location, $recovered_by, $current_custodian, $status]);
            $evidence_id = $db->lastInsertId();

            // Create initial chain-of-custody record
            $stmtCustody = $db->prepare("
                INSERT INTO evidence_chain_of_custody (evidence_id, officer_name, action_type, custody_date, location, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $notes = "Evidence logged and secured. Initial custodian: " . $current_custodian;
            if ($initial_notes !== '') {
                $notes .= ". Notes: " . $initial_notes;
            }
            $stmtCustody->execute([$evidence_id, $recovered_by, 'Recovery', $recovered_at, $recovered_location, $notes]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Physical evidence logged successfully.']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to log physical evidence: ' . $e->getMessage()]);
        }
    } elseif ($action === 'add_custody_transfer') {
        // Auth check
        $stmtCheck = $db->prepare("SELECT id FROM cases WHERE id = ? AND officer_id = ?");
        $stmtCheck->execute([$case_id, $officer['id']]);
        if (!$stmtCheck->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Ensure the case is accepted by you.']);
            exit;
        }

        $evidence_id = (int)($_POST['evidence_id'] ?? 0);
        $officer_name = trim($_POST['officer_name'] ?? '');
        $action_type = trim($_POST['action_type'] ?? '');
        $custody_date = trim($_POST['custody_date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $new_status = trim($_POST['status'] ?? ''); // Optional status update

        if (!$evidence_id || $officer_name === '' || $action_type === '' || $custody_date === '' || $location === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required custody transfer fields.']);
            exit;
        }

        // Verify evidence belongs to the case
        $stmtEvCheck = $db->prepare("SELECT id, status, current_custodian FROM physical_evidence WHERE id = ? AND case_id = ?");
        $stmtEvCheck->execute([$evidence_id, $case_id]);
        $evidenceItem = $stmtEvCheck->fetch();
        if (!$evidenceItem) {
            echo json_encode(['success' => false, 'message' => 'Evidence item not found or does not belong to this case.']);
            exit;
        }

        // Start transaction
        $db->beginTransaction();
        try {
            // Insert timeline record
            $stmtCustody = $db->prepare("
                INSERT INTO evidence_chain_of_custody (evidence_id, officer_name, action_type, custody_date, location, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtCustody->execute([$evidence_id, $officer_name, $action_type, $custody_date, $location, $notes]);

            // Update parent physical evidence custodian and status
            $update_status = ($new_status !== '') ? $new_status : $evidenceItem['status'];
            if ($action_type === 'Release' && $new_status === '') {
                $update_status = 'Released';
            } elseif ($action_type === 'Destruction' && $new_status === '') {
                $update_status = 'Destroyed';
            }

            $stmtUpdateEv = $db->prepare("
                UPDATE physical_evidence
                SET current_custodian = ?, status = ?
                WHERE id = ?
            ");
            
            $new_custodian = $officer_name;
            if ($action_type === 'Release') {
                $new_custodian = 'Released to Owner';
            } elseif ($action_type === 'Destruction') {
                $new_custodian = 'None (Destroyed)';
            }
            
            $stmtUpdateEv->execute([$new_custodian, $update_status, $evidence_id]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Custody transfer logged successfully.']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to log custody transfer: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
