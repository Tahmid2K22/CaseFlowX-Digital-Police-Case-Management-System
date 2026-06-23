<?php
/**
 * search-criminals.php — CaseFlowX Criminal Database Search
 * Allows authenticated FIR Officers to search criminals by name or NID.
 */
require_once __DIR__ . '/db.php';

// Auth check
$officer = require_officer();
$db = get_db();

// Handle AJAX Search
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $query = trim($_GET['query'] ?? '');
    
    if ($query === '') {
        echo json_encode(['success' => true, 'criminals' => []]);
        exit;
    }

    try {
        // Search by name or NID
        $stmt = $db->prepare("
            SELECT * FROM criminals 
            WHERE name LIKE ? OR nid LIKE ?
            ORDER BY name ASC
        ");
        $stmt->execute(["%$query%", "%$query%"]);
        $criminals = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'criminals' => $criminals]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Search Criminal Database — CaseFlowX</title>
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
<body class="min-h-screen bg-[#F4F6F9] pb-12">

<!-- Premium Header -->
<header class="bg-navy text-white shadow-md">
  <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
      <a href="officer-dashboard.php" class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center text-white text-xl shadow hover:bg-accent-dark transition">
        <i class="ti ti-arrow-left"></i>
      </a>
      <div>
        <h1 class="text-lg font-bold leading-none">Criminal Database</h1>
        <p class="text-[11px] text-white/50 mt-0.5">Search and retrieve criminal profiles</p>
      </div>
    </div>
    <div class="flex items-center gap-5 text-sm">
      <div class="hidden sm:block text-right">
        <div class="font-semibold text-white"><?= htmlspecialchars($officer['full_name']) ?></div>
        <div class="text-[11px] text-white/55">Badge: <?= htmlspecialchars($officer['badge_number']) ?> · Station: <?= htmlspecialchars($officer['station_code']) ?></div>
      </div>
      <div class="h-6 w-px bg-white/20 hidden sm:block"></div>
      <a href="officer-dashboard.php" class="text-white/70 hover:text-white transition flex items-center gap-1 text-xs font-semibold">
        <i class="ti ti-layout-dashboard"></i> Dashboard
      </a>
      <a href="logout.php" class="text-white/70 hover:text-red-400 transition flex items-center gap-1 text-xs">
        <i class="ti ti-logout text-sm"></i> Logout
      </a>
    </div>
  </div>
</header>

<main class="max-w-4xl mx-auto px-4 py-8">

  <!-- Search Card -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
    <h2 class="text-navy font-bold text-lg mb-2 flex items-center gap-2">
      <i class="ti ti-search text-accent"></i> Search Profile
    </h2>
    <p class="text-gray-400 text-xs mb-4">Enter a full or partial name, or search using a 10-digit National ID (NID).</p>
    
    <form id="search-form" onsubmit="event.preventDefault(); triggerSearch();" class="relative flex items-center gap-3">
      <div class="relative flex-grow">
        <span class="absolute inset-y-0 left-4 flex items-center text-gray-400">
          <i class="ti ti-search text-lg"></i>
        </span>
        <input type="text" id="search-input" placeholder="Enter Name or NID..." class="w-full pl-12 pr-10 py-3.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent shadow-sm" oninput="debounceSearch()">
        <button type="button" id="clear-btn" class="hidden absolute inset-y-0 right-4 flex items-center text-gray-400 hover:text-navy transition" onclick="clearSearch()">
          <i class="ti ti-x text-base"></i>
        </button>
      </div>
      <button type="submit" class="bg-accent hover:bg-accent-dark text-white px-6 py-3.5 rounded-xl text-sm font-semibold transition shadow duration-150">
        Search
      </button>
    </form>
    
    <div id="feedback-message" class="hidden mt-3 p-3 rounded-xl border text-xs"></div>
  </div>

  <!-- Search Status Overlay & Results Area -->
  <div id="results-container" class="space-y-4">
    <!-- Initial State Overlay -->
    <div id="initial-state" class="text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="w-16 h-16 rounded-full bg-navy/5 flex items-center justify-center text-navy text-3xl mx-auto mb-4">
        <i class="ti ti-user-search"></i>
      </div>
      <h3 class="text-navy font-bold text-base">Criminal Records Search</h3>
      <p class="text-gray-400 text-xs mt-1 max-w-sm mx-auto">Please enter a Name or National Identification Number (NID) above to query the central database.</p>
    </div>

    <!-- Loading State Overlay -->
    <div id="loading-state" class="hidden text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="w-12 h-12 rounded-full bg-accent/10 flex items-center justify-center text-accent text-2xl mx-auto mb-4">
        <i class="ti ti-loader-2 animate-spin"></i>
      </div>
      <h3 class="text-navy font-bold text-base">Searching...</h3>
      <p class="text-gray-400 text-xs mt-1">Connecting to CaseFlowX records server...</p>
    </div>

    <!-- Empty Results Overlay -->
    <div id="empty-state" class="hidden text-center py-16 bg-white rounded-2xl border border-gray-100 shadow-sm">
      <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center text-red-500 text-3xl mx-auto mb-4">
        <i class="ti ti-alert-circle"></i>
      </div>
      <h3 class="text-navy font-bold text-base">No Records Found</h3>
      <p class="text-gray-400 text-xs mt-1">No criminal records match your search query. Please double-check spelling or NID.</p>
    </div>

    <!-- Results Grid -->
    <div id="results-grid" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Injected dynamically -->
    </div>
  </div>

</main>

<script>
let debounceTimeout = null;

function debounceSearch() {
  clearTimeout(debounceTimeout);
  
  const query = document.getElementById('search-input').value.trim();
  const clearBtn = document.getElementById('clear-btn');
  
  if (query.length > 0) {
    clearBtn.classList.remove('hidden');
  } else {
    clearBtn.classList.add('hidden');
    showState('initial');
    return;
  }
  
  debounceTimeout = setTimeout(() => {
    triggerSearch();
  }, 250);
}

function clearSearch() {
  document.getElementById('search-input').value = '';
  document.getElementById('clear-btn').value = '';
  document.getElementById('clear-btn').classList.add('hidden');
  document.getElementById('feedback-message').classList.add('hidden');
  showState('initial');
}

function showState(state) {
  const initial = document.getElementById('initial-state');
  const loading = document.getElementById('loading-state');
  const empty = document.getElementById('empty-state');
  const grid = document.getElementById('results-grid');
  
  initial.classList.add('hidden');
  loading.classList.add('hidden');
  empty.classList.add('hidden');
  grid.classList.add('hidden');
  
  if (state === 'initial') initial.classList.remove('hidden');
  else if (state === 'loading') loading.classList.remove('hidden');
  else if (state === 'empty') empty.classList.remove('hidden');
  else if (state === 'results') grid.classList.remove('hidden');
}

async function triggerSearch() {
  const query = document.getElementById('search-input').value.trim();
  const feedback = document.getElementById('feedback-message');
  
  if (query === '') {
    showState('initial');
    return;
  }
  
  feedback.classList.add('hidden');
  showState('loading');
  
  try {
    const res = await fetch(`search-criminals.php?ajax=1&query=${encodeURIComponent(query)}`);
    if (!res.ok) {
      throw new Error(`Server returned HTTP ${res.status}`);
    }
    
    const data = await res.json();
    if (data.success) {
      renderResults(data.criminals);
    } else {
      showFeedback(data.message || 'An error occurred during search.', false);
      showState('empty');
    }
  } catch (err) {
    showFeedback(err.message || 'Network error occurred. Please try again.', false);
    showState('empty');
  }
}

function showFeedback(msg, isSuccess) {
  const fb = document.getElementById('feedback-message');
  fb.textContent = msg;
  fb.className = 'mt-3 p-3 rounded-xl border text-xs ' + 
                 (isSuccess ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700');
  fb.classList.remove('hidden');
}

function renderResults(criminals) {
  const grid = document.getElementById('results-grid');
  grid.innerHTML = '';
  
  if (!criminals || criminals.length === 0) {
    showState('empty');
    return;
  }
  
  criminals.forEach(c => {
    let statusClass = 'border-orange-200 bg-orange-50 text-orange-700';
    if (c.status === 'In Custody') statusClass = 'border-yellow-200 bg-yellow-50 text-yellow-700';
    else if (c.status === 'Released') statusClass = 'border-green-200 bg-green-50 text-green-700';
    else if (c.status === 'Convicted') statusClass = 'border-blue-200 bg-blue-50 text-blue-700';
    
    const genderIcon = c.gender === 'female' ? 'ti-user-heart' : 'ti-user';
    const age = c.date_of_birth ? calculateAge(c.date_of_birth) : '—';
    
    const cardHtml = `
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 hover:border-accent/40 hover:shadow transition duration-200 flex flex-col justify-between">
        <div>
          <!-- Header -->
          <div class="flex items-start justify-between gap-3 mb-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-navy/5 flex items-center justify-center text-navy text-xl">
                <i class="ti ${genderIcon}"></i>
              </div>
              <div>
                <h4 class="text-navy font-bold text-sm leading-tight">${escapeHtml(c.name)}</h4>
                <p class="text-[10px] text-gray-400 mt-0.5 font-mono">NID: ${escapeHtml(c.nid)}</p>
              </div>
            </div>
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold border ${statusClass}">
              ${escapeHtml(c.status)}
            </span>
          </div>

          <!-- Bio & Location -->
          <div class="space-y-1.5 text-xs text-gray-600 mb-4">
            <div class="flex items-center gap-2">
              <i class="ti ti-calendar text-gray-400 text-sm"></i>
              <span>DOB: <strong class="text-gray-800">${escapeHtml(c.date_of_birth || '—')}</strong> (Age: ${age})</span>
            </div>
            <div class="flex items-center gap-2">
              <i class="ti ti-map-pin text-gray-400 text-sm"></i>
              <span>Last Location: <strong class="text-gray-800">${escapeHtml(c.last_known_location || '—')}</strong></span>
            </div>
          </div>
        </div>

        <!-- Crimes -->
        <div class="bg-gray-50 border border-gray-100 rounded-xl p-3 text-xs">
          <span class="text-gray-400 font-semibold block uppercase text-[9px] tracking-wide mb-1 flex items-center gap-1">
            <i class="ti ti-gavel"></i> Crimes Committed
          </span>
          <p class="text-red-700 font-semibold leading-relaxed">${escapeHtml(c.crimes_committed || 'No criminal record entered.')}</p>
        </div>
      </div>
    `;
    grid.innerHTML += cardHtml;
  });
  
  showState('results');
}

function calculateAge(dobString) {
  try {
    const today = new Date();
    const birthDate = new Date(dobString);
    let age = today.getFullYear() - birthDate.getFullYear();
    const m = today.getMonth() - birthDate.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return isNaN(age) ? '—' : age;
  } catch (e) {
    return '—';
  }
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
}
</script>
</body>
</html>
