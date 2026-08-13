<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../phpmailer/src/Exception.php';
require '../../../phpmailer/src/PHPMailer.php';
require '../../../phpmailer/src/SMTP.php';
include '../email-templates.php';


function send_email($recipient_email, $subject, $body){
    $mail = new PHPMailer(true);
    try {
        
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'mail.xgramm.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply@uvix-market.cc';
        $mail->Password   = 'Bazarin@tech1';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
    
        // Email setup
        $mail->setFrom('noreply@uvix-market.cc', 'Sanderson Farm');
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

