<?php
// admin/api/visitor_pass_form.php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/input_validator.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
require_once __DIR__ . '/qr_helper.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (!in_array($method, ['GET', 'POST'], true)) {
  http_response_code(405);
  header('Allow: GET, POST');
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Method not allowed']);
  exit;
}

// Authorization check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = InputSanitizer::generateCsrf();
$csrf = $_SESSION['csrf_token'];

AuditLogger::init($pdo);

// POST create/edit (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $posted = InputSanitizer::post('csrf_token', 'string');
    if (!InputSanitizer::validateCsrf($posted)) {
    http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
    }
    
    // Sanitize all inputs
    $pass_id = InputSanitizer::post('id', 'int', 0);
    $homeowner_id = InputSanitizer::post('homeowner_id', 'int');
    $visitor_name = strtoupper(InputSanitizer::post('visitor_name', 'string'));
    $visitor_plate = strtoupper(InputSanitizer::post('visitor_plate', 'string'));
    $purpose = InputSanitizer::post('purpose', 'string');
    $valid_from = InputSanitizer::post('valid_from', 'string');
    $valid_until = InputSanitizer::post('valid_until', 'string');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $status = InputSanitizer::post('status', 'string', 'pending');
    $allowedStatuses = ['pending', 'cancelled', 'rejected', 'expired'];

    if (!$homeowner_id || !$visitor_name || !$visitor_plate || !$purpose || !$valid_from) {
        http_response_code(400);
      echo json_encode(['success'=>false,'message'=>'All required fields must be filled']); exit;
    }

    if (!in_array($status, $allowedStatuses, true)) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Invalid status selection']);
      exit;
    }

    $fromTs = strtotime($valid_from);
    if ($fromTs === false) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Invalid date format']);
      exit;
    }

    if (!empty($valid_until)) {
      $submittedUntil = strtotime($valid_until);
      if ($submittedUntil === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid end date format']);
        exit;
      }
      if (date('Y-m-d', $submittedUntil) !== date('Y-m-d', $fromTs)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Visitor passes must expire on the same day they start']);
        exit;
      }
    }

    $untilTs = strtotime(date('Y-m-d', $fromTs) . ' 23:59:59');
    if ($untilTs === false) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Unable to determine expiration time']);
      exit;
    }

    $durationMinutes = ($untilTs - $fromTs) / 60;
    if ($durationMinutes < 30) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => 'Visit duration must be at least 30 minutes']);
      exit;
    }

    $plateValidation = InputValidator::validatePlateNumber($visitor_plate);
    if (!$plateValidation['valid']) {
      http_response_code(400);
      echo json_encode(['success' => false, 'message' => $plateValidation['message']]);
      exit;
    }
    $visitor_plate = $plateValidation['formatted'];
    
    try {
      $ownerStmt = $pdo->prepare("SELECT account_status FROM homeowners WHERE id = ? LIMIT 1");
      $ownerStmt->execute([$homeowner_id]);
      $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
      if (!$owner || ($owner['account_status'] ?? '') !== 'approved') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Selected homeowner is missing or not approved']);
        exit;
      }

      if ($pass_id > 0) {
        $existingPassStmt = $pdo->prepare("SELECT homeowner_id, visitor_name, visitor_plate, purpose, valid_from, valid_until, is_recurring, status FROM visitor_passes WHERE id = ? LIMIT 1");
        $existingPassStmt->execute([$pass_id]);
        $existingPass = $existingPassStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("UPDATE visitor_passes SET homeowner_id = ?, visitor_name = ?, visitor_plate = ?, purpose = ?, valid_from = ?, valid_until = ?, is_recurring = ?, status = ? WHERE id = ?");
        $stmt->execute([$homeowner_id, $visitor_name, $visitor_plate, $purpose, $valid_from, date('Y-m-d H:i:s', $untilTs), $is_recurring, $status, $pass_id]);
        if ($stmt->rowCount() !== 1) {
          http_response_code(404);
          echo json_encode(['success' => false, 'message' => 'Visitor pass not found or unchanged']);
          exit;
        }

        try {
          AuditLogger::logDataChange('visitor_pass_update', 'visitor_passes', (int)$pass_id, $existingPass, [
            'homeowner_id' => $homeowner_id,
            'visitor_name' => $visitor_name,
            'visitor_plate' => $visitor_plate,
            'purpose' => $purpose,
            'valid_from' => $valid_from,
            'valid_until' => date('Y-m-d H:i:s', $untilTs),
            'is_recurring' => (int)$is_recurring,
            'status' => $status,
          ]);
        } catch (Exception $auditError) {
          error_log('Visitor pass update audit error: ' . $auditError->getMessage());
        }

        echo json_encode(['success'=>true,'message'=>'Visitor pass updated successfully']);
        exit;
      }

      // Generate unique QR token
      $qrToken = generateSecureToken();

      // Insert with QR token
      $stmt = $pdo->prepare("INSERT INTO visitor_passes (homeowner_id, visitor_name, visitor_plate, purpose, valid_from, valid_until, is_recurring, status, qr_token) VALUES (?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$homeowner_id, $visitor_name, $visitor_plate, $purpose, $valid_from, date('Y-m-d H:i:s', $untilTs), $is_recurring, 'pending', $qrToken]);

      $passId = $pdo->lastInsertId();

      // Generate QR code
      $qrCode = generateVisitorPassQR($passId, $qrToken, $pdo);

      if ($qrCode) {
        // Update with QR code
        $stmt = $pdo->prepare("UPDATE visitor_passes SET qr_code = ? WHERE id = ?");
        $stmt->execute([$qrCode, $passId]);
      }

      try {
        AuditLogger::logDataChange('visitor_pass_created', 'visitor_passes', (int)$passId, null, [
          'homeowner_id' => $homeowner_id,
          'visitor_name' => $visitor_name,
          'visitor_plate' => $visitor_plate,
          'purpose' => $purpose,
          'valid_from' => $valid_from,
          'valid_until' => date('Y-m-d H:i:s', $untilTs),
          'is_recurring' => (int)$is_recurring,
          'status' => 'pending',
          'qr_code' => !empty($qrCode),
        ]);
      } catch (Exception $auditError) {
        error_log('Visitor pass create audit error: ' . $auditError->getMessage());
      }

      echo json_encode(['success'=>true,'message'=>'Visitor pass created successfully with QR code']);
    } catch (Exception $e) {
        error_log("Visitor pass creation error: " . $e->getMessage());
      http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Failed to create visitor pass. Please try again later.']);
    }
    exit;
}

