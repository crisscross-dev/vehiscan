<?php
// Account Approvals Page
require_once __DIR__ . '/../../db.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Account Approvals</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Review and approve pending account registrations</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 animate-fade-in-up animate-delay-1">
        <div class="ta-stat-card glass">
            <div class="ta-stat-icon blue">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Pending Accounts</div>
                <div class="ta-stat-value" id="pendingAccountsCount">0</div>
            </div>
        </div>
        <div class="ta-stat-card glass">
            <div class="ta-stat-icon purple">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Profile Requests</div>
                <div class="ta-stat-value" id="pendingProfileCount">0</div>
                <button type="button" id="openProfileRequestsBtn" class="mt-2 text-xs text-blue-600 dark:text-blue-400 font-semibold hover:underline cursor-pointer">View Details</button>
            </div>
        </div>
        <div class="ta-stat-card glass">
            <div class="ta-stat-icon amber">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Total Actions</div>
                <div class="ta-stat-value" id="pendingTotalCount">0</div>
            </div>
        </div>
    </div>


    <!-- Bulk Actions Bar -->
    <div id="bulkActionsBar" class="hidden mb-4 p-4 glass border-blue-200 dark:border-blue-800/50 rounded-xl flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-3">
            <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <span class="text-sm font-medium text-blue-800 dark:text-blue-300">
                <span id="selectedCount" class="font-bold">0</span> accounts selected for bulk processing
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="bulkApproveBtn" class="ta-btn ta-btn-success shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Approve
            </button>
            <button type="button" id="bulkRejectBtn" class="ta-btn ta-btn-red shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                Reject
            </button>
        </div>
    </div>

    <!-- Pending Accounts Table -->
    <div class="ta-table-wrapper glass animate-fade-in-up animate-delay-2">
        <div class="px-6 py-4 flex items-center justify-between flex-wrap gap-3" style="border-bottom: 1px solid var(--ta-card-border);">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Registrations</h3>
            <div class="flex items-center gap-2">
                <div class="relative flex items-center">
                    <svg class="absolute left-4 h-4 w-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" id="approvalsSearchInput"
                        class="h-9 px-4 pl-12 border border-gray-300 dark:border-slate-600 rounded-lg min-w-[240px] text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all dark:bg-slate-700 dark:text-gray-200"
                        placeholder="Search accounts...">
                </div>
                <span id="approvalsSearchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table id="approvalsTable" class="ta-table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" id="selectAllApprovals" class="rounded border-gray-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="approvalsBody">
                    <!-- Loaded via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/js/admin/approvals-page.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin/approvals-page.js'); ?>"></script>

<style>
/* Ensure table is not overlapping */
#approvalsTable {
    position: relative;
    z-index: 1;
}

/* Better table row hover */
#approvalsTable tbody tr:hover {
    background-color: #f9fafb;
}

/* Ensure buttons don't wrap awkwardly */
#approvalsTable td button {
    white-space: nowrap;
}
</style>
