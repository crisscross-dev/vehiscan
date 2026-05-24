<?php
/**
 * Database Migration Runner
 * Run from command line: php scripts/migrate.php
 * Or access via browser (requires admin session)
 */

require_once __DIR__ . '/../db.php';

// Restrict to CLI or authenticated admin
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/session_admin_unified.php';
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
        http_response_code(403);
        echo 'Access denied. Admin authentication required.';
        exit();
    }
}

// Set appropriate headers
header('Content-Type: text/html; charset=utf-8');

class MigrationRunner {
    private $pdo;
    private $migrationsDir;
    private $output = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrationsDir = dirname(__DIR__) . '/migrations';
        $this->ensureMigrationsTable();
    }
    
    private function ensureMigrationsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                migration_name VARCHAR(255) UNIQUE NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                execution_time_ms INT,
                status ENUM('success', 'failed') DEFAULT 'success'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    private function log($message, $type = 'info') {
        $icons = [
            'info' => '<svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>',
            'success' => '<svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>',
            'error' => '<svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/></svg>',
            'skip' => '<svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 4 15 12 5 20"/><line x1="19" y1="5" x2="19" y2="19"/></svg>',
            'warning' => '<svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
        ];
        
        $icon = $icons[$type] ?? '&bull;';
        $this->output[] = ['type' => $type, 'message' => "$icon $message"];
        echo "<div class='log-$type'>$icon $message</div>\n";
        flush();
    }
    
    public function run() {
        $this->log("Starting migration process...", 'info');
        
        if (!is_dir($this->migrationsDir)) {
            $this->log("Migrations directory not found: {$this->migrationsDir}", 'error');
            return false;
        }
        
        $files = glob($this->migrationsDir . '/*.sql');
        if (empty($files)) {
            $this->log("No migration files found", 'warning');
            return true;
        }
        
        sort($files);
        
        $executed = 0;
        $skipped = 0;
        $failed = 0;
        
        foreach ($files as $file) {
            $name = basename($file);
            
            // Check if already executed
            $stmt = $this->pdo->prepare("SELECT id, status FROM migrations WHERE migration_name = ?");
            $stmt->execute([$name]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $this->log("Skipping: $name (already executed)", 'skip');
                $skipped++;
                continue;
            }
            
            $this->log("Running: $name", 'info');
            
            $startTime = microtime(true);
            
            try {
                $sql = file_get_contents($file);
                
                // Split by semicolons but preserve them in strings
                $statements = $this->splitSQL($sql);
                
                $this->pdo->beginTransaction();
                
                foreach ($statements as $statement) {
                    $trimmed = trim($statement);
                    if (empty($trimmed) || strpos($trimmed, '--') === 0) {
                        continue;
                    }
                    $this->pdo->exec($trimmed);
                }
                
                $executionTime = round((microtime(true) - $startTime) * 1000);
                
                // Mark as executed
                $stmt = $this->pdo->prepare("
                    INSERT INTO migrations (migration_name, execution_time_ms, status) 
                    VALUES (?, ?, 'success')
                ");
                $stmt->execute([$name, $executionTime]);
                
                $this->pdo->commit();
                
                $this->log("Completed: $name ({$executionTime}ms)", 'success');
                $executed++;
                
            } catch (Exception $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                
                $this->log("Failed: $name - " . $e->getMessage(), 'error');
                $failed++;
                
                // Log failed migration
                try {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO migrations (migration_name, status) 
                        VALUES (?, 'failed')
                    ");
                    $stmt->execute([$name]);
                } catch (Exception $e2) {
                    // Ignore
                }
                
                break; // Stop on first failure
            }
        }
        
        $this->log("", 'info');
        $this->log("Migration Summary:", 'info');
        $this->log("  Executed: $executed", $executed > 0 ? 'success' : 'info');
        $this->log("  Skipped: $skipped", 'skip');
        $this->log("  Failed: $failed", $failed > 0 ? 'error' : 'info');
        
        return $failed === 0;
    }
    
    private function splitSQL($sql) {
        // Simple SQL splitter - splits on semicolons not in quotes/comments
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $inComment = false;
        
        $lines = explode("\n", $sql);
        
        foreach ($lines as $line) {
            $line = rtrim($line);
            
            // Skip comment lines
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            
            // Check for statement delimiter
            if (preg_match('/;\s*$/', $line) && !$inString) {
                $current .= $line . "\n";
                $statements[] = $current;
                $current = '';
            } else {
                $current .= $line . "\n";
            }
        }
        
        if (trim($current)) {
            $statements[] = $current;
        }
        
        return $statements;
    }
    
    public function getExecutedMigrations() {
        $stmt = $this->pdo->query("
            SELECT migration_name, executed_at, execution_time_ms, status 
            FROM migrations 
            ORDER BY executed_at DESC
        ");
        return $stmt->fetchAll();
    }
}

// HTML Output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migrations - VehiScan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; font-size: 0.95rem; }
        .content { padding: 2rem; }
        .log-container {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .log-container div {
            padding: 0.5rem;
            border-left: 3px solid transparent;
            margin-bottom: 0.25rem;
        }
        .log-info { border-left-color: #3182ce; }
        .log-success { border-left-color: #38a169; background: #f0fff4; }
        .log-error { border-left-color: #e53e3e; background: #fff5f5; }
        .log-skip { border-left-color: #d69e2e; background: #fffff0; }
        .log-warning { border-left-color: #ed8936; background: #fffaf0; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover { transform: translateY(-2px); }
        .migration-list {
            margin-top: 2rem;
            border-top: 2px solid #e2e8f0;
            padding-top: 2rem;
        }
        .migration-list h3 {
            margin-bottom: 1rem;
            color: #2d3748;
        }
        .migration-item {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .migration-name { font-weight: 600; color: #2d3748; }
        .migration-date { font-size: 0.85rem; color: #718096; }
        .migration-time { font-size: 0.85rem; color: #38a169; font-weight: 600; }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-success { background: #c6f6d5; color: #22543d; }
        .status-failed { background: #fed7d7; color: #742a2a; }
        .actions {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #e2e8f0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><svg style="width:1.2em;height:1.2em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg> Database Migrations</h1>
            <p>VehiScan RFID System Migration Runner</p>
        </div>
        
        <div class="content">
            <div class="log-container">
                <?php
                try {
                    $runner = new MigrationRunner($pdo);
                    $success = $runner->run();
                    
                    if ($success) {
                        echo "<div class='log-success'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='m9 12 2 2 4-4'/></svg> All migrations completed successfully!</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='log-error'><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='m15 9-6 6m0-6 6 6'/></svg> Migration error: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
            
            <div class="migration-list">
                <h3><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8M8 12h8M8 17h4"/></svg> Migration History</h3>
                <?php
                try {
                    $migrations = $runner->getExecutedMigrations();
                    
                    if (empty($migrations)) {
                        echo "<p style='color: #718096;'>No migrations have been executed yet.</p>";
                    } else {
                        foreach ($migrations as $migration) {
                            $statusClass = 'status-' . $migration['status'];
                            $time = $migration['execution_time_ms'] ? $migration['execution_time_ms'] . 'ms' : 'N/A';
                            
                            echo "<div class='migration-item'>";
                            echo "<div>";
                            echo "<div class='migration-name'>{$migration['migration_name']}</div>";
                            echo "<div class='migration-date'>" . date('Y-m-d H:i:s', strtotime($migration['executed_at'])) . "</div>";
                            echo "</div>";
                            echo "<div style='text-align: right;'>";
                            echo "<span class='status-badge $statusClass'>{$migration['status']}</span><br>";
                            echo "<span class='migration-time'>$time</span>";
                            echo "</div>";
                            echo "</div>";
                        }
                    }
                } catch (Exception $e) {
                    echo "<p style='color: #e53e3e;'>Error loading migration history</p>";
                }
                ?>
            </div>
            
            <div class="actions">
                <a href="../auth/first_run_setup.php" class="btn"><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Continue to First-Run Setup</a>
                <a href="../admin/admin_panel.php" class="btn" style="margin-left: 1rem; background: #48bb78;"><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg> Go to Admin Panel</a>
            </div>
        </div>
    </div>
</body>
</html>
