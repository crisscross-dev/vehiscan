<header
    class="flex h-14 items-center gap-4 border-b border-gray-200 dark:border-slate-700 px-6 bg-white dark:bg-slate-900 transition-colors duration-300">
    <!-- Mobile Menu Button -->
    <button id="mobile-menu-btn" type="button"
        class="flex h-9 w-9 items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors md:hidden"
        aria-label="Toggle mobile menu">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <div class="flex items-center gap-2">
        <h1 id="page-title" class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guardPageTitle); ?></h1>
        <button id="editDashboardTitleBtn" type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-800 dark:hover:text-gray-200 transition-colors"
            aria-label="Edit dashboard title" title="Edit dashboard title">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
        </button>
    </div>
    <div class="ml-auto flex items-center gap-4">
        <!-- Notification Bell -->
        <div class="ta-notification-bell relative" id="guardNotifBellWrapper">
            <button id="guardNotifBellBtn" type="button" class="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors" aria-label="Notifications">
                <svg class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="ta-notification-dot hidden" id="guardNotifDot"></span>
            </button>
        </div>

        <!-- New Logs Badge -->
        <div id="newLogsBadge"
            class="hidden bg-gray-700 text-white px-3 py-1.5 rounded-full text-sm font-semibold animate-pulse">
            <svg class="h-3.5 w-3.5 inline-block -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span id="newLogsCount">0</span> new
        </div>

        <!-- Live Time -->
        <div id="liveTime" class="text-sm font-mono text-gray-600 dark:text-gray-300 hidden sm:block">--:--:--</div>

        <!-- Guard Dark Mode Toggle -->
        <button id="guardDarkModeToggle" type="button" class="theme-toggle-btn" aria-label="Toggle Dark Mode">
            <svg class="theme-icon sun-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
            <svg class="theme-icon moon-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>
    </div>
</header>