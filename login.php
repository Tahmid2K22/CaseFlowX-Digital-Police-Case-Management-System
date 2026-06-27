<?php
/**
 * login.php — CaseFlowX Citizen Login Page
 * Rendered inside master.html layout.
 */
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — Citizen Login
     Tailwind CSS via CDN · Tabler Icons · PHP backend
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Tailwind + Tabler -->
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

<!-- ── Page content ─────────────────────────────────────────────────────── -->
<div class="min-h-screen bg-[#F4F6F9] py-10 px-4">

  <!-- Breadcrumb -->
  <div class="max-w-md mx-auto mb-5 flex items-center gap-2 text-sm text-gray-500">
    <a href="home.php" class="hover:text-accent transition-colors flex items-center gap-1">
      <i class="ti ti-home text-base"></i> Home
    </a>
    <i class="ti ti-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium">Citizen Login</span>
  </div>

  <!-- Card -->
  <div class="max-w-md mx-auto bg-white rounded-2xl shadow-md overflow-hidden">

    <!-- Card header -->
    <div class="bg-navy px-8 py-6 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
        <i class="ti ti-login"></i>
      </div>
      <div>
        <h1 class="text-white text-xl font-bold leading-tight">Citizen Login</h1>
        <p class="text-white/55 text-sm mt-0.5">Sign in to file complaints &amp; track cases</p>
      </div>
    </div>

    <div class="px-8 py-8">

      <!-- Global alert (shown on server/fetch errors) -->
      <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

      <form id="login-form" novalidate>

        <!-- Phone / NID -->
        <div class="mb-4">
          <label for="identifier" class="block text-xs font-semibold text-gray-600 mb-1.5">
            Phone Number or NID <span class="text-red-400">*</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
              <i class="ti ti-user text-base"></i>
            </span>
            <input type="text" id="identifier" name="identifier"
                   placeholder="01XXXXXXXXX or 10/17-digit NID"
                   class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                          text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                          transition placeholder-gray-300">
          </div>
          <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-identifier">
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

        <!-- Remember me & Forgot password -->
        <div class="flex items-center justify-between mb-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" id="remember" name="remember"
                   class="w-4 h-4 rounded accent-accent cursor-pointer">
            <span class="text-sm text-gray-600">Remember me</span>
          </label>
          <a href="#" class="text-sm text-accent font-medium hover:underline">Forgot password?</a>
        </div>

        <!-- Submit button -->
        <button type="submit" id="submit-btn"
                class="w-full bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                       text-sm font-semibold flex items-center justify-center gap-2 transition mb-4">
          <i class="ti ti-login text-base"></i> Sign In
        </button>

        <!-- Track status quick link -->
        <div class="text-center">
          <a href="track-status.php" class="inline-flex items-center gap-1.5 text-xs text-navy hover:text-accent font-semibold transition">
            <i class="ti ti-radar text-sm"></i> Track Complaint Status
          </a>
        </div>

      </form>

      <!-- ── Success state (redirect) ─────────────────────── -->
      <div id="success-panel" class="hidden text-center py-8">
        <div class="w-20 h-20 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-5">
          <i class="ti ti-circle-check text-accent text-5xl"></i>
        </div>
        <h2 class="text-navy text-2xl font-bold mb-2">Login Successful!</h2>
        <p class="text-gray-500 mb-6 text-sm max-w-sm mx-auto">
          Welcome back! Redirecting you to your dashboard...
        </p>
      </div>

    </div><!-- /px-8 py-8 -->

    <!-- Card footer -->
    <div class="bg-[#f8f9fc] border-t border-gray-100 px-8 py-4 text-center text-sm text-gray-500">
      Don't have an account?
      <a href="register(2).php" class="text-accent font-semibold hover:underline ml-1">Create one now</a>
    </div>

  </div><!-- /card -->
</div>

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
  document.getElementById('identifier').classList.remove('border-red-400','ring-red-200');
  document.getElementById('identifier').classList.add('border-gray-200');
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

  const identifier = document.getElementById('identifier').value.trim();
  const password = document.getElementById('password').value;

  if (!identifier) {
    showError('identifier', 'Phone or NID is required.');
    ok = false;
  } else {
    // Check if it looks like a phone or NID
    const isPhone = /^01[3-9]\d{8}$/.test(identifier);
    const isNID = /^\d{10}$|^\d{17}$/.test(identifier);
    if (!isPhone && !isNID) {
      showError('identifier', 'Enter a valid phone number or NID.');
      ok = false;
    }
  }

  if (!password) {
    showError('password', 'Password is required.');
    ok = false;
  }

  return ok;
}

/* ── Form submission ─────────────────────────────────────────────────────── */
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
    const resp = await fetch('login_action.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success) {
      document.getElementById('login-form').classList.add('hidden');
      document.getElementById('success-panel').classList.remove('hidden');
      // Redirect after brief delay
      setTimeout(() => {
        window.location.href = data.redirect || 'dashboard.php';
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
