<?php
/**
 * dashboard.php — CaseFlowX Citizen Dashboard
 * Main dashboard for logged-in citizens.
 */

// Start session and check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

// Fetch citizen details
$db = get_db();
$stmt = $db->prepare('SELECT * FROM users WHERE id = :id AND role = "Citizen" LIMIT 1');
$stmt->execute([':id' => $_SESSION['citizen_id']]);
$citizen = $stmt->fetch();

if (!$citizen) {
    // Invalid session, logout
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch real dashboard data
$stmt = $db->prepare('
    SELECT * FROM cases
    WHERE citizen_id = :citizen_id
    ORDER BY created_at DESC
    LIMIT 5
');
$stmt->execute([':citizen_id' => $_SESSION['citizen_id']]);
$recentCases = $stmt->fetchAll();

// Stats counts
$totalStmt = $db->prepare('SELECT COUNT(*) FROM cases WHERE citizen_id = :citizen_id');
$totalStmt->execute([':citizen_id' => $_SESSION['citizen_id']]);
$totalCases = (int) $totalStmt->fetchColumn();

$openStmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE citizen_id = :citizen_id AND status = 'open'");
$openStmt->execute([':citizen_id' => $_SESSION['citizen_id']]);
$openCases = (int) $openStmt->fetchColumn();

$resolvedStmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE citizen_id = :citizen_id AND status = 'resolved'");
$resolvedStmt->execute([':citizen_id' => $_SESSION['citizen_id']]);
$resolvedCases = (int) $resolvedStmt->fetchColumn();

// Helper for status badges
function statusBadge(string $status): string {
    $colors = [
        'open' => 'bg-blue-100 text-blue-700 border-blue-200',
        'in_progress' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'resolved' => 'bg-green-100 text-green-700 border-green-200',
        'closed' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
    $icons = [
        'open' => 'ti-circle-dot',
        'in_progress' => 'ti-loader',
        'resolved' => 'ti-check-circle',
        'closed' => 'ti-x',
    ];
    $labels = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
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
        'low' => 'text-gray-500',
        'medium' => 'text-orange-500',
        'high' => 'text-red-500',
    ];
    $cls = $colors[$priority] ?? $colors['low'];
    return "<span class=\"text-xs font-medium {$cls}\">" . ucfirst($priority) . "</span>";
}
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — Citizen Dashboard
     Tailwind CSS via CDN · Tabler Icons
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Tailwind + Tabler -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Citizen Dashboard — CaseFlowX</title>
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
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Welcome Section -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-navy">Welcome back, <?= htmlspecialchars(explode(' ', $citizen['full_name'])[0]) ?>!</h1>
      <p class="text-gray-500 mt-1">Manage your cases and track their progress from your personal dashboard.</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
      <!-- Total Cases -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm font-medium">Total Cases</p>
            <p class="text-3xl font-bold text-navy mt-1"><?= $totalCases ?></p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
            <i class="ti ti-folders text-2xl"></i>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2 text-sm">
          <span class="text-accent font-medium flex items-center gap-1">
            <i class="ti ti-folder"></i> All your
          </span>
          <span class="text-gray-400">filed complaints</span>
        </div>
      </div>

      <!-- Open Cases -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm font-medium">Open Cases</p>
            <p class="text-3xl font-bold text-navy mt-1"><?= $openCases ?></p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-yellow-50 flex items-center justify-center text-yellow-600">
            <i class="ti ti-clock text-2xl"></i>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2 text-sm">
          <span class="text-yellow-600 font-medium">Awaiting response</span>
        </div>
      </div>

      <!-- Resolved -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm font-medium">Resolved</p>
            <p class="text-3xl font-bold text-navy mt-1"><?= $resolvedCases ?></p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center text-green-600">
            <i class="ti ti-circle-check text-2xl"></i>
          </div>
        </div>
        <div class="mt-4 flex items-center gap-2 text-sm">
          <span class="text-green-600 font-medium flex items-center gap-1">
            <i class="ti ti-check"></i> Completed
          </span>
        </div>
      </div>

      <!-- Quick Action -->
      <a href="new-case.php" class="bg-gradient-to-br from-accent to-accent-dark rounded-2xl p-6 shadow-sm text-white hover:shadow-md transition group">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-white/80 text-sm font-medium">New Case</p>
            <p class="text-lg font-bold mt-1">File Complaint</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center group-hover:scale-110 transition">
            <i class="ti ti-plus text-2xl"></i>
          </div>
        </div>
        <div class="mt-4 text-sm text-white/70 flex items-center gap-1">
          Start now <i class="ti ti-arrow-right"></i>
        </div>
      </a>
    </div>

    <!-- Recent Cases Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
      <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-navy font-semibold text-lg flex items-center gap-2">
          <i class="ti ti-list-details text-accent"></i> Recent Cases
        </h2>
        <a href="cases.php" class="text-accent text-sm font-medium hover:underline flex items-center gap-1">
          View All <i class="ti ti-arrow-right text-xs"></i>
        </a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-[#f8f9fc]">
            <tr>
              <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Case ID</th>
              <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Title</th>
              <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
              <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Priority</th>
              <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
              <th class="px-6 py-3.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php foreach ($recentCases as $case): ?>
            <tr class="hover:bg-gray-50/50 transition">
              <td class="px-6 py-4 text-sm font-medium text-navy"><?= htmlspecialchars($case['case_number']) ?></td>
              <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($case['title']) ?></td>
              <td class="px-6 py-4"><?= statusBadge($case['status']) ?></td>
              <td class="px-6 py-4"><?= priorityBadge($case['priority']) ?></td>
              <td class="px-6 py-4 text-sm text-gray-500"><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
              <td class="px-6 py-4 text-right">
                <a href="case-details.php?id=<?= (int)$case['id'] ?>" class="text-accent hover:text-accent-dark font-medium text-sm flex items-center gap-1 justify-end">
                  View <i class="ti ti-external-link text-xs"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentCases)): ?>
            <tr>
              <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                <div class="flex flex-col items-center gap-3">
                  <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center text-gray-300">
                    <i class="ti ti-inbox text-3xl"></i>
                  </div>
                  <p>No cases yet. <a href="new-case.php" class="text-accent hover:underline">File your first complaint</a>.</p>
                </div>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Profile Summary -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-semibold text-lg mb-5 flex items-center gap-2">
          <i class="ti ti-user-circle text-accent"></i> Profile
        </h2>
        
        <div class="flex items-center gap-4 mb-6">
          <div class="w-16 h-16 rounded-2xl bg-accent text-white flex items-center justify-center text-2xl font-bold">
            <?= strtoupper(substr($citizen['full_name'], 0, 1)) ?>
          </div>
          <div>
            <p class="font-semibold text-navy"><?= htmlspecialchars($citizen['full_name']) ?></p>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($citizen['phone']) ?></p>
          </div>
        </div>

        <div class="space-y-3 text-sm">
          <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
            <i class="ti ti-id-badge text-gray-400"></i>
            <span class="text-gray-600">NID:</span>
            <span class="font-medium text-navy ml-auto"><?= htmlspecialchars($citizen['national_id']) ?></span>
          </div>
          <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
            <i class="ti ti-mail text-gray-400"></i>
            <span class="text-gray-600">Email:</span>
            <span class="font-medium text-navy ml-auto truncate max-w-[150px]">
              <?= $citizen['email'] ? htmlspecialchars($citizen['email']) : '<span class=\"text-gray-400 italic\">Not added</span>' ?>
            </span>
          </div>
          <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
            <i class="ti ti-calendar text-gray-400"></i>
            <span class="text-gray-600">Member since:</span>
            <span class="font-medium text-navy ml-auto"><?= date('M Y', strtotime($citizen['created_at'])) ?></span>
          </div>
          <div class="flex items-center gap-3">
            <i class="ti ti-map-pin text-gray-400"></i>
            <span class="text-gray-600">Location:</span>
            <span class="font-medium text-navy ml-auto"><?= htmlspecialchars($citizen['district']) ?>, <?= htmlspecialchars($citizen['division']) ?></span>
          </div>
        </div>

        <a href="profile.php" class="mt-5 w-full block text-center py-2.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition">
          Edit Profile
        </a>
      </div>

      <!-- Quick Links & Announcements -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Quick Actions Grid -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <h2 class="text-navy font-semibold text-lg mb-5 flex items-center gap-2">
            <i class="ti ti-bolt text-accent"></i> Quick Actions
          </h2>
          
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <a href="new-case.php" class="group p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-blue-500 text-white flex items-center justify-center group-hover:scale-110 transition">
                <i class="ti ti-file-plus text-lg"></i>
              </div>
              <span class="text-sm font-medium text-navy">New Case</span>
            </a>
            <a href="cases.php" class="group p-4 rounded-xl bg-green-50 hover:bg-green-100 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-green-500 text-white flex items-center justify-center group-hover:scale-110 transition">
                <i class="ti ti-folder text-lg"></i>
              </div>
              <span class="text-sm font-medium text-navy">My Cases</span>
            </a>

            <a href="support.php" class="group p-4 rounded-xl bg-orange-50 hover:bg-orange-100 transition text-center">
              <div class="w-10 h-10 mx-auto mb-2 rounded-lg bg-orange-500 text-white flex items-center justify-center group-hover:scale-110 transition">
                <i class="ti ti-help-circle text-lg"></i>
              </div>
              <span class="text-sm font-medium text-navy">Support</span>
            </a>
          </div>
        </div>

      </div>
    </div>

  </main>
  </div>

  <footer class="py-6 mt-12">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-xs text-gray-500 font-medium">
        © 2026 CaseFlowX
      </p>
    </div>
  </footer>
</body>
</html>
