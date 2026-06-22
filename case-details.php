<?php
/**
 * case-details.php — CaseFlowX Case & FIR Detailed View
 * Displays complete complaint details, incident timeline, evidence, and actions.
 */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';

if (!is_logged_in() && empty($_SESSION['logged_in']) && empty($_SESSION['officer_id']) && empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}

$db = get_db();
$caseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$caseId) {
    header('Location: dashboard.php');
    exit;
}

$officer = null;
$citizen = null;

if (!empty($_SESSION['officer_id'])) {
    $officer = require_officer();
} elseif (($_SESSION['role'] ?? '') === 'Officer') {
    $officer = [
        'id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['username'] ?? 'Officer',
        'role' => 'Officer'
    ];
} elseif (!empty($_SESSION['citizen_id'])) {
    $citizen = require_citizen();
}

try {
    // Fetch case with assigned officer name and citizen complainant name
    $stmt = $db->prepare("
        SELECT c.*, 
               COALESCE(o.full_name, u_off.full_name) as officer_name,
               COALESCE(ct.full_name, u_cit.full_name) as citizen_name,
               COALESCE(ct.phone, u_cit.phone) as citizen_phone,
               COALESCE(ct.email, u_cit.email) as citizen_email,
               COALESCE(ct.national_id, u_cit.national_id) as citizen_nid,
               COALESCE(ct.address, u_cit.address) as citizen_address
        FROM cases c
        LEFT JOIN officers o ON c.officer_id = o.id
        LEFT JOIN citizens ct ON c.citizen_id = ct.id
        LEFT JOIN users u_cit ON c.citizen_id = u_cit.id
        LEFT JOIN users u_off ON c.officer_id = u_off.id
        WHERE c.id = ?
    ");
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();

    if (!$case) {
        die("Case not found.");
    }

    // Access control check
    $allowed = false;
    $role = $_SESSION['role'] ?? '';
    if (!empty($role)) {
        if ($role === 'Admin' || $role === 'Officer') {
            $allowed = true;
        } elseif ($role === 'Investigator') {
            if ($case['investigator_id'] == $_SESSION['user_id']) {
                $allowed = true;
            }
        } elseif ($role === 'Citizen') {
            if ($case['citizen_id'] == $_SESSION['user_id']) {
                $allowed = true;
            }
        }
    } elseif ($officer) {
        $allowed = true;
    } elseif ($citizen) {
        if ($case['citizen_id'] == $citizen['id']) {
            $allowed = true;
        }
    }

    if (!$allowed) {
        header('Location: unauthorized.php');
        exit;
    }

    // Fetch evidence files
    $evStmt = $db->prepare("SELECT * FROM fir_evidence WHERE case_id = ?");
    $evStmt->execute([$caseId]);
    $evidenceList = $evStmt->fetchAll();

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
<body class="bg-[#F4F6F9] min-h-screen flex flex-col justify-between">
  <div class="flex-grow">

<!-- Premium Header -->
<header class="bg-navy text-white shadow-md">
  <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <?php
        $backUrl = 'index.php';
        $sessionRole = $_SESSION['role'] ?? '';
        if ($officer) {
            $backUrl = ($sessionRole === 'Officer') ? 'fir_officer_dashboard.php' : 'officer-dashboard.php';
        } elseif ($sessionRole === 'Admin') {
            $backUrl = 'admin_firs.php';
        } elseif ($sessionRole === 'Investigator') {
            $backUrl = 'investigator_dashboard.php';
        } elseif ($sessionRole === 'Citizen' || !empty($_SESSION['citizen_id'])) {
            $backUrl = 'cases.php';
        }
      ?>
      <a href="<?= $backUrl ?>" class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-white text-xl shadow hover:bg-accent-dark transition">
        <i class="ti ti-arrow-left"></i>
      </a>
      <div>
        <h1 class="text-lg font-bold leading-none">Case Details</h1>
        <p class="text-[11px] text-white/50 mt-0.5"><?= htmlspecialchars($case['case_number']) ?> <?php if(!empty($case['fir_number'])) echo "· " . htmlspecialchars($case['fir_number']); ?></p>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <a href="<?= $backUrl ?>" class="text-white/70 hover:text-white transition text-xs font-semibold flex items-center gap-1">
        <i class="ti ti-arrow-back"></i> Back
      </a>
    </div>
  </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-6">

  <!-- Alert Banner for Status Actions -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-navy/5 flex items-center justify-center text-navy text-2xl flex-shrink-0">
        <i class="ti ti-file-certificate"></i>
      </div>
      <div>
        <div class="text-xs text-gray-400 font-semibold uppercase tracking-wider">FIR Case & Status Details</div>
        <div class="flex flex-wrap items-center gap-2 mt-0.5">
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold border bg-navy text-white border-navy/20">
            <i class="ti ti-file-text"></i> FIR: <?= htmlspecialchars($case['fir_number'] ?: 'Pending Registration') ?>
          </span>
          <?= statusBadge($case['status']) ?>
          <?= priorityBadge($case['priority']) ?>
        </div>
      </div>
    </div>

    <!-- Quick Action buttons -->
    <div class="flex items-center gap-3">
      <?php if ($isUnassigned && ($case['status'] === 'Submitted' || $case['status'] === 'open')): ?>
        <button onclick="acceptCase(<?= (int)$case['id'] ?>, this)" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
          <i class="ti ti-check"></i> Accept Case
        </button>
        <button onclick="rejectCase(<?= (int)$case['id'] ?>, this)" class="bg-red-50 text-red-600 hover:bg-red-100 px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition">
          <i class="ti ti-x"></i> Reject Case
        </button>
      <?php elseif ($isAssignedToMe): ?>
        <button onclick="openAssignModal(<?= (int)$case['id'] ?>, '<?= htmlspecialchars($case['investigating_officer'] ?? '') ?>')" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
          <i class="ti ti-user-shield"></i> Assign / Update Investigator
        </button>
      <?php endif; ?>

      <?php if ($sessionRole === 'Admin' && ($case['status'] === 'Submitted' || $case['status'] === 'Under Review' || $case['status'] === 'open')): ?>
        <?php
          $stmtFir = $db->prepare("SELECT id FROM fir_records WHERE fir_number = ? LIMIT 1");
          $stmtFir->execute([$case['fir_number']]);
          $firRecord = $stmtFir->fetch();
          $firId = $firRecord ? (int)$firRecord['id'] : 0;
        ?>
        <?php if ($firId > 0): ?>
          <button onclick="adminApproveFIR(<?= $firId ?>, '<?= htmlspecialchars($case['fir_number']) ?>', this)" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
            <i class="ti ti-check"></i> Approve FIR
          </button>
          <button onclick="adminOpenRejectModal(<?= $firId ?>, '<?= htmlspecialchars($case['fir_number']) ?>')" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-xl text-sm font-semibold flex items-center gap-1.5 transition shadow">
            <i class="ti ti-x"></i> Reject FIR
          </button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="max-w-4xl mx-auto space-y-6">

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
            <span class="text-gray-800 font-semibold"><?= htmlspecialchars(($case['complainant_name'] !== '' && $case['complainant_name'] !== null) ? $case['complainant_name'] : ($case['citizen_name'] ?: '—')) ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">National ID (NID)</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars(($case['complainant_nid'] !== '' && $case['complainant_nid'] !== null) ? $case['complainant_nid'] : ($case['citizen_nid'] ?: '—')) ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Phone Number</span>
            <span class="text-gray-800 font-medium"><?= htmlspecialchars(($case['complainant_phone'] !== '' && $case['complainant_phone'] !== null) ? $case['complainant_phone'] : ($case['citizen_phone'] ?: '—')) ?></span>
          </div>
          <div>
            <span class="text-xs text-gray-400 font-semibold block">Full Address</span>
            <span class="text-gray-800"><?= htmlspecialchars(($case['complainant_address'] !== '' && $case['complainant_address'] !== null) ? $case['complainant_address'] : ($case['citizen_address'] ?: '—')) ?></span>
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

      <!-- Investigation Info -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-user-shield text-accent"></i> Investigation Info
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
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
            <div class="sm:col-span-3 border-t border-gray-100 pt-3">
              <span class="text-xs text-gray-400 block">Last Modified</span>
              <span class="text-gray-500 font-medium text-xs block mt-0.5"><?= date('M d, Y H:i', strtotime($case['modified_at'])) ?></span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Evidence / Attachments -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-4 flex items-center gap-2">
          <i class="ti ti-paperclip text-accent"></i> Case Evidence & Attachments
        </h2>
        <div id="evidence-list-container">
          <?php if (count($evidenceList) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                      <p class="text-sm font-semibold text-gray-800 truncate" title="<?= htmlspecialchars($ev['file_name']) ?>"><?= htmlspecialchars($ev['file_name']) ?></p>
                      <p class="text-xs text-gray-400"><?= number_format($ev['file_size'] / 1024, 1) ?> KB</p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <a href="<?= htmlspecialchars($ev['file_path']) ?>" target="_blank" class="w-8 h-8 rounded-full bg-navy/5 text-navy hover:bg-navy hover:text-white flex items-center justify-center transition" title="Download">
                      <i class="ti ti-download text-sm"></i>
                    </a>
                    <?php if ($canUploadEvidence): ?>
                      <button onclick="deleteEvidence(<?= (int)$ev['id'] ?>, this)" class="w-8 h-8 rounded-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition" title="Delete">
                        <i class="ti ti-trash text-sm"></i>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-gray-400 text-sm italic">No evidence files uploaded for this complaint.</p>
          <?php endif; ?>
        </div>

        <?php
          $canUploadEvidence = false;
          if ($role === 'Admin' || $role === 'Officer') {
              $canUploadEvidence = true;
          } elseif ($role === 'Investigator' && (int)$case['investigator_id'] === (int)$_SESSION['user_id']) {
              $canUploadEvidence = true;
          } elseif (!empty($_SESSION['officer_id'])) {
              $canUploadEvidence = true;
          }
          if ($canUploadEvidence):
        ?>
          <div class="mt-6 pt-6 border-t border-gray-100">
            <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
              <i class="ti ti-upload text-accent text-sm"></i> Upload Digital Forensic Evidence
            </h3>
            <form id="evidence-upload-form" enctype="multipart/form-data" class="space-y-4">
              <input type="hidden" name="case_id" value="<?= (int)$case['id'] ?>">
              
              <!-- Drag and drop zone style -->
              <div class="border-2 border-dashed border-slate-200 hover:border-accent/50 rounded-2xl p-6 text-center cursor-pointer transition bg-slate-50/50 hover:bg-slate-50 relative group" id="drop-zone">
                <input type="file" name="file" id="evidence-file-input" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".pdf,image/jpeg,image/png,video/mp4" required>
                <div class="space-y-2 pointer-events-none">
                  <div class="w-10 h-10 rounded-full bg-accent/10 text-accent flex items-center justify-center mx-auto text-lg group-hover:scale-110 transition">
                    <i class="ti ti-cloud-upload"></i>
                  </div>
                  <p class="text-xs font-semibold text-slate-700" id="file-select-label">Drag & drop files here or click to browse</p>
                  <p class="text-[10px] text-slate-400">Supported formats: PDF, JPG, PNG, MP4 (Max 10MB)</p>
                </div>
              </div>
              
              <!-- Selected file preview/status -->
              <div id="upload-status-banner" class="hidden p-3 rounded-xl text-[11px] font-semibold border"></div>

              <div class="flex justify-end">
                <button type="submit" id="upload-submit-btn" disabled class="bg-accent hover:bg-accent-dark text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow opacity-50 cursor-not-allowed">
                  <i class="ti ti-arrow-up-bar"></i> Upload Evidence
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div>

      <!-- Suspect Profiles -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-4">
          <h2 class="text-navy font-bold text-lg flex items-center gap-2">
            <i class="ti ti-users text-accent"></i> Suspect Profiles
          </h2>
          <?php
            $canAddSuspect = false;
            if ($role === 'Admin' || $role === 'Officer') {
                $canAddSuspect = true;
            } elseif ($role === 'Investigator' && (int)$case['investigator_id'] === (int)$_SESSION['user_id']) {
                $canAddSuspect = true;
            } elseif (!empty($_SESSION['officer_id'])) {
                $canAddSuspect = true;
            }
            if ($canAddSuspect):
          ?>
            <button onclick="openSuspectModal()" class="bg-accent hover:bg-accent-dark text-white px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1 transition shadow-sm">
              <i class="ti ti-plus text-sm"></i> Add Suspect
            </button>
          <?php endif; ?>
        </div>
        
        <div id="suspects-list-container">
          <p class="text-gray-400 text-sm italic">Loading suspect profiles...</p>
        </div>
      </div>

      <!-- Investigation & Progress Timeline -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-bold text-lg border-b border-gray-100 pb-3 mb-5 flex items-center justify-between gap-2">
          <span class="flex items-center gap-2">
            <i class="ti ti-history text-accent"></i> Investigation Timeline
          </span>
          <span class="text-xs font-normal text-slate-400 bg-slate-100 px-2.5 py-0.5 rounded-full" id="timeline-count">Loading timeline...</span>
        </h2>

        <!-- Vertical Stepper Timeline -->
        <div class="relative pl-6 border-l-2 border-slate-150 space-y-6 ml-3 mb-6" id="timeline-container">
          <div class="text-slate-400 text-xs italic py-2">Loading timeline events...</div>
        </div>

        <!-- Add Progress Update Form (Admins, Officers, and Assigned Investigators only) -->
        <?php 
          $canAddEvent = false;
          if ($role === 'Admin' || $role === 'Officer') {
              $canAddEvent = true;
          } elseif ($role === 'Investigator' && (int)$case['investigator_id'] === (int)$_SESSION['user_id']) {
              $canAddEvent = true;
          } elseif ($officer) {
              $canAddEvent = true;
          }
          if ($canAddEvent):
        ?>
          <div class="border-t border-slate-100 pt-5 mt-5">
            <h3 class="text-navy font-bold text-sm mb-3 flex items-center gap-1.5">
              <i class="ti ti-plus text-accent"></i> Add Investigation Note / Progress
            </h3>
            <form id="add-timeline-form" class="space-y-3.5">
              <input type="hidden" name="action" value="add_timeline_event">
              <input type="hidden" name="case_id" value="<?= $caseId ?>">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-1">
                  <label for="event_type" class="block text-xs font-semibold text-slate-500 mb-1">Event Type</label>
                  <select name="event_type" id="event_type" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent bg-white text-slate-700 font-medium">
                    <option value="note_added">Progress Note</option>
                    <option value="status_change">Status Update</option>
                    <option value="other">Other Event</option>
                  </select>
                </div>
                <div class="sm:col-span-2">
                  <label for="event_title" class="block text-xs font-semibold text-slate-500 mb-1">Event Title <span class="text-rose-500">*</span></label>
                  <input type="text" name="title" id="event_title" required placeholder="e.g., Interviewed eyewitnesses / Visited location" class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent text-slate-800 font-medium">
                </div>
              </div>
              <div>
                <label for="event_desc" class="block text-xs font-semibold text-slate-500 mb-1">Description / Investigation Log</label>
                <textarea name="description" id="event_desc" rows="2" placeholder="Describe the outcome, actions taken, or details..." class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent text-slate-700 resize-y"></textarea>
              </div>
              <div class="flex justify-end">
                <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
                  <i class="ti ti-plus"></i> Add Event
                </button>
              </div>
            </form>
          </div>
        <?php endif; ?>
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

<!-- Add Suspect Modal -->
<div id="suspect-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-xl animate-in fade-in duration-200 my-8">
    <div class="bg-navy px-6 py-4 flex items-center justify-between">
      <h3 class="text-white font-semibold text-lg flex items-center gap-2">
        <i class="ti ti-user-plus"></i> Add Suspect Profile
      </h3>
      <button onclick="closeSuspectModal()" class="text-white/75 hover:text-white transition">
        <i class="ti ti-x text-lg"></i>
      </button>
    </div>
    <form id="suspect-form" enctype="multipart/form-data" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
      <input type="hidden" name="case_id" value="<?= $caseId ?>">
      
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="suspect_full_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Full Name <span class="text-red-400">*</span>
          </label>
          <input type="text" id="suspect_full_name" name="full_name" required placeholder="Enter full name" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
        <div>
          <label for="suspect_status" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Status <span class="text-red-400">*</span>
          </label>
          <select id="suspect_status" name="status" required class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white">
            <option value="identified">Identified</option>
            <option value="wanted">Wanted</option>
            <option value="under_arrest">Under Arrest</option>
            <option value="released">Released</option>
            <option value="convicted">Convicted</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="suspect_gender" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Gender
          </label>
          <select id="suspect_gender" name="gender" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white">
            <option value="">-- Select Gender --</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label for="suspect_dob" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Date of Birth
          </label>
          <input type="date" id="suspect_dob" name="date_of_birth" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label for="suspect_nid" class="block text-xs font-semibold text-gray-600 mb-1.5">
            National ID (NID)
          </label>
          <input type="text" id="suspect_nid" name="national_id" placeholder="Enter NID (10-17 digits)" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
        <div>
          <label for="suspect_phone" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Phone Number
          </label>
          <input type="text" id="suspect_phone" name="phone" placeholder="Enter phone number" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
        </div>
      </div>

      <div>
        <label for="suspect_email" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Email Address
        </label>
        <input type="email" id="suspect_email" name="email" placeholder="example@domain.com" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent">
      </div>

      <div>
        <label for="suspect_phys" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Physical Description (height, marks, features)
        </label>
        <textarea id="suspect_phys" name="physical_description" rows="2" placeholder="e.g. 5'8&quot;, scar on left arm, dark hair" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent resize-y"></textarea>
      </div>

      <div>
        <label for="suspect_addr" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Full Address / Last Known Location
        </label>
        <textarea id="suspect_addr" name="address" rows="2" placeholder="Enter full address" class="w-full px-4 py-2 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent resize-y"></textarea>
      </div>

      <div>
        <label for="suspect_photo" class="block text-xs font-semibold text-gray-600 mb-1.5">
          Suspect Photograph (JPG/PNG, Max 5MB)
        </label>
        <input type="file" id="suspect_photo" name="photo" accept="image/jpeg,image/png" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-accent/10 file:text-accent hover:file:bg-accent/20">
      </div>

      <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
        <button type="button" onclick="closeSuspectModal()" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
          Cancel
        </button>
        <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
          Save Suspect Profile
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
        'Under Review'  => ['bg-orange-50 text-orange-700 border-orange-200', 'ti-eye', 'Under Review'],
        'Registered'    => ['bg-green-50 text-green-700 border-green-200', 'ti-circle-check', 'Registered'],
        'Rejected'      => ['bg-red-50 text-red-700 border-red-200', 'ti-x', 'Rejected'],
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
// Escape HTML to prevent XSS
function escapeHTML(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

// Render timeline stepper
function renderTimeline(events) {
  const container = document.getElementById('timeline-container');
  const countEl = document.getElementById('timeline-count');
  
  if (!events || events.length === 0) {
    container.innerHTML = `<p class="text-slate-400 text-xs italic py-2">No timeline events recorded yet.</p>`;
    countEl.textContent = '0 events';
    return;
  }
  
  countEl.textContent = `${events.length} event${events.length > 1 ? 's' : ''}`;
  
  const typeMap = {
    'created': { icon: 'ti-circle-plus', bg: 'bg-blue-50 text-blue-600 border border-blue-150' },
    'status_change': { icon: 'ti-refresh', bg: 'bg-purple-50 text-purple-600 border border-purple-155' },
    'investigator_assigned': { icon: 'ti-user-shield', bg: 'bg-emerald-50 text-emerald-600 border border-emerald-150' },
    'evidence_uploaded': { icon: 'ti-paperclip', bg: 'bg-amber-50 text-amber-600 border border-amber-150' },
    'note_added': { icon: 'ti-notebook', bg: 'bg-slate-100 text-slate-600 border border-slate-200' },
    'other': { icon: 'ti-circle-dot', bg: 'bg-slate-50 text-slate-500 border border-slate-100' }
  };
  
  container.innerHTML = events.map(e => {
    const config = typeMap[e.event_type] || typeMap['other'];
    const formattedDate = new Date(e.created_at).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    
    return `
      <div class="relative group animate-in fade-in duration-300">
        <!-- Bullet dot centered on line -->
        <span class="absolute -left-[36px] top-0 w-6 h-6 rounded-full ${config.bg} flex items-center justify-center text-[10px] shadow-sm group-hover:scale-110 transition-all">
          <i class="ti ${config.icon}"></i>
        </span>
        <!-- Content -->
        <div>
          <div class="flex items-baseline justify-between gap-4">
            <h4 class="text-xs font-semibold text-slate-800">${escapeHTML(e.title)}</h4>
            <span class="text-[9px] text-slate-400 font-semibold whitespace-nowrap">${formattedDate}</span>
          </div>
          ${e.description ? `<p class="text-[11px] text-slate-500 mt-1 whitespace-pre-line leading-relaxed">${escapeHTML(e.description)}</p>` : ''}
          ${e.created_by_name ? `
            <div class="flex items-center gap-1 mt-1 text-[9px] font-semibold text-slate-400">
              <i class="ti ti-user-circle"></i> By ${escapeHTML(e.created_by_name)}
            </div>
          ` : ''}
        </div>
      </div>
    `;
  }).join('');
}

// Render evidence files dynamically
function renderEvidence(evidence, currentUser, caseObj) {
  const container = document.getElementById('evidence-list-container');
  if (!container) return;
  
  let canDelete = false;
  if (currentUser) {
    if (currentUser.role === 'Admin' || currentUser.role === 'Officer') {
      canDelete = true;
    } else if (currentUser.role === 'Investigator' && caseObj && parseInt(caseObj.investigator_id) === parseInt(currentUser.id)) {
      canDelete = true;
    }
  }

  if (evidence && evidence.length > 0) {
    container.innerHTML = `
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 animate-in fade-in duration-300">
        ${evidence.map(ev => {
          const isImg = /^image\//.test(ev.file_type);
          const isPdf = ev.file_type === 'application/pdf';
          const isMp4 = ev.file_type === 'video/mp4';
          
          let icon = 'ti-file text-gray-500';
          let bg = 'bg-gray-100';
          if (isImg) { icon = 'ti-photo text-green-600'; bg = 'bg-green-50'; }
          else if (isPdf) { icon = 'ti-file-text text-red-500'; bg = 'bg-red-50'; }
          else if (isMp4) { icon = 'ti-video text-purple-600'; bg = 'bg-purple-50'; }
          
          const sizeKb = (ev.file_size / 1024).toFixed(1);
          
          return `
            <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:bg-gray-50 transition">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg ${bg} flex items-center justify-center flex-shrink-0">
                  <i class="ti ${icon} text-lg"></i>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-800 truncate" title="${escapeHTML(ev.file_name)}">${escapeHTML(ev.file_name)}</p>
                  <p class="text-xs text-gray-400">${sizeKb} KB</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <a href="${escapeHTML(ev.file_path)}" target="_blank" class="w-8 h-8 rounded-full bg-navy/5 text-navy hover:bg-navy hover:text-white flex items-center justify-center transition" title="Download">
                  <i class="ti ti-download text-sm"></i>
                </a>
                ${canDelete ? `
                  <button onclick="deleteEvidence(${ev.id}, this)" class="w-8 h-8 rounded-full bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition" title="Delete">
                    <i class="ti ti-trash text-sm"></i>
                  </button>
                ` : ''}
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  } else {
    container.innerHTML = `<p class="text-gray-400 text-sm italic">No evidence files uploaded for this complaint.</p>`;
  }
}

function openSuspectModal() {
  document.getElementById('suspect-modal').classList.remove('hidden');
}

function closeSuspectModal() {
  document.getElementById('suspect-modal').classList.add('hidden');
}

function renderSuspects(suspects, currentUser, caseObj) {
  const container = document.getElementById('suspects-list-container');
  if (!container) return;
  
  if (!suspects || suspects.length === 0) {
    container.innerHTML = `<p class="text-gray-400 text-sm italic">No suspect profiles recorded for this case.</p>`;
    return;
  }
  
  const statusMap = {
    'identified':   { bg: 'bg-amber-50 text-amber-700 border-amber-200', text: 'Identified' },
    'wanted':       { bg: 'bg-red-50 text-red-700 border-red-200', text: 'Wanted' },
    'under_arrest': { bg: 'bg-purple-50 text-purple-700 border-purple-200', text: 'Under Arrest' },
    'released':     { bg: 'bg-green-50 text-green-700 border-green-200', text: 'Released' },
    'convicted':    { bg: 'bg-rose-50 text-rose-700 border-rose-200', text: 'Convicted' }
  };

  let html = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">`;
  suspects.forEach(susp => {
    const badge = statusMap[susp.status] || { bg: 'bg-gray-50 text-gray-700 border-gray-200', text: susp.status };
    const genderText = susp.gender ? susp.gender.charAt(0).toUpperCase() + susp.gender.slice(1) : '—';
    const dobText = susp.date_of_birth ? susp.date_of_birth : '—';
    const phoneVal = susp.phone ? susp.phone : '—';
    const emailVal = susp.email ? susp.email : '—';
    const nidVal = susp.national_id ? susp.national_id : '—';
    const physVal = susp.physical_description ? susp.physical_description : '—';
    const addrVal = susp.address ? susp.address : '—';
    
    const photoHtml = susp.photo_path 
      ? `<img src="${escapeHTML(susp.photo_path)}" alt="${escapeHTML(susp.full_name)}" class="w-12 h-12 rounded-xl object-cover border border-slate-200 flex-shrink-0">`
      : `<div class="w-12 h-12 rounded-xl bg-navy/5 text-navy flex items-center justify-center flex-shrink-0"><i class="ti ti-user text-lg"></i></div>`;

    html += `
      <div class="border border-gray-100 rounded-xl p-4 bg-slate-50/30 hover:bg-slate-50 transition shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center gap-3 mb-3">
            ${photoHtml}
            <div>
              <h4 class="font-bold text-gray-800 text-sm">${escapeHTML(susp.full_name)}</h4>
              <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border ${badge.bg}">
                ${badge.text}
              </span>
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-x-2 gap-y-1.5 text-xs text-gray-600 border-t border-gray-100 pt-3">
            <div>
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Gender</span>
              <span class="font-medium text-gray-700">${escapeHTML(genderText)}</span>
            </div>
            <div>
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Date of Birth</span>
              <span class="font-medium text-gray-700">${escapeHTML(dobText)}</span>
            </div>
            <div>
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">National ID</span>
              <span class="font-medium text-gray-700">${escapeHTML(nidVal)}</span>
            </div>
            <div>
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Phone</span>
              <span class="font-medium text-gray-700">${escapeHTML(phoneVal)}</span>
            </div>
            <div class="col-span-2">
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Email</span>
              <span class="font-medium text-gray-700 truncate block" title="${escapeHTML(emailVal)}">${escapeHTML(emailVal)}</span>
            </div>
            <div class="col-span-2">
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Physical Description</span>
              <p class="font-medium text-gray-700 whitespace-pre-line leading-tight text-[11px]">${escapeHTML(physVal)}</p>
            </div>
            <div class="col-span-2">
              <span class="text-[10px] text-gray-400 block uppercase font-semibold">Address / Location</span>
              <p class="font-medium text-gray-700 text-[11px]">${escapeHTML(addrVal)}</p>
            </div>
          </div>
        </div>
      </div>
    `;
  });
  html += `</div>`;
  container.innerHTML = html;
}

async function deleteEvidence(evidenceId, btn) {
  if (!confirm('Are you sure you want to delete this evidence file?')) return;
  btn.disabled = true;
  const oldContent = btn.innerHTML;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-sm"></i>';
  
  try {
    const fd = new FormData();
    fd.append('evidence_id', evidenceId);
    const res = await fetch('api/delete_case_evidence.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      await loadCaseData();
    } else {
      alert(data.error || 'Failed to delete evidence file');
      btn.disabled = false;
      btn.innerHTML = oldContent;
    }
  } catch (err) {
    alert('Network error');
    btn.disabled = false;
    btn.innerHTML = oldContent;
  }
}

// Load case details and timeline events from API
async function loadCaseData() {
  const caseId = <?= $caseId ?>;
  try {
    const res = await fetch(`api/get_case_details.php?id=${caseId}`);
    const data = await res.json();
    if (data.success) {
      renderTimeline(data.timeline);
      renderEvidence(data.evidence, data.session_user, data.case);
      renderSuspects(data.suspects, data.session_user, data.case);
    } else {
      console.error('Failed to load case data:', data.message);
      document.getElementById('timeline-count').textContent = 'Error loading';
    }
  } catch (err) {
    console.error('Fetch error:', err);
    document.getElementById('timeline-count').textContent = 'Error';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadCaseData();
  
  document.getElementById('add-timeline-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    const oldText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin"></i> Adding…';
    
    try {
      const fd = new FormData(this);
      const res = await fetch('api/case.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        this.reset();
        await loadCaseData();
      } else {
        alert(data.message || 'Failed to add timeline event');
      }
    } catch (err) {
      alert('Network error');
    } finally {
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldText;
    }
  });

  // Client-side file validation and async upload
  const fileInput = document.getElementById('evidence-file-input');
  const uploadForm = document.getElementById('evidence-upload-form');
  const uploadBtn = document.getElementById('upload-submit-btn');
  const banner = document.getElementById('upload-status-banner');
  const selectLabel = document.getElementById('file-select-label');

  if (fileInput) {
    fileInput.addEventListener('change', function(e) {
      const file = this.files[0];
      if (!file) {
        banner.classList.add('hidden');
        uploadBtn.disabled = true;
        uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');
        selectLabel.textContent = 'Drag & drop files here or click to browse';
        return;
      }

      const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'mp4'];
      const allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'video/mp4'];
      const maxFileSize = 10 * 1024 * 1024; // 10MB
      
      const fileExtension = file.name.split('.').pop().toLowerCase();
      const isValidExtension = allowedExtensions.includes(fileExtension);
      const isValidMime = allowedMimeTypes.includes(file.type);
      const isValidSize = file.size <= maxFileSize;

      banner.classList.remove('hidden');
      if (!isValidExtension || !isValidMime) {
        banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-red-50 text-red-700 border-red-200';
        banner.textContent = 'Invalid file type. Only PDF, JPG, PNG, and MP4 are allowed.';
        uploadBtn.disabled = true;
        uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');
        selectLabel.textContent = file.name;
      } else if (!isValidSize) {
        banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-red-50 text-red-700 border-red-200';
        banner.textContent = 'File is too large. Maximum size is 10MB.';
        uploadBtn.disabled = true;
        uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');
        selectLabel.textContent = file.name;
      } else {
        banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-green-50 text-green-700 border-green-200';
        banner.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
        uploadBtn.disabled = false;
        uploadBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        selectLabel.textContent = file.name;
      }
    });
  }

  if (uploadForm) {
    uploadForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      uploadBtn.disabled = true;
      const oldText = uploadBtn.innerHTML;
      uploadBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-sm"></i> Uploading…';

      try {
        const fd = new FormData(this);
        const res = await fetch('api/upload_case_evidence.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        
        if (data.success) {
          banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-green-50 text-green-700 border-green-200';
          banner.textContent = 'Forensic evidence file uploaded successfully!';
          uploadForm.reset();
          selectLabel.textContent = 'Drag & drop files here or click to browse';
          uploadBtn.classList.add('opacity-50', 'cursor-not-allowed');
          await loadCaseData();
          setTimeout(() => {
            banner.classList.add('hidden');
          }, 3000);
        } else {
          banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-red-50 text-red-700 border-red-200';
          banner.textContent = data.error || 'Failed to upload evidence.';
          uploadBtn.disabled = false;
        }
      } catch (err) {
        banner.className = 'p-3 rounded-xl text-[11px] font-semibold border bg-red-50 text-red-700 border-red-200';
        banner.textContent = 'Network error during upload.';
        uploadBtn.disabled = false;
      } finally {
        uploadBtn.innerHTML = oldText;
      }
    });
  }

  const suspectForm = document.getElementById('suspect-form');
  if (suspectForm) {
    suspectForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      const oldText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-sm"></i> Saving…';

      try {
        const fd = new FormData(this);
        const res = await fetch('api/add_suspect_profile.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        
        if (data.success) {
          alert(data.message || 'Suspect profile added successfully!');
          closeSuspectModal();
          suspectForm.reset();
          await loadCaseData();
        } else {
          alert(data.error || 'Failed to add suspect profile.');
        }
      } catch (err) {
        alert('Network error during suspect profile addition.');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = oldText;
      }
    });
  }
});

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

