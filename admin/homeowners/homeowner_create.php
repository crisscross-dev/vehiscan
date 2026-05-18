<?php
// admin/homeowners/homeowner_create.php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_validator.php';

// Authorization check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

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
    // Check if a primary vehicle exists
    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE homeowner_id = ? ORDER BY is_primary DESC, registered_at DESC, id DESC LIMIT 1");
    $stmt->execute([$homeownerId]);
    $existingVehicleId = $stmt->fetchColumn();

    if ($existingVehicleId !== false) {
      // Update existing
      $updateStmt = $pdo->prepare("UPDATE vehicles SET plate_number = ?, vehicle_type = ?, color = ? WHERE id = ? AND homeowner_id = ?");
      $updateStmt->execute([$plateNumber, $vehicleType, $color, $existingVehicleId, $homeownerId]);
      return;
    }

    // Insert new
    $insertStmt = $pdo->prepare("INSERT INTO vehicles (homeowner_id, plate_number, vehicle_type, color, is_primary, is_active, registered_at) VALUES (?, ?, ?, ?, 1, 1, NOW())");
    $insertStmt->execute([$homeownerId, $plateNumber, $vehicleType, $color]);
  }

// POST create (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $posted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, (string)$posted)) {
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
    }
    $firstName = normalizeNamePart((string)($_POST['first_name'] ?? ''));
    $lastName = normalizeNamePart((string)($_POST['last_name'] ?? ''));
    $name = trim($firstName . ' ' . $lastName);
    $plate = trim($_POST['plate_number'] ?? '');
    $vehicle = trim($_POST['vehicle_type'] ?? '');
    $vehicleOther = trim($_POST['vehicle_type_other'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $colorOther = trim($_POST['color_other'] ?? '');

    if (!$firstName || !$lastName || !$plate) {
      echo json_encode(['success'=>false,'message'=>'First name, last name, and plate number are required']); exit;
    }

    // Validate name length
    if (strlen($firstName) < 2 || strlen($firstName) > 50 || strlen($lastName) < 2 || strlen($lastName) > 50 || strlen($name) > 100) {
      echo json_encode(['success'=>false,'message'=>'First and last name must be 2-50 characters each']); exit;
    }

    if (strcasecmp($vehicle, 'Car') === 0) {
      $vehicle = 'Sedan';
    }
    $allowedVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'E-bike', 'Truck', 'Other'];
    if (!in_array($vehicle, $allowedVehicleTypes, true)) {
      echo json_encode(['success'=>false,'message'=>'Invalid vehicle type']); exit;
    }
    if ($vehicle === 'Other') {
      $vehicle = trim($vehicleOther);
      if ($vehicle === '' || strlen($vehicle) > 40) {
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

    // Validate plate number format
    $plateCheck = InputValidator::validatePlateNumber($plate);
    if (!$plateCheck['valid']) {
        echo json_encode(['success'=>false,'message'=>$plateCheck['message']]); exit;
    }
    $plate = $plateCheck['formatted'];

    // Prevent multiple homeowner records from sharing the same plate number.
    $dupStmt = $pdo->prepare("SELECT id, name FROM homeowners WHERE plate_number = ? LIMIT 1");
    $dupStmt->execute([$plate]);
    $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicate) {
      $dupName = htmlspecialchars($duplicate['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
      echo json_encode(['success' => false, 'message' => 'Plate number already linked to homeowner: ' . $dupName]);
      exit;
    }

    // Validate phone number if provided
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
    
    // Handle image uploads
    $owner_img = null;
    $car_img = null;
    $car_img_front = null;
    $car_img_left = null;
    $car_img_right = null;
    $car_img_rear = null;
    
    $owners_upload_dir = __DIR__ . '/../../uploads/homeowners/';
    $vehicles_upload_dir = __DIR__ . '/../../uploads/vehicles/';
    
    // Ensure upload directories exist
    foreach ([$owners_upload_dir, $vehicles_upload_dir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Handle owner image upload
    if (!empty($_FILES['owner_img']['name']) && $_FILES['owner_img']['error'] === UPLOAD_ERR_OK) {
        $validation = InputValidator::validateImageUpload($_FILES['owner_img'], 5 * 1024 * 1024);
        if (!$validation['valid']) {
            echo json_encode(['success'=>false,'message'=>'Owner image: ' . $validation['message']]); exit;
        }
        $ext = strtolower(pathinfo($_FILES['owner_img']['name'], PATHINFO_EXTENSION));
        $filename = date('Ymd_His_') . 'owner_' . uniqid() . '.' . $ext;
        if (saveFixedSizeImage($_FILES['owner_img']['tmp_name'], $owners_upload_dir . $filename, $ext)) {
            $owner_img = 'homeowners/' . $filename;
        }
    }
    
    // Handle car image upload
    if (!empty($_FILES['car_img']['name']) && $_FILES['car_img']['error'] === UPLOAD_ERR_OK) {
        $validation = InputValidator::validateImageUpload($_FILES['car_img'], 5 * 1024 * 1024);
        if (!$validation['valid']) {
            echo json_encode(['success'=>false,'message'=>'Vehicle image: ' . $validation['message']]); exit;
        }
        $ext = strtolower(pathinfo($_FILES['car_img']['name'], PATHINFO_EXTENSION));
        $filename = date('Ymd_His_') . 'car_' . uniqid() . '.' . $ext;
        if (saveFixedSizeImage($_FILES['car_img']['tmp_name'], $vehicles_upload_dir . $filename, $ext)) {
            $car_img = 'vehicles/' . $filename;
        }
    }

      // Optional vehicle angle uploads
      foreach (['car_img_front', 'car_img_left', 'car_img_right', 'car_img_rear'] as $angleField) {
        if (!empty($_FILES[$angleField]['name']) && $_FILES[$angleField]['error'] === UPLOAD_ERR_OK) {
          $validation = InputValidator::validateImageUpload($_FILES[$angleField], 5 * 1024 * 1024);
          if (!$validation['valid']) {
            echo json_encode(['success'=>false,'message'=>ucfirst(str_replace('_', ' ', $angleField)) . ': ' . $validation['message']]); exit;
          }
          $ext = strtolower(pathinfo($_FILES[$angleField]['name'], PATHINFO_EXTENSION));
          $filename = date('Ymd_His_') . $angleField . '_' . uniqid() . '.' . $ext;
          if (saveFixedSizeImage($_FILES[$angleField]['tmp_name'], $vehicles_upload_dir . $filename, $ext)) {
            $$angleField = 'vehicles/' . $filename;
          }
        }
      }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO homeowners 
            (name, first_name, last_name, plate_number, vehicle_type, contact_number, address, color, owner_img, car_img) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $firstName, $lastName, $plate, $vehicle, $contact, $address, $color, $owner_img, $car_img
        ]);

        $homeownerId = (int)$pdo->lastInsertId();
        if ($homeownerId > 0) {
          try {
            syncPrimaryVehicleRecord($pdo, $homeownerId, $plate, $vehicle, $color, $car_img);
          } catch (Exception $e) {
            error_log('[HOMEOWNER_CREATE] Vehicle sync warning: ' . $e->getMessage());
          }
        }

        echo json_encode(['success'=>true,'message'=>'Homeowner added successfully']);
    } catch (Exception $e) {
        error_log('[HOMEOWNER_CREATE] DB error: ' . $e->getMessage());
        echo json_encode(['success'=>false,'message'=>'A database error occurred. Please try again.']);
    }
    exit;
}

// GET => render form fragment (when called with ajax=1)
?>
<style>
  .create-homeowner-form {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
  }
  .create-homeowner-form .form-section {
    border: 1px solid var(--ta-card-border);
    border-radius: 0.75rem;
    padding: 1rem;
    background: var(--ta-card-bg);
  }
  .create-homeowner-form .section-title {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--ta-text-secondary);
    margin-bottom: 0.75rem;
  }
  .create-homeowner-form .form-title {
    font-size: 1.35rem !important;
    line-height: 1.2;
    margin-bottom: 0.25rem !important;
  }
  .create-homeowner-form .form-subtitle {
    color: var(--ta-text-muted);
    font-size: 0.8rem;
    margin-bottom: 1rem;
  }
  .create-homeowner-form .ta-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.875rem;
  }
  .create-homeowner-form .field-span-2 {
    grid-column: span 2;
  }
  .create-homeowner-form .field-wrap {
    min-width: 0;
  }
  .create-homeowner-form .ta-file-input {
    font-size: 0.8rem;
    line-height: 1.25;
    display: block;
    width: 100%;
    min-width: 0;
    max-width: 100%;
    padding: 0.28rem 0.5rem !important;
    min-height: 2.5rem;
  }
  .create-homeowner-form .ta-file-input::file-selector-button {
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
  .create-homeowner-form .ta-file-input::-webkit-file-upload-button {
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
  .create-homeowner-form .form-actions {
    margin-top: 1.2rem;
    padding-top: 1rem;
    padding-inline: 0.35rem;
    padding-bottom: 0.45rem;
    border-top: 1px solid var(--ta-card-border);
    flex-wrap: wrap;
  }
  .create-homeowner-form .form-actions .action-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
  .create-homeowner-form .form-actions .ta-btn {
    min-width: 120px;
  }
  .create-homeowner-form .wizard-step-indicator {
    transition: all 0.15s ease;
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.35);
  }
  .create-homeowner-form .wizard-step-indicator.bg-blue-100 {
    box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.18);
  }
  @media (min-width: 1280px) {
    .create-homeowner-form .form-section {
      padding: 1.25rem;
    }
    .create-homeowner-form .compact-grid {
      gap: 1rem;
    }
    .create-homeowner-form .section-title {
      font-size: 0.82rem;
      margin-bottom: 0.85rem;
    }
    .create-homeowner-form input[type="text"],
    .create-homeowner-form textarea {
      padding-top: 0.72rem !important;
      padding-bottom: 0.72rem !important;
    }
  }
  @media (max-width: 1024px) {
    .create-homeowner-form .ta-grid-2 {
      grid-template-columns: 1fr;
    }
    .create-homeowner-form .field-span-2 {
      grid-column: span 1;
    }
    .create-homeowner-form .form-section {
      padding: 0.85rem;
      border-radius: 0.65rem;
    }
    .create-homeowner-form .section-title {
      margin-bottom: 0.6rem;
    }
    .create-homeowner-form .form-actions {
      justify-content: stretch;
    }
    .create-homeowner-form .form-actions .action-group {
      width: 100%;
    }
    .create-homeowner-form .form-actions .ta-btn {
      flex: 1 1 100%;
      min-width: 0;
    }
  }
