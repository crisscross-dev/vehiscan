/**
 * Mobile Touch Gestures
 * Adds swipe-to-open sidebar functionality for mobile devices
 */

(function () {
    'use strict';

    const DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
    const debugLog = (...args) => { if (DEBUG) console.log(...args); };

    // Only activate on mobile devices
    if (window.innerWidth >= 768) {
        return;
    }

    let touchStartX = 0;
    let touchStartY = 0;
    let touchStartTime = 0;
    const SWIPE_THRESHOLD = 50; // pixels
    const VERTICAL_THRESHOLD = 30; // pixels
    const TIME_THRESHOLD = 300; // milliseconds

    // Get sidebar and toggle button
    // Support both admin (sidebar-toggle) and guard/homeowner (mobile-menu-btn) button IDs
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle') || document.getElementById('mobile-menu-btn');

    if (!sidebar || !sidebarToggle) {
        console.warn('[Mobile Gestures] Sidebar or toggle button not found (checked for both sidebar-toggle and mobile-menu-btn)');
        return;
    }

    /**
     * Handle touch start - detect edge swipe
     */
    document.addEventListener('touchstart', (e) => {
        // Only trigger from left edge (first 20px)
        if (e.touches[0].clientX < 20) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchStartTime = Date.now();
        }
    }, { passive: true });

    /**
     * Handle touch move - track swipe direction
     */
    document.addEventListener('touchmove', (e) => {
        if (touchStartX === 0) return;

        const deltaX = e.touches[0].clientX - touchStartX;
        const deltaY = Math.abs(e.touches[0].clientY - touchStartY);
        const deltaTime = Date.now() - touchStartTime;

        // Check if it's a horizontal swipe (not vertical scroll)
        if (deltaX > SWIPE_THRESHOLD && deltaY < VERTICAL_THRESHOLD && deltaTime < TIME_THRESHOLD) {
            // Open sidebar
            if (sidebarToggle && typeof sidebarToggle.click === 'function') {
                sidebarToggle.click();
            }

            // Reset
            touchStartX = 0;
            touchStartY = 0;
            touchStartTime = 0;
        }
    }, { passive: true });

    /**
     * Handle touch end - reset tracking
     */
    document.addEventListener('touchend', () => {
        touchStartX = 0;
        touchStartY = 0;
        touchStartTime = 0;
    }, { passive: true });

    /**
     * Swipe to close sidebar
     */
    if (sidebar) {
        let sidebarTouchStartX = 0;

        sidebar.addEventListener('touchstart', (e) => {
            sidebarTouchStartX = e.touches[0].clientX;
        }, { passive: true });

        sidebar.addEventListener('touchmove', (e) => {
            if (sidebarTouchStartX === 0) return;

            const deltaX = e.touches[0].clientX - sidebarTouchStartX;

            // Swipe left to close
            if (deltaX < -SWIPE_THRESHOLD) {
                if (sidebarToggle && typeof sidebarToggle.click === 'function') {
                    sidebarToggle.click();
                }
                sidebarTouchStartX = 0;
            }
        }, { passive: true });

        sidebar.addEventListener('touchend', () => {
            sidebarTouchStartX = 0;
        }, { passive: true });
    }

    debugLog('[Mobile Gestures] Initialized - Swipe from left edge to open sidebar');
})();
