// guard/js/guard_side.js

const guardLog = (...args) => {
  if (typeof window.__vsLog === 'function') {
    window.__vsLog(...args);
  } else if (window.vehiscanConfig && window.vehiscanConfig.debug) {
    console.log(...args);
  }
};

  // Secure CSRF Token Retrieval - Get from meta tag instead of window scope
  window.getCsrfToken = function() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') : '';
  };

// SweetAlert2 Fallback - Must be defined before any Swal usage
if (typeof Swal === 'undefined') {
  console.warn('[GUARD] SweetAlert2 not loaded, using fallback alert/confirm');
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
    }
  };
} else {
  guardLog('[GUARD] SweetAlert2 loaded successfully');
}

const hasKeyboardRegistry = !!(window.keyboardShortcuts && typeof window.keyboardShortcuts.register === 'function');
const FILTER_ACTIVE_CLASS = 'toggle-active';
const GUARD_DEFAULT_DASHBOARD_TITLE = 'VehiScan';

const resolveGuardBaseUrl = () => {
  const configuredBase = window.vehiscanConfig?.baseUrl || window.baseUrl;
  if (configuredBase) {
    if (/^https?:\/\//i.test(configuredBase)) return configuredBase;
    return configuredBase.startsWith('/')
      ? window.location.origin + configuredBase
      : window.location.origin + '/' + configuredBase.replace(/^\/+/, '');
  }

  const path = window.location.pathname || '';
  const idx = path.indexOf('/guard/');
  const appBasePath = idx >= 0 ? path.slice(0, idx) : '';
  return window.location.origin + appBasePath + '/';
};

/* ---------- Global Session Expiration Handler ---------- */
if (!window.__fetchPatched) {
  window.__fetchPatched = true;
  const originalFetch = window.fetch;
  window.fetch = async function (...args) {
    const response = await originalFetch.apply(this, args);

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
          return new Response(JSON.stringify(json), { status: 403 });
        }
      } catch (e) {
        // Not JSON or other error, continue with original response
      }
    }

    return response;
  };
} // end __fetchPatched guard

/* ---------- No Session Timeout for Guard (24/7 Access) ---------- */
// Guard needs to be logged in 24/7, so no timeout warnings
// Only logout when explicitly requested

