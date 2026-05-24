// guard/js/camera-handler.js - Floating Camera Window Handler

(function() {
  'use strict';

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFloatingCamera);
  } else {
    initFloatingCamera();
  }

  function initFloatingCamera() {
    const floatingToggle = document.getElementById('floatingCameraToggle');
    const floatingWindow = document.getElementById('floatingCameraWindow');
    const closeBtn = document.getElementById('closeCameraBtn');
    const minimizeBtn = document.getElementById('minimizeCameraBtn');
    const header = document.getElementById('cameraWindowHeader');

    if (!floatingToggle || !floatingWindow || !closeBtn || !minimizeBtn || !header) {
      console.warn('[CAMERA] Missing floating camera elements');
      return;
    }

    const controller = window.createCameraController({
      logPrefix: 'CAMERA',
      videoId: 'floatingCamera',
      canvasId: 'floatingCameraCanvas',
      overlayId: 'floatingCameraOverlay',
      toggleButtonId: 'floatingToggleCamera',
      snapshotButtonId: 'floatingSnapshotBtn',
      switchButtonId: 'floatingSwitchCameraBtn',
      timestampId: 'floatingTimestamp',
      statusId: 'floatCameraStatus',
      buttonTextId: 'floatingCameraBtnText',
      flashId: 'floatingSnapshotFlash',
      startText: 'Start',
      stopText: 'Stop',
      startingText: 'Starting...',
      switchingText: 'Switching...',
      startedToast: 'Camera started',
      stoppedToast: 'Camera stopped',
      snapshotToast: 'Snapshot saved',
      snapshotPrefix: 'snapshot',
      onlineStatusText: 'Live',
      offlineStatusText: 'Offline',
      onlineDotClass: 'bg-green-500',
      offlineDotClass: 'bg-gray-300',
    });

    if (!controller) {
      return;
    }

    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let initialLeft = 0;
    let initialTop = 0;

    floatingToggle.addEventListener('click', () => {
      floatingWindow.classList.toggle('hidden');
      if (!floatingWindow.classList.contains('hidden') && !controller.getState().stream) {
        setTimeout(() => {
          const toggleBtn = document.getElementById('floatingToggleCamera');
          if (toggleBtn) toggleBtn.click();
        }, 100);
      }
    });

    closeBtn.addEventListener('click', () => {
      floatingWindow.classList.add('hidden');
      controller.stopCamera();
    });

    minimizeBtn.addEventListener('click', () => {
      floatingWindow.classList.add('hidden');
    });

    header.addEventListener('mousedown', (e) => {
      if (e.target.closest('button')) return;
      isDragging = true;
      const rect = floatingWindow.getBoundingClientRect();
      initialLeft = rect.left;
      initialTop = rect.top;
      startX = e.clientX;
      startY = e.clientY;
      header.style.cursor = 'grabbing';
      floatingWindow.style.transition = 'none';
    });

    document.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      const deltaX = e.clientX - startX;
      const deltaY = e.clientY - startY;
      const left = initialLeft + deltaX;
      const top = initialTop + deltaY;
      const maxLeft = window.innerWidth - floatingWindow.offsetWidth;
      const maxTop = window.innerHeight - floatingWindow.offsetHeight;
      floatingWindow.style.left = Math.max(0, Math.min(left, maxLeft)) + 'px';
      floatingWindow.style.top = Math.max(0, Math.min(top, maxTop)) + 'px';
      floatingWindow.style.bottom = 'auto';
      floatingWindow.style.right = 'auto';
    });

    document.addEventListener('mouseup', () => {
      if (!isDragging) return;
      isDragging = false;
      header.style.cursor = 'move';
      floatingWindow.style.transition = '';
    });

    window.startFloatingCamera = controller.startCamera;
    window.stopFloatingCamera = controller.stopCamera;
  }
})();
