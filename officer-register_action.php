<?php
/**
 * officer-register_action.php — CaseFlowX Officer Registration Handler
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function json_exit(array $data): void {
    echo json_encode($data);
    exit;
}

$errors = [];

// ── Collect & trim inputs ────────────────────────────────────────────────────
$badge_number    = trim($_POST['badge_number'] ?? '');
$full_name       = trim($_POST['full_name'] ?? '');
$email           = trim($_POST['email'] ?? '');
$phone           = trim($_POST['phone'] ?? '');
$station_code    = trim($_POST['station_code'] ?? '');
// Role is fixed — always FIR Officer
$role = 'FIR Officer';
$password        = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// ── Validate badge_number ────────────────────────────────────────────────────
if (empty($badge_number)) {
    $errors['badge_number'] = 'Badge number is required.';
} elseif (strlen($badge_number) < 3) {
    $errors['badge_number'] = 'Badge number must be at least 3 characters.';
}

// ── Validate full_name ───────────────────────────────────────────────────────
if (empty($full_name)) {
    $errors['full_name'] = 'Full name is required.';
} elseif (strlen($full_name) < 3) {
    $errors['full_name'] = 'Full name must be at least 3 characters.';
}

// ── Validate email (optional) ────────────────────────────────────────────────
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

// ── Validate phone (Bangladesh format) ──────────────────────────────────────
if (empty($phone)) {
    $errors['phone'] = 'Phone number is required.';
} elseif (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
    $errors['phone'] = 'Enter a valid Bangladesh phone number (01XXXXXXXXX).';
}

// ── Validate station_code ────────────────────────────────────────────────────
if (empty($station_code)) {
    $errors['station_code'] = 'Station code is required.';
} elseif (strlen($station_code) < 2) {
    $errors['station_code'] = 'Station code must be at least 2 characters.';
}

// ── Validate password ────────────────────────────────────────────────────────
if (empty($password)) {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

// ── Validate password_confirm ────────────────────────────────────────────────
if ($password !== $password_confirm) {
    $errors['password_confirm'] = 'Passwords do not match.';
}

// ── Return validation errors early ───────────────────────────────────────────
if (!empty($errors)) {
    json_exit([
        'success' => false,
        'message' => 'Please correct the errors below.',
        'errors'  => $errors,
    ]);
}

// ── Database uniqueness checks ───────────────────────────────────────────────
try {
    $db = get_db();

    // Check badge_number uniqueness
    $stmt = $db->prepare('SELECT id FROM officers WHERE badge_number = ?');
    $stmt->execute([$badge_number]);
    if ($stmt->fetch()) {
        $errors['badge_number'] = 'This badge number is already registered.';
    }

    // Check phone uniqueness
    $stmt = $db->prepare('SELECT id FROM officers WHERE phone = ?');
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        $errors['phone'] = 'This phone number is already registered.';
    }

    // Check email uniqueness (only if provided)
    if ($email !== '') {
        $stmt = $db->prepare('SELECT id FROM officers WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email address is already registered.';
        }
    }

    if (!empty($errors)) {
        json_exit([
            'success' => false,
            'message' => 'Please correct the errors below.',
            'errors'  => $errors,
        ]);
    }

    // ── Hash password & insert ──────────────────────────────────────────────────
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare("
        INSERT INTO officers (badge_number, full_name, email, phone, station_code, role, password_hash, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', datetime('now'))
    ");
    $stmt->execute([
        $badge_number,
        $full_name,
        $email ?: null,
        $phone,
        $station_code,
        $role,
        $password_hash,
    ]);

    json_exit([
        'success'  => true,
        'message'  => 'Officer registration successful!',
        'redirect' => 'officer-login.php',
    ]);

} catch (PDOException $e) {
    error_log('Officer registration error: ' . $e->getMessage());
    json_exit([
        'success' => false,
        'message' => 'Registration failed. Please try again later.',
    ]);
}