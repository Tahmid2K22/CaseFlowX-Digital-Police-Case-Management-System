<?php
/**
 * emergency_action.php — CaseFlowX
 * Backend endpoint for anonymous emergency report submission and tracking.
 * Ensures reporter anonymity by storing zero identifiable citizen account linkage.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

function json_exit(bool $ok, string $message, array $extra = []): never {
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

// Support both POST (submission) and GET (optional status lookup)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Optional feature: allow citizens to lookup report status anonymously
    $ref = trim($_GET['ref'] ?? '');
    if ($ref === '') {
        json_exit(false, 'Reference number is required.');
    }

    if (!preg_match('/^EMG-\d{4}-\d{4}$/i', $ref)) {
        json_exit(false, 'Invalid reference number format. Must be like EMG-2026-0001.');
    }

    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT report_number, type, location, status, created_at FROM emergencies WHERE UPPER(report_number) = UPPER(:ref) LIMIT 1');
        $stmt->execute([':ref' => $ref]);
        $report = $stmt->fetch();

        if (!$report) {
            json_exit(false, 'Emergency report reference not found.');
        }

        json_exit(true, 'Report status retrieved successfully.', ['report' => $report]);
    } catch (PDOException $e) {
        error_log('[CaseFlowX] Emergency lookup DB error: ' . $e->getMessage());
        json_exit(false, 'A database error occurred.');
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(false, 'Method not allowed.');
}

// Collect input
$type        = trim($_POST['type'] ?? '');
$location    = trim($_POST['location'] ?? '');
$description = trim($_POST['description'] ?? '');
$contactInfo = trim($_POST['contact_info'] ?? '');

$errors = [];

// Validation
$allowedTypes = ['medical', 'fire', 'crime', 'accident', 'other'];
if ($type === '' || !in_array($type, $allowedTypes, true)) {
    $errors['type'] = 'Please select a valid emergency type.';
}

if ($location === '') {
    $errors['location'] = 'Location details are required.';
} elseif (mb_strlen($location) < 5) {
    $errors['location'] = 'Please provide a more specific location (at least 5 characters).';
} elseif (mb_strlen($location) > 500) {
    $errors['location'] = 'Location details must not exceed 500 characters.';
}

if ($description === '') {
    $errors['description'] = 'Description of the emergency is required.';
} elseif (mb_strlen($description) < 10) {
    $errors['description'] = 'Please provide more details about the situation (at least 10 characters).';
} elseif (mb_strlen($description) > 3000) {
    $errors['description'] = 'Description must not exceed 3000 characters.';
}

if ($contactInfo !== '' && mb_strlen($contactInfo) > 100) {
    $errors['contact_info'] = 'Contact details must not exceed 100 characters.';
}

if (!empty($errors)) {
    json_exit(false, 'Please correct the validation errors.', ['errors' => $errors]);
}

try {
    $db = get_db();

    // Generate unique report number: EMG-YYYY-XXXX (sequential per year)
    $year = date('Y');
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

    // Insert emergency report anonymously (zero linkage to citizen user accounts)
    $insert = $db->prepare("
        INSERT INTO emergencies (report_number, type, location, description, contact_info, status)
        VALUES (:report_number, :type, :location, :description, :contact_info, 'received')
    ");

    $insert->execute([
        ':report_number' => $reportNumber,
        ':type'          => $type,
        ':location'      => $location,
        ':description'   => $description,
        ':contact_info'  => ($contactInfo !== '') ? $contactInfo : null
    ]);

    // Simulate notification dispatch system (SCRUM-147)
    // We log the notification dispatch event to an internal dispatch log file
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logPath = $logDir . '/emergency_dispatch.log';
    
    // Choose department based on type
    $department = 'General Emergency Responders';
    if ($type === 'medical') $department = 'Ambulance Services & Health Responders';
    elseif ($type === 'fire') $department = 'Fire Service & Civil Defence';
    elseif ($type === 'crime') $department = 'Local Police Patrol Unit';
    elseif ($type === 'accident') $department = 'Highway Police & Rescue Team';

    $logMsg = sprintf(
        "[%s] DISPATCH TRIGGERED: Reference %s | Type: %s | Location: %s | Notified: %s\n",
        date('Y-m-d H:i:s'),
        $reportNumber,
        strtoupper($type),
        $location,
        $department
    );
    file_put_contents($logPath, $logMsg, FILE_APPEND | LOCK_EX);

    json_exit(true, 'Emergency report submitted anonymously.', [
        'report_number' => $reportNumber,
        'notification_sent' => true,
        'department' => $department
    ]);

} catch (PDOException $e) {
    error_log('[CaseFlowX] Emergency submit DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Emergency services could not be reached via database. Please call 999 directly.');
}
