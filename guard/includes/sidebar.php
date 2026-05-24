<aside id="sidebar"
  class="sidebar-transition sidebar-open relative flex flex-col border-r border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-hidden"
  role="navigation" aria-label="Main navigation">
  <!-- Brand Header -->
  <div class="flex h-14 items-center border-b border-gray-200 dark:border-slate-700 px-4">
    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center">
      <img src="../../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="h-full w-full object-contain"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span style="display:none;" class="text-2xl text-blue-600 font-bold">V</span>
    </div>
    <div class="ml-3 flex min-w-0 items-center gap-1">
      <span id="guardDashboardTitleText" class="truncate text-left font-bold text-lg text-gray-900 dark:text-white">VehiScan</span>
      <button id="editDashboardTitleBtnSidebar" type="button"
        class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-800 dark:hover:text-gray-200 transition-colors"
        aria-label="Edit dashboard title" title="Edit dashboard title">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
      </button>
    </div>
  </div>

  <!-- Navigation Menu -->
  <div class="flex-1 overflow-y-auto py-2 hide-scrollbar">
    <div class="mb-4 px-3">
      <div class="mb-2 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
        Guard panel
      </div>
      <div class="mb-3 px-2 flex items-center justify-between">
        <span id="guardDisplayNameText" class="text-xs font-medium text-gray-700 dark:text-gray-300">Guard</span>
        <button id="editGuardNameBtn" type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-800 dark:hover:text-gray-200 transition-colors"
          aria-label="Edit guard name" title="Edit guard name">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
          </svg>
        </button>
      </div>
      <div class="space-y-1">
        <a href="?gpage=logs"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo $guardActivePage === 'logs' ? 'active' : ''; ?>"
          data-page="logs">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
          </svg>
          <span>Access Logs</span>
        </a>

        <a href="?gpage=vehicles"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo $guardActivePage === 'vehicles' ? 'active' : ''; ?>"
          data-page="vehicles">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10m10 0h4m-4 0H9m10 0a1 1 0 001-1v-4a1 1 0 00-1-1h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 00-.293.707V16"></path>
          </svg>
          <span>Vehicles</span>
        </a>

        <a href="?gpage=camera"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo $guardActivePage === 'camera' ? 'active' : ''; ?>"
          data-page="camera">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z">
            </path>
          </svg>
          <span>Live Camera</span>
        </a>

        <a href="?gpage=visitor"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo $guardActivePage === 'visitor' ? 'active' : ''; ?>"
          data-page="visitor">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
          </svg>
          <span>Visitor Passes</span>
        </a>
      </div>
    </div>

    <!-- System Section -->
    <div class="px-3">
      <div class="mb-2 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
        ACTIONS
      </div>
      <div class="space-y-1">
        <button id="exportLogsBtn" type="button"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
          </svg>
          <span>Export Logs</span>
        </button>

        <button id="refreshAllBtn" type="button"
          class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
            </path>
          </svg>
          <span>Refresh All</span>
        </button>
      </div>
    </div>
  </div>



  <!-- User Section -->
  <div class="mt-auto border-t border-gray-200 dark:border-slate-700 p-4">
    <div class="relative">
      <button id="user-trigger" type="button"
        class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-slate-700 flex-shrink-0">
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
          </svg>
        </div>
        <div class="flex flex-col items-start flex-1 min-w-0">
          <div class="flex w-full items-center gap-1">
            <span id="guardDisplayNameText"
              class="truncate text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guard'); ?></span>
          </div>
          <span
            class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($_SESSION['role'] ?? 'guard'); ?></span>
        </div>
        <svg id="user-chevron" class="ml-auto h-4 w-4 transition-transform rotate-180" fill="none" stroke="currentColor"
          viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
      </button>
    </div>
  </div>
</aside>