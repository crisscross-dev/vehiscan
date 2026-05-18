// Guard page inline behaviors: live clock and auto-refresh polling
(function(){
  'use strict';
  try {
    function updateLiveTime() {
      const liveTime = document.getElementById('liveTime');
      if (liveTime) {
        const now = new Date();
        liveTime.textContent = now.toLocaleTimeString('en-US', {
          hour: 'numeric',
          minute: '2-digit',
          second: '2-digit',
          hour12: true
        });
      }
    }
    setInterval(updateLiveTime, 1000);
    updateLiveTime();

    let autoRefreshInterval = null;
    const POLLING_INTERVAL = 15000; // 15 seconds

    function startAutoRefresh() {
      if (autoRefreshInterval) return;
      __vsLog && __vsLog('[GUARD] Starting auto-refresh polling (15s)');
      autoRefreshInterval = setInterval(() => {
        const activeTab = document.querySelector('.menu-item[data-page].active')?.dataset.page;
        if (activeTab === 'logs' || activeTab === 'visitor' || activeTab === 'visitors' || !activeTab) {
          __vsLog && __vsLog('[GUARD] Auto-refreshing data...');
          if (typeof loadLogs === 'function') loadLogs(currentLogPage, { silent: true });
          if (typeof loadVisitorPasses === 'function') loadVisitorPasses({ silent: true });
        }
      }, POLLING_INTERVAL);
    }

    function stopAutoRefresh() {
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
        __vsLog && __vsLog('[GUARD] Auto-refresh polling stopped');
      }
    }

    // Start by default
    startAutoRefresh();

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stopAutoRefresh();
      else startAutoRefresh();
    });
  } catch (e) {
    console.error('[guard-inline] init error', e);
  }
})();
