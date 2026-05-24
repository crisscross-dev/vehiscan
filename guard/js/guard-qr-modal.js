/**
 * Guard QR Code Zoom Modal
 * Click QR codes to view them in full size
 */

(function() {
  'use strict';

  function registerEscapeHandler(modal) {
    if (window.keyboardShortcuts && typeof window.keyboardShortcuts.register === 'function') {
      window.keyboardShortcuts.register('escape', function() {
        if (!modal.classList.contains('active')) return false;
        closeQRZoom();
        return true;
      }, {
        id: 'guard.qrmodal.escape',
        description: 'Close guard QR zoom modal',
        preventDefault: false,
        allowWhileTyping: true
      });
      return;
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.classList.contains('active')) {
        closeQRZoom();
      }
    });
  }
  
  // Use global logger provided by `logger.js`
  __vsLog('[QR MODAL] Initializing...');
  
  // Create modal on page load
  function createQRModal() {
    // Check if modal already exists
    if (document.getElementById('qrZoomModal')) {
      __vsLog('[QR MODAL] Modal already exists');
      return;
    }
    
    const modal = document.createElement('div');
    modal.id = 'qrZoomModal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div class="qr-modal-content">
        <button type="button" class="qr-modal-close" onclick="closeQRZoom()" aria-label="Close">&times;</button>
        <div class="qr-modal-image-wrapper">
          <img id="qrZoomImage" class="qr-modal-image" src="" alt="QR Code">
          <img class="qr-modal-logo" src="../../assets/images/ville_de_palme.png" alt="Logo">
        </div>
        <div class="qr-modal-info">
          <p>Click outside or press ESC to close</p>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    __vsLog('[QR MODAL] Modal created');
    
    // Close on outside click
    modal.addEventListener('click', function(e) {
      if (e.target === modal) {
        closeQRZoom();
      }
    });
    
    registerEscapeHandler(modal);
  }
  
  // Open QR zoom modal
  window.openQRZoom = function(src) {
    if (!src || src.includes('placeholder')) {
      __vsLog('[QR MODAL] Invalid QR source:', src);
      return;
    }
    
    __vsLog('[QR MODAL] Opening with src:', src);
    const modal = document.getElementById('qrZoomModal');
    const img = document.getElementById('qrZoomImage');
    
    if (modal && img) {
      img.src = src;
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
    } else {
      console.error('[QR MODAL] Modal or image element not found');
    }
  };
  
  // Close QR zoom modal
  window.closeQRZoom = function() {
    __vsLog('[QR MODAL] Closing');
    const modal = document.getElementById('qrZoomModal');
    if (modal) {
      modal.classList.remove('active');
      modal.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
    }
  };
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createQRModal);
  } else {
    createQRModal();
  }
  
  // Event delegation for dynamically loaded QR codes
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('qr-clickable')) {
      __vsLog('[QR MODAL] QR image clicked:', e.target.src);
      openQRZoom(e.target.src);
    }
  });
  
  __vsLog('[QR MODAL] Initialization complete');
})();
