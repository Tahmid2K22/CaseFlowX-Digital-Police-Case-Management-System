<?php
// login.php - Login Page
require_once __DIR__ . '/auth.php';

// Handle logout first if requested
if (isset($_GET['logout'])) {
    logout();
    $info = $_SESSION['lang'] === 'bn' ? 'সফলভাবে লগআউট করা হয়েছে।' : 'Logged out successfully.';
}

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: admin_users.php");
        exit;
    } else {
        header("Location: unauthorized.php");
        exit;
    }
}

$error = '';
$info = '';

if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$username_val = '';
if (isset($_SESSION['login_username'])) {
    $username_val = $_SESSION['login_username'];
    unset($_SESSION['login_username']);
}

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['username'] ?? ''); // Maps to phone or NID
    $password = $_POST['password'] ?? '';
    
    if (empty($identifier) || empty($password)) {
        $_SESSION['login_error'] = $_SESSION['lang'] === 'bn' ? 'অনুগ্রহ করে মোবাইল নম্বর/এনআইডি এবং পাসওয়ার্ড প্রদান করুন।' : 'Please enter both Phone/NID and password.';
        $_SESSION['login_username'] = $identifier;
        header("Location: login.php");
        exit;
    } else {
        $res = login($identifier, $password);
        if ($res['success']) {
            if ($_SESSION['role'] === 'Admin') {
                header("Location: admin_users.php");
            } else {
                header("Location: unauthorized.php");
            }
            exit;
        } else {
            $_SESSION['login_error'] = $res['error'];
            $_SESSION['login_username'] = $identifier;
            header("Location: login.php");
            exit;
        }
    }
}

$lang = $_SESSION['lang'];

