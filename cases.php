<?php
/**
 * cases.php — CaseFlowX
 * Lists all cases for the logged-in citizen.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$db = get_db();
$stmt = $db->prepare('
    SELECT * FROM cases
    WHERE citizen_id = :citizen_id
    ORDER BY created_at DESC
');
$stmt->execute([':citizen_id' => $_SESSION['citizen_id']]);
$cases = $stmt->fetchAll();

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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Cases — CaseFlowX</title>
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-navy">My Cases</h1>
        <p class="text-gray-500 mt-1">View and track all your filed complaints.</p>
      </div>
      <a href="new-case.php" class="inline-flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
        <i class="ti ti-plus text-base"></i> New Case
      </a>
    </div>

    <!-- Cases Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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
            <?php foreach ($cases as $case): ?>
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
            <?php if (empty($cases)): ?>
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
