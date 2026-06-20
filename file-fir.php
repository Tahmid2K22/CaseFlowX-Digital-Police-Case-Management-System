<?php
/**
 * file-fir.php — CaseFlowX FIR Filing Page for FIR Officers
 * Multi-step wizard: Complainant → Incident → Evidence → Review → Submit
 */

require_once __DIR__ . '/db.php';

// Redirect non-officers to login
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['officer_id'])) {
    header('Location: officer-login.php');
    exit;
}

$officer = require_officer();
?>
<!-- ═══════════════════════════════════════════════════════════════════════════
     CaseFlowX — FIR Filing
     Tailwind CSS via CDN · Tabler Icons · PHP backend
     ═══════════════════════════════════════════════════════════════════════════ -->

<!-- Tailwind + Tabler -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>File FIR — CaseFlowX</title>
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

  <!-- Logout -->
  <div class="max-w-3xl mx-auto mb-4 flex justify-end">
    <a href="logout.php" class="text-sm text-gray-500 hover:text-red-500 flex items-center gap-1 transition">
      <i class="ti ti-logout text-base"></i> Logout
    </a>
  </div>

  <div class="max-w-3xl mx-auto">
    <!-- Breadcrumb -->
    <div class="mb-5 flex items-center gap-2 text-sm text-gray-500">
      <a href="dashboard1.php" class="hover:text-accent transition-colors flex items-center gap-1">
        <i class="ti ti-home text-base"></i> Dashboard
      </a>
      <i class="ti ti-chevron-right text-xs"></i>
      <span class="text-gray-700 font-medium">File FIR</span>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

      <!-- Card header -->
      <div class="bg-navy px-8 py-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-accent flex items-center justify-center text-white text-2xl shadow">
          <i class="ti ti-file-infinity"></i>
        </div>
        <div>
          <h1 class="text-white text-xl font-bold leading-tight">File FIR</h1>
          <p class="text-white/55 text-sm mt-0.5">First Information Report — Digital Filing System</p>
        </div>
      </div>

      <!-- Step indicator -->
      <div class="bg-[#f8f9fc] border-b border-gray-100 px-8 py-4">
        <div class="flex items-center justify-between max-w-lg mx-auto">
          <?php
          $fir_steps = [
            ['icon' => 'ti-user', 'label' => 'Complainant'],
            ['icon' => 'ti-map-pin', 'label' => 'Incident'],
            ['icon' => 'ti-paperclip', 'label' => 'Evidence'],
            ['icon' => 'ti-clipboard-check', 'label' => 'Review'],
            ['icon' => 'ti-check', 'label' => 'Submit'],
          ];
          foreach ($fir_steps as $i => $s):
            $num = $i + 1;
          ?>
            <div class="fir-step flex flex-col items-center relative" data-step="<?= $num ?>">
              <?php if ($i > 0): ?>
                <div class="fir-line absolute top-4 -left-12 w-12 h-0.5 bg-gray-200 transition-colors duration-300"
                     id="fir-line-<?= $i ?>"></div>
              <?php endif; ?>
              <div class="fir-circle w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold
                          bg-gray-200 text-gray-500 transition-all duration-300 border-2 border-transparent"
                   id="fir-circle-<?= $num ?>">
                <i class="ti <?= $s['icon'] ?> text-base"></i>
              </div>
              <span class="fir-label text-[10px] font-medium text-gray-400 mt-1.5 transition-colors duration-300"
                    id="fir-label-<?= $num ?>">
                <?= $s['label'] ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Form content -->
      <div class="px-8 py-8">

        <!-- Global alert -->
        <div id="alert-global" class="hidden mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border"></div>

        <form id="fir-form" novalidate>
          <input type="hidden" name="officer_id" value="<?= (int)$officer['id'] ?>">
          <input type="hidden" name="station_code" value="<?= htmlspecialchars($officer['station_code']) ?>">
          <input type="hidden" name="action" id="form-action" value="create">

          <!-- ═══ STEP 1 — Complainant Details ════════════════════════════════ -->
          <div class="step-panel active" id="fir-panel-1">
            <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
              <i class="ti ti-user-circle text-accent text-lg"></i> Complainant Information
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

              <!-- Complainant Name -->
              <div class="sm:col-span-2">
                <label for="complainant_name" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Full Name <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-user text-base"></i>
                  </span>
                  <input type="text" id="complainant_name" name="complainant_name"
                         placeholder="Full name as per NID"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-complainant_name">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Complainant NID -->
              <div>
                <label for="complainant_nid" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  National ID (NID) <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-id-badge text-base"></i>
                  </span>
                  <input type="text" id="complainant_nid" name="complainant_nid"
                         placeholder="10 or 17-digit NID" maxlength="17" inputmode="numeric"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-complainant_nid">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Complainant Phone -->
              <div>
                <label for="complainant_phone" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Phone Number <span class="text-gray-400 text-xs">(optional)</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-phone text-base"></i>
                  </span>
                  <input type="tel" id="complainant_phone" name="complainant_phone"
                         placeholder="01XXXXXXXXX" maxlength="11" inputmode="tel"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-complainant_phone">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Complainant Address -->
              <div class="sm:col-span-2">
                <label for="complainant_address" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Full Address <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute top-3 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-home text-base"></i>
                  </span>
                  <textarea id="complainant_address" name="complainant_address" rows="3"
                            placeholder="Complete address as per NID"
                            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                   transition placeholder-gray-300 resize-none"></textarea>
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-complainant_address">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

            </div>

            <div class="mt-7 flex justify-end">
              <button type="button" onclick="goFirStep(2)"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 transition">
                Next <i class="ti ti-arrow-right"></i>
              </button>
            </div>
          </div><!-- /fir-panel-1 -->


          <!-- ═══ STEP 2 — Incident Details ══════════════════════════════════ -->
          <div class="step-panel" id="fir-panel-2">
            <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
              <i class="ti ti-map-pin text-accent text-lg"></i> Incident Details
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">

              <!-- Incident Date -->
              <div>
                <label for="incident_date" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Incident Date <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-calendar text-base"></i>
                  </span>
                  <input type="date" id="incident_date" name="incident_date"
                         max="<?= date('Y-m-d') ?>"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition text-gray-600">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-incident_date">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Incident Time -->
              <div>
                <label for="incident_time" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Incident Time <span class="text-gray-400 text-xs">(optional)</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-clock text-base"></i>
                  </span>
                  <input type="time" id="incident_time" name="incident_time"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition text-gray-600">
                </div>
              </div>

              <!-- Incident Location -->
              <div class="sm:col-span-2">
                <label for="incident_location" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Incident Location <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-map-triforce text-base"></i>
                  </span>
                  <input type="text" id="incident_location" name="incident_location"
                         placeholder="Specific location where incident occurred"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-incident_location">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Incident Description -->
              <div class="sm:col-span-2">
                <label for="incident_description" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Incident Description <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute top-3 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-align-left text-base"></i>
                  </span>
                  <textarea id="incident_description" name="incident_description" rows="5"
                            placeholder="Detailed description of the incident (minimum 20 characters)..."
                            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                   transition placeholder-gray-300 resize-y"></textarea>
                </div>
                <p class="err-msg hidden text-xs text-red-500 mt-1 flex items-center gap-1" id="err-incident_description">
                  <i class="ti ti-alert-circle text-sm"></i> <span></span>
                </p>
              </div>

              <!-- Sections Applied -->
              <div>
                <label for="sections_applied" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Sections Applied <span class="text-gray-400 text-xs">(optional)</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-scale text-base"></i>
                  </span>
                  <input type="text" id="sections_applied" name="sections_applied"
                         placeholder="e.g. Section 302, 304"
                         class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                transition placeholder-gray-300">
                </div>
              </div>

              <!-- Priority -->
              <div>
                <label for="priority" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Priority <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-flag text-base"></i>
                  </span>
                  <select id="priority" name="priority"
                          class="w-full pl-9 pr-8 py-2.5 rounded-xl border border-gray-200 text-sm
                                 focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                 transition appearance-none bg-white text-gray-600">
                    <option value="low">Low — Minor matter</option>
                    <option value="medium" selected>Medium — Moderate concern</option>
                    <option value="high">High — Serious, urgent attention</option>
                  </select>
                  <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-gray-400">
                    <i class="ti ti-chevron-down text-sm"></i>
                  </span>
                </div>
              </div>

              <!-- Witness Details -->
              <div class="sm:col-span-2">
                <label for="witness_details" class="block text-xs font-semibold text-gray-600 mb-1.5">
                  Witness Details <span class="text-gray-400 text-xs">(optional)</span>
                </label>
                <div class="relative">
                  <span class="absolute top-3 left-3 flex items-center text-gray-400 pointer-events-none">
                    <i class="ti ti-users text-base"></i>
                  </span>
                  <textarea id="witness_details" name="witness_details" rows="3"
                            placeholder="Names and contact details of witnesses, if any"
                            class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent
                                   transition placeholder-gray-300 resize-none"></textarea>
                </div>
              </div>

            </div>

            <div class="mt-7 flex justify-between">
              <button type="button" onclick="goFirStep(1)"
                      class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
                <i class="ti ti-arrow-left"></i> Back
              </button>
              <button type="button" onclick="goFirStep(3)"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 transition">
                Next <i class="ti ti-arrow-right"></i>
              </button>
            </div>
          </div><!-- /fir-panel-2 -->


          <!-- ═══ STEP 3 — Evidence Upload ════════════════════════════════════ -->
          <div class="step-panel" id="fir-panel-3">
            <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
              <i class="ti ti-paperclip text-accent text-lg"></i> Evidence & Attachments
            </h2>

            <!-- Upload zone -->
            <div id="upload-zone"
                 class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center
                        cursor-pointer hover:border-accent hover:bg-accent/5 transition duration-200">
              <input type="file" id="file-input" multiple accept=".pdf,.jpg,.jpeg,.png,.mp4"
                     class="hidden" onchange="handleFileSelect(event)">
              <div class="w-14 h-14 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-3">
                <i class="ti ti-cloud-upload text-accent text-2xl"></i>
              </div>
              <p class="text-sm font-medium text-gray-700 mb-1">Click to upload or drag & drop</p>
              <p class="text-xs text-gray-400">PDF, JPG, PNG, or MP4 — Max 10MB each</p>
            </div>

            <!-- Uploaded files list -->
            <div id="uploaded-files" class="mt-5 space-y-3"></div>

            <!-- Hidden inputs for uploaded file IDs -->
            <div id="evidence-ids"></div>

            <p class="text-xs text-gray-400 mt-4">
              <i class="ti ti-info-circle text-sm"></i>
              Files are uploaded securely and linked to this FIR record.
            </p>

            <div class="mt-7 flex justify-between">
              <button type="button" onclick="goFirStep(2)"
                      class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
                <i class="ti ti-arrow-left"></i> Back
              </button>
              <button type="button" onclick="goFirStep(4)"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 transition">
                Next <i class="ti ti-arrow-right"></i>
              </button>
            </div>
          </div><!-- /fir-panel-3 -->


          <!-- ═══ STEP 4 — Review ══════════════════════════════════════════════ -->
          <div class="step-panel" id="fir-panel-4">
            <h2 class="text-navy font-semibold text-base mb-5 flex items-center gap-2">
              <i class="ti ti-clipboard-check text-accent text-lg"></i> Review FIR Details
            </h2>

            <div class="bg-[#f8f9fc] rounded-xl p-5 mb-6 space-y-4 text-sm">

              <!-- Complainant section -->
              <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Complainant</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                  <div><span class="text-gray-500">Name:</span> <span id="review-complainant_name" class="font-medium text-gray-800"></span></div>
                  <div><span class="text-gray-500">NID:</span> <span id="review-complainant_nid" class="font-medium text-gray-800"></span></div>
                  <div><span class="text-gray-500">Phone:</span> <span id="review-complainant_phone" class="font-medium text-gray-800"></span></div>
                  <div class="col-span-2"><span class="text-gray-500">Address:</span> <span id="review-complainant_address" class="font-medium text-gray-800"></span></div>
                </div>
              </div>

              <hr class="border-gray-200">

              <!-- Incident section -->
              <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Incident</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                  <div><span class="text-gray-500">Date:</span> <span id="review-incident_date" class="font-medium text-gray-800"></span></div>
                  <div><span class="text-gray-500">Time:</span> <span id="review-incident_time" class="font-medium text-gray-800"></span></div>
                  <div class="col-span-2"><span class="text-gray-500">Location:</span> <span id="review-incident_location" class="font-medium text-gray-800"></span></div>
                  <div class="col-span-2"><span class="text-gray-500">Description:</span> <span id="review-incident_description" class="font-medium text-gray-800"></span></div>
                  <div><span class="text-gray-500">Sections:</span> <span id="review-sections_applied" class="font-medium text-gray-800"></span></div>
                  <div><span class="text-gray-500">Priority:</span> <span id="review-priority" class="font-medium text-gray-800"></span></div>
                  <div class="col-span-2"><span class="text-gray-500">Witnesses:</span> <span id="review-witness_details" class="font-medium text-gray-800"></span></div>
                </div>
              </div>

              <hr class="border-gray-200">

              <!-- Evidence section -->
              <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Evidence</h3>
                <div id="review-evidence-list" class="text-gray-700">
                  <span class="text-gray-400 italic">No files uploaded</span>
                </div>
              </div>

              <hr class="border-gray-200">

              <!-- Officer info -->
              <div>
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Filing Officer</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                  <div><span class="text-gray-500">Name:</span> <span class="font-medium text-gray-800"><?= htmlspecialchars($officer['full_name']) ?></span></div>
                  <div><span class="text-gray-500">Badge:</span> <span class="font-medium text-gray-800"><?= htmlspecialchars($officer['badge_number']) ?></span></div>
                  <div><span class="text-gray-500">Station:</span> <span class="font-medium text-gray-800"><?= htmlspecialchars($officer['station_code']) ?></span></div>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap gap-3">
              <button type="button" onclick="goFirStep(3)"
                      class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
                <i class="ti ti-arrow-left"></i> Back to Edit
              </button>
              <button type="button" onclick="submitFir('draft')"
                      class="border border-accent text-accent hover:bg-accent/5 px-5 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 transition">
                <i class="ti ti-device-floppy"></i> Save as Draft
              </button>
              <button type="button" onclick="submitFir('create')"
                      class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm
                             font-semibold flex items-center gap-2 transition ml-auto">
                <i class="ti ti-send"></i> Submit FIR
              </button>
            </div>
          </div><!-- /fir-panel-4 -->


          <!-- ═══ STEP 5 — Success ════════════════════════════════════════════ -->
          <div class="step-panel" id="fir-panel-5">
            <div class="text-center py-8">
              <div class="w-20 h-20 rounded-full bg-accent/10 flex items-center justify-center mx-auto mb-5">
                <i class="ti ti-circle-check text-accent text-5xl"></i>
              </div>
              <h2 class="text-navy text-2xl font-bold mb-2" id="success-title">FIR Filed Successfully!</h2>
              <p class="text-gray-500 mb-4 text-sm max-w-sm mx-auto" id="success-message">
                Your FIR has been submitted and is pending review.
              </p>

              <div class="bg-[#f8f9fc] rounded-xl p-5 inline-block mb-6 text-left">
                <p class="text-xs text-gray-400 mb-1">FIR Number</p>
                <p class="text-2xl font-bold text-accent" id="display-fir-number">—</p>
                <div class="mt-3 pt-3 border-t border-gray-200 grid grid-cols-2 gap-x-6 text-sm">
                  <div>
                    <span class="text-gray-400 text-xs">Filed by</span>
                    <p class="font-medium text-gray-700"><?= htmlspecialchars($officer['full_name']) ?></p>
                  </div>
                  <div>
                    <span class="text-gray-400 text-xs">Station</span>
                    <p class="font-medium text-gray-700"><?= htmlspecialchars($officer['station_code']) ?></p>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-3 justify-center">
                <button type="button" onclick="resetFirForm()"
                        class="border border-gray-300 text-gray-600 px-5 py-2.5 rounded-xl text-sm
                               font-semibold flex items-center gap-2 hover:bg-gray-50 transition">
                  <i class="ti ti-plus"></i> File Another FIR
                </button>
                <a href="officer-dashboard.php"
                   class="bg-accent hover:bg-accent-dark text-white px-6 py-2.5 rounded-xl text-sm
                          font-semibold flex items-center gap-2 transition">
                  <i class="ti ti-layout-dashboard"></i> Go to Dashboard
                </a>
              </div>
            </div>
          </div><!-- /fir-panel-5 -->

        </form>
      </div><!-- /px-8 py-8 -->

    </div><!-- /card -->
  </div><!-- /max-w-3xl -->
