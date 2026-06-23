<?php
/**
 * tests/test_physical_evidence.php — Automated tests for physical evidence and chain of custody tracking.
 */

require_once __DIR__ . '/../db.php';

echo "=== Running Physical Evidence & Chain of Custody Tests ===\n";

try {
    $db = get_db();
    
    // 1. Seed Test Data
    echo "[Seeding] Creating test officer, citizen, and case...\n";
    
    // Seed Officer
    $badge = 'PE-TEST-OFF';
    $db->prepare("DELETE FROM officers WHERE badge_number = ?")->execute([$badge]);
    $stmtOff = $db->prepare("
        INSERT INTO officers (badge_number, full_name, email, phone, station_code, role, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtOff->execute([
        $badge,
        'PE Test Officer',
        'petest@caseflowx.local',
        '01777777777',
        'HQ01',
        'FIR Officer',
        password_hash('Password@123', PASSWORD_DEFAULT),
        'active'
    ]);
    $officerId = $db->lastInsertId();
    
    // Seed Citizen
    $nid = '7777777777';
    $db->prepare("DELETE FROM citizens WHERE national_id = ?")->execute([$nid]);
    $stmtCit = $db->prepare("
        INSERT INTO citizens (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password_hash, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtCit->execute([
        'PE Test Citizen',
        $nid,
        '1995-05-05',
        'female',
        '01666666666',
        'pecitizen@caseflowx.local',
        'Dhaka',
        'Dhaka',
        'Test Address',
        password_hash('Password@123', PASSWORD_DEFAULT),
        'active'
    ]);
    $citizenId = $db->lastInsertId();
    
    // Seed Case
    $caseNum = 'CF-PE-TEST';
    $db->prepare("DELETE FROM cases WHERE case_number = ?")->execute([$caseNum]);
    $stmtCase = $db->prepare("
        INSERT INTO cases (citizen_id, case_number, title, description, status, priority, officer_id, station_code)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtCase->execute([
        $citizenId,
        $caseNum,
        'Physical Evidence Test Case',
        'Test case description for physical evidence checking.',
        'Open',
        'high',
        $officerId,
        'HQ01'
    ]);
    $caseId = $db->lastInsertId();

    echo "[Seed Success] Seeded Case ID: $caseId, Officer ID: $officerId\n\n";

    // 2. Test 1: Log Physical Evidence
    echo "[Test 1] Logging new physical evidence...\n";
    $db->beginTransaction();
    
    $itemName = '9mm Pistol';
    $description = 'Black semi-automatic handgun found in trash can';
    $serialNumber = 'SN-123456';
    $recoveredAt = '2026-06-23T12:00';
    $recoveredLocation = 'Trash can behind grocery store';
    $recoveredBy = 'PE Test Officer';
    $currentCustodian = 'HQ Evidence Locker Room A';
    $status = 'Secured';
    $notes = 'Initial notes: Bagged and tagged';

    $stmtInsert = $db->prepare("
        INSERT INTO physical_evidence (case_id, item_name, description, serial_number, recovered_at, recovered_location, recovered_by, current_custodian, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([$caseId, $itemName, $description, $serialNumber, $recoveredAt, $recoveredLocation, $recoveredBy, $currentCustodian, $status]);
    $evidenceId = $db->lastInsertId();

    // Create initial custody record
    $stmtCustody = $db->prepare("
        INSERT INTO evidence_chain_of_custody (evidence_id, officer_name, action_type, custody_date, location, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtCustody->execute([$evidenceId, $recoveredBy, 'Recovery', $recoveredAt, $recoveredLocation, $notes]);
    $db->commit();

    // Verify evidence was created
    $peItem = $db->query("SELECT * FROM physical_evidence WHERE id = $evidenceId")->fetch();
    if ($peItem && $peItem['item_name'] === $itemName && $peItem['status'] === 'Secured') {
        echo "=> PASS: Physical evidence record inserted successfully.\n";
    } else {
        throw new Exception("FAIL: Physical evidence record not inserted correctly.");
    }

    // Verify initial timeline entry
    $cocItem = $db->query("SELECT * FROM evidence_chain_of_custody WHERE evidence_id = $evidenceId")->fetch();
    if ($cocItem && $cocItem['action_type'] === 'Recovery' && $cocItem['location'] === $recoveredLocation) {
        echo "=> PASS: Initial custody record created successfully.\n\n";
    } else {
        throw new Exception("FAIL: Initial custody timeline record not created correctly.");
    }

    // 3. Test 2: Add Custody Transfer
    echo "[Test 2] Updating custody (Transfer to Forensic Lab)...\n";
    $db->beginTransaction();
    
    $transferOfficer = 'Forensic Specialist Jane';
    $transferAction = 'Transfer';
    $transferDate = '2026-06-23T14:30';
    $transferLocation = 'Central Forensic Lab';
    $transferNotes = 'Sent for ballistics testing';
    $newStatus = 'Sent to Lab';

    // Insert timeline record
    $stmtCustodyTransfer = $db->prepare("
        INSERT INTO evidence_chain_of_custody (evidence_id, officer_name, action_type, custody_date, location, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtCustodyTransfer->execute([$evidenceId, $transferOfficer, $transferAction, $transferDate, $transferLocation, $transferNotes]);

    // Update parent status & custodian
    $stmtUpdateEv = $db->prepare("
        UPDATE physical_evidence
        SET current_custodian = ?, status = ?
        WHERE id = ?
    ");
    $stmtUpdateEv->execute([$transferOfficer, $newStatus, $evidenceId]);
    $db->commit();

    // Verify updates on parent item
    $updatedPE = $db->query("SELECT * FROM physical_evidence WHERE id = $evidenceId")->fetch();
    if ($updatedPE && $updatedPE['current_custodian'] === $transferOfficer && $updatedPE['status'] === $newStatus) {
        echo "=> PASS: Physical evidence custodian and status updated successfully.\n";
    } else {
        throw new Exception("FAIL: Physical evidence custodian/status not updated.");
    }

    // Verify history timeline has 2 records now
    $timelineCount = (int)$db->query("SELECT COUNT(*) FROM evidence_chain_of_custody WHERE evidence_id = $evidenceId")->fetchColumn();
    if ($timelineCount === 2) {
        echo "=> PASS: Custody transfer timeline shows 2 steps.\n\n";
    } else {
        throw new Exception("FAIL: Custody timeline count is not 2 (got: $timelineCount)");
    }

    // 4. Cleanup Seeded Data
    echo "[Cleanup] Deleting test records to keep database clean...\n";
    $db->prepare("DELETE FROM evidence_chain_of_custody WHERE evidence_id = ?")->execute([$evidenceId]);
    $db->prepare("DELETE FROM physical_evidence WHERE case_id = ?")->execute([$caseId]);
    $db->prepare("DELETE FROM cases WHERE id = ?")->execute([$caseId]);
    $db->prepare("DELETE FROM citizens WHERE id = ?")->execute([$citizenId]);
    $db->prepare("DELETE FROM officers WHERE id = ?")->execute([$officerId]);
    echo "=> Cleanup completed successfully.\n\n";
    
    echo "=== ALL TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "!!! TEST RUN ENCOUNTERED ERROR: " . $e->getMessage() . "\n";
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    exit(1);
}
