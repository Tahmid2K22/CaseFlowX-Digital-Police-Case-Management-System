<?php
/**
 * emergency.php — CaseFlowX
 * Public-facing page to submit anonymous emergency reports and track them.
 * Absolute anonymity guaranteed. No session citizen_id is saved to database records.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['citizen_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Report Emergency Anonymously — CaseFlowX</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            navy:   '#1B2A4A',
            accent: '#E11D48', // Crimson/Rose red for emergency theme
            'accent-dark': '#BE123C',
            emerald: {
              500: '#10B981',
              600: '#059669',
            }
          }
        }
      }
    }
  </script>
  <style>
    .fade-in {
      animation: fadeIn 0.35s ease-out forwards;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    /* Pulse effect for dispatched status */
    .pulse-glow {
      box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.4);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(225, 29, 72, 0.7); }
      70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(225, 29, 72, 0); }
      100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(225, 29, 72, 0); }
    }
  </style>
</head>
<body class="bg-[#F4F6F9] min-h-screen font-sans text-sm">

  <!-- CRITICAL RED BANNER -->
  <div class="bg-red-600 text-white font-bold text-center px-4 py-2.5 text-xs sm:text-sm shadow-md flex items-center justify-center gap-2 select-none">
    <i class="ti ti-bell-ringing-2 animate-bounce text-base"></i>
    <span>IF YOU ARE IN IMMEDIATE DANGER, PLEASE CALL 999 OR 911 IMMEDIATELY.</span>
  </div>

  <div class="py-8 px-4">
    <div class="max-w-3xl mx-auto">

      <!-- Breadcrumb -->
      <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php" class="hover:text-accent transition-colors flex items-center gap-1">
            <i class="ti ti-home text-base"></i> Dashboard
          </a>
        <?php else: ?>
          <a href="login.php" class="hover:text-accent transition-colors flex items-center gap-1">
            <i class="ti ti-home text-base"></i> Home
          </a>
        <?php endif; ?>
        <i class="ti ti-chevron-right text-xs"></i>
        <span class="text-gray-700 font-medium">Report Emergency</span>
      </div>

      <!-- Tab Buttons -->
      <div class="flex items-center gap-2 mb-4 bg-gray-200/60 p-1.5 rounded-2xl w-fit">
        <button onclick="switchTab('report')" id="tab-btn-report"
                class="px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 bg-white text-navy shadow-sm">
          <i class="ti ti-alert-triangle mr-1"></i> Report Emergency
        </button>
        <button onclick="switchTab('track')" id="tab-btn-track"
                class="px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 text-gray-600 hover:text-navy">
          <i class="ti ti-search mr-1"></i> Track Report Status
        </button>
      </div>

      <!-- MAIN EMERGENCY CONTAINER -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        
        <!-- ==================== TAB: REPORT ==================== -->
        <div id="tab-report" class="fade-in">
          
          <!-- Header -->
          <div class="bg-navy px-8 py-6 flex items-center gap-4 border-b border-rose-500/20">
            <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow-md">
              <i class="ti ti-shield-half text-white"></i>
            </div>
            <div>
              <h1 class="text-white text-xl font-bold leading-tight">File Anonymous Report</h1>
              <p class="text-white/55 text-xs mt-0.5">Absolute anonymity guaranteed. Your IP and account details are not stored.</p>
            </div>
          </div>

          <!-- Report Form -->
          <div class="px-8 py-8">
            <div id="alert-report" class="hidden mb-6 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700"></div>

            <form id="emergency-form" novalidate>
              
              <!-- Emergency Type -->
              <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-600 mb-2">
                  Emergency Type <span class="text-red-400">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3" id="type-selector">
                  
                  <label class="cursor-pointer">
                    <input type="radio" name="type" value="medical" class="sr-only peer" checked>
                    <div class="p-3 border border-gray-200 rounded-xl text-center hover:bg-gray-50 peer-checked:border-accent peer-checked:bg-rose-50/30 peer-checked:text-accent transition flex flex-col items-center gap-1.5">
                      <i class="ti ti-ambulance text-xl"></i>
                      <span class="text-[10px] font-bold">Medical</span>
                    </div>
                  </label>

                  <label class="cursor-pointer">
                    <input type="radio" name="type" value="fire" class="sr-only peer">
                    <div class="p-3 border border-gray-200 rounded-xl text-center hover:bg-gray-50 peer-checked:border-accent peer-checked:bg-rose-50/30 peer-checked:text-accent transition flex flex-col items-center gap-1.5">
                      <i class="ti ti-flame text-xl"></i>
                      <span class="text-[10px] font-bold">Fire</span>
                    </div>
                  </label>

                  <label class="cursor-pointer">
                    <input type="radio" name="type" value="crime" class="sr-only peer">
                    <div class="p-3 border border-gray-200 rounded-xl text-center hover:bg-gray-50 peer-checked:border-accent peer-checked:bg-rose-50/30 peer-checked:text-accent transition flex flex-col items-center gap-1.5">
                      <i class="ti ti-gavel text-xl"></i>
                      <span class="text-[10px] font-bold">Crime</span>
                    </div>
                  </label>

                  <label class="cursor-pointer">
                    <input type="radio" name="type" value="accident" class="sr-only peer">
                    <div class="p-3 border border-gray-200 rounded-xl text-center hover:bg-gray-50 peer-checked:border-accent peer-checked:bg-rose-50/30 peer-checked:text-accent transition flex flex-col items-center gap-1.5">
                      <i class="ti ti-car-crash text-xl"></i>
                      <span class="text-[10px] font-bold">Accident</span>
                    </div>
                  </label>

                  <label class="cursor-pointer col-span-2 sm:col-span-1">
                    <input type="radio" name="type" value="other" class="sr-only peer">
                    <div class="p-3 border border-gray-200 rounded-xl text-center hover:bg-gray-50 peer-checked:border-accent peer-checked:bg-rose-50/30 peer-checked:text-accent transition flex flex-col items-center gap-1.5 justify-center h-full">
                      <i class="ti ti-dots-circle text-xl"></i>
                      <span class="text-[10px] font-bold">Other</span>
                    </div>
                  </label>

                </div>
              </div>

              <!-- Location -->
              <div class="mb-5">
                <label for="location" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Emergency Location <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-map-pin text-base"></i>
                  </span>
                  <input type="text" id="location" name="location"
                         placeholder="e.g. Sector 10, Road 4, House 12, Mirpur, Dhaka"
                         required
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-location">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Description -->
              <div class="mb-5">
                <label for="description" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Situation Details <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute top-3 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-align-left text-base"></i>
                  </span>
                  <textarea id="description" name="description" rows="4"
                            placeholder="Describe what is happening. Are there injuries? Is there immediate danger? How many people are involved?"
                            required
                            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200
                                   text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                   transition placeholder-gray-300 resize-y"></textarea>
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-description">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Reporter Contact (Optional) -->
              <div class="mb-6 bg-gray-50 rounded-xl p-4 border border-gray-100">
                <label for="contact_info" class="block text-xs font-semibold text-gray-700 mb-1.5">
                  Contact Info <span class="text-gray-400 font-normal">(Optional)</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-phone text-base"></i>
                  </span>
                  <input type="text" id="contact_info" name="contact_info"
                         placeholder="Leave phone/email if you wish responders to contact you. Otherwise leave blank."
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="text-[10px] text-gray-400 mt-1">If blank, you will remain 100% anonymous. We do not track accounts or logins.</p>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-contact_info">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Submit Button -->
              <button type="submit" id="submit-btn"
                      class="w-full bg-accent hover:bg-accent-dark text-white px-6 py-3 rounded-xl
                             text-sm font-semibold flex items-center justify-center gap-2 transition shadow-md">
                <i class="ti ti-alert-triangle text-base"></i> Submit Anonymous Report
              </button>

            </form>
          </div>
        </div>


        <!-- ==================== TAB: TRACK ==================== -->
        <div id="tab-track" class="hidden fade-in">
          <!-- Header -->
          <div class="bg-navy px-8 py-6 flex items-center gap-4 border-b border-rose-500/20">
            <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow-md">
              <i class="ti ti-radar text-white"></i>
            </div>
            <div>
              <h1 class="text-white text-xl font-bold leading-tight">Track Emergency Status</h1>
              <p class="text-white/55 text-xs mt-0.5">Check response details anonymously by entering the report reference code.</p>
            </div>
          </div>

          <div class="px-8 py-8">
            <!-- Search Bar -->
            <form id="track-form" class="mb-8" novalidate>
              <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-hash text-base"></i>
                  </span>
                  <input type="text" id="track_ref" name="track_ref"
                         placeholder="e.g. EMG-2026-0001"
                         class="w-full pl-9 pr-4 py-3 rounded-xl border border-gray-200
                                text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300 font-semibold uppercase">
                </div>
                <button type="submit" id="track-btn"
                        class="bg-navy hover:bg-navy/95 text-white px-6 py-3 rounded-xl
                               text-sm font-semibold flex items-center justify-center gap-2 transition shadow-sm">
                  <i class="ti ti-search text-base"></i> Track Report
                </button>
              </div>
              <p class="err-msg hidden text-xs text-red-500 mt-2 flex items-center gap-1" id="err-track_ref">
                <i class="ti ti-alert-circle text-sm"></i> <span></span>
              </p>
            </form>

            <div id="alert-track" class="hidden flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700 mb-6"></div>

            <!-- Tracking Result Panel -->
            <div id="track-result" class="hidden fade-in bg-gray-50 border border-gray-100 rounded-2xl p-6">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200/60 pb-5 mb-5">
                <div>
                  <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wide">Report Reference</h3>
                  <p id="res-ref" class="text-lg font-bold text-navy mt-0.5"></p>
                </div>
                <div id="res-badge-container">
                  <!-- Filled by JS -->
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div>
                  <h4 class="text-xs font-semibold text-gray-500">Emergency Type</h4>
                  <p id="res-type" class="text-sm font-bold text-navy mt-0.5 uppercase"></p>
                </div>
                <div>
                  <h4 class="text-xs font-semibold text-gray-500">Filing Date</h4>
                  <p id="res-date" class="text-sm font-semibold text-navy mt-0.5"></p>
                </div>
              </div>

              <div class="mb-5">
                <h4 class="text-xs font-semibold text-gray-500">Reported Location</h4>
                <p id="res-location" class="text-sm text-navy mt-1 bg-white p-3 rounded-xl border border-gray-200/60 font-semibold"></p>
              </div>

              <!-- Dispatch Status Progress Bar -->
              <div>
                <h4 class="text-xs font-semibold text-gray-500 mb-3">Response Status</h4>
                <div class="relative flex items-center justify-between px-3 mt-4">
                  <div class="absolute left-6 right-6 top-1/2 -translate-y-1/2 h-1 bg-gray-200 -z-10" id="prog-line">
                    <div class="h-full bg-red-500 w-0 transition-all duration-300" id="prog-fill"></div>
                  </div>
                  <div class="flex flex-col items-center" id="node-received">
                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-xs border border-white">
                      <i class="ti ti-circle-dot"></i>
                    </div>
                    <span class="text-[10px] font-bold mt-1.5 text-gray-400">Received</span>
                  </div>
                  <div class="flex flex-col items-center" id="node-dispatched">
                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-xs border border-white">
                      <i class="ti ti-loader"></i>
                    </div>
                    <span class="text-[10px] font-bold mt-1.5 text-gray-400">Dispatched</span>
                  </div>
                  <div class="flex flex-col items-center" id="node-resolved">
                    <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-xs border border-white">
                      <i class="ti ti-circle-check"></i>
                    </div>
                    <span class="text-[10px] font-bold mt-1.5 text-gray-400">Resolved</span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ==================== ACKNOWLEDGMENT STATE ==================== -->
        <div id="acknowledgment-panel" class="hidden px-8 py-10 text-center fade-in">
          <div class="w-20 h-20 rounded-full bg-rose-50 text-accent flex items-center justify-center mx-auto mb-6 pulse-glow">
            <i class="ti ti-shield-alert text-4xl"></i>
          </div>
          
          <h2 class="text-navy text-2xl font-bold mb-2">Emergency Dispatch Initiated</h2>
          <p class="text-gray-500 mb-6 text-sm max-w-md mx-auto">
            Your report was submitted anonymously. Crews are being alerted. Write down the reference code below to check status updates:
          </p>

          <!-- Code Display -->
          <div class="bg-navy rounded-2xl p-5 mb-6 max-w-sm mx-auto flex items-center justify-between border border-gray-700 shadow-inner">
            <div class="text-left">
              <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block">Reference Code</span>
              <span id="ack-ref-code" class="text-white text-xl font-mono font-bold">EMG-2026-0000</span>
            </div>
            <button onclick="copyAckCode()" class="bg-white/10 hover:bg-white/20 text-white rounded-xl p-2.5 transition flex items-center justify-center" title="Copy Reference">
              <i class="ti ti-copy text-lg" id="copy-ico"></i>
            </button>
          </div>

          <!-- Alert notification system log (SCRUM-147) -->
          <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-emerald-800 text-xs text-left max-w-md mx-auto mb-8 flex items-start gap-2.5">
            <i class="ti ti-message-share text-lg text-emerald-500 flex-shrink-0 mt-0.5"></i>
            <div>
              <span class="font-bold block mb-0.5">Simulated Dispatch Notification Triggered</span>
              An urgent dispatch SMS has been sent directly to: <strong class="font-bold" id="ack-dept">General Responders</strong>.
            </div>
          </div>

          <!-- Guidelines -->
          <div class="border-t border-gray-100 pt-6 max-w-md mx-auto">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider text-left mb-3">CRITICAL SAFETY INSTRUCTIONS</h3>
            <ul class="text-left text-xs text-gray-600 space-y-2">
              <li class="flex items-start gap-2"><i class="ti ti-circle-check text-rose-500 flex-shrink-0"></i> Move away from the danger zone to a secure location immediately.</li>
              <li class="flex items-start gap-2"><i class="ti ti-circle-check text-rose-500 flex-shrink-0"></i> If medical assistance is needed, clear the entry pathways for responders.</li>
              <li class="flex items-start gap-2"><i class="ti ti-circle-check text-rose-500 flex-shrink-0"></i> Do not attempt to intervene or confront suspects in active crimes.</li>
            </ul>
          </div>

          <div class="mt-8 flex justify-center gap-3">
            <button onclick="resetEmergencyForm()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-xl text-xs font-bold transition">
              File Another Report
            </button>
            <button onclick="trackReportCodeFromAck()" class="bg-navy hover:bg-navy/95 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition">
              Track This Report
            </button>
          </div>

        </div>

      </div><!-- /card -->

      <!-- Back links -->
      <div class="flex items-center justify-between px-2">
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
            <i class="ti ti-arrow-left text-xs"></i> Back to Dashboard
          </a>
        <?php else: ?>
          <a href="login.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
            <i class="ti ti-arrow-left text-xs"></i> Back to Login
          </a>
          <a href="register.php" class="text-rose-600 font-semibold hover:underline text-sm">Create an account</a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <script>
    // Tab controller
    function switchTab(tab) {
      document.querySelectorAll('.err-msg').forEach(el => el.classList.add('hidden'));
      document.getElementById('alert-report').classList.add('hidden');
      document.getElementById('alert-track').classList.add('hidden');
      
      const tabReport = document.getElementById('tab-report');
      const tabTrack = document.getElementById('tab-track');
      const ackPanel = document.getElementById('acknowledgment-panel');
      const btnReport = document.getElementById('tab-btn-report');
      const btnTrack = document.getElementById('tab-btn-track');

      ackPanel.classList.add('hidden');

      if (tab === 'report') {
        tabReport.classList.remove('hidden');
        tabTrack.classList.add('hidden');
        
        btnReport.className = "px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 bg-white text-navy shadow-sm";
        btnTrack.className = "px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 text-gray-600 hover:text-navy";
      } else {
        tabReport.classList.add('hidden');
        tabTrack.classList.remove('hidden');

        btnReport.className = "px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 text-gray-600 hover:text-navy";
        btnTrack.className = "px-5 py-2 rounded-xl text-xs font-bold transition-all duration-200 bg-white text-navy shadow-sm";
      }
    }

    // Copy ref code
    function copyAckCode() {
      const code = document.getElementById('ack-ref-code').textContent;
      navigator.clipboard.writeText(code).then(() => {
        const ico = document.getElementById('copy-ico');
        ico.className = "ti ti-check text-emerald-400";
        setTimeout(() => {
          ico.className = "ti ti-copy text-lg";
        }, 1500);
      });
    }

    function resetEmergencyForm() {
      document.getElementById('emergency-form').reset();
      switchTab('report');
    }

    function trackReportCodeFromAck() {
      const code = document.getElementById('ack-ref-code').textContent;
      switchTab('track');
      document.getElementById('track_ref').value = code;
      document.getElementById('track-form').dispatchEvent(new Event('submit'));
    }

    // Submit emergency report
    const form = document.getElementById('emergency-form');
    form.addEventListener('submit', async function(e) {
      e.preventDefault();

      // Clear errors
      document.querySelectorAll('.err-msg').forEach(el => el.classList.add('hidden'));
      document.getElementById('alert-report').classList.add('hidden');

      let ok = true;
      const type = document.querySelector('input[name="type"]:checked').value;
      const location = document.getElementById('location').value.trim();
      const description = document.getElementById('description').value.trim();
      const contactInfo = document.getElementById('contact_info').value.trim();

      if (location.length < 5) {
        showError('location', 'Please provide a more specific location (at least 5 characters).');
        ok = false;
      }
      if (description.length < 10) {
        showError('description', 'Please provide more details about the situation (at least 10 characters).');
        ok = false;
      }

      if (!ok) return;

      const btn = document.getElementById('submit-btn');
      btn.disabled = true;
      btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Dispatching Rescue Crews…';

      try {
        const formData = new FormData();
        formData.append('type', type);
        formData.append('location', location);
        formData.append('description', description);
        formData.append('contact_info', contactInfo);

        const resp = await fetch('emergency_action.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.success) {
          document.getElementById('tab-report').classList.add('hidden');
          document.getElementById('ack-ref-code').textContent = data.report_number;
          document.getElementById('ack-dept').textContent = data.department || 'Emergency Dispatch Unit';
          document.getElementById('acknowledgment-panel').classList.remove('hidden');
        } else {
          if (data.errors) {
            Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
          } else {
            showGlobalAlert('alert-report', data.message);
          }
        }
      } catch (err) {
        showGlobalAlert('alert-report', 'Failed to reach emergency servers. If you are in urgent danger, please call 999 immediately.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-alert-triangle text-base"></i> Submit Anonymous Report';
      }
    });

    // Track emergency status lookup
    const trackForm = document.getElementById('track-form');
    trackForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      document.getElementById('err-track_ref').classList.add('hidden');
      document.getElementById('alert-track').classList.add('hidden');
      document.getElementById('track-result').classList.add('hidden');

      const ref = document.getElementById('track_ref').value.trim();
      if (!ref) {
        showError('track_ref', 'Reference number is required.');
        return;
      }
      if (!/^EMG-\d{4}-\d{4}$/i.test(ref)) {
        showError('track_ref', 'Invalid format. Must be like EMG-2026-0001.');
        return;
      }

      const btn = document.getElementById('track-btn');
      btn.disabled = true;
      btn.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Fetching…';

      try {
        const resp = await fetch(`emergency_action.php?ref=${ref}`);
        const data = await resp.json();

        if (data.success) {
          const r = data.report;
          document.getElementById('res-ref').textContent = r.report_number;
          document.getElementById('res-type').textContent = r.type;
          document.getElementById('res-location').textContent = r.location;

          // Date formatting
          const dateObj = new Date(r.created_at.replace(/-/g, "/"));
          const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
          document.getElementById('res-date').textContent = isNaN(dateObj) ? r.created_at : dateObj.toLocaleDateString('en-US', options);

          // Render badges and progress bar
          const badgeContainer = document.getElementById('res-badge-container');
          const pFill = document.getElementById('prog-fill');
          
          // Reset nodes
          const nodes = ['received', 'dispatched', 'resolved'];
          nodes.forEach(n => {
            const circle = document.getElementById(`node-${n}`).querySelector('.w-8');
            circle.className = 'w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-xs border border-white';
          });

          let badgeHtml = '';
          let fillWidth = '0%';

          if (r.status === 'received') {
            badgeHtml = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-blue-50 border-blue-200 text-blue-700">
                          <i class="ti ti-circle-dot"></i> Report Received
                         </span>`;
            fillWidth = '0%';
            setNodeActive('received', 'bg-blue-600 text-white');
          } else if (r.status === 'dispatched') {
            badgeHtml = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-rose-50 border-rose-200 text-rose-700 animate-pulse">
                          <i class="ti ti-loader"></i> Responders Dispatched
                         </span>`;
            fillWidth = '50%';
            setNodeActive('received', 'bg-blue-600 text-white');
            setNodeActive('dispatched', 'bg-rose-600 text-white animate-pulse');
          } else if (r.status === 'resolved') {
            badgeHtml = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-emerald-50 border-emerald-200 text-emerald-700">
                          <i class="ti ti-circle-check"></i> Situation Resolved
                         </span>`;
            fillWidth = '100%';
            setNodeActive('received', 'bg-blue-600 text-white');
            setNodeActive('dispatched', 'bg-rose-600 text-white');
            setNodeActive('resolved', 'bg-emerald-600 text-white');
          } else if (r.status === 'spurious') {
            badgeHtml = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border bg-gray-100 border-gray-300 text-gray-600">
                          <i class="ti ti-x"></i> Dismissed / Spurious
                         </span>`;
            fillWidth = '0%';
          }

          badgeContainer.innerHTML = badgeHtml;
          pFill.style.width = fillWidth;

          document.getElementById('track-result').classList.remove('hidden');
        } else {
          showGlobalAlert('alert-track', data.message);
        }
      } catch (err) {
        showGlobalAlert('alert-track', 'Connection error. Could not query report status.');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-search text-base"></i> Track Report';
      }
    });

    function setNodeActive(nodeName, classStr) {
      const circle = document.getElementById(`node-${nodeName}`).querySelector('.w-8');
      circle.className = `w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs border border-white ${classStr}`;
    }

    function showError(field, msg) {
      const err = document.getElementById('err-' + field);
      if (err) {
        err.querySelector('span').textContent = msg;
        err.classList.remove('hidden');
      }
    }

    function showGlobalAlert(id, msg) {
      const alertEl = document.getElementById(id);
      alertEl.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${msg}</span>`;
      alertEl.classList.remove('hidden');
    }
  </script>
</body>
</html>
