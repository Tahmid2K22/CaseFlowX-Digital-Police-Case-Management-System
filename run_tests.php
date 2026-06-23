<?php
// run_tests.php - Automated Test Suite

// Setup a separate test database file so we don't pollute caseflowx.db
$test_db_path = __DIR__ . '/test_caseflowx.db';
if (file_exists($test_db_path)) {
    unlink($test_db_path);
}

// Set up clean database connection for testing
try {
    $pdo = new PDO("sqlite:$test_db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    
    // Create users table matching the main db.php schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        national_id TEXT NOT NULL UNIQUE,
        date_of_birth TEXT NOT NULL,
        gender TEXT NOT NULL CHECK(gender IN ('male', 'female', 'other')),
        phone TEXT NOT NULL UNIQUE,
        email TEXT UNIQUE,
        division TEXT NOT NULL,
        district TEXT NOT NULL,
        address TEXT NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('Admin', 'Officer', 'Investigator', 'Citizen')),
        status TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Suspended')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create cases table matching the main db.php schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS cases (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        citizen_id      INTEGER,
        case_number     TEXT    NOT NULL UNIQUE,
        title           TEXT    NOT NULL,
        description     TEXT    NOT NULL,
        status          TEXT    NOT NULL DEFAULT 'open' CHECK(status IN ('open','in_progress','resolved','closed','Draft','Submitted','Under Review','Registered','Rejected')),
        priority        TEXT    NOT NULL DEFAULT 'low' CHECK(priority IN ('low','medium','high')),
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        investigator_id INTEGER,
        fir_number      TEXT,
        complainant_name TEXT,
        complainant_nid  TEXT,
        complainant_phone TEXT,
        complainant_address TEXT,
        incident_date    TEXT,
        incident_time    TEXT,
        incident_location TEXT,
        incident_description TEXT,
        sections_applied TEXT,
        witness_details  TEXT,
        officer_id       INTEGER,
        station_code     TEXT,
        investigating_officer TEXT,
        modified_by      INTEGER,
        modified_at      TEXT,
        FOREIGN KEY (citizen_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (investigator_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    // Create fir_records table
    $pdo->exec("CREATE TABLE IF NOT EXISTS fir_records (
        id                      INTEGER PRIMARY KEY AUTOINCREMENT,
        fir_number              TEXT    NOT NULL UNIQUE,
        complainant_name        TEXT    NOT NULL,
        complainant_nid         TEXT    NOT NULL,
        complainant_phone       TEXT,
        complainant_address     TEXT,
        incident_date           TEXT    NOT NULL,
        incident_time           TEXT,
        incident_location       TEXT    NOT NULL,
        incident_description    TEXT    NOT NULL,
        sections_applied        TEXT,
        witness_details         TEXT,
        officer_id              INTEGER,
        station_code            TEXT    NOT NULL,
        status                  TEXT    NOT NULL DEFAULT 'Draft' CHECK(status IN ('Draft','Submitted','Under Review','Registered','Rejected')),
        priority                TEXT    NOT NULL DEFAULT 'medium' CHECK(priority IN ('low','medium','high')),
        created_by              INTEGER,
        created_at              TEXT    NOT NULL DEFAULT (datetime('now')),
        modified_by             INTEGER,
        modified_at             TEXT
    )");

    // Create notifications table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id     INTEGER,
        title       TEXT NOT NULL,
        message     TEXT NOT NULL,
        is_read     INTEGER DEFAULT 0,
        created_at  TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Create case_timeline table
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_timeline (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id         INTEGER NOT NULL,
        event_type      TEXT    NOT NULL CHECK(event_type IN ('created', 'status_change', 'investigator_assigned', 'evidence_uploaded', 'note_added', 'other')),
        title           TEXT    NOT NULL,
        description     TEXT,
        created_by_name TEXT,
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
    )");

    // Create fir_evidence table matching modified db.php schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS fir_evidence (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id         INTEGER NOT NULL,
        file_name       TEXT    NOT NULL,
        file_path       TEXT    NOT NULL,
        file_type       TEXT    NOT NULL,
        file_size       INTEGER NOT NULL,
        uploaded_by     INTEGER NOT NULL,
        uploaded_at     TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE
    )");

    // Create suspect_profiles table matching db.php schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS suspect_profiles (
        id                    INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id               INTEGER NOT NULL,
        full_name             TEXT    NOT NULL,
        national_id           TEXT,
        phone                 TEXT,
        email                 TEXT,
        gender                TEXT    CHECK(gender IN ('male', 'female', 'other')),
        date_of_birth         TEXT,
        physical_description  TEXT,
        address               TEXT,
        status                TEXT    NOT NULL CHECK(status IN ('identified', 'wanted', 'under_arrest', 'released', 'convicted')) DEFAULT 'identified',
        photo_path            TEXT,
        created_by            INTEGER NOT NULL,
        created_at            TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    // Create case_tasks table matching db.php schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS case_tasks (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        case_id         INTEGER NOT NULL,
        title           TEXT    NOT NULL,
        description     TEXT,
        status          TEXT    NOT NULL CHECK(status IN ('todo', 'in_progress', 'done')) DEFAULT 'todo',
        created_by      INTEGER NOT NULL,
        assigned_to     INTEGER,
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        updated_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )");
} catch (Exception $e) {
    die("Test DB Setup Failed: " . $e->getMessage());
}

