<?php
/**
 * register_test.php — SCRUM-71
 * Unit & integration tests for citizen registration backend logic.
 *
 * Run from CLI:  php register_test.php
 * Run from web:  http://localhost/citizen_registration/register_test.php
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

// ── Helper: simulate insert ──────────────────────────────────────────────────
function db_register(PDO $db, array $d): array {
    try {
        $hash = password_hash($d['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO citizens
                (full_name,national_id,date_of_birth,gender,phone,email,
                 division,district,address,password_hash)
            VALUES
                (:full_name,:national_id,:date_of_birth,:gender,:phone,:email,
                 :division,:district,:address,:password_hash)
        ");
        $stmt->execute([
            ':full_name'     => $d['full_name'],
            ':national_id'   => $d['national_id'],
            ':date_of_birth' => $d['date_of_birth'],
            ':gender'        => $d['gender'],
            ':phone'         => $d['phone'],
            ':email'         => $d['email'] ?? null,
            ':division'      => $d['division'],
            ':district'      => $d['district'],
            ':address'       => $d['address'],
            ':password_hash' => $hash,
        ]);
        return ['success' => true, 'id' => (int)$db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ── Validation function (mirrors register_action.php logic) ──────────────────
function validate_registration(array $data): array {
    $errors = [];

    $full_name = trim($data['full_name'] ?? '');
    if ($full_name === '') $errors['full_name'] = 'Required';
    elseif (mb_strlen($full_name) < 3) $errors['full_name'] = 'Too short';
    elseif (!preg_match('/^[\p{L}\s\-\.\']+$/u', $full_name)) $errors['full_name'] = 'Invalid chars';

    $nid = trim($data['national_id'] ?? '');
    if ($nid === '') $errors['national_id'] = 'Required';
    elseif (!preg_match('/^\d{10}$|^\d{17}$/', $nid)) $errors['national_id'] = 'Must be 10 or 17 digits';

    $dob = trim($data['date_of_birth'] ?? '');
    if ($dob === '') {
        $errors['date_of_birth'] = 'Required';
    } else {
        $dt = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$dt) $errors['date_of_birth'] = 'Invalid format';
        else {
            $age = (new DateTime())->diff($dt)->y;
            if ($age < 18) $errors['date_of_birth'] = 'Must be 18+';
        }
    }

    $gender = trim($data['gender'] ?? '');
    if (!in_array($gender, ['male','female','other'], true)) $errors['gender'] = 'Invalid';

    $phone = trim($data['phone'] ?? '');
    if ($phone === '') $errors['phone'] = 'Required';
    elseif (!preg_match('/^01[3-9]\d{8}$/', $phone)) $errors['phone'] = 'Invalid BD number';

    $email = trim($data['email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email';

    if (trim($data['division'] ?? '') === '') $errors['division'] = 'Required';
    if (trim($data['district'] ?? '') === '') $errors['district'] = 'Required';

    $address = trim($data['address'] ?? '');
    if ($address === '') $errors['address'] = 'Required';
    elseif (mb_strlen($address) < 10) $errors['address'] = 'Too short';

    $pw  = $data['password']         ?? '';
    $pwc = $data['password_confirm'] ?? '';
    if ($pw === '') $errors['password'] = 'Required';
    elseif (strlen($pw) < 8) $errors['password'] = 'Too short';
    elseif (!preg_match('/[A-Z]/', $pw)) $errors['password'] = 'No uppercase';
    elseif (!preg_match('/[0-9]/', $pw)) $errors['password'] = 'No number';

    if ($pw !== $pwc) $errors['password_confirm'] = 'Mismatch';

    return $errors;
}

// ── Sample valid payload ──────────────────────────────────────────────────────
$valid = [
    'full_name'        => 'Mohammad Rahman',
    'national_id'      => '1234567890',
    'date_of_birth'    => '1990-06-15',
    'gender'           => 'male',
    'phone'            => '01712345678',
    'email'            => 'rahman@example.com',
    'division'         => 'Dhaka',
    'district'         => 'Dhaka',
    'address'          => 'House 12, Road 4, Banani, Dhaka',
    'password'         => 'Secret123',
    'password_confirm' => 'Secret123',
];

// ════════════════════════════════════════════════════════════════════════════
//  TEST SUITE
// ════════════════════════════════════════════════════════════════════════════

section('1. Validation — Required fields');

test('Empty full_name flagged', validate_registration(array_merge($valid,['full_name'=>'']))['full_name'] ?? '' !== '');
test('Short full_name (2 chars) flagged', isset(validate_registration(array_merge($valid,['full_name'=>'Ab']))['full_name']));
test('Numeric-only name flagged', isset(validate_registration(array_merge($valid,['full_name'=>'12345']))['full_name']));
test('Valid full_name passes', !isset(validate_registration($valid)['full_name']));

section('2. Validation — National ID');

test('10-digit NID accepted',  !isset(validate_registration($valid)['national_id']));
test('17-digit NID accepted',  !isset(validate_registration(array_merge($valid,['national_id'=>'12345678901234567']))['national_id']));
test('9-digit NID rejected',   isset(validate_registration(array_merge($valid,['national_id'=>'123456789']))['national_id']));
test('11-digit NID rejected',  isset(validate_registration(array_merge($valid,['national_id'=>'12345678901']))['national_id']));
test('Alpha NID rejected',     isset(validate_registration(array_merge($valid,['national_id'=>'ABC1234567']))['national_id']));

section('3. Validation — Age');

test('Age 18 accepted',  !isset(validate_registration(array_merge($valid,['date_of_birth'=>date('Y-m-d',strtotime('-18 years'))]))['date_of_birth']));
test('Age 17 rejected',  isset(validate_registration(array_merge($valid,['date_of_birth'=>date('Y-m-d',strtotime('-17 years'))]))['date_of_birth']));
test('Invalid date format rejected', isset(validate_registration(array_merge($valid,['date_of_birth'=>'15/06/1990']))['date_of_birth']));

section('4. Validation — Phone');

test('Valid BD phone 013 accepted', !isset(validate_registration(array_merge($valid,['phone'=>'01312345678']))['phone']));
test('Valid BD phone 019 accepted', !isset(validate_registration(array_merge($valid,['phone'=>'01912345678']))['phone']));
test('10-digit phone rejected',     isset(validate_registration(array_merge($valid,['phone'=>'0171234567']))['phone']));
test('012 prefix rejected',         isset(validate_registration(array_merge($valid,['phone'=>'01212345678']))['phone']));
test('Non-numeric phone rejected',  isset(validate_registration(array_merge($valid,['phone'=>'0171234567A']))['phone']));

section('5. Validation — Email');

test('Valid email passes',   !isset(validate_registration($valid)['email']));
test('Empty email passes (optional)', !isset(validate_registration(array_merge($valid,['email'=>'']))['email']));
test('Bad email rejected',   isset(validate_registration(array_merge($valid,['email'=>'not-an-email']))['email']));

section('6. Validation — Password');

test('Strong password passes', !isset(validate_registration($valid)['password']));
test('Short password rejected', isset(validate_registration(array_merge($valid,['password'=>'Ab1','password_confirm'=>'Ab1']))['password']));
test('No uppercase rejected',   isset(validate_registration(array_merge($valid,['password'=>'secret123','password_confirm'=>'secret123']))['password']));
test('No digit rejected',       isset(validate_registration(array_merge($valid,['password'=>'SecretPass','password_confirm'=>'SecretPass']))['password']));
test('Mismatched confirm rejected', isset(validate_registration(array_merge($valid,['password_confirm'=>'Different1']))['password_confirm']));

section('7. Validation — Address');

test('Valid address passes', !isset(validate_registration($valid)['address']));
test('Short address rejected', isset(validate_registration(array_merge($valid,['address'=>'Short']))['address']));
test('Empty address rejected', isset(validate_registration(array_merge($valid,['address'=>'']))['address']));

section('8. Database — Successful registration');

$result1 = db_register($test_pdo, $valid);
test('Insert succeeds', $result1['success'] === true);
test('Returns numeric ID', isset($result1['id']) && $result1['id'] > 0);

$row = $test_pdo->query('SELECT * FROM citizens WHERE id = ' . $result1['id'])->fetch();
test('Full name stored correctly',    $row['full_name']    === 'Mohammad Rahman');
test('Phone stored correctly',        $row['phone']        === '01712345678');
test('Division stored correctly',     $row['division']     === 'Dhaka');
test('Status defaults to active',     $row['status']       === 'active');
test('Password is hashed (not plain)', $row['password_hash'] !== 'Secret123');
test('Password hash verifiable',       password_verify('Secret123', $row['password_hash']));
test('created_at is set',              !empty($row['created_at']));

section('9. Database — Uniqueness constraints');

// Duplicate NID
$dup_nid = db_register($test_pdo, array_merge($valid, ['phone'=>'01812345678','email'=>'other@x.com']));
test('Duplicate NID rejected by DB', $dup_nid['success'] === false);

// Duplicate phone
$dup_phone = db_register($test_pdo, array_merge($valid, ['national_id'=>'9876543210','email'=>'other2@x.com']));
test('Duplicate phone rejected by DB', $dup_phone['success'] === false);

// Duplicate email
$dup_email = db_register($test_pdo, array_merge($valid, [
    'national_id' => '1111111111',
    'phone'       => '01911111111',
    'email'       => 'rahman@example.com',  // same as valid
]));
test('Duplicate email rejected by DB', $dup_email['success'] === false);

section('10. Database — NULL email allowed');

$no_email = db_register($test_pdo, array_merge($valid, [
    'national_id' => '2222222222',
    'phone'       => '01922222222',
    'email'       => '',
]));
test('Registration without email succeeds', $no_email['success'] === true);
$row2 = $test_pdo->query('SELECT email FROM citizens WHERE id = ' . $no_email['id'])->fetch();
test('Email stored as NULL when empty', $row2['email'] === null);

section('11. Database — Multiple citizens');

$second = db_register($test_pdo, [
    'full_name'     => 'Fatema Begum',
    'national_id'   => '3333333333',
    'date_of_birth' => '1985-03-20',
    'gender'        => 'female',
    'phone'         => '01633333333',
    'email'         => 'fatema@example.com',
    'division'      => 'Chittagong',
    'district'      => 'Chattogram',
    'address'       => 'Road 7, Agrabad, Chattogram',
    'password'      => 'Password1',
    'password_confirm' => 'Password1',
]);
test('Second citizen registered successfully', $second['success'] === true);
$count = $test_pdo->query('SELECT COUNT(*) as c FROM citizens')->fetch()['c'];
test('DB has 3 citizen records (valid + no_email + second)', (int)$count === 3);

// ════════════════════════════════════════════════════════════════════════════
//  OUTPUT
// ════════════════════════════════════════════════════════════════════════════
$is_cli = php_sapi_name() === 'cli';

if ($is_cli) {
    echo "\n  CaseFlowX — Citizen Registration Test Suite\n";
    echo "  SCRUM-71 | " . date('Y-m-d H:i:s') . "\n";
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
<title>Registration Tests — CaseFlowX SCRUM-71</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans text-sm p-8">
<div class="max-w-2xl mx-auto">
  <div class="bg-[#1B2A4A] text-white rounded-2xl px-7 py-5 mb-6 flex items-center gap-4">
    <i class="ti ti-test-pipe text-3xl text-[#1D9E75]"></i>
    <div>
      <h1 class="text-lg font-bold">CaseFlowX — Registration Test Suite</h1>
      <p class="text-white/50 text-xs mt-0.5">SCRUM-71 · <?= date('Y-m-d H:i:s') ?></p>
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

  <div class="mt-6 rounded-2xl px-6 py-4 <?= $FAIL > 0 ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200' ?>">
    <p class="font-bold text-base <?= $FAIL > 0 ? 'text-red-700' : 'text-green-700' ?>">
      <?= $FAIL > 0 ? "⚠ {$FAIL} test(s) failed." : "✅ All {$PASS} tests passed." ?>
    </p>
  </div>
</div>
</body>
</html>
<?php