</style>
<form id="createHomeownerForm" class="modern-form compact-form create-homeowner-form" action="homeowners/homeowner_create.php" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

  <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1 text-center form-title">Add New Homeowner</h3>
  <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 text-center form-subtitle">Three-step registration to avoid long scrolling.</p>

  <div class="mb-5 grid grid-cols-3 gap-2" id="homeownerWizardSteps">
    <div class="wizard-step-indicator text-center px-2 py-2 rounded-lg text-xs font-semibold bg-blue-100 text-blue-700" data-step="1">1. Basic Info</div>
    <div class="wizard-step-indicator text-center px-2 py-2 rounded-lg text-xs font-semibold bg-gray-100 text-gray-500" data-step="2">2. Contact</div>
    <div class="wizard-step-indicator text-center px-2 py-2 rounded-lg text-xs font-semibold bg-gray-100 text-gray-500" data-step="3">3. Photos</div>
  </div>

  <section class="wizard-panel" data-step="1">
    <div class="form-section">
      <p class="section-title">Basic Information</p>
      <div class="ta-grid-2">
      <label class="field-wrap">
        <span class="ta-label">First Name <span class="text-red-500">*</span></span>
        <input
          type="text"
          name="first_name"
          required
          minlength="2"
          maxlength="50"
          placeholder="First name"
          class="ta-input"
        >
      </label>

      <label class="field-wrap">
        <span class="ta-label">Last Name <span class="text-red-500">*</span></span>
        <input
          type="text"
          name="last_name"
          required
          minlength="2"
          maxlength="50"
          placeholder="Last name"
          class="ta-input"
        >
      </label>

      <label class="field-wrap">
        <span class="ta-label">Plate Number <span class="text-red-500">*</span></span>
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
          placeholder="e.g., ABC-1234"
          class="ta-input"
        >
      </label>

      <label class="field-wrap">
        <span class="ta-label">Vehicle Type</span>
        <select
          name="vehicle_type"
          class="ta-input"
        >
          <option value="">Select vehicle type</option>
          <option value="Sedan">Sedan</option>
          <option value="SUV">SUV</option>
          <option value="Hatchback">Hatchback</option>
          <option value="Pickup">Pickup</option>
          <option value="Van">Van</option>
          <option value="Motorcycle">Motorcycle</option>
          <option value="Truck">Truck</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" name="vehicle_type_other" maxlength="40" placeholder="Enter vehicle type" class="ta-input mt-2 hidden" id="vehicleTypeOtherInput">
      </label>

      <label class="field-wrap">
        <span class="ta-label">Color</span>
        <select
          name="color"
          class="ta-input"
        >
          <option value="">Select color</option>
          <option value="Black">Black</option>
          <option value="White">White</option>
          <option value="Silver">Silver</option>
          <option value="Gray">Gray</option>
          <option value="Red">Red</option>
          <option value="Blue">Blue</option>
          <option value="Green">Green</option>
          <option value="Brown">Brown</option>
          <option value="Yellow">Yellow</option>
          <option value="Orange">Orange</option>
          <option value="Other">Other</option>
        </select>
        <input type="text" name="color_other" maxlength="30" placeholder="Enter color" class="ta-input mt-2 hidden" id="colorOtherInput">
      </label>
      </div>
    </div>
  </section>

  <section class="wizard-panel hidden" data-step="2">
    <div class="form-section">
      <p class="section-title">Contact Details</p>
      <div class="ta-grid-2">
      <label class="field-wrap">
        <span class="ta-label">Contact</span>
        <input
          type="text"
          name="contact"
          inputmode="numeric"
          autocomplete="off"
          maxlength="11"
          placeholder="Phone number"
          class="ta-input"
        >
      </label>

      <label class="field-wrap">
        <span class="ta-label">Address</span>
        <textarea
          name="address"
          rows="2"
          maxlength="255"
          placeholder="Full address"
          class="ta-input resize-none"
        ></textarea>
      </label>
      </div>
    </div>
  </section>

  <section class="wizard-panel hidden" data-step="3">
    <div class="form-section">
      <p class="section-title">Vehicle Media</p>
      <div class="ta-grid-2">
      <div class="field-wrap">
        <label class="ta-label">Owner Image</label>
        <input
          type="file"
          name="owner_img"
          accept="image/*"
          class="ta-input ta-file-input"
        >
        <small class="text-gray-500 text-xs mt-1 block">Optional. JPG, PNG, WEBP.</small>
      </div>

      <div class="field-wrap">
        <label class="ta-label">Car Image</label>
        <input
          type="file"
          name="car_img"
          accept="image/*"
          class="ta-input ta-file-input"
        >
        <small class="text-gray-500 text-xs mt-1 block">Optional. JPG, PNG, WEBP.</small>
      </div>

      </div>
    </div>
  </section>

  <div class="flex items-center justify-between gap-3 pt-4 mt-6 border-t border-gray-200 form-actions">
    <div class="action-group">
      <button type="button" id="wizardPrevBtn" class="ta-btn ta-btn-secondary hidden">Back</button>
      <button type="button" id="wizardNextBtn" class="ta-btn ta-btn-primary">Next</button>
    </div>
    <div class="action-group">
      <button type="button" class="ta-btn ta-btn-secondary cancel-btn">Cancel</button>
      <button type="submit" id="wizardSubmitBtn" class="ta-btn ta-btn-primary hidden">Add</button>
    </div>
  </div>