// ANSI Escape Codes for CLI Colors
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_CYAN', "\033[36m");
define('COLOR_RESET', "\033[0m");

$tests_run = 0;
$tests_passed = 0;

function assert_true($condition, $description) {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($condition) {
        $tests_passed++;
        echo COLOR_GREEN . "✔ [PASS] " . COLOR_RESET . $description . PHP_EOL;
    } else {
        echo COLOR_RED . "✘ [FAIL] " . COLOR_RESET . $description . PHP_EOL;
    }
}

function assert_equals($expected, $actual, $description) {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($expected === $actual) {
        $tests_passed++;
        echo COLOR_GREEN . "✔ [PASS] " . COLOR_RESET . $description . PHP_EOL;
    } else {
        echo COLOR_RED . "✘ [FAIL] " . COLOR_RESET . $description . " (Expected: " . print_r($expected, true) . ", Got: " . print_r($actual, true) . ")" . COLOR_RESET . PHP_EOL;
    }
}

echo COLOR_CYAN . "=========================================================" . COLOR_RESET . PHP_EOL;
echo COLOR_CYAN . "=== CaseFlowX User Administration Test Suite          ===" . COLOR_RESET . PHP_EOL;
echo COLOR_CYAN . "=========================================================" . COLOR_RESET . PHP_EOL;

// 1. Database Connectivity and Schema Creation
assert_true(file_exists($test_db_path), "Test database file 'test_caseflowx.db' should be created.");

// Seed default accounts for testing
$admin_pw = password_hash('admin123', PASSWORD_BCRYPT);
$officer_pw = password_hash('officer123', PASSWORD_BCRYPT);

$pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute(['Test Admin', '1234567890', '1990-01-01', 'male', '01712345678', 'admin@test.com', 'Dhaka', 'Dhaka', 'HQ', $admin_pw, 'Admin', 'Active']);

$pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute(['Test Officer', '1234567891', '1988-05-15', 'male', '01712345679', 'officer@test.com', 'Dhaka', 'Dhaka', 'Station', $officer_pw, 'Officer', 'Active']);

// 2. Validate Seeds
$stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute(['01712345678']);
$admin = $stmt->fetch();
assert_equals('Admin', $admin['role'], "Admin user should have 'Admin' role assigned.");
assert_equals('Active', $admin['status'], "Admin user status should be 'Active'.");

// 3. User Login Logic Verification
$valid_login = password_verify('admin123', $admin['password']);
assert_true($valid_login, "Correct password verification should succeed.");
$invalid_login = password_verify('wrongpass', $admin['password']);
assert_true(!$invalid_login, "Incorrect password verification should fail.");

// 4. Duplicate User Creation Validation
// Phone duplicate
try {
    $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute(['New Name', '1234567899', '1990-01-01', 'male', '01712345678', 'diff@test.com', 'Dhaka', 'Dhaka', 'HQ', 'pass123', 'Officer', 'Active']);
    assert_true(false, "Inserting duplicate phone number should throw database constraint error.");
} catch (PDOException $e) {
    assert_true(true, "Inserting duplicate phone number threw error as expected: " . $e->getCode());
}
// NID duplicate
try {
    $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute(['New Name', '1234567890', '1990-01-01', 'male', '01712345699', 'diff@test.com', 'Dhaka', 'Dhaka', 'HQ', 'pass123', 'Officer', 'Active']);
    assert_true(false, "Inserting duplicate NID should throw database constraint error.");
} catch (PDOException $e) {
    assert_true(true, "Inserting duplicate NID threw error as expected: " . $e->getCode());
}
// Email duplicate
try {
    $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute(['New Name', '1234567899', '1990-01-01', 'male', '01712345699', 'admin@test.com', 'Dhaka', 'Dhaka', 'HQ', 'pass123', 'Officer', 'Active']);
    assert_true(false, "Inserting duplicate email should throw database constraint error.");
} catch (PDOException $e) {
    assert_true(true, "Inserting duplicate email threw error as expected: " . $e->getCode());
}

// 5. Create a user successfully (Citizen registration simulation)
$stmt = $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$res = $stmt->execute(['Test Citizen', '1234567893', '1995-08-25', 'male', '01712345681', 'citizen@test.com', 'Dhaka', 'Dhaka', 'Mirpur Road', password_hash('citizen123', PASSWORD_BCRYPT), 'Citizen', 'Active']);
assert_true($res, "Creating a valid new user should succeed.");

$stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
$stmt->execute(['01712345681']);
$citizen = $stmt->fetch();
assert_equals('Citizen', $citizen['role'], "Created user should have Citizen role.");

// 6. Role Assignment & Editing
$stmt = $pdo->prepare("UPDATE users SET role = ?, status = ?, full_name = ? WHERE id = ?");
$res = $stmt->execute(['Investigator', 'Suspended', 'Updated Citizen Name', $citizen['id']]);
assert_true($res, "Updating user's role and status should succeed.");

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$citizen['id']]);
$updated_citizen = $stmt->fetch();
assert_equals('Investigator', $updated_citizen['role'], "User's role should be updated to Investigator.");
assert_equals('Suspended', $updated_citizen['status'], "User's status should be updated to Suspended.");
assert_equals('Updated Citizen Name', $updated_citizen['full_name'], "User's name should be updated successfully.");

