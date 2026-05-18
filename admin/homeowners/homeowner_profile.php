<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<p class="text-sm text-red-600">Invalid homeowner ID.</p>';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM homeowners WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$homeowner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$homeowner) {
    echo '<p class="text-sm text-red-600">Homeowner not found.</p>';
    exit;
}

$fullName = trim((string)($homeowner['name'] ?? ''));
$nameParts = preg_split('/\s+/', $fullName, 2);
$firstName = htmlspecialchars($homeowner['first_name'] ?? ($nameParts[0] ?? ''));
$lastName = htmlspecialchars($homeowner['last_name'] ?? ($nameParts[1] ?? ''));
$displayName = trim(implode(' ', array_filter([
  (string)($homeowner['first_name'] ?? ''),
  (string)($homeowner['middle_name'] ?? ''),
  (string)($homeowner['last_name'] ?? ''),
  (string)($homeowner['suffix'] ?? '')
])));
if ($displayName === '') {
  $displayName = $fullName !== '' ? $fullName : 'Unknown User';
}

$normalizeUploadUrl = static function (?string $rawPath): string {
  $rawPath = trim((string)$rawPath);
  if ($rawPath === '') {
    return '';
  }

  // Normalize DB path variants: uploads/foo.jpg, /uploads/foo.jpg, homeowners/foo.jpg, foo.jpg
  $clean = ltrim($rawPath, '/');
  if (stripos($clean, 'uploads/') === 0) {
    $clean = substr($clean, strlen('uploads/'));
  }

  // Modal content is injected into /admin/admin_panel.php context, so uploads are at ../uploads/
  return '../uploads/' . ltrim($clean, '/');
};

$ownerImgUrl = $normalizeUploadUrl($homeowner['owner_img'] ?? '');
$carImgUrl = $normalizeUploadUrl($homeowner['car_img'] ?? '');

$ownedVehicles = [];
try {
  $vehicleColumns = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);

  if (!empty($vehicleColumns)) {
    $idExpr = in_array('id', $vehicleColumns, true)
      ? 'v.id'
      : (in_array('vehicle_id', $vehicleColumns, true) ? 'v.vehicle_id' : 'NULL');

    $plateExpr = in_array('plate_number', $vehicleColumns, true) ? 'v.plate_number' : "''";
    $typeExpr = in_array('vehicle_type', $vehicleColumns, true) ? 'v.vehicle_type' : "''";
    $colorExpr = in_array('color', $vehicleColumns, true) ? 'v.color' : "''";
    $primaryExpr = in_array('is_primary', $vehicleColumns, true) ? 'v.is_primary' : '0';
    $imageExpr = in_array('vehicle_img', $vehicleColumns, true) ? 'v.vehicle_img' : 'NULL';

    $activeFilter = '';
    if (in_array('is_active', $vehicleColumns, true)) {
      $activeFilter = ' AND v.is_active = 1';
    } elseif (in_array('status', $vehicleColumns, true)) {
      $activeFilter = " AND v.status = 'active'";
    }

    $orderExpr = in_array('registered_at', $vehicleColumns, true)
      ? 'v.registered_at DESC'
      : (in_array('created_at', $vehicleColumns, true) ? 'v.created_at DESC' : 'id DESC');

    $vehiclesStmt = $pdo->prepare("\n            SELECT\n                {$idExpr} AS id,\n                {$plateExpr} AS plate_number,\n                {$typeExpr} AS vehicle_type,\n                {$colorExpr} AS color,\n                {$primaryExpr} AS is_primary,\n                {$imageExpr} AS vehicle_img\n            FROM vehicles v\n            WHERE v.homeowner_id = ?{$activeFilter}\n            ORDER BY {$primaryExpr} DESC, {$orderExpr}\n        ");
    $vehiclesStmt->execute([$id]);
    $ownedVehicles = $vehiclesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Exception $e) {
  $ownedVehicles = [];
}

if (empty($ownedVehicles) && !empty($homeowner['plate_number'])) {
  $ownedVehicles[] = [
    'id' => null,
    'plate_number' => (string)($homeowner['plate_number'] ?? ''),
    'vehicle_type' => (string)($homeowner['vehicle_type'] ?? ''),
    'color' => (string)($homeowner['color'] ?? ''),
    'is_primary' => 1,
    'vehicle_img' => (string)($homeowner['car_img'] ?? '')
  ];
}

foreach ($ownedVehicles as &$vehicleRow) {
  $vehicleRow['vehicle_img_url'] = $normalizeUploadUrl((string)($vehicleRow['vehicle_img'] ?? ''));
}
unset($vehicleRow);