</div><!-- /min-h-screen -->
  </div>

  <footer class="py-6 mt-8">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-xs text-gray-500 font-medium">
        © 2026 CaseFlowX
      </p>
    </div>
  </footer>

<!-- ── JavaScript ───────────────────────────────────────────────────────── -->
<script>
/* ── State ──────────────────────────────────────────────────────────────── */
let currentFirStep = 1;
let uploadedFiles = []; // { id, name, size, type, preview }
let evidenceIds = [];   // IDs from server after upload

const FIR_STEP_FIELDS = {
  1: ['complainant_name', 'complainant_nid', 'complainant_phone', 'complainant_address'],
  2: ['incident_date', 'incident_time', 'incident_location', 'incident_description', 'sections_applied', 'priority', 'witness_details'],
  3: [], // File upload — handled separately
  4: [],
};

/* ── Step navigation ────────────────────────────────────────────────────── */
function goFirStep(target) {
  // Validate current step before moving forward
  if (target > currentFirStep && !validateFirStep(currentFirStep)) {
    return;
  }

  // If moving to review step (4), populate review data first
  if (target === 4) {
    collectFirData();
    renderFirReview();
  }

  // Hide current, show target
  document.getElementById('fir-panel-' + currentFirStep).classList.remove('active');
  document.getElementById('fir-panel-' + target).classList.add('active');
  currentFirStep = target;
  updateFirStepUI();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateFirStepUI() {
  for (let i = 1; i <= 5; i++) {
    const circle = document.getElementById('fir-circle-' + i);
    const label  = document.getElementById('fir-label-'  + i);
    const line   = document.getElementById('fir-line-'   + (i - 1));

    if (i < currentFirStep) {
      // Completed
      circle.className = 'fir-circle w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold bg-accent text-white transition-all duration-300 border-2 border-transparent';
      circle.innerHTML = '<i class="ti ti-check text-base"></i>';
      label && label.classList.replace('text-gray-400', 'text-accent');
      line  && line.classList.replace('bg-gray-200', 'bg-accent');
    } else if (i === currentFirStep) {
      // Active
      circle.className = 'fir-circle w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold bg-navy text-white transition-all duration-300 border-2 border-accent';
      circle.innerHTML = '<i class="ti ' + ['ti-user','ti-map-pin','ti-paperclip','ti-clipboard-check','ti-check'][i-1] + ' text-base"></i>';
      label && label.classList.replace('text-gray-400', 'text-navy');
    } else {
      // Pending
      circle.className = 'fir-circle w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold bg-gray-200 text-gray-500 transition-all duration-300 border-2 border-transparent';
      circle.innerHTML = '<i class="ti ' + ['ti-user','ti-map-pin','ti-paperclip','ti-clipboard-check','ti-check'][i-1] + ' text-base"></i>';
      label && (label.className = label.className.replace('text-navy','text-gray-400').replace('text-accent','text-gray-400'));
    }
  }
}

// Initialize step UI
updateFirStepUI();

/* ── Validation ─────────────────────────────────────────────────────────── */
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

function validateFirStep(step) {
  clearErrors();
  let ok = true;
  const fields = FIR_STEP_FIELDS[step] || [];

  fields.forEach(name => {
    const el = document.getElementById(name);
    if (!el) return;
    const val = el.value.trim();

    if (name === 'complainant_name') {
      if (!val) { showError(name, 'Complainant name is required.'); ok = false; }
      else if (val.length < 3) { showError(name, 'Name must be at least 3 characters.'); ok = false; }
    }
    if (name === 'complainant_nid') {
      if (!val) { showError(name, 'NID is required.'); ok = false; }
      else if (!/^\d{10}$|^\d{17}$/.test(val)) { showError(name, 'Must be 10 or 17 digits.'); ok = false; }
    }
    if (name === 'complainant_phone' && val) {
      if (!/^01[3-9]\d{8}$/.test(val)) { showError(name, 'Enter a valid Bangladesh phone (01XXXXXXXXX).'); ok = false; }
    }
    if (name === 'complainant_address') {
      if (!val) { showError(name, 'Address is required.'); ok = false; }
    }
    if (name === 'incident_date') {
      if (!val) { showError(name, 'Incident date is required.'); ok = false; }
      else if (val > new Date().toISOString().split('T')[0]) { showError(name, 'Date cannot be in the future.'); ok = false; }
    }
    if (name === 'incident_location') {
      if (!val) { showError(name, 'Incident location is required.'); ok = false; }
    }
    if (name === 'incident_description') {
      if (!val) { showError(name, 'Description is required.'); ok = false; }
      else if (val.length < 20) { showError(name, 'Please provide at least 20 characters.'); ok = false; }
    }
  });

  return ok;
}

/* ── File upload ────────────────────────────────────────────────────────── */
const uploadZone = document.getElementById('upload-zone');
const fileInput  = document.getElementById('file-input');

uploadZone.addEventListener('click', () => fileInput.click());
uploadZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  uploadZone.classList.add('border-accent', 'bg-accent/5');
});
uploadZone.addEventListener('dragleave', () => {
  uploadZone.classList.remove('border-accent', 'bg-accent/5');
});
uploadZone.addEventListener('drop', (e) => {
  e.preventDefault();
  uploadZone.classList.remove('border-accent', 'bg-accent/5');
  const files = Array.from(e.dataTransfer.files);
  processFiles(files);
});

