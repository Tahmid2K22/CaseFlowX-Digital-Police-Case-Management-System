<?php
/**
 * fir_officer_dashboard.php - CaseFlowX
 * Secure dashboard for FIR Officers (role = Officer).
 * SCRUM-57: FIR Officer Login UI & Dashboard
 * SCRUM-58: Secure session management
 */

require_once __DIR__ . '/auth.php';

// Enforce session: only Officer role allowed
check_access(['Officer']);

$lang = $_SESSION['lang'] ?? 'en';
$officerName = $_SESSION['username'] ?? 'Officer';
$officerPhone = '';
$officerNID = '';
$officerDivision = '';
$officerDistrict = '';
$officerEmail = '';

// Fetch live officer details from DB
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $officer = $stmt->fetch();
    if ($officer) {
        $officerName     = $officer['full_name'];
        $officerPhone    = $officer['phone'];
        $officerNID      = $officer['national_id'];
        $officerDivision = $officer['division'];
        $officerDistrict = $officer['district'];
        $officerEmail    = $officer['email'] ?? '';
    }
} catch (PDOException $e) {
    error_log('[CaseFlowX] Officer dashboard DB error: ' . $e->getMessage());
}

// Case Statistics
$totalCases = 0;
$openCases = 0;
$resolvedCases = 0;
$recentCases = [];

