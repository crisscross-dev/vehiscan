// QR registration small helpers (migrated from inline in qr_registration.php)
(function(){
  'use strict';
  try {
    document.querySelector('.copy-btn')?.addEventListener('click', (event) => {
      const button = event.currentTarget;
      const value = button.dataset.copy;

      if (!navigator.clipboard) {
        const temp = document.createElement('textarea');
        temp.value = value;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
      } else {
        navigator.clipboard.writeText(value).catch(()=>{});
      }

      Swal.fire({ icon: 'success', title: 'Copied', text: 'Registration link copied to clipboard.', timer: 1400, showConfirmButton: false });
    });
  } catch (e) {
    console.error('[qr-registration] init error', e);
  }
})();
