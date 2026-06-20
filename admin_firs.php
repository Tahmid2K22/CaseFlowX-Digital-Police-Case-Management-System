<?php
// admin_firs.php - Admin FIR Management Panel
require_once __DIR__ . '/auth.php';

// RBAC Check: Restrict to Admin only
check_access(['Admin']);

$msg_success = '';
$msg_error = '';

$lang = $_SESSION['lang'] ?? 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'bn'], true)) {
    $lang = $_GET['lang'];
    $_SESSION['lang'] = $lang;
}

// Translations
$t = [
    'en' => [
        'title' => 'FIR Management — CaseFlowX',
        'heading' => 'FIR Administration',
        'subheading' => 'Review, approve, or reject digital First Information Reports filed by officers.',
        'stat_total' => 'Total FIRs',
        'stat_pending' => 'Pending Review',
        'stat_approved' => 'Registered & Active',
        'stat_rejected' => 'Rejected FIRs',
        'search_placeholder' => 'Search by FIR #, complainant, NID...',
        'filter_status' => 'Status Filter',
        'filter_priority' => 'Priority Filter',
        'all_statuses' => 'All Statuses',
        'all_priorities' => 'All Priorities',
        'tbl_fir_number' => 'FIR Number',
        'tbl_complainant' => 'Complainant & NID',
        'tbl_station' => 'Station',
        'tbl_priority' => 'Priority',
        'tbl_status' => 'Status',
        'tbl_date' => 'Filing Date',
        'tbl_actions' => 'Actions',
        'btn_logout' => 'Log Out',
        'btn_cancel' => 'Cancel',
        'btn_submit_reject' => 'Confirm Rejection',
        'reject_title' => 'Reject FIR Complaint',
        'reject_label' => 'Reason for Rejection',
        'reject_placeholder' => 'Enter explanation or citation of legal code...',
        'toast_success' => 'Operation completed successfully.',
        'no_firs' => 'No FIR records found.'
    ],
    'bn' => [
        'title' => 'এফআইআর ব্যবস্থাপনা — কেসফ্লোএক্স',
        'heading' => 'এফআইআর প্রশাসন ও নিয়ন্ত্রণ',
        'subheading' => 'কর্মকর্তাদের দাখিলকৃত ডিজিটাল এফআইআর পর্যালোচনা, অনুমোদন বা প্রত্যাখ্যান করুন।',
        'stat_total' => 'মোট এফআইআর',
        'stat_pending' => 'পর্যালোচনার অপেক্ষায়',
        'stat_approved' => 'অনুমোদিত ও নিবন্ধিত',
        'stat_rejected' => 'প্রত্যাখ্যাত এফআইআর',
        'search_placeholder' => 'এফআইআর নম্বর, অভিযোগকারী, এনআইডি দিয়ে খুঁজুন...',
        'filter_status' => 'অবস্থা ফিল্টার',
        'filter_priority' => 'অগ্রাধিকার ফিল্টার',
        'all_statuses' => 'সকল অবস্থা',
        'all_priorities' => 'সকল অগ্রাধিকার',
        'tbl_fir_number' => 'এফআইআর নম্বর',
        'tbl_complainant' => 'অভিযোগকারী ও এনআইডি',
        'tbl_station' => 'থানা কোড',
        'tbl_priority' => 'অগ্রাধিকার',
        'tbl_status' => 'অবস্থা',
        'tbl_date' => 'দাখিলের তারিখ',
        'tbl_actions' => 'কার্যক্রম',
        'btn_logout' => 'লগআউট',
        'btn_cancel' => 'বাতিল',
        'btn_submit_reject' => 'প্রত্যাখ্যান নিশ্চিত করুন',
        'reject_title' => 'এফআইআর প্রত্যাখ্যান করুন',
        'reject_label' => 'প্রত্যাখ্যানের কারণ',
        'reject_placeholder' => 'আইনি ধারা বা ব্যাখ্যা লিখুন...',
        'toast_success' => 'কার্যক্রমটি সফলভাবে সম্পন্ন হয়েছে।',
        'no_firs' => 'কোনো এফআইআর রেকর্ড পাওয়া যায়নি।'
    ]
];

$cur = $t[$lang];

$db = get_db();

// Handle search and filters
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_priority = trim($_GET['priority'] ?? '');

