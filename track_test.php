<?php
/**
 * track_test.php — CaseFlowX (SCRUM-24)
 * Unit & integration tests for citizen complaint status tracking backend & authorization logic.
 *
 * Run from CLI:  php track_test.php
 * Run from web:  http://localhost/track_test.php
 *
 * Uses an isolated in-memory SQLite DB.
 */

// ── Minimal test harness ─────────────────────────────────────────────────────
$RESULTS = [];
$PASS    = 0;
$FAIL    = 0;

function test(string $name, bool $condition, string $detail = ''): void {
    global $RESULTS, $PASS, $FAIL;
    if ($condition) {
        $PASS++;
        $RESULTS[] = ['pass', $name, $detail];
    } else {
        $FAIL++;
        $RESULTS[] = ['fail', $name, $detail ?: 'Assertion failed'];
    }
}

function section(string $title): void {
    global $RESULTS;
    $RESULTS[] = ['section', $title, ''];
}

// ── Bootstrap: in-memory test DB ─────────────────────────────────────────────
$test_pdo = new PDO('sqlite::memory:');
$test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$test_pdo->exec('PRAGMA foreign_keys = ON');

$test_pdo->exec("
    CREATE TABLE IF NOT EXISTS citizens (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name       TEXT    NOT NULL,
        national_id     TEXT    NOT NULL UNIQUE,
        date_of_birth   TEXT    NOT NULL,
        gender          TEXT    NOT NULL CHECK(gender IN ('male','female','other')),
        phone           TEXT    NOT NULL UNIQUE,
        email           TEXT    UNIQUE,
        division        TEXT    NOT NULL,
        district        TEXT    NOT NULL,
        address         TEXT    NOT NULL,
        password_hash   TEXT    NOT NULL,
        status          TEXT    NOT NULL DEFAULT 'active',
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        last_login      TEXT
    )
");

$test_pdo->exec("
    CREATE TABLE IF NOT EXISTS cases (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        citizen_id      INTEGER NOT NULL,
        case_number     TEXT    NOT NULL UNIQUE,
        title           TEXT    NOT NULL,
        description     TEXT    NOT NULL,
        status          TEXT    NOT NULL DEFAULT 'open' CHECK(status IN ('open','in_progress','resolved','closed')),
        priority        TEXT    NOT NULL DEFAULT 'low' CHECK(priority IN ('low','medium','high')),
        created_at      TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (citizen_id) REFERENCES citizens(id) ON DELETE CASCADE
    )
");

// ── Setup default citizens & cases ──────────────────────────────────────────
$test_pdo->exec("
    INSERT INTO citizens (id, full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password_hash)
    VALUES 
    (1, 'John Doe', '1234567890', '1990-01-01', 'male', '01712345678', 'john@example.com', 'Dhaka', 'Dhaka', 'Test Address 1', 'hash'),
    (2, 'Jane Smith', '0987654321', '1992-02-02', 'female', '01812345678', 'jane@example.com', 'Chittagong', 'Chittagong', 'Test Address 2', 'hash')
");

$test_pdo->exec("
    INSERT INTO cases (id, citizen_id, case_number, title, description, status, priority, created_at)
    VALUES 
    (10, 1, 'CF001-2026', 'Stolen bicycle', 'A black mountain bike was stolen from my driveway.', 'in_progress', 'high', '2026-06-26 12:00:00'),
    (11, 2, 'CF002-2026', 'Water blockage', 'Drainage system clogged for 3 days.', 'resolved', 'medium', '2026-06-26 14:00:00')
");

// ── Validation logic (mirrors track_status_action.php) ───────────────────────
function validate_track_input(string $caseNumber): ?string {
    $caseNumber = trim($caseNumber);
    if ($caseNumber === '') {
        return 'Reference number is required.';
    }
    if (!preg_match('/^CF\d{3}-\d{4}$/i', $caseNumber)) {
        return 'Invalid reference number format. Must be like CF001-2026.';
    }
    return null;
}

// ── Status retrieval simulation (mirrors track_status_action.php) ────────────
function simulate_track_status(PDO $db, string $caseNumber, ?int $sessionCitizenId): array {
    $valError = validate_track_input($caseNumber);
    if ($valError !== null) {
        return ['success' => false, 'message' => $valError];
    }

    try {
        $stmt = $db->prepare('SELECT * FROM cases WHERE UPPER(case_number) = UPPER(:case_number) LIMIT 1');
        $stmt->execute([':case_number' => trim($caseNumber)]);
        $case = $stmt->fetch();

        if (!$case) {
            return ['success' => false, 'message' => 'Reference number not found.'];
        }

        $isOwner = ($sessionCitizenId !== null && (int)$case['citizen_id'] === $sessionCitizenId);

        if ($isOwner) {
            return [
                'success' => true,
                'case' => [
                    'id' => (int)$case['id'],
                    'case_number' => $case['case_number'],
                    'title' => $case['title'],
                    'description' => $case['description'],
                    'status' => $case['status'],
                    'priority' => $case['priority'],
                    'created_at' => $case['created_at'],
                    'is_owner' => true
                ]
            ];
        } else {
            return [
                'success' => true,
                'case' => [
                    'case_number' => $case['case_number'],
                    'title' => $case['title'],
                    'status' => $case['status'],
                    'priority' => $case['priority'],
                    'created_at' => $case['created_at'],
                    'is_owner' => false
                ]
            ];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  TEST SUITE
// ════════════════════════════════════════════════════════════════════════════

section('1. Validation — Reference Number Format');

test('Empty case number returns validation error', validate_track_input('') !== null);
test('Short case number (CF1-202) is flagged', validate_track_input('CF1-202') !== null);
test('Invalid prefix (XX001-2026) is flagged', validate_track_input('XX001-2026') !== null);
test('Invalid suffix year length (CF001-26) is flagged', validate_track_input('CF001-26') !== null);
test('Valid case number passes (CF001-2026)', validate_track_input('CF001-2026') === null);
test('Case-insensitive case number passes (cf001-2026)', validate_track_input('cf001-2026') === null);

section('2. Retrieval — Non-existent Case Numbers');

$resNotFound = simulate_track_status($test_pdo, 'CF999-2026', null);
test('Querying non-existent case fails', $resNotFound['success'] === false);
test('Correct error message returned', $resNotFound['message'] === 'Reference number not found.');

section('3. Authorization — Authenticated Owner');

// Citizen 1 tracks their own case CF001-2026
$resOwner = simulate_track_status($test_pdo, 'CF001-2026', 1);
test('Owner retrieval succeeds', $resOwner['success'] === true);
test('is_owner flag is true', $resOwner['case']['is_owner'] === true);
test('ID is present for owner', isset($resOwner['case']['id']));
test('Title is correct', $resOwner['case']['title'] === 'Stolen bicycle');
test('Description is returned for owner', isset($resOwner['case']['description']));
test('Description matches database exactly', $resOwner['case']['description'] === 'A black mountain bike was stolen from my driveway.');
test('Status is correct', $resOwner['case']['status'] === 'in_progress');
test('Priority is correct', $resOwner['case']['priority'] === 'high');

section('4. Authorization — Unauthenticated Guest');

// Guest (null citizen ID) tracks CF001-2026
$resGuest = simulate_track_status($test_pdo, 'CF001-2026', null);
test('Guest retrieval succeeds', $resGuest['success'] === true);
test('is_owner flag is false', $resGuest['case']['is_owner'] === false);
test('ID is omitted for guest', !isset($resGuest['case']['id']));
test('Description is omitted for guest', !isset($resGuest['case']['description']));
test('Title is visible to guest', isset($resGuest['case']['title']));
test('Status is visible to guest', $resGuest['case']['status'] === 'in_progress');
test('Priority is visible to guest', $resGuest['case']['priority'] === 'high');

section('5. Authorization — Non-owner Citizen');

// Citizen 2 tracks Citizen 1's case CF001-2026
$resNonOwner = simulate_track_status($test_pdo, 'CF001-2026', 2);
test('Non-owner citizen retrieval succeeds', $resNonOwner['success'] === true);
test('is_owner flag is false for non-owner', $resNonOwner['case']['is_owner'] === false);
test('ID is omitted for non-owner', !isset($resNonOwner['case']['id']));
test('Description is omitted for non-owner', !isset($resNonOwner['case']['description']));
test('Title is visible to non-owner', isset($resNonOwner['case']['title']));
test('Status is visible to non-owner', $resNonOwner['case']['status'] === 'in_progress');

// ════════════════════════════════════════════════════════════════════════════
//  OUTPUT
// ════════════════════════════════════════════════════════════════════════════
$is_cli = php_sapi_name() === 'cli';

if ($is_cli) {
    echo "\n  CaseFlowX — Complaint Status Tracking Test Suite\n";
    echo "  SCRUM-24 | " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($RESULTS as [$type, $name, $detail]) {
        if ($type === 'section') {
            echo "\n  ── {$name}\n";
        } elseif ($type === 'pass') {
            echo "  \033[32m✓\033[0m  {$name}\n";
        } else {
            echo "  \033[31m✗\033[0m  {$name}  →  {$detail}\n";
        }
    }
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "  Total: " . ($PASS + $FAIL) . "  |  ";
    echo "\033[32mPassed: {$PASS}\033[0m  |  ";
    echo ($FAIL > 0 ? "\033[31m" : "\033[32m") . "Failed: {$FAIL}\033[0m\n\n";
    exit($FAIL > 0 ? 1 : 0);
}

// HTML output
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Complaint Status Tracking Tests — CaseFlowX SCRUM-24</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F4F6F9] font-sans text-sm p-8">
<div class="max-w-2xl mx-auto">
  <div class="bg-[#1B2A4A] text-white rounded-2xl px-7 py-5 mb-6 flex items-center gap-4 shadow-sm">
    <div class="w-12 h-12 rounded-xl bg-[#1D9E75] flex items-center justify-center text-white text-2xl shadow">
      <i class="ti ti-test-pipe"></i>
    </div>
    <div>
      <h1 class="text-lg font-bold">CaseFlowX — Complaint Tracking Test Suite</h1>
      <p class="text-white/50 text-xs mt-0.5">SCRUM-24 · <?= date('Y-m-d H:i:s') ?></p>
    </div>
    <div class="ml-auto text-right">
      <div class="text-2xl font-bold <?= $FAIL > 0 ? 'text-red-400' : 'text-[#1D9E75]' ?>">
        <?= $PASS ?>/<?= $PASS + $FAIL ?>
      </div>
      <div class="text-xs text-white/50">tests passed</div>
    </div>
  </div>

  <?php foreach ($RESULTS as [$type, $name, $detail]): ?>
    <?php if ($type === 'section'): ?>
      <h2 class="text-gray-500 text-xs font-bold uppercase tracking-wider mt-6 mb-2 px-1"><?= htmlspecialchars($name) ?></h2>
    <?php elseif ($type === 'pass'): ?>
      <div class="flex items-center gap-3 bg-white rounded-xl px-4 py-2.5 mb-1.5 shadow-sm border border-green-100">
        <i class="ti ti-circle-check text-green-500 text-lg flex-shrink-0"></i>
        <span class="text-gray-700"><?= htmlspecialchars($name) ?></span>
      </div>
    <?php else: ?>
      <div class="flex items-start gap-3 bg-white rounded-xl px-4 py-2.5 mb-1.5 shadow-sm border border-red-200">
        <i class="ti ti-circle-x text-red-500 text-lg flex-shrink-0 mt-0.5"></i>
        <div>
          <div class="text-gray-800 font-medium"><?= htmlspecialchars($name) ?></div>
          <div class="text-red-500 text-xs mt-0.5"><?= htmlspecialchars($detail) ?></div>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="mt-6 rounded-2xl px-6 py-4 <?= $FAIL > 0 ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' ?> shadow-sm">
    <p class="font-bold text-base <?= $FAIL > 0 ? 'text-red-700' : 'text-green-700' ?>">
      <?= $FAIL > 0 ? "⚠ {$FAIL} test(s) failed." : "✅ All {$PASS} tests passed." ?>
    </p>
  </div>
</div>
</body>
</html>
