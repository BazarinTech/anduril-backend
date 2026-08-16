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

    // -- Object storage (Railway Bucket, or any S3-compatible endpoint) ---
    //
    // Product images used to be written to admin/uploads/ on the web host.
    // That does not survive a container platform: the filesystem is ephemeral,
    // so every deploy discards whatever was uploaded since the last one.
    //
    // Fill these in from the Bucket service's "Credentials" tab. All four are
    // required together -- a partly-filled bucket is treated as not configured,
    // so uploads keep working locally instead of half-failing.
    //
    // Leave them empty in development to keep writing to disk.
    'S3_ENDPOINT'          => '',   // e.g. https://bucket-production-xxxx.up.railway.app
    'S3_ACCESS_KEY_ID'     => '',
    'S3_SECRET_ACCESS_KEY' => '',
    'S3_BUCKET'            => '',   // the bucket name, e.g. roomy-wardrobe
    'S3_REGION'            => 'us-east-1',  // most S3-compatible hosts ignore this
    // Path-style (endpoint/bucket/key) rather than virtual-host style
    // (bucket.endpoint/key). Self-hosted endpoints rarely have the wildcard
    // DNS that virtual-host style needs, so this defaults on.
    'S3_PATH_STYLE'        => '1',
    'S3_PREFIX'            => 'products',

    // 'local' or 's3'. Empty picks s3 when the four keys above are set.
    'STORAGE_DRIVER'       => '',

    // How the app is given an image URL:
    //   public   straight at the bucket (needs public read; fastest)
    //   presign  signed, expiring URL minted per request (bucket stays private)
    //   proxy    through backend/mains/image.php (private bucket, stable URLs)
    // Empty picks 'public' when S3_PUBLIC_BASE_URL is set, 'presign' otherwise.
    'STORAGE_URL_MODE'     => '',
    'S3_PUBLIC_BASE_URL'   => '',   // e.g. https://bucket-production-xxxx.up.railway.app/roomy-wardrobe
    'S3_PRESIGN_TTL'       => 86400,
    'S3_TIMEOUT'           => 20,

    // -- Encryption -------------------------------------------------------
    // Used by Bazarin\Security\Cryptions.
    'CRYPT_KEY' => '',
];
