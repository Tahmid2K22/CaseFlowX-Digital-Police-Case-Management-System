<?php
// admin_users.php - User Account and Role Management Control Panel
require_once __DIR__ . '/auth.php';

// RBAC Check: Restrict to Admin only
check_access(['Admin']);

$msg_success = '';
$msg_error = '';

// Handle CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Update User & Role (SCRUM-48 & SCRUM-50)
    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $national_id = trim($_POST['national_id'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        
        if (empty($full_name) || empty($national_id) || empty($phone) || empty($role)) {
            $msg_error = $_SESSION['lang'] === 'bn' ? 'পূর্ণ নাম, এনআইডি, মোবাইল নম্বর এবং ভূমিকা আবশ্যক।' : 'Name, NID, Phone, and Role are required.';
        } elseif (!preg_match('/^\d{10}$|^\d{17}$/', $national_id)) {
            $msg_error = $_SESSION['lang'] === 'bn' ? 'এনআইডি অবশ্যই ১০ বা ১৭ ডিজিটের হতে হবে।' : 'NID must be 10 or 17 digits.';
        } elseif (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            $msg_error = $_SESSION['lang'] === 'bn' ? 'সঠিক মোবাইল নম্বর প্রদান করুন।' : 'Please enter a valid Bangladeshi phone number.';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg_error = $_SESSION['lang'] === 'bn' ? 'সঠিক ইমেল ঠিকানা প্রদান করুন।' : 'Please enter a valid email address.';
        } else {
            // Check NID uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE national_id = ? AND id != ?");
            $stmt->execute([$national_id, $id]);
            if ($stmt->fetchColumn() > 0) {
                $msg_error = $_SESSION['lang'] === 'bn' ? 'জাতীয় পরিচয়পত্র নম্বরটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'National ID is already registered.';
            } else {
                // Check phone uniqueness
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE phone = ? AND id != ?");
                $stmt->execute([$phone, $id]);
                if ($stmt->fetchColumn() > 0) {
                    $msg_error = $_SESSION['lang'] === 'bn' ? 'মোবাইল নম্বরটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'Phone number is already registered.';
                } else {
                    // Check email uniqueness
                    if (!empty($email)) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $msg_error = $_SESSION['lang'] === 'bn' ? 'ইমেল ঠিকানাটি ইতিপূর্বে ব্যবহৃত হয়েছে।' : 'Email address is already in use.';
                        }
                    }
                }
            }

            if (empty($msg_error)) {
                $query = "UPDATE users SET full_name = :full_name, national_id = :national_id, phone = :phone, email = :email, role = :role, status = :status, updated_at = CURRENT_TIMESTAMP";
                $params = [
                    'full_name' => $full_name,
                    'national_id' => $national_id,
                    'phone' => $phone,
                    'email' => !empty($email) ? $email : null,
                    'role' => $role,
                    'status' => $status,
                    'id' => $id
                ];
                
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $msg_error = $_SESSION['lang'] === 'bn' ? 'পাসওয়ার্ডটি অবশ্যই কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters long.';
                    } else {
                        $query .= ", password = :password";
                        $params['password'] = password_hash($password, PASSWORD_BCRYPT);
                    }
                }
                
                if (empty($msg_error)) {
                    $query .= " WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $res = $stmt->execute($params);
                    if ($res) {
                        $msg_success = $_SESSION['lang'] === 'bn' ? 'ব্যবহারকারীর তথ্য সফলভাবে আপডেট করা হয়েছে।' : 'User account updated successfully.';
                        
                        // Live session sync if editing own profile
                        if ($id === $_SESSION['user_id']) {
                            $_SESSION['username'] = $full_name;
                            $_SESSION['role'] = $role;
                            if ($status === 'Suspended' || $role !== 'Admin') {
                                header("Location: login.php");
                                exit;
                            }
                        }
                    } else {
                        $msg_error = $_SESSION['lang'] === 'bn' ? 'তথ্য আপডেট করতে ব্যর্থ হয়েছে।' : 'Failed to update user.';
                    }
                }
            }
        }
    }
    
    // Delete User
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            $msg_error = $_SESSION['lang'] === 'bn' ? 'আপনি নিজের অ্যাকাউন্ট ডিলিট করতে পারবেন না!' : 'You cannot delete your own account!';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $res = $stmt->execute([$id]);
            if ($res) {
                $msg_success = $_SESSION['lang'] === 'bn' ? 'ব্যবহারকারী অ্যাকাউন্ট সফলভাবে ডিলিট করা হয়েছে।' : 'User account deleted successfully.';
            } else {
                $msg_error = $_SESSION['lang'] === 'bn' ? 'অ্যাকাউন্ট ডিলিট করতে ত্রুটি হয়েছে।' : 'Failed to delete user account.';
            }
        }
    }
}

