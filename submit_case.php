
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

    // Generate case number: CFXXX-YYYY
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

    $insert = $db->prepare("
        INSERT INTO cases (citizen_id, case_number, title, description, status, priority)
        VALUES (:citizen_id, :case_number, :title, :description, 'open', :priority)
    ");

    $insert->execute([
        ':citizen_id'  => $citizenId,
        ':case_number' => $caseNumber,
        ':title'       => $title,
        ':description' => $description,
        ':priority'    => $priority,
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
