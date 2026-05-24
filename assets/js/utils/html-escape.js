(function (global) {
  'use strict';

  var root = global || window;
  root.VehiScanUtils = root.VehiScanUtils || {};

  root.VehiScanUtils.escapeHtml = function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };
})(typeof window !== 'undefined' ? window : this);