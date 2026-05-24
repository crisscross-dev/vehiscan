/**
 * Real-Time Updates Module
 * Polls for new data and updates UI without full page refresh
 */

const REALTIME_DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
const realtimeLog = (...args) => { if (REALTIME_DEBUG) console.log(...args); };

(function () {
  'use strict';

  const POLL_INTERVAL = 10000; // 10 seconds
  let lastLogId = 0;
  let lastApprovalCount = 0;
  let lastPassCount = 0;
  let lastProfileRequestCount = 0;
  let pollTimer = null;
  let isPolling = false;

  /**
   * Initialize real-time updates
   */
  function initRealTimeUpdates() {
    realtimeLog('[RealTime] Initializing real-time updates...');

    // Start polling when on dashboard or logs page
    startPolling();

    // Stop polling when page is hidden (tab inactive)
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stopPolling();
      } else {
        startPolling();
      }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', stopPolling);
  }

  /**
   * Start polling for updates
   */
  function startPolling() {
    if (isPolling) return;

    isPolling = true;
    realtimeLog('[RealTime] Started polling');

    // Initial check
    checkForUpdates();

    // Set up interval
    pollTimer = setInterval(checkForUpdates, POLL_INTERVAL);
  }

  /**
   * Stop polling
   */
  function stopPolling() {
    if (!isPolling) return;

    isPolling = false;
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    realtimeLog('[RealTime] Stopped polling');
  }

  /**
   * Check for new logs and pending approvals
   */
  async function checkForUpdates() {
    try {
      let latestOverview = null;

      const overviewResponse = await fetch('api/get_pending_approval_overview.php', {
        method: 'GET',
        credentials: 'same-origin'
      });

      if (overviewResponse.ok) {
        const overviewData = await overviewResponse.json();
        if (overviewData && overviewData.success) {
          latestOverview = overviewData;
        }
      }

      // Check for new access logs
      const logsResponse = await fetch('api/check_new_logs.php', {
        method: 'GET',
        credentials: 'same-origin'
      });

      if (logsResponse.ok) {
        const logsData = await logsResponse.json();
        if (logsData.success && logsData.latest_log_id > lastLogId) {
          const newCount = logsData.new_count || 1;

          // Only show notification if this is NOT the initial load
          // On first load, lastLogId is 0, so we just set it without notifying
          if (lastLogId !== 0) {
            showNewLogsNotification(newCount);
          }

          lastLogId = logsData.latest_log_id;

          // Auto-refresh if on logs page
          const currentPage = document.querySelector('.page-content.active');
          if (currentPage && currentPage.id === 'page-access-logs') {
            refreshAccessLogs();
          }
        }
      } else if (logsResponse.status === 403) {
        console.warn('[RealTime] Session expired, stopping polling');
        stopPolling();
        return;
      }

      const approvalsBadge = document.getElementById('pendingApprovalsBadge');
      if (approvalsBadge) {
        // Check for new pending approvals only when approvals UI is available
        const approvalsResponse = await fetch('api/check_pending_approvals.php', {
          method: 'GET',
          credentials: 'same-origin'
        });

        if (approvalsResponse.ok) {
          const approvalsData = await approvalsResponse.json();
          if (approvalsData.success && approvalsData.pending_count !== lastApprovalCount) {
            lastApprovalCount = approvalsData.pending_count;
            updateApprovalsBadge(approvalsData.pending_count);
          }
        } else if (approvalsResponse.status === 403) {
          console.warn('[RealTime] Session expired, stopping polling');
          stopPolling();
          return;
        }
      }

      const passesBadge = document.getElementById('pendingPassesBadge');
      if (passesBadge) {
        const passesResponse = await fetch('api/get_pending_passes.php', {
          method: 'GET',
          credentials: 'same-origin'
        });

        if (passesResponse.ok) {
          const passesData = await passesResponse.json();
          const passCount = Array.isArray(passesData)
            ? passesData.length
            : (Number(passesData.pending_count || 0) || 0);

          if (passCount !== lastPassCount) {
            lastPassCount = passCount;
            updatePassesBadge(passCount);
          }
        } else if (passesResponse.status === 403) {
          stopPolling();
          return;
        }
      }

      const notifDot = document.getElementById('notifDot');
      const notificationList = document.getElementById('notificationList');
      if (notifDot && notificationList) {
        if (latestOverview) {
          const approvalsFromOverview = Number(latestOverview.pending_accounts || 0) || 0;
          const profileCount = Number(latestOverview.pending_profile_requests || 0) || 0;

          if (document.getElementById('pendingApprovalsBadge')) {
            updateApprovalsBadge(approvalsFromOverview);
          }

          lastApprovalCount = approvalsFromOverview;
          lastProfileRequestCount = profileCount;
        }

        renderNotificationSummary({
          approvals: lastApprovalCount,
          passes: lastPassCount,
          profileRequests: lastProfileRequestCount
        });
      }

    } catch (error) {
      console.error('[RealTime] Poll error:', error);
      // Keep polling; transient network failures should self-heal.
    }
  }

  /**
   * Show notification for new logs
   */
  function showNewLogsNotification(count) {
    if (typeof showGrowl === 'function') {
      showGrowl(`${count} new access log${count > 1 ? 's' : ''}`, 'info');
    }

    // Play sound notification (optional)
    playNotificationSound();
  }

  /**
   * Update approvals badge in sidebar
   */
  function updateApprovalsBadge(count) {
    const badge = document.getElementById('pendingApprovalsBadge');
    if (badge) {
      if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-flex';
      } else {
        badge.style.display = 'none';
      }
    }
  }

  function updatePassesBadge(count) {
    const badge = document.getElementById('pendingPassesBadge');
    if (!badge) return;

    if (count > 0) {
      badge.textContent = count;
      badge.style.display = 'inline-flex';
    } else {
      badge.style.display = 'none';
    }
  }

  function renderNotificationSummary(payload) {
    const notifDot = document.getElementById('notifDot');
    const notificationList = document.getElementById('notificationList');
    if (!notifDot || !notificationList) return;

    const approvals = Number(payload.approvals || 0);
    const passes = Number(payload.passes || 0);
    const profileRequests = Number(payload.profileRequests || 0);
    const total = approvals + passes + profileRequests;

    notifDot.classList.toggle('hidden', total === 0);

    if (total === 0) {
      notificationList.innerHTML = '<div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">No new notifications</div>';
      return;
    }

    const buildSummaryItem = (kind, label, count) => {
      const iconByKind = {
        approvals: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        profile: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>',
        passes: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>'
      };

      const colorByKind = {
        approvals: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20',
        profile: 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20',
        passes: 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20'
      };

      return `
        <div class="ta-notification-item">
          <div class="flex gap-3 items-start">
            <svg class="h-5 w-5 flex-shrink-0 mt-0.5 ${colorByKind[kind] || colorByKind.profile}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              ${iconByKind[kind] || iconByKind.profile}
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white">${count} ${label}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Needs review</p>
            </div>
          </div>
        </div>
      `;
    };

    const items = [];
    if (approvals > 0) items.push(buildSummaryItem('approvals', `pending account approval${approvals > 1 ? 's' : ''}`, approvals));
    if (profileRequests > 0) items.push(buildSummaryItem('profile', `pending profile request${profileRequests > 1 ? 's' : ''}`, profileRequests));
    if (passes > 0) items.push(buildSummaryItem('passes', `pending visitor pass${passes > 1 ? 'es' : ''}`, passes));

    notificationList.innerHTML = items.join('');
  }

  /**
   * Refresh access logs table
   */
  function refreshAccessLogs() {
    const refreshBtn = document.querySelector('#refreshLogsBtn');
    if (refreshBtn) {
      refreshBtn.click();
    }
  }

  /**
   * Play notification sound - DISABLED due to CSP policy
   */
  function playNotificationSound() {
    // Disabled to prevent CSP violations
    // Audio notifications removed as they violate Content Security Policy
  }

  // Auto-initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRealTimeUpdates);
  } else {
    initRealTimeUpdates();
  }

  // Export to global scope
  window.RealTimeUpdates = {
    start: startPolling,
    stop: stopPolling,
    checkNow: checkForUpdates
  };

})();


