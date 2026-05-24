/**
 * Enhanced Toast Notification System using SweetAlert2
 * Provides window.toast API with action buttons, positioning, and queue management
 * Location: assets/js/toast.js
 * Phase 3.1 Enhancement
 */

(function () {
  'use strict';

  const DEBUG = !!(window.vehiscanConfig && window.vehiscanConfig.debug);
  const debugLog = (...args) => { if (DEBUG) console.log(...args); };

  // Toast queue for managing multiple toasts
  const toastQueue = [];
  let isShowingToast = false;

  // Base toast configuration
  const getBaseConfig = (position = 'top-end', duration = 3000) => ({
    toast: true,
    position: position,
    showConfirmButton: false,
    timer: duration > 0 ? duration : null,
    timerProgressBar: duration > 0,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
    didClose: () => {
      isShowingToast = false;
      processQueue();
    }
  });

  // Process toast queue
  const processQueue = () => {
    if (toastQueue.length > 0 && !isShowingToast) {
      const nextToast = toastQueue.shift();
      nextToast();
    }
  };

  // Create toast with full options
  const createToast = function (options) {
    // Check if Swal is available
    if (typeof Swal === 'undefined') {
      console.error('SweetAlert2 is not loaded. Cannot show toast notification.');
      return;
    }

    const {
      message,
      type = 'info',
      duration = 3000,
      position = 'top-end',
      showAction = false,
      actionText = 'Action',
      onAction = null
    } = options;

    const showToast = () => {
      isShowingToast = true;

      const iconMap = {
        'success': 'success',
        'error': 'error',
        'warning': 'warning',
        'info': 'info'
      };

      const config = {
        ...getBaseConfig(position, duration),
        icon: iconMap[type] || 'info',
        title: message
      };

      // Add action button if requested
      if (showAction && onAction) {
        config.showConfirmButton = true;
        config.confirmButtonText = actionText;
        config.confirmButtonColor = '#3b82f6';
        config.timer = 0; // Disable auto-dismiss when action button present
        config.timerProgressBar = false;
      }

      Swal.fire(config).then((result) => {
        if (result.isConfirmed && onAction) {
          onAction();
        }
      });
    };

    // Add to queue or show immediately
    if (isShowingToast) {
      toastQueue.push(showToast);
    } else {
      showToast();
    }
  };

  // Create window.toast API
  window.toast = {
    // Basic show method (backward compatible)
    show: function (message, type = 'info', duration = 3000) {
      createToast({ message, type, duration });
    },

    // Convenience methods (backward compatible)
    success: function (message, duration = 3000) {
      createToast({ message, type: 'success', duration });
    },

    error: function (message, duration = 3000) {
      createToast({ message, type: 'error', duration });
    },

    warning: function (message, duration = 3000) {
      createToast({ message, type: 'warning', duration });
    },

    info: function (message, duration = 3000) {
      createToast({ message, type: 'info', duration });
    },

    // NEW: Show toast with action button
    showWithAction: function (message, type, actionText, onAction, duration = 0) {
      createToast({
        message,
        type,
        duration,
        showAction: true,
        actionText,
        onAction
      });
    },

    // NEW: Show toast at specific position
    showAt: function (message, type, position, duration = 3000) {
      const validPositions = [
        'top', 'top-start', 'top-end',
        'center', 'center-start', 'center-end',
        'bottom', 'bottom-start', 'bottom-end'
      ];

      if (!validPositions.includes(position)) {
        console.warn(`Invalid position: ${position}. Using 'top-end'`);
        position = 'top-end';
      }

      createToast({ message, type, position, duration });
    },

    // NEW: Persistent toast (no auto-dismiss)
    persistent: function (message, type = 'info') {
      createToast({ message, type, duration: 0 });
    },

    // NEW: Clear all queued toasts
    clearQueue: function () {
      toastQueue.length = 0;
    },

    // NEW: Get queue length
    getQueueLength: function () {
      return toastQueue.length;
    }
  };

  // Log that enhanced toast system is ready
  debugLog('[Toast] Enhanced toast notification system initialized (Phase 3.1)');

})();