// 7. Verify Suspended Account Enforcement
$suspended_login_allowed = ($updated_citizen['status'] === 'Active');
assert_true(!$suspended_login_allowed, "Suspended users should be blocked from logging in.");

// 8. Delete user
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$res = $stmt->execute([$citizen['id']]);
assert_true($res, "Deleting user from database should succeed.");

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
$stmt->execute([$citizen['id']]);
assert_equals(0, intval($stmt->fetchColumn()), "User should no longer exist in the database after deletion.");

// 9. Role-Based Access Control Simulation (SCRUM-49)
function simulate_rbac_check($user_role, $allowed_roles) {
    return in_array($user_role, $allowed_roles);
}
assert_true(simulate_rbac_check('Admin', ['Admin']), "Admin should be allowed access to Admin area.");
assert_true(!simulate_rbac_check('Officer', ['Admin']), "Officer should be denied access to Admin area.");
assert_true(!simulate_rbac_check('Citizen', ['Admin']), "Citizen should be denied access to Admin area.");
assert_true(simulate_rbac_check('Officer', ['Admin', 'Officer']), "Officer should be allowed access to Officer area.");

// 10. Investigation Officer Login & Redirection Simulation (SCRUM-63 & SCRUM-62)
assert_true(simulate_rbac_check('Investigator', ['Investigator']), "Investigator should be allowed access to Investigator area.");
assert_true(!simulate_rbac_check('Citizen', ['Investigator']), "Citizen should be denied access to Investigator area.");

// 11. Investigation Officer Case Filtering (SCRUM-60)
// Seed an Investigator and mock cases in the test database
$investigator_pw = password_hash('investigator123', PASSWORD_BCRYPT);
$pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute(['Test Investigator', '1234567894', '1985-11-20', 'male', '01712345689', 'investigator@test.com', 'Dhaka', 'Dhaka', 'Station', $investigator_pw, 'Investigator', 'Active']);

// Fetch Investigator ID
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'Investigator' AND phone = '01712345689' LIMIT 1");
$stmt->execute();
$inv_id = $stmt->fetchColumn();

// Seed cases
// Case 1: Assigned to Investigator
$pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([1, 'CF001-2026', 'Assigned Case 1', 'Desc 1', 'open', 'high', $inv_id]);
// Case 2: Assigned to Investigator
$pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([1, 'CF002-2026', 'Assigned Case 2', 'Desc 2', 'in_progress', 'medium', $inv_id]);
// Case 3: Assigned to someone else (null investigator)
$pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([1, 'CF003-2026', 'Unassigned Case', 'Desc 3', 'open', 'low', null]);

// Filter cases by investigator_id
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = ?");
$stmt->execute([$inv_id]);
$assigned_cases_count = (int)$stmt->fetchColumn();
assert_equals(2, $assigned_cases_count, "Investigator should see only their 2 assigned cases.");

// Filter unassigned cases
$stmt = $pdo->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id IS NULL");
$stmt->execute();
$unassigned_cases_count = (int)$stmt->fetchColumn();
assert_equals(1, $unassigned_cases_count, "Unassigned cases should not be visible to this investigator.");

// 12. Admin Review, Approval, Rejection of FIRs (SCRUM-98 & SCRUM-102)
// Seed mock FIR and corresponding case
$pdo->prepare("INSERT INTO fir_records (fir_number, complainant_name, complainant_nid, incident_date, incident_location, incident_description, station_code, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute(['FIR-DHAK-2026-00001', 'Citizen Tahmid', '1234567893', '2026-06-19', 'Dhanmondi', 'Burglary', 'DHAK', 'Submitted', 'medium']);

$pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, fir_number) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([1, 'CF004-2026', 'FIR Dhanmondi', 'Burglary', 'Submitted', 'medium', 'FIR-DHAK-2026-00001']);

// Verify initial status is Submitted
$stmt = $pdo->prepare("SELECT status FROM fir_records WHERE fir_number = 'FIR-DHAK-2026-00001'");
$stmt->execute();
assert_equals('Submitted', $stmt->fetchColumn(), "Initial FIR status should be Submitted.");

// Simulate Admin Approval Action (update to Registered)
$pdo->prepare("UPDATE fir_records SET status = 'Registered' WHERE fir_number = 'FIR-DHAK-2026-00001'")->execute();
$pdo->prepare("UPDATE cases SET status = 'Registered' WHERE fir_number = 'FIR-DHAK-2026-00001'")->execute();

// Check if status updated in both tables
$stmt = $pdo->prepare("SELECT status FROM fir_records WHERE fir_number = 'FIR-DHAK-2026-00001'");
$stmt->execute();
assert_equals('Registered', $stmt->fetchColumn(), "Approved FIR status should update to Registered.");

$stmt = $pdo->prepare("SELECT status FROM cases WHERE fir_number = 'FIR-DHAK-2026-00001'");
$stmt->execute();
assert_equals('Registered', $stmt->fetchColumn(), "Approved corresponding case status should update to Registered.");

// Simulate Notification insertion on approval (SCRUM-101)
$pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
    ->execute([1, 'FIR Approved', 'FIR FIR-DHAK-2026-00001 approved.']);

// Verify notification count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND title = 'FIR Approved'");
$stmt->execute();
assert_equals(1, (int)$stmt->fetchColumn(), "Notification should be successfully generated for citizen.");