fileInput.addEventListener('change', (e) => {
  processFiles(Array.from(e.target.files));
  fileInput.value = '';
});

function processFiles(files) {
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'video/mp4'];
  const maxSize = 10 * 1024 * 1024; // 10MB

  files.forEach(file => {
    if (!allowedTypes.includes(file.type)) {
      showGlobalAlert('Invalid file type: ' + file.name + '. Only PDF, JPG, PNG, MP4 allowed.', 'error');
      return;
    }
    if (file.size > maxSize) {
      showGlobalAlert('File too large: ' + file.name + '. Maximum 10MB allowed.', 'error');
      return;
    }

    // Add to local list with temp id
    const localId = 'local_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    const fileObj = {
      id: localId,
      name: file.name,
      size: file.size,
      type: file.type,
      file: file,
    };
    uploadedFiles.push(fileObj);
    renderFileItem(fileObj);

    // Upload asynchronously
    uploadFile(fileObj);
  });
}

function renderFileItem(fileObj) {
  const container = document.getElementById('uploaded-files');
  const isImage = fileObj.type.startsWith('image/');
  const isPdf   = fileObj.type === 'application/pdf';
  const isVideo = fileObj.type === 'video/mp4';

  let iconClass = 'ti ti-file text-gray-500';
  let iconBg = 'bg-gray-100';

  if (isImage) { iconClass = 'ti ti-photo text-green-600'; iconBg = 'bg-green-50'; }
  else if (isPdf) { iconClass = 'ti ti-file-text text-red-500'; iconBg = 'bg-red-50'; }
  else if (isVideo) { iconClass = 'ti ti-video text-purple-500'; iconBg = 'bg-purple-50'; }

  const sizeKB = (fileObj.size / 1024).toFixed(1);
  const sizeStr = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';

  const html = `
    <div id="file-${fileObj.id}" class="flex items-center gap-3 p-3 bg-white border border-gray-100 rounded-xl">
      <div class="w-10 h-10 rounded-lg ${iconBg} flex items-center justify-center flex-shrink-0">
        <i class="ti ${iconClass} text-lg"></i>
      </div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-800 truncate">${escapeHtml(fileObj.name)}</p>
        <p class="text-xs text-gray-400">${sizeStr}</p>
      </div>
      <button type="button" onclick="removeFile('${fileObj.id}')"
              class="w-8 h-8 rounded-full hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-500 transition">
        <i class="ti ti-x text-sm"></i>
      </button>
    </div>
  `;
  container.insertAdjacentHTML('beforeend', html);
}