try {
    $checkTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cases'")->fetchColumn();
    if ($checkTable) {
        $totalCases    = (int) $pdo->query("SELECT COUNT(*) FROM cases")->fetchColumn();
        $openCases     = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status IN ('open','pending')")->fetchColumn();
        $resolvedCases = (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE status = 'resolved'")->fetchColumn();
        $recentCases   = $pdo->query("SELECT * FROM cases ORDER BY created_at DESC LIMIT 6")->fetchAll();
    }
} catch (PDOException $e) {
    error_log('[CaseFlowX] Case query error: ' . $e->getMessage());
}

$nameParts = array_slice(explode(' ', $officerName), 0, 2);
$initials  = strtoupper(implode('', array_map(fn($w) => $w[0], $nameParts)));
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FIR Officer Dashboard - CaseFlowX</title>
<meta name="description" content="Secure FIR Officer portal on CaseFlowX - manage and track police cases efficiently.">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        navy: '#1B2A4A', navyDark: '#141f36', navy2: '#253554',
        accent: '#1D9E75', 'accent-dark': '#0F6E56', 'accent-light': '#E8F8F3',
      },
      fontFamily: { sans: ['Inter','Segoe UI','sans-serif'] },
    }
  }
}
</script>
<style>
body { font-family:'Inter',sans-serif; background:#F0F4F8; }
.sidebar-link {
  display:flex; align-items:center; gap:10px; padding:10px 16px;
  border-radius:10px; font-size:14px; font-weight:500;
  color:rgba(255,255,255,0.70); transition:all 0.2s ease; text-decoration:none;
}
.sidebar-link:hover { background:rgba(255,255,255,0.08); color:#fff; }
.sidebar-link.active {
  background:rgba(29,158,117,0.25); color:#1D9E75; border-left:3px solid #1D9E75;
}
.stat-card {
  border-radius:16px; padding:24px; transition:transform 0.2s,box-shadow 0.2s;
}
.stat-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,0.12); }
.badge { display:inline-flex; align-items:center; padding:3px 10px;
  border-radius:999px; font-size:11px; font-weight:600; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.animate-fade-in { animation:fadeIn 0.4s ease-out; }
.animate-slide-up { animation:slideUp 0.5s ease-out; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.4)} }
.pulse-dot { animation:pulse-dot 2s infinite; }
::-webkit-scrollbar { width:6px; }
::-webkit-scrollbar-thumb { background:#CBD5E1; border-radius:4px; }
</style>
</head>
<body class="h-screen flex overflow-hidden">

<!-- SIDEBAR -->
<aside class="w-64 h-screen sticky top-0 bg-navyDark flex flex-col flex-shrink-0 shadow-xl overflow-y-auto" id="sidebar">

  <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
    <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center text-white shadow-lg">
      <i class="ti ti-shield-check text-lg"></i>
    </div>
    <div>
      <span class="text-white font-bold text-base tracking-tight">CaseFlowX</span>
      <p class="text-white/40 text-[10px] font-medium tracking-wider uppercase">FIR Portal</p>
    </div>
  </div>

  <div class="mx-4 mt-5 mb-4 p-3 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white text-sm font-bold shadow">
      <?php echo htmlspecialchars($initials); ?>
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-white text-xs font-semibold truncate"><?php echo htmlspecialchars($officerName); ?></p>
      <p class="text-accent text-[10px] font-medium flex items-center gap-1">
        <i class="ti ti-badge"></i> FIR Officer
      </p>
    </div>
    <div class="w-2 h-2 rounded-full bg-accent flex-shrink-0 pulse-dot"></div>
  </div>

  <nav class="flex-1 px-3 space-y-1">
    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 px-3 pt-2 pb-1">Main</p>
    <a href="fir_officer_dashboard.php" class="sidebar-link active"><i class="ti ti-layout-dashboard text-base"></i> Dashboard</a>
    <a href="cases.php" class="sidebar-link"><i class="ti ti-file-description text-base"></i> FIR Cases</a>

    <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 px-3 pt-4 pb-1">Account</p>
    <a href="profile.php" class="sidebar-link"><i class="ti ti-user-circle text-base"></i> My Profile</a>
    <a href="support.php" class="sidebar-link"><i class="ti ti-headset text-base"></i> Support</a>
  </nav>

  <div class="px-3 pb-5 pt-4 border-t border-white/10 bg-navyDark">
    <a href="login.php?logout=1" id="btn-logout"
       class="sidebar-link text-red-400 hover:text-red-300 hover:bg-red-500/10">
      <i class="ti ti-logout text-base"></i> Sign Out
    </a>
  </div>
</aside>

<!-- MAIN -->
<main class="flex-1 flex flex-col overflow-auto">

  <!-- Top Bar -->
  <header class="bg-white border-b border-slate-200 px-6 py-3.5 flex items-center justify-between shadow-sm sticky top-0 z-10">
    <div class="flex items-center gap-3">
      <button id="btn-toggle-sidebar" class="text-gray-500 hover:text-navy transition-colors">
        <i class="ti ti-menu-2 text-xl"></i>
      </button>
      <div>
        <h1 class="text-navy text-base font-bold">FIR Officer Dashboard</h1>
        <p class="text-gray-400 text-xs"><i class="ti ti-calendar-event text-xs"></i> <?php echo date('l, d F Y'); ?></p>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <button class="relative w-9 h-9 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center justify-center text-gray-500 hover:text-navy transition-all">
        <i class="ti ti-bell text-base"></i>
        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-accent rounded-full pulse-dot"></span>
      </button>
      <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white text-xs font-bold shadow">
          <?php echo htmlspecialchars($initials); ?>
        </div>
        <div class="hidden sm:block">
          <p class="text-navy text-xs font-semibold"><?php echo htmlspecialchars($officerName); ?></p>
          <p class="text-gray-400 text-[10px]">FIR Officer</p>
        </div>
      </div>
    </div>
  </header>

  <!-- Page Body -->
  <div class="flex-1 p-6 space-y-6 animate-fade-in">

    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-navy via-navy2 to-[#1a3060] rounded-2xl p-6 text-white relative overflow-hidden shadow-lg">
      <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <p class="text-white/60 text-sm font-medium mb-1 flex items-center gap-1.5">
            <i class="ti ti-shield-check text-accent"></i> Secure FIR Portal
          </p>
          <h2 class="text-2xl font-bold">Welcome back, <?php echo htmlspecialchars(explode(' ', $officerName)[0]); ?>!</h2>
          <p class="text-white/55 text-sm mt-1">
            <?php echo htmlspecialchars($officerDistrict ? $officerDistrict . ' Police Station' : 'CaseFlowX Digital Police Portal'); ?>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <a href="cases.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border border-white/20">
            <i class="ti ti-list"></i> All Cases
          </a>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-slide-up">
      <div class="stat-card bg-white border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
            <i class="ti ti-files text-xl text-blue-600"></i>
          </div>
          <span class="badge bg-blue-50 text-blue-600">Total</span>
        </div>
        <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $totalCases; ?>"><?php echo $totalCases; ?></p>
        <p class="text-gray-500 text-sm mt-1 font-medium">Total FIR Cases</p>
      </div>

      <div class="stat-card bg-white border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
            <i class="ti ti-clock-hour-4 text-xl text-amber-600"></i>
          </div>
          <span class="badge bg-amber-50 text-amber-600">Active</span>
        </div>
        <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $openCases; ?>"><?php echo $openCases; ?></p>
        <p class="text-gray-500 text-sm mt-1 font-medium">Active Cases</p>
      </div>

      <div class="stat-card bg-white border border-slate-100 shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-11 h-11 rounded-xl bg-[#E8F8F3] flex items-center justify-center">
            <i class="ti ti-circle-check text-xl text-accent"></i>
          </div>
          <span class="badge bg-[#E8F8F3] text-accent">Closed</span>
        </div>
        <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $resolvedCases; ?>"><?php echo $resolvedCases; ?></p>
        <p class="text-gray-500 text-sm mt-1 font-medium">Resolved Cases</p>
      </div>

      <div class="stat-card bg-gradient-to-br from-navy to-navy2 text-white shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
            <i class="ti ti-lock-open text-xl text-white"></i>
          </div>
          <span class="badge bg-accent/20 text-accent">Secured</span>
        </div>
        <p class="text-xl font-bold"><?php echo htmlspecialchars($officerDistrict ?: 'N/A'); ?></p>
        <p class="text-white/55 text-sm mt-1 font-medium">Assigned District</p>
      </div>
    </div>

    <!-- Cases Table + Profile -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Cases Table -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 class="text-navy font-bold text-sm">Recent FIR Cases</h3>
            <p class="text-gray-400 text-xs mt-0.5">Latest case records in the system</p>
          </div>
          <a href="cases.php" class="text-accent text-xs font-semibold hover:underline flex items-center gap-1">
            View all <i class="ti ti-arrow-right text-xs"></i>
          </a>
        </div>
        <div class="overflow-x-auto">
          <?php if (empty($recentCases)): ?>
          <div class="px-6 py-12 text-center">
            <i class="ti ti-file-off text-5xl text-gray-200 mb-3 block"></i>
            <p class="text-gray-400 text-sm font-medium">No FIR cases found in the system.</p>
            <a href="new-case.php" class="inline-flex items-center gap-1 text-accent text-xs font-semibold mt-2 hover:underline">
              <i class="ti ti-plus"></i> File first FIR
            </a>
          </div>
          <?php else: ?>
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-slate-50 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                <th class="text-left px-6 py-3">Case ID</th>
                <th class="text-left px-4 py-3">Type</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-left px-4 py-3">Date</th>
                <th class="text-left px-4 py-3">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <?php foreach ($recentCases as $case):
                $sc = ['open'=>'bg-blue-50 text-blue-600','pending'=>'bg-amber-50 text-amber-600',
                       'in_progress'=>'bg-purple-50 text-purple-600','resolved'=>'bg-green-50 text-green-600',
                       'closed'=>'bg-gray-100 text-gray-500'][$case['status'] ?? 'open'] ?? 'bg-gray-100 text-gray-500';
                $dt = isset($case['created_at']) ? date('d M Y', strtotime($case['created_at'])) : '-';
              ?>
              <tr class="hover:bg-slate-50/60 transition-colors">
                <td class="px-6 py-3.5 font-mono text-xs text-navy font-semibold">#<?php echo htmlspecialchars($case['id'] ?? '-'); ?></td>
                <td class="px-4 py-3.5 text-gray-600"><?php echo htmlspecialchars($case['case_type'] ?? $case['type'] ?? 'FIR'); ?></td>
                <td class="px-4 py-3.5"><span class="badge <?php echo $sc; ?>"><?php echo ucfirst(str_replace('_',' ',$case['status'] ?? 'open')); ?></span></td>
                <td class="px-4 py-3.5 text-gray-400 text-xs"><?php echo $dt; ?></td>
                <td class="px-4 py-3.5">
                  <a href="case-details.php?id=<?php echo htmlspecialchars($case['id']); ?>" class="text-accent hover:text-[#0F6E56] text-xs font-semibold hover:underline">View</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Officer Info + Quick Actions -->
      <div class="space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="bg-gradient-to-r from-navy to-navy2 px-5 py-4 flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white font-bold shadow-lg">
              <?php echo htmlspecialchars($initials); ?>
            </div>
            <div>
              <p class="text-white font-bold text-sm"><?php echo htmlspecialchars($officerName); ?></p>
              <p class="text-white/50 text-xs"><i class="ti ti-badge"></i> FIR Officer</p>
            </div>
          </div>
          <div class="px-5 py-4 space-y-2 text-sm">
            <?php foreach ([
              ['ti-phone','Phone',$officerPhone],
              ['ti-id-badge','NID',$officerNID],
              ['ti-mail','Email',$officerEmail ?: '-'],
              ['ti-map-pin','District',$officerDistrict],
              ['ti-building','Division',$officerDivision],
            ] as [$ico,$lbl,$val]): ?>
            <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors">
              <div class="w-8 h-8 rounded-lg bg-[#E8F8F3] flex items-center justify-center flex-shrink-0">
                <i class="ti <?php echo $ico; ?> text-sm text-accent"></i>
              </div>
              <div>
                <p class="text-gray-400 text-[10px] font-semibold uppercase tracking-wide"><?php echo $lbl; ?></p>
                <p class="text-navy text-xs font-semibold"><?php echo htmlspecialchars($val ?: '-'); ?></p>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="px-5 pb-4">
            <a href="profile.php" class="w-full inline-flex items-center justify-center gap-2 border border-accent text-accent hover:bg-accent hover:text-white px-4 py-2.5 rounded-xl text-xs font-semibold transition-all">
              <i class="ti ti-user-edit"></i> Edit Profile
            </a>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
          <h3 class="text-navy font-bold text-sm mb-3">Quick Actions</h3>
          <div class="space-y-2">
            <?php foreach ([
              ['new-case.php','ti-plus','bg-[#E8F8F3] text-accent','File New FIR'],
              ['cases.php','ti-file-search','bg-blue-50 text-blue-600','Search Cases'],
              ['profile.php','ti-user-circle','bg-purple-50 text-purple-600','Update Profile'],
              ['support.php','ti-headset','bg-amber-50 text-amber-600','Get Support'],
            ] as [$href,$ico,$cls,$lbl]): ?>
            <a href="<?php echo $href; ?>" class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 border border-transparent hover:border-slate-100 transition-all group">
              <div class="w-8 h-8 rounded-lg <?php echo $cls; ?> flex items-center justify-center flex-shrink-0">
                <i class="ti <?php echo $ico; ?> text-sm"></i>
              </div>
              <span class="text-navy text-xs font-semibold group-hover:text-accent transition-colors"><?php echo $lbl; ?></span>
              <i class="ti ti-chevron-right text-xs text-gray-300 ml-auto group-hover:text-accent transition-colors"></i>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Security Notice -->
    <div class="bg-navy/5 border border-navy/10 rounded-xl px-5 py-3 flex items-center gap-3 text-sm text-navy/70">
      <i class="ti ti-lock text-accent text-base flex-shrink-0"></i>
      <span>
        Your session is <strong class="text-accent">securely authenticated</strong>.
        All actions are logged and audited as per Bangladesh Police digital policy.
        <a href="login.php?logout=1" class="text-red-500 font-semibold ml-1 hover:underline">Sign out</a> when done.
      </span>
    </div>

  </div><!-- /page body -->

  <footer class="py-6 mt-auto">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-xs text-gray-500 font-medium">
        © 2026 CaseFlowX
      </p>
    </div>
  </footer>
</main>

<script>
// Sign Out: direct redirect, no confirmation dialog

document.getElementById('btn-toggle-sidebar')?.addEventListener('click', function() {
  const s = document.getElementById('sidebar');
  s.style.width = s.style.width === '0px' ? '256px' : '0px';
  s.style.overflow = s.style.width === '0px' ? 'hidden' : '';
});

// Animate counters
document.querySelectorAll('.counter').forEach(el => {
  const target = parseInt(el.dataset.target);
  if (!target) return;
  let cur = 0;
  const step = Math.ceil(target / 20);
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = cur;
    if (cur >= target) clearInterval(t);
  }, 40);
});
</script>
</body>
</html>
