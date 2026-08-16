<?php
/**
 * ENVIRONMENT TEMPLATE
 * ====================
 * Copy this file to `env.php` and fill in the real values:
 *
 *     cp config/env.example.php config/env.php
 *
 * `config/env.php` is gitignored and must never be committed.
 *
 * Any key below can also be supplied as a real environment variable of the
 * same name (cPanel > Setup Node/PHP App > Environment Variables, or the
 * server's SetEnv). A real environment variable always wins over this file,
 * so production can keep secrets out of the filesystem entirely.
 *
 * See bootstrap/bootstrap.php for the lookup order.
 */

return [
    // -- Application ------------------------------------------------------
    'APP_ENV'   => 'production',      // 'production' | 'development'
    'APP_DEBUG' => false,             // never true in production
    // Pins PHP and the MySQL session to one zone so date arithmetic agrees.
    'APP_TIMEZONE' => 'Africa/Nairobi',
    'APP_URL'   => 'https://example.com',

    // Origin allowed to call the JSON API. '*' is permitted only while the
    // frontend origin is still unsettled; Phase 1 pins this to a real host.
    'CORS_ORIGIN' => '*',

    // -- Database ---------------------------------------------------------
    'DB_HOST' => 'localhost',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASS' => '',

    // -- JWT --------------------------------------------------------------
    // Must match JWT_SECRET in the frontend (anduril-market) .env exactly.
    // Generate with: php -r 'echo bin2hex(random_bytes(32));'
    'JWT_SECRET' => '',
    'JWT_ALGO'   => 'HS256',

    // -- Palpluss (M-Pesa STK push + B2C payouts) -------------------------
    'PALPLUSS_KEY'           => '',
    'PALPLUSS_CHANNEL_ID'    => '',
    'PALPLUSS_CREDENTIAL_ID' => '',
    // Deposit STK push (returns a transactionId UUID, accepts Idempotency-Key)
    'PALPLUSS_TOPUP_URL'     => 'https://api.palpluss.com/v1/wallets/b2c/topups',
    'PALPLUSS_B2C_URL'       => 'https://api.palpluss.com/v1/b2c/payouts',

    // Float balances shown on the admin dashboard.
    'PALPLUSS_B2C_BALANCE_URL'     => 'https://api.palpluss.com/v1/wallets/b2c/balance',
    'PALPLUSS_SERVICE_BALANCE_URL' => 'https://api.palpluss.com/v1/wallets/service/balance',

    // Topping up the service wallet from admin/service-wallet.php.
    'PALPLUSS_SERVICE_TOPUP_URL'   => 'https://api.palpluss.com/v1/wallets/service/topups',

    // Base URL the payment provider posts callbacks back to.
    'CALLBACK_BASE_URL' => 'https://example.com/backend/mains/callbacks',

    // Shared secret appended to each callback URL and required back from the
    // provider. Without it the callbacks refuse every request.
    // Generate with: php -r 'echo bin2hex(random_bytes(24));'
    'CALLBACK_TOKEN' => '',

    // Optional comma-separated allowlist of provider egress IPs. Empty skips
    // the check; accepted callbacks log their source IP so you can fill it in.
    'CALLBACK_IPS' => '',

    // -- SMTP (PHPMailer) -------------------------------------------------
    'SMTP_HOST'      => '',
    'SMTP_PORT'      => 587,
    'SMTP_USER'      => '',
    'SMTP_PASS'      => '',
    'SMTP_FROM'      => '',
    'SMTP_FROM_NAME' => '',

    // -- SMS (HostPinnacle) -----------------------------------------------
    // Used for phone verification, password reset, and withdrawal PIN reset.
    //
    // SMS_API_KEY is what actually authenticates -- it goes out as the
    // `apikey` header. SMS_PASSWORD is posted in the body and the gateway
    // accepts the request regardless of what it contains, so a placeholder
    // there does not mean SMS is disabled. Verified against the live
    // endpoint: messages send with SMS_PASSWORD set to 'xxxxx'.
    //
    // The gateway also answers `"status":"success"` for numbers that cannot
    // receive anything, so a Success response is not proof of delivery.
    'SMS_URL'       => 'https://smsportal.hostpinnacle.co.ke/SMSApi/send',
    'SMS_USERID'    => '',
    'SMS_PASSWORD'  => '',
    'SMS_API_KEY'   => '',
    'SMS_SENDER_ID' => '',

    // -- Encryption -------------------------------------------------------
    // Used by Bazarin\Security\Cryptions.
    'CRYPT_KEY' => '',
];
