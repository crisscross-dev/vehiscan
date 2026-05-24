<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}
?>

<!-- Visitor Pass Management Section for Admin -->
<div class="mb-6">
  <div class="flex items-center gap-3 mb-2">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 text-white">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Visitor Pass Requests</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Review and manage pending visitor pass requests</p>
    </div>
  </div>
</div>

<div id="pendingPassesContainer">
    <div class="space-y-4 py-4 animate-pulse">
        <div class="ta-skeleton ta-skeleton-card" style="height:6rem"></div>
        <div class="ta-skeleton ta-skeleton-card" style="height:6rem"></div>
        <div class="ta-skeleton ta-skeleton-card" style="height:6rem"></div>
    </div>
</div>

