<?php
/**
 * login_action.php — CaseFlowX
 * Handles citizen login form submission.
 * Validates input, checks credentials, starts session.
 * Returns JSON response (used by login.php via fetch).
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// ── Helper ──────────────────────────────────────────────────────────────────
function json_exit(bool $ok, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

// ── Only accept POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(false, 'Method not allowed.');
}

// ── Collect input ────────────────────────────────────────────────────────────
$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === 'on';

$errors = [];

// ── Validation ───────────────────────────────────────────────────────────────
if ($identifier === '') {
    $errors['identifier'] = 'Phone or NID is required.';
} else {
    // Check if it looks like a phone or NID
    $isPhone = preg_match('/^01[3-9]\d{8}$/', $identifier);
    $isNID = preg_match('/^\d{10}$|^\d{17}$/', $identifier);
    if (!$isPhone && !$isNID) {
        $errors['identifier'] = 'Enter a valid phone number or NID.';
    }
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
}

if (!empty($errors)) {
    json_exit(false, 'Please fix the errors below.', ['errors' => $errors]);
}

// ── Authentication ───────────────────────────────────────────────────────────
try {
    $db = get_db();

    // Determine if identifier is phone or NID
    $isPhone = preg_match('/^01[3-9]\d{8}$/', $identifier);

    if ($isPhone) {
        $stmt = $db->prepare('SELECT * FROM citizens WHERE phone = :identifier LIMIT 1');
    } else {
        $stmt = $db->prepare('SELECT * FROM citizens WHERE national_id = :identifier LIMIT 1');
    }
    $stmt->execute([':identifier' => $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        json_exit(false, 'Invalid credentials.', ['errors' => ['identifier' => 'Account not found.']]);
    }

    if ($user['status'] !== 'active') {
        json_exit(false, 'This account has been suspended. Please contact support.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        json_exit(false, 'Invalid credentials.', ['errors' => ['password' => 'Incorrect password.']]);
    }

    // ── Update last login ─────────────────────────────────────────────────────
    $update = $db->prepare('UPDATE citizens SET last_login = datetime("now") WHERE id = :id');
    $update->execute([':id' => $user['id']]);

    // ── Set session ───────────────────────────────────────────────────────────
    $_SESSION['citizen_id'] = $user['id'];
    $_SESSION['citizen_name'] = $user['full_name'];
    $_SESSION['citizen_phone'] = $user['phone'];
    $_SESSION['citizen_nid'] = $user['national_id'];
    $_SESSION['logged_in'] = true;

    // If remember me is checked, set longer session
    if ($remember) {
        $_SESSION['remember'] = true;
        // Extend session cookie lifetime to 30 days
        setcookie(session_name(), session_id(), [
            'expires' => time() + 30 * 24 * 60 * 60,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    json_exit(true, 'Login successful! Welcome back.', ['redirect' => 'dashboard.php']);

} catch (PDOException $e) {
    // Log internally, return generic error to client
    error_log('[CaseFlowX] Login DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Please try again later.');
}
