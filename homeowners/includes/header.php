<!-- Header -->
<header
    class="flex h-14 items-center gap-4 border-b border-gray-200 px-6 bg-white shadow-sm dark:bg-slate-900 dark:border-slate-800 transition-colors duration-300">
    <!-- Mobile Menu Button -->
    <button id="mobile-menu-btn" type="button"
        class="flex items-center justify-center h-10 w-10 rounded-lg hover:bg-gray-100 active:bg-gray-200 transition-all duration-200">
        <svg class="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <div class="flex items-center gap-3">
        <div class="h-8 w-1 bg-blue-600 rounded-full"></div>
        <h1 id="page-title" class="text-xl font-bold text-gray-900 transition-all duration-300 dark:text-white">
            <?php echo htmlspecialchars($homeownerPageTitle ?? 'Dashboard'); ?>
        </h1>
    </div>

    <div class="ml-auto flex items-center gap-4">
        <!-- Notification Bell -->
        <div class="ta-notification-bell relative" id="hoNotifBellWrapper">
            <button id="hoNotifBellBtn" type="button" class="relative flex h-9 w-9 items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors" aria-label="Notifications" aria-haspopup="dialog" aria-controls="hoNotificationPanel" aria-expanded="false">
                <svg class="h-5 w-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span class="ta-notification-dot hidden" id="hoNotifDot"></span>
            </button>
            <div class="ta-notification-panel hidden" id="hoNotificationPanel" role="dialog" aria-label="Homeowner notifications panel" aria-hidden="true">
                <div class="ta-notification-header">
                    <span class="font-semibold text-sm">Notifications</span>
                    <button type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:underline" id="hoMarkAllReadBtn">Mark all read</button>
                </div>
                <div class="ta-notification-list" id="hoNotificationList">
                    <div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">No new notifications</div>
                </div>
                <div class="ta-notification-footer">
                    <a href="#" id="hoNotificationViewAllLink" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">View all notifications</a>
                </div>
            </div>
        </div>

        <!-- Dark Mode Toggle -->
        <button id="homeownerDarkModeToggle" type="button" class="theme-toggle-btn" aria-label="Toggle Dark Mode">
            <svg class="theme-icon sun-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
            <svg class="theme-icon moon-icon h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                </path>
            </svg>
        </button>
        <!-- Live Clock -->
        <div
            class="hidden sm:flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-lg border border-gray-200 dark:bg-slate-800 dark:border-slate-700">
            <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span id="liveTime" class="text-gray-700 text-sm font-semibold tabular-nums dark:text-gray-300"></span>
        </div>

        <!-- Session indicator -->
        <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-green-50 border border-green-200 rounded-lg">
            <div class="h-2 w-2 bg-green-500 rounded-full animate-pulse"></div>
            <span class="text-xs font-semibold text-green-700">Active</span>
        </div>
    </div>
</header>