async function uploadFile(fileObj) {
  const formData = new FormData();
  formData.append('file', fileObj.file);
  formData.append('officer_id', <?= (int)$officer['id'] ?>);

  try {
    const resp = await fetch('api/upload_evidence.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success && data.id) {
      // Store server ID and remove local temp id
      const idx = uploadedFiles.findIndex(f => f.id === fileObj.id);
      if (idx !== -1) {
        uploadedFiles[idx].serverId = data.id;
        evidenceIds.push(data.id);
        updateEvidenceIds();
      }
    } else {
      showGlobalAlert('Upload failed: ' + (data.error || 'Unknown error'), 'error');
      removeFile(fileObj.id);
    }
  } catch (err) {
    showGlobalAlert('Upload error. Please try again.', 'error');
    removeFile(fileObj.id);
  }
}

function removeFile(localId) {
  const idx = uploadedFiles.findIndex(f => f.id === localId);
  if (idx !== -1) {
    const fileObj = uploadedFiles[idx];
    if (fileObj.serverId) {
      // Mark for deletion on server
      const idInput = document.getElementById('remove-ev-' + fileObj.serverId);
      if (idInput) idInput.value = '1';
      evidenceIds = evidenceIds.filter(id => id !== fileObj.serverId);
      updateEvidenceIds();
    }
    uploadedFiles.splice(idx, 1);
  }
  const el = document.getElementById('file-' + localId);
  if (el) el.remove();
}

