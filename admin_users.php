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
$total_staff = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('Officer', 'Investigator')")->fetchColumn();
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
        'stat_staff' => 'Officers & Investigators',
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
        'stat_staff' => 'অফিসার ও তদন্তকারী',
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
    <title><?php echo $cur['title']; ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Tabler Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css"/>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#1B2A4A',
                        accent: '#1D9E75',
                        'accent-dark': '#0F6E56',
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
        }
    </style>
</head>
<body class="bg-[#F4F6F9] min-h-screen flex flex-col justify-between">

    <!-- Top Navigation Bar -->
    <div>
        <div class="bg-navyDark border-b border-white/10 px-6 py-2 flex justify-between items-center gap-2">
            <div class="text-xs text-[#8FA3C8] font-medium flex items-center gap-2">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span><?php echo $cur['logged_as']; ?>: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo $_SESSION['role']; ?>)</span>
            </div>
            <div class="flex items-center gap-2">
                <i class="ti ti-world text-white/50 text-base"></i>
                <a href="?lang=en<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_role) ? '&role='.urlencode($filter_role) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?>" 
                   class="px-2.5 py-0.5 text-xs rounded-full border border-white/20 transition-all <?php echo $lang === 'en' ? 'bg-accent text-white font-semibold border-accent' : 'text-[#8FA3C8] hover:bg-white/10 hover:text-white'; ?>">English</a>
                <a href="?lang=bn<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($filter_role) ? '&role='.urlencode($filter_role) : ''; ?><?php echo !empty($filter_status) ? '&status='.urlencode($filter_status) : ''; ?>" 
                   class="px-2.5 py-0.5 text-xs rounded-full border border-white/20 transition-all <?php echo $lang === 'bn' ? 'bg-accent text-white font-semibold border-accent' : 'text-[#8FA3C8] hover:bg-white/10 hover:text-white'; ?>">বাংলা</a>
            </div>
        </div>

        <nav class="bg-navy px-6 py-3 flex items-center justify-between shadow-md">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-accent rounded-lg flex items-center justify-center text-white text-2xl shadow">
                    <i class="ti ti-shield-check"></i>
                </div>
                <div>
                    <h1 class="text-white font-bold leading-none text-base">CaseFlowX</h1>
                    <span class="text-xs text-accent font-semibold tracking-wide mt-0.5 block">Admin Control Panel</span>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden md:flex gap-1">
                    <a href="home.html" class="text-slate-300 hover:text-white px-3 py-1.5 rounded-lg text-sm transition-colors flex items-center gap-1.5"><i class="ti ti-home"></i> <?php echo $lang === 'bn' ? 'হোম' : 'Home'; ?></a>
                    <a href="#" class="text-white bg-white/10 px-3 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-1.5"><i class="ti ti-users"></i> <?php echo $lang === 'bn' ? 'ব্যবহারকারী' : 'Users'; ?></a>
                    <a href="#" class="text-slate-300 hover:text-white px-3 py-1.5 rounded-lg text-sm transition-colors flex items-center gap-1.5"><i class="ti ti-file-description"></i> FIR</a>
                </div>
                <a href="login.php?logout=1" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold px-4 py-1.5 rounded-xl text-sm transition-all flex items-center gap-1.5 shadow shadow-rose-600/25">
                    <i class="ti ti-logout"></i>
                    <?php echo $cur['btn_logout']; ?>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h2 class="text-2xl font-bold text-navy flex items-center gap-2">
                    <i class="ti ti-users-gear text-accent"></i>
                    <?php echo $cur['heading']; ?>
                </h2>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    <?php echo $cur['subheading']; ?>
                </p>
            </div>
            <a href="register.php"
               class="bg-accent hover:bg-accent-dark text-white font-bold px-4 py-2.5 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm shrink-0">
                <i class="ti ti-user-plus text-base"></i>
                <?php echo $cur['btn_add']; ?>
            </a>
        </div>

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

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 bg-navy/5 rounded-xl flex items-center justify-center text-navy text-2xl shadow-sm">
                    <i class="ti ti-users"></i>
                </div>
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?php echo $cur['stat_total']; ?></span>
                    <h3 class="text-2xl font-bold text-navy mt-0.5"><?php echo $total_users; ?></h3>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-50 rounded-xl flex items-center justify-center text-purple-600 text-2xl shadow-sm">
                    <i class="ti ti-user-shield"></i>
                </div>
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?php echo $cur['stat_admins']; ?></span>
                    <h3 class="text-2xl font-bold text-purple-700 mt-0.5"><?php echo $total_admins; ?></h3>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 text-2xl shadow-sm">
                    <i class="ti ti-badge"></i>
                </div>
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?php echo $cur['stat_staff']; ?></span>
                    <h3 class="text-2xl font-bold text-blue-700 mt-0.5"><?php echo $total_staff; ?></h3>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 bg-rose-50 rounded-xl flex items-center justify-center text-rose-600 text-2xl shadow-sm">
                    <i class="ti ti-user-off"></i>
                </div>
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?php echo $cur['stat_suspended']; ?></span>
                    <h3 class="text-2xl font-bold text-rose-700 mt-0.5"><?php echo $total_suspended; ?></h3>
                </div>
            </div>
        </div>

        <!-- Users Search & Table Section -->
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
    </main>

    <!-- Footer -->
    <footer class="bg-navy border-t border-white/10 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex justify-center gap-6 flex-wrap mb-3 text-sm text-[#8FA3C8]">
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-lock"></i> <?php echo $cur['privacy']; ?></a>
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-file-certificate"></i> <?php echo $cur['terms']; ?></a>
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-mail"></i> <?php echo $cur['contact']; ?></a>
            </div>
            <p class="text-xs text-[#8FA3C8]">
                <i class="ti ti-copyright text-[11px] inline-block mr-0.5"></i> <?php echo $cur['copyright']; ?>
            </p>
        </div>
    </footer>

    <!-- Hidden POST delete form -->
    <form id="deleteForm" method="POST" action="">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id" value="">
    </form>



    <!-- ================= EDIT USER MODAL ================= -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden border border-slate-100">
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
