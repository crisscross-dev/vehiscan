/* admin/admin_panel.js - COMPLETE VERSION WITH SHADCN SIDEBAR */

const ADMIN_PANEL_DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
const adminPanelLog = (...args) => { if (ADMIN_PANEL_DEBUG) console.log(...args); };

// SweetAlert2 Fallback - Must be defined before DOMContentLoaded
if (typeof Swal === 'undefined') {
  console.warn('[ADMIN] SweetAlert2 not loaded, using fallback alert/confirm');
  window.Swal = {
    fire: function (options) {
      const isConfirm = options.showCancelButton || options.showConfirmButton !== false;
      const message = options.html || options.text || options.title || '';
      if (isConfirm) {
        return Promise.resolve({ isConfirmed: confirm(message), value: true });
      } else {
        alert(options.icon ? options.icon.toUpperCase() + ': ' + message : message);
        return Promise.resolve({ isConfirmed: true });
      }
    },
    mixin: function () { return this; },
    stopTimer: function () { },
    resumeTimer: function () { },
    showValidationError: function (msg) { alert('Validation Error: ' + msg); },
    showValidationMessage: function (msg) { alert('Validation: ' + msg); }
  };
} else {
  adminPanelLog('[ADMIN] SweetAlert2 loaded successfully');
}

