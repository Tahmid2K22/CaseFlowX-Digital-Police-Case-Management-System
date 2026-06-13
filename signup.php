<?php
// signup.php - Public User Registration Portal
require_once __DIR__ . '/auth.php';

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

$lang = $_SESSION['lang'] ?? 'en';

$t = [
    'en' => [
        'title' => 'Register User Account — Police Portal',
        'heading' => 'Create Account',
        'subheading' => 'Register a new security profile, credentials, and set system access permissions.',
        'btn_cancel' => 'Cancel',
        'btn_next' => 'Next Step',
        'btn_prev' => 'Previous',
        'btn_submit' => 'Register Account',
        'back_login' => 'Back to Login',
        'copyright' => '2025 CaseFlowX — Case Management Platform',
        
        // Steps
        'step1_title' => 'Personal Info',
        'step1_desc' => 'Personal Information',
        'step2_title' => 'Location & Role',
        'step2_desc' => 'Location & System Role',
        'step3_title' => 'Security',
        'step3_desc' => 'Set Account Password',
        
        // Fields
        'lbl_fullname' => 'Full Name',
        'lbl_nid' => 'National ID (NID)',
        'lbl_dob' => 'Date of Birth',
        'lbl_gender' => 'Gender',
        'lbl_phone' => 'Mobile Phone',
        'lbl_email' => 'Email Address',
        'lbl_division' => 'Division',
        'lbl_district' => 'District',
        'lbl_address' => 'Detailed Address',
        'lbl_role' => 'Role Type',
        'lbl_status' => 'Account Status',
        'lbl_password' => 'Password',
        'lbl_password_confirm' => 'Confirm Password',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'placeholder_fullname' => 'e.g. Mohammad Rahman',
        'placeholder_nid' => '10 or 17-digit NID',
        'placeholder_phone' => '01XXXXXXXXX',
        'placeholder_email' => 'Optional — e.g. you@example.com',
        'placeholder_address' => 'Enter house, street, area details...',
        'select_gender' => 'Select gender',
        'select_division' => 'Select division',
    ],
    'bn' => [
        'title' => 'ব্যবহারকারী নিবন্ধন — পুলিশ পোর্টাল',
        'heading' => 'অ্যাকাউন্ট তৈরি করুন',
        'subheading' => 'একটি নতুন সুরক্ষা প্রোফাইল তৈরি করুন, ক্রেডেনশিয়াল সেট করুন এবং সিস্টেমের প্রবেশাধিকার নির্ধারণ করুন।',
        'btn_cancel' => 'বাতিল',
        'btn_next' => 'পরবর্তী ধাপ',
        'btn_prev' => 'পূর্ববর্তী',
        'btn_submit' => 'নিবন্ধন সম্পন্ন করুন',
        'back_login' => 'লগইনে ফিরে যান',
        'copyright' => '২০২৫ কেসফ্লোএক্স — মামলা ব্যবস্থাপনা প্ল্যাটফর্ম',
        
        // Steps
        'step1_title' => 'ব্যক্তিগত তথ্য',
        'step1_desc' => 'ব্যক্তিগত তথ্য',
        'step2_title' => 'অবস্থান ও ভূমিকা',
        'step2_desc' => 'অবস্থান বিবরণ ও সিস্টেম ভূমিকা',
        'step3_title' => 'নিরাপত্তা',
        'step3_desc' => 'পাসওয়ার্ড নির্ধারণ',
        
        // Fields
        'lbl_fullname' => 'পূর্ণ নাম',
        'lbl_nid' => 'জাতীয় পরিচয়পত্র নম্বর (এনআইডি)',
        'lbl_dob' => 'জন্ম তারিখ',
        'lbl_gender' => 'লিঙ্গ',
        'lbl_phone' => 'মোবাইল নম্বর',
        'lbl_email' => 'ইমেল ঠিকানা (ঐচ্ছিক)',
        'lbl_division' => 'বিভাগ',
        'lbl_district' => 'জেলা',
        'lbl_address' => 'বিস্তারিত ঠিকানা',
        'lbl_role' => 'ভূমিকার ধরন',
        'lbl_status' => 'অ্যাকাউন্টের অবস্থা',
        'lbl_password' => 'পাসওয়ার্ড',
        'lbl_password_confirm' => 'পাসওয়ার্ড নিশ্চিত করুন',
        'active' => 'সক্রিয়',
        'suspended' => 'স্থগিত',
        'placeholder_fullname' => 'যেমন: মোহাম্মদ রহমান',
        'placeholder_nid' => '১০ বা ১৭ ডিজিটের এনআইডি',
        'placeholder_phone' => '01XXXXXXXXX',
        'placeholder_email' => 'ঐচ্ছিক — যেমন: you@example.com',
        'placeholder_address' => 'বাড়ি, সড়ক, এলাকার বিবরণ দিন...',
        'select_gender' => 'লিঙ্গ নির্বাচন করুন',
        'select_division' => 'বিভাগ নির্বাচন করুন',
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
    <main class="flex-1 max-w-3xl w-full mx-auto p-4 md:p-6 space-y-6">

        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 justify-start">
            <a href="login.php" class="hover:text-accent transition-colors flex items-center gap-1 font-semibold">
                <i class="ti ti-arrow-left text-base"></i> <?php echo $cur['back_login']; ?>
            </a>
            <i class="ti ti-chevron-right text-xs"></i>
            <span class="text-gray-700 font-bold"><?php echo $cur['heading']; ?></span>
        </div>

        <!-- Registration Card -->
        <div class="bg-white rounded-2xl shadow-md border border-slate-100 overflow-hidden">

            <!-- Card Header -->
            <div class="bg-navy px-8 py-6 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
                    <i class="ti ti-user-plus"></i>
                </div>
                <div>
                    <h1 class="text-white text-xl font-bold leading-tight"><?php echo $cur['heading']; ?></h1>
                    <p class="text-white/55 text-xs mt-0.5"><?php echo $cur['subheading']; ?></p>
                </div>
            </div>

            <!-- Step Indicators -->
            <div class="bg-[#f8f9fc] border-b border-gray-100 px-8 py-3 flex items-center gap-0">
                <?php
                $steps = [
                    ['icon' => 'ti-user', 'label' => $cur['step1_title']],
                    ['icon' => 'ti-map-pin', 'label' => $cur['step2_title']],
                    ['icon' => 'ti-lock', 'label' => $cur['step3_title']],
                ];
                foreach ($steps as $i => $s):
                    $num = $i + 1;
                ?>
                    <div class="step-item flex items-center gap-2 flex-1 <?php echo $i > 0 ? 'ml-2' : ''; ?>" data-step="<?php echo $num; ?>">
                        <?php if ($i > 0): ?>
                            <div class="step-line h-px flex-1 bg-gray-200 transition-colors duration-300" id="line-<?php echo $i; ?>"></div>
                        <?php endif; ?>
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-500 transition-all duration-300" id="circle-<?php echo $num; ?>">
                                <?php echo $num; ?>
                            </div>
                            <span class="step-label text-xs font-semibold text-gray-400 hidden sm:inline transition-colors duration-300" id="label-<?php echo $num; ?>">
                                <?php echo $s['label']; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Form Content -->
            <div class="px-8 py-8">
                <!-- Global Alert -->
                <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

                <form id="reg-form" novalidate>
                    
                    <!-- STEP 1 - Personal Info -->
                    <div class="step-panel" id="panel-1">
                        <h2 class="text-navy font-bold text-base mb-5 flex items-center gap-2">
                            <i class="ti ti-user-circle text-accent text-lg"></i>
                            <?php echo $cur['step1_desc']; ?>
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Full Name -->
                            <div class="md:col-span-2">
                                <label for="full_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_fullname']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                        <i class="ti ti-user"></i>
                                    </span>
                                    <input type="text" id="full_name" name="full_name" required
                                           placeholder="<?php echo $cur['placeholder_fullname']; ?>"
                                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-full_name">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- National ID -->
                            <div>
                                <label for="national_id" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_nid']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                        <i class="ti ti-id"></i>
                                    </span>
                                    <input type="text" id="national_id" name="national_id" required
                                           placeholder="<?php echo $cur['placeholder_nid']; ?>"
                                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-national_id">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Date of Birth -->
                            <div>
                                <label for="date_of_birth" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_dob']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <input type="date" id="date_of_birth" name="date_of_birth" required
                                           class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-date_of_birth">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_gender']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none z-10">
                                        <i class="ti ti-gender-transgender"></i>
                                    </span>
                                    <select id="gender" name="gender" required
                                            class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition appearance-none bg-white text-navy font-medium">
                                        <option value=""><?php echo $cur['select_gender']; ?></option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-gender">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Mobile Phone -->
                            <div>
                                <label for="phone" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_phone']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                        <i class="ti ti-phone"></i>
                                    </span>
                                    <input type="text" id="phone" name="phone" required
                                           placeholder="<?php echo $cur['placeholder_phone']; ?>"
                                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-phone">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Email Address -->
                            <div class="md:col-span-2">
                                <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_email']; ?>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                        <i class="ti ti-mail"></i>
                                    </span>
                                    <input type="email" id="email" name="email"
                                           placeholder="<?php echo $cur['placeholder_email']; ?>"
                                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-email">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 - Location & System Role -->
                    <div class="step-panel hidden" id="panel-2">
                        <h2 class="text-navy font-bold text-base mb-5 flex items-center gap-2">
                            <i class="ti ti-map-pin text-accent text-lg"></i>
                            <?php echo $cur['step2_desc']; ?>
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Division -->
                            <div>
                                <label for="division" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_division']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none z-10">
                                        <i class="ti ti-map"></i>
                                    </span>
                                    <select id="division" name="division" required
                                            class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition appearance-none bg-white text-navy font-medium">
                                        <option value=""><?php echo $cur['select_division']; ?></option>
                                        <option>Barisal</option>
                                        <option>Chattogram</option>
                                        <option>Dhaka</option>
                                        <option>Khulna</option>
                                        <option>Mymensingh</option>
                                        <option>Rajshahi</option>
                                        <option>Rangpur</option>
                                        <option>Sylhet</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-division">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- District -->
                            <div>
                                <label for="district" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_district']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                                        <i class="ti ti-map-pin"></i>
                                    </span>
                                    <input type="text" id="district" name="district" required
                                           placeholder="e.g. Dhaka"
                                           class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-district">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Detailed Address -->
                            <div class="md:col-span-2">
                                <label for="address" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_address']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <textarea id="address" name="address" required rows="2"
                                              placeholder="<?php echo $cur['placeholder_address']; ?>"
                                              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent text-navy"></textarea>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-address">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Role -->
                            <div>
                                <label for="role" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_role']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none z-10">
                                        <i class="ti ti-badge"></i>
                                    </span>
                                    <select id="role" name="role" required
                                            class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition appearance-none bg-white text-navy font-medium">
                                        <option value="Admin">Admin</option>
                                        <option value="Officer">Officer</option>
                                        <option value="Investigator">Investigator</option>
                                        <option value="Citizen">Citizen</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-role">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_status']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none z-10">
                                        <i class="ti ti-activity"></i>
                                    </span>
                                    <select id="status" name="status" required
                                            class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition appearance-none bg-white text-navy font-medium">
                                        <option value="Active"><?php echo $cur['active']; ?></option>
                                        <option value="Suspended"><?php echo $cur['suspended']; ?></option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-status">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 - Security & Password -->
                    <div class="step-panel hidden" id="panel-3">
                        <h2 class="text-navy font-bold text-base mb-5 flex items-center gap-2">
                            <i class="ti ti-lock text-accent text-lg"></i>
                            <?php echo $cur['step3_desc']; ?>
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_password']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                                        <i class="ti ti-lock"></i>
                                    </span>
                                    <input type="password" id="password" name="password" required
                                           placeholder="••••••••"
                                           class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                    <button type="button" onclick="togglePwdVisibility('password', 'eye-pwd')"
                                            class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <i class="ti ti-eye text-base" id="eye-pwd"></i>
                                    </button>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-password">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirm" class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    <?php echo $cur['lbl_password_confirm']; ?> <span class="text-red-400">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-gray-400">
                                        <i class="ti ti-lock-check"></i>
                                    </span>
                                    <input type="password" id="password_confirm" name="password_confirm" required
                                           placeholder="••••••••"
                                           class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent transition placeholder-gray-300 text-navy">
                                    <button type="button" onclick="togglePwdVisibility('password_confirm', 'eye-pwd-confirm')"
                                            class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <i class="ti ti-eye text-base" id="eye-pwd-confirm"></i>
                                    </button>
                                </div>
                                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-password_confirm">
                                    <i class="ti ti-alert-circle"></i> <span></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Row -->
                    <div class="flex justify-between items-center pt-6 mt-8 border-t border-slate-100">
                        <button type="button" id="prev-btn" onclick="prevStep()"
                                class="hidden px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition-colors text-slate-700">
                            <i class="ti ti-arrow-left text-base inline-block mr-1"></i>
                            <?php echo $cur['btn_prev']; ?>
                        </button>
                        
                        <a href="login.php" id="cancel-btn"
                           class="px-5 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition-colors text-slate-700">
                            <?php echo $cur['btn_cancel']; ?>
                        </a>

                        <button type="button" id="next-btn" onclick="nextStep()"
                                class="bg-accent hover:bg-accent-dark text-white font-bold px-5 py-2.5 rounded-xl text-sm shadow transition-colors ml-auto flex items-center gap-1">
                            <?php echo $cur['btn_next']; ?>
                            <i class="ti ti-arrow-right text-base inline-block ml-1"></i>
                        </button>

                        <button type="submit" id="submit-btn" class="hidden bg-accent hover:bg-accent-dark text-white font-bold px-6 py-2.5 rounded-xl text-sm shadow transition-colors ml-auto flex items-center gap-1">
                            <i class="ti ti-user-check text-base"></i>
                            <?php echo $cur['btn_submit']; ?>
                        </button>
                    </div>

                </form>

                <!-- Success Panel -->
                <div id="success-panel" class="hidden text-center py-8">
                    <div class="w-20 h-20 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center mx-auto mb-5 text-emerald-500 shadow-sm text-5xl">
                        <i class="ti ti-circle-check"></i>
                    </div>
                    <h2 class="text-navy text-2xl font-bold mb-2">
                        <?php echo $lang === 'bn' ? 'নিবন্ধন সফল হয়েছে!' : 'Account Created Successfully!'; ?>
                    </h2>
                    <p class="text-slate-500 mb-6 text-sm max-w-sm mx-auto">
                        <?php echo $lang === 'bn' ? 'আপনার অ্যাকাউন্টটি সফলভাবে তৈরি হয়েছে। লগইন পৃষ্ঠায় নিয়ে যাওয়া হচ্ছে...' : 'Your account has been registered successfully. Redirecting you to the login page...'; ?>
                    </p>
                </div>

            </div>
        </div>

        <!-- Back Link -->
        <div class="text-center">
            <a href="login.php" class="inline-flex items-center gap-1.5 text-sm font-semibold text-accent hover:text-accent-dark transition-colors">
                <i class="ti ti-arrow-left"></i>
                <?php echo $cur['back_login']; ?>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-navy border-t border-white/10 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-xs text-[#8FA3C8]">
                <i class="ti ti-copyright text-[11px] inline-block mr-0.5"></i> 2025 CaseFlowX — Case Management Platform
            </p>
        </div>
    </footer>

    <!-- Multi-step Wizard JS -->
    <script>
        let currentStep = 1;
        const totalSteps = 3;

        function updateStepsUI() {
            for (let i = 1; i <= totalSteps; i++) {
                const panel = document.getElementById('panel-' + i);
                if (i === currentStep) {
                    panel.classList.remove('hidden');
                } else {
                    panel.classList.add('hidden');
                }

                const circle = document.getElementById('circle-' + i);
                const label = document.getElementById('label-' + i);
                
                if (i < currentStep) {
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-accent text-white shadow-sm border border-accent";
                    circle.innerHTML = '<i class="ti ti-check text-base"></i>';
                    if (label) label.className = "step-label text-xs font-semibold text-accent";
                } else if (i === currentStep) {
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-navy text-white shadow-sm border border-navy";
                    circle.innerHTML = i;
                    if (label) label.className = "step-label text-xs font-semibold text-navy";
                } else {
                    circle.className = "step-circle w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-500 border border-transparent";
                    circle.innerHTML = i;
                    if (label) label.className = "step-label text-xs font-semibold text-gray-400";
                }

                if (i > 1) {
                    const line = document.getElementById('line-' + (i - 1));
                    if (i <= currentStep) {
                        line.classList.remove('bg-gray-200');
                        line.classList.add('bg-accent');
                    } else {
                        line.classList.remove('bg-accent');
                        line.classList.add('bg-gray-200');
                    }
                }
            }

            const prevBtn = document.getElementById('prev-btn');
            const cancelBtn = document.getElementById('cancel-btn');
            const nextBtn = document.getElementById('next-btn');
            const submitBtn = document.getElementById('submit-btn');

            if (currentStep === 1) {
                prevBtn.classList.add('hidden');
                cancelBtn.classList.remove('hidden');
            } else {
                prevBtn.classList.remove('hidden');
                cancelBtn.classList.add('hidden');
            }

            if (currentStep === totalSteps) {
                nextBtn.classList.add('hidden');
                submitBtn.classList.remove('hidden');
            } else {
                nextBtn.classList.remove('hidden');
                submitBtn.classList.add('hidden');
            }
        }

        updateStepsUI();

        function clearErrors() {
            document.querySelectorAll('.err-msg').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('input, select, textarea').forEach(el => {
                el.classList.remove('border-red-400', 'ring-2', 'ring-red-100');
                el.classList.add('border-gray-200');
            });
        }

        function showError(field, msg) {
            const el = document.getElementById('err-' + field);
            const input = document.getElementById(field);
            if (el) {
                el.classList.remove('hidden');
                el.querySelector('span').textContent = msg;
            }
            if (input) {
                input.classList.remove('border-gray-200');
                input.classList.add('border-red-400', 'ring-2', 'ring-red-100');
            }
        }

        function validateStep1() {
            clearErrors();
            let valid = true;
            
            const fullName = document.getElementById('full_name').value.trim();
            const nid = document.getElementById('national_id').value.trim();
            const dob = document.getElementById('date_of_birth').value;
            const gender = document.getElementById('gender').value;
            const phone = document.getElementById('phone').value.trim();
            const email = document.getElementById('email').value.trim();

            if (!fullName) {
                showError('full_name', "<?php echo $lang === 'bn' ? 'পূর্ণ নাম আবশ্যক।' : 'Full Name is required.'; ?>");
                valid = false;
            }

            if (!nid) {
                showError('national_id', "<?php echo $lang === 'bn' ? 'এনআইডি আবশ্যক।' : 'National ID is required.'; ?>");
                valid = false;
            } else if (!/^\d{10}$|^\d{17}$/.test(nid)) {
                showError('national_id', "<?php echo $lang === 'bn' ? 'এনআইডি অবশ্যই ১০ বা ১৭ ডিজিটের হতে হবে।' : 'NID must be 10 or 17 digits.'; ?>");
                valid = false;
            }

            if (!dob) {
                showError('date_of_birth', "<?php echo $lang === 'bn' ? 'জন্ম তারিখ আবশ্যক।' : 'Date of birth is required.'; ?>");
                valid = false;
            }

            if (!gender) {
                showError('gender', "<?php echo $lang === 'bn' ? 'লিঙ্গ নির্বাচন করুন।' : 'Please select gender.'; ?>");
                valid = false;
            }

            if (!phone) {
                showError('phone', "<?php echo $lang === 'bn' ? 'মোবাইল নম্বর আবশ্যক।' : 'Mobile Phone is required.'; ?>");
                valid = false;
            } else if (!/^01[3-9]\d{8}$/.test(phone)) {
                showError('phone', "<?php echo $lang === 'bn' ? 'সঠিক মোবাইল নম্বর (যেমন: 01XXXXXXXXX) প্রদান করুন।' : 'Enter a valid Bangladeshi phone number.'; ?>");
                valid = false;
            }

            if (email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showError('email', "<?php echo $lang === 'bn' ? 'সঠিক ইমেল ঠিকানা প্রদান করুন।' : 'Please enter a valid email address.'; ?>");
                    valid = false;
                }
            }

            return valid;
        }

        function validateStep2() {
            clearErrors();
            let valid = true;
            
            const division = document.getElementById('division').value;
            const district = document.getElementById('district').value.trim();
            const address = document.getElementById('address').value.trim();
            const role = document.getElementById('role').value;
            const status = document.getElementById('status').value;

            if (!division) {
                showError('division', "<?php echo $lang === 'bn' ? 'বিভাগ নির্বাচন করুন।' : 'Division is required.'; ?>");
                valid = false;
            }
            if (!district) {
                showError('district', "<?php echo $lang === 'bn' ? 'জেলা আবশ্যক।' : 'District is required.'; ?>");
                valid = false;
            }
            if (!address) {
                showError('address', "<?php echo $lang === 'bn' ? 'ঠিকানা আবশ্যক।' : 'Address is required.'; ?>");
                valid = false;
            }
            if (!role) {
                showError('role', 'Role is required.');
                valid = false;
            }
            if (!status) {
                showError('status', 'Status is required.');
                valid = false;
            }

            return valid;
        }

        function validateStep3() {
            clearErrors();
            let valid = true;
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            if (!password) {
                showError('password', "<?php echo $lang === 'bn' ? 'পাসওয়ার্ড আবশ্যক।' : 'Password is required.'; ?>");
                valid = false;
            } else if (password.length < 6) {
                showError('password', "<?php echo $lang === 'bn' ? 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষরের হতে হবে।' : 'Password must be at least 6 characters.'; ?>");
                valid = false;
            }

            if (password !== passwordConfirm) {
                showError('password_confirm', "<?php echo $lang === 'bn' ? 'পাসওয়ার্ড দুটি মেলেনি।' : 'Passwords do not match.'; ?>");
                valid = false;
            }

            return valid;
        }

        function nextStep() {
            if (currentStep === 1 && !validateStep1()) return;
            if (currentStep === 2 && !validateStep2()) return;
            
            if (currentStep < totalSteps) {
                currentStep++;
                updateStepsUI();
            }
        }

        // Form Submit Handler
        document.getElementById('reg-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            clearErrors();

            if (!validateStep3()) return;

            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Processing…';

            const global = document.getElementById('alert-global');
            global.classList.add('hidden');

            try {
                const formData = new FormData(this);
                const resp = await fetch('signup_action.php', { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.success) {
                    document.getElementById('reg-form').classList.add('hidden');
                    document.getElementById('success-panel').classList.remove('hidden');
                    setTimeout(() => {
                        window.location.href = data.redirect || 'login.php';
                    }, 1500);
                } else {
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
                        
                        // Redirect to step containing error
                        if (data.errors.full_name || data.errors.national_id || data.errors.date_of_birth || data.errors.gender || data.errors.phone || data.errors.email) {
                            currentStep = 1;
                        } else if (data.errors.division || data.errors.district || data.errors.address || data.errors.role || data.errors.status) {
                            currentStep = 2;
                        } else if (data.errors.password || data.errors.password_confirm) {
                            currentStep = 3;
                        }
                        updateStepsUI();
                    }
                    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700 font-medium';
                    global.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5 text-red-600"></i><span>${data.message}</span>`;
                    global.classList.remove('hidden');
                }
            } catch (err) {
                global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700 font-medium';
                global.innerHTML = '<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5 text-red-600"></i><span>A network error occurred. Please try again.</span>';
                global.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="ti ti-user-check text-base"></i> <?php echo $cur["btn_submit"]; ?>';
            }
        });

        function togglePwdVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'ti ti-eye-off text-base';
            } else {
                input.type = 'password';
                icon.className = 'ti ti-eye text-base';
            }
        }
    </script>
</body>
</html>
