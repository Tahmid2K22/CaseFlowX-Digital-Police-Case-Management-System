<?php
/**
 * tests/test_fir_attachment_and_status.php — Automated tests for FIR status updates and evidence attachments.
 */

require_once __DIR__ . '/../db.php';

echo "=== Running FIR Attachment & Status Update Tests ===\n";

try {
    $db = get_db();
    
    // 1. Seed Test Data
    echo "[Seeding] Creating test officer, citizen, and case...\n";
    
    // Seed Officer
    $badge = 'TEST-OFF-999';
    $db->prepare("DELETE FROM officers WHERE badge_number = ?")->execute([$badge]);
    $stmtOff = $db->prepare("
        INSERT INTO officers (badge_number, full_name, email, phone, station_code, role, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtOff->execute([
        $badge,
        'Test Officer 999',
        'test999@caseflowx.local',
        '01999999999',
        'HQ01',
        'FIR Officer',
        password_hash('Password@123', PASSWORD_DEFAULT),
        'active'
    ]);
    $officerId = $db->lastInsertId();
    
    // Seed Citizen
    $nid = '9999999999';
    $db->prepare("DELETE FROM citizens WHERE national_id = ?")->execute([$nid]);
    $stmtCit = $db->prepare("
        INSERT INTO citizens (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtCit->execute([
        'Test Citizen 999',
        $nid,
        '1990-01-01',
        'male',
        '01888888888',
        'citizen999@caseflowx.local',
        'Dhaka',
        'Dhaka',
        'Test Address',
        password_hash('Password@123', PASSWORD_DEFAULT),
        'active'
    ]);
    $citizenId = $db->lastInsertId();
    
    // Seed Case
    $caseNum = 'CF-TEST-999';
    $db->prepare("DELETE FROM cases WHERE case_number = ?")->execute([$caseNum]);
    $stmtCase = $db->prepare("
        INSERT INTO cases (citizen_id, case_number, title, description, status, priority, officer_id, station_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtCase->execute([
        $citizenId,
        $caseNum,
        'Test Case 999 Title',
        'Test case description of at least 10 characters.',
        'Open',
        'medium',
        $officerId,
        'HQ01'
    ]);
    $caseId = $db->lastInsertId();
    
    // Seed FIR Record
    $firNum = 'FIR-TEST-999';
    $db->prepare("DELETE FROM fir_records WHERE fir_number = ?")->execute([$firNum]);
    $stmtFir = $db->prepare("
        INSERT INTO fir_records (fir_number, complainant_name, complainant_nid, incident_date, incident_location, incident_description, officer_id, station_code, status, priority, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtFir->execute([
        $firNum,
        'Test Citizen 999',
        $nid,
        '2026-06-22',
        'Dhaka',
        'Test incident description of at least 20 characters.',
        $officerId,
        'HQ01',
        'Open',
        'medium',
        $officerId
    ]);
    $firId = $db->lastInsertId();

    echo "[Seed Success] Seeded test case ID: $caseId, FIR ID: $firId, Officer ID: $officerId\n\n";

    // 2. Test status CHECK constraints
    echo "[Test 1] Testing valid status update to 'Pending'...\n";
    $db->prepare("UPDATE cases SET status = 'Pending' WHERE id = ?")->execute([$caseId]);
    $status = $db->query("SELECT status FROM cases WHERE id = $caseId")->fetchColumn();
    if ($status === 'Pending') {
        echo "=> PASS: Status updated to 'Pending' successfully.\n\n";
    } else {
        throw new Exception("FAIL: Status is not 'Pending' (got: $status)");
    }

    echo "[Test 2] Testing valid status update to 'Closed'...\n";
    $db->prepare("UPDATE cases SET status = 'Closed' WHERE id = ?")->execute([$caseId]);
    $status = $db->query("SELECT status FROM cases WHERE id = $caseId")->fetchColumn();
    if ($status === 'Closed') {
        echo "=> PASS: Status updated to 'Closed' successfully.\n\n";
    } else {
        throw new Exception("FAIL: Status is not 'Closed' (got: $status)");
    }

    echo "[Test 3] Testing valid status update to 'Open'...\n";
    $db->prepare("UPDATE cases SET status = 'Open' WHERE id = ?")->execute([$caseId]);
    $status = $db->query("SELECT status FROM cases WHERE id = $caseId")->fetchColumn();
    if ($status === 'Open') {
        echo "=> PASS: Status updated to 'Open' successfully.\n\n";
    } else {
        throw new Exception("FAIL: Status is not 'Open' (got: $status)");
    }

    echo "[Test 4] Testing invalid status constraint block...\n";
    try {
        $db->prepare("UPDATE cases SET status = 'InvalidStatusValue' WHERE id = ?")->execute([$caseId]);
        throw new Exception("FAIL: SQLite allowed setting status to an invalid value");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'CHECK constraint failed') !== false) {
            echo "=> PASS: SQLite correctly blocked invalid status setting with a CHECK constraint error.\n\n";
        } else {
            throw $e;
        }
    }

    // 3. Test evidence linking
    echo "[Test 5] Testing evidence file attachment and linking...\n";
    $stmtEv = $db->prepare("
        INSERT INTO fir_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtEv->execute([
        $caseId,
        'test_photo.jpg',
        'uploads/test_photo.jpg',
        'image/jpeg',
        500000,
        $officerId
    ]);
    $evId = $db->lastInsertId();
    
    $evCount = (int)$db->query("SELECT COUNT(*) FROM fir_evidence WHERE case_id = $caseId")->fetchColumn();
    if ($evCount === 1) {
        echo "=> PASS: Evidence attached and linked to Case ID: $caseId successfully.\n\n";
    } else {
        throw new Exception("FAIL: Evidence count is not 1 (got: $evCount)");
    }

    // 4. Cleanup Seeded Data
    echo "[Cleanup] Deleting test records to keep database clean...\n";
    $db->prepare("DELETE FROM fir_evidence WHERE case_id = ?")->execute([$caseId]);
    $db->prepare("DELETE FROM cases WHERE id = ?")->execute([$caseId]);
    $db->prepare("DELETE FROM fir_records WHERE id = ?")->execute([$firId]);
    $db->prepare("DELETE FROM citizens WHERE id = ?")->execute([$citizenId]);
    $db->prepare("DELETE FROM officers WHERE id = ?")->execute([$officerId]);
    echo "=> Cleanup completed successfully.\n\n";
    
    echo "=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "!!! TEST RUN ENCOUNTERED ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
