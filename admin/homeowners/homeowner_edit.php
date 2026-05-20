<?php
// admin/homeowners/homeowner_edit.php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

// Authorization check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
AuditLogger::init($pdo);

function normalizeNamePart(string $value): string {
  $value = trim($value);
  if ($value === '') {
    return '';
  }
  return preg_replace_callback('/\b([a-z])/', function ($m) {
    return strtoupper($m[1]);
  }, strtolower($value));
}

function saveFixedSizeImage(string $tmpPath, string $destination, string $ext): bool {
  // Fall back to original upload when GD is unavailable.
  if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
    return move_uploaded_file($tmpPath, $destination);
  }

  $raw = @file_get_contents($tmpPath);
  if ($raw === false) {
    return move_uploaded_file($tmpPath, $destination);
  }

  $src = @imagecreatefromstring($raw);
  if (!$src) {
    return move_uploaded_file($tmpPath, $destination);
  }

  $srcW = imagesx($src);
  $srcH = imagesy($src);
  if ($srcW <= 0 || $srcH <= 0) {
    imagedestroy($src);
    return move_uploaded_file($tmpPath, $destination);
  }

  $targetW = 1024;
  $targetH = 1024;
  $srcRatio = $srcW / $srcH;
  $targetRatio = $targetW / $targetH;

  if ($srcRatio > $targetRatio) {
    $cropH = $srcH;
    $cropW = (int)round($srcH * $targetRatio);
    $cropX = (int)floor(($srcW - $cropW) / 2);
    $cropY = 0;
  } else {
    $cropW = $srcW;
    $cropH = (int)round($srcW / $targetRatio);
    $cropX = 0;
    $cropY = (int)floor(($srcH - $cropH) / 2);
  }

  $dst = imagecreatetruecolor($targetW, $targetH);
  $ext = strtolower($ext);
  if (in_array($ext, ['png', 'webp'], true)) {
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);
  } else {
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);
  }

  imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);

  $saved = false;
  if (in_array($ext, ['jpg', 'jpeg'], true)) {
    $saved = imagejpeg($dst, $destination, 85);
  } elseif ($ext === 'png') {
    $saved = imagepng($dst, $destination, 6);
  } elseif ($ext === 'webp' && function_exists('imagewebp')) {
    $saved = imagewebp($dst, $destination, 85);
  }

  imagedestroy($dst);
  imagedestroy($src);

  if (!$saved) {
    return move_uploaded_file($tmpPath, $destination);
  }

  return true;
}

  function syncPrimaryVehicleRecord(PDO $pdo, int $homeownerId, string $plateNumber, string $vehicleType, string $color, ?string $vehicleImage = null): void {
    // Whitelist of allowed columns for validation
    $allowedColumns = ['id', 'vehicle_id', 'homeowner_id', 'plate_number', 'vehicle_type', 'color', 'vehicle_img', 'is_primary', 'is_active', 'registered_at', 'created_at'];
    
    $vehicleColumns = [];
    try {
      $vehicleColumns = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
      return;
    }

    if (empty($vehicleColumns) || !in_array('homeowner_id', $vehicleColumns, true) || !in_array('plate_number', $vehicleColumns, true)) {
      return;
    }

    // Only use columns that exist in table AND are in whitelist
    $vehicleColumns = array_intersect($vehicleColumns, $allowedColumns);
    
    // Determine which columns are available
    $idColumn = in_array('id', $vehicleColumns, true) ? 'id' : (in_array('vehicle_id', $vehicleColumns, true) ? 'vehicle_id' : null);
    if ($idColumn === null) {
      return;
    }

    $hasVehicleType = in_array('vehicle_type', $vehicleColumns, true);
    $hasColor = in_array('color', $vehicleColumns, true);
    $hasVehicleImg = in_array('vehicle_img', $vehicleColumns, true);
    $hasIsPrimary = in_array('is_primary', $vehicleColumns, true);
    $hasIsActive = in_array('is_active', $vehicleColumns, true);
    $hasRegisteredAt = in_array('registered_at', $vehicleColumns, true);
    $hasCreatedAt = in_array('created_at', $vehicleColumns, true);

    $orderParts = [];
    if ($hasIsPrimary) {
      $orderParts[] = 'is_primary DESC';
    }
    if ($hasRegisteredAt) {
      $orderParts[] = 'registered_at DESC';
    } elseif ($hasCreatedAt) {
      $orderParts[] = 'created_at DESC';
    }
    $orderParts[] = $idColumn . ' DESC';

    $where = 'homeowner_id = ?';
    if ($hasIsActive) {
      $where .= ' AND is_active = 1';
    }

    $stmt = $pdo->prepare("SELECT {$idColumn} FROM vehicles WHERE {$where} ORDER BY " . implode(', ', $orderParts) . " LIMIT 1");
    $stmt->execute([$homeownerId]);
    $existingVehicleId = $stmt->fetchColumn();

    if ($existingVehicleId !== false) {
      $setClauses = ['plate_number = ?'];
      $params = [$plateNumber];

      if ($hasVehicleType) {
        $setClauses[] = 'vehicle_type = ?';
        $params[] = $vehicleType;
      }
      if ($hasColor) {
        $setClauses[] = 'color = ?';
        $params[] = $color;
      }
      if ($hasVehicleImg && $vehicleImage !== null && trim((string)$vehicleImage) !== '') {
        $setClauses[] = 'vehicle_img = ?';
        $params[] = $vehicleImage;
      }

      $params[] = $existingVehicleId;
      $params[] = $homeownerId;
      $updateStmt = $pdo->prepare("UPDATE vehicles SET " . implode(', ', $setClauses) . " WHERE {$idColumn} = ? AND homeowner_id = ?");
      $updateStmt->execute($params);
      return;
    }

    $insertColumns = ['homeowner_id', 'plate_number'];
    $insertValues = [$homeownerId, $plateNumber];

    if ($hasVehicleType) {
      $insertColumns[] = 'vehicle_type';
      $insertValues[] = $vehicleType;
    }
    if ($hasColor) {
      $insertColumns[] = 'color';
      $insertValues[] = $color;
    }
    if ($hasVehicleImg && $vehicleImage !== null && trim((string)$vehicleImage) !== '') {
      $insertColumns[] = 'vehicle_img';
      $insertValues[] = $vehicleImage;
    }
    if ($hasIsPrimary) {
      $insertColumns[] = 'is_primary';
      $insertValues[] = 1;
    }
    if ($hasIsActive) {
      $insertColumns[] = 'is_active';
      $insertValues[] = 1;
    }
    if ($hasRegisteredAt) {
      $insertColumns[] = 'registered_at';
      $insertValues[] = date('Y-m-d H:i:s');
    } elseif ($hasCreatedAt) {
      $insertColumns[] = 'created_at';
      $insertValues[] = date('Y-m-d H:i:s');
    }

    $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
    $insertStmt = $pdo->prepare("INSERT INTO vehicles (" . implode(',', $insertColumns) . ") VALUES (" . $placeholders . ")");
    $insertStmt->execute($insertValues);
  }

