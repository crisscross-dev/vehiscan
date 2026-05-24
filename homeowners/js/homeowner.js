// Homeowner Portal JavaScript
let currentPage = 'dashboard';
let visitorPasses = [];
let passFilters = {
    search: '',
    status: 'all'
};
let homeownerNotif = {
    pendingPasses: 0,
    openProfileRequests: 0,
    approvedPasses: 0,
    usedPasses: 0,
    rejectedPasses: 0,
    expiredPasses: 0,
    unread: true
};
let liveTimeIntervalId = null;
let homeownerNotificationsBound = false;
let homeownerPortalInitialized = false;
let passNotificationState = {
    approvedSeen: new Set(),
    usedSeen: new Set(),
    rejectedSeen: new Set(),
    expiredSeen: new Set()
};

// Module-level UI element references for unified event handling
let userDropdown = null;
let userTrigger = null;
let notificationPanel = null;
let bellBtn = null;

const HOMEOWNER_ALLOWED_PAGES = new Set(['dashboard', 'passes', 'vehicles', 'activity', 'profile']);

async function fetchJson(url, options = {}) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
    };

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers
    });

    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        throw new Error(`Unexpected server response (${response.status})`);
    }

    const data = await response.json();
    if (!response.ok) {
        const message = data.message || data.error || `Request failed (${response.status})`;
        const error = new Error(String(message));
        error.status = response.status;
        throw error;
    }

    return data;
}

window.homeownerFetchJson = fetchJson;

function initializeHomeownerPortal() {
    if (homeownerPortalInitialized) {
        return;
    }
    homeownerPortalInitialized = true;

    initializePassNotificationState();
    initializeNavigation();
    initializeUserMenu();
    initializeMobileMenu();
    initializeHomeownerNotifications();
    if (typeof window.initializeVehicleManagement === 'function') {
        window.initializeVehicleManagement();
    }
    initializeScrollEffects();
    updateLiveTime();
    if (liveTimeIntervalId) {
        clearInterval(liveTimeIntervalId);
    }
    liveTimeIntervalId = setInterval(updateLiveTime, 1000);

    initializePassFilters();
    const initialPage = getInitialHomeownerPage();
    loadPage(initialPage);
    
    // Add page visibility handler
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('beforeunload', () => {
        if (liveTimeIntervalId) {
            clearInterval(liveTimeIntervalId);
            liveTimeIntervalId = null;
        }
    }, { once: true });
}

// Initialize immediately when possible; fallback to DOMContentLoaded.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeHomeownerPortal);
} else {
    initializeHomeownerPortal();
}

// Navigation
function initializeNavigation() {
    const menuItems = document.querySelectorAll('.menu-item');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const page = this.getAttribute('data-page');
            if (page && HOMEOWNER_ALLOWED_PAGES.has(page)) {
                loadPage(page);
                
                // Close mobile menu if open (consolidates mobile menu logic)
                if (window.innerWidth <= 768 && sidebar && overlay) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                }
            }
            
            return false;
        });
    });
}

function loadPage(page) {
    if (!HOMEOWNER_ALLOWED_PAGES.has(page)) {
        page = 'dashboard';
    }

    // Update active menu item with smooth transition
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
    });
    const activeItem = document.querySelector(`[data-page="${page}"]`);
    if (activeItem) {
        activeItem.classList.add('active');
    }
    
    // Update page title with animation
    const titles = {
        'dashboard': 'Dashboard',
        'passes': 'Visitor Passes',
        'vehicles': 'My Vehicles',
        'activity': 'Vehicle Activity',
        'profile': 'My Profile'
    };
    const titleElement = document.getElementById('page-title');
    titleElement.style.opacity = '0';
    titleElement.style.transform = 'translateX(-10px)';
    
    setTimeout(() => {
        titleElement.textContent = titles[page] || page;
        titleElement.style.transition = 'all 0.3s ease';
        titleElement.style.opacity = '1';
        titleElement.style.transform = 'translateX(0)';
    }, 150);
    
    // Show/hide page content with fade effect
    document.querySelectorAll('.page-content').forEach(content => {
        content.classList.remove('active');
    });
    
    const pageContent = document.getElementById(`page-${page}`);
    if (pageContent) {
        // Small delay for smooth transition
        setTimeout(() => {
            pageContent.classList.add('active');
        }, 100);
    }
    
    currentPage = page;
    setHomeownerPageInUrl(page);
    
    // Load data if needed
    if (page === 'passes') {
        loadVisitorPasses(true);
    } else if (page === 'dashboard') {
        loadVisitorPasses(false);
    } else if (page === 'vehicles') {
        if (typeof loadVehicles === 'function') loadVehicles();
    } else if (page === 'activity') {
        if (typeof loadVehicleActivity === 'function') loadVehicleActivity();
    }
    
    // Scroll to top smoothly
    document.querySelector('.flex-1.overflow-y-auto')?.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

