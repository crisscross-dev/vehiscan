/**
 * Registration Form Functionality
 * Handles camera capture, file uploads, form validation, and submission
 * Enhanced with real-time validation, drag-and-drop, keyboard shortcuts
 */

// Loading overlay management
const loadingOverlay = document.getElementById('loadingOverlay');
const progressBar = document.getElementById('progressBar');
const loadingMessage = document.getElementById('loadingMessage');

function showLoading(message = 'Processing...') {
  loadingOverlay.classList.remove('hidden');
  loadingMessage.textContent = message;
  progressBar.style.width = '0%';
}

function updateProgress(percent) {
  progressBar.style.width = percent + '%';
}

function hideLoading() {
  loadingOverlay.classList.add('hidden');
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
  document.addEventListener('keydown', (e) => {
    // Ctrl+Enter to submit
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      const nextBtn = document.getElementById('wizardNextBtn');
      const submitBtn = document.getElementById('submitBtn');

      if (nextBtn && !nextBtn.hidden) {
        nextBtn.click();
      } else if (submitBtn && !submitBtn.hidden) {
        submitBtn.click();
      }
    }
  });
}

// Three-step registration wizard
function initializeRegistrationWizard() {
  const form = document.getElementById('registrationForm');
  if (!form) return;

  const panels = Array.from(form.querySelectorAll('.wizard-panel'));
  const stepButtons = Array.from(document.querySelectorAll('#registrationWizardSteps .wizard-step'));
  const prevBtn = document.getElementById('wizardPrevBtn');
  const nextBtn = document.getElementById('wizardNextBtn');
  const submitBtn = document.getElementById('submitBtn');
  const progressBar = document.getElementById('wizardProgressBar');
  const keyboardHintAction = document.getElementById('keyboardHintAction');

  if (!panels.length || !prevBtn || !nextBtn || !submitBtn) return;

  let currentStep = 1;
  const totalSteps = panels.length;

  function validateStep(stepNumber) {
    const panel = panels.find((p) => Number(p.dataset.step || '0') === stepNumber);
    if (!panel) return true;

    const fields = Array.from(panel.querySelectorAll('input, select, textarea')).filter((el) => !el.disabled);
    
    // Special validation for step 2 (Vehicle Information) - check plate availability
    if (stepNumber === 2) {
      const plateInput = panel.querySelector('input[name="plate_number"]');
      if (plateInput && plateInput.value.length >= 3) {
        // Check if plate was marked as unavailable
        if (plateInput.dataset.plateAvailable === 'false') {
          plateInput.reportValidity();
          plateInput.focus();
          return false;
        }
      }
    }
    
    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    return true;
  }

  function validateCurrentStep() {
    return validateStep(currentStep);
  }

  function showStep(step) {
    currentStep = Math.min(Math.max(step, 1), totalSteps);

    panels.forEach((panel) => {
      const panelStep = Number(panel.dataset.step || '0');
      panel.hidden = panelStep !== currentStep;
    });

    stepButtons.forEach((btn) => {
      const btnStep = Number(btn.dataset.step || '0');
      btn.classList.toggle('active', btnStep === currentStep);
      btn.classList.toggle('completed', btnStep < currentStep);
      btn.setAttribute('aria-current', btnStep === currentStep ? 'step' : 'false');
    });

    if (progressBar) {
      progressBar.style.width = `${(currentStep / totalSteps) * 100}%`;
    }

    prevBtn.hidden = currentStep === 1;
    nextBtn.hidden = currentStep === totalSteps;
    submitBtn.hidden = currentStep !== totalSteps;

    nextBtn.textContent = currentStep === totalSteps - 1 ? 'Continue to Photos' : 'Next';
    if (keyboardHintAction) {
      keyboardHintAction.textContent = currentStep === totalSteps ? 'submit' : 'continue';
    }
  }

  prevBtn.addEventListener('click', () => showStep(currentStep - 1));
  nextBtn.addEventListener('click', () => {
    if (!validateCurrentStep()) return;
    showStep(currentStep + 1);
  });

  stepButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = Number(btn.dataset.step || '1');
      if (target <= currentStep) {
        showStep(target);
        return;
      }

      for (let s = currentStep; s < target; s += 1) {
        if (!validateStep(s)) return;
      }

      showStep(target);
    });
  });

  form.addEventListener('submit', (e) => {
    if (currentStep !== totalSteps) {
      e.preventDefault();
      if (validateCurrentStep()) showStep(currentStep + 1);
    }
  });

  form.__registrationWizard = {
    showStep,
    getCurrentStep: () => currentStep
  };

  showStep(1);
}

