<?php
require_once __DIR__ . '/vendor/PHPMailer/Exception.php';
require_once __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/vendor/PHPMailer/SMTP.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an HTML email to a user.
 *
 * @param mysqli $conn      Database connection
 * @param int    $userId    Target user ID
 * @param string $subject   Email subject
 * @param string $htmlBody  HTML email body
 * @return bool True on success, false on failure
 */
function sendUserEmail($conn, $userId, $subject, $htmlBody)
{
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();

    if (!$user || empty($user['email'])) {
        return false;
    }

    if (strpos(SMTP_USER, 'REPLACE_WITH') !== false || strpos(SMTP_PASS, 'REPLACE_WITH') !== false) {
        $logLine = '[' . date('Y-m-d H:i:s') . '] To=' . $user['email'] . ' Subject=' . $subject . ' Error: SMTP credentials not configured yet - edit includes/mail_config.php and set SMTP_USER + SMTP_PASS.' . PHP_EOL;
        @file_put_contents(__DIR__ . '/../uploads/mail_log.txt', $logLine, FILE_APPEND);
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => __DIR__ . '/vendor/cacert.pem',
            ],
        ];

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($user['email'], $user['name']);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;

        return $mail->send();
    } catch (Exception $e) {
        $logLine = '[' . date('Y-m-d H:i:s') . '] To=' . ($user['email'] ?? '?') . ' Subject=' . $subject . ' Error: ' . $e->getMessage() . PHP_EOL;
        @file_put_contents(__DIR__ . '/../uploads/mail_log.txt', $logLine, FILE_APPEND);
        error_log('Booking email error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send a booking status email (confirmed / cancelled / completed).
 *
 * @param mysqli $conn      Database connection
 * @param int    $userId    User ID
 * @param string $status    'confirmed', 'cancelled' or 'completed'
 * @param string $eventName Booking event name
 * @param string $dateStr   Human readable event date
 * @param string $reason    Cancellation reason (optional)
 * @return bool
 */
function sendBookingMail($conn, $userId, $status, $eventName, $dateStr, $reason = '')
{
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $stmt->close();
    $userName = $user ? htmlspecialchars($user['name']) : '';

    $statuses = [
        'confirmed' => [
            'subject' => "Booking Confirmed: {$eventName}",
            'heading' => 'Your booking has been confirmed!',
            'message' => "Great news, <strong>{$userName}</strong>! Your booking for <strong>{$eventName}</strong> on <strong>{$dateStr}</strong> has been confirmed. We look forward to seeing you there.",
        ],
        'cancelled' => [
            'subject' => "Booking Cancelled: {$eventName}",
            'heading' => 'Your booking has been cancelled',
            'message' => "Your booking for <strong>{$eventName}</strong> on <strong>{$dateStr}</strong> has been cancelled. Reason: " . nl2br(htmlspecialchars($reason ?: 'No reason provided')) . ".<br>If you have any questions, please contact us.",
        ],
        'completed' => [
            'subject' => "Booking Completed: {$eventName}",
            'heading' => 'Your booking is completed',
            'message' => "Your booking for <strong>{$eventName}</strong> on <strong>{$dateStr}</strong> has been marked as completed. Thank you for choosing us!",
        ],
    ];

    if (!isset($statuses[$status])) {
        return false;
    }

    $statusColors = [
        'confirmed' => ['#9966cc', '#f4effb'],
        'cancelled' => ['#e11d48', '#fdeaea'],
        'completed' => ['#16a34a', '#e7f6ee'],
    ];
    [$accent, $tint] = $statusColors[$status];

    $statusLabel = ucfirst($status);

    $html = '<!DOCTYPE html>'
          . '<html lang="en"><head><meta charset="UTF-8"></head>'
          . '<body style="margin:0;padding:0;background-color:#f3f1f6;font-family:Arial,\'Helvetica Neue\',Helvetica,sans-serif;">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f1f6;padding:32px 16px;">'
          . '<tr><td align="center">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.08);">'

          // Header
          . '<tr><td style="background:linear-gradient(135deg,#9966cc,#a020f0);padding:28px 32px;">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
          . '<td style="vertical-align:middle;">'
          . '<div style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:0.5px;">Event<span style="color:#ffe3ff;">Pro</span></div>'
          . '<div style="font-size:12px;color:rgba(255,255,255,0.85);margin-top:2px;letter-spacing:1.5px;">EVENT PLANNING &amp; MANAGEMENT</div>'
          . '</td>'
          . '<td align="right" style="vertical-align:middle;">'
          . '<span style="display:inline-block;background:' . $tint . ';color:' . $accent . ';font-size:11px;font-weight:bold;padding:6px 14px;border-radius:999px;text-transform:uppercase;letter-spacing:0.5px;">' . $statusLabel . '</span>'
          . '</td></tr></table>'
          . '</td></tr>'

          // Body
          . '<tr><td style="padding:32px;">'
          . '<h1 style="margin:0 0 8px;font-size:22px;color:#383242;font-weight:700;">' . $statuses[$status]['heading'] . '</h1>'
          . '<div style="width:48px;height:4px;border-radius:4px;background:' . $accent . ';margin:0 0 20px;"></div>'
          . '<p style="margin:0;color:#555;font-size:14px;line-height:1.7;">' . $statuses[$status]['message'] . '</p>'
          . '</td></tr>'

          // Summary card
          . '<tr><td style="padding:0 32px;">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f1f6;border-radius:12px;padding:16px 20px;">'
          . '<tr><td style="padding:4px 0;font-size:12px;color:#888;width:110px;">Event</td><td style="padding:4px 0;font-size:13px;color:#383242;font-weight:600;">' . $eventName . '</td></tr>'
          . '<tr><td style="padding:4px 0;font-size:12px;color:#888;width:110px;">Date</td><td style="padding:4px 0;font-size:13px;color:#383242;font-weight:600;">' . $dateStr . '</td></tr>'
          . '</table>'
          . '</td></tr>'

          // Footer
          . '<tr><td style="padding:24px 32px 32px;">'
          . '<div style="border-top:1px solid #eee;padding-top:16px;text-align:center;">'
          . '<p style="margin:0 0 6px;color:#999;font-size:12px;">You can view your bookings in your account dashboard.</p>'
          . '<p style="margin:0;color:#bbb;font-size:11px;">&copy; ' . date('Y') . ' Event Pro &bull; Plan Your Perfect Event With Us</p>'
          . '</div>'
          . '</td></tr>'

          . '</table>'
          . '</td></tr></table>'
          . '</body></html>';

    return sendUserEmail($conn, $userId, $statuses[$status]['subject'], $html);
}