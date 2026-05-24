// guard/js/main-camera-handler.js - Main Camera Page Handler

(function() {
  'use strict';

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMainCamera);
  } else {
    initMainCamera();
  }

  function initMainCamera() {
    const controller = window.createCameraController({
      logPrefix: 'MAIN-CAMERA',
      videoId: 'liveCamera',
      containerId: 'cameraContainer',
      canvasId: 'cameraCanvas',
      overlayId: 'cameraOverlay',
      toggleButtonId: 'toggleCamera',
      snapshotButtonId: 'snapshotBtn',
      switchButtonId: 'switchCameraBtn',
      cameraSelectId: 'cameraSelect',
      fullscreenButtonId: 'fullscreenCamera',
      statusId: 'cameraStatus',
      secondaryControlsId: 'secondaryControls',
      timestampId: 'cameraTimestamp',
      flashId: 'snapshotFlash',
      buttonTextId: 'cameraBtnText',
      startText: 'Start Camera',
      stopText: 'Stop Camera',
      startingText: 'Starting...',
      switchingText: 'Switching...',
      startedToast: 'Camera started',
      stoppedToast: 'Camera stopped',
      snapshotToast: 'Snapshot saved',
      snapshotPrefix: 'camera_snapshot',
      onlineStatusText: 'Live',
      offlineStatusText: 'Offline',
      onlineDotClass: 'bg-green-500',
      offlineDotClass: 'bg-gray-400',
      accessDeniedToast: 'Camera access denied. Please allow camera access in your browser.',
    });

    if (!controller) {
      return;
    }

    window.startCamera = controller.startCamera;
    window.stopCamera = controller.stopCamera;
    window.takeCameraSnapshot = controller.takeSnapshot;

    __vsLog('[MAIN-CAMERA] Camera handler initialized (cameras will be enumerated when started)');
  }
})();