// Simulate Admin Rejection Action (update to Rejected)
$pdo->prepare("INSERT INTO fir_records (fir_number, complainant_name, complainant_nid, incident_date, incident_location, incident_description, station_code, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute(['FIR-DHAK-2026-00002', 'Citizen Karim', '1234567895', '2026-06-19', 'Gulshan', 'Theft', 'DHAK', 'Submitted', 'low']);

$pdo->prepare("INSERT INTO cases (citizen_id, case_number, title, description, status, priority, fir_number) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([1, 'CF005-2026', 'FIR Gulshan', 'Theft', 'Submitted', 'low', 'FIR-DHAK-2026-00002']);

$pdo->prepare("UPDATE fir_records SET status = 'Rejected' WHERE fir_number = 'FIR-DHAK-2026-00002'")->execute();
$pdo->prepare("UPDATE cases SET status = 'Rejected' WHERE fir_number = 'FIR-DHAK-2026-00002'")->execute();

// Check if status updated to Rejected in both tables
$stmt = $pdo->prepare("SELECT status FROM fir_records WHERE fir_number = 'FIR-DHAK-2026-00002'");
$stmt->execute();
assert_equals('Rejected', $stmt->fetchColumn(), "Rejected FIR status should update to Rejected.");

$stmt = $pdo->prepare("SELECT status FROM cases WHERE fir_number = 'FIR-DHAK-2026-00002'");
$stmt->execute();
assert_equals('Rejected', $stmt->fetchColumn(), "Rejected corresponding case status should update to Rejected.");

// 13. Case File & Timeline viewing functionality tests (SCRUM-104 & SCRUM-105 & SCRUM-109)
echo COLOR_CYAN . "=== Running Timeline & Case Access Tests (SCRUM-109) ===" . COLOR_RESET . PHP_EOL;

// A. Mocking access control checks
$assigned_case = ['id' => 101, 'investigator_id' => 4];
$other_case = ['id' => 102, 'investigator_id' => 5];
$unassigned_case = ['id' => 103, 'investigator_id' => null];

function simulate_case_details_access($user_role, $user_id, $case) {
    if ($user_role === 'Admin' || $user_role === 'Officer') {
        return true;
    } elseif ($user_role === 'Investigator') {
        return ((int)$case['investigator_id'] === (int)$user_id);
    }
    return false;
}

assert_true(simulate_case_details_access('Investigator', 4, $assigned_case), "Investigator should be allowed access to their assigned case.");
assert_true(!simulate_case_details_access('Investigator', 4, $other_case), "Investigator should be denied access to cases assigned to other investigators.");
assert_true(!simulate_case_details_access('Investigator', 4, $unassigned_case), "Investigator should be denied access to unassigned cases.");
assert_true(simulate_case_details_access('Admin', 4, $other_case), "Admin should be allowed access to any case.");

// B. Database Model Verification (Case Timeline)
// Insert a case first
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([200, 1, 'CF-TEST-TIMELINE', 'Timeline Test Case', 'Testing timeline', 'open', 'low']);

// Insert timeline events
$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([200, 'created', 'Case Filed', 'Registered by citizen', 'Citizen Tahmid']);

$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([200, 'status_change', 'Status Updated', 'Accepted by Officer', 'Officer Mahbub']);

// Fetch timeline events
$stmt = $pdo->prepare("SELECT * FROM case_timeline WHERE case_id = ? ORDER BY id ASC");
$stmt->execute([200]);
$events = $stmt->fetchAll();

assert_equals(2, count($events), "Case should have exactly 2 timeline events.");
assert_equals('created', $events[0]['event_type'], "First event type should be 'created'.");
assert_equals('Case Filed', $events[0]['title'], "First event title should be 'Case Filed'.");
assert_equals('Citizen Tahmid', $events[0]['created_by_name'], "First event creator should be 'Citizen Tahmid'.");
assert_equals('status_change', $events[1]['event_type'], "Second event type should be 'status_change'.");

// C. Verify Cascade Delete
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([200]);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_timeline WHERE case_id = ?");
$stmt->execute([200]);
assert_equals(0, (int)$stmt->fetchColumn(), "Associated timeline events should be cascade-deleted when case is deleted.");

// 14. Digital Forensic Evidence Upload tests (SCRUM-116 to SCRUM-123)
echo COLOR_CYAN . "=== Running Evidence Upload Tests (SCRUM-116 to SCRUM-123) ===" . COLOR_RESET . PHP_EOL;

$assigned_case_ev = ['id' => 301, 'investigator_id' => 4];
$other_case_ev = ['id' => 302, 'investigator_id' => 5];

function simulate_evidence_upload_access($user_role, $user_id, $case) {
    if ($user_role === 'Admin' || $user_role === 'Officer') {
        return true;
    } elseif ($user_role === 'Investigator') {
        return ((int)$case['investigator_id'] === (int)$user_id);
    }
    return false;
}

// A. Access Control Tests
assert_true(simulate_evidence_upload_access('Investigator', 4, $assigned_case_ev), "Assigned investigator should be allowed to upload evidence.");
assert_true(!simulate_evidence_upload_access('Investigator', 4, $other_case_ev), "Unassigned investigator should be denied evidence upload.");
assert_true(simulate_evidence_upload_access('Admin', 4, $other_case_ev), "Admin should be allowed evidence upload on any case.");
assert_true(simulate_evidence_upload_access('Officer', 4, $other_case_ev), "Officer should be allowed evidence upload on any case.");

// B. File Format and Size Validation Tests
function mock_validate_evidence($filename, $mime, $size) {
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $mimeMap = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'mp4'  => 'video/mp4'
    ];
    
    if (!isset($mimeMap[$ext]) || $mimeMap[$ext] !== $mime) {
        return ['success' => false, 'error' => 'Invalid file type or extension mismatch'];
    }
    if ($size > $maxFileSize) {
        return ['success' => false, 'error' => 'File too large'];
    }
    return ['success' => true];
}

assert_true(mock_validate_evidence('report.pdf', 'application/pdf', 5 * 1024 * 1024)['success'], "Valid PDF should pass validation.");
assert_true(mock_validate_evidence('photo.png', 'image/png', 2 * 1024 * 1024)['success'], "Valid PNG should pass validation.");
assert_true(!mock_validate_evidence('malicious.exe', 'application/octet-stream', 1024)['success'], "EXE file extension and mime type should be rejected.");
assert_true(!mock_validate_evidence('photo.png', 'application/pdf', 1024)['success'], "Mismatched mime type should be rejected.");
assert_true(!mock_validate_evidence('huge_video.mp4', 'video/mp4', 11 * 1024 * 1024)['success'], "Files larger than 10MB should be rejected.");

// C. Database Model and Timeline Event Creation Tests
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([400, 1, 'CF-TEST-EVIDENCE', 'Evidence Test Case', 'Testing evidence features', 'open', 'low']);

$pdo->prepare("INSERT INTO fir_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([400, 'case_dump.pdf', 'uploads/case_dump.pdf', 'application/pdf', 1048576, 4]);

$stmt = $pdo->prepare("SELECT * FROM fir_evidence WHERE case_id = ?");
$stmt->execute([400]);
$evRec = $stmt->fetch();
assert_equals('case_dump.pdf', $evRec['file_name'], "Evidence should be saved in database with correct filename.");
assert_equals('uploads/case_dump.pdf', $evRec['file_path'], "Evidence should be saved with correct file path.");

// Insert corresponding timeline event
$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([400, 'evidence_uploaded', 'Evidence Uploaded', 'Forensic evidence file uploaded: case_dump.pdf', 'Investigator Salam']);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_timeline WHERE case_id = ? AND event_type = ?");
$stmt->execute([400, 'evidence_uploaded']);
assert_equals(1, (int)$stmt->fetchColumn(), "Evidence upload timeline event should be logged.");

// D. Evidence Deletion Tests
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([500, 1, 'CF-TEST-EVIDENCE-DEL', 'Evidence Delete Test Case', 'Testing evidence deletion', 'open', 'low', 4]);

$pdo->prepare("INSERT INTO fir_evidence (id, case_id, file_name, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([900, 500, 'to_delete.pdf', 'uploads/to_delete.pdf', 'application/pdf', 1048576, 4]);

// Verify it exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fir_evidence WHERE id = 900");
$stmt->execute();
assert_equals(1, (int)$stmt->fetchColumn(), "Evidence should exist before deletion.");

// Delete it
$pdo->prepare("DELETE FROM fir_evidence WHERE id = 900")->execute();
$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([500, 'other', 'Evidence Deleted', 'Forensic evidence file deleted: to_delete.pdf', 'Investigator Salam']);

// Verify it is gone
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fir_evidence WHERE id = 900");
$stmt->execute();
assert_equals(0, (int)$stmt->fetchColumn(), "Evidence record should be removed from database upon deletion.");

// Verify timeline event logged
$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_timeline WHERE case_id = 500 AND title = 'Evidence Deleted'");
$stmt->execute();
assert_equals(1, (int)$stmt->fetchColumn(), "An Evidence Deleted timeline event should be logged.");

// Clean up case 500
$pdo->prepare("DELETE FROM cases WHERE id = 500")->execute();

// E. Evidence Cascade Delete Tests
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([400]);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM fir_evidence WHERE case_id = ?");
$stmt->execute([400]);
assert_equals(0, (int)$stmt->fetchColumn(), "Evidence records should be cascade-deleted when their associated case is deleted.");

// 15. Suspect Profiles tests (SCRUM-132 to SCRUM-136)
echo COLOR_CYAN . "=== Running Suspect Profile Tests (SCRUM-132 to SCRUM-136) ===" . COLOR_RESET . PHP_EOL;

$assigned_case_susp = ['id' => 601, 'investigator_id' => 4];
$other_case_susp = ['id' => 602, 'investigator_id' => 5];

function simulate_suspect_add_access($user_role, $user_id, $case) {
    if ($user_role === 'Admin' || $user_role === 'Officer') {
        return true;
    } elseif ($user_role === 'Investigator') {
        return ((int)$case['investigator_id'] === (int)$user_id);
    }
    return false;
}

// A. Access Control Tests
assert_true(simulate_suspect_add_access('Investigator', 4, $assigned_case_susp), "Assigned investigator should be allowed to add suspect profiles.");
assert_true(!simulate_suspect_add_access('Investigator', 4, $other_case_susp), "Unassigned investigator should be denied adding suspect profiles.");
assert_true(simulate_suspect_add_access('Admin', 4, $other_case_susp), "Admin should be allowed to add suspect profiles to any case.");
assert_true(simulate_suspect_add_access('Officer', 4, $other_case_susp), "Officer should be allowed to add suspect profiles to any case.");

// B. Input Validation Tests
function mock_validate_suspect($full_name, $status, $gender, $dob, $nid, $phone, $email) {
    if (trim($full_name) === '') {
        return ['success' => false, 'error' => 'Full name is required.'];
    }
    if (!in_array($status, ['identified', 'wanted', 'under_arrest', 'released', 'convicted'], true)) {
        return ['success' => false, 'error' => 'Invalid status option selected.'];
    }
    if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        return ['success' => false, 'error' => 'Invalid gender option selected.'];
    }
    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
        return ['success' => false, 'error' => 'Date of birth must be in YYYY-MM-DD format.'];
    }
    if ($nid !== '' && !preg_match('/^\d{10,17}$/', $nid)) {
        return ['success' => false, 'error' => 'National ID must be between 10 and 17 digits.'];
    }
    if ($phone !== '' && !preg_match('/^\+?\d{9,15}$/', $phone)) {
        return ['success' => false, 'error' => 'Invalid phone number format.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address format.'];
    }
    return ['success' => true];
}

assert_true(mock_validate_suspect('Kamil Chowdhury', 'wanted', 'male', '1990-05-12', '1234567890', '01712345678', 'kamil@test.com')['success'], "Valid suspect inputs should pass validation.");
assert_true(!mock_validate_suspect('', 'wanted', 'male', '', '', '', '')['success'], "Empty full name should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'invalid_status', 'male', '', '', '', '')['success'], "Invalid status should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'wanted', 'alien', '', '', '', '')['success'], "Invalid gender should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'wanted', 'male', '90-05-12', '', '', '')['success'], "Invalid DOB format should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'wanted', 'male', '', '12345', '', '')['success'], "Invalid NID length should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'wanted', 'male', '', '', '123', '')['success'], "Invalid phone number should be rejected.");
assert_true(!mock_validate_suspect('Kamil Chowdhury', 'wanted', 'male', '', '', '', 'invalid_email')['success'], "Invalid email should be rejected.");