document.addEventListener('DOMContentLoaded', function () {
  // Use global logger provided by `logger.js`
  __vsLog('[GUARD] Initializing guard panel...');

  // ====== MOBILE MENU TOGGLE ======
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mobileOverlay = document.getElementById('mobile-overlay');
  const sidebar = document.getElementById('sidebar');
  const menuItems = document.querySelectorAll('.menu-item[data-page]');

  if (mobileMenuBtn && mobileOverlay && sidebar) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      sidebar.classList.toggle('mobile-open');
      mobileOverlay.classList.toggle('active');
      __vsLog('[GUARD] Mobile menu toggled');
    });

    mobileOverlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      mobileOverlay.classList.remove('active');
      __vsLog('[GUARD] Mobile overlay clicked - menu closed');
    });

    __vsLog('[GUARD] Mobile menu initialized');
  }

  const pageTitle = document.getElementById('page-title');
  const dashboardTitleText = document.getElementById('guardDashboardTitleText');
  const guardDisplayNameText = document.getElementById('guardDisplayNameText');
  const editDashboardTitleBtn = document.getElementById('editDashboardTitleBtn');
  const editDashboardTitleBtnSidebar = document.getElementById('editDashboardTitleBtnSidebar');
  const editGuardNameBtn = document.getElementById('editGuardNameBtn');
  const guardDetailRail = document.getElementById('guardDetailRail');
  const guardDetailToggleBtn = document.getElementById('guardDetailToggleBtn');
  const guardDetailToggleLabel = document.getElementById('guardDetailToggleLabel');
  const guardDetailEmpty = document.getElementById('guardDetailEmpty');
  const guardDetailContent = document.getElementById('guardDetailContent');
  const guardDetailStatus = document.getElementById('guardDetailStatus');
  const guardDetailOwnerImg = document.getElementById('guardDetailOwnerImg');
  const guardDetailCarImg = document.getElementById('guardDetailCarImg');
  const guardDetailPlate = document.getElementById('guardDetailPlate');
  const guardDetailHomeowner = document.getElementById('guardDetailHomeowner');
  const guardDetailVehicle = document.getElementById('guardDetailVehicle');
  const guardDetailColor = document.getElementById('guardDetailColor');
  const guardDetailTime = document.getElementById('guardDetailTime');
  const guardDetailDuration = document.getElementById('guardDetailDuration');
  const guardDetailViewHistoryBtn = document.getElementById('guardDetailViewHistoryBtn');
  const guardDetailVehicleNavigator = document.getElementById('guardDetailVehicleNavigator');
  const guardDetailVehiclePosition = document.getElementById('guardDetailVehiclePosition');
  const guardDetailVehiclePrevBtn = document.getElementById('guardDetailVehiclePrev');
  const guardDetailVehicleNextBtn = document.getElementById('guardDetailVehicleNext');
  const guardDetailVehicleMatch = document.getElementById('guardDetailVehicleMatch');
  const guardDetailNavPlate = document.getElementById('guardDetailNavPlate');
  const guardDetailNavVehicle = document.getElementById('guardDetailNavVehicle');
  const guardDetailNavColor = document.getElementById('guardDetailNavColor');

  let selectedGuardLogEntry = null;
  let guardDetailVehicles = [];
  let guardDetailVehicleIndex = 0;
  let guardDetailVehicleContextKey = '';
  let guardDetailVehicleRequestToken = 0;
  const GUARD_DETAIL_MINIMIZED_KEY = 'guardDetailMinimized';

  function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
  }

  function applyGuardDetailMinimizedState(minimized, { persist = true } = {}) {
    if (!guardDetailRail) return;

    const shouldMinimize = Boolean(minimized) && isMobileViewport();
    guardDetailRail.classList.toggle('is-minimized', shouldMinimize);

    if (guardDetailToggleBtn) {
      guardDetailToggleBtn.setAttribute('aria-expanded', shouldMinimize ? 'false' : 'true');
      guardDetailToggleBtn.setAttribute('aria-label', shouldMinimize ? 'Expand entry details' : 'Minimize entry details');
    }
    if (guardDetailToggleLabel) {
      guardDetailToggleLabel.textContent = shouldMinimize ? 'Expand' : 'Minimize';
    }

    if (persist) {
      try {
        localStorage.setItem(GUARD_DETAIL_MINIMIZED_KEY, shouldMinimize ? '1' : '0');
      } catch (_) {
        // Ignore storage errors in restricted browsing contexts.
      }
    }
  }

  function getStoredGuardDetailMinimized() {
    try {
      const stored = localStorage.getItem(GUARD_DETAIL_MINIMIZED_KEY);
      if (stored === null) {
        // Default to compact rail on first mobile visit.
        return isMobileViewport();
      }
      return stored === '1';
    } catch (_) {
      return isMobileViewport();
    }
  }

  const guardUiPreferences = {
    dashboardTitle: GUARD_DEFAULT_DASHBOARD_TITLE,
    displayName: (guardDisplayNameText?.textContent || '').trim() || 'Guard'
  };

  function normalizePreferenceText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function applyGuardUiPreferences() {
    if (dashboardTitleText) {
      dashboardTitleText.textContent = guardUiPreferences.dashboardTitle || GUARD_DEFAULT_DASHBOARD_TITLE;
    }

    if (guardDisplayNameText) {
      guardDisplayNameText.textContent = guardUiPreferences.displayName || 'Guard';
    }

    const activePage = document.querySelector('.page-content.active')?.id || '';
    if (activePage === 'page-logs' && pageTitle) {
      pageTitle.textContent = guardUiPreferences.dashboardTitle || GUARD_DEFAULT_DASHBOARD_TITLE;
    }
  }

  async function loadGuardUiPreferences() {
    try {
      const res = await fetch('../api/get_ui_preferences.php', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      if (!res.ok) return;
      const json = await res.json();
      if (!json.success || !json.data) return;

      const title = normalizePreferenceText(json.data.dashboard_title);
      const displayName = normalizePreferenceText(json.data.display_name);

      if (title) {
        guardUiPreferences.dashboardTitle = title;
      }
      if (displayName) {
        guardUiPreferences.displayName = displayName;
      }

      applyGuardUiPreferences();
    } catch (err) {
      console.warn('[GUARD] Failed to load UI preferences:', err);
    }
  }

  async function saveGuardUiPreferences(payload) {
    const res = await fetch('../api/update_ui_preferences.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        csrf_token: getCsrfToken(),
        ...payload
      })
    });

    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.success || !json.data) {
      throw new Error(json.message || 'Unable to save preferences');
    }

    guardUiPreferences.dashboardTitle = normalizePreferenceText(json.data.dashboard_title) || GUARD_DEFAULT_DASHBOARD_TITLE;
    guardUiPreferences.displayName = normalizePreferenceText(json.data.display_name) || 'Guard';
    applyGuardUiPreferences();
  }

  async function promptDashboardTitleEdit() {
    const result = await Swal.fire({
      title: 'Edit Dashboard Title',
      input: 'text',
      inputValue: guardUiPreferences.dashboardTitle,
      inputLabel: 'Dashboard title',
      inputPlaceholder: 'Enter dashboard title',
      showCancelButton: true,
      confirmButtonText: 'Save',
      cancelButtonText: 'Cancel',
      inputValidator: (value) => {
        const normalized = normalizePreferenceText(value);
        if (normalized.length < 3 || normalized.length > 80) {
          return 'Title must be 3-80 characters';
        }
        return null;
      },
      heightAuto: false
    });

    if (!result.isConfirmed) return;
    await saveGuardUiPreferences({ dashboard_title: normalizePreferenceText(result.value) });
    showGrowl('Dashboard title updated', 'success');
  }

  async function promptGuardNameEdit() {
    const result = await Swal.fire({
      title: 'Edit Guard Name',
      input: 'text',
      inputValue: guardUiPreferences.displayName,
      inputLabel: 'Display name',
      inputPlaceholder: 'Enter display name',
      showCancelButton: true,
      confirmButtonText: 'Save',
      cancelButtonText: 'Cancel',
      inputValidator: (value) => {
        const normalized = normalizePreferenceText(value);
        if (normalized.length < 2 || normalized.length > 80) {
          return 'Guard name must be 2-80 characters';
        }
        return null;
      },
      heightAuto: false
    });

    if (!result.isConfirmed) return;
    await saveGuardUiPreferences({ display_name: normalizePreferenceText(result.value) });
    showGrowl('Guard name updated', 'success');
  }

  [editDashboardTitleBtn, editDashboardTitleBtnSidebar].forEach((btn) => {
    btn?.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      try {
        await promptDashboardTitleEdit();
      } catch (err) {
        showGrowl(err.message || 'Failed to update dashboard title', 'error');
      }
    });
  });

  editGuardNameBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    try {
      await promptGuardNameEdit();
    } catch (err) {
      showGrowl(err.message || 'Failed to update guard name', 'error');
    }
  });

  applyGuardUiPreferences();

  function clearSelectedLogRow() {
    document.querySelectorAll('.log-row.log-row-selected').forEach((row) => {
      row.classList.remove('log-row-selected');
    });
  }

  function clearFlagRowHighlight(row) {
    if (!row) return;
    row.classList.remove('log-row-selected');
  }

  function setGuardDetailPlaceholder() {
    if (!guardDetailRail) return;
    if (guardDetailEmpty) {
      guardDetailEmpty.classList.remove('hidden');
    }
    if (guardDetailContent) {
      guardDetailContent.classList.add('hidden');
    }
    selectedGuardLogEntry = null;
    guardDetailVehicles = [];
    guardDetailVehicleIndex = 0;
    guardDetailVehicleContextKey = '';
    guardDetailVehicleRequestToken += 1;
    updateGuardDetailVehicleNavigator();
    clearSelectedLogRow();
  }

  function renderDetailStatus(status) {
    const normalized = String(status || '').toUpperCase() === 'IN' ? 'IN' : 'OUT';
    const icon = normalized === 'IN'
      ? '<svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>'
      : '<svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>';
    return {
      statusClass: normalized === 'IN' ? 'status-in' : 'status-out',
      html: `${icon}<span>${normalized}</span>`
    };
  }

  function normalizeImagePath(value) {
    return String(value || '').trim();
  }

  function normalizePlateValue(value) {
    return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
  }

  function getGuardDetailVehicleContextKey(entry) {
    if (!entry) return '';
    const homeownerId = Number.parseInt(entry.homeownerId || '0', 10) || 0;
    const normalizedPlate = normalizePlateValue(entry.plate || '');
    return `${homeownerId}:${normalizedPlate}`;
  }

  function toGuardImageUrl(path, fallback) {
    const normalizedPath = normalizeImagePath(path);
    if (!normalizedPath) return fallback;
    if (/^data:image\//i.test(normalizedPath) || /^https?:\/\//i.test(normalizedPath)) {
      return normalizedPath;
    }

    let relativePath = normalizedPath.replace(/^\/+/, '');
    if (!/^uploads\//i.test(relativePath)) {
      relativePath = `uploads/${relativePath}`;
    }

    const base = resolveGuardBaseUrl();
    return `${base.replace(/\/$/, '')}/${relativePath}`;
  }

  function updateGuardDetailMedia(ownerImgPath, carImgPath) {
    if (guardDetailOwnerImg) {
      guardDetailOwnerImg.src = toGuardImageUrl(ownerImgPath, PLACEHOLDER_OWNER);
      guardDetailOwnerImg.onerror = function () {
        this.src = PLACEHOLDER_OWNER;
      };
    }

    if (guardDetailCarImg) {
      guardDetailCarImg.src = toGuardImageUrl(carImgPath, PLACEHOLDER_CAR);
      guardDetailCarImg.onerror = function () {
        this.src = PLACEHOLDER_CAR;
      };
    }
  }

  function applyGuardDetailVehicleSelection(selectedVehicle) {
    if (!selectedGuardLogEntry) return;

    const normalizedVehicle = selectedVehicle || {};
    const plateText = normalizePreferenceText(normalizedVehicle.plate_number || '') || selectedGuardLogEntry.plate || '-';
    const vehicleText = normalizePreferenceText(normalizedVehicle.vehicle_type || '') || selectedGuardLogEntry.vehicle || '-';
    const colorText = normalizePreferenceText(normalizedVehicle.color || '') || selectedGuardLogEntry.color || '-';

    if (guardDetailPlate) guardDetailPlate.textContent = plateText;
    if (guardDetailVehicle) guardDetailVehicle.textContent = vehicleText;
    if (guardDetailColor) guardDetailColor.textContent = colorText;

    if (guardDetailCarImg) {
      const selectedCarImage = normalizeImagePath(normalizedVehicle.vehicle_img || selectedGuardLogEntry.carImg || '');
      guardDetailCarImg.src = toGuardImageUrl(selectedCarImage, PLACEHOLDER_CAR);
      guardDetailCarImg.onerror = function () {
        this.src = PLACEHOLDER_CAR;
      };
    }
  }

  function updateGuardDetailVehicleNavigator() {
    if (!guardDetailVehicleNavigator) return;

    guardDetailVehicleNavigator.classList.remove('hidden');

    const total = guardDetailVehicles.length;
    if (!total) {
      if (guardDetailVehiclePosition) guardDetailVehiclePosition.textContent = 'Vehicle 0 of 0';
      if (guardDetailNavPlate) guardDetailNavPlate.textContent = '-';
      if (guardDetailNavVehicle) guardDetailNavVehicle.textContent = '-';
      if (guardDetailNavColor) guardDetailNavColor.textContent = '-';
      if (guardDetailVehicleMatch) {
        guardDetailVehicleMatch.classList.remove('success', 'warning', 'info');
        guardDetailVehicleMatch.classList.add('info');
        guardDetailVehicleMatch.textContent = 'No linked vehicles found';
      }
      if (guardDetailVehiclePrevBtn) guardDetailVehiclePrevBtn.disabled = true;
      if (guardDetailVehicleNextBtn) guardDetailVehicleNextBtn.disabled = true;
      return;
    }

    guardDetailVehicleIndex = ((guardDetailVehicleIndex % total) + total) % total;
    const selectedVehicle = guardDetailVehicles[guardDetailVehicleIndex] || {};

    if (guardDetailVehiclePosition) {
      guardDetailVehiclePosition.textContent = `Vehicle ${guardDetailVehicleIndex + 1} of ${total}`;
    }
    if (guardDetailNavPlate) guardDetailNavPlate.textContent = selectedVehicle.plate_number || '-';
    if (guardDetailNavVehicle) guardDetailNavVehicle.textContent = selectedVehicle.vehicle_type || '-';
    if (guardDetailNavColor) guardDetailNavColor.textContent = selectedVehicle.color || '-';
    applyGuardDetailVehicleSelection(selectedVehicle);

    const selectedNormalizedPlate = normalizePlateValue(selectedVehicle.plate_number || '');
    const scannedNormalizedPlate = normalizePlateValue(selectedGuardLogEntry?.plate || '');
    const isMatchedPlate = selectedNormalizedPlate !== '' && scannedNormalizedPlate !== '' && selectedNormalizedPlate === scannedNormalizedPlate;

    if (guardDetailVehicleMatch) {
      guardDetailVehicleMatch.classList.remove('success', 'warning', 'info');
      if (isMatchedPlate) {
        guardDetailVehicleMatch.classList.add('success');
        guardDetailVehicleMatch.textContent = 'Plate matches scan';
      } else {
        guardDetailVehicleMatch.classList.add('warning');
        guardDetailVehicleMatch.textContent = 'Different from scanned plate';
      }
    }

    const disableNavigation = total <= 1;
    if (guardDetailVehiclePrevBtn) guardDetailVehiclePrevBtn.disabled = disableNavigation;
    if (guardDetailVehicleNextBtn) guardDetailVehicleNextBtn.disabled = disableNavigation;
  }

  async function fetchGuardHomeownerVehicles(params = {}) {
    const qs = new URLSearchParams();
    const homeownerId = Number.parseInt(params.homeownerId, 10) || 0;
    const plate = normalizePreferenceText(params.plate || '');

    if (homeownerId > 0) {
      qs.set('homeowner_id', String(homeownerId));
    } else if (plate) {
      qs.set('plate', plate);
    } else {
      return [];
    }

    const res = await fetch(`../fetch/fetch_homeowner_vehicles.php?${qs.toString()}`, {
      credentials: 'same-origin'
    });
    const json = await res.json();
    if (!res.ok || !json.success) {
      throw new Error(json.message || 'Failed to fetch vehicles');
    }

    return Array.isArray(json.vehicles) ? json.vehicles : [];
  }

  async function loadGuardDetailVehicles(entry) {
    if (!guardDetailVehicleNavigator) return;

    const requestToken = ++guardDetailVehicleRequestToken;
    guardDetailVehicles = [];
    guardDetailVehicleIndex = 0;

    if (guardDetailVehicleMatch) {
      guardDetailVehicleMatch.classList.remove('success', 'warning', 'info');
      guardDetailVehicleMatch.classList.add('info');
      guardDetailVehicleMatch.textContent = 'Loading linked vehicles...';
    }
    if (guardDetailVehiclePosition) {
      guardDetailVehiclePosition.textContent = 'Loading...';
    }
    if (guardDetailVehiclePrevBtn) guardDetailVehiclePrevBtn.disabled = true;
    if (guardDetailVehicleNextBtn) guardDetailVehicleNextBtn.disabled = true;
    if (guardDetailNavPlate) guardDetailNavPlate.textContent = '-';
    if (guardDetailNavVehicle) guardDetailNavVehicle.textContent = '-';
    if (guardDetailNavColor) guardDetailNavColor.textContent = '-';
    guardDetailVehicleNavigator.classList.remove('hidden');

    if (!entry) {
      guardDetailVehicleContextKey = '';
      updateGuardDetailVehicleNavigator();
      return;
    }

    const contextKey = getGuardDetailVehicleContextKey(entry);

    try {
      const vehicles = await fetchGuardHomeownerVehicles({
        homeownerId: entry.homeownerId,
        plate: entry.plate
      });

      if (requestToken !== guardDetailVehicleRequestToken) {
        return;
      }

      guardDetailVehicles = vehicles;
      const targetPlate = normalizePlateValue(entry.plate || '');
      const matchedIndex = guardDetailVehicles.findIndex((vehicle) => normalizePlateValue(vehicle.plate_number || '') === targetPlate);
      guardDetailVehicleIndex = matchedIndex >= 0 ? matchedIndex : 0;
      guardDetailVehicleContextKey = contextKey;
      updateGuardDetailVehicleNavigator();
    } catch (error) {
      if (requestToken !== guardDetailVehicleRequestToken) {
        return;
      }
      console.error('[GUARD] loadGuardDetailVehicles error:', error);
      guardDetailVehicles = [];
      guardDetailVehicleIndex = 0;
      updateGuardDetailVehicleNavigator();
      if (guardDetailVehicleMatch) {
        guardDetailVehicleMatch.classList.remove('success', 'warning', 'info');
        guardDetailVehicleMatch.classList.add('warning');
        guardDetailVehicleMatch.textContent = 'Unable to load linked vehicles';
      }
      applyGuardDetailVehicleSelection(null);
    }
  }

  function renderGuardDetailEntry(entry, row, options = {}) {
    if (!guardDetailRail || !entry) return;

    const shouldAutoExpand = options.autoExpand !== false;
    const shouldScrollIntoView = options.scrollIntoView !== false;

    if (shouldAutoExpand && guardDetailRail.classList.contains('is-minimized')) {
      applyGuardDetailMinimizedState(false, { persist: false });
    }

    selectedGuardLogEntry = {
      ...entry,
      homeownerId: Number.parseInt(entry.homeownerId || '0', 10) || 0
    };

    clearSelectedLogRow();
    if (row) {
      row.classList.add('log-row-selected');
    }

    if (guardDetailPlate) guardDetailPlate.textContent = entry.plate || '-';
    if (guardDetailHomeowner) guardDetailHomeowner.textContent = entry.name || '-';
    if (guardDetailVehicle) guardDetailVehicle.textContent = entry.vehicle || '-';
    if (guardDetailColor) guardDetailColor.textContent = entry.color || '-';
    if (guardDetailTime) guardDetailTime.textContent = entry.time || '-';
    if (guardDetailDuration) guardDetailDuration.textContent = entry.duration || '-';

    updateGuardDetailMedia(entry.ownerImg, entry.carImg);
    const contextKey = getGuardDetailVehicleContextKey(selectedGuardLogEntry);
    const shouldReloadVehicles = !guardDetailVehicles.length || guardDetailVehicleContextKey !== contextKey;
    if (shouldReloadVehicles) {
      loadGuardDetailVehicles(selectedGuardLogEntry);
    } else {
      updateGuardDetailVehicleNavigator();
    }

    if (guardDetailStatus) {
      const rendered = renderDetailStatus(entry.status || 'OUT');
      guardDetailStatus.classList.remove('status-in', 'status-out');
      guardDetailStatus.classList.add(rendered.statusClass);
      guardDetailStatus.innerHTML = rendered.html;
    }

    guardDetailEmpty?.classList.add('hidden');
    guardDetailContent?.classList.remove('hidden');
    if (guardDetailContent) {
      guardDetailContent.classList.remove('guard-detail-refresh');
      guardDetailContent.offsetHeight;
      guardDetailContent.classList.add('guard-detail-refresh');
    }

    if (shouldScrollIntoView && isMobileViewport()) {
      guardDetailRail.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function showGuardLogDetail(row, options = {}) {
    if (!row || !guardDetailRail) return;
    const name = normalizePreferenceText(row.dataset.name || '-') || '-';
    const plate = normalizePreferenceText(row.dataset.plate || '-') || '-';
    const vehicle = normalizePreferenceText(row.dataset.vehicle || '-') || '-';
    const color = normalizePreferenceText(row.dataset.color || '-') || '-';
    const time = normalizePreferenceText(row.dataset.time || '-') || '-';
    const duration = normalizePreferenceText(row.dataset.duration || '-') || '-';
    const status = normalizePreferenceText(row.dataset.status || 'OUT') || 'OUT';
    const homeownerId = Number.parseInt(row.dataset.homeownerId || '0', 10) || 0;
    const logId = Number.parseInt(row.dataset.logId || '0', 10) || 0;
    const ownerImg = normalizeImagePath(row.dataset.ownerImg || '');
    const carImg = normalizeImagePath(row.dataset.carImg || '');

    renderGuardDetailEntry({
      logId,
      name,
      plate,
      vehicle,
      color,
      time,
      duration,
      status,
      homeownerId,
      ownerImg,
      carImg
    }, row, options);
  }

  function syncGuardDetailFromVisibleLogs() {
    const rows = Array.from(document.querySelectorAll('#logsContainerWrapper tr.log-row[data-log-id]'));
    if (!rows.length) {
      setGuardDetailPlaceholder();
      return;
    }

    if (selectedGuardLogEntry?.logId) {
      const selectedRow = rows.find((row) => Number.parseInt(row.dataset.logId || '0', 10) === selectedGuardLogEntry.logId);
      if (selectedRow) {
        showGuardLogDetail(selectedRow, { autoExpand: false, scrollIntoView: false });
        return;
      }
    }

    showGuardLogDetail(rows[0], { autoExpand: false, scrollIntoView: false });
  }

  function showGuardRfidScanDetail(scanData) {
    if (!guardDetailRail || !scanData) return;

    const logId = Number.parseInt(String(scanData.log_id || 0), 10) || 0;
    const matchingRow = logId
      ? document.querySelector(`tr.log-row[data-log-id="${logId}"]`)
      : null;

    const fallbackDuration = String(scanData.status || '').toUpperCase() === 'IN'
      ? 'In progress'
      : 'Recent exit';

    const entry = {
      logId,
      name: normalizePreferenceText(scanData.name || '-') || '-',
      plate: normalizePreferenceText(scanData.plate_number || '-') || '-',
      vehicle: normalizePreferenceText(scanData.vehicle_type || '-') || '-',
      color: normalizePreferenceText(scanData.color || '-') || '-',
      time: normalizePreferenceText(scanData.log_time || '-') || '-',
      duration: matchingRow?.dataset.duration || fallbackDuration,
      status: normalizePreferenceText(scanData.status || 'OUT') || 'OUT',
      homeownerId: Number.parseInt(String(scanData.homeowner_id || matchingRow?.dataset.homeownerId || 0), 10) || 0,
      ownerImg: normalizeImagePath(scanData.owner_img || matchingRow?.dataset.ownerImg || ''),
      carImg: normalizeImagePath(scanData.car_img || matchingRow?.dataset.carImg || '')
    };

    renderGuardDetailEntry(entry, matchingRow);
  }

  const guardCommandShell = document.querySelector('.guard-command-shell');

  guardDetailViewHistoryBtn?.addEventListener('click', async () => {
    if (!selectedGuardLogEntry?.plate) return;
    if (typeof switchPage === 'function') {
      switchPage('logs');
    }
    window.filterLogsByUser?.(selectedGuardLogEntry.plate, selectedGuardLogEntry.name || 'Selected user');
  });

  guardDetailVehiclePrevBtn?.addEventListener('click', () => {
    if (!guardDetailVehicles.length) return;
    guardDetailVehicleIndex = (guardDetailVehicleIndex - 1 + guardDetailVehicles.length) % guardDetailVehicles.length;
    updateGuardDetailVehicleNavigator();
  });

  guardDetailVehicleNextBtn?.addEventListener('click', () => {
    if (!guardDetailVehicles.length) return;
    guardDetailVehicleIndex = (guardDetailVehicleIndex + 1) % guardDetailVehicles.length;
    updateGuardDetailVehicleNavigator();
  });

  setGuardDetailPlaceholder();

  function setGuardDetailRailVisibility(pageName) {
    if (!guardDetailRail) return;
    if (pageName === 'logs') {
      guardDetailRail.classList.remove('hidden');
      document.body.classList.remove('guard-detail-collapsed');
      guardCommandShell?.classList.remove('guard-shell-fullwidth');
      applyGuardDetailMinimizedState(getStoredGuardDetailMinimized(), { persist: false });
    } else {
      guardDetailRail.classList.add('hidden');
      document.body.classList.add('guard-detail-collapsed');
      guardCommandShell?.classList.add('guard-shell-fullwidth');
    }
  }

  guardDetailToggleBtn?.addEventListener('click', () => {
    const isMinimized = guardDetailRail?.classList.contains('is-minimized');
    applyGuardDetailMinimizedState(!isMinimized);
  });

  window.addEventListener('resize', () => {
    applyGuardDetailMinimizedState(getStoredGuardDetailMinimized(), { persist: false });
  });

  // Determine initial page from URL state (with fallback to server-rendered active state)
  const initialPage = (() => {
    const page = (new URLSearchParams(window.location.search).get('gpage') || '').trim().toLowerCase();
    const allowedPages = new Set(['logs', 'vehicles', 'camera', 'visitor']);
    return allowedPages.has(page) ? page : 'logs';
  })();
  
  setGuardDetailRailVisibility(initialPage);

  // ====== PAGE SWITCHING ======
  let visitorPassesRefreshTimer = null;
  let visitorScanRelativeTimeTimer = null;
  let visitorAutoRefreshIntervalMs = 30000;
  const VISITOR_SCAN_AUTO_REFRESH_INTERVAL_KEY = 'guard.visitorScanHistory.refreshMs';

  function normalizeVisitorAutoRefreshInterval(value) {
    const parsed = Number.parseInt(value, 10);
    if (parsed === 15000 || parsed === 60000) return parsed;
    return 30000;
  }

  function stopVisitorPassesAutoRefresh() {
    if (visitorPassesRefreshTimer) {
      clearInterval(visitorPassesRefreshTimer);
      visitorPassesRefreshTimer = null;
    }
  }

  function startVisitorPassesAutoRefresh() {
    if (visitorPassesRefreshTimer) return;

    visitorPassesRefreshTimer = setInterval(() => {
      const visitorPage = document.getElementById('page-visitor');
      if (visitorPage && visitorPage.classList.contains('active')) {
        loadVisitorPasses({ silent: true });
        loadVisitorScanHistory({ silent: true });
      }
    }, visitorAutoRefreshIntervalMs);
  }

  function restartVisitorPassesAutoRefresh() {
    stopVisitorPassesAutoRefresh();
    startVisitorPassesAutoRefresh();
  }

  function refreshVisitorScanRelativeTimeCells() {
    const tbody = document.getElementById('visitorScanHistoryBody');
    if (!tbody) return;

    const cells = tbody.querySelectorAll('td[data-scan-time="1"]');
    cells.forEach((cell) => {
      const isoValue = cell.getAttribute('data-scanned-at') || '';
      const exactValue = formatVisitorScanDateTime(isoValue);
      const displayValue = visitorScanHistoryTimeMode === 'relative'
        ? formatVisitorScanRelativeTime(isoValue)
        : exactValue;

      if (visitorScanHistoryTimeMode === 'relative') {
        cell.setAttribute('title', exactValue);
      } else {
        cell.removeAttribute('title');
      }

      cell.textContent = displayValue;
    });
  }

  function stopVisitorScanRelativeTimeAutoUpdate() {
    if (visitorScanRelativeTimeTimer) {
      clearInterval(visitorScanRelativeTimeTimer);
      visitorScanRelativeTimeTimer = null;
    }
  }

  function startVisitorScanRelativeTimeAutoUpdate() {
    if (visitorScanRelativeTimeTimer) return;

    visitorScanRelativeTimeTimer = setInterval(() => {
      if (!isVisitorPageActive() || visitorScanHistoryTimeMode !== 'relative') return;
      refreshVisitorScanRelativeTimeCells();
    }, 30000);
  }

  function restartVisitorScanRelativeTimeAutoUpdate() {
    stopVisitorScanRelativeTimeAutoUpdate();
    if (visitorScanHistoryTimeMode === 'relative' && isVisitorPageActive()) {
      startVisitorScanRelativeTimeAutoUpdate();
    }
  }

  window.switchPage = function (pageName) {
    __vsLog('[GUARD] Switching to page:', pageName);

    // Preserve URL state for reload
    const url = new URL(window.location.href);
    url.searchParams.set('gpage', pageName);
    history.replaceState({}, '', `${url.pathname}?${url.searchParams.toString()}`);

    // Get all pages
    const allPages = document.querySelectorAll('.page-content');
    const targetPage = document.getElementById(`page-${pageName}`);

    if (!targetPage) {
      __vsLog('[GUARD] Target page not found:', pageName);
      return;
    }

    // Fade out all pages except target
    allPages.forEach(page => {
      if (page !== targetPage) {
        page.style.opacity = '0';
        page.style.transform = 'translateY(10px)';
        setTimeout(() => {
          page.classList.add('hidden');
          page.classList.remove('active');
        }, 300);
      }
    });

    // Show target page immediately, then fade in
    targetPage.classList.remove('hidden');
    targetPage.classList.add('active');
    targetPage.style.opacity = '0';
    targetPage.style.transform = 'translateY(10px)';

    // Force reflow
    targetPage.offsetHeight;

    // Fade in target page
    requestAnimationFrame(() => {
      targetPage.style.opacity = '1';
      targetPage.style.transform = 'translateY(0)';
    });

    // Update page title
    const titles = {
      'logs': guardUiPreferences.dashboardTitle || GUARD_DEFAULT_DASHBOARD_TITLE,
      'homeowners': 'Homeowners',
      'vehicles': 'Vehicles',
      'camera': 'Live Camera',
      'visitor': 'Visitor Passes'
    };
    if (pageTitle && titles[pageName]) {
      pageTitle.textContent = titles[pageName];
    }

    if (pageName !== 'logs') {
      setGuardDetailPlaceholder();
      document.querySelectorAll('.log-row.log-row-selected').forEach(clearFlagRowHighlight);
    } else if (!selectedGuardLogEntry) {
      setGuardDetailPlaceholder();
    }

    setGuardDetailRailVisibility(pageName);

    // Update active menu item
    document.querySelectorAll('.menu-item').forEach(item => {
      item.classList.remove('active');
      const itemPage = item.getAttribute('data-page');
      if (itemPage === pageName) {
        item.classList.add('active');
      }
    });

    // Page-specific initialization
    if (pageName === 'logs') {
      loadLogs();
    } else if (pageName === 'homeowners') {
      if (typeof loadHomeowners === 'function') loadHomeowners(true);
    } else if (pageName === 'vehicles') {
      loadGuardVehiclesPage(1);
    } else if (pageName === 'camera') {
      __vsLog('[GUARD] Camera page loaded');
    } else if (pageName === 'visitor') {
      loadVisitorPasses();
      loadVisitorScanHistory();
      restartVisitorScanRelativeTimeAutoUpdate();
    } else {
      stopVisitorScanRelativeTimeAutoUpdate();
    }
  };

  // Initialize menu items click handlers
  document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const pageName = item.getAttribute('data-page');
      if (pageName) {
        switchPage(pageName);
      }
    });
  });

  // ====== USER DROPDOWN (Match Admin Panel) ======
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


  __vsLog('[USER-DROPDOWN] Elements found:', {
    trigger: !!userTrigger,
    dropdown: !!userDropdown,
    chevron: !!userChevron
  });

  // Position dropdown dynamically (from bottom)
  function positionDropdown() {
    if (!userTrigger || !userDropdown) {
      __vsLog('[USER-DROPDOWN] Missing elements, cannot position');
      return;
    }

    const triggerRect = userTrigger.getBoundingClientRect();
    const sidebar = document.getElementById('sidebar');
    const sidebarRect = sidebar ? sidebar.getBoundingClientRect() : null;
    const gap = 8; // 0.5rem gap

    // Position relative to sidebar width
    const left = sidebarRect ? sidebarRect.left : triggerRect.left;
    const bottom = window.innerHeight - triggerRect.top + gap;
    const width = sidebarRect ? sidebarRect.width : triggerRect.width;

    __vsLog('[USER-DROPDOWN] Positioning:', {
      left,
      bottom,
      width,
      sidebarWidth: sidebarRect?.width
    });

    // Use bottom positioning and align with sidebar
    userDropdown.style.left = `${left}px`;
    userDropdown.style.bottom = `${bottom}px`;
    userDropdown.style.top = 'auto';
    userDropdown.style.width = `${width}px`;
    userDropdown.style.display = 'block';
  }

  if (userTrigger && userDropdown) {
    userTrigger.addEventListener('click', (e) => {
      __vsLog('[USER-DROPDOWN] Trigger clicked!');
      e.stopPropagation();
      const isHidden = userDropdown.style.display === 'none' || userDropdown.style.display === '';

      __vsLog('[USER-DROPDOWN] Current state:', { isHidden, display: userDropdown.style.display });

      if (isHidden) {
        positionDropdown();
        userDropdown.style.display = 'block';
        userDropdown.setAttribute('aria-hidden', 'false');
        userTrigger.setAttribute('aria-expanded', 'true');
        __vsLog('[USER-DROPDOWN] Dropdown opened');
      } else {
        closeUserDropdown();
        __vsLog('[USER-DROPDOWN] Dropdown closed');
      }

      if (userChevron) {
        // When closed: point up (180deg), When open: point down (0deg)
        userChevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(180deg)';
      }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
      if (userDropdown.style.display === 'block') {
        closeUserDropdown();
      }
    });

    userDropdown.addEventListener('click', (e) => {
      e.stopPropagation();
    });

    // Reposition on window resize
    window.addEventListener('resize', () => {
      if (userDropdown.style.display === 'block') {
        positionDropdown();
      }
    });

    if (hasKeyboardRegistry) {
      window.keyboardShortcuts.register('escape', () => {
        if (userDropdown.style.display !== 'block') return false;
        closeUserDropdown();
        return true;
      }, {
        id: 'guard.usermenu.escape',
        description: 'Close guard user menu',
        preventDefault: false,
        allowWhileTyping: true
      });
    } else {
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && userDropdown.style.display === 'block') {
          closeUserDropdown();
        }
      });
    }
  }

  // Sign Out button in dropdown
  const signOutBtn = document.getElementById('signOutBtn');
  if (signOutBtn) {
    signOutBtn.addEventListener('click', async (e) => {
      e.preventDefault();

      const result = await Swal.fire({
        title: 'Confirm Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel',
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--guard-warn') || getComputedStyle(document.documentElement).getPropertyValue('--warn') || '#ef4444',
        cancelButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--guard-accent') || getComputedStyle(document.documentElement).getPropertyValue('--accent') || '#6b7280',
        heightAuto: false,
        reverseButtons: true
      });

      if (result.isConfirmed) {
        try {
          const res = await fetch('../../auth/logout.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });

          if (res.ok) {
            window.location.href = '../../auth/login.php';
          } else {
            throw new Error('Logout failed');
          }
        } catch (err) {
          console.error('[GUARD] Logout error:', err);
          window.location.href = '../../auth/logout.php';
        }
      }
    });
  }

  // ====== DARK MODE ======
  // Dark mode is now handled by guard-dark.js (separate dedicated file)
  // This keeps guard panel dark mode independent from admin panel

  // ====== SEARCH HISTORY ======
  let searchHistory = JSON.parse(localStorage.getItem('guardSearchHistory') || '[]');
  const MAX_HISTORY = 5;

  function addToSearchHistory(term) {
    if (!term || term.length < 2) return;

    // Remove duplicates and add to front
    searchHistory = searchHistory.filter(item => item !== term);
    searchHistory.unshift(term);

    // Limit to MAX_HISTORY items
    if (searchHistory.length > MAX_HISTORY) {
      searchHistory = searchHistory.slice(0, MAX_HISTORY);
    }

    localStorage.setItem('guardSearchHistory', JSON.stringify(searchHistory));
  }

  function showSearchHistory() {
    const dropdown = document.getElementById('searchHistory');
    if (!dropdown || searchHistory.length === 0) return;

    dropdown.innerHTML = searchHistory.map(term => {
      const safeTerm = escapeHtml(term);
      const encodedTerm = encodeURIComponent(term);
      return `<div class="history-item" data-term="${encodedTerm}">
        <span class="history-icon"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span>
        <span>${safeTerm}</span>
      </div>`;
    }).join('') +
      '<div class="history-clear"><svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>Clear History</div>';

    dropdown.classList.remove('hidden');

    // Add click handlers
    dropdown.querySelectorAll('.history-item').forEach(item => {
      item.addEventListener('click', () => {
        const term = decodeURIComponent(item.dataset.term || '');
        logsSearch.value = term;
        logsSearch.dispatchEvent(new Event('input'));
        dropdown.classList.add('hidden');
      });
    });

    dropdown.querySelector('.history-clear')?.addEventListener('click', () => {
      searchHistory = [];
      localStorage.removeItem('guardSearchHistory');
      dropdown.classList.add('hidden');
      if (window.toast) {
        window.toast.success('Search history cleared');
      }
    });
  }

  function hideSearchHistory() {
    setTimeout(() => {
      const dropdown = document.getElementById('searchHistory');
      if (dropdown) dropdown.classList.add('hidden');
    }, 200);
  }

  // Elements
  const ownerImage = document.getElementById('ownerImage');
  const carImage = document.getElementById('carImage');
  const ownerName = document.getElementById('ownerName');
  const ownerAddress = document.getElementById('ownerAddress');
  const ownerContact = document.getElementById('ownerContact');
  const guardVehiclesList = document.getElementById('guardMyVehiclesList');
  const vehicleType = document.getElementById('vehicleType');
  const vehicleColor = document.getElementById('vehicleColor');
  const plateNumber = document.getElementById('plateNumber');
  const searchInput = document.getElementById('homeownerSearch');
  const clearSearch = document.getElementById('clearSearch');
  const prevBtn = document.getElementById('prevOwner');
  const nextBtn = document.getElementById('nextOwner');
  const ownerCounter = document.getElementById('ownerCounter');
  const logoutBtn = document.getElementById('signOutBtn');
  const clockEl = document.getElementById('clock'); // May not exist in new UI
  const toggleCamera = document.getElementById('toggleCamera');
  const liveCamera = document.getElementById('liveCamera');
  const powerIcon = document.getElementById('powerIcon');

  let allHomeowners = [];
  let currentIndex = 0;
  let cameraStream = null;
  let cameraEnabled = false;
  let guardVehiclesPage = 1;
  let guardVehiclesTotalPages = 1;
  let guardVehiclesSearchTerm = '';
  let guardVisitorHomeownerOptions = [];

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

  // Placeholder images using data URIs (no network calls)
  const PLACEHOLDER_CAR = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="200"%3E%3Crect fill="%23ddd" width="400" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="18" font-family="Arial"%3ENo Vehicle%3C/text%3E%3C/svg%3E';
  const PLACEHOLDER_OWNER = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="200"%3E%3Crect fill="%23ddd" width="400" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-size="18" font-family="Arial"%3ENo Owner%3C/text%3E%3C/svg%3E';

  // Set default placeholders
  carImage.src = PLACEHOLDER_CAR;
  ownerImage.src = PLACEHOLDER_OWNER;

  // Clock
  function updateClock() {
    if (!clockEl) return; // Guard against missing element
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('en-US', {
      hour12: true,
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit'
    });
  }
  if (clockEl) {
    updateClock();
    setInterval(updateClock, 1000);
  }

  // Track active fetch requests
  let activeLogsFetch = false;
  let currentLogPage = 1;
  let logsFilterDebounceTimer = null;
  let queuedLogsRequest = null;
  let lastCompletedLogsRequestKey = '';

  function getLogsFilterState() {
    const search = (document.getElementById('logsSearch')?.value || '').trim();
    const perPage = (document.getElementById('logsPerPage')?.value || '').trim();
    const plate = (window.activeUserFilter || '').trim();
    const dateFrom = (document.getElementById('logsDateFrom')?.value || '').trim();
    const dateTo = (document.getElementById('logsDateTo')?.value || '').trim();
    return {
      search,
      perPage,
      filter: currentFilter || '',
      plate,
      dateFrom,
      dateTo
    };
  }

  function requestLogsReload(page = 1, debounce = false) {
    if (debounce) {
      clearTimeout(logsFilterDebounceTimer);
      logsFilterDebounceTimer = setTimeout(() => loadLogs(page), 300);
      return;
    }
    clearTimeout(logsFilterDebounceTimer);
    loadLogs(page);
  }

  // Date filter button handler
  const applyLogsDateFilterBtn = document.getElementById('applyLogsDateFilter');
  if (applyLogsDateFilterBtn) {
    applyLogsDateFilterBtn.addEventListener('click', () => {
      requestLogsReload(1);
    });
  }

  // Also reload on Enter key in date inputs
  const logsDateFromInput = document.getElementById('logsDateFrom');
  const logsDateToInput = document.getElementById('logsDateTo');
  [logsDateFromInput, logsDateToInput].forEach((input) => {
    if (input) {
      input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          requestLogsReload(1);
        }
      });
    }
  });

  // Track the last seen log ID to detect NEW logs
  let lastSeenLogId = parseInt(localStorage.getItem('lastSeenLogId')) || 0;
  __vsLog('[GUARD] Starting with lastSeenLogId:', lastSeenLogId);

  // Load recent logs with SERVER-SIDE PAGINATION (matching admin panel architecture)
  async function loadLogs(page = 1) {
    const filters = getLogsFilterState();
    const requestKey = JSON.stringify({ page, ...filters });

    if (activeLogsFetch) {
      queuedLogsRequest = { page, requestKey };
      return;
    }
    activeLogsFetch = true;

    const logsWrapper = document.getElementById('logsContainerWrapper');
    if (!logsWrapper) {
      console.error('[GUARD] logsContainerWrapper not found');
      activeLogsFetch = false;
      return;
    }

    try {
      __vsLog('[GUARD] Fetching logs page', page, 'from ../fetch/fetch_logs.php');

      // Show loading state
      logsWrapper.innerHTML = `
        <div class="logs-table-container">
          <div class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-gray-300 dark:border-slate-600 border-t-gray-600 dark:border-t-gray-400"></div>
            <p class="mt-2 text-gray-500 dark:text-gray-400">Loading logs...</p>
          </div>
        </div>
      `;

      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('_', String(Date.now()));
      if (filters.search) params.set('search', filters.search);
      if (filters.filter) params.set('filter', filters.filter);
      if (filters.plate) params.set('plate', filters.plate);
      if (filters.perPage) params.set('per_page', filters.perPage);
      if (filters.dateFrom) params.set('date_from', filters.dateFrom);
      if (filters.dateTo) params.set('date_to', filters.dateTo);

      // Fetch HTML from server (matching admin pattern)
      const res = await fetch(`../fetch/fetch_logs.php?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      __vsLog('[GUARD] Response status:', res.status);

      if (!res.ok) {
        if (res.status === 403) {
          window.location.href = '../../auth/login.php?timeout=1';
          return;
        }
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }

      // Get HTML content from server
      const html = await res.text();
      __vsLog('[GUARD] Received HTML, length:', html.length);

      // Replace wrapper content with server-rendered HTML
      logsWrapper.innerHTML = html;

      // Keep detail rail populated by default using the latest visible log.
      syncGuardDetailFromVisibleLogs();

      // Store current page
      currentLogPage = page;
      lastCompletedLogsRequestKey = requestKey;
      __vsLog('[GUARD] Updated currentLogPage to:', currentLogPage);

      // Scroll to top for better UX
      const mainContent = document.querySelector('.content-scroll');
      if (mainContent) {
        mainContent.scrollTo({ top: 0, behavior: 'smooth' });
      }

      __vsLog('[GUARD] Logs loaded successfully for page', page);

    } catch (err) {
      console.error('[GUARD] Load logs error:', err);
      logsWrapper.innerHTML = `
        <div class="logs-table-container">
          <div class="text-center py-12 text-red-500">Error: ${err.message}</div>
        </div>
      `;
    } finally {
      activeLogsFetch = false;

      if (queuedLogsRequest) {
        const nextRequest = queuedLogsRequest;
        queuedLogsRequest = null;
        if (nextRequest.requestKey !== lastCompletedLogsRequestKey) {
          loadLogs(nextRequest.page);
        }
      }
    }
  }

  // Load all homeowners for search/carousel with better state management
  async function loadHomeowners(preserveIndex = false) {
    try {
      __vsLog('[GUARD] Fetching homeowners...');
      populateGuardVisitorHomeowners('loading');
      // Use configured endpoint or fallback and build absolute URL
      const endpoint = window.vehiscanConfig?.apiEndpoints?.homeowners || 'guard/fetch/fetch_homeowners.php';
      const endpointUrl = new URL(endpoint, resolveGuardBaseUrl()).toString();
      __vsLog('[GUARD] Fetching homeowners from:', endpointUrl);

      const res = await fetch(endpointUrl, { credentials: 'same-origin' });
      const jsonResponse = await res.json();
      __vsLog('[GUARD] API Response:', jsonResponse);

      if (!res.ok || jsonResponse.error) {
        throw new Error(jsonResponse.error || 'Failed to load homeowners');
      }

      const newHomeowners = Array.isArray(jsonResponse) ? jsonResponse : (jsonResponse.data || []);
      __vsLog('[GUARD] Loaded homeowners:', newHomeowners.length);

      // Get last known state
      const lastIndex = parseInt(localStorage.getItem('lastHomeownerIndex') || '0');
      const lastId = localStorage.getItem('lastHomeownerId');

      // Sort homeowners by ID to maintain consistent order
      newHomeowners.sort((a, b) => parseInt(a.id) - parseInt(b.id));
      allHomeowners = newHomeowners;
      populateGuardVisitorHomeowners(allHomeowners.length > 0 ? 'ready' : 'empty');

      if (allHomeowners.length > 0) {
        if (preserveIndex && lastId) {
          // Try to find the same homeowner by ID first
          const newIndex = allHomeowners.findIndex(h => h.id.toString() === lastId);
          if (newIndex !== -1) {
            currentIndex = newIndex;
          } else {
            // If not found, try to use the last known index if it's valid
            currentIndex = lastIndex < allHomeowners.length ? lastIndex : 0;
          }
        } else {
          currentIndex = 0;
        }

        __vsLog('[GUARD] Setting index to:', {
          preserveIndex,
          currentIndex,
          totalHomeowners: allHomeowners.length,
          currentHomeowner: allHomeowners[currentIndex]?.name
        });

        displayHomeowner(currentIndex);
      }
    } catch (err) {
      console.error('[GUARD] Load homeowners error:', err);
      allHomeowners = [];
      populateGuardVisitorHomeowners('error');
    }
  }

  async function loadGuardVehiclesPage(page = 1) {
    const tbody = document.getElementById('guardVehiclesTableBody');
    const countEl = document.getElementById('guardVehiclesCount');
    const pagerEl = document.getElementById('guardVehiclesPager');
    const prevBtn = document.getElementById('guardVehiclesPrev');
    const nextBtn = document.getElementById('guardVehiclesNext');
    if (!tbody || !countEl || !pagerEl || !prevBtn || !nextBtn) return;

    const safePage = Math.max(1, parseInt(page, 10) || 1);
    guardVehiclesPage = safePage;

    tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Loading vehicles...</td></tr>';

    try {
      const params = new URLSearchParams();
      params.set('page', String(guardVehiclesPage));
      params.set('per_page', '25');
      if (guardVehiclesSearchTerm) {
        params.set('search', guardVehiclesSearchTerm);
      }

      const res = await fetch(`../fetch/fetch_vehicles.php?${params.toString()}`, {
        credentials: 'same-origin'
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        throw new Error(json.message || 'Failed to load vehicles');
      }

      const rows = Array.isArray(json.vehicles) ? json.vehicles : [];
      const pagination = json.pagination || {};
      guardVehiclesTotalPages = Math.max(1, parseInt(pagination.total_pages, 10) || 1);

      countEl.textContent = `${pagination.total || rows.length} vehicles`;
      pagerEl.textContent = `Page ${guardVehiclesPage} of ${guardVehiclesTotalPages}`;
      prevBtn.disabled = guardVehiclesPage <= 1;
      nextBtn.disabled = guardVehiclesPage >= guardVehiclesTotalPages;

      if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No vehicles found.</td></tr>';
        return;
      }

      tbody.innerHTML = rows.map((v) => {
        const plate = escapeHtml(v.plate_number || '-');
        const owner = escapeHtml(v.homeowner_name || '-');
        const vehicleType = escapeHtml(v.vehicle_type || '-');
        const color = escapeHtml(v.color || '-');

        return `
          <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/50">
            <td class="px-4 py-3 font-mono text-gray-800 dark:text-gray-100">${plate}</td>
            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">${owner}</td>
            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">${vehicleType}</td>
            <td class="px-4 py-3 text-gray-700 dark:text-gray-200">${color}</td>
          </tr>
        `;
      }).join('');
    } catch (err) {
      console.error('[GUARD] loadGuardVehiclesPage error:', err);
      tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-8 text-center text-red-500">${escapeHtml(err.message || 'Failed to load vehicles')}</td></tr>`;
      countEl.textContent = '0 vehicles';
      pagerEl.textContent = 'Page 1 of 1';
      prevBtn.disabled = true;
      nextBtn.disabled = true;
    }
  }

  function initGuardVehiclesControls() {
    const searchInput = document.getElementById('guardVehiclesSearch');
    const clearBtn = document.getElementById('guardVehiclesClearSearch');
    const refreshBtn = document.getElementById('guardVehiclesRefresh');
    const refreshLabel = document.getElementById('guardVehiclesRefreshLabel');
    const prevBtn = document.getElementById('guardVehiclesPrev');
    const nextBtn = document.getElementById('guardVehiclesNext');

    if (!searchInput || !clearBtn || !refreshBtn || !prevBtn || !nextBtn) return;
    if (searchInput.dataset.bound === '1') return;
    searchInput.dataset.bound = '1';

    let searchTimer = null;

    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        guardVehiclesSearchTerm = searchInput.value.trim();
        loadGuardVehiclesPage(1);
      }, 250);
    });

    clearBtn.addEventListener('click', () => {
      searchInput.value = '';
      guardVehiclesSearchTerm = '';
      loadGuardVehiclesPage(1);
    });

    refreshBtn.addEventListener('click', async () => {
      if (refreshBtn.disabled) return;
      refreshBtn.disabled = true;
      refreshBtn.classList.add('is-loading');
      if (refreshLabel) refreshLabel.textContent = 'Refreshing...';
      try {
        await loadGuardVehiclesPage(guardVehiclesPage);
      } finally {
        refreshBtn.disabled = false;
        refreshBtn.classList.remove('is-loading');
        if (refreshLabel) refreshLabel.textContent = 'Refresh';
      }
    });

    prevBtn.addEventListener('click', () => {
      if (guardVehiclesPage > 1) {
        loadGuardVehiclesPage(guardVehiclesPage - 1);
      }
    });

    nextBtn.addEventListener('click', () => {
      if (guardVehiclesPage < guardVehiclesTotalPages) {
        loadGuardVehiclesPage(guardVehiclesPage + 1);
      }
    });
  }

  // Display homeowner at index with better tracking and async handling
  async function displayHomeowner(index, skipAnimation = false) {
    if (!Array.isArray(allHomeowners) || allHomeowners.length === 0) {
      __vsLog('[GUARD] No homeowners to display');
      return;
    }

    // Ensure index is within bounds
    if (index < 0 || index >= allHomeowners.length) {
      console.warn('[GUARD] Invalid index:', index);
      index = 0;
    }

    const h = allHomeowners[index];
    if (!h || !h.id) {
      console.warn('[GUARD] Invalid homeowner data at index:', index);
      return;
    }

    // Save current state
    localStorage.setItem('lastHomeownerIndex', index.toString());
    localStorage.setItem('lastHomeownerId', h.id.toString());

    // Add animation unless skipped
    if (!skipAnimation) {
      const container = document.querySelector('.homeowner-details-container');
      if (container) {
        container.style.animation = 'none';
        container.offsetHeight; // Trigger reflow
        container.style.animation = 'fadeInRight 0.3s ease-out';
      }
    }

    updateHomeownerDisplay(h);
    loadGuardVehiclesForHomeowner(h);
    ownerCounter.textContent = `${index + 1}/${allHomeowners.length}`;

    // Update navigation buttons state
    if (prevBtn && nextBtn) {
      prevBtn.disabled = allHomeowners.length <= 1;
      nextBtn.disabled = allHomeowners.length <= 1;
    }

    __vsLog('[GUARD] Displaying homeowner:', {
      index,
      id: h.id,
      name: h.name,
      plate: h.plate_number
    });
  }

  // Add animation styles if not present
  if (!document.getElementById('homeownerStyles')) {
    const style = document.createElement('style');
    style.id = 'homeownerStyles';
    style.textContent = `
      @keyframes fadeInRight {
        from {
          opacity: 0;
          transform: translateX(10px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }
      
      .homeowner-details-container {
        animation: fadeInRight 0.3s ease-out;
      }
    `;
    document.head.appendChild(style);
  }

  // Update homeowner display
  function updateHomeownerDisplay(data) {
    __vsLog('[GUARD] Updating homeowner display:', data);

    ownerName.textContent = `Name: ${data.name || '-'}`;
    ownerAddress.textContent = `Address: ${data.address || '-'}`;
    ownerContact.textContent = `Contact: ${data.contact || '-'}`;
    vehicleType.textContent = `Vehicle Type: ${data.vehicle_type || '-'}`;
    vehicleColor.textContent = `Color: ${data.color || '-'}`;
    plateNumber.textContent = `Plate Number: ${data.plate_number || '-'}`;

    // Images with error handling
    const tryLoadImage = async (imageElement, filePath, type, placeholder) => {
      if (!filePath) {
        __vsLog(`[GUARD] No ${type} image provided, using placeholder`);
        imageElement.src = placeholder;
        return;
      }
      __vsLog(`[GUARD] Loading ${type} image:`, filePath);

      // Build a robust absolute URL for the image. Support:
      //  - full http(s) URLs
      //  - paths starting with '/'
      //  - stored values like 'uploads/vehicles/foo.jpg' or just 'foo.jpg'
      const buildImageUrl = (rawPath, kind) => {
        if (!rawPath) return null;
        // Full URL
        if (/^https?:\/\//i.test(rawPath)) return rawPath;

        // Normalize leading slashes
        let p = rawPath.replace(/^\/+/, '');

        // If it's already under uploads/ use it; if it starts with vehicles/ or homeowners/ prefix uploads/
        if (/^(uploads\/)/i.test(p)) {
          // OK
        } else if (/^vehicles\//i.test(p)) {
          p = 'uploads/' + p;
        } else if (/^homeowners\//i.test(p)) {
          p = 'uploads/' + p;
        } else {
          // Bare filename - decide folder based on kind
          if (kind === 'vehicle') p = 'uploads/vehicles/' + p;
          else if (kind === 'owner') p = 'uploads/homeowners/' + p;
          else p = 'uploads/' + p;
        }

        // Resolve base (vehiscanConfig.baseUrl may be a site-relative path)
        let base = window.vehiscanConfig?.baseUrl || window.baseUrl || '';
        const origin = window.location.origin;
        if (!base) base = origin;
        else if (base.startsWith('//')) base = window.location.protocol + base;
        else if (base.startsWith('/')) base = origin + base;
        else if (!/^https?:\/\//i.test(base)) base = origin + '/' + base.replace(/^\/+/, '');

        return base.replace(/\/$/, '') + '/' + p.replace(/^\/+/, '');
      };

      const imageUrl = buildImageUrl(filePath, type);
      __vsLog(`[GUARD] Attempting to load image from: ${imageUrl}`);

      // Function to check if image URL is accessible
      const checkImage = (url) => {
        return new Promise((resolve) => {
          const img = new Image();
          img.onload = () => resolve(true);
          img.onerror = () => resolve(false);
          img.src = url;
        });
      };

      try {
        // First try HEAD request
        const response = await fetch(imageUrl, { method: 'HEAD' }).catch(() => ({ ok: false }));

        if (response.ok) {
          imageElement.src = imageUrl;
          __vsLog(`[GUARD] Successfully loaded ${type} image`);
        } else {
          // If HEAD fails, try Image loading
          const isValid = await checkImage(imageUrl);
          if (isValid) {
            imageElement.src = imageUrl;
            __vsLog(`[GUARD] Successfully loaded ${type} image after retry`);
          } else {
            __vsLog(`[GUARD] Image not found at ${imageUrl}, using placeholder`);
            imageElement.src = placeholder;
          }
        }
      } catch (error) {
        console.error(`[GUARD] Error loading ${type} image:`, error);
        imageElement.src = placeholder;
      }
    };

    // Load images
    // If the server indicated the file doesn't exist, skip network attempts and use placeholder
    const ownerPath = data.owner_img_url || data.owner_img;
    const carPath = data.car_img_url || data.car_img;

    if (data.owner_img_exists === false) {
      __vsLog('[GUARD] Server reports owner image missing, using placeholder');
      ownerImage.src = PLACEHOLDER_OWNER;
    } else {
      tryLoadImage(ownerImage, ownerPath, 'owner', PLACEHOLDER_OWNER);
    }

    if (data.car_img_exists === false) {
      __vsLog('[GUARD] Server reports car image missing, using placeholder');
      carImage.src = PLACEHOLDER_CAR;
    } else {
      tryLoadImage(carImage, carPath, 'vehicle', PLACEHOLDER_CAR);
    }
  }

  function renderGuardVehicles(vehicles, fallbackHomeowner) {
    if (!guardVehiclesList) return;

    const rows = Array.isArray(vehicles) ? vehicles : [];
    if (rows.length === 0 && fallbackHomeowner) {
      guardVehiclesList.innerHTML = `
        <article class="guard-vehicle-card">
          <p class="guard-vehicle-title">${escapeHtml(fallbackHomeowner.plate_number || '-')}</p>
          <p class="guard-vehicle-meta">${escapeHtml(fallbackHomeowner.vehicle_type || '-')} - ${escapeHtml(fallbackHomeowner.color || '-')}</p>
          <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">Legacy vehicle record</p>
        </article>
      `;
      return;
    }

    if (rows.length === 0) {
      guardVehiclesList.innerHTML = '<div class="col-span-full text-sm text-gray-500 dark:text-gray-400">No active vehicles linked to this homeowner.</div>';
      return;
    }

    guardVehiclesList.innerHTML = rows.map((v) => {
      const activeBadge = Number(v.is_active) === 1
        ? '<span class="guard-status-badge active">Active</span>'
        : '<span class="guard-status-badge inactive">Inactive</span>';
      const rfid = v.rfid_uid ? v.rfid_uid : 'Not bound';
      return `
        <article class="guard-vehicle-card">
          <div class="flex items-center justify-between gap-2">
            <p class="guard-vehicle-title">${escapeHtml(v.plate_number || '-')}</p>
            ${activeBadge}
          </div>
          <p class="guard-vehicle-meta">${escapeHtml(v.vehicle_type || '-')} - ${escapeHtml(v.color || '-')}</p>
          <p class="guard-vehicle-rfid">RFID: ${escapeHtml(rfid)}</p>
        </article>
      `;
    }).join('');
  }

  async function loadGuardVehiclesForHomeowner(homeowner) {
    if (!guardVehiclesList) return;
    if (!homeowner || !homeowner.id) {
      guardVehiclesList.innerHTML = '<div class="col-span-full text-sm text-gray-500 dark:text-gray-400">Select a homeowner to view linked vehicles.</div>';
      return;
    }

    guardVehiclesList.innerHTML = '<div class="col-span-full text-sm text-gray-500 dark:text-gray-400">Loading vehicles...</div>';

    try {
      const vehicles = await fetchGuardHomeownerVehicles({ homeownerId: homeowner.id });
      renderGuardVehicles(vehicles, homeowner);
    } catch (err) {
      console.error('[GUARD] loadGuardVehiclesForHomeowner error:', err);
      renderGuardVehicles([], homeowner);
    }
  }

  function populateGuardVisitorHomeowners(state = 'auto') {
    const searchInput = document.getElementById('guardVisitorHomeownerSearch');
    const list = document.getElementById('guardVisitorHomeownerList');
    const hiddenIdInput = document.getElementById('guardVisitorHomeownerId');
    const hint = document.getElementById('guardVisitorHomeownerHint');
    const selectionHint = document.getElementById('guardVisitorHomeownerSelection');
    const form = document.getElementById('guardAddVisitorForm');
    if (!searchInput || !list || !hiddenIdInput || !form) return;

    const derivedState = state === 'auto'
      ? (allHomeowners.length > 0 ? 'ready' : 'empty')
      : state;
    const hasHomeowners = derivedState === 'ready' && allHomeowners.length > 0;

    const currentValue = String(hiddenIdInput.value || '');
    let options = [];
    guardVisitorHomeownerOptions = [];

    if (hasHomeowners) {
      allHomeowners.forEach((h) => {
        const id = String(h.id || '');
        const nameRaw = String(h.name || 'Unknown');
        const plateRaw = String(h.plate_number || 'No plate');
        const label = `${nameRaw} (${plateRaw})`;

        guardVisitorHomeownerOptions.push({ id, label });
        options.push(`<option value="${escapeHtml(label)}"></option>`);
      });
    }

    list.innerHTML = options.join('');
    searchInput.disabled = !hasHomeowners;

    const selected = guardVisitorHomeownerOptions.find((o) => o.id === currentValue);
    if (selected) {
      searchInput.value = selected.label;
      hiddenIdInput.value = selected.id;
      if (selectionHint) selectionHint.textContent = `Selected: ${selected.label} (ID ${selected.id})`;
    } else if (!hasHomeowners) {
      searchInput.value = '';
      hiddenIdInput.value = '';
      if (selectionHint) selectionHint.textContent = '';
    } else if (!currentValue) {
      searchInput.value = '';
      hiddenIdInput.value = '';
      if (selectionHint) selectionHint.textContent = '';
    }

    if (hint) {
      if (derivedState === 'loading') {
        hint.textContent = 'Loading approved homeowners...';
        hint.className = 'mt-1 text-[11px] text-sky-600 dark:text-sky-400';
        searchInput.placeholder = 'Loading approved homeowners...';
      } else if (derivedState === 'error') {
        hint.textContent = 'Unable to load approved homeowners. Try refreshing the page.';
        hint.className = 'mt-1 text-[11px] text-rose-600 dark:text-rose-400';
        searchInput.placeholder = 'Unable to load homeowners';
      } else if (hasHomeowners) {
        hint.textContent = 'Search and select an approved homeowner to continue.';
        hint.className = 'mt-1 text-[11px] text-gray-500 dark:text-gray-400';
        searchInput.placeholder = 'Search homeowner by name or plate';
      } else {
        hint.textContent = 'No approved homeowners found. Add or approve homeowners first.';
        hint.className = 'mt-1 text-[11px] text-amber-600 dark:text-amber-400';
        searchInput.placeholder = 'No approved homeowners available';
      }
    }

    const controls = Array.from(form.querySelectorAll('input, textarea, select'));
    controls.forEach((el) => {
      if (el.id === 'guardVisitorHomeownerSearch' || el.id === 'guardVisitorHomeownerId') {
        el.disabled = !hasHomeowners;
        return;
      }
      if (el.type === 'hidden') return;
      el.disabled = !hasHomeowners;
    });

    const submitBtn = document.getElementById('guardAddVisitorSubmit');
    if (submitBtn) {
      submitBtn.disabled = !hasHomeowners;
      if (derivedState === 'loading') {
        submitBtn.textContent = 'Loading Homeowners...';
      } else if (derivedState === 'error') {
        submitBtn.textContent = 'Homeowners Unavailable';
      } else {
        submitBtn.textContent = hasHomeowners ? 'Submit Visitor Request' : 'Awaiting Approved Homeowner';
      }
    }
  }

  function initGuardAddVisitorForm() {
    const form = document.getElementById('guardAddVisitorForm');
    if (!form || form.dataset.bound === '1') return;
    form.dataset.bound = '1';

    const fromInput = document.getElementById('guardVisitorFrom');
    const untilInput = document.getElementById('guardVisitorUntil');
    const submitBtn = document.getElementById('guardAddVisitorSubmit');
    const homeownerSearchInput = document.getElementById('guardVisitorHomeownerSearch');
    const homeownerIdInput = document.getElementById('guardVisitorHomeownerId');
    const selectionHint = document.getElementById('guardVisitorHomeownerSelection');

    populateGuardVisitorHomeowners('loading');

    const setBusy = (busy) => {
      if (!submitBtn) return;
      const hasHomeowners = allHomeowners.length > 0;
      submitBtn.disabled = busy || !hasHomeowners;
      if (!hasHomeowners) {
        submitBtn.textContent = 'Awaiting Approved Homeowner';
      } else {
        submitBtn.textContent = busy ? 'Submitting Request...' : 'Submit Visitor Request';
      }
    };

    const setDefaultDateRange = () => {
      if (!fromInput || !untilInput) return;
      const now = new Date();
      const endOfDay = new Date(now);
      endOfDay.setHours(23, 59, 0, 0);
      const toLocal = (d) => new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
      fromInput.value = toLocal(now);
      untilInput.value = toLocal(endOfDay);
      untilInput.readOnly = false;
      untilInput.removeAttribute('aria-readonly');
    };

    const syncUntilToDayEnd = () => {
      if (!fromInput || !untilInput || !fromInput.value) return;
      const fromDate = new Date(fromInput.value);
      if (!Number.isFinite(fromDate.getTime())) return;
      const endOfDay = new Date(fromDate);
      endOfDay.setHours(23, 59, 0, 0);
      const toLocal = (d) => new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
      untilInput.value = toLocal(endOfDay);
    };

    const validateDateRange = () => {
      if (!fromInput || !untilInput || !fromInput.value || !untilInput.value) return true;
      const from = new Date(fromInput.value).getTime();
      const until = new Date(untilInput.value).getTime();
      if (!Number.isFinite(from) || !Number.isFinite(until)) return false;
      if (until <= from) {
        untilInput.setCustomValidity('Valid Until must be later than Valid From');
        untilInput.reportValidity();
        return false;
      }
      const minutes = (until - from) / 60000;
      if (minutes < 30) {
        untilInput.setCustomValidity('Visit duration must be at least 30 minutes');
        untilInput.reportValidity();
        return false;
      }

      const fromDay = new Date(fromInput.value).toISOString().slice(0, 10);
      const untilDay = new Date(untilInput.value).toISOString().slice(0, 10);
      if (fromDay !== untilDay) {
        untilInput.setCustomValidity('Visitor passes must expire on the same day they start.');
        untilInput.reportValidity();
        return false;
      }

      untilInput.setCustomValidity('');
      return true;
    };

    if (fromInput && untilInput && !fromInput.value && !untilInput.value) {
      setDefaultDateRange();
    } else {
      syncUntilToDayEnd();
    }

    if (fromInput && untilInput) {
      fromInput.addEventListener('change', () => {
        syncUntilToDayEnd();
        validateDateRange();
      });
      untilInput.addEventListener('change', validateDateRange);
    }

    const resolveHomeownerSelection = () => {
      if (!homeownerSearchInput || !homeownerIdInput) return false;
      const typed = String(homeownerSearchInput.value || '').trim().toLowerCase();
      const matched = guardVisitorHomeownerOptions.find((o) => o.label.toLowerCase() === typed);

      if (matched) {
        homeownerIdInput.value = matched.id;
        homeownerSearchInput.setCustomValidity('');
        if (selectionHint) selectionHint.textContent = `Selected: ${matched.label} (ID ${matched.id})`;
        return true;
      }

      homeownerIdInput.value = '';
      if (selectionHint) selectionHint.textContent = '';
      if (typed) {
        homeownerSearchInput.setCustomValidity('Please choose a homeowner from the search list.');
      } else {
        homeownerSearchInput.setCustomValidity('Please select a homeowner.');
      }
      return false;
    };

    homeownerSearchInput?.addEventListener('input', () => {
      if (homeownerIdInput) homeownerIdInput.value = '';
      homeownerSearchInput.setCustomValidity('');
      if (selectionHint) selectionHint.textContent = '';
    });

    homeownerSearchInput?.addEventListener('change', resolveHomeownerSelection);
    homeownerSearchInput?.addEventListener('blur', resolveHomeownerSelection);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (allHomeowners.length === 0) {
        if (window.toast) window.toast.warning('No approved homeowners available for visitor requests yet.');
        return;
      }
      if (!resolveHomeownerSelection()) {
        homeownerSearchInput?.reportValidity();
        homeownerSearchInput?.focus();
        return;
      }
      if (!form.reportValidity()) return;
      if (!validateDateRange()) return;

      const payload = new FormData(form);
      payload.append('csrf_token', getCsrfToken());

      setBusy(true);
      try {
        const res = await fetch('../api/create_visitor_request.php', {
          method: 'POST',
          credentials: 'same-origin',
          body: payload
        });
        const json = await res.json();
        if (!res.ok || !json.success) {
          throw new Error(json.message || 'Failed to create visitor request');
        }

        if (window.toast) window.toast.success(json.message || 'Visitor pass request submitted for approval');
        form.reset();
        setDefaultDateRange();
        populateGuardVisitorHomeowners();
        loadVisitorPasses();
      } catch (err) {
        console.error('[GUARD] create visitor request error:', err);
        if (window.toast) window.toast.error(err.message || 'Failed to create visitor request');
      } finally {
        setBusy(false);
      }
    });
  }

  window.showGuardVisitorModal = function () {
    const modal = document.getElementById('guardVisitorModal');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    populateGuardVisitorHomeowners(allHomeowners.length > 0 ? 'ready' : 'loading');
    if (allHomeowners.length === 0 && typeof loadHomeowners === 'function') {
      loadHomeowners(true);
    }

    const homeownerSearchInput = document.getElementById('guardVisitorHomeownerSearch');
    if (homeownerSearchInput && !homeownerSearchInput.disabled) {
      setTimeout(() => homeownerSearchInput.focus(), 80);
    }
  };

  window.closeGuardVisitorModal = function () {
    const modal = document.getElementById('guardVisitorModal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = 'auto';
  };

  function bindGuardActionButtons() {
    const openVisitorBtn = document.getElementById('openVisitorModalBtn');
    if (openVisitorBtn && openVisitorBtn.dataset.bound !== '1') {
      openVisitorBtn.dataset.bound = '1';
      openVisitorBtn.addEventListener('click', showGuardVisitorModal);
    }

    const closeVisitorBackdropBtn = document.getElementById('guardVisitorModalBackdrop');
    if (closeVisitorBackdropBtn && closeVisitorBackdropBtn.dataset.bound !== '1') {
      closeVisitorBackdropBtn.dataset.bound = '1';
      closeVisitorBackdropBtn.addEventListener('click', closeGuardVisitorModal);
    }

    const closeVisitorCancelBtn = document.getElementById('guardVisitorModalCancelBtn');
    if (closeVisitorCancelBtn && closeVisitorCancelBtn.dataset.bound !== '1') {
      closeVisitorCancelBtn.dataset.bound = '1';
      closeVisitorCancelBtn.addEventListener('click', closeGuardVisitorModal);
    }

    const openQrScannerBtn = document.getElementById('openQrScannerBtn');
    if (openQrScannerBtn && openQrScannerBtn.dataset.bound !== '1') {
      openQrScannerBtn.dataset.bound = '1';
      openQrScannerBtn.addEventListener('click', showQRScannerModal);
    }

    const openManualLogBtn = document.getElementById('openManualLogBtn');
    if (openManualLogBtn && openManualLogBtn.dataset.bound !== '1') {
      openManualLogBtn.dataset.bound = '1';
      openManualLogBtn.addEventListener('click', showManualLogModal);
    }

    // QR scanner modal close button
    const qrScannerCloseBtn = document.getElementById('qrScannerModalCloseBtn');
    if (qrScannerCloseBtn && qrScannerCloseBtn.dataset.bound !== '1') {
      qrScannerCloseBtn.dataset.bound = '1';
      qrScannerCloseBtn.addEventListener('click', (e) => { e.preventDefault(); closeQRScannerModal(); });
    }

    // Manual log modal close button and form submit binding
    const manualLogCloseBtn = document.getElementById('manualLogModalCloseBtn');
    if (manualLogCloseBtn && manualLogCloseBtn.dataset.bound !== '1') {
      manualLogCloseBtn.dataset.bound = '1';
      manualLogCloseBtn.addEventListener('click', (e) => { e.preventDefault(); closeManualLogModal(); });
    }

    const manualLogForm = document.getElementById('manual-log-form');
    if (manualLogForm && manualLogForm.dataset.bound !== '1') {
      manualLogForm.dataset.bound = '1';
      manualLogForm.addEventListener('submit', submitManualLog);
    }

    // Image zoom modal: backdrop click to close and close button
    const imageZoomModal = document.getElementById('imageZoomModal');
    if (imageZoomModal && imageZoomModal.dataset.bound !== '1') {
      imageZoomModal.dataset.bound = '1';
      imageZoomModal.addEventListener('click', (e) => {
        if (e.target === imageZoomModal) closeImageZoom();
      });
    }

    const imageZoomCloseBtn = document.getElementById('imageZoomCloseBtn');
    if (imageZoomCloseBtn && imageZoomCloseBtn.dataset.bound !== '1') {
      imageZoomCloseBtn.dataset.bound = '1';
      imageZoomCloseBtn.addEventListener('click', (e) => { e.stopPropagation(); closeImageZoom(); });
    }

    // Bind image zoom openers
    document.querySelectorAll('.open-image-zoom-btn').forEach((btn) => {
      if (btn.dataset.bound === '1') return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', (e) => {
        try {
          const targetId = btn.getAttribute('data-image-target');
          const img = document.getElementById(targetId);
          if (img && img.src) {
            openImageZoom(img.src);
          } else {
            if (window.toast) window.toast.info('Image not available');
          }
        } catch (err) {
          console.error('[ImageZoom] open error', err);
        }
      });
    });
  }



  // Global error handler to catch unexpected runtime errors and log them
  window.addEventListener('error', function (evt) {
    console.error('[GUARD] Uncaught error:', evt.error || evt.message, evt);
  });

  // Carousel controls with index tracking and lock
  let lastClickTime = 0;
  let isNavigating = false;
  const DEBOUNCE_TIME = 300; // Prevent rapid clicking

  async function navigateCarousel(direction) {
    // Prevent navigation if already in progress
    if (isNavigating) {
      __vsLog('[GUARD] Navigation in progress, skipping request');
      return;
    }

    const now = Date.now();
    if (now - lastClickTime < DEBOUNCE_TIME) return;
    lastClickTime = now;

    if (allHomeowners.length === 0) return;

    isNavigating = true;
    try {
      const currentId = allHomeowners[currentIndex]?.id;

      // Calculate new index
      if (direction === 'next') {
        currentIndex = (currentIndex + 1) % allHomeowners.length;
      } else {
        currentIndex = (currentIndex - 1 + allHomeowners.length) % allHomeowners.length;
      }

      // Ensure we're not showing the same homeowner
      if (allHomeowners[currentIndex]?.id === currentId) {
        if (direction === 'next') {
          currentIndex = (currentIndex + 1) % allHomeowners.length;
        } else {
          currentIndex = (currentIndex - 1 + allHomeowners.length) % allHomeowners.length;
        }
      }

      await displayHomeowner(currentIndex);

      __vsLog(`[GUARD] Navigated ${direction}:`, {
        newIndex: currentIndex,
        totalItems: allHomeowners.length,
        currentHomeowner: allHomeowners[currentIndex]?.name,
        homeownerId: allHomeowners[currentIndex]?.id
      });
    } finally {
      isNavigating = false;
    }
  }

  // Attach navigation event handlers (guard against missing elements)
  if (prevBtn) prevBtn.addEventListener('click', () => navigateCarousel('prev'));
  else console.warn('[GUARD] prevOwner button not found');
  if (nextBtn) nextBtn.addEventListener('click', () => navigateCarousel('next'));
  else console.warn('[GUARD] nextOwner button not found');

  // Add keyboard navigation (defensive - skip when user is typing in an input)
  const useShortcutRegistry = hasKeyboardRegistry;
  if (useShortcutRegistry) {
    window.keyboardShortcuts.register('arrowleft', () => {
      const tag = document.activeElement?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (prevBtn) prevBtn.click();
    }, {
      id: 'guard.homeowners.prev',
      description: 'Previous homeowner',
      preventDefault: false
    });

    window.keyboardShortcuts.register('arrowright', () => {
      const tag = document.activeElement?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      if (nextBtn) nextBtn.click();
    }, {
      id: 'guard.homeowners.next',
      description: 'Next homeowner',
      preventDefault: false
    });
  } else {
    document.addEventListener('keydown', (e) => {
      const tag = document.activeElement?.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

      if (e.key === 'ArrowLeft') {
        if (prevBtn) prevBtn.click();
      } else if (e.key === 'ArrowRight') {
        if (nextBtn) nextBtn.click();
      }
    });
  }

  // Search functionality with better index handling
  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimeout);

      searchTimeout = setTimeout(() => {
        const query = this.value.toLowerCase().trim();

        if (query === '') {
          // Don't reset to first item if we're just clearing the search
          return;
        }

        // Find all matching homeowners
        const matches = allHomeowners.filter(h =>
          (h.name && h.name.toLowerCase().includes(query)) ||
          (h.plate_number && h.plate_number.toLowerCase().includes(query)) ||
          (h.address && h.address.toLowerCase().includes(query))
        );

        if (matches.length > 0) {
          // Find the first match that comes after our current position
          const nextMatch = matches.find(h =>
            allHomeowners.indexOf(h) > currentIndex
          );

          // If found, use that, otherwise use the first match
          if (nextMatch) {
            currentIndex = allHomeowners.indexOf(nextMatch);
          } else {
            currentIndex = allHomeowners.indexOf(matches[0]);
          }

          displayHomeowner(currentIndex);
        } else {
          // Show no-results empty state
          const ownerName = document.getElementById('ownerName');
          const ownerAddress = document.getElementById('ownerAddress');
          const ownerContact = document.getElementById('ownerContact');
          const vType = document.getElementById('vehicleType');
          const vColor = document.getElementById('vehicleColor');
          const pNum = document.getElementById('plateNumber');
          const counter = document.getElementById('ownerCounter');
          if (ownerName) ownerName.textContent = 'No matches found';
          if (ownerAddress) ownerAddress.textContent = 'Try a different search term';
          if (ownerContact) ownerContact.textContent = '';
          if (vType) vType.textContent = 'Vehicle Type: -';
          if (vColor) vColor.textContent = 'Color: -';
          if (pNum) pNum.textContent = 'Plate Number: -';
          if (counter) counter.textContent = '0/0';
        }
      }, 300); // Debounce search for performance
    });
  } else {
    console.warn('[GUARD] Search input not found');
  }

  // Clear button - clears the search input and restores display
  if (clearSearch) {
    clearSearch.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
        // Restore current homeowner display after clearing search
        if (allHomeowners.length > 0) {
          displayHomeowner(currentIndex);
        }
        guardLog('[GUARD] Search input cleared, display restored');
      }
    });
  } else {
    console.warn('[GUARD] clearSearch button not found');
  }

  // Camera toggle: the shared camera core and thin wrappers handle camera lifecycle and UI binding.
  // `camera-core.js` exposes the shared controller, while `main-camera-handler.js` and
  // `camera-handler.js` expose `window.startCamera`, `window.stopCamera`, `window.startFloatingCamera`,
  // and `window.stopFloatingCamera`.
  if (toggleCamera) {
    __vsLog('[GUARD] Camera UI is managed by main-camera-handler.js');
  } else {
    __vsLog('[GUARD] toggleCamera button not found');
  }

  // Logout with confirmation (handled by signOutBtn listener above - line ~366)
  // NOTE: duplicate handler removed to prevent double-firing the Swal popup

  // Refresh button - reloads homeowners list from server
  const reloadBtn = document.getElementById('reloadHomeowners') || document.getElementById('reloadList');
  if (reloadBtn) {
    guardLog('[GUARD] reload button found, attaching click handler');
    reloadBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (reloadBtn.disabled) return;
      try {
        reloadBtn.disabled = true;
        reloadBtn.classList.add('is-loading');

        // Show loading state
        if (window.toast) {
          window.toast.info('Refreshing homeowners list...');
        }

        // Reload all homeowners from server
        await loadHomeowners();

        // Success message
        if (window.toast) {
          window.toast.success(`Refreshed! ${allHomeowners.length} homeowners loaded`);
        }

        guardLog('[GUARD] Homeowners list refreshed successfully');
      } catch (err) {
        console.error('[GUARD] Refresh error:', err);
        if (window.toast) {
          window.toast.error('Failed to refresh homeowners list');
        }
      } finally {
        reloadBtn.disabled = false;
        reloadBtn.classList.remove('is-loading');
      }
    });
  } else {
    console.warn('[GUARD] reload button not present at bind time, adding delegated listener for #reloadHomeowners');
    // Delegated click handler as a fallback
    document.body.addEventListener('click', async (e) => {
      const target = e.target;
      if (!target) return;
      if (target.id === 'reloadHomeowners' || target.closest && target.closest('#reloadHomeowners')) {
        e.preventDefault();
        try {
          guardLog('[GUARD] Delegated refresh click detected');
          await loadHomeowners();
          if (window.toast) {
            window.toast.success(`Refreshed! ${allHomeowners.length} homeowners loaded`);
          }
        } catch (err) {
          console.error('[GUARD] Delegated refresh error:', err);
          if (window.toast) {
            window.toast.error('Failed to refresh');
          }
        }
      }
    });
  }

  // Refresh only the currently displayed homeowner by plate number
  async function refreshCurrentHomeowner() {
    try {
      if (!Array.isArray(allHomeowners) || allHomeowners.length === 0) return;
      const current = allHomeowners[currentIndex];
      if (!current || !current.plate_number) return;

      // Request single homeowner by plate (tolerant) to get updated image flags/urls
      const endpoint = window.vehiscanConfig?.apiEndpoints?.homeowners || 'guard/fetch/fetch_homeowners.php';
      const url = new URL(endpoint, resolveGuardBaseUrl());
      url.searchParams.set('plate', current.plate_number);

      const res = await fetch(url.toString(), { credentials: 'same-origin' });
      if (!res.ok) {
        throw new Error('Failed to fetch homeowner details');
      }
      const payload = await res.json();
      const single = Array.isArray(payload) ? payload[0] : (payload.data ? payload.data[0] : payload[0]);
      if (single) {
        // Replace the homeowner entry in our list with fresh data
        allHomeowners[currentIndex] = Object.assign({}, allHomeowners[currentIndex], single);
        // Re-display using updated data
        displayHomeowner(currentIndex, true);
      }
    } catch (err) {
      console.error('[GUARD] refreshCurrentHomeowner error:', err);
    }
  }

  // ====== LOGS SEARCH & FILTER ======
  let currentFilter = null; // 'today', 'in', 'out', 'visitors', or null
  let allLogs = []; // Store all logs for filtering

  function updateLogsCounter() {
    // Counter is server-rendered and already reflects active filters.
  }

  function highlightSearchTerms(text, searchTerm) {
    if (!searchTerm) return text;
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<span class="search-highlight">$1</span>');
  }

  function filterLogs() {
    requestLogsReload(1, true);
  }

  // Search input
  const logsSearch = document.getElementById('logsSearch');
  const logsPerPage = document.getElementById('logsPerPage');
  if (logsSearch) {
    logsSearch.addEventListener('input', filterLogs);

    // Show history on focus
    logsSearch.addEventListener('focus', showSearchHistory);
    logsSearch.addEventListener('blur', hideSearchHistory);

    // Add to history on Enter
    logsSearch.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && logsSearch.value.trim()) {
        addToSearchHistory(logsSearch.value.trim());
        hideSearchHistory();
      }
    });

    // Keyboard shortcut: Ctrl/Cmd + K
    if (hasKeyboardRegistry) {
      window.keyboardShortcuts.register('ctrl+k', () => {
        if (logsSearch.offsetParent === null) return;
        logsSearch.focus();
        logsSearch.select();
      }, {
        id: 'guard.logs.search.focus',
        description: 'Focus guard logs search',
        preventDefault: true
      });
    } else {
      document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
          e.preventDefault();
          logsSearch.focus();
          logsSearch.select();
        }
      });
    }
  }

  if (logsPerPage) {
    logsPerPage.addEventListener('change', () => {
      requestLogsReload(1);
    });
  }

  // Filter buttons
  const filterToday = document.getElementById('filterToday');
  const filterIn = document.getElementById('filterIn');
  const filterOut = document.getElementById('filterOut');
  const clearLogsFilter = document.getElementById('clearLogsFilter');

  function setActiveLogFilter(nextFilter) {
    currentFilter = nextFilter;
    if (filterToday) filterToday.classList.toggle(FILTER_ACTIVE_CLASS, currentFilter === 'today');
    if (filterIn) filterIn.classList.toggle(FILTER_ACTIVE_CLASS, currentFilter === 'in');
    if (filterOut) filterOut.classList.toggle(FILTER_ACTIVE_CLASS, currentFilter === 'out');
  }

  if (filterToday) {
    filterToday.addEventListener('click', () => {
      setActiveLogFilter('today');

      requestLogsReload(1);
    });
  }

  if (filterIn) {
    filterIn.addEventListener('click', () => {
      setActiveLogFilter('in');

      requestLogsReload(1);
    });
  }

  if (filterOut) {
    filterOut.addEventListener('click', () => {
      setActiveLogFilter('out');

      requestLogsReload(1);
    });
  }

  if (clearLogsFilter) {
    clearLogsFilter.addEventListener('click', () => {
      setActiveLogFilter(null);
      window.activeUserFilter = null;
      if (logsSearch) logsSearch.value = '';

      __vsLog('[GUARD] Cleared all filters, reloading page 1');
      requestLogsReload(1);

      if (window.toast) {
        window.toast.success('All filters cleared');
      }
    });
  }

  // CSV Export Function (reusable)
  function exportLogsToCSV(filename) {
    const table = document.getElementById('logsTable');
    if (!table) return 0;

    const rows = [];
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
    rows.push(headers.join(','));

    // Export ALL rows (not just visible)
    const allRows = Array.from(table.querySelectorAll('tbody tr'));

    allRows.forEach(row => {
      const cells = Array.from(row.querySelectorAll('td')).map(td => {
        let text = td.textContent.trim();
        // Remove emojis and clean up
        text = text.replace(/[\u{1F195}\u{1F697}\u{1F7E2}\u{1F534}\u{1F3AB}]/gu, '').trim();
        // Escape commas and quotes
        if (text.includes(',') || text.includes('"')) {
          text = `"${text.replace(/"/g, '""')}"`;
        }
        return text;
      });
      rows.push(cells.join(','));
    });

    const csvContent = rows.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);

    return allRows.length;
  }

  // CSV Export Button
  // Helper function to close actions dropdown
  function closeActionsDropdown() {
    const dropdown = document.getElementById('actionsDropdownContent');
    if (dropdown) dropdown.classList.add('hidden');
  }

  const exportLogs = document.getElementById('exportLogsBtn');
  if (exportLogs) {
    exportLogs.addEventListener('click', () => {
      const count = exportLogsToCSV(`guard_logs_${new Date().toISOString().split('T')[0]}.csv`);

      if (window.toast) {
        window.toast.success(`Exported ${count} logs to CSV`);
      }
      closeActionsDropdown();
    });
  }

  // Refresh logs button (reload current page - matching admin panel pattern)
  const refreshLogsBtn = document.getElementById('refreshLogs');
  if (refreshLogsBtn) {
    refreshLogsBtn.addEventListener('click', async () => {
      if (refreshLogsBtn.disabled) return;
      guardLog('[GUARD] Manual refresh triggered for page', currentLogPage);
      refreshLogsBtn.disabled = true;
      refreshLogsBtn.classList.add('is-loading');
      const refreshLogsLabel = document.getElementById('refreshLogsLabel');
      if (refreshLogsLabel) refreshLogsLabel.textContent = 'Refreshing...';

      try {
        await loadLogs(currentLogPage);
      } finally {
        refreshLogsBtn.disabled = false;
        refreshLogsBtn.classList.remove('is-loading');
        if (refreshLogsLabel) refreshLogsLabel.textContent = 'Refresh';
      }

      if (window.toast) {
        window.toast.success('Logs refreshed');
      }
    });
  }

  // Refresh All button (sidebar)
  const refreshAllBtn = document.getElementById('refreshAllBtn');
  if (refreshAllBtn) {
    refreshAllBtn.addEventListener('click', async () => {
      if (refreshAllBtn.disabled) return;
      guardLog('[GUARD] Refresh All triggered');

      const originalText = refreshAllBtn.innerHTML;
      refreshAllBtn.disabled = true;
      refreshAllBtn.classList.add('is-loading');
      refreshAllBtn.innerHTML = '<svg class="h-4 w-4 flex-shrink-0 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg><span>Refreshing...</span>';

      try {
        await Promise.all([loadLogs(), loadHomeowners(true), loadGuardVehiclesPage(guardVehiclesPage)]);
      } finally {
        refreshAllBtn.disabled = false;
        refreshAllBtn.classList.remove('is-loading');
        refreshAllBtn.innerHTML = originalText;
      }

      if (window.toast) {
        window.toast.success('All data refreshed');
      }
    });
  }

  // Export Logs button (sidebar)
  const exportLogsBtn = document.getElementById('exportLogsBtn');
  if (exportLogsBtn) {
    exportLogsBtn.addEventListener('click', async () => {
      guardLog('[GUARD] Export Logs triggered');

      try {
        const response = await fetch('../export_logs.php');
        if (!response.ok) throw new Error('Export failed');

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `access_logs_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        if (window.toast) {
          window.toast.success('Logs exported successfully');
        }
      } catch (error) {
        console.error('[GUARD] Export error:', error);
        if (window.toast) {
          window.toast.error('Failed to export logs');
        }
      }
    });
  }

  // Update counter after each loadLogs
  const originalLoadLogs = loadLogs;
  loadLogs = async function (page = 1) {
    await originalLoadLogs(page);
    setTimeout(updateLogsCounter, 100); // Wait for DOM update
  };

  // ====== LIVE LOG COUNTER BADGE ======
  let badgeCounter = 0;

  function updateNewLogsBadge(count) {
    badgeCounter += count;
    const badge = document.getElementById('newLogsBadge');
    const badgeCount = document.getElementById('newLogsCount');

    if (badge && badgeCount && badgeCounter > 0) {
      badgeCount.textContent = badgeCounter;
      badge.style.display = 'flex';
    }
  }

  function clearNewLogsBadge() {
    badgeCounter = 0;
    const badge = document.getElementById('newLogsBadge');
    if (badge) {
      badge.style.display = 'none';
    }
  }

  // Clear badge on click
  const newLogsBadge = document.getElementById('newLogsBadge');
  if (newLogsBadge) {
    newLogsBadge.addEventListener('click', clearNewLogsBadge);
  }

  // Clear badge when user interacts with logs container
  const logsContainerElement = document.getElementById('logsContainerWrapper');
  if (logsContainerElement) {
    logsContainerElement.addEventListener('scroll', clearNewLogsBadge);

    logsContainerElement.addEventListener('click', (e) => {
      clearNewLogsBadge();

      const paginationButton = e.target.closest('.pagination-btn, .pagination-page');
      if (paginationButton) {
        e.preventDefault();
        e.stopPropagation();

        __vsLog('[GUARD] Pagination button clicked:', paginationButton.className, 'dataset.page:', paginationButton.dataset.page);

        if (paginationButton.disabled || paginationButton.classList.contains('disabled')) {
          __vsLog('[GUARD] Pagination button is disabled, skipping');
          return;
        }

        const page = parseInt(paginationButton.dataset.page, 10);
        __vsLog('[GUARD] Parsed page number:', page, 'Current page:', currentLogPage);

        if (!page || page < 1) {
          __vsLog('[GUARD] Invalid page number, skipping');
          return;
        }

        if (page === currentLogPage) {
          __vsLog('[GUARD] Already on page', page, 'skipping reload');
          return;
        }

        requestLogsReload(page);
        return;
      }

      // Event delegation for table row clicks
      const row = e.target.closest('tr.log-row[data-log-id]');
      if (row) {
        const plateNumber = row.dataset.plate;
        const userName = row.dataset.name;
        __vsLog('[GUARD] Log row clicked:', userName, plateNumber);
        showGuardLogDetail(row);
      }
    });
  }

  // ====== IMAGE ZOOM FUNCTIONALITY ======
  window.openImageZoom = function (src) {
    const modal = document.getElementById('imageZoomModal');
    const img = document.getElementById('zoomedImage');
    if (modal && img && src) {
      img.src = src;
      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    }
  };

  window.closeImageZoom = function () {
    const modal = document.getElementById('imageZoomModal');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = 'auto';
    }
  };

  // Add click handlers to images
  document.querySelectorAll('[data-zoom-target]').forEach(container => {
    container.addEventListener('click', function () {
      const targetId = this.dataset.zoomTarget;
      const img = document.getElementById(targetId);
      if (img && img.src && !img.src.includes('data:image/svg')) {
        openImageZoom(img.src);
      }
    });

    container.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      const targetId = this.dataset.zoomTarget;
      const img = document.getElementById(targetId);
      if (img && img.src && !img.src.includes('data:image/svg')) {
        openImageZoom(img.src);
      }
    });
  });

  // ESC key to close
  if (hasKeyboardRegistry) {
    window.keyboardShortcuts.register('escape', () => {
      const modal = document.getElementById('imageZoomModal');
      if (!modal || modal.classList.contains('hidden')) return false;
      closeImageZoom();
      return true;
    }, {
      id: 'guard.imagezoom.escape',
      description: 'Close guard image zoom',
      preventDefault: false,
      allowWhileTyping: true
    });
  } else {
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeImageZoom();
      }
    });
  }
  // ====== FILTER LOGS BY USER ======
  window.filterLogsByUser = function (plateNumber, userName) {
    guardLog('[GUARD] Filtering logs by user:', userName, plateNumber);

    // Store the active filter
    window.activeUserFilter = plateNumber;

    requestLogsReload(1);

    // Show toast notification
    if (window.toast) {
      window.toast.info(`Showing history for ${userName} (${plateNumber})`);
    }
  };

  // ====== JUMP TO HOMEOWNER FROM LOG ======
  window.jumpToHomeowner = async function (plateNumber) {
    guardLog('[GUARD] Jump to homeowner:', plateNumber);

    if (!plateNumber || !Array.isArray(allHomeowners)) return;

    const index = allHomeowners.findIndex(h =>
      h.plate_number && h.plate_number.toLowerCase() === plateNumber.toLowerCase()
    );

    if (index !== -1) {
      currentIndex = index;
      await displayHomeowner(currentIndex);

      if (window.toast) {
        window.toast.success(`Showing: ${allHomeowners[index].name}`);
      }

      // Smooth scroll to homeowner section
      document.querySelector('.homeowner-box')?.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
      });
    } else {
      if (window.toast) {
        window.toast.warning(`Homeowner not found for plate: ${plateNumber}`);
      }
    }
  };

  // ====== KEYBOARD SHORTCUTS ======
  if (hasKeyboardRegistry) {
    window.keyboardShortcuts.register('ctrl+shift+r', () => {
      const refreshBtn = document.getElementById('refreshLogs');
      if (!refreshBtn || refreshBtn.offsetParent === null) return false;
      refreshBtn.click();
      return true;
    }, {
      id: 'guard.logs.refresh',
      description: 'Refresh logs',
      preventDefault: true,
      allowWhileTyping: false
    });

    window.keyboardShortcuts.register('ctrl+e', () => {
      const exportBtn = document.getElementById('exportLogsBtn');
      if (!exportBtn || exportBtn.offsetParent === null) return false;
      exportBtn.click();
      return true;
    }, {
      id: 'guard.logs.export',
      description: 'Export logs CSV',
      preventDefault: true,
      allowWhileTyping: false
    });
  } else {
    document.addEventListener('keydown', (e) => {
      // Ctrl/Cmd + Shift + R - Refresh logs (Shift added to avoid overriding browser refresh)
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'R') {
        e.preventDefault();
        document.getElementById('refreshLogs')?.click();
      }

      // Ctrl/Cmd + E - Export CSV
      if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        document.getElementById('exportLogsBtn')?.click();
      }
    });
  }

  // ====== VISITOR PASSES FUNCTIONALITY ======
  async function loadVisitorPasses(options = {}) {
    const silent = !!options.silent;
    const container = document.getElementById('visitorPassesContainer');
    const refreshVisitorBtn = document.getElementById('refreshVisitorPasses');
    const refreshLabel = document.getElementById('refreshVisitorPassesLabel');
    if (!container) {
      console.warn('[VISITOR] Container not found');
      return;
    }

    if (!silent && refreshVisitorBtn) {
      refreshVisitorBtn.classList.add('is-loading');
      refreshVisitorBtn.disabled = true;
    }
    if (!silent && refreshLabel) refreshLabel.textContent = 'Refreshing...';

    if (!silent) {
      // Skeleton loader
      container.innerHTML = `
        <div class="col-span-full">
          <div class="skeleton skeleton-card"></div>
          <div class="skeleton skeleton-card"></div>
          <div class="skeleton skeleton-card"></div>
        </div>
      `;
    }

    try {
      const res = await fetch('../fetch/fetch_visitors.php');

      guardLog('[VISITOR] Response status:', res.status);

      if (!res.ok) {
        const errorText = await res.text();
        console.error('[VISITOR] Fetch failed:', res.status, errorText);
        throw new Error(`Server error: ${res.status}`);
      }

      let data;
      try {
        data = await res.json();
      } catch (parseError) {
        console.error('[VISITOR] JSON parse error:', parseError);
        throw new Error('Invalid response format from server');
      }

      if (!data.success || !data.passes || data.passes.length === 0) {
        container.innerHTML = '<div class="col-span-full ta-empty-state"><div class="ta-empty-icon"><svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg></div><p class="ta-empty-title">No visitor passes found</p><p class="ta-empty-desc">There are no active visitor passes at this time.</p></div>';
        return;
      }

      const passes = data.passes;

      // Render cards
      container.innerHTML = passes.map(pass => {
        const statusColors = {
          active: 'bg-green-100 text-green-800 border-green-500',
          used: 'bg-blue-100 text-blue-800 border-blue-500',
          expired: 'bg-yellow-100 text-yellow-800 border-yellow-500',
          cancelled: 'bg-red-100 text-red-800 border-red-500'
        };

        const statusColor = statusColors[pass.status] || 'bg-[color:var(--badge-in-bg)] text-[color:var(--badge-in-text)] border-[color:var(--border)]';

        // Format dates
        const formatDate = (dateStr) => {
          const date = new Date(dateStr);
          return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          });
        };

        const qrCodeHtml = pass.qr_code ? `
          <div class="flex items-center justify-center bg-white dark:bg-slate-800 p-2 rounded border border-gray-200 dark:border-slate-700">
            <img src="${pass.qr_code}" alt="QR Code" class="w-24 h-24 qr-clickable" style="image-rendering: pixelated;" title="Click to zoom">
          </div>
        ` : '';

        return `
          <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border-l-4 ${statusColor} overflow-hidden hover:shadow-md transition-shadow" style="min-height: 200px; max-height: 320px;">
            <div class="p-3">
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-2 flex-1 min-w-0">
                  <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-xl font-bold text-white shadow-sm flex-shrink-0">
                    ${pass.visitor_name.charAt(0).toUpperCase()}
                  </div>
                  <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-900 dark:text-white text-base truncate">${pass.visitor_name}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 font-mono font-semibold">${pass.visitor_plate}</p>
                  </div>
                </div>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold uppercase ${statusColor} whitespace-nowrap ml-2">
                  ${pass.status}
                </span>
              </div>
              
              <div class="space-y-2 text-sm mb-3">
                <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                  <svg class="w-4 h-4" style="color: var(--accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                  </svg>
                  <span class="font-medium truncate">${pass.homeowner_name}</span>
                </div>
              </div>
              
              ${qrCodeHtml}
              
              <div class="space-y-1.5 text-xs bg-gray-50 dark:bg-slate-700/50 rounded-lg p-2.5 mt-2">
                <div class="flex items-start gap-2">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold whitespace-nowrap">Valid From:</span>
                  <span class="font-medium text-gray-900 dark:text-white text-right flex-1">${formatDate(pass.valid_from)}</span>
                </div>
                <div class="flex items-start gap-2">
                  <span class="text-gray-500 dark:text-gray-400 font-semibold whitespace-nowrap">Valid Until:</span>
                  <span class="font-medium text-gray-900 dark:text-white text-right flex-1">${formatDate(pass.valid_until)}</span>
                </div>
              </div>
              
            </div>
          </div>
        `;
      }).join('');

      guardLog('[VISITOR] Loaded', passes.length, 'visitor passes');
    } catch (err) {
      console.error('[VISITOR] Error loading passes:', err);
      container.innerHTML = '<div class="col-span-full text-center py-12 text-red-500">Error loading visitor passes</div>';
    } finally {
      if (!silent && refreshVisitorBtn) {
        refreshVisitorBtn.classList.remove('is-loading');
        refreshVisitorBtn.disabled = false;
      }
      if (!silent && refreshLabel) refreshLabel.textContent = 'Refresh';
    }
  }

  let visitorScanSearchDebounce;
  let visitorScanHistoryPage = 1;
  let visitorScanHistoryTotalPages = 1;
  let visitorScanHistoryPerPage = 25;
  let visitorScanHistoryDensity = 'comfortable';
  let visitorScanHistoryTimeMode = 'exact';
  const VISITOR_SCAN_HISTORY_PER_PAGE_KEY = 'guard.visitorScanHistory.perPage';
  const VISITOR_SCAN_HISTORY_DENSITY_KEY = 'guard.visitorScanHistory.density';
  const VISITOR_SCAN_HISTORY_TIME_MODE_KEY = 'guard.visitorScanHistory.timeMode';
  const VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY = 'guard.visitorScanHistory.exportScope';
  const VISITOR_SCAN_HISTORY_LAST_EXPORT_KEY = 'guard.visitorScanHistory.lastExport';
  const VISITOR_SCAN_HISTORY_STATE_KEY = 'guard.visitorScanHistory.state';
  const VISITOR_SCAN_META_EXPANDED_KEY = 'guard.visitorScanHistory.metaExpanded';
  const VISITOR_SCAN_ADVANCED_EXPANDED_KEY = 'guard.visitorScanHistory.advancedExpanded';

  function normalizeVisitorScanPerPage(value) {
    const parsed = Number.parseInt(value, 10);
    if (parsed === 50 || parsed === 100) return parsed;
    return 25;
  }

  function normalizeVisitorScanDensity(value) {
    return value === 'compact' ? 'compact' : 'comfortable';
  }

  function normalizeVisitorScanTimeMode(value) {
    return value === 'relative' ? 'relative' : 'exact';
  }

  function normalizeVisitorScanExportScope(value) {
    if (value === 'page') return 'page';
    if (value === 'all') return 'all';
    return null;
  }

  function normalizeVisitorScanPreset(value) {
    if (value === 'today' || value === 'last7' || value === 'month') return value;
    return '';
  }

  function normalizeVisitorScanPage(value) {
    const parsed = Number.parseInt(value, 10);
    if (Number.isInteger(parsed) && parsed > 0) return Math.min(parsed, 10000);
    return 1;
  }

  function normalizeVisitorScanPanelExpanded(value, fallback = false) {
    if (value === '1') return true;
    if (value === '0') return false;
    return fallback;
  }

  function formatVisitorScanDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function formatVisitorScanRelativeTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);

    const deltaMs = Date.now() - date.getTime();
    const isFuture = deltaMs < 0;
    const absMs = Math.abs(deltaMs);

    const minuteMs = 60 * 1000;
    const hourMs = 60 * minuteMs;
    const dayMs = 24 * hourMs;

    const minutes = Math.floor(absMs / minuteMs);
    const hours = Math.floor(absMs / hourMs);
    const days = Math.floor(absMs / dayMs);

    let label;
    if (minutes < 1) {
      label = 'just now';
    } else if (minutes < 60) {
      label = `${minutes} minute${minutes === 1 ? '' : 's'}`;
    } else if (hours < 24) {
      label = `${hours} hour${hours === 1 ? '' : 's'}`;
    } else {
      label = `${days} day${days === 1 ? '' : 's'}`;
    }

    if (label === 'just now') return label;
    return isFuture ? `in ${label}` : `${label} ago`;
  }

  function updateVisitorScanExportScopeHint() {
    const hintEl = document.getElementById('visitorScanExportScopeHint');
    if (!hintEl) return;

    const storedScope = normalizeVisitorScanExportScope(localStorage.getItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY)) || 'all';
    const scopeLabel = storedScope === 'page' ? 'Export Current Page' : 'Export All Filtered';
    hintEl.textContent = `Using saved default: ${scopeLabel}`;
  }

  function getVisitorScanScopeLabel(scope) {
    return scope === 'page' ? 'Current Page' : 'All Filtered';
  }

  function updateVisitorScanLastRefreshed(value) {
    const el = document.getElementById('visitorScanLastRefreshed');
    if (!el) return;

    if (!value) {
      el.textContent = 'Last refreshed: not yet.';
      return;
    }

    const date = value instanceof Date ? value : new Date(value);
    const formatted = Number.isNaN(date.getTime())
      ? String(value)
      : date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });

    el.textContent = `Last refreshed: ${formatted}.`;
  }

  function updateVisitorScanLastExportInfo() {
    const infoEl = document.getElementById('visitorScanLastExportInfo');
    if (!infoEl) return;

    const raw = localStorage.getItem(VISITOR_SCAN_HISTORY_LAST_EXPORT_KEY);
    if (!raw) {
      infoEl.textContent = 'Last export: none yet.';
      return;
    }

    let parsed = null;
    try {
      parsed = JSON.parse(raw);
    } catch (e) {
      parsed = null;
    }

    if (!parsed || !parsed.scope || !parsed.at) {
      infoEl.textContent = 'Last export: none yet.';
      return;
    }

    const dt = new Date(parsed.at);
    const readableTime = Number.isNaN(dt.getTime())
      ? parsed.at
      : dt.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });

    infoEl.textContent = `Last export: ${getVisitorScanScopeLabel(parsed.scope)} at ${readableTime}.`;
  }

  const resetVisitorScanExportScopeBtn = document.getElementById('resetVisitorScanExportScope');
  if (resetVisitorScanExportScopeBtn) {
    resetVisitorScanExportScopeBtn.addEventListener('click', () => {
      localStorage.removeItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY);
      updateVisitorScanExportScopeHint();
      if (window.toast) {
        window.toast.success('Export scope default reset to Export All Filtered');
      }
    });
  }

  const visitorScanMetaToggle = document.getElementById('visitorScanMetaToggle');
  const visitorScanMetaPanel = document.getElementById('visitorScanMetaPanel');

  function setVisitorScanMetaExpanded(expanded, options = {}) {
    if (!visitorScanMetaToggle || !visitorScanMetaPanel) return;
    const isExpanded = !!expanded;
    const shouldPersist = options.persist !== false;
    visitorScanMetaToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    visitorScanMetaToggle.textContent = isExpanded ? 'Hide details' : 'Show details';
    visitorScanMetaPanel.classList.toggle('hidden', !isExpanded);
    visitorScanMetaPanel.setAttribute('aria-hidden', isExpanded ? 'false' : 'true');

    if (shouldPersist) {
      localStorage.setItem(VISITOR_SCAN_META_EXPANDED_KEY, isExpanded ? '1' : '0');
    }
  }

  visitorScanMetaToggle?.addEventListener('click', () => {
    const isExpanded = visitorScanMetaToggle.getAttribute('aria-expanded') === 'true';
    setVisitorScanMetaExpanded(!isExpanded);
  });

  const visitorScanAdvancedToggle = document.getElementById('visitorScanAdvancedToggle');
  const visitorScanAdvancedFilters = document.getElementById('visitorScanAdvancedFilters');

  function setVisitorScanAdvancedExpanded(expanded, options = {}) {
    if (!visitorScanAdvancedToggle || !visitorScanAdvancedFilters) return;
    const isExpanded = !!expanded;
    const shouldPersist = options.persist !== false;
    visitorScanAdvancedToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    visitorScanAdvancedToggle.textContent = isExpanded ? 'Less filters' : 'More filters';
    visitorScanAdvancedFilters.classList.toggle('hidden', !isExpanded);
    visitorScanAdvancedFilters.setAttribute('aria-hidden', isExpanded ? 'false' : 'true');

    if (shouldPersist) {
      localStorage.setItem(VISITOR_SCAN_ADVANCED_EXPANDED_KEY, isExpanded ? '1' : '0');
    }
  }

  visitorScanAdvancedToggle?.addEventListener('click', () => {
    const isExpanded = visitorScanAdvancedToggle.getAttribute('aria-expanded') === 'true';
    setVisitorScanAdvancedExpanded(!isExpanded);
  });

  const visitorScanPerPageInput = document.getElementById('visitorScanPerPage');
  if (visitorScanPerPageInput) {
    const storedPerPage = localStorage.getItem(VISITOR_SCAN_HISTORY_PER_PAGE_KEY);
    visitorScanHistoryPerPage = normalizeVisitorScanPerPage(storedPerPage);
    visitorScanPerPageInput.value = String(visitorScanHistoryPerPage);
  }

  const visitorScanDensityInput = document.getElementById('visitorScanDensity');
  if (visitorScanDensityInput) {
    const storedDensity = localStorage.getItem(VISITOR_SCAN_HISTORY_DENSITY_KEY);
    visitorScanHistoryDensity = normalizeVisitorScanDensity(storedDensity);
    visitorScanDensityInput.value = visitorScanHistoryDensity;
  }

  const visitorScanTimeModeInput = document.getElementById('visitorScanTimeMode');
  if (visitorScanTimeModeInput) {
    const storedTimeMode = localStorage.getItem(VISITOR_SCAN_HISTORY_TIME_MODE_KEY);
    visitorScanHistoryTimeMode = normalizeVisitorScanTimeMode(storedTimeMode);
    visitorScanTimeModeInput.value = visitorScanHistoryTimeMode;
  }

  const visitorScanRefreshIntervalInput = document.getElementById('visitorScanRefreshInterval');
  if (visitorScanRefreshIntervalInput) {
    const storedRefreshInterval = localStorage.getItem(VISITOR_SCAN_AUTO_REFRESH_INTERVAL_KEY);
    visitorAutoRefreshIntervalMs = normalizeVisitorAutoRefreshInterval(storedRefreshInterval);
    visitorScanRefreshIntervalInput.value = String(visitorAutoRefreshIntervalMs);
  }

  const visitorScanPresetToday = document.getElementById('visitorScanPresetToday');
  const visitorScanPresetLast7 = document.getElementById('visitorScanPresetLast7');
  const visitorScanPresetMonth = document.getElementById('visitorScanPresetMonth');
  const visitorScanActiveFilters = document.getElementById('visitorScanActiveFilters');

  function getCurrentVisitorScanPreset() {
    if (visitorScanPresetToday?.getAttribute('aria-pressed') === 'true') return 'today';
    if (visitorScanPresetLast7?.getAttribute('aria-pressed') === 'true') return 'last7';
    if (visitorScanPresetMonth?.getAttribute('aria-pressed') === 'true') return 'month';
    return '';
  }

  function saveVisitorScanHistoryState() {
    const payload = {
      search: document.getElementById('visitorScanSearchInput')?.value.trim() || '',
      status: document.getElementById('visitorScanStatusFilter')?.value.trim() || '',
      dateFrom: document.getElementById('visitorScanDateFrom')?.value.trim() || '',
      dateTo: document.getElementById('visitorScanDateTo')?.value.trim() || '',
      preset: getCurrentVisitorScanPreset(),
      page: visitorScanHistoryPage,
      perPage: visitorScanHistoryPerPage,
      density: visitorScanHistoryDensity,
      refreshInterval: visitorAutoRefreshIntervalMs,
      timeMode: visitorScanHistoryTimeMode,
    };

    localStorage.setItem(VISITOR_SCAN_HISTORY_STATE_KEY, JSON.stringify(payload));
  }

  function restoreVisitorScanHistoryState() {
    let state = null;
    try {
      state = JSON.parse(localStorage.getItem(VISITOR_SCAN_HISTORY_STATE_KEY) || 'null');
    } catch (e) {
      state = null;
    }

    if (!state) {
      updateVisitorScanLastRefreshed(null);
      return;
    }

    const searchInput = document.getElementById('visitorScanSearchInput');
    const statusFilter = document.getElementById('visitorScanStatusFilter');
    const dateFromInput = document.getElementById('visitorScanDateFrom');
    const dateToInput = document.getElementById('visitorScanDateTo');

    if (searchInput) searchInput.value = String(state.search || '');
    if (statusFilter) statusFilter.value = String(state.status || '');
    if (dateFromInput) dateFromInput.value = String(state.dateFrom || '');
    if (dateToInput) dateToInput.value = String(state.dateTo || '');

    const restoredPerPage = normalizeVisitorScanPerPage(state.perPage);
    visitorScanHistoryPerPage = restoredPerPage;
    if (visitorScanPerPageInput) {
      visitorScanPerPageInput.value = String(restoredPerPage);
    }
    localStorage.setItem(VISITOR_SCAN_HISTORY_PER_PAGE_KEY, String(restoredPerPage));

    const restoredDensity = normalizeVisitorScanDensity(state.density);
    visitorScanHistoryDensity = restoredDensity;
    const densityInput = document.getElementById('visitorScanDensity');
    if (densityInput) densityInput.value = restoredDensity;
    localStorage.setItem(VISITOR_SCAN_HISTORY_DENSITY_KEY, restoredDensity);

    const restoredTimeMode = normalizeVisitorScanTimeMode(state.timeMode);
    visitorScanHistoryTimeMode = restoredTimeMode;
    const timeModeInput = document.getElementById('visitorScanTimeMode');
    if (timeModeInput) timeModeInput.value = restoredTimeMode;
    localStorage.setItem(VISITOR_SCAN_HISTORY_TIME_MODE_KEY, restoredTimeMode);

    const restoredRefreshInterval = normalizeVisitorAutoRefreshInterval(state.refreshInterval);
    visitorAutoRefreshIntervalMs = restoredRefreshInterval;
    const refreshIntervalInput = document.getElementById('visitorScanRefreshInterval');
    if (refreshIntervalInput) refreshIntervalInput.value = String(restoredRefreshInterval);
    localStorage.setItem(VISITOR_SCAN_AUTO_REFRESH_INTERVAL_KEY, String(restoredRefreshInterval));

    visitorScanHistoryPage = normalizeVisitorScanPage(state.page);
    const restoredPreset = normalizeVisitorScanPreset(state.preset);
    if (restoredPreset === 'today') {
      setVisitorScanPresetButtonActive(visitorScanPresetToday);
    } else if (restoredPreset === 'last7') {
      setVisitorScanPresetButtonActive(visitorScanPresetLast7);
    } else if (restoredPreset === 'month') {
      setVisitorScanPresetButtonActive(visitorScanPresetMonth);
    } else {
      setVisitorScanPresetButtonActive(null);
    }

    updateVisitorScanLastRefreshed(null);
  }

  function formatDateInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function setVisitorScanPresetButtonActive(activeBtn) {
    [visitorScanPresetToday, visitorScanPresetLast7, visitorScanPresetMonth].forEach((btn) => {
      if (!btn) return;
      const isActive = btn === activeBtn;
      btn.classList.toggle('ta-btn-primary', isActive);
      btn.classList.toggle('ta-btn-secondary', !isActive);
      btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function describeVisitorScanFilters() {
    const filters = [];
    const searchValue = document.getElementById('visitorScanSearchInput')?.value.trim() || '';
    const statusValue = document.getElementById('visitorScanStatusFilter')?.value.trim() || '';
    const dateFromValue = document.getElementById('visitorScanDateFrom')?.value.trim() || '';
    const dateToValue = document.getElementById('visitorScanDateTo')?.value.trim() || '';

    if (searchValue) {
      filters.push(`search "${searchValue}"`);
    }

    if (statusValue) {
      const statusLabel = statusValue.replace(/_/g, ' ');
      filters.push(`status ${statusLabel}`);
    }

    const presetMap = [
      [visitorScanPresetToday, 'Today'],
      [visitorScanPresetLast7, 'Last 7 Days'],
      [visitorScanPresetMonth, 'This Month'],
    ];
    const activePreset = presetMap.find(([btn]) => btn && btn.getAttribute('aria-pressed') === 'true');

    if (activePreset) {
      filters.push(`range ${activePreset[1]}`);
    } else if (dateFromValue || dateToValue) {
      const from = dateFromValue || 'start';
      const to = dateToValue || 'today';
      filters.push(`range ${from} to ${to}`);
    }

    if (visitorScanHistoryTimeMode === 'relative') {
      filters.push('time relative');
    }

    return filters;
  }

  function updateVisitorScanExportSummary() {
    const summaryEl = document.getElementById('visitorScanExportSummary');
    if (!summaryEl) return;

    const filters = describeVisitorScanFilters();
    const filterText = filters.length > 0 ? `Filters: ${filters.join(', ')}.` : 'Filters: none.';
    summaryEl.textContent = `Current page: ${visitorScanHistoryPage} (${visitorScanHistoryPerPage} rows/page). ${filterText}`;
    renderVisitorScanActiveFilters();
  }

  function getVisitorScanPresetName(presetKey) {
    if (presetKey === 'today') return 'Today';
    if (presetKey === 'last7') return 'Last 7 Days';
    if (presetKey === 'month') return 'This Month';
    return '';
  }

  function renderVisitorScanActiveFilters() {
    const chipsContainer = document.getElementById('visitorScanActiveFilters');
    if (!chipsContainer) return;

    const searchInput = document.getElementById('visitorScanSearchInput');
    const statusFilter = document.getElementById('visitorScanStatusFilter');
    const dateFromInput = document.getElementById('visitorScanDateFrom');
    const dateToInput = document.getElementById('visitorScanDateTo');

    const searchValue = searchInput?.value.trim() || '';
    const statusValue = statusFilter?.value.trim() || '';
    const dateFromValue = dateFromInput?.value.trim() || '';
    const dateToValue = dateToInput?.value.trim() || '';
    const presetKey = getCurrentVisitorScanPreset();

    const chips = [];
    if (searchValue) {
      chips.push({ key: 'search', label: `Search: ${searchValue}` });
    }
    if (statusValue) {
      chips.push({ key: 'status', label: `Status: ${statusValue.replace(/_/g, ' ')}` });
    }
    if (presetKey) {
      chips.push({ key: 'preset', label: `Range: ${getVisitorScanPresetName(presetKey)}` });
    } else if (dateFromValue || dateToValue) {
      const fromLabel = dateFromValue || 'start';
      const toLabel = dateToValue || 'today';
      chips.push({ key: 'date', label: `Range: ${fromLabel} to ${toLabel}` });
    }
    if (visitorScanHistoryPerPage !== 25) {
      chips.push({ key: 'perPage', label: `Rows: ${visitorScanHistoryPerPage}` });
    }
    if (visitorScanHistoryTimeMode === 'relative') {
      chips.push({ key: 'timeMode', label: 'Time: Relative' });
    }

    if (chips.length === 0) {
      chipsContainer.innerHTML = '';
      return;
    }

    chipsContainer.innerHTML = chips.map((chip) => {
      const label = escapeHtml(chip.label);
      const key = escapeHtml(chip.key);
      return `<button type="button" data-filter-chip="${key}" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-[11px] bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900/50"><span>${label}</span><span aria-hidden="true">x</span></button>`;
    }).join('');
  }

  function setVisitorScanFilterStatus(isApplying) {
    const statusEl = document.getElementById('visitorScanFilterStatus');
    if (!statusEl) return;
    statusEl.classList.toggle('hidden', !isApplying);
  }

  function updateVisitorScanShortcutHints() {
    const hintEl = document.getElementById('visitorScanShortcutHints');
    if (!hintEl) return;

    if (!hasKeyboardRegistry) {
      hintEl.textContent = 'Tips: Use Prev/Next, Enter on jump box, and filter chips for quick scan-history navigation.';
      return;
    }

    hintEl.textContent = 'Shortcuts: Alt+Left/Right page, Ctrl+Shift+F search, Ctrl+Shift+J jump, Ctrl+Shift+V refresh, Ctrl+Shift+E export.';
  }

  function announceVisitorScanHistory(message, options = {}) {
    const announcementEl = document.getElementById('visitorScanA11yAnnouncements');
    if (!announcementEl || !message) return;

    const shouldAnnounce = options.force === true || options.fromFilter === true || options.silent !== true;
    if (!shouldAnnounce) return;

    announcementEl.textContent = '';
    window.setTimeout(() => {
      announcementEl.textContent = String(message);
    }, 10);
  }

  function isVisitorPageActive() {
    const visitorPage = document.getElementById('page-visitor');
    return !!(visitorPage && visitorPage.classList.contains('active'));
  }

  async function loadVisitorScanHistory(options = {}) {
    const silent = !!options.silent;
    const requestedPage = Number.parseInt(options.page, 10);
    const tbody = document.getElementById('visitorScanHistoryBody');
    const refreshBtn = document.getElementById('refreshVisitorScanHistory');
    const refreshLabel = document.getElementById('refreshVisitorScanHistoryLabel');
    const prevBtn = document.getElementById('visitorScanHistoryPrev');
    const nextBtn = document.getElementById('visitorScanHistoryNext');
    const jumpInput = document.getElementById('visitorScanHistoryJumpInput');
    const jumpBtn = document.getElementById('visitorScanHistoryJumpBtn');
    const pageLabel = document.getElementById('visitorScanHistoryPageLabel');
    const summaryLabel = document.getElementById('visitorScanHistorySummary');
    if (!tbody) return;

    const perPage = normalizeVisitorScanPerPage(visitorScanHistoryPerPage);
    visitorScanHistoryPerPage = perPage;
    const activePage = Number.isInteger(requestedPage) && requestedPage > 0 ? requestedPage : visitorScanHistoryPage;

    if (!silent && refreshBtn) {
      refreshBtn.classList.add('is-loading');
      refreshBtn.disabled = true;
    }
    if (!silent && refreshLabel) refreshLabel.textContent = 'Refreshing...';

    if (!silent) {
      tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Loading scan history...</td></tr>';
    }

    const searchInput = document.getElementById('visitorScanSearchInput');
    const statusFilter = document.getElementById('visitorScanStatusFilter');
    const dateFromInput = document.getElementById('visitorScanDateFrom');
    const dateToInput = document.getElementById('visitorScanDateTo');

    const queryParams = new URLSearchParams({ page: String(activePage), per_page: String(perPage) });
    if (searchInput && searchInput.value.trim() !== '') {
      queryParams.set('search', searchInput.value.trim());
    }
    if (statusFilter && statusFilter.value.trim() !== '') {
      queryParams.set('scan_status', statusFilter.value.trim());
    }
    if (dateFromInput && dateFromInput.value.trim() !== '') {
      queryParams.set('date_from', dateFromInput.value.trim());
    }
    if (dateToInput && dateToInput.value.trim() !== '') {
      queryParams.set('date_to', dateToInput.value.trim());
    }

    const hasFilters = queryParams.has('search') || queryParams.has('scan_status') || queryParams.has('date_from') || queryParams.has('date_to');

    try {
      if (options.fromFilter === true) {
        setVisitorScanFilterStatus(true);
      }
      const res = await fetch(`../fetch/fetch_visitor_scan_logs.php?${queryParams.toString()}`, {
        credentials: 'same-origin'
      });
      
      let json;
      try {
        json = await res.json();
      } catch (parseError) {
        console.error('[VISITOR-SCANS] JSON parse error:', parseError);
        throw new Error('Invalid response format from server');
      }
      
      if (!res.ok || !json.success) {
        throw new Error(json.message || 'Failed to fetch scan history');
      }

      const pagination = json.pagination || {};
      const currentPage = Math.max(1, Number.parseInt(pagination.page, 10) || activePage);
      const totalPages = Math.max(1, Number.parseInt(pagination.total_pages, 10) || 1);
      const totalCount = Math.max(0, Number.parseInt(pagination.total_count, 10) || 0);

      visitorScanHistoryPage = currentPage;
      visitorScanHistoryTotalPages = totalPages;
      updateVisitorScanLastRefreshed(new Date());
      saveVisitorScanHistoryState();

      if (pageLabel) {
        pageLabel.textContent = `Page ${currentPage} of ${totalPages}`;
      }
      if (summaryLabel) {
        const start = totalCount === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
        const end = Math.min(currentPage * perPage, totalCount);
        summaryLabel.textContent = totalCount === 0
          ? 'No scans found'
          : `Showing ${start}-${end} of ${totalCount} scans`;
      }
      const startCount = totalCount === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
      const endCount = Math.min(currentPage * perPage, totalCount);
      announceVisitorScanHistory(
        totalCount === 0
          ? 'No scan records found.'
          : `Scan history updated. Showing ${startCount} to ${endCount} of ${totalCount} records on page ${currentPage} of ${totalPages}.`,
        { silent, fromFilter: options.fromFilter === true }
      );
      if (prevBtn) prevBtn.disabled = currentPage <= 1;
      if (nextBtn) nextBtn.disabled = currentPage >= totalPages;
      if (jumpInput) {
        jumpInput.value = '';
        jumpInput.setAttribute('max', String(totalPages));
        jumpInput.placeholder = `Page 1-${totalPages}`;
      }
      if (jumpBtn) {
        jumpBtn.disabled = totalPages <= 1;
      }
      updateVisitorScanExportSummary();

      const logs = Array.isArray(json.logs) ? json.logs : [];
      if (logs.length === 0) {
        const emptyMessage = hasFilters
          ? 'No scan records match the selected filters.'
          : 'No visitor pass scans recorded yet.';
        const emptyHints = [];
        if (searchInput && searchInput.value.trim() !== '') {
          emptyHints.push('Try broadening or clearing the search text.');
        }
        if (statusFilter && statusFilter.value.trim() !== '') {
          emptyHints.push('Set status back to All statuses.');
        }
        if ((dateFromInput && dateFromInput.value.trim() !== '') || (dateToInput && dateToInput.value.trim() !== '')) {
          emptyHints.push('Widen the date range or use a Quick range preset.');
        }
        if (activePage > 1) {
          emptyHints.push('Try the previous page.');
        }

        const hintHtml = emptyHints.length > 0
          ? `<div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">${emptyHints.join(' ')}</div>`
          : '';

        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400"><div>${escapeHtml(emptyMessage)}</div>${hintHtml}</td></tr>`;
        announceVisitorScanHistory(`${emptyMessage} ${emptyHints.join(' ')}`.trim(), {
          silent,
          fromFilter: options.fromFilter === true
        });
        return;
      }

      const badgeClassForScanStatus = (scanStatus) => {
        const normalized = String(scanStatus || '').toLowerCase();
        if (normalized === 'used_first_time') return 'success';
        if (normalized === 'repeat_scan') return 'warning';
        return 'info';
      };

      tbody.innerHTML = logs.map((log) => {
        const rowPaddingClass = visitorScanHistoryDensity === 'compact' ? 'py-2' : 'py-3';
        const visitor = escapeHtml(log.visitor_name || 'Unknown Visitor');
        const plate = escapeHtml(log.visitor_plate || '-');
        const homeowner = escapeHtml(log.homeowner_name || 'Unknown Homeowner');
        const scanStatus = String(log.scan_status || 'scan').replace(/_/g, ' ');
        const scanBadgeClass = badgeClassForScanStatus(log.scan_status);
        const scannedAtExact = formatVisitorScanDateTime(log.scanned_at);
        const scannedAtDisplay = visitorScanHistoryTimeMode === 'relative'
          ? formatVisitorScanRelativeTime(log.scanned_at)
          : scannedAtExact;
        const scannedAtTitle = visitorScanHistoryTimeMode === 'relative'
          ? ` title="${escapeHtml(scannedAtExact)}"`
          : '';
        const scannedAtRaw = escapeHtml(String(log.scanned_at || ''));

        return `
          <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/50">
            <td class="px-4 ${rowPaddingClass} text-gray-700 dark:text-gray-200" data-scan-time="1" data-scanned-at="${scannedAtRaw}"${scannedAtTitle}>${escapeHtml(scannedAtDisplay)}</td>
            <td class="px-4 ${rowPaddingClass} text-gray-800 dark:text-gray-100 font-medium">${visitor}</td>
            <td class="px-4 ${rowPaddingClass} font-mono text-gray-700 dark:text-gray-200">${plate}</td>
            <td class="px-4 ${rowPaddingClass} text-gray-700 dark:text-gray-200">${homeowner}</td>
            <td class="px-4 ${rowPaddingClass}"><span class="ta-badge ${scanBadgeClass}">${escapeHtml(scanStatus)}</span></td>
          </tr>
        `;
      }).join('');
      refreshVisitorScanRelativeTimeCells();
      restartVisitorScanRelativeTimeAutoUpdate();
    } catch (error) {
      console.error('[VISITOR] loadVisitorScanHistory error:', error);
      tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-red-500">${escapeHtml(error.message || 'Failed to load scan history')}</td></tr>`;
      if (summaryLabel) summaryLabel.textContent = 'Failed to load scan history';
      if (jumpBtn) jumpBtn.disabled = true;
      updateVisitorScanExportSummary();
      updateVisitorScanLastRefreshed(null);
      announceVisitorScanHistory(error.message || 'Failed to load scan history.', {
        silent,
        fromFilter: true,
        force: true
      });
    } finally {
      if (options.fromFilter === true) {
        setVisitorScanFilterStatus(false);
      }
      if (!silent && refreshBtn) {
        refreshBtn.classList.remove('is-loading');
        refreshBtn.disabled = false;
      }
      if (!silent && refreshLabel) refreshLabel.textContent = 'Refresh';
    }
  }

  // Refresh visitor passes button
  const refreshVisitorBtn = document.getElementById('refreshVisitorPasses');
  if (refreshVisitorBtn) {
    refreshVisitorBtn.addEventListener('click', () => {
      if (refreshVisitorBtn.disabled) return;
      guardLog('[VISITOR] Refresh button clicked');
      loadVisitorPasses();
      loadVisitorScanHistory();
    });
  }

  const refreshVisitorScanBtn = document.getElementById('refreshVisitorScanHistory');
  if (refreshVisitorScanBtn) {
    refreshVisitorScanBtn.addEventListener('click', () => {
      if (refreshVisitorScanBtn.disabled) return;
      updateVisitorScanExportSummary();
      loadVisitorScanHistory();
    });
  }

  function startVisitorScanHistoryExport(options = {}) {
    const button = options.button;
    const label = options.label;
    const scope = options.scope === 'page' ? 'page' : 'all';
    const successMessage = options.successMessage || 'Visitor scan history export started';
    const idleLabel = options.idleLabel || 'Export';

    if (!button || button.disabled) return;

    const searchValue = document.getElementById('visitorScanSearchInput')?.value.trim() || '';
    const statusValue = document.getElementById('visitorScanStatusFilter')?.value.trim() || '';
    const dateFromValue = document.getElementById('visitorScanDateFrom')?.value.trim() || '';
    const dateToValue = document.getElementById('visitorScanDateTo')?.value.trim() || '';

    const params = new URLSearchParams();
    if (searchValue) params.set('search', searchValue);
    if (statusValue) params.set('scan_status', statusValue);
    if (dateFromValue) params.set('date_from', dateFromValue);
    if (dateToValue) params.set('date_to', dateToValue);
    params.set('scope', scope);

    if (scope === 'page') {
      params.set('page', String(visitorScanHistoryPage));
      params.set('per_page', String(visitorScanHistoryPerPage));
    }

    button.disabled = true;
    button.classList.add('is-loading');
    if (label) label.textContent = 'Exporting...';

    const exportUrl = `../export_visitor_scan_logs.php?${params.toString()}`;
    const anchor = document.createElement('a');
    anchor.href = exportUrl;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);

    if (window.toast) {
      window.toast.success(successMessage);
    }

    localStorage.setItem(VISITOR_SCAN_HISTORY_LAST_EXPORT_KEY, JSON.stringify({
      scope,
      at: new Date().toISOString()
    }));
    updateVisitorScanLastExportInfo();

      updateVisitorScanExportSummary();

    setTimeout(() => {
      button.disabled = false;
      button.classList.remove('is-loading');
      if (label) label.textContent = idleLabel;
    }, 1000);
  }

  async function promptVisitorScanExportScope(preferredScope) {
    const storedScope = normalizeVisitorScanExportScope(localStorage.getItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY));
    const fallbackScope = preferredScope === 'page' ? 'page' : 'all';
    const primaryScope = storedScope || fallbackScope;
    const secondaryScope = primaryScope === 'page' ? 'all' : 'page';
    const filterParts = describeVisitorScanFilters();
    const filterSummary = filterParts.length > 0 ? filterParts.join(', ') : 'none';
    const primaryScopeLabel = primaryScope === 'page' ? 'current page' : 'all filtered';
    const detailSummary = `Page ${visitorScanHistoryPage}, ${visitorScanHistoryPerPage} rows/page, filters: ${filterSummary}. Default scope: ${primaryScopeLabel}.`;

    const primaryLabel = primaryScope === 'page' ? 'Export Current Page' : 'Export All Filtered';
    const secondaryLabel = secondaryScope === 'page' ? 'Export Current Page' : 'Export All Filtered';

    const result = await Swal.fire({
      title: 'Export Visitor Scan History?',
      html: `<p class="text-sm text-gray-600">${escapeHtml(detailSummary)}</p>`,
      icon: 'question',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: primaryLabel,
      denyButtonText: secondaryLabel,
      cancelButtonText: 'Cancel',
      heightAuto: false
    });

    if (result.isConfirmed) {
      localStorage.setItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY, primaryScope);
      updateVisitorScanExportScopeHint();
      return primaryScope;
    }
    if (result.isDenied) {
      localStorage.setItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY, secondaryScope);
      updateVisitorScanExportScopeHint();
      return secondaryScope;
    }
    return null;
  }

  const exportVisitorScanHistoryBtn = document.getElementById('exportVisitorScanHistory');
  if (exportVisitorScanHistoryBtn) {
    exportVisitorScanHistoryBtn.addEventListener('click', async () => {
      const selectedScope = await promptVisitorScanExportScope('all');
      if (!selectedScope) return;

      startVisitorScanHistoryExport({
        button: exportVisitorScanHistoryBtn,
        label: document.getElementById('exportVisitorScanHistoryLabel'),
        scope: selectedScope,
        idleLabel: 'Export All',
        successMessage: selectedScope === 'page'
          ? 'Current scan-history page export started'
          : 'Full filtered scan history export started'
      });
    });
  }

  const exportVisitorScanHistoryPageBtn = document.getElementById('exportVisitorScanHistoryPage');
  if (exportVisitorScanHistoryPageBtn) {
    exportVisitorScanHistoryPageBtn.addEventListener('click', async () => {
      const selectedScope = await promptVisitorScanExportScope('page');
      if (!selectedScope) return;

      startVisitorScanHistoryExport({
        button: exportVisitorScanHistoryPageBtn,
        label: document.getElementById('exportVisitorScanHistoryPageLabel'),
        scope: selectedScope,
        idleLabel: 'Export Page',
        successMessage: selectedScope === 'page'
          ? 'Current scan-history page export started'
          : 'Full filtered scan history export started'
      });
    });
  }

  const visitorScanSearchInput = document.getElementById('visitorScanSearchInput');
  if (visitorScanSearchInput) {
    visitorScanSearchInput.addEventListener('input', () => {
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      if (visitorScanSearchDebounce) {
        clearTimeout(visitorScanSearchDebounce);
      }
      visitorScanSearchDebounce = setTimeout(() => {
        loadVisitorScanHistory({ silent: true, page: 1, fromFilter: true });
      }, 250);
    });
  }

  const visitorScanStatusFilter = document.getElementById('visitorScanStatusFilter');
  if (visitorScanStatusFilter) {
    visitorScanStatusFilter.addEventListener('change', () => {
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ silent: true, page: 1, fromFilter: true });
    });
  }

  if (visitorScanPerPageInput) {
    visitorScanPerPageInput.addEventListener('change', () => {
      visitorScanHistoryPerPage = normalizeVisitorScanPerPage(visitorScanPerPageInput.value);
      visitorScanPerPageInput.value = String(visitorScanHistoryPerPage);
      localStorage.setItem(VISITOR_SCAN_HISTORY_PER_PAGE_KEY, String(visitorScanHistoryPerPage));
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  if (visitorScanDensityInput) {
    visitorScanDensityInput.addEventListener('change', () => {
      visitorScanHistoryDensity = normalizeVisitorScanDensity(visitorScanDensityInput.value);
      visitorScanDensityInput.value = visitorScanHistoryDensity;
      localStorage.setItem(VISITOR_SCAN_HISTORY_DENSITY_KEY, visitorScanHistoryDensity);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: visitorScanHistoryPage, fromFilter: true });
    });
  }

  if (visitorScanTimeModeInput) {
    visitorScanTimeModeInput.addEventListener('change', () => {
      visitorScanHistoryTimeMode = normalizeVisitorScanTimeMode(visitorScanTimeModeInput.value);
      visitorScanTimeModeInput.value = visitorScanHistoryTimeMode;
      localStorage.setItem(VISITOR_SCAN_HISTORY_TIME_MODE_KEY, visitorScanHistoryTimeMode);
      saveVisitorScanHistoryState();
      restartVisitorScanRelativeTimeAutoUpdate();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: visitorScanHistoryPage, fromFilter: true });
    });
  }

  if (visitorScanRefreshIntervalInput) {
    visitorScanRefreshIntervalInput.addEventListener('change', () => {
      visitorAutoRefreshIntervalMs = normalizeVisitorAutoRefreshInterval(visitorScanRefreshIntervalInput.value);
      visitorScanRefreshIntervalInput.value = String(visitorAutoRefreshIntervalMs);
      localStorage.setItem(VISITOR_SCAN_AUTO_REFRESH_INTERVAL_KEY, String(visitorAutoRefreshIntervalMs));
      saveVisitorScanHistoryState();
      restartVisitorPassesAutoRefresh();

      if (isVisitorPageActive()) {
        setVisitorScanFilterStatus(true);
        loadVisitorPasses({ silent: true });
        loadVisitorScanHistory({ silent: true, fromFilter: true });
      }
    });
  }

  const visitorScanDateFrom = document.getElementById('visitorScanDateFrom');
  if (visitorScanDateFrom) {
    visitorScanDateFrom.addEventListener('change', () => {
      setVisitorScanPresetButtonActive(null);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ silent: true, page: 1, fromFilter: true });
    });
  }

  const visitorScanDateTo = document.getElementById('visitorScanDateTo');
  if (visitorScanDateTo) {
    visitorScanDateTo.addEventListener('change', () => {
      setVisitorScanPresetButtonActive(null);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ silent: true, page: 1, fromFilter: true });
    });
  }

  const clearVisitorScanFilters = document.getElementById('clearVisitorScanFilters');
  if (clearVisitorScanFilters) {
    clearVisitorScanFilters.addEventListener('click', () => {
      if (visitorScanSearchInput) visitorScanSearchInput.value = '';
      if (visitorScanStatusFilter) visitorScanStatusFilter.value = '';
      if (visitorScanDateFrom) visitorScanDateFrom.value = '';
      if (visitorScanDateTo) visitorScanDateTo.value = '';
      setVisitorScanPresetButtonActive(null);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  const resetVisitorScanHistoryStateBtn = document.getElementById('resetVisitorScanHistoryState');
  if (resetVisitorScanHistoryStateBtn) {
    resetVisitorScanHistoryStateBtn.addEventListener('click', () => {
      localStorage.removeItem(VISITOR_SCAN_HISTORY_STATE_KEY);
      localStorage.removeItem(VISITOR_SCAN_HISTORY_PER_PAGE_KEY);
      localStorage.removeItem(VISITOR_SCAN_HISTORY_DENSITY_KEY);
      localStorage.removeItem(VISITOR_SCAN_HISTORY_TIME_MODE_KEY);
      localStorage.removeItem(VISITOR_SCAN_AUTO_REFRESH_INTERVAL_KEY);
      localStorage.removeItem(VISITOR_SCAN_HISTORY_EXPORT_SCOPE_KEY);
      localStorage.removeItem(VISITOR_SCAN_HISTORY_LAST_EXPORT_KEY);
      localStorage.removeItem(VISITOR_SCAN_META_EXPANDED_KEY);
      localStorage.removeItem(VISITOR_SCAN_ADVANCED_EXPANDED_KEY);

      if (visitorScanSearchInput) visitorScanSearchInput.value = '';
      if (visitorScanStatusFilter) visitorScanStatusFilter.value = '';
      if (visitorScanDateFrom) visitorScanDateFrom.value = '';
      if (visitorScanDateTo) visitorScanDateTo.value = '';

      visitorScanHistoryPage = 1;
      visitorScanHistoryPerPage = 25;
      visitorScanHistoryDensity = 'comfortable';
      visitorScanHistoryTimeMode = 'exact';
      visitorAutoRefreshIntervalMs = 30000;
      if (visitorScanPerPageInput) visitorScanPerPageInput.value = '25';
      if (visitorScanDensityInput) visitorScanDensityInput.value = 'comfortable';
      if (visitorScanTimeModeInput) visitorScanTimeModeInput.value = 'exact';
      if (visitorScanRefreshIntervalInput) visitorScanRefreshIntervalInput.value = '30000';
      restartVisitorPassesAutoRefresh();
      setVisitorScanMetaExpanded(false);
      setVisitorScanAdvancedExpanded(false);

      setVisitorScanPresetButtonActive(null);
      updateVisitorScanExportSummary();
      updateVisitorScanExportScopeHint();
      updateVisitorScanLastExportInfo();
      updateVisitorScanLastRefreshed(null);
      setVisitorScanFilterStatus(false);

      if (window.toast) {
        window.toast.success('Visitor scan history preferences reset');
      }

      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  if (visitorScanPresetToday) {
    visitorScanPresetToday.addEventListener('click', () => {
      const today = new Date();
      const todayValue = formatDateInputValue(today);
      if (visitorScanDateFrom) visitorScanDateFrom.value = todayValue;
      if (visitorScanDateTo) visitorScanDateTo.value = todayValue;
      setVisitorScanPresetButtonActive(visitorScanPresetToday);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  if (visitorScanPresetLast7) {
    visitorScanPresetLast7.addEventListener('click', () => {
      const end = new Date();
      const start = new Date(end);
      start.setDate(end.getDate() - 6);
      if (visitorScanDateFrom) visitorScanDateFrom.value = formatDateInputValue(start);
      if (visitorScanDateTo) visitorScanDateTo.value = formatDateInputValue(end);
      setVisitorScanPresetButtonActive(visitorScanPresetLast7);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  if (visitorScanPresetMonth) {
    visitorScanPresetMonth.addEventListener('click', () => {
      const end = new Date();
      const start = new Date(end.getFullYear(), end.getMonth(), 1);
      if (visitorScanDateFrom) visitorScanDateFrom.value = formatDateInputValue(start);
      if (visitorScanDateTo) visitorScanDateTo.value = formatDateInputValue(end);
      setVisitorScanPresetButtonActive(visitorScanPresetMonth);
      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  if (visitorScanActiveFilters) {
    visitorScanActiveFilters.addEventListener('click', (event) => {
      const chip = event.target.closest('[data-filter-chip]');
      if (!chip) return;

      const chipKey = chip.getAttribute('data-filter-chip');
      if (chipKey === 'search') {
        if (visitorScanSearchInput) visitorScanSearchInput.value = '';
      } else if (chipKey === 'status') {
        if (visitorScanStatusFilter) visitorScanStatusFilter.value = '';
      } else if (chipKey === 'preset' || chipKey === 'date') {
        if (visitorScanDateFrom) visitorScanDateFrom.value = '';
        if (visitorScanDateTo) visitorScanDateTo.value = '';
        setVisitorScanPresetButtonActive(null);
      } else if (chipKey === 'perPage') {
        visitorScanHistoryPerPage = 25;
        if (visitorScanPerPageInput) visitorScanPerPageInput.value = '25';
        localStorage.setItem(VISITOR_SCAN_HISTORY_PER_PAGE_KEY, '25');
      } else if (chipKey === 'timeMode') {
        visitorScanHistoryTimeMode = 'exact';
        if (visitorScanTimeModeInput) visitorScanTimeModeInput.value = 'exact';
        localStorage.setItem(VISITOR_SCAN_HISTORY_TIME_MODE_KEY, 'exact');
      }

      updateVisitorScanExportSummary();
      saveVisitorScanHistoryState();
      setVisitorScanFilterStatus(true);
      loadVisitorScanHistory({ page: 1, fromFilter: true });
    });
  }

  restoreVisitorScanHistoryState();
  const storedMetaExpanded = normalizeVisitorScanPanelExpanded(localStorage.getItem(VISITOR_SCAN_META_EXPANDED_KEY), false);
  const storedAdvancedExpanded = normalizeVisitorScanPanelExpanded(localStorage.getItem(VISITOR_SCAN_ADVANCED_EXPANDED_KEY), false);
  setVisitorScanMetaExpanded(storedMetaExpanded, { persist: false });
  setVisitorScanAdvancedExpanded(storedAdvancedExpanded, { persist: false });
  updateVisitorScanExportSummary();
  updateVisitorScanExportScopeHint();
  updateVisitorScanLastExportInfo();
  updateVisitorScanShortcutHints();

  const visitorScanPrevBtn = document.getElementById('visitorScanHistoryPrev');
  if (visitorScanPrevBtn) {
    visitorScanPrevBtn.addEventListener('click', () => {
      const currentPage = Number.parseInt(visitorScanHistoryPage, 10) || 1;
      if (currentPage <= 1) return;
      loadVisitorScanHistory({ page: currentPage - 1 });
    });
  }

  const visitorScanNextBtn = document.getElementById('visitorScanHistoryNext');
  if (visitorScanNextBtn) {
    visitorScanNextBtn.addEventListener('click', () => {
      const currentPage = Number.parseInt(visitorScanHistoryPage, 10) || 1;
      const totalPages = Number.parseInt(visitorScanHistoryTotalPages, 10) || 1;
      if (currentPage >= totalPages) return;
      loadVisitorScanHistory({ page: currentPage + 1 });
    });
  }

  function goVisitorScanHistoryPreviousPage() {
    const currentPage = Number.parseInt(visitorScanHistoryPage, 10) || 1;
    if (currentPage <= 1) return false;
    loadVisitorScanHistory({ page: currentPage - 1 });
    return true;
  }

  function goVisitorScanHistoryNextPage() {
    const currentPage = Number.parseInt(visitorScanHistoryPage, 10) || 1;
    const totalPages = Number.parseInt(visitorScanHistoryTotalPages, 10) || 1;
    if (currentPage >= totalPages) return false;
    loadVisitorScanHistory({ page: currentPage + 1 });
    return true;
  }

  function handleVisitorScanJumpToPage() {
    const jumpInput = document.getElementById('visitorScanHistoryJumpInput');
    if (!jumpInput) return;

    const totalPages = Number.parseInt(visitorScanHistoryTotalPages, 10) || 1;
    const rawValue = jumpInput.value.trim();

    if (rawValue === '') {
      if (window.toast) window.toast.info('Enter a page number first');
      return;
    }

    let targetPage = Number.parseInt(rawValue, 10);
    if (!Number.isInteger(targetPage) || targetPage < 1) {
      if (window.toast) window.toast.warning('Please enter a valid page number');
      jumpInput.focus();
      return;
    }

    targetPage = Math.min(targetPage, totalPages);
    jumpInput.value = String(targetPage);

    const currentPage = Number.parseInt(visitorScanHistoryPage, 10) || 1;
    if (targetPage === currentPage) {
      if (window.toast) window.toast.info(`Already on page ${currentPage}`);
      return;
    }

    setVisitorScanFilterStatus(true);
    loadVisitorScanHistory({ page: targetPage, fromFilter: true });
  }

  const visitorScanJumpBtn = document.getElementById('visitorScanHistoryJumpBtn');
  if (visitorScanJumpBtn) {
    visitorScanJumpBtn.addEventListener('click', () => {
      handleVisitorScanJumpToPage();
    });
  }

  const visitorScanJumpInput = document.getElementById('visitorScanHistoryJumpInput');
  if (visitorScanJumpInput) {
    visitorScanJumpInput.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      handleVisitorScanJumpToPage();
    });
  }

  if (hasKeyboardRegistry) {
    window.keyboardShortcuts.register('ctrl+shift+v', () => {
      if (!isVisitorPageActive()) return false;
      loadVisitorPasses();
      loadVisitorScanHistory();
      return true;
    }, {
      id: 'guard.visitor.refresh',
      description: 'Refresh visitor passes and scan history',
      preventDefault: true,
      allowWhileTyping: false
    });

    window.keyboardShortcuts.register('ctrl+shift+e', () => {
      if (!isVisitorPageActive()) return false;
      const exportVisitorScanHistoryPageBtn = document.getElementById('exportVisitorScanHistoryPage');
      if (!exportVisitorScanHistoryPageBtn || exportVisitorScanHistoryPageBtn.disabled || exportVisitorScanHistoryPageBtn.offsetParent === null) {
        return false;
      }

      (async () => {
        const selectedScope = await promptVisitorScanExportScope('page');
        if (!selectedScope) return;
        startVisitorScanHistoryExport({
          button: exportVisitorScanHistoryPageBtn,
          label: document.getElementById('exportVisitorScanHistoryPageLabel'),
          scope: selectedScope,
          idleLabel: 'Export Page',
          successMessage: selectedScope === 'page'
            ? 'Current scan-history page export started'
            : 'Full filtered scan history export started'
        });
      })();

      return true;
    }, {
      id: 'guard.visitor.exportPage',
      description: 'Export visitor scan history',
      preventDefault: true,
      allowWhileTyping: false
    });

    window.keyboardShortcuts.register('alt+arrowleft', () => {
      if (!isVisitorPageActive()) return false;
      return goVisitorScanHistoryPreviousPage();
    }, {
      id: 'guard.visitor.scanHistory.prevPage',
      description: 'Go to previous scan-history page',
      preventDefault: true,
      allowWhileTyping: false
    });

    window.keyboardShortcuts.register('alt+arrowright', () => {
      if (!isVisitorPageActive()) return false;
      return goVisitorScanHistoryNextPage();
    }, {
      id: 'guard.visitor.scanHistory.nextPage',
      description: 'Go to next scan-history page',
      preventDefault: true,
      allowWhileTyping: false
    });

    window.keyboardShortcuts.register('ctrl+shift+f', () => {
      if (!isVisitorPageActive()) return false;
      const input = document.getElementById('visitorScanSearchInput');
      if (!input) return false;
      input.focus();
      input.select?.();
      return true;
    }, {
      id: 'guard.visitor.scanHistory.focusSearch',
      description: 'Focus scan-history search input',
      preventDefault: true,
      allowWhileTyping: true
    });

    window.keyboardShortcuts.register('ctrl+shift+j', () => {
      if (!isVisitorPageActive()) return false;
      const input = document.getElementById('visitorScanHistoryJumpInput');
      if (!input) return false;
      input.focus();
      input.select?.();
      return true;
    }, {
      id: 'guard.visitor.scanHistory.focusJump',
      description: 'Focus scan-history jump-to-page input',
      preventDefault: true,
      allowWhileTyping: true
    });
  }

  // Visitor search
  const visitorSearchInput = document.getElementById('visitorSearchInput');
  if (visitorSearchInput) {
    visitorSearchInput.addEventListener('input', function () {
      const searchTerm = this.value.toLowerCase();
      const cards = document.querySelectorAll('#visitorPassesContainer > div');

      cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    });
  }  // ====== USB RFID KEYBOARD WEDGE INTERCEPTOR ======

  startVisitorPassesAutoRefresh();
  (function initRfidWedgeListener() {
    let rfidBuffer = '';
    let rfidTimeoutToken = null;
    const rfidScannerTimeout = 100; // ms
    const rfidMinLength = 8;
    let isHardwareScanBusy = false;

    document.addEventListener('keydown', function (e) {
        // Buffering Logic
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
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                        const val = activeEl.value;
                        if (val.endsWith(capturedUid)) {
                            activeEl.value = val.substring(0, val.length - capturedUid.length);
                        }
                    }

                    handleHardwareScan(capturedUid);
                } else {
                    // Regular Enter, reset buffer
                    rfidBuffer = '';
                }
                return;
            }

            // Normal character - add to buffer
            rfidBuffer += e.key;

            rfidTimeoutToken = setTimeout(() => {
                if (rfidBuffer.length >= rfidMinLength) {
                    const capturedUid = rfidBuffer.trim();
                    rfidBuffer = '';

                    // Cleanup focused input if hardware scan occurred without Enter
                    const activeEl = document.activeElement;
                    if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA')) {
                        const val = activeEl.value;
                        if (val.endsWith(capturedUid)) {
                            activeEl.value = val.substring(0, val.length - capturedUid.length);
                        }
                    }

                    handleHardwareScan(capturedUid);
                } else {
                    rfidBuffer = '';
                }
            }, rfidScannerTimeout);
        }
    }, true); // Use capture phase for maximum priority

    async function handleHardwareScan(uid) {
      if (isHardwareScanBusy) {
        guardLog('[RFID] Ignoring scan while another scan is processing');
        return;
      }
      isHardwareScanBusy = true;
        guardLog('[RFID] Processing hardware scan:', uid);
        
        try {
        await new Promise((resolve) => setTimeout(resolve, 1000));

            const formData = new URLSearchParams();
            formData.append('rfid_uid', uid);
              formData.append('csrf_token', getCsrfToken());
            formData.append('session_type', 'guard');

            const response = await fetch('../../api/rfid/scan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: formData.toString()
            });

            const data = await response.json();
            if (data.success) {
                if (data.scan_result === 'access_granted') {
                // Access-granted details are shown by the RFID image overlay with its own countdown.
                    if (window.toast) window.toast.success(data.message || 'Access Granted');
                } else if (data.unknown_uid || data.inactive_uid) {
                    // Show a warning for unbound or inactive tags instead of an error
                  if (window.toast) window.toast.warning(data.message || 'Unknown RFID Tag', 5000);
                } else {
                    if (window.toast) window.toast.info(data.message || 'Scan Processed');
                }
            } else {
                // Actual system or validation error
                if (window.toast) window.toast.error(data.error || data.message || 'Scan Failed');
            }
        } catch (error) {
            console.error('[RFID] Hardware scan error:', error);
            if (window.toast) window.toast.error('Network error processing scan');
          } finally {
            isHardwareScanBusy = false;
        }
    }
  })();

  // ====== RFID SCAN POLLING ======
  (function initRfidScanPoller() {
    const POLL_INTERVAL = 3000;

    let lastLogTime = null;
    let pollTimer     = null;
    let pollAbortController = null;
    let initialPoll   = true;   // suppress overlay on first load

    function show(data) {
      const isIn = (data.status || '').toUpperCase() === 'IN';
      showGuardRfidScanDetail(data);

      // Play a subtle notification sound cue (Web Audio beep)
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.value = isIn ? 880 : 440;
        gain.gain.value = 0.12;
        osc.start(); osc.stop(ctx.currentTime + 0.15);
      } catch(e) { /* audio not available */ }
      __vsLog('[RFID-POLL] Scan detail shown in Entry Details:', data.plate_number, data.status);
    }

    async function poll() {
      try {
        // Cancel previous request if still pending (prevents duplicate in-flight requests)
        if (pollAbortController) {
          pollAbortController.abort();
        }
        pollAbortController = new AbortController();

        const res = await fetch('../fetch/fetch_rfid_scan.php?_=' + Date.now(), {
          credentials: 'same-origin',
          signal: pollAbortController.signal
        });
        if (!res.ok) { initialPoll = false; return; }
        
        let json;
        try {
          json = await res.json();
        } catch (parseError) {
          __vsLog('[RFID-POLL] JSON parse error:', parseError.message);
          initialPoll = false;
          return;
        }
        
        if (!json.success || !json.data) { initialPoll = false; return; }

        const d = json.data;
        const logKey = d.log_id 
          ? String(d.log_id) 
          : d.plate_number + '|' + (d.created_at || d.log_time);

        if (logKey !== lastLogTime) {
          lastLogTime = logKey;
          // On the very first poll after page load, just record the current
          // scan so it is not re-shown as "new" on every refresh.
          if (initialPoll) {
            initialPoll = false;
            __vsLog('[RFID-POLL] Seeded initial scan key (no overlay):', logKey);
            return;
          }
          show(d);
          // Also refresh the logs table if currently on logs page
          if (typeof loadLogs === 'function') {
            const logsPage = document.getElementById('page-logs');
            if (logsPage && !logsPage.classList.contains('hidden')) {
              loadLogs(currentLogPage);
            }
          }
        }
      } catch(e) {
        // Network error - silently retry next interval
      }
    }

    // Start polling
    poll();
    pollTimer = setInterval(poll, POLL_INTERVAL);

    // Pause polling when tab backgrounded, resume on focus
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        clearInterval(pollTimer); pollTimer = null;
        if (pollAbortController) {
          pollAbortController.abort();
          pollAbortController = null;
        }
      } else {
        if (!pollTimer) { poll(); pollTimer = setInterval(poll, POLL_INTERVAL); }
      }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
      if (pollTimer) clearInterval(pollTimer);
      if (pollAbortController) pollAbortController.abort();
    });

    __vsLog('[RFID-POLL] Scan poller initialized (interval: ' + POLL_INTERVAL + 'ms)');
  })();

  // ====== QR SCANNER MODAL FUNCTIONS ======
  let qrScanner = null;

  window.showQRScannerModal = function () {
    const modal = document.getElementById('qrScannerModal');
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    const isLocalHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    if (!window.isSecureContext && !isLocalHost) {
      if (window.toast) window.toast.error('Camera scanning requires HTTPS in hosted environments.');
      closeQRScannerModal();
      return;
    }

    // Initialize or resume QR scanner
    setTimeout(() => {
      if (typeof Html5Qrcode === 'undefined') {
        if (window.toast) window.toast.error('QR Scanner library not loaded');
        closeQRScannerModal();
        return;
      }

      const qrReaderDiv = document.getElementById('qr-reader');
      const resultsDiv = document.getElementById('qr-reader-results');

      if (!qrReaderDiv) return;

      try {
        // Create new scanner instance if needed
        if (!qrScanner) {
          qrScanner = new Html5Qrcode('qr-reader');
        }

        // Start camera
        qrScanner.start(
          { facingMode: 'environment' },
          { fps: 30, qrbox: { width: 250, height: 250 } },
          (decodedText) => {
            handleQRScan(decodedText, resultsDiv);
          },
          (errorMessage) => {
            // Silent error handling - just continue scanning
          }
        ).catch((err) => {
          console.warn('[QR Scanner] Failed to start:', err);
          if (window.toast) window.toast.error('Failed to access camera');
          closeQRScannerModal();
        });
      } catch (err) {
        console.warn('[QR Scanner] Error initializing:', err);
        if (window.toast) window.toast.error('QR Scanner initialization failed');
        closeQRScannerModal();
      }
    }, 100);
  };

  window.closeQRScannerModal = function () {
    const modal = document.getElementById('qrScannerModal');
    if (!modal) return;

    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';

    // Stop scanner
    if (qrScanner && qrScanner.isScanning) {
      qrScanner.stop().catch(() => {});
    }

    // Clear results
    const resultsDiv = document.getElementById('qr-reader-results');
    if (resultsDiv) {
      resultsDiv.classList.add('hidden');
      resultsDiv.innerHTML = '';
    }
  };

  window.handleQRScan = async function (qrToken, resultsDiv) {
    if (!resultsDiv) return;

    // Stop scanning to prevent duplicate scans
    if (qrScanner && qrScanner.isScanning) {
      qrScanner.stop().catch(() => {});
    }

    // Disable scanner temporarily
    const qrReaderDiv = document.getElementById('qr-reader');
    if (qrReaderDiv) {
      qrReaderDiv.style.opacity = '0.5';
      qrReaderDiv.style.pointerEvents = 'none';
    }

    // Show processing state
    resultsDiv.innerHTML = '<span class="text-gray-600 dark:text-gray-300">Processing...</span>';
    resultsDiv.classList.remove('hidden');

    try {
      // Determine direction
      const directionSelect = document.getElementById('qr-direction-select');
      const direction = directionSelect ? directionSelect.value : 'in';

      // Submit to API
      const response = await fetch('../../guard/api/validate_qr.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          token: qrToken,
          direction: direction,
          csrf_token: getCsrfToken()
        })
      });

      const data = await response.json();

      if (data.success) {
        // Show success message
        resultsDiv.innerHTML = `
          <div class="bg-green-100 dark:bg-green-900/30 border border-green-500 rounded p-3">
            <p class="font-semibold text-green-800 dark:text-green-300">Access Granted!</p>
            <p class="text-sm text-green-700 dark:text-green-400">${escapeHtml(data.visitor_name)}</p>
            <p class="text-xs text-green-600 dark:text-green-500">${escapeHtml(data.purpose || 'No purpose specified')}</p>
          </div>
        `;
        resultsDiv.classList.remove('hidden');

        if (window.toast) window.toast.success(data.message);

        // Auto-close after 2 seconds and refresh logs
        setTimeout(() => {
          closeQRScannerModal();
          requestLogsReload(1);
        }, 2000);
      } else {
        resultsDiv.innerHTML = `
          <div class="bg-red-100 dark:bg-red-900/30 border border-red-500 rounded p-3">
            <p class="font-semibold text-red-800 dark:text-red-300">Access Denied</p>
            <p class="text-sm text-red-700 dark:text-red-400">${escapeHtml(data.message || 'Unknown error')}</p>
          </div>
        `;
        resultsDiv.classList.remove('hidden');

        if (window.toast) window.toast.error(data.message);

        // Re-enable scanner after delay
        setTimeout(() => {
          if (qrReaderDiv) {
            qrReaderDiv.style.opacity = '1';
            qrReaderDiv.style.pointerEvents = 'auto';
          }
          const qrModal = document.getElementById('qrScannerModal');
          const latestResultsDiv = document.getElementById('qr-reader-results');
          if (qrModal && qrModal.classList.contains('hidden')) return;
          // Restart scanner
          if (qrScanner && !qrScanner.isScanning) {
            qrScanner.start(
              { facingMode: 'environment' },
              { fps: 30, qrbox: { width: 250, height: 250 } },
              (decodedText) => handleQRScan(decodedText, latestResultsDiv),
              () => {}
            ).catch(() => {});
          }
        }, 2000);
      }
    } catch (err) {
      console.error('[QR Scanner] API Error:', err);
      resultsDiv.innerHTML = `
        <div class="bg-red-100 dark:bg-red-900/30 border border-red-500 rounded p-3">
          <p class="font-semibold text-red-800 dark:text-red-300">Error</p>
          <p class="text-sm text-red-700 dark:text-red-400">Failed to process QR code</p>
        </div>
      `;
      resultsDiv.classList.remove('hidden');

      if (window.toast) window.toast.error('Failed to process QR code');

      // Re-enable scanner after delay
      setTimeout(() => {
        if (qrReaderDiv) {
          qrReaderDiv.style.opacity = '1';
          qrReaderDiv.style.pointerEvents = 'auto';
        }
        const qrModal = document.getElementById('qrScannerModal');
        const latestResultsDiv = document.getElementById('qr-reader-results');
        if (qrModal && qrModal.classList.contains('hidden')) return;
        // Restart scanner
        if (qrScanner && !qrScanner.isScanning) {
          qrScanner.start(
            { facingMode: 'environment' },
            { fps: 30, qrbox: { width: 250, height: 250 } },
            (decodedText) => handleQRScan(decodedText, latestResultsDiv),
            () => {}
          ).catch(() => {});
        }
      }, 2000);
    }
  };

  // ====== MANUAL LOG MODAL FUNCTIONS ======
  window.showManualLogModal = function () {
    const modal = document.getElementById('manualLogModal');
    if (!modal) {
      if (window.toast) window.toast.error('Manual log modal not found');
      return;
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Focus on plate number input
    const plateInput = document.getElementById('manual-plate-input');
    if (plateInput) {
      setTimeout(() => plateInput.focus(), 100);
    }
  };

  window.closeManualLogModal = function () {
    const modal = document.getElementById('manualLogModal');
    if (!modal) return;

    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';

    // Clear form
    const form = document.getElementById('manual-log-form');
    if (form) form.reset();
  };

  window.submitManualLog = async function (e) {
    if (e) {
      e.preventDefault();
    }

    const plateInput = document.getElementById('manual-plate-input');
    const directionSelect = document.getElementById('manual-direction-select');
    const submitBtn = document.querySelector('#manual-log-form button[type="submit"]');

    if (!plateInput || !plateInput.value.trim()) {
      if (window.toast) window.toast.error('Please enter a plate number');
      return;
    }

    const plateNumber = plateInput.value.trim().toUpperCase();
    const direction = directionSelect ? directionSelect.value : 'in';

    // Disable button during submission
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    }

    try {
      const response = await fetch('../../guard/api/manual_log.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          plate_number: plateNumber,
          direction: direction,
          csrf_token: getCsrfToken()
        })
      });

      const data = await response.json();

      if (data.success) {
        if (window.toast) window.toast.success(data.message);

        // Close modal and refresh logs
        setTimeout(() => {
          closeManualLogModal();
          requestLogsReload(1);
        }, 500);
      } else {
        if (window.toast) window.toast.error(data.message);
      }
    } catch (err) {
      console.error('[Manual Log] API Error:', err);
      if (window.toast) window.toast.error('Failed to submit manual log entry');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Entry';
      }
    }
  };

  // Close modals with ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeQRScannerModal();
      closeManualLogModal();
      closeGuardVisitorModal();
    }
  });

  // Initial load
  bindGuardActionButtons();
  initGuardAddVisitorForm();
  initGuardVehiclesControls();
  loadLogs();
  loadHomeowners();

  guardLog('[GUARD] Guard panel initialized successfully');
  guardLog('[GUARD] Keyboard shortcuts: Ctrl+K (Search), Ctrl+Shift+R (Refresh Logs), Ctrl+E (Export)');
});