$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
} else {
  $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
}
$stmt = $pdo->prepare("SELECT * FROM homeowners WHERE id = ?");
$stmt->execute([$id]);
$homeowner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$homeowner && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<p>Record not found.</p>"; exit;
}

$editCarImgUrl = '';
if (!empty($homeowner)) {
  $editCarImgUrl = trim((string)($homeowner['car_img'] ?? ''));
  if ($editCarImgUrl === '') {
    try {
      $vehicleColumns = $pdo->query("SHOW COLUMNS FROM vehicles")->fetchAll(PDO::FETCH_COLUMN);
      if (in_array('vehicle_img', $vehicleColumns, true)) {
        $vehicleIdExpr = in_array('id', $vehicleColumns, true)
          ? 'id'
          : (in_array('vehicle_id', $vehicleColumns, true) ? 'vehicle_id' : 'id');
        $orderExpr = in_array('registered_at', $vehicleColumns, true)
          ? 'registered_at DESC, ' . $vehicleIdExpr . ' DESC'
          : (in_array('created_at', $vehicleColumns, true) ? 'created_at DESC, ' . $vehicleIdExpr . ' DESC' : $vehicleIdExpr . ' DESC');

        $vehicleStmt = $pdo->prepare("\n          SELECT vehicle_img\n          FROM vehicles\n          WHERE homeowner_id = ? AND vehicle_img IS NOT NULL AND vehicle_img <> ''\n          ORDER BY is_primary DESC, {$orderExpr}\n          LIMIT 1\n        ");
        $vehicleStmt->execute([$id]);
        $editCarImgUrl = trim((string)($vehicleStmt->fetchColumn() ?: ''));
      }
    } catch (Exception $e) {
      $editCarImgUrl = '';
    }
  }

  if ($editCarImgUrl !== '' && !preg_match('#^uploads/#i', $editCarImgUrl)) {
    $editCarImgUrl = 'uploads/' . ltrim($editCarImgUrl, '/');
  }
}

