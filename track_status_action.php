<?php
/**
 * track_status_action.php — CaseFlowX
 * Backend endpoint to retrieve complaint status by reference number.
 * Validates format, fetches details from SQLite, and applies privacy-based authorization filters.
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

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(false, 'Method not allowed.');
}

// Collect input
$caseNumber = trim($_POST['case_number'] ?? '');

// Validation
if ($caseNumber === '') {
    json_exit(false, 'Reference number is required.', ['errors' => ['case_number' => 'Reference number is required.']]);
}

// Check format: e.g. CF001-2026
if (!preg_match('/^CF\d{3}-\d{4}$/i', $caseNumber)) {
    json_exit(false, 'Invalid reference number format. Must be like CF001-2026.', [
        'errors' => ['case_number' => 'Please enter a valid format (e.g. CF001-2026).']
    ]);
}

try {
    $db = get_db();

    // Query case details
    $stmt = $db->prepare('SELECT * FROM cases WHERE UPPER(case_number) = UPPER(:case_number) LIMIT 1');
    $stmt->execute([':case_number' => $caseNumber]);
    $case = $stmt->fetch();

    if (!$case) {
        json_exit(false, 'Reference number not found. Please verify the number and try again.', [
            'errors' => ['case_number' => 'Reference number not found.']
        ]);
    }

    // Determine ownership
    $isOwner = false;
    if (!empty($_SESSION['logged_in']) && !empty($_SESSION['citizen_id'])) {
        if ((int)$case['citizen_id'] === (int)$_SESSION['citizen_id']) {
            $isOwner = true;
        }
    }

    // Return fields based on authorization status
    if ($isOwner) {
        // Authenticated owner gets full details
        $data = [
            'id' => (int)$case['id'],
            'case_number' => $case['case_number'],
            'title' => $case['title'],
            'description' => $case['description'],
            'status' => $case['status'],
            'priority' => $case['priority'],
            'created_at' => $case['created_at'],
            'is_owner' => true
        ];
    } else {
        // Guest or other citizens get limited public details (masking/excluding private data)
        $data = [
            'case_number' => $case['case_number'],
            'title' => $case['title'],
            'status' => $case['status'],
            'priority' => $case['priority'],
            'created_at' => $case['created_at'],
            'is_owner' => false
        ];
    }

    json_exit(true, 'Complaint status retrieved successfully.', ['case' => $data]);

} catch (PDOException $e) {
    error_log('[CaseFlowX] Track status DB error: ' . $e->getMessage());
    json_exit(false, 'A server error occurred. Please try again later.');
}