async function adminApproveFIR(firId, firNumber, btn) {
  if (!confirm(`Are you sure you want to approve and register FIR ${firNumber}?`)) return;
  btn.disabled = true;
  const oldText = btn.innerHTML;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Approving…';
  try {
    const fd = new FormData();
    fd.append('fir_id', firId);
    fd.append('action', 'approve');
    const res = await fetch('api/admin_fir.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      alert(data.message || 'FIR Approved and Registered successfully.');
      location.reload();
    } else {
      alert(data.message || 'Failed to approve FIR');
      btn.disabled = false;
      btn.innerHTML = oldText;
    }
  } catch (e) {
    alert('Network error');
    btn.disabled = false;
    btn.innerHTML = oldText;
  }
}

function adminOpenRejectModal(firId, firNumber) {
  document.getElementById('modal-fir-id').value = firId;
  document.getElementById('reject-reason').value = '';
  document.getElementById('reject-modal').classList.remove('hidden');
}

function adminCloseRejectModal() {
  document.getElementById('reject-modal').classList.add('hidden');
}

document.getElementById('reject-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const submitBtn = this.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  const oldText = submitBtn.innerHTML;
  submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Rejecting…';
  
  try {
    const fd = new FormData(this);
    const res = await fetch('api/admin_fir.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      adminCloseRejectModal();
      alert(data.message || 'FIR Rejected successfully.');
      location.reload();
    } else {
      alert(data.message || 'Failed to reject FIR');
      submitBtn.disabled = false;
      submitBtn.innerHTML = oldText;
    }
  } catch (e) {
    alert('Network error');
    submitBtn.disabled = false;
    submitBtn.innerHTML = oldText;
  }
});
</script>

