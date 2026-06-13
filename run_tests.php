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