// Base Query
$query = "
    SELECT f.*, c.id as case_id
    FROM fir_records f
    LEFT JOIN cases c ON f.fir_number = c.fir_number
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (f.fir_number LIKE ? OR f.complainant_name LIKE ? OR f.complainant_nid LIKE ?)";
    $bindVal = "%{$search}%";
    $params[] = $bindVal;
    $params[] = $bindVal;
    $params[] = $bindVal;
}

if ($filter_status !== '') {
    $query .= " AND f.status = ?";
    $params[] = $filter_status;
}

if ($filter_priority !== '') {
    $query .= " AND f.priority = ?";
    $params[] = $filter_priority;
}

$query .= " ORDER BY f.created_at DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $firs = $stmt->fetchAll();
} catch (PDOException $e) {
    $firs = [];
    $msg_error = "Failed to query FIR records: " . $e->getMessage();
}

// Stats queries
$totalFIRs = 0;
$pendingReview = 0;
$registeredCount = 0;
$rejectedCount = 0;

try {
    $totalFIRs = (int)$db->query("SELECT COUNT(*) FROM fir_records")->fetchColumn();
    $pendingReview = (int)$db->query("SELECT COUNT(*) FROM fir_records WHERE status = 'Submitted' OR status = 'Under Review'")->fetchColumn();
    $registeredCount = (int)$db->query("SELECT COUNT(*) FROM fir_records WHERE status = 'Registered'")->fetchColumn();
    $rejectedCount = (int)$db->query("SELECT COUNT(*) FROM fir_records WHERE status = 'Rejected'")->fetchColumn();
} catch (PDOException $e) {
    // Ignore stats queries errors
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cur['title']; ?> - CaseFlowX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#1B2A4A',
                        accent: '#1D9E75',
                        'accent-dark': '#0F6E56',
                        'accent-light': '#E8F8F3',
                        navyDark: '#141f36',
                        navy2: '#253554',
                    },
                    fontFamily: {
                        sans: ['Inter', 'Segoe UI', 'sans-serif'],
                        bengali: ['Hind Siliguri', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: <?php echo $lang === 'bn' ? "'Hind Siliguri', 'Inter', sans-serif" : "'Inter', sans-serif"; ?>;
            background: #F0F4F8;
        }
        .sidebar-link {
            display: flex; align-items: center; gap: 10px; padding: 10px 16px;
            border-radius: 10px; font-size: 14px; font-weight: 500;
            color: rgba(255,255,255,0.70); transition: all 0.2s ease; text-decoration: none;
        }
        .sidebar-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .sidebar-link.active {
            background: rgba(29,158,117,0.25); color: #1D9E75; border-left: 3px solid #1D9E75;
        }
        .stat-card {
            border-radius: 16px; padding: 24px; transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.12); }
        .badge { display: inline-flex; align-items: center; padding: 3px 10px;
            border-radius: 999px; font-size: 11px; font-weight: 600; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.4)} }
        .pulse-dot { animation: pulse-dot 2s infinite; }
    </style>
</head>
<body class="h-screen flex overflow-hidden">

<?php
$adminName = $_SESSION['username'] ?? 'Admin';
$nameParts = array_slice(explode(' ', $adminName), 0, 2);
$initials  = strtoupper(implode('', array_map(fn($w) => !empty($w) ? $w[0] : '', $nameParts)));
?>

