<!-- Shadcn Sidebar Component for Admin Pages -->
<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
  .sidebar-transition {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .sidebar-open {
    width: 16rem;
  }
  .sidebar-closed {
    width: 4rem;
  }
  .sidebar-text {
    transition: opacity 0.2s;
  }
  #sidebar.sidebar-closed .sidebar-text {
    opacity: 0;
    pointer-events: none;
  }
</style>

<aside id="sidebar" class="sidebar-transition sidebar-open fixed left-0 top-0 h-screen flex flex-col border-r bg-white dark:bg-slate-900 text-gray-900 dark:text-gray-100 dark:border-slate-700 overflow-x-hidden z-40" role="navigation" aria-label="Main navigation">
  <!-- Brand Header -->
  <div id="brand-header" class="flex h-14 items-center border-b border-gray-200 dark:border-slate-700 px-4">
    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center">
      <img src="../../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="h-full w-full object-contain" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span style="display:none;" class="text-2xl text-blue-600 font-bold">V</span>
    </div>
    <span id="brand-name" class="sidebar-text ml-3 text-left font-bold text-lg dark:text-white">VehiScan</span>
  </div>

  <!-- Navigation Menu -->
  <div class="flex-1 overflow-y-auto py-2">
    <div class="mb-4 px-3">
      <div id="main-label" class="sidebar-text mb-2 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
        Main menu
      </div>
      <div class="space-y-1">
        <a href="admin_panel.php?apage=dashboard" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
          </svg>
          <span class="sidebar-text">Dashboard</span>
        </a>
        
        <a href="admin_panel.php?apage=manage" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
          <span class="sidebar-text">Manage Records</span>
        </a>
        
        <a href="admin_panel.php?apage=logs" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          <span class="sidebar-text">Access Logs</span>
        </a>
        
        <a href="admin_panel.php?apage=audit" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
          </svg>
          <span class="sidebar-text">Audit Logs</span>
        </a>
        
        <a href="employee_list.php" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all bg-gray-200 dark:bg-slate-700 text-gray-900 dark:text-white font-medium">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
          </svg>
          <span class="sidebar-text">Employee Management</span>
        </a>
        

      </div>
    </div>

    <!-- System Section -->
    <div class="px-3">
      <div id="system-label" class="sidebar-text mb-2 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
        SYSTEM
      </div>
      <div class="space-y-1">
        <a href="admin_panel.php?apage=backup" class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
          </svg>
          <span class="sidebar-text">Database Backup</span>
        </a>
      </div>
    </div>
  </div>

  <!-- User Section -->
  <div class="border-t border-gray-200 dark:border-slate-700 p-4">
    <div class="flex items-center gap-3">
      <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-gray-600 text-white font-semibold">
        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
      </div>
      <div class="sidebar-text flex-1 min-w-0">
        <?php
          $sessionRole = (string)($_SESSION['role'] ?? 'Role');
          $roleDisplay = $sessionRole === 'owner' ? 'homeowner' : $sessionRole;
        ?>
        <div class="text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
        <div class="text-xs text-gray-600 dark:text-gray-400 truncate"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $roleDisplay))); ?></div>
      </div>
      <a href="../../auth/logout.php" class="sidebar-text flex-shrink-0">
        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400 hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
        </svg>
      </a>
    </div>
  </div>
</aside>
