<?php
/**
 * complaint_test.php — SCRUM-143
 * Unit & integration tests for citizen online complaint filing backend logic.
 *
 * Run from CLI:  php complaint_test.php
 * Run from web:  http://localhost/complaint_test.php
 *
 * Uses an isolated in-memory SQLite DB (no production data touched).
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

// ── Setup default citizen ────────────────────────────────────────────────────
$test_pdo->exec("
    INSERT INTO citizens (id, full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password_hash)
    VALUES (1, 'John Doe', '1234567890', '1990-01-01', 'male', '01712345678', 'john@example.com', 'Dhaka', 'Dhaka', 'Test Address', 'hash')
");

// ── Validation logic (mirrors submit_case.php) ────────────────────────────────
function validate_complaint(array $data): array {
    $title       = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $priority    = trim($data['priority'] ?? '');

    $errors = [];

    if ($title === '') {
        $errors['title'] = 'Case title is required.';
    } elseif (mb_strlen($title) < 3) {
        $errors['title'] = 'Title must be at least 3 characters.';
    } elseif (mb_strlen($title) > 200) {
        $errors['title'] = 'Title must not exceed 200 characters.';
    }

    if ($description === '') {
        $errors['description'] = 'Description is required.';
    } elseif (mb_strlen($description) < 10) {
        $errors['description'] = 'Please provide a more detailed description (at least 10 characters).';
    } elseif (mb_strlen($description) > 5000) {
        $errors['description'] = 'Description must not exceed 5000 characters.';
    }

    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $errors['priority'] = 'Please select a valid priority.';
    }

    return $errors;
}

// ── DB insert simulation (mirrors submit_case.php) ───────────────────────────
function db_submit_case(PDO $db, int $citizenId, array $data, ?string $year = null): array {
    try {
        if ($year === null) {
            $year = date('Y');
        }
        $prefix = "CF%{$year}";

        $stmt = $db->prepare("
            SELECT case_number FROM cases
            WHERE case_number LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix]);
        $last = $stmt->fetch();

        $nextNum = 1;
        if ($last && preg_match('/CF(\d+)-' . $year . '/', $last['case_number'], $m)) {
            $nextNum = (int)$m[1] + 1;
        }
        $caseNumber = sprintf('CF%03d-%s', $nextNum, $year);

        $insert = $db->prepare("
            INSERT INTO cases (citizen_id, case_number, title, description, status, priority)
            VALUES (:citizen_id, :case_number, :title, :description, 'open', :priority)
        ");

        $insert->execute([
            ':citizen_id'  => $citizenId,
            ':case_number' => $caseNumber,
            ':title'       => trim($data['title'] ?? ''),
            ':description' => trim($data['description'] ?? ''),
            ':priority'    => trim($data['priority'] ?? ''),
        ]);

        return [
            'success'     => true,
            'id'          => (int)$db->lastInsertId(),
            'case_number' => $caseNumber,
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error'   => $e->getMessage(),
        ];
    }
}

// ── Sample valid complaint payload ───────────────────────────────────────────
$valid = [
    'title'       => 'Stolen bicycle from front yard',
    'description' => 'A black mountain bike was stolen from my driveway between 2 PM and 4 PM.',
    'priority'    => 'medium',
];

// ════════════════════════════════════════════════════════════════════════════
//  TEST SUITE
// ════════════════════════════════════════════════════════════════════════════

section('1. Validation — Title Rules');

test('Empty title is flagged', isset(validate_complaint(array_merge($valid, ['title' => '']))['title']));
test('Short title (2 chars) is flagged', isset(validate_complaint(array_merge($valid, ['title' => 'Ab']))['title']));
test('Long title (> 200 chars) is flagged', isset(validate_complaint(array_merge($valid, ['title' => str_repeat('A', 201)]))['title']));
test('Valid title (3 chars) passes', !isset(validate_complaint(array_merge($valid, ['title' => 'Abc']))['title']));
test('Valid normal title passes', !isset(validate_complaint($valid)['title']));

section('2. Validation — Description Rules');

test('Empty description is flagged', isset(validate_complaint(array_merge($valid, ['description' => '']))['description']));
test('Short description (9 chars) is flagged', isset(validate_complaint(array_merge($valid, ['description' => 'Short 123']))['description']));
test('Long description (> 5000 chars) is flagged', isset(validate_complaint(array_merge($valid, ['description' => str_repeat('A', 5001)]))['description']));
test('Valid description (10 chars) passes', !isset(validate_complaint(array_merge($valid, ['description' => '1234567890']))['description']));
test('Valid normal description passes', !isset(validate_complaint($valid)['description']));

section('3. Validation — Priority Rules');

test('Priority "low" passes', !isset(validate_complaint(array_merge($valid, ['priority' => 'low']))['priority']));
test('Priority "medium" passes', !isset(validate_complaint(array_merge($valid, ['priority' => 'medium']))['priority']));
test('Priority "high" passes', !isset(validate_complaint(array_merge($valid, ['priority' => 'high']))['priority']));
test('Invalid priority value is flagged', isset(validate_complaint(array_merge($valid, ['priority' => 'critical']))['priority']));

section('4. Database — Case Number Generation');

$year = '2026';
$res1 = db_submit_case($test_pdo, 1, $valid, $year);
test('First case generation succeeds', $res1['success'] === true);
test('First case number starts at CF001', $res1['case_number'] === 'CF001-2026');

$res2 = db_submit_case($test_pdo, 1, $valid, $year);
test('Second case generation succeeds', $res2['success'] === true);
test('Second case number increments to CF002', $res2['case_number'] === 'CF002-2026');

// Test year transition reset
$nextYear = '2027';
$res3 = db_submit_case($test_pdo, 1, $valid, $nextYear);
test('Transition year case generation succeeds', $res3['success'] === true);
test('Transition year case number resets to CF001', $res3['case_number'] === 'CF001-2027');

section('5. Database — Integration & Column Correctness');

$resRow = db_submit_case($test_pdo, 1, [
    'title'       => 'Waterlogging Issue in Sec 2',
    'description' => 'The drainage system has been clogged for 3 days causing flood.',
    'priority'    => 'high'
], '2026');

test('Insertion succeeds', $resRow['success'] === true);
$insertedId = $resRow['id'];

$row = $test_pdo->query('SELECT * FROM cases WHERE id = ' . $insertedId)->fetch();
test('Citizen ID matches correctly', (int)$row['citizen_id'] === 1);
test('Case number stored correctly', $row['case_number'] === 'CF003-2026');
test('Title stored correctly', $row['title'] === 'Waterlogging Issue in Sec 2');
test('Description stored correctly', $row['description'] === 'The drainage system has been clogged for 3 days causing flood.');
test('Status defaults to open', $row['status'] === 'open');
test('Priority stored correctly', $row['priority'] === 'high');
test('created_at timestamp is populated', !empty($row['created_at']));

section('6. Database — Integrity & Foreign Key Constraint');

// Insert a case with a non-existent citizen_id (e.g. 99)
$resInvalidUser = db_submit_case($test_pdo, 99, $valid, '2026');
test('Foreign key constraint restricts invalid citizen_id', $resInvalidUser['success'] === false);

// ════════════════════════════════════════════════════════════════════════════
//  OUTPUT
// ════════════════════════════════════════════════════════════════════════════
$is_cli = php_sapi_name() === 'cli';

if ($is_cli) {
    echo "\n  CaseFlowX — Citizen Online Complaint Filing Test Suite\n";
    echo "  SCRUM-143 | " . date('Y-m-d H:i:s') . "\n";
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
<title>Complaint Filing Tests — CaseFlowX SCRUM-143</title>
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
      <h1 class="text-lg font-bold">CaseFlowX — Complaint Filing Test Suite</h1>
      <p class="text-white/50 text-xs mt-0.5">SCRUM-143 · <?= date('Y-m-d H:i:s') ?></p>
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
