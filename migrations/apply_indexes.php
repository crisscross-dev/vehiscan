<?php
/**
 * Apply Performance Indexes Migration
 * Run this script to add database indexes for improved query performance
 */

require_once __DIR__ . '/../db.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Database Index Migration</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;}.error{color:red;}.info{color:blue;}</style></head><body>";
echo "<h1>Database Performance Index Migration</h1>";

try {
    // Read the migration file
    $sqlFile = __DIR__ . '/002_add_performance_indexes.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolons to get individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Filter out comments and empty statements
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^\/\*/', $stmt);
        }
    );
    
    echo "<p class='info'>Found " . count($statements) . " SQL statements to execute.</p>";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        try {
            // Extract index name for display
            if (preg_match('/CREATE INDEX.*?IF NOT EXISTS\s+(\w+)/i', $statement, $matches)) {
                $indexName = $matches[1];
                echo "<p>Creating index: <strong>$indexName</strong>... ";
                
                $pdo->exec($statement);
                echo "<span class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Success</span></p>";
                $successCount++;
            } else {
                echo "<p>Executing statement... ";
                $pdo->exec($statement);
                echo "<span class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Success</span></p>";
                $successCount++;
            }
        } catch (PDOException $e) {
            // Check if error is because index already exists
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "<span class='info'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='M12 16v-4M12 8h.01'/></svg> Already exists</span></p>";
                $successCount++;
            } else {
                echo "<span class='error'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M18 6 6 18M6 6l12 12'/></svg> Error: " . htmlspecialchars($e->getMessage()) . "</span></p>";
                $errorCount++;
            }
        }
    }
    
    echo "<hr>";
    echo "<h2>Migration Summary</h2>";
    echo "<p class='success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Successful: $successCount</p>";
    if ($errorCount > 0) {
        echo "<p class='error'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M18 6 6 18M6 6l12 12'/></svg> Errors: $errorCount</p>";
    }
    
    // Show index information
    echo "<hr>";
    echo "<h2>Index Information</h2>";
    
    $tables = ['recent_logs', 'homeowners', 'visitor_passes', 'users', 'audit_logs', 'failed_login_attempts', 'super_admin'];
    
    foreach ($tables as $table) {
        try {
            // Sanitize table name
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $stmt = $pdo->prepare("SHOW INDEX FROM `$safeTable`");
            $stmt->execute();
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($indexes)) {
                echo "<h3>Table: $table</h3>";
                echo "<ul>";
                $indexNames = array_unique(array_column($indexes, 'Key_name'));
                foreach ($indexNames as $indexName) {
                    if ($indexName !== 'PRIMARY') {
                        echo "<li>$indexName</li>";
                    }
                }
                echo "</ul>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>Table $table not found (this is normal if table doesn't exist yet)</p>";
        }
    }
    
    echo "<hr>";
    echo "<p class='success'><strong><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M20 6 9 17l-5-5'/></svg> Migration completed successfully!</strong></p>";
    echo "<p><a href='../admin/admin_panel.php'>← Back to Admin Panel</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'><strong><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='3'><path d='M18 6 6 18M6 6l12 12'/></svg> Migration failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='../admin/admin_panel.php'>← Back to Admin Panel</a></p>";
}

echo "</body></html>";
?>