document.addEventListener("DOMContentLoaded", () => {
  adminPanelLog('[ADMIN] DOMContentLoaded fired');

  const contentArea = document.getElementById("content-area");
  const menuLinks = document.querySelectorAll(".menu-item[data-page]");
  const liveTime = document.getElementById("liveTime");
  const signOutBtn = document.getElementById("signOutBtn");
  const backupBtn = document.getElementById("backupBtn");
  const modalEl = document.getElementById("editModal");
  const modalBody = document.getElementById("modal-body");
  const pageTitle = document.getElementById("page-title");
  const brandLogoImg = document.getElementById('brand-logo-img');
  const brandLogoFallback = document.getElementById('brand-logo-fallback');
  const notificationBellBtn = document.getElementById('notificationBellBtn');
  const notificationPanel = document.getElementById('notificationPanel');
  const notificationViewAllLink = document.getElementById('notificationViewAllLink');
  const editModalBackdrop = document.getElementById('editModalBackdrop');
  const editModalCloseBtn = document.getElementById('editModalCloseBtn');
  const allowedAdminPages = new Set(['dashboard', 'manage', 'logs', 'audit', 'rfid', 'visitors', 'visitor_logs', 'employees', 'profile_requests', 'approvals']);
  
  // Hardware RFID scanner wedge listener (global)
  let rfidBuffer = '';
  let rfidTimeoutToken = null;
  const rfidScannerTimeout = 100; // ms
  const rfidMinLength = 8;
  let isRfidProcessing = false;
  let bindRealtimeRefreshTimer = null;
  let csrf = window.__ADMIN_CSRF__; // updated from X-CSRF-Token response header
  let currentPage = "dashboard"; // Track current page for reload after form submit

  function getLogsStateFromUrl() {
    const params = new URLSearchParams(window.location.search);
    return {
      plate: (params.get('logs_plate') || '').trim(),
      perPage: (params.get('logs_per_page') || '').trim(),
      page: Math.max(1, parseInt(params.get('logs_page') || '1', 10) || 1)
    };
  }

  function setLogsStateInUrl(state) {
    const url = new URL(window.location.href);
    const params = url.searchParams;

    params.set('apage', 'logs');

    if (state.plate) params.set('logs_plate', state.plate);
    else params.delete('logs_plate');

    params.delete('logs_date_from');
    params.delete('logs_date_to');

    if (state.perPage) params.set('logs_per_page', state.perPage);
    else params.delete('logs_per_page');

    if (state.page && Number(state.page) > 1) params.set('logs_page', String(state.page));
    else params.delete('logs_page');

    history.replaceState({}, '', `${url.pathname}?${params.toString()}`);
  }

  function setActivePageInUrl(page) {
    if (!allowedAdminPages.has(page)) return;
    const url = new URL(window.location.href);
    url.searchParams.set('apage', page);
    history.replaceState({}, '', `${url.pathname}?${url.searchParams.toString()}`);
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

  async function openImagePreviewModal(src, title = 'Image Preview', caption = '') {
    if (!src) return;

    const safeTitle = escapeHtml(title);
    const safeCaption = escapeHtml(caption);
    const safeSrc = escapeHtml(String(src));

    if (typeof Swal === 'undefined' || !Swal.fire) {
      window.open(safeSrc, '_blank', 'noopener,noreferrer');
      return;
    }

    await Swal.fire({
      title: safeTitle,
      html: `
        <div class="text-left">
          <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900">
            <img src="${safeSrc}" alt="${safeTitle}" class="w-full max-h-[70vh] object-contain bg-black" />
          </div>
          ${safeCaption ? `<p class="mt-3 text-sm text-slate-500 dark:text-slate-400">${safeCaption}</p>` : ''}
        </div>
      `,
      showConfirmButton: false,
      showCloseButton: true,
      width: 'min(92vw, 980px)',
      background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff'
    });
  }

  function setButtonLoading(btn, loadingText) {
    if (!btn) return () => {};
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('is-loading');
    if (loadingText) btn.innerHTML = loadingText;

    return function restore() {
      btn.disabled = false;
      btn.classList.remove('is-loading');
      btn.innerHTML = originalHtml;
    };
  }

  function triggerBindRealtimeRefresh(targetPage) {
    if (bindRealtimeRefreshTimer) {
      clearTimeout(bindRealtimeRefreshTimer);
      bindRealtimeRefreshTimer = null;
    }

    bindRealtimeRefreshTimer = setTimeout(() => {
      loadPage(targetPage);
      bindRealtimeRefreshTimer = null;
    }, 180);
  }

  async function showCountdownPopup(title, html, icon = 'info', durationMs = 5000) {
    let countdownTimer = null;
    const countdownId = `rfid-countdown-${Date.now()}`;
    await Swal.fire({
      title,
      html: `${html}<p class="text-xs text-gray-500 mt-3">Auto closing in <strong id="${countdownId}">${Math.ceil(durationMs / 1000)}</strong>s...</p>`,
      icon,
      timer: durationMs,
      timerProgressBar: true,
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      didOpen: () => {
        const el = document.getElementById(countdownId);
        if (!el) return;
        countdownTimer = setInterval(() => {
          const left = Swal.getTimerLeft();
          if (left === undefined || left === null) return;
          el.textContent = String(Math.max(0, Math.ceil(left / 1000)));
        }, 200);
      },
      willClose: () => {
        if (countdownTimer) {
          clearInterval(countdownTimer);
          countdownTimer = null;
        }
      }
    });
  }

  function formatRealtimeBoundDate() {
    const d = new Date();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const hour24 = d.getHours();
    const hour12 = ((hour24 + 11) % 12) + 1;
    const mins = String(d.getMinutes()).padStart(2, '0');
    const ampm = hour24 >= 12 ? 'pm' : 'am';
    return `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()} ${hour12}:${mins}${ampm}`;
  }

  function applyRealtimeRfidBindingUi(plateNumber, rfidUid) {
    const normalizedPlate = String(plateNumber || '').trim().toUpperCase();
    const uid = String(rfidUid || '').trim();
    if (!normalizedPlate || !uid) return false;

    let updated = false;

    // RFID management page row update.
    const rfidBindButtons = document.querySelectorAll('.btn-bind-rfid');
    rfidBindButtons.forEach((btn) => {
      const btnPlate = String(btn.dataset.plate || '').trim().toUpperCase();
      if (btnPlate !== normalizedPlate) return;

      const row = btn.closest('tr');
      if (!row) return;

      const statusCell = row.querySelector('td:nth-child(4)');
      const uidCell = row.querySelector('td:nth-child(5)');
      const boundAtCell = row.querySelector('td:nth-child(6)');

      if (statusCell) {
        statusCell.innerHTML = '<span class="ta-badge success">Bound</span>';
      }
      if (uidCell) {
        uidCell.innerHTML = escapeHtml(uid);
      }
      if (boundAtCell) {
        boundAtCell.innerHTML = formatRealtimeBoundDate();
      }

      btn.disabled = true;
      btn.classList.remove('ta-btn-primary');
      btn.classList.add('ta-btn-secondary');
      btn.textContent = 'Bound';
      updated = true;
    });

    // Manage records page row update.
    const manageBindButtons = document.querySelectorAll('.btn-bind-rfid-manage');
    manageBindButtons.forEach((btn) => {
      const btnPlate = String(btn.dataset.plate || '').trim().toUpperCase();
      if (btnPlate !== normalizedPlate) return;

      const col = btn.closest('.rfid-col-container');
      if (!col) return;

      col.innerHTML = `
        <div class="rfid-badge-bound" title="UID: ${escapeHtml(uid)}">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
          </svg>
          Bound
        </div>
      `;

      updated = true;
    });

    return updated;
  }

  adminPanelLog('[ADMIN] Elements found:', {
    contentArea: !!contentArea,
    menuLinks: menuLinks.length,
    pageTitle: !!pageTitle,
    signOutBtn: !!signOutBtn,
    backupBtn: !!backupBtn
  });

  // === SIDEBAR TOGGLE ===
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebar-toggle');
  const sidebarTexts = document.querySelectorAll('.sidebar-text');
  let sidebarOpen = localStorage.getItem('sidebarOpen') !== 'false'; // Default true

  adminPanelLog('[SIDEBAR] Sidebar elements:', {
    sidebar: !!sidebar,
    sidebarToggle: !!sidebarToggle,
    sidebarTexts: sidebarTexts.length,
    sidebarOpen: sidebarOpen
  });

  // Initialize sidebar state
  if (sidebar) {
    if (!sidebarOpen) {
      sidebar.classList.remove('sidebar-open');
      sidebar.classList.add('sidebar-closed');
    }
    adminPanelLog('[SIDEBAR] Initial classes:', sidebar.className);
  }

  const hamburgerIcon = document.getElementById('hamburger-icon');

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', (e) => {
      adminPanelLog('[SIDEBAR] Toggle clicked!');
      e.preventDefault();
      e.stopPropagation();

      sidebarOpen = !sidebarOpen;
      localStorage.setItem('sidebarOpen', sidebarOpen);

      if (sidebarOpen) {
        sidebar.classList.remove('sidebar-closed');
        sidebar.classList.add('sidebar-open');
        if (hamburgerIcon) hamburgerIcon.style.transform = 'rotate(0deg)';
        adminPanelLog('[SIDEBAR] Opened - classes:', sidebar.className);
      } else {
        sidebar.classList.remove('sidebar-open');
        sidebar.classList.add('sidebar-closed');
        if (hamburgerIcon) hamburgerIcon.style.transform = 'rotate(90deg)';
        adminPanelLog('[SIDEBAR] Closed - classes:', sidebar.className);
      }
    });
    adminPanelLog('[SIDEBAR] Toggle listener attached');
  } else {
    console.error('[SIDEBAR] Toggle button not found!');
  }

  const sidebarGroupButtons = document.querySelectorAll('[data-sidebar-group]');
  const sidebarGroupPanels = document.querySelectorAll('[data-sidebar-group-panel]');

  function sidebarHasAlpineState() {
    return !!document.querySelector('[x-data]');
  }

  function setSidebarPanelState(panel, isOpen) {
    if (!panel) return;
    panel.style.display = isOpen ? 'block' : 'none';
    panel.setAttribute('aria-hidden', String(!isOpen));
  }

  function initSidebarGroupFallback() {
    if (sidebarGroupButtons.length === 0 || sidebarGroupPanels.length === 0) return;
    // Improved Alpine detection: if Alpine is loaded, it will handle the sidebar.
    // If Alpine is present but not yet initialized, it will set x-data soon.
    if (window.Alpine) {
      adminPanelLog('[SIDEBAR] Alpine detected, waiting for initialization');
      return;
    }

    adminPanelLog('[SIDEBAR] Alpine not detected or not initialized, applying fallback to sidebar groups');
    const defaultOpenGroup = 'main';

    sidebarGroupButtons.forEach((btn) => {
      const group = btn.dataset.sidebarGroup;
      const panel = document.querySelector(`[data-sidebar-group-panel="${group}"]`);
      const icon = btn.querySelector('svg');
      const isOpenByDefault = group === defaultOpenGroup;

      setSidebarPanelState(panel, isOpenByDefault);
      btn.setAttribute('aria-expanded', String(isOpenByDefault));
      if (icon) {
        icon.style.transform = isOpenByDefault ? 'rotate(180deg)' : 'rotate(0deg)';
      }

      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const openState = panel.style.display === 'none';
        setSidebarPanelState(panel, openState);
        btn.setAttribute('aria-expanded', String(openState));
        if (icon) icon.style.transform = openState ? 'rotate(180deg)' : 'rotate(0deg)';
      });
    });
  }

  // Small delay to allow Alpine.js to initialize first if it's present
  setTimeout(initSidebarGroupFallback, 50);

  /* ---------- Mobile Menu Toggle ---------- */
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mobileOverlay = document.getElementById('mobile-overlay');

  if (mobileMenuBtn && mobileOverlay && sidebar) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      sidebar.classList.toggle('mobile-open');
      mobileOverlay.classList.toggle('active');
      adminPanelLog('[MOBILE] Menu toggled');
    });

    mobileOverlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      mobileOverlay.classList.remove('active');
      adminPanelLog('[MOBILE] Overlay clicked - menu closed');
    });

    // Close mobile menu when a menu item is clicked
    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove('mobile-open');
          mobileOverlay.classList.remove('active');
        }
      });
    });
  }

  /* ---------- User Dropdown ---------- */
  const userTrigger = document.getElementById('user-trigger');
  const userDropdown = document.getElementById('user-dropdown');
  const userChevron = document.getElementById('user-chevron');
  function closeUserDropdown() {
    if (!userDropdown) return;
    userDropdown.style.display = 'none';
    userDropdown.setAttribute('aria-hidden', 'true');
    userTrigger?.setAttribute('aria-expanded', 'false');
    if (userChevron) userChevron.style.transform = 'rotate(180deg)';
  }

  function closeNotificationPanel() {
    if (!notificationPanel) return;
    notificationPanel.classList.add('hidden');
    notificationPanel.classList.remove('open');
    notificationPanel.setAttribute('aria-hidden', 'true');
    notificationBellBtn?.setAttribute('aria-expanded', 'false');
  }

  // Expose a narrow helper so other admin shell handlers can close shell popovers safely.
  window.__vsAdminCloseShellPopovers = function() {
    closeUserDropdown();
    closeNotificationPanel();
  };

  function closeActionDropdowns() {
    if (typeof window.__vsAdminCloseActionDropdowns === 'function') {
      window.__vsAdminCloseActionDropdowns();
      return;
    }

    document.querySelectorAll('.ta-action-dropdown.open').forEach((dropdown) => {
      dropdown.classList.remove('open');
      const trigger = dropdown.querySelector('.ta-action-btn');
      const menu = dropdown.querySelector('.ta-action-menu');
      if (menu) {
        menu.removeAttribute('style');
        menu.setAttribute('aria-hidden', 'true');
      }
      if (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  adminPanelLog('[USER-DROPDOWN] Elements found:', {
    trigger: !!userTrigger,
    dropdown: !!userDropdown,
    chevron: !!userChevron
  });

  // Position dropdown dynamically
  function positionDropdown() {
    if (!userTrigger || !userDropdown) {
      adminPanelLog('[USER-DROPDOWN] Missing elements, cannot position');
      return;
    }

    const triggerRect = userTrigger.getBoundingClientRect();
    const sidebar = document.getElementById('sidebar');
    const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
    const gap = 8; // 0.5rem gap

    // Position relative to sidebar width when it's visible
    const left = sidebarRect ? sidebarRect.left : triggerRect.left;
    const bottom = window.innerHeight - triggerRect.top + gap;
    const width = sidebarRect ? sidebarRect.width : triggerRect.width;

    adminPanelLog('[USER-DROPDOWN] Positioning:', {
      left,
      bottom,
      width,
      sidebarWidth: sidebarRect?.width,
      triggerLeft: triggerRect.left
    });

    // Use bottom positioning and align with sidebar
    userDropdown.style.left = `${left}px`;
    userDropdown.style.bottom = `${bottom}px`;
    userDropdown.style.top = 'auto';
    userDropdown.style.width = `${width}px`;
    userDropdown.style.display = 'block';
  }

  userTrigger?.addEventListener('click', (e) => {
    adminPanelLog('[USER-DROPDOWN] Trigger clicked!');
    e.stopPropagation();
    const isHidden = userDropdown.style.display === 'none' || userDropdown.style.display === '';

    adminPanelLog('[USER-DROPDOWN] Current state:', { isHidden, display: userDropdown.style.display });

    if (isHidden) {
      closeActionDropdowns();
      closeNotificationPanel();
      positionDropdown();
      userDropdown.style.display = 'block';
      userDropdown.setAttribute('aria-hidden', 'false');
      userTrigger.setAttribute('aria-expanded', 'true');
      adminPanelLog('[USER-DROPDOWN] Dropdown opened');
    } else {
      closeUserDropdown();
      adminPanelLog('[USER-DROPDOWN] Dropdown closed');
    }

    if (userChevron) {
      // When closed: point up (180deg), When open: point down (0deg)
      userChevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
    }
  });

  /* ---------- Notification Panel ---------- */
  if (notificationBellBtn && notificationPanel && notificationBellBtn.dataset.vsNotifBound !== '1') {
    notificationBellBtn.dataset.vsNotifBound = '1';
    notificationBellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = notificationPanel.classList.contains('hidden');
      if (willOpen) {
        closeActionDropdowns();
        closeUserDropdown();
        notificationPanel.classList.remove('hidden');
        notificationPanel.classList.add('open');
        notificationPanel.setAttribute('aria-hidden', 'false');
        notificationBellBtn.setAttribute('aria-expanded', 'true');
      } else {
        closeNotificationPanel();
      }
    });
  }

  // Close popovers when clicking outside of them.
  document.addEventListener('click', (e) => {
    const clickedUserTrigger = userTrigger?.contains(e.target);
    const clickedUserDropdown = userDropdown?.contains(e.target);
    const clickedNotificationBell = notificationBellBtn?.contains(e.target);
    const clickedNotificationPanel = notificationPanel?.contains(e.target);

    if (userDropdown?.style.display === 'block' && !clickedUserTrigger && !clickedUserDropdown) {
      closeUserDropdown();
    }

    if (notificationPanel && !notificationPanel.classList.contains('hidden') && !clickedNotificationBell && !clickedNotificationPanel) {
      closeNotificationPanel();
    }
  });

  // Reposition on window resize
  window.addEventListener('resize', () => {
    if (userDropdown?.style.display === 'block') {
      positionDropdown();
    }
  });

  if (notificationViewAllLink) {
    notificationViewAllLink.addEventListener('click', (e) => {
      e.preventDefault();
      if (typeof window.loadPage === 'function') {
        window.loadPage('logs');
      }
      closeNotificationPanel();
    });
  }

  /* ---------- Modal Close Triggers ---------- */
  if (editModalBackdrop) {
    editModalBackdrop.addEventListener('click', () => {
      if (typeof window.closeModal === 'function') {
        window.closeModal();
      }
    });
  }

  if (editModalCloseBtn) {
    editModalCloseBtn.addEventListener('click', () => {
      if (typeof window.closeModal === 'function') {
        window.closeModal();
      }
    });
  }

  /* ---------- Brand Logo Fallback ---------- */
  if (brandLogoImg && brandLogoFallback) {
    const showFallbackLogo = () => {
      brandLogoImg.style.display = 'none';
      brandLogoFallback.style.display = 'flex';
    };

    brandLogoImg.addEventListener('error', showFallbackLogo);

    // Handle cached-but-broken image states.
    if (brandLogoImg.complete && brandLogoImg.naturalWidth === 0) {
      showFallbackLogo();
    }
  }

  /* ---------- Global Session Expiration Handler ---------- */
  if (!window.__fetchPatched) {
    window.__fetchPatched = true;
    const originalFetch = window.fetch;
    window.fetch = async function (...args) {
      const response = await originalFetch.apply(this, args);

    // Auto-refresh CSRF token from response header.
    // After session rebuild (GC, timeout, CASE 2 resolution),
    // the server generates a new CSRF token and sends it via header.
    const newCsrf = response.headers.get('X-CSRF-Token');
    if (newCsrf && newCsrf !== csrf) {
      adminPanelLog('[SESSION] CSRF token refreshed from server');
      csrf = newCsrf;
      window.__ADMIN_CSRF__ = newCsrf;
    }

    // Check for session expiration on any AJAX request
    if (response.status === 403) {
      try {
        const clone = response.clone();
        const json = await clone.json();
        if (json.error === 'Session expired' && json.redirect) {
          await Swal.fire({
            title: 'Session Expired',
            text: 'Your session has expired. Please login again.',
            icon: 'warning',
            confirmButtonText: 'Login',
            allowOutsideClick: false,
            heightAuto: false
          });
          window.location.href = json.redirect;
          return response;
        }
      } catch (e) {
        // Not JSON or other error, continue with original response
      }
    }

    return response;
  };
  } // end __fetchPatched guard

  /* ---------- Toast Notifications (replacing Growl) ---------- */
  function showGrowl(msg, type = "success") {
    // Prefer shared toast utility to keep queueing and behavior consistent across modules.
    if (window.toast && typeof window.toast.show === 'function') {
      window.toast.show(msg, type, 3000);
      return;
    }

    const icon = (type === 'error' || type === 'warning' || type === 'info') ? type : 'success';
    Swal.fire({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      icon,
      title: msg
    });
  }

  /* ---------- Live Clock ---------- */
  function updateTime() {
    const now = new Date();
    if (liveTime)
      liveTime.textContent = now.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      });
  }
  updateTime();
  setInterval(updateTime, 1000);

  /* ---------- Navigation ---------- */
  adminPanelLog('[NAV] Setting up navigation for', menuLinks.length, 'menu items');

  menuLinks.forEach((link, index) => {
    adminPanelLog(`[NAV] Menu item ${index}:`, {
      text: link.textContent.trim(),
      page: link.dataset.page,
      href: link.href
    });

    link.addEventListener("click", (e) => {
      adminPanelLog('[NAV] Menu clicked:', link.dataset.page);
      e.preventDefault();
      e.stopPropagation();

      if (typeof window.closeModal === 'function') {
        window.closeModal();
      }

      const page = link.dataset.page;

      if (!page) {
        console.error('[NAV] No data-page attribute found!');
        return;
      }

      // Update active state
      menuLinks.forEach((l) => l.classList.remove("active"));
      link.classList.add("active");
      adminPanelLog('[NAV] Active class set on:', page);

      // Update page title
      const pageNames = {
        'dashboard': 'Dashboard',
        'manage': 'Manage Records',
        'logs': 'Access Logs',
        'audit': 'Audit Logs',
        'rfid': 'RFID Management',
        'visitors': 'Visitor Passes',
        'employees': 'Employee Management',
        'profile_requests': 'Profile Requests',
        'approvals': 'Account Approvals'
      };
      if (pageTitle && pageNames[page]) {
        pageTitle.textContent = pageNames[page];
        adminPanelLog('[NAV] Page title updated to:', pageNames[page]);
      }

      loadPage(page);
    });
  });

  const useSharedKeyboardShortcuts = !!(window.keyboardShortcuts && typeof window.keyboardShortcuts.register === 'function');
  if (useSharedKeyboardShortcuts) {
    window.keyboardShortcuts.register('escape', () => {
      let handled = false;

      if (userDropdown?.style.display === 'block') {
        closeUserDropdown();
        handled = true;
      }
      if (notificationPanel && !notificationPanel.classList.contains('hidden')) {
        closeNotificationPanel();
        handled = true;
      }

      return handled;
    }, {
      id: 'admin.popovers.escape',
      description: 'Close admin user/notification popovers',
      preventDefault: false,
      allowWhileTyping: true
    });
  }

  function cleanupRfidScanFromInput(capturedUid) {
    const activeEl = document.activeElement;
    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
      const val = activeEl.value;
      if (val.endsWith(capturedUid)) {
        activeEl.value = val.substring(0, val.length - capturedUid.length);
      }
    }
  }

  document.addEventListener('keydown', function(e) {
      const targetEl = e.target;
      const isEditableTarget = !!(targetEl && (
      targetEl.isContentEditable ||
      ['INPUT', 'TEXTAREA', 'SELECT'].includes(targetEl.tagName)
      ));

      if (isEditableTarget && !window.rfidBindingSessionActive) {
        rfidBuffer = '';
        if (rfidTimeoutToken) {
          clearTimeout(rfidTimeoutToken);
          rfidTimeoutToken = null;
        }
        return;
      }

      if (e.key === 'Enter' && window.rfidBindingSessionActive) {
          e.preventDefault();
          e.stopPropagation();
          adminPanelLog('[RFID_MGMT] Intercepted hardware Enter keystroke during binding.');
          return;
      }

      if (!useSharedKeyboardShortcuts && e.key === 'Escape') {
        if (userDropdown?.style.display === 'block') {
          closeUserDropdown();
        }
        if (notificationPanel && !notificationPanel.classList.contains('hidden')) {
          closeNotificationPanel();
        }
      }

      // 1. Buffering Logic
      if (e.key.length === 1 || e.key === 'Enter') {
          if (rfidTimeoutToken) clearTimeout(rfidTimeoutToken);

          if (e.key === 'Enter') {
              // Potential end of scan
              if (rfidBuffer.length >= rfidMinLength) {
                  const capturedUid = rfidBuffer.trim();
                  rfidBuffer = '';
                  
                  e.preventDefault();
                  e.stopPropagation();

                  // Cleanup focused input if hardware scan occurred
                    cleanupRfidScanFromInput(capturedUid);

                  handleGlobalRfidScan(capturedUid);
              } else {
                  // It was just a regular Enter key, reset buffer
                  rfidBuffer = '';
                  
                  // If a binding session is active, we MUST prevent standard Enter behavior 
                  // (like clicking the 'Cancel' button in the modal)
                  if (window.rfidBindingSessionActive) {
                      e.preventDefault();
                      e.stopPropagation();
                      adminPanelLog('[RFID] Intercepted Enter during binding session');
                  }
              }
              return;
          }

          // Normal character - add to buffer
          rfidBuffer += e.key;

            rfidTimeoutToken = setTimeout(() => {
              rfidTimeoutToken = null;
              if (rfidBuffer.length >= rfidMinLength) {
                  const capturedUid = rfidBuffer.trim();
                  rfidBuffer = '';
                  
                  // Cleanup focused input if hardware scan occurred without Enter
                    cleanupRfidScanFromInput(capturedUid);
                  
                  handleGlobalRfidScan(capturedUid);
              } else {
                  rfidBuffer = '';
              }
          }, rfidScannerTimeout);
      }
  }, true); // Using capture phase to ensure we catch it before specific element listeners

  // Logic for handling the captured RFID UID
  async function handleGlobalRfidScan(uid) {
      if (isRfidProcessing) {
        adminPanelLog('[RFID] Scan ignored while previous scan is still processing');
        return;
      }
      isRfidProcessing = true;
      adminPanelLog('[RFID] Processing hardware scan:', uid);
      
      // If we are in a binding session, just show a notification and PROCEED to the fetch.
      // Many readers act as keyboard wedges; we MUST send the UID to the server to complete the bind.
      if (window.rfidBindingSessionActive) {
          adminPanelLog('[RFID] Binding session active, forwarding scan to server...');
          if (typeof showGrowl === 'function') showGrowl('Scan captured, processing bind...', 'info');
      }

      // General scan logic for Admin Panel
      try {
          await new Promise((resolve) => setTimeout(resolve, 1000));

          const formData = new URLSearchParams();
          formData.append('rfid_uid', uid);
          formData.append('csrf_token', window.__ADMIN_CSRF__ || '');
          formData.append('session_type', 'admin');

          const response = await fetch('../api/rfid/scan.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              credentials: 'same-origin',
              body: formData.toString()
          });

          const data = await response.json();
          if (data.success) {
              // Suppress generic success if we are binding (modal handles it)
              if (!window.rfidBindingSessionActive && typeof showGrowl === 'function') {
                  showGrowl(`Scan Successful: ${data.message}`, 'success');
                await showCountdownPopup('RFID Scan Processed', `<p>${escapeHtml(data.message || 'Scan processed successfully.')}</p>`, 'success', 5000);
              }
                if (data.scan_result === 'uid_bound' && (currentPage === 'rfid' || currentPage === 'manage')) {
                    applyRealtimeRfidBindingUi(data.data?.plate_number || '', data.data?.rfid_uid || '');
                    triggerBindRealtimeRefresh(currentPage);
              }
          } else {
              // Suppress generic error if we are binding (modal handles it)
              if (!window.rfidBindingSessionActive && !data.unbound && typeof showGrowl === 'function') {
                  showGrowl(`Scan Error: ${data.error || data.message}`, 'error');
                await showCountdownPopup('RFID Scan Error', `<p>${escapeHtml(data.error || data.message || 'Unknown error')}</p>`, 'error', 5000);
              }
          }
      } catch (error) {
          console.error('[RFID] Hardware scan fail:', error);
          } finally {
            isRfidProcessing = false;
      }
  }


  adminPanelLog('[NAV] All navigation listeners attached');

  /* ---------- Initial Page ---------- */
  const initialPage = (() => {
    const page = (new URLSearchParams(window.location.search).get('apage') || '').trim().toLowerCase();
    return allowedAdminPages.has(page) ? page : 'dashboard';
  })();

  menuLinks.forEach((l) => l.classList.remove('active'));
  const initialLink = document.querySelector(`.menu-item[data-page="${initialPage}"]`);
  if (initialLink) initialLink.classList.add('active');
  loadPage(initialPage);

  /* ---------- Logout ---------- */
  signOutBtn?.addEventListener("click", async () => {
    const result = await Swal.fire({
      title: "Sign out?",
      text: "You will be logged out of the admin panel.",
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Yes, Logout",
      cancelButtonText: "Cancel",
      confirmButtonColor: "#ef4444",
      cancelButtonColor: "#6b7280",
      heightAuto: false,
    });

    if (!result.isConfirmed) return;

    showGrowl("Logging out...", "success");

    try {
      const res = await fetch("../auth/logout.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      });

      if (res.ok) {
        const data = await res.json().catch(() => null);
        if (data && data.success) {
          await Swal.fire({
            title: "Logged out",
            text: "Redirecting to login...",
            icon: "success",
            timer: 900,
            showConfirmButton: false,
            heightAuto: false
          });
          setTimeout(() => {
            window.location.href = "../auth/login.php";
          }, 900);
          return;
        }
      }

      window.location.href = "../auth/logout.php";
    } catch (err) {
      console.error(err);
      window.location.href = "../auth/logout.php";
    }
  });

  /* ---------- Load Page Fragment ---------- */
  async function loadPage(page) {
    if (typeof window.closeModal === 'function') {
      window.closeModal();
    }

    if (rfidTimeoutToken) {
      clearTimeout(rfidTimeoutToken);
      rfidTimeoutToken = null;
    }

    currentPage = page; // Track which page we're on
    setActivePageInUrl(page);

    // Fade out animation
    contentArea.style.opacity = '0';
    contentArea.style.transform = 'translateY(10px)';

    await new Promise(resolve => setTimeout(resolve, 200));

    // Page-specific skeleton loaders using .ta-skeleton design tokens
    const skeletons = {
      dashboard: `
        <div class="p-6 space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="ta-skeleton ta-skeleton-card"></div>
            <div class="ta-skeleton ta-skeleton-card"></div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="ta-skeleton" style="height:5rem;"></div>
            <div class="ta-skeleton" style="height:5rem;"></div>
            <div class="ta-skeleton" style="height:5rem;"></div>
            <div class="ta-skeleton" style="height:5rem;"></div>
          </div>
          <div class="ta-skeleton" style="height:18rem;"></div>
        </div>`,
      _table: `
        <div class="p-6 space-y-4">
          <div class="flex gap-3 flex-wrap">
            <div class="ta-skeleton" style="width:8rem;height:2.5rem;"></div>
            <div class="ta-skeleton" style="width:8rem;height:2.5rem;"></div>
            <div class="ta-skeleton" style="width:14rem;height:2.5rem;margin-left:auto;"></div>
          </div>
          <div class="ta-skeleton" style="height:2.75rem;"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
          <div class="ta-skeleton ta-skeleton-row"></div>
        </div>`
    };
    contentArea.innerHTML = skeletons[page] || skeletons._table;

    // Fade in loading state
    contentArea.style.opacity = '1';
    contentArea.style.transform = 'none';

    try {
      let fetchUrl = `fetch/fetch_${page}.php`;
      if (page === 'logs') {
        const savedLogs = getLogsStateFromUrl();
        const savedParams = new URLSearchParams();
        if (savedLogs.plate) savedParams.set('plate', savedLogs.plate);
        if (savedLogs.perPage) savedParams.set('per_page', savedLogs.perPage);
        if (savedLogs.page > 1) savedParams.set('page', String(savedLogs.page));
        if (savedParams.toString()) {
          fetchUrl += `?${savedParams.toString()}`;
        }
      }

      const res = await fetch(fetchUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      // Session expiration is now handled by global fetch interceptor
      // Just check if response is ok
      if (!res.ok) {
        // If it's 403, the global interceptor already handled it
        if (res.status === 403) return;
        throw new Error("fetch failed");
      }

      const html = await res.text();

      // Fade out before changing content
      contentArea.style.opacity = '0';
      await new Promise(resolve => setTimeout(resolve, 150));

      contentArea.innerHTML = html;
      
      // Robust Alpine.js re-initialization for injected content
      if (window.Alpine) {
        try {
          if (typeof window.Alpine.initTree === 'function') {
            window.Alpine.initTree(contentArea);
            adminPanelLog('[Alpine] Initialized subtree using initTree');
          } else if (typeof window.Alpine.discover === 'function') {
            window.Alpine.discover(contentArea);
            adminPanelLog('[Alpine] Initialized subtree using discover');
          } else if (typeof window.Alpine.start === 'function') {
            // Some versions of v3 might not expose initTree but start() will re-scan
            window.Alpine.start();
            adminPanelLog('[Alpine] Re-scanned document using start()');
          }
        } catch (alpineErr) {
          console.warn('[Alpine] Subtree initialization failed:', alpineErr);
        }
      }


      // Execute external scripts in the loaded content and wait for them to finish.
      const scripts = Array.from(contentArea.querySelectorAll('script'));
      for (const oldScript of scripts) {
        try {
          const isExternalScript = !!oldScript.getAttribute('src');

          // Harden fragment execution: allow external scripts only.
          if (!isExternalScript) {
            oldScript.remove();
            continue;
          }

          const newScript = document.createElement('script');

          // Copy attributes
          Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
          });

          // Copy content
          newScript.textContent = oldScript.textContent;

          await new Promise((resolve, reject) => {
            newScript.addEventListener('load', resolve, { once: true });
            newScript.addEventListener('error', reject, { once: true });

            if (oldScript.parentNode) {
              oldScript.parentNode.replaceChild(newScript, oldScript);
            } else {
              resolve();
            }
          });
        } catch (error) {
          console.error('Error executing script:', error);
          // Do not eval fragment script text; keep execution path DOM-based only.
        }
      }

      // Fade in new content
      contentArea.style.opacity = '1';
      // Clear transform after transition so it doesn't create a containing block
      // that breaks position:fixed action dropdowns
      setTimeout(function() { contentArea.style.transform = 'none'; }, 350);

      adminPanelLog(`[Page Load] ${page} loaded successfully`);

      // Attach page-specific controls
      if (page === "manage") attachManageControls();
      if (page === "logs") attachLogsControls();
      if (page === "dashboard") attachDashboardControls();
      if (page === "rfid") attachRFIDControls();
      if (page === "visitors") attachVisitorsControls();
      if (page === "audit") attachAuditControls();
      if (page === "reports") attachReportsControls();
      if (page === "employees") attachEmployeesControls();
      if (page === "profile_requests") attachProfileRequestsControls();
      if (page === "approvals") attachApprovalsControls();

    } catch (err) {
      console.error(err);
      contentArea.innerHTML = "<p style='color:red'>Failed to load page</p>";
      showGrowl("Failed to load page", "error");
    }
  }

  /* ---------- RFID Management Controls ---------- */
  let rfidBindingPollTimer = null;
  let rfidBindingSessionId = null;
  let rfidBindingExpiry = null;

  function attachRFIDControls() {
    adminPanelLog('[RFID_MGMT] Attaching controls...');

    // Search functionality
    const searchInput = document.getElementById('rfidSearchInput');
    const searchCount = document.getElementById('rfidSearchCount');
    const table = document.getElementById('rfidTable');

    if (searchInput && table) {
      const rows = getSearchableRows(table, 'tbody tr[data-vehicle-id]');

      const applyFilter = () => {
        const term = searchInput.value.toLowerCase().trim();
        let visible = 0;
        rows.forEach(row => {
          const text = Array.from(row.querySelectorAll('td')).map(c => c.textContent).join(' ').toLowerCase();
          const show = !term || text.includes(term);
          row.style.display = show ? '' : 'none';
          if (show) visible++;
        });

        updateSearchCounter(searchCount, visible, rows.length, 'vehicles');
      };

      searchInput.addEventListener('input', applyFilter);
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          this.value = '';
          applyFilter();
        }
      });

      applyFilter();
    }

    // Refresh button
    document.getElementById('rfidRefreshBtn')?.addEventListener('click', () => loadPage('rfid'));

    // Bind RFID buttons
    document.querySelectorAll('.btn-bind-rfid').forEach(btn => {
      btn.addEventListener('click', async function () {
        const vehicleId = this.dataset.vehicleId;
        const plate = this.dataset.plate;
        const owner = this.dataset.owner;

        const result = await Swal.fire({
          title: 'Bind RFID Tag',
          html: `<div class="text-left">
            <p class="mb-3">Start a binding session for:</p>
            <div class="bg-gray-50 rounded-lg p-3 mb-3">
              <p class="font-bold text-gray-800">${plate}</p>
              <p class="text-sm text-gray-600">${owner}</p>
            </div>
            <p class="text-sm text-gray-500">After clicking <strong>Start</strong>, scan an RFID tag within 5 minutes. The tag will be automatically bound to this vehicle.</p>
          </div>`,
          icon: 'info',
          showCancelButton: true,
          confirmButtonText: 'Start Binding',
          confirmButtonColor: '#3b82f6',
          cancelButtonText: 'Cancel',
          width: '420px'
        });

        if (!result.isConfirmed) return;

        window.rfidBindingSessionActive = true;

        try {
          const form = new FormData();
          form.append('action', 'initiate');
          form.append('csrf_token', csrf);
          form.append('vehicle_id', vehicleId);

          const res = await fetch('../api/rfid/bind.php', { method: 'POST', body: form });
          const json = await res.json();

          if (json.success) {
            showGrowl(json.message, 'success');
            rfidBindingSessionId = json.data.session_id;
            rfidBindingExpiry = new Date(json.data.expires_at).getTime();

            // Show binding banner
            const banner = document.getElementById('bindingBanner');
            if (banner) {
              banner.classList.remove('hidden');
              const target = document.getElementById('bindingTarget');
              if (target) target.textContent = `Binding to: ${plate} (${owner})`;
              const cancelBtn = document.getElementById('cancelBindingBtn');
              if (cancelBtn) cancelBtn.dataset.sessionId = rfidBindingSessionId;
            }

            // Start polling for binding completion
            startBindingPoll();
          } else {
            showGrowl(json.message || 'Failed to start binding', 'error');
          }
        } catch (err) {
          console.error('[RFID_MGMT] Bind error:', err);
          showGrowl('Failed to start binding session', 'error');
        }
      });
    });

    // Unbind RFID buttons
    document.querySelectorAll('.btn-unbind-rfid').forEach(btn => {
      btn.addEventListener('click', async function () {
        const vehicleId = this.dataset.vehicleId;
        const plate = this.dataset.plate;
        const uid = this.dataset.uid;

        const result = await Swal.fire({
          title: 'Unbind RFID Tag',
          html: `<div class="text-left">
            <p class="mb-3">Remove RFID binding from:</p>
            <div class="bg-gray-50 rounded-lg p-3 mb-3">
              <p class="font-bold text-gray-800">${plate}</p>
              <p class="text-sm text-gray-600 font-mono">UID: ${uid}</p>
            </div>
            <p class="text-sm text-red-500 font-medium">This vehicle will lose RFID access until a new tag is bound.</p>
          </div>`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Unbind',
          confirmButtonColor: '#ef4444',
          cancelButtonText: 'Keep',
          width: '420px'
        });

        if (!result.isConfirmed) return;

        try {
          const form = new FormData();
          form.append('action', 'unbind');
          form.append('csrf_token', csrf);
          form.append('vehicle_id', vehicleId);

          const res = await fetch('../api/rfid/bind.php', { method: 'POST', body: form });
          const json = await res.json();

          if (json.success) {
            showGrowl(json.message, 'success');
            loadPage('rfid');
          } else {
            showGrowl(json.message || 'Unbind failed', 'error');
          }
        } catch (err) {
          console.error('[RFID_MGMT] Unbind error:', err);
          showGrowl('Failed to unbind RFID tag', 'error');
        }
      });
    });

    // Cancel binding button
    document.getElementById('cancelBindingBtn')?.addEventListener('click', async function () {
      const sessionId = this.dataset.sessionId;
      if (!sessionId) return;

      try {
        const form = new FormData();
        form.append('action', 'cancel');
        form.append('csrf_token', csrf);
        form.append('session_id', sessionId);

        const res = await fetch('../api/rfid/bind.php', { method: 'POST', body: form });
        const json = await res.json();

        stopBindingPoll();
        window.rfidBindingSessionActive = false; // Release interceptor
        if (json.success) {
          showGrowl('Binding session cancelled', 'info');
          window.rfidBindingSessionActive = false;
          rfidBindingSessionId = null;
          loadPage('rfid');
        } else {
          showGrowl(json.message || 'Cancel failed', 'error');
        }
      } catch (err) {
        console.error('[RFID_MGMT] Cancel error:', err);
        showGrowl('Failed to cancel binding', 'error');
      }
    });

    // Check if there's already an active session on page load
    const banner = document.getElementById('bindingBanner');
    const cancelBtn = document.getElementById('cancelBindingBtn');
    if (banner && !banner.classList.contains('hidden') && cancelBtn?.dataset.sessionId) {
      const restoredSessionId = Number(cancelBtn.dataset.sessionId);
      if (!Number.isFinite(restoredSessionId) || restoredSessionId <= 0) {
        adminPanelLog('[RFID_MGMT] Ignoring invalid binding session id from banner:', cancelBtn.dataset.sessionId);
      } else {
        rfidBindingSessionId = restoredSessionId;
        window.rfidBindingSessionActive = true;
        startBindingPoll();
      }
    }
  }

  function startBindingPoll() {
    stopBindingPoll();
    adminPanelLog('[RFID_MGMT] Starting binding poll for session:', rfidBindingSessionId);

    rfidBindingPollTimer = setInterval(async () => {
      try {
        const res = await fetch(`../api/rfid/bind.php?action=status&session_id=${rfidBindingSessionId}`);
        const json = await res.json();

        if (!json.success) {
          stopBindingPoll();
          window.rfidBindingSessionActive = false;
          rfidBindingSessionId = null;
          return;
        }

        const sessionData = json?.data || {};
        const sessionStatus = String(sessionData.status || '');
        const remainingSeconds = Number(sessionData.remaining_seconds);

        // Update timer display
        const timerEl = document.getElementById('bindingTimer');
        if (timerEl && Number.isFinite(remainingSeconds)) {
          const mins = Math.floor(remainingSeconds / 60);
          const secs = remainingSeconds % 60;
          timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        // Check if session completed/timed out/cancelled
        if (!json.active || sessionStatus !== 'pending') {
          stopBindingPoll();
          window.rfidBindingSessionActive = false; // Release interceptor

          if (sessionStatus === 'completed') {
            const boundUid = escapeHtml(sessionData.scanned_uid || '');
            const boundPlate = escapeHtml(sessionData.plate_number || '');
            const patched = applyRealtimeRfidBindingUi(sessionData.plate_number || '', sessionData.scanned_uid || '');
            if (!patched) {
              loadPage('rfid');
            }

            showCountdownPopup(
              'RFID Bound!',
              `<p>RFID tag <strong class="font-mono">${boundUid}</strong> has been bound to <strong>${boundPlate}</strong></p>`,
              'success',
              5000
            ).catch(() => {});
          } else if (sessionStatus === 'timeout') {
            showGrowl('Binding session timed out', 'warning');
          } else if (sessionStatus === 'cancelled') {
            showGrowl('Binding session was cancelled', 'info');
          }
        }
      } catch (err) {
        console.error('[RFID_MGMT] Poll error:', err);
      }
    }, 800); // Poll faster for near realtime bind completion
  }

  function stopBindingPoll() {
    if (rfidBindingPollTimer) {
      clearInterval(rfidBindingPollTimer);
      rfidBindingPollTimer = null;
      adminPanelLog('[RFID_MGMT] Stopped binding poll');
    }
    rfidBindingSessionId = null;
    window.rfidBindingSessionActive = false; // Ensure interceptor is released
  }

  /* ---------- RFID Simulator Controls ---------- */

  /* ---------- Visitors Page Controls ---------- */
  function attachVisitorsControls() {
    adminPanelLog('[Visitors] Attaching controls');

    let visitorsDebounceTimer;

    // Legacy pending passes fragment support (fetch_visitor_passes.php)
    const pendingPassesContainer = document.getElementById('pendingPassesContainer');

    const loadLegacyPendingPasses = async () => {
      if (!pendingPassesContainer) return;

      try {
        const response = await fetch('api/get_pending_passes.php', { credentials: 'same-origin' });
        const passes = await response.json();

        if (!Array.isArray(passes) || passes.length === 0) {
          pendingPassesContainer.innerHTML = `
            <div class="ta-empty-state">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
              </svg>
              <p>No pending visitor pass requests</p>
            </div>
          `;
          return;
        }

        const esc = (s) => {
          const d = document.createElement('div');
          d.textContent = s ?? '';
          return d.innerHTML;
        };

        const titleCase = (value) => String(value ?? '')
          .trim()
          .toLowerCase()
          .replace(/\b\w/g, (char) => char.toUpperCase());

        pendingPassesContainer.innerHTML = passes.map((pass) => `
          <div class="ta-card mb-3">
            <div class="ta-card-body">
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <h3 class="font-semibold text-gray-900 dark:text-white">${esc(titleCase(pass.visitor_name))}</h3>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Purpose: ${esc(pass.purpose)}</p>
                  <p class="text-sm text-gray-600 dark:text-gray-400">Homeowner: ${esc(titleCase(pass.homeowner_name))}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Valid: ${esc(new Date(pass.valid_from).toLocaleString())} to ${esc(new Date(pass.valid_until).toLocaleString())}</p>
                  ${pass.visitor_plate ? `<p class="text-xs text-gray-500 dark:text-gray-400">Plate: <span class="ta-badge neutral">${esc(pass.visitor_plate)}</span></p>` : ''}
                  <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Requested: ${esc(new Date(pass.created_at).toLocaleString())}</p>
                </div>
                <div class="flex gap-2">
                  <div class="ta-action-dropdown">
                    <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false" aria-controls="visitor-pass-actions-${pass.id}">
                      Actions
                      <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div class="ta-action-menu" id="visitor-pass-actions-${pass.id}" role="menu" aria-hidden="true">
                      <button type="button" role="menuitem" class="ta-action-menu-item green" onclick="approvePass(${pass.id})">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Approve
                      </button>
                      <div class="ta-action-divider"></div>
                      <button type="button" role="menuitem" class="ta-action-menu-item red" onclick="rejectPass(${pass.id})">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reject
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `).join('');
      } catch (error) {
        console.error('[Visitors] Legacy pending passes load error:', error);
        pendingPassesContainer.innerHTML = '<div class="text-center py-12 text-red-500"><p>Error loading visitor passes.</p></div>';
      }
    };

    window.approvePass = async function (passId) {
      const result = await Swal.fire({
        title: 'Approve Visitor Pass?',
        text: 'This will allow the visitor to enter during the specified time period.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Approve',
        confirmButtonColor: '#10b981',
        cancelButtonText: 'Cancel'
      });

      if (!result.isConfirmed) return;

      try {
        const response = await fetch('api/approve_visitor_pass.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pass_id: passId, csrf_token: csrf })
        });
        const data = await response.json();

        if (!data.success) throw new Error(data.message || 'Failed to approve pass');

        await Swal.fire({
          icon: 'success',
          title: 'Activated!',
          text: 'Visitor pass is now active.',
          confirmButtonColor: '#3b82f6'
        });

        if (pendingPassesContainer) {
          loadLegacyPendingPasses();
        } else {
          loadPage('visitors');
        }
      } catch (error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message,
          confirmButtonColor: '#ef4444'
        });
      }
    };

    window.rejectPass = async function (passId) {
      const { value: reason } = await Swal.fire({
        title: 'Reject Visitor Pass',
        input: 'textarea',
        inputLabel: 'Reason for rejection',
        inputPlaceholder: 'Enter reason...',
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
          if (!value) return 'Please provide a reason for rejection';
        }
      });

      if (!reason) return;

      try {
        const response = await fetch('api/reject_visitor_pass.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pass_id: passId, reason, csrf_token: csrf })
        });
        const data = await response.json();

        if (!data.success) throw new Error(data.message || 'Failed to reject pass');

        await Swal.fire({
          icon: 'success',
          title: 'Rejected',
          text: 'Visitor pass has been rejected.',
          confirmButtonColor: '#3b82f6'
        });

        if (pendingPassesContainer) {
          loadLegacyPendingPasses();
        } else {
          loadPage('visitors');
        }
      } catch (error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message,
          confirmButtonColor: '#ef4444'
        });
      }
    };

    if (pendingPassesContainer) {
      loadLegacyPendingPasses();
    }

    const buildVisitorsQuery = (page = 1) => {
      const params = new URLSearchParams();
      params.set('page', String(page));

      const search = (document.getElementById('visitorsSearchInput')?.value || '').trim();
      const perPage = (document.getElementById('visitorsPerPage')?.value || '25').trim();

      if (search) params.set('search', search);
      if (perPage) params.set('per_page', perPage);
      params.set('_', String(Date.now()));
      return params.toString();
    };

    const loadVisitorsWithFilters = async (page = 1) => {
      contentArea.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <div class="spinner spinner-lg mx-auto mb-4"></div>
            <p class="text-gray-500 text-sm">Loading...</p>
          </div>
        </div>
      `;

      try {
        const response = await fetch(`fetch/fetch_visitors.php?${buildVisitorsQuery(page)}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const html = await response.text();
        contentArea.innerHTML = html;
        contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        attachVisitorsControls();
      } catch (error) {
        console.error('[Visitors] Reload error:', error);
        showGrowl('Failed to load visitor passes', 'error');
      }
    };

    // Define approve and reject functions
    window.approveVisitorPass = async function (passId) {
      const result = await Swal.fire({
        title: 'Approve Visitor Pass?',
        html: `
          <div class="text-left">
            <p class="mb-3">This will:</p>
            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
              <li>Approve the visitor pass request</li>
              <li>Generate a QR code for entry</li>
              <li>Make it available to the homeowner</li>
              <li>Allow the visitor to enter during the specified period</li>
            </ul>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Approve',
        confirmButtonColor: '#10b981',
        cancelButtonText: 'Cancel',
        cancelButtonColor: '#6b7280'
      });

      if (result.isConfirmed) {
        try {
          const response = await fetch('api/approve_visitor_pass.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pass_id: passId, csrf_token: csrf })
          });

          const data = await response.json();

          if (data.success) {
            await Swal.fire({
              icon: 'success',
              title: 'Approved!',
              html: `
                <div class="text-center">
                  <p class="mb-2">Visitor pass has been approved successfully.</p>
                  <p class="text-sm text-gray-600">The homeowner can now view and share the QR code.</p>
                </div>
              `,
              confirmButtonColor: '#3b82f6'
            });
            loadPage("visitors");
          } else {
            throw new Error(data.message || 'Failed to approve pass');
          }
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message,
            confirmButtonColor: '#ef4444'
          });
        }
      }
    };

    window.rejectVisitorPass = async function (passId) {
      const { value: reason } = await Swal.fire({
        title: 'Reject Visitor Pass',
        html: `
          <div class="text-left mb-3">
            <p class="text-sm text-gray-600 mb-2">Please provide a reason for rejecting this pass request:</p>
          </div>
        `,
        input: 'textarea',
        inputPlaceholder: 'e.g., Invalid documentation, security concerns, etc.',
        showCancelButton: true,
        confirmButtonText: 'Reject',
        confirmButtonColor: '#ef4444',
        cancelButtonText: 'Cancel',
        cancelButtonColor: '#6b7280',
        inputValidator: (value) => {
          if (!value || value.trim().length < 5) {
            return 'Please provide a detailed reason (at least 5 characters)';
          }
        },
        inputAttributes: {
          'aria-label': 'Rejection reason',
          'style': 'min-height: 80px;'
        }
      });

      if (reason) {
        try {
          const response = await fetch('api/reject_visitor_pass.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              pass_id: passId,
              reason: reason.trim(),
              csrf_token: csrf
            })
          });

          const data = await response.json();

          if (data.success) {
            await Swal.fire({
              icon: 'success',
              title: 'Rejected',
              html: `
                <div class="text-center">
                  <p class="mb-2">Visitor pass has been rejected.</p>
                  <p class="text-sm text-gray-600">The homeowner will be notified with your reason.</p>
                </div>
              `,
              confirmButtonColor: '#3b82f6'
            });
            loadPage("visitors");
          } else {
            throw new Error(data.message || 'Failed to reject pass');
          }
        } catch (error) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message,
            confirmButtonColor: '#ef4444'
          });
        }
      }
    };

    // Visitor Pass Create Button
    document
      .getElementById("createPassBtn")
      ?.addEventListener("click", () => {
        adminPanelLog('[Visitors] Create Pass button clicked');
        openModal("api/visitor_pass_form.php");
      });

    // Visitor Pass Edit Buttons
    document.querySelectorAll('.editPassBtn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        openModal(`api/visitor_pass_form.php?id=${id}`);
      });
    });

    // Refresh Button
    document.getElementById("refreshPassesBtn")?.addEventListener("click", async (e) => {
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restore = setButtonLoading(btn, '<span>Refreshing...</span>');
      const activePage = Number(document.querySelector('.visitors-page-btn.ta-btn-primary')?.dataset.page || '1');
      try {
        await loadVisitorsWithFilters(activePage);
      } finally {
        restore();
      }
    });

    // Export CSV Button
    document.getElementById("exportPassesBtn")?.addEventListener("click", () => {
      exportTableToCSV('passesTable', 'visitor_passes_export.csv');
    });

    // Cancel Pass Buttons with Type-out Confirmation
    document.querySelectorAll(".cancelPassBtn").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const id = btn.dataset.id;
        const { value: typed } = await Swal.fire({
          title: "Confirm Cancellation",
          html: `Type <strong>CANCEL</strong> to cancel visitor pass #${id}`,
          input: "text",
          inputPlaceholder: "Type CANCEL to confirm",
          showCancelButton: true,
          confirmButtonText: "Cancel Pass",
          confirmButtonColor: "#ef4444",
          preConfirm: (v) => {
            if (v !== "CANCEL")
              Swal.showValidationError("You must type CANCEL to confirm");
            return v;
          },
          allowOutsideClick: false,
          width: "340px",
          heightAuto: false,
        });

        if (typed === "CANCEL") {
          try {
            const form = new FormData();
            form.append("csrf_token", csrf);
            form.append("id", id);
            const res = await fetch("api/cancel_visitor_pass.php", {
              method: "POST",
              body: form,
            });
            let json;
            try {
              json = await res.json();
            } catch (err) {
              json = { success: false, message: 'Invalid response' };
            }
            if (json && json.success) {
              showGrowl(json.message || "Pass cancelled");
              loadPage("visitors");
            } else {
              showGrowl(json.message || "Cancellation failed", "error");
            }
          } catch (err) {
            console.error("cancel error:", err);
            showGrowl("Cancellation failed", "error");
          }
        }
      });
    });

    // QR Code Click Handlers - Attach after content is loaded
    adminPanelLog('[Visitors] Attaching QR click handlers');
    const qrImages = document.querySelectorAll('.qr-clickable');
    adminPanelLog(`[Visitors] Found ${qrImages.length} QR images`);

    qrImages.forEach((img) => {
      img.addEventListener('click', () => {
        adminPanelLog('[Visitors] QR image clicked, src:', img.src);
        if (typeof window.openQRZoom === 'function') {
          window.openQRZoom(img.src);
        } else {
          console.error('[Visitors] openQRZoom function not found!');
        }
      });
    });

    const visitorsSearchInput = document.getElementById('visitorsSearchInput');
    visitorsSearchInput?.addEventListener('input', () => {
      clearTimeout(visitorsDebounceTimer);
      visitorsDebounceTimer = setTimeout(() => {
        loadVisitorsWithFilters(1);
      }, 300);
    });

    visitorsSearchInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(visitorsDebounceTimer);
        loadVisitorsWithFilters(1);
      }
      if (e.key === 'Escape') {
        visitorsSearchInput.value = '';
        clearTimeout(visitorsDebounceTimer);
        loadVisitorsWithFilters(1);
      }
    });

    document.getElementById('visitorsPerPage')?.addEventListener('change', () => {
      loadVisitorsWithFilters(1);
    });

    document.querySelectorAll('.visitors-page-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const page = Number(btn.dataset.page || '1');
        loadVisitorsWithFilters(page);
      });
    });
  }

  /* ---------- Employee Management Controls ---------- */
  function attachEmployeesControls() {
    adminPanelLog('[Employees] Attaching controls');
    let employeesDebounceTimer;

    const buildEmployeesQuery = (page = 1) => {
      const params = new URLSearchParams();
      params.set('page', String(page));

      const search = (document.getElementById('employeeSearchInput')?.value || '').trim();
      const role = (document.getElementById('employeeRoleFilter')?.value || '').trim();
      const perPage = (document.getElementById('employeesPerPage')?.value || '25').trim();

      if (search) params.set('search', search);
      if (role) params.set('role', role);
      if (perPage) params.set('per_page', perPage);
      params.set('_', String(Date.now()));
      return params.toString();
    };

    const loadEmployeesWithFilters = async (page = 1) => {
      contentArea.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <div class="spinner spinner-lg mx-auto mb-4"></div>
            <p class="text-gray-500 text-sm">Loading...</p>
          </div>
        </div>
      `;

      try {
        const response = await fetch(`fetch/fetch_employees.php?${buildEmployeesQuery(page)}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const html = await response.text();
        contentArea.innerHTML = html;
        contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        attachEmployeesControls();
      } catch (error) {
        console.error('[Employees] Reload error:', error);
        showGrowl('Failed to load employees', 'error');
      }
    };

    // Wait for DOM elements with polling
    const pollInterval = setInterval(() => {
      const createBtn = document.getElementById("createEmployeeBtn");
      const refreshBtn = document.getElementById("refreshEmployeesBtn");
      const searchInput = document.getElementById('employeeSearchInput');
      const roleFilter = document.getElementById('employeeRoleFilter');
      const perPageInput = document.getElementById('employeesPerPage');

      if (!createBtn && !refreshBtn && !searchInput && !roleFilter && !perPageInput) {
        return; // Keep polling
      }

      clearInterval(pollInterval);
      adminPanelLog('[Employees] Elements found, attaching listeners');

      // Create Employee Button
      if (createBtn) {
        createBtn.addEventListener('click', () => {
          adminPanelLog('[Employees] Create Employee button clicked');
          openModal('api/employee_form.php');
        });
      }

      // Refresh Button
      if (refreshBtn) {
        refreshBtn.addEventListener('click', async (e) => {
          const btn = e.currentTarget;
          if (btn.disabled) return;
          const restore = setButtonLoading(btn, '<span>Refreshing...</span>');
          const activePage = Number(document.querySelector('.employees-page-btn.ta-btn-primary')?.dataset.page || '1');
          adminPanelLog('[Employees] Refresh button clicked');
          try {
            await loadEmployeesWithFilters(activePage);
          } finally {
            restore();
          }
        });
      }

      // Search functionality
      if (searchInput) {
        searchInput.addEventListener('input', function () {
          clearTimeout(employeesDebounceTimer);
          employeesDebounceTimer = setTimeout(() => {
            loadEmployeesWithFilters(1);
          }, 300);
        });

        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            loadEmployeesWithFilters(1);
          }
        });
      }

      if (roleFilter) {
        roleFilter.addEventListener('change', function () {
          loadEmployeesWithFilters(1);
        });
      }

      if (perPageInput) {
        perPageInput.addEventListener('change', function () {
          loadEmployeesWithFilters(1);
        });
      }

      // Edit Employee Buttons - Same pattern as Manage Records
      document.querySelectorAll(".editEmployeeBtn").forEach((btn) => {
        btn.addEventListener("click", () => {
          const id = btn.dataset.id;
          adminPanelLog('[Employees] Edit employee:', id);
          openModal(`api/employee_form.php?id=${id}`);
        });
      });

      document.querySelectorAll('.employees-page-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          const page = Number(btn.dataset.page || '1');
          loadEmployeesWithFilters(page);
        });
      });

      // Bulk Action Handlers (Global scope for onclick)
      window.toggleAllEmployees = (master) => {
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(cb => cb.checked = master.checked);
        window.updateEmployeeBulkBar();
      };

      window.updateEmployeeBulkBar = () => {
        const selected = document.querySelectorAll('.employee-checkbox:checked');
        const bar = document.getElementById('bulkActionsBar');
        const count = document.getElementById('selectedCount');
        
        if (bar && count) {
          if (selected.length > 0) {
            bar.classList.remove('hidden');
            count.textContent = selected.length;
          } else {
            bar.classList.add('hidden');
            const selectAll = document.getElementById('selectAllEmployees');
            if (selectAll) selectAll.checked = false;
          }
        }
      };

      window.processBulkAction = async (action) => {
        const selected = document.querySelectorAll('.employee-checkbox:checked');
        if (selected.length === 0) return;
        
        const ids = Array.from(selected).map(cb => cb.dataset.id);
        const actionLabel = action === 'suspend' ? 'Suspend' : 'Activate';
        
        const { value: confirmed } = await Swal.fire({
          title: `${actionLabel} ${ids.length} accounts?`,
          text: `Are you sure you want to ${action.toLowerCase()} these accounts?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: action === 'suspend' ? '#f59e0b' : '#10b981',
          confirmButtonText: `Yes, ${actionLabel}`
        });

        if (confirmed) {
          Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
          
          try {
            const res = await fetch('api/bulk_employee_action.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                ids: ids,
                action: action,
                csrf_token: csrf
              })
            });
            const data = await res.json();
            
            if (data.success) {
              await Swal.fire({ icon: 'success', title: 'Success', text: data.message });
              loadEmployeesWithFilters(1);
            } else {
              Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Action failed' });
            }
          } catch (error) {
            console.error('Bulk action error:', error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while processing.' });
          }
        }
      };

      window.clearSelection = () => {
        const checkboxes = document.querySelectorAll('.employee-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        const selectAll = document.getElementById('selectAllEmployees');
        if (selectAll) selectAll.checked = false;
        window.updateEmployeeBulkBar();
      };
    }, 100); // Poll every 100ms
  }

  /* ---------- Profile Requests Controls ---------- */
  function attachProfileRequestsControls() {
    adminPanelLog('[Profile Requests] Attaching controls...');

    const CSRF = window.__ADMIN_CSRF__ || document.querySelector('meta[name="csrf-token"]')?.content || '';
    let currentRequestId = null;
    let currentAction = null;
    let debounceTimer = null;

    const actionConfig = {
      acknowledged: { title: 'Acknowledge Request', color: 'bg-blue-600 hover:bg-blue-700' },
      completed: { title: 'Mark as Completed', color: 'bg-green-600 hover:bg-green-700' },
      rejected: { title: 'Reject Request', color: 'bg-red-600 hover:bg-red-700' }
    };

    const modal = document.getElementById('profileReqModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContext = document.getElementById('modalContext');
    const modalNotes = document.getElementById('modalNotes');
    const modalConfirm = document.getElementById('modalConfirm');
    const modalCancel = document.getElementById('modalCancel');

    const reloadProfileRequests = (opts = {}) => {
      const search = opts.search !== undefined ? opts.search : (document.getElementById('profileReqSearch')?.value || '');
      const perPage = opts.perPage !== undefined ? opts.perPage : (document.getElementById('profileReqPerPage')?.value || '15');
      const status = opts.status !== undefined
        ? opts.status
        : (document.querySelector('.stat-filter-btn.ring-2[data-status]')?.dataset.status || '');

      const params = new URLSearchParams();
      params.set('_', String(Date.now()));
      if (search) params.set('search', search);
      if (status) params.set('status', status);
      if (perPage) params.set('per_page', perPage);
      if (opts.page) params.set('p', String(opts.page));

      fetch(`fetch/fetch_profile_requests.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then((r) => r.text())
        .then((html) => {
          if (contentArea) {
            contentArea.innerHTML = html;
            attachProfileRequestsControls();
          }
        })
        .catch((err) => {
          console.error('[Profile Requests] Reload error:', err);
          showGrowl('Failed to refresh profile requests', 'error');
        });
    };

    const closeProfileReqModal = () => {
      if (!modal) return;
      modal.classList.add('hidden');
      currentRequestId = null;
      currentAction = null;
    };

    const openProfileReqModal = (requestId, action, homeownerName = '') => {
      if (!modal || !modalTitle || !modalConfirm || !modalNotes) return;

      document.querySelectorAll('.ta-action-dropdown.open').forEach((dd) => dd.classList.remove('open'));

      currentRequestId = requestId;
      currentAction = action;
      const config = actionConfig[action] || actionConfig.acknowledged;
      modalTitle.textContent = config.title;
      if (modalContext) {
        modalContext.textContent = homeownerName
          ? `Request for ${homeownerName}`
          : 'Review this request before confirming your action.';
      }
      modalConfirm.className = `px-3 py-1.5 text-sm font-medium text-white rounded-lg transition-colors ${config.color}`;
      modalNotes.value = '';
      modal.classList.remove('hidden');
    };

    document.querySelectorAll('.action-btn').forEach((btn) => {
      btn.addEventListener('click', function () {
        openProfileReqModal(this.dataset.id, this.dataset.action, this.dataset.homeowner || '');
      });
    });

    modalCancel?.addEventListener('click', closeProfileReqModal);
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) closeProfileReqModal();
    });

    modalConfirm?.addEventListener('click', async () => {
      if (!currentRequestId || !currentAction || !modalConfirm || !modalNotes) return;

      modalConfirm.disabled = true;
      modalConfirm.textContent = 'Processing...';

      try {
        const formData = new FormData();
        formData.append('request_id', currentRequestId);
        formData.append('action', currentAction);
        formData.append('admin_notes', modalNotes.value.trim());
        formData.append('csrf_token', CSRF);

        const res = await fetch('api/handle_profile_request.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        const data = await res.json();
        if (data.success) {
          closeProfileReqModal();
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'success',
              title: 'Done',
              text: data.message,
              timer: 2000,
              showConfirmButton: false,
              toast: true,
              position: 'top-end'
            });
          }
          reloadProfileRequests();
        } else {
          if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'An error occurred' });
          } else {
            showGrowl(data.message || 'An error occurred', 'error');
          }
        }
      } catch (err) {
        console.error('[Profile Requests] Action error:', err);
        showGrowl('Network error occurred', 'error');
      } finally {
        modalConfirm.disabled = false;
        modalConfirm.textContent = 'Confirm';
      }
    });

    document.querySelectorAll('.stat-filter-btn').forEach((btn) => {
      btn.addEventListener('click', function () {
        reloadProfileRequests({ status: this.dataset.status || '', page: 1 });
      });
    });

    const searchInput = document.getElementById('profileReqSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          reloadProfileRequests({ search: this.value || '', page: 1 });
        }, 400);
      });
    }

    const perPageSelect = document.getElementById('profileReqPerPage');
    perPageSelect?.addEventListener('change', function () {
      reloadProfileRequests({ perPage: this.value || '15', page: 1 });
    });

    document.getElementById('refreshProfileReqs')?.addEventListener('click', () => reloadProfileRequests());
    document.getElementById('profileReqClearFilters')?.addEventListener('click', () => {
      reloadProfileRequests({ search: '', status: '', page: 1 });
    });

    document.querySelectorAll('.page-btn').forEach((btn) => {
      btn.addEventListener('click', function () {
        const page = Number(this.dataset.page || '1');
        reloadProfileRequests({ page });
      });
    });
  }

  /* ---------- Approvals Controls ---------- */
  function attachApprovalsControls() {
    adminPanelLog('[Approvals] Page loaded - controls are self-contained in approvals_page.php');
  }

  /* ---------- Audit Search Functionality ---------- */
  function initializeAuditSearch() {
    const searchInput = document.getElementById('auditSearchInput');
    const searchCount = document.getElementById('auditSearchCount');
    const table = document.getElementById('auditTable');

    adminPanelLog('[AUDIT SEARCH] Initializing search...', { searchInput, searchCount, table });

    if (!searchInput || !table) {
      console.error('[AUDIT SEARCH] Search elements not found');
      return;
    }

    const rows = getSearchableRows(table);
    adminPanelLog('[AUDIT SEARCH] Total rows:', rows.length);

    const applyFilter = () => {
      const searchTerm = searchInput.value.toLowerCase().trim();
      let visibleCount = 0;

      rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        const text = cells.map(cell => cell.textContent).join(' ').toLowerCase();
        const isVisible = searchTerm === '' || text.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
      });

      updateSearchCounter(searchCount, visibleCount, rows.length, 'records');
      adminPanelLog('[AUDIT SEARCH] Visible count:', visibleCount);
    };

    searchInput.addEventListener('input', function (e) {
      adminPanelLog('[AUDIT SEARCH] Search triggered:', e.target.value);
      applyFilter();
    });

    // ESC key clears search
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        this.value = '';
        applyFilter();
      }
    });

    applyFilter();

    adminPanelLog('[AUDIT SEARCH] Search initialized successfully');
  }

  /* ---------- Audit Logs Controls ---------- */
  function attachAuditControls() {
    adminPanelLog('[Audit] Attaching controls...');

    // Initialize search functionality (same pattern as manage)
    initializeAuditSearch();

    const validateAuditDateRange = () => {
      const dateFrom = document.getElementById('auditDateFrom')?.value || '';
      const dateTo = document.getElementById('auditDateTo')?.value || '';

      if (!dateFrom && !dateTo) {
        return { valid: true };
      }

      const fromDate = dateFrom ? new Date(`${dateFrom}T00:00:00`) : null;
      const toDate = dateTo ? new Date(`${dateTo}T23:59:59`) : null;

      if ((fromDate && Number.isNaN(fromDate.getTime())) || (toDate && Number.isNaN(toDate.getTime()))) {
        return { valid: false, message: 'Invalid audit date format.' };
      }

      if (fromDate && toDate) {
        if (fromDate > toDate) {
          return { valid: false, message: 'Start date must be earlier than or equal to end date.' };
        }

        const days = Math.floor((toDate - fromDate) / 86400000);
        if (days > 366) {
          return { valid: false, message: 'Audit date range cannot exceed 366 days.' };
        }
      }

      return { valid: true };
    };

    const buildAuditQuery = (page = null) => {
      const params = new URLSearchParams();
      const action = document.getElementById('actionFilter')?.value || '';
      const user = (document.getElementById('userFilter')?.value || '').trim();
      const dateFrom = document.getElementById('auditDateFrom')?.value || '';
      const dateTo = document.getElementById('auditDateTo')?.value || '';
      const limit = document.getElementById('auditLimit')?.value || '';

      if (action) params.set('action', action);
      if (user) params.set('user', user);
      if (dateFrom) params.set('date_from', dateFrom);
      if (dateTo) params.set('date_to', dateTo);
      if (limit) params.set('limit', limit);
      if (page !== null) params.set('page', String(page));
      params.set('_', String(Date.now()));

      return params.toString();
    };

    // Apply Filters button
    const applyBtn = document.getElementById('applyFilters');
    if (applyBtn) {
      applyBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        if (applyBtn.disabled) return;
        const restoreApply = setButtonLoading(applyBtn, '<span>Applying...</span>');
        adminPanelLog('[Audit] Apply filters clicked');

        const actionFilter = document.getElementById('actionFilter');
        const userFilter = document.getElementById('userFilter');
        const limitFilter = document.getElementById('auditLimit');
        const action = actionFilter?.value || '';
        const user = userFilter?.value?.trim() || '';
        const dateFrom = document.getElementById('auditDateFrom')?.value || '';
        const dateTo = document.getElementById('auditDateTo')?.value || '';
        const limit = limitFilter?.value || '';

        const rangeValidation = validateAuditDateRange();
        if (!rangeValidation.valid) {
          showGrowl(rangeValidation.message, 'error');
          return;
        }

        adminPanelLog('[Audit] Filter values:', { action, user, dateFrom, dateTo, limit });

        contentArea.innerHTML = `
          <div class="flex items-center justify-center min-h-[400px]">
            <div class="text-center">
              <div class="spinner spinner-lg mx-auto mb-4"></div>
              <p class="text-gray-500 text-sm">Applying filters...</p>
            </div>
          </div>
        `;

        try {
          const url = `fetch/fetch_audit.php?${buildAuditQuery()}`;
          adminPanelLog('[Audit] Fetching:', url);

          const res = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });

          if (!res.ok) throw new Error(`HTTP ${res.status}`);

          const html = await res.text();
          contentArea.innerHTML = html;

          // Re-attach controls after loading filtered content
          attachAuditControls();

          showGrowl('Filters applied', 'success');
        } catch (err) {
          console.error('[Audit] Error:', err);
          contentArea.innerHTML = "<p style='color:red'>Failed to apply filters</p>";
          showGrowl('Failed to apply filters', 'error');
        } finally {
          restoreApply();
        }
      });
    }

    // Clear button
    document.getElementById('clearFilters')?.addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restoreClear = setButtonLoading(btn, '<span>Clearing...</span>');
      adminPanelLog('[Audit] Clear filters clicked');

      const actionFilter = document.getElementById('actionFilter');
      const userFilter = document.getElementById('userFilter');
      const dateFromFilter = document.getElementById('auditDateFrom');
      const dateToFilter = document.getElementById('auditDateTo');
      const limitFilter = document.getElementById('auditLimit');
      if (actionFilter) actionFilter.value = '';
      if (userFilter) userFilter.value = '';
      if (dateFromFilter) dateFromFilter.value = '';
      if (dateToFilter) dateToFilter.value = '';
      if (limitFilter) limitFilter.value = '200';

      contentArea.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <div class="spinner spinner-lg mx-auto mb-4"></div>
            <p class="text-gray-500 text-sm">Loading...</p>
          </div>
        </div>
      `;

      try {
          const res = await fetch(`fetch/fetch_audit.php?_=${Date.now()}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const html = await res.text();
        contentArea.innerHTML = html;

        // Re-attach controls after clearing
        attachAuditControls();

        showGrowl('Filters cleared', 'success');
      } catch (err) {
        console.error('[Audit] Error:', err);
        showGrowl('Failed to clear filters', 'error');
      } finally {
        restoreClear();
      }
    });

    // Export CSV button
    document.getElementById('exportAuditBtn')?.addEventListener('click', () => {
      adminPanelLog('[EXPORT] Exporting table: auditTable');
      exportTableToCSV('auditTable', 'audit_logs_export.csv');
    });

    document.getElementById('auditLimit')?.addEventListener('change', () => {
      document.getElementById('applyFilters')?.click();
    });

    ['userFilter', 'auditDateFrom', 'auditDateTo'].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          document.getElementById('applyFilters')?.click();
        }
      });
    });

    // Pagination controls for Audit Logs
    document.querySelectorAll(".pagination-btn").forEach((btn) => {
      btn.addEventListener("click", async function () {
        if (this.disabled) return;
        const restorePageBtn = setButtonLoading(this);
        const page = this.dataset.page;
        adminPanelLog("[AUDIT] Loading page:", page);

        contentArea.innerHTML = `
          <div class="flex items-center justify-center min-h-[400px]">
            <div class="text-center">
              <div class="spinner spinner-lg mx-auto mb-4"></div>
              <p class="text-gray-500 text-sm">Loading...</p>
            </div>
          </div>
        `;

        try {
          const res = await fetch(`fetch/fetch_audit.php?${buildAuditQuery(page)}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });

          if (!res.ok) throw new Error(`HTTP ${res.status}`);

          const html = await res.text();
          contentArea.innerHTML = html;

          contentArea.scrollTo({ top: 0, behavior: "smooth" });

          // Re-attach controls for new content
          attachAuditControls();
        } catch (err) {
          console.error("[AUDIT] Pagination error:", err);
          showGrowl("Failed to load page", "error");
        } finally {
          restorePageBtn();
        }
      });
    });
  }

  /* ---------- Reports Controls ---------- */
  function attachReportsControls() {
    adminPanelLog('[Reports] Attaching controls');
  }

  /* ---------- Modal Handling ---------- */
  async function openModal(url) {
    if (!modalEl || !modalBody) {
      console.error('[MODAL] Modal elements not found!');
      return;
    }

    modalEl.classList.remove('modal-form');
    modalEl.classList.remove('modal-visitor-pass');
    modalEl.classList.remove('modal-homeowner-profile');
    if (String(url || '').includes('visitor_pass_form.php')) {
      modalEl.classList.add('modal-visitor-pass');
    } else if (String(url || '').includes('homeowner_profile.php')) {
      modalEl.classList.add('modal-homeowner-profile');
    }

    modalEl.classList.remove('hidden');
    modalEl.setAttribute('aria-hidden', 'false');
    // Hide approvals bulk actions bar (if present) while modal is open
    try {
      const bulkBar = document.getElementById('bulkActionsBar');
      if (bulkBar) {
        // store previous visibility state
        bulkBar.dataset.__wasHidden = bulkBar.classList.contains('hidden') ? '1' : '0';
        bulkBar.classList.add('hidden');
      }
    } catch (e) {
      /* ignore */
    }
    modalBody.innerHTML = "<div class='loading'>Loading...</div>";
    document.body.classList.add('modal-open');

    try {
      const sep = url.includes("?") ? "&" : "?";
      const fullUrl = `${url}${sep}ajax=1`;

      const res = await fetch(fullUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      // Session expiration is now handled by global fetch interceptor
      if (!res.ok) {
        throw new Error("load failed");
      }

      const html = await res.text();
      modalBody.innerHTML = html;
      bindModalForm();
      modalBody.querySelectorAll('.js-profile-preview').forEach((img) => {
        img.addEventListener('click', (event) => {
          if (img.tagName === 'A') {
            event.preventDefault();
          }
          openImagePreviewModal(
            img.dataset.previewSrc || img.getAttribute('src') || '',
            img.dataset.previewTitle || img.getAttribute('alt') || 'Image Preview',
            img.dataset.previewCaption || ''
          );
        });
      });

      // Focus management: focus first input
      setTimeout(() => {
        const firstInput = modalBody.querySelector('input:not([type="hidden"]), textarea, select');
        if (firstInput) firstInput.focus();
      }, 100);

      // Keyboard trap: keep focus inside modal
      setupModalKeyboardTrap();
    } catch (err) {
      console.error("openModal error:", err);
      modalBody.innerHTML = '<p style="color:red;">Failed to load form</p>';
    }
  }
  window.openModal = openModal;

  window.closeModal = function () {
    if (!modalEl) return;
    modalEl.classList.add('hidden');
    modalEl.classList.remove('modal-form');
    modalEl.classList.remove('modal-visitor-pass');
    modalEl.classList.remove('modal-homeowner-profile');
    modalEl.setAttribute('aria-hidden', 'true');
    modalBody.innerHTML = "";
    document.body.classList.remove('modal-open');

    // Restore approvals bulk actions bar visibility when modal closed
    try {
      const bulkBar = document.getElementById('bulkActionsBar');
      if (bulkBar && typeof bulkBar.dataset.__wasHidden !== 'undefined') {
        if (bulkBar.dataset.__wasHidden === '0') {
          bulkBar.classList.remove('hidden');
        } else {
          bulkBar.classList.add('hidden');
        }
        delete bulkBar.dataset.__wasHidden;
      }
    } catch (e) {
      /* ignore */
    }
    // Return focus to trigger element if available
    if (document.activeElement) {
      document.activeElement.blur();
    }
  };

  /* ---------- Modal Keyboard Trap ---------- */
  function setupModalKeyboardTrap() {
    if (!modalEl) return;

    if (modalEl.__trapFocusHandler) {
      modalEl.removeEventListener('keydown', modalEl.__trapFocusHandler);
      modalEl.__trapFocusHandler = null;
    }

    const focusableElements = modalEl.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    if (!focusableElements.length) return;

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    const trapFocus = function (e) {
      if (e.key !== 'Tab') {
        // ESC key closes modal
        if (e.key === 'Escape') {
          if (typeof window.closeModal === 'function') {
            window.closeModal();
          }
        }
        return;
      }

      if (e.shiftKey) {
        // Shift + Tab
        if (document.activeElement === firstElement) {
          lastElement.focus();
          e.preventDefault();
        }
      } else {
        // Tab
        if (document.activeElement === lastElement) {
          firstElement.focus();
          e.preventDefault();
        }
      }
    };

    modalEl.__trapFocusHandler = trapFocus;
    modalEl.addEventListener('keydown', trapFocus);
  }

  function bindModalCancel() {
    modalBody.querySelectorAll(".cancel-btn").forEach((btn) => {
      btn.removeEventListener("click", closeModal);
      btn.addEventListener("click", closeModal);
    });
  }

  function bindModalForm() {
    bindModalCancel();
    const form = modalBody.querySelector("form");
    if (!form) {
      modalEl?.classList.remove('modal-form');
      return;
    }

    form.classList.add('modal-unified-form');
    modalEl?.classList.add('modal-form');

    adminPanelLog('[bindModalForm] Binding form:', form.id, 'Action:', form.getAttribute('action'));

    if (!form.getAttribute('action')) {
      if (form.dataset.url) form.setAttribute('action', form.dataset.url);
    }

    // Bind quick duration buttons for visitor pass form
    const quickDurationBtns = modalBody.querySelectorAll('.quick-duration');
    if (quickDurationBtns.length > 0) {
      const fromInput = modalBody.querySelector('#valid_from');
      const untilInput = modalBody.querySelector('#valid_until');

      if (fromInput && untilInput) {
        quickDurationBtns.forEach(btn => {
          btn.addEventListener('click', (e) => {
            e.preventDefault();
            quickDurationBtns.forEach(other => {
              other.classList.remove('is-active');
              other.setAttribute('aria-pressed', 'false');
            });
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');

            const base = fromInput.value ? new Date(fromInput.value) : new Date();
            if (!fromInput.value) {
              fromInput.value = base.toISOString().slice(0, 16);
            }

            const next = new Date(base);
            if (btn.dataset.hours) {
              next.setHours(next.getHours() + parseInt(btn.dataset.hours, 10));
            } else if (btn.dataset.days) {
              next.setDate(next.getDate() + parseInt(btn.dataset.days, 10));
            }

            untilInput.value = next.toISOString().slice(0, 16);
          });
        });
      }
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      adminPanelLog('[bindModalForm] Form submit intercepted');

      const submitBtn = form.querySelector('button[type="submit"]');
      const restoreSubmit = submitBtn
        ? setButtonLoading(submitBtn, '<span>Saving...</span>')
        : () => {};
      const data = new FormData(form);
      if (!data.get("csrf_token")) data.append("csrf_token", csrf);

      // Password validation for employee forms
      const password = data.get('password');
      const confirmPassword = data.get('confirm_password');
      if (password && confirmPassword && password !== confirmPassword) {
        showGrowl('Passwords do not match', 'error');
        restoreSubmit();
        return;
      }

      const action = form.getAttribute("action") || form.dataset.url;
      adminPanelLog('[bindModalForm] Submitting to:', action);
      adminPanelLog('[bindModalForm] FormData:', Array.from(data.entries()));

      try {
        const res = await fetch(action, {
          method: "POST",
          body: data,
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        adminPanelLog('[bindModalForm] Response status:', res.status);

        let json;
        try {
          const text = await res.text();
          adminPanelLog('[bindModalForm] Response text:', text);
          json = JSON.parse(text);
        } catch (err) {
          console.error('[bindModalForm] JSON parse error:', err);
          json = { success: false, message: 'Invalid response from server' };
        }

        if (json && json.success) {
          showGrowl(json.message || "Saved");
          closeModal();
          loadPage(currentPage);
        } else {
          showGrowl(json.message || "Save failed", "error");
        }
      } catch (err) {
        console.error("bindModalForm submit error:", err);
        showGrowl("Request failed", "error");
      } finally {
        restoreSubmit();
      }
    });
  }

  /* ---------- Shared Table Search Utility ---------- */
  function getSearchableRows(table, rowSelector = 'tbody tr') {
    return Array.from(table.querySelectorAll(rowSelector)).filter((row) => {
      const cells = row.querySelectorAll('td');
      if (cells.length === 0) return false;
      return !row.querySelector('td[colspan]');
    });
  }

  function updateSearchCounter(searchCount, visibleCount, totalRows, label = 'records') {
    if (!searchCount) return;
    searchCount.textContent = `${visibleCount} of ${totalRows} ${label}`;
    searchCount.style.color = visibleCount > 0 ? '#16a34a' : '#dc2626';
  }

  function initializeTableSearch(inputId, countId, tableId) {
    const searchInput = document.getElementById(inputId);
    const searchCount = document.getElementById(countId);
    const table = document.getElementById(tableId);

    if (!searchInput || !table) return;

    const applyFilter = () => {
      const searchTerm = searchInput.value.toLowerCase().trim();
      const rows = getSearchableRows(table);
      let visibleCount = 0;

      rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td:not(:last-child)'));
        const text = cells.map(cell => cell.textContent).join(' ').toLowerCase();
        const isVisible = searchTerm === '' || text.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
      });

      updateSearchCounter(searchCount, visibleCount, rows.length, 'records');
    };

    searchInput.addEventListener('input', function () {
      applyFilter();
    });

    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        this.value = '';
        applyFilter();
      }
    });

    applyFilter();
  }

  /* ---------- CSV Export Functionality ---------- */
  function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
      console.error('[EXPORT] Table not found:', tableId);
      showGrowl('Table not found', 'error');
      return;
    }

    adminPanelLog('[EXPORT] Exporting table:', tableId);

    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
      const text = th.textContent.trim();
      // Skip empty headers or "Actions" column
      if (text && text.toLowerCase() !== 'actions') {
        headers.push(text);
      }
    });

    // Get visible rows only (respects search filter)
    const rows = [];
    table.querySelectorAll('tbody tr').forEach(tr => {
      // Skip hidden rows (filtered out by search)
      if (tr.style.display === 'none') return;

      const rowData = [];
      tr.querySelectorAll('td').forEach((td, index) => {
        // Skip the last column (Actions)
        if (index < tr.querySelectorAll('td').length - 1) {
          // Escape quotes and wrap in quotes if contains comma
          let value = td.textContent.trim();
          value = value.replace(/"/g, '""'); // Escape quotes
          if (value.includes(',') || value.includes('\n') || value.includes('"')) {
            value = `"${value}"`;
          }
          rowData.push(value);
        }
      });
      if (rowData.length > 0) {
        rows.push(rowData);
      }
    });

    if (rows.length === 0) {
      showGrowl('No data to export', 'warning');
      return;
    }

    // Build CSV content
    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
      csv += row.join(',') + '\n';
    });

    // Create blob and download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showGrowl(`Exported ${rows.length} records to CSV`, 'success');
    adminPanelLog('[EXPORT] Export completed:', rows.length, 'rows');
  }

  /* ---------- Manage Page Controls ---------- */
  function attachManageControls() {
    adminPanelLog('[MANAGE] Attaching controls...');
    let manageDebounceTimer;

    const applyManageRfidFilterUi = () => {
      const filter = (document.getElementById('manageRfidStatus')?.value || 'all').trim();
      const table = document.getElementById('homeownersTable');
      if (!table) return;

      const rows = Array.from(table.querySelectorAll('tbody tr'));
      let visible = 0;

      rows.forEach((row) => {
        const isDataRow = row.querySelector('td');
        if (!isDataRow) return;

        const isBound = !!row.querySelector('.rfid-badge-bound');
        const show = filter === 'all' || (filter === 'bound' && isBound) || (filter === 'unbound' && !isBound);

        row.style.display = show ? '' : 'none';
        if (show) visible += 1;
      });

      const countEl = document.getElementById('searchCount');
      if (countEl) {
        countEl.textContent = `${visible} of ${rows.filter((row) => row.querySelector('td')).length} shown`;
      }
    };

    const buildManageQuery = (page = 1) => {
      const params = new URLSearchParams();
      params.set('page', String(page));

      const search = (document.getElementById('searchInput')?.value || '').trim();
      const perPage = (document.getElementById('managePerPage')?.value || '25').trim();
      const rfidStatus = (document.getElementById('manageRfidStatus')?.value || 'all').trim();
      const dateFrom = (document.getElementById('manageDateFrom')?.value || '').trim();
      const dateTo = (document.getElementById('manageDateTo')?.value || '').trim();

      if (search) params.set('search', search);
      if (perPage) params.set('per_page', perPage);
      if (rfidStatus) params.set('rfid_status', rfidStatus);
      if (dateFrom) params.set('date_from', dateFrom);
      if (dateTo) params.set('date_to', dateTo);
      params.set('_', String(Date.now()));
      return params.toString();
    };

    const loadManageWithFilters = async (page = 1) => {
      contentArea.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <div class="spinner spinner-lg mx-auto mb-4"></div>
            <p class="text-gray-500 text-sm">Loading...</p>
          </div>
        </div>
      `;

      try {
        const res = await fetch(`fetch/fetch_manage.php?${buildManageQuery(page)}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const html = await res.text();
        contentArea.innerHTML = html;
        contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        attachManageControls();
      } catch (err) {
        console.error('[MANAGE] Reload error:', err);
        showGrowl('Failed to load records', 'error');
      }
    };

    // Refresh button
    document.getElementById("refreshBtn")?.addEventListener("click", async (e) => {
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restore = setButtonLoading(btn, '<span>Refreshing...</span>');
      const activePage = Number(document.querySelector('.pagination-btn.ta-btn-primary')?.dataset.page || '1');
      try {
        await loadManageWithFilters(activePage);
      } finally {
        restore();
      }
    });

    // Add New button
    document
      .getElementById("openCreateBtn")
      ?.addEventListener("click", () =>
        openModal("homeowners/homeowner_create.php")
      );

    // Export CSV button
    document.getElementById("exportManageBtn")?.addEventListener("click", () => {
      exportTableToCSV('homeownersTable', 'homeowners_export.csv');
    });

    // QR Registration button - opens print-ready page
    document.getElementById("qrRegistrationBtn")?.addEventListener("click", () => {
      const base = window.location.pathname.replace(/\/admin\/.*$/, '');
      window.open(base + '/homeowners/qr_registration.php', '_blank');
    });

    document.querySelectorAll(".btn-edit").forEach((btn) => {
      btn.addEventListener("click", () =>
        openModal(`homeowners/homeowner_edit.php?id=${btn.dataset.id}`)
      );
    });

    document.querySelectorAll('.btn-view').forEach((btn) => {
      btn.addEventListener('click', () =>
        openModal(`homeowners/homeowner_profile.php?id=${btn.dataset.id}`)
      );
    });

    document.querySelectorAll('.btn-delete').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const homeownerName = btn.dataset.name || 'this record';
        const confirmResult = await Swal.fire({
          title: 'Delete homeowner?',
          html: `<p>This will permanently delete <strong>${escapeHtml(homeownerName)}</strong> and associated vehicles.</p>`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, Delete',
          confirmButtonColor: '#ef4444',
          cancelButtonText: 'Cancel'
        });

        if (!confirmResult.isConfirmed) return;

        try {
          const form = new FormData();
          form.append('id', btn.dataset.id);
          form.append('csrf_token', window.__ADMIN_CSRF__ || csrf || '');
          form.append('confirmation', 'DELETE');

          const response = await fetch('homeowners/homeowner_delete.php', {
            method: 'POST',
            body: form
          });

          const result = await response.json();
          if (result.success) {
            showGrowl(result.message || 'Homeowner deleted', 'success');
            loadPage('manage');
          } else {
            showGrowl(result.message || 'Failed to delete homeowner', 'error');
          }
        } catch (error) {
          console.error('[MANAGE] Delete error:', error);
          showGrowl('Connection error', 'error');
        }
      });
    });

    // Unbind RFID button (from Manage Records page)
    document.querySelectorAll('.btn-unbind-rfid-manage').forEach(btn => {
      btn.addEventListener('click', async function () {
        const vehicleId = this.dataset.vehicleId;
        const plate = this.dataset.plate;

        const result = await Swal.fire({
          title: 'Unbind RFID Tag?',
          html: `<p>Are you sure you want to remove the RFID association for vehicle <strong>${plate}</strong>?</p>`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, Unbind',
          confirmButtonColor: '#ef4444',
          cancelButtonText: 'Cancel'
        });

        if (!result.isConfirmed) return;

        try {
          const form = new FormData();
          form.append('action', 'unbind');
          form.append('csrf_token', window.__ADMIN_CSRF__);
          form.append('vehicle_id', vehicleId);

          const res = await fetch('../api/rfid/bind.php', { method: 'POST', body: form });
          const json = await res.json();

          if (json.success) {
            showGrowl('RFID tag unbound successfully', 'success');
            loadPage('manage');
          } else {
            showGrowl(json.message || 'Failed to unbind tag', 'error');
          }
        } catch (err) {
          console.error('[MANAGE] Unbind error:', err);
          showGrowl('Connection error', 'error');
        }
      });
    });

    document.querySelectorAll(".cancel-btn").forEach((btn) => {
      btn.addEventListener("click", closeModal);
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(manageDebounceTimer);
        manageDebounceTimer = setTimeout(() => {
          loadManageWithFilters(1);
        }, 300);
      });

      searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          clearTimeout(manageDebounceTimer);
          loadManageWithFilters(1);
        }
        if (e.key === 'Escape') {
          searchInput.value = '';
          clearTimeout(manageDebounceTimer);
          loadManageWithFilters(1);
        }
      });
    }

    // Date filters for Manage Records
    const manageDateFrom = document.getElementById('manageDateFrom');
    const manageDateTo = document.getElementById('manageDateTo');
    [manageDateFrom, manageDateTo].forEach((el) => {
      if (!el) return;
      el.addEventListener('change', () => loadManageWithFilters(1));
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          loadManageWithFilters(1);
        }
      });
    });

    document.getElementById('managePerPage')?.addEventListener('change', () => {
      loadManageWithFilters(1);
    });

    // Pagination controls for Manage Records
    document.querySelectorAll(".pagination-btn").forEach((btn) => {
      btn.addEventListener("click", function () {
        const page = Number(this.dataset.page || '1');
        loadManageWithFilters(page);
      });
    });
  }

  /* ---------- Access Logs Search Functionality ---------- */
  function initializeLogsSearch() {
    const searchInput = document.getElementById('logsSearchInput');
    const searchCount = document.getElementById('logsSearchCount');
    const table = document.getElementById('logsTable');

    adminPanelLog('[LOGS SEARCH] Initializing search...', { searchInput, searchCount, table });

    if (!searchInput || !table) {
      console.error('[LOGS SEARCH] Search elements not found');
      return;
    }

    const rows = getSearchableRows(table);
    adminPanelLog('[LOGS SEARCH] Total rows:', rows.length);

    const applyFilter = () => {
      const searchTerm = searchInput.value.toLowerCase().trim();
      let visibleCount = 0;

      rows.forEach(row => {
        const cells = Array.from(row.querySelectorAll('td:not(:last-child)'));
        const text = cells.map(cell => cell.textContent).join(' ').toLowerCase();
        const isVisible = searchTerm === '' || text.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
        if (isVisible) visibleCount++;
      });

      updateSearchCounter(searchCount, visibleCount, rows.length, 'records');
      adminPanelLog('[LOGS SEARCH] Visible count:', visibleCount);
    };

    searchInput.addEventListener('input', function (e) {
      adminPanelLog('[LOGS SEARCH] Search triggered:', e.target.value);
      applyFilter();
    });

    // ESC key clears search
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        this.value = '';
        applyFilter();
      }
    });

    applyFilter();

    adminPanelLog('[LOGS SEARCH] Search initialized successfully');
  }

  /* ---------- Access Logs Controls ---------- */
  function attachLogsControls() {
    adminPanelLog('[LOGS] Attaching controls...');

    // Initialize search functionality
    initializeLogsSearch();

    const normalizePlateFilter = (value) => {
      return String(value || '')
        .toUpperCase()
        .replace(/[^A-Z0-9\- ]/g, '')
        .trim();
    };

    const buildLogsQuery = (page = 1, state = null) => {
      const params = new URLSearchParams();
      params.set('page', String(page));

      const plateSource = state?.plate ?? (document.getElementById('logsPlateFilter')?.value || '');
      const perPageSource = state?.perPage ?? (document.getElementById('logsPerPage')?.value || '50');

      const plate = normalizePlateFilter(plateSource);
      const perPage = String(perPageSource).trim();

      if (plate) params.set('plate', plate);
      if (perPage) params.set('per_page', perPage);

      params.set('_', String(Date.now()));
      return params.toString();
    };

    const applySavedLogsStateToInputs = () => {
      const saved = getLogsStateFromUrl();
      const plateInput = document.getElementById('logsPlateFilter');
      const perPageInput = document.getElementById('logsPerPage');

      if (plateInput && saved.plate) plateInput.value = normalizePlateFilter(saved.plate);
      if (perPageInput && saved.perPage) perPageInput.value = saved.perPage;
    };

    applySavedLogsStateToInputs();

    const loadLogsWithFilters = async (page = 1) => {
      const stateForUrl = {
        plate: normalizePlateFilter(document.getElementById('logsPlateFilter')?.value || ''),
        perPage: (document.getElementById('logsPerPage')?.value || '50').trim(),
        page: Number(page) || 1
      };

      document.querySelectorAll('.resolveFlagBtn').forEach(btn => {
        btn.addEventListener('click', async () => {
          const flagId = btn.dataset.flagId;
          const logId = btn.dataset.logId;
          
          const { value: confirmed } = await Swal.fire({
            title: 'Resolve this flag?',
            text: 'Marking this flag as resolved will remove the warning from the logs.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Resolve'
          });

          if (confirmed) {
            setButtonLoading(btn, '<span>...</span>');
            try {
              const res = await fetch('api/resolve_log_flag.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ flag_id: flagId, log_id: logId, csrf_token: csrf })
              });
              const data = await res.json();
              if (data.success) {
                showGrowl('Flag resolved', 'success');
                loadLogsWithFilters(page);
              } else {
                showGrowl(data.message || 'Failed to resolve', 'error');
              }
            } catch (error) {
              showGrowl('Connection error', 'error');
            }
          }
        });
      });

      const queryString = buildLogsQuery(page, stateForUrl);
      setLogsStateInUrl(stateForUrl);

      contentArea.innerHTML = `
        <div class="flex items-center justify-center min-h-[400px]">
          <div class="text-center">
            <div class="spinner spinner-lg mx-auto mb-4"></div>
            <p class="text-gray-500 text-sm">Loading...</p>
          </div>
        </div>
      `;

      try {
        const res = await fetch(`fetch/fetch_logs.php?${queryString}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const html = await res.text();
        contentArea.innerHTML = html;
        contentArea.scrollTo({ top: 0, behavior: 'smooth' });
        attachLogsControls();
      } catch (err) {
        console.error('[LOGS] Error:', err);
        showGrowl('Failed to load logs', 'error');
      }
    };

    // Refresh logs button
    document.getElementById("refreshLogsBtn")?.addEventListener("click", async (e) => {
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restore = setButtonLoading(btn, '<span>Refreshing...</span>');
      const activePage = Number(document.querySelector('.pagination-btn.ta-btn-primary')?.dataset.page || '1');
      try {
        await loadLogsWithFilters(activePage);
      } finally {
        restore();
      }
    });

    // Export logs CSV button
    document.getElementById("exportLogsBtn")?.addEventListener("click", () => {
      const currentMonth = new Date().toISOString().slice(0, 7);
      const plate = normalizePlateFilter(document.getElementById('logsPlateFilter')?.value || '');
      const params = new URLSearchParams();
      params.set('month', currentMonth);
      if (plate) params.set('plate', plate);
      window.location.href = `fetch/export_logs_csv.php?${params.toString()}`;
    });

    document.getElementById('applyLogsFiltersBtn')?.addEventListener('click', async (e) => {
      e.preventDefault();
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restore = setButtonLoading(btn, '<span>Applying...</span>');
      try {
        await loadLogsWithFilters(1);
      } finally {
        restore();
      }
    });

    document.getElementById('clearLogsFiltersBtn')?.addEventListener('click', async (e) => {
      e.preventDefault();
      const btn = e.currentTarget;
      if (btn.disabled) return;
      const restore = setButtonLoading(btn, '<span>Clearing...</span>');
      const plateInput = document.getElementById('logsPlateFilter');
      const perPageInput = document.getElementById('logsPerPage');
      if (plateInput) plateInput.value = '';
      if (perPageInput) perPageInput.value = '50';
      try {
        await loadLogsWithFilters(1);
      } finally {
        restore();
      }
    });

    document.getElementById('logsPerPage')?.addEventListener('change', () => {
      loadLogsWithFilters(1);
    });

    document.getElementById('logsPlateFilter')?.addEventListener('input', (e) => {
      e.target.value = normalizePlateFilter(e.target.value);
    });

    ['logsPlateFilter'].forEach((id) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          loadLogsWithFilters(1);
        }
      });
    });

    // Pagination buttons
    document.querySelectorAll('.pagination-btn').forEach(btn => {
      btn.addEventListener('click', async function () {
        if (this.disabled) return;
        const restore = setButtonLoading(this);
        const page = this.dataset.page;
        adminPanelLog('[LOGS] Loading page:', page);
        try {
          await loadLogsWithFilters(page);
        } finally {
          restore();
        }
      });
    });

  }

  /* ---------- Dashboard Controls ---------- */
  function attachDashboardControls() {
    const statusCtx = document.getElementById('statusPieChart');
    const homeownerStatusCtx = document.getElementById('homeownerStatusPieChart');
    const weeklyCtx = document.getElementById('weeklyLineChart');
    const dataNode = document.getElementById('dashboardChartData');
    const stackedDataNode = document.getElementById('dashboardStackedData');
    const homeownerSvg = document.getElementById('homeownerChart');
    const accessSvg = document.getElementById('accessChart');
    const vehicleSvg = document.getElementById('vehicleChart');

    if (!statusCtx && !weeklyCtx && !stackedDataNode && !homeownerSvg && !accessSvg && !vehicleSvg) {
      adminPanelLog('[DASHBOARD] No dashboard elements found to attach');
      return;
    }

    // --- Tab Switching Fallback (In case Alpine fails) ---
    const tabButtons = document.querySelectorAll('[data-tab-btn]');
    const tabContents = document.querySelectorAll('[data-tab-content]');

    if (tabButtons.length > 0) {
      tabButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
          // If Alpine is working, we let it handle the state. 
          // If it's not, we manually toggle.
          const target = this.dataset.tabBtn;
          const isAlpineWorking = this.__x && this.__x.$data; 
          
          if (!isAlpineWorking) {
            adminPanelLog('[DASHBOARD] Alpine not active on tabs, using vanilla fallback');
            tabContents.forEach(content => {
              content.style.display = content.dataset.tabContent === target ? 'block' : 'none';
            });
            
            // Update button styles manually
            tabButtons.forEach(b => {
              const isActive = b.dataset.tabBtn === target;
              if (isActive) {
                b.classList.add('bg-white', 'shadow-sm', 'text-blue-600', 'ring-1', 'ring-black/5');
                b.classList.remove('text-gray-600', 'hover:bg-white/50');
              } else {
                b.classList.remove('bg-white', 'shadow-sm', 'text-blue-600', 'ring-1', 'ring-black/5');
                b.classList.add('text-gray-600', 'hover:bg-white/50');
              }
            });

            // Re-init charts now that their container might be visible
            if (window.reinitDashboardCharts) {
              setTimeout(window.reinitDashboardCharts, 10);
            }
          }
        });
      });
    }

    const isDark = document.body.classList.contains('dark') || document.body.classList.contains('dark');
    const allowed = Number(dataNode?.dataset.allowed || 0);
    const denied = Number(dataNode?.dataset.denied || 0);
    const homeownerStatusData = JSON.parse(dataNode?.dataset.homeownerStatuses || '[]');

    // Avoid duplicate charts/listeners on repeated page loads.
    if (!window.__vsDashboardCharts) {
      window.__vsDashboardCharts = { statusPie: null, homeownerStatusPie: null, weeklyLine: null, stackedResizeHandler: null };
    }

    const showCanvasMessage = (canvas, tone, title, subtitle) => {
      if (!canvas || !canvas.parentElement) return;
      const toneClass = tone === 'error' ? 'text-red-500' : 'text-gray-400';
      canvas.parentElement.innerHTML = `<div class="flex flex-col items-center justify-center h-full ${toneClass}"><p class="text-sm font-medium">${title}</p><p class="text-xs mt-1">${subtitle}</p></div>`;
    };

    const waitForChartJS = () => {
      const maxAttempts = 25;
      let attempts = 0;

      const tick = () => {
        if (typeof Chart !== 'undefined') {
          initializeDashboardCharts();
          return;
        }
        attempts += 1;
        if (attempts >= maxAttempts) {
          showCanvasMessage(statusCtx, 'error', 'Chart library not loaded', 'Please refresh the page.');
          showCanvasMessage(weeklyCtx, 'error', 'Chart library not loaded', 'Please refresh the page.');
          return;
        }
        setTimeout(tick, 200);
      };

      tick();
    };

    const initializeDashboardCharts = () => {
      if (statusCtx) {
        if (window.__vsDashboardCharts.statusPie) {
          window.__vsDashboardCharts.statusPie.destroy();
          window.__vsDashboardCharts.statusPie = null;
        }

        const total = allowed + denied;
        if (total === 0) {
          showCanvasMessage(statusCtx, 'neutral', 'No activity data today', 'Chart will display once logs are recorded.');
        } else {
          window.__vsDashboardCharts.statusPie = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
              labels: ['Entries', 'Exits'],
              datasets: [{
                data: [allowed, denied],
                backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(59, 130, 246, 0.8)'],
                borderColor: ['rgb(16, 185, 129)', 'rgb(59, 130, 246)'],
                borderWidth: 2,
                hoverOffset: 8
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 20,
                    font: { size: 13, weight: '500' },
                    color: isDark ? '#cbd5e1' : '#374151',
                    usePointStyle: true,
                    pointStyle: 'circle'
                  }
                },
                tooltip: {
                  backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(0, 0, 0, 0.8)',
                  titleColor: isDark ? '#f1f5f9' : '#ffffff',
                  bodyColor: isDark ? '#cbd5e1' : '#ffffff',
                  padding: 12,
                  titleFont: { size: 14, weight: 'bold' },
                  bodyFont: { size: 13 },
                  callbacks: {
                    label: function (context) {
                      const datasetTotal = context.dataset.data.reduce((a, b) => a + b, 0);
                      const percentage = datasetTotal > 0 ? ((context.parsed / datasetTotal) * 100).toFixed(1) : '0.0';
                      return `${context.label}: ${context.parsed} (${percentage}%)`;
                    }
                  }
                }
              },
              animation: {
                animateRotate: true,
                animateScale: true
              }
            }
          });
        }
      }

      if (homeownerStatusCtx) {
        if (window.__vsDashboardCharts.homeownerStatusPie) {
          window.__vsDashboardCharts.homeownerStatusPie.destroy();
          window.__vsDashboardCharts.homeownerStatusPie = null;
        }

        if (homeownerStatusData.length === 0) {
          showCanvasMessage(homeownerStatusCtx, 'neutral', 'No homeowner data', 'Chart will display once homeowners are registered.');
        } else {
          const labels = homeownerStatusData.map(h => h.account_status.charAt(0).toUpperCase() + h.account_status.slice(1));
          const counts = homeownerStatusData.map(h => parseInt(h.count));
          const backgroundColors = homeownerStatusData.map(h => {
            const s = h.account_status.toLowerCase();
            if (s === 'approved') return 'rgba(34, 197, 94, 0.8)';
            if (s === 'pending') return 'rgba(245, 158, 11, 0.8)';
            if (s === 'suspended') return 'rgba(239, 68, 68, 0.8)';
            return 'rgba(100, 116, 139, 0.8)';
          });
          const borderColors = homeownerStatusData.map(h => {
            const s = h.account_status.toLowerCase();
            if (s === 'approved') return 'rgb(34, 197, 94)';
            if (s === 'pending') return 'rgb(245, 158, 11)';
            if (s === 'suspended') return 'rgb(239, 68, 68)';
            return 'rgb(100, 116, 139)';
          });

          window.__vsDashboardCharts.homeownerStatusPie = new Chart(homeownerStatusCtx, {
            type: 'pie',
            data: {
              labels: labels,
              datasets: [{
                data: counts,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 2,
                hoverOffset: 8
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 20,
                    font: { size: 13, weight: '500' },
                    color: isDark ? '#cbd5e1' : '#374151',
                    usePointStyle: true,
                    pointStyle: 'circle'
                  }
                },
                tooltip: {
                  backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(0, 0, 0, 0.8)',
                  padding: 12,
                  callbacks: {
                    label: function (context) {
                      const datasetTotal = context.dataset.data.reduce((a, b) => a + b, 0);
                      const percentage = datasetTotal > 0 ? ((context.parsed / datasetTotal) * 100).toFixed(1) : '0.0';
                      return `${context.label}: ${context.parsed} (${percentage}%)`;
                    }
                  }
                }
              }
            }
          });
        }
      }

      fetch('api/get_weekly_stats.php', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
      })
        .then((res) => {
          if (!res.ok) {
            return res.json().then((err) => {
              throw new Error(err.error || `HTTP ${res.status}`);
            });
          }
          return res.json();
        })
        .then((data) => {
          if (!weeklyCtx) return;

          if (window.__vsDashboardCharts.weeklyLine) {
            window.__vsDashboardCharts.weeklyLine.destroy();
            window.__vsDashboardCharts.weeklyLine = null;
          }

          if (!data.success) {
            showCanvasMessage(weeklyCtx, 'error', 'Failed to load chart data', data.error || 'Unknown error');
            return;
          }

          const hasData = Array.isArray(data.values) && data.values.some((v) => Number(v) > 0);
          if (!hasData) {
            showCanvasMessage(weeklyCtx, 'neutral', 'No activity in the last 7 days', 'Chart will display once logs are recorded.');
            return;
          }

          window.__vsDashboardCharts.weeklyLine = new Chart(weeklyCtx, {
            type: 'line',
            data: {
              labels: data.labels,
              datasets: [{
                label: 'Daily Access Entries',
                data: data.values,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverBackgroundColor: 'rgb(59, 130, 246)',
                pointHoverBorderColor: '#fff'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              interaction: {
                mode: 'index',
                intersect: false
              },
              plugins: {
                legend: {
                  display: true,
                  position: 'top',
                  align: 'end',
                  labels: {
                    boxWidth: 12,
                    boxHeight: 12,
                    padding: 15,
                    font: { size: 12, weight: '500' },
                    color: isDark ? '#cbd5e1' : '#374151',
                    usePointStyle: true
                  }
                },
                tooltip: {
                  backgroundColor: isDark ? 'rgba(15, 23, 42, 0.95)' : 'rgba(0, 0, 0, 0.8)',
                  titleColor: isDark ? '#f1f5f9' : '#ffffff',
                  bodyColor: isDark ? '#cbd5e1' : '#ffffff',
                  padding: 12,
                  titleFont: { size: 14, weight: 'bold' },
                  bodyFont: { size: 13 },
                  mode: 'index',
                  intersect: false
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    precision: 0,
                    font: { size: 11 },
                    color: isDark ? '#94a3b8' : '#6b7280'
                  },
                  grid: {
                    color: isDark ? 'rgba(100, 116, 139, 0.15)' : 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
                  }
                },
                x: {
                  ticks: {
                    font: { size: 11 },
                    color: isDark ? '#94a3b8' : '#6b7280'
                  },
                  grid: {
                    display: false,
                    drawBorder: false
                  }
                }
              },
              animation: {
                duration: 750,
                easing: 'easeInOutQuart'
              }
            }
          });
        })
        .catch((err) => {
          console.error('[Dashboard] Failed to load weekly stats:', err);
          showCanvasMessage(weeklyCtx, 'error', 'Network Error', err.message || 'Failed to load weekly data.');
        });
    };

    const parseStackedSeries = (raw) => {
      try {
        const parsed = JSON.parse(raw || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch (err) {
        console.error('[Dashboard] Invalid stacked chart data:', err);
        return [];
      }
    };

    const initializeStackedCharts = () => {
      adminPanelLog('[DASHBOARD] Initializing stacked charts via Chart.js');
      const homeownerData = parseStackedSeries(stackedDataNode?.dataset.homeowner);
      const accessData = parseStackedSeries(stackedDataNode?.dataset.access);
      const vehicleData = parseStackedSeries(stackedDataNode?.dataset.vehicle);

      if (!window.__vsDashboardCharts.stacked) {
        window.__vsDashboardCharts.stacked = { homeowners: null, access: null, vehicles: null };
      }

      // 1. Homeowners Chart
      if (homeownerSvg) {
        if (window.__vsDashboardCharts.stacked.homeowners) window.__vsDashboardCharts.stacked.homeowners.destroy();
        window.__vsDashboardCharts.stacked.homeowners = new Chart(homeownerSvg, {
          type: 'bar',
          data: {
            labels: homeownerData.map(d => d.month),
            datasets: [
              {
                label: 'Approved',
                data: homeownerData.map(d => d.approved),
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgb(59, 130, 246)',
                borderWidth: 1,
                borderRadius: 4
              },
              {
                label: 'Pending',
                data: homeownerData.map(d => d.pending),
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: 'rgb(245, 158, 11)',
                borderWidth: 1,
                borderRadius: 4
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { stacked: true, grid: { display: false }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } },
              y: { stacked: true, grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: isDark ? '#cbd5e1' : '#374151', usePointStyle: true } },
              tooltip: { backgroundColor: isDark ? '#1e293b' : '#0f172a', padding: 10 }
            }
          }
        });
      }

      // 2. Access Chart
      if (accessSvg) {
        if (window.__vsDashboardCharts.stacked.access) window.__vsDashboardCharts.stacked.access.destroy();
        window.__vsDashboardCharts.stacked.access = new Chart(accessSvg, {
          type: 'bar',
          data: {
            labels: accessData.map(d => d.month),
            datasets: [
              {
                label: 'Entries',
                data: accessData.map(d => d.entries),
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgb(16, 185, 129)',
                borderWidth: 1,
                borderRadius: 4
              },
              {
                label: 'Exits',
                data: accessData.map(d => d.exits),
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                borderColor: 'rgb(239, 68, 68)',
                borderWidth: 1,
                borderRadius: 4
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { stacked: true, grid: { display: false }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } },
              y: { stacked: true, grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: isDark ? '#cbd5e1' : '#374151', usePointStyle: true } },
              tooltip: { backgroundColor: isDark ? '#1e293b' : '#0f172a', padding: 10 }
            }
          }
        });
      }

      // 3. Vehicles Chart
      if (vehicleSvg) {
        if (window.__vsDashboardCharts.stacked.vehicles) window.__vsDashboardCharts.stacked.vehicles.destroy();
        window.__vsDashboardCharts.stacked.vehicles = new Chart(vehicleSvg, {
          type: 'bar',
          data: {
            labels: vehicleData.map(d => d.month),
            datasets: [
              {
                label: 'Registrations',
                data: vehicleData.map(d => d.count),
                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                borderColor: 'rgb(139, 92, 246)',
                borderWidth: 1,
                borderRadius: 4
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: { grid: { display: false }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } },
              y: { grid: { color: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#94a3b8' : '#64748b' } }
            },
            plugins: {
              legend: { position: 'bottom', labels: { color: isDark ? '#cbd5e1' : '#374151', usePointStyle: true } },
              tooltip: { backgroundColor: isDark ? '#1e293b' : '#0f172a', padding: 10 }
            }
          }
        });
      }
    };

    // Expose re-init function globally so it can be called on tab switch
    window.reinitDashboardCharts = initializeStackedCharts;

    if (!window.__vsDashboardCharts.stackedResizeHandler) {
      window.__vsDashboardCharts.stackedResizeHandler = () => {
        if (window.reinitDashboardCharts) window.reinitDashboardCharts();
      };
      window.addEventListener('resize', window.__vsDashboardCharts.stackedResizeHandler);
    }

    initializeStackedCharts();

    waitForChartJS();
  }

  /* ---------- Database Backup Button ---------- */
  if (backupBtn) {
    backupBtn.addEventListener('click', async () => {
      adminPanelLog('[ADMIN] Database backup triggered');

      const originalContent = backupBtn.innerHTML;
      backupBtn.disabled = true;
      backupBtn.innerHTML = `
        <svg class="h-4 w-4 flex-shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        <span class="sidebar-text">Creating Backup...</span>
      `;

      try {
        const response = await fetch('utilities/backup_database.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ csrf_token: window.__ADMIN_CSRF__ })
        });

        if (!response.ok) {
          throw new Error('Backup request failed');
        }

        const result = await response.json();

        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'Backup Created',
            html: `
              <p class="text-sm text-gray-600">Database backup completed successfully!</p>
              <div class="mt-3 p-3 bg-gray-50 rounded text-left text-sm">
                <p><strong>Filename:</strong> ${result.filename}</p>
                <p><strong>Size:</strong> ${result.size}</p>
              </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#3b82f6'
          });
        } else {
          throw new Error(result.message || 'Backup failed');
        }
      } catch (error) {
        console.error('[ADMIN] Backup error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Backup Failed',
          text: error.message || 'Failed to create database backup',
          confirmButtonText: 'OK',
          confirmButtonColor: '#ef4444'
        });
      } finally {
        backupBtn.disabled = false;
        backupBtn.innerHTML = originalContent;
      }
    });
  }

  /* ---------- Make functions globally accessible ---------- */
  window.loadPage = loadPage;
  window.showGrowl = showGrowl;
  window.exportTableToCSV = exportTableToCSV;
});

