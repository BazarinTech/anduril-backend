<?php

function registration_template($username){
    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>Welcome to Our Platform, $username!</h2>
            <p style='font-size:16px; color:#555;'>
                Your account has been successfully created. We're excited to have you on board.
            </p>
            <p style='font-size:16px; color:#555;'>
                You can now log in and start using all the features available to you.
            </p>
            <p style='margin-top:30px; font-size:14px; color:#888;'>
                If you did not create this account, please contact support immediately.
            </p>
        </div>
    </body>
    </html>
    ";
}


function login_template($username){
    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>New Login Alert</h2>
            <p style='font-size:16px; color:#555;'>
                Hello <b>$username</b>, we noticed a new login to your account.
            </p>
            <p style='font-size:16px; color:#555;'>
                If this was you, no further action is required.
            </p>
            <p style='font-size:16px; color:#555;'>
                If you did NOT log in, please reset your password immediately or contact support.
            </p>
            <p style='margin-top:30px; font-size:14px; color:#888;'>
                This is an automated message — do not reply.
            </p>
        </div>
    </body>
    </html>
    ";
}

function pending_withdrawal_template($username, $amount, $fee){
    // Format amounts with 2 decimals and thousands separator
    $amountFormatted = number_format($amount, 2, '.', ',');
    $feeFormatted = number_format($fee, 2, '.', ',');
    $totalFormatted = number_format($amount - $fee, 2, '.', ',');

    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>Withdrawal Request Received</h2>
            <p style='font-size:16px; color:#555;'>
                Hello <b>$username</b>, your withdrawal request has been submitted and is now pending review.
            </p>
            <div style='background:#f0f0f0; padding:15px; border-radius:8px; margin-top:20px;'>
                <p style='font-size:16px;'><b>Amount:</b> Kes $amountFormatted</p>
                <p style='font-size:16px;'><b>Fee:</b> Kes $feeFormatted</p>
                <p style='font-size:16px;'><b>Amount To Recieve:</b> Kes $totalFormatted</p>
            </div>
            <p style='font-size:16px; color:#555; margin-top:20px;'>
                Our team will review your request shortly and notify you once it is processed.
            </p>
            <p style='margin-top:30px; font-size:14px; color:#888;'>
                If you did not make this request, please contact support immediately.
            </p>
        </div>
    </body>
    </html>
    ";
}


function deposit_template($username, $amount, $method){
    // Format amount
    $amountFormatted = number_format($amount, 2, '.', ',');

    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>Deposit Successful</h2>
            <p style='font-size:16px; color:#555;'>
                Hello <b>$username</b>, your deposit has been confirmed.
            </p>
            <div style='background:#f0f0f0; padding:15px; border-radius:8px; margin-top:20px;'>
                <p style='font-size:16px;'><b>Amount:</b> Kes $amountFormatted</p>
                <p style='font-size:16px;'><b>Method:</b> $method</p>
            </div>
            <p style='font-size:16px; color:#555; margin-top:20px;'>
                The funds have been added to your account. You can now begin using them immediately.
            </p>
            <p style='margin-top:30px; font-size:14px; color:#888;'>
                Thank you for using our platform.
            </p>
        </div>
    </body>
    </html>
    ";
}

function success_withdrawal_template($username, $amount, $trackingID){
    // Format amount with 2 decimals and thousand separator
    $amountFormatted = number_format($amount, 2, '.', ',');

    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>Withdrawal Successful</h2>

            <p style='font-size:16px; color:#555;'>
                Hello <b>$username</b>, your withdrawal request has been successfully processed.
            </p>

            <div style='background:#f0f0f0; padding:15px; border-radius:8px; margin-top:20px;'>
                <p style='font-size:16px;'><b>Amount:</b> Kes $amountFormatted</p>
                <p style='font-size:16px;'><b>Transaction ID:</b> $trackingID</p>
            </div>

            <p style='font-size:16px; color:#555; margin-top:20px;'>
                The funds have been sent to your account. You can use the tracking ID to follow up with support if needed.
            </p>

            <p style='margin-top:30px; font-size:14px; color:#888;'>
                Thank you for using our platform.
            </p>
        </div>
    </body>
    </html>
    ";
}

function declined_withdrawal_template($username, $amount, $reason){
    // Format amount with 2 decimals and thousand separator
    $amountFormatted = number_format($amount, 2, '.', ',');

    return "
    <html>
    <body style='font-family: Arial, sans-serif; background:#f7f7f7; padding:20px;'>
        <div style='max-width:600px; margin:auto; background:#ffffff; padding:20px; border-radius:8px;'>
            <h2 style='color:#333;'>Withdrawal Declined</h2>

            <p style='font-size:16px; color:#555;'>
                Hello <b>$username</b>, we regret to inform you that your recent withdrawal request could not be processed.
            </p>

            <div style='background:#f0f0f0; padding:15px; border-radius:8px; margin-top:20px;'>
                <p style='font-size:16px;'><b>Amount:</b> Kes $amountFormatted</p>
                <p style='font-size:16px;'><b>Reason:</b> $reason</p>
            </div>

            <p style='font-size:16px; color:#555; margin-top:20px;'>
                Please review the reason provided and try again or contact our support team for assistance.
            </p>

            <p style='margin-top:30px; font-size:14px; color:#888;'>
                This is an automated message — do not reply directly to this email.
            </p>
        </div>
    </body>
    </html>
    ";
}


