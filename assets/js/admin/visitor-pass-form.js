// Sync valid_until to end of selected valid_from date
(function(){
  'use strict';
  try {
    const fromInput = document.getElementById('valid_from');
    const untilInput = document.getElementById('valid_until');
    if (!fromInput || !untilInput) return;

    const syncValidUntil = () => {
      const value = fromInput.value;
      if (!value) return;
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return;
      date.setHours(23, 59, 59, 0);
      const pad = (n) => String(n).padStart(2, '0');
      untilInput.value = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    fromInput.addEventListener('change', syncValidUntil);
    fromInput.addEventListener('input', syncValidUntil);
    syncValidUntil();
  } catch (e) {
    console.error('[visitor-pass-form] init error', e);
  }
})();
