<?php
/**
 * PasswordResetHandler — Manages password reset token generation, validation, and execution.
 *
 * Usage:
 *   require_once __DIR__ . '/password_reset.php';
 *   $handler = new PasswordResetHandler($pdo);
 *   $result  = $handler->requestReset($email);
 *   $result  = $handler->validateToken($token);
 *   $result  = $handler->resetPassword($token, $newPassword);
 */

require_once __DIR__ . '/email.php';
require_once __DIR__ . '/email_templates.php';

class PasswordResetHandler
{
    private $pdo;
    private $tokenExpiryMinutes = 60;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Request a password reset for the given email.
     * Looks up the email across all user tables.
     *
     * @param  string $email
     * @return array  ['success' => bool, 'message' => string]
     */
    public function requestReset($email)
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Intentionally vague to prevent email enumeration
            return ['success' => true, 'message' => 'If that email exists in our system, a reset link has been sent.'];
        }

        // Rate limit: max 3 reset requests per email per 15 minutes
        $rateLimitStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM password_reset_tokens
            WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $rateLimitStmt->execute([$email]);
        if ((int)$rateLimitStmt->fetchColumn() >= 3) {
            // Silently return success to prevent enumeration
            return ['success' => true, 'message' => 'If that email exists in our system, a reset link has been sent.'];
        }

        // Look up which user table this email belongs to
        $userType = $this->findUserByEmail($email);

        if (!$userType) {
            // Return success anyway to prevent email enumeration
            return ['success' => true, 'message' => 'If that email exists in our system, a reset link has been sent.'];
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->tokenExpiryMinutes * 60));

        // Invalidate any existing unused tokens for this email
        $invalidateStmt = $this->pdo->prepare("
            UPDATE password_reset_tokens SET used_at = NOW()
            WHERE email = ? AND used_at IS NULL
        ");
        $invalidateStmt->execute([$email]);

        // Insert new token
        $insertStmt = $this->pdo->prepare("
            INSERT INTO password_reset_tokens (email, token, user_type, expires_at, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $email,
            $token,
            $userType,
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);

        // Build reset URL
        if (!function_exists('getAppUrl')) {
            require_once __DIR__ . '/../config.php';
        }
        $resetUrl = getAppUrl() . '/auth/reset-password.php?token=' . $token;

        // Send email
        $htmlBody = EmailTemplates::passwordResetEmail($resetUrl, $this->tokenExpiryMinutes);
        $sent = EmailService::send($email, 'Password Reset — VehiScan RFID', $htmlBody);

        if (!$sent) {
            error_log("[PasswordReset] Failed to send reset email to: {$email}");
        }

        // Log the reset request
        $this->logAudit('password_reset_requested', [
            'email' => $email,
            'user_type' => $userType,
            'email_sent' => $sent,
        ]);

        return ['success' => true, 'message' => 'If that email exists in our system, a reset link has been sent.'];
    }

    /**
     * Validate a reset token.
     *
     * @param  string $token
     * @return array  ['valid' => bool, 'email' => string|null, 'user_type' => string|null, 'error' => string]
     */
    public function validateToken($token)
    {
        if (empty($token) || strlen($token) !== 64) {
            return ['valid' => false, 'email' => null, 'user_type' => null, 'error' => 'Invalid token format.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT email, user_type, expires_at, used_at
            FROM password_reset_tokens
            WHERE token = ?
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['valid' => false, 'email' => null, 'user_type' => null, 'error' => 'Invalid or expired token.'];
        }

        if ($row['used_at'] !== null) {
            return ['valid' => false, 'email' => null, 'user_type' => null, 'error' => 'This reset link has already been used.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            return ['valid' => false, 'email' => null, 'user_type' => null, 'error' => 'This reset link has expired.'];
        }

        return [
            'valid'     => true,
            'email'     => $row['email'],
            'user_type' => $row['user_type'],
            'error'     => '',
        ];
    }

    /**
     * Reset the password using a valid token.
     *
     * @param  string $token
     * @param  string $newPassword  Plain-text password
     * @return array  ['success' => bool, 'message' => string]
     */
    public function resetPassword($token, $newPassword)
    {
        $validation = $this->validateToken($token);

        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['error']];
        }

        // Validate password strength
        $minLen = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12;
        if (strlen($newPassword) < $minLen) {
            return ['success' => false, 'message' => "Password must be at least $minLen characters long."];
        }

        $email    = $validation['email'];
        $userType = $validation['user_type'];
        $hash     = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $this->pdo->beginTransaction();

            // Update the password in the appropriate table
            $updated = $this->updatePassword($email, $userType, $hash);

            if (!$updated) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Could not update password. Account may no longer exist.'];
            }

            // Mark token as used
            $markStmt = $this->pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE token = ?");
            $markStmt->execute([$token]);

            $this->pdo->commit();

            // Log the successful reset
            $this->logAudit('password_reset_completed', [
                'email'     => $email,
                'user_type' => $userType,
            ]);

            // Send confirmation email
            $this->sendConfirmationEmail($email);

            return ['success' => true, 'message' => 'Your password has been reset successfully.'];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("[PasswordReset] Error resetting password: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    /**
     * Find which user table an email belongs to.
     *
     * @param  string $email
     * @return string|null  'super_admin', 'user', 'homeowner', or null
     */
    private function findUserByEmail($email)
    {
        // Check super_admin
        $stmt = $this->pdo->prepare("SELECT id FROM super_admin WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) return 'super_admin';

        // Check users (admins/guards)
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) return 'user';

        // Check homeowner_auth
        $stmt = $this->pdo->prepare("SELECT id FROM homeowner_auth WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) return 'homeowner';

        return null;
    }

    /**
     * Update the password hash in the appropriate table.
     *
     * @param  string $email
     * @param  string $userType
     * @param  string $hash
     * @return bool
     */
    private function updatePassword($email, $userType, $hash)
    {
        switch ($userType) {
            case 'super_admin':
                $stmt = $this->pdo->prepare("UPDATE super_admin SET password_hash = ?, password_changed_at = NOW() WHERE email = ?");
                $stmt->execute([$hash, $email]);
                return $stmt->rowCount() > 0;

            case 'user':
                $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hash, $email]);
                return $stmt->rowCount() > 0;

            case 'homeowner':
                $stmt = $this->pdo->prepare("UPDATE homeowner_auth SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE email = ?");
                $stmt->execute([$hash, $email]);
                return $stmt->rowCount() > 0;

            default:
                return false;
        }
    }

    /**
     * Send a confirmation email after a successful password reset.
     */
    private function sendConfirmationEmail($email)
    {
        $body = '<h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Password Changed</h2>'
              . '<p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">'
              . 'Your password was successfully reset on ' . date('M j, Y \a\t g:i A') . '.</p>'
              . '<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">'
              . 'If you did not make this change, please contact your system administrator immediately.</p>';

        EmailService::send($email, 'Password Changed — VehiScan RFID', $body);
    }

    /**
     * Log an audit event (if AuditLogger is available).
     */
    private function logAudit($action, $data = [])
    {
        try {
            if (class_exists('AuditLogger')) {
                AuditLogger::init($this->pdo);
                AuditLogger::logSecurity($action, 'medium', $data);
            }
        } catch (Exception $e) {
            error_log("[PasswordReset] Audit log error: " . $e->getMessage());
        }
    }
}
