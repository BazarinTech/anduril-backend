<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';
include 'email-templates.php';


function send_email($recipient_email, $subject, $body){
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
        return [
            'status' => 'Failed',
            'message' => 'Email failed: {$mail->ErrorInfo}'
            ];
    }
}

