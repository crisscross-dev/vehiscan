// Initialize admin CSRF from meta tag if not already provided
(function(){
  'use strict';
  try {
    if (typeof window.__ADMIN_CSRF__ === 'undefined' || !window.__ADMIN_CSRF__) {
      const m = document.querySelector('meta[name="csrf-token"]');
      if (m && m.content) {
        window.__ADMIN_CSRF__ = m.content;
      }
    }
  } catch (e) {
    // silent
  }
})();
