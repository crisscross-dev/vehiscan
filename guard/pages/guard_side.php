<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Ensure proper session state
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
  header("Location: ../../auth/login.php");
  exit();
}

// Refresh session data
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Server-side page state (same pattern as homeowner portal)
$allowedGuardPages = ['logs', 'vehicles', 'camera', 'visitor'];
$guardActivePage = strtolower(trim((string)($_GET['gpage'] ?? 'logs')));
if (!in_array($guardActivePage, $allowedGuardPages, true)) {
    $guardActivePage = 'logs';
}
$guardPageTitle = [
    'logs' => 'Access Logs',
    'vehicles' => 'Vehicles',
    'camera' => 'Live Camera',
    'visitor' => 'Visitor Passes'
][$guardActivePage] ?? 'Access Logs';

require_once __DIR__ . '/../../db.php';
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Guard Panel — VehiScan</title>

  <!-- CSS Files - Load in Order -->
  <link rel="stylesheet" href="../../assets/css/tailwind.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/tailwind.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/system.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/system.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/tailadmin-components.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/tailadmin-components.css'); ?>">
  <link rel="stylesheet" href="../css/guard_side.css?v=<?php echo @filemtime(__DIR__ . '/../css/guard_side.css'); ?>">
  <link rel="stylesheet" href="../css/guard-dark-mode.css?v=<?php echo @filemtime(__DIR__ . '/../css/guard-dark-mode.css'); ?>">
  <link rel="stylesheet" href="../css/guard-components.css?v=<?php echo @filemtime(__DIR__ . '/../css/guard-components.css'); ?>">
  <link rel="stylesheet" href="../css/guard-qr-modal.css?v=<?php echo @filemtime(__DIR__ . '/../css/guard-qr-modal.css'); ?>">
  <link rel="stylesheet" href="../../assets/css/premium-polish.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/premium-polish.css'); ?>">

  <style>
    /* Skeleton Loader — adapts to light/dark mode */
    :root {
      --skeleton-from: #e5e7eb;
      --skeleton-to: #f1f5f9;
    }

    body.dark,
    body.dark {
      --skeleton-from: #1e293b;
      --skeleton-to: #334155;
    }

    .skeleton {
      background: linear-gradient(90deg, var(--skeleton-from) 25%, var(--skeleton-to) 50%, var(--skeleton-from) 75%);
      background-size: 200% 100%;
      animation: skeleton-loading 1.5s infinite;
    }

    @keyframes skeleton-loading {
      0% {
        background-position: 200% 0;
      }

      100% {
        background-position: -200% 0;
      }
    }

    .skeleton-card {
      height: 200px;
      border-radius: 8px;
      margin-bottom: 1rem;
    }
  </style>

  <!-- External Libraries - Must load before custom scripts -->
  <script src="../../assets/js/libs/sweetalert2.all.min.js"></script>
  <script src="../../assets/js/libs/html5-qrcode.min.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/libs/html5-qrcode.min.js'); ?>" type="text/javascript"></script>

    <!-- CSRF Token - stored in meta tag for security (not in window scope) -->
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

  <!-- Core Utilities -->
  <script src="../../assets/js/toast.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/toast.js'); ?>"></script>
  <!-- Session timeout disabled for guard - 24/7 operation -->
</head>

