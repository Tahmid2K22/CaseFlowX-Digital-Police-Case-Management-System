<?php
/**
 * tests/test_criminal_search.php — Automated tests for criminal database search.
 */

require_once __DIR__ . '/../db.php';

echo "=== Running Criminal Database Search Tests ===\n";

try {
    $db = get_db();

    // 1. Verify table exists and has seeded data
    echo "[Test 1] Verifying criminals table exists and has seeded data...\n";
    $count = (int)$db->query("SELECT COUNT(*) FROM criminals")->fetchColumn();
    if ($count >= 4) {
        echo "=> PASS: Criminals table seeded successfully (Total: $count records).\n\n";
    } else {
        throw new Exception("FAIL: Criminals table is empty or has fewer than 4 records (got: $count)");
    }

    // 2. Test Search by Name (Exact & Partial)
    echo "[Test 2] Testing search by Name (partial/exact)...\n";
    
    // Partial Match 1
    $search1 = "Akash";
    $stmt1 = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmt1->execute(["%$search1%", "%$search1%"]);
    $res1 = $stmt1->fetchAll();
    if (count($res1) === 1 && $res1[0]['name'] === 'Akash Ahmed') {
        echo "=> PASS: Search for 'Akash' returned exactly 'Akash Ahmed'.\n";
    } else {
        throw new Exception("FAIL: Search for 'Akash' failed. Results: " . json_encode($res1));
    }

    // Case Insensitive Partial Match
    $search2 = "rahman";
    $stmt2 = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmt2->execute(["%$search2%", "%$search2%"]);
    $res2 = $stmt2->fetchAll();
    if (count($res2) === 1 && $res2[0]['name'] === 'Tasnim Rahman') {
        echo "=> PASS: Search for 'rahman' returned exactly 'Tasnim Rahman'.\n";
    } else {
        throw new Exception("FAIL: Search for 'rahman' failed. Results: " . json_encode($res2));
    }

    // Multiple Matches
    $search3 = "a";
    $stmt3 = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmt3->execute(["%$search3%", "%$search3%"]);
    $res3 = $stmt3->fetchAll();
    if (count($res3) >= 3) {
        echo "=> PASS: Search for 'a' returned multiple records (" . count($res3) . ").\n\n";
    } else {
        throw new Exception("FAIL: Search for 'a' returned fewer than 3 records (got: " . count($res3) . ")");
    }

    // 3. Test Search by NID (Exact & Prefix)
    echo "[Test 3] Testing search by NID (exact/prefix)...\n";
    
    // Exact Match
    $nid1 = "1234567890";
    $stmtNid1 = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmtNid1->execute(["%$nid1%", "%$nid1%"]);
    $resNid1 = $stmtNid1->fetchAll();
    if (count($resNid1) === 1 && $resNid1[0]['name'] === 'Akash Ahmed') {
        echo "=> PASS: Search for NID '1234567890' returned 'Akash Ahmed'.\n";
    } else {
        throw new Exception("FAIL: Search for exact NID failed.");
    }

    // Prefix Match
    $nid2 = "11223";
    $stmtNid2 = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmtNid2->execute(["%$nid2%", "%$nid2%"]);
    $resNid2 = $stmtNid2->fetchAll();
    if (count($resNid2) === 1 && $resNid2[0]['name'] === 'Kamal Uddin') {
        echo "=> PASS: Search for NID prefix '11223' returned 'Kamal Uddin'.\n\n";
    } else {
        throw new Exception("FAIL: Search for prefix NID failed.");
    }

    // 4. Test Search with No Results
    echo "[Test 4] Testing search for non-existent criminal...\n";
    $searchNone = "NonExistentCriminalNamexyz";
    $stmtNone = $db->prepare("SELECT * FROM criminals WHERE name LIKE ? OR nid LIKE ?");
    $stmtNone->execute(["%$searchNone%", "%$searchNone%"]);
    $resNone = $stmtNone->fetchAll();
    if (count($resNone) === 0) {
        echo "=> PASS: Search for non-existent records returned empty array.\n\n";
    } else {
        throw new Exception("FAIL: Search for non-existent records returned data: " . json_encode($resNone));
    }

    echo "=== ALL CRIMINAL SEARCH TESTS PASSED SUCCESSFULLY ===\n";

} catch (Exception $e) {
    echo "!!! TEST RUN ENCOUNTERED ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