function initializeTailAdminFormClasses() {
  const form = document.getElementById('registrationForm');
  if (!form) return;

  form.querySelectorAll('.form-group').forEach((group) => {
    group.classList.add('ta-form-group');
  });

  form.querySelectorAll('input, textarea, select').forEach((field) => {
    if (field.type === 'file' || field.type === 'hidden') return;
    if (field.tagName === 'SELECT') {
      field.classList.add('ta-select');
    } else {
      field.classList.add('ta-input');
    }
  });

  document.querySelectorAll('.camera-btn, .gallery-btn').forEach((btn) => {
    btn.classList.add('ta-btn', 'ta-btn-outline-primary', 'ta-btn-sm');
  });
}

// Real-time validation
function initializeValidation() {
  const nameInput = document.getElementById('nameInput');
  const contactInput = document.getElementById('contactInput') || document.getElementById('contact');
  const plateInput = document.getElementById('plateInput');
  const addressInput = document.getElementById('addressInput');
  const passwordInput = document.getElementById('passwordInput');
  const confirmPasswordInput = document.getElementById('confirmPasswordInput');

  if (nameInput) {
    nameInput.addEventListener('input', function() {
      const hint = document.getElementById('nameHint');
      if (this.value.length >= 3) {
        this.classList.add('valid');
        this.classList.remove('invalid');
        if (hint) {
          hint.textContent = 'Name looks good';
          hint.style.color = '#10b981';
        }
      } else if (this.value.length > 0) {
        this.classList.add('invalid');
        this.classList.remove('valid');
        if (hint) {
          hint.textContent = 'Name too short (min 3 characters)';
          hint.style.color = '#ef4444';
        }
      } else {
        this.classList.remove('valid', 'invalid');
        if (hint) {
          hint.textContent = 'Enter your complete legal name';
          hint.style.color = '';
        }
      }
    });
  }

  if (contactInput) {
    contactInput.addEventListener('input', function() {
      const hint = document.getElementById('contactHint');
      // Auto-format phone number
      let value = this.value.replace(/[^0-9]/g, '');
      if (value.length > 11) value = value.slice(0, 11);
      
      if (value.length >= 4 && value.length <= 7) {
        this.value = value.slice(0, 4) + '-' + value.slice(4);
      } else if (value.length > 7) {
        this.value = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7);
      } else {
        this.value = value;
      }

      if (value.length === 11) {
        this.classList.add('valid');
        this.classList.remove('invalid');
        if (hint) {
          hint.textContent = 'Valid contact number';
          hint.style.color = '#10b981';
        }
      } else if (value.length > 0) {
        this.classList.add('invalid');
        this.classList.remove('valid');
        if (hint) {
          hint.textContent = `${11 - value.length} more digit${11 - value.length !== 1 ? 's' : ''} needed`;
          hint.style.color = '#ef4444';
        }
      } else {
        this.classList.remove('valid', 'invalid');
        if (hint) {
          hint.textContent = "We'll use this for important notifications";
          hint.style.color = '';
        }
      }
    });
  }

  if (plateInput) {
    let plateCheckTimeout;
    let lastCheckedPlate = '';
    
    plateInput.addEventListener('input', function() {
      const hint = document.getElementById('plateHint');
      const value = this.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '').slice(0, 7);
      this.value = value;
      
      // Clear previous timeout
      clearTimeout(plateCheckTimeout);
      
      if (value.length >= 3) {
        this.classList.add('valid');
        this.classList.remove('invalid');
        hint.textContent = `Plate number accepted (${value.length}/7)`;
        hint.style.color = '#10b981';
        
        // Check for duplicates if plate changed
        if (value !== lastCheckedPlate && value.length >= 3) {
          lastCheckedPlate = value;
          plateCheckTimeout = setTimeout(async () => {
            try {
              const response = await fetch(`../api/check_plate.php?plate=${encodeURIComponent(value)}`);
              const result = await response.json();
              
              if (result.success && !result.available) {
                this.classList.add('invalid');
                this.classList.remove('valid');
                hint.textContent = result.message || 'Plate number already registered';
                hint.style.color = '#ef4444';
                // Store validation state
                this.dataset.plateAvailable = 'false';
              } else if (result.success && result.available) {
                this.classList.add('valid');
                this.classList.remove('invalid');
                hint.textContent = 'Plate number available';
                hint.style.color = '#10b981';
                this.dataset.plateAvailable = 'true';
              }
            } catch (error) {
              console.error('Plate check error:', error);
              // Don't show error to user, just let validation proceed
            }
          }, 500);
        }
      } else if (value.length > 0) {
        this.classList.add('invalid');
        this.classList.remove('valid');
        hint.textContent = 'Too short (min 3 characters)';
        hint.style.color = '#ef4444';
        this.dataset.plateAvailable = '';
      } else {
        this.classList.remove('valid', 'invalid');
        hint.textContent = 'Used for gate access verification (auto-uppercase)';
        hint.style.color = '';
        this.dataset.plateAvailable = '';
      }
    });
  }

  if (addressInput) {
    addressInput.addEventListener('input', function() {
      const hint = document.getElementById('addressHint');
      const remaining = 200 - this.value.length;
      if (this.value.length >= 10) {
        this.classList.add('valid');
        this.classList.remove('invalid');
        if (hint) {
          hint.textContent = `${remaining} characters remaining`;
          hint.style.color = '#10b981';
        }
      } else if (this.value.length > 0) {
        this.classList.add('invalid');
        this.classList.remove('valid');
        if (hint) {
          hint.textContent = 'Address too short';
          hint.style.color = '#ef4444';
        }
      } else {
        this.classList.remove('valid', 'invalid');
        if (hint) {
          hint.textContent = 'Include complete address within subdivision';
          hint.style.color = '';
        }
      }
    });
  }

  if (passwordInput && confirmPasswordInput) {
    const validatePasswords = () => {
      const pass = passwordInput.value;
      const confirm = confirmPasswordInput.value;

      if (!confirm) {
        confirmPasswordInput.classList.remove('valid', 'invalid');
        confirmPasswordInput.setCustomValidity('');
        return;
      }

      if (pass === confirm) {
        confirmPasswordInput.classList.add('valid');
        confirmPasswordInput.classList.remove('invalid');
        confirmPasswordInput.setCustomValidity('');
      } else {
        confirmPasswordInput.classList.add('invalid');
        confirmPasswordInput.classList.remove('valid');
        confirmPasswordInput.setCustomValidity('Passwords do not match');
      }
    };

    passwordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);
  }
}

