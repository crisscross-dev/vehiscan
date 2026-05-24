<?php
/**
 * EmailService — PHPMailer wrapper with mail() fallback
 * 
 * Usage:
 *   require_once __DIR__ . '/email.php';
 *   EmailService::send($to, $subject, $body);
 *   EmailService::test($email);
 *   EmailService::getConfigStatus();
 */

class EmailService
{
    /**
     * Ensure the Composer autoloader is loaded (for PHPMailer).
     */
    private static function ensureAutoloader()
    {
        static $loaded = false;
        if (!$loaded) {
            $autoloader = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($autoloader)) {
                require_once $autoloader;
                $loaded = true;
            }
        }
        return $loaded;
    }

    /**
     * Get SMTP configuration from environment.
     */
    private static function getSmtpConfig()
    {
        // Ensure config() helper is available
        if (!function_exists('config')) {
            require_once __DIR__ . '/../config.php';
        }

        return [
            'host'       => config('SMTP_HOST', ''),
            'port'       => (int) config('SMTP_PORT', 587),
            'encryption' => config('SMTP_ENCRYPTION', 'tls'),
            'auth'       => filter_var(config('SMTP_AUTH', true), FILTER_VALIDATE_BOOLEAN),
            'username'   => config('SMTP_USERNAME', ''),
            'password'   => config('SMTP_PASSWORD', ''),
        ];
    }

    /**
     * Send an email.
     *
     * @param string      $to       Recipient email address
     * @param string      $subject  Email subject
     * @param string      $body     Email body (HTML or plain text)
     * @param string|null $altBody  Plain-text alternative body (auto-generated from $body if null and $isHtml)
     * @param bool        $isHtml   Whether $body is HTML
     * @return bool
     */
    public static function send($to, $subject, $body, $altBody = null, $isHtml = true)
    {
        // Try PHPMailer first
        if (self::ensureAutoloader() && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return self::sendViaPHPMailer($to, $subject, $body, $altBody, $isHtml);
        }

        // Fallback to mail()
        return self::sendViaMail($to, $subject, $body, $isHtml);
    }

    /**
     * Send via PHPMailer.
     */
    private static function sendViaPHPMailer($to, $subject, $body, $altBody, $isHtml)
    {
        $smtp = self::getSmtpConfig();

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            // Server settings
            if ($smtp['host']) {
                $mail->isSMTP();
                $mail->Host       = $smtp['host'];
                $mail->Port       = $smtp['port'];
                $mail->SMTPSecure = $smtp['encryption'];
                $mail->SMTPAuth   = $smtp['auth'];
                $mail->Username   = $smtp['username'];
                $mail->Password   = $smtp['password'];
            }

            // Sender / Recipient
            $fromEmail = config('SUPPORT_EMAIL', $smtp['username'] ?: 'noreply@vehiscan.local');
            $fromName  = config('SUPPORT_NAME', 'VehiScan Support');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            if ($isHtml) {
                $mail->AltBody = $altBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));
            }

            $mail->send();
            return true;

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log("[EmailService] PHPMailer error: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("[EmailService] General error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via PHP mail() function.
     */
    private static function sendViaMail($to, $subject, $body, $isHtml)
    {
        $fromEmail = config('SUPPORT_EMAIL', 'noreply@vehiscan.local');
        $fromName  = config('SUPPORT_NAME', 'VehiScan Support');

        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        $result = @mail($to, $subject, $body, $headers);

        if (!$result) {
            error_log("[EmailService] mail() failed for: {$to}");
        }

        return $result;
    }

    /**
     * Send a test email to verify configuration.
     *
     * @param string $recipientEmail
     * @return array ['success' => bool, 'message' => string, 'error' => string]
     */
    public static function test($recipientEmail)
    {
        $subject = 'VehiScan Email Test';
        $body    = '<h2>Email Test Successful</h2>'
                 . '<p>This is a test email from VehiScan RFID System.</p>'
                 . '<p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';

        try {
            $success = self::send($recipientEmail, $subject, $body);
            return [
                'success' => $success,
                'message' => $success ? "Test email sent to {$recipientEmail}" : "Failed to send test email",
                'error'   => $success ? '' : 'Send returned false — check error_log for details',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception during send',
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Get the current email configuration status.
     *
     * @return array
     */
    public static function getConfigStatus()
    {
        self::ensureAutoloader();

        $smtp = self::getSmtpConfig();
        $phpmailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
        $mailAvailable = function_exists('mail');
        $smtpConfigured = !empty($smtp['host']) && !empty($smtp['username']);

        $recommendations = [];
        if (!$phpmailerAvailable) {
            $recommendations[] = 'Install PHPMailer via Composer for reliable SMTP delivery.';
        }
        if (!$smtpConfigured) {
            $recommendations[] = 'Configure SMTP settings in .env for production email delivery.';
        }
        if (!$mailAvailable && !$phpmailerAvailable) {
            $recommendations[] = 'No email transport available. Install PHPMailer or enable PHP mail().';
        }

        return [
            'phpmailer_available'    => $phpmailerAvailable,
            'mail_function_available' => $mailAvailable,
            'smtp_configured'        => $smtpConfigured,
            'from_email'             => config('SUPPORT_EMAIL', ''),
            'from_name'              => config('SUPPORT_NAME', 'VehiScan Support'),
            'smtp_config'            => [
                'host'       => $smtp['host'],
                'port'       => $smtp['port'],
                'encryption' => $smtp['encryption'],
                'auth'       => $smtp['auth'],
                'username'   => $smtp['username'] ? '***configured***' : '',
                'password'   => $smtp['password'] ? '***configured***' : '',
            ],
            'recommendations'        => $recommendations,
        ];
    }
}
