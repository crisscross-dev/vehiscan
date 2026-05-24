/**
 * QR Code Zoom Modal
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
        id: 'admin.qrmodal.escape',
        description: 'Close QR zoom modal',
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
  
  // Create modal on page load
  function createQRModal() {
    if (document.getElementById('qrZoomModal')) return;

    const modal = document.createElement('div');
    modal.id = 'qrZoomModal';
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div class="qr-modal-content">
        <button type="button" class="qr-modal-close" onclick="closeQRZoom()" aria-label="Close">&times;</button>
        <div class="qr-modal-image-wrapper">
          <img id="qrZoomImage" class="qr-modal-image" src="" alt="QR Code">
          <img class="qr-modal-logo" src="../assets/images/ville_de_palme.png" alt="Logo">
        </div>
        <div class="qr-modal-info">
          <p>Click outside or press ESC to close</p>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    
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
    if (!src || src.includes('placeholder')) return;
    
    const modal = document.getElementById('qrZoomModal');
    const img = document.getElementById('qrZoomImage');
    
    if (modal && img) {
      img.src = src;
      modal.classList.add('active');
      modal.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
    }
  };
  
  // Close QR zoom modal
  window.closeQRZoom = function() {
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
  
  // Make QR codes clickable
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('qr-clickable')) {
      openQRZoom(e.target.src);
    }
  });
})();