// POST update (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $posted = $_POST['csrf_token'] ?? '';
  if (!InputSanitizer::validateCsrf((string)$posted)) {
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF']); exit;
    }

    $existingName = trim((string)($homeowner['name'] ?? ''));
    $fallbackParts = preg_split('/\s+/', $existingName, 2);
    $fallbackFirst = $fallbackParts[0] ?? '';
    $fallbackLast = $fallbackParts[1] ?? '';

    $firstName = normalizeNamePart((string)($_POST['first_name'] ?? ($homeowner['first_name'] ?? $fallbackFirst)));
    $lastName = normalizeNamePart((string)($_POST['last_name'] ?? ($homeowner['last_name'] ?? $fallbackLast)));
    $name = trim($firstName . ' ' . $lastName);
    $contact = trim($_POST['contact'] ?? $homeowner['contact_number']);
    $address = trim($_POST['address'] ?? $homeowner['address']);
    $vehicle_type = trim($_POST['vehicle_type'] ?? $homeowner['vehicle_type']);
    $vehicleTypeOther = trim($_POST['vehicle_type_other'] ?? '');
    $color = trim($_POST['color'] ?? $homeowner['color']);
    $colorOther = trim($_POST['color_other'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? $homeowner['plate_number']);

    // Validate name
    if (strlen($firstName) < 2 || strlen($firstName) > 50 || strlen($lastName) < 2 || strlen($lastName) > 50 || strlen($name) > 100) {
      echo json_encode(['success'=>false,'message'=>'First and last name must be 2-50 characters each']); exit;
    }

    if (strcasecmp($vehicle_type, 'Car') === 0) {
      $vehicle_type = 'Sedan';
    }

    $allowedVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'E-bike', 'Truck', 'Other'];
    if (!in_array($vehicle_type, $allowedVehicleTypes, true)) {
      echo json_encode(['success'=>false,'message'=>'Invalid vehicle type']); exit;
    }
    if ($vehicle_type === 'Other') {
      $vehicle_type = trim($vehicleTypeOther);
      if ($vehicle_type === '' || strlen($vehicle_type) > 40) {
        echo json_encode(['success'=>false,'message'=>'Please provide a valid custom vehicle type (max 40 characters)']); exit;
      }
    }

    $allowedColors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Yellow', 'Orange', 'Other'];
    if (!in_array($color, $allowedColors, true)) {
      echo json_encode(['success'=>false,'message'=>'Invalid vehicle color']); exit;
    }
    if ($color === 'Other') {
      $color = trim($colorOther);
      if ($color === '' || strlen($color) > 30) {
        echo json_encode(['success'=>false,'message'=>'Please provide a valid custom vehicle color (max 30 characters)']); exit;
      }
    }

    // Validate plate number
    $plateCheck = InputValidator::validatePlateNumber($plate_number);
    if (!$plateCheck['valid']) {
        echo json_encode(['success'=>false,'message'=>$plateCheck['message']]); exit;
    }
    $plate_number = $plateCheck['formatted'];

    $dupStmt = $pdo->prepare("SELECT id, name FROM homeowners WHERE plate_number = ? AND id <> ? LIMIT 1");
    $dupStmt->execute([$plate_number, $id]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
      echo json_encode(['success' => false, 'message' => 'Plate number already linked to homeowner: ' . ($duplicate['name'] ?? 'Unknown')]); exit;
    }

    // Validate phone if provided
    if ($contact !== '') {
        $phoneCheck = InputValidator::validatePhoneNumber($contact);
        if (!$phoneCheck['valid']) {
            echo json_encode(['success'=>false,'message'=>$phoneCheck['message']]); exit;
        }
        $contact = $phoneCheck['formatted'];
    }

    // Validate address length
    if ($address !== '' && strlen($address) > 255) {
        echo json_encode(['success'=>false,'message'=>'Address must not exceed 255 characters']); exit;
    }

    // handle simple image uploads if present (optional)
    $owner_img = $homeowner['owner_img'] ?? null;
    $car_img   = $homeowner['car_img'] ?? null;
    $car_img_front = $homeowner['car_img_front'] ?? null;
    $car_img_left = $homeowner['car_img_left'] ?? null;
    $car_img_right = $homeowner['car_img_right'] ?? null;
    $car_img_rear = $homeowner['car_img_rear'] ?? null;
    
    $owners_upload_dir = __DIR__ . '/../../uploads/homeowners/';
    $vehicles_upload_dir = __DIR__ . '/../../uploads/vehicles/';
    
    // Ensure upload directories exist
    foreach ([$owners_upload_dir, $vehicles_upload_dir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    foreach (['owner_img','car_img'] as $field) {
        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $validation = InputValidator::validateImageUpload($_FILES[$field], 5 * 1024 * 1024);
            if (!$validation['valid']) {
                echo json_encode(['success'=>false,'message'=>ucfirst($field) . ': ' . $validation['message']]); exit;
            }

            $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
            $filename = date('Ymd_His_') . $field . '_' . time() . '.' . $ext;
            $upload_dir = $field === 'owner_img' ? $owners_upload_dir : $vehicles_upload_dir;
            $relative_path = $field === 'owner_img' ? 'homeowners/' : 'vehicles/';
                
            if (saveFixedSizeImage($_FILES[$field]['tmp_name'], $upload_dir . $filename, $ext)) {
                if ($field === 'owner_img') {
                    $owner_img = $relative_path . $filename;
                } else {
                    $car_img = $relative_path . $filename;
                }
            }
        }
    }

      foreach (['car_img_front', 'car_img_left', 'car_img_right', 'car_img_rear'] as $angleField) {
        if (!empty($_FILES[$angleField]['name']) && $_FILES[$angleField]['error'] === UPLOAD_ERR_OK) {
          $validation = InputValidator::validateImageUpload($_FILES[$angleField], 5 * 1024 * 1024);
          if (!$validation['valid']) {
            echo json_encode(['success'=>false,'message'=>ucfirst(str_replace('_', ' ', $angleField)) . ': ' . $validation['message']]); exit;
          }
          $ext = strtolower(pathinfo($_FILES[$angleField]['name'], PATHINFO_EXTENSION));
          $filename = date('Ymd_His_') . $angleField . '_' . time() . '.' . $ext;
          if (saveFixedSizeImage($_FILES[$angleField]['tmp_name'], $vehicles_upload_dir . $filename, $ext)) {
            $$angleField = 'vehicles/' . $filename;
          }
        }
      }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);
        $hasAngles = in_array('car_img_front', $cols, true) && in_array('car_img_left', $cols, true)
          && in_array('car_img_right', $cols, true) && in_array('car_img_rear', $cols, true);

        $hasSplitNames = in_array('first_name', $cols, true) && in_array('last_name', $cols, true);
        $setClauses = ['name=?', 'contact_number=?', 'address=?', 'vehicle_type=?', 'color=?', 'plate_number=?', 'owner_img=?', 'car_img=?'];
        $params = [$name, $contact, $address, $vehicle_type, $color, $plate_number, $owner_img, $car_img];

        if ($hasSplitNames) {
            $setClauses[] = 'first_name=?';
            $setClauses[] = 'last_name=?';
            $params[] = $firstName;
            $params[] = $lastName;
        }

        if ($hasAngles) {
            $setClauses[] = 'car_img_front=?';
            $setClauses[] = 'car_img_left=?';
            $setClauses[] = 'car_img_right=?';
            $setClauses[] = 'car_img_rear=?';
            $params[] = $car_img_front;
            $params[] = $car_img_left;
            $params[] = $car_img_right;
            $params[] = $car_img_rear;
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE homeowners SET " . implode(', ', $setClauses) . " WHERE id=?");
        $ok = $stmt->execute($params);

        if ($ok) {
          try {
            syncPrimaryVehicleRecord($pdo, $id, $plate_number, $vehicle_type, $color, $car_img);

            $oldValues = [
              'name' => $homeowner['name'] ?? null,
              'contact_number' => $homeowner['contact_number'] ?? null,
              'address' => $homeowner['address'] ?? null,
              'vehicle_type' => $homeowner['vehicle_type'] ?? null,
              'color' => $homeowner['color'] ?? null,
              'plate_number' => $homeowner['plate_number'] ?? null,
              'owner_img' => $homeowner['owner_img'] ?? null,
              'car_img' => $homeowner['car_img'] ?? null,
            ];
            $newValues = [
              'name' => $name,
              'contact_number' => $contact,
              'address' => $address,
              'vehicle_type' => $vehicle_type,
              'color' => $color,
              'plate_number' => $plate_number,
              'owner_img' => $owner_img,
              'car_img' => $car_img,
            ];

            if ($hasSplitNames) {
              $oldValues['first_name'] = $homeowner['first_name'] ?? null;
              $oldValues['last_name'] = $homeowner['last_name'] ?? null;
              $newValues['first_name'] = $firstName;
              $newValues['last_name'] = $lastName;
            }

            if ($hasAngles) {
              $oldValues['car_img_front'] = $homeowner['car_img_front'] ?? null;
              $oldValues['car_img_left'] = $homeowner['car_img_left'] ?? null;
              $oldValues['car_img_right'] = $homeowner['car_img_right'] ?? null;
              $oldValues['car_img_rear'] = $homeowner['car_img_rear'] ?? null;
              $newValues['car_img_front'] = $car_img_front;
              $newValues['car_img_left'] = $car_img_left;
              $newValues['car_img_right'] = $car_img_right;
              $newValues['car_img_rear'] = $car_img_rear;
            }

            AuditLogger::logDataChange('homeowner_update', 'homeowners', (int)$id, $oldValues, $newValues);
          } catch (Exception $e) {
            error_log('[HOMEOWNER_EDIT] Vehicle sync warning: ' . $e->getMessage());
          }
        }

        echo json_encode(['success'=>$ok,'message'=>$ok ? 'Record updated' : 'Update failed']);
    } catch (Exception $e) {
        error_log('[HOMEOWNER_EDIT] DB error: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'A database error occurred. Please try again.']);
    }
    exit;
}

