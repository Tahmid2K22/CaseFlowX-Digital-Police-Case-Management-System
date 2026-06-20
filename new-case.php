<?php
/**
 * new-case.php — CaseFlowX
 * Form to file a new complaint. Rendered inside master.html layout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Case — CaseFlowX</title>
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
    <div class="min-h-screen bg-[#F4F6F9] py-8 px-4">
  <div class="max-w-3xl mx-auto">
    <!-- Breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
      <a href="dashboard.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Dashboard
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium">New Case</span>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-navy px-8 py-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
          <i class="ti ti-file-plus"></i>
        </div>
        <div>
          <h1 class="text-white text-xl font-bold leading-tight">File a Complaint</h1>
          <p class="text-white/55 text-sm mt-0.5">Describe your issue and we will take it from there</p>
        </div>
      </div>

      <div class="px-8 py-8">
        <!-- Global alert -->
        <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

        <form id="case-form" novalidate>
          <!-- Title -->
          <div class="mb-5">
            <label for="title" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Case Title <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-heading text-base"></i>
              </span>
              <input type="text" id="title" name="title"
                     placeholder="e.g. Street Light Issue in Main Road"
                     required
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                            text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-title">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Description -->
          <div class="mb-5">
            <label for="description" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Description <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute top-3 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-align-left text-base"></i>
              </span>
              <textarea id="description" name="description" rows="5"
                        placeholder="Provide detailed information about the issue..."
                        required
                        class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                               text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                               transition placeholder-gray-300 resize-y"></textarea>
            </div>
            <p class="text-xs text-gray-400 mt-1">Include location, date observed, and any relevant details.</p>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-description">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Priority -->
          <div class="mb-6">
            <label for="priority" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Priority <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-flag text-base"></i>
              </span>
              <select id="priority" name="priority" required
                      class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                             text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                             transition bg-white">
                <option value="low">Low — Minor issue, no urgency</option>
                <option value="medium" selected>Medium — Moderate concern</option>
                <option value="high">High — Serious problem needing immediate attention</option>
              </select>
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-priority">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Submit -->
          <div class="flex items-center gap-3">
            <button type="submit" id="submit-btn"
                    class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                           text-sm font-semibold flex items-center gap-2 transition">
              <i class="ti ti-send text-base"></i> Submit Complaint
            </button>
            <a href="dashboard1.php"
               class="px-6 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
  </div>

  <footer class="py-6 mt-8">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-xs text-gray-500 font-medium">
        © 2026 CaseFlowX
      </p>
    </div>
  </footer>

<script>
function clearErrors() {
  document.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
  document.querySelectorAll('input, textarea, select').forEach(el => {
    el.classList.remove('border-red-400', 'ring-2', 'ring-red-100');
    el.classList.add('border-gray-200');
  });
}

function showError(field, msg) {
  const el = document.getElementById('err-' + field);
  const input = document.getElementById(field);
  if (el) { el.classList.remove('hidden'); el.querySelector('span').textContent = msg; }
  if (input) {
    input.classList.remove('border-gray-200');
    input.classList.add('border-red-400', 'ring-2', 'ring-red-100');
  }
}

document.getElementById('case-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors();

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Submitting…';

  const global = document.getElementById('alert-global');
  global.classList.add('hidden');

  try {
    const resp = await fetch('submit_case.php', { method: 'POST', body: new FormData(this) });
    const data = await resp.json();

    if (data.success) {
      window.location.href = data.redirect || 'cases.php';
    } else {
      if (data.errors) {
        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
      }
      global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
      global.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${data.message || 'Something went wrong.'}</span>`;
      global.classList.remove('hidden');
    }
  } catch (err) {
    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
    global.innerHTML = '<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>Network error. Please check your connection and try again.</span>';
    global.classList.remove('hidden');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-send text-base"></i> Submit Complaint';
  }
});
</script>
</body>
</html>