// Initialize plate number auto-uppercase
function initializePlateInput() {
  const plateInput = document.getElementById('plateInput');
    if (plateInput) {
    plateInput.addEventListener('input', function(e) {
      e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '').slice(0, 7);
    });
  }
}

function initializeVehicleColorOtherFields() {
  const vehicleTypeInput = document.getElementById('vehicleTypeInput');
  const vehicleTypeOtherInput = document.getElementById('vehicleTypeOtherInput');
  const colorInput = document.getElementById('colorInput');
  const colorOtherInput = document.getElementById('colorOtherInput');

  const syncOtherField = (selectEl, otherEl) => {
    if (!selectEl || !otherEl) return;
    const isOther = selectEl.value === 'Other';
    otherEl.style.display = isOther ? 'block' : 'none';
    otherEl.required = isOther;
    if (!isOther) {
      otherEl.value = '';
      otherEl.setCustomValidity('');
    }
  };

  if (vehicleTypeInput && vehicleTypeOtherInput) {
    vehicleTypeInput.addEventListener('change', () => syncOtherField(vehicleTypeInput, vehicleTypeOtherInput));
    syncOtherField(vehicleTypeInput, vehicleTypeOtherInput);
    // Make plate optional when E-bike is selected
    const plateInput = document.getElementById('plateInput');
    const plateHint = document.getElementById('plateHint');
    const updatePlateRequirement = () => {
      if (!plateInput) return;
      const isEbike = vehicleTypeInput.value === 'E-bike';
      plateInput.required = !isEbike;
      if (plateHint) {
        plateHint.textContent = isEbike ? 'Plate number is optional for E-bike' : 'Required for automated gate recognition (optional for E-bike)';
      }
    };
    vehicleTypeInput.addEventListener('change', updatePlateRequirement);
    updatePlateRequirement();
  }

  if (colorInput && colorOtherInput) {
    colorInput.addEventListener('change', () => syncOtherField(colorInput, colorOtherInput));
    syncOtherField(colorInput, colorOtherInput);
  }
}