function getInitialHomeownerPage() {
    const params = new URLSearchParams(window.location.search);
    const pageFromUrl = (params.get('hpage') || '').trim().toLowerCase();
    if (HOMEOWNER_ALLOWED_PAGES.has(pageFromUrl)) {
        return pageFromUrl;
    }

    const pageFromStorage = (localStorage.getItem('homeownerCurrentPage') || '').trim().toLowerCase();
    if (HOMEOWNER_ALLOWED_PAGES.has(pageFromStorage)) {
        return pageFromStorage;
    }

    return 'dashboard';
}

function setHomeownerPageInUrl(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('hpage', page);
    history.replaceState({}, '', `${url.pathname}?${url.searchParams.toString()}`);
    localStorage.setItem('homeownerCurrentPage', page);
}

// Mobile Menu
function initializeMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobile-overlay');
    
    if (mobileMenuBtn && sidebar && overlay) {
        // Toggle menu
        mobileMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
        
        // Close menu when clicking overlay
        overlay.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        });
        
        // NOTE: Menu item click handlers are now consolidated in initializeNavigation()
        // to prevent duplicate event listeners and ensure preventDefault is called first
    }
}

// User Menu
function initializeUserMenu() {
    userTrigger = document.getElementById('user-trigger');
    userDropdown = document.getElementById('user-dropdown');
    const signOutBtn = document.getElementById('signOutBtn');

    function closeUserDropdown() {
        if (!userDropdown) return;
        userDropdown.classList.add('hidden');
        userDropdown.setAttribute('aria-hidden', 'true');
        userTrigger?.setAttribute('aria-expanded', 'false');
    }
    
    if (userTrigger && userDropdown) {
        userTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = userDropdown.classList.contains('hidden');
            
            if (isHidden) {
                const rect = userTrigger.getBoundingClientRect();
                userDropdown.style.bottom = `${window.innerHeight - rect.top}px`;
                userDropdown.style.left = `${rect.left}px`;
                userDropdown.classList.remove('hidden');
                userDropdown.setAttribute('aria-hidden', 'false');
                userTrigger.setAttribute('aria-expanded', 'true');
            } else {
                closeUserDropdown();
            }
        });
    }
    
    if (signOutBtn) {
        signOutBtn.addEventListener('click', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Sign Out?',
                    text: 'Are you sure you want to end your session?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Yes, sign out',
                    cancelButtonText: 'Cancel',
                    background: document.documentElement.classList.contains('dark') ? '#1e293b' : '#fff',
                    color: document.documentElement.classList.contains('dark') ? '#f1f5f9' : '#1e293b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../auth/logout.php';
                    }
                });
            } else {
                if (confirm('Are you sure you want to sign out?')) {
                    window.location.href = '../auth/logout.php';
                }
            }
        });
    }
    // Note: Click outside handling for user menu is handled in unified document click handler in initializeHomeownerNotifications()

    if (window.keyboardShortcuts && typeof window.keyboardShortcuts.register === 'function') {
        window.keyboardShortcuts.register('escape', function() {
            if (!userDropdown || userDropdown.classList.contains('hidden')) return false;
            closeUserDropdown();
            return true;
        }, {
            id: 'homeowner.usermenu.escape',
            description: 'Close homeowner user menu',
            preventDefault: false,
            allowWhileTyping: true
        });
    }
    // Note: Escape key for user menu fallback is handled in unified document keydown handler in initializeHomeownerNotifications()
}