function updateEvidenceIds() {
  const container = document.getElementById('evidence-ids');
  container.innerHTML = evidenceIds.map(id =>
    `<input type="hidden" name="evidence_ids[]" value="${id}">`
  ).join('');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

/* ── Collect & render review ────────────────────────────────────────────── */
function collectFirData() {
  // Data is already in the form, nothing to do here
}

function renderFirReview() {
  const fields = {
    'review-complainant_name': 'complainant_name',
    'review-complainant_nid': 'complainant_nid',
    'review-complainant_phone': 'complainant_phone',
    'review-complainant_address': 'complainant_address',
    'review-incident_date': 'incident_date',
    'review-incident_time': 'incident_time',
    'review-incident_location': 'incident_location',
    'review-incident_description': 'incident_description',
    'review-sections_applied': 'sections_applied',
    'review-priority': 'priority',
    'review-witness_details': 'witness_details',
  };

  Object.entries(fields).forEach(([reviewId, inputId]) => {
    const el = document.getElementById(reviewId);
    const input = document.getElementById(inputId);
    if (el && input) {
      let val = input.value.trim();
      if (inputId === 'incident_date' && val) {
        val = new Date(val).toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });
      }
      if (inputId === 'priority' && val) {
        val = val.charAt(0).toUpperCase() + val.slice(1);
      }
      el.textContent = val || '—';
    }
  });

  // Evidence list
  const evidenceList = document.getElementById('review-evidence-list');
  if (uploadedFiles.length > 0) {
    evidenceList.innerHTML = '<ul class="space-y-1">' + uploadedFiles.map(f => {
      const sizeKB = (f.size / 1024).toFixed(1);
      const sizeStr = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';
      return `<li class="flex items-center gap-2 text-gray-700">
        <i class="ti ${f.type.startsWith('image/') ? 'ti-photo text-green-600' : f.type === 'application/pdf' ? 'ti-file-text text-red-500' : 'ti-file text-gray-500'}"></i>
        <span class="text-sm">${escapeHtml(f.name)}</span>
        <span class="text-xs text-gray-400">(${sizeStr})</span>
      </li>`;
    }).join('') + '</ul>';
  } else {
    evidenceList.innerHTML = '<span class="text-gray-400 italic">No files uploaded</span>';
  }
}

