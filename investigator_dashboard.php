<?php
/**
 * investigator_dashboard.php - CaseFlowX
 * Secure dashboard for Investigation Officers (role = Investigator).
 * SCRUM-60: Implement filtering logic to display only assigned cases
 * SCRUM-64: Create UI for displaying assigned cases list
 */

require_once __DIR__ . '/auth.php';

// Enforce session: only Investigator role allowed
check_access(['Investigator']);

$lang = $_SESSION['lang'] ?? 'en';
$investigatorId = $_SESSION['user_id'];
$investigatorName = $_SESSION['username'] ?? 'Investigator';

$db = get_db();

// Fetch live investigator details from DB
$investigatorDistrict = 'N/A';
$investigatorDivision = 'N/A';
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $investigatorId]);
    $inv = $stmt->fetch();
    if ($inv) {
        $investigatorName     = $inv['full_name'];
        $investigatorDistrict = $inv['district'];
        $investigatorDivision = $inv['division'];
    }
} catch (PDOException $e) {
    error_log('[CaseFlowX] Investigator details query error: ' . $e->getMessage());
}

// Translations
$t = [
    'en' => [
        'title' => 'Investigator Dashboard - CaseFlowX',
        'heading' => 'Investigator Dashboard',
        'subheading' => 'Manage and track your assigned police investigations.',
        'stat_total' => 'Total Assigned Cases',
        'stat_active' => 'Active Cases',
        'stat_resolved' => 'Resolved Cases',
        'stat_location' => 'Assigned Location',
        'search_placeholder' => 'Search by Case ID, Title, or Citizen details...',
        'all_statuses' => 'All Statuses',
        'all_priorities' => 'All Priorities',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'tbl_case_id' => 'Case ID',
        'tbl_citizen' => 'Citizen Details',
        'tbl_title' => 'Title',
        'tbl_status' => 'Status',
        'tbl_priority' => 'Priority',
        'tbl_date' => 'Date',
        'tbl_actions' => 'Action',
        'btn_clear' => 'Clear',
        'btn_logout' => 'Sign Out',
        'no_cases' => 'No assigned cases found matching the filters.',
    ],
    'bn' => [
        'title' => 'তদন্ত কর্মকর্তা ড্যাশবোর্ড - কেসফ্লোএক্স',
        'heading' => 'তদন্ত কর্মকর্তা ড্যাশবোর্ড',
        'subheading' => 'আপনার বরাদ্দকৃত মামলা ও তদন্ত সমূহ পরিচালনা করুন।',
        'stat_total' => 'মোট বরাদ্দকৃত মামলা',
        'stat_active' => 'সক্রিয় মামলা',
        'stat_resolved' => 'মীমাংসিত মামলা',
        'stat_location' => 'কর্মস্থল থানা ও জেলা',
        'search_placeholder' => 'কেস আইডি, শিরোনাম বা নাগরিকের তথ্য দিয়ে খুঁজুন...',
        'all_statuses' => 'সকল অবস্থা',
        'all_priorities' => 'সকল অগ্রাধিকার',
        'active' => 'সক্রিয়',
        'suspended' => 'স্থগিত',
        'tbl_case_id' => 'কেস আইডি',
        'tbl_citizen' => 'নাগরিকের তথ্য',
        'tbl_title' => 'শিরোনাম',
        'tbl_status' => 'অবস্থা',
        'tbl_priority' => 'অগ্রাধিকার',
        'tbl_date' => 'তারিখ',
        'tbl_actions' => 'পদক্ষেপ',
        'btn_clear' => 'পরিষ্কার',
        'btn_logout' => 'লগআউট',
        'no_cases' => 'ফিল্টারের সাথে মেলে এমন কোনো মামলা পাওয়া যায়নি।',
    ]
];

$cur = $t[$lang];

// Fetch Stats Counts
$totalCases = 0;
$activeCases = 0;
$resolvedCases = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = :id");
    $stmt->execute(['id' => $investigatorId]);
    $totalCases = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = :id AND status IN ('open', 'in_progress', 'pending')");
    $stmt->execute(['id' => $investigatorId]);
    $activeCases = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM cases WHERE investigator_id = :id AND status = 'resolved'");
    $stmt->execute(['id' => $investigatorId]);
    $resolvedCases = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[CaseFlowX] Investigator stats query error: ' . $e->getMessage());
}

// Search and Filtering Parameters
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_priority = trim($_GET['priority'] ?? '');

