// TailAdmin Action Dropdown Handler (migrated from inline admin_panel.php)
(function(){
  'use strict';
  const OPEN_DROPDOWN_SELECTOR = '.ta-action-dropdown.open';

  function closeDropdown(dd) {
    dd.classList.remove('open');
    const trigger = dd.querySelector('.ta-action-btn');
    const menu = dd.querySelector('.ta-action-menu');
    if (menu) {
      menu.removeAttribute('style');
      menu.setAttribute('aria-hidden', 'true');
    }
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
  }
  function closeAllDropdowns(except) {
    document.querySelectorAll(OPEN_DROPDOWN_SELECTOR).forEach(function(d) {
      if (d !== except) closeDropdown(d);
    });
  }
  window.__vsAdminCloseActionDropdowns = function() {
    closeAllDropdowns(null);
  };
  function positionDrop(dd) {
    const menu = dd.querySelector('.ta-action-menu');
    const trigger = dd.querySelector('.ta-action-btn');
    if (!menu || !trigger) return;
    menu.style.cssText = 'position:fixed;visibility:hidden;display:block;right:auto;width:auto;left:-9999px;top:0;';
    const menuWidth = Math.max(menu.offsetWidth, 160);
    const rect = trigger.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const dropUp = spaceBelow < 180 && rect.top > 180;
    let leftPos = rect.right - menuWidth;
    if (leftPos < 4) leftPos = 4;
    if (leftPos + menuWidth > window.innerWidth - 4) leftPos = window.innerWidth - menuWidth - 4;
    menu.style.cssText = [
      'position:fixed',
      'z-index:9990',
      'width:' + menuWidth + 'px',
      'right:auto',
      'margin:0',
      'left:' + leftPos + 'px',
      dropUp
        ? 'top:auto;bottom:' + (window.innerHeight - rect.top + 4) + 'px'
        : 'top:' + (rect.bottom + 4) + 'px;bottom:auto'
    ].join(';');
    dd.classList.toggle('drop-up', dropUp);
  }

  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.ta-action-btn');
    if (btn) {
      e.stopPropagation();
      const dd = btn.closest('.ta-action-dropdown');
      const wasOpen = dd.classList.contains('open');
      closeAllDropdowns(dd);
      if (wasOpen) {
        closeDropdown(dd);
      } else {
        if (typeof window.__vsAdminCloseShellPopovers === 'function') {
          window.__vsAdminCloseShellPopovers();
        }
        dd.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
        const menu = dd.querySelector('.ta-action-menu');
        if (menu) menu.setAttribute('aria-hidden', 'false');
        positionDrop(dd);
      }
      return;
    }
    const item = e.target.closest('.ta-action-menu-item');
    if (item) {
      closeDropdown(item.closest('.ta-action-dropdown'));
      return;
    }
    closeAllDropdowns(null);
  });

  const useSharedKeyboardShortcuts = !!(window.keyboardShortcuts && typeof window.keyboardShortcuts.register === 'function');
  if (useSharedKeyboardShortcuts) {
    window.keyboardShortcuts.register('escape', function() {
      const hasOpenDropdown = !!document.querySelector(OPEN_DROPDOWN_SELECTOR);
      if (hasOpenDropdown) {
        closeAllDropdowns(null);
        return true;
      }
      return false;
    }, {
      id: 'admin.actionDropdown.escape',
      description: 'Close admin action dropdowns',
      preventDefault: false,
      allowWhileTyping: true
    });
  } else {
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeAllDropdowns(null);
      }
    });
  }

  ['scroll', 'resize'].forEach(function(ev) {
    window.addEventListener(ev, function() {
      const open = document.querySelector(OPEN_DROPDOWN_SELECTOR);
      if (open) positionDrop(open);
    }, { passive: true });
  });

  const contentArea = document.getElementById('content-area');
  if (contentArea) {
    contentArea.addEventListener('scroll', function() {
      closeAllDropdowns(null);
    }, { passive: true });
  }
})();