// Initialize camera button functionality
function initializeCameraButtons() {
  document.querySelectorAll('.camera-btn').forEach(btn => {
    const inputName = btn.dataset.for;
    const mainFileInput = document.querySelector(`input[name="${inputName}"]`);
    
    if (!mainFileInput) {
      console.error('Main file input not found for:', inputName);
      return;
    }

    console.log('Initializing camera button for:', inputName);

    // Create a wrapper div for the button to contain the file input
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'position: relative; display: inline-flex;';
    
    // Create a dedicated file input positioned over the button
    const cameraInput = document.createElement('input');
    cameraInput.type = 'file';
    // Encourage mobile browsers to open the camera directly where supported
    cameraInput.accept = 'image/*;capture=camera';
    cameraInput.setAttribute('capture', 'environment');
    cameraInput.id = inputName + '_cameraInput';
    cameraInput.className = 'hidden-file-input';
    cameraInput.style.cssText = `
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      cursor: pointer;
      z-index: 1;
    `;
    
    // Wrap the button and insert the input inside the wrapper
    btn.parentNode.insertBefore(wrapper, btn);
    wrapper.appendChild(btn);
    wrapper.appendChild(cameraInput);
    
    // When camera input changes, transfer the file to main input
    cameraInput.addEventListener('change', function() {
      if (cameraInput.files && cameraInput.files.length > 0) {
        // Transfer the file to the main input
        try {
          const dt = new DataTransfer();
          dt.items.add(cameraInput.files[0]);
          mainFileInput.files = dt.files;
          
          // Trigger change event on main input to show preview
          mainFileInput.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err) {
          console.error('Error transferring photo:', err);
        }
      }
    });
    
  });
}

// Initialize gallery button functionality
function initializeGalleryButtons() {
  document.querySelectorAll('.gallery-btn').forEach(btn => {
    const inputName = btn.dataset.for;
    const mainFileInput = document.querySelector(`input[name="${inputName}"]`);
    
    if (!mainFileInput) {
      console.error('Main file input not found for:', inputName);
      return;
    }

    console.log('Initializing gallery button for:', inputName);

    // Create a wrapper div for the button to contain the file input
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'position: relative; display: inline-flex;';
    
    // Create a dedicated file input positioned over the button
    const galleryInput = document.createElement('input');
    galleryInput.type = 'file';
    galleryInput.accept = 'image/*';
    galleryInput.id = inputName + '_galleryInput';
    galleryInput.className = 'hidden-file-input';
    galleryInput.style.cssText = `
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      cursor: pointer;
      z-index: 1;
    `;
    
    // Wrap the button and insert the input inside the wrapper
    btn.parentNode.insertBefore(wrapper, btn);
    wrapper.appendChild(btn);
    wrapper.appendChild(galleryInput);
    
    // When gallery input changes, transfer the file to main input
    galleryInput.addEventListener('change', function() {
      if (galleryInput.files && galleryInput.files.length > 0) {
        // Transfer the file to the main input
        try {
          const dt = new DataTransfer();
          for (let i = 0; i < galleryInput.files.length; i++) {
            dt.items.add(galleryInput.files[i]);
          }
          mainFileInput.files = dt.files;
          
          // Trigger change event on main input to show preview
          mainFileInput.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err) {
          console.error('Error transferring file:', err);
        }
      }
    });
    
  });
}

