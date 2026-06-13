<?php
/**
 * profile.php — CaseFlowX
 * Edit profile for the logged-in citizen.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logged_in']) || empty($_SESSION['citizen_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$db = get_db();
$stmt = $db->prepare('SELECT * FROM citizens WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $_SESSION['citizen_id']]);
$citizen = $stmt->fetch();

if (!$citizen) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
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

<div class="min-h-[calc(100vh-200px)] bg-[#F4F6F9]">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
      <a href="dashboard.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Dashboard
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium">Edit Profile</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Profile Summary Card -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
          <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-2xl bg-accent text-white flex items-center justify-center text-2xl font-bold">
              <?= strtoupper(substr($citizen['full_name'], 0, 1)) ?>
            </div>
            <div>
              <p class="font-semibold text-navy"><?= htmlspecialchars($citizen['full_name']) ?></p>
              <p class="text-sm text-gray-500"><?= htmlspecialchars($citizen['phone']) ?></p>
            </div>
          </div>

          <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
              <i class="ti ti-id-badge text-gray-400"></i>
              <span class="text-gray-600">NID:</span>
              <span class="font-medium text-navy ml-auto"><?= htmlspecialchars($citizen['national_id']) ?></span>
            </div>
            <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
              <i class="ti ti-calendar text-gray-400"></i>
              <span class="text-gray-600">Date of Birth:</span>
              <span class="font-medium text-navy ml-auto"><?= htmlspecialchars($citizen['date_of_birth']) ?></span>
            </div>
            <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
              <i class="ti ti-gender-male text-gray-400"></i>
              <span class="text-gray-600">Gender:</span>
              <span class="font-medium text-navy ml-auto"><?= ucfirst(htmlspecialchars($citizen['gender'])) ?></span>
            </div>
            <div class="flex items-center gap-3">
              <i class="ti ti-calendar-event text-gray-400"></i>
              <span class="text-gray-600">Member since:</span>
              <span class="font-medium text-navy ml-auto"><?= date('M Y', strtotime($citizen['created_at'])) ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Forms -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Profile Info Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-navy px-8 py-4 flex items-center gap-3">
            <i class="ti ti-user-circle text-accent text-xl"></i>
            <h2 class="text-white font-semibold">Update Profile</h2>
          </div>

          <div class="px-8 py-6">
            <div id="profile-alert" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

            <form id="profile-form" novalidate>
              <!-- Email -->
              <div class="mb-5">
                <label for="email" class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-mail text-base"></i>
                  </span>
                  <input type="email" id="email" name="email" value="<?= htmlspecialchars($citizen['email'] ?? '') ?>"
                         placeholder="your@email.com"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-email">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Division -->
              <div class="mb-5">
                <label for="division" class="block text-xs font-semibold text-gray-600 mb-1.5">Division <span class="text-red-400">*</span></label>
                <input type="text" id="division" name="division" value="<?= htmlspecialchars($citizen['division']) ?>" required
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition">
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-division">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- District -->
              <div class="mb-5">
                <label for="district" class="block text-xs font-semibold text-gray-600 mb-1.5">District <span class="text-red-400">*</span></label>
                <input type="text" id="district" name="district" value="<?= htmlspecialchars($citizen['district']) ?>" required
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition">
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-district">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Address -->
              <div class="mb-5">
                <label for="address" class="block text-xs font-semibold text-gray-600 mb-1.5">Address <span class="text-red-400">*</span></label>
                <textarea id="address" name="address" rows="3" required
                          class="w-full px-4 py-2.5 rounded-xl border border-gray-200
                                 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                 transition resize-y"><?= htmlspecialchars($citizen['address']) ?></textarea>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-address">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <button type="submit" id="profile-btn"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                             text-sm font-semibold flex items-center gap-2 transition">
                <i class="ti ti-device-floppy text-base"></i> Save Changes
              </button>
            </form>
          </div>
        </div>

        <!-- Password Change Form -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-navy px-8 py-4 flex items-center gap-3">
            <i class="ti ti-lock text-accent text-xl"></i>
            <h2 class="text-white font-semibold">Change Password</h2>
          </div>

          <div class="px-8 py-6">
            <div id="password-alert" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

            <form id="password-form" novalidate>
              <!-- Current Password -->
              <div class="mb-5">
                <label for="current_password" class="block text-xs font-semibold text-gray-600 mb-1.5">Current Password <span class="text-red-400">*</span></label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-lock text-base"></i>
                  </span>
                  <input type="password" id="current_password" name="current_password" required
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-current_password">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- New Password -->
              <div class="mb-5">
                <label for="new_password" class="block text-xs font-semibold text-gray-600 mb-1.5">New Password <span class="text-red-400">*</span></label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-key text-base"></i>
                  </span>
                  <input type="password" id="new_password" name="new_password" required
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-new_password">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Confirm New Password -->
              <div class="mb-5">
                <label for="confirm_password" class="block text-xs font-semibold text-gray-600 mb-1.5">Confirm New Password <span class="text-red-400">*</span></label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-key text-base"></i>
                  </span>
                  <input type="password" id="confirm_password" name="confirm_password" required
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-confirm_password">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <button type="submit" id="password-btn"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                             text-sm font-semibold flex items-center gap-2 transition">
                <i class="ti ti-lock-access text-base"></i> Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function clearErrors(formId) {
  const form = document.getElementById(formId);
  form.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
  form.querySelectorAll('input, textarea, select').forEach(el => {
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

function showAlert(elId, type, message) {
  const el = document.getElementById(elId);
  if (type === 'success') {
    el.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-green-50 border-green-200 text-green-700';
    el.innerHTML = '<i class="ti ti-circle-check text-lg flex-shrink-0 mt-0.5"></i><span>' + message + '</span>';
  } else {
    el.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
    el.innerHTML = '<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>' + message + '</span>';
  }
  el.classList.remove('hidden');
}

document.getElementById('profile-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors('profile-form');

  const btn = document.getElementById('profile-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Saving…';

  try {
    const resp = await fetch('update_profile.php', { method: 'POST', body: new FormData(this) });
    const data = await resp.json();

    if (data.success) {
      showAlert('profile-alert', 'success', data.message || 'Profile updated successfully.');
    } else {
      if (data.errors) {
        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
      }
      showAlert('profile-alert', 'error', data.message || 'Update failed.');
    }
  } catch (err) {
    showAlert('profile-alert', 'error', 'Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-device-floppy text-base"></i> Save Changes';
  }
});

document.getElementById('password-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors('password-form');

  const btn = document.getElementById('password-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Updating…';

  try {
    const resp = await fetch('update_profile.php', { method: 'POST', body: new FormData(this) });
    const data = await resp.json();

    if (data.success) {
      showAlert('password-alert', 'success', data.message || 'Password updated successfully.');
      document.getElementById('password-form').reset();
    } else {
      if (data.errors) {
        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
      }
      showAlert('password-alert', 'error', data.message || 'Update failed.');
    }
  } catch (err) {
    showAlert('password-alert', 'error', 'Network error. Please try again.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="ti ti-lock-access text-base"></i> Update Password';
  }
});
</script>