// GET: render the edit form (fragment)
if (!empty($_GET['ajax'])):
?>
<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
  .edit-homeowner-form {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
  }
  .edit-homeowner-form .form-section {
    border: 1px solid var(--ta-card-border);
    border-radius: 0.75rem;
    padding: 1rem;
    background: var(--ta-card-bg);
  }
  .edit-homeowner-form .section-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ta-text-secondary);
    margin-bottom: 0.75rem;
  }
  .edit-homeowner-form .form-title {
    font-size: 1.25rem !important;
    line-height: 1.2;
    margin-bottom: 0.25rem !important;
  }
  .edit-homeowner-form .form-subtitle {
    color: var(--ta-text-muted);
    font-size: 0.8rem;
    margin-bottom: 1.1rem;
  }
  .edit-homeowner-form .ta-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.875rem;
  }
  .edit-homeowner-form .ta-grid-1 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.875rem;
  }
  .edit-homeowner-form .field-span-2 {
    grid-column: span 2;
  }
  .edit-homeowner-form .field-wrap {
    min-width: 0;
  }
  .edit-homeowner-form .ta-file-input {
    font-size: 0.8rem;
    line-height: 1.25;
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    padding: 0.28rem 0.5rem !important;
    min-height: 2.5rem;
  }
  .edit-homeowner-form .ta-file-input::file-selector-button {
    margin: 0 0.6rem 0 0;
    border: 1px solid var(--ta-input-border);
    border-radius: 0.45rem;
    background: var(--ta-card-bg);
    color: var(--ta-text-primary);
    padding: 0.34rem 0.68rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.1;
    height: 1.9rem;
    cursor: pointer;
    white-space: nowrap;
    vertical-align: middle;
  }
  .edit-homeowner-form .ta-file-input::-webkit-file-upload-button {
    margin: 0 0.6rem 0 0;
    border: 1px solid var(--ta-input-border);
    border-radius: 0.45rem;
    background: var(--ta-card-bg);
    color: var(--ta-text-primary);
    padding: 0.34rem 0.68rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.1;
    height: 1.9rem;
    cursor: pointer;
    white-space: nowrap;
    vertical-align: middle;
  }
  .edit-homeowner-form .preview-img {
    width: 92px;
    height: 92px;
    object-fit: cover;
    border-radius: 0.5rem;
    border: 1px solid var(--ta-card-border);
    display: block;
    margin-bottom: 0.5rem;
  }
  .edit-homeowner-form .helper-text {
    color: var(--ta-text-muted);
    font-size: 0.72rem;
    margin-top: 0.35rem;
  }
  .edit-homeowner-form .form-actions {
    margin-top: 1.2rem;
    padding-top: 0.9rem;
    padding-inline: 0.35rem;
    padding-bottom: 0.45rem;
    border-top: 1px solid var(--ta-card-border);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.6rem;
    flex-wrap: wrap;
  }
  .edit-homeowner-form .form-actions .ta-btn {
    min-width: 128px;
  }
  @media (max-width: 1024px) {
    .edit-homeowner-form .ta-grid-2 {
      grid-template-columns: 1fr;
    }
    .edit-homeowner-form .field-span-2 {
      grid-column: span 1;
    }
    .edit-homeowner-form .form-actions {
      justify-content: stretch;
    }
    .edit-homeowner-form .form-actions .ta-btn {
      flex: 1 1 100%;
      min-width: 0;
    }
  }
  @media (max-width: 640px) {
    .edit-homeowner-form .form-section {
      padding: 0.85rem;
      border-radius: 0.65rem;
    }
    .edit-homeowner-form .form-title {
      font-size: 1.08rem !important;
    }
  }
