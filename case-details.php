<?php
/**
 * case-details.php — CaseFlowX Case & FIR Detailed View
 * Displays complete complaint details, incident timeline, evidence, and actions.
 */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in'])) {
    header('Location: officer-login.php');
    exit;
}

$officer = null;
$citizen = null;
$db = get_db();

if (!empty($_SESSION['officer_id'])) {
    $officer = require_officer();
} elseif (!empty($_SESSION['citizen_id'])) {
    $citizen = require_citizen();
} else {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

$caseId = (int)($_GET['id'] ?? 0);
if (!$caseId) {
    if ($officer) {
        header('Location: officer-dashboard.php');
    } else {
        exit('Invalid case ID');
    }
    exit;
}

try {
    // Fetch case with assigned officer name and citizen complainant name
    $stmt = $db->prepare("
        SELECT c.*, o.full_name as officer_name, ct.full_name as citizen_name
        FROM cases c
        LEFT JOIN officers o ON c.officer_id = o.id
        LEFT JOIN citizens ct ON c.citizen_id = ct.id
        WHERE c.id = ?
    ");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        die("Case not found.");
    }

    // If logged in as a citizen, verify they own this case
    if ($citizen && (int)$case['citizen_id'] !== (int)$citizen['id']) {
        die("Unauthorized access to this case.");
    }

    // Fetch evidence files
    $evStmt = $db->prepare("SELECT * FROM fir_evidence WHERE case_id = ?");
    $evStmt->execute([$caseId]);
    $evidenceList = $evStmt->fetchAll();

    // Fetch physical evidence list
    $peStmt = $db->prepare("SELECT * FROM physical_evidence WHERE case_id = ? ORDER BY id DESC");
    $peStmt->execute([$caseId]);
    $physicalEvidenceList = $peStmt->fetchAll();

    // Fetch custody history for each physical evidence item
    $custodyListByEvidence = [];
    if (count($physicalEvidenceList) > 0) {
        $cocStmt = $db->prepare("SELECT * FROM evidence_chain_of_custody WHERE evidence_id = ? ORDER BY custody_date ASC, id ASC");
        foreach ($physicalEvidenceList as $pe) {
            $cocStmt->execute([$pe['id']]);
            $custodyListByEvidence[$pe['id']] = $cocStmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$isAssignedToMe = $officer && ((int)$case['officer_id'] === (int)$officer['id']);
$isUnassigned = $officer && ($case['officer_id'] === null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Case Details #<?= htmlspecialchars($case['case_number']) ?> — CaseFlowX</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          navy:   '#1B2A4A',
          accent: '#1D9E75',
          'accent-dark': '#0F6E56',
        }
      }
    }
  }
</script>
</head>
<body class="min-h-screen bg-[#F4F6F9] pb-12">

<!-- Premium Header -->
<header class="bg-navy text-white shadow-md">
  <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <?php if ($officer): ?>
        <a href="officer-dashboard.php" class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-white text-xl shadow hover:bg-accent-dark transition">
          <i class="ti ti-arrow-left"></i>
        </a>
      <?php else: ?>
        <a href="index.php" class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-white text-xl shadow hover:bg-accent-dark transition">
          <i class="ti ti-home"></i>
        </a>
      <?php endif; ?>
      <div>
        <h1 class="text-lg font-bold leading-none">Case Details</h1>
        <p class="text-[11px] text-white/50 mt-0.5"><?= htmlspecialchars($case['case_number']) ?> <?php if(!empty($case['fir_number'])) echo "· " . htmlspecialchars($case['fir_number']); ?></p>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <?php if ($officer): ?>
        <a href="officer-dashboard.php" class="text-white/70 hover:text-white transition text-xs font-semibold flex items-center gap-1">
          <i class="ti ti-layout-dashboard"></i> Back to Dashboard
        </a>
      <?php else: ?>
        <a href="index.php" class="text-white/70 hover:text-white transition text-xs font-semibold flex items-center gap-1">
          <i class="ti ti-home"></i> Back to Home
        </a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-6">

  <!-- Alert Banner for Status Actions -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-navy/5 flex items-center justify-center text-navy text-2xl flex-shrink-0">
        <i class="ti ti-info-circle"></i>
      </div>
      <div>
        <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider">Current Case Status</div>
        <div class="flex items-center gap-2 mt-0.5">
          <?= statusBadge($case['status']) ?>
          <?= priorityBadge($case['priority']) ?>
        </div>
      </div>
    </div>

    <!-- Quick Action buttons -->
    <div class="flex items-center gap-3">
      <?php if ($isUnassigned && ($case['status'] === 'Submitted' || $case['status'] === 'open' || $case['status'] === 'Open')): ?>
        <button onclick="acceptCase(<?= (int)$case['id'] ?>, this)" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
          <i class="ti ti-check"></i> Accept Case
        </button>
        <button onclick="rejectCase(<?= (int)$case['id'] ?>, this)" class="bg-red-50 text-red-600 hover:bg-red-100 px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition">
          <i class="ti ti-x"></i> Reject Case
        </button>
      <?php elseif ($isAssignedToMe): ?>
        <!-- Status Update Dropdown -->
        <div class="relative inline-block text-left">
          <select onchange="updateCaseStatus(<?= (int)$case['id'] ?>, this.value)" class="bg-white border border-gray-200 text-gray-700 px-4 py-2 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent appearance-none pr-8">
            <option value="">-- Update Status --</option>
            <option value="Open" <?= $case['status'] === 'Open' || $case['status'] === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="Pending" <?= $case['status'] === 'Pending' || $case['status'] === 'in_progress' ? 'selected' : '' ?>>Pending</option>
            <option value="Closed" <?= $case['status'] === 'Closed' || $case['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
          </select>
          <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
            <i class="ti ti-chevron-down text-sm"></i>
          </span>
        </div>

        <button onclick="openAssignModal(<?= (int)$case['id'] ?>, '<?= htmlspecialchars($case['investigating_officer'] ?? '') ?>')" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
          <i class="ti ti-user-shield"></i> Assign / Update Investigator
        </button>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    <!-- Left / Center 2 Columns: Main details -->
    <div class="md:col-span-2 space-y-6">

      <!-- Case Overview -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-file-description text-accent"></i> Complaint & Case Overview
        </h2>
        <div class="space-y-4">
          <div>
            <span class="text-xs font-semibold text-gray-400 uppercase">Title</span>
            <p class="text-gray-800 font-semibold text-base mt-0.5"><?= htmlspecialchars($case['title']) ?></p>
          </div>
          <div>
            <span class="text-xs font-semibold text-gray-400 uppercase">Details & Complaint Statement</span>
            <p class="text-gray-600 text-sm mt-1 whitespace-pre-line leading-relaxed"><?= htmlspecialchars($case['description']) ?></p>
          </div>
        </div>
      </div>

      <!-- Complainant Section -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-user-circle text-accent"></i> Complainant Information
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Full Name</span>
            <span class="text-gray-800 font-semibold"><?= htmlspecialchars($case['complainant_name'] ?? $case['citizen_name'] ?? '—') ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">National ID (NID)</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars($case['complainant_nid'] ?? '—') ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Phone Number</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars($case['complainant_phone'] ?? '—') ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Full Address</span>
            <span class="text-gray-800"><?= htmlspecialchars($case['complainant_address'] ?? '—') ?></span>
          </div>
        </div>
      </div>

      <!-- Incident details -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-map-pin text-accent"></i> Incident Information
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm mb-4">
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Incident Date</span>
            <span class="text-gray-800 font-medium"><?= !empty($case['incident_date']) ? date('F d, Y', strtotime($case['incident_date'])) : '—' ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Incident Time</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars($case['incident_time'] ?? '—') ?></span>
          </div>
          <div class="sm:col-span-2">
            <span class="text-xs text-gray-400 font-semibold block">Incident Location</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars($case['incident_location'] ?? '—') ?></span>
          </div>
          <?php if(!empty($case['sections_applied'])): ?>
          <div class="sm:col-span-2">
            <span class="text-xs text-gray-400 font-semibold block">Sections Applied</span>
            <span class="text-red-700 bg-red-50 border border-red-100 px-2.5 py-1 rounded-md text-xs font-semibold inline-block mt-1"><?= htmlspecialchars($case['sections_applied']) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <?php if(!empty($case['witness_details'])): ?>
          <div class="border-t border-gray-100 pt-3 text-sm">
            <span class="text-xs text-gray-400 font-semibold block">Witness Details</span>
            <p class="text-gray-700 mt-1 whitespace-pre-line"><?= htmlspecialchars($case['witness_details']) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Evidence / Attachments -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-paperclip text-accent"></i> Case Evidence & Attachments
        </h2>
        <?php if (count($evidenceList) > 0): ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <?php foreach ($evidenceList as $ev): 
              $isImg = preg_match('/^image\//', $ev['file_type']);
              $isPdf = ($ev['file_type'] === 'application/pdf');
              $isMp4 = ($ev['file_type'] === 'video/mp4');
              
              $icon = 'ti-file text-gray-500';
              $bg = 'bg-gray-100';
              if ($isImg) { $icon = 'ti-photo text-green-600'; $bg = 'bg-green-50'; }
              elseif ($isPdf) { $icon = 'ti-file-text text-red-500'; $bg = 'bg-red-50'; }
              elseif ($isMp4) { $icon = 'ti-video text-purple-600'; $bg = 'bg-purple-50'; }
            ?>
              <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50 transition">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-10 h-10 rounded-lg <?= $bg ?> flex items-center justify-center flex-shrink-0">
                    <i class="ti <?= $icon ?> text-lg"></i>
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($ev['file_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= number_format($ev['file_size'] / 1024, 1) ?> KB</p>
                  </div>
                </div>
                <a href="<?= htmlspecialchars($ev['file_path']) ?>" target="_blank" class="w-8 h-8 rounded-full bg-navy/5 text-navy hover:bg-navy hover:text-white flex items-center justify-center transition">
                  <i class="ti ti-download text-sm"></i>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="text-gray-400 text-sm italic mb-4">No evidence files uploaded for this complaint.</p>
        <?php endif; ?>

        <!-- Add Attachment Form for Handling Officer -->
        <?php if ($isAssignedToMe): ?>
          <div class="mt-6 pt-6 border-t border-gray-100">
            <h3 class="text-navy font-semibold text-sm mb-3">Attach Photos / Documents</h3>
            <div id="detail-upload-zone" class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-accent hover:bg-accent/5 transition duration-200">
              <input type="file" id="detail-file-input" accept=".pdf,.jpg,.jpeg,.png,.mp4" class="hidden" onchange="uploadDetailEvidence(<?= (int)$case['id'] ?>, event)">
              <div class="w-10 h-10 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-2">
                <i class="ti ti-cloud-upload text-accent text-lg"></i>
              </div>
              <p class="text-xs font-semibold text-gray-700 mb-0.5">Click to upload photo or document</p>
              <p class="text-[10px] text-gray-400">PDF, JPG, PNG, or MP4 — Max 10MB</p>
            </div>
            <div id="detail-upload-loading" class="hidden mt-3 text-center text-xs text-gray-500">
              <i class="ti ti-loader-2 animate-spin text-accent text-base"></i> Uploading attachment...
            </div>
            <div id="detail-upload-error" class="hidden mt-2 p-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg text-center"></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Physical Evidence & Chain of Custody -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex justify-between items-center border-b border-gray-100 pb-3 mb-4">
          <h2 class="text-navy font-bold text-lg flex items-center gap-2">
            <i class="ti ti-archive text-accent"></i> Physical Evidence & Chain of Custody
          </h2>
          <?php if ($isAssignedToMe): ?>
            <button onclick="openPhysicalEvidenceModal()" class="bg-accent/10 hover:bg-accent/20 text-accent px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1 transition">
              <i class="ti ti-plus"></i> Log Evidence
            </button>
          <?php endif; ?>
        </div>

        <?php if (count($physicalEvidenceList) > 0): ?>
          <div class="space-y-6">
            <?php foreach ($physicalEvidenceList as $pe): ?>
              <div class="border border-gray-100 rounded-2xl p-5 bg-[#F8FAFC]">
                <div class="flex flex-wrap justify-between items-start gap-4 mb-4 border-b border-gray-100/50 pb-3">
                  <div>
                    <h3 class="text-navy font-bold text-base"><?= htmlspecialchars($pe['item_name']) ?></h3>
                    <?php if (!empty($pe['description'])): ?>
                      <p class="text-gray-500 text-xs mt-0.5"><?= htmlspecialchars($pe['description']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($pe['serial_number'])): ?>
                      <p class="text-gray-400 text-[11px] mt-1 font-mono">Serial/ID: <?= htmlspecialchars($pe['serial_number']) ?></p>
                    <?php endif; ?>
                  </div>
                  <div class="flex flex-col items-end gap-1.5">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold border border-accent/20 bg-accent/5 text-accent-dark">
                      <?= htmlspecialchars($pe['status']) ?>
                    </span>
                    <span class="text-[11px] text-gray-400">Custodian: <strong class="text-gray-700"><?= htmlspecialchars($pe['current_custodian']) ?></strong></span>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-600 mb-4 bg-white p-3 rounded-xl border border-gray-100">
                  <div>
                    <span class="text-gray-400 block font-semibold uppercase text-[9px] tracking-wider">Recovered Location</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($pe['recovered_location']) ?></span>
                  </div>
                  <div>
                    <span class="text-gray-400 block font-semibold uppercase text-[9px] tracking-wider">Recovered Date</span>
                    <span class="font-medium text-gray-800"><?= !empty($pe['recovered_at']) ? date('M d, Y H:i', strtotime($pe['recovered_at'])) : '—' ?></span>
                  </div>
                  <div>
                    <span class="text-gray-400 block font-semibold uppercase text-[9px] tracking-wider">Recovered By</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($pe['recovered_by']) ?></span>
                  </div>
                </div>

                <!-- Chain of Custody Timeline -->
                <div>
                  <h4 class="text-xs font-bold text-navy uppercase tracking-wider mb-3 flex items-center gap-1">
                    <i class="ti ti-git-commit text-accent"></i> Chain of Custody Timeline
                  </h4>
                  <div class="relative pl-6 border-l border-dashed border-gray-200 space-y-4 ml-2">
                    <?php 
                    $timeline = $custodyListByEvidence[$pe['id']] ?? [];
                    foreach ($timeline as $idx => $step):
                      $stepIcon = 'ti-circle-dot';
                      $iconColor = 'text-accent bg-accent/10 border-accent/20';
                      if ($step['action_type'] === 'Recovery') {
                          $stepIcon = 'ti-package';
                          $iconColor = 'text-blue-600 bg-blue-50 border-blue-100';
                      } elseif ($step['action_type'] === 'Transfer') {
                          $stepIcon = 'ti-arrows-left-right';
                          $iconColor = 'text-orange-600 bg-orange-50 border-orange-100';
                      } elseif ($step['action_type'] === 'Release') {
                          $stepIcon = 'ti-circle-check';
                          $iconColor = 'text-green-600 bg-green-50 border-green-100';
                      } elseif ($step['action_type'] === 'Destruction') {
                          $stepIcon = 'ti-trash';
                          $iconColor = 'text-red-600 bg-red-50 border-red-100';
                      }
                    ?>
                      <div class="relative">
                        <!-- Bullet Icon -->
                        <span class="absolute -left-[35px] top-0 w-6 h-6 rounded-full flex items-center justify-center border <?= $iconColor ?> text-xs font-semibold shadow-sm">
                          <i class="ti <?= $stepIcon ?>"></i>
                        </span>
                        
                        <div>
                          <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-bold text-gray-800"><?= htmlspecialchars($step['action_type']) ?> — Custodian: <?= htmlspecialchars($step['officer_name']) ?></span>
                            <span class="text-[10px] text-gray-400 font-medium"><?= !empty($step['custody_date']) ? date('M d, Y H:i', strtotime($step['custody_date'])) : '—' ?></span>
                          </div>
                          <p class="text-[11px] text-gray-500 mt-0.5">Location: <span class="font-medium text-gray-700"><?= htmlspecialchars($step['location']) ?></span></p>
                          <?php if (!empty($step['notes'])): ?>
                            <p class="text-[11px] text-gray-400 italic mt-1 bg-white px-2 py-1 rounded border border-gray-100"><?= htmlspecialchars($step['notes']) ?></p>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <?php if ($isAssignedToMe && $pe['status'] !== 'Released' && $pe['status'] !== 'Destroyed'): ?>
                  <div class="mt-4 pt-4 border-t border-gray-100 flex justify-end">
                    <button onclick="openCustodyTransferModal(<?= $pe['id'] ?>, '<?= htmlspecialchars($pe['item_name'], ENT_QUOTES) ?>')" class="bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                      <i class="ti ti-arrows-left-right text-gray-500"></i> Update Custody / Transfer
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-6 border border-dashed border-gray-200 rounded-2xl bg-gray-50/50">
            <i class="ti ti-archive text-gray-300 text-3xl mb-1.5 block"></i>
            <p class="text-gray-400 text-sm italic">No physical evidence logged for this case.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Right 1 Column: Assignment & Info -->
    <div class="space-y-6">

      <!-- Case Assignment details -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-navy font-bold text-md border-b border-gray-100 pb-3 mb-4 flex items-center gap-1.5">
          <i class="ti ti-user-shield text-accent"></i> Investigation Info
        </h3>
        <div class="space-y-4 text-sm">
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Handling Officer</span>
            <?php if ($case['officer_id'] !== null): ?>
              <div class="font-semibold text-gray-800 mt-0.5">
                <?= htmlspecialchars($case['officer_name'] ?? 'Officer #' . $case['officer_id']) ?>
                <?php if ($isAssignedToMe): ?>
                  <span class="text-xs font-semibold text-accent">(Me)</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="text-orange-600 font-semibold block mt-0.5">Awaiting Acceptance</span>
            <?php endif; ?>
          </div>

          <div>
            <span class="text-xs text-gray-400 font-semibold block">Investigating Officer</span>
            <?php if (!empty($case['investigating_officer'])): ?>
              <div class="font-semibold text-gray-800 flex items-center gap-1 mt-0.5">
                <i class="ti ti-user-shield text-accent"></i>
                <?= htmlspecialchars($case['investigating_officer']) ?>
              </div>
            <?php else: ?>
              <span class="text-gray-400 italic block mt-0.5">Not Assigned</span>
            <?php endif; ?>
          </div>

          <div>
            <span class="text-xs text-gray-400 font-semibold block">Filing Station Code</span>
            <span class="font-semibold text-gray-800 mt-0.5 block"><?= htmlspecialchars($case['station_code'] ?? 'HQ01') ?></span>
          </div>

          <?php if (!empty($case['modified_at'])): ?>
            <div class="border-t border-gray-100 pt-3">
              <span class="text-xs text-gray-400 block">Last Modified</span>
              <span class="text-gray-500 font-medium text-xs block mt-0.5"><?= date('M d, Y H:i', strtotime($case['modified_at'])) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>

</main>

<!-- Assign Investigator Modal -->
<div id="assign-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl animate-in fade-in duration-200">
    <div class="bg-navy px-6 py-4 flex items-center justify-between">
      <h3 class="text-white font-semibold text-lg flex items-center gap-2">
        <i class="ti ti-user-shield"></i> Assign Investigator
      </h3>
      <button onclick="closeAssignModal()" class="text-white/75 hover:text-white transition">
        <i class="ti ti-x text-lg"></i>
      </button>
    </div>
    <form id="assign-form" class="p-6 space-y-4">
      <input type="hidden" id="modal-case-id" name="case_id" value="<?= $caseId ?>">
      <input type="hidden" id="modal-action" name="action" value="assign_investigator">
      
      <div>
        <label for="investigating_officer_select" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Select Investigator <span class="text-red-400">*</span>
        </label>
        <select id="investigating_officer_select" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white" onchange="syncInvestigatorName()">
          <option value="">-- Choose Predefined Investigator --</option>
          <option value="Sub-Inspector Amit Hasan">Sub-Inspector Amit Hasan (Badge: SI-902)</option>
          <option value="Sub-Inspector Sabrina Khan">Sub-Inspector Sabrina Khan (Badge: SI-411)</option>
          <option value="Inspector Rafiqul Islam">Inspector Rafiqul Islam (Badge: INS-203)</option>
          <option value="Sub-Inspector Joynal Abedin">Sub-Inspector Joynal Abedin (Badge: SI-108)</option>
          <option value="custom">-- Type Custom Name --</option>
        </select>
      </div>

      <div id="custom-investigator-container" class="hidden">
        <label for="investigating_officer" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Investigating Officer Name <span class="text-red-400">*</span>
        </label>
        <input type="text" id="investigating_officer" name="investigating_officer" placeholder="Enter investigator name" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeAssignModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
          Cancel
        </button>
        <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
          Confirm & Assign
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Log Physical Evidence Modal -->
<div id="physical-evidence-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-xl animate-in fade-in duration-200">
    <div class="bg-navy px-6 py-4 flex items-center justify-between">
      <h3 class="text-white font-semibold text-lg flex items-center gap-2">
        <i class="ti ti-archive"></i> Log Physical Evidence
      </h3>
      <button onclick="closePhysicalEvidenceModal()" class="text-white/75 hover:text-white transition">
        <i class="ti ti-x text-lg"></i>
      </button>
    </div>
    <form id="physical-evidence-form" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      <input type="hidden" name="action" value="log_physical_evidence">
      
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2">
          <label for="pe_item_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Item Name/Type <span class="text-red-400">*</span>
          </label>
          <input type="text" id="pe_item_name" name="item_name" required placeholder="e.g. 9mm Pistol, Bloody Shirt, Wallet" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
        
        <div class="sm:col-span-2">
          <label for="pe_description" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Item Description
          </label>
          <textarea id="pe_description" name="description" rows="2" placeholder="Provide distinct characteristics, color, condition, labels, etc." class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent"></textarea>
        </div>
        
        <div>
          <label for="pe_serial_number" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Serial Number / Brand ID
          </label>
          <input type="text" id="pe_serial_number" name="serial_number" placeholder="e.g. Serial #, Model, Brand" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
        
        <div>
          <label for="pe_status" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Initial Status <span class="text-red-400">*</span>
          </label>
          <select id="pe_status" name="status" required class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white">
            <option value="Secured">Secured (Evidence Room)</option>
            <option value="In Transit">In Transit</option>
            <option value="Sent to Lab">Sent to Lab</option>
          </select>
        </div>

        <div>
          <label for="pe_recovered_at" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Date & Time Recovered <span class="text-red-400">*</span>
          </label>
          <input type="datetime-local" id="pe_recovered_at" name="recovered_at" required value="<?= date('Y-m-d\TH:i') ?>" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div>
          <label for="pe_recovered_by" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Recovered By <span class="text-red-400">*</span>
          </label>
          <input type="text" id="pe_recovered_by" name="recovered_by" required value="<?= htmlspecialchars($officer['full_name'] ?? '') ?>" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div class="sm:col-span-2">
          <label for="pe_recovered_location" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Location Found / Recovered <span class="text-red-400">*</span>
          </label>
          <input type="text" id="pe_recovered_location" name="recovered_location" required placeholder="e.g. Under driver seat of vehicle, 43 Elm St" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div>
          <label for="pe_current_custodian" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Initial Custodian <span class="text-red-400">*</span>
          </label>
          <input type="text" id="pe_current_custodian" name="current_custodian" required placeholder="e.g. Officer Smith, Locker room A" value="<?= htmlspecialchars($officer['full_name'] ?? '') ?>" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div class="sm:col-span-2">
          <label for="pe_initial_notes" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Initial Custody Remarks/Notes
          </label>
          <input type="text" id="pe_initial_notes" name="initial_notes" placeholder="e.g. Bagged, tagged, sealed with evidence tape" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closePhysicalEvidenceModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
          Cancel
        </button>
        <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
          Log Evidence
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Update Custody / Transfer Modal -->
<div id="custody-transfer-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl animate-in fade-in duration-200">
    <div class="bg-navy px-6 py-4 flex items-center justify-between">
      <h3 class="text-white font-semibold text-lg flex items-center gap-2">
        <i class="ti ti-arrows-left-right"></i> Update Custody / Transfer
      </h3>
      <button onclick="closeCustodyTransferModal()" class="text-white/75 hover:text-white transition">
        <i class="ti ti-x text-lg"></i>
      </button>
    </div>
    <form id="custody-transfer-form" class="p-6 space-y-4">
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      <input type="hidden" name="action" value="add_custody_transfer">
      <input type="hidden" id="transfer_evidence_id" name="evidence_id" value="">

      <div>
        <span class="block text-xs font-semibold text-gray-400 uppercase">Evidence Item</span>
        <p id="transfer_evidence_name" class="text-sm font-semibold text-navy mt-0.5">Item Name</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="coc_action_type" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Action Type <span class="text-red-400">*</span>
          </label>
          <select id="coc_action_type" name="action_type" required onchange="handleCocActionChange()" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white">
            <option value="Transfer">Transfer Custody</option>
            <option value="Lab Analysis">Send to Forensic Lab</option>
            <option value="Court Presentation">Present in Court</option>
            <option value="Release">Release to Owner</option>
            <option value="Destruction">Destruction</option>
          </select>
        </div>

        <div>
          <label for="coc_status" class="block text-xs font-semibold text-gray-600 mb-1.5">
            New Status (Optional)
          </label>
          <select id="coc_status" name="status" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white">
            <option value="">-- Keep Current Status --</option>
            <option value="Secured">Secured (Evidence Room)</option>
            <option value="In Transit">In Transit</option>
            <option value="Sent to Lab">Sent to Lab</option>
            <option value="In Court">In Court</option>
            <option value="Released">Released</option>
            <option value="Destroyed">Destroyed</option>
          </select>
        </div>

        <div class="sm:col-span-2" id="custodian_container">
          <label for="coc_officer_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
            New Custodian Name / Entity <span class="text-red-400">*</span>
          </label>
          <input type="text" id="coc_officer_name" name="officer_name" required placeholder="e.g. Lab Analyst Jane, Officer Smith" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div class="sm:col-span-2">
          <label for="coc_location" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Location <span class="text-red-400">*</span>
          </label>
          <input type="text" id="coc_location" name="location" required placeholder="e.g. Forensic Lab Rm 101, Storage Locker B" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div class="sm:col-span-2">
          <label for="coc_custody_date" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Date & Time of Action <span class="text-red-400">*</span>
          </label>
          <input type="datetime-local" id="coc_custody_date" name="custody_date" required value="<?= date('Y-m-d\TH:i') ?>" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>

        <div class="sm:col-span-2">
          <label for="coc_notes" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Remarks / Notes
          </label>
          <textarea id="coc_notes" name="notes" rows="2" placeholder="State reason for transfer, package integrity, seals, etc." class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent"></textarea>
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="closeCustodyTransferModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
          Cancel
        </button>
        <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
          Save Transfer
        </button>
      </div>
    </form>
  </div>
</div>

<?php
function statusBadge(string $status): string {
    $map = [
        'Submitted'     => ['bg-blue-50 text-blue-700 border-blue-200', 'ti-send', 'Submitted'],
        'open'          => ['bg-blue-50 text-blue-700 border-blue-200', 'ti-send', 'Submitted'],
        'Open'          => ['bg-blue-50 text-blue-700 border-blue-200', 'ti-send', 'Open'],
        'Closed'        => ['bg-red-50 text-red-700 border-red-200', 'ti-x', 'Closed'],
        'Pending'       => ['bg-orange-50 text-orange-700 border-orange-200', 'ti-clock', 'Pending'],
        'Under Review'  => ['bg-orange-50 text-orange-700 border-orange-200', 'ti-eye', 'Under Review'],
        'Registered'    => ['bg-green-50 text-green-700 border-green-200', 'ti-circle-check', 'Registered'],
        'Rejected'      => ['bg-red-50 text-red-700 border-red-200', 'ti-x', 'Rejected'],
        'in_progress'   => ['bg-orange-50 text-orange-700 border-orange-200', 'ti-clock', 'Pending'],
        'closed'        => ['bg-red-50 text-red-700 border-red-200', 'ti-x', 'Closed'],
    ];
    [$cls, $ico, $lbl] = $map[$status] ?? [$map['Submitted'][0], $map['Submitted'][1], $status];
    return "<span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border {$cls}\"><i class=\"ti {$ico}\"></i> {$lbl}</span>";
}
function priorityBadge(string $priority): string {
    $map = [
        'low'    => ['bg-gray-100 text-gray-600 border-gray-200', 'Low'],
        'medium' => ['bg-orange-50 text-orange-700 border-orange-200', 'Medium'],
        'high'   => ['bg-red-50 text-red-700 border-red-200', 'High'],
    ];
    [$cls, $lbl] = $map[$priority] ?? [$map['low'][0], ucfirst($priority)];
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold border {$cls}\">{$lbl}</span>";
}
?>

<script>
async function acceptCase(caseId, btn) {
  btn.disabled = true;
  const oldText = btn.innerHTML;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Accepting…';
  try {
    const fd = new FormData();
    fd.append('action', 'accept');
    fd.append('case_id', caseId);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Failed to accept case');
      btn.disabled = false;
      btn.innerHTML = oldText;
    }
  } catch (e) {
    alert('Network error');
    btn.disabled = false;
    btn.innerHTML = oldText;
  }
}

async function rejectCase(caseId, btn) {
  if (!confirm('Are you sure you want to reject this case?')) return;
  btn.disabled = true;
  const oldText = btn.innerHTML;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Rejecting…';
  try {
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('case_id', caseId);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Failed to reject case');
      btn.disabled = false;
      btn.innerHTML = oldText;
    }
  } catch (e) {
    alert('Network error');
    btn.disabled = false;
    btn.innerHTML = oldText;
  }
}

function openAssignModal(caseId, currentInvestigator = '') {
  const sel = document.getElementById('investigating_officer_select');
  const input = document.getElementById('investigating_officer');
  const customContainer = document.getElementById('custom-investigator-container');
  
  if (currentInvestigator) {
    let found = false;
    for (let i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value === currentInvestigator) {
        sel.selectedIndex = i;
        found = true;
        break;
      }
    }
    if (!found) {
      sel.value = 'custom';
      input.value = currentInvestigator;
      customContainer.classList.remove('hidden');
    } else {
      input.value = currentInvestigator;
      customContainer.classList.add('hidden');
    }
  } else {
    sel.selectedIndex = 0;
    input.value = '';
    customContainer.classList.add('hidden');
  }
  
  document.getElementById('assign-modal').classList.remove('hidden');
}

function closeAssignModal() {
  document.getElementById('assign-modal').classList.add('hidden');
}

function syncInvestigatorName() {
  const sel = document.getElementById('investigating_officer_select');
  const customContainer = document.getElementById('custom-investigator-container');
  const input = document.getElementById('investigating_officer');
  
  if (sel.value === 'custom') {
    customContainer.classList.remove('hidden');
    input.value = '';
    input.focus();
  } else {
    customContainer.classList.add('hidden');
    input.value = sel.value;
  }
}

document.getElementById('assign-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const investigator = document.getElementById('investigating_officer').value.trim();
  
  if (!investigator) {
    alert('Please select or enter an investigator name.');
    return;
  }
  
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  const oldText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Assigning…';
  
  try {
    const fd = new FormData(this);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeAssignModal();
      location.reload();
    } else {
      alert(data.message || 'Failed to assign investigator');
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldText;
    }
  } catch (err) {
    alert('Network error');
    submitBtn.disabled = false;
    submitBtn.innerHTML = oldText;
  }
});