// Initialize file input with drag-and-drop and preview
function initializeFileInputLabels() {
  const ownerInput = document.getElementById('ownerImgInput');
  const ownerLabel = document.getElementById('ownerImgLabel');
  const ownerBox = document.getElementById('ownerUploadBox');
  const ownerPreview = document.getElementById('ownerPreview');
  const ownerPreviewImg = document.getElementById('ownerPreviewImg');
  
  const carInput = document.getElementById('carImgInput');
  const carLabel = document.getElementById('carImgLabel');
  const carBox = document.getElementById('carUploadBox');
  const carPreview = document.getElementById('carPreview');
  const carPreviewImg = document.getElementById('carPreviewImg');

  function setupFileInput(input, label, box, preview, previewImg) {
    if (!input || !box) return;

    // File selection handler with enhanced validation
    input.addEventListener('change', async () => {
      if (input.files && input.files.length > 0) {
        const file = input.files[0];
        const maxSize = 4 * 1024 * 1024;
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type.toLowerCase())) {
          Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Please upload a JPG, PNG, or WEBP image file.',
            confirmButtonColor: '#ef4444'
          });
          input.value = '';
          return;
        }
        
        // Validate file size
        if (file.size > maxSize) {
          Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: `Image is ${(file.size / 1024 / 1024).toFixed(2)}MB. Maximum size is 4MB.`,
            html: '<small>Tip: Try compressing the image or taking a new photo.</small>',
            confirmButtonColor: '#ef4444'
          });
          input.value = '';
          return;
        }

        // Show loading state
        if (label) {
          label.textContent = 'Processing image...';
          label.style.color = '#3b82f6';
        }

        // Load and validate image
        const reader = new FileReader();
        reader.onload = async (e) => {
          const img = new Image();
          img.onload = async () => {
            // Show preview with smooth animation
            if (previewImg && preview) {
              previewImg.src = e.target.result;
              preview.style.display = 'block';
              box.classList.add('has-file');
              
              // Add fade-in animation
              preview.style.opacity = '0';
              setTimeout(() => {
                preview.style.transition = 'opacity 0.3s ease';
                preview.style.opacity = '1';
              }, 10);
            }

            // Update label with file info
            if (label) {
              const sizeKB = (file.size / 1024).toFixed(0);
              const dimensions = `${img.width}x${img.height}`;
              label.innerHTML = `<svg style="width:0.85em;height:0.85em;vertical-align:-0.1em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> ${file.name} <span style="color: #6b7280;">(${sizeKB}KB &bull; ${dimensions}px)</span>`;
            }
          };
          
          img.onerror = () => {
            Swal.fire({
              icon: 'error',
              title: 'Invalid Image',
              text: 'Unable to load the image. Please try a different file.',
              confirmButtonColor: '#ef4444'
            });
            input.value = '';
            resetFileInput(input, label, box, preview);
          };
          
          img.src = e.target.result;
        };
        
        reader.onerror = () => {
          Swal.fire({
            icon: 'error',
            title: 'Read Error',
            text: 'Failed to read the image file. Please try again.',
            confirmButtonColor: '#ef4444'
          });
          input.value = '';
          resetFileInput(input, label, box, preview);
        };
        
        reader.readAsDataURL(file);
      } else {
        resetFileInput(input, label, box, preview);
      }
    });

    // Enhanced drag and drop with validation
    box.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      box.classList.add('drag-over');
      
      // Show visual feedback
      const uploadText = box.querySelector('.upload-title');
      if (uploadText) {
        uploadText.dataset.originalText = uploadText.dataset.originalText || uploadText.textContent;
        uploadText.textContent = 'Drop image here';
      }
    });

    box.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      box.classList.remove('drag-over');
      
      // Restore original text
      const uploadText = box.querySelector('.upload-title');
      if (uploadText && uploadText.dataset.originalText) {
        uploadText.textContent = uploadText.dataset.originalText;
      }
    });

    box.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();
      box.classList.remove('drag-over');
      
      // Restore original text
      const uploadText = box.querySelector('.upload-title');
      if (uploadText && uploadText.dataset.originalText) {
        uploadText.textContent = uploadText.dataset.originalText;
      }
      
      const files = e.dataTransfer.files;
      if (files.length === 0) return;
      
      // Validate file type before processing
      const file = files[0];
      const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type.toLowerCase())) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid File',
          text: 'Please drop a JPG, PNG, or WEBP image file.',
          confirmButtonColor: '#ef4444'
        });
        return;
      }
      
      if (files.length > 1) {
        Swal.fire({
          icon: 'info',
          title: 'Multiple Files',
          text: 'Only the first image will be used.',
          confirmButtonColor: '#3b82f6',
          timer: 2000
        });
      }
      
      const dt = new DataTransfer();
      dt.items.add(files[0]);
      input.files = dt.files;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Click to select
    box.addEventListener('click', (e) => {
      // Don't trigger if clicking buttons or preview remove
      if (e.target.closest('.camera-btn') || 
          e.target.closest('.gallery-btn') || 
          e.target.closest('.preview-remove') ||
          e.target.closest('.upload-actions')) {
        return;
      }
      input.click();
    });
  }

  function resetFileInput(input, label, box, preview) {
    if (preview) {
      preview.style.display = 'none';
      preview.style.opacity = '0';
      preview.style.transition = '';
    }
    if (box) box.classList.remove('has-file');
    if (label) {
      label.style.color = '';
      if (input.name === 'owner_img') {
        label.innerHTML = 'Owner photo is required for verification';
      } else {
        label.innerHTML = 'Vehicle photo is required for verification';
      }
    }
  }

  // Setup both inputs
  setupFileInput(ownerInput, ownerLabel, ownerBox, ownerPreview, ownerPreviewImg);
  setupFileInput(carInput, carLabel, carBox, carPreview, carPreviewImg);

  // Remove button handlers with confirmation
  document.querySelectorAll('.preview-remove').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const targetName = btn.dataset.for;
      const input = document.querySelector(`input[name="${targetName}"]`);
      const label = document.getElementById(targetName === 'owner_img' ? 'ownerImgLabel' : 'carImgLabel');
      const box = document.getElementById(targetName === 'owner_img' ? 'ownerUploadBox' : 'carUploadBox');
      const preview = document.getElementById(targetName === 'owner_img' ? 'ownerPreview' : 'carPreview');
      
      if (input) {
        // Fade out animation
        if (preview) {
          preview.style.transition = 'opacity 0.2s ease';
          preview.style.opacity = '0';
        }
        
        setTimeout(() => {
          input.value = '';
          resetFileInput(input, label, box, preview);
        }, 200);
      }
    });
  });
}