<!-- Rejection Reason Modal (Admin only) -->
<?php if ($sessionRole === 'Admin'): ?>
<div id="reject-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl border border-slate-100">
        <div class="bg-navy px-6 py-4 flex items-center justify-between text-white">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i class="ti ti-circle-x text-rose-500"></i> Reject FIR Complaint
            </h3>
            <button onclick="adminCloseRejectModal()" class="text-white/70 hover:text-white transition"><i class="ti ti-x text-lg"></i></button>
        </div>
        <form id="reject-form" class="p-6 space-y-4">
            <input type="hidden" id="modal-fir-id" name="fir_id">
            <input type="hidden" name="action" value="reject">
            
            <div>
                <label for="reject-reason" class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Reason for Rejection <span class="text-rose-500">*</span>
                </label>
                <textarea id="reject-reason" name="reason" rows="4" required 
                          placeholder="Enter explanation or citation of legal code..." 
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition resize-y"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="adminCloseRejectModal()" 
                        class="px-4 py-2 border border-slate-200 rounded-xl text-sm font-semibold text-gray-600 hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition shadow-sm">
                    Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

  </div>

  <footer class="py-6 mt-8">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-xs text-gray-500 font-medium">
        © 2026 CaseFlowX
      </p>
    </div>
  </footer>
</body>
</html>