<!-- SIDEBAR -->
<aside class="w-64 h-screen sticky top-0 bg-navyDark flex flex-col flex-shrink-0 shadow-xl overflow-y-auto" id="sidebar">
    <div class="px-6 py-5 border-b border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center text-white shadow-lg">
            <i class="ti ti-shield-check text-lg"></i>
        </div>
        <div>
            <span class="text-white font-bold text-base tracking-tight">CaseFlowX</span>
            <p class="text-white/40 text-[10px] font-medium tracking-wider uppercase">Admin Portal</p>
        </div>
    </div>

    <div class="mx-4 mt-5 mb-4 p-3 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white text-sm font-bold shadow">
            <?php echo htmlspecialchars($initials); ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white text-xs font-semibold truncate"><?php echo htmlspecialchars($adminName); ?></p>
            <p class="text-accent text-[10px] font-medium flex items-center gap-1">
                <i class="ti ti-user-shield"></i> Admin
            </p>
        </div>
        <div class="w-2 h-2 rounded-full bg-accent flex-shrink-0 pulse-dot"></div>
    </div>

    <nav class="flex-1 px-3 space-y-1">
        <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 px-3 pt-2 pb-1">Main</p>
        <a href="admin_users.php" class="sidebar-link"><i class="ti ti-users text-base"></i> Manage Users</a>
        <a href="register.php" class="sidebar-link"><i class="ti ti-user-plus text-base"></i> Register User</a>
        <a href="admin_firs.php" class="sidebar-link active"><i class="ti ti-file-description text-base"></i> Manage FIRs</a>

        <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 px-3 pt-4 pb-1">Account</p>
        <a href="profile.php" class="sidebar-link"><i class="ti ti-user-circle text-base"></i> My Profile</a>
        <a href="support.php" class="sidebar-link"><i class="ti ti-headset text-base"></i> Support</a>
    </nav>

    <div class="px-3 pb-5 pt-4 border-t border-white/10 bg-navyDark">
        <a href="login.php?logout=1" id="btn-logout"
           class="sidebar-link text-red-400 hover:text-red-300 hover:bg-red-500/10">
            <i class="ti ti-logout text-base"></i> <?php echo $cur['btn_logout']; ?>
        </a>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="flex-1 flex flex-col overflow-auto">
    <!-- Sticky Top Bar -->
    <header class="bg-white border-b border-slate-200 px-6 py-3.5 flex items-center justify-between shadow-sm sticky top-0 z-10">
        <div class="flex items-center gap-3">
            <button id="btn-toggle-sidebar" class="text-gray-500 hover:text-navy transition-colors">
                <i class="ti ti-menu-2 text-xl"></i>
            </button>
            <div>
                <h1 class="text-navy text-base font-bold"><?php echo $cur['heading']; ?></h1>
                <p class="text-gray-400 text-xs"><i class="ti ti-calendar-event text-xs"></i> <?php echo date('l, d F Y'); ?></p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Language Switcher in Header -->
            <div class="flex items-center gap-2 mr-2">
                <i class="ti ti-world text-gray-400 text-base"></i>
                <a href="?lang=en<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?><?php echo !empty($filter_priority) ? '&priority='.urlencode($filter_priority) : ''; ?>" 
                   class="px-2.5 py-0.5 text-xs rounded-full border border-slate-200 transition-all <?php echo $lang === 'en' ? 'bg-accent text-white font-semibold border-accent' : 'text-gray-500 hover:bg-slate-100 hover:text-navy'; ?>">English</a>
                <a href="?lang=bn<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?><?php echo !empty($filter_priority) ? '&priority='.urlencode($filter_priority) : ''; ?>" 
                   class="px-2.5 py-0.5 text-xs rounded-full border border-slate-200 transition-all <?php echo $lang === 'bn' ? 'bg-accent text-white font-semibold border-accent' : 'text-gray-500 hover:bg-slate-100 hover:text-navy'; ?>">বাংলা</a>
            </div>

            <button class="relative w-9 h-9 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 flex items-center justify-center text-gray-500 hover:text-navy transition-all">
                <i class="ti ti-bell text-base"></i>
                <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-accent rounded-full pulse-dot"></span>
            </button>
        </div>
    </header>

    <div class="p-6 space-y-6 flex-1 max-w-7xl w-full mx-auto">
        <!-- Error / Success Notices -->
        <?php if(!empty($msg_error)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl flex items-center gap-3 text-red-700 animate-slide-up">
                <i class="ti ti-alert-triangle text-xl"></i>
                <span><?php echo $msg_error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Stat 1 -->
            <div class="bg-white border border-slate-100 shadow-sm stat-card flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider"><?php echo $cur['stat_total']; ?></p>
                    <h3 class="text-navy text-2xl font-extrabold mt-1"><?php echo $totalFIRs; ?></h3>
                </div>
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center shadow-inner">
                    <i class="ti ti-folders text-xl"></i>
                </div>
            </div>
            <!-- Stat 2 -->
            <div class="bg-white border border-slate-100 shadow-sm stat-card flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider"><?php echo $cur['stat_pending']; ?></p>
                    <h3 class="text-navy text-2xl font-extrabold mt-1"><?php echo $pendingReview; ?></h3>
                </div>
                <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center shadow-inner">
                    <i class="ti ti-hourglass text-xl"></i>
                </div>
            </div>
            <!-- Stat 3 -->
            <div class="bg-white border border-slate-100 shadow-sm stat-card flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider"><?php echo $cur['stat_approved']; ?></p>
                    <h3 class="text-navy text-2xl font-extrabold mt-1"><?php echo $registeredCount; ?></h3>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center shadow-inner">
                    <i class="ti ti-circle-check text-xl"></i>
                </div>
            </div>
            <!-- Stat 4 -->
            <div class="bg-white border border-slate-100 shadow-sm stat-card flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider"><?php echo $cur['stat_rejected']; ?></p>
                    <h3 class="text-navy text-2xl font-extrabold mt-1"><?php echo $rejectedCount; ?></h3>
                </div>
                <div class="w-12 h-12 bg-red-50 text-red-600 rounded-2xl flex items-center justify-center shadow-inner">
                    <i class="ti ti-circle-x text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Filter and Search Header -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <form method="GET" class="w-full flex flex-col sm:flex-row items-center gap-3">
                <div class="relative w-full sm:max-w-md">
                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                        <i class="ti ti-search text-base"></i>
                    </span>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo $cur['search_placeholder']; ?>" 
                           class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent transition">
                </div>

                <!-- Status Filter -->
                <div class="relative w-full sm:w-48">
                    <select name="status" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent bg-white transition appearance-none">
                        <option value=""><?php echo $cur['all_statuses']; ?></option>
                        <option value="Submitted" <?php echo $filter_status === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="Under Review" <?php echo $filter_status === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="Registered" <?php echo $filter_status === 'Registered' ? 'selected' : ''; ?>>Registered (Approved)</option>
                        <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <span class="absolute right-3 top-3 text-gray-400 pointer-events-none"><i class="ti ti-chevron-down text-xs"></i></span>
                </div>

                <!-- Priority Filter -->
                <div class="relative w-full sm:w-48">
                    <select name="priority" onchange="this.form.submit()" 
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/20 focus:border-accent bg-white transition appearance-none">
                        <option value=""><?php echo $cur['all_priorities']; ?></option>
                        <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                    </select>
                    <span class="absolute right-3 top-3 text-gray-400 pointer-events-none"><i class="ti ti-chevron-down text-xs"></i></span>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden animate-slide-up">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase tracking-wider text-gray-400">
                            <th class="px-6 py-4"><?php echo $cur['tbl_fir_number']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_complainant']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_station']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_priority']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_status']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_date']; ?></th>
                            <th class="px-6 py-4 text-center"><?php echo $cur['tbl_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php foreach($firs as $fir): 
                            // Status badge mapping
                            $status_map = [
                                'Submitted'     => ['bg-blue-50 text-blue-700 border-blue-200', 'ti-send', 'Submitted'],
                                'Under Review'  => ['bg-orange-50 text-orange-700 border-orange-200', 'ti-eye', 'Under Review'],
                                'Registered'    => ['bg-green-50 text-green-700 border-green-200', 'ti-circle-check', 'Registered'],
                                'Rejected'      => ['bg-red-50 text-red-700 border-red-200', 'ti-circle-x', 'Rejected'],
                                'Draft'         => ['bg-gray-50 text-gray-600 border-gray-200', 'ti-edit', 'Draft'],
                            ];
                            [$badge_cls, $badge_ico, $badge_lbl] = $status_map[$fir['status']] ?? ['bg-gray-50 text-gray-600 border-gray-200', 'ti-info-circle', $fir['status']];

                            // Priority badge mapping
                            $pri_map = [
                                'low'    => 'bg-slate-100 text-slate-700',
                                'medium' => 'bg-amber-100 text-amber-700',
                                'high'   => 'bg-rose-100 text-rose-700'
                            ];
                            $pri_cls = $pri_map[$fir['priority']] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 font-mono font-bold text-xs text-navy">
                                <?php echo htmlspecialchars($fir['fir_number']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($fir['complainant_name']); ?></div>
                                <div class="text-xs text-gray-400">NID: <?php echo htmlspecialchars($fir['complainant_nid']); ?></div>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-700">
                                <?php echo htmlspecialchars($fir['station_code']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold <?php echo $pri_cls; ?>">
                                    <?php echo ucfirst($fir['priority']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="badge border <?php echo $badge_cls; ?>">
                                    <i class="ti <?php echo $badge_ico; ?> mr-1"></i> <?php echo $badge_lbl; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500 font-medium">
                                <?php echo date('d M Y, h:i A', strtotime($fir['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex items-center gap-2">
                                    <?php if(!empty($fir['case_id'])): ?>
                                        <a href="case-details.php?id=<?php echo $fir['case_id']; ?>" 
                                           class="w-8 h-8 rounded-lg bg-slate-50 hover:bg-slate-100 text-gray-600 flex items-center justify-center transition border border-slate-200" 
                                           title="Review Details">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if($fir['status'] === 'Submitted' || $fir['status'] === 'Under Review'): ?>
                                        <button onclick="approveFIR(<?php echo $fir['id']; ?>, '<?php echo $fir['fir_number']; ?>')" 
                                                class="w-8 h-8 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 flex items-center justify-center transition border border-emerald-100" 
                                                title="Approve FIR">
                                            <i class="ti ti-check"></i>
                                        </button>
                                        <button onclick="openRejectModal(<?php echo $fir['id']; ?>, '<?php echo $fir['fir_number']; ?>')" 
                                                class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition border border-rose-100" 
                                                title="Reject FIR">
                                            <i class="ti ti-x"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($firs)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="w-16 h-16 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-gray-300">
                                            <i class="ti ti-file-x text-3xl"></i>
                                        </div>
                                        <p class="text-sm font-semibold"><?php echo $cur['no_firs']; ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="py-6 mt-auto">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-xs text-gray-500 font-medium">
                © 2026 CaseFlowX
            </p>
        </div>
    </footer>
</main>

<!-- Rejection Reason Modal -->
<div id="reject-modal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-xl animate-fade-in border border-slate-100">
        <div class="bg-navy px-6 py-4 flex items-center justify-between text-white">
            <h3 class="font-bold text-lg flex items-center gap-2">
                <i class="ti ti-circle-x text-rose-500"></i> <?php echo $cur['reject_title']; ?>
            </h3>
            <button onclick="closeRejectModal()" class="text-white/70 hover:text-white transition"><i class="ti ti-x text-lg"></i></button>
        </div>
        <form id="reject-form" class="p-6 space-y-4">
            <input type="hidden" id="modal-fir-id" name="fir_id">
            <input type="hidden" name="action" value="reject">
            
            <div>
                <label for="reject-reason" class="block text-xs font-semibold text-gray-600 mb-1.5">
                    <?php echo $cur['reject_label']; ?> <span class="text-rose-500">*</span>
                </label>
                <textarea id="reject-reason" name="reason" rows="4" required 
                          placeholder="<?php echo $cur['reject_placeholder']; ?>" 
                          class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition resize-y"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeRejectModal()" 
                        class="px-4 py-2 border border-slate-200 rounded-xl text-sm font-semibold text-gray-600 hover:bg-slate-50 transition">
                    <?php echo $cur['btn_cancel']; ?>
                </button>
                <button type="submit" 
                        class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition shadow-sm">
                    <?php echo $cur['btn_submit_reject']; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Sidebar toggle
    document.getElementById('btn-toggle-sidebar')?.addEventListener('click', function() {
        const s = document.getElementById('sidebar');
        s.classList.toggle('hidden');
    });

    async function approveFIR(firId, firNumber) {
        if (!confirm(`Are you sure you want to approve and register FIR ${firNumber}?`)) return;
        
        try {
            const fd = new FormData();
            fd.append('fir_id', firId);
            fd.append('action', 'approve');
            
            const res = await fetch('api/admin_fir.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.success) {
                alert("<?php echo $cur['toast_success']; ?>");
                location.reload();
            } else {
                alert(data.message || 'Operation failed.');
            }
        } catch (e) {
            alert('A network error occurred.');
        }
    }

    function openRejectModal(firId, firNumber) {
        document.getElementById('modal-fir-id').value = firId;
        document.getElementById('reject-reason').value = '';
        document.getElementById('reject-modal').classList.remove('hidden');
    }

    function closeRejectModal() {
        document.getElementById('reject-modal').classList.add('hidden');
    }

    document.getElementById('reject-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        
        try {
            const fd = new FormData(this);
            const res = await fetch('api/admin_fir.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.success) {
                closeRejectModal();
                alert("<?php echo $cur['toast_success']; ?>");
                location.reload();
            } else {
                alert(data.message || 'Operation failed.');
                submitBtn.disabled = false;
            }
        } catch (e) {
            alert('A network error occurred.');
            submitBtn.disabled = false;
        }
    });
</script>
</body>
</html>
