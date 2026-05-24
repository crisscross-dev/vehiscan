<aside id="sidebar"
    class="sidebar-transition sidebar-open relative flex flex-col border-r border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-x-hidden transition-colors duration-300"
    role="navigation" aria-label="Main navigation">
    <!-- Brand Header -->
    <div class="flex h-14 items-center border-b border-gray-200 dark:border-slate-700 px-4">
        <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center">
            <img src="../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="h-full w-full object-contain"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span style="display:none;" class="text-2xl text-blue-600 font-bold">V</span>
        </div>
        <span class="ml-3 text-left font-bold text-lg text-gray-900 dark:text-white">VehiScan</span>
    </div>

    <!-- Navigation Menu -->
    <div class="flex-1 overflow-y-auto hide-scrollbar py-2">
        <div class="mb-4 px-3">
            <div class="mb-2 px-2 text-xs font-semibold text-gray-600 dark:text-gray-400">
                HOMEOWNER PORTAL
            </div>
            <div class="space-y-1">
                <a href="?hpage=dashboard"
                    class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo (($homeownerActivePage ?? 'dashboard') === 'dashboard') ? 'active' : ''; ?>"
                    data-page="dashboard">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                        </path>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="?hpage=passes"
                    class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo (($homeownerActivePage ?? 'dashboard') === 'passes') ? 'active' : ''; ?>"
                    data-page="passes">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z">
                        </path>
                    </svg>
                    <span>Visitor Passes</span>
                </a>

                <a href="?hpage=vehicles"
                    class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo (($homeownerActivePage ?? 'dashboard') === 'vehicles') ? 'active' : ''; ?>"
                    data-page="vehicles">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2">
                        </path>
                    </svg>
                    <span>My Vehicles</span>
                </a>

                <a href="?hpage=activity"
                    class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo (($homeownerActivePage ?? 'dashboard') === 'activity') ? 'active' : ''; ?>"
                    data-page="activity">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                    <span>Vehicle Activity</span>
                </a>

                <a href="?hpage=profile"
                    class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-800 <?php echo (($homeownerActivePage ?? 'dashboard') === 'profile') ? 'active' : ''; ?>"
                    data-page="profile">
                    <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>My Profile</span>
                </a>
            </div>
        </div>
    </div>



    <!-- User Section -->
    <div class="mt-auto border-t border-gray-200 dark:border-slate-700 p-4">
        <div class="relative">
            <button id="user-trigger" type="button" aria-haspopup="menu" aria-controls="user-dropdown" aria-expanded="false"
                class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-sm hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 flex-shrink-0">
                    <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <div class="flex flex-col items-start flex-1">
                    <span
                        class="text-sm font-medium text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($homeowner['name'] ?? ''); ?></span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">Homeowner</span>
                </div>
                <svg id="user-chevron" class="ml-auto h-4 w-4 transition-transform rotate-180" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                    </path>
                </svg>
            </button>
        </div>
    </div>
</aside>