/**
 * Admin Panel Dark Mode
 * Dedicated dark mode system for admin panel
 */

(function () {
    'use strict';

    const darkModeToggle = document.getElementById('darkModeToggle');
    const html = document.documentElement;
    const body = document.body;

    // Check for saved theme preference or default to light
    const currentTheme = localStorage.getItem('adminDarkMode') || 'light';

    // Initialize theme
    function enableDarkMode() {
        html.classList.add('dark');
        body.classList.add('dark');
        body.style.backgroundColor = ''; // Let Tailwind dark: classes handle it
    }

    function enableLightMode() {
        html.classList.remove('dark');
        body.classList.remove('dark');
        body.style.backgroundColor = ''; // Let Tailwind classes handle it
    }

    // Initialize on load
    if (currentTheme === 'dark') {
        enableDarkMode();
    } else {
        enableLightMode();
    }

    // Toggle dark mode
    if (darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            const isDark = html.classList.contains('dark');

            if (isDark) {
                enableLightMode();
                localStorage.setItem('adminDarkMode', 'light');
            } else {
                enableDarkMode();
                localStorage.setItem('adminDarkMode', 'dark');
            }
        });

        // Keyboard accessibility
        darkModeToggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                darkModeToggle.click();
            }
        });
    }
})();
