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

    $html = '<html><body style="font-family:Arial,sans-serif;background:#f6f7fb;padding:20px;">'
          . '<div style="max-width:520px;margin:auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e3e3e3;">'
          . '<div style="background:#9d84c7;color:#fff;padding:20px 24px;font-size:20px;font-weight:bold;">Event Planning</div>'
          . '<div style="padding:24px;">'
          . '<h2 style="margin:0 0 12px;color:#333;">' . $statuses[$status]['heading'] . '</h2>'
          . '<p style="color:#555;line-height:1.6;">' . $statuses[$status]['message'] . '</p>'
          . '<p style="color:#999;font-size:12px;margin-top:20px;">You can view your bookings in your account dashboard.</p>'
          . '</div></div></body></html>';

    return sendUserEmail($conn, $userId, $statuses[$status]['subject'], $html);
}