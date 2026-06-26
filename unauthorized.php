<?php
// unauthorized.php - Access Denied Notice
require_once __DIR__ . '/auth.php';

$lang = $_SESSION['lang'] ?? 'en';

$t = [
    'en' => [
        'title' => 'Access Denied — CaseFlowX',
        'heading' => 'Access Denied',
        'desc' => 'You do not have the required permissions to view this resource.',
        'logged_as' => 'You are currently logged in as',
        'logout_btn' => 'Log Out & Try Different Account',
        'back_home' => 'Back to Home',
        'role_label' => 'Role'
    ],
    'bn' => [
        'title' => 'অনুমতি নেই — কেসফ্লোএক্স',
        'heading' => 'প্রবেশাধিকার অস্বীকৃত',
        'desc' => 'এই রিসোর্সটি দেখার জন্য আপনার প্রয়োজনীয় অনুমতি নেই।',
        'logged_as' => 'আপনি বর্তমানে লগ ইন আছেন',
        'logout_btn' => 'লগআউট এবং অন্য অ্যাকাউন্ট চেষ্টা করুন',
        'back_home' => 'হোমে ফিরে যান',
        'role_label' => 'ভূমিকা'
    ]
];

$cur = $t[$lang];

$desc = $cur['desc'];
if (isset($_GET['msg']) && trim($_GET['msg']) !== '') {
    $desc = htmlspecialchars(trim($_GET['msg']));
}

$homeUrl = 'index.php';
if (is_logged_in() || !empty($_SESSION['officer_id']) || !empty($_SESSION['citizen_id'])) {
    $sessionRole = $_SESSION['role'] ?? '';
    $officerId = $_SESSION['officer_id'] ?? 0;
    $citizenId = $_SESSION['citizen_id'] ?? 0;
    if ($officerId > 0 || $sessionRole === 'Officer') {
        $homeUrl = ($sessionRole === 'Officer') ? 'fir_officer_dashboard.php' : 'officer-dashboard.php';
    } elseif ($sessionRole === 'Admin') {
        $homeUrl = 'admin_firs.php';
    } elseif ($sessionRole === 'Investigator') {
        $homeUrl = 'investigator_dashboard.php';
    } elseif ($sessionRole === 'Citizen' || $citizenId > 0) {
        $homeUrl = 'cases.php';
    }
}
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
        <div class="max-w-md w-full text-center">
            <div class="w-20 h-20 bg-rose-50 border border-rose-200 rounded-full flex items-center justify-center text-rose-600 text-4xl shadow-inner mx-auto mb-6">
                <i class="ti ti-shield-x"></i>
            </div>
            
            <h1 class="text-3xl font-extrabold text-navy mb-3">
                <?php echo $cur['heading']; ?>
            </h1>
            
            <p class="text-slate-600 mb-6 leading-relaxed">
                <?php echo $desc; ?>
            </p>

            <?php if (is_logged_in()): ?>
                <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm inline-block text-left w-full max-w-sm mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-navy/10 rounded-full flex items-center justify-center text-navy font-bold text-lg">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide"><?php echo $cur['logged_as']; ?></p>
                            <p class="font-bold text-navy text-sm"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            <p class="text-xs text-slate-500 font-medium"><?php echo $cur['role_label']; ?>: <span class="text-accent font-semibold"><?php echo htmlspecialchars($_SESSION['role']); ?></span></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex flex-col gap-2.5 max-w-sm mx-auto">
                <a href="login.php?logout=1"
                   class="bg-accent hover:bg-accent-dark text-white font-bold py-2.5 px-4 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm">
                    <i class="ti ti-logout"></i>
                    <?php echo $cur['logout_btn']; ?>
                </a>
                
                <a href="<?php echo htmlspecialchars($homeUrl); ?>"
                   class="bg-navy hover:bg-navy2 text-white font-bold py-2.5 px-4 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm">
                    <i class="ti ti-home"></i>
                    <?php echo $cur['back_home']; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-navy border-t border-white/10 py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-xs text-[#8FA3C8]">
                © 2026 CaseFlowX
            </p>
        </div>
    </footer>

</body>
</html>
