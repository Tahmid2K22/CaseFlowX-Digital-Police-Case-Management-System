<?php
/**
 * register_action.php — SCRUM-67 / SCRUM-68
 * Handles citizen registration form submission.
 * Validates input, hashes password, stores to SQLite.
 * Returns JSON response (used by register.php via fetch).
 */

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

// ── Collect & sanitize input ─────────────────────────────────────────────────
$raw = [
    'full_name'      => trim($_POST['full_name']      ?? ''),
    'national_id'    => trim($_POST['national_id']    ?? ''),
    'date_of_birth'  => trim($_POST['date_of_birth']  ?? ''),
    'gender'         => trim($_POST['gender']         ?? ''),
    'phone'          => trim($_POST['phone']          ?? ''),
    'email'          => trim($_POST['email']          ?? ''),
    'division'       => trim($_POST['division']       ?? ''),
    'district'       => trim($_POST['district']       ?? ''),
    'address'        => trim($_POST['address']        ?? ''),
    'password'       => $_POST['password']            ?? '',
    'password_confirm' => $_POST['password_confirm']  ?? '',
];

$errors = [];

// ── Validation rules ─────────────────────────────────────────────────────────

// Full name
if ($raw['full_name'] === '') {
    $errors['full_name'] = 'Full name is required.';
} elseif (mb_strlen($raw['full_name']) < 3) {
    $errors['full_name'] = 'Name must be at least 3 characters.';
} elseif (!preg_match('/^[\p{L}\s\-\.\']+$/u', $raw['full_name'])) {
    $errors['full_name'] = 'Name contains invalid characters.';
}

// National ID — Bangladesh NID: 10 or 17 digits
if ($raw['national_id'] === '') {
    $errors['national_id'] = 'National ID is required.';
} elseif (!preg_match('/^\d{10}$|^\d{17}$/', $raw['national_id'])) {
    $errors['national_id'] = 'National ID must be 10 or 17 digits.';
}

// Date of birth
if ($raw['date_of_birth'] === '') {
    $errors['date_of_birth'] = 'Date of birth is required.';
} else {
    $dob = DateTime::createFromFormat('Y-m-d', $raw['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $raw['date_of_birth']) {
        $errors['date_of_birth'] = 'Invalid date format.';
    } else {
        $age = (new DateTime())->diff($dob)->y;
        if ($age < 18) {
            $errors['date_of_birth'] = 'You must be at least 18 years old.';
        } elseif ($age > 120) {
            $errors['date_of_birth'] = 'Please enter a valid date of birth.';
        }
    }
}

// Gender
if (!in_array($raw['gender'], ['male', 'female', 'other'], true)) {
    $errors['gender'] = 'Please select a valid gender.';
}

// Phone — Bangladesh mobile: 01XXXXXXXXX (11 digits)
if ($raw['phone'] === '') {
    $errors['phone'] = 'Phone number is required.';
} elseif (!preg_match('/^01[3-9]\d{8}$/', $raw['phone'])) {
    $errors['phone'] = 'Enter a valid Bangladeshi phone number (e.g. 01XXXXXXXXX).';
}

// Email (optional but must be valid if provided)
if ($raw['email'] !== '' && !filter_var($raw['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

// Division
if ($raw['division'] === '') {
    $errors['division'] = 'Division is required.';
}

// District
if ($raw['district'] === '') {
    $errors['district'] = 'District is required.';
}

// Address
if ($raw['address'] === '') {
    $errors['address'] = 'Address is required.';
} elseif (mb_strlen($raw['address']) < 10) {
    $errors['address'] = 'Please provide a more detailed address.';
}

// Password
if ($raw['password'] === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($raw['password']) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
} elseif (!preg_match('/[A-Z]/', $raw['password'])) {
    $errors['password'] = 'Password must contain at least one uppercase letter.';
} elseif (!preg_match('/[0-9]/', $raw['password'])) {
    $errors['password'] = 'Password must contain at least one number.';
}

// Confirm password
if ($raw['password'] !== $raw['password_confirm']) {
    $errors['password_confirm'] = 'Passwords do not match.';
}

// ── Return validation errors early ───────────────────────────────────────────
if (!empty($errors)) {
    json_exit(false, 'Please fix the errors below.', ['errors' => $errors]);
}

// ── Uniqueness checks ─────────────────────────────────────────────────────────
try {
    $db = get_db();

    $stmt = $db->prepare('SELECT id FROM citizens WHERE national_id = :nid LIMIT 1');
    $stmt->execute([':nid' => $raw['national_id']]);
    if ($stmt->fetch()) {
        json_exit(false, 'Please fix the errors below.', [
            'errors' => ['national_id' => 'This National ID is already registered.']
        ]);
    }

    $stmt = $db->prepare('SELECT id FROM citizens WHERE phone = :phone LIMIT 1');
    $stmt->execute([':phone' => $raw['phone']]);
    if ($stmt->fetch()) {
        json_exit(false, 'Please fix the errors below.', [
            'errors' => ['phone' => 'This phone number is already registered.']
        ]);
    }

    if ($raw['email'] !== '') {
        $stmt = $db->prepare('SELECT id FROM citizens WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $raw['email']]);
        if ($stmt->fetch()) {
            json_exit(false, 'Please fix the errors below.', [
                'errors' => ['email' => 'This email address is already registered.']
            ]);
        }
    }

    // ── Insert ────────────────────────────────────────────────────────────────
    $hash = password_hash($raw['password'], PASSWORD_BCRYPT);

    $insert = $db->prepare("
        INSERT INTO citizens
            (full_name, national_id, date_of_birth, gender, phone, email,
             division, district, address, password_hash)
        VALUES
            (:full_name, :national_id, :date_of_birth, :gender, :phone, :email,
             :division, :district, :address, :password_hash)
    ");

    $insert->execute([
        ':full_name'     => $raw['full_name'],
        ':national_id'   => $raw['national_id'],
        ':date_of_birth' => $raw['date_of_birth'],
        ':gender'        => $raw['gender'],
        ':phone'         => $raw['phone'],
        ':email'         => $raw['email'] !== '' ? $raw['email'] : null,
        ':division'      => $raw['division'],
        ':district'      => $raw['district'],
        ':address'       => $raw['address'],
        ':password_hash' => $hash,
    ]);

    $newId = $db->lastInsertId();

    json_exit(true, 'Registration successful! You can now log in to file complaints.', [
        'citizen_id' => (int)$newId
    ]);

} catch (PDOException $e) {
    // Log internally, return generic error to client
    error_log('[CaseFlowX] Registration DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Please try again later.');
}
