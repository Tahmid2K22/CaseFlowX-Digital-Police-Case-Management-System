<?php
/**
 * officer-login_action.php — FIR Officer Login Handler
 */

require __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Collect input
$badge_number = trim($_POST['badge_number'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validate required fields
$errors = [];
if (!$badge_number) {
    $errors['badge_number'] = 'Badge number is required.';
}
if (!$password) {
    $errors['password'] = 'Password is required.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Please fix the errors below.', 'errors' => $errors]);
    exit;
}

try {
    $db = get_db();

    // Find officer by badge number
    $stmt = $db->prepare('SELECT * FROM officers WHERE badge_number = ?');
    $stmt->execute([$badge_number]);
    $officer = $stmt->fetch();

    // Check if officer exists
    if (!$officer) {
        error_log("Officer login failed: badge '$badge_number' not found");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    // Check if officer is active
    if ($officer['status'] !== 'active') {
        error_log("Officer login failed: badge '$badge_number' account is {$officer['status']}");
        echo json_encode(['success' => false, 'message' => 'Your account is not active. Please contact your supervisor.']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $officer['password_hash'])) {
        error_log("Officer login failed: invalid password for badge '$badge_number'");
        echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        exit;
    }

    // Update last login
    $update = $db->prepare('UPDATE officers SET last_login = datetime("now") WHERE id = ?');
    $update->execute([$officer['id']]);

    // Set session variables
    $_SESSION['logged_in'] = true;
    $_SESSION['officer_id'] = $officer['id'];
    $_SESSION['officer_name'] = $officer['full_name'];
    $_SESSION['officer_badge'] = $officer['badge_number'];
    $_SESSION['officer_role'] = $officer['role'];
    $_SESSION['officer_station'] = $officer['station_code'];

    // Extend session cookie if remember me is checked
    if ($remember) {
        $lifetime = 30 * 24 * 60 * 60; // 30 days in seconds
        session_set_cookie_params($lifetime, '/', '', true, true);
        session_regenerate_id(true);
    }

    error_log("Officer logged in: badge '{$officer['badge_number']}', name '{$officer['full_name']}'");

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => 'officer-dashboard.php'
    ]);
    exit;

} catch (Exception $e) {
    error_log("Officer login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
    exit;
}