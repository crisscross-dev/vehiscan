// Vehicle Management JavaScript for Homeowner Portal

let activityChart = null;
let currentPeriod = 'week';
let vehicleManagementInitialized = false;

async function fetchVehicleJson(url, options = {}) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
    };

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers
    });

    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        throw new Error(`Unexpected server response (${response.status})`);
    }

    const data = await response.json();
    if (!response.ok) {
        const message = data.message || data.error || `Request failed (${response.status})`;
        const error = new Error(String(message));
        error.status = response.status;
        throw error;
    }

    return data;
}

function escapeHtml(value) {
    if (window.VehiScanUtils && typeof window.VehiScanUtils.escapeHtml === 'function') {
        return window.VehiScanUtils.escapeHtml(value);
    }

    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeVehicleImagePath(path) {
    const normalized = String(path ?? '').replace(/\\/g, '/');
    const sanitized = normalized.replace(/[^a-zA-Z0-9_./-]/g, '');
    return sanitized.replace(/^\/+/, '');
}

function resolveVehicleImageSrc(path) {
    const safePath = sanitizeVehicleImagePath(path);
    if (!safePath) {
        return '';
    }

    if (safePath.startsWith('uploads/')) {
        return `../${safePath}`;
    }

    return `../uploads/${safePath}`;
}

// Get CSRF token from meta tag
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// Load vehicles
async function loadVehicles() {
    try {
        const data = await fetchVehicleJson('api/get_vehicles.php');
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load vehicles');
        }
        
        renderVehicles(data.vehicles);
    } catch (error) {
        console.error('Load vehicles error:', error);
        document.getElementById('vehiclesGrid').innerHTML = `
            <div class="col-span-full text-center py-8 text-red-600">
                <p>Error loading vehicles: ${escapeHtml(error.message)}</p>
            </div>
        `;
    }
}

