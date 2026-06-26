<?php
/**
 * support.php — CaseFlowX
 * Support & help center for logged-in citizens.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

if (!is_logged_in() && (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id']))) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Support — CaseFlowX</title>
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
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb & Back -->
    <div class="mb-5 flex items-center justify-between">
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="dashboard.php" class="hover:text-accent transition-colors flex items-center gap-1">
          <i class="ti ti-home text-base"></i> Dashboard
        </a>
        <i class="ti ti-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Support</span>
      </div>
      <button onclick="history.back()" class="text-gray-500 hover:text-navy transition-colors flex items-center gap-1 text-xs font-semibold border border-slate-200 px-2.5 py-1 rounded-xl bg-slate-50 hover:bg-slate-100 transition shadow-sm">
        <i class="ti ti-arrow-left"></i> Back
      </button>
    </div>

    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-navy">Help & Support</h1>
      <p class="text-gray-500 mt-1">Find answers or get in touch with our team.</p>
    </div>

    <!-- FAQ Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-semibold text-base mb-4 flex items-center gap-2">
          <i class="ti ti-file-plus text-accent"></i> Filing a Case
        </h2>
        <div class="space-y-4 text-sm text-gray-600">
          <div>
            <p class="font-semibold text-navy text-sm mb-1">How do I file a complaint?</p>
            <p>Go to your dashboard and click "File Complaint" or "New Case" in Quick Actions. Fill in the title, description, and priority, then submit.</p>
          </div>
          <div>
            <p class="font-semibold text-navy text-sm mb-1">What should I include in the description?</p>
            <p>Include the exact location, date and time you noticed the issue, and any relevant details that can help us resolve it faster.</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-semibold text-base mb-4 flex items-center gap-2">
          <i class="ti ti-clock-hour-4 text-accent"></i> Tracking & Responses
        </h2>
        <div class="space-y-4 text-sm text-gray-600">
          <div>
            <p class="font-semibold text-navy text-sm mb-1">How do I track my case?</p>
            <p>Visit the <a href="cases.php" class="text-accent hover:underline">My Cases</a> page from your dashboard to see all your complaints and their current status.</p>
          </div>
          <div>
            <p class="font-semibold text-navy text-sm mb-1">How long does resolution take?</p>
            <p>High-priority cases are typically addressed within 24 hours. Medium and low priority cases may take 3–7 business days.</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-semibold text-base mb-4 flex items-center gap-2">
          <i class="ti ti-shield-check text-accent"></i> Account & Privacy
        </h2>
        <div class="space-y-4 text-sm text-gray-600">
          <div>
            <p class="font-semibold text-navy text-sm mb-1">Is my information secure?</p>
            <p>Yes. Your personal data is stored securely and is only used for processing your complaints and contacting you about updates.</p>
          </div>
          <div>
            <p class="font-semibold text-navy text-sm mb-1">How do I update my profile?</p>
            <p>Use the "Edit Profile" button on your dashboard to update your contact details and address.</p>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-navy font-semibold text-base mb-4 flex items-center gap-2">
          <i class="ti ti-phone text-accent"></i> Contact Us
        </h2>
        <div class="space-y-4 text-sm text-gray-600">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-accent/10 flex items-center justify-center text-accent">
              <i class="ti ti-phone"></i>
            </div>
            <div>
              <p class="font-medium text-navy">Helpline</p>
              <p>+880 1234-567890</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-accent/10 flex items-center justify-center text-accent">
              <i class="ti ti-mail"></i>
            </div>
            <div>
              <p class="font-medium text-navy">Email</p>
              <p>support@caseflowx.local</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-accent/10 flex items-center justify-center text-accent">
              <i class="ti ti-map-pin"></i>
            </div>
            <div>
              <p class="font-medium text-navy">Office</p>
              <p>Dhaka, Bangladesh</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Back link -->
    <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
      <i class="ti ti-arrow-left text-xs"></i> Back to Dashboard
    </a>
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
