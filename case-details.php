<?php
/**
 * case-details.php — CaseFlowX
 * Displays full details of a single case.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$caseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = get_db();
$stmt = $db->prepare('
    SELECT c.*, u.full_name as citizen_name, u.phone as citizen_phone, u.email as citizen_email
    FROM cases c
    JOIN users u ON c.citizen_id = u.id
    WHERE c.id = :id
    LIMIT 1
');
$stmt->execute([':id' => $caseId]);
$case = $stmt->fetch();

if (!$case) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'Investigator') {
        header('Location: investigator_dashboard.php');
    } else {
        header('Location: cases.php');
    }
    exit;
}

$role = $_SESSION['role'] ?? '';
$allowed = false;

if ($role === 'Admin' || $role === 'Officer') {
    $allowed = true;
} elseif ($role === 'Citizen') {
    if ($case['citizen_id'] == $_SESSION['user_id']) {
        $allowed = true;
    }
} elseif ($role === 'Investigator') {
    if ($case['investigator_id'] == $_SESSION['user_id']) {
        $allowed = true;
    }
}

if (!$allowed) {
    header('Location: unauthorized.php');
    exit;
}

$assignedInvestigator = null;
if (!empty($case['investigator_id'])) {
    $i_stmt = $db->prepare('SELECT full_name, phone FROM users WHERE id = :id LIMIT 1');
    $i_stmt->execute([':id' => $case['investigator_id']]);
    $assignedInvestigator = $i_stmt->fetch();
}

function statusBadge(string $status): string {
    $colors = [
        'open'        => 'bg-blue-100 text-blue-700 border-blue-200',
        'in_progress' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'resolved'    => 'bg-green-100 text-green-700 border-green-200',
        'closed'      => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
    $icons = [
        'open'        => 'ti-circle-dot',
        'in_progress' => 'ti-loader',
        'resolved'    => 'ti-check-circle',
        'closed'      => 'ti-x',
    ];
    $labels = [
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
    ];
    $cls = $colors[$status] ?? $colors['open'];
    $ico = $icons[$status] ?? $icons['open'];
    $lbl = $labels[$status] ?? ucfirst($status);
    return "<span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {$cls}\">
            <i class=\"ti {$ico}\"></i> {$lbl}
          </span>";
}

function priorityBadge(string $priority): string {
    $colors = [
        'low'    => 'text-gray-500',
        'medium' => 'text-orange-500',
        'high'   => 'text-red-500',
    ];
    $cls = $colors[$priority] ?? $colors['low'];
    return "<span class=\"text-xs font-medium {$cls}\">" . ucfirst($priority) . "</span>";
}
?>
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

<div class="min-h-[calc(100vh-200px)] bg-[#F4F6F9]">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
      <a href="dashboard.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Dashboard
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <a href="cases.php" class="hover:text-accent transition-colors flex items-center gap-1">
        My Cases
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium"><?= htmlspecialchars($case['case_number']) ?></span>
    </div>

    <!-- Case Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
      <div class="px-8 py-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <h1 class="text-xl font-bold text-navy"><?= htmlspecialchars($case['title']) ?></h1>
          </div>
          <p class="text-sm text-gray-500 flex items-center gap-1.5">
            <i class="ti ti-hash text-xs"></i>
            <?= htmlspecialchars($case['case_number']) ?>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <?= statusBadge($case['status']) ?>
          <?= priorityBadge($case['priority']) ?>
        </div>
      </div>

      <div class="px-8 py-6">
        <!-- Description -->
        <div class="mb-6">
          <h2 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
            <i class="ti ti-align-left text-accent"></i> Description
          </h2>
          <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">
            <?= nl2br(htmlspecialchars($case['description'])) ?>
          </div>
        </div>

        <!-- Contact / Assignment details -->
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-slide-up">
          <?php if ($role === 'Investigator' || $role === 'Admin' || $role === 'Officer'): ?>
            <div class="bg-[#E8F8F3] border border-emerald-100 rounded-xl p-4">
              <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800 mb-2 flex items-center gap-1.5">
                <i class="ti ti-user"></i> Citizen Information
              </h3>
              <p class="text-sm text-navy font-semibold"><?= htmlspecialchars($case['citizen_name']) ?></p>
              <p class="text-xs text-gray-500 mt-1"><i class="ti ti-phone text-xs"></i> Phone: <?= htmlspecialchars($case['citizen_phone']) ?></p>
              <?php if (!empty($case['citizen_email'])): ?>
                <p class="text-xs text-gray-500 mt-0.5"><i class="ti ti-mail text-xs"></i> Email: <?= htmlspecialchars($case['citizen_email']) ?></p>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
            <h3 class="text-xs font-bold uppercase tracking-wider text-blue-800 mb-2 flex items-center gap-1.5">
              <i class="ti ti-user-shield"></i> Assigned Investigator
            </h3>
            <?php if ($assignedInvestigator): ?>
              <p class="text-sm text-navy font-semibold"><?= htmlspecialchars($assignedInvestigator['full_name']) ?></p>
              <p class="text-xs text-gray-500 mt-1"><i class="ti ti-phone text-xs"></i> Phone: <?= htmlspecialchars($assignedInvestigator['phone']) ?></p>
            <?php else: ?>
              <p class="text-sm text-gray-400 italic font-medium">No investigator assigned yet.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Meta grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-[#f8f9fc] rounded-xl p-4">
            <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <i class="ti ti-calendar-event text-xs"></i> Filed On
            </p>
            <p class="text-sm font-semibold text-navy"><?= date('M d, Y \a\t h:i A', strtotime($case['created_at'])) ?></p>
          </div>
          <div class="bg-[#f8f9fc] rounded-xl p-4">
            <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <i class="ti ti-flag text-xs"></i> Priority
            </p>
            <p class="text-sm font-semibold text-navy"><?= ucfirst(htmlspecialchars($case['priority'])) ?></p>
          </div>
          <div class="bg-[#f8f9fc] rounded-xl p-4">
            <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
              <i class="ti ti-status-change text-xs"></i> Status
            </p>
            <p class="text-sm font-semibold text-navy"><?= ucfirst(str_replace('_', ' ', htmlspecialchars($case['status']))) ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between">
      <?php if ($role === 'Investigator'): ?>
      <a href="investigator_dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
        <i class="ti ti-arrow-left text-xs"></i> Back to Dashboard
      </a>
      <?php else: ?>
      <a href="cases.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
        <i class="ti ti-arrow-left text-xs"></i> Back to My Cases
      </a>
      <?php endif; ?>
      
      <?php if ($role === 'Citizen'): ?>
      <a href="new-case.php" class="inline-flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
        <i class="ti ti-plus text-base"></i> File Another Case
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
