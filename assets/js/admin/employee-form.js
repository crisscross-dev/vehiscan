// Employee form helpers (migrated from inline in employee_form.php)
(function(){
  'use strict';
  try {
    setTimeout(() => {
      const resetCheckbox = document.getElementById('reset_password');
      const passwordField = document.getElementById('passwordField');
      const newPasswordInput = document.getElementById('new_password');
      
      if (resetCheckbox && passwordField && newPasswordInput) {
          resetCheckbox.addEventListener('change', function() {
              passwordField.classList.toggle('hidden', !this.checked);
              newPasswordInput.required = this.checked;
          });
      }
    }, 100);
  } catch (e) {
    console.error('[employee-form] init error', e);
  }
})();
