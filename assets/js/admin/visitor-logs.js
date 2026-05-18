/**
 * Visitor Pass Scan Logs Manager
 * Handles loading, filtering, and displaying visitor pass QR code scans
 */

(function () {
const visitorLogsRoot = window;
if (visitorLogsRoot.__vehiscanVisitorLogsInitialized) {
    return;
}
visitorLogsRoot.__vehiscanVisitorLogsInitialized = true;

const visitorLogsState = visitorLogsRoot.__vehiscanVisitorLogsState || {
    page: 1,
    perPage: 20,
    total: 0,
    totalPages: 1,
    search: '',
    status: '',
    dateFrom: '',
    dateTo: ''
};
visitorLogsRoot.__vehiscanVisitorLogsState = visitorLogsState;

function getAppBasePath() {
    const path = window.location.pathname || '';
    const markers = ['/admin/', '/guard/', '/homeowners/', '/auth/', '/api/', '/visitor/'];
    let cutIndex = -1;

    markers.forEach((marker) => {
        const idx = path.indexOf(marker);
        if (idx >= 0 && (cutIndex === -1 || idx < cutIndex)) {
            cutIndex = idx;
        }
    });

    return cutIndex >= 0 ? path.slice(0, cutIndex) : '';
}

function loadVisitorLogs(page = 1) {
    const baseUrl = window.location.origin + (window.vehiscanConfig?.baseUrl || getAppBasePath());
    const params = new URLSearchParams({
        page: page,
        per_page: visitorLogsState.perPage,
        search: visitorLogsState.search,
        status: visitorLogsState.status,
        date_from: visitorLogsState.dateFrom,
        date_to: visitorLogsState.dateTo
    });

    fetch(`${baseUrl}/admin/api/get_visitor_pass_logs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayVisitorLogs(data.data);
                updateVisitorLogsPagination(data.pagination);
                updateVisitorLogsStats(data.data);
            } else {
                showError(data.message || 'Failed to load visitor logs');
            }
        })
        .catch(error => {
            console.error('Error loading visitor logs:', error);
            showError('Failed to load visitor logs');
        });
}

function displayVisitorLogs(logs) {
    const tbody = document.getElementById('visitorLogsBody');
    
    if (!logs || logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-12">
                    <div class="ta-empty-state">
                        <svg class="ta-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <p class="ta-empty-title">No visitor pass scans found</p>
                        <p class="ta-empty-desc">No visitor passes have been scanned yet</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = logs.map(log => `
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">${log.visitor_name}</td>
            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">${log.homeowner_name}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">${log.homeowner_email}</td>
            <td class="px-6 py-4 text-sm">
                <span class="px-2 py-1 rounded-full text-xs font-semibold ${getStatusBadgeClass(log.scan_status)}">
                    ${log.scan_status}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">${log.scanned_at_formatted}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">
                ${log.valid_until ? new Date(log.valid_until).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A'}
            </td>
            <td class="px-6 py-4 text-sm font-mono text-gray-600 dark:text-gray-400">${log.scanner_ip}</td>
            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 max-w-xs truncate" title="${log.notes}">${log.notes || '—'}</td>
        </tr>
    `).join('');
}

function getStatusBadgeClass(status) {
    const statusMap = {
        'used': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'scanned': 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'verified': 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
        'expired': 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400'
    };
    return statusMap[status?.toLowerCase()] || 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
}

function updateVisitorLogsPagination(pagination) {
    visitorLogsState.page = pagination.current_page;
    visitorLogsState.total = pagination.total;
    visitorLogsState.totalPages = pagination.total_pages;

    const infoEl = document.getElementById('visitorLogsPaginationInfo');
    const pageEl = document.getElementById('visitorLogsPageInfo');
    const prevBtn = document.getElementById('visitorLogsPrevBtn');
    const nextBtn = document.getElementById('visitorLogsNextBtn');

    const start = (pagination.current_page - 1) * pagination.per_page + 1;
    const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
    infoEl.textContent = `${start}-${end} of ${pagination.total}`;
    pageEl.textContent = `Page ${pagination.current_page} of ${pagination.total_pages}`;

    prevBtn.disabled = pagination.current_page === 1;
    nextBtn.disabled = pagination.current_page === pagination.total_pages;
}

function updateVisitorLogsStats(logs) {
    // Calculate stats from logs
    const usedCount = logs.filter(l => l.scan_status.toLowerCase() === 'used').length;
    const todayLogsCount = logs.filter(l => {
        const logDate = new Date(l.scanned_at).toDateString();
        const today = new Date().toDateString();
        return logDate === today;
    }).length;
    const uniqueVisitors = new Set(logs.map(l => l.visitor_name)).size;

    document.getElementById('totalScansCount').textContent = visitorLogsState.total;
    document.getElementById('usedPassesCount').textContent = usedCount;
    document.getElementById('todayScansCount').textContent = todayLogsCount;
    document.getElementById('uniqueVisitorsCount').textContent = uniqueVisitors;
}

function showError(message) {
    const tbody = document.getElementById('visitorLogsBody');
    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-8 text-red-600 dark:text-red-400">
                ${message}
            </td>
        </tr>
    `;
}

// Event listeners
function initVisitorLogsPage() {
    const filterBtn = document.getElementById('visitorLogsFilterBtn');
    const clearBtn = document.getElementById('visitorLogsClearBtn');
    const refreshBtn = document.getElementById('visitorLogsRefreshBtn');
    const prevBtn = document.getElementById('visitorLogsPrevBtn');
    const nextBtn = document.getElementById('visitorLogsNextBtn');

    const searchInput = document.getElementById('visitorLogsSearch');
    const statusSelect = document.getElementById('visitorLogsStatus');
    const dateFromInput = document.getElementById('visitorLogsDateFrom');
    const dateToInput = document.getElementById('visitorLogsDateTo');

    filterBtn?.addEventListener('click', function() {
        visitorLogsState.search = searchInput.value.trim();
        visitorLogsState.status = statusSelect.value;
        visitorLogsState.dateFrom = dateFromInput.value;
        visitorLogsState.dateTo = dateToInput.value;
        visitorLogsState.page = 1;
        loadVisitorLogs(1);
    });

    clearBtn?.addEventListener('click', function() {
        searchInput.value = '';
        statusSelect.value = '';
        dateFromInput.value = '';
        dateToInput.value = '';
        visitorLogsState.search = '';
        visitorLogsState.status = '';
        visitorLogsState.dateFrom = '';
        visitorLogsState.dateTo = '';
        visitorLogsState.page = 1;
        loadVisitorLogs(1);
    });

    refreshBtn?.addEventListener('click', function() {
        loadVisitorLogs(visitorLogsState.page);
    });

    prevBtn?.addEventListener('click', function() {
        if (visitorLogsState.page > 1) {
            loadVisitorLogs(visitorLogsState.page - 1);
        }
    });

    nextBtn?.addEventListener('click', function() {
        if (visitorLogsState.page < visitorLogsState.totalPages) {
            loadVisitorLogs(visitorLogsState.page + 1);
        }
    });

    // Initial load
    loadVisitorLogs(1);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVisitorLogsPage, { once: true });
} else {
    initVisitorLogsPage();
}
})();
