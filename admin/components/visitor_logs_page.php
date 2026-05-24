<?php
// Visitor Pass Scan Logs Component
require_once __DIR__ . '/../../db.php';
?>

<div class="p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Visitor Pass Scan Logs</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Track all QR code scans and visitor pass usage activity</p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="ta-stat-card">
            <div class="ta-stat-icon blue">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Total Scans</div>
                <div class="ta-stat-value" id="totalScansCount">0</div>
            </div>
        </div>
        <div class="ta-stat-card">
            <div class="ta-stat-icon green">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Used Passes</div>
                <div class="ta-stat-value" id="usedPassesCount">0</div>
            </div>
        </div>
        <div class="ta-stat-card">
            <div class="ta-stat-icon amber">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Today's Scans</div>
                <div class="ta-stat-value" id="todayScansCount">0</div>
            </div>
        </div>
        <div class="ta-stat-card">
            <div class="ta-stat-icon purple">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <div class="ta-stat-title">Unique Visitors</div>
                <div class="ta-stat-value" id="uniqueVisitorsCount">0</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                <input type="text" id="visitorLogsSearch" placeholder="Visitor name, homeowner, email..." 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select id="visitorLogsStatus" class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
                    <option value="">All Status</option>
                    <option value="used">Used</option>
                    <option value="scanned">Scanned</option>
                    <option value="verified">Verified</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                <input type="date" id="visitorLogsDateFrom" 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                <input type="date" id="visitorLogsDateTo" 
                       class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
            </div>
        </div>
        <div class="flex gap-2 mt-3">
            <button type="button" id="visitorLogsFilterBtn" class="ta-btn ta-btn-primary ta-btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Apply Filters
            </button>
            <button type="button" id="visitorLogsClearBtn" class="ta-btn ta-btn-secondary ta-btn-sm">Clear</button>
            <button type="button" id="visitorLogsRefreshBtn" class="ta-btn ta-btn-secondary ta-btn-sm ml-auto">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="visitorLogsTable" class="ta-table">
                <thead>
                    <tr>
                        <th>Visitor Name</th>
                        <th>Homeowner</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Scanned At</th>
                        <th>Valid Until</th>
                        <th>Scanner IP</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody id="visitorLogsBody">
                    <tr>
                        <td colspan="8" class="text-center py-8">
                            <div class="inline-block">
                                <svg class="animate-spin h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                            <p class="mt-2 text-gray-500">Loading visitor logs...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700 flex items-center justify-between">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Showing <span id="visitorLogsPaginationInfo">0 of 0</span> entries
            </div>
            <div class="flex gap-2">
                <button type="button" id="visitorLogsPrevBtn" class="ta-btn ta-btn-secondary ta-btn-sm">Previous</button>
                <span id="visitorLogsPageInfo" class="text-sm text-gray-600 dark:text-gray-400">Page 1</span>
                <button type="button" id="visitorLogsNextBtn" class="ta-btn ta-btn-secondary ta-btn-sm">Next</button>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/admin/visitor-logs.js?v=<?php echo filemtime(__DIR__ . '/../../assets/js/admin/visitor-logs.js'); ?>"></script>