async function updateCaseStatus(caseId, status) {
  if (!status) return;
  try {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('case_id', caseId);
    fd.append('status', status);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Failed to update status');
    }
  } catch (e) {
    alert('Network error');
  }
}

document.getElementById('detail-upload-zone')?.addEventListener('click', () => {
  document.getElementById('detail-file-input').click();
});

async function uploadDetailEvidence(caseId, event) {
  const file = event.target.files[0];
  if (!file) return;
  
  const loading = document.getElementById('detail-upload-loading');
  const errorDiv = document.getElementById('detail-upload-error');
  loading.classList.remove('hidden');
  errorDiv.classList.add('hidden');
  
  const fd = new FormData();
  fd.append('file', file);
  fd.append('case_id', caseId);
  
  try {
    const res = await fetch('api/attach_evidence.php', {
      method: 'POST',
      body: fd
    });
    const data = await res.json();
    if (data.success) {
      location.reload();
    } else {
      errorDiv.textContent = data.error || 'Failed to upload attachment';
      errorDiv.classList.remove('hidden');
      loading.classList.add('hidden');
    }
  } catch (e) {
    errorDiv.textContent = 'Network error during file upload';
    errorDiv.classList.remove('hidden');
    loading.classList.add('hidden');
}
  } finally {
    event.target.value = ''; // Reset file input
  }
}

