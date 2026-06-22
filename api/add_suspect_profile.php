<?php
/**
 * api/add_suspect_profile.php — Handles adding a suspect profile linked to a case.
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) && !isset($_SESSION['user_id']) && empty($_SESSION['officer_id']) && empty($_SESSION['citizen_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $db = get_db();

    // Resolve user details
    $currentUserRole = $_SESSION['role'] ?? '';
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $currentUserName = $_SESSION['username'] ?? '';

    $isOfficerSession = !empty($_SESSION['officer_id']);

    if ($isOfficerSession) {
        $stmt = $db->prepare('SELECT id, badge_number, full_name, email, phone, station_code, role, status FROM officers WHERE id = ? AND status = ?');
        $stmt->execute([$_SESSION['officer_id'], 'active']);
        $officer = $stmt->fetch();
        if ($officer) {
            $currentUserRole = $officer['role'];
            $currentUserId = $officer['id'];
            $currentUserName = $officer['full_name'];
        }
    } else {
        $stmt = $db->prepare('SELECT id, full_name, role, status FROM users WHERE id = ? AND status = ?');
        $stmt->execute([$currentUserId, 'Active']);
        $user = $stmt->fetch();
        if ($user) {
            $currentUserRole = $user['role'];
            $currentUserName = $user['full_name'];
        }
    }

    if (empty($currentUserRole)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid session or account inactive']);
        exit;
    }

    // Input collection
    $case_id = (int)($_POST['case_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $national_id = trim($_POST['national_id'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $physical_description = trim($_POST['physical_description'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? 'identified');

    if (!$case_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid case ID']);
        exit;
    }

    $stmtCase = $db->prepare("SELECT * FROM cases WHERE id = ?");
    $stmtCase->execute([$case_id]);
    $case = $stmtCase->fetch();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Case not found']);
        exit;
    }

    // Access control check: Admins, Officers, and Assigned Investigators only
    $allowed = false;
    if ($currentUserRole === 'Admin' || $currentUserRole === 'Officer' || $currentUserRole === 'FIR Officer' || $currentUserRole === 'Supervisor') {
        $allowed = true;
    } elseif ($currentUserRole === 'Investigator') {
        if ((int)$case['investigator_id'] === (int)$currentUserId) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied. Only the assigned investigator or managing officers can add suspect profiles.']);
        exit;
    }

    // Validation
    if ($full_name === '') {
        echo json_encode(['success' => false, 'error' => 'Full name is required.']);
        exit;
    }

    $validStatuses = ['identified', 'wanted', 'under_arrest', 'released', 'convicted'];
    if (!in_array($status, $validStatuses, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status option selected.']);
        exit;
    }

    if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid gender option selected.']);
        exit;
    }

    if ($date_of_birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
        echo json_encode(['success' => false, 'error' => 'Date of birth must be in YYYY-MM-DD format.']);
        exit;
    }

    if ($national_id !== '' && !preg_match('/^\d{10,17}$/', $national_id)) {
        echo json_encode(['success' => false, 'error' => 'National ID must be between 10 and 17 digits.']);
        exit;
    }

    if ($phone !== '' && !preg_match('/^\+?\d{9,15}$/', $phone)) {
        echo json_encode(['success' => false, 'error' => 'Invalid phone number format.']);
        exit;
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address format.']);
        exit;
    }

    // Photo upload handling
    $db_photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($file['tmp_name']);
        
        $mimeMap = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png'
        ];

        if (!isset($mimeMap[$ext]) || $mimeMap[$ext] !== $mime) {
            echo json_encode(['success' => false, 'error' => 'Invalid photo file type. Only JPG and PNG are allowed.']);
            exit;
        }

        if ($file['size'] > $maxSize) {
            echo json_encode(['success' => false, 'error' => 'Photo file too large. Maximum 5MB allowed.']);
            exit;
        }

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'suspect_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($file['name']));
        $filePath = $uploadDir . $fileName;
        $relativeVisitPath = 'uploads/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save suspect photo.']);
            exit;
        }
        $db_photo_path = $relativeVisitPath;
    }

    // Normalize empty strings to null for optional database columns
    $db_national_id = ($national_id === '') ? null : $national_id;
    $db_phone = ($phone === '') ? null : $phone;
    $db_email = ($email === '') ? null : $email;
    $db_gender = ($gender === '') ? null : $gender;
    $db_dob = ($date_of_birth === '') ? null : $date_of_birth;
    $db_phys = ($physical_description === '') ? null : $physical_description;
    $db_addr = ($address === '') ? null : $address;

    // Save to database
    $stmtInsert = $db->prepare("
        INSERT INTO suspect_profiles (
            case_id, full_name, national_id, phone, email, gender, date_of_birth, physical_description, address, status, photo_path, created_by
        ) VALUES (
            :case_id, :full_name, :national_id, :phone, :email, :gender, :date_of_birth, :physical_description, :address, :status, :photo_path, :created_by
        )
    ");
    $stmtInsert->execute([
        'case_id' => $case_id,
        'full_name' => $full_name,
        'national_id' => $db_national_id,
        'phone' => $db_phone,
        'email' => $db_email,
        'gender' => $db_gender,
        'date_of_birth' => $db_dob,
        'physical_description' => $db_phys,
        'address' => $db_addr,
        'status' => $status,
        'photo_path' => $db_photo_path,
        'created_by' => $currentUserId
    ]);

    // Format status for logs/display
    $statusText = ucfirst(str_replace('_', ' ', $status));

    // Log timeline event
    add_case_timeline_event($db, $case_id, 'other', 'Suspect Profile Added', "Suspect profile added: {$full_name} ({$statusText})", $currentUserName);

    // Update modified_by / modified_at in cases table
    $stmtUpdateCase = $db->prepare("UPDATE cases SET modified_by = ?, modified_at = datetime('now') WHERE id = ?");
    $stmtUpdateCase->execute([$currentUserId, $case_id]);

    // Fetch updated suspects list
    $suspStmt = $db->prepare("SELECT * FROM suspect_profiles WHERE case_id = ? ORDER BY id ASC");
    $suspStmt->execute([$case_id]);
    $suspects = $suspStmt->fetchAll();

    // Fetch updated timeline events
    $tlStmt = $db->prepare("SELECT * FROM case_timeline WHERE case_id = ? ORDER BY created_at ASC, id ASC");
    $tlStmt->execute([$case_id]);
    $timeline = $tlStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'message' => 'Suspect profile added successfully',
        'suspects' => $suspects,
        'timeline' => $timeline
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
