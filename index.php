<?php
/**
 * index.php — CaseFlowX Home
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CaseFlowX — Welcome</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#F4F6F9] min-h-screen flex flex-col justify-between">
    <div class="flex-grow flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center border border-slate-100">
            <h1 class="text-3xl font-bold text-[#1B2A4A] mb-4">CaseFlowX</h1>
            <p class="text-gray-600 mb-8">Digital FIR and Case Management System</p>
            
            <div class="space-y-4">
                <a href="officer-login.php" class="block w-full bg-[#1D9E75] hover:bg-[#0F6E56] text-white font-semibold py-3 rounded-xl transition shadow-md">
                    Officer Portal
                </a>
                <p class="text-sm text-gray-400">Citizen portal coming soon</p>
            </div>
        </div>
    </div>

    <footer class="py-6">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-xs text-gray-500 font-medium">
                © 2026 CaseFlowX
            </p>
        </div>
    </footer>
</body>
</html>
