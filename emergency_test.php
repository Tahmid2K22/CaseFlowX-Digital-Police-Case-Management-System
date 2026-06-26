<?php
/**
 * emergency_test.php — CaseFlowX (SCRUM-25)
 * Unit & integration tests for anonymous emergency reporting backend & dispatch logging.
 *
 * Run from CLI:  php emergency_test.php
 * Run from web:  http://localhost/emergency_test.php
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

$test_pdo->exec("
    CREATE TABLE IF NOT EXISTS emergencies (
        id              INTEGER PRIMARY KEY AUTOINCREMENT,
        report_number   TEXT    NOT NULL UNIQUE,
        type            TEXT    NOT NULL CHECK(type IN ('medical', 'fire', 'crime', 'accident', 'other')),
        location        TEXT    NOT NULL,
        description     TEXT    NOT NULL,
        contact_info    TEXT,
        status          TEXT    NOT NULL DEFAULT 'received' CHECK(status IN ('received', 'dispatched', 'resolved', 'spurious')),
        created_at      TEXT    NOT NULL DEFAULT (datetime('now'))
    )
");

// ── Validation logic (mirrors emergency_action.php) ──────────────────────────
function validate_emergency_report(array $data): array {
    $type        = trim($data['type'] ?? '');
    $location    = trim($data['location'] ?? '');
    $description = trim($data['description'] ?? '');
    $contactInfo = trim($data['contact_info'] ?? '');

    $errors = [];
    $allowedTypes = ['medical', 'fire', 'crime', 'accident', 'other'];

    if ($type === '' || !in_array($type, $allowedTypes, true)) {
        $errors['type'] = 'Please select a valid emergency type.';
    }

    if ($location === '') {
        $errors['location'] = 'Location details are required.';
    } elseif (mb_strlen($location) < 5) {
        $errors['location'] = 'Please provide a more specific location (at least 5 characters).';
    }

    if ($description === '') {
        $errors['description'] = 'Description of the emergency is required.';
    } elseif (mb_strlen($description) < 10) {
        $errors['description'] = 'Please provide more details about the situation (at least 10 characters).';
    }

    return $errors;
}

// ── DB Submission simulation (mirrors emergency_action.php) ──────────────────
function db_submit_emergency(PDO $db, array $data, ?string $year = null, ?string $mockLogPath = null): array {
    $errors = validate_emergency_report($data);
    if (!empty($errors)) {
        return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
    }

    try {
        if ($year === null) {
            $year = date('Y');
        }
        $prefix = "EMG-{$year}-%";

        $stmt = $db->prepare("
            SELECT report_number FROM emergencies
            WHERE report_number LIKE :prefix
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([':prefix' => $prefix]);
        $last = $stmt->fetch();

        $nextNum = 1;
        if ($last && preg_match('/EMG-' . $year . '-(\d+)/i', $last['report_number'], $m)) {
            $nextNum = (int)$m[1] + 1;
        }
        $reportNumber = sprintf('EMG-%s-%04d', $year, $nextNum);

        $insert = $db->prepare("
            INSERT INTO emergencies (report_number, type, location, description, contact_info, status)
            VALUES (:report_number, :type, :location, :description, :contact_info, 'received')
        ");

        $insert->execute([
            ':report_number' => $reportNumber,
            ':type'          => trim($data['type']),
            ':location'      => trim($data['location']),
            ':description'   => trim($data['description']),
            ':contact_info'  => !empty($data['contact_info']) ? trim($data['contact_info']) : null
        ]);

        // Simulate notification dispatch logging
        if ($mockLogPath !== null) {
            $logMsg = sprintf(
                "[%s] DISPATCH TRIGGERED: Reference %s | Type: %s | Location: %s\n",
                date('Y-m-d H:i:s'),
                $reportNumber,
                strtoupper($data['type']),
                trim($data['location'])
            );
            file_put_contents($mockLogPath, $logMsg, FILE_APPEND | LOCK_EX);
        }

        return [
            'success'       => true,
            'id'            => (int)$db->lastInsertId(),
            'report_number' => $reportNumber
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ── Tracking status simulation (mirrors emergency_action.php) ────────────────
function db_track_emergency(PDO $db, string $ref): array {
    $ref = trim($ref);
    if (!preg_match('/^EMG-\d{4}-\d{4}$/i', $ref)) {
        return ['success' => false, 'message' => 'Invalid reference number format.'];
    }

    try {
        $stmt = $db->prepare('SELECT * FROM emergencies WHERE UPPER(report_number) = UPPER(:ref) LIMIT 1');
        $stmt->execute([':ref' => $ref]);
        $report = $stmt->fetch();

        if (!$report) {
            return ['success' => false, 'message' => 'Emergency report reference not found.'];
        }

        return ['success' => true, 'report' => $report];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
    }
}

// ── Sample valid emergency payload ───────────────────────────────────────────
$validReport = [
    'type'         => 'fire',
    'location'     => 'House 14, Road 2, Block A, Banani',
    'description'  => 'Kitchen fire started in the restaurant and spreading to adjacent shops.',
    'contact_info' => '01700000000'
];

// ════════════════════════════════════════════════════════════════════════════
//  TEST SUITE
// ════════════════════════════════════════════════════════════════════════════

section('1. Validation — Emergency Form Rules');

test('Empty emergency type is flagged', isset(validate_emergency_report(array_merge($validReport, ['type' => '']))['type']));
test('Invalid emergency type (tornado) is flagged', isset(validate_emergency_report(array_merge($validReport, ['type' => 'tornado']))['type']));
test('Valid emergency type (medical) passes', !isset(validate_emergency_report(array_merge($validReport, ['type' => 'medical']))['type']));

test('Empty location is flagged', isset(validate_emergency_report(array_merge($validReport, ['location' => '']))['location']));
test('Short location (4 chars) is flagged', isset(validate_emergency_report(array_merge($validReport, ['location' => 'H-12']))['location']));
test('Valid location (5 chars) passes', !isset(validate_emergency_report(array_merge($validReport, ['location' => 'H-123']))['location']));

test('Empty description is flagged', isset(validate_emergency_report(array_merge($validReport, ['description' => '']))['description']));
test('Short description (9 chars) is flagged', isset(validate_emergency_report(array_merge($validReport, ['description' => 'Fire here']))['description']));
test('Valid description (10 chars) passes', !isset(validate_emergency_report(array_merge($validReport, ['description' => 'Large fire']))['description']));

section('2. Database — Anonymous Storage & No linkage');

$res1 = db_submit_emergency($test_pdo, $validReport, '2026');
test('Submit valid emergency report succeeds', $res1['success'] === true);
test('Unique report number generated starts at EMG-2026-0001', $res1['report_number'] === 'EMG-2026-0001');

$insertedId = $res1['id'];
$row = $test_pdo->query('SELECT * FROM emergencies WHERE id = ' . $insertedId)->fetch();
test('Location stored correctly', $row['location'] === 'House 14, Road 2, Block A, Banani');
test('Description stored correctly', $row['description'] === 'Kitchen fire started in the restaurant and spreading to adjacent shops.');
test('Contact info stored correctly', $row['contact_info'] === '01700000000');
test('Status defaults to received', $row['status'] === 'received');
test('Created at populated', !empty($row['created_at']));

// Verify absolute anonymity: There is no column for citizen_id in the table!
$schemaCheck = true;
try {
    $test_pdo->query('SELECT citizen_id FROM emergencies');
    $schemaCheck = false; // If this query succeeds, it means it is not anonymous!
} catch (PDOException $e) {
    // Expected to fail as there is no citizen_id column
    $schemaCheck = true;
}
test('No citizen_id column exists (Anonymity verified)', $schemaCheck === true);

section('3. Database — Report Number Generation & Year Reset');

$res2 = db_submit_emergency($test_pdo, $validReport, '2026');
test('Second report in same year increments count to 0002', $res2['report_number'] === 'EMG-2026-0002');

$res3 = db_submit_emergency($test_pdo, $validReport, '2027');
test('Report in new year resets count to 0001', $res3['report_number'] === 'EMG-2027-0001');

section('4. Notification System — Log Dispatch Acknowledgment');

$mockLog = tempnam(sys_get_temp_dir(), 'mock_emg_log_');
$resLog = db_submit_emergency($test_pdo, $validReport, '2026', $mockLog);
test('Submission with logging succeeds', $resLog['success'] === true);

$logContents = file_get_contents($mockLog);
test('Dispatch notification logged successfully', strpos($logContents, 'DISPATCH TRIGGERED: Reference EMG-2026-0003') !== false);
test('Correct emergency type written in log', strpos($logContents, 'Type: FIRE') !== false);
unlink($mockLog);

section('5. Tracking Status — Anonymously');

$resTrackValid = db_track_emergency($test_pdo, 'EMG-2026-0001');
test('Track existing report status succeeds', $resTrackValid['success'] === true);
test('Correct location returned', $resTrackValid['report']['location'] === 'House 14, Road 2, Block A, Banani');
test('Correct status returned', $resTrackValid['report']['status'] === 'received');

$resTrackInvalidFormat = db_track_emergency($test_pdo, 'EMG-01-01');
test('Track invalid reference format fails', $resTrackInvalidFormat['success'] === false);
test('Correct format error message', $resTrackInvalidFormat['message'] === 'Invalid reference number format.');

$resTrackNotFound = db_track_emergency($test_pdo, 'EMG-2026-9999');
test('Track non-existent reference fails', $resTrackNotFound['success'] === false);
test('Correct not found message', $resTrackNotFound['message'] === 'Emergency report reference not found.');

// ════════════════════════════════════════════════════════════════════════════
//  OUTPUT
// ════════════════════════════════════════════════════════════════════════════
$is_cli = php_sapi_name() === 'cli';

if ($is_cli) {
    echo "\n  CaseFlowX — Anonymous Emergency Reporting Test Suite\n";
    echo "  SCRUM-25 | " . date('Y-m-d H:i:s') . "\n";
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
<title>Anonymous Emergency Reporting Tests — CaseFlowX SCRUM-25</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F4F6F9] font-sans text-sm p-8">
<div class="max-w-2xl mx-auto">
  <div class="bg-[#1B2A4A] text-white rounded-2xl px-7 py-5 mb-6 flex items-center gap-4 shadow-sm">
    <div class="w-12 h-12 rounded-xl bg-rose-600 flex items-center justify-center text-white text-2xl shadow">
      <i class="ti ti-test-pipe"></i>
    </div>
    <div>
      <h1 class="text-lg font-bold">CaseFlowX — Emergency Reporting Test Suite</h1>
      <p class="text-white/50 text-xs mt-0.5">SCRUM-25 · <?= date('Y-m-d H:i:s') ?></p>
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