/* ── Form submission ─────────────────────────────────────────────────────── */
async function submitFir(action) {
  clearErrors();
  const global = document.getElementById('alert-global');
  global.classList.add('hidden');

  const btnMap = {
    'draft': { selector: 'button[onclick="submitFir(\'draft\')"]', loading: 'Saving draft…' },
    'create': { selector: 'button[onclick="submitFir(\'create\')"]', loading: 'Submitting FIR…' },
  };

  const btnConfig = btnMap[action];
  const btn = document.querySelector(btnConfig.selector);
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<i class="ti ti-loader-2 animate-spin text-base"></i> ${btnConfig.loading}`;
  }

  try {
    const formData = new FormData(document.getElementById('fir-form'));
    formData.set('action', action);

    const resp = await fetch('api/fir.php', { method: 'POST', body: formData });
    const data = await resp.json();

    if (data.success) {
      // Show success panel
      document.getElementById('fir-panel-4').classList.remove('active');
      document.getElementById('fir-panel-5').classList.add('active');
      currentFirStep = 5;
      updateFirStepUI();

      document.getElementById('display-fir-number').textContent = data.fir_number || '—';

      if (action === 'draft') {
        document.getElementById('success-title').textContent = 'Draft Saved!';
        document.getElementById('success-message').textContent = 'Your FIR has been saved as a draft. You can resume it later.';
      } else {
        document.getElementById('success-title').textContent = 'FIR Filed Successfully!';
        document.getElementById('success-message').textContent = 'Your FIR has been submitted and is pending review.';
      }
    } else {
      if (data.errors) {
        Object.entries(data.errors).forEach(([k, v]) => showError(k, v));
      }
      if (data.message) {
        global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
        global.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${data.message}</span>`;
        global.classList.remove('hidden');
      }
    }
  } catch (err) {
    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
    global.innerHTML = '<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>Network error. Please check your connection and try again.</span>';
    global.classList.remove('hidden');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = action === 'draft'
        ? '<i class="ti ti-device-floppy"></i> Save as Draft'
        : '<i class="ti ti-send"></i> Submit FIR';
    }
  }
}