</form>

<script>
(function () {
  const form = document.getElementById('createHomeownerForm');
  if (!form || form.dataset.wizardBound === '1') return;
  form.dataset.wizardBound = '1';

  const panels = Array.from(form.querySelectorAll('.wizard-panel'));
  const indicators = Array.from(form.querySelectorAll('.wizard-step-indicator'));
  const prevBtn = document.getElementById('wizardPrevBtn');
  const nextBtn = document.getElementById('wizardNextBtn');
  const submitBtn = document.getElementById('wizardSubmitBtn');
  const vehicleTypeSelect = form.querySelector('select[name="vehicle_type"]');
  const vehicleTypeOtherInput = document.getElementById('vehicleTypeOtherInput');
  const colorSelect = form.querySelector('select[name="color"]');
  const colorOtherInput = document.getElementById('colorOtherInput');
  let currentStep = 1;

  function syncOtherField(selectEl, otherInput) {
    if (!selectEl || !otherInput) return;
    const showOther = selectEl.value === 'Other';
    otherInput.classList.toggle('hidden', !showOther);
    otherInput.required = showOther;
    if (!showOther) {
      otherInput.value = '';
    }
  }

  function normalizeNameInput(el) {
    if (!el || !el.value) return;
    const normalized = el.value
      .trim()
      .toLowerCase()
      .replace(/\b([a-z])/g, (m) => m.toUpperCase());
    el.value = normalized;
  }

  function formatPlateInput(el) {
    if (!el || !el.value) return;
    el.value = el.value
      .toUpperCase()
      .replace(/[^A-Z0-9-]/g, '')
      .slice(0, 15);
  }

  function formatContactInput(el) {
    if (!el) return;
    el.value = el.value.replace(/\D/g, '').slice(0, 11);
  }

  function updateIndicators(step) {
    indicators.forEach((indicator) => {
      const n = Number(indicator.dataset.step || '0');
      if (n === step) {
        indicator.classList.remove('bg-gray-100', 'text-gray-500');
        indicator.classList.add('bg-blue-100', 'text-blue-700');
      } else {
        indicator.classList.remove('bg-blue-100', 'text-blue-700');
        indicator.classList.add('bg-gray-100', 'text-gray-500');
      }
    });
  }

  function showStep(step) {
    currentStep = step;
    panels.forEach((panel) => {
      const n = Number(panel.dataset.step || '0');
      panel.classList.toggle('hidden', n !== step);
    });

    updateIndicators(step);
    prevBtn.classList.toggle('hidden', step === 1);
    nextBtn.classList.toggle('hidden', step === panels.length);
    submitBtn.classList.toggle('hidden', step !== panels.length);
  }

  function validateCurrentStep() {
    const panel = panels.find((p) => Number(p.dataset.step || '0') === currentStep);
    if (!panel) return true;

    const requiredFields = Array.from(panel.querySelectorAll('[required]'));
    for (const field of requiredFields) {
      if (!field.value || !String(field.value).trim()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    return true;
  }

  prevBtn.addEventListener('click', function () {
    if (currentStep > 1) showStep(currentStep - 1);
  });

  nextBtn.addEventListener('click', function () {
    if (!validateCurrentStep()) return;
    if (currentStep < panels.length) showStep(currentStep + 1);
  });

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

  showStep(1);
})();
</script>
