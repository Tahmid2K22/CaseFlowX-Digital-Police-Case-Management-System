<?php
/**
 * officer-login.php — CaseFlowX FIR Officer Login Page
 */
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — FIR Officer Login
     Tailwind CSS via CDN · Tabler Icons · PHP backend
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Tailwind + Tabler -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FIR Officer Login — CaseFlowX</title>
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
    <div class="min-h-screen bg-[#F4F6F9] py-10 px-4">

  <!-- Breadcrumb & Back -->
  <div class="max-w-md mx-auto mb-5 flex items-center justify-between">
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <a href="index.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Home
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium">FIR Officer Login</span>
    </div>
    <button onclick="history.back()" class="text-gray-500 hover:text-navy transition-colors flex items-center gap-1 text-xs font-semibold border border-slate-200 px-2.5 py-1 rounded-xl bg-slate-50 hover:bg-slate-100 transition shadow-sm">
      <i class="ti ti-arrow-left"></i> Back
    </button>
  </div>

  <!-- Card -->
  <div class="max-w-md mx-auto bg-white rounded-2xl shadow-md overflow-hidden">

    <!-- Card header -->
    <div class="bg-navy px-8 py-6 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
        <i class="ti ti-shield"></i>
      </div>
      <div>
        <h1 class="text-white text-xl font-bold leading-tight">FIR Officer Login</h1>
        <p class="text-white/55 text-sm mt-0.5">Sign in to file and manage FIRs</p>
      </div>
    </div>

    <div class="px-8 py-8">

      <!-- Global alert (shown on server/fetch errors) -->
      <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

      <form id="login-form" novalidate>

        <!-- Badge Number -->
        <div class="mb-4">
          <label for="badge_number" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Badge Number <span class="text-red-400">*</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
              <i class="ti ti-id-badge text-base"></i>
            </span>
            <input type="text" id="badge_number" name="badge_number"
                   placeholder="Enter your badge number"
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                          text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                          transition placeholder-gray-300">
          </div>
          <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-badge_number">
            <i class="ti ti-alert-circle text-sm"></i> <span></span>
          </p>
        </div>

        <!-- Password -->
        <div class="mb-2">
          <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Password <span class="text-red-400">*</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
              <i class="ti ti-lock text-base"></i>
            </span>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200
                          text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                          transition placeholder-gray-300">
            <button type="button" onclick="togglePwd('password')"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
              <i class="ti ti-eye text-base" id="eye-password"></i>
            </button>
          </div>
          <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-password">
            <i class="ti ti-alert-circle text-sm"></i> <span></span>
          </p>
        </div>

        <!-- Remember me -->
        <div class="flex items-center justify-between mb-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="remember" name="remember"
                   class="w-4 h-4 rounded accent-accent cursor-pointer">
            <span class="text-sm text-gray-600">Remember me</span>
          </label>
        </div>

        <!-- Submit button -->
        <button type="submit" id="submit-btn"
                class="w-full bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                       text-sm font-semibold flex items-center justify-center gap-2 transition">
          <i class="ti ti-login text-base"></i> Sign In
        </button>

      </form>

      <!-- ── Success state (redirect) ─────────────────────── -->
      <div id="success-panel" class="hidden text-center py-8">
        <div class="w-20 h-20 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-5">
          <i class="ti ti-circle-check text-accent text-5xl"></i>
        </div>
        <h2 class="text-navy text-2xl font-bold mb-2">Login Successful!</h2>
        <p class="text-gray-500 mb-6 text-sm max-w-sm mx-auto">
          Redirecting you to FIR filing...
        </p>
      </div>

    </div><!-- /px-8 py-8 -->

    <!-- Card footer -->
    <div class="bg-[#f8f9fc] border-t border-gray-100 px-8 py-4 text-center text-sm text-gray-500">
      New officer? <a href="officer-register.php" class="text-accent font-semibold hover:underline ml-1">Create account</a>
    </div>

  </div><!-- /card -->
</div>
  </div>
</div>

<footer class="bg-navy border-t border-white/10 py-6 mt-8">
  <div class="max-w-7xl mx-auto px-4 text-center">
    <p class="text-xs text-[#8FA3C8]">
      © 2026 CaseFlowX
    </p>
  </div>
</footer>

<!-- ── JavaScript ───────────────────────────────────────────────────────── -->
<script>
/* ── Show/hide password ─────────────────────────────────────────────────── */
function togglePwd(id) {
  const inp = document.getElementById(id);
  const ico = document.getElementById('eye-' + id);
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'ti ti-eye-off text-base';
  } else {
    inp.type = 'password';
    ico.className = 'ti ti-eye text-base';
  }
}

/* ── Client-side validation ─────────────────────────────────────────────── */
function clearErrors() {
  document.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
  document.getElementById('badge_number').classList.remove('border-red-400','ring-red-200');
  document.getElementById('badge_number').classList.add('border-gray-200');
  document.getElementById('password').classList.remove('border-red-400','ring-red-200');
  document.getElementById('password').classList.add('border-gray-200');
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

function validateForm() {
  clearErrors();
  let ok = true;

  const badge_number = document.getElementById('badge_number').value.trim();
  const password = document.getElementById('password').value;

  if (!badge_number) {
    showError('badge_number', 'Badge number is required.');
    ok = false;
  }

  if (!password) {
    showError('password', 'Password is required.');
    ok = false;
  }

  return ok;
}

document.getElementById('login-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors();

  if (!validateForm()) return;

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Signing in…';

  const global = document.getElementById('alert-global');
  global.classList.add('hidden');

  try {
    const formData = new FormData(this);
    const resp = await fetch('officer-login_action.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success) {
      document.getElementById('login-form').classList.add('hidden');
      document.getElementById('success-panel').classList.remove('hidden');
      // Redirect after brief delay
      setTimeout(() => {
        window.location.href = data.redirect || 'officer-dashboard.php';
      }, 1500);
    } else {
      if (data.errors) {
        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
      }
      global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
      global.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${data.message}</span>`;
      global.classList.remove('hidden');
    }
  } catch (err) {
    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
    global.innerHTML = '<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>Network error. Please check your connection and try again.</span>';
    global.classList.remove('hidden');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-login text-base"></i> Sign In';
  }
});
</script>
</body>
</html>