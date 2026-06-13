<?php
/**
 * register.php — SCRUM-66 / SCRUM-70 / SCRUM-69
 * Citizen Registration Page — rendered inside master.html layout.
 * Include master.html header/footer; this file provides only the content block.
 *
 * Usage: The master page includes this file's output in its content area,
 * OR this page can load standalone by including the master partials.
 */
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — Citizen Registration  (SCRUM-66, SCRUM-70, SCRUM-69)
     Tailwind CSS via CDN · Tabler Icons · PHP backend
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Tailwind + Tabler (master.html may already load these; safe to include again) -->
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
  <div class="max-w-3xl mx-auto mb-5 flex items-center gap-2 text-sm text-gray-500">
    <a href="home.php" class="hover:text-accent transition-colors flex items-center gap-1">
      <i class="ti ti-home text-base"></i> Home
    </a>
    <i class="ti ti-chevron-right text-xs"></i>
    <span class="text-gray-700 font-medium">Citizen Registration</span>
  </div>

  <!-- Card -->
  <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-md overflow-hidden">

    <!-- Card header -->
    <div class="bg-navy px-8 py-6 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-content-center flex items-center justify-center text-white text-2xl shadow">
        <i class="ti ti-user-plus"></i>
      </div>
      <div>
        <h1 class="text-white text-xl font-bold leading-tight">Citizen Registration</h1>
        <p class="text-white/55 text-sm mt-0.5">Create your account to file complaints &amp; track cases</p>
      </div>
    </div>

    <!-- Step indicator -->
    <div class="bg-[#f8f9fc] border-b border-gray-100 px-8 py-3 flex items-center gap-0">
      <?php
      $steps = [
        ['icon' => 'ti-user', 'label' => 'Personal Info'],
        ['icon' => 'ti-map-pin', 'label' => 'Location'],
        ['icon' => 'ti-lock', 'label' => 'Security'],
      ];
      foreach ($steps as $i => $s):
        $num = $i + 1;
      ?>
        <div class="step-item flex items-center gap-2 flex-1 <?= $i > 0 ? 'ml-2' : '' ?>"
             data-step="<?= $num ?>">
          <!-- connector -->
          <?php if ($i > 0): ?>
            <div class="step-line h-px flex-1 bg-gray-200 transition-colors duration-300" id="line-<?= $i ?>"></div>
          <?php endif; ?>
          <div class="flex items-center gap-2 whitespace-nowrap">
            <div class="step-circle w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                        bg-gray-200 text-gray-500 transition-all duration-300" id="circle-<?= $num ?>">
              <?= $num ?>
            </div>
            <span class="step-label text-xs font-medium text-gray-400 hidden sm:inline
                         transition-colors duration-300" id="label-<?= $num ?>">
              <?= $s['label'] ?>
            </span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Form ──────────────────────────────────────────────────────────── -->
    <div class="px-8 py-8">

      <!-- Global alert (shown on server/fetch errors) -->
      <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

      <form id="reg-form" novalidate>

        <!-- ═══ STEP 1 — Personal Info ═════════════════════════════════════ -->
        <div class="step-panel" id="panel-1">
          <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
            <i class="ti ti-user-circle text-accent text-lg"></i> Personal Information
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

            <!-- Full Name -->
            <div class="sm:col-span-2">
              <?php field_label('full_name', 'Full Name', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-user text-base"></i>
                </span>
                <input type="text" id="full_name" name="full_name"
                       placeholder="e.g. Mohammad Rahman"
                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300">
              </div>
              <?php field_error('full_name') ?>
            </div>

            <!-- National ID -->
            <div>
              <?php field_label('national_id', 'National ID (NID)', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-id-badge text-base"></i>
                </span>
                <input type="text" id="national_id" name="national_id"
                       placeholder="10 or 17-digit NID"
                       maxlength="17" inputmode="numeric"
                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300">
              </div>
              <?php field_error('national_id') ?>
            </div>

            <!-- Date of Birth -->
            <div>
              <?php field_label('date_of_birth', 'Date of Birth', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-calendar text-base"></i>
                </span>
                <input type="date" id="date_of_birth" name="date_of_birth"
                       max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition text-gray-600">
              </div>
              <?php field_error('date_of_birth') ?>
            </div>

            <!-- Gender -->
            <div>
              <?php field_label('gender', 'Gender', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-gender-bigender text-base"></i>
                </span>
                <select id="gender" name="gender"
                        class="field-input w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200
                               text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                               transition appearance-none bg-white text-gray-600">
                  <option value="">Select gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                  <i class="ti ti-chevron-down text-sm"></i>
                </span>
              </div>
              <?php field_error('gender') ?>
            </div>

            <!-- Phone -->
            <div>
              <?php field_label('phone', 'Mobile Phone', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-phone text-base"></i>
                </span>
                <input type="tel" id="phone" name="phone"
                       placeholder="01XXXXXXXXX"
                       maxlength="11" inputmode="tel"
                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300">
              </div>
              <?php field_error('phone') ?>
            </div>

            <!-- Email -->
            <div class="sm:col-span-2">
              <?php field_label('email', 'Email Address', false) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-mail text-base"></i>
                </span>
                <input type="email" id="email" name="email"
                       placeholder="Optional — e.g. you@example.com"
                       class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300">
              </div>
              <?php field_error('email') ?>
            </div>

          </div><!-- /grid -->

          <div class="mt-7 flex justify-end">
            <button type="button" onclick="goStep(2)"
                    class="btn-next bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                           text-sm font-semibold flex items-center gap-2 transition">
              Next <i class="ti ti-arrow-right"></i>
            </button>
          </div>
        </div><!-- /panel-1 -->


        <!-- ═══ STEP 2 — Location ══════════════════════════════════════════ -->
        <div class="step-panel hidden" id="panel-2">
          <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
            <i class="ti ti-map-pin text-accent text-lg"></i> Location Details
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

            <!-- Division -->
            <div>
              <?php field_label('division', 'Division', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-map text-base"></i>
                </span>
                <select id="division" name="division" onchange="populateDistricts(this.value)"
                        class="field-input w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200
                               text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                               transition appearance-none bg-white text-gray-600">
                  <option value="">Select division</option>
                  <option>Barisal</option><option>Chattogram</option><option>Dhaka</option>
                  <option>Khulna</option><option>Mymensingh</option><option>Rajshahi</option>
                  <option>Rangpur</option><option>Sylhet</option>
                </select>
                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                  <i class="ti ti-chevron-down text-sm"></i>
                </span>
              </div>
              <?php field_error('division') ?>
            </div>

            <!-- District -->
            <div>
              <?php field_label('district', 'District', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-building-community text-base"></i>
                </span>
                <select id="district" name="district"
                        class="field-input w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200
                               text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                               transition appearance-none bg-white text-gray-600">
                  <option value="">Select division first</option>
                </select>
                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                  <i class="ti ti-chevron-down text-sm"></i>
                </span>
              </div>
              <?php field_error('district') ?>
            </div>

            <!-- Address -->
            <div class="sm:col-span-2">
              <?php field_label('address', 'Full Address', true) ?>
              <div class="relative">
                <span class="field-icon absolute top-3 left-3 text-gray-400 pointer-events-none">
                  <i class="ti ti-home text-base"></i>
                </span>
                <textarea id="address" name="address" rows="3"
                          placeholder="House/flat number, road, area, thana…"
                          class="field-input w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                 transition placeholder-gray-300 resize-none"></textarea>
              </div>
              <?php field_error('address') ?>
            </div>

          </div>

          <div class="mt-7 flex justify-between">
            <button type="button" onclick="goStep(1)"
                    class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                           font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
              <i class="ti ti-arrow-left"></i> Back
            </button>
            <button type="button" onclick="goStep(3)"
                    class="btn-next bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl
                           text-sm font-semibold flex items-center gap-2 transition">
              Next <i class="ti ti-arrow-right"></i>
            </button>
          </div>
        </div><!-- /panel-2 -->


        <!-- ═══ STEP 3 — Security ══════════════════════════════════════════ -->
        <div class="step-panel hidden" id="panel-3">
          <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
            <i class="ti ti-shield-lock text-accent text-lg"></i> Set Your Password
          </h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

            <!-- Password -->
            <div>
              <?php field_label('password', 'Password', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-lock text-base"></i>
                </span>
                <input type="password" id="password" name="password"
                       placeholder="Min 8 chars, 1 uppercase, 1 number"
                       class="field-input w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300"
                       oninput="updateStrength(this.value)">
                <button type="button" onclick="togglePwd('password')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                  <i class="ti ti-eye text-base" id="eye-password"></i>
                </button>
              </div>
              <!-- Strength bar -->
              <div class="mt-2 flex gap-1 h-1.5" id="strength-bar">
                <div class="flex-1 rounded-full bg-gray-200 transition-colors duration-300" id="s1"></div>
                <div class="flex-1 rounded-full bg-gray-200 transition-colors duration-300" id="s2"></div>
                <div class="flex-1 rounded-full bg-gray-200 transition-colors duration-300" id="s3"></div>
                <div class="flex-1 rounded-full bg-gray-200 transition-colors duration-300" id="s4"></div>
              </div>
              <p class="text-xs text-gray-400 mt-1" id="strength-text"></p>
              <?php field_error('password') ?>
            </div>

            <!-- Confirm Password -->
            <div>
              <?php field_label('password_confirm', 'Confirm Password', true) ?>
              <div class="relative">
                <span class="field-icon absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-lock-check text-base"></i>
                </span>
                <input type="password" id="password_confirm" name="password_confirm"
                       placeholder="Repeat your password"
                       class="field-input w-full pl-9 pr-10 py-2.5 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300">
                <button type="button" onclick="togglePwd('password_confirm')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                  <i class="ti ti-eye text-base" id="eye-password_confirm"></i>
                </button>
              </div>
              <?php field_error('password_confirm') ?>
            </div>

            <!-- Terms -->
            <div class="sm:col-span-2">
              <label class="flex items-start gap-3 cursor-pointer group">
                <input type="checkbox" id="terms" name="terms"
                       class="mt-0.5 w-4 h-4 rounded accent-accent cursor-pointer flex-shrink-0">
                <span class="text-sm text-gray-600 leading-relaxed">
                  I agree to the
                  <a href="#" class="text-accent font-medium hover:underline">Terms of Use</a>
                  and
                  <a href="#" class="text-accent font-medium hover:underline">Privacy Policy</a>.
                  I confirm that the information provided is accurate.
                </span>
              </label>
              <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-terms">
                <i class="ti ti-alert-circle text-sm"></i> You must accept the terms to continue.
              </p>
            </div>

          </div>

          <div class="mt-7 flex justify-between items-center">
            <button type="button" onclick="goStep(2)"
                    class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                           font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
              <i class="ti ti-arrow-left"></i> Back
            </button>
            <button type="submit" id="submit-btn"
                    class="bg-accent hover:bg-accent-dark text-white px-7 py-2.5 rounded-xl text-sm
                           font-semibold flex items-center gap-2 transition min-w-[160px] justify-center">
              <i class="ti ti-user-check text-base"></i> Create Account
            </button>
          </div>
        </div><!-- /panel-3 -->

      </form>

      <!-- ── Success state ─────────────────────────────────────────────── -->
      <div id="success-panel" class="hidden text-center py-8">
        <div class="w-20 h-20 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-5">
          <i class="ti ti-circle-check text-accent text-5xl"></i>
        </div>
        <h2 class="text-navy text-2xl font-bold mb-2">Registration Complete!</h2>
        <p class="text-gray-500 mb-6 text-sm max-w-sm mx-auto">
          Your citizen account has been created. You can now log in and file complaints.
        </p>
        <a href="login.php"
           class="inline-flex items-center gap-2 bg-accent hover:bg-accent-dark text-white
                  px-7 py-3 rounded-xl font-semibold text-sm transition">
          <i class="ti ti-login"></i> Go to Login
        </a>
        <p class="mt-4 text-xs text-gray-400">
          Or <a href="home.php" class="text-accent hover:underline">return to home</a>
        </p>
      </div>

    </div><!-- /px-8 py-8 -->

    <!-- Card footer -->
    <div class="bg-[#f8f9fc] border-t border-gray-100 px-8 py-4 text-center text-sm text-gray-500">
      Already have an account?
      <a href="login.php" class="text-accent font-semibold hover:underline ml-1">Sign in here</a>
    </div>

  </div><!-- /card -->
</div>

<?php
/* ── PHP helper functions ──────────────────────────────────────────────────── */
function field_label(string $id, string $label, bool $required): void {
    $req = $required
        ? '<span class="text-red-400 ml-0.5">*</span>'
        : '<span class="text-gray-400 text-xs ml-1">(optional)</span>';
    echo "<label for=\"{$id}\" class=\"block text-xs font-semibold text-gray-600 mb-1.5\">{$label}{$req}</label>";
}

function field_error(string $field): void {
    echo "<p class=\"err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1\" id=\"err-{$field}\">"
       . "<i class=\"ti ti-alert-circle text-sm\"></i> <span></span></p>";
}
?>

<!-- ── Districts data + JS logic ─────────────────────────────────────────── -->
<script>
/* ── District data (Bangladesh) ─────────────────────────────────────────── */
const DISTRICTS = {
  "Barisal":    ["Barguna","Barishal","Bhola","Jhalokathi","Patuakhali","Pirojpur"],
  "Chattogram": ["Bandarban","Brahmanbaria","Chandpur","Chattogram","Cox's Bazar","Cumilla","Feni","Khagrachari","Lakshmipur","Noakhali","Rangamati"],
  "Dhaka":      ["Dhaka","Faridpur","Gazipur","Gopalganj","Kishoreganj","Madaripur","Manikganj","Munshiganj","Narayanganj","Narsingdi","Rajbari","Shariatpur","Tangail"],
  "Khulna":     ["Bagerhat","Chuadanga","Jessore","Jhenaidah","Khulna","Kushtia","Magura","Meherpur","Narail","Satkhira"],
  "Mymensingh": ["Jamalpur","Mymensingh","Netrokona","Sherpur"],
  "Rajshahi":   ["Bogura","Chapainawabganj","Joypurhat","Naogaon","Natore","Pabna","Rajshahi","Sirajganj"],
  "Rangpur":    ["Dinajpur","Gaibandha","Kurigram","Lalmonirhat","Nilphamari","Panchagarh","Rangpur","Thakurgaon"],
  "Sylhet":     ["Habiganj","Moulvibazar","Sunamganj","Sylhet"],
};

function populateDistricts(division) {
  const sel = document.getElementById('district');
  sel.innerHTML = '<option value="">Select district</option>';
  (DISTRICTS[division] || []).forEach(d => {
    const o = document.createElement('option');
    o.value = o.textContent = d;
    sel.appendChild(o);
  });
}

/* ── Step navigation ────────────────────────────────────────────────────── */
let currentStep = 1;
const STEP_FIELDS = {
  1: ['full_name','national_id','date_of_birth','gender','phone','email'],
  2: ['division','district','address'],
  3: ['password','password_confirm'],
};

function goStep(target) {
  if (target > currentStep && !validateStep(currentStep)) return;
  document.getElementById('panel-' + currentStep).classList.add('hidden');
  document.getElementById('panel-' + target).classList.remove('hidden');
  currentStep = target;
  updateStepUI();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateStepUI() {
  for (let i = 1; i <= 3; i++) {
    const circle = document.getElementById('circle-' + i);
    const label  = document.getElementById('label-'  + i);
    const line   = document.getElementById('line-'   + (i - 1));
    if (i < currentStep) {
      circle.className = circle.className.replace(/bg-\S+|text-\S+/g, '');
      circle.classList.add('bg-accent', 'text-white');
      circle.innerHTML = '<i class="ti ti-check text-xs"></i>';
      label && label.classList.replace('text-gray-400', 'text-accent');
      line  && line.classList.replace('bg-gray-200', 'bg-accent');
    } else if (i === currentStep) {
      circle.className = circle.className.replace(/bg-\S+|text-\S+/g, '');
      circle.classList.add('bg-navy', 'text-white');
      circle.textContent = i;
      label && label.classList.replace('text-gray-400', 'text-navy');
    } else {
      circle.className = circle.className.replace(/bg-\S+|text-\S+/g, '');
      circle.classList.add('bg-gray-200', 'text-gray-500');
      circle.textContent = i;
      label && (label.className = label.className.replace('text-navy','text-gray-400').replace('text-accent','text-gray-400'));
    }
  }
}
updateStepUI();

/* ── Client-side validation ─────────────────────────────────────────────── */
function clearErrors() {
  document.querySelectorAll('.err-msg').forEach(e => e.classList.add('hidden'));
  document.querySelectorAll('.field-input').forEach(f => {
    f.classList.remove('border-red-400','ring-red-200');
    f.classList.add('border-gray-200');
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

function showErrors(errors) {
  Object.entries(errors).forEach(([k, v]) => showError(k, v));
}

function validateStep(step) {
  clearErrors();
  let ok = true;
  const fields = STEP_FIELDS[step] || [];

  fields.forEach(name => {
    const el = document.getElementById(name);
    if (!el) return;
    const val = el.value.trim();

    if (name === 'full_name') {
      if (!val) { showError(name, 'Full name is required.'); ok = false; }
      else if (val.length < 3) { showError(name, 'Name must be at least 3 characters.'); ok = false; }
    }
    if (name === 'national_id') {
      if (!val) { showError(name, 'National ID is required.'); ok = false; }
      else if (!/^\d{10}$|^\d{17}$/.test(val)) { showError(name, 'Must be 10 or 17 digits.'); ok = false; }
    }
    if (name === 'date_of_birth') {
      if (!val) { showError(name, 'Date of birth is required.'); ok = false; }
      else {
        const age = Math.floor((Date.now() - new Date(val)) / 3.156e10);
        if (age < 18) { showError(name, 'You must be at least 18 years old.'); ok = false; }
      }
    }
    if (name === 'gender' && !val) { showError(name, 'Please select a gender.'); ok = false; }
    if (name === 'phone') {
      if (!val) { showError(name, 'Phone number is required.'); ok = false; }
      else if (!/^01[3-9]\d{8}$/.test(val)) { showError(name, 'Enter a valid Bangladeshi number (01XXXXXXXXX).'); ok = false; }
    }
    if (name === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      showError(name, 'Enter a valid email address.'); ok = false;
    }
    if (name === 'division' && !val) { showError(name, 'Please select a division.'); ok = false; }
    if (name === 'district' && !val) { showError(name, 'Please select a district.'); ok = false; }
    if (name === 'address' && val.length < 10) { showError(name, 'Please provide a more detailed address.'); ok = false; }
    if (name === 'password') {
      if (!val) { showError(name, 'Password is required.'); ok = false; }
      else if (val.length < 8) { showError(name, 'At least 8 characters required.'); ok = false; }
      else if (!/[A-Z]/.test(val)) { showError(name, 'Must contain at least one uppercase letter.'); ok = false; }
      else if (!/[0-9]/.test(val)) { showError(name, 'Must contain at least one number.'); ok = false; }
    }
    if (name === 'password_confirm') {
      const pw = document.getElementById('password')?.value || '';
      if (el.value !== pw) { showError(name, 'Passwords do not match.'); ok = false; }
    }
  });
  return ok;
}

/* ── Password strength ──────────────────────────────────────────────────── */
function updateStrength(pw) {
  let score = 0;
  if (pw.length >= 8)  score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const colors = ['', 'bg-red-400', 'bg-orange-400', 'bg-yellow-400', 'bg-accent'];
  const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  for (let i = 1; i <= 4; i++) {
    const bar = document.getElementById('s' + i);
    bar.className = 'flex-1 rounded-full transition-colors duration-300 ' + (i <= score ? colors[score] : 'bg-gray-200');
  }
  document.getElementById('strength-text').textContent = pw.length ? labels[score] : '';
}

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

/* ── Form submission ─────────────────────────────────────────────────────── */
document.getElementById('reg-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  clearErrors();

  // Terms check
  if (!document.getElementById('terms').checked) {
    document.getElementById('err-terms').classList.remove('hidden');
    return;
  }

  if (!validateStep(3)) return;

  const btn = document.getElementById('submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Creating account…';

  const global = document.getElementById('alert-global');
  global.classList.add('hidden');

  try {
    const formData = new FormData(this);
    const resp = await fetch('register_action.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success) {
      document.getElementById('reg-form').classList.add('hidden');
      document.getElementById('success-panel').classList.remove('hidden');
    } else {
      if (data.errors) {
        // Show field errors — navigate to the step that has the error
        const errFields = Object.keys(data.errors);
        let targetStep = 3;
        for (let s = 1; s <= 3; s++) {
          if ((STEP_FIELDS[s] || []).some(f => errFields.includes(f))) { targetStep = s; break; }
        }
        if (targetStep !== currentStep) {
          document.getElementById('panel-' + currentStep).classList.add('hidden');
          document.getElementById('panel-' + targetStep).classList.remove('hidden');
          currentStep = targetStep;
          updateStepUI();
        }
        showErrors(data.errors);
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
    btn.innerHTML = '<i class="ti ti-user-check text-base"></i> Create Account';
  }
});
</script>
