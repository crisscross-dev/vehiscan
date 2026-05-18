// Admin create form helpers (migrated from inline in admin_create.php)
(function(){
  'use strict';
  try {
    const roleSelect = document.querySelector('select[name="role"]');
    const superAdminFields = document.getElementById('superAdminFields');
    if (!roleSelect || !superAdminFields) return;

    function syncSuperAdminFields() {
      const isSuperAdmin = roleSelect.value === 'super_admin';
      superAdminFields.style.display = isSuperAdmin ? 'block' : 'none';
      const fullNameInput = superAdminFields.querySelector('input[name="full_name"]');
      const emailInput = superAdminFields.querySelector('input[name="email"]');
      if (fullNameInput) fullNameInput.required = isSuperAdmin;
      if (emailInput) emailInput.required = isSuperAdmin;
    }

    roleSelect.addEventListener('change', syncSuperAdminFields);
    syncSuperAdminFields();
  } catch (e) {
    console.error('[admin-create] init error', e);
  }
})();
