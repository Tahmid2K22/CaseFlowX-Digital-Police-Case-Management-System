<?php
/**
 * submit_case.php — CaseFlowX
 * Validates and persists a new case complaint.
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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(false, 'Method not allowed.');
}

// Collect input
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$priority    = trim($_POST['priority'] ?? '');

$errors = [];

// Validation
if ($title === '') {
    $errors['title'] = 'Case title is required.';
} elseif (mb_strlen($title) < 3) {
    $errors['title'] = 'Title must be at least 3 characters.';
} elseif (mb_strlen($title) > 200) {
    $errors['title'] = 'Title must not exceed 200 characters.';
}

if ($description === '') {
    $errors['description'] = 'Description is required.';
} elseif (mb_strlen($description) < 10) {
    $errors['description'] = 'Please provide a more detailed description (at least 10 characters).';
} elseif (mb_strlen($description) > 5000) {
    $errors['description'] = 'Description must not exceed 5000 characters.';
}

if (!in_array($priority, ['low', 'medium', 'high'], true)) {
    $errors['priority'] = 'Please select a valid priority.';
}

if (!empty($errors)) {
    json_exit(false, 'Please fix the errors below.', ['errors' => $errors]);
}

try {
    $db = get_db();
    $citizenId = (int)$_SESSION['citizen_id'];

    // Fetch citizen data
    $citizenStmt = $db->prepare("
        SELECT full_name, national_id, phone, address, district, division
        FROM citizens WHERE id = :id
    ");
    $citizenStmt->execute([':id' => $citizenId]);
    $citizen = $citizenStmt->fetch(PDO::FETCH_ASSOC);

    if (!$citizen) {
        json_exit(false, 'Citizen record not found.');
    }

    // Build complainant address
    $complainantAddress = implode(', ', array_filter([
        $citizen['district'],
        $citizen['division'],
        $citizen['address'],
    ]));

    // Generate case number: CF%03d-YYYY
    $year = date('Y');
    $prefix = "CF%{$year}";

    $stmt = $db->prepare("
        SELECT case_number FROM cases
        WHERE case_number LIKE :prefix
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':prefix' => $prefix]);
    $last = $stmt->fetch();

    $nextNum = 1;
    if ($last && preg_match('/CF(\d+)-' . $year . '/', $last['case_number'], $m)) {
        $nextNum = (int)$m[1] + 1;
    }
    $caseNumber = sprintf('CF%03d-%s', $nextNum, $year);

    // Generate FIR number: FIR-HQ01-YYYY-%05d
    $firYear = date('Y');
    $firPrefix = "FIR-HQ01-{$firYear}-%";

    $firSeqStmt = $db->prepare("
        SELECT fir_number FROM cases
        WHERE fir_number LIKE :prefix
        ORDER BY id DESC LIMIT 1
    ");
    $firSeqStmt->execute([':prefix' => $firPrefix]);
    $lastFir = $firSeqStmt->fetch();

    $firNextNum = 1;
    if ($lastFir && preg_match('/FIR-HQ01-\d+-(\d+)/', $lastFir['fir_number'], $fm)) {
        $firNextNum = (int)$fm[1] + 1;
    }
    $firNumber = sprintf('FIR-HQ01-%s-%05d', $firYear, $firNextNum);

    $insert = $db->prepare("
        INSERT INTO cases (
            citizen_id, case_number, title, description, status, priority,
            fir_number, complainant_name, complainant_nid, complainant_phone,
            complainant_address, incident_date, incident_time, incident_location,
            incident_description, sections_applied, witness_details,
            officer_id, station_code
        ) VALUES (
            :citizen_id, :case_number, :title, :description, 'open', :priority,
            :fir_number, :complainant_name, :complainant_nid, :complainant_phone,
            :complainant_address, :incident_date, :incident_time, :incident_location,
            :incident_description, :sections_applied, :witness_details,
            :officer_id, :station_code
        )
    ");

    $insert->execute([
        ':citizen_id'           => $citizenId,
        ':case_number'          => $caseNumber,
        ':title'                => $title,
        ':description'          => $description,
        ':priority'             => $priority,
        ':fir_number'           => $firNumber,
        ':complainant_name'     => $citizen['full_name'],
        ':complainant_nid'      => $citizen['national_id'],
        ':complainant_phone'    => $citizen['phone'],
        ':complainant_address'  => $complainantAddress,
        ':incident_date'        => date('Y-m-d'),
        ':incident_time'        => null,
        ':incident_location'    => $title,
        ':incident_description' => $title . "\n\n" . $description,
        ':sections_applied'     => null,
        ':witness_details'      => null,
        ':officer_id'           => null,
        ':station_code'         => 'HQ01',
    ]);

    $newId = (int)$db->lastInsertId();

    json_exit(true, 'Case filed successfully.', [
        'case_id'     => $newId,
        'case_number' => $caseNumber,
        'redirect'    => 'case-details.php?id=' . $newId,
    ]);

} catch (PDOException $e) {
    error_log('[CaseFlowX] Submit case DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Please try again later.');
}