// GET => render form fragment
$homeowners = $pdo->query("SELECT id, name FROM homeowners WHERE account_status = 'approved' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$editPass = null;
$editId = InputSanitizer::get('id', 'int', 0);
if ($editId > 0) {
    $passStmt = $pdo->prepare("SELECT * FROM visitor_passes WHERE id = ? LIMIT 1");
    $passStmt->execute([$editId]);
    $editPass = $passStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Set default times (now and end of the same day)
$defaultFrom = $editPass ? date('Y-m-d\TH:i', strtotime($editPass['valid_from'])) : date('Y-m-d\TH:i');
$defaultUntil = $editPass ? date('Y-m-d\TH:i', strtotime($editPass['valid_until'])) : date('Y-m-d\TH:i', strtotime('today 23:59:59'));
$formTitle = $editPass ? 'Edit Visitor Pass' : 'Create Visitor Pass';
$submitLabel = $editPass ? 'Update Pass' : 'Create Pass';
?>
<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
  #editModal .visitor-pass-modal-form {
    max-width: 100%;
  }
  #editModal .visitor-pass-modal-form .ta-input,
  #editModal .visitor-pass-modal-form .ta-select {
    min-height: 44px;
  }
  #editModal .visitor-pass-modal-form textarea.ta-input {
    min-height: 92px;
    line-height: 1.45;
  }
  .visitor-pass-modal-form {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
  }
  .visitor-pass-modal-form .vp-header-meta {
    margin-top: -0.35rem;
    margin-bottom: 0.55rem;
    color: #64748b;
    font-size: 0.82rem;
  }
  .visitor-pass-modal-form .vp-section {
    border: 1px solid #e2e8f0;
    border-radius: 0.9rem;
    background: #f8fafc;
    padding: 1.1rem;
  }
  .dark .visitor-pass-modal-form .vp-section {
    border-color: #334155;
    background: rgba(15, 23, 42, 0.45);
  }
  .visitor-pass-modal-form .ta-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }
  .visitor-pass-modal-form .field-wrap {
    min-width: 0;
  }
  .visitor-pass-modal-form .field-span-2 {
    grid-column: span 2;
  }
  .visitor-pass-modal-form .form-actions {
    border-top: 1px solid var(--ta-card-border);
    background: linear-gradient(to top, rgba(248, 250, 252, 0.98), rgba(248, 250, 252, 0.78));
    padding-top: 1rem;
    padding-inline: 0.1rem;
    padding-bottom: 0.2rem;
    margin-top: 1.2rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.7rem;
    flex-wrap: wrap;
  }
  .visitor-pass-modal-form .form-actions .ta-btn {
    min-width: 120px;
  }
  .dark .visitor-pass-modal-form .form-actions {
    background: linear-gradient(to top, rgba(15, 23, 42, 0.98), rgba(15, 23, 42, 0.7));
  }
  .visitor-pass-modal-form .quick-duration-wrap {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 0.2rem;
  }
  .visitor-pass-modal-form .quick-duration {
    min-height: 34px;
    padding-inline: 0.8rem;
  }
  .visitor-pass-modal-form .quick-duration.is-active {
    background: #2563eb;
    color: #fff;
    border-color: #1d4ed8;
  }
  .visitor-pass-modal-form .ta-help {
    display: block;
    margin-top: 0.35rem;
    font-size: 0.76rem;
    color: #64748b;
  }
  .visitor-pass-modal-form .ta-label {
    margin-bottom: 0.35rem;
    display: inline-block;
  }
  .visitor-pass-modal-form .vp-inline-block {
    margin-top: 0.2rem;
  }

  .visitor-pass-modal-form .field-span-2 + .vp-inline-block {
    margin-top: 0.65rem;
  }

  .visitor-pass-modal-form .vp-section + .vp-inline-block {
    margin-top: 0.75rem;
  }
  .dark .visitor-pass-modal-form .ta-help {
    color: #94a3b8;
  }
  @media (max-width: 1280px) {
    .visitor-pass-modal-form .ta-grid-2 {
      grid-template-columns: 1fr;
    }
    .visitor-pass-modal-form .field-span-2 {
      grid-column: span 1;
    }
    .visitor-pass-modal-form .form-actions .ta-btn {
      flex: 1 1 100%;
      min-width: 0;
    }
  }
