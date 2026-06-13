<?php
/**
 * update_profile.php — CaseFlowX
 * Handles profile updates and password changes.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

function json_exit(bool $ok, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(false, 'Method not allowed.');
}

$isPasswordChange = !empty($_POST['current_password']) || !empty($_POST['new_password']);

try {
    $db = get_db();
    $citizenId = (int)$_SESSION['citizen_id'];

    // Fetch current user for verification
    $stmt = $db->prepare('SELECT * FROM citizens WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $citizenId]);
    $user = $stmt->fetch();

    if (!$user) {
        json_exit(false, 'User not found.');
    }

    if ($isPasswordChange) {
        // ── Password Change ───────────────────────────────────────────────
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $errors = [];

        if ($currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, $user['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        if ($newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $errors['new_password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $errors['new_password'] = 'Password must contain at least one number.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            json_exit(false, 'Please fix the errors below.', ['errors' => $errors]);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $update = $db->prepare('UPDATE citizens SET password_hash = :hash WHERE id = :id');
        $update->execute([':hash' => $newHash, ':id' => $citizenId]);

        json_exit(true, 'Password updated successfully.');
    } else {
        // ── Profile Update ────────────────────────────────────────────────
        $email    = trim($_POST['email'] ?? '');
        $division = trim($_POST['division'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $errors   = [];

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if ($division === '') {
            $errors['division'] = 'Division is required.';
        }

        if ($district === '') {
            $errors['district'] = 'District is required.';
        }

        if ($address === '') {
            $errors['address'] = 'Address is required.';
        } elseif (mb_strlen($address) < 10) {
            $errors['address'] = 'Please provide a more detailed address.';
        }

        // Uniqueness check for email if changed
        if ($email !== '' && $email !== ($user['email'] ?? '')) {
            $check = $db->prepare('SELECT id FROM citizens WHERE email = :email AND id != :id LIMIT 1');
            $check->execute([':email' => $email, ':id' => $citizenId]);
            if ($check->fetch()) {
                $errors['email'] = 'This email is already in use.';
            }
        }

        if (!empty($errors)) {
            json_exit(false, 'Please fix the errors below.', ['errors' => $errors]);
        }

        $update = $db->prepare('
            UPDATE citizens
            SET email = :email, division = :division, district = :district, address = :address
            WHERE id = :id
        ');
        $update->execute([
            ':email'    => $email !== '' ? $email : null,
            ':division' => $division,
            ':district' => $district,
            ':address'  => $address,
            ':id'       => $citizenId,
        ]);

        json_exit(true, 'Profile updated successfully.');
    }
} catch (PDOException $e) {
    error_log('[CaseFlowX] Profile update DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Please try again later.');
}
