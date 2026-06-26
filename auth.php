<?php
// auth.php - Authentication and Session Management

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Language switching logic
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    $query = $_GET;
    unset($query['lang']);
    if (!empty($query)) {
        $redirect .= '?' . http_build_query($query);
    }
    header("Location: " . $redirect);
    exit;
}

// Default language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

/**
 * Log in a user by verifying their password against Phone or NID.
 */
function login($identifier, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = :val OR national_id = :val LIMIT 1");
    $stmt->execute(['val' => $identifier]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] === 'Suspended') {
            return ['success' => false, 'error' => 'Your account is suspended. Please contact support.'];
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        if ($user['role'] === 'Officer') {
            $_SESSION['officer_id'] = $user['id'];
            $_SESSION['officer_name'] = $user['full_name'];
            $_SESSION['officer_role'] = $user['role'];
        } elseif ($user['role'] === 'Citizen') {
            $_SESSION['citizen_id'] = $user['id'];
        }
        
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => 'Invalid Phone/NID or password.'];
}

/**
 * Log out the current user.
 */
function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $lang = $_SESSION['lang'] ?? 'en';
    $_SESSION = [];
    session_destroy();

    // Restart session to store language preference
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['lang'] = $lang;
}

/**
 * Check if the user is logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Check role-based access. Redirects if access is denied.
 *
 * @param array $allowed_roles Array of roles allowed to access the page
 */
function check_access(array $allowed_roles) {
    global $pdo;

    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }

    // Live database status check to ensure immediate enforcement of suspensions or role changes
    $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        logout();
        header("Location: login.php");
        exit;
    }

    if ($user['status'] === 'Suspended') {
        logout();
        header("Location: login.php?error=" . urlencode("Your account has been suspended."));
        exit;
    }

    // Update session role with live database role in case it was changed
    $_SESSION['role'] = $user['role'];

    if (!in_array($user['role'], $allowed_roles)) {
        header("Location: unauthorized.php");
        exit;
    }
}
