<?php
/**
 * officer-dashboard.php — CaseFlowX FIR Officer Dashboard
 * Displays assigned FIRs, stats, and unassigned FIR management.
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['officer_id'])) {
    header('Location: officer-login.php');
    exit;
}

$officer = require_officer();

$db = get_db();

// ── Fetch Stats ─────────────────────────────────────────────────────────
$totalAssigned = 0;
$unassignedCount = 0;
$underReviewCount = 0;
$registeredCount = 0;

try {
    // Total FIRs assigned to this officer
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM fir_records WHERE officer_id = ?");
    $stmt->execute([$officer['id']]);
    $totalAssigned = $stmt->fetch()['cnt'];

    // Unassigned FIRs (officer_id = 0 or null)
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM fir_records WHERE officer_id = 0 OR officer_id IS NULL");
    $stmt->execute();
    $unassignedCount = $stmt->fetch()['cnt'];

    // Under Review FIRs assigned to this officer
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM fir_records WHERE officer_id = ? AND status = 'Under Review'");
    $stmt->execute([$officer['id']]);
    $underReviewCount = $stmt->fetch()['cnt'];

    // Registered FIRs assigned to this officer
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM fir_records WHERE officer_id = ? AND status = 'Registered'");
    $stmt->execute([$officer['id']]);
    $registeredCount = $stmt->fetch()['cnt'];
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// ── Fetch Assigned FIRs ────────────────────────────────────────────────
$assignedFirs = [];
try {
    $stmt = $db->prepare("
        SELECT f.*, 
               (SELECT COUNT(*) FROM fir_evidence WHERE fir_id = f.id) as evidence_count
        FROM fir_records f
        WHERE f.officer_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$officer['id']]);
    $assignedFirs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard FIRs error: " . $e->getMessage());
}

// ── Fetch Unassigned FIRs ──────────────────────────────────────────────
$unassignedFirs = [];
try {
    $stmt = $db->prepare("
        SELECT f.*,
               (SELECT COUNT(*) FROM fir_evidence WHERE fir_id = f.id) as evidence_count
        FROM fir_records f
        WHERE f.officer_id = 0 OR f.officer_id IS NULL
        ORDER BY f.created_at DESC
    ");
    $stmt->execute();
    $unassignedFirs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard unassigned FIRs error: " . $e->getMessage());
}

// ── Fetch available officers for assignment dropdown ──────────────────
$allOfficers = [];
try {
    $stmt = $db->prepare("
        SELECT id, badge_number, full_name, role 
        FROM officers 
        WHERE status = 'active' AND id != ?
        ORDER BY full_name ASC
    ");
    $stmt->execute([$officer['id']]);
    $allOfficers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard officers list error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIR Officer Dashboard — CaseFlowX</title>
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
<body class="min-h-screen bg-[#F4F6F9]">

    <!-- ── Header ────────────────────────────────────────────────────── -->
    <header class="bg-navy text-white">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center">
                    <i class="ti ti-shield text-xl"></i>
                </div>
                <div>
                    <h1 class="font-bold text-lg leading-tight">FIR Officer Dashboard</h1>
                    <p class="text-white/55 text-xs">CaseFlowX — Digital FIR Filing System</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="officer-profile.php" class="text-sm text-white/70 hover:text-white flex items-center gap-1.5 transition">
                    <i class="ti ti-user text-base"></i>
                    <span class="hidden sm:inline">Profile</span>
                </a>
                <a href="logout.php" class="text-sm text-white/70 hover:text-red-400 flex items-center gap-1.5 transition">
                    <i class="ti ti-logout text-base"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </header>

    <!-- ── Main Content ──────────────────────────────────────────────── -->
    <main class="max-w-7xl mx-auto px-4 py-6">

        <!-- Breadcrumb -->
        <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
            <a href="dashboard1.php" class="hover:text-accent transition-colors flex items-center gap-1">
                <i class="ti ti-home text-base"></i> Home
            </a>
            <i class="ti ti-chevron-right text-xs"></i>
            <span class="text-gray-700 font-medium">Officer Dashboard</span>
        </div>

        <!-- Welcome Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-accent/10 flex items-center justify-center text-accent text-3xl">
                    <i class="ti ti-user-circle"></i>
                </div>
                <div>
                    <h2 class="text-navy text-xl font-bold">Welcome, <?= htmlspecialchars($officer['full_name']) ?></h2>
                    <p class="text-gray-500 text-sm mt-0.5">
                        Badge No: <span class="font-semibold text-navy"><?= htmlspecialchars($officer['badge_number']) ?></span>
                        &nbsp;·&nbsp;
                        Station: <span class="font-semibold text-navy"><?= htmlspecialchars($officer['station_code']) ?></span>
                        &nbsp;·&nbsp;
                        Role: <span class="font-semibold text-accent"><?= htmlspecialchars($officer['role']) ?></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Cards (4 columns) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Total FIRs Assigned -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-navy/10 flex items-center justify-center text-navy text-2xl flex-shrink-0">
                    <i class="ti ti-file-text"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Total FIRs Assigned</p>
                    <p class="text-navy text-2xl font-bold mt-1"><?= number_format($totalAssigned) ?></p>
                </div>
            </div>

            <!-- Unassigned FIRs -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-orange-100 flex items-center justify-center text-orange-600 text-2xl flex-shrink-0">
                    <i class="ti ti-inbox"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Unassigned FIRs</p>
                    <p class="text-orange-600 text-2xl font-bold mt-1"><?= number_format($unassignedCount) ?></p>
                </div>
            </div>

            <!-- Under Review -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 text-2xl flex-shrink-0">
                    <i class="ti ti-eye"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs font-medium uppercase tracking-wide">Under Review</p>
                    <p class="text-blue-600 text-2xl font-bold mt-1"><?= number_format($underReviewCount) ?></p>
                </div>
            </div>

            <!-- Registered -->
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

        <!-- Unassigned FIRs Section (NEW arrivals from citizens) -->
        <?php if (count($unassignedFirs) > 0): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
            <div class="bg-orange-50 border-b border-orange-100 px-6 py-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center text-white text-lg">
                    <i class="ti ti-inbox"></i>
                </div>
                <div>
                    <h3 class="text-navy font-bold">Unassigned FIRs — New Arrivals</h3>
                    <p class="text-gray-500 text-xs">FIRs waiting to be claimed by an officer</p>
                </div>
                <span class="ml-auto bg-orange-500 text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    <?= count($unassignedFirs) ?>
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">FIR Number</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Complainant</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Incident Date</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($unassignedFirs as $fir): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors" id="unassigned-row-<?= $fir['id'] ?>">
                            <td class="px-6 py-3.5 font-medium text-navy"><?= htmlspecialchars($fir['fir_number']) ?></td>
                            <td class="px-6 py-3.5 text-gray-700"><?= htmlspecialchars($fir['complainant_name']) ?></td>
                            <td class="px-6 py-3.5 text-gray-600"><?= htmlspecialchars($fir['incident_date']) ?></td>
                            <td class="px-6 py-3.5 text-gray-600 max-w-[200px] truncate" title="<?= htmlspecialchars($fir['incident_location']) ?>">
                                <?= htmlspecialchars($fir['incident_location']) ?>
                            </td>
                            <td class="px-6 py-3.5">
                                <?php
                                $priority_class = match($fir['priority']) {
                                    'high' => 'bg-red-100 text-red-700',
                                    'medium' => 'bg-yellow-100 text-yellow-700',
                                    'low' => 'bg-gray-100 text-gray-600',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $priority_class ?>">
                                    <?= ucfirst(htmlspecialchars($fir['priority'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <!-- Accept (assign to self) -->
                                    <button onclick="acceptFir(<?= $fir['id'] ?>)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent hover:bg-accent-dark text-white text-xs font-medium transition"
                                            id="accept-btn-<?= $fir['id'] ?>">
                                        <i class="ti ti-check text-sm"></i> Accept
                                    </button>

                                    <!-- Assign to Officer dropdown -->
                                    <div class="relative inline-block">
                                        <select onchange="assignFir(<?= $fir['id'] ?>, this.value)"
                                                class="appearance-none bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium pl-3 pr-8 py-1.5 rounded-lg cursor-pointer transition border-0 focus:outline-none focus:ring-2 focus:ring-accent/30">
                                            <option value="">Assign to Officer...</option>
                                            <?php foreach ($allOfficers as $opt): ?>
                                                <option value="<?= $opt['id'] ?>">
                                                    <?= htmlspecialchars($opt['full_name']) ?> (<?= htmlspecialchars($opt['badge_number']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="ti ti-chevron-down absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                    </div>

                                    <!-- View Details -->
                                    <a href="fir-details.php?id=<?= $fir['id'] ?>"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium transition">
                                        <i class="ti ti-eye text-sm"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- My Assigned FIRs Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-navy px-6 py-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-accent flex items-center justify-center text-white text-lg">
                    <i class="ti ti-files"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold">My Assigned FIRs</h3>
                    <p class="text-white/55 text-xs">All FIRs assigned to your account</p>
                </div>
                <span class="ml-auto bg-white/20 text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    <?= count($assignedFirs) ?>
                </span>
            </div>

            <?php if (empty($assignedFirs)): ?>
                <div class="py-16 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4 text-gray-400 text-3xl">
                        <i class="ti ti-file-x"></i>
                    </div>
                    <h4 class="text-gray-600 font-medium mb-1">No FIRs Assigned</h4>
                    <p class="text-gray-400 text-sm">Accept unassigned FIRs above to get started.</p>
                </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">FIR Number</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Complainant</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Incident Date</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Evidence</th>
                            <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($assignedFirs as $fir): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-3.5 font-medium text-navy"><?= htmlspecialchars($fir['fir_number']) ?></td>
                            <td class="px-6 py-3.5 text-gray-700"><?= htmlspecialchars($fir['complainant_name']) ?></td>
                            <td class="px-6 py-3.5 text-gray-600"><?= htmlspecialchars($fir['incident_date']) ?></td>
                            <td class="px-6 py-3.5 text-gray-600 max-w-[180px] truncate" title="<?= htmlspecialchars($fir['incident_location']) ?>">
                                <?= htmlspecialchars($fir['incident_location']) ?>
                            </td>
                            <td class="px-6 py-3.5">
                                <?php
                                $status_class = match($fir['status']) {
                                    'Draft' => 'bg-gray-100 text-gray-600',
                                    'Submitted' => 'bg-blue-100 text-blue-700',
                                    'Under Review' => 'bg-orange-100 text-orange-700',
                                    'Registered' => 'bg-green-100 text-green-700',
                                    'Rejected' => 'bg-red-100 text-red-700',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $status_class ?>">
                                    <?= htmlspecialchars($fir['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <?php
                                $priority_class = match($fir['priority']) {
                                    'high' => 'bg-red-100 text-red-700',
                                    'medium' => 'bg-yellow-100 text-yellow-700',
                                    'low' => 'bg-gray-100 text-gray-600',
                                    default => 'bg-gray-100 text-gray-600',
                                };
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $priority_class ?>">
                                    <?= ucfirst(htmlspecialchars($fir['priority'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <span class="inline-flex items-center gap-1 text-gray-500 text-xs">
                                    <i class="ti ti-paperclip text-sm"></i>
                                    <?= intval($fir['evidence_count']) ?> file(s)
                                </span>
                            </td>
                            <td class="px-6 py-3.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <a href="fir-details.php?id=<?= $fir['id'] ?>"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-navy hover:bg-navy/90 text-white text-xs font-medium transition">
                                        <i class="ti ti-eye text-sm"></i> View
                                    </a>
                                    <?php if ($fir['status'] === 'Draft' || $fir['status'] === 'Submitted'): ?>
                                    <a href="file-fir.php?edit=<?= $fir['id'] ?>"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent hover:bg-accent-dark text-white text-xs font-medium transition">
                                        <i class="ti ti-edit text-sm"></i> Edit
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- ── Footer ────────────────────────────────────────────────────── -->
    <footer class="mt-8 py-4 text-center text-xs text-gray-400">
        <p>CaseFlowX — Digital FIR Filing System &copy; <?= date('Y') ?></p>
    </footer>

</body>
</html>

<script>
/**
 * Accept an unassigned FIR (assign to self)
 */