</style>
<form id="visitorPassForm" class="space-y-6 modern-form compact-form visitor-pass-modal-form" action="api/visitor_pass_form.php" method="post">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
  <input type="hidden" name="id" value="<?php echo (int)($editPass['id'] ?? 0); ?>">
  <h3 class="text-2xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200 flex items-center gap-2 form-title">
    <span class="text-3xl"><svg style="width:1em;height:1em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v1.5a1.5 1.5 0 1 0 0 3V16a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1.5a1.5 1.5 0 1 0 0-3V9z"/></svg></span>
    <span><?php echo htmlspecialchars($formTitle); ?></span>
  </h3>
  <p class="vp-header-meta">Set a clear schedule and homeowner link so guards can verify entry quickly at the gate.</p>
  
  <div class="vp-section">
  <div class="ta-grid-2">
    <label class="field-wrap field-span-2">
      <span class="ta-label">Linked Homeowner <span class="text-red-500">*</span></span>
      <select name="homeowner_id" class="ta-select w-full">
        <option value="">-- Select Approved Homeowner --</option>
        <?php foreach ($homeowners as $h): ?>
          <option value="<?php echo $h['id']; ?>" <?php echo ((int)$h['id'] === (int)($editPass['homeowner_id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['name'] ?? ''); ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    
    <label class="field-wrap">
      <span class="ta-label">Visitor Name <span class="text-red-500">*</span></span>
      <input type="text" id="visitor_name" name="visitor_name" required placeholder="JOHN DOE" autocomplete="off" value="<?php echo htmlspecialchars($editPass['visitor_name'] ?? ''); ?>" class="ta-input uppercase">
    </label>
    
    <label class="field-wrap">
      <span class="ta-label">Vehicle Plate <span class="text-red-500">*</span></span>
      <input type="text" id="visitor_plate" name="visitor_plate" required maxlength="15" placeholder="ABC-1234" autocomplete="off" value="<?php echo htmlspecialchars($editPass['visitor_plate'] ?? ''); ?>" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9- ]/g, '').slice(0, 15)" class="ta-input uppercase">
    </label>
    
    <label class="field-wrap field-span-2">
      <span class="ta-label">Purpose of Visit <span class="text-red-500">*</span></span>
      <textarea name="purpose" required placeholder="Guest, Delivery, Maintenance, etc." rows="2" class="ta-input resize-y"><?php echo htmlspecialchars($editPass['purpose'] ?? ''); ?></textarea>
      <small class="ta-help">Example: Delivery - parcel drop-off to Block 13 Lot 42.</small>
    </label>
    
    <label class="field-wrap">
      <span class="ta-label">Valid From <span class="text-red-500">*</span></span>
      <input type="datetime-local" id="valid_from" name="valid_from" required value="<?php echo $defaultFrom; ?>" class="ta-input">
      <small class="ta-help">Start time of visitor access window.</small>
    </label>
    
    <div class="field-span-2">
      <div class="ta-label mb-2">Validity</div>
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-900/20 dark:text-emerald-200">
        Visitor passes are valid for the selected day only and will expire automatically at 11:59 PM.
      </div>
      <input type="hidden" id="valid_until" name="valid_until" value="<?php echo $defaultUntil; ?>">
    </div>
  </div>
  </div>
    
    <label class="flex items-center gap-2 field-span-2 cursor-pointer vp-section vp-inline-block" style="padding:0.8rem 0.9rem;">
      <input type="checkbox" name="is_recurring" value="1" <?php echo !empty($editPass['is_recurring']) ? 'checked' : ''; ?> class="w-4 h-4 text-blue-500 border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
      <span class="text-sm text-gray-700">Recurring Pass (Auto-renew daily/weekly for regular visitors)</span>
    </label>

    <label class="field-wrap field-span-2 vp-inline-block">
      <span class="ta-label">Status</span>
      <select name="status" class="ta-select w-full">
        <?php $currentStatus = $editPass['status'] ?? 'pending'; ?>
        <option value="pending" <?php echo $currentStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="cancelled" <?php echo $currentStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
        <option value="rejected" <?php echo $currentStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
        <option value="expired" <?php echo $currentStatus === 'expired' ? 'selected' : ''; ?>>Expired</option>
      </select>
    </label>
  </div>
  
  <div class="form-actions">
    <button type="button" class="ta-btn ta-btn-secondary cancel-btn">Cancel</button>
    <button type="submit" class="ta-btn ta-btn-primary"><?php echo htmlspecialchars($submitLabel); ?></button>
  </div>
</form>
<script src="../../assets/js/admin/visitor-pass-form.js?v=<?php echo safeFileTime(__DIR__ . '/../../assets/js/admin/visitor-pass-form.js'); ?>"></script>
