/**
 * Homeowner Portal Dark Mode
 * Matches system-wide dark mode implementation
 */

(function () {
    'use strict';

    const toggleBtn = document.getElementById('homeownerDarkModeToggle');
    const html = document.documentElement;
    const body = document.body;

    // Check for saved theme preference or default to light
    const currentTheme = localStorage.getItem('homeownerDarkMode') || 'light';

    // Initialize theme
    function enableDarkMode() {
        html.classList.add('dark');
        body.classList.add('dark');
        // Remove hardcoded styles to let CSS handle it
        body.style.backgroundColor = ''; 
    }

    function enableLightMode() {
        html.classList.remove('dark');
        body.classList.remove('dark');
        body.style.backgroundColor = ''; 
    }

    // Initialize on load
    if (currentTheme === 'dark') {
        enableDarkMode();
    } else {
        enableLightMode();
    }

    // Toggle listener
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const isDark = html.classList.contains('dark');

            if (isDark) {
                enableLightMode();
                localStorage.setItem('homeownerDarkMode', 'light');
            } else {
                enableDarkMode();
                localStorage.setItem('homeownerDarkMode', 'dark');
            }
        });

        // Keyboard accessibility
        toggleBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleBtn.click();
            }
        });
    }
})();
