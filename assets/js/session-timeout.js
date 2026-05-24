/**
 * Session Timeout Warning System
 * 
 * Displays a warning before session expires and auto-logs out when timeout occurs
 * Usage: Include this script in any page that requires session management
 */

(function() {
    'use strict';

    const DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
    const debugLog = (...args) => { if (DEBUG) console.log(...args); };
    
    // Configuration
    const CONFIG = {
        sessionLifetime: 1800, // 30 minutes in seconds
        warningTime: 300,      // Show warning 5 minutes before timeout
        checkInterval: 30000,  // Check every 30 seconds
        logoutUrl: '../auth/logout.php',
        keepAliveUrl: ''
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

    const toAppPath = (relative) => {
        const base = getAppBasePath();
        const clean = String(relative || '').replace(/^\/+/, '');
        return `${base}/${clean}`;
    };

    CONFIG.keepAliveUrl = toAppPath('api/keep_alive.php');
    
    let lastActivity = Date.now();
    let warningShown = false;
    let warningDialog = null;
    
    /**
     * Initialize the session timeout monitor
     */
    function init() {
        // Track user activity
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        activityEvents.forEach(event => {
            document.addEventListener(event, resetActivity, true);
        });
        
        // Start monitoring
        setInterval(checkSession, CONFIG.checkInterval);
        
        debugLog('[Session Monitor] Initialized - Session timeout: ' + (CONFIG.sessionLifetime / 60) + ' minutes');
    }
    
    /**
     * Reset last activity timestamp
     */
    function resetActivity() {
        const now = Date.now();
        
        // Only reset if warning is not shown (to prevent accidental dismissal)
        if (!warningShown) {
            lastActivity = now;
        }
        
        // Send keep-alive ping if activity detected after warning
        if (warningShown && (now - lastActivity) > 5000) { // 5 seconds after warning
            sendKeepAlive();
        }
    }
    
    /**
     * Check session status
     */
    function checkSession() {
        const now = Date.now();
        const inactiveTime = (now - lastActivity) / 1000; // Convert to seconds
        const timeRemaining = CONFIG.sessionLifetime - inactiveTime;
        
        // Session expired
        if (timeRemaining <= 0) {
            handleSessionExpired();
            return;
        }
        
        // Show warning if approaching timeout
        if (timeRemaining <= CONFIG.warningTime && !warningShown) {
            showWarning(Math.floor(timeRemaining));
        }
        
        // Update warning countdown if shown
        if (warningShown && warningDialog) {
            updateWarningCountdown(Math.floor(timeRemaining));
        }
    }
    
    /**
     * Show session timeout warning
     */
    function showWarning(secondsRemaining) {
        warningShown = true;
        
        const minutes = Math.floor(secondsRemaining / 60);
        const seconds = secondsRemaining % 60;
        const timeText = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Check if SweetAlert2 is available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Expiring Soon',
                html: `
                    <p style="margin-bottom: 15px;">Your session will expire in:</p>
                    <div style="font-size: 2rem; font-weight: bold; color: #f59e0b; margin: 20px 0;" id="sessionCountdown">${timeText}</div>
                    <p style="margin-top: 15px; color: #6b7280;">Click "Stay Logged In" to continue your session.</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Stay Logged In',
                cancelButtonText: 'Logout',
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                allowOutsideClick: false,
                allowEscapeKey: false,
                heightAuto: false
            }).then((result) => {
                warningShown = false;
                
                if (result.isConfirmed) {
                    // Extend session
                    extendSession();
                } else if (result.isDismissed) {
                    // User chose to logout
                    logout();
                }
            });
            
            warningDialog = Swal;
        } else {
            // Fallback to native confirm
            const message = `Your session will expire in ${minutes} minutes and ${seconds} seconds.\n\nClick OK to stay logged in, or Cancel to logout now.`;
            if (confirm(message)) {
                warningShown = false;
                extendSession();
            } else {
                logout();
            }
        }
    }
    
    /**
     * Update warning countdown display
     */
    function updateWarningCountdown(secondsRemaining) {
        const countdownEl = document.getElementById('sessionCountdown');
        if (countdownEl) {
            const minutes = Math.floor(secondsRemaining / 60);
            const seconds = secondsRemaining % 60;
            countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            // Change color as time runs out
            if (secondsRemaining <= 60) {
                countdownEl.style.color = '#dc2626'; // Red
            } else if (secondsRemaining <= 120) {
                countdownEl.style.color = '#f59e0b'; // Orange
            }
        }
    }
    
    /**
     * Handle session expiration
     */
    function handleSessionExpired() {
        warningShown = false;
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Expired',
                text: 'Your session has expired due to inactivity. You will be redirected to the login page.',
                icon: 'info',
                confirmButtonText: 'Login Again',
                confirmButtonColor: '#3b82f6',
                allowOutsideClick: false,
                heightAuto: false
            }).then(() => {
                window.location.href = CONFIG.logoutUrl + '?timeout=1';
            });
        } else {
            alert('Your session has expired. You will be redirected to the login page.');
            window.location.href = CONFIG.logoutUrl + '?timeout=1';
        }
    }
    
    /**
     * Extend session by sending keep-alive request
     */
    async function extendSession() {
        try {
            const keepAliveResult = await sendKeepAlive();
            if (!keepAliveResult.ok) {
                throw new Error(keepAliveResult.message || 'Keep-alive failed');
            }

            lastActivity = Date.now();
            warningShown = false;

            if (typeof Swal !== 'undefined') {
                Swal.close();
            }

            // Show success toast if available
            if (window.toast && typeof window.toast.success === 'function') {
                window.toast.success('Session extended');
            }

            debugLog('[Session Monitor] Session extended');
        } catch (error) {
            console.error('[Session Monitor] Unable to extend session:', error);
            warningShown = false;

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Session Extension Failed',
                    text: 'The session could not be extended. Please sign in again.',
                    icon: 'error',
                    confirmButtonText: 'Sign In Again',
                    confirmButtonColor: '#3b82f6',
                    allowOutsideClick: false,
                    heightAuto: false
                }).then(() => {
                    window.location.href = CONFIG.logoutUrl + '?timeout=1';
                });
            } else {
                alert('The session could not be extended. Please sign in again.');
                window.location.href = CONFIG.logoutUrl + '?timeout=1';
            }
        }
    }
    
    /**
     * Determine the current role for keep-alive requests.
     */
    function getKeepAliveRole() {
        const path = window.location.pathname.toLowerCase();
        if (path.includes('/admin/')) return 'admin';
        if (path.includes('/guard/')) return 'guard';
        if (path.includes('/homeowners/')) return 'homeowner';
        return null;
    }

    /**
     * Send keep-alive ping to server
     */
    async function sendKeepAlive() {
        try {
            const role = getKeepAliveRole();
            const payload = { action: 'keep_alive' };
            if (role) {
                payload.role = role;
            }

            const response = await fetch(CONFIG.keepAliveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            let data = {};
            try {
                data = await response.clone().json();
            } catch (error) {
                data = {};
            }

            return {
                ok: response.ok && data.success !== false,
                status: response.status,
                message: data.message || ''
            };
        } catch (error) {
            console.error('[Session Monitor] Keep-alive failed:', error);
            return {
                ok: false,
                status: 0,
                message: error instanceof Error ? error.message : 'Keep-alive failed'
            };
        }
    }
    
    /**
     * Logout user
     */
    function logout() {
        window.location.href = CONFIG.logoutUrl;
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