$t = [
    'en' => [
        'title' => 'CaseFlowX — Police Portal Login',
        'heading' => 'Police Portal Login',
        'subheading' => 'Access CaseFlowX Case Management Platform',
        'username' => 'Phone Number or NID',
        'password' => 'Password',
        'login' => 'Log In',
        'demo_title' => 'Demo Testing Accounts',
        'copyright' => '2025 CaseFlowX — Case Management Platform',
        'developed' => 'Developed by CaseFlowX',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Use',
        'contact' => 'Contact Us',
        'back_home' => 'Back to Home',
        'no_account' => "Don't have an account?",
        'register_link' => 'Create one now'
    ],
    'bn' => [
        'title' => 'কেসফ্লোএক্স — পুলিশ পোর্টাল লগইন',
        'heading' => 'পুলিশ পোর্টাল লগইন',
        'subheading' => 'কেসফ্লোএক্স মামলা ব্যবস্থাপনা প্ল্যাটফর্ম',
        'username' => 'মোবাইল নম্বর বা এনআইডি',
        'password' => 'পাসওয়ার্ড',
        'login' => 'লগইন করুন',
        'demo_title' => 'ডেমো টেস্টিং অ্যাকাউন্ট',
        'copyright' => '২০২৫ কেসফ্লোএক্স — মামলা ব্যবস্থাপনা প্ল্যাটফর্ম',
        'developed' => 'কেসফ্লোএক্স দ্বারা তৈরি',
        'privacy' => 'গোপনীয়তা নীতি',
        'terms' => 'ব্যবহারের শর্তাবলী',
        'contact' => 'যোগাযোগ করুন',
        'back_home' => 'হোমে ফিরে যান',
        'no_account' => 'অ্যাকাউন্ট নেই?',
        'register_link' => 'এখনই তৈরি করুন'
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

    <!-- Top Language Bar -->
    <div class="bg-navyDark border-b border-white/10 px-6 py-2 flex justify-end items-center gap-2">
        <i class="ti ti-world text-white/50 text-base"></i>
        <a href="?lang=en" class="px-3 py-1 text-xs rounded-full border border-white/20 transition-all <?php echo $lang === 'en' ? 'bg-accent text-white font-semibold border-accent' : 'text-[#8FA3C8] hover:bg-white/10 hover:text-white'; ?>">English</a>
        <a href="?lang=bn" class="px-3 py-1 text-xs rounded-full border border-white/20 transition-all <?php echo $lang === 'bn' ? 'bg-accent text-white font-semibold border-accent' : 'text-[#8FA3C8] hover:bg-white/10 hover:text-white'; ?>">বাংলা</a>
    </div>

    <!-- Main Container -->
    <div class="flex-1 flex items-center justify-center p-4">
        <div class="max-w-md w-full">
            
            <!-- Breadcrumb -->
            <div class="mb-5 flex items-center gap-2 text-sm text-gray-500 justify-start">
                <a href="home.html" class="hover:text-accent transition-colors flex items-center gap-1 font-medium">
                    <i class="ti ti-home text-base"></i> <?php echo $lang === 'bn' ? 'হোম' : 'Home'; ?>
                </a>
                <i class="ti ti-chevron-right text-xs"></i>
                <span class="text-gray-700 font-semibold"><?php echo $lang === 'bn' ? 'পোর্টাল লগইন' : 'Portal Login'; ?></span>
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden">
                <!-- Card Header -->
                <div class="bg-navy px-8 py-6 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
                        <i class="ti ti-user-shield"></i>
                    </div>
                    <div>
                        <h1 class="text-white text-xl font-bold leading-tight"><?php echo $cur['heading']; ?></h1>
                        <p class="text-white/55 text-xs mt-0.5"><?php echo $cur['subheading']; ?></p>
                    </div>
                </div>
                
                <div class="px-8 py-8">
                    <!-- Notifications -->
                    <?php if (!empty($error)): ?>
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl flex items-start gap-3">
                            <i class="ti ti-alert-triangle text-lg mt-0.5 shrink-0"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($info)): ?>
                        <div class="mb-5 p-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl flex items-start gap-3">
                            <i class="ti ti-circle-check text-lg mt-0.5 shrink-0 text-accent"></i>
                            <div><?php echo $info; ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" class="space-y-4">
                        <div>
                            <label for="username" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                <?php echo $cur['username']; ?> <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                                    <i class="ti ti-device-mobile text-base"></i>
                                </span>
                                <input type="text" name="username" id="username" required
                                       value="<?php echo htmlspecialchars($username_val); ?>"
                                       class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy"
                                       placeholder="01XXXXXXXXX or NID">
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                <?php echo $cur['password']; ?> <span class="text-red-400">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                                    <i class="ti ti-lock text-base"></i>
                                </span>
                                <input type="password" name="password" id="password" required
                                       class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePwd()"
                                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <i class="ti ti-eye text-base" id="eye-icon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-2 transition mt-4 shadow-md">
                            <i class="ti ti-login text-base"></i>
                            <?php echo $cur['login']; ?>
                        </button>

                        <div class="text-center text-sm text-gray-500 mt-4">
                            <?php echo $cur['no_account']; ?>
                            <a href="signup.php" class="text-accent font-semibold hover:underline ml-1">
                                <?php echo $cur['register_link']; ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Home link -->
            <div class="text-center mt-4">
                <a href="home.html" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                    <i class="ti ti-arrow-left"></i>
                    <?php echo $cur['back_home']; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-navy border-t border-white/10 py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <div class="flex justify-center gap-6 flex-wrap mb-3 text-sm text-[#8FA3C8]">
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-lock"></i> <?php echo $cur['privacy']; ?></a>
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-file-certificate"></i> <?php echo $cur['terms']; ?></a>
                <a href="#" class="hover:underline flex items-center gap-1"><i class="ti ti-mail"></i> <?php echo $cur['contact']; ?></a>
            </div>
            <p class="text-xs text-[#8FA3C8]">
                <i class="ti ti-copyright text-[11px] inline-block mr-0.5"></i> <?php echo $cur['copyright']; ?>
            </p>
            <p class="text-xs text-slate-500 mt-1">
                <i class="ti ti-building-community"></i> <?php echo $cur['developed']; ?>
            </p>
        </div>
    </footer>

    <script>
        function togglePwd() {
            const pwdInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.className = 'ti ti-eye-off text-base';
            } else {
                pwdInput.type = 'password';
                eyeIcon.className = 'ti ti-eye text-base';
            }
        }
    </script>
</body>
</html>