// Fetch Stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
$total_officers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Officer'")->fetchColumn();
$total_investigators = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Investigator'")->fetchColumn();
$total_citizens = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Citizen'")->fetchColumn();
$total_suspended = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'Suspended'")->fetchColumn();

// Handle Search and Filter Query
$search = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$query_str = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_str .= " AND (full_name LIKE :search OR email LIKE :search OR phone LIKE :search OR national_id LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($filter_role)) {
    $query_str .= " AND role = :role";
    $params['role'] = $filter_role;
}

if (!empty($filter_status)) {
    $query_str .= " AND status = :status";
    $params['status'] = $filter_status;
}

$query_str .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query_str);
$stmt->execute($params);
$users_list = $stmt->fetchAll();

$lang = $_SESSION['lang'];

$t = [
    'en' => [
        'title' => 'User & Role Management — CaseFlowX',
        'heading' => 'User & Role Administration',
        'subheading' => 'Create new security profiles, assign specific system permissions, and monitor authorization levels.',
        'btn_add' => 'Add User',
        'search_placeholder' => 'Search by name, phone, NID, or email...',
        'filter_role' => 'Filter by Role',
        'filter_status' => 'Filter by Status',
        'all_roles' => 'All Roles',
        'all_statuses' => 'All Statuses',
        'btn_clear' => 'Clear',
        'stat_total' => 'Total Users',
        'stat_admins' => 'Admin Accounts',
        'stat_officers' => 'Officer Accounts',
        'stat_investigators' => 'Investigator Accounts',
        'stat_citizens' => 'Citizen Accounts',
        'stat_suspended' => 'Suspended Accounts',
        'tbl_name' => 'Name',
        'tbl_phone' => 'Phone',
        'tbl_nid' => 'NID',
        'tbl_email' => 'Email',
        'tbl_role' => 'Role',
        'tbl_status' => 'Status',
        'tbl_created' => 'Created',
        'tbl_actions' => 'Actions',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'btn_logout' => 'Log Out',
        'logged_as' => 'Logged in as',
        'copyright' => '2025 CaseFlowX — Case Management Platform',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Use',
        'contact' => 'Contact Us',
        
        // Modals
        'lbl_name' => 'Full Name',
        'lbl_nid' => 'National ID (NID)',
        'lbl_phone' => 'Mobile Phone',
        'lbl_email' => 'Email address',
        'lbl_password' => 'Password',
        'lbl_role' => 'Role Type',
        'lbl_status' => 'Status',
        'btn_cancel' => 'Cancel',
        'btn_save' => 'Save Changes',
        'btn_create' => 'Create Account',
        'title_add' => 'Create User Account',
        'title_edit' => 'Edit User Account & Role',
        'edit_pass_help' => 'Leave blank if you do not wish to change the password.',
        'confirm_delete' => 'Are you sure you want to delete this user?'
    ],
    'bn' => [
        'title' => 'ব্যবহারকারী ও ভূমিকা ব্যবস্থাপনা — কেসফ্লোএক্স',
        'heading' => 'ব্যবহারকারী ও ভূমিকা প্রশাসন',
        'subheading' => 'নতুন সুরক্ষা প্রোফাইল তৈরি করুন, নির্দিষ্ট সিস্টেম অনুমতি বরাদ্দ করুন এবং অ্যাক্সেস পর্যবেক্ষণ করুন।',
        'btn_add' => 'নতুন ব্যবহারকারী',
        'search_placeholder' => 'নাম, মোবাইল, এনআইডি বা ইমেল দিয়ে খুঁজুন...',
        'filter_role' => 'ভূমিকা ফিল্টার',
        'filter_status' => 'অবস্থা ফিল্টার',
        'all_roles' => 'সকল ভূমিকা',
        'all_statuses' => 'সকল অবস্থা',
        'btn_clear' => 'মুছুন',
        'stat_total' => 'মোট ব্যবহারকারী',
        'stat_admins' => 'অ্যাডমিন অ্যাকাউন্ট',
        'stat_officers' => 'অফিসার অ্যাকাউন্ট',
        'stat_investigators' => 'তদন্তকারী অ্যাকাউন্ট',
        'stat_citizens' => 'নাগরিক অ্যাকাউন্ট',
        'stat_suspended' => 'স্থগিত অ্যাকাউন্ট',
        'tbl_name' => 'নাম',
        'tbl_phone' => 'মোবাইল',
        'tbl_nid' => 'এনআইডি',
        'tbl_email' => 'ইমেল',
        'tbl_role' => 'ভূমিকা',
        'tbl_status' => 'অবস্থা',
        'tbl_created' => 'তৈরির তারিখ',
        'tbl_actions' => 'কার্যক্রম',
        'active' => 'সক্রিয়',
        'suspended' => 'স্থগিত',
        'btn_logout' => 'লগআউট',
        'logged_as' => 'লগইন আছেন',
        'copyright' => '২০২৫ কেসফ্লোএক্স — মামলা ব্যবস্থাপনা প্ল্যাটফর্ম',
        'privacy' => 'গোপনীয়তা নীতি',
        'terms' => 'ব্যবহারের শর্তাবলী',
        'contact' => 'যোগাযোগ করুন',
        
        // Modals
        'lbl_name' => 'পূর্ণ নাম',
        'lbl_nid' => 'জাতীয় পরিচয়পত্র নম্বর (এনআইডি)',
        'lbl_phone' => 'মোবাইল নম্বর',
        'lbl_email' => 'ইমেল ঠিকানা',
        'lbl_password' => 'পাসওয়ার্ড',
        'lbl_role' => 'ভূমিকার প্রকার',
        'lbl_status' => 'অবস্থা',
        'btn_cancel' => 'বাতিল',
        'btn_save' => 'তথ্য সংরক্ষণ করুন',
        'btn_create' => 'অ্যাকাউন্ট তৈরি করুন',
        'title_add' => 'ব্যবহারকারী অ্যাকাউন্ট তৈরি',
        'title_edit' => 'ব্যবহারকারী অ্যাকাউন্ট ও ভূমিকা সম্পাদন',
        'edit_pass_help' => 'পাসওয়ার্ড পরিবর্তন করতে না চাইলে খালি রাখুন।',
        'confirm_delete' => 'আপনি কি নিশ্চিত যে আপনি এই ব্যবহারকারীকে ডিলিট করতে চান?'
    ]
];