if ($carImgUrl === '') {
  foreach ($ownedVehicles as $vehicleRow) {
    if (!empty($vehicleRow['vehicle_img_url'])) {
      $carImgUrl = (string)$vehicleRow['vehicle_img_url'];
      break;
    }
  }
}

$status = htmlspecialchars($homeowner['account_status'] ?? 'pending');
$statusColor = ['approved' => 'green', 'pending' => 'yellow', 'rejected' => 'red'][$status] ?? 'gray';
?>
<style>
  .homeowner-profile-modal {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
    max-height: calc(92vh - 2rem);
  }
  .homeowner-profile-modal .profile-scroll {
    overflow-y: auto;
    padding-right: 0.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
  }
  .homeowner-profile-modal .profile-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 1rem;
    padding: 1rem;
  }
  .dark .homeowner-profile-modal .profile-card {
    background: rgba(30, 41, 59, 0.6);
    border-color: #334155;
  }
  .homeowner-profile-modal .preview-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }
  .homeowner-profile-modal .preview-avatar {
    width: 140px;
    height: 140px;
    border-radius: 1rem;
    overflow: hidden;
    flex: 0 0 auto;
    background: linear-gradient(135deg, #dbeafe, #cbd5e1);
    border: 1px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .dark .homeowner-profile-modal .preview-avatar {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.95), rgba(51, 65, 85, 0.95));
    border-color: #334155;
  }
  .homeowner-profile-modal .preview-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .homeowner-profile-modal .preview-avatar svg {
    width: 2.4rem;
    height: 2.4rem;
    color: #94a3b8;
  }
  .homeowner-profile-modal .preview-meta {
    min-width: 0;
    flex: 1;
  }
  .homeowner-profile-modal .preview-name {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
  }
  .dark .homeowner-profile-modal .preview-name {
    color: #f8fafc;
  }
  .homeowner-profile-modal .preview-name small {
    font-size: 0.78em;
    font-weight: 700;
    color: #64748b;
  }
  .dark .homeowner-profile-modal .preview-name small {
    color: #94a3b8;
  }
  .homeowner-profile-modal .preview-line {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-top: 0.2rem;
  }
  .dark .homeowner-profile-modal .preview-line {
    color: #94a3b8;
  }
  .homeowner-profile-modal .preview-line svg {
    width: 0.95rem;
    height: 0.95rem;
    flex: 0 0 auto;
    margin-top: 0.16rem;
  }
  .homeowner-profile-modal .preview-status {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: rgba(226, 232, 240, 0.85);
    color: #334155;
  }
  .dark .homeowner-profile-modal .preview-status {
    background: rgba(51, 65, 85, 0.9);
    color: #e2e8f0;
  }
  .homeowner-profile-modal .section-heading {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.95rem;
    font-weight: 800;
    color: #334155;
    margin-bottom: 0.85rem;
  }
  .dark .homeowner-profile-modal .section-heading {
    color: #e2e8f0;
  }
  .homeowner-profile-modal .section-heading svg {
    width: 1rem;
    height: 1rem;
    color: #4f46e5;
  }
  .homeowner-profile-modal .vehicle-details-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.9rem;
    margin-bottom: 0.95rem;
  }
  .homeowner-profile-modal .vehicle-detail {
    min-width: 0;
  }
  .homeowner-profile-modal .vehicle-detail-label {
    font-size: 0.7rem;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
  }
  .homeowner-profile-modal .vehicle-detail-value {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.35;
    word-break: break-word;
  }
  .dark .homeowner-profile-modal .vehicle-detail-value {
    color: #f8fafc;
  }
  .homeowner-profile-modal .vehicle-photo-card {
    border-radius: 0.95rem;
    overflow: hidden;
    border: 1px solid #dbe4ef;
    background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
    min-height: 260px;
    display: block;
    position: relative;
  }
  .dark .homeowner-profile-modal .vehicle-photo-card {
    border-color: #334155;
    background: linear-gradient(180deg, rgba(51, 65, 85, 0.75) 0%, rgba(15, 23, 42, 0.9) 100%);
  }
  .homeowner-profile-modal .vehicle-photo-card img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .homeowner-profile-modal .vehicle-photo-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    color: #94a3b8;
    text-align: center;
    min-height: 260px;
    position: relative;
    z-index: 1;
  }
  .homeowner-profile-modal .vehicle-photo-placeholder svg {
    width: 1.7rem;
    height: 1.7rem;
  }
  .homeowner-profile-modal .registered-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }
  .homeowner-profile-modal .registered-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    padding: 0.8rem 0.9rem;
    border-radius: 0.8rem;
    border: 1px solid #dbe4ef;
    background: #ffffff;
  }
  .dark .homeowner-profile-modal .registered-item {
    background: rgba(15, 23, 42, 0.75);
    border-color: #334155;
  }
  .homeowner-profile-modal .registered-left {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    min-width: 0;
  }
  .homeowner-profile-modal .registered-dot {
    width: 0.82rem;
    height: 0.82rem;
    border-radius: 999px;
    flex: 0 0 auto;
    background: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
  }
  .homeowner-profile-modal .registered-dot.primary {
    background: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
  }
  .homeowner-profile-modal .registered-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    font-size: 0.96rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
    flex-wrap: wrap;
  }
  .dark .homeowner-profile-modal .registered-title {
    color: #f8fafc;
  }
  .homeowner-profile-modal .registered-subtitle {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.18rem;
  }
  .dark .homeowner-profile-modal .registered-subtitle {
    color: #94a3b8;
  }
  .homeowner-profile-modal .registered-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.28rem 0.62rem;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    background: #eef2ff;
    color: #6366f1;
  }
  .homeowner-profile-modal .registered-badge.muted {
    background: #e2e8f0;
    color: #475569;
  }
  .homeowner-profile-modal .profile-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    border-top: 1px solid #e2e8f0;
    padding-top: 1rem;
  }
  .dark .homeowner-profile-modal .profile-actions {
    border-top-color: #334155;
  }
  .homeowner-profile-modal .profile-actions .ta-btn {
    min-width: 180px;
  }
  .homeowner-profile-modal .photo-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.9rem;
    align-items: start;
  }
  .homeowner-profile-modal .photo-block {
    min-width: 0;
  }
  .homeowner-profile-modal .photo-frame {
    width: 100%;
    aspect-ratio: 1 / 1;
    max-height: 420px;
    object-fit: cover;
    border-radius: 0.7rem;
  }
  .homeowner-profile-modal .photo-frame.is-clickable {
    cursor: zoom-in;
  }
  .homeowner-profile-modal .photo-caption {
    margin-top: 0.45rem;
    font-size: 0.72rem;
    color: #2563eb;
    font-weight: 600;
  }
  .dark .homeowner-profile-modal .photo-caption {
    color: #60a5fa;
  }
  @media (min-width: 900px) {
    .homeowner-profile-modal .vehicle-details-grid {
      grid-template-columns: 1.15fr 0.9fr 0.95fr;
    }
    .homeowner-profile-modal .photo-grid {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 768px) {
    .homeowner-profile-modal .preview-header {
      flex-direction: column;
    }
    .homeowner-profile-modal .preview-avatar {
      width: 84px;
      height: 84px;
    }
    .homeowner-profile-modal .vehicle-details-grid {
      grid-template-columns: 1fr;
    }
    .homeowner-profile-modal .profile-actions {
      justify-content: stretch;
    }
    .homeowner-profile-modal .profile-actions .ta-btn {
      width: 100%;
      min-width: 0;
    }
  }
</style>

<div class="homeowner-profile-modal">
  <div class="border-b border-gray-200 dark:border-slate-700 pb-4 ">
    <div class="flex items-start justify-between gap-3">
    </div>
  </div>

  <div class="profile-scroll">
    <div class="profile-card">
      <div class="preview-header">
        <div class="preview-avatar">
          <?php if ($ownerImgUrl !== ''): ?>
            <img src="<?php echo htmlspecialchars($ownerImgUrl); ?>" alt="Owner image" class="js-profile-preview" data-preview-src="<?php echo htmlspecialchars($ownerImgUrl); ?>" data-preview-title="Owner verification photo">
          <?php else: ?>
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M5.121 17.804A4 4 0 019 15h6a4 4 0 013.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
          <?php endif; ?>
        </div>
        <div class="preview-meta">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="preview-name"><?php echo htmlspecialchars($displayName); ?> <?php if (!empty($homeowner['suffix'])): ?><small><?php echo htmlspecialchars((string)$homeowner['suffix']); ?></small><?php endif; ?></div>
              <div class="preview-line">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                <span><?php echo htmlspecialchars((string)($homeowner['address'] ?? '')); ?></span>
              </div>
              <div class="preview-line">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.97.757l1.157 4.629a1 1 0 01-.514 1.16l-2.2 1.1a11.038 11.038 0 005.516 5.516l1.1-2.2a1 1 0 011.16-.514l4.629 1.157a1 1 0 01.757.97V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                <span><?php echo htmlspecialchars((string)($homeowner['contact_number'] ?? '')); ?></span>
              </div>
              <div class="preview-line">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Registered: <?php echo htmlspecialchars(date('F j, Y', strtotime($homeowner['created_at']))); ?></span>
              </div>
            </div>
            <span class="px-2 py-1 rounded-md text-xs font-semibold ta-badge ta-badge-<?php echo $statusColor; ?>"><?php echo ucfirst($status); ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-card">
      <div class="section-heading">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h1l1 2h13l1-2h1M5 6h14l1 4H4l1-4zm0 8h14l-1 4H6l-1-4z"></path></svg>
        <span>Vehicle Details</span>
      </div>
      <div class="vehicle-details-grid">
        <div class="vehicle-detail">
          <div class="vehicle-detail-label">Plate Number</div>
          <div class="vehicle-detail-value"><?php echo htmlspecialchars((string)($homeowner['plate_number'] ?? '')); ?></div>
        </div>
        <div class="vehicle-detail">
          <div class="vehicle-detail-label">Color</div>
          <div class="vehicle-detail-value"><?php echo htmlspecialchars((string)($homeowner['color'] ?? '')); ?></div>
        </div>
        <div class="vehicle-detail">
          <div class="vehicle-detail-label">Vehicle Type</div>
          <div class="vehicle-detail-value"><?php echo htmlspecialchars((string)($homeowner['vehicle_type'] ?? '')); ?></div>
        </div>
      </div>
      <div class="vehicle-photo-card">
        <?php if ($carImgUrl !== ''): ?>
          <img src="<?php echo htmlspecialchars($carImgUrl); ?>"
               alt="Vehicle image"
               class="js-profile-preview"
               data-preview-src="<?php echo htmlspecialchars($carImgUrl); ?>"
               data-preview-title="Vehicle verification photo"
               onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
          <div class="vehicle-photo-placeholder hidden">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13l2-2m0 0l4-4 4 4M5 11v8a1 1 0 001 1h3m10-9l-2-2m0 0l-4-4-4 4m10 2v8a1 1 0 01-1 1h-3"></path></svg>
            <span class="text-sm font-semibold">Click to preview</span>
          </div>
        <?php else: ?>
          <div class="vehicle-photo-placeholder">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6m-3-5v5m-4 0h8M4 7h16v10H4z"></path></svg>
            <span class="text-sm font-semibold">No vehicle photo</span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="profile-card">
      <div class="section-heading">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>Registered Vehicles</span>
        <span class="ta-badge neutral ml-auto"><?php echo count($ownedVehicles); ?> Registered</span>
      </div>

      <?php if (empty($ownedVehicles)): ?>
        <div class="vehicle-photo-card">
          <div class="vehicle-photo-placeholder">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6m-3-5v5m-4 0h8M4 7h16v10H4z"></path></svg>
            <span class="text-sm font-semibold">No registered vehicles found</span>
          </div>
        </div>
      <?php else: ?>
        <div class="registered-list">
          <?php foreach ($ownedVehicles as $index => $vehicle): ?>
            <div class="registered-item">
              <div class="registered-left">
                <span class="registered-dot <?php echo !empty($vehicle['is_primary']) ? 'primary' : ''; ?>"></span>
                <div class="min-w-0">
                  <div class="registered-title">
                    <span><?php echo htmlspecialchars((string)($vehicle['plate_number'] ?? '')); ?></span>
                    <span class="text-xs font-medium text-gray-400 dark:text-slate-400"><?php echo htmlspecialchars((string)($vehicle['vehicle_type'] ?? '')); ?></span>
                  </div>
                  <div class="registered-subtitle">
                    <?php echo htmlspecialchars((string)($vehicle['color'] ?? 'Unknown Color')); ?>
                  </div>
                </div>
              </div>
              <?php if (!empty($vehicle['vehicle_img_url'])): ?>
                <a class="registered-badge js-profile-preview" href="#"
                   data-preview-src="<?php echo htmlspecialchars((string)$vehicle['vehicle_img_url']); ?>"
                   data-preview-title="Vehicle image"
                   data-preview-caption="<?php echo htmlspecialchars((string)($vehicle['plate_number'] ?? '')); ?>">
                  <?php echo !empty($vehicle['is_primary']) ? 'Active' : 'View'; ?>
                </a>
              <?php else: ?>
                <span class="registered-badge muted"><?php echo !empty($vehicle['is_primary']) ? 'Active' : 'Inactive'; ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="profile-actions">
    <button type="button" class="ta-btn ta-btn-secondary cancel-btn">Close</button>
  </div>
</div>