<body class="m-0 p-0 overflow-hidden bg-guard-bg">

  <!-- User Dropdown (Fixed Position) -->
  <div id="user-dropdown" class="fixed rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg"
    style="z-index: var(--z-swal); display: none;" role="menu" aria-labelledby="user-trigger" aria-hidden="true">
    <div class="p-1">
      <button id="signOutBtn" type="button" role="menuitem"
        class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 font-medium transition-colors">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
        </svg>
        <span>Sign Out</span>
      </button>
    </div>
  </div>

  <!-- Mobile Menu Overlay -->
  <div id="mobile-overlay"></div>

  <div class="flex h-screen w-full">
    <!-- Fixed Sidebar -->
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col bg-gray-100 dark:bg-slate-950 transition-colors duration-300">
      <?php include __DIR__ . '/../includes/header.php'; ?>

      <!-- Content Area -->
      <div class="guard-command-shell flex-1 min-h-0">
        <section class="guard-center-panel min-w-0">
          <div class="guard-center-scroll overflow-auto p-6">
        <!-- Access Logs Page -->
        <div id="page-logs" class="page-content active">
          <div class="space-y-6">
            <!-- Page Header with Manual Entry Button -->
            <div class="flex flex-wrap justify-between items-center gap-4 bg-white dark:bg-slate-800 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Live Access Logs</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Monitor real-time vehicle entries and exits.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" id="openQrScannerBtn" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 11v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Scan QR
                    </button>
                    <button type="button" id="openManualLogBtn" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-md font-medium transition-colors shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Manual Entry
                    </button>
                </div>
            </div>
            <!-- Live Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="ta-stat-card">
                    <div class="ta-stat-icon blue">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </div>
                    <div class="ta-stat-content">
                        <p class="ta-stat-label">Entries Today</p>
                        <p id="statEntriesToday" class="ta-stat-value">0</p>
                    </div>
                </div>
                <div class="ta-stat-card">
                    <div class="ta-stat-icon amber">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </div>
                    <div class="ta-stat-content">
                        <p class="ta-stat-label">Exits Today</p>
                        <p id="statExitsToday" class="ta-stat-value">0</p>
                    </div>
                </div>
                <div class="ta-stat-card">
                    <div class="ta-stat-icon purple">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    <div class="ta-stat-content">
                        <p class="ta-stat-label">Active Visitors</p>
                        <p id="statActiveVisitors" class="ta-stat-value">0</p>
                    </div>
                </div>
            </div>

            <!-- Logs Container -->
            <div id="logsContainerWrapper">
                <div class="logs-table-container">
                <div class="text-center py-12">
                  <div
                    class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-gray-300 border-t-gray-600">
                  </div>
                  <p class="mt-2 text-gray-500">Loading logs...</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Homeowners Page -->
        <div id="page-homeowners" class="page-content hidden">
          <div class="space-y-6">
            <!-- Search Bar -->
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4"
              style="box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
              <div class="flex gap-3">
                <input type="text" id="homeownerSearch" placeholder="Search by name, plate, or address..." aria-label="Search homeowners"
                  class="flex-1 px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 dark:text-gray-200 dark:placeholder-gray-400">
                <button id="clearSearch" type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                  Clear
                </button>
                <button id="reloadHomeowners" type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                  </svg>
                  Refresh
                </button>
              </div>
            </div>

            <!-- Homeowner Details Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 bg-guard-bg">
              <!-- Vehicle Card -->
              <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-4 py-3 font-semibold"
                  style="background: var(--card, #fff); color: var(--guard-accent-dark, #222);">
                  <svg class="h-5 w-5 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0h4m-4 0H9m10 0a1 1 0 001-1v-4a1 1 0 00-1-1h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 00-.293.707V16"/></svg>
                  Vehicle Information
                </div>
                <div class="p-4">
                  <button type="button" class="aspect-video bg-white dark:bg-slate-700 rounded-lg overflow-hidden mb-4 cursor-zoom-in focus:outline-none"
                    onclick="openImageZoom(document.getElementById('carImage').src)">
                    <img id="carImage" src="" alt="Vehicle" class="w-full h-full object-contain">
                  </button>
                  <div class="space-y-2 text-sm">
                    <p id="vehicleType" class="font-medium text-gray-700 dark:text-gray-300">Vehicle Type: -</p>
                    <p id="vehicleColor" class="font-medium text-gray-700 dark:text-gray-300">Color: -</p>
                    <p id="plateNumber" class="font-medium text-gray-700 dark:text-gray-300">Plate Number: -</p>
                  </div>
                </div>
              </div>
              <!-- Owner Card -->
              <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                <div class="px-4 py-3 font-semibold"
                  style="background: var(--card, #fff); color: var(--guard-accent-dark, #222);">
                  <svg class="h-5 w-5 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                  Owner Information
                </div>
                <div class="p-4">
                  <button type="button" class="aspect-video bg-white dark:bg-slate-700 rounded-lg overflow-hidden mb-4 cursor-zoom-in focus:outline-none"
                    onclick="openImageZoom(document.getElementById('ownerImage').src)">
                    <img id="ownerImage" src="" alt="Owner" class="w-full h-full object-contain">
                  </button>
                  <div class="space-y-2 text-sm">
                    <p id="ownerName" class="font-medium text-gray-700 dark:text-gray-300">Name: -</p>
                    <p id="ownerAddress" class="font-medium text-gray-700 dark:text-gray-300">Address: -</p>
                    <p id="ownerContact" class="font-medium text-gray-700 dark:text-gray-300">Contact: -</p>
                  </div>
                </div>
                <div class="bg-white dark:bg-slate-800 px-4 py-3 border-t border-gray-200 dark:border-slate-700 flex justify-between items-center">
                  <button id="prevOwner" type="button"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>Prev
                  </button>
                  <span id="ownerCounter" class="text-sm font-medium text-gray-600">-/-</span>
                  <button id="nextOwner" type="button"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                    Next<svg class="w-4 h-4 inline ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                  </button>
                </div>
              </div>
            </div>

            <div class="guard-module-card overflow-hidden">
              <div class="guard-module-header">
                <div class="guard-module-title">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0h4m-4 0H9m10 0a1 1 0 001-1v-4a1 1 0 00-1-1h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 00-.293.707V16"></path>
                </svg>
                <span>My Vehicles</span>
                </div>
                <span class="guard-module-subtitle">Linked vehicles for selected homeowner</span>
              </div>
              <div id="guardMyVehiclesList" class="p-4 guard-vehicles-grid">
                <div class="col-span-full text-sm text-gray-500 dark:text-gray-400">Select a homeowner to view linked vehicles.</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Vehicles Page -->
        <div id="page-vehicles" class="page-content hidden">
          <div class="space-y-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4"
              style="box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
              <div class="flex flex-wrap gap-3 items-center">
                <input type="text" id="guardVehiclesSearch" placeholder="Search by plate, owner, type, color..." aria-label="Search vehicles"
                  class="flex-1 min-w-[220px] px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 dark:text-gray-200 dark:placeholder-gray-400">
                <button id="guardVehiclesClearSearch" type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                  Clear
                </button>
                <button id="guardVehiclesRefresh" type="button"
                  class="ta-btn ta-btn-primary ta-btn-sm guard-loading-btn">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                  </svg>
                  <span id="guardVehiclesRefreshLabel">Refresh</span>
                </button>
              </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden"
              style="box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);">
              <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Registered Vehicles</h3>
                <span id="guardVehiclesCount" class="text-xs text-gray-500 dark:text-gray-400">0 vehicles</span>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 dark:text-gray-400 uppercase text-xs tracking-wide">
                    <tr>
                      <th class="px-4 py-3 text-left">Plate</th>
                      <th class="px-4 py-3 text-left">Owner</th>
                      <th class="px-4 py-3 text-left">Vehicle</th>
                      <th class="px-4 py-3 text-left">Color</th>
                    </tr>
                  </thead>
                  <tbody id="guardVehiclesTableBody" class="divide-y divide-gray-200 dark:divide-slate-700">
                    <tr>
                      <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Loading vehicles...</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="px-4 py-3 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between">
                <button id="guardVehiclesPrev" type="button" class="ta-btn ta-btn-secondary ta-btn-xs">Previous</button>
                <span id="guardVehiclesPager" class="text-xs text-gray-500 dark:text-gray-400">Page 1 of 1</span>
                <button id="guardVehiclesNext" type="button" class="ta-btn ta-btn-secondary ta-btn-xs">Next</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Camera Page -->
        <div id="page-camera" class="page-content hidden">
          <div class="space-y-6 bg-guard-bg">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
              <div
                class="bg-gradient-to-r from-purple-500 to-purple-600 text-white px-4 py-3 flex justify-between items-center">
                <div class="flex items-center gap-2 font-semibold">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                  <span>Live Camera Feed</span>
                </div>
                <div class="flex items-center gap-3">
                  <select id="cameraSelect"
                    class="hidden text-xs bg-gray-700 border border-gray-600 rounded px-2 py-1 text-white">
                    <option value="">Select Camera</option>
                  </select>
                  <button id="fullscreenCamera" type="button" class="hidden p-2 hover:bg-gray-700 rounded transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4">
                      </path>
                    </svg>
                  </button>
                  <div class="flex items-center gap-2" id="cameraStatus">
                    <span class="w-2 h-2 rounded-full bg-gray-400 animate-pulse"></span>
                    <span class="text-xs font-semibold uppercase">Offline</span>
                  </div>
                </div>
              </div>
              <div class="p-6 bg-white dark:bg-slate-800">
                <div class="max-w-4xl mx-auto">
                  <div id="cameraContainer" class="aspect-video bg-black rounded-xl overflow-hidden relative shadow-lg">
                    <video id="liveCamera" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <canvas id="cameraCanvas" class="hidden"></canvas>

                    <div
                      class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-gray-700 to-gray-900 flex justify-center items-center"
                      id="cameraOverlay">
                      <div class="text-center text-gray-400">
                        <i class="fas fa-video-slash text-6xl mb-4 opacity-50"></i>
                        <p class="text-lg font-semibold uppercase tracking-wide">Camera is off</p>
                        <p class="text-sm mt-2 opacity-75">Click Start Camera to begin</p>
                      </div>
                    </div>

                    <div id="recordingIndicator"
                      class="absolute top-4 right-4 flex items-center gap-2 bg-red-600 text-white px-3 py-2 rounded-full text-sm font-bold shadow-lg"
                      style="display: none;">
                      <span class="w-2 h-2 bg-white rounded-full animate-pulse"></span>
                      <span>REC</span>
                    </div>

                    <div id="cameraTimestamp"
                      class="hidden absolute bottom-4 left-4 bg-black bg-opacity-70 text-white px-3 py-2 rounded text-sm font-mono">
                      --:--:--
                    </div>

                    <div id="snapshotFlash" class="hidden absolute inset-0 bg-white pointer-events-none"></div>
                  </div>

                  <div class="mt-6 flex flex-col gap-4">
                    <div class="flex justify-center gap-3 guard-camera-actions">
                      <button id="toggleCamera" type="button"
                        class="ta-btn ta-btn-primary ta-btn-lg guard-camera-btn guard-loading-btn">
                        <span id="powerIcon"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9"/></svg></span>
                        <span id="cameraBtnText">Start Camera</span>
                      </button>
                    </div>

                    <div id="secondaryControls" class="flex justify-center gap-3 guard-camera-actions" style="display: none;">
                      <button id="snapshotBtn" type="button"
                        class="ta-btn ta-btn-success ta-btn-sm guard-camera-btn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        <span>Snapshot</span>
                      </button>
                      <button id="switchCameraBtn" type="button"
                        class="ta-btn ta-btn-secondary ta-btn-sm guard-camera-btn"
                        style="display: none;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Switch Camera</span>
                      </button>
                      <button id="fullscreenCamera" type="button"
                        class="ta-btn ta-btn-secondary ta-btn-sm guard-camera-btn">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5v-4m0 4h-4m4 0l-5-5"></path></svg>
                        <span>Fullscreen</span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Visitor Passes Page -->
        <div id="page-visitor" class="page-content hidden">
          <div class="space-y-6 bg-guard-bg">
            <!-- Header -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div
                  class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center text-white">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z">
                    </path>
                  </svg>
                </div>
                <div>
                  <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Visitor Passes</h2>
                  <p class="text-sm text-gray-600 dark:text-gray-400">View active visitor passes</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button id="openVisitorModalBtn" type="button" class="ta-btn ta-btn-success ta-btn-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  New Request
                </button>
                <button id="refreshVisitorPasses" type="button" class="ta-btn ta-btn-primary ta-btn-sm guard-loading-btn">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                  <span id="refreshVisitorPassesLabel">Refresh</span>
                </button>
              </div>
            </div>

            <!-- Search Bar -->
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-4">
              <input type="text" id="visitorSearchInput" placeholder="Search by visitor name, plate number..." aria-label="Search visitor passes"
                class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white dark:bg-slate-700 dark:text-gray-200 dark:placeholder-gray-400">
            </div>



            <!-- Visitor Passes Cards Grid -->
            <div id="visitorPassesContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div class="col-span-full text-center py-12">
                <div
                  class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-gray-300 border-t-purple-500">
                </div>
                <p class="mt-2 text-gray-500">Loading visitor passes...</p>
              </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
              <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between gap-3">
                <div>
                  <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">QR Scan History</h3>
                  <p class="text-xs text-gray-500 dark:text-gray-400">Latest visitor pass scans at the gate</p>
                </div>
                <div class="flex items-center gap-2">
                  <button id="exportVisitorScanHistoryPage" type="button" class="ta-btn ta-btn-secondary ta-btn-sm guard-loading-btn">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-8m0 8l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1"></path>
                    </svg>
                    <span id="exportVisitorScanHistoryPageLabel">Export Page</span>
                  </button>
                  <button id="exportVisitorScanHistory" type="button" class="ta-btn ta-btn-secondary ta-btn-sm guard-loading-btn">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16v-8m0 8l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1"></path>
                    </svg>
                    <span id="exportVisitorScanHistoryLabel">Export All</span>
                  </button>
                  <button id="refreshVisitorScanHistory" type="button" class="ta-btn ta-btn-secondary ta-btn-sm guard-loading-btn">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span id="refreshVisitorScanHistoryLabel">Refresh</span>
                  </button>
                </div>
              </div>

              <div class="px-4 py-2 border-b border-gray-200 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-900/20">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p id="visitorScanExportSummary" class="text-xs text-gray-600 dark:text-gray-300">
                    Current page: 1 (25 rows/page). Filters: none.
                  </p>
                  <button id="visitorScanMetaToggle" type="button" class="ta-btn ta-btn-ghost ta-btn-sm" aria-expanded="false" aria-controls="visitorScanMetaPanel">
                    Show details
                  </button>
                </div>
                <div id="visitorScanMetaPanel" class="mt-2 hidden">
                  <div class="flex flex-wrap items-center gap-2">
                    <p id="visitorScanExportScopeHint" class="text-[11px] text-gray-500 dark:text-gray-400">
                      Using saved default: Export All Filtered
                    </p>
                    <button id="resetVisitorScanExportScope" type="button" class="text-[11px] font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 underline decoration-dotted">
                      Reset default
                    </button>
                  </div>
                  <p id="visitorScanLastExportInfo" class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                    Last export: none yet.
                  </p>
                </div>
              </div>

              <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700 bg-gray-50/80 dark:bg-slate-900/30">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-2.5">
                  <label class="block lg:col-span-2">
                    <span class="sr-only">Search scan history</span>
                    <input
                      id="visitorScanSearchInput"
                      type="text"
                      maxlength="100"
                      placeholder="Search visitor, plate, homeowner..." aria-label="Search visitor scan history"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100 dark:placeholder-gray-400"
                    >
                  </label>

                  <label class="block">
                    <span class="sr-only">Scan status</span>
                    <select
                      id="visitorScanStatusFilter"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                    >
                      <option value="">All statuses</option>
                      <option value="used_first_time">First Use</option>
                      <option value="repeat_scan">Repeat Scan</option>
                      <option value="scan">Scan</option>
                    </select>
                  </label>

                  <label class="block">
                    <span class="sr-only">Rows per page</span>
                    <select
                      id="visitorScanPerPage"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                    >
                      <option value="25">25 / page</option>
                      <option value="50">50 / page</option>
                      <option value="100">100 / page</option>
                    </select>
                  </label>

                  <div class="flex items-center gap-2">
                    <button id="visitorScanAdvancedToggle" type="button" class="ta-btn ta-btn-secondary ta-btn-sm w-full" aria-expanded="false" aria-controls="visitorScanAdvancedFilters">
                      More filters
                    </button>
                  </div>
                </div>

                <div id="visitorScanAdvancedFilters" class="mt-2.5 hidden rounded-md border border-gray-200 dark:border-slate-700 bg-white/90 dark:bg-slate-900/40 p-3">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-2.5">
                    <label class="block">
                      <span class="sr-only">Table density</span>
                      <select
                        id="visitorScanDensity"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                      >
                        <option value="comfortable">Comfortable</option>
                        <option value="compact">Compact</option>
                      </select>
                    </label>

                    <label class="block">
                      <span class="sr-only">Auto refresh interval</span>
                      <select
                        id="visitorScanRefreshInterval"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                      >
                        <option value="15000">Auto refresh: 15s</option>
                        <option value="30000">Auto refresh: 30s</option>
                        <option value="60000">Auto refresh: 60s</option>
                      </select>
                    </label>

                    <label class="block">
                      <span class="sr-only">Time display mode</span>
                      <select
                        id="visitorScanTimeMode"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                      >
                        <option value="exact">Time: Exact</option>
                        <option value="relative">Time: Relative</option>
                      </select>
                    </label>

                    <label class="block">
                      <span class="sr-only">Date from</span>
                      <input
                        id="visitorScanDateFrom"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                      >
                    </label>

                    <label class="block">
                      <span class="sr-only">Date to</span>
                      <input
                        id="visitorScanDateTo"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-700 dark:text-gray-100"
                      >
                    </label>
                  </div>

                  <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Quick range:</span>
                    <button id="visitorScanPresetToday" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">Today</button>
                    <button id="visitorScanPresetLast7" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">Last 7 Days</button>
                    <button id="visitorScanPresetMonth" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">This Month</button>
                    <div class="ml-auto flex items-center gap-2">
                      <button id="clearVisitorScanFilters" type="button" class="ta-btn ta-btn-ghost ta-btn-sm whitespace-nowrap">Clear</button>
                      <button id="resetVisitorScanHistoryState" type="button" class="ta-btn ta-btn-ghost ta-btn-sm whitespace-nowrap">Reset All</button>
                    </div>
                  </div>
                </div>

                <p id="visitorScanFilterStatus" class="mt-2 text-[11px] text-blue-600 dark:text-blue-400 hidden" aria-live="polite">
                  Applying filters...
                </p>

                <div id="visitorScanActiveFilters" class="mt-2 flex flex-wrap gap-2"></div>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                  <thead class="bg-gray-50 dark:bg-slate-900/40 text-gray-500 dark:text-gray-400 uppercase text-xs tracking-wide">
                    <tr>
                      <th class="px-4 py-3 text-left">Scanned At</th>
                      <th class="px-4 py-3 text-left">Visitor</th>
                      <th class="px-4 py-3 text-left">Plate</th>
                      <th class="px-4 py-3 text-left">Homeowner</th>
                      <th class="px-4 py-3 text-left">Scan Status</th>
                    </tr>
                  </thead>
                  <tbody id="visitorScanHistoryBody" class="divide-y divide-gray-200 dark:divide-slate-700">
                    <tr>
                      <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Loading scan history...</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="px-4 py-3 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between gap-3">
                <div class="space-y-0.5">
                  <p id="visitorScanHistorySummary" class="text-xs text-gray-500 dark:text-gray-400">Showing latest scans</p>
                  <p id="visitorScanLastRefreshed" class="text-[11px] text-gray-500 dark:text-gray-400">Last refreshed: not yet.</p>
                  <p id="visitorScanShortcutHints" class="text-[11px] text-gray-500 dark:text-gray-400">Shortcuts: Alt+Left/Right page, Ctrl+Shift+F search, Ctrl+Shift+J jump, Ctrl+Shift+V refresh, Ctrl+Shift+E export.</p>
                  <p id="visitorScanA11yAnnouncements" class="sr-only" aria-live="polite" aria-atomic="true"></p>
                </div>
                <div class="flex items-center gap-2">
                  <button id="visitorScanHistoryPrev" type="button" class="ta-btn ta-btn-secondary ta-btn-sm" disabled>Prev</button>
                  <span id="visitorScanHistoryPageLabel" class="text-xs font-medium text-gray-600 dark:text-gray-300">Page 1 of 1</span>
                  <div class="flex items-center gap-1.5">
                    <label for="visitorScanHistoryJumpInput" class="sr-only">Jump to page</label>
                    <input
                      id="visitorScanHistoryJumpInput"
                      type="number"
                      min="1"
                      step="1"
                      inputmode="numeric"
                      aria-label="Jump to page"
                      class="w-20 px-2 py-1 border border-gray-300 dark:border-slate-600 rounded-md text-xs bg-white dark:bg-slate-700 dark:text-gray-100 dark:placeholder-gray-400"
                      placeholder="Page"
                    >
                    <button id="visitorScanHistoryJumpBtn" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">Go</button>
                  </div>
                  <button id="visitorScanHistoryNext" type="button" class="ta-btn ta-btn-secondary ta-btn-sm" disabled>Next</button>
                </div>
              </div>
            </div>
          </div>
        </div>
          </div>
        </section>

        <aside id="guardDetailRail" class="guard-detail-rail border-r border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900">
          <div class="guard-detail-header">
            <div>
              <h3 class="guard-detail-title">Entry Details</h3>
              <p class="guard-detail-subtitle">Select any log row to inspect.</p>
            </div>
            <button id="guardDetailToggleBtn" type="button" class="guard-detail-toggle-btn" aria-expanded="true" aria-controls="guardDetailContent" aria-label="Minimize entry details">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"></path>
              </svg>
              <span id="guardDetailToggleLabel">Minimize</span>
            </button>
          </div>

          <div class="guard-detail-toolbar border-b border-gray-200 dark:border-slate-700 p-3">
            <div class="space-y-2">
              <div class="relative">
                <input type="text" id="logsSearch" placeholder="Search logs by name, plate, or action..." aria-label="Search access logs"
                  class="w-full pl-12 pr-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 transition-all">
                <svg class="absolute left-4 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9"
                    d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0"></path>
                </svg>
              </div>

              <div class="flex flex-wrap gap-2 items-center">
                <div class="filter-toggle-group" data-type="multiple">
                  <button id="filterToday" type="button" class="filter-toggle-item" data-variant="today" data-value="today"
                    aria-label="Filter logs for today (calendar day)">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Entries Today</span>
                  </button>
                  <button id="filterIn" type="button" class="filter-toggle-item" data-variant="in" data-value="in"
                    aria-label="Filter IN logs">
                    <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="6"/></svg>
                    <span>IN Only</span>
                  </button>
                  <button id="filterOut" type="button" class="filter-toggle-item" data-variant="out" data-value="out"
                    aria-label="Filter OUT logs">
                    <svg class="h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="6"/></svg>
                    <span>OUT Only</span>
                  </button>
                </div>

                <button id="clearLogsFilter" type="button"
                  class="inline-flex items-center gap-2 px-3 py-2 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                  </svg>
                  <span>Clear</span>
                </button>

                <button id="refreshLogs" type="button" class="ta-btn ta-btn-primary ta-btn-sm guard-loading-btn">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                    </path>
                  </svg>
                  <span id="refreshLogsLabel">Refresh</span>
                </button>
              </div>

              <!-- Date Range Filters -->
              <div class="flex flex-wrap gap-2 items-center bg-slate-50 dark:bg-slate-800/50 p-3 rounded-md border border-gray-200 dark:border-slate-700">
                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">Date Range:</label>
                <input type="date" id="logsDateFrom" placeholder="From" aria-label="Filter logs from date"
                  class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 dark:text-gray-200">
                <span class="text-gray-400">to</span>
                <input type="date" id="logsDateTo" placeholder="To" aria-label="Filter logs to date"
                  class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-slate-700 dark:text-gray-200">
                <button id="applyLogsDateFilter" type="button" class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                  Apply
                </button>
              </div>

              <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed">
                "Entries Today" uses the current calendar day (12:00 AM to now), not a rolling 24-hour window.
              </p>
            </div>
          </div>

          <div id="guardDetailEmpty" class="guard-detail-empty" aria-hidden="true"></div>

          <div id="guardDetailContent" class="guard-detail-content hidden" aria-live="polite">
            <div id="guardDetailStatus" class="guard-detail-status">-</div>

            <div class="guard-detail-media">
              <button type="button" class="guard-detail-media-card" data-zoom-target="guardDetailCarImg" aria-label="Zoom vehicle image">
                <span class="guard-detail-media-label">Vehicle</span>
                <img id="guardDetailCarImg" class="guard-detail-media-img" src="" alt="Vehicle image">
              </button>
              <button type="button" class="guard-detail-media-card" data-zoom-target="guardDetailOwnerImg" aria-label="Zoom homeowner image">
                <span class="guard-detail-media-label">Homeowner</span>
                <img id="guardDetailOwnerImg" class="guard-detail-media-img" src="" alt="Homeowner image">
              </button>

              <div id="guardDetailVehicleNavigator" class="mt-3 rounded-lg border border-gray-200 dark:border-slate-700 p-3 hidden">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Linked Vehicles</span>
                  <span id="guardDetailVehiclePosition" class="text-xs text-gray-600 dark:text-gray-300">Vehicle 0 of 0</span>
                </div>

                <div class="mt-2 flex items-center gap-2">
                  <button id="guardDetailVehiclePrev" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">Prev</button>
                  <button id="guardDetailVehicleNext" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">Next</button>
                  <span id="guardDetailVehicleMatch" class="ta-badge info">No vehicle selected</span>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
                  <div class="rounded-md bg-gray-50 dark:bg-slate-800 px-2 py-1.5">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Plate</p>
                    <p id="guardDetailNavPlate" class="font-semibold text-gray-800 dark:text-gray-100">-</p>
                  </div>
                  <div class="rounded-md bg-gray-50 dark:bg-slate-800 px-2 py-1.5">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Vehicle</p>
                    <p id="guardDetailNavVehicle" class="font-semibold text-gray-800 dark:text-gray-100">-</p>
                  </div>
                  <div class="rounded-md bg-gray-50 dark:bg-slate-800 px-2 py-1.5">
                    <p class="text-[11px] uppercase tracking-wide text-gray-500 dark:text-gray-400">Color</p>
                    <p id="guardDetailNavColor" class="font-semibold text-gray-800 dark:text-gray-100">-</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="guard-detail-grid">
              <div class="guard-detail-item guard-detail-item-full guard-detail-item-homeowner">
                <span class="guard-detail-label">Homeowner</span>
                <span id="guardDetailHomeowner" class="guard-detail-value">-</span>
              </div>

              <div class="guard-detail-item guard-detail-item-full guard-detail-item-plate">
                <span class="guard-detail-label">Plate</span>
                <span id="guardDetailPlate" class="ta-badge info guard-detail-plate-badge">-</span>
              </div>
              <div class="guard-detail-item">
                <span class="guard-detail-label">Vehicle</span>
                <span id="guardDetailVehicle" class="guard-detail-value">-</span>
              </div>
              <div class="guard-detail-item">
                <span class="guard-detail-label">Color</span>
                <span id="guardDetailColor" class="guard-detail-value">-</span>
              </div>
              <div class="guard-detail-item">
                <span class="guard-detail-label">Time</span>
                <span id="guardDetailTime" class="guard-detail-value">-</span>
              </div>
              <div class="guard-detail-item">
                <span class="guard-detail-label">Duration</span>
                <span id="guardDetailDuration" class="guard-detail-value">-</span>
              </div>
            </div>

            <div class="guard-detail-actions">
              <button id="guardDetailViewHistoryBtn" type="button" class="ta-btn ta-btn-secondary ta-btn-sm">View History</button>
            </div>
          </div>
        </aside>
      </div>
    </main>
  </div>

  <!-- Floating Camera Toggle Button -->
  <button id="floatingCameraToggle" type="button"
    class="fixed bottom-6 right-6 w-14 h-14 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all z-50 flex items-center justify-center group hover:w-auto hover:px-4">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
      </path>
    </svg>
    <span class="ml-2 hidden group-hover:inline-block text-sm font-semibold whitespace-nowrap">Camera</span>
  </button>

  <!-- Floating Camera Window -->
  <div id="floatingCameraWindow"
    class="hidden fixed glass rounded-xl shadow-2xl border border-white/20 backdrop-blur-xl"
    style="width: 640px; height: 480px; bottom: 90px; right: 20px; z-index: var(--z-floating);">
    <!-- Window Header -->
    <div id="cameraWindowHeader"
      class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-2.5 rounded-t-lg cursor-move flex items-center justify-between"
      style="user-select: none;">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
          </path>
        </svg>
        <span class="font-semibold text-sm">Live Camera</span>
        <div class="flex items-center gap-1 ml-2" id="floatCameraStatus">
          <span class="w-2 h-2 rounded-full bg-gray-300"></span>
          <span class="text-xs">Offline</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button id="minimizeCameraBtn" type="button" class="hover:bg-white hover:bg-opacity-20 p-1 rounded transition-colors"
          title="Minimize">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
          </svg>
        </button>
        <button id="closeCameraBtn" type="button" class="hover:bg-white hover:bg-opacity-20 rounded transition-colors"
          title="Close">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Camera View -->
    <div class="relative bg-black" style="height: 360px;">
      <video id="floatingCamera" autoplay playsinline muted class="w-full h-full object-cover"
        style="display: block;"></video>
      <canvas id="floatingCameraCanvas" class="hidden"></canvas>

      <div
        class="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-gray-700 to-gray-900 flex justify-center items-center"
        id="floatingCameraOverlay">
        <div class="text-center text-gray-400">
          <svg class="w-16 h-16 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
            </path>
          </svg>
          <p class="text-sm font-semibold">Camera Off</p>
        </div>
      </div>

      <div id="floatingTimestamp"
        class="hidden absolute bottom-2 left-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded text-xs font-mono">
        --:--:--
      </div>

      <div id="floatingSnapshotFlash" class="hidden absolute inset-0 bg-white pointer-events-none"></div>
    </div>

    <!-- Controls -->
    <div class="p-3 bg-white dark:bg-slate-800 rounded-b-lg flex items-center justify-between gap-2">
      <button id="floatingToggleCamera" type="button"
        class="flex-1 ta-btn ta-btn-primary ta-btn-sm guard-loading-btn">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M5.636 18.364a9 9 0 010-12.728m12.728 0a9 9 0 010 12.728m-9.9-2.829a5 5 0 010-7.07m7.072 0a5 5 0 010 7.07M13 12a1 1 0 11-2 0 1 1 0 012 0z">
          </path>
        </svg>
        <span id="floatingCameraBtnText">Start</span>
      </button>
      <button id="floatingSnapshotBtn" type="button"
        class="hidden ta-btn ta-btn-success ta-btn-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z">
          </path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z">
          </path>
        </svg>
      </button>
      <button id="floatingSwitchCameraBtn" type="button"
        class="hidden ta-btn ta-btn-secondary ta-btn-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
          </path>
        </svg>
      </button>
    </div>

    <!-- Resize Handle -->
    <div class="absolute bottom-0 right-0 w-4 h-4 cursor-nwse-resize" id="resizeHandle">
      <svg class="w-full h-full text-gray-400" fill="currentColor" viewBox="0 0 16 16">
        <path d="M16 16V11h-1v4h-4v1h5zM16 7V2h-5v1h4v4h1zM1 7V2h5V1H0v6h1zM1 11v5h5v-1H1v-4H0v5h1z" />
      </svg>
    </div>
  </div>

  <!-- Visitor Request Modal -->
  <div id="guardVisitorModal" class="hidden fixed inset-0 z-[60] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <button type="button" id="guardVisitorModalBackdrop" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity focus:outline-none" aria-hidden="true" aria-label="Close visitor modal"></button>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      
      <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full border border-gray-200 dark:border-slate-700">
        <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-900/30 sm:mx-0 sm:h-10 sm:w-10">
              <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
              </svg>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">Submit Visitor Request</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Submit a same-day visitor pass request for admin approval.</p>
              
              <div class="mt-4">
                <form id="guardAddVisitorForm" class="space-y-4" novalidate>
                  <label class="block relative">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Homeowner <span class="text-red-500">*</span></span>
                    <input type="text" id="guardVisitorHomeownerSearch" autocomplete="off" required aria-label="Search homeowner by name or plate" list="guardVisitorHomeownerList"
                      class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-gray-400"
                      placeholder="Search homeowner by name or plate">
                    <datalist id="guardVisitorHomeownerList"></datalist>
                    <input type="hidden" id="guardVisitorHomeownerId" name="homeowner_id" required>
                    <p id="guardVisitorHomeownerHint" class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Loading approved homeowners...</p>
                    <p id="guardVisitorHomeownerSelection" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400"></p>
                  </label>
                  
                  <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Visitor Name <span class="text-red-500">*</span></span>
                    <input type="text" id="guardVisitorName" name="visitor_name" required maxlength="100" aria-label="Full visitor name"
                      class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-gray-400"
                      placeholder="Full visitor name">
                  </label>
                  
                  <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Visitor Plate (Optional)</span>
                    <input type="text" id="guardVisitorPlate" name="visitor_plate" maxlength="15" aria-label="Visitor plate number"
                      class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-gray-400"
                      placeholder="e.g. ABC123" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9- ]/g, '').slice(0, 15)">
                  </label>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <label class="block">
                      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Valid From <span class="text-red-500">*</span></span>
                      <input type="datetime-local" id="guardVisitorFrom" name="valid_from" required
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white">
                    </label>
                    <label class="block">
                      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Valid Until <span class="text-red-500">*</span></span>
                      <input type="datetime-local" id="guardVisitorUntil" name="valid_until" required
                        class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white">
                    </label>
                  </div>
                  
                  <label class="block">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Purpose / Notes <span class="text-red-500">*</span></span>
                    <textarea id="guardVisitorPurpose" name="purpose" rows="3" required maxlength="500" aria-label="Purpose or notes for visitor"
                      class="mt-1 w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 bg-white dark:bg-slate-700 dark:text-white dark:placeholder-gray-400 resize-y"
                      placeholder="Enter complete purpose of visit..."></textarea>
                  </label>
                </form>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 dark:bg-slate-900/40 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200 dark:border-slate-700">
          <button type="submit" form="guardAddVisitorForm" id="guardAddVisitorSubmit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
            Submit Request
          </button>
          <button type="button" id="guardVisitorModalCancelBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- QR Scanner Modal -->
  <div id="qrScannerModal" class="fixed inset-0 z-[60] overflow-y-auto hidden" aria-hidden="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity" aria-hidden="true">
        <div class="absolute inset-0 bg-gray-500 dark:bg-slate-900 opacity-75"></div>
      </div>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200 dark:border-slate-700">
        <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">Scan Visitor QR Code</h3>
              <div class="mt-4 space-y-3">
                <div>
                  <label for="qr-direction-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Direction</label>
                  <select 
                    id="qr-direction-select" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="in">Entry</option>
                    <option value="out">Exit</option>
                  </select>
                </div>
                <div id="qr-reader" style="width: 100%; min-height: 300px; background: #000; border-radius: 8px; overflow: hidden;"></div>
                <div id="qr-reader-results" class="mt-4 p-3 bg-gray-100 dark:bg-slate-700 rounded text-sm hidden"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 dark:bg-slate-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button type="button" onclick="closeQRScannerModal()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Manual Log Entry Modal -->
  <div id="manualLogModal" class="fixed inset-0 z-[60] overflow-y-auto hidden" aria-hidden="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity" aria-hidden="true">
        <div class="absolute inset-0 bg-gray-500 dark:bg-slate-900 opacity-75"></div>
      </div>
      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
      <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200 dark:border-slate-700">
        <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
              <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="manual-modal-title">Manual Access Log Entry</h3>
              <form id="manual-log-form" onsubmit="submitManualLog(event)" class="mt-4 space-y-4">
                <div>
                  <label for="manual-plate-input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plate Number</label>
                  <input 
                    type="text" 
                    id="manual-plate-input" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="e.g., ABC-1234" 
                    required
                    maxlength="20"
                    style="text-transform: uppercase;"
                  />
                </div>
                <div>
                  <label for="manual-direction-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Direction</label>
                  <select 
                    id="manual-direction-select" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-md bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="in">Entry</option>
                    <option value="out">Exit</option>
                  </select>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded p-3">
                  <p class="text-xs text-blue-700 dark:text-blue-300">
                    The system will automatically determine if this is a homeowner or visitor based on the plate number.
                  </p>
                </div>
              </form>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 dark:bg-slate-700/50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
          <button type="submit" form="manual-log-form" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 hover:bg-blue-700 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
            Submit Entry
          </button>
          <button type="button" onclick="closeManualLogModal()" class="w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Image Zoom Modal -->
  <div id="imageZoomModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-label="Image zoom viewer" aria-hidden="true"
    style="z-index: var(--z-imagezoom);" onclick="closeImageZoom()">
    <button type="button" class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300 transition-colors"
      onclick="(event||window.event).stopPropagation(); closeImageZoom()">&times;</button>
    <img id="zoomedImage" src="" alt="Zoomed" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
  </div>



  <!-- Guard Application Scripts - Load in order: utils -> config -> features -> main -->
  <script src="../js/logger.js?v=<?php echo @filemtime(__DIR__ . '/../js/logger.js'); ?>"></script>
  <script src="../../assets/js/utils/html-escape.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/utils/html-escape.js'); ?>"></script>
  <script src="../../assets/js/keyboard-shortcuts.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/keyboard-shortcuts.js'); ?>"></script>
  <script src="../../assets/js/mobile-gestures.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/mobile-gestures.js'); ?>"></script>
  <script src="../../assets/js/table-enhancer.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/table-enhancer.js'); ?>"></script>
  <script src="../js/guard-dark-mode.js?v=<?php echo @filemtime(__DIR__ . '/../js/guard-dark-mode.js'); ?>"></script>
  <script src="../js/guard-qr-modal.js?v=<?php echo @filemtime(__DIR__ . '/../js/guard-qr-modal.js'); ?>"></script>
  <script src="../js/guard_side.js?v=<?php echo @filemtime(__DIR__ . '/../js/guard_side.js'); ?>"></script>
  <script src="../js/camera-core.js?v=<?php echo @filemtime(__DIR__ . '/../js/camera-core.js'); ?>"></script>
  <script src="../js/camera-handler.js?v=<?php echo @filemtime(__DIR__ . '/../js/camera-handler.js'); ?>"></script>
  <script src="../js/main-camera-handler.js?v=<?php echo @filemtime(__DIR__ . '/../js/main-camera-handler.js'); ?>"></script>
  <script>
    // Image zoom/open/close handled centrally in `guard_side.js` (openImageZoom / closeImageZoom)

    // Live time update
    function updateLiveTime() {
      const liveTime = document.getElementById('liveTime');
      if (liveTime) {
        const now = new Date();
        liveTime.textContent = now.toLocaleTimeString('en-US', {
          hour: 'numeric',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        });
      }
    }
    setInterval(updateLiveTime, 1000);
    updateLiveTime();

    // --- Auto-Refresh (Polling) for Guard Logs & Visitors ---
    let autoRefreshInterval = null;
    const POLLING_INTERVAL = 15000; // 15 seconds

    function startAutoRefresh() {
      if (autoRefreshInterval) return;
      __vsLog('[GUARD] Starting auto-refresh polling (15s)');
      autoRefreshInterval = setInterval(() => {
        // Only refresh if we are on the logs or visitor page
        const activeTab = document.querySelector('.menu-item[data-page].active')?.dataset.page;
        if (activeTab === 'logs' || activeTab === 'visitor' || activeTab === 'visitors' || !activeTab) {
          __vsLog('[GUARD] Auto-refreshing data...');
          if (typeof loadLogs === 'function') loadLogs(currentLogPage, { silent: true });
          if (typeof loadVisitorPasses === 'function') loadVisitorPasses({ silent: true });
        }
      }, POLLING_INTERVAL);
    }

    function stopAutoRefresh() {
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        __vsLog('[GUARD] Auto-refresh polling stopped');
      }
    }

    // Start by default
    startAutoRefresh();

    // Pause polling when tab is not visible to save resources
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopAutoRefresh();
      else startAutoRefresh();
    });
  </script>
</body>

</html>
