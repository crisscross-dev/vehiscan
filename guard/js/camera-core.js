// Shared camera controller used by guard camera UIs.
(function() {
  'use strict';

  function log(message, error) {
    if (typeof window.__vsLog === 'function') {
      window.__vsLog(message, error);
    } else if (error !== undefined) {
      console.warn(message, error);
    } else {
      console.warn(message);
    }
  }

  function getElement(id) {
    return document.getElementById(id);
  }

  function setText(node, value) {
    if (node) {
      node.textContent = value;
    }
  }

  function createCameraController(config) {
    const state = {
      stream: null,
      availableCameras: [],
      currentCameraIndex: 0,
      busy: false,
    };

    const elements = {
      toggleButton: getElement(config.toggleButtonId),
      video: getElement(config.videoId),
      canvas: getElement(config.canvasId),
      overlay: getElement(config.overlayId),
      switchButton: config.switchButtonId ? getElement(config.switchButtonId) : null,
      snapshotButton: config.snapshotButtonId ? getElement(config.snapshotButtonId) : null,
      timestamp: config.timestampId ? getElement(config.timestampId) : null,
      status: config.statusId ? getElement(config.statusId) : null,
      buttonText: config.buttonTextId ? getElement(config.buttonTextId) : null,
      flash: config.flashId ? getElement(config.flashId) : null,
      cameraSelect: config.cameraSelectId ? getElement(config.cameraSelectId) : null,
      fullscreenButton: config.fullscreenButtonId ? getElement(config.fullscreenButtonId) : null,
      secondaryControls: config.secondaryControlsId ? getElement(config.secondaryControlsId) : null,
      container: config.containerId ? getElement(config.containerId) : null,
    };

    if (!elements.toggleButton || !elements.video) {
      log(`[${config.logPrefix}] Camera elements not found`);
      return null;
    }

    function setBusy(busy, loadingText) {
      elements.toggleButton.disabled = busy;
      elements.toggleButton.classList.toggle('is-loading', busy);
      if (elements.buttonText && busy && loadingText) {
        elements.buttonText.textContent = loadingText;
      }
      if (elements.snapshotButton) elements.snapshotButton.disabled = busy;
      if (elements.switchButton) elements.switchButton.disabled = busy;
      if (elements.fullscreenButton) elements.fullscreenButton.disabled = busy;
    }

    function updateStatus(isLive) {
      if (!elements.status) return;
      const statusDot = elements.status.querySelector('span:first-child');
      const statusText = elements.status.querySelector('span:last-child');
      if (statusDot) {
        statusDot.classList.toggle(config.offlineDotClass, !isLive);
        statusDot.classList.toggle(config.onlineDotClass, isLive);
      }
      setText(statusText, isLive ? config.onlineStatusText : config.offlineStatusText);
    }

    async function enumerateCameras() {
      try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
          log(`[${config.logPrefix}] Camera enumeration not supported`);
          return [];
        }

        const devices = await navigator.mediaDevices.enumerateDevices();
        state.availableCameras = devices.filter((device) => device.kind === 'videoinput');

        if (elements.cameraSelect) {
          elements.cameraSelect.classList.toggle('hidden', state.availableCameras.length <= 1);
          if (state.availableCameras.length > 1) {
            elements.cameraSelect.innerHTML = '<option value="">Select Camera...</option>';
            state.availableCameras.forEach((camera, index) => {
              const option = document.createElement('option');
              option.value = String(index);
              option.textContent = camera.label || `Camera ${index + 1}`;
              elements.cameraSelect.appendChild(option);
            });
          }
        }

        if (elements.switchButton) {
          elements.switchButton.classList.toggle('hidden', state.availableCameras.length <= 1);
        }

        return state.availableCameras;
      } catch (error) {
        log(`[${config.logPrefix}] Camera enumeration skipped`, error);
        return [];
      }
    }

    function updateTimestamp() {
      if (!state.stream || !elements.timestamp) return;
      elements.timestamp.textContent = new Date().toLocaleTimeString('en-US', { hour12: true });
      setTimeout(updateTimestamp, 1000);
    }

    async function startCamera(deviceId = null, skipBusyGuard = false) {
      if (!skipBusyGuard) {
        if (state.busy) return;
        state.busy = true;
        setBusy(true, config.startingText);
      }

      try {
        const constraints = {
          video: deviceId ? { deviceId: { exact: deviceId } } : { facingMode: 'environment' },
          audio: false,
        };

        state.stream = await navigator.mediaDevices.getUserMedia(constraints);
        elements.video.srcObject = state.stream;

        if (elements.overlay) elements.overlay.classList.add('hidden');
        if (elements.timestamp) elements.timestamp.classList.remove('hidden');
        if (elements.snapshotButton) elements.snapshotButton.classList.remove('hidden');
        if (elements.secondaryControls) elements.secondaryControls.classList.remove('hidden');
        if (elements.fullscreenButton) elements.fullscreenButton.classList.remove('hidden');

        updateStatus(true);
        setText(elements.buttonText, config.stopText);
        updateTimestamp();
        await enumerateCameras();

        if (window.toast) {
          window.toast.success(config.startedToast);
        }
      } catch (error) {
        log(`[${config.logPrefix}] Camera error`, error);
        if (window.toast) {
          window.toast.error(config.accessDeniedToast || 'Camera access denied');
        }
      } finally {
        if (!skipBusyGuard) {
          state.busy = false;
          setBusy(false);
        }
      }
    }

    function stopCamera() {
      if (!state.stream) return;

      state.stream.getTracks().forEach((track) => track.stop());
      state.stream = null;
      elements.video.srcObject = null;

      if (elements.overlay) elements.overlay.classList.remove('hidden');
      if (elements.timestamp) elements.timestamp.classList.add('hidden');
      if (elements.snapshotButton) elements.snapshotButton.classList.add('hidden');
      if (elements.secondaryControls) elements.secondaryControls.classList.add('hidden');
      if (elements.fullscreenButton) elements.fullscreenButton.classList.add('hidden');

      updateStatus(false);
      setText(elements.buttonText, config.startText);

      if (window.toast) {
        window.toast.info(config.stoppedToast);
      }
    }

    function takeSnapshot() {
      if (!state.stream || !elements.canvas) return;

      elements.canvas.width = elements.video.videoWidth;
      elements.canvas.height = elements.video.videoHeight;
      const ctx = elements.canvas.getContext('2d');
      ctx.drawImage(elements.video, 0, 0);

      if (elements.flash) {
        elements.flash.classList.remove('hidden');
        setTimeout(() => elements.flash.classList.add('hidden'), 150);
      }

      elements.canvas.toBlob((blob) => {
        if (!blob) return;
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = `${config.snapshotPrefix}_${Date.now()}.png`;
        anchor.click();
        URL.revokeObjectURL(url);
      });

      if (window.toast) {
        window.toast.success(config.snapshotToast);
      }
    }

    async function switchCamera() {
      if (state.busy || state.availableCameras.length <= 1) return;
      state.busy = true;
      setBusy(true, config.switchingText);

      try {
        state.currentCameraIndex = (state.currentCameraIndex + 1) % state.availableCameras.length;
        const deviceId = state.availableCameras[state.currentCameraIndex].deviceId;
        stopCamera();
        await startCamera(deviceId, true);
      } finally {
        state.busy = false;
        setBusy(false);
      }
    }

    function toggleFullscreen() {
      const fsElement = elements.container || elements.video;
      if (!fsElement.requestFullscreen) return;
      
      if (!document.fullscreenElement) {
        fsElement.requestFullscreen().catch((error) => log(`[${config.logPrefix}] Fullscreen error`, error));
      } else {
        document.exitFullscreen();
      }
    }

    function bindEvents() {
      elements.toggleButton.addEventListener('click', async () => {
        if (state.busy) return;
        if (state.stream) {
          stopCamera();
        } else {
          await enumerateCameras();
          await startCamera();
        }
      });

      if (elements.snapshotButton) {
        elements.snapshotButton.addEventListener('click', takeSnapshot);
      }
      if (elements.switchButton) {
        elements.switchButton.addEventListener('click', switchCamera);
      }
      if (elements.fullscreenButton) {
        elements.fullscreenButton.addEventListener('click', toggleFullscreen);
      }
      if (elements.cameraSelect) {
        elements.cameraSelect.addEventListener('change', async () => {
          const index = Number(elements.cameraSelect.value);
          if (!Number.isInteger(index) || !state.availableCameras[index]) return;
          state.currentCameraIndex = index;
          await startCamera(state.availableCameras[index].deviceId);
        });
      }
    }

    bindEvents();
    enumerateCameras();

    return {
      startCamera,
      stopCamera,
      enumerateCameras,
      switchCamera,
      takeSnapshot,
      toggleFullscreen,
      getState: () => state,
    };
  }

  window.createCameraController = createCameraController;
})();
