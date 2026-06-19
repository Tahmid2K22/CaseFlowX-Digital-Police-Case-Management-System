<?php
/**
 * api/fir.php — Handles FIR creation and draft saving.
 */

require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$officer = require_officer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? 'create';
$errors = [];

// Basic validation (shared)
$complainant_name = trim($_POST['complainant_name'] ?? '');
$complainant_nid = trim($_POST['complainant_nid'] ?? '');
$incident_date = $_POST['incident_date'] ?? '';
$incident_location = trim($_POST['incident_location'] ?? '');
$incident_description = trim($_POST['incident_description'] ?? '');

if (empty($complainant_name)) $errors['complainant_name'] = 'Complainant name is required.';
if (empty($complainant_nid)) $errors['complainant_nid'] = 'NID is required.';
if (empty($incident_date)) $errors['incident_date'] = 'Incident date is required.';
if (empty($incident_location)) $errors['incident_location'] = 'Incident location is required.';
if (empty($incident_description)) $errors['incident_description'] = 'Description is required.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

try {
    $db = get_db();
    
    // Generate FIR Number: FIR-STATION-YEAR-SEQUENCE
    $station = $officer['station_code'];
    $year = date('Y');
    $prefix = "FIR-{$station}-{$year}-%";
    
    $stmt = $db->prepare("SELECT fir_number FROM fir_records WHERE fir_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix]);
    $last = $stmt->fetch();
    
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', $last['fir_number'], $m)) {
        $seq = (int)$m[1] + 1;
    }
    $fir_number = sprintf("FIR-%s-%s-%05d", $station, $year, $seq);
    
    $status = ($action === 'draft') ? 'Draft' : 'Submitted';
    
    $stmt = $db->prepare("
        INSERT INTO fir_records (
            fir_number, complainant_name, complainant_nid, complainant_phone, 
            complainant_address, incident_date, incident_time, incident_location, 
            incident_description, sections_applied, witness_details, 
            officer_id, station_code, status, priority, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    $stmt->execute([
        $fir_number,
        $complainant_name,
        $complainant_nid,
        $_POST['complainant_phone'] ?? null,
        $_POST['complainant_address'] ?? '',
        $incident_date,
        $_POST['incident_time'] ?? null,
        $incident_location,
        $incident_description,
        $_POST['sections_applied'] ?? null,
        $_POST['witness_details'] ?? null,
        $officer['id'],
        $station,
        $status,
        $_POST['priority'] ?? 'medium',
        $officer['id']
    ]);
    
    $fir_id = $db->lastInsertId();
    
    // Also insert into cases table so it shows up on the dashboard
    $stmtCase = $db->prepare("
        INSERT INTO cases (
            case_number, title, description, status, priority,
            fir_number, complainant_name, complainant_nid, complainant_phone,
            complainant_address, incident_date, incident_time, incident_location,
            incident_description, sections_applied, witness_details,
            officer_id, station_code, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
    ");
    
    // Use incident_location as title if not provided
    $title = "FIR: " . $incident_location;
    
    $stmtCase->execute([
        $fir_number, // using fir_number as case_number for consistency in officer-filed FIRs
        $title,
        $incident_description,
        $status,
        $_POST['priority'] ?? 'medium',
        $fir_number,
        $complainant_name,
        $complainant_nid,
        $_POST['complainant_phone'] ?? null,
        $_POST['complainant_address'] ?? '',
        $incident_date,
        $_POST['incident_time'] ?? null,
        $incident_location,
        $incident_description,
        $_POST['sections_applied'] ?? null,
        $_POST['witness_details'] ?? null,
        $officer['id'],
        $station
    ]);
    
    $case_id = $db->lastInsertId();
    
    // Handle evidence IDs
    $evidence_ids = $_POST['evidence_ids'] ?? [];
    foreach ($evidence_ids as $fileName) {
        $filePath = 'uploads/' . $fileName;
        $fullPath = __DIR__ . '/../' . $filePath;
        if (file_exists($fullPath)) {
            $fsize = filesize($fullPath);
            $ftype = mime_content_type($fullPath);
            
            $stmtEv = $db->prepare("
                INSERT INTO fir_evidence (case_id, file_name, file_path, file_type, file_size, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtEv->execute([$case_id, basename($fileName), $filePath, $ftype, $fsize, $officer['id']]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => ($action === 'draft' ? 'Draft saved' : 'FIR submitted'),
        'fir_number' => $fir_number
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
