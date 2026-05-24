<?php
/**
 * Migration Script: Add homeowner authentication fields
 * Run this once to add username and password_hash to homeowners table
 */

require_once __DIR__ . '/../db.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Database Migration - Homeowner Auth</title>\n<style>\nbody { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }\n.success { color: #10b981; background: #d1fae5; padding: 15px; border-radius: 8px; margin: 10px 0; }\n.error { color: #ef4444; background: #fee2e2; padding: 15px; border-radius: 8px; margin: 10px 0; }\n.info { color: #3b82f6; background: #dbeafe; padding: 15px; border-radius: 8px; margin: 10px 0; }\ncode { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; }\n</style>\n</head>\n<body>\n";

echo "<h1><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2'/></svg> Database Migration: Homeowner Authentication</h1>\n";
echo "<p>This migration adds <code>username</code> and <code>password_hash</code> columns to the homeowners table.</p>\n";

try {
    // Check if columns already exist
    $checkStmt = $pdo->query("SHOW COLUMNS FROM `homeowners` LIKE 'username'");
    if ($checkStmt->rowCount() > 0) {
        echo "<div class='info'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='M12 16v-4M12 8h.01'/></svg> Migration already applied. Username and password columns exist.</div>";
        
        // Show existing homeowners
        $homeowners = $pdo->query("SELECT id, name, username FROM homeowners ORDER BY id")->fetchAll();
        if ($homeowners) {
            echo "<h3>Existing Homeowners:</h3>";
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Username</th></tr>";
            foreach ($homeowners as $h) {
                echo "<tr><td>{$h['id']}</td><td>" . htmlspecialchars($h['name']) . "</td><td>" . htmlspecialchars($h['username']) . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='info'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><polyline points='12 6 12 12 16.5 12'/></svg> Starting migration...</div>\n";
        
        // Read and execute migration
        $sql = file_get_contents(__DIR__ . '/004_add_homeowner_auth.sql');
        
        // Split by semicolons and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            // Skip comments and empty statements
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                // Extract table name or action from statement
                if (preg_match('/ALTER TABLE.*?`(\w+)`/i', $statement, $matches)) {
                    echo "<div class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Modified table: {$matches[1]}</div>\n";
                } elseif (preg_match('/CREATE INDEX.*?ON.*?`(\w+)`/i', $statement, $matches)) {
                    echo "<div class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Created index on: {$matches[1]}</div>\n";
                } elseif (preg_match('/UPDATE.*?`(\w+)`/i', $statement, $matches)) {
                    echo "<div class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Updated records in: {$matches[1]}</div>\n";
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "<div class='info'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='M12 16v-4M12 8h.01'/></svg> Column already exists, skipping...</div>\n";
                } else {
                    throw $e;
                }
            }
        }
        
        echo "<div class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Migration completed successfully!</div>\n";
        
        // Verify changes
        $columns = $pdo->query("SHOW COLUMNS FROM `homeowners`")->fetchAll(PDO::FETCH_ASSOC);
        $hasUsername = false;
        $hasPassword = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'username') $hasUsername = true;
            if ($col['Field'] === 'password_hash') $hasPassword = true;
        }
        
        if ($hasUsername && $hasPassword) {
            echo "<div class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Verified: Both username and password_hash columns exist.</div>\n";
            
            // Check existing homeowners
            $count = $pdo->query("SELECT COUNT(*) FROM homeowners WHERE username IS NOT NULL")->fetchColumn();
            echo "<div class='info'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='M12 16v-4M12 8h.01'/></svg> Total homeowners with usernames: {$count}</div>\n";
            
            if ($count > 0) {
                echo "<div class='info'>";
                echo "<strong><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Important:</strong> Existing homeowners have been assigned temporary usernames (homeowner_1, homeowner_2, etc.) ";
                echo "with the default password <code>password</code>. They should update their credentials upon first login.";
                echo "</div>\n";
            }
        } else {
            echo "<div class='error'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M18 6 6 18M6 6l12 12'/></svg> Verification failed: Columns may not have been created properly.</div>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M18 6 6 18M6 6l12 12'/></svg> Error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<div class='info'>Please check your database connection and permissions.</div>\n";
}

echo "\n<hr>\n<p><a href='../admin/admin_panel.php'>← Back to Admin Panel</a></p>\n";
echo "</body>\n</html>";