// Render vehicles grid
function renderVehicles(vehicles) {
    const grid = document.getElementById('vehiclesGrid');
    
    if (vehicles.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full text-center py-12">
                <svg class="h-16 w-16 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                </svg>
                <p class="text-gray-600 dark:text-gray-400 mb-4">No vehicles registered yet</p>
                <button type="button" data-action="showAddVehicleModal" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Add Your First Vehicle
                </button>
            </div>
        `;
        return;
    }
    
    grid.innerHTML = vehicles.map((vehicle, index) => {
        const safeVehicleType = escapeHtml(vehicle.vehicle_type);
        const safeColor = escapeHtml(vehicle.color);
        const safePlate = escapeHtml(vehicle.plate_number);
        const safeVehicleId = Number.parseInt(vehicle.id ?? vehicle.vehicle_id, 10) || 0;
        const isPrimary = Number.parseInt(String(vehicle.is_primary), 10) === 1 || vehicle.is_primary === true;
        const vehicleImageSrc = resolveVehicleImageSrc(vehicle.vehicle_img || vehicle.homeowner_car_img);
        const delay = (index % 4) * 100;

        return `
        <div class="glass overflow-hidden hover:shadow-xl transition-all duration-300 animate-fade-in-up" style="animation-delay: ${delay}ms">
            <div class="relative group">
                ${vehicleImageSrc ? `
                    <img src="${vehicleImageSrc}" alt="${safeVehicleType}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-500">
                ` : `
                    <div class="w-full h-48 bg-gray-100 dark:bg-slate-700/50 flex items-center justify-center">
                        <svg class="h-16 w-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                        </svg>
                    </div>
                `}
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                     <span class="text-white text-xs font-medium bg-white/20 backdrop-blur-md px-2 py-1 rounded">ID: ${safeVehicleId}</span>
                </div>
            </div>
            <div class="p-5">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-lg">${safeVehicleType}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Color: ${safeColor}</p>
                    </div>
                    ${isPrimary ? '<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] uppercase font-bold tracking-wider rounded-full border border-blue-200 dark:border-blue-800">Primary</span>' : ''}
                </div>
                
                <div class="flex items-center gap-2 mb-5">
                    <span class="text-xs font-mono font-bold bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-slate-600 shadow-sm">${safePlate}</span>
                </div>

                <div class="flex gap-2 pt-2 border-t border-gray-100 dark:border-slate-700/50">
                    ${!isPrimary ? `
                        <button type="button" data-action="setPrimaryVehicle" data-action-arg="${safeVehicleId}" class="flex-1 py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-2 text-sm font-semibold">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Primary
                        </button>
                    ` : ''}
                    <button type="button" data-action="deleteVehicle" data-action-arg="${safeVehicleId}" class="flex-1 py-2 px-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition-all flex items-center justify-center gap-2 text-sm font-semibold">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Remove
                    </button>
                </div>
            </div>
        </div>
    `;
    }).join('');
}

// Set primary vehicle
async function setPrimaryVehicle(vehicleId) {
    try {
        const parsedVehicleId = Number.parseInt(vehicleId, 10) || 0;
        if (parsedVehicleId <= 0) {
            throw new Error('Invalid vehicle selected');
        }

        const data = await fetchVehicleJson('api/set_primary_vehicle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ vehicle_id: parsedVehicleId, csrf_token: getCSRFToken() })
        });

        if (!data.success) {
            throw new Error(data.message || data.error || 'Failed to set primary vehicle');
        }

        Swal.fire('Updated!', 'Primary vehicle has been updated', 'success');
        await loadVehicles();
    } catch (error) {
        console.error('Set primary vehicle error:', error);
        Swal.fire('Error!', error.message, 'error');
    }
}

// Show add vehicle modal
function showAddVehicleModal() {
    const modal = document.getElementById('addVehicleModal');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    const firstFocusable = modal.querySelector('input, select, textarea, button');
    if (firstFocusable) {
        firstFocusable.focus();
    }
}

function closeAddVehicleModal() {
    const modal = document.getElementById('addVehicleModal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
}

// Delete vehicle
async function deleteVehicle(vehicleId) {
    const result = await Swal.fire({
        title: 'Remove Vehicle?',
        html: 'Type <strong>DELETE</strong> to deactivate this vehicle from your account.',
        input: 'text',
        inputPlaceholder: 'Type DELETE to confirm',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        preConfirm: (value) => {
            const normalized = String(value || '').trim().toUpperCase();
            if (normalized !== 'DELETE') {
                Swal.showValidationMessage('You must type DELETE to confirm');
            }
            return normalized;
        }
    });
    
    if (result.isConfirmed && String(result.value || '').trim().toUpperCase() === 'DELETE') {
        try {
            const data = await fetchVehicleJson('api/delete_vehicle.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ vehicle_id: vehicleId, csrf_token: getCSRFToken(), confirmation: 'DELETE' })
            });
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to delete vehicle');
            }
            
            Swal.fire('Removed!', 'Vehicle has been removed', 'success');
            loadVehicles();
        } catch (error) {
            console.error('Delete vehicle error:', error);
            Swal.fire('Error!', error.message, 'error');
        }
    }
}

// Load vehicle activity
async function loadVehicleActivity(period = 'week') {
    currentPeriod = period;
    
    // Update active period button
    document.querySelectorAll('.ta-pill-tab').forEach(btn => {
        btn.classList.remove('active', 'bg-blue-100', 'text-blue-700');
        if (btn.dataset.period === period) {
            btn.classList.add('active', 'bg-blue-100', 'text-blue-700');
        }
    });
    
    try {
        const data = await fetchVehicleJson(`api/get_vehicle_activity.php?period=${period}`);
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load activity');
        }
        
        // Update summary cards
        document.getElementById('totalEntries').textContent = data.summary.total_entries || 0;
        document.getElementById('totalExits').textContent = data.summary.total_exits || 0;
        document.getElementById('totalActivity').textContent = data.summary.total_logs || 0;
        
        // Render chart
        window.activityChartData = data.activity;
        renderActivityChart(data.activity);
    } catch (error) {
        console.error('Load activity error:', error);
    }
}

// Render activity chart
function renderActivityChart(activity) {
    const ctx = document.getElementById('activityChart');
    
    if (!ctx) return;
    
    if (activityChart) {
        activityChart.destroy();
    }
    
    const labels = activity.map(a => a.period);
    const entries = activity.map(a => parseInt(a.entries) || 0);
    const exits = activity.map(a => parseInt(a.exits) || 0);
    const maxValue = Math.max(1, ...entries, ...exits);
    
    activityChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Entries',
                    data: entries,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.68,
                    categoryPercentage: 0.62,
                    maxBarThickness: 44
                },
                {
                    label: 'Exits',
                    data: exits,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.75)',
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
                    barPercentage: 0.68,
                    categoryPercentage: 0.62,
                    maxBarThickness: 44
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: false,
                        boxWidth: 14,
                        boxHeight: 10,
                        padding: 14
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.92)',
                    titleColor: '#f8fafc',
                    bodyColor: '#e2e8f0',
                    padding: 10,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkipPadding: 16,
                        color: '#64748b'
                    }
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: maxValue + 1,
                    grace: '15%',
                    grid: {
                        color: 'rgba(148, 163, 184, 0.2)',
                        borderDash: [5, 5],
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        precision: 0,
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

function initializeVehicleManagement() {
    if (vehicleManagementInitialized) {
        return;
    }
    vehicleManagementInitialized = true;

    const addVehicleBtn = document.getElementById('addVehicleBtn');
    if (addVehicleBtn) {
        addVehicleBtn.addEventListener('click', showAddVehicleModal);
    }

    document.querySelectorAll('.ta-pill-tab').forEach(btn => {
        if (!btn.dataset.period) return;
        btn.addEventListener('click', function() {
            loadVehicleActivity(this.dataset.period);
        });
    });
}

document.addEventListener('DOMContentLoaded', initializeVehicleManagement);