function initializeHomeownerNotifications() {
    if (homeownerNotificationsBound) return;

    bellBtn = document.getElementById('hoNotifBellBtn');
    notificationPanel = document.getElementById('hoNotificationPanel');
    const dot = document.getElementById('hoNotifDot');
    const list = document.getElementById('hoNotificationList');
    const markAllReadBtn = document.getElementById('hoMarkAllReadBtn');
    const viewAllLink = document.getElementById('hoNotificationViewAllLink');

    if (!bellBtn || !notificationPanel || !dot || !list) return;
    homeownerNotificationsBound = true;

    const initial = window.__HOMEOWNER_NOTIF__ || {};
    homeownerNotif.pendingPasses = Number(initial.pendingPasses || 0);
    homeownerNotif.openProfileRequests = Number(initial.openProfileRequests || 0);

    const closePanel = () => {
        notificationPanel.classList.add('hidden');
        notificationPanel.classList.remove('open');
        notificationPanel.setAttribute('aria-hidden', 'true');
        bellBtn.setAttribute('aria-expanded', 'false');
    };

    const openPanel = () => {
        notificationPanel.classList.remove('hidden');
        notificationPanel.classList.add('open');
        notificationPanel.setAttribute('aria-hidden', 'false');
        bellBtn.setAttribute('aria-expanded', 'true');
    };

    bellBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (notificationPanel.classList.contains('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    markAllReadBtn?.addEventListener('click', () => {
        homeownerNotif.unread = false;
        homeownerNotif.approvedPasses = 0;
        homeownerNotif.usedPasses = 0;
        homeownerNotif.rejectedPasses = 0;
        homeownerNotif.expiredPasses = 0;
        renderHomeownerNotifications();
    });

    viewAllLink?.addEventListener('click', (e) => {
        e.preventDefault();
        if (homeownerNotif.pendingPasses > 0 || homeownerNotif.approvedPasses > 0 || homeownerNotif.usedPasses > 0) {
            loadPage('passes');
        } else if (homeownerNotif.openProfileRequests > 0) {
            loadPage('profile');
        } else {
            loadPage('dashboard');
        }
        closePanel();
    });

    renderHomeownerNotifications();

    // Consolidated document-level click handler (performance optimization)
    // Handles: user dropdown, notification panel, image zoom, action buttons
    document.addEventListener('click', (e) => {
        // Handle user dropdown - close if clicking outside
        if (userDropdown && !userDropdown.classList.contains('hidden')) {
            if (userTrigger && !userTrigger.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.add('hidden');
                userDropdown.setAttribute('aria-hidden', 'true');
                userTrigger?.setAttribute('aria-expanded', 'false');
            }
        }
        
        // Handle notification panel - close if clicking outside
        if (!notificationPanel.contains(e.target) && !bellBtn.contains(e.target)) {
            closePanel();
        }

        // Handle image zoom buttons
        const zoomButton = e.target.closest('[data-zoom-src]');
        if (zoomButton) {
            const src = zoomButton.getAttribute('data-zoom-src');
            const title = zoomButton.getAttribute('data-zoom-title') || 'Zoomed image';
            if (src) {
                viewImage(src, title);
            }
            return;
        }

        // Handle action buttons
        const actionButton = e.target.closest('[data-action]');
        if (actionButton) {
            const action = actionButton.getAttribute('data-action');
            switch (action) {
                case 'showAddVisitorPassModal':
                    showAddVisitorPassModal();
                    break;
                case 'loadPage':
                    const page = actionButton.getAttribute('data-page');
                    if (page) {
                        loadPage(page);
                    }
                    break;
                case 'showAddVehicleModal':
                    showAddVehicleModal();
                    break;
                case 'deleteVehicle':
                    const vehicleId = actionButton.getAttribute('data-vehicle-id');
                    if (vehicleId) {
                        deleteVehicle(parseInt(vehicleId));
                    }
                    break;
                case 'closeAddVehicleModal':
                    closeAddVehicleModal();
                    break;
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            // Priority 1: Close notification panel if open
            if (!notificationPanel.classList.contains('hidden')) {
                closePanel();
                e.preventDefault();
                return;
            }
            // Priority 2: Close user menu if keyboard shortcuts system not available
            if (!window.keyboardShortcuts && userDropdown && !userDropdown.classList.contains('hidden')) {
                userDropdown.classList.add('hidden');
                userDropdown.setAttribute('aria-hidden', 'true');
                userTrigger?.setAttribute('aria-expanded', 'false');
                e.preventDefault();
                return;
            }
        }
    });

    renderHomeownerNotifications();
}

function renderHomeownerNotifications() {
    const dot = document.getElementById('hoNotifDot');
    const list = document.getElementById('hoNotificationList');
    const viewAllLink = document.getElementById('hoNotificationViewAllLink');
    if (!dot || !list) return;

    const pendingPasses = Number(homeownerNotif.pendingPasses || 0);
    const openProfileRequests = Number(homeownerNotif.openProfileRequests || 0);
    const approvedPasses = Number(homeownerNotif.approvedPasses || 0);
    const usedPasses = Number(homeownerNotif.usedPasses || 0);
    const rejectedPasses = Number(homeownerNotif.rejectedPasses || 0);
    const expiredPasses = Number(homeownerNotif.expiredPasses || 0);
    const total = pendingPasses + openProfileRequests + approvedPasses + usedPasses + rejectedPasses + expiredPasses;

    dot.classList.toggle('hidden', !homeownerNotif.unread || total === 0);

    if (viewAllLink) {
        if (pendingPasses > 0 || approvedPasses > 0 || usedPasses > 0) {
            viewAllLink.textContent = 'Go to visitor passes';
        } else if (openProfileRequests > 0) {
            viewAllLink.textContent = 'Go to profile requests';
        } else {
            viewAllLink.textContent = 'View dashboard';
        }
    }

    if (total === 0) {
        list.innerHTML = '<div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">No new notifications</div>';
        return;
    }

    const items = [];
    if (pendingPasses > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${pendingPasses} visitor pass request${pendingPasses > 1 ? 's' : ''} pending</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Awaiting admin review</p>
                    </div>
                </div>
            </div>
        `);
    }

    if (approvedPasses > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${approvedPasses} visitor pass${approvedPasses > 1 ? 'es' : ''} approved</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Your visitor access is ready to use</p>
                    </div>
                </div>
            </div>
        `);
    }

    if (usedPasses > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 19c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${usedPasses} visitor pass${usedPasses > 1 ? 'es' : ''} used</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">A visitor has entered using your pass</p>
                    </div>
                </div>
            </div>
        `);
    }

    if (openProfileRequests > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${openProfileRequests} open profile request${openProfileRequests > 1 ? 's' : ''}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Check latest admin updates</p>
                    </div>
                </div>
            </div>
        `);
    }

    if (rejectedPasses > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${rejectedPasses} visitor pass${rejectedPasses > 1 ? 'es' : ''} rejected</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Review rejection details in your passes</p>
                    </div>
                </div>
            </div>
        `);
    }

    if (expiredPasses > 0) {
        items.push(`
            <div class="ta-notification-item">
                <div class="flex gap-3 items-start">
                    <svg class="h-5 w-5 flex-shrink-0 mt-0.5 text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">${expiredPasses} visitor pass${expiredPasses > 1 ? 'es' : ''} expired</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">These passes are no longer valid</p>
                    </div>
                </div>
            </div>
        `);
    }

    list.innerHTML = items.join('');
}

// Scroll Effects
function initializeScrollEffects() {
    const contentArea = document.querySelector('.flex-1.overflow-y-auto');
    const header = document.querySelector('header');
    
    if (contentArea && header) {
        contentArea.addEventListener('scroll', () => {
            if (contentArea.scrollTop > 20) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
}

// Handle page visibility (security feature)
function handleVisibilityChange() {
    if (!document.hidden) {
        // Optional: Check if session is still valid
        checkSessionValidity();
    }
}

// Check session validity
async function checkSessionValidity() {
    try {
        const result = await fetchJson('api/check_session.php');
        
        if (!result.valid) {
            Swal.fire({
                icon: 'error',
                title: 'Session Expired',
                text: 'Your session has expired. Please login again.',
                confirmButtonText: 'Login',
                confirmButtonColor: '#3b82f6',
                allowOutsideClick: false
            }).then(() => {
                window.location.href = '../auth/login.php?timeout=1';
            });
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

// Live Time
function updateLiveTime() {
    const timeElement = document.getElementById('liveTime');
    if (timeElement) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', {
            hour12: true,
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit'
        });
        timeElement.textContent = timeStr;
    }
}

// Load Visitor Passes
async function loadVisitorPasses(announceUpdates = true) {
    try {
        const result = await fetchJson('api/get_visitor_passes.php');
        
        if (!result.success) {
            throw new Error(result.message || 'Failed to load visitor passes');
        }

        visitorPasses = Array.isArray(result.passes) ? result.passes : [];
        homeownerNotif.pendingPasses = visitorPasses.filter((pass) => String(pass.display_status || '').toLowerCase() === 'pending').length;
        if (announceUpdates) {
            announceVisitorPassUpdates(visitorPasses);
        }
        displayVisitorPasses();
        renderRecentPassActivity();
        renderHomeownerNotifications();
    } catch (error) {
        console.error('Error loading visitor passes:', error);
        if (typeof showGrowl === 'function') {
            showGrowl('error', 'Unable to load visitor passes right now.');
        }
    }
}

function initializePassNotificationState() {
    try {
        const approvedRaw = localStorage.getItem('homeowner_pass_notifications_approved');
        const usedRaw = localStorage.getItem('homeowner_pass_notifications_used');
        const rejectedRaw = localStorage.getItem('homeowner_pass_notifications_rejected');
        const expiredRaw = localStorage.getItem('homeowner_pass_notifications_expired');

        if (approvedRaw) {
            passNotificationState.approvedSeen = new Set(JSON.parse(approvedRaw));
        }
        if (usedRaw) {
            passNotificationState.usedSeen = new Set(JSON.parse(usedRaw));
        }
        if (rejectedRaw) {
            passNotificationState.rejectedSeen = new Set(JSON.parse(rejectedRaw));
        }
        if (expiredRaw) {
            passNotificationState.expiredSeen = new Set(JSON.parse(expiredRaw));
        }
    } catch (error) {
        console.warn('Failed to load pass notification state:', error);
    }
}

function persistPassNotificationState() {
    try {
        localStorage.setItem('homeowner_pass_notifications_approved', JSON.stringify(Array.from(passNotificationState.approvedSeen)));
        localStorage.setItem('homeowner_pass_notifications_used', JSON.stringify(Array.from(passNotificationState.usedSeen)));
        localStorage.setItem('homeowner_pass_notifications_rejected', JSON.stringify(Array.from(passNotificationState.rejectedSeen)));
        localStorage.setItem('homeowner_pass_notifications_expired', JSON.stringify(Array.from(passNotificationState.expiredSeen)));
    } catch (error) {
        console.warn('Failed to persist pass notification state:', error);
    }
}

function announceVisitorPassUpdates(passes) {
    if (!Array.isArray(passes) || passes.length === 0) {
        return;
    }

    let approvedCount = 0;
    let usedCount = 0;
    let rejectedCount = 0;
    let expiredCount = 0;

    passes.forEach((pass) => {
        const passId = String(pass.id || '');
        if (!passId) {
            return;
        }

        const status = String(pass.display_status || '').toLowerCase();
        if ((status === 'active' || status === 'approved') && !passNotificationState.approvedSeen.has(passId)) {
            approvedCount += 1;
            passNotificationState.approvedSeen.add(passId);
        }

        if (status === 'used' && !passNotificationState.usedSeen.has(passId)) {
            usedCount += 1;
            passNotificationState.usedSeen.add(passId);
        }

        if (status === 'rejected' && !passNotificationState.rejectedSeen.has(passId)) {
            rejectedCount += 1;
            passNotificationState.rejectedSeen.add(passId);
        }

        if (status === 'expired' && !passNotificationState.expiredSeen.has(passId)) {
            expiredCount += 1;
            passNotificationState.expiredSeen.add(passId);
        }
    });

    if (approvedCount > 0) {
        homeownerNotif.approvedPasses += approvedCount;
        homeownerNotif.unread = true;
        showGrowl('success', `You have ${approvedCount} newly approved visitor pass${approvedCount > 1 ? 'es' : ''}.`);
    }
    if (usedCount > 0) {
        homeownerNotif.usedPasses += usedCount;
        homeownerNotif.unread = true;
        showGrowl('info', `A visitor pass was marked as used (${usedCount} update${usedCount > 1 ? 's' : ''}).`);
    }
    if (rejectedCount > 0) {
        homeownerNotif.rejectedPasses += rejectedCount;
        homeownerNotif.unread = true;
        showGrowl('error', `${rejectedCount} visitor pass${rejectedCount > 1 ? 'es were' : ' was'} rejected by admin.`);
    }
    if (expiredCount > 0) {
        homeownerNotif.expiredPasses += expiredCount;
        homeownerNotif.unread = true;
        showGrowl('warning', `${expiredCount} visitor pass${expiredCount > 1 ? 'es have' : ' has'} expired.`);
    }

    if (approvedCount > 0 || usedCount > 0 || rejectedCount > 0 || expiredCount > 0) {
        persistPassNotificationState();
    }

    if (approvedCount > 0 || usedCount > 0 || rejectedCount > 0 || expiredCount > 0) {
        renderHomeownerNotifications();
    }
}

// Display Visitor Passes
function displayVisitorPasses() {
    const container = document.getElementById('passes-list');
    if (!container) return;

    const filteredPasses = visitorPasses.filter((pass) => {
        const status = String(pass.display_status || '').toLowerCase();
        const matchesStatus = passFilters.status === 'all' || status === passFilters.status;

        const searchable = [
            pass.visitor_name,
            pass.purpose,
            pass.visitor_plate,
            pass.display_status
        ].join(' ').toLowerCase();
        const matchesSearch = !passFilters.search || searchable.includes(passFilters.search);

        return matchesStatus && matchesSearch;
    });

    const resultCountEl = document.getElementById('passesResultCount');
    if (resultCountEl) {
        resultCountEl.textContent = `Showing ${filteredPasses.length} of ${visitorPasses.length}`;
    }
    
    if (visitorPasses.length === 0) {
        container.innerHTML = `
            <div class="ta-empty-state">
                <div class="ta-empty-icon">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                    </svg>
                </div>
                <p class="ta-empty-title">No visitor passes yet</p>
                <p class="ta-empty-desc">Create your first visitor pass to get started.</p>
                <button onclick="showAddVisitorPassModal()" class="ta-btn ta-btn-primary mt-3">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add Visitor Pass
                </button>
            </div>
        `;
        return;
    }

    if (filteredPasses.length === 0) {
        container.innerHTML = `
            <div class="ta-empty-state">
                <div class="ta-empty-icon">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <p class="ta-empty-title">No matching passes</p>
                <p class="ta-empty-desc">Try a different keyword or status filter.</p>
                <button type="button" onclick="resetPassFilters()" class="ta-btn ta-btn-secondary mt-3">Clear Filters</button>
            </div>
        `;
        return;
    }
    
    container.innerHTML = filteredPasses.map(pass => createPassCard(pass)).join('');
}

function renderRecentPassActivity() {
    const container = document.getElementById('recentPassActivity');
    if (!container) return;

    const recentScans = visitorPasses
        .filter((pass) => Number(pass.scan_count || 0) > 0 || pass.first_scanned_at || pass.last_scanned_at)
        .sort((left, right) => {
            const leftTime = new Date(left.last_scanned_at || left.first_scanned_at || left.created_at || 0).getTime();
            const rightTime = new Date(right.last_scanned_at || right.first_scanned_at || right.created_at || 0).getTime();
            return rightTime - leftTime;
        })
        .slice(0, 5);

    if (recentScans.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-sm text-gray-500 dark:text-gray-400">
                No pass activity yet. Scans will appear here after a visitor uses an approved pass.
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <div class="space-y-3">
            ${recentScans.map((pass) => {
                const status = String(pass.display_status || '').toLowerCase();
                const count = Number(pass.scan_count || 0);
                const lastScan = pass.last_scanned_at ? formatDateTime(pass.last_scanned_at) : '';
                const statusLabel = status === 'used' ? 'Used' : status === 'active' || status === 'approved' ? 'Active' : status;
                return `
                    <div class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/50 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold text-slate-900 dark:text-white truncate">${escapeHtml(pass.visitor_name || 'Visitor')}</p>
                                <span class="ta-badge ${getPassBadgeClass(status)}">${escapeHtml(statusLabel)}</span>
                            </div>
                            <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 truncate">${escapeHtml(pass.purpose || 'Visitor pass')}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                ${count} scan${count === 1 ? '' : 's'}${lastScan ? ` · Last scan ${escapeHtml(lastScan)}` : ''}
                            </p>
                        </div>
                        <div class="shrink-0 text-right text-xs text-slate-500 dark:text-slate-400">
                            ${pass.visitor_plate ? `<div class="font-mono text-slate-700 dark:text-slate-300">${escapeHtml(pass.visitor_plate)}</div>` : ''}
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function initializePassFilters() {
    const searchInput = document.getElementById('passesSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            passFilters.search = String(this.value || '').trim().toLowerCase();
            if (currentPage === 'passes') {
                displayVisitorPasses();
            }
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                this.value = '';
                passFilters.search = '';
                if (currentPage === 'passes') {
                    displayVisitorPasses();
                }
            }
        });
    }

    const filterContainer = document.getElementById('passesStatusFilter');
    if (!filterContainer) return;

    filterContainer.querySelectorAll('[data-pass-status]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const status = String(this.dataset.passStatus || 'all').toLowerCase();
            passFilters.status = status;

            filterContainer.querySelectorAll('[data-pass-status]').forEach((node) => {
                node.classList.remove('active');
            });
            this.classList.add('active');

            if (currentPage === 'passes') {
                displayVisitorPasses();
            }
        });
    });
}

