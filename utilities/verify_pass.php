<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/common_utilities.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request');
}

try {
    $stmt = $pdo->prepare("
        SELECT vp.*, h.name as homeowner_name, h.address, h.contact_number
        FROM visitor_passes vp
        JOIN homeowners h ON vp.homeowner_id = h.id
        WHERE vp.qr_token = ?
    ");
    $stmt->execute([$token]);
    $pass = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pass) {
        $error = 'Invalid or expired visitor pass';
    } else {
        // Check if pass is still valid
        $now = new DateTime();
        $validFrom = new DateTime($pass['valid_from']);
        $validUntil = new DateTime($pass['valid_until']);
        
        $isExpired = $now > $validUntil;
        $notYetValid = $now < $validFrom;
        $isActive = $pass['status'] === 'active';
    }
    
} catch (PDOException $e) {
    error_log("Verify pass error: " . $e->getMessage());
    $error = 'System error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Visitor Pass Verification — VehiScan</title>
  <link rel="stylesheet" href="../assets/css/tailwind.css">
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    .card {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
      max-width: 500px;
      width: 100%;
      overflow: hidden;
    }
    .valid-badge {
      background: #d5f4e6;
      color: #27ae60;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .invalid-badge {
      background: #fadbd8;
      color: #e74c3c;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    .warning-badge {
      background: #ffeaa7;
      color: #f39c12;
      padding: 0.5rem 1rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 700;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
  </style>
</head>
<body>
  <div class="card">
    <!-- Header -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-6 text-center">
      <div class="mb-2">
        <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
      </div>
      <h1 class="text-2xl font-bold">Visitor Pass Verification</h1>
      <p class="text-purple-100 text-sm mt-1">VehiScan RFID System</p>
    </div>

    <!-- Content -->
    <div class="p-6">
      <?php if (isset($error)): ?>
        <div class="text-center py-8">
          <svg class="w-20 h-20 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
          </svg>
          <h2 class="text-2xl font-bold text-gray-800 mb-2">Invalid Pass</h2>
          <p class="text-gray-600"><?php echo htmlspecialchars($error ?? ''); ?></p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <!-- Status Badge -->
          <div class="text-center mb-4">
            <?php if ($isActive && !$isExpired && !$notYetValid): ?>
              <span class="valid-badge">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Valid Pass
              </span>
            <?php elseif ($isExpired): ?>
              <span class="invalid-badge">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                Expired
              </span>
            <?php elseif ($notYetValid): ?>
              <span class="warning-badge">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                Not Yet Valid
              </span>
            <?php else: ?>
              <span class="invalid-badge">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo ucfirst($pass['status']); ?>
              </span>
            <?php endif; ?>
          </div>

          <!-- Visitor Information -->
          <div class="bg-gray-50 rounded-lg p-4 space-y-3">
            <div>
              <p class="text-sm text-gray-500 font-semibold">Visitor Name</p>
              <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($pass['visitor_name'] ?? ''); ?></p>
            </div>
            
            <div>
              <p class="text-sm text-gray-500 font-semibold">Vehicle Plate</p>
              <p class="text-lg font-bold text-gray-900 font-mono"><?php echo htmlspecialchars($pass['visitor_plate'] ?? ''); ?></p>
            </div>
            
            <div>
              <p class="text-sm text-gray-500 font-semibold">Visiting</p>
              <p class="text-base font-semibold text-gray-900"><?php echo htmlspecialchars($pass['homeowner_name'] ?? ''); ?></p>
              <?php if ($pass['address']): ?>
                <p class="text-sm text-gray-600"><?php echo htmlspecialchars($pass['address'] ?? ''); ?></p>
              <?php endif; ?>
            </div>
            
            <?php if ($pass['purpose']): ?>
            <div>
              <p class="text-sm text-gray-500 font-semibold">Purpose</p>
              <p class="text-base text-gray-900"><?php echo htmlspecialchars($pass['purpose'] ?? ''); ?></p>
            </div>
            <?php endif; ?>
          </div>

          <!-- Validity Period -->
          <div class="bg-blue-50 rounded-lg p-4">
            <p class="text-sm font-semibold text-blue-900 mb-2"><svg class="w-4 h-4 inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Validity Period</p>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-blue-600 font-semibold">From</p>
                <p class="text-blue-900 font-bold"><?php echo formatDisplayDateTime($pass['valid_from'], false); ?></p>
                <p class="text-blue-700"><?php echo date('g:i A', strtotime($pass['valid_from'])); ?></p>
              </div>
              <div>
                <p class="text-blue-600 font-semibold">Until</p>
                <p class="text-blue-900 font-bold"><?php echo formatDisplayDateTime($pass['valid_until'], false); ?></p>
                <p class="text-blue-700"><?php echo date('g:i A', strtotime($pass['valid_until'])); ?></p>
              </div>
            </div>
          </div>

          <!-- Pass ID -->
          <div class="text-center pt-4 border-t">
            <p class="text-xs text-gray-500">Pass ID: #<?php echo $pass['id']; ?></p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
