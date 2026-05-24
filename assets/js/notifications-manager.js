/**
 * Notifications Manager
 * Centralized notification system for admin panel
 */

(function(global) {
  'use strict';

  const NOTIFICATION_TYPES = {
    INFO: 'info',
    SUCCESS: 'success',
    WARNING: 'warning',
    ERROR: 'error'
  };

  class NotificationManager {
    constructor() {
      this.notifications = [];
      this.panelEl = null;
      this.listEl = null;
      this.bellBtnEl = null;
      this.dotEl = null;
      this.isInitialized = false;
    }

    init() {
      if (this.isInitialized) return;

      this.panelEl = document.getElementById('notificationPanel');
      this.listEl = document.getElementById('notificationList');
      this.bellBtnEl = document.getElementById('notificationBellBtn');
      this.dotEl = document.getElementById('notifDot');

      if (!this.listEl || !this.bellBtnEl) {
        console.warn('[NotificationManager] Panel elements not found');
        return;
      }

      this.setupBellButton();
      this.isInitialized = true;
    }

    setupBellButton() {
      if (!this.bellBtnEl) return;

      // Avoid duplicate bindings when page-specific scripts also manage this bell.
      if (this.bellBtnEl.dataset.vsNotifBound === '1') {
        return;
      }
      this.bellBtnEl.dataset.vsNotifBound = '1';

      this.bellBtnEl.addEventListener('click', (e) => {
        e.stopPropagation();
        this.togglePanel();
      });

      document.addEventListener('click', (e) => {
        if (!this.panelEl?.classList.contains('hidden') && 
            !this.bellBtnEl?.contains(e.target) && 
            !this.panelEl?.contains(e.target)) {
          this.closePanel();
        }
      });
    }

    togglePanel() {
      if (this.panelEl.classList.contains('hidden')) {
        this.openPanel();
      } else {
        this.closePanel();
      }
    }

    openPanel() {
      if (!this.panelEl) return;

      if (window.RealTimeUpdates && typeof window.RealTimeUpdates.checkNow === 'function') {
        window.RealTimeUpdates.checkNow();
      }

      this.panelEl.classList.remove('hidden');
      this.panelEl.classList.add('open');
      this.panelEl.setAttribute('aria-hidden', 'false');
      this.bellBtnEl?.setAttribute('aria-expanded', 'true');
    }

    closePanel() {
      if (!this.panelEl) return;
      this.panelEl.classList.add('hidden');
      this.panelEl.classList.remove('open');
      this.panelEl.setAttribute('aria-hidden', 'true');
      this.bellBtnEl?.setAttribute('aria-expanded', 'false');
    }

    add(message, type = NOTIFICATION_TYPES.INFO, duration = 5000) {
      if (!this.isInitialized) this.init();

      const notification = {
        id: `notif-${Date.now()}`,
        message: String(message).substring(0, 200),
        type: type || NOTIFICATION_TYPES.INFO,
        timestamp: new Date(),
        read: false
      };

      this.notifications.unshift(notification);
      
      // Keep only last 50 notifications
      if (this.notifications.length > 50) {
        this.notifications = this.notifications.slice(0, 50);
      }

      this.updateUI();

      // Auto-dismiss non-persistent notifications
      if (duration > 0) {
        setTimeout(() => {
          this.notifications = this.notifications.filter(n => n.id !== notification.id);
          this.updateUI();
        }, duration);
      }

      return notification;
    }

    addSuccess(message) {
      return this.add(message, NOTIFICATION_TYPES.SUCCESS, 4000);
    }

    addError(message) {
      return this.add(message, NOTIFICATION_TYPES.ERROR, 6000);
    }

    addWarning(message) {
      return this.add(message, NOTIFICATION_TYPES.WARNING, 5000);
    }

    addInfo(message) {
      return this.add(message, NOTIFICATION_TYPES.INFO, 4000);
    }

    updateUI() {
      if (!this.listEl) return;

      const unreadCount = this.notifications.filter(n => !n.read).length;

      if (this.notifications.length === 0) {
        this.listEl.innerHTML = '<div class="text-center py-6 text-gray-400 dark:text-gray-500 text-sm">No notifications</div>';
        if (this.dotEl) this.dotEl.classList.add('hidden');
        return;
      }

      // Show dot if unread
      if (this.dotEl) {
        if (unreadCount > 0) {
          this.dotEl.classList.remove('hidden');
          this.dotEl.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
        } else {
          this.dotEl.classList.add('hidden');
        }
      }

      this.listEl.innerHTML = this.notifications
        .slice(0, 8)
        .map(n => this.renderNotification(n))
        .join('');

      // Mark as read on view
      this.notifications.forEach(n => { n.read = true; });
    }

    renderNotification(notif) {
      const iconSvg = {
        [NOTIFICATION_TYPES.SUCCESS]: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        [NOTIFICATION_TYPES.ERROR]: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l-4-4m0 0l-4 4m4-4v12"></path>',
        [NOTIFICATION_TYPES.WARNING]: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        [NOTIFICATION_TYPES.INFO]: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
      }[notif.type] || '<circle cx="12" cy="12" r="1"></circle>';

      const colorClass = {
        [NOTIFICATION_TYPES.SUCCESS]: 'text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20',
        [NOTIFICATION_TYPES.ERROR]: 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20',
        [NOTIFICATION_TYPES.WARNING]: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20',
        [NOTIFICATION_TYPES.INFO]: 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20'
      }[notif.type] || 'text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/20';

      const timeStr = new Date(notif.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

      return `
        <div class="ta-notification-item">
          <div class="flex gap-3 items-start">
            <svg class="h-5 w-5 flex-shrink-0 mt-0.5 ${colorClass}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              ${iconSvg}
            </svg>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2">${notif.message}</p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${timeStr}</p>
            </div>
          </div>
        </div>
      `;
    }

    clear() {
      this.notifications = [];
      this.updateUI();
    }

    markAllAsRead() {
      this.notifications.forEach(n => { n.read = true; });
      this.updateUI();
    }
  }

  // Export
  const manager = new NotificationManager();
  global.VehiScanNotifications = manager;
  
  // Auto-init when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => manager.init());
  } else {
    manager.init();
  }

})(typeof window !== 'undefined' ? window : global);
