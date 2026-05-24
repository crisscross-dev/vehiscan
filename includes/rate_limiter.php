<?php
/**
 * Simple Rate Limiter
 * Prevents brute force attacks by limiting request frequency
 */

class RateLimiter {
    private $pdo;
    private $rateLimitColumns = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Check if action is rate limited.
     * In non-production environments, the limiter is disabled so feature testing is not blocked.
     */
    public function check($identifier, $action = 'default', $maxAttempts = 5, $windowMinutes = 15) {
        try {
            if (!$this->isEnabled()) {
                return [
                    'allowed' => true,
                    'remaining' => (int)$maxAttempts,
                    'reset_time' => null,
                    'attempts' => 0,
                ];
            }

            $identifier = $this->normalizeIdentifier($identifier);
            $windowMinutes = max(1, (int)$windowMinutes);
            $lookupKey = $this->getLookupValue($identifier);
            $whereColumn = $this->getIdentifierColumnName();

            $windowStmt = $this->pdo->prepare("SELECT DATE_SUB(NOW(), INTERVAL ? MINUTE)");
            $windowStmt->execute([$windowMinutes]);
            $windowStart = $windowStmt->fetchColumn();
            if (!$windowStart) {
                throw new RuntimeException('Unable to determine rate-limit window start');
            }

            $this->pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?")->execute([$windowStart]);

            $stmt = $this->pdo->prepare("\n                SELECT COUNT(*) as attempt_count, MIN(created_at) as oldest_attempt\n                FROM rate_limits\n                WHERE action = ?\n                AND created_at >= ?\n                AND {$whereColumn} = ?\n            ");
            $stmt->execute([$action, $windowStart, $lookupKey]);
            $result = $stmt->fetch();
            $attemptCount = $result ? (int)$result['attempt_count'] : 0;
            $oldestAttempt = $result['oldest_attempt'] ?? null;

            $remaining = max(0, $maxAttempts - $attemptCount);
            $resetTime = $oldestAttempt
                ? date('Y-m-d H:i:s', strtotime($oldestAttempt . " +{$windowMinutes} minutes"))
                : date('Y-m-d H:i:s', strtotime("+{$windowMinutes} minutes"));

            return [
                'allowed' => $attemptCount < $maxAttempts,
                'remaining' => $remaining,
                'reset_time' => $resetTime,
                'attempts' => $attemptCount
            ];
        } catch (PDOException $e) {
            error_log("Rate limiter error: " . $e->getMessage());
            return ['allowed' => false, 'remaining' => 0, 'reset_time' => null, 'attempts' => $maxAttempts];
        }
    }

    /**
     * Record an attempt.
     * Disabled outside production so dev/testing flows do not get throttled.
     */
    public function recordAttempt($identifier, $action = 'default', $metadata = []) {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $identifier = $this->normalizeIdentifier($identifier);
            $lookupKey = $this->getLookupValue($identifier);
            $ipAddress = filter_var($lookupKey, FILTER_VALIDATE_IP)
                ? $lookupKey
                : ($_SERVER['REMOTE_ADDR'] ?? null);

            $metadataJson = null;
            if (!empty($metadata)) {
                $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    $metadataJson = $encoded;
                }
            }

            $columns = [];
            $values = [];

            if ($this->hasColumn('identifier')) {
                $columns[] = 'identifier';
                $values[] = $identifier;
            }

            if ($this->hasColumn('ip_address')) {
                $columns[] = 'ip_address';
                $values[] = $ipAddress ?? $lookupKey;
            }

            if ($this->hasColumn('user_id')) {
                $columns[] = 'user_id';
                $values[] = null;
            }

            if ($this->hasColumn('action')) {
                $columns[] = 'action';
                $values[] = $action;
            }

            if ($this->hasColumn('user_agent')) {
                $columns[] = 'user_agent';
                $values[] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            }

            if ($this->hasColumn('metadata')) {
                $columns[] = 'metadata';
                $values[] = $metadataJson;
            }

            if ($this->hasColumn('success')) {
                $columns[] = 'success';
                $values[] = 0;
            }

            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnList = implode(', ', $columns);
            $stmt = $this->pdo->prepare("INSERT INTO rate_limits ({$columnList}, created_at) VALUES ({$placeholders}, NOW())");
            $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("Rate limiter record error: " . $e->getMessage());
        }
    }

    /**
     * Reset rate limit for an identifier.
     */
    public function reset($identifier, $action = 'default') {
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $identifier = $this->normalizeIdentifier($identifier);
            $lookupKey = $this->getLookupValue($identifier);
            $whereColumn = $this->getIdentifierColumnName();

            $stmt = $this->pdo->prepare("\n                DELETE FROM rate_limits\n                WHERE action = ? AND {$whereColumn} = ?\n            ");
            $stmt->execute([$action, $lookupKey]);
        } catch (PDOException $e) {
            error_log("Rate limiter reset error: " . $e->getMessage());
        }
    }

    /**
     * Check if identifier is currently locked out
     */
    public function isLockedOut($identifier, $action = 'default', $maxAttempts = 5, $lockoutMinutes = 15) {
        $result = $this->check($identifier, $action, $maxAttempts, $lockoutMinutes);

        if (!$result['allowed']) {
            return [
                'locked' => true,
                'unlock_time' => $result['reset_time'],
                'attempts' => $result['attempts']
            ];
        }

        return ['locked' => false, 'unlock_time' => null, 'attempts' => $result['attempts']];
    }

    private function normalizeIdentifier($identifier) {
        $value = trim((string)$identifier);
        return $value !== '' ? $value : 'unknown';
    }

    private function getLookupValue(string $identifier): string {
        if ($this->hasColumn('identifier')) {
            return $identifier;
        }

        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return $remoteAddr !== '' ? $remoteAddr : $identifier;
    }

    private function getIdentifierColumnName(): string {
        if ($this->hasColumn('identifier')) {
            return 'identifier';
        }

        if ($this->hasColumn('ip_address')) {
            return 'ip_address';
        }

        return 'identifier';
    }

    private function hasColumn(string $columnName): bool {
        $columns = $this->getRateLimitColumns();
        return isset($columns[strtolower($columnName)]);
    }

    private function getRateLimitColumns(): array {
        if ($this->rateLimitColumns !== null) {
            return $this->rateLimitColumns;
        }

        $this->rateLimitColumns = [];

        try {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM rate_limits');
            if ($stmt !== false) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['Field'])) {
                        $this->rateLimitColumns[strtolower((string)$row['Field'])] = true;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Rate limiter schema lookup error: ' . $e->getMessage());
        }

        return $this->rateLimitColumns;
    }

    private function isEnabled() {
        return defined('APP_ENV') && APP_ENV === 'production';
    }
}

/**
 * Migration SQL to create rate_limits table:
 *
 * CREATE TABLE IF NOT EXISTS rate_limits (
 *     id INT AUTO_INCREMENT PRIMARY KEY,
 *     identifier VARCHAR(255) NOT NULL,
 *     action VARCHAR(50) NOT NULL DEFAULT 'default',
 *     ip_address VARCHAR(45),
 *     user_agent TEXT,
 *     metadata JSON,
 *     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 *     INDEX idx_identifier_action (identifier, action),
 *     INDEX idx_created_at (created_at)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