async function acceptFir(firId) {
    const btn = document.getElementById('accept-btn-' + firId);
    if (!btn) return;

    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-sm"></i> Accepting...';

    try {
        const formData = new FormData();
        formData.append('action', 'accept');
        formData.append('fir_id', firId);

        const resp = await fetch('api/fir.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        if (data.success) {
            // Remove the row from unassigned table
            const row = document.getElementById('unassigned-row-' + firId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            // Reload page after brief delay to refresh all data
            setTimeout(() => location.reload(), 500);
        } else {
            alert(data.message || 'Failed to accept FIR');
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        }
    } catch (err) {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHTML;
    }
}

/**
 * Assign an unassigned FIR to a specific officer
 */
async function assignFir(firId, officerId) {
    if (!officerId) return;

    try {
        const formData = new FormData();
        formData.append('action', 'accept');
        formData.append('fir_id', firId);
        formData.append('assign_to_officer', officerId);

        const resp = await fetch('api/fir.php', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();

        if (data.success) {
            // Remove the row from unassigned table
            const row = document.getElementById('unassigned-row-' + firId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
            }
            setTimeout(() => location.reload(), 500);
        } else {
            alert(data.message || 'Failed to assign FIR');
            location.reload();
        }
    } catch (err) {
        alert('Network error. Please try again.');
        location.reload();
    }
}
</script>