<?php
/**
 * officer-register.php — CaseFlowX FIR Officer Registration Page
 */
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — FIR Officer Registration
     Tailwind CSS via CDN · Tabler Icons · PHP backend
     ═══════════════════════════════════════════════════════════════════════════ -->

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>FIR Officer Registration — CaseFlowX</title>
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
  <div class="max-w-3xl mx-auto mb-5 flex items-center justify-between">
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <a href="index.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Home
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium">Officer Registration</span>
    </div>
    <button onclick="history.back()" class="text-gray-500 hover:text-navy transition-colors flex items-center gap-1 text-xs font-semibold border border-slate-200 px-2.5 py-1 rounded-xl bg-slate-50 hover:bg-slate-100 transition shadow-sm">
      <i class="ti ti-arrow-left"></i> Back
    </button>
  </div>

  <!-- Card -->
  <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md overflow-hidden">

    <!-- Card header -->
    <div class="bg-navy px-8 py-6 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
        <i class="ti ti-shield-plus"></i>
      </div>
      <div>
        <h1 class="text-white text-xl font-bold leading-tight">FIR Officer Registration</h1>
        <p class="text-white/55 text-sm mt-0.5">Create your officer account to file and manage FIRs</p>
      </div>
    </div>

    <div class="px-8 py-8">

      <!-- Global alert -->
      <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

      <form id="register-form" novalidate>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

          <!-- Badge Number -->
          <div>
            <label for="badge_number" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Badge Number <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-id-badge text-base"></i>
              </span>
              <input type="text" id="badge_number" name="badge_number"
                     placeholder="e.g. HQ-001"
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-badge_number">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Full Name -->
          <div>
            <label for="full_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Full Name <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-user text-base"></i>
              </span>
              <input type="text" id="full_name" name="full_name"
                     placeholder="Full name"
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-full_name">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Email <span class="text-gray-400 text-xs">(optional)</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-mail text-base"></i>
              </span>
              <input type="email" id="email" name="email"
                     placeholder="officer@example.com"
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-email">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Phone -->
          <div>
            <label for="phone" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Phone Number <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-phone text-base"></i>
              </span>
              <input type="tel" id="phone" name="phone"
                     placeholder="01XXXXXXXXX" maxlength="11"
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-phone">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Station Code -->
          <div>
            <label for="station_code" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Station Code <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-building text-base"></i>
              </span>
              <input type="text" id="station_code" name="station_code"
                     placeholder="e.g. HQ01"
                     class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-station_code">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

          <!-- Hidden role field -->
          <input type="hidden" name="role" value="FIR Officer">

          <!-- Password -->
          <div>
            <label for="password" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Password <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-lock text-base"></i>
              </span>
              <input type="password" id="password" name="password"
                     placeholder="Min 8 characters"
                     class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
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

          <!-- Confirm Password -->
          <div>
            <label for="password_confirm" class="block text-xs font-semibold text-gray-600 mb-1.5">
              Confirm Password <span class="text-red-400">*</span>
            </label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <i class="ti ti-lock-check text-base"></i>
              </span>
              <input type="password" id="password_confirm" name="password_confirm"
                     placeholder="Repeat password"
                     class="w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200 text-sm
                            focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                            transition placeholder-gray-300">
              <button type="button" onclick="togglePwd('password_confirm')"
                      class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                <i class="ti ti-eye text-base" id="eye-password_confirm"></i>
              </button>
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-password_confirm">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </div>

        </div>

        <!-- Submit -->
        <div class="mt-7 flex items-center gap-3">
          <button type="submit" id="submit-btn"
                  class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                         text-sm font-semibold flex items-center gap-2 transition">
            <i class="ti ti-user-plus text-base"></i> Create Officer Account
          </button>
        </div>
      </form>

      <!-- Card footer -->
      <div class="bg-[#f8f9fc] border-t border-gray-100 px-8 py-4 text-center text-sm text-gray-500">
        Already have an officer account?
        <a href="officer-login.php" class="text-accent font-semibold hover:underline ml-1">Sign In</a>
      </div>

      <!-- Success state -->
      <div id="success-panel" class="hidden text-center py-8">
        <div class="w-20 h-20 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-5">
          <i class="ti ti-circle-check text-accent text-5xl"></i>
        </div>
        <h2 class="text-navy text-2xl font-bold mb-2">Registration Successful!</h2>
        <p class="text-gray-500 mb-6 text-sm max-w-sm mx-auto">
          Your officer account has been created. You can now log in and start filing FIRs.
        </p>
        <a href="officer-login.php"
           class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                  text-sm font-semibold inline-flex items-center gap-2 transition">
          <i class="ti ti-login text-base"></i> Go to Login
        </a>
      </div>

    </div>
  </div>
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

<script>
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

function clearErrors() {
  document.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
  document.querySelectorAll('input, select').forEach(el => {
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

function validateForm() {
  clearErrors();
  let ok = true;

  const badge = document.getElementById('badge_number').value.trim();
  const name = document.getElementById('full_name').value.trim();
  const email = document.getElementById('email').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const station = document.getElementById('station_code').value.trim();
  const password = document.getElementById('password').value;
  const confirm = document.getElementById('password_confirm').value;

  if (!badge) {
    showError('badge_number', 'Badge number is required.');
    ok = false;
  } else if (badge.length < 3) {
    showError('badge_number', 'Badge number must be at least 3 characters.');
    ok = false;
  }

  if (!name) {
    showError('full_name', 'Full name is required.');
    ok = false;
  } else if (name.length < 3) {
    showError('full_name', 'Name must be at least 3 characters.');
    ok = false;
  }

  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    showError('email', 'Enter a valid email address.');
    ok = false;
  }

  if (!phone) {
    showError('phone', 'Phone number is required.');
    ok = false;
  } else if (!/^01[3-9]\d{8}$/.test(phone)) {
    showError('phone', 'Enter a valid Bangladesh phone number (01XXXXXXXXX).');
    ok = false;
  }

  if (!station) {
    showError('station_code', 'Station code is required.');
    ok = false;
  }

  if (!password) {
    showError('password', 'Password is required.');
    ok = false;
  } else if (password.length < 8) {
    showError('password', 'Password must be at least 8 characters.');
    ok = false;
  }

  if (password !== confirm) {
    showError('password_confirm', 'Passwords do not match.');
    ok = false;
  }

  return ok;
}

document.getElementById('register-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors();
  if (!validateForm()) return;

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Creating account…';

  const global = document.getElementById('alert-global');
  global.classList.add('hidden');

  try {
    const formData = new FormData(this);
    const resp = await fetch('officer-register_action.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success) {
      document.getElementById('register-form').classList.add('hidden');
      document.getElementById('success-panel').classList.remove('hidden');
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
    btn.innerHTML = '<i class="ti ti-user-plus text-base"></i> Create Officer Account';
  }
});
</script>
</body>
</html>