$cases_list = [];
try {
    $query = "
        SELECT c.*, u.full_name as citizen_name, u.phone as citizen_phone
        FROM cases c
        JOIN users u ON c.citizen_id = u.id
        WHERE c.investigator_id = :investigator_id
    ";
    $params = [':investigator_id' => $investigatorId];

    if ($search !== '') {
        $query .= " AND (c.case_number LIKE :search OR c.title LIKE :search OR c.description LIKE :search OR u.full_name LIKE :search OR u.phone LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    if ($filter_status !== '') {
        $query .= " AND c.status = :status";
        $params[':status'] = $filter_status;
    }
    if ($filter_priority !== '') {
        $query .= " AND c.priority = :priority";
        $params[':priority'] = $filter_priority;
    }

    $query .= " ORDER BY c.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $cases_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[CaseFlowX] Investigator cases query error: ' . $e->getMessage());
}

$nameParts = array_slice(explode(' ', $investigatorName), 0, 2);
$initials  = strtoupper(implode('', array_map(fn($w) => !empty($w) ? $w[0] : '', $nameParts)));
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cur['title']; ?></title>
    <meta name="description" content="Secure portal for CaseFlowX Investigation Officers.">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
    <!-- Fonts -->
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
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
        @keyframes slideUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        .animate-slide-up { animation: slideUp 0.5s ease-out; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(1.4)} }
        .pulse-dot { animation: pulse-dot 2s infinite; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 4px; }
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
            <p class="text-white/40 text-[10px] font-medium tracking-wider uppercase">Investigator Portal</p>
        </div>
    </div>

    <div class="mx-4 mt-5 mb-4 p-3 rounded-xl bg-white/5 border border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white text-sm font-bold shadow">
            <?php echo htmlspecialchars($initials); ?>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white text-xs font-semibold truncate"><?php echo htmlspecialchars($investigatorName); ?></p>
            <p class="text-accent text-[10px] font-medium flex items-center gap-1">
                <i class="ti ti-badge"></i> Investigator
            </p>
        </div>
        <div class="w-2 h-2 rounded-full bg-accent flex-shrink-0 pulse-dot"></div>
    </div>

    <nav class="flex-1 px-3 space-y-1">
        <p class="text-[10px] font-bold uppercase tracking-widest text-white/30 px-3 pt-2 pb-1">Main</p>
        <a href="investigator_dashboard.php" class="sidebar-link active"><i class="ti ti-layout-dashboard text-base"></i> Dashboard</a>

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

            <div class="flex items-center gap-2.5 pl-3 border-l border-slate-200">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-accent to-[#0F6E56] flex items-center justify-center text-white text-xs font-bold shadow">
                    <?php echo htmlspecialchars($initials); ?>
                </div>
                <div class="hidden sm:block">
                    <p class="text-navy text-xs font-semibold"><?php echo htmlspecialchars($investigatorName); ?></p>
                    <p class="text-gray-400 text-[10px]">Investigator</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Body -->
    <div class="flex-1 p-6 space-y-6 animate-fade-in">

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-navy via-navy2 to-[#1a3060] rounded-2xl p-6 text-white relative overflow-hidden shadow-lg animate-slide-up">
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-white/60 text-sm font-medium mb-1 flex items-center gap-1.5">
                        <i class="ti ti-shield-check text-accent"></i> Secure Investigator Portal
                    </p>
                    <h2 class="text-2xl font-bold">
                        <?php 
                        $welcome_text = $lang === 'bn' ? 'স্বাগতম, ' : 'Welcome back, ';
                        echo $welcome_text . htmlspecialchars(explode(' ', $investigatorName)[0]); 
                        ?>!
                    </h2>
                    <p class="text-white/55 text-sm mt-1">
                        <?php echo $cur['subheading']; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 animate-slide-up">
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="ti ti-files text-xl text-blue-600"></i>
                    </div>
                    <span class="badge bg-blue-50 text-blue-600"><?php echo $lang === 'bn' ? 'মোট' : 'Total'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $totalCases; ?>"><?php echo $totalCases; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_total']; ?></p>
            </div>

            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center">
                        <i class="ti ti-clock-hour-4 text-xl text-amber-600"></i>
                    </div>
                    <span class="badge bg-amber-50 text-amber-600"><?php echo $lang === 'bn' ? 'সক্রিয়' : 'Active'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $activeCases; ?>"><?php echo $activeCases; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_active']; ?></p>
            </div>

            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-[#E8F8F3] flex items-center justify-center">
                        <i class="ti ti-circle-check text-xl text-accent"></i>
                    </div>
                    <span class="badge bg-[#E8F8F3] text-accent"><?php echo $lang === 'bn' ? 'মীমাংসিত' : 'Resolved'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $resolvedCases; ?>"><?php echo $resolvedCases; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_resolved']; ?></p>
            </div>

            <div class="stat-card bg-gradient-to-br from-navy to-navy2 text-white shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                        <i class="ti ti-map-pin text-xl text-white"></i>
                    </div>
                    <span class="badge bg-accent/20 text-accent"><?php echo $lang === 'bn' ? 'নিরাপদ' : 'Secured'; ?></span>
                </div>
                <p class="text-xl font-bold"><?php echo htmlspecialchars($investigatorDistrict); ?></p>
                <p class="text-white/55 text-sm mt-1 font-medium"><?php echo $cur['stat_location']; ?></p>
            </div>
        </div>

        <!-- Cases Search & Filter Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            
            <!-- Filters Panel -->
            <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                <form action="" method="GET" class="flex flex-col md:flex-row gap-3">
                    
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="ti ti-search text-base"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="<?php echo $cur['search_placeholder']; ?>"
                               class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition text-navy">
                    </div>

                    <div class="grid grid-cols-2 md:flex gap-3">
                        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                            <option value=""><?php echo $cur['all_statuses']; ?></option>
                            <option value="open" <?php echo $filter_status === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="closed" <?php echo $filter_status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>

                        <select name="priority" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                            <option value=""><?php echo $cur['all_priorities']; ?></option>
                            <option value="low" <?php echo $filter_priority === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $filter_priority === 'high' ? 'selected' : ''; ?>>High</option>
                        </select>
                    </div>

                    <div class="flex gap-2 shrink-0 justify-end">
                        <button type="submit" class="bg-navy hover:bg-navy2 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_status) || !empty($filter_priority)): ?>
                            <a href="?lang=<?php echo $lang; ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center justify-center">
                                <?php echo $cur['btn_clear']; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Case Data Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 font-bold text-xs uppercase border-b border-slate-100">
                            <th class="px-6 py-4"><?php echo $cur['tbl_case_id']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_title']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_citizen']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_priority']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_status']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_date']; ?></th>
                            <th class="px-6 py-4 text-right"><?php echo $cur['tbl_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($cases_list)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400 font-medium">
                                    <i class="ti ti-file-description-off text-4xl block mb-2 text-slate-300"></i>
                                    <?php echo $cur['no_cases']; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cases_list as $case): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 font-mono text-xs text-navy font-semibold">
                                        #<?php echo htmlspecialchars($case['case_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-navy">
                                        <?php echo htmlspecialchars($case['title']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">
                                        <div class="font-medium"><?php echo htmlspecialchars($case['citizen_name']); ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><i class="ti ti-phone text-xs"></i> <?php echo htmlspecialchars($case['citizen_phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $p_color = 'text-gray-500';
                                        if ($case['priority'] === 'medium') $p_color = 'text-amber-600';
                                        elseif ($case['priority'] === 'high') $p_color = 'text-red-600';
                                        ?>
                                        <span class="text-xs font-semibold <?php echo $p_color; ?>">
                                            <?php echo ucfirst($case['priority']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $sc = ['open'=>'bg-blue-50 text-blue-600 border-blue-100',
                                               'in_progress'=>'bg-amber-50 text-amber-600 border-amber-100',
                                               'resolved'=>'bg-emerald-50 text-emerald-600 border-emerald-100',
                                               'closed'=>'bg-gray-100 text-gray-500 border-gray-100'][$case['status'] ?? 'open'] ?? 'bg-gray-100 text-gray-500 border-gray-100';
                                        ?>
                                        <span class="badge border <?php echo $sc; ?>">
                                            <?php echo ucfirst(str_replace('_',' ',$case['status'] ?? 'open')); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-400 text-xs">
                                        <?php echo date('M d, Y', strtotime($case['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="case-details.php?id=<?php echo $case['id']; ?>" 
                                           class="text-accent hover:text-[#0F6E56] font-semibold text-xs flex items-center gap-1 justify-end hover:underline">
                                            View <i class="ti ti-arrow-right text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
