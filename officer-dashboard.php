<?php
/**
 * officer-dashboard.php — CaseFlowX Officer Dashboard
 * Displays complaints and FIRs with actions to Accept, Reject, and Assign Investigators.
 */
require_once __DIR__ . '/db.php';

$officer = require_officer();
$db = get_db();

// ── Stats ───────────────────────────────────────────────────────────────
$totalAssigned = 0;
$unassignedCount = 0;
$underReviewCount = 0;
$registeredCount = 0;
try {
    $totalAssigned = (int) $db->query("SELECT COUNT(*) FROM cases WHERE officer_id IS NOT NULL")->fetchColumn();
    $unassignedCount = (int) $db->query("SELECT COUNT(*) FROM cases WHERE officer_id IS NULL AND (status IN ('Submitted','open','Open','Pending'))")->fetchColumn();
    $underReviewCount = (int) $db->query("SELECT COUNT(*) FROM cases WHERE (status IN ('Under Review','in_progress','Pending'))")->fetchColumn();
    $registeredCount = (int) $db->query("SELECT COUNT(*) FROM cases WHERE (status IN ('Registered','resolved','Closed','closed'))")->fetchColumn();
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// ── Assigned cases ──────────────────────────────────────────────────────
$assignedCases = [];
try {
    $stmt = $db->query("
        SELECT c.*, o.full_name as officer_name,
               (SELECT COUNT(*) FROM fir_evidence WHERE case_id = c.id) as evidence_count
        FROM cases c
        LEFT JOIN officers o ON c.officer_id = o.id
        WHERE c.officer_id IS NOT NULL
        ORDER BY c.created_at DESC
    ");
    $assignedCases = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard assigned cases error: " . $e->getMessage());
}

// ── Unassigned cases ────────────────────────────────────────────────────
$unassignedCases = [];
try {
    $stmt = $db->query("
        SELECT c.*, ct.full_name as citizen_name
        FROM cases c
        LEFT JOIN citizens ct ON c.citizen_id = ct.id
        WHERE c.officer_id IS NULL AND (c.status IN ('Submitted','open','Open'))
        ORDER BY c.created_at DESC
    ");
    $unassignedCases = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard unassigned cases error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Officer Dashboard — CaseFlowX</title>
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
      <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-white text-xl shadow">
        <i class="ti ti-shield"></i>
      </div>
      <div>
        <h1 class="text-lg font-bold leading-none">CaseFlowX</h1>
        <p class="text-[11px] text-white/50 mt-0.5">Digital Police Case Management System</p>
      </div>
    </div>
    <div class="flex items-center gap-5 text-sm">
      <div class="hidden sm:block text-right">
        <div class="font-semibold text-white"><?= htmlspecialchars($officer['full_name']) ?></div>
        <div class="text-[11px] text-white/55">Badge: <?= htmlspecialchars($officer['badge_number']) ?> · Station: <?= htmlspecialchars($officer['station_code']) ?></div>
      </div>
      <div class="h-6 w-px bg-white/20 hidden sm:block"></div>
      <a href="search-criminals.php" class="border border-white/20 hover:bg-white/10 text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
        <i class="ti ti-search text-xs"></i> Search Criminals
      </a>
      <a href="file-fir.php" class="bg-accent hover:bg-accent-dark text-white px-4 py-2 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow">
        <i class="ti ti-file-plus"></i> File FIR
      </a>
      <a href="logout.php" class="text-white/70 hover:text-red-400 transition flex items-center gap-1 text-xs">
        <i class="ti ti-logout text-sm"></i> Logout
      </a>
    </div>
  </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
      <div class="w-12 h-12 rounded-xl bg-navy/10 flex items-center justify-center text-navy text-2xl flex-shrink-0">
        <i class="ti ti-file-text"></i>
      </div>
      <div>
        <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Total Assigned</p>
        <p class="text-navy text-2xl font-bold mt-1"><?= number_format($totalAssigned) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
      <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center text-orange-600 text-2xl flex-shrink-0">
        <i class="ti ti-inbox"></i>
      </div>
      <div>
        <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Unassigned</p>
        <p class="text-orange-600 text-2xl font-bold mt-1"><?= number_format($unassignedCount) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
      <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-2xl flex-shrink-0">
        <i class="ti ti-eye"></i>
      </div>
      <div>
        <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Under Review</p>
        <p class="text-blue-600 text-2xl font-bold mt-1"><?= number_format($underReviewCount) ?></p>
      </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
      <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-600 text-2xl flex-shrink-0">
        <i class="ti ti-circle-check"></i>
      </div>
      <div>
        <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Registered</p>
        <p class="text-green-600 text-2xl font-bold mt-1"><?= number_format($registeredCount) ?></p>
      </div>
    </div>
  </div>

  <!-- Unassigned Cases -->
  <?php if (count($unassignedCases) > 0): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
    <div class="bg-orange-50 border-b border-orange-100 px-6 py-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center text-white text-lg"><i class="ti ti-inbox"></i></div>
      <div>
        <h2 class="text-navy font-semibold text-base">Unassigned complaints / FIRs</h2>
        <p class="text-gray-400 text-xs">New complaints awaiting acceptance & investigator assignment</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead class="bg-[#f8f9fc]">
          <tr>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Case / FIR #</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Title</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Complainant</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Date</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Priority</th>
            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($unassignedCases as $case): ?>
          <tr id="unassigned-row-<?= (int)$case['id'] ?>" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-6 py-4 text-sm font-medium text-navy">
              <div><?= htmlspecialchars($case['case_number']) ?></div>
              <?php if (!empty($case['fir_number'])): ?>
                <div class="text-[11px] text-gray-400 font-normal mt-0.5"><?= htmlspecialchars($case['fir_number']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600 font-medium"><?= htmlspecialchars($case['title']) ?></td>
            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($case['citizen_name'] ?? $case['complainant_name'] ?? '—') ?></td>
            <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
            <td class="px-6 py-4"><?= priorityBadge($case['priority']) ?></td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <button onclick="acceptCase(<?= (int)$case['id'] ?>, this)" class="bg-accent/15 text-accent hover:bg-accent hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition">
                  <i class="ti ti-check"></i> Accept
                </button>
                <button onclick="rejectCase(<?= (int)$case['id'] ?>, this)" class="bg-red-50 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition">
                  <i class="ti ti-x"></i> Reject
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center mb-6">
    <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
      <i class="ti ti-inbox text-3xl"></i>
    </div>
    <h3 class="text-navy font-semibold text-lg">No unassigned cases</h3>
    <p class="text-gray-400 text-sm">All complaints have been reviewed or accepted.</p>
  </div>
  <?php endif; ?>

  <!-- Assigned Cases -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-navy px-6 py-4 flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-white text-lg"><i class="ti ti-folder"></i></div>
      <div>
        <h2 class="text-white font-semibold text-base">Assigned & Registered Cases</h2>
        <p class="text-white/55 text-xs">Under police jurisdiction and assigned for active investigation</p>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-left">
        <thead class="bg-[#f8f9fc]">
          <tr>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Case / FIR #</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Title</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Assigned Officer</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Investigating Officer</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3.5 text-xs font-semibold text-gray-500 uppercase">Priority</th>
            <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($assignedCases as $case): 
            $isAssignedToMe = ((int)$case['officer_id'] === (int)$officer['id']);
          ?>
          <tr class="hover:bg-gray-50/50 transition-colors">
            <td class="px-6 py-4 text-sm font-medium text-navy">
              <div><?= htmlspecialchars($case['case_number']) ?></div>
              <?php if (!empty($case['fir_number'])): ?>
                <div class="text-[11px] text-gray-400 font-normal mt-0.5"><?= htmlspecialchars($case['fir_number']) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600 font-medium"><?= htmlspecialchars($case['title']) ?></td>
            <td class="px-6 py-4 text-sm text-gray-600">
              <?php if ($isAssignedToMe): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-navy/10 text-navy">
                  <i class="ti ti-user-check text-xs"></i> Assigned to Me
                </span>
              <?php else: ?>
                <span class="text-gray-700"><?= htmlspecialchars($case['officer_name'] ?? '—') ?></span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4 text-sm">
              <?php if (!empty($case['investigating_officer'])): ?>
                <div class="flex items-center gap-1.5 text-gray-800 font-semibold">
                  <i class="ti ti-user-shield text-accent text-base"></i>
                  <span><?= htmlspecialchars($case['investigating_officer']) ?></span>
                </div>
              <?php else: ?>
                <span class="text-gray-400 italic">Not Assigned</span>
              <?php endif; ?>
            </td>
            <td class="px-6 py-4"><?= statusBadge($case['status']) ?></td>
            <td class="px-6 py-4"><?= priorityBadge($case['priority']) ?></td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-3">
                <a href="case-details.php?id=<?= (int)$case['id'] ?>" class="text-accent hover:text-accent-dark font-bold text-xs flex items-center gap-0.5">
                  <i class="ti ti-external-link"></i> View Details
                </a>
                <?php if ($isAssignedToMe): ?>
                  <button onclick="openAssignModal(<?= (int)$case['id'] ?>, '<?= htmlspecialchars($case['investigating_officer'] ?? '') ?>')" class="bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white px-2.5 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1 transition">
                    <i class="ti ti-user-shield"></i> Assign
                  </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($assignedCases)): ?>
          <tr>
            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
              No assigned or registered cases found.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
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
      <input type="hidden" id="modal-case-id" name="case_id">
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
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold border {$cls}\">{$lbl}</span>";
}
?>

<script>
async function acceptCase(caseId, btn) {
  btn.disabled = true;
  const oldText = btn.innerHTML;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin"></i> Accepting…';
  try {
    const fd = new FormData();
    fd.append('action', 'accept');
    fd.append('case_id', caseId);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.getElementById('unassigned-row-' + caseId)?.remove();
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
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin"></i> Rejecting…';
  try {
    const fd = new FormData();
    fd.append('action', 'reject');
    fd.append('case_id', caseId);
    const res = await fetch('api/case.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      document.getElementById('unassigned-row-' + caseId)?.remove();
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
  document.getElementById('modal-case-id').value = caseId;
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
  submitBtn.innerHTML = '<i class="ti ti-loader-2 animate-spin"></i> Assigning…';
  
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
</script>
</body>
</html>
