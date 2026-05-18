<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';
require_once __DIR__ . '/../includes/common_utilities.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
    if (!is_dir($appSavePath)) { mkdir($appSavePath, 0700, true); }
    ini_set('session.save_path', $appSavePath);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

// Generate CSRF token using InputSanitizer
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = InputSanitizer::generateCsrf();
}
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

// formatContactNumber() now comes from common_utilities.php

// Debug classic upload handler removed for security — was allowing unauthenticated uploads

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');

  // Rate limiting check (3 registration attempts per hour per IP)
  $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $rateLimiter = new RateLimiter($pdo);
  $rateCheck = $rateLimiter->check($ipAddress, 'registration', 3, 60);
  
  if (!$rateCheck['allowed']) {
    $resetTs = strtotime((string)($rateCheck['reset_time'] ?? ''));
    $minutesLeft = $resetTs ? max(1, (int)ceil(($resetTs - time()) / 60)) : 60;
    echo json_encode([
      'success' => false, 
      'message' => "Too many registration attempts. Please try again in {$minutesLeft} minutes."
    ]);
    exit;
  }

  // Validate CSRF token using InputSanitizer
  $posted_csrf = InputSanitizer::post('csrf_token', 'string');
  if (!InputSanitizer::validateCsrf($posted_csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
  }

  // Get structured name fields using InputSanitizer
  $firstName = normalizeNamePart(InputSanitizer::post('first_name', 'string'));
  $middleName = normalizeNamePart(InputSanitizer::post('middle_name', 'string'));
  $lastName = normalizeNamePart(InputSanitizer::post('last_name', 'string'));
  $suffix = trim(InputSanitizer::post('suffix', 'string'));
  
  // Combine names for backward compatibility
  $fullName = trim("$firstName $middleName $lastName $suffix");
  
  $email = InputSanitizer::post('email', 'email');
  $contact = InputSanitizer::post('contact', 'string');
  $address = InputSanitizer::post('address', 'string');
  $vehicle_type = InputSanitizer::post('vehicle_type', 'string');
  $vehicle_type_other = InputSanitizer::post('vehicle_type_other', 'string');
  $color = InputSanitizer::post('color', 'string');
  $color_other = InputSanitizer::post('color_other', 'string');
  $plate_number = strtoupper(InputSanitizer::post('plate_number', 'string'));
  $password = InputSanitizer::post('password', 'string');
  $confirm_password = InputSanitizer::post('confirm_password', 'string');

  // Email is now the only login identifier for homeowner accounts.
  $loginIdentity = strtolower(trim((string)$email));
  
  // Validate required fields. Plate number is optional for E-bike
  $plateRequired = ($vehicle_type !== 'E-bike');
  if (empty($firstName) || empty($lastName) || empty($email) || empty($contact) || empty($address) || empty($vehicle_type) || empty($color) || ($plateRequired && empty($plate_number)) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled out.']);
    exit;
  }

  if (strlen($firstName) < 2 || strlen($firstName) > 50 || strlen($lastName) < 2 || strlen($lastName) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'First and last name must be 2-50 characters each.']);
    exit;
  }

  if (!empty($middleName) && strlen($middleName) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Middle name must not exceed 50 characters.']);
    exit;
  }

  if (!empty($suffix) && strlen($suffix) > 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Suffix must not exceed 10 characters.']);
    exit;
  }

  $allowedVehicleTypes = ['Sedan', 'SUV', 'Hatchback', 'Pickup', 'Van', 'Motorcycle', 'E-bike', 'Truck', 'Other'];
  if (!in_array($vehicle_type, $allowedVehicleTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle type.']);
    exit;
  }
  if ($vehicle_type === 'Other') {
    $vehicle_type = trim($vehicle_type_other);
    if ($vehicle_type === '' || strlen($vehicle_type) > 40) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle type (max 40 characters).']);
      exit;
    }
  }

  $allowedColors = ['Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Yellow', 'Orange', 'Other'];
  if (!in_array($color, $allowedColors, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle color.']);
    exit;
  }
  if ($color === 'Other') {
    $color = trim($color_other);
    if ($color === '' || strlen($color) > 30) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Please provide a valid custom vehicle color (max 30 characters).']);
      exit;
    }
  }
  
  // Validate email format
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
    exit;
  }
  
  // Format contact number (0912-345-6789)
  $contact = formatContactNumber($contact);
  if ($contact && !preg_match('/^\d{4}-\d{3}-\d{4}$/', $contact)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Contact number must be in format: 0912-345-6789']);
    exit;
  }

  $ownersDir = __DIR__ . '/../uploads/homeowners/';
  $vehiclesDir = __DIR__ . '/../uploads/vehicles/';

  $ownerImagePath = null;
  $vehicleImagePath = null;
  $uploadErrors = [];

  $saveFixedSizeImage = static function (string $tmpPath, string $destination, string $ext): bool {
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
  };

  // Load validation library
  require_once __DIR__ . '/../includes/input_validator.php';

  // Ensure directories exist
  foreach ([$ownersDir, $vehiclesDir] as $dir) {
    if (!is_dir($dir)) {
      if (!mkdir($dir, 0755, true)) {
        error_log("Failed to create directory: " . $dir);
        $uploadErrors[] = "Failed to create upload directory. Please contact administrator.";
      }
    }
    
    if (!is_writable($dir)) {
      error_log("Directory not writable: " . $dir);
      $uploadErrors[] = "Upload directory not writable. Please contact administrator.";
    }
  }

  if ($uploadErrors) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $uploadErrors)]);
    exit;
  }

  $saveFile = function (string $fieldName, bool $required = false) use ($ownersDir, $vehiclesDir, &$uploadErrors, $saveFixedSizeImage) {
    if (!isset($_FILES[$fieldName]) || (int)($_FILES[$fieldName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      if ($required) {
        $uploadErrors[] = ucfirst(str_replace('_', ' ', $fieldName)) . ' is required.';
      }
      return null;
    }

    $file = $_FILES[$fieldName];
    $fieldLabel = ucfirst(str_replace('_', ' ', $fieldName));

    if ($file['error'] !== UPLOAD_ERR_OK) {
      $uploadErrors[] = sprintf('%s upload failed (error code %d).', $fieldLabel, $file['error']);
      return null;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
      $uploadErrors[] = sprintf('%s upload failed (temp file missing).', $fieldLabel);
      return null;
    }

    $maxSize = 4 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
      $uploadErrors[] = sprintf('%s exceeds the 4 MB limit.', $fieldLabel);
      return null;
    }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    $detectedType = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) finfo_close($finfo);

    $typeToCheck = $detectedType ?: ($file['type'] ?? '');

    if ($typeToCheck && stripos($typeToCheck, 'image/') !== 0) {
      $uploadErrors[] = sprintf('%s must be an image file.', $fieldLabel);
      return null;
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!$ext && $typeToCheck) {
      $ext = str_replace('image/', '', $typeToCheck);
    }
    if (!$ext || strlen($ext) > 5) {
      $ext = 'png';
    }

    // Generate a unique filename
    $filename = date('Ymd_His_') . uniqid() . '.' . preg_replace('/[^a-z0-9]/i', '', $ext);
    
    // Determine the correct directory and path
    $uploadDir = $fieldName === 'owner_img' ? $ownersDir : $vehiclesDir;
    $destination = $uploadDir . $filename;
    $relativePath = $fieldName === 'owner_img' ? 'homeowners/' : 'vehicles/';

    if (!$saveFixedSizeImage($file['tmp_name'], $destination, $ext)) {
      $uploadErrors[] = sprintf('%s could not be saved. Error: %s', $fieldLabel, error_get_last()['message'] ?? 'Unknown error');
      return null;
    }

    return $relativePath . $filename;
  };

  $ownerImagePath = $saveFile('owner_img', true);
  $vehicleImagePath = $saveFile('car_img', true);

  if ($uploadErrors) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $uploadErrors)]);
    exit;
  }

  // Validate password
  if ($password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
  }

  // Registrator / homeowner password policy: minimum 8 chars and at least one symbol
  $passwordValidation = InputValidator::validatePassword($password, 8, true);
  if (!$passwordValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $passwordValidation['message']]);
    exit;
  }

  // Validate plate number
  $plateValidation = InputValidator::validatePlateNumber($plate_number);
  if (!$plateValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $plateValidation['message']]);
    exit;
  }
  $plate_number = $plateValidation['formatted']; // Use sanitized version

  // Prevent duplicate homeowner plate numbers during registration.
  $dupStmt = $pdo->prepare("SELECT id, name FROM homeowners WHERE plate_number = ? LIMIT 1");
  $dupStmt->execute([$plate_number]);
  $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
  if ($duplicate) {
    http_response_code(409);
    echo json_encode([
      'success' => false,
      'message' => 'Plate number already linked to homeowner: ' . ($duplicate['name'] ?? 'Unknown')
    ]);
    exit;
  }

  // Validate phone number
  $phoneValidation = InputValidator::validatePhoneNumber($contact);
  if (!$phoneValidation['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $phoneValidation['message']]);
    exit;
  }

  if ($fullName && $contact && $address && $vehicle_type && $color && $plate_number && $password) {
    try {
      // Hash the password (do this BEFORE transaction for better performance)
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      
      // Check if homeowner_auth table exists
      $tableExists = false;
      try {
        $pdo->query("SELECT 1 FROM homeowner_auth LIMIT 1");
        $tableExists = true;
      } catch (PDOException $e) {
        // Table doesn't exist yet
      }
      
      // If table doesn't exist, create it now
      if (!$tableExists) {
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS homeowner_auth (
            id INT AUTO_INCREMENT PRIMARY KEY,
            homeowner_id INT NOT NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            is_active TINYINT(1) DEFAULT 0,
            account_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            last_login DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (homeowner_id) REFERENCES homeowners(id) ON DELETE CASCADE,
            INDEX idx_username (username),
            INDEX idx_email (email)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
      }
      
      // Start transaction for data consistency
      $pdo->beginTransaction();

      // Insert into homeowners table (vehicle and owner info)
      // Note: using 'contact_number' not 'contact', 'email' already exists in table
      $stmt = $pdo->prepare("INSERT INTO homeowners (name, first_name, middle_name, last_name, suffix, email, contact_number, address, vehicle_type, color, plate_number, owner_img, car_img, account_status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
      $stmt->execute([
        $fullName,
        $firstName,
        $middleName,
        $lastName,
        $suffix,
        $email,
        $contact,
        $address,
        $vehicle_type,
        $color,
        $plate_number,
        $ownerImagePath,
        $vehicleImagePath
      ]);
      
      $homeowner_id = $pdo->lastInsertId();

      // Insert into homeowner_auth table (login credentials - inactive until approved)
      // Note: account_status column doesn't exist in homeowner_auth
      $authStmt = $pdo->prepare("INSERT INTO homeowner_auth (homeowner_id, username, password_hash, email, is_active, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
      $authStmt->execute([
        $homeowner_id,
        $loginIdentity,
        $password_hash,
        $email
      ]);

      // Insert the primary vehicle into vehicles table
      if (!empty($vehicle_type) && !empty($color) && !empty($plate_number)) {
          $vehStmt = $pdo->prepare("INSERT INTO vehicles (homeowner_id, vehicle_type, color, plate_number, is_primary, is_active, registered_at) VALUES (?, ?, ?, ?, 1, 1, NOW())");
          $vehStmt->execute([
              $homeowner_id,
              $vehicle_type,
              $color,
              $plate_number
          ]);
      }

      // Commit transaction
      $pdo->commit();

      // Reset rate limit on successful registration
      $rateLimiter->reset($ipAddress, 'registration');

      echo json_encode([
        'success' => true, 
        'message' => 'Registration successful! Your account is pending approval by the administrator. You will be notified via email once approved.',
        'status' => 'pending'
      ]);
    } catch (PDOException $e) {
      // Rollback transaction on error
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      
      // Check if it's a duplicate email/login identity
      if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode([
          'success' => false,
          'message' => 'Email already exists. Please use a different one.'
        ]);
      }
      // Check if it's a column not found error
      else if (strpos($e->getMessage(), 'Unknown column') !== false) {
        http_response_code(500);
        echo json_encode([
          'success' => false, 
          'message' => 'Database configuration error: Please contact the administrator to update the system.',
          'technical_details' => 'Run the migration script at: _testing/apply_homeowner_auth_migration.php'
        ]);
      } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again later.']);
      }
      
      error_log('REGISTRATION ERROR: ' . $e->getMessage());
      
      // Record failed attempt
      $rateLimiter->recordAttempt($ipAddress, 'registration', [
        'email' => $email ?? 'unknown',
        'error' => 'database_error'
      ]);
    } catch (Exception $e) {
      // Rollback transaction on error
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Unexpected error occurred. Please try again later.']);
      error_log('REGISTRATION ERROR: ' . $e->getMessage());
      
      // Record failed attempt
      $rateLimiter->recordAttempt($ipAddress, 'registration', [
        'email' => $email ?? 'unknown',
        'error' => 'exception'
      ]);
    }
  } else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    
    // Record failed attempt
    $rateLimiter->recordAttempt($ipAddress, 'registration', [
      'error' => 'validation_failed'
    ]);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Homeowner Registration — VehiScan</title>
<link rel="stylesheet" href="../assets/css/tailadmin-components.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/tailadmin-components.css'); ?>">
<link rel="stylesheet" href="../assets/css/registration.css">
<script src="../assets/js/libs/sweetalert2.all.min.js"></script>
</head>
<body>
  <!-- Background decorations -->
  <div class="bg-decoration bg-decoration-1"></div>
  <div class="bg-decoration bg-decoration-2"></div>

  <!-- Loading Overlay -->
  <div id="loadingOverlay" class="loading-overlay hidden">
    <div class="loading-card">
      <div class="spinner"></div>
      <h3 class="loading-title">Processing Registration</h3>
      <p class="loading-message" id="loadingMessage">Uploading images and saving data...</p>
      <div class="progress-bar-container">
        <div id="progressBar" class="progress-bar" style="width: 0%"></div>
      </div>
    </div>
  </div>

  <div class="registration-container ta-card">
    <div class="logo-container">
      <img src="../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="logo-image">
    </div>

    <h1>Create Your Account</h1>
    <p class="subtitle">Set up your homeowner account for seamless gate access.</p>

    <div class="wizard-steps" id="registrationWizardSteps" aria-label="Registration steps">
      <button type="button" class="wizard-step active" data-step="1" aria-current="step">
        <span class="wizard-step-index">1</span>
        <span class="wizard-step-label">Account Setup</span>
      </button>
      <button type="button" class="wizard-step" data-step="2">
        <span class="wizard-step-index">2</span>
        <span class="wizard-step-label">Vehicle Details</span>
      </button>
      <button type="button" class="wizard-step" data-step="3">
        <span class="wizard-step-index">3</span>
        <span class="wizard-step-label">Photo Upload</span>
      </button>
    </div>

    <div class="wizard-progress" aria-hidden="true">
      <div class="wizard-progress-bar" id="wizardProgressBar"></div>
    </div>

    <form id="registrationForm" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf ?? '') ?>">

      <!-- Homeowner Details -->
      <fieldset class="wizard-panel" data-step="1">
        <legend>Account Information</legend>
        <p class="fieldset-description">Your personal details and login credentials.</p>

        <div class="form-row">
          <div class="form-group" style="flex: 2;">
            <label class="form-label">
              First Name <span class="required">*</span>
            </label>
            <input
              type="text"
              name="first_name"
              id="firstName"
              placeholder="e.g., Juan"
              required
              minlength="2"
              maxlength="50"
            >
          </div>
          
          <div class="form-group" style="flex: 1;">
            <label class="form-label">
              Middle Name
            </label>
            <input
              type="text"
              name="middle_name"
              id="middleName"
              placeholder="e.g., Santos"
              maxlength="50"
            >
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group" style="flex: 2;">
            <label class="form-label">
              Last Name <span class="required">*</span>
            </label>
            <input
              type="text"
              name="last_name"
              id="lastName"
              placeholder="e.g., Dela Cruz"
              required
              minlength="2"
              maxlength="50"
            >
          </div>
          
          <div class="form-group" style="flex: 1;">
            <label class="form-label">
              Suffix
            </label>
            <input
              type="text"
              name="suffix"
              id="suffix"
              placeholder="Jr., Sr., III"
              maxlength="10"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Email Address <span class="required">*</span>
          </label>
          <input
            type="email"
            name="email"
            id="email"
            placeholder="juan.delacruz@email.com"
            required
            maxlength="100"
          >
          <p class="form-hint">For account notifications and password recovery</p>
        </div>

        <div class="form-group">
          <label class="form-label">
            Password <span class="required">*</span>
          </label>
          <div class="password-wrapper">
            <input
              type="password"
              name="password"
              id="passwordInput"
              placeholder="Create a secure password"
              required
              minlength="8"
              maxlength="100"
              pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
              title="Must be at least 8 characters and include a symbol"
            >
            <button type="button" class="toggle-password" data-target="passwordInput" aria-label="Toggle password visibility" tabindex="-1">
              <span class="eye-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
            </button>
          </div>
          <p class="form-hint">At least 8 characters including a symbol</p>
        </div>

        <div class="form-group">
          <label class="form-label">
            Confirm Password <span class="required">*</span>
          </label>
          <div class="password-wrapper">
            <input
              type="password"
              name="confirm_password"
              id="confirmPasswordInput"
              placeholder="Re-enter password"
              required
              minlength="8"
              maxlength="100"
            >
            <button type="button" class="toggle-password" data-target="confirmPasswordInput" aria-label="Toggle confirm password visibility" tabindex="-1">
              <span class="eye-icon"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></span>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Contact Number <span class="required">*</span>
          </label>
          <input
            type="tel"
            name="contact"
            id="contact"
            placeholder="e.g., 0912-345-6789"
            required
            pattern="^\d{4}-\d{3}-\d{4}$"
            title="Format: 0912-345-6789"
          >
          <p class="form-hint">Will auto-format to: 0912-345-6789</p>
        </div>

        <div class="form-group">
          <label class="form-label">
            Home Address <span class="required">*</span>
          </label>
          <textarea
            name="address"
            placeholder="e.g., Block 10 Lot 5, Maple Street, Greenwood Subdivision"
            required
          ></textarea>
        </div>
      </fieldset>

      <!-- Vehicle Details -->
      <fieldset class="wizard-panel" data-step="2" hidden>
        <legend>Vehicle Information</legend>
        <p class="fieldset-description">Register your primary vehicle for automated gate entry.</p>

        <div class="grid-cols-2">
          <div class="form-group">
            <label class="form-label">
              Vehicle Type <span class="required">*</span>
            </label>
            <select
              name="vehicle_type"
              id="vehicleTypeInput"
              required
            >
              <option value="">Choose vehicle type</option>
              <option value="Sedan">Sedan</option>
              <option value="Truck">Truck</option>
              <option value="SUV">SUV</option>
              <option value="Hatchback">Hatchback</option>
              <option value="Pickup">Pickup</option>
              <option value="Van">Van</option>
              <option value="Motorcycle">Motorcycle</option>
              <option value="E-bike">E-bike</option>
              <option value="Other">Other</option>
            </select>
            <input
              type="text"
              name="vehicle_type_other"
              id="vehicleTypeOtherInput"
              placeholder="Enter vehicle type"
              maxlength="30"
              style="display:none; margin-top: 0.5rem;"
            >
          </div>

          <div class="form-group">
            <label class="form-label">
              Vehicle Color <span class="required">*</span>
            </label>
            <select
              name="color"
              id="colorInput"
              required
            >
              <option value="">Choose vehicle color</option>
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
            <input
              type="text"
              name="color_other"
              id="colorOtherInput"
              placeholder="Enter vehicle color"
              maxlength="30"
              style="display:none; margin-top: 0.5rem;"
            >
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            License Plate Number <span class="required">*</span>
          </label>
          <input
            type="text"
            name="plate_number"
            id="plateInput"
            placeholder="e.g., ABC-1234"
            required
            maxlength="7"
            pattern="[A-Z0-9\-]{3,7}"
            title="Plate number should be 3-7 characters (letters, numbers, hyphens)"
            style="text-transform: uppercase;"
            autocomplete="off"
          >
          <p class="form-hint" id="plateHint">Required for automated gate recognition (optional for E-bike)</p>
        </div>
      </fieldset>

      <!-- Photos -->
      <fieldset class="wizard-panel" data-step="3" hidden>
        <legend>Upload Photos</legend>
        <p class="fieldset-description">Clear photos enable quick verification at the gate.</p>

        <div class="form-group">
          <label class="form-label">
            Your Photo <span class="required">*</span>
          </label>
          <div class="upload-box" id="ownerUploadBox" data-for="owner_img">
            <div class="upload-icon"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
            <div class="upload-text">
              <p class="upload-title">Click to upload or drag and drop</p>
              <p class="upload-subtitle">JPG, PNG, or WEBP (max 4MB)</p>
            </div>
            <input
              type="file"
              name="owner_img"
              id="ownerImgInput"
              accept="image/*"
              required
              class="upload-input"
            >
            <div class="upload-preview" id="ownerPreview" style="display: none;">
              <img id="ownerPreviewImg" alt="Owner preview">
              <button type="button" class="preview-remove" data-for="owner_img"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="upload-actions">
              <button type="button" class="camera-btn" data-for="owner_img"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Use Camera</button>
              <button type="button" class="gallery-btn" data-for="owner_img"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Choose from Gallery</button>
            </div>
            <div class="note" id="ownerImgLabel">Owner photo is required for verification</div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">
            Vehicle Photo <span class="required">*</span>
          </label>
          <div class="upload-box" id="carUploadBox" data-for="car_img">
            <div class="upload-icon"><svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div>
            <div class="upload-text">
              <p class="upload-title">Click to upload or drag and drop</p>
              <p class="upload-subtitle">JPG, PNG, or WEBP (max 4MB)</p>
            </div>
            <input
              type="file"
              name="car_img"
              id="carImgInput"
              accept="image/*"
              required
              class="upload-input"
            >
            <div class="upload-preview" id="carPreview" style="display: none;">
              <img id="carPreviewImg" alt="Vehicle preview">
              <button type="button" class="preview-remove" data-for="car_img"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="upload-actions">
              <button type="button" class="camera-btn" data-for="car_img"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg> Use Camera</button>
              <button type="button" class="gallery-btn" data-for="car_img"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Choose from Gallery</button>
            </div>
            <div class="note" id="carImgLabel">Vehicle photo is required for verification</div>
          </div>
        </div>
      </fieldset>

      <div class="wizard-actions">
        <button type="button" class="btn-secondary ta-btn ta-btn-secondary" id="wizardPrevBtn" hidden>Back</button>
        <button type="button" class="btn-secondary ta-btn ta-btn-secondary" id="wizardNextBtn">Next</button>
      </div>

      <button type="submit" class="btn-primary ta-btn ta-btn-primary" id="submitBtn" hidden>
        <span class="btn-text">Create Account</span>
        <span class="btn-loading" style="display: none;">
          <span class="btn-spinner"></span>
          <span>Processing...</span>
        </span>
      </button>

      <div class="keyboard-hint">
        Press <span class="hint-key">Ctrl</span> + <span class="hint-key">Enter</span> to <span id="keyboardHintAction">continue</span>
      </div>

      <div class="info-box ta-alert ta-alert-info">
        <p class="info-box-text">
          <strong>Note:</strong> All fields marked with <span class="required">*</span> are required. Your license plate number will be automatically converted to uppercase for consistency.
        </p>
      </div>
    </form>

    <div class="divider"><span>or</span></div>

    <p class="signup-link">Already have an account? <a href="../auth/login.php">Sign in here</a></p>
  </div>

  <!-- Local Scripts -->
  <!-- SweetAlert2 fallback: Create window.Swal object if not loaded from CDN -->
  <script>
    if (typeof Swal === 'undefined') {
      window.Swal = {
        fire: function(options) {
          const isConfirm = options.showCancelButton || options.showConfirmButton !== false;
          const message = options.html || options.text || options.title || '';
          if (isConfirm) {
            return Promise.resolve({ isConfirmed: confirm(message), value: true });
          } else {
            alert(options.icon ? options.icon.toUpperCase() + ': ' + message : message);
            return Promise.resolve({ isConfirmed: true });
          }
        }
      };
      console.warn('SweetAlert2 not loaded, using fallback alert/confirm');
    }

    // Password toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      const toggleButtons = document.querySelectorAll('.toggle-password');
      
      toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
          const targetId = this.getAttribute('data-target');
          const input = document.getElementById(targetId);
          const eyeIcon = this.querySelector('.eye-icon');
          
          if (input) {
            if (input.type === 'password') {
              input.type = 'text';
              eyeIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
              this.setAttribute('aria-label', 'Hide password');
            } else {
              input.type = 'password';
              eyeIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
              this.setAttribute('aria-label', 'Show password');
            }
          }
        });
      });
      
      // Auto-format contact number to 0912-345-6789
      const contactInput = document.getElementById('contact');
      if (contactInput) {
        contactInput.addEventListener('input', function(e) {
          let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
          
          // Limit to 11 digits (Philippine mobile number)
          if (value.length > 11) {
            value = value.slice(0, 11);
          }
          
          // Format as 0912-345-6789
          if (value.length >= 4) {
            value = value.slice(0, 4) + '-' + value.slice(4);
          }
          if (value.length >= 8) {
            value = value.slice(0, 8) + '-' + value.slice(8);
          }
          
          e.target.value = value;
        });
        
        // Validate on blur
        contactInput.addEventListener('blur', function(e) {
          const pattern = /^\d{4}-\d{3}-\d{4}$/;
          if (e.target.value && !pattern.test(e.target.value)) {
            e.target.setCustomValidity('Contact number must be in format: 0912-345-6789');
            e.target.reportValidity();
          } else {
            e.target.setCustomValidity('');
          }
        });
      }
    });
  </script>
  <script src="../assets/js/registration.js"></script>
</body>
</html>