function openPhysicalEvidenceModal() {
  document.getElementById('pe_recovered_at').value = new Date().toISOString().slice(0, 16);
  document.getElementById('physical-evidence-modal').classList.remove('hidden');
}

function closePhysicalEvidenceModal() {
  document.getElementById('physical-evidence-modal').classList.add('hidden');
  document.getElementById('physical-evidence-form').reset();
}

function openCustodyTransferModal(evidenceId, itemName) {
  document.getElementById('transfer_evidence_id').value = evidenceId;
  document.getElementById('transfer_evidence_name').textContent = itemName;
  document.getElementById('coc_custody_date').value = new Date().toISOString().slice(0, 16);
  handleCocActionChange(); // Sync inputs
  document.getElementById('custody-transfer-modal').classList.remove('hidden');
}

function closeCustodyTransferModal() {
  document.getElementById('custody-transfer-modal').classList.add('hidden');
  document.getElementById('custody-transfer-form').reset();
}

function handleCocActionChange() {
  const actionType = document.getElementById('coc_action_type').value;
  const custodianInput = document.getElementById('coc_officer_name');
  const custodianContainer = document.getElementById('custodian_container');
  
  if (actionType === 'Release') {
    custodianInput.value = 'Released to Owner';
    custodianContainer.classList.add('hidden');
  } else if (actionType === 'Destruction') {
    custodianInput.value = 'None (Destroyed)';
    custodianContainer.classList.add('hidden');
  } else {
    custodianInput.value = '';
    custodianContainer.classList.remove('hidden');
  }
}

document.getElementById('physical-evidence-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  const oldText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Saving…';
  
  try {
    const fd = new FormData(this);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closePhysicalEvidenceModal();
      location.reload();
    } else {
      alert(data.message || 'Failed to log physical evidence');
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldText;
    }
  } catch (err) {
    alert('Network error');
    submitBtn.disabled = false;
    submitBtn.innerHTML = oldText;
  }
});

document.getElementById('custody-transfer-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  const oldText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Saving…';
  
  try {
    const fd = new FormData(this);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      closeCustodyTransferModal();
      location.reload();
    } else {
      alert(data.message || 'Failed to update custody');
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldText;
    }
  } catch (err) {
    alert('Network error');
    submitBtn.disabled = false;
    submitBtn.innerHTML = oldText;
  }
});
</script>
</body>
</html>