function resetPassFilters() {
    passFilters.search = '';
    passFilters.status = 'all';

    const searchInput = document.getElementById('passesSearchInput');
    if (searchInput) searchInput.value = '';

    const filterContainer = document.getElementById('passesStatusFilter');
    if (filterContainer) {
        filterContainer.querySelectorAll('[data-pass-status]').forEach((node) => {
            node.classList.toggle('active', node.dataset.passStatus === 'all');
        });
    }

    if (currentPage === 'passes') {
        displayVisitorPasses();
    }
}

// Create Pass Card
function createPassCard(pass) {
    const statusClass = pass.display_status === 'active' ? 'active' : 
                       pass.display_status === 'approved' ? 'approved' :
                       pass.display_status === 'used' ? 'approved' : '';
    const badgeClass = getPassBadgeClass(pass.display_status);
    
    const qrButton = (pass.display_status === 'active' || pass.display_status === 'approved') ? `
        <button onclick="viewQRCode('${pass.qr_token}', '${escapeHtml(pass.visitor_name)}')" 
            class="ta-btn ta-btn-primary ta-btn-sm">
            <svg class="w-4 h-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="3" width="12" height="18" rx="2"/><circle cx="12" cy="17" r="1"/></svg> View QR Code
        </button>
    ` : '';
    
    const rejectionNote = pass.display_status === 'rejected' && pass.rejection_reason ? `
        <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
            <strong class="flex items-center gap-1"><svg class="w-4 h-4 inline flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/></svg> Rejection Reason:</strong>
            <p class="mt-1">${escapeHtml(pass.rejection_reason)}</p>
        </div>
    ` : '';
    
    const pendingNote = pass.display_status === 'pending' ? `
        <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-800">
            <svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16.5 12"/></svg> Waiting for admin approval
        </div>
    ` : '';

    const usedNote = pass.display_status === 'used' ? `
        <div class="mt-3 p-2 bg-sky-50 border border-sky-200 rounded-lg text-xs text-sky-800">
            <svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-6"/></svg> Marked used ${pass.first_scanned_at ? `on ${formatDateTime(pass.first_scanned_at)}` : 'at the gate'}.
        </div>
    ` : '';

    const scanNote = Number(pass.scan_count || 0) > 0 ? `
        <div class="mt-3 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700">
            <svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18"/><path d="M3 12h18"/><path d="M3 17h18"/></svg> Scanned ${Number(pass.scan_count)} time${Number(pass.scan_count) === 1 ? '' : 's'}${pass.last_scanned_at ? ` · Last scan ${formatDateTime(pass.last_scanned_at)}` : ''}.
        </div>
    ` : '';
    
    return `
        <div class="ta-card visitor-pass-card ${statusClass}">
            <div class="flex justify-between items-start mb-3">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-3">
                        <h3 class="font-bold text-lg text-gray-900">${escapeHtml(pass.visitor_name)}</h3>
                        <span class="ta-badge ${badgeClass}">
                            ${pass.display_status}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div>
                            <span class="font-medium text-gray-500">Purpose:</span>
                            <p class="text-gray-900 mt-0.5">${escapeHtml(pass.purpose)}</p>
                        </div>
                        ${pass.visitor_plate ? `
                        <div>
                            <span class="font-medium text-gray-500">Vehicle Plate:</span>
                            <p class="text-gray-900 font-mono mt-0.5">${escapeHtml(pass.visitor_plate)}</p>
                        </div>
                        ` : ''}
                        <div>
                            <span class="font-medium text-gray-500">Valid From:</span>
                            <p class="text-gray-900 mt-0.5">${formatDateTime(pass.valid_from)}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-500">Valid Until:</span>
                            <p class="text-gray-900 mt-0.5">${formatDateTime(pass.valid_until)}</p>
                        </div>
                    </div>
                    
                    <div class="mt-2 text-xs text-gray-500">
                        Created: ${formatDateTime(pass.created_at)}
                    </div>
                </div>
                
                ${qrButton ? `<div class="ml-4">${qrButton}</div>` : ''}
            </div>
            
            ${rejectionNote}
            ${pendingNote}
            ${usedNote}
            ${scanNote}
        </div>
    `;
}

