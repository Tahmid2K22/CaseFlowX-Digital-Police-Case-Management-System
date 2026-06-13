<?php
// signup_action.php - Handles guest public registrations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');
$date_of_birth = trim($_POST['date_of_birth'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$division = trim($_POST['division'] ?? '');
$district = trim($_POST['district'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$role = $_POST['role'] ?? 'Citizen';
$status = $_POST['status'] ?? 'Active';

$errors = [];

// Validation rules
if (empty($full_name)) {
    $errors['full_name'] = $_SESSION['lang'] === 'bn' ? 'পূর্ণ নাম আবশ্যক।' : 'Full Name is required.';
}

if (empty($national_id)) {
    $errors['national_id'] = $_SESSION['lang'] === 'bn' ? 'জাতীয় পরিচয়পত্র নম্বর (এনআইডি) আবশ্যক।' : 'National ID (NID) is required.';
} elseif (!preg_match('/^\d{10}$|^\d{17}$/', $national_id)) {
    $errors['national_id'] = $_SESSION['lang'] === 'bn' ? 'এনআইডি অবশ্যই ১০ বা ১৭ ডিজিটের হতে হবে।' : 'NID must be 10 or 17 digits.';
}

if (empty($date_of_birth)) {
    $errors['date_of_birth'] = $_SESSION['lang'] === 'bn' ? 'জন্ম তারিখ আবশ্যক।' : 'Date of Birth is required.';
}

if (!in_array($gender, ['male', 'female', 'other'])) {
    $errors['gender'] = $_SESSION['lang'] === 'bn' ? 'লিঙ্গ নির্বাচন করুন।' : 'Please select a gender.';
}

if (empty($phone)) {
    $errors['phone'] = $_SESSION['lang'] === 'bn' ? 'মোবাইল নম্বর আবশ্যক।' : 'Mobile Phone is required.';
} elseif (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
    $errors['phone'] = $_SESSION['lang'] === 'bn' ? 'সঠিক মোবাইল নম্বর (যেমন: 01XXXXXXXXX) প্রদান করুন।' : 'Enter a valid Bangladeshi phone number (e.g. 01XXXXXXXXX).';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = $_SESSION['lang'] === 'bn' ? 'সঠিক ইমেল ঠিকানা প্রদান করুন।' : 'Please enter a valid email address.';
}

if (empty($division)) {
    $errors['division'] = $_SESSION['lang'] === 'bn' ? 'বিভাগ আবশ্যক।' : 'Division is required.';
}

if (empty($district)) {
    $errors['district'] = $_SESSION['lang'] === 'bn' ? 'জেলা আবশ্যক।' : 'District is required.';
}

if (empty($address)) {
    $errors['address'] = $_SESSION['lang'] === 'bn' ? 'ঠিকানা আবশ্যক।' : 'Address is required.';
}

if (empty($password)) {
    $errors['password'] = $_SESSION['lang'] === 'bn' ? 'পাসওয়ার্ড আবশ্যক।' : 'Password is required.';
} elseif (strlen($password) < 6) {
    $errors['password'] = $_SESSION['lang'] === 'bn' ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.';
}

if ($password !== $password_confirm) {
    $errors['password_confirm'] = $_SESSION['lang'] === 'bn' ? 'পাসওয়ার্ড দুটি মেলেনি।' : 'Passwords do not match.';
}

if (!in_array($role, ['Admin', 'Officer', 'Investigator', 'Citizen'])) {
    $errors['role'] = 'Invalid role.';
}

if (!in_array($status, ['Active', 'Suspended'])) {
    $errors['status'] = 'Invalid status.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => ($_SESSION['lang'] === 'bn' ? 'অনুগ্রহ করে ত্রুটিগুলো সংশোধন করুন।' : 'Please fix the errors below.'), 'errors' => $errors]);
    exit;
}

try {
    // Check unique phone
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errors found.', 
            'errors' => ['phone' => ($_SESSION['lang'] === 'bn' ? 'মোবাইল নম্বরটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'Phone number is already registered.')]
        ]);
        exit;
    }

    // Check unique NID
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE national_id = ?");
    $stmt->execute([$national_id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Errors found.', 
            'errors' => ['national_id' => ($_SESSION['lang'] === 'bn' ? 'জাতীয় পরিচয়পত্র নম্বরটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'National ID is already registered.')]
        ]);
        exit;
    }

    // Check unique email (if provided)
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false, 
                'message' => 'Errors found.', 
                'errors' => ['email' => ($_SESSION['lang'] === 'bn' ? 'ইমেল ঠিকানাটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'Email address is already in use.')]
            ]);
            exit;
        }
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, national_id, date_of_birth, gender, phone, email, division, district, address, password, role, status) VALUES (:full_name, :national_id, :date_of_birth, :gender, :phone, :email, :division, :district, :address, :password, :role, :status)");
    $res = $stmt->execute([
        'full_name' => $full_name,
        'national_id' => $national_id,
        'date_of_birth' => $date_of_birth,
        'gender' => $gender,
        'phone' => $phone,
        'email' => !empty($email) ? $email : null,
        'division' => $division,
        'district' => $district,
        'address' => $address,
        'password' => $hashed_password,
        'role' => $role,
        'status' => $status
    ]);

    if ($res) {
        echo json_encode([
            'success' => true, 
            'message' => ($_SESSION['lang'] === 'bn' ? 'অ্যাকাউন্ট সফলভাবে তৈরি হয়েছে!' : 'Account created successfully!'), 
            'redirect' => 'login.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => ($_SESSION['lang'] === 'bn' ? 'অ্যাকাউন্ট তৈরি করতে ব্যর্থ হয়েছে।' : 'Failed to create account.')]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
exit;
