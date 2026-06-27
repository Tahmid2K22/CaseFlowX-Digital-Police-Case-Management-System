<?php
/**
 * track-status.php — CaseFlowX
 * Public-facing page to track complaint status by reference number.
 * Supports URL pre-population via ?ref=CFXXX-YYYY.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['citizen_id']);
$prefilledRef = isset($_GET['ref']) ? htmlspecialchars(trim($_GET['ref'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Complaint Status — CaseFlowX</title>
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
  <style>
    /* Subtle fade-in animations for search results */
    .fade-in {
      animation: fadeIn 0.35s ease-out forwards;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="bg-[#F4F6F9] min-h-screen font-sans text-sm">

  <div class="py-10 px-4">
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
        <span class="text-gray-700 font-medium">Track Complaint Status</span>
      </div>

      <!-- Main Tracking Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        
        <!-- Header -->
        <div class="bg-navy px-8 py-6 flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
            <i class="ti ti-radar"></i>
          </div>
          <div>
            <h1 class="text-white text-xl font-bold leading-tight">Track Complaint</h1>
            <p class="text-white/55 text-sm mt-0.5">Enter your reference number to view progress in real-time</p>
          </div>
        </div>

        <!-- Search Input Form -->
        <div class="px-8 py-6 border-b border-gray-100 bg-[#f8f9fc]/50">
          <form id="track-form" novalidate>
            <div class="flex flex-col sm:flex-row gap-3">
              <div class="relative flex-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                  <i class="ti ti-hash text-base"></i>
                </span>
                <input type="text" id="case_number" name="case_number"
                       placeholder="e.g. CF001-2026"
                       value="<?= $prefilledRef ?>"
                       class="w-full pl-9 pr-4 py-3 rounded-xl border border-gray-200
                              text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                              transition placeholder-gray-300 font-medium uppercase">
              </div>
              <button type="submit" id="track-btn"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-3 rounded-xl
                             text-sm font-semibold flex items-center justify-center gap-2 transition shadow-sm whitespace-nowrap">
                <i class="ti ti-search text-base"></i> Track Status
              </button>
            </div>
            <p class="err-msg hidden text-xs text-red-500 mt-2 flex items-center gap-1 animate-pulse" id="err-case_number">
              <i class="ti ti-alert-circle text-sm"></i> <span></span>
            </p>
          </form>
        </div>

        <!-- Global Alert Display -->
        <div class="px-8 pt-6">
          <div id="alert-global" class="hidden flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700"></div>
        </div>

        <!-- Tracking Results Panel (dynamically populated) -->
        <div id="result-panel" class="hidden px-8 py-6 fade-in">
          
          <!-- Basic Info Header -->
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8 pb-6 border-b border-gray-100">
            <div>
              <h2 id="result-title" class="text-lg font-bold text-navy"></h2>
              <p class="text-sm text-gray-500 mt-1 flex items-center gap-1.5">
                <i class="ti ti-hash text-xs"></i>
                <span id="result-number" class="font-semibold text-gray-700"></span>
              </p>
            </div>
            <div class="flex items-center gap-2" id="result-badges">
              <!-- Filled by JS -->
            </div>
          </div>

          <!-- Progress Workflow Timeline -->
          <div class="mb-8">
            <h3 class="text-sm font-semibold text-gray-700 mb-6 flex items-center gap-2">
              <i class="ti ti-route text-accent"></i> Complaint Progress
            </h3>

            <!-- Desktop Timeline -->
            <div class="relative flex items-center justify-between mb-2 px-4 select-none">
              <!-- Connecting Line -->
              <div class="absolute left-8 right-8 top-1/2 -translate-y-1/2 h-1 bg-gray-200 -z-10" id="timeline-line">
                <div class="h-full bg-accent w-0 transition-all duration-500" id="timeline-progress-bar"></div>
              </div>

              <!-- Step 1: Filed -->
              <div class="flex flex-col items-center text-center group w-24" id="step-open">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-all duration-300 step-node border-2 border-white">
                  <i class="ti ti-file-plus text-lg"></i>
                </div>
                <span class="text-xs font-semibold mt-2 text-gray-500 step-label">Filed</span>
              </div>

              <!-- Step 2: In Progress -->
              <div class="flex flex-col items-center text-center group w-28" id="step-in_progress">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-all duration-300 step-node border-2 border-white">
                  <i class="ti ti-loader text-lg"></i>
                </div>
                <span class="text-xs font-semibold mt-2 text-gray-500 step-label">Investigating</span>
              </div>

              <!-- Step 3: Resolved -->
              <div class="flex flex-col items-center text-center group w-24" id="step-resolved">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-all duration-300 step-node border-2 border-white">
                  <i class="ti ti-circle-check text-lg"></i>
                </div>
                <span class="text-xs font-semibold mt-2 text-gray-500 step-label">Resolved</span>
              </div>

              <!-- Step 4: Closed -->
              <div class="flex flex-col items-center text-center group w-24" id="step-closed">
                <div class="w-10 h-10 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-all duration-300 step-node border-2 border-white">
                  <i class="ti ti-lock text-lg"></i>
                </div>
                <span class="text-xs font-semibold mt-2 text-gray-500 step-label">Closed</span>
              </div>
            </div>
            
            <div class="grid grid-cols-4 px-1 text-[10px] text-gray-400 text-center select-none">
              <div>Awaiting officer review</div>
              <div>Officer assigned &amp; active</div>
              <div>Issue fixed/resolved</div>
              <div>Case officially closed</div>
            </div>
          </div>

          <!-- Metadata & Description -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
              <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                <i class="ti ti-calendar-event text-xs"></i> Date Filed
              </p>
              <p id="result-date" class="text-sm font-semibold text-navy">-</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
              <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                <i class="ti ti-flag text-xs"></i> Priority Level
              </p>
              <p id="result-priority" class="text-sm font-semibold text-navy">-</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
              <p class="text-xs text-gray-500 mb-1 flex items-center gap-1">
                <i class="ti ti-status-change text-xs"></i> Current Status
              </p>
              <p id="result-status-txt" class="text-sm font-semibold text-navy">-</p>
            </div>
          </div>

          <!-- Case Description (Authorization Filtered) -->
          <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
              <i class="ti ti-align-left text-accent"></i> Case Details
            </h4>
            <div id="description-authorized" class="hidden bg-emerald-50/30 rounded-xl p-4 border border-emerald-100 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap"></div>
            <div id="description-unauthorized" class="hidden bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 flex items-start gap-3">
              <i class="ti ti-shield-lock text-lg flex-shrink-0 mt-0.5"></i>
              <div>
                <span class="font-semibold block mb-0.5">Detailed description is restricted</span>
                To protect citizen privacy, full case details are only visible to the registered owner. Please sign in to view the complete details of this case.
              </div>
            </div>
          </div>

          <!-- Quick Action Buttons -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-8 pt-6 border-t border-gray-100">
            <span class="text-xs text-gray-400">CaseFlowX Digital Verification Services</span>
            <div id="action-buttons">
              <!-- Populated by JS -->
            </div>
          </div>

        </div><!-- /result-panel -->

      </div><!-- /card -->

      <!-- Footer back actions -->
      <div class="flex items-center justify-between px-2">
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
            <i class="ti ti-arrow-left text-xs"></i> Back to Dashboard
          </a>
        <?php else: ?>
          <a href="login.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-navy text-sm font-medium transition">
            <i class="ti ti-arrow-left text-xs"></i> Back to Login
          </a>
          <a href="register.php" class="text-accent font-semibold hover:underline text-sm">Create an account</a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- JavaScript -->
  <script>
    const form = document.getElementById('track-form');
    const input = document.getElementById('case_number');
    const button = document.getElementById('track-btn');
    const globalAlert = document.getElementById('alert-global');
    const resultPanel = document.getElementById('result-panel');
    const errCaseNumber = document.getElementById('err-case_number');

    // Clear alert and errors
    function clearMessages() {
      globalAlert.classList.add('hidden');
      errCaseNumber.classList.add('hidden');
      input.classList.remove('border-red-400', 'ring-red-100', 'ring-2');
      input.classList.add('border-gray-200');
    }

    function showInputError(msg) {
      errCaseNumber.querySelector('span').textContent = msg;
      errCaseNumber.classList.remove('hidden');
      input.classList.remove('border-gray-200');
      input.classList.add('border-red-400', 'ring-red-100', 'ring-2');
    }

    function showGlobalError(msg) {
      globalAlert.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${msg}</span>`;
      globalAlert.classList.remove('hidden');
    }

    // Format badge helpers
    function getStatusBadgeHtml(status) {
      const colors = {
        'open': 'bg-blue-100 text-blue-700 border-blue-200',
        'in_progress': 'bg-yellow-100 text-yellow-700 border-yellow-200',
        'resolved': 'bg-green-100 text-green-700 border-green-200',
        'closed': 'bg-gray-100 text-gray-600 border-gray-200',
      };
      const icons = {
        'open': 'ti-circle-dot',
        'in_progress': 'ti-loader',
        'resolved': 'ti-check-circle',
        'closed': 'ti-x',
      };
      const labels = {
        'open': 'Open',
        'in_progress': 'In Progress',
        'resolved': 'Resolved',
        'closed': 'Closed',
      };
      const cls = colors[status] || colors['open'];
      const ico = icons[status] || icons['open'];
      const lbl = labels[status] || status;
      return `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border ${cls}">
                <i class="ti ${ico}"></i> ${lbl}
              </span>`;
    }

    function getPriorityBadgeHtml(priority) {
      const colors = {
        'low': 'bg-gray-100 text-gray-600 border-gray-200',
        'medium': 'bg-orange-50 text-orange-700 border-orange-200',
        'high': 'bg-red-50 text-red-700 border-red-200',
      };
      const cls = colors[priority] || colors['low'];
      return `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border ${cls}">
                ${priority.charAt(0).toUpperCase() + priority.slice(1)} Priority
              </span>`;
    }

    // Render timeline updates
    function updateTimeline(status) {
      const steps = ['open', 'in_progress', 'resolved', 'closed'];
      const currentIdx = steps.indexOf(status);

      // Reset all nodes
      steps.forEach((step) => {
        const node = document.getElementById(`step-${step}`);
        const circle = node.querySelector('.step-node');
        const label = node.querySelector('.step-label');

        circle.className = 'w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shadow-sm transition-all duration-300 step-node border-2 border-white bg-gray-200 text-gray-500';
        label.className = 'text-xs font-semibold mt-2 text-gray-500 step-label';
      });

      // Calculate progress width
      let progressWidth = '0%';
      if (currentIdx === 1) progressWidth = '33%';
      else if (currentIdx === 2) progressWidth = '66%';
      else if (currentIdx === 3) progressWidth = '100%';

      document.getElementById('timeline-progress-bar').style.width = progressWidth;

      // Color active steps
      for (let i = 0; i <= currentIdx; i++) {
        const step = steps[i];
        const node = document.getElementById(`step-${step}`);
        const circle = node.querySelector('.step-node');
        const label = node.querySelector('.step-label');

        // Color logic
        circle.classList.remove('bg-gray-200', 'text-gray-500');
        if (status === 'closed' && step === 'closed') {
          circle.classList.add('bg-gray-600', 'text-white');
          label.classList.add('text-gray-600');
        } else if (step === 'resolved') {
          circle.classList.add('bg-green-600', 'text-white');
          label.classList.add('text-green-600');
        } else if (step === 'in_progress') {
          circle.classList.add('bg-yellow-500', 'text-white');
          label.classList.add('text-yellow-600');
        } else {
          circle.classList.add('bg-accent', 'text-white');
          label.classList.add('text-accent');
        }
      }
    }

    // Submit handler
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      clearMessages();
      resultPanel.classList.add('hidden');

      const val = input.value.trim();
      if (!val) {
        showInputError('Reference number is required.');
        return;
      }

      if (!/^CF\d{3}-\d{4}$/i.test(val)) {
        showInputError('Please enter a valid reference format (e.g. CF001-2026).');
        return;
      }

      button.disabled = true;
      button.innerHTML = '<i class="ti ti-loader-2 animate-spin text-base"></i> Tracking…';

      try {
        const formData = new FormData();
        formData.append('case_number', val);

        const resp = await fetch('track_status_action.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.success) {
          const c = data.case;
          
          // Populate details
          document.getElementById('result-title').textContent = c.title;
          document.getElementById('result-number').textContent = c.case_number;
          
          // Badges
          const badgeContainer = document.getElementById('result-badges');
          badgeContainer.innerHTML = getStatusBadgeHtml(c.status) + getPriorityBadgeHtml(c.priority);

          // Timeline
          updateTimeline(c.status);

          // Date & Priority
          const dateObj = new Date(c.created_at.replace(/-/g, "/"));
          const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
          document.getElementById('result-date').textContent = isNaN(dateObj) ? c.created_at : dateObj.toLocaleDateString('en-US', options);
          document.getElementById('result-priority').textContent = c.priority.charAt(0).toUpperCase() + c.priority.slice(1);
          
          const statusLabels = { 'open': 'Open (Filed)', 'in_progress': 'Under Investigation', 'resolved': 'Resolved', 'closed': 'Closed' };
          document.getElementById('result-status-txt').textContent = statusLabels[c.status] || c.status;

          // Description & details authorization filter
          const authorizedDesc = document.getElementById('description-authorized');
          const unauthorizedDesc = document.getElementById('description-unauthorized');
          const actionBtnContainer = document.getElementById('action-buttons');

          if (c.is_owner) {
            authorizedDesc.textContent = c.description;
            authorizedDesc.classList.remove('hidden');
            unauthorizedDesc.classList.add('hidden');

            actionBtnContainer.innerHTML = `
              <a href="case-details.php?id=${c.id}" class="inline-flex items-center gap-2 bg-navy hover:bg-navy/90 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition shadow-sm">
                View Full Case Details <i class="ti ti-external-link text-xs"></i>
              </a>
            `;
          } else {
            authorizedDesc.classList.add('hidden');
            unauthorizedDesc.classList.remove('hidden');

            actionBtnContainer.innerHTML = `
              <a href="login.php" class="inline-flex items-center gap-2 bg-accent hover:bg-accent-dark text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition shadow-sm">
                Log In as Owner <i class="ti ti-login text-xs"></i>
              </a>
            `;
          }

          resultPanel.classList.remove('hidden');
        } else {
          if (data.errors && data.errors.case_number) {
            showInputError(data.errors.case_number);
          } else {
            showGlobalError(data.message);
          }
        }
      } catch (err) {
        showGlobalError('Network error. Failed to connect to server. Please try again.');
      } finally {
        button.disabled = false;
        button.innerHTML = '<i class="ti ti-search text-base"></i> Track Status';
      }
    });

    // Auto-track on page load if prefilled reference is present
    window.addEventListener('DOMContentLoaded', () => {
      if (input.value.trim() !== '') {
        form.dispatchEvent(new Event('submit'));
      }
    });
  </script>
</body>
</html>