function getPassBadgeClass(status) {
    const normalized = String(status || '').toLowerCase();
    if (normalized === 'approved' || normalized === 'active') return 'success';
    if (normalized === 'pending') return 'warning';
    if (normalized === 'rejected' || normalized === 'cancelled') return 'danger';
    if (normalized === 'expired') return 'neutral';
    return 'info';
}

// Add Visitor Pass
async function showAddVisitorPassModal() {
    const defaults = getDefaultDates();
    
    const { value: formValues } = await Swal.fire({
        title: 'Add Visitor Pass',
        width: '600px',
        html: `
            <div class="text-left space-y-4" style="padding: 10px;">
                <div>
                    <label for="visitor_name" class="block text-sm font-medium text-gray-700 mb-1">Visitor Name *</label>
                    <input id="visitor_name" class="swal2-input" placeholder="e.g., Juan Dela Cruz" 
                           style="width: 100%; margin: 0;" required aria-required="true">
                </div>
                <div>
                    <label for="purpose_select" class="block text-sm font-medium text-gray-700 mb-1">Purpose *</label>
                    <select id="purpose_select" class="swal2-input" style="width: 100%; margin: 0;" aria-required="true">
                        <option value="">-- Select Purpose --</option>
                        <option value="Delivery">Delivery</option>
                        <option value="Service Provider">Service Provider</option>
                        <option value="Guest">Guest</option>
                        <option value="Contractor">Contractor</option>
                        <option value="Other">Other (Specify below)</option>
                    </select>
                    <textarea id="purpose" class="swal2-textarea" placeholder="Additional details or specify if 'Other'" 
                              style="width: 100%; margin-top: 8px; min-height: 60px;" aria-label="Purpose details"></textarea>
                </div>
                <div>
                    <label for="visitor_plate" class="block text-sm font-medium text-gray-700 mb-1">Vehicle Plate Number (Optional)</label>
                    <input id="visitor_plate" class="swal2-input" placeholder="e.g., ABC-1234" 
                           style="width: 100%; margin: 0; text-transform: uppercase;" aria-label="Vehicle plate number">
                    <small class="text-gray-500 text-xs">Leave blank if visitor has no vehicle</small>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="valid_from" class="block text-sm font-medium text-gray-700 mb-1">Valid From *</label>
                        <input id="valid_from" type="datetime-local" class="swal2-input" 
                               value="${defaults.from}" style="width: 100%; margin: 0;" required aria-required="true">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expires Automatically</label>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                            Same-day only. The pass will expire at 11:59 PM on the selected date.
                        </div>
                    </div>
                </div>
                <input id="valid_until" type="hidden" value="${defaults.until}">
                <div class="bg-blue-50 border border-blue-200 rounded p-3 text-sm text-gray-700">
                    <strong>Note:</strong> Your visitor pass will be sent to admin for approval. 
                    You'll be notified once it's processed.
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Submit Request',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        didOpen: () => {
            const plateInput = document.getElementById('visitor_plate');
            plateInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });

            const validFromInput = document.getElementById('valid_from');
            const validUntilInput = document.getElementById('valid_until');
            const syncValidUntil = () => {
                if (!validFromInput || !validUntilInput || !validFromInput.value) return;
                const expiry = new Date(validFromInput.value);
                if (Number.isNaN(expiry.getTime())) return;
                expiry.setHours(23, 59, 59, 0);
                const pad = (n) => String(n).padStart(2, '0');
                validUntilInput.value = `${expiry.getFullYear()}-${pad(expiry.getMonth() + 1)}-${pad(expiry.getDate())}T${pad(expiry.getHours())}:${pad(expiry.getMinutes())}`;
            };

            if (validFromInput && validUntilInput) {
                validFromInput.addEventListener('input', syncValidUntil);
                validFromInput.addEventListener('change', syncValidUntil);
                syncValidUntil();
            }
            
            const purposeSelect = document.getElementById('purpose_select');
            const purposeText = document.getElementById('purpose');
            purposeSelect.addEventListener('change', (e) => {
                if (e.target.value && e.target.value !== 'Other') {
                    purposeText.value = e.target.value;
                } else if (e.target.value === 'Other') {
                    purposeText.value = '';
                    purposeText.focus();
                }
            });
        },
        preConfirm: () => {
            const visitor_name = document.getElementById('visitor_name').value.trim();
            const purpose_select = document.getElementById('purpose_select').value;
            const purpose_text = document.getElementById('purpose').value.trim();
            const visitor_plate = document.getElementById('visitor_plate').value.trim().toUpperCase();
            const valid_from = document.getElementById('valid_from').value;
            const valid_until = document.getElementById('valid_until').value;

            // Basic validation (backend will do comprehensive validation)
            if (!visitor_name) {
                Swal.showValidationMessage('Visitor name is required');
                return false;
            }
            
            if (visitor_name.length < 2) {
                Swal.showValidationMessage('Visitor name must be at least 2 characters');
                return false;
            }
            
            const purpose = purpose_text || purpose_select;
            if (!purpose) {
                Swal.showValidationMessage('Please select or specify a purpose');
                return false;
            }
            
            if (!valid_from || !valid_until) {
                Swal.showValidationMessage('Please select a start date');
                return false;
            }
            
            const fromDate = new Date(valid_from);
            const untilDate = new Date(valid_until);
            
            if (untilDate <= fromDate) {
                Swal.showValidationMessage('The pass must end on the same day it starts');
                return false;
            }
            
            // Check minimum duration
            const durationMinutes = (untilDate - fromDate) / (1000 * 60);
            if (durationMinutes < 30) {
                Swal.showValidationMessage('Visit duration must be at least 30 minutes');
                return false;
            }

            const sameDay = fromDate.toDateString() === untilDate.toDateString();
            if (!sameDay) {
                Swal.showValidationMessage('Visitor passes are valid for one day only');
                return false;
            }

            return { visitor_name, purpose, visitor_plate, valid_from, valid_until };
        }
    });

    if (formValues) {
        await submitVisitorPass(formValues);
    }
}

// Submit Visitor Pass
async function submitVisitorPass(formData) {
    try {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.content : '';
        if (!csrfToken) {
            throw new Error('Security token missing. Please refresh the page and try again.');
        }

        const response = await fetch('api/create_visitor_pass.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                ...formData,
                csrf_token: csrfToken
            })
        });

        const result = await response.json();

        if (result.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Visitor Pass Created!',
                html: `
                    <div class="text-left">
                        <p class="mb-3">Your visitor pass request has been submitted successfully.</p>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-gray-700">
                            <strong><svg style="width:0.85em;height:0.85em;vertical-align:-0.1em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16.5 12"/></svg> Status:</strong> Pending Admin Approval<br>
                            <span class="text-xs text-gray-600 mt-1 block">You will be notified once the admin reviews your request.</span>
                                    <span class="text-xs text-gray-600 mt-1 block">The pass will automatically expire at the end of the selected day.</span>
                        </div>
                    </div>
                `,
                confirmButtonText: 'OK, Got it!',
                confirmButtonColor: '#3b82f6'
            });
            loadVisitorPasses();
        } else {
            if (response.status === 429) {
                throw new Error(result.message || 'You have reached the visitor pass request limit. Please wait before trying again.');
            }
            throw new Error(result.message || 'Failed to create visitor pass');
        }
    } catch (error) {
        const isCooldown = /wait|limit|too many|cooldown/i.test(error.message || '');
        Swal.fire({
            icon: isCooldown ? 'warning' : 'error',
            title: isCooldown ? 'Request Cooldown' : 'Error Creating Pass',
            text: error.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

// View QR Code
function viewQRCode(token, visitorName) {
    const qrUrl = `../visitor/view_pass.php?token=${token}`;
    
    // Find the pass with this token to get the QR code
    const pass = visitorPasses.find(p => p.qr_token === token);
    const qrCodeSrc = pass && pass.qr_code ? pass.qr_code : pass.qr_code || 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    
    Swal.fire({
        title: 'Visitor Pass QR Code',
        html: `
            <div class="text-center">
                <p class="mb-4 text-gray-700">Share this QR code or link with <strong>${visitorName}</strong></p>
                <div class="mb-4 p-4 bg-white border-2 border-gray-300 rounded-lg inline-block" style="position:relative;">
                    <img src="${qrCodeSrc}" alt="QR Code" style="width: 200px; height: 200px; image-rendering: pixelated;">
                    <img src="../assets/images/ville_de_palme.png" alt="Logo" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:36px; height:36px; object-fit:contain; border-radius:50%; background:white; padding:2px; box-shadow:0 2px 6px rgba(0,0,0,0.15); pointer-events:none;">
                </div>
                <div class="mt-4 p-3 bg-gray-50 rounded-lg text-left">
                    <p class="text-xs font-medium text-gray-600 mb-2">Share Link:</p>
                    <div class="flex gap-2">
                        <input type="text" value="${qrUrl}" readonly 
                               class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded bg-white font-mono">
                        <button onclick="copyToClipboard('${qrUrl}')" 
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs font-medium">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        `,
        width: '500px',
        showConfirmButton: true,
        confirmButtonText: 'Close',
        confirmButtonColor: '#6b7280'
    });
}

// Copy to Clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Link copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

// Utility Functions
function getDefaultDates() {
    const now = new Date();
    // Round to next 5 minutes
    const minutes = Math.ceil(now.getMinutes() / 5) * 5;
    now.setMinutes(minutes);
    now.setSeconds(0);
    now.setMilliseconds(0);
    
    const later = new Date(now.getTime());
    later.setHours(23, 59, 59, 0);
    
    const formatDateTime = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    };
    
    return {
        from: formatDateTime(now),
        until: formatDateTime(later)
    };
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function escapeHtml(value) {
    if (window.VehiScanUtils && typeof window.VehiScanUtils.escapeHtml === 'function') {
        return window.VehiScanUtils.escapeHtml(value);
    }

    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Image Zoom Functionality
window.viewImage = function (src, title) {
    const modal = document.getElementById('imageZoomModal');
    const img = document.getElementById('zoomedImage');
    if (modal && img && src) {
        img.src = src;
        img.alt = title || 'Zoomed image';
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }
};

window.closeImageZoom = function () {
    const modal = document.getElementById('imageZoomModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }
};

// Add ESC key handler for image zoom
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageZoom();
    }
});