</style>
<form id="editForm" method="post" enctype="multipart/form-data" class="modern-form compact-form edit-homeowner-form" action="homeowners/homeowner_edit.php?id=<?php echo intval($id); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
  <input type="hidden" name="id" value="<?php echo intval($id); ?>">

  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1 text-center form-title">Edit Homeowner</h3>
  <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 text-center form-subtitle">Update homeowner information and attached vehicle details.</p>

  <?php
    $existingFullName = trim((string)($homeowner['name'] ?? ''));
    $nameParts = preg_split('/\s+/', $existingFullName, 2);
    $existingFirstName = $homeowner['first_name'] ?? ($nameParts[0] ?? '');
    $existingLastName = $homeowner['last_name'] ?? ($nameParts[1] ?? '');
    $vehicleTypeValue = (string)($homeowner['vehicle_type'] ?? '');
    $knownVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'Truck'];
    $vehicleTypeIsOther = ($vehicleTypeValue !== '' && !in_array($vehicleTypeValue, $knownVehicleTypes, true));
    $colorValue = (string)($homeowner['color'] ?? '');
    $knownColors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Yellow', 'Orange'];
    $colorIsOther = ($colorValue !== '' && !in_array($colorValue, $knownColors, true));
  ?>

  <div class="space-y-4">
    <div class="form-section">
      <p class="section-title">Basic Information</p>
      <div class="ta-grid-2">
    <!-- Row 1 -->
    <label class="field-wrap">
      <span class="ta-label">First Name</span>
      <input
        type="text"
        name="first_name"
        required
        minlength="2"
        maxlength="50"
        value="<?php echo htmlspecialchars($existingFirstName); ?>"
        placeholder="First name"
        class="ta-input"
      >
    </label>

    <label class="field-wrap">
      <span class="ta-label">Last Name</span>
      <input
        type="text"
        name="last_name"
        required
        minlength="2"
        maxlength="50"
        value="<?php echo htmlspecialchars($existingLastName); ?>"
        placeholder="Last name"
        class="ta-input"
      >
    </label>

    <label class="field-wrap">
      <span class="ta-label">Contact</span>
      <input
        type="text"
        name="contact"
        required
        inputmode="numeric"
        autocomplete="off"
        maxlength="11"
        oninput="this.value = this.value.replace(/\D/g, '').slice(0, 11)"
        value="<?php echo htmlspecialchars($homeowner['contact_number'] ?? ''); ?>"
        placeholder="Phone number"
        class="ta-input"
      >
    </label>

    <!-- Row 2 -->
    <label class="field-wrap field-span-2">
      <span class="ta-label">Address</span>
      <textarea
        name="address"
        rows="2"
        required
        maxlength="255"
        placeholder="Full address"
        class="ta-input resize-none"
      ><?php echo htmlspecialchars($homeowner['address'] ?? ''); ?></textarea>
    </label>

    <!-- Row 3 -->
    <label class="field-wrap">
      <span class="ta-label">Vehicle Type</span>
      <select
        name="vehicle_type"
        class="ta-input"
      >
        <option value="" <?php echo $vehicleTypeValue === '' ? 'selected' : ''; ?>>Select vehicle type</option>
        <option value="Sedan" <?php echo $vehicleTypeValue === 'Sedan' ? 'selected' : ''; ?>>Sedan</option>
        <option value="SUV" <?php echo $vehicleTypeValue === 'SUV' ? 'selected' : ''; ?>>SUV</option>
        <option value="Hatchback" <?php echo $vehicleTypeValue === 'Hatchback' ? 'selected' : ''; ?>>Hatchback</option>
        <option value="Pickup" <?php echo $vehicleTypeValue === 'Pickup' ? 'selected' : ''; ?>>Pickup</option>
        <option value="Van" <?php echo $vehicleTypeValue === 'Van' ? 'selected' : ''; ?>>Van</option>
        <option value="Motorcycle" <?php echo $vehicleTypeValue === 'Motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
        <option value="Truck" <?php echo $vehicleTypeValue === 'Truck' ? 'selected' : ''; ?>>Truck</option>
        <option value="Other" <?php echo $vehicleTypeIsOther ? 'selected' : ''; ?>>Other</option>
      </select>
      <input type="text" name="vehicle_type_other" value="<?php echo $vehicleTypeIsOther ? htmlspecialchars($vehicleTypeValue) : ''; ?>" maxlength="40" placeholder="Enter vehicle type" class="ta-input mt-2 <?php echo $vehicleTypeIsOther ? '' : 'hidden'; ?>" id="editVehicleTypeOtherInput">
    </label>

    <label class="field-wrap">
      <span class="ta-label">Color</span>
      <select
        name="color"
        class="ta-input"
      >
        <option value="" <?php echo $colorValue === '' ? 'selected' : ''; ?>>Select color</option>
        <option value="Black" <?php echo $colorValue === 'Black' ? 'selected' : ''; ?>>Black</option>
        <option value="White" <?php echo $colorValue === 'White' ? 'selected' : ''; ?>>White</option>
        <option value="Silver" <?php echo $colorValue === 'Silver' ? 'selected' : ''; ?>>Silver</option>
        <option value="Gray" <?php echo $colorValue === 'Gray' ? 'selected' : ''; ?>>Gray</option>
        <option value="Red" <?php echo $colorValue === 'Red' ? 'selected' : ''; ?>>Red</option>
        <option value="Blue" <?php echo $colorValue === 'Blue' ? 'selected' : ''; ?>>Blue</option>
        <option value="Green" <?php echo $colorValue === 'Green' ? 'selected' : ''; ?>>Green</option>
        <option value="Brown" <?php echo $colorValue === 'Brown' ? 'selected' : ''; ?>>Brown</option>
        <option value="Yellow" <?php echo $colorValue === 'Yellow' ? 'selected' : ''; ?>>Yellow</option>
        <option value="Orange" <?php echo $colorValue === 'Orange' ? 'selected' : ''; ?>>Orange</option>
        <option value="Other" <?php echo $colorIsOther ? 'selected' : ''; ?>>Other</option>
      </select>
      <input type="text" name="color_other" value="<?php echo $colorIsOther ? htmlspecialchars($colorValue) : ''; ?>" maxlength="30" placeholder="Enter color" class="ta-input mt-2 <?php echo $colorIsOther ? '' : 'hidden'; ?>" id="editColorOtherInput">
    </label>

    <!-- Row 4 -->
    <label class="field-wrap">
      <span class="ta-label">Plate Number</span>
      <input
        type="text"
        name="plate_number"
        id="plateInput"
        required
        maxlength="15"
        pattern="[A-Z0-9\-]{3,15}"
        title="Plate number should be 3-15 characters (letters, numbers, hyphens)"
        style="text-transform: uppercase;"
        autocomplete="off"
        value="<?php echo htmlspecialchars($homeowner['plate_number'] ?? ''); ?>"
        placeholder="e.g., ABC-1234"
        class="ta-input"
      >
    </label>

      </div>
    </div>

    <div class="form-section">
      <p class="section-title">Vehicle and Media</p>
      <div class="ta-grid-2">
    <!-- Images -->
    <div class="field-wrap">
      <label class="ta-label">Owner Image</label>
      <?php if (!empty($homeowner['owner_img'])): ?>
        <?php
          $ownerImgPath = $homeowner['owner_img'];
          if (!preg_match('#^uploads/#i', $ownerImgPath)) {
              $ownerImgPath = 'uploads/' . ltrim($ownerImgPath, '/');
          }
        ?>
        <img src="../../<?php echo htmlspecialchars($ownerImgPath ?? ''); ?>" class="preview-img" onerror="this.style.display='none'">
      <?php endif; ?>
      <input
        type="file"
        name="owner_img"
        accept="image/*"
        class="ta-input ta-file-input"
      >
      <small class="helper-text">Leave empty to keep current image.</small>
    </div>

    <div class="field-wrap">
      <label class="ta-label">Car Image</label>
      <?php if (!empty($editCarImgUrl)): ?>
        <img src="../../<?php echo htmlspecialchars($editCarImgUrl ?? ''); ?>" class="preview-img" onerror="this.style.display='none'">
      <?php endif; ?>
      <input
        type="file"
        name="car_img"
        accept="image/*"
        class="ta-input ta-file-input"
      >
      <small class="helper-text">Leave empty to keep current image.</small>
    </div>

      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="button" class="ta-btn ta-btn-secondary cancel-btn">Cancel</button>
    <button type="submit" class="ta-btn ta-btn-primary"><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M7 3v4h7"/><path d="M7 17h10"/><path d="M7 13h10"/></svg> Save Changes</button>
  </div>