function showGlobalAlert(msg, type) {
  const global = document.getElementById('alert-global');
  if (type === 'error') {
    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-red-50 border-red-200 text-red-700';
    global.innerHTML = `<i class="ti ti-alert-triangle text-lg flex-shrink-0 mt-0.5"></i><span>${msg}</span>`;
  } else {
    global.className = 'mb-5 flex items-start gap-3 p-4 rounded-xl text-sm border bg-green-50 border-green-200 text-green-700';
    global.innerHTML = `<i class="ti ti-circle-check text-lg flex-shrink-0 mt-0.5"></i><span>${msg}</span>`;
  }
  global.classList.remove('hidden');
}

/* ── Reset form ─────────────────────────────────────────────────────────── */
function resetFirForm() {
  document.getElementById('fir-form').reset();
  uploadedFiles = [];
  evidenceIds = [];
  document.getElementById('uploaded-files').innerHTML = '';
  document.getElementById('evidence-ids').innerHTML = '';
  document.getElementById('alert-global').classList.add('hidden');

  // Reset priority default
  document.getElementById('priority').value = 'medium';

  // Go back to step 1
  document.getElementById('fir-panel-5').classList.remove('active');
  document.getElementById('fir-panel-1').classList.add('active');
  currentFirStep = 1;
  updateFirStepUI();
  clearErrors();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>
</body>
</html>