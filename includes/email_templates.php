<?php
/**
 * Email Templates — HTML email body generators
 * 
 * Usage:
 *   require_once __DIR__ . '/email_templates.php';
 *   $html = EmailTemplates::passwordResetEmail($resetUrl, 60);
 */

class EmailTemplates
{
    /**
     * Base HTML wrapper for all emails.
     */
    private static function wrap($title, $contentHtml)
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:system-ui,-apple-system,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5;">
        <tr>
            <td align="center" style="padding:40px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background-color:#ffffff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding:32px 32px 0;text-align:center;">
                            <h1 style="margin:0;font-size:22px;font-weight:700;color:#111827;">VehiScan RFID</h1>
                            <div style="width:48px;height:3px;background:linear-gradient(90deg,#3b82f6,#6366f1);margin:12px auto 0;border-radius:2px;"></div>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding:24px 32px 32px;">
                            ' . $contentHtml . '
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 32px;border-top:1px solid #e5e7eb;text-align:center;">
                            <p style="margin:0;font-size:12px;color:#9ca3af;">
                                This email was sent by VehiScan RFID System.<br>
                                Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Password reset email.
     *
     * @param string $resetUrl  Full URL for the reset link
     * @param int    $expiryMinutes  Link expiry time in minutes
     * @return string  HTML email body
     */
    public static function passwordResetEmail($resetUrl, $expiryMinutes = 60)
    {
        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Password Reset Request</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                We received a request to reset your password. Click the button below to create a new password.
            </p>
            <div style="text-align:center;margin:24px 0;">
                <a href="' . htmlspecialchars($resetUrl) . '"
                   style="display:inline-block;padding:12px 32px;background-color:#3b82f6;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:8px;">
                    Reset Password
                </a>
            </div>
            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.6;">
                This link will expire in <strong>' . intval($expiryMinutes) . ' minutes</strong>.
            </p>
            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.6;">
                If you didn\'t request this, you can safely ignore this email.
            </p>
            <div style="margin-top:20px;padding:12px;background-color:#f9fafb;border-radius:8px;word-break:break-all;">
                <p style="margin:0;font-size:12px;color:#9ca3af;">Direct link:</p>
                <p style="margin:4px 0 0;font-size:12px;color:#3b82f6;">' . htmlspecialchars($resetUrl) . '</p>
            </div>';

        return self::wrap('Password Reset — VehiScan', $content);
    }

    /**
     * Account approved notification email.
     *
     * @param string $homeownerName
     * @param string $loginUrl
     * @return string  HTML email body
     */
    public static function accountApprovedEmail($homeownerName, $loginUrl)
    {
        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Account Approved!</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Hi ' . htmlspecialchars($homeownerName) . ',
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Your VehiScan RFID homeowner account has been approved. You can now log in to the homeowner portal.
            </p>
            <div style="text-align:center;margin:24px 0;">
                <a href="' . htmlspecialchars($loginUrl) . '"
                   style="display:inline-block;padding:12px 32px;background-color:#22c55e;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:8px;">
                    Log In Now
                </a>
            </div>';

        return self::wrap('Account Approved — VehiScan', $content);
    }

    /**
     * Account rejected notification email.
     *
     * @param string $homeownerName
     * @param string $reason
     * @return string  HTML email body
     */
    public static function accountRejectedEmail($homeownerName, $reason = '')
    {
        $reasonBlock = '';
        if ($reason) {
            $reasonBlock = '
            <div style="margin:16px 0;padding:12px;background-color:#fef2f2;border-left:3px solid #ef4444;border-radius:4px;">
                <p style="margin:0;font-size:13px;color:#991b1b;"><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>
            </div>';
        }

        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Account Not Approved</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Hi ' . htmlspecialchars($homeownerName) . ',
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Unfortunately, your VehiScan RFID homeowner account registration was not approved.
            </p>
            ' . $reasonBlock . '
            <p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">
                If you believe this was an error, please contact your subdivision administration.
            </p>';

        return self::wrap('Account Status — VehiScan', $content);
    }

    /**
     * Visitor pass approved notification email.
     *
     * @param string $homeownerName
     * @param string $visitorName
     * @param string $validFrom
     * @param string $validUntil
     * @param string $passUrl
     * @return string HTML email body
     */
    public static function visitorPassApprovedEmail($homeownerName, $visitorName, $validFrom, $validUntil, $passUrl)
    {
        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Visitor Pass Approved</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Hi ' . htmlspecialchars($homeownerName) . ',
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Your visitor pass request for <strong>' . htmlspecialchars($visitorName) . '</strong> has been approved.
            </p>
            <div style="margin:16px 0;padding:12px;background-color:#eff6ff;border-left:3px solid #2563eb;border-radius:4px;">
                <p style="margin:0 0 4px;font-size:13px;color:#1e3a8a;"><strong>Valid from:</strong> ' . htmlspecialchars($validFrom) . '</p>
                <p style="margin:0;font-size:13px;color:#1e3a8a;"><strong>Valid until:</strong> ' . htmlspecialchars($validUntil) . '</p>
            </div>
            <div style="text-align:center;margin:24px 0;">
                <a href="' . htmlspecialchars($passUrl) . '"
                   style="display:inline-block;padding:12px 32px;background-color:#2563eb;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:8px;">
                    View Visitor Pass
                </a>
            </div>';

        return self::wrap('Visitor Pass Approved — VehiScan', $content);
    }

    /**
     * Visitor pass rejected notification email.
     *
     * @param string $homeownerName
     * @param string $visitorName
     * @param string $reason
     * @return string HTML email body
     */
    public static function visitorPassRejectedEmail($homeownerName, $visitorName, $reason = '')
    {
        $reasonBlock = '';
        if ($reason !== '') {
            $reasonBlock = '
            <div style="margin:16px 0;padding:12px;background-color:#fef2f2;border-left:3px solid #ef4444;border-radius:4px;">
                <p style="margin:0;font-size:13px;color:#991b1b;"><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>
            </div>';
        }

        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Visitor Pass Rejected</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Hi ' . htmlspecialchars($homeownerName) . ',
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Your visitor pass request for <strong>' . htmlspecialchars($visitorName) . '</strong> was not approved.
            </p>
            ' . $reasonBlock . '
            <p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">
                If you believe this was an error, please contact your subdivision administration.
            </p>';

        return self::wrap('Visitor Pass Rejected — VehiScan', $content);
    }

    /**
     * Visitor pass used notification email.
     *
     * @param string $homeownerName
     * @param string $visitorName
     * @param string $usedAt
     * @return string HTML email body
     */
    public static function visitorPassUsedEmail($homeownerName, $visitorName, $usedAt)
    {
        $content = '
            <h2 style="margin:0 0 12px;font-size:18px;font-weight:600;color:#111827;">Visitor Pass Used</h2>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                Hi ' . htmlspecialchars($homeownerName) . ',
            </p>
            <p style="margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;">
                The visitor pass for <strong>' . htmlspecialchars($visitorName) . '</strong> was scanned at the gate.
            </p>
            <div style="margin:16px 0;padding:12px;background-color:#f0fdf4;border-left:3px solid #16a34a;border-radius:4px;">
                <p style="margin:0;font-size:13px;color:#166534;"><strong>Scanned at:</strong> ' . htmlspecialchars($usedAt) . '</p>
            </div>
            <p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">
                If this was not expected, please contact your subdivision administration immediately.
            </p>';

        return self::wrap('Visitor Pass Used — VehiScan', $content);
    }
}
