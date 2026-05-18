<?php
/**
 * Setup Script: Create Homeowner Portal Accounts
 * Run this once to create login credentials for existing homeowners
 */

require_once __DIR__ . '/../db.php';

// Default password for all accounts (users should change this)
$default_password = 'homeowner123';
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);

try {
    // Get all homeowners without auth accounts
    $stmt = $pdo->query("
        SELECT h.id, h.name, h.address, h.contact_number
        FROM homeowners h
        LEFT JOIN homeowner_auth ha ON h.id = ha.homeowner_id
        WHERE ha.id IS NULL
        ORDER BY h.id
    ");
    
    $homeowners = $stmt->fetchAll();
    
    if (empty($homeowners)) {
        echo "[OK] All homeowners already have accounts!\n";
        exit(0);
    }
    
    echo "Found " . count($homeowners) . " homeowners without accounts.\n\n";
    
    $created = 0;
    foreach ($homeowners as $h) {
        // Generate username from name (lowercase, no spaces)
        $username = strtolower(str_replace(' ', '_', trim($h['name'])));
        // Make unique if needed
        $base_username = $username;
        $counter = 1;
        
        while (true) {
            $check = $pdo->prepare("SELECT id FROM homeowner_auth WHERE username = ?");
            $check->execute([$username]);
            if (!$check->fetch()) break;
            $username = $base_username . $counter;
            $counter++;
        }
        
        // Generate email
        $email = $username . '@vehiscan.local';
        
        // Create account
        $stmt = $pdo->prepare("
            INSERT INTO homeowner_auth (homeowner_id, username, password_hash, email, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([$h['id'], $username, $password_hash, $email]);
        
        echo "[OK] Created account for: {$h['name']}\n";
        echo "  Username: $username\n";
        echo "  Password: $default_password\n";
        echo "  Address: {$h['address']}\n\n";
        
        $created++;
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "SUCCESS: Created $created homeowner accounts!\n";
    echo str_repeat("=", 60) . "\n\n";
    $appUrl = getAppUrl();
    echo "Access Portal: " . $appUrl . "/auth/login.php\n";
    echo "Default Password: $default_password\n";
    echo "\nIMPORTANT: Users should change their passwords after first login!\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