</form>
<script nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
(function () {
  const form = document.getElementById('editForm');
  if (!form || form.dataset.enhanced === '1') return;
  form.dataset.enhanced = '1';

  const vehicleTypeSelect = form.querySelector('select[name="vehicle_type"]');
  const vehicleTypeOtherInput = document.getElementById('editVehicleTypeOtherInput');
  const colorSelect = form.querySelector('select[name="color"]');
  const colorOtherInput = document.getElementById('editColorOtherInput');

  function syncOtherField(selectEl, otherInput) {
    if (!selectEl || !otherInput) return;
    const showOther = selectEl.value === 'Other';
    otherInput.classList.toggle('hidden', !showOther);
    otherInput.required = showOther;
    if (!showOther) otherInput.value = '';
  }

  function normalizeNameInput(el) {
    if (!el || !el.value) return;
    el.value = el.value
      .trim()
      .toLowerCase()
      .replace(/\b([a-z])/g, (m) => m.toUpperCase());
  }

  function formatPlateInput(el) {
    if (!el || !el.value) return;
    el.value = el.value
      .toUpperCase()
      .replace(/[^A-Z0-9-]/g, '')
      .slice(0, 15);
  }

  function formatContactInput(el) {
    if (!el || !el.value) return;
    el.value = el.value.replace(/\D/g, '').slice(0, 11);
  }

  vehicleTypeSelect?.addEventListener('change', () => syncOtherField(vehicleTypeSelect, vehicleTypeOtherInput));
  colorSelect?.addEventListener('change', () => syncOtherField(colorSelect, colorOtherInput));
  syncOtherField(vehicleTypeSelect, vehicleTypeOtherInput);
  syncOtherField(colorSelect, colorOtherInput);

  form.querySelectorAll('input[name="first_name"], input[name="last_name"]').forEach((input) => {
    input.addEventListener('input', () => normalizeNameInput(input));
    input.addEventListener('blur', () => normalizeNameInput(input));
  });

  form.querySelectorAll('input[name="plate_number"]').forEach((input) => {
    input.addEventListener('input', () => formatPlateInput(input));
    formatPlateInput(input);
  });

  form.querySelectorAll('input[name="contact"]').forEach((input) => {
    input.addEventListener('input', () => formatContactInput(input));
    formatContactInput(input);
  });
})();
</script>
<?php endif; ?>
