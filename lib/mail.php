<?php
/**
 * OUTBOUND EMAIL
 * ==============
 * Canonical send_email(). There were three byte-identical copies of this --
 * backend/mains/, backend/mains/callbacks/ and admin/includes/ -- differing
 * only in how many `../` they needed to reach PHPMailer. Three copies means
 * three places to change an SMTP setting and two chances to forget.
 *
 * The old locations are now shims that require this file, so every existing
 * `include 'send-email.php'` keeps working unchanged.
 *
 * Paths here are __DIR__-relative, so this file does not care who included it
 * or from what depth.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';
require_once __DIR__ . '/email-templates.php';

if (!function_exists('send_email')) {
    function send_email($recipient_email, $subject, $body)
    {
        $mail = new PHPMailer(true);

        try {
            // SMTP Settings
            $mail->isSMTP();
            $mail->Host       = env('SMTP_HOST');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('SMTP_USER');
            $mail->Password   = env('SMTP_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('SMTP_PORT', 587);

            // A slow or unreachable mail host must not hold a web request
            // open for PHPMailer's 300-second default.
            $mail->Timeout    = 15;

            // Email setup
            $mail->setFrom(env('SMTP_FROM'), env('SMTP_FROM_NAME'));
            $mail->addAddress($recipient_email);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            // Send email
            $mail->send();

            return [
                'status' => 'Success',
                'message' => 'Email sent successfully'
            ];
        } catch (Exception $e) {
            // The old version returned the literal string '{$mail->ErrorInfo}'
            // -- single quotes, so it never interpolated and every failure
            // reported the same useless text. Log the real reason instead.
            error_log('[mail] send to ' . $recipient_email . ' failed: ' . $mail->ErrorInfo);

            return [
                'status' => 'Failed',
                'message' => 'Email could not be sent'
            ];
        }
    }
}
