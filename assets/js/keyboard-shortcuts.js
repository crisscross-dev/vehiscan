/**
 * Keyboard Shortcuts System
 * Provides global keyboard shortcuts with conflict prevention
 * Location: assets/js/keyboard-shortcuts.js
 * Phase 3.3
 */

(function () {
    'use strict';

    const DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
    const debugLog = (...args) => { if (DEBUG) console.log(...args); };

    if (window.keyboardShortcuts && window.keyboardShortcuts.__initialized) {
        debugLog('[Keyboard] Keyboard shortcuts already initialized; skipping duplicate init');
        return;
    }

    // Registered shortcuts (multiple handlers per combo)
    const shortcuts = new Map();
    let enabled = true;

    // Check if user is typing in an input
    const isTyping = () => {
        const activeEl = document.activeElement;
        return activeEl && (
            activeEl.tagName === 'INPUT' ||
            activeEl.tagName === 'TEXTAREA' ||
            activeEl.isContentEditable
        );
    };

    const getAppBasePath = () => {
        const path = window.location.pathname || '';
        const markers = ['/admin/', '/guard/', '/homeowners/', '/auth/', '/api/', '/visitor/'];
        let cutIndex = -1;

        markers.forEach((marker) => {
            const idx = path.indexOf(marker);
            if (idx >= 0 && (cutIndex === -1 || idx < cutIndex)) {
                cutIndex = idx;
            }
        });

        return cutIndex >= 0 ? path.slice(0, cutIndex) : '';
    };

    const toAppPath = (relativePath) => {
        const clean = String(relativePath || '').replace(/^\/+/, '');
        const base = getAppBasePath();
        return `${base}/${clean}`;
    };

    // Handle keydown events
    const handleKeydown = (e) => {
        if (!enabled) return;

        // Build key combination string
        const parts = [];
        if (e.ctrlKey || e.metaKey) parts.push('ctrl');
        if (e.altKey) parts.push('alt');
        if (e.shiftKey) parts.push('shift');
        parts.push(e.key.toLowerCase());
        const combo = parts.join('+');

        // Check if shortcut is registered
        const handlers = shortcuts.get(combo);
        if (!handlers || handlers.length === 0) return;

        // Newer registrations run first so page-specific handlers can override globals.
        for (let i = handlers.length - 1; i >= 0; i -= 1) {
            const shortcut = handlers[i];

            // Check if typing (unless shortcut allows it)
            if (isTyping() && !shortcut.allowWhileTyping) continue;

            // Prevent default if specified
            if (shortcut.preventDefault) {
                e.preventDefault();
            }

            // Returning true marks the shortcut as handled and stops further handlers.
            const handled = shortcut.callback(e);
            if (handled === true) {
                break;
            }
        }
    };

    // Show help modal
    const showHelp = () => {
        const shortcutList = Array.from(shortcuts.entries())
            .flatMap(([combo, handlers]) => handlers.map((data) => ({ combo, data })))
            .map(({ combo, data }) => {
                const keys = combo.split('+').map(k => {
                    const keyMap = {
                        'ctrl': 'Ctrl',
                        'alt': 'Alt',
                        'shift': 'Shift'
                    };
                    return keyMap[k] || k.toUpperCase();
                }).join(' + ');

                return `
          <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700">
            <span class="text-sm text-gray-700 dark:text-gray-300">${data.description}</span>
            <kbd class="px-2 py-1 text-xs font-semibold text-gray-800 bg-gray-100 border border-gray-200 rounded dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600">
              ${keys}
            </kbd>
          </div>
        `;
            }).join('');

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Keyboard Shortcuts',
                html: `<div class="text-left max-h-96 overflow-y-auto">${shortcutList}</div>`,
                icon: 'info',
                confirmButtonText: 'Got it!',
                confirmButtonColor: '#3b82f6',
                width: '600px'
            });
        }
    };

    // Public API
    window.keyboardShortcuts = {
        __initialized: true,

        // Register a shortcut
        register: function (key, callback, options = {}) {
            const {
                id = null,
                description = 'No description',
                preventDefault = true,
                allowWhileTyping = false
            } = options;

            const combo = key.toLowerCase();
            const shortcut = {
                id,
                callback,
                description,
                preventDefault,
                allowWhileTyping
            };

            if (!shortcuts.has(combo)) {
                shortcuts.set(combo, []);
            }

            const handlers = shortcuts.get(combo);
            if (id) {
                const existingIndex = handlers.findIndex((h) => h.id === id);
                if (existingIndex !== -1) {
                    handlers.splice(existingIndex, 1);
                }
            }

            handlers.push(shortcut);
        },

        // Unregister a shortcut
        unregister: function (key, id = null) {
            const combo = key.toLowerCase();
            if (!shortcuts.has(combo)) return;

            if (!id) {
                shortcuts.delete(combo);
                return;
            }

            const handlers = shortcuts.get(combo).filter((handler) => handler.id !== id);
            if (handlers.length === 0) {
                shortcuts.delete(combo);
            } else {
                shortcuts.set(combo, handlers);
            }
        },

        // Show help modal
        showHelp: showHelp,

        // Enable/disable shortcuts
        enable: function () {
            enabled = true;
        },

        disable: function () {
            enabled = false;
        },

        // Get all registered shortcuts
        getAll: function () {
            return Array.from(shortcuts.keys());
        }
    };

    // Register global shortcuts
    const registerGlobalShortcuts = () => {
        // Help modal
        window.keyboardShortcuts.register('?', showHelp, {
            id: 'global.help',
            description: 'Show keyboard shortcuts',
            preventDefault: true,
            allowWhileTyping: false
        });

        // Focus search (if exists)
        window.keyboardShortcuts.register('ctrl+k', () => {
            const searchInputs = [
                document.getElementById('logsSearch'),
                document.getElementById('searchInput'),
                document.querySelector('input[type="search"]'),
                document.querySelector('input[placeholder*="Search"]')
            ];

            const searchInput = searchInputs.find(el => el && el.offsetParent !== null);
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, {
            id: 'global.search.focus.ctrlk',
            description: 'Focus search',
            preventDefault: true
        });

        // Close modals with Escape (enhance existing behavior)
        window.keyboardShortcuts.register('escape', () => {
            // Close SweetAlert
            if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                Swal.close();
                return;
            }

            // Use centralized modal close logic when available so body/UI state is restored.
            if (typeof window.closeModal === 'function') {
                window.closeModal();
                return;
            }

            // Close custom modals
            const modals = document.querySelectorAll('.modal:not(.hidden), [id*="Modal"]:not(.hidden)');
            modals.forEach(modal => {
                if (modal.classList.contains('hidden') === false) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
            });

            // Restore page scroll and modal state classes for fallback modal closures.
            document.body.classList.remove('modal-open');
        }, {
            id: 'global.modal.close.escape',
            description: 'Close modals/dialogs',
            preventDefault: false,
            allowWhileTyping: true
        });

        // Navigation shortcuts (portal-specific, will be overridden if needed)
        window.keyboardShortcuts.register('ctrl+h', () => {
            if (typeof loadPage === 'function') {
                loadPage('dashboard');
            } else if (window.location.pathname.includes('admin')) {
                window.location.href = toAppPath('admin/admin_panel.php');
            } else if (window.location.pathname.includes('guard')) {
                window.location.href = toAppPath('guard/pages/guard_side.php');
            } else if (window.location.pathname.includes('homeowners')) {
                window.location.href = toAppPath('homeowners/portal.php');
            }
        }, {
            id: 'global.nav.home.ctrlh',
            description: 'Go to Dashboard/Home',
            preventDefault: true
        });

        window.keyboardShortcuts.register('ctrl+l', () => {
            if (typeof loadPage === 'function') {
                loadPage('logs');
            }
        }, {
            id: 'global.nav.logs.ctrll',
            description: 'View Access Logs',
            preventDefault: true
        });

        window.keyboardShortcuts.register('ctrl+v', () => {
            if (typeof loadPage === 'function') {
                loadPage('visitors');
            }
        }, {
            id: 'global.nav.visitors.ctrlv',
            description: 'View Visitor Passes',
            preventDefault: true
        });

        window.keyboardShortcuts.register('ctrl+m', () => {
            if (typeof loadPage === 'function') {
                loadPage('manage');
            }
        }, {
            id: 'global.nav.manage.ctrlm',
            description: 'Manage Records',
            preventDefault: true
        });

        // Action shortcuts
        window.keyboardShortcuts.register('ctrl+n', () => {
            // Try to find and click "Add New" or similar button
            const addButtons = [
                document.querySelector('[onclick*="openAddModal"]'),
                document.querySelector('[onclick*="openNewModal"]'),
                document.querySelector('button[data-action="add"]'),
                document.querySelector('.btn-add')
            ];

            const addButton = addButtons.find(btn => btn && btn.offsetParent !== null);
            if (addButton) {
                addButton.click();
            }
        }, {
            id: 'global.action.addnew.ctrln',
            description: 'Add New Record',
            preventDefault: true
        });

        // Focus search with /
        window.keyboardShortcuts.register('/', () => {
            const searchInputs = [
                document.getElementById('logsSearch'),
                document.getElementById('searchInput'),
                document.querySelector('input[type="search"]'),
                document.querySelector('input[placeholder*="Search"]')
            ];

            const searchInput = searchInputs.find(el => el && el.offsetParent !== null);
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, {
            id: 'global.search.focus.slash',
            description: 'Focus Search',
            preventDefault: true,
            allowWhileTyping: false
        });
    };

    // Initialize
    document.addEventListener('keydown', handleKeydown);
    registerGlobalShortcuts();

    debugLog('[Keyboard] Keyboard shortcuts system initialized (Phase 3.3)');
    debugLog('[Keyboard] Press ? to see all available shortcuts');

})();