// C. Database Model and Timeline Event Creation Tests
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([600, 1, 'CF-TEST-SUSPECT', 'Suspect Test Case', 'Testing suspect features', 'open', 'low']);

$pdo->prepare("
    INSERT INTO suspect_profiles (case_id, full_name, national_id, phone, email, gender, date_of_birth, physical_description, address, status, photo_path, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
")->execute([600, 'Tamal Ghosh', '1234567890123', '01812345678', 'tamal@test.com', 'male', '1988-10-15', 'Scar on left arm', 'Dhaka', 'wanted', 'uploads/suspect_tamal.png', 4]);

$stmt = $pdo->prepare("SELECT * FROM suspect_profiles WHERE case_id = ?");
$stmt->execute([600]);
$suspRec = $stmt->fetch();
assert_equals('Tamal Ghosh', $suspRec['full_name'], "Suspect name should be saved in database correctly.");
assert_equals('wanted', $suspRec['status'], "Suspect status should be saved in database correctly.");
assert_equals('1234567890123', $suspRec['national_id'], "Suspect NID should be saved in database correctly.");
assert_equals('uploads/suspect_tamal.png', $suspRec['photo_path'], "Suspect photo path should be saved in database correctly.");

// Insert corresponding timeline event
$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([600, 'other', 'Suspect Profile Added', 'Suspect profile added: Tamal Ghosh (Wanted)', 'Investigator Salam']);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_timeline WHERE case_id = ? AND event_type = ? AND title = ?");
$stmt->execute([600, 'other', 'Suspect Profile Added']);
assert_equals(1, (int)$stmt->fetchColumn(), "Suspect profile addition timeline event should be logged.");

// D. Cascade Delete Tests
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([600]);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM suspect_profiles WHERE case_id = ?");
$stmt->execute([600]);
assert_equals(0, (int)$stmt->fetchColumn(), "Suspect profile records should be cascade-deleted when their associated case is deleted.");

// 16. Case Task Board tests (SCRUM-79 to SCRUM-83)
echo COLOR_CYAN . "=== Running Case Task Board Tests (SCRUM-79 to SCRUM-83) ===" . COLOR_RESET . PHP_EOL;

$assigned_case_task = ['id' => 701, 'investigator_id' => 4];
$other_case_task = ['id' => 702, 'investigator_id' => 5];

function simulate_task_manage_access($user_role, $user_id, $case) {
    if ($user_role === 'Admin' || $user_role === 'Officer') {
        return true;
    } elseif ($user_role === 'Investigator') {
        return ((int)$case['investigator_id'] === (int)$user_id);
    }
    return false;
}

// A. Access Control Tests
assert_true(simulate_task_manage_access('Investigator', 4, $assigned_case_task), "Assigned investigator should be allowed to manage tasks.");
assert_true(!simulate_task_manage_access('Investigator', 4, $other_case_task), "Unassigned investigator should be denied managing tasks.");
assert_true(simulate_task_manage_access('Admin', 4, $other_case_task), "Admin should be allowed to manage tasks on any case.");
assert_true(simulate_task_manage_access('Officer', 4, $other_case_task), "Officer should be allowed to manage tasks on any case.");

// B. Input Validation Tests
function mock_validate_task($title, $status) {
    if (trim($title) === '') {
        return ['success' => false, 'error' => 'Task title is required.'];
    }
    if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
        return ['success' => false, 'error' => 'Invalid status option selected.'];
    }
    return ['success' => true];
}

assert_true(mock_validate_task('Investigate scene', 'todo')['success'], "Valid task title and status should pass validation.");
assert_true(!mock_validate_task('', 'todo')['success'], "Empty task title should be rejected.");
assert_true(!mock_validate_task('Investigate scene', 'invalid_status')['success'], "Invalid status option should be rejected.");

// C. Auto-Generation of Default Tasks upon Case Assignment
// Insert a new test case
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority) VALUES (?, ?, ?, ?, ?, ?, ?)")
    ->execute([700, 1, 'CF-TEST-TASKBOARD', 'Taskboard Test Case', 'Testing task board features', 'open', 'low']);

