// Profile draft & request handling (migrated from inline in portal.php)
(function () {
  'use strict';
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const homeownerId = document.getElementById('profileRequestForm')?.dataset.homeownerId || '';
    const draftStorageKey = homeownerId ? `vehiscan.profileDraft.${homeownerId}` : 'vehiscan.profileDraft';

    const draftInputs = {
      name: document.getElementById('draftName'),
      contact: document.getElementById('draftContact'),
      address: document.getElementById('draftAddress'),
      plate: document.getElementById('draftPlate'),
      vehicleType: document.getElementById('draftVehicleType'),
      color: document.getElementById('draftColor')
    };
    const profileRequestText = document.getElementById('profileRequestText');
    const profileRequestDraft = document.getElementById('profileRequestDraft');
    const profileDraftState = document.getElementById('profileDraftState');
    const saveDraftBtn = document.getElementById('saveProfileDraftBtn');

    const defaultDraft = window.__HOMEOWNER_DEFAULT_DRAFT__ || {};

    const readDraft = () => {
      try {
        const saved = localStorage.getItem(draftStorageKey);
        if (!saved) return defaultDraft;
        return { ...defaultDraft, ...JSON.parse(saved) };
      } catch (error) {
        return defaultDraft;
      }
    };

    const writeDraft = () => {
      const payload = {
        name: draftInputs.name?.value.trim() || '',
        contact: draftInputs.contact?.value.trim() || '',
        address: draftInputs.address?.value.trim() || '',
        plate: draftInputs.plate?.value.trim().toUpperCase() || '',
        vehicleType: draftInputs.vehicleType?.value.trim() || '',
        color: draftInputs.color?.value.trim() || ''
      };
      localStorage.setItem(draftStorageKey, JSON.stringify(payload));
      if (profileDraftState) {
        const changed = Object.entries(payload).some(([key, value]) => value !== (defaultDraft[key] || ''));
        profileDraftState.textContent = changed ? 'Draft saved locally. Submit when ready for admin and super admin approval.' : 'No local changes yet.';
      }
      return payload;
    };

    const applyDraft = (draft) => {
      if (draftInputs.name) draftInputs.name.value = draft.name || '';
      if (draftInputs.contact) draftInputs.contact.value = draft.contact || '';
      if (draftInputs.address) draftInputs.address.value = draft.address || '';
      if (draftInputs.plate) draftInputs.plate.value = (draft.plate || '').toUpperCase();
      if (draftInputs.vehicleType) draftInputs.vehicleType.value = draft.vehicleType || '';
      if (draftInputs.color) draftInputs.color.value = draft.color || '';
    };

    applyDraft(readDraft());

    Object.values(draftInputs).forEach((input) => {
      input?.addEventListener('input', writeDraft);
      input?.addEventListener('change', writeDraft);
    });

    if (saveDraftBtn) {
      saveDraftBtn.addEventListener('click', () => {
        writeDraft();
        Swal.fire({ icon: 'success', title: 'Draft saved', text: 'Your local profile draft has been saved in this browser.', confirmButtonColor: '#3b82f6' });
      });
    }

    const form = document.getElementById('profileRequestForm');
    if (form) {
      form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const payload = writeDraft();
        const summaryParts = [];

        if ((payload.name || '') !== (defaultDraft.name || '')) summaryParts.push(`Name: ${defaultDraft.name || '—'} -> ${payload.name || '—'}`);
        if ((payload.contact || '') !== (defaultDraft.contact || '')) summaryParts.push(`Contact: ${defaultDraft.contact || '—'} -> ${payload.contact || '—'}`);
        if ((payload.address || '') !== (defaultDraft.address || '')) summaryParts.push(`Address updated`);
        if ((payload.plate || '') !== (defaultDraft.plate || '')) summaryParts.push(`Plate: ${defaultDraft.plate || '—'} -> ${payload.plate || '—'}`);
        if ((payload.vehicleType || '') !== (defaultDraft.vehicleType || '')) summaryParts.push(`Vehicle type updated`);
        if ((payload.color || '') !== (defaultDraft.color || '')) summaryParts.push(`Vehicle color updated`);

        const ownerImg = document.getElementById('draftOwnerImg')?.files[0];
        const carImg = document.getElementById('draftCarImg')?.files[0];

        if (ownerImg) summaryParts.push(`New owner photo uploaded`);
        if (carImg) summaryParts.push(`New vehicle photo uploaded`);

        if (summaryParts.length === 0) {
          Swal.fire({ icon: 'warning', title: 'No changes detected', text: 'Edit at least one field before submitting for approval.', confirmButtonColor: '#3b82f6' });
          return;
        }

        const text = `Profile change request: ${summaryParts.join('; ')}.`;
        if (profileRequestText) {
          profileRequestText.value = text;
        }
        if (profileRequestDraft) {
          profileRequestDraft.value = JSON.stringify(payload);
        }

        const btn = document.getElementById('submitProfileReqBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }

        try {
          const fd = new FormData();
          fd.append('request_text', text);
          fd.append('draft_payload', JSON.stringify(payload));
          fd.append('csrf_token', csrf);
          if (ownerImg) fd.append('owner_img', ownerImg);
          if (carImg) fd.append('car_img', carImg);

          const fetchJson = window.homeownerFetchJson || (async (url, options = {}) => {
            const response = await fetch(url, {
              credentials: 'same-origin',
              ...options,
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {})
              }
            });
            const contentType = String(response.headers.get('content-type') || '').toLowerCase();
            if (!contentType.includes('application/json')) {
              throw new Error(`Unexpected server response (${response.status})`);
            }
            const payload = await response.json();
            if (!response.ok) {
              throw new Error(payload.message || payload.error || `Request failed (${response.status})`);
            }
            return payload;
          });

          const data = await fetchJson('api/submit_profile_request.php', { method: 'POST', body: fd });

          if (data.success) {
            await Swal.fire({
              icon: 'success',
              title: 'Request Submitted',
              text: data.message,
              confirmButtonColor: '#3b82f6'
            });
            window.location.reload();
          } else {
            const message = data.message || 'Please wait before submitting another request.';
            Swal.fire({ icon: 'error', title: 'Could not submit', text: message, confirmButtonColor: '#ef4444' });
            if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Submit Request'; }
          }
        } catch (err) {
          console.error('[ProfileRequest] submit error:', err);
          Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.', confirmButtonColor: '#ef4444' });
          if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Submit Request'; }
        }
      });
    }
  } catch (e) {
    console.error('[portal-profile-request] init error', e);
  }
})();