$cur = $t[$lang];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cur['title']; ?> - CaseFlowX</title>
    <meta name="description" content="Secure Admin Panel on CaseFlowX - manage user accounts and system roles.">
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
        <a href="admin_users.php" class="sidebar-link active"><i class="ti ti-users text-base"></i> Manage Users</a>
        <a href="register.php" class="sidebar-link"><i class="ti ti-user-plus text-base"></i> Register User</a>
        <a href="admin_firs.php" class="sidebar-link"><i class="ti ti-file-description text-base"></i> Manage FIRs</a>

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
                <a href="?lang=en<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_role) ? '&role='.urlencode($filter_role) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?>" 
                   class="px-2.5 py-0.5 text-xs rounded-full border border-slate-200 transition-all <?php echo $lang === 'en' ? 'bg-accent text-white font-semibold border-accent' : 'text-gray-500 hover:bg-slate-100 hover:text-navy'; ?>">English</a>
                <a href="?lang=bn<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_role) ? '&role='.urlencode($filter_role) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?>" 
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
                    <p class="text-navy text-xs font-semibold"><?php echo htmlspecialchars($adminName); ?></p>
                    <p class="text-gray-400 text-[10px]">Admin</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Page Body -->
    <div class="flex-1 p-6 space-y-6 animate-fade-in">

        <!-- System Alerts -->
        <?php if (!empty($msg_success)): ?>
            <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 rounded-r-xl shadow-sm flex items-start gap-2.5">
                <i class="ti ti-circle-check text-xl mt-0.5 shrink-0 text-emerald-600"></i>
                <div class="font-medium text-sm"><?php echo $msg_success; ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($msg_error)): ?>
            <div class="p-4 bg-rose-50 border-l-4 border-rose-500 text-rose-800 rounded-r-xl shadow-sm flex items-start gap-2.5">
                <i class="ti ti-alert-triangle text-xl mt-0.5 shrink-0 text-rose-600"></i>
                <div class="font-medium text-sm"><?php echo $msg_error; ?></div>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-navy via-navy2 to-[#1a3060] rounded-2xl p-6 text-white relative overflow-hidden shadow-lg">
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <p class="text-white/60 text-sm font-medium mb-1 flex items-center gap-1.5">
                        <i class="ti ti-shield-check text-accent"></i> <?php echo $lang === 'bn' ? 'নিরাপদ অ্যাডমিন পোর্টাল' : 'Secure Admin Portal'; ?>
                    </p>
                    <h2 class="text-2xl font-bold">
                        <?php 
                        $welcome_text = $lang === 'bn' ? 'স্বাগতম, ' : 'Welcome back, ';
                        echo $welcome_text . htmlspecialchars(explode(' ', $adminName)[0]); 
                        ?>!
                    </h2>
                    <p class="text-white/55 text-sm mt-1">
                        <?php echo $cur['subheading']; ?>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="register.php" class="inline-flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border border-accent">
                        <i class="ti ti-user-plus"></i> <?php echo $cur['btn_add']; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 animate-slide-up">
            <!-- Total Users -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="ti ti-users text-xl text-blue-600"></i>
                    </div>
                    <span class="badge bg-blue-50 text-blue-600"><?php echo $lang === 'bn' ? 'মোট' : 'Total'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_users; ?>"><?php echo $total_users; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_total']; ?></p>
            </div>

            <!-- Admins -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-purple-50 flex items-center justify-center">
                        <i class="ti ti-user-shield text-xl text-purple-600"></i>
                    </div>
                    <span class="badge bg-purple-50 text-purple-600"><?php echo $lang === 'bn' ? 'অ্যাডমিন' : 'Admin'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_admins; ?>"><?php echo $total_admins; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_admins']; ?></p>
            </div>

            <!-- Officers -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="ti ti-shield text-xl text-blue-600"></i>
                    </div>
                    <span class="badge bg-blue-50 text-blue-600"><?php echo $lang === 'bn' ? 'কর্মকর্তা' : 'Officer'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_officers; ?>"><?php echo $total_officers; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_officers']; ?></p>
            </div>

            <!-- Investigators -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i class="ti ti-briefcase text-xl text-indigo-600"></i>
                    </div>
                    <span class="badge bg-indigo-50 text-indigo-600"><?php echo $lang === 'bn' ? 'তদন্তকারী' : 'Investigator'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_investigators; ?>"><?php echo $total_investigators; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_investigators']; ?></p>
            </div>

            <!-- Citizens -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="ti ti-user-check text-xl text-emerald-600"></i>
                    </div>
                    <span class="badge bg-emerald-50 text-emerald-600"><?php echo $lang === 'bn' ? 'নাগরিক' : 'Citizen'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_citizens; ?>"><?php echo $total_citizens; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_citizens']; ?></p>
            </div>

            <!-- Suspended -->
            <div class="stat-card bg-white border border-slate-100 shadow-sm">
                <div class="flex items-start justify-between mb-3">
                    <div class="w-11 h-11 rounded-xl bg-rose-50 flex items-center justify-center">
                        <i class="ti ti-user-off text-xl text-rose-600"></i>
                    </div>
                    <span class="badge bg-rose-50 text-rose-600"><?php echo $lang === 'bn' ? 'স্থগিত' : 'Suspended'; ?></span>
                </div>
                <p class="text-3xl font-bold text-navy counter" data-target="<?php echo $total_suspended; ?>"><?php echo $total_suspended; ?></p>
                <p class="text-gray-500 text-sm mt-1 font-medium"><?php echo $cur['stat_suspended']; ?></p>
            </div>
        </div>

        <!-- Users Directory Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <!-- Filters Panel -->
            <div class="p-4 border-b border-slate-100 bg-slate-50/50">
                <form action="" method="GET" class="flex flex-col md:flex-row gap-3">
                    <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                    
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                            <i class="ti ti-search text-base"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="<?php echo $cur['search_placeholder']; ?>"
                               class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition text-navy">
                    </div>

                    <div class="grid grid-cols-2 md:flex gap-3">
                        <select name="role" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                            <option value=""><?php echo $cur['all_roles']; ?></option>
                            <option value="Admin" <?php echo $filter_role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Officer" <?php echo $filter_role === 'Officer' ? 'selected' : ''; ?>>Officer</option>
                            <option value="Investigator" <?php echo $filter_role === 'Investigator' ? 'selected' : ''; ?>>Investigator</option>
                            <option value="Citizen" <?php echo $filter_role === 'Citizen' ? 'selected' : ''; ?>>Citizen</option>
                        </select>

                        <select name="status" class="border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                            <option value=""><?php echo $cur['all_statuses']; ?></option>
                            <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>><?php echo $cur['active']; ?></option>
                            <option value="Suspended" <?php echo $filter_status === 'Suspended' ? 'selected' : ''; ?>><?php echo $cur['suspended']; ?></option>
                        </select>
                    </div>

                    <div class="flex gap-2 shrink-0 justify-end">
                        <button type="submit" class="bg-navy hover:bg-navy2 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all">
                            Filter
                        </button>
                        <?php if (!empty($search) || !empty($filter_role) || !empty($filter_status)): ?>
                            <a href="?lang=<?php echo $lang; ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center justify-center">
                                <?php echo $cur['btn_clear']; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- User Data Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 font-bold text-xs uppercase border-b border-slate-100">
                            <th class="px-6 py-4"><?php echo $cur['tbl_name']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_phone']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_nid']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_email']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_role']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_status']; ?></th>
                            <th class="px-6 py-4"><?php echo $cur['tbl_created']; ?></th>
                            <th class="px-6 py-4 text-right"><?php echo $cur['tbl_actions']; ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (empty($users_list)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-slate-400 font-medium">
                                    <i class="ti ti-users-minus text-4xl block mb-2 text-slate-300"></i>
                                    <?php echo $lang === 'bn' ? 'কোনো ব্যবহারকারী পাওয়া যায়নি।' : 'No users found matching the filter criteria.'; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users_list as $user): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4 font-bold text-navy flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold uppercase text-xs">
                                            <?php echo substr($user['full_name'], 0, 2); ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 font-medium">
                                        <?php echo htmlspecialchars($user['phone']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 font-medium">
                                        <?php echo htmlspecialchars($user['national_id']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 font-medium text-xs">
                                        <?php echo htmlspecialchars($user['email'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        // Dynamic role badges
                                        $role_classes = 'bg-slate-100 text-slate-800 border-slate-200';
                                        if ($user['role'] === 'Admin') $role_classes = 'bg-purple-100 text-purple-800 border-purple-200';
                                        elseif ($user['role'] === 'Officer') $role_classes = 'bg-blue-100 text-blue-800 border-blue-200';
                                        elseif ($user['role'] === 'Investigator') $role_classes = 'bg-indigo-100 text-indigo-800 border-indigo-200';
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border <?php echo $role_classes; ?>">
                                            <?php echo $user['role']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($user['status'] === 'Active'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                <?php echo $cur['active']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-rose-50 text-rose-700 border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                <?php echo $cur['suspended']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-slate-400 text-xs">
                                        <?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-1 shrink-0">
                                        <?php
                                        // Safely pass user parameters to edit handler
                                        $user_json = json_encode([
                                            'id' => $user['id'],
                                            'full_name' => $user['full_name'],
                                            'national_id' => $user['national_id'],
                                            'phone' => $user['phone'],
                                            'email' => $user['email'] ?? '',
                                            'role' => $user['role'],
                                            'status' => $user['status']
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                                        ?>
                                        <button onclick='openEditModal(<?php echo $user_json; ?>)'
                                                class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 p-1.5 rounded-lg transition-colors"
                                                title="Edit">
                                            <i class="ti ti-edit text-base"></i>
                                        </button>
                                        
                                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                            <button onclick="confirmDelete(<?php echo $user['id']; ?>)"
                                                    class="text-rose-600 hover:text-rose-900 bg-rose-50 hover:bg-rose-100 p-1.5 rounded-lg transition-colors"
                                                    title="Delete">
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Hidden POST delete form -->
<form id="deleteForm" method="POST" action="">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id" value="">
</form>

<!-- ================= EDIT USER MODAL ================= -->
<div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden z-50">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden border border-slate-100 animate-fade-in">
        <!-- Modal Header -->
        <div class="bg-navy px-6 py-4 flex items-center justify-between text-white">
            <h3 class="font-bold flex items-center gap-2">
                <i class="ti ti-users-gear text-accent text-lg"></i>
                <?php echo $cur['title_edit']; ?>
            </h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-white transition-colors">
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id" value="">
            
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_name']; ?></label>
                <input type="text" name="full_name" id="edit_full_name" required
                       class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_phone']; ?></label>
                    <input type="text" name="phone" id="edit_phone" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_nid']; ?></label>
                    <input type="text" name="national_id" id="edit_national_id" required
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_email']; ?></label>
                <input type="email" name="email" id="edit_email"
                       class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                    <?php echo $cur['lbl_password']; ?> 
                    <span class="text-[10px] text-slate-400 font-normal lowercase">(<?php echo $cur['edit_pass_help']; ?>)</span>
                </label>
                <input type="password" name="password" id="edit_password"
                       class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy"
                       placeholder="••••••••">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_role']; ?></label>
                    <select name="role" id="edit_role" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                        <option value="Admin">Admin</option>
                        <option value="Officer">Officer</option>
                        <option value="Investigator">Investigator</option>
                        <option value="Citizen">Citizen</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1"><?php echo $cur['lbl_status']; ?></label>
                    <select name="status" id="edit_status" required class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent bg-white text-navy font-medium">
                        <option value="Active"><?php echo $cur['active']; ?></option>
                        <option value="Suspended"><?php echo $cur['suspended']; ?></option>
                    </select>
                </div>
            </div>

            <!-- Footer buttons -->
            <div class="flex justify-end gap-2 pt-4 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition-colors text-slate-700">
                    <?php echo $cur['btn_cancel']; ?>
                </button>
                <button type="submit" class="bg-accent hover:bg-accent-dark text-white font-bold px-4 py-2 rounded-xl text-sm shadow transition-colors">
                    <?php echo $cur['btn_save']; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JS Helper Functions -->
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

    function openEditModal(user) {
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_full_name').value = user.full_name;
        document.getElementById('edit_phone').value = user.phone;
        document.getElementById('edit_national_id').value = user.national_id;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_status').value = user.status;
        document.getElementById('edit_password').value = '';
        document.getElementById('editModal').classList.remove('hidden');
    }

    function confirmDelete(userId) {
        const confirmMsg = "<?php echo $cur['confirm_delete']; ?>";
        if (confirm(confirmMsg)) {
            document.getElementById('delete_id').value = userId;
            document.getElementById('deleteForm').submit();
        }
    }
</script>
</body>
</html>
