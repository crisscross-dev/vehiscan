// Delegated action binder for data-action attributes
(function(){
  'use strict';

  function resolveFn(path) {
    if (!path) return null;
    const parts = path.split('.');
    let ctx = window;
    for (let p of parts) {
      if (ctx[p] === undefined) return null;
      ctx = ctx[p];
    }
    return typeof ctx === 'function' ? ctx : null;
  }

  function parseArgs(el) {
    // simple single arg via data-action-arg
    if (el.dataset.actionArg !== undefined) {
      const raw = el.dataset.actionArg;
      if (raw === 'this' || raw === 'el' || raw === 'self') return [el];
      return [raw];
    }
    // JSON args via data-action-args
    if (el.dataset.actionArgs) {
      try { return JSON.parse(el.dataset.actionArgs); } catch (e) { return [el.dataset.actionArgs]; }
    }
    return [];
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    try {
      e.preventDefault();
      const actionName = btn.dataset.action;
      const fn = resolveFn(actionName) || window[actionName];
      const args = parseArgs(btn);
      if (typeof fn === 'function') {
        fn.apply(btn, args);
      } else {
        console.warn('[inline-actions] No function found for action', actionName);
      }
    } catch (err) {
      console.error('[inline-actions] handler error', err);
    }
  });
})();