// Form submission handler with validation and progress tracking
function initializeFormSubmission() {
  const form = document.getElementById('registrationForm');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Show loading overlay
    showLoading('Validating data...');
    updateProgress(10);
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoading = submitBtn.querySelector('.btn-loading');
    
    submitBtn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnLoading) btnLoading.style.display = 'flex';
    
    // Validate required fields using structured name fields
    const firstNameInput = e.target.querySelector('input[name="first_name"]');
    const lastNameInput = e.target.querySelector('input[name="last_name"]');
    const plateInput = e.target.querySelector('input[name="plate_number"]');
    const passwordInput = e.target.querySelector('input[name="password"]');
    const confirmPasswordInput = e.target.querySelector('input[name="confirm_password"]');
    
    if (!firstNameInput || !lastNameInput) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'Form Error',
        text: 'Name fields not found. Please refresh the page.',
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    const firstName = firstNameInput.value.trim();
    const lastName = lastNameInput.value.trim();
    const plate = plateInput ? plateInput.value.trim() : '';
    
    if (!firstName || firstName.length < 2) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'Invalid First Name',
        text: 'Please enter a valid first name (minimum 2 characters)',
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    if (!lastName || lastName.length < 2) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'Invalid Last Name',
        text: 'Please enter a valid last name (minimum 2 characters)',
        confirmButtonColor: '#ef4444'
      });
      return;
    }

    if (passwordInput && confirmPasswordInput && passwordInput.value !== confirmPasswordInput.value) {
      hideLoading();
      submitBtn.disabled = false;
      if (btnText) btnText.style.display = 'inline';
      if (btnLoading) btnLoading.style.display = 'none';
      confirmPasswordInput.focus();
      Swal.fire({
        icon: 'error',
        title: 'Password Mismatch',
        text: 'Confirm password must match your password.',
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    updateProgress(20);
    
    const formData = new FormData(e.target);
    
    // Validate file size before upload
    const ownerImg = e.target.querySelector('input[name="owner_img"]').files[0];
    const vehicleImg = e.target.querySelector('input[name="car_img"]').files[0];
    
    const maxSize = 4 * 1024 * 1024; // 4MB
    
    if (!ownerImg) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'Owner Photo Required',
        text: 'Please upload a photo of the homeowner',
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    if (!vehicleImg) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'Vehicle Photo Required',
        text: 'Please upload a photo of your vehicle',
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    if (ownerImg && ownerImg.size > maxSize) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'File Too Large',
        text: `Owner photo is ${(ownerImg.size / 1024 / 1024).toFixed(2)}MB. Maximum size is 4MB.`,
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    if (vehicleImg && vehicleImg.size > maxSize) {
      hideLoading();
      submitBtn.disabled = false;
      Swal.fire({
        icon: 'error',
        title: 'File Too Large',
        text: `Vehicle photo is ${(vehicleImg.size / 1024 / 1024).toFixed(2)}MB. Maximum size is 4MB.`,
        confirmButtonColor: '#ef4444'
      });
      return;
    }
    
    updateProgress(40);
    loadingMessage.textContent = 'Uploading images...';

    try {
      const res = await fetch(window.location.href, { method: 'POST', body: formData, credentials: 'same-origin' });
      
      updateProgress(80);
      loadingMessage.textContent = 'Saving to database...';
      
      updateProgress(90);
      
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      
      const json = await res.json();
      console.log('Registration response:', json);
      
      updateProgress(100);

      hideLoading();
      
      if (json.success) {
        const fullName = `${formData.get('first_name')} ${formData.get('last_name')}`;
        const email = json.email || formData.get('email');
        const plateNum = json.plate_number || formData.get('plate_number');
        
        await Swal.fire({
          icon: 'warning',
          title: 'Registration Submitted',
          html: `
            <div style="text-align:left;padding:10px;font-size:14px;">
              <p><b>Name:</b> ${fullName}</p>
              <p><b>Email:</b> ${email}</p>
              <p><b>Plate:</b> ${plateNum}</p>
              <hr style="margin:15px 0;">
              <p style="background:#fef3c7;padding:12px;border-radius:4px;border-left:4px solid #f59e0b;">
                <strong style="color:#92400e;">Pending admin approval</strong><br>
                <span style="color:#78350f;font-size:13px;">Your account will be reviewed. You'll receive an email once approved.</span>
              </p>
              <p style="text-align:center;margin-top:10px;">
                <small style="color:#6b7280;">Status: <strong style="color:#d97706;">Pending approval</strong></small>
              </p>
            </div>
          `,
          confirmButtonText: 'OK',
          confirmButtonColor: '#3b82f6'
        });
        e.target.reset();

        initializeVehicleColorOtherFields();

        if (e.target.__registrationWizard && typeof e.target.__registrationWizard.showStep === 'function') {
          e.target.__registrationWizard.showStep(1);
        }
        
        // Reset file input labels
        const ownerLabel = document.getElementById('ownerImgLabel');
        const carLabel = document.getElementById('carImgLabel');
        if (ownerLabel) {
          ownerLabel.textContent = 'Owner photo is required for verification';
        }
        if (carLabel) {
          carLabel.textContent = 'Vehicle photo is required for verification';
        }
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Registration Failed',
          text: json.message || 'An error occurred during registration',
          confirmButtonColor: '#ef4444',
          footer: '<p class="text-xs text-gray-500">If this problem persists, please contact the administrator.</p>'
        });
      }
    } catch (err) {
      hideLoading();
      console.error('Registration error:', err);
      Swal.fire({
        icon: 'error',
        title: 'Connection Error',
        html: `
          <p>Unable to submit registration. Please check your connection and try again.</p>
          <p class="text-sm text-gray-500 mt-2">Error details: ${err.message}</p>
        `,
        confirmButtonColor: '#ef4444'
      });
    } finally {
      // Restore button state
      submitBtn.disabled = false;
      if (btnText) btnText.style.display = 'inline';
      if (btnLoading) btnLoading.style.display = 'none';
    }
  });
}

// Auto-focus first input
function initializeAutoFocus() {
  const firstInput = document.getElementById('firstNameInput');
  if (firstInput) {
    setTimeout(() => firstInput.focus(), 100);
  }
}

// Initialize all functionality when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('Registration page initialized');
  initializeTailAdminFormClasses();
  initializeRegistrationWizard();
  initializeKeyboardShortcuts();
  initializeValidation();
  initializePlateInput();
  initializeVehicleColorOtherFields();
  initializeCameraButtons();
  initializeGalleryButtons();
  initializeFileInputLabels();
  initializeFormSubmission();
  initializeAutoFocus();
  
  // Add visual feedback on form interaction
  const form = document.getElementById('registrationForm');
  if (form) {
    form.addEventListener('input', () => {
      form.classList.add('form-touched');
    });
  }
});