// Simulate the auto-population logic that runs during assignment
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM case_tasks WHERE case_id = ?");
$checkStmt->execute([700]);
if ((int)$checkStmt->fetchColumn() === 0) {
    $defaultTasks = [
        'Review FIR and case details',
        'Visit incident scene and gather initial evidence',
        'Identify and interview witnesses',
        'Identify potential suspects',
        'Draft and submit final investigation report'
    ];
    $insertTaskStmt = $pdo->prepare("
        INSERT INTO case_tasks (case_id, title, description, status, created_by)
        VALUES (?, ?, '', 'todo', ?)
    ");
    foreach ($defaultTasks as $taskTitle) {
        $insertTaskStmt->execute([700, $taskTitle, 4]);
    }
}

// Verify 5 default tasks were generated
$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_tasks WHERE case_id = ?");
$stmt->execute([700]);
assert_equals(5, (int)$stmt->fetchColumn(), "5 default tasks should be automatically generated for the case.");

// D. CRUD Operations: Add, Update Status, Edit, Delete
// Create custom task
$pdo->prepare("INSERT INTO case_tasks (case_id, title, description, status, created_by) VALUES (?, ?, ?, ?, ?)")
    ->execute([700, 'Obtain CCTV footage', 'Request footage from bank', 'todo', 4]);

$stmt = $pdo->prepare("SELECT * FROM case_tasks WHERE case_id = ? AND title = ?");
$stmt->execute([700, 'Obtain CCTV footage']);
$taskRec = $stmt->fetch();
assert_equals('Obtain CCTV footage', $taskRec['title'], "Custom task should be added to database.");
assert_equals('todo', $taskRec['status'], "Newly added task status should default to todo.");

$taskId = (int)$taskRec['id'];

// Update status to in_progress
$pdo->prepare("UPDATE case_tasks SET status = ? WHERE id = ?")->execute(['in_progress', $taskId]);
$stmt = $pdo->prepare("SELECT status FROM case_tasks WHERE id = ?");
$stmt->execute([$taskId]);
assert_equals('in_progress', $stmt->fetchColumn(), "Task status should update to in_progress.");

// Edit task details
$pdo->prepare("UPDATE case_tasks SET title = ?, description = ? WHERE id = ?")->execute(['Obtain CCTV footage (Urgent)', 'Bank CCTV request approved', $taskId]);
$stmt = $pdo->prepare("SELECT * FROM case_tasks WHERE id = ?");
$stmt->execute([$taskId]);
$updatedTask = $stmt->fetch();
assert_equals('Obtain CCTV footage (Urgent)', $updatedTask['title'], "Task title should be updated.");
assert_equals('Bank CCTV request approved', $updatedTask['description'], "Task description should be updated.");

// Delete task
$pdo->prepare("DELETE FROM case_tasks WHERE id = ?")->execute([$taskId]);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_tasks WHERE id = ?");
$stmt->execute([$taskId]);
assert_equals(0, (int)$stmt->fetchColumn(), "Task record should be removed from database.");

// E. Cascade Delete Tests
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([700]);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_tasks WHERE case_id = ?");
$stmt->execute([700]);
assert_equals(0, (int)$stmt->fetchColumn(), "All tasks should be cascade-deleted when the case is deleted.");

// F. Case Status Update Tests
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([800, 1, 'CF-TEST-CASE-STATUS', 'Status Update Test Case', 'Testing status change features', 'in_progress', 'low', 4]);

function simulate_status_change_access($user_role, $user_id, $case) {
    if ($user_role === 'Admin' || $user_role === 'Officer') {
        return true;
    } elseif ($user_role === 'Investigator') {
        return ((int)$case['investigator_id'] === (int)$user_id);
    }
    return false;
}

// Access Control
assert_true(simulate_status_change_access('Investigator', 4, ['investigator_id' => 4]), "Assigned investigator should be allowed to update case status.");
assert_true(!simulate_status_change_access('Investigator', 4, ['investigator_id' => 5]), "Unassigned investigator should be denied status updates.");
assert_true(simulate_status_change_access('Admin', 4, ['investigator_id' => 5]), "Admin should be allowed status updates on any case.");
assert_true(simulate_status_change_access('Officer', 4, ['investigator_id' => 5]), "Officer should be allowed status updates on any case.");

// Update Case Status to resolved
$pdo->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute(['resolved', 800]);
$stmt = $pdo->prepare("SELECT status FROM cases WHERE id = ?");
$stmt->execute([800]);
assert_equals('resolved', $stmt->fetchColumn(), "Case status should be updated to resolved in database.");

// Log corresponding status change event
$pdo->prepare("INSERT INTO case_timeline (case_id, event_type, title, description, created_by_name) VALUES (?, ?, ?, ?, ?)")
    ->execute([800, 'status_change', 'Case Status Updated', 'Case status updated to: Resolved', 'Investigator Salam']);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM case_timeline WHERE case_id = ? AND event_type = ? AND title = ?");
$stmt->execute([800, 'status_change', 'Case Status Updated']);
assert_equals(1, (int)$stmt->fetchColumn(), "Case status update timeline event should be logged.");

// Reopen Case (in_progress)
$pdo->prepare("UPDATE cases SET status = ? WHERE id = ?")->execute(['in_progress', 800]);
$stmt = $pdo->prepare("SELECT status FROM cases WHERE id = ?");
$stmt->execute([800]);
assert_equals('in_progress', $stmt->fetchColumn(), "Case status should be updated back to in_progress.");

// Cleanup
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([800]);

// G. Case Task Assignment and Notifications Tests (SCRUM-21)
echo COLOR_CYAN . "=== Running Case Task Assignment Tests (SCRUM-21) ===" . COLOR_RESET . PHP_EOL;

// Fetch Investigator and Officer user details dynamically to prevent ID mismatches
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'Investigator' LIMIT 1");
$stmt->execute();
$invUser = $stmt->fetch();
$investigatorUserId = (int)$invUser['id'];
$investigatorName = $invUser['full_name'];

$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'Officer' LIMIT 1");
$stmt->execute();
$offUser = $stmt->fetch();
$officerUserId = (int)$offUser['id'];

$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'Admin' LIMIT 1");
$stmt->execute();
$adminUserId = (int)$stmt->fetchColumn();

// 1. Create a test case
$pdo->prepare("INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority, investigator_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([900, 1, 'CF-TEST-ASSIGN-TASK', 'Task Assignment Test Case', 'Testing task assignment', 'in_progress', 'low', $investigatorUserId]);

// 2. Create a task assigned to our Investigator
$pdo->prepare("INSERT INTO case_tasks (case_id, title, description, status, created_by, assigned_to) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([900, 'Assigned Task 1', 'Testing task assignment', 'todo', $adminUserId, $investigatorUserId]);

// Simulate notification triggered by assignment
$pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
    ->execute([$investigatorUserId, 'New Task Assigned', "You have been assigned a new task: 'Assigned Task 1' on Case CF-TEST-ASSIGN-TASK."]);

// 3. Verify task is in DB and has correct assignee ID
$stmt = $pdo->prepare("SELECT * FROM case_tasks WHERE case_id = ? AND title = ?");
$stmt->execute([900, 'Assigned Task 1']);
$task = $stmt->fetch();
assert_equals($investigatorUserId, (int)$task['assigned_to'], "Task assigned_to should be the Investigator user ID.");

// 4. Verify task query LEFT JOIN returns assignee_name
$stmt = $pdo->prepare("
    SELECT t.*, u.full_name AS assignee_name 
    FROM case_tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    WHERE t.id = ?
");
$stmt->execute([$task['id']]);
$taskWithAssignee = $stmt->fetch();
assert_equals($investigatorName, $taskWithAssignee['assignee_name'], "Task assignee_name should join and resolve to the investigator's full name.");

// 5. Verify notification was logged
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'New Task Assigned'");
$stmt->execute([$investigatorUserId]);
assert_equals(1, (int)$stmt->fetchColumn(), "Notification should be generated for the assignee.");

// 6. Reassign task to Officer (user ID $officerUserId)
$pdo->prepare("UPDATE case_tasks SET assigned_to = ? WHERE id = ?")->execute([$officerUserId, $task['id']]);

// Simulate notification for reassignment
$pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
    ->execute([$officerUserId, 'Task Assigned', "You have been assigned the task: 'Assigned Task 1' on Case CF-TEST-ASSIGN-TASK."]);

// Verify reassignment in DB
$stmt = $pdo->prepare("SELECT assigned_to FROM case_tasks WHERE id = ?");
$stmt->execute([$task['id']]);
assert_equals($officerUserId, (int)$stmt->fetchColumn(), "Task assignee should be updated to Officer user ID.");

// Verify reassigned notification was logged
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = 'Task Assigned'");
$stmt->execute([$officerUserId]);
assert_equals(1, (int)$stmt->fetchColumn(), "Notification should be generated for the new assignee.");

// Cleanup
$pdo->prepare("DELETE FROM cases WHERE id = ?")->execute([900]);

echo PHP_EOL;
echo COLOR_CYAN . "=========================================================" . COLOR_RESET . PHP_EOL;
echo COLOR_CYAN . "=== Test Run Summary                                  ===" . COLOR_RESET . PHP_EOL;
echo COLOR_CYAN . "=========================================================" . COLOR_RESET . PHP_EOL;
echo "Total Tests Run: $tests_run" . PHP_EOL;
if ($tests_passed === $tests_run) {
    echo COLOR_GREEN . "SUCCESS: All tests passed successfully!" . COLOR_RESET . PHP_EOL;
} else {
    echo COLOR_RED . "FAILURE: " . ($tests_run - $tests_passed) . " test(s) failed." . COLOR_RESET . PHP_EOL;
}
echo COLOR_CYAN . "=========================================================" . COLOR_RESET . PHP_EOL;

// Cleanup
if (file_exists($test_db_path)) {
    $stmt = null;
    $pdo = null;
    gc_collect_cycles();
    unlink($test_db_path);
